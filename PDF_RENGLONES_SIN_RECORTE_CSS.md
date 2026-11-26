# El PDF ya no deja que dompdf re-envuelva el texto por su cuenta

## El problema (reporte: diseño 246, "BENJA PIÑON P")

Con `max_chars_per_line: 13` en todos los tamaños de un mismo diseño, el mismo texto de 13 caracteres salía partido en 2 renglones en algunos tamaños y en uno solo en otros — **sin que la config cambiara entre ellos**.

## Causa

`RENDER.blade.php` solo ponía `white-space: nowrap` en el `<p>` de un texto cuando era parte de un `layout_group` (`$el['layout_text']`). Para un texto SUELTO (como los de la grilla del diseño 246, todos con `group_id: null`), el `<p>` no tenía `nowrap` — así que, aunque `EtiquetaService::dividirEnRenglones()` ya había calculado los renglones finales (`resolved_text_html`, con sus `<br>` según `max_chars_per_line`/`max_lines`), si esa línea igual no entraba en el ANCHO REAL de la caja (en píxeles, a ese `font_size_px` concreto), dompdf la volvía a envolver por su cuenta — con su propio criterio de ancho, no con la cuenta de caracteres configurada. Cada tamaño de etiqueta tiene su propia combinación `width_cm`/`font_size_px`, así que el punto exacto en el que "ya no entra" cae distinto en cada uno — de ahí la inconsistencia.

Con "BENJA PIÑON P" (13 caracteres) y el límite en 13, `dividirEnRenglones()` nunca lo parte — lo que se veía partido en 2 líneas en algunos tamaños era 100% el re-envuelto de dompdf, no la configuración.

## El fix (parche rápido)

`RENDER.blade.php`, el `<p>` de `type: "text"`: `white-space: nowrap` ahora es **incondicional**, para todo texto (antes solo los de un `layout_group`). `resolved_text_html` pasa a ser la única fuente de verdad de dónde van los cortes — dompdf ya no vuelve a tocarlo.

**Trade-off conocido, ya verificado**: si la config (`max_chars_per_line`/`width_cm`/`font_size_px`) no alcanza para que el renglón calculado entre de verdad en la caja, el texto ahora se desborda hacia los costados (puede superponerse con la etiqueta de al lado) en vez de auto-ajustarse con un envoltorio extra silencioso. Probado contra el diseño 246 real: el tamaño más grande (fuente 31px, caja 6.14cm) desborda un poco porque "BENJA PIÑON P" mide ahí ~6.24cm — antes esto se "tapaba" partiéndolo en 2 líneas sin avisar; ahora se ve el desborde real, así se nota que hay que ajustar `max_chars_per_line`/el ancho/el tamaño de fuente para ESE tramo puntual. Ya no hay una red de contención automática — es a propósito: ahora lo que se configura es exactamente lo que se imprime.

Pendiente (más grande, no incluido en este parche): medir el ancho REAL con las métricas de la fuente (en vez de contar caracteres) para que `max_chars_per_line` nunca tenga que adivinarse — ver conversación, opción B considerada y pospuesta.

## Pendiente del front

Ver `PDF_RENGLONES_SIN_RECORTE_CSS_FRONT.md` — el editor todavía re-envuelve el preview por ancho real cuando el texto no está en un layout_group (`PdfDesignElementGlyph.vue`), que era justamente el comportamiento que el PDF descargado YA NO tiene. Sin ese cambio, el preview del editor y la descarga van a seguir sin coincidir.
