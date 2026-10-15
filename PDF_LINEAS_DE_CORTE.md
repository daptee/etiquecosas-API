# Líneas de corte en el preview del PDF

El preview del diseño puede devolver ahora un PDF de **2 capas**: el arte de siempre y, encima, las líneas de corte de las etiquetas. Sirve para mandar a la imprenta o al plotter.

## Endpoint

```
GET /product-pdf-designs/{id}/preview?cut_lines=1
```

Se suma a los parámetros que ya acepta (`firstName`, `lastName`, `fecha`, `numeroPedido`, `icon`, `variantId`, …).

| `cut_lines` | Resultado |
|---|---|
| ausente o `0` | Igual que hoy: un PDF sin líneas de corte. |
| `1` | Un único `application/pdf` con 2 capas (OCG). |

`?format=layout` ignora `cut_lines`.

El botón "Descargar" normal **no cambia**: sigue sin mandar `cut_lines`.

## Capas del PDF

| Capa | Contenido |
|---|---|
| **Líneas de corte** (arriba) | Solo trazos vectoriales, sin relleno y con línea continua, en un spot color llamado `CutContour`. |
| **Arte** (abajo) | Exactamente lo mismo que el preview sin `cut_lines`. |

- Las 2 capas existen siempre y vienen visibles. Si ninguna página tiene líneas, la capa de corte queda vacía.
- El grosor sale de `cut_lines.width_cm` de la página. Si falta, se usa `0.03`.
- El color sale de `cut_lines.color` de la página, `hex` o `cmyk`. Si falta, se usa `#FF00FF`.

## Datos que usa (ya los guardan, no hay cambios en el guardado)

**Por página** (`data.pages[].cut_lines`): si no existe, o `enabled` no es `true`, esa página no dibuja ninguna línea.

**Por etiqueta**: solo los elementos `type: "background"`. Los `text`, `icon` y `shape` no generan línea.

| Campo | Qué hace |
|---|---|
| `cut_line: "hidden"` | Esa etiqueta no lleva línea. Si falta o es `"auto"`, se dibuja. |
| `cut_vertical_cm` | Desplaza los bordes de arriba y abajo. |
| `cut_horizontal_cm` | Desplaza los bordes izquierdo y derecho. |

En `cut_vertical_cm` y `cut_horizontal_cm`, positivo lleva la línea hacia afuera y negativo hacia adentro. Si faltan valen `0`.

La línea respeta la rotación (`rotation_deg`), la forma de la etiqueta (`rect`, `circle` o `custom`) y el radio de esquina, con las mismas fórmulas que se acordaron. El borde visual y el padding no afectan la línea.

En una forma `custom` cuyo `outline_svg` no se puede leer (por ejemplo, porque tiene curvas), la línea sale como rectángulo.

## Páginas prestadas (`source`)

Las páginas con `source: { designId, pageId }` toman de la página origen sus etiquetas, `cut_lines`, `layout_groups` y hoja.

> ⚠️ **Cambio de comportamiento:** esto aplica a **todos** los PDF, no solo con `cut_lines=1`. Antes esas páginas salían en blanco en el PDF (preview normal y ventas). Ahora salen con el contenido de la página origen.

- Si la página origen ya no existe, la página sale vacía.
- Se resuelve un solo nivel. Una página prestada de otra página prestada sale vacía.

## Ejemplo de uso desde el front

```js
const res = await api.get(`/product-pdf-designs/${id}/preview`, {
  params: { firstName, lastName, cut_lines: 1 },
  responseType: 'blob',
});
const url = URL.createObjectURL(res.data); // abrir o descargar igual que el preview normal
```

Los errores son los mismos que en el preview actual: un 500 con `{ message, error }` en JSON.

## Abrir el archivo

- **Acrobat:** panel "Capas" con "Líneas de corte" y "Arte".
- **Inkscape y Affinity:** importan las capas.
- **Illustrator:** al abrir un PDF que no generó él suele juntar todo en una capa. Aun así las líneas quedan como trazos separados en el spot `CutContour` y se pueden seleccionar por color.
