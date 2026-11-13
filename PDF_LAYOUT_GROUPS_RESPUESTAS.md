# Respuestas del backend: generador de PDF y `layout_groups`

Respuestas a las preguntas del frontend sobre el generador de PDF, como base para implementar los grupos con distribución (`layout_groups`).

## Antes de empezar: tres datos que cambian supuestos del frontend

- **El backend no aplica `vertical_offset_cm`.** Solo lo limita a un rango y lo guarda. El render centra el texto con CSS a partir del texto ya armado. Si el frontend replica `vertical_offset_cm`, ya hoy no coincide con el PDF.
- **El backend no sustituye `{{fecha}}` ni `{{numero_pedido}}` como tokens dentro de `content`.** Esos dos datos solo llegan con `dynamic_field: "fecha" | "numero_pedido"`. Los tokens que sí se sustituyen son `{{customer_name}}`, `{{customer_first_name}}` y `{{customer_last_name}}`.
- **El backend no replica columnas × filas.** Ignora `sheet.columns` y `sheet.rows` y dibuja exactamente los elementos que trae `elements[]`. Cada copia de la etiqueta ya viene como elementos propios desde el frontend.

---

## Motor de generación

### 1. ¿Con qué se generan los PDF?

Con HTML a PDF usando **dompdf 3.1.2** (`barryvdh/laravel-dompdf`). Las hojas se agrupan por tamaño, se genera un PDF por grupo y después se unen con FPDI.

- Núcleo: `EtiquetaService::generarEtiquetasDesdePaginas` ([app/Services/EtiquetaService.php:359](app/Services/EtiquetaService.php#L359))
- Vista: [resources/views/tematica/editor/RENDER.blade.php](resources/views/tematica/editor/RENDER.blade.php)

### 2. ¿Cada elemento se posiciona de forma absoluta? ¿Sirve un contenedor flex?

Sí: cada elemento es un `div.editor-element` con `position:absolute` y `left`, `top`, `width` y `height` en cm ([RENDER.blade.php:101-108](resources/views/tematica/editor/RENDER.blade.php#L101-L108)).

Pero **dompdf no soporta flexbox ni `gap`**, así que el contenedor flex no es una opción. El layout se calcula en PHP antes del render: se reescriben `x_cm` y `y_cm` de los miembros, y la vista Blade sigue igual que hoy. Para el generador es lo más simple.

### 3. ¿Hay acceso a las métricas de las fuentes?

Sí. `$dompdf->getFontMetrics()->getTextWidth($text, $font, $size, $wordSpacing, $charSpacing)` es la **misma función** que usa dompdf para acomodar el texto (`vendor/dompdf/dompdf/src/FrameReflower/Text.php:143`), y ya recibe el letter-spacing. Si medimos con ella, el ancho coincide con el render.

La condición es tener la fuente registrada en esa instancia de dompdf antes de medir. También está php-font-lib, que ya se usa para leer métricas verticales en `obtenerRatioMetricasFuente`.

---

## Medición del texto

### 4. ¿Cómo se corta el texto en líneas?

Por caracteres, no por píxeles: `dividirEnRenglones` ([EtiquetaService.php:734](app/Services/EtiquetaService.php#L734)) recibe `max_lines` y `max_chars_per_line`, con valores por defecto 3 y 10.

- **Nombre completo** (`nombre_apellido` o `{{customer_name}}`): si el texto no supera `max_chars_per_line`, queda en una línea. Si lo supera, se parte en dos: nombre, y apellido entero. Son 2 líneas como máximo y el apellido no se vuelve a cortar.
- **Resto de los textos**: corte por palabra completa. Las partículas como "de la" o "van" se agrupan con la palabra siguiente.
- **Si el texto excede `max_lines`**: no se recorta ni se agrega "…". Todo lo que sobra se **pega en la última línea**, que puede quedar más larga que `max_chars_per_line`. La rama con "…" casi nunca se ejecuta; solo en el nombre completo con `max_lines = 1`, y en ese caso **se pierde el apellido**.
- **Una palabra más larga que `max_chars_per_line`** no se parte.

⚠️ **dompdf además corta por ancho.** El `<p>` tiene el ancho de la caja y un `white-space` normal. Si una línea ya cortada por caracteres tiene espacios y no entra en `width_cm`, dompdf la vuelve a partir. Las líneas reales pueden ser más que las del algoritmo por caracteres. Para los grupos hay que elegir:

- **(a)** Renderizar los textos agrupados con `white-space: nowrap`, para que líneas reales = líneas por caracteres. **Es lo que recomiendo para la fase 1.**
- **(b)** Simular también el corte por ancho con `getTextWidth`.

### 5. ¿Dónde se resuelven la altura del bloque y `vertical_offset_cm`?

Hoy el backend **no calcula** la altura: la resuelve dompdf al dibujar. El centrado está en [RENDER.blade.php:199-216](resources/views/tematica/editor/RENDER.blade.php#L199-L216) y funciona así:

- Para `middle` y `bottom`, la caja del texto se ubica en `top: 50%` o `top: 100%`, se corre con `translateY(-50%)` o `translateY(-100%)` y se le suman ajustes ópticos a ojo: ±0,20 × font_size.
- Para `top` hay un ajuste de 0,10 × font_size.
- `vertical_offset_cm` no se usa en ningún lado: [PdfDesignSanitizer](app/Services/PdfDesignSanitizer.php) solo lo limita a −50/50.

La altura efectiva **sí da ≈ líneas × font_size × line_height**. Pasa así:

1. dompdf multiplica el `line-height` sin unidad por `(hhea.ascent − hhea.descent) / unitsPerEm × 1.1`.
2. El backend divide antes por ese mismo factor (`obtenerRatioMetricasFuente`, [EtiquetaService.php:871](app/Services/EtiquetaService.php#L871)), así que se cancela.

Hay dos excepciones: cuando no se puede leer el archivo de fuente y cuando el texto no tiene fuente (Helvetica). En los dos casos el factor no se corrige y la altura se aleja un poco de la fórmula.

Ojo con `min_lines`: hoy **sí reserva espacio**. `formatearTextoElemento` completa con líneas `&nbsp;` hasta llegar a `min_lines`. Dentro de un grupo habría que no completar.

### 6. ¿Cómo se cargan las fuentes?

Se resuelven en `resolverElementoDesign` ([EtiquetaService.php:647-669](app/Services/EtiquetaService.php#L647-L669)):

- **Prioridad**: primero la tipografía del atributo de la variante (`dynamic_attribute_id`). Si no hay, la de `font_id`, y de esa **solo se usa el primer archivo** de `Typography->files`.
- **Carga**: un `@font-face` por cada elemento de texto, con `file://` y siempre `format('truetype')`. dompdf descarta el `@font-face` si el formato dice otra cosa.
- **Con `font_id` vacío**: se usa `font-family: 'sans-serif'`, que dompdf resuelve como **Helvetica** (fuente base del PDF). No es Arial: tienen métricas parecidas pero no iguales.
- **Si una fuente subida no carga**: dompdf usa la fuente por defecto, `default_font: 'serif'`, que es **Times-Roman**. También cae a Times-Bold si el peso pedido no coincide con el del `@font-face`.
- **Qué fuente falla hoy**: desde el código no puedo saberlo; hay que probar fuente por fuente.
- **A verificar en producción**: en tiempo de ejecución se cambia el caché de fuentes a `storage/fonts_cache`, mientras que la configuración usa `storage/fonts`. En mi entorno local `storage/fonts_cache` no existe.

### 7. ¿Cómo se aplican el letter-spacing y el interlineado?

- **Letter-spacing**: CSS `letter-spacing` en px, con `dpi: 96`, así que 1 px = 0,75 pt, igual que en el navegador. dompdf lo incluye al medir y al cortar líneas.
- **Interlineado**: con la corrección del punto 5 se comporta como el `line-height` del navegador.
- **Diferencias con el navegador**: los ajustes ópticos de `vertical_align`, la fuente Helvetica cuando no se elige ninguna, y el posible corte por ancho del punto 4.

---

## Orden de cálculo

### 8. ¿Cuándo se resuelven los textos y las reglas por longitud?

Todo se resuelve en `resolverElementoDesign`: texto final, líneas en HTML, font_size, line_height y letter_spacing según sus reglas, y la fuente. Se llama dentro del `array_map` de [EtiquetaService.php:425-433](app/Services/EtiquetaService.php#L425-L433), una vez por nombre.

El layout entra justo después y antes de `agruparPaginasPorTamano`, como un `aplicarLayoutGroups($page)`.

Detalle a tener en cuenta: ese `array_map` hoy **solo copia `sheet` y `elements`** de cada página. Hay que agregarle `layout_groups` o se pierde.

### 9. ¿Cómo se replica la hoja en columnas × filas?

No se replica (ver el comienzo). Cada etiqueta física ya es su propio `background` con sus propios elementos, así que el layout por etiqueta funciona solo: **el frontend tiene que emitir un `layout_group` por cada copia**, con su `container_element_id` y sus miembros.

Calcular una vez y repetir no hace falta: dentro de una hoja todas las copias tienen el mismo texto y el cálculo es barato.

---

## Datos y compatibilidad

### 10. ¿Dónde se valida y se guarda `data.pages[]`?

En `ProductPdfDesignController::rules()` y `sanitizeDesignData()` ([ProductPdfDesignController.php:484-517](app/Http/Controllers/ProductPdfDesignController.php#L484-L517)). La validación es mínima: exige `data.pages` y que `elements` sea un array. La sanitización solo toca `elements` y `sheet`, y **cualquier otra clave de la página se guarda tal cual** (el modelo castea `data` como array). Por eso `layout_groups` se guardaría hoy sin cambios y sin romper nada.

No hay un esquema formal que actualizar. Igual voy a agregar un `sanitizeLayoutGroups` en `PdfDesignSanitizer` que haga esto:

- solo acepta los valores conocidos de `direction` y `align`;
- limita `gap_cm` a un rango;
- limpia los ids;
- descarta los grupos sin contenedor o sin miembros.

### 11. ¿La vista previa y la generación real comparten código?

Sí, el mismo. `preview()` ([ProductPdfDesignController.php:404](app/Http/Controllers/ProductPdfDesignController.php#L404)) llama a `generarEtiquetasDesdeDesign`. La venta real pasa por `ProductPdfResolverService`, que llama a la misma función o a `generarEtiquetasDesdeDesignsMultiples`. Las dos terminan en `generarEtiquetasDesdePaginas`. Lo que se implemente se aplica a las dos.

---

## Alcance y esfuerzo

### 12. ¿Cuánto cambio implica cada fase?

**Fase 1 (grupos verticales)**: chica a media, unos 1–2 días con pruebas sobre PDFs reales. Incluye:

- un método nuevo `aplicarLayoutGroups`;
- pasar `layout_groups` a las páginas resueltas;
- no completar `min_lines` en los textos de un grupo;
- en Blade, para los textos de un grupo, sin `vertical_align` ni ajustes ópticos: caja de alto = líneas × fs × lh, con `top: 0` y `nowrap`;
- el sanitizer.

El riesgo principal es que la posición visual del texto dentro de su caja no coincida exactamente con la del navegador, porque dompdf usa otro "aire" arriba del primer renglón. Eso se ajusta midiendo.

**Fase 2 (grupos horizontales)**: media, unos 2–3 días. Hay que medir con `getTextWidth` **antes** del render, lo que implica:

1. crear la instancia de dompdf;
2. registrar las fuentes de cada texto (`FontMetrics::registerFont`) con la misma familia que el `@font-face`;
3. medir;
4. después renderizar.

Además conviene fijar el `width_cm` del texto al ancho medido, para que `text_align` no lo corra.

**Qué no encaja**: flex/gap (dompdf no los tiene), la idea de que el backend aplica `vertical_offset_cm`, y el corte extra por ancho del punto 4. Más allá de eso, el formato propuesto sirve tal cual.

### 13. Casos borde y cómo propongo tratarlos

| Caso | Propuesta |
|---|---|
| No existe el `container_element_id` | Se ignora el grupo y los miembros quedan en su posición de diseño |
| No existe el id de un miembro | Se omite ese miembro y se calcula con el resto |
| Elementos sin `id` (el contrato lo dice "recomendado", y puede haber diseños viejos sin ids) | Para los grupos, el `id` pasa a ser obligatorio |
| **Ícono sin imagen resuelta** (`resolved_icon_path` null, por ejemplo el cliente no eligió ícono en un diseño personalizable) | Hoy no dibuja nada pero ocupa su lugar. Propongo **tratarlo como ausente**: no suma tamaño ni gap. Es el caso típico de "PERSONALIZABLE SIN ICONO" |
| Texto que queda vacío (por ejemplo, `apellido` sin dato) | Igual que el ícono: ausente, sin gap |
| Miembro de tipo `shape` | Hoy `shape` está reservado y **no dibuja nada**. Puede ser miembro (aporta su tamaño fijo), pero no se va a ver |
| Mismo miembro en dos grupos | Lo rechaza el sanitizer, o gana el primer grupo |
| Contenedor con `padding_cm`, borde o forma `custom` | Hay que definir qué rectángulo se usa para centrar. Propongo la caja completa (`x/y/width/height`) **sin** restar el padding. Si lo quieren con padding, avisen |
| z-index distintos | No hay problema: el layout solo cambia `x/y` y no toca `z_index` ni el orden en el DOM |
| Páginas combinadas de varios diseños | Los grupos son por página, así que no hay choque de ids entre diseños |
