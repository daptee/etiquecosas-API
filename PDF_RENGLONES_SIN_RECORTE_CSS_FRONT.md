# Front: el preview de un texto suelto ya no debe re-envolver por ancho

Contexto completo en `PDF_RENGLONES_SIN_RECORTE_CSS.md`. Resumen: el PDF descargado ya NO deja que el texto se re-envuelva por ancho real además del corte por `max_chars_per_line`/`max_lines` — ahora `resolved_text_html` (los renglones ya calculados por caracteres) es la única fuente de verdad, para todo texto, esté o no dentro de un `layout_group`. El editor todavía hace lo viejo para un texto SUELTO (sin `layout_group`): lo re-envuelve además por ancho real medido en el canvas, acotado a `max_lines` — por eso el preview y la descarga dejaron de coincidir (el preview sigue "salvando" con una línea de más algo que el PDF ahora deja desbordar, o al revés, corta distinto).

## Qué hay que cambiar

**`components/productPdfDesigns/shared/PdfDesignElementGlyph.vue`**, `wrappedDisplayText` (línea ~481-498):

```ts
// ANTES
const wrappedDisplayText = computed(() => {
	const { text } = textDisplay.value;
	if (props.element.type !== 'text') return text;
	if (!props.element.layout_nowrap && !textHasChip.value) {
		const lines = PdfDesignHelper.visualTextLines(props.element, fontFamily.value);
		if (lines.length) return lines.map(line => line.words.join(' ')).join('\n');
	}
	const maxLines = Math.max(1, props.element.max_lines ?? 3);
	const maxCharsPerLine = Math.max(1, props.element.max_chars_per_line ?? 10);
	const lines = PdfDesignHelper.wrapByCharsPerLine(
		text,
		maxCharsPerLine,
		maxLines,
		props.element.wrap_first_name,
		Math.max(1, props.element.min_lines ?? 1)
	);
	return lines.length ? lines.join('\n') : text;
});
```

```ts
// DESPUÉS — se saca el bloque de visualTextLines(): el resto ya hacía exactamente
// lo que el backend hace ahora siempre (wrapByCharsPerLine, sin ningún envoltorio extra).
const wrappedDisplayText = computed(() => {
	const { text } = textDisplay.value;
	if (props.element.type !== 'text') return text;
	const maxLines = Math.max(1, props.element.max_lines ?? 3);
	const maxCharsPerLine = Math.max(1, props.element.max_chars_per_line ?? 10);
	const lines = PdfDesignHelper.wrapByCharsPerLine(
		text,
		maxCharsPerLine,
		maxLines,
		props.element.wrap_first_name,
		Math.max(1, props.element.min_lines ?? 1)
	);
	return lines.length ? lines.join('\n') : text;
});
```

Esto es EXACTAMENTE lo mismo que ya se hacía para un texto dentro de un `layout_group` (`props.element.layout_nowrap` true) — ahora es el único camino, para cualquier texto.

No hace falta tocar `whiteSpace` (línea ~462, el `computed` de `textStyle`): para un texto real (sin `{{token}}`, el caso de "BENJA PIÑON P") ya da `'pre'` hoy (`!textHasChip.value` alcanza), que es justo lo que hace falta — nunca auto-envuelve, solo respeta los `\n` que le llegan. El `'pre-line'` que queda para cuando SÍ hay un chip (`{{Nombre y Apellido}}` mostrado como placeholder) es un caso aparte, de edición, que no representa texto real final — no hace falta tocarlo para este fix.

**No toquen** `PdfDesignHelper.visualTextLines()` en sí (línea ~855) ni `keepTextLinesPatch()` (línea ~911, que también la usa) — esa función sigue haciendo falta para OTRA cosa (mantener la estructura de renglones al cambiar la tipografía mientras se edita). Alcanza con dejar de llamarla desde `wrappedDisplayText`.

## Qué esperar después del cambio

Con esto, un texto que en el tamaño más grande no entre de verdad en el ancho configurado (ver el ejemplo del diseño 246 en `PDF_RENGLONES_SIN_RECORTE_CSS.md`: "BENJA PIÑON P" a 31px en una caja de 6.14cm) va a verse desbordado en el EDITOR también, en vez de auto-ajustado con una línea de más — que es justo lo que hoy hace el PDF descargado. Es la señal visual correcta para que el admin ajuste `max_chars_per_line`/el ancho/el tamaño de fuente de ese elemento puntual, en vez de enterarse recién al abrir el PDF.
