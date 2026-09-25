# Cómo se parte "Nombre y Apellido" en 1, 2 o 3 renglones en el PDF

## Resumen

Todo el texto de las etiquetas (legacy y el editor nuevo de `product_pdf_designs`) pasa por la misma función: `formatName()` en [app/Helpers/Helpers.php](app/Helpers/Helpers.php#L56) (autoload global, disponible en cualquier vista Blade).

```php
formatName($name, $maxLines = 3, $maxCharsPerLine = 10, $firstName = null)
```

**En el editor nuevo, cada elemento `text` de un diseño (`product_pdf_designs.data.pages[].elements[]`) puede configurar esto individualmente** con `max_lines`, `min_lines`, `max_chars_per_line` y `font_size_rules` — documentado con ejemplos en [PDF_EDITOR.md](PDF_EDITOR.md#2-diseños-de-pdf-product_pdf_designs). Si no se manda nada, quedan los defaults de siempre (`max_lines: 3`, `max_chars_per_line: 10`, `min_lines: 1`), así que ningún diseño existente cambia de comportamiento.

La función tiene **dos modos** completamente distintos, y cuál se usa depende de si se manda o no un `$firstName`:

| Se manda `$firstName`? | Modo | Renglones con los defaults (`maxLines=3`) |
|---|---|---|
| Sí (no vacío) | **A — Nombre / Apellido** | 1 o **2**, nunca 3 |
| No (null o `""`) | **B — Multi-renglón** | 1, 2 o **3** |

En el editor, el `firstName` solo se manda para el elemento cuyo `content` es literalmente `{{customer_name}}` — cualquier otro texto fijo siempre entra en Modo B, sin importar el nombre del cliente de esa venta.

---

## Qué tenés que mandar

### Para el placeholder `{{customer_name}}` del editor (o el flujo de checkout legacy)

El checkout manda `customization_data`:

```json
{
  "form": { "name": "Juan", "lastName": "Pérez" }
}
```

- `form.name` → es el `$firstName` que le llega a `formatName()`.
- `form.name + " " + form.lastName` (recortado) → es el `$name` completo.

**Con `form.name` y `form.lastName` los dos completos → Modo A → 1 o 2 renglones con los defaults.**

**Si `form.name` viene vacío** (o no se manda), cae en el **Modo B** (multi-renglón) usando lo que haya en `form.lastName` como texto único.

### Para un texto fijo del editor (no el nombre del cliente)

Si en un elemento `text` del diseño ponés un `content` que **no** sea `{{customer_name}}` (ej. "FELIZ CUMPLE", "GRACIAS POR TU COMPRA"), automáticamente entra en **Modo B** — nunca se le aplica el corte nombre/apellido, así que no hace falta mandar nada especial.

---

## Modo A — Nombre / Apellido

Regla exacta:
1. Si `NOMBRE + " " + APELLIDO` entra en `max_chars_per_line` caracteres → **1 renglón**, todo junto.
2. Si no entra → siempre se arma **exactamente 2 "piezas"**: el nombre y el apellido — tal cual, sin volver a partir cada uno aunque individualmente supere `max_chars_per_line`.
3. Recién ahí se aplica `max_lines`: si `max_lines >= 2` (el default es 3) salen las 2 piezas en 2 renglones. **Si configurás `max_lines: 1`, se trunca a 1 renglón y se pierde el apellido** (se le agrega "…").

### Ejemplos reales (corridos contra el código, `max_chars_per_line=10`)

| `form.name` | `form.lastName` | `max_lines` | Resultado |
|---|---|---|---|
| Juan | Perez | 3 (default) | `JUAN PEREZ` *(1 renglón — entra en 10 caracteres)* |
| Ana | Lopez | 3 (default) | `ANA LOPEZ` *(1 renglón)* |
| Guillermina | Rodriguez | 3 (default) | `GUILLERMINA` <br> `RODRIGUEZ` *(2 renglones)* |
| Maria Jose | Fernandez | 3 (default) | `MARIA JOSE` <br> `FERNANDEZ` *(2 renglones — "Maria Jose" queda junto en el renglón 1)* |
| María | González | 3 (default) | `MARÍA` <br> `GONZÁLEZ` *(2 renglones — los acentos cuentan como 1 carácter normal)* |
| Sofia | *(vacío)* | 3 (default) | `SOFIA` *(1 renglón — sin apellido, no hay nada para partir)* |
| Guillermina | Rodriguez | **1** ⚠️ | `GUILLERMINA…` *(se pierde el apellido — ver nota abajo)* |

⚠️ **Ojo con `max_lines: 1` en el nombre del cliente**: si lo configurás así en un elemento con `content: "{{customer_name}}"`, cuando el nombre completo no entra en un renglón vas a perder el apellido (queda truncado con "…"). Para el nombre del cliente, `max_lines` tiene que ser **2 o más**.

---

## Modo B — Multi-renglón, sin nombre/apellido separado

Se usa cuando **no** hay `firstName` (texto fijo del editor, o `form.name` vacío en el checkout legacy). Divide el texto por **palabras completas** (nunca corta una palabra a la mitad), respetando `max_chars_per_line` caracteres por renglón, hasta `max_lines` renglones.

Detalle importante: las partículas de apellidos compuestos nunca quedan solas — siempre se pegan a la palabra siguiente:

```
DE, DEL, DE LA, DE LOS, DE LAS, DI, LA, LAS, LOS, EL, Y, VAN, VON, BIN, BTE
```

### Ejemplos reales (corridos contra el código, defaults `max_lines=3`, `max_chars_per_line=10`)

| Texto | Resultado |
|---|---|
| `Sofia` | `SOFIA` *(1 renglón)* |
| `Juan Perez` | `JUAN PEREZ` *(1 renglón — entra en 10 caracteres)* |
| `Guillermina` | *(renglón vacío)* <br> `GUILLERMINA` *(2 renglones — ver nota abajo)* |
| `Juan Carlos Perez` | `JUAN` <br> `CARLOS` <br> `PEREZ` *(3 renglones)* |
| `Maria de la Cruz` | `MARIA` <br> `DE LA CRUZ` *(2 renglones — "DE LA CRUZ" no se separa)* |
| `Juan de los Santos` | `JUAN` <br> `DE LOS SANTOS` *(2 renglones)* |
| `Maria Fernanda Gonzalez Rodriguez Perez` | `MARIA` <br> `FERNANDA` <br> `GONZALEZ RODRIGUEZ PEREZ` *(3 renglones — todo lo que sobra se amontona en el último)* |
| `Ana Maria de los Santos Perez Gomez Lopez Ruiz Torres` | `ANA MARIA` <br> `DE LOS SANTOS` <br> `PEREZ GOMEZ LOPEZ RUIZ TORRES` *(3 renglones — el último puede superar los 10 caracteres, no se corta más)* |

⚠️ **A diferencia del Modo A, acá el truncado con "…" nunca se activa**, sin importar qué `max_lines` configures — por cómo arma los renglones, el algoritmo nunca genera más de `max_lines` renglones para empezar (el último renglón simplemente absorbe todo lo que quede, por más que sea largo, como en el último ejemplo de la tabla).

⚠️ **Caso raro**: una sola palabra sin espacios y más larga que `max_chars_per_line` (ej. `GUILLERMINA` sola) puede generar un **renglón vacío antes** de la palabra, porque el algoritmo solo corta entre palabras, nunca dentro de una palabra. Si te pasa esto en un texto fijo del editor, conviene acortar el texto o agrandar `max_chars_per_line`.

---

## `min_lines` — reservar espacio aunque el texto sea corto

Si el texto entra en menos renglones de los que pide `min_lines`, se completan con renglones vacíos al final. Sirve para que el bloque de texto siempre ocupe la misma altura visual, aunque el nombre sea corto.

| Texto | `min_lines` | Resultado |
|---|---|---|
| `Ana Lopez` (con firstName="Ana") | 3 | `ANA LOPEZ` <br> *(vacío)* <br> *(vacío)* |
| `Sofia` (sin firstName) | 2 | `SOFIA` <br> *(vacío)* |

---

## `font_size_rules` — tamaño de letra según cantidad de caracteres

Reemplaza el `getFontSize()`/`fontClass` hardcodeado que usan las vistas legacy (que reducía la fuente para nombres largos con umbrales fijos: 16 y 20 caracteres). En el editor nuevo, cada elemento de texto puede definir su propia escala:

```json
"font_size_rules": [
  { "max_chars": 5, "font_size_px": 80 },
  { "max_chars": 10, "font_size_px": 50 },
  { "max_chars": null, "font_size_px": 30 }
]
```

Se evalúan ordenadas de menor a mayor `max_chars`, y se usa la primera que alcance (`max_chars: null` = sin límite, para "todo lo demás"). La longitud se mide sobre el texto ya resuelto (para `{{customer_name}}`, el nombre completo del cliente).

| Nombre | Caracteres | `font_size_px` resultante |
|---|---|---|
| ANA | 3 | 80 *(≤5)* |
| GUILLERMINA | 11 | 30 *(no entra en 5 ni en 10 → regla "para el resto")* |

Si no se manda `font_size_rules`, se usa siempre el `font_size_px` fijo del elemento — sin cambios de comportamiento para diseños ya creados.

---

## Resumen para el front

- Para el nombre del cliente (`{{customer_name}}`): dejá `max_lines` en 2 o más (el default, 3, ya funciona bien). Con 1 se pierde el apellido.
- Para textos fijos que necesitás que se puedan partir en 3 (o más, si subís `max_lines`) renglones: no hace falta hacer nada especial, cualquier texto que no sea `{{customer_name}}` ya entra en el modo multi-renglón.
- `min_lines` para reservar espacio fijo aunque el texto sea corto.
- `font_size_rules` para que el tamaño de letra escale según la longitud del texto, en vez de un `font_size_px` fijo.
- Todos estos campos son opcionales — si no los mandás, el diseño se comporta exactamente igual que si no existieran.
