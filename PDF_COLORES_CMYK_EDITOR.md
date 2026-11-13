# Colores CMYK: por qué el editor los muestra distinto al PDF final

Diagnóstico del back para el front. Síntoma reportado: en el editor de diseños (`productPdfDesigns`)
los colores de las etiquetas/íconos/textos se ven **distintos** a como quedan en el PDF generado o
descargado — el PDF final está bien, el que está mal es lo que se ve en pantalla mientras se edita.

No es un bug de "falta convertir" (la conversión ya existe y se aplica en todos lados) — es que la
fórmula usada para esa conversión no reproduce cómo un visor de PDF interpreta CMYK. Abajo está el
circuito completo, con archivo y línea de cada parte, y las opciones para cerrar (o reducir) la
diferencia.

---

## Resumen

| | Qué pasa | Dónde |
|---|---|---|
| Dato guardado | `{ mode: "cmyk", value: "c,m,y,k" }` (0–1 por canal), la fuente de verdad para imprimir | `product_pdf_designs.data` |
| PDF final | El back escribe el CMYK **tal cual** en el PDF (operador `cmyk()` de CSS que dompdf entiende de forma nativa como `/DeviceCMYK`) | `RENDER.blade.php` |
| Editor (canvas) | Un navegador **no** entiende `cmyk()` — el front convierte ese mismo CMYK a un hex aproximado, con una fórmula matemática simple, para poder pintarlo con `background`/`border`/`color` normales | `ColorsHelper.ts` |
| Resultado | La fórmula del front es una aproximación razonable pero **no es la misma cuenta** que hace el visor de PDF al mostrar `/DeviceCMYK` real (que normalmente aplica algún perfil de color) → mismo CMYK, dos colores distintos en pantalla | — |

---

## El circuito, paso a paso

### 1. Se guarda CMYK crudo, no un hex

El modelo de `fill`/`color`/`border.color` acepta `{ mode: "hex" | "cmyk", value }` (ver
[PDF_FONDO_ELEMENTOS.md](PDF_FONDO_ELEMENTOS.md)). Cuando es `cmyk`, `value` es un string
`"c,m,y,k"` con cada canal en **0–1** (no 0–100), ej. `"0.1,0.39,0,0.05"`.

Al importar desde un PDF legacy, si el rectángulo original se pintó con el operador CMYK (`k` del
content stream, no `rg`), el back **guarda el CMYK tal cual** en vez de convertirlo a hex en ese
momento — a propósito:

```php
// app/Services/PdfDesignImportService.php:552-559
// Si el PDF original pintó este rect con el operador CMYK `k` (no RGB
// `rg`), se guarda el CMYK tal cual en vez de convertirlo a hex: un
// visor de PDF con manejo de color le aplica a `k` un perfil que lo
// ve bien más apagado que la conversión matemática simple que usamos
// para el hex (ver PdfContentStreamParser::cmykToHex) — para que el
// fondo importado se vea IDÉNTICO al original (no solo "matemáticamente
// equivalente") hay que reproducir el mismo operador `k`, no un RGB.
```

Ese comentario ya adelanta el problema: **la propia build del back sabe que la conversión
matemática simple no es fiel a cómo se ve un CMYK real en un visor.**

### 2. El PDF final: CMYK literal, sin pasar por ninguna fórmula

`RENDER.blade.php` (la vista que arma tanto el PDF real como la que alimentaba el preview viejo del
editor) escribe el valor CMYK **directo** en el CSS que dompdf interpreta:

```php
// resources/views/tematica/editor/RENDER.blade.php
// línea 89  (fondo de página)
// línea 120 (fill de etiqueta/ícono/texto/forma)
// línea 188 (border)
// línea 335 (color de texto)
($color['mode'] ?? 'hex') === 'cmyk' ? 'cmyk(' . $color['value'] . ')' : $color['value']
```

dompdf entiende `cmyk(c,m,y,k)` de forma nativa y lo escribe en el PDF como color `/DeviceCMYK` —
no hay ninguna conversión a RGB en este camino. El color que se ve al abrir/descargar el PDF es el
que decide el **visor de PDF** (o la imprenta) al interpretar ese `/DeviceCMYK`, típicamente con
algún perfil de color — por eso, para colores oscuros o muy saturados, suele verse **más apagado**
que la cuenta matemática ingenua.

### 3. El editor: no puede pintar CMYK, convierte a hex con una fórmula propia

Un navegador no tiene forma de pintar `cmyk()` en CSS (no es una función CSS válida). El editor
resuelve esto convirtiendo a hex **al vuelo**, cada vez que arma el estilo visual de un elemento:

```ts
// utils/ColorsHelper.ts:59-74
static cmykToHex({ c, m, y, k }) {
	const cn = c / 100, mn = m / 100, yn = y / 100, kn = k / 100;
	const r = Math.round(255 * (1 - cn) * (1 - kn));
	const g = Math.round(255 * (1 - mn) * (1 - kn));
	const b = Math.round(255 * (1 - yn) * (1 - kn));
	return '#' + [r, g, b].map(v => v.toString(16).padStart(2, '0')).join('').toUpperCase();
}

// utils/ColorsHelper.ts:76-84 — puente del formato "0-1 string" que usa el modelo
static cmykStringToHex(value: string): string {
	const [c, m, y, k] = String(value).split(',').map(Number);
	return ColorHelper.cmykToHex({ c: c * 100, m: m * 100, y: y * 100, k: k * 100 });
}
```

Esta es la fórmula **multiplicativa** estándar de libro (`R = 255·(1-C)·(1-K)`, etc.) — la misma,
de hecho, que usa el propio back en `PdfContentStreamParser::cmykToHex()` (línea 560), pero ahí
**solo** para un uso interno sin relación con el render real (ver más abajo). No está mal escrita;
el problema es que ningún visor de PDF "normal" (Acrobat, Chrome, Preview de macOS, una RIP de
imprenta) usa exactamente esta cuenta para mostrar `/DeviceCMYK` — todos aplican, en mayor o menor
medida, algún tipo de perfil/gestión de color, y el resultado visual tiende a ser más apagado/oscuro
que esta aproximación lineal, sobre todo con K alto o con mezclas de 3-4 canales.

Esta conversión se usa de forma consistente en **todos** los lugares donde el editor pinta un color
CMYK en pantalla:

| Archivo | Línea | Qué pinta |
|---|---|---|
| `utils/ColorsHelper.ts` | 59, 79 | la fórmula base (`cmykToHex`/`cmykStringToHex`) |
| `utils/PdfDesignHelper.ts` | 227 (`elementFillStyle`) | fondo (`fill`) de cualquier elemento en el canvas |
| `components/productPdfDesigns/shared/PdfDesignElementGlyph.vue` | 120, 155 | color de borde y, vía `elementFillStyle`, el fondo del elemento |
| `components/productPdfDesigns/shared/PdfColorPicker.vue` | 196, 254 | swatch de color y preview dentro del selector |
| `components/productPdfDesigns/shared/PdfFillPicker.vue` | 143 (vía `elementFillStyle`) | preview del selector de fondo |
| `components/productPdfDesigns/shared/PdfTextInlineEditor.vue` | 55 | color de texto mientras se edita inline |
| `utils/PdfCutLinesHelper.ts` | 33 | color de las líneas de corte en el canvas |

No hay ningún lugar donde se "olvide" convertir — la cobertura está completa. El desvío es 100%
de fórmula, no de que falte aplicarla en algún componente.

---

## Por qué esto no tiene un "arreglo perfecto" solo en el front

Reproducir en una pantalla sRGB exactamente cómo se va a ver un color CMYK impreso (o en un visor
de PDF con su propio perfil) es, en general, un problema sin una respuesta única: depende del
perfil ICC que use ese visor/esa imprenta en particular, y no hay forma de saberlo con certeza
desde el navegador. Por eso conviene separar esto en dos partes:

- **Reducir el desvío** (una fórmula de aproximación mejor que la actual) — barato, pero sigue
  siendo una aproximación.
- **Eliminar el desvío** (mostrar en el editor el mismo render que produce el PDF real) — más
  trabajo, pero exacto.

### Opción A — más preciso: preview renderizado por el back

En vez de que el front calcule el color en JS, pedirle al back un preview ya rasterizado (un PNG
chico del elemento, o de la etiqueta completa) usando el **mismo** pipeline que genera el PDF real
(`RENDER.blade.php` + dompdf). Esto garantiza paridad exacta porque es literalmente el mismo
renderer, no una aproximación aparte.

Esto ya es parecido a algo que el back hace para otro caso (recorte de imagen en forma `custom`,
ver [PDF_FONDO_ELEMENTOS.md](PDF_FONDO_ELEMENTOS.md) → `PdfFillImage.php`): genera un PNG cacheado
server-side en vez de intentar reproducir el recorte en CSS. Es la solución más robusta, pero
implica un endpoint nuevo y probablemente debounce/cache en el front para no pedir un render por
cada tecla tipeada mientras se ajusta un color.

### Opción B — más barato: mejorar la fórmula de aproximación

Si renderizar server-side no es viable ahora, vale la pena probar una fórmula distinta a la
multiplicativa actual contra algunos PDFs reales ya generados (abrir el PDF al lado del editor y
comparar a ojo). Una alternativa común — el modelo "aditivo"/GCR, que muchas herramientas usan
porque se parece más a cómo trabajan los RIPs de imprenta — es:

```ts
const r = Math.round(255 * (1 - Math.min(1, cn + kn)));
const g = Math.round(255 * (1 - Math.min(1, mn + kn)));
const b = Math.round(255 * (1 - Math.min(1, yn + kn)));
```

Esto suele dar colores más oscuros/apagados que la multiplicativa, en la misma dirección en la que
el comentario de `PdfDesignImportService.php:555` ya describe el real como "más apagado". No
garantiza coincidir exactamente (sigue sin perfil ICC real), pero es un cambio de una línea para
probar si se acerca más. **Conviene validarlo a ojo contra el visor de PDF que de verdad use el
equipo de producción/imprenta antes de darlo por bueno** — distintos visores (Chrome, Acrobat,
Preview) pueden mostrar el mismo `/DeviceCMYK` con matices distintos entre sí.

### Opción C — mínimo: avisar que es una aproximación

Si ninguna de las dos de arriba entra en el alcance actual, al menos dejar explícito en la UI
(un tooltip/hint cerca del picker en modo CMYK) que el color en pantalla es aproximado y que el
color real es el del PDF exportado — hoy el editor no distingue entre "esto es exacto" (modo hex)
y "esto es una aproximación" (modo cmyk), y el usuario no tiene forma de saberlo sin comparar los
dos archivos.

---

## Para decidir

Antes de elegir A, B o C conviene confirmar con el equipo que controla la calidad de impresión:
**¿con qué abren/revisan el PDF final en la práctica?** (Chrome, Acrobat, directo en una RIP de
imprenta). Eso determina si vale la pena afinar una fórmula aproximada (Opción B) o si directamente
conviene ir a un preview renderizado por el back (Opción A) porque ningún cálculo en JS va a
coincidir con el perfil real de esa herramienta.
