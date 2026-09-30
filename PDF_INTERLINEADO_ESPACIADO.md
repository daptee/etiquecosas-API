# Interlineado y espacio entre palabras en el diseñador de PDF

Igual que ya se puede elegir en cuántos renglones parte un texto (`max_lines`/`min_lines`) o el tamaño de fuente según la longitud (`font_size_rules`), ahora también se puede configurar por elemento de texto:

- **`line_height`** — el interlineado (espacio entre renglones).
- **`word_spacing_px`** — el espacio extra entre palabras.

Ninguno de los dos es obligatorio — si no se mandan, el texto se comporta exactamente igual que antes.

---

## `line_height`

```json
{ "type": "text", "content": "EJEMPLO", "font_size_px": 40, "line_height": 1.8 }
```

- Es el mismo número que CSS `line-height` **sin unidad**: multiplica el tamaño de fuente. `1.0` = renglones pegados (sin aire extra), `2.0` = el doble de alto que el tamaño de fuente entre renglón y renglón.
- **Default: `1.15`** — es el valor que ya se usaba fijo antes de poder configurarlo, así que un diseño viejo sin este campo se ve exactamente igual que siempre.
- Se clampea entre `0.5` y `5`.
- Solo tiene efecto visible en textos de **más de un renglón** — con nombre/apellido en una sola línea no se nota.

## `word_spacing_px`

```json
{ "type": "text", "content": "ESCRIBI VOS", "font_size_px": 40, "word_spacing_px": 12 }
```

- Espacio **extra** (en px) que se suma en cada espacio entre palabras, además del espacio normal de esa tipografía. `0` = sin cambios. Puede ser negativo para juntar más las palabras.
- **Default: `0`**.
- Se clampea entre `-50` y `200`.
- Solo tiene efecto si el texto tiene más de una palabra (nombre y apellido juntos, texto fijo con varias palabras, etc.) — un nombre solo no tiene espacios donde aplicarlo.

---

## Verificación

Ambas propiedades se probaron generando un PDF real y leyendo los operadores de dibujo del PDF resultante (no a simple vista):

- `word_spacing_px` se traduce al operador `Tw` de PDF (espaciado de palabra nativo del formato) — confirmado que con `40px` aparece `30.000 Tw` en el stream, y con `0px` no aparece (default `0`).
- `line_height` se confirmó midiendo la distancia real entre renglones: con `line_height: 2.5` la distancia entre líneas fue exactamente 2.5 veces la de `line_height: 1.0`.

---

## Dónde usarlo

Van en el mismo elemento `type: "text"` que ya tiene `font_size_px`, `max_lines`, etc. — no son un elemento aparte:

```json
{
  "type": "text",
  "content": "{{customer_name}}",
  "font_id": 5,
  "font_size_px": 40,
  "line_height": 1.4,
  "word_spacing_px": 6,
  "max_lines": 2,
  "text_align": "center",
  "vertical_align": "middle"
}
```
