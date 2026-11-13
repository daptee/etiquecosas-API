# Líneas de corte en el preview del PDF

Con `cut_lines=1`, el preview del diseño devuelve un PDF que tiene **solo las líneas de corte** de las etiquetas, sin el arte. Sirve para mandar al plotter de corte o para superponerlo al PDF del arte.

## Endpoint

```
GET /product-pdf-designs/{id}/preview?cut_lines=1
```

Se suma a los parámetros que ya acepta (`firstName`, `lastName`, `fecha`, `numeroPedido`, `icon`, `variantId`, …). Los nombres no cambian nada en este PDF porque no hay texto, pero se aceptan igual.

| `cut_lines` | Resultado |
|---|---|
| ausente o `0` | Igual que hoy: el PDF del arte, sin líneas de corte. |
| `1` | Un `application/pdf` solo con las líneas de corte. |

`?format=layout` ignora `cut_lines`.

El botón "Descargar" normal **no cambia**: sigue sin mandar `cut_lines`.

## Qué tiene el PDF

- Una página por hoja del diseño, en el mismo orden y con el mismo tamaño. Se puede superponer 1 a 1 con el PDF del arte.
- Solo trazos vectoriales: sin relleno, línea continua y en un color directo (spot) llamado `CutContour`, que es el que leen los plotters. No hay texto, íconos ni fondos.
- El grosor sale de `cut_lines.width_cm` de la página. Si falta, se usa `0.03`.
- El color sale de `cut_lines.color` de la página, `hex` o `cmyk`. Si falta, se usa `#FF00FF`.
- Una hoja sin `cut_lines.enabled: true` sale en blanco. Se mantiene igual, para que las páginas sigan coincidiendo con el diseño.
- Pesa unos pocos KB y se genera al instante: no pasa por el render del arte.

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

## Qué cambiar en el front

El front ya descarga esto bien. Llama al preview con `cut_lines=1` y lo guarda como PDF (`FileHelper.downloadPDF`, con nombre `<diseño> - corte.pdf`). Solo conviene actualizar el texto del botón, que hoy dice que el PDF trae 2 capas:

- `components/productPdfDesigns/editor/EditorTopBar.vue`: `subtitle="PDF con 2 capas: arte y corte"` → `subtitle="PDF solo con las líneas de corte"`.
- `components/productPdfDesigns/editor/PageConfig.vue`: el texto de ayuda dice que las líneas "se exportan en su propia capa". Ahora se exportan en su propio PDF.
- `services/productPdfDesigns/productPdfDesigns.service.ts`: el comentario de `cutLines` dice que devuelve un PDF de 2 capas.

Los errores son los mismos que en el preview actual: un 500 con `{ message, error }` en JSON.
