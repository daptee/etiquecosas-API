# Líneas de corte en el preview del PDF

Con `cut_lines=1`, el preview del diseño descarga un archivo **`.ai` para Illustrator** con 2 capas: el arte y, encima, las líneas de corte de las etiquetas. Sirve para mandar a la imprenta o al plotter.

## Endpoint

```
GET /product-pdf-designs/{id}/preview?cut_lines=1
```

Se suma a los parámetros que ya acepta (`firstName`, `lastName`, `fecha`, `numeroPedido`, `icon`, `variantId`, …).

| `cut_lines` | Resultado |
|---|---|
| ausente o `0` | Igual que hoy: un PDF sin líneas de corte. |
| `1` | Descarga un `.ai` para Illustrator con 2 capas. Ver [Descarga para Illustrator](#descarga-para-illustrator-ai). |
| `1` + `format=pdf` | Un `application/pdf` con 2 capas (OCG) y el arte vectorial. Ver [Capas del PDF](#capas-del-pdf-cut_lines1formatpdf). |

`?format=layout` ignora `cut_lines`.

El botón "Descargar" normal **no cambia**: sigue sin mandar `cut_lines`.

## Capas del PDF (`cut_lines=1&format=pdf`)

| Capa | Contenido |
|---|---|
| **Líneas de corte** (arriba) | Solo trazos vectoriales, sin relleno y con línea continua, en un spot color llamado `CutContour`. |
| **Arte** (abajo) | Exactamente lo mismo que el preview sin `cut_lines`, en vectores. |

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

## Abrir el archivo

- **Acrobat:** panel "Capas" con "Líneas de corte" y "Arte".
- **Inkscape y Affinity:** importan las capas.
- **Illustrator:** no respeta las capas de un PDF que no generó él y abre todo en una sola capa. Para Illustrator hay que usar la descarga `.ai` de la sección siguiente.

## Descarga para Illustrator (`.ai`)

```
GET /product-pdf-designs/{id}/preview?cut_lines=1
```

Acepta los mismos parámetros que el preview (`firstName`, `lastName`, …).

Devuelve un archivo `.ai` como descarga, con `Content-Disposition: attachment` y el nombre del diseño. Illustrator lo abre directamente con 2 capas reales:

| Capa | Contenido |
|---|---|
| **Lineas de corte** (arriba) | Trazos vectoriales en el color directo `CutContour`, con sobreimpresión de trazo activada. El nombre va sin tilde a propósito. |
| **Arte** (abajo) | La hoja completa como **imagen CMYK de 300 dpi**. No es editable: los textos e íconos no se pueden modificar. |

- Si el diseño tiene varias hojas, todas van en el mismo `.ai`, una al lado de la otra, separadas 2 cm.
- **El archivo es pesado:** una hoja de 18,5 × 29 cm a 300 dpi pesa unos 60 MB. Conviene mostrar un "Generando…" mientras se descarga, porque tarda varios segundos.

```js
const res = await api.get(`/product-pdf-designs/${id}/preview`, {
  params: { firstName, lastName, cut_lines: 1 },
  responseType: 'blob',
});
const url = URL.createObjectURL(res.data);
const a = document.createElement('a');
a.href = url;
a.download = `${nombreDiseno}.ai`;
a.click();
URL.revokeObjectURL(url);
```

Si el servidor no tiene Ghostscript instalado, responde un 500 con `{ message, error }` y el error lo explica.

## Illustrator: script para separar las capas (alternativa)

Es la opción si hace falta el arte **vectorial y editable** en Illustrator. Se descarga el PDF con `cut_lines=1&format=pdf` y se separan las capas con este script.

El backend sirve un script de Illustrator como archivo estático:

```
GET {BACKEND_URL}/illustrator/separar-lineas-de-corte.jsx
```

Es un archivo público, sin auth y sin `/api`. Sugerencia: poner un link "Script para Illustrator" al lado del botón de descarga con líneas de corte.

Pasos para el diseñador:

1. Descargar el PDF con líneas de corte (`cut_lines=1&format=pdf`) y abrirlo en Illustrator. Si tiene varias páginas, Illustrator pregunta cuál abrir y abre cada una como documento aparte.
2. Ir a **Archivo > Secuencias de comandos > Otra secuencia de comandos…** y elegir `separar-lineas-de-corte.jsx`.
3. El script deja 2 capas en cada documento abierto:
   - **Líneas de corte** (arriba): todos los trazos con el color directo `CutContour`.
   - **Arte** (abajo): todo lo demás.

   Al terminar muestra cuántas líneas movió en cada documento.

Todo sigue siendo vectorial. El script solo mueve elementos de capa y no modifica nada.

Si muestra 0 líneas, esa página no tiene `cut_lines.enabled: true` o el PDF no se descargó con `cut_lines=1&format=pdf`.

Para no buscarlo cada vez, se puede copiar el `.jsx` en la carpeta `Presets/<idioma>/Scripts` de Illustrator. Así aparece directamente en **Archivo > Secuencias de comandos**.
