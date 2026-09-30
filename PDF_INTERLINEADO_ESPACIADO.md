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

### `line_height_rules` — interlineado según la longitud del texto

Igual que `font_size_rules` (tamaño de fuente según cantidad de caracteres), `line_height_rules` cambia el **interlineado** según cuántos caracteres tiene el texto ya resuelto (con el nombre/apellido real del cliente, no el texto de ejemplo del editor):

```json
{
  "type": "text",
  "content": "{{customer_name}}",
  "font_size_px": 40,
  "line_height": 1.0,
  "line_height_rules": [
    { "max_chars": 7, "line_height": 1.0 },
    { "max_chars": 15, "line_height": 1.8 },
    { "max_chars": null, "line_height": 2.0 }
  ]
}
```

Con esa config: "ANA" (3 caracteres) usa `line_height: 1.0`, "ROBERTITO" (9 caracteres) usa `1.8`, "GUILLERMINA CASTRO" (18 caracteres) usa `2.0`.

- Es una lista ordenada por `max_chars` (de menor a mayor); se usa la primera regla cuyo `max_chars` sea mayor o igual a la cantidad de caracteres. Una regla sin `max_chars` (o `null`) actúa como "para el resto" — conviene ponerla al final.
- Cada `line_height` de la lista se clampea igual que el campo suelto (0.5 a 5).
- Si no se manda `line_height_rules` (o ninguna regla matchea), se usa el `line_height` fijo del elemento — total compatibilidad con diseños que no usan esto.
- No existe un equivalente para `word_spacing_px` — ese solo se puede fijar, no varía según longitud (no lo pidieron y no hay un caso de uso claro para eso; se puede agregar después si hace falta).

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
