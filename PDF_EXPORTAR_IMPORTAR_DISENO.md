# Exportar / importar un diseño PDF entre entornos (dev → prod)

Para pasar un diseño del editor de un entorno a otro sin rehacerlo a mano: se **exporta** en dev
(se descarga un `.json`) y se **importa** ese archivo en prod.

Los dos endpoints están dentro de `jwt.auth`, como el resto de `/product-pdf-designs`.

---

## 1. Exportar

```
GET /api/product-pdf-designs/{id}/export
```

Devuelve el archivo para descargar (`Content-Disposition: attachment; filename="diseno-pdf-{id}-{nombre}.json"`).
Es un JSON autocontenido:

| Campo | Qué es |
|---|---|
| `format` / `version` | `"etiquecosas.pdf-design"` / `1`. El import rechaza cualquier otra cosa. |
| `source` | `app_url` y `design_id` del entorno de origen (solo informativo). |
| `design` | Nombre, `data`, `label_shape_id` y la configuración (`fill_mode`, `accumulation_*`, `pages_output_mode`). |
| `refs` | Lo que el diseño usa por id: tipografías, íconos, formas de etiqueta y atributos, con sus nombres. |
| `files` | Los archivos que usa el diseño, en base64: imágenes subidas (fondo de hoja, fondo de elemento, ícono propio), archivos de fuente y SVG de íconos. |
| `warnings` | Avisos al exportar (ver abajo). |

Las **páginas prestadas** (`source: {designId, pageId}`) se exportan como copia, con el contenido de la página
origen: en el otro entorno ese diseño origen no existe o es otro. Viene un aviso en `warnings`.

Para el front alcanza con un botón "Exportar" que descargue la respuesta como archivo.

---

## 2. Importar

```
POST /api/product-pdf-designs/import
Content-Type: multipart/form-data
```

| Campo | Tipo | |
|---|---|---|
| `file` | archivo `.json` (hasta 100 MB) | El archivo que bajó el export. |
| `bundle` | objeto | Alternativa a `file`: el mismo JSON en el body (`application/json`). Uno de los dos. |
| `name` | string, opcional | Nombre del diseño. Si no viene, el del diseño exportado. |
| `targetDesignId` | int, opcional | Si viene, **reemplaza el contenido de ese diseño** en vez de crear uno nuevo. |

### Qué hace

- **Sin `targetDesignId`**: crea un diseño nuevo **sin publicar** y **sin productos vinculados** (los productos
  no viajan; se vinculan a mano en prod, igual que hoy).
- **Con `targetDesignId`**: reemplaza `data`, forma de etiqueta y configuración de ese diseño. **Conserva** sus
  productos vinculados y su estado de publicación. Sirve para volver a pasar cambios de dev a un diseño que ya
  está andando en prod. Ojo: si ese diseño está publicado, el cambio se usa desde ese momento en los pedidos.

Los ids de dev no sirven en prod, así que cada referencia se busca **por nombre** en prod:

| Referencia | Si existe en prod (mismo nombre) | Si no existe |
|---|---|---|
| Tipografía (`font_id`) | Se usa la de prod. | Se crea, con sus archivos de fuente. |
| Ícono (`icon_id`) | Se usa el de prod. | Se crea, con su SVG. |
| Forma de etiqueta (`label_shape_id`) | Se usa la de prod. | Se crea. |
| Atributo (`dynamic_attribute_id`, también en colores) | Se usa el de prod (mismo nombre y tipo). | **No se crea** (es de los productos): queda en `null` y se avisa en `report.missing`. |

Las imágenes propias del diseño (`pdf-element-images/…`, `pdf-backgrounds/…`) se copian a la carpeta del diseño
nuevo. Si algo falla, no queda nada a medias: ni el diseño ni los archivos.

### Respuesta

```json
{
  "message": "Diseño importado",
  "data": {
    "design": { "...": "mismo formato que GET /product-pdf-designs/{id}" },
    "report": {
      "matched": ["Tipografía \"oswald\" (#11)", "Ícono \"Ancla\" (#1)"],
      "created": ["Forma de etiqueta \"Maxi Combinada\" (#19)"],
      "missing": ["Atributo \"Color banda 2\" no existe: los elementos que lo usaban quedan sin atributo"],
      "warnings": ["La página 2 tomaba el contenido del diseño #120: se exporta como copia, ya no queda vinculada"]
    }
  }
}
```

Conviene mostrar el `report` después de importar, sobre todo `missing` y `created`, para revisar el diseño antes
de publicarlo.

### Errores

- `422` con `errors.file`: el archivo no es una exportación válida o es de otra versión.
- `422`: algún archivo trae una ruta o extensión no permitida (solo imágenes y fuentes), o los `layout_groups`
  no validan.
- `500`: cualquier otro error. No se guarda nada.

---

## A tener en cuenta

- **El nombre es la clave.** Si en prod hay una tipografía, ícono o forma con el mismo nombre pero distinta, se
  usa la de prod. Si el nombre difiere (por ejemplo "Oswald" vs "oswald bold"), se crea una nueva.
- **Tamaño:** el archivo lleva fuentes e imágenes en base64. Si el import falla por tamaño, revisar
  `upload_max_filesize` / `post_max_size` de PHP en prod.
- El archivo se puede abrir y revisar: es JSON plano.
