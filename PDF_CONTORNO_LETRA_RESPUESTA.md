# Contorno de letra en textos de diseños PDF — respuesta del back

Respuesta a [PDF_CONTORNO_LETRA.md](PDF_CONTORNO_LETRA.md). El back ya guarda `text_stroke` y lo dibuja
en el PDF. Del lado del front no hace falta cambiar nada: se usa el formato tal cual lo mandan.

---

## Qué guarda el back

Mismo endpoint de siempre (`POST /product-pdf-designs` y `POST /product-pdf-designs/{id}`). El contorno
queda dentro de `data`, en el elemento, sin columna nueva.

Al guardar se sanea así:

| Campo | Qué hace el back |
|---|---|
| `text_stroke` en un elemento que no es `type: "text"` | Se descarta. |
| `width_cm` ausente, no numérico o `<= 0` | Se descarta todo `text_stroke` (= sin contorno). |
| `width_cm` mayor a 0,5 | Se guarda 0,5 (tope de seguridad; el editor llega a 0,1). |
| `color` sin `value` o con `value` vacío | Se descarta todo `text_stroke`. |
| `color.mode` | `hex` o `cmyk`; cualquier otro valor se guarda como `hex`. |
| `color.value_mode` | Se conserva (`dynamic` o `fixed`), así al reabrir el diseño sigue apareciendo "De la compra". |
| `color.dynamic_attribute_id` / `color.editable_by_customer` | Se conservan. |
| `position` | `inside` o `outside`; si falta o viene otra cosa, `outside`. |
| `join` | `miter` o `round`; si falta o viene otra cosa, `round`. |

O sea, si el front manda algo inválido, el diseño vuelve **sin** `text_stroke` en vez de dar error.

---

## Cómo sale en el PDF

- **Exterior (`outside`)**: el contorno queda todo por fuera de la letra. Si hay varios renglones, el
  contorno de uno no tapa la letra del otro.
- **Interior (`inside`)**: la letra conserva su tamaño y el contorno ocupa su borde.
- **Esquinas `round`**: esquinas y puntas redondeadas.
- **Esquinas `miter`**: en punta. El back usa el mismo límite de punta que el navegador (4), así que
  en ángulos muy cerrados (M, W, V) la punta se corta igual que en la vista previa del editor.
- **No se recorta a la caja**: el contorno exterior puede salirse de `x_cm`/`y_cm`/`width_cm`/`height_cm`,
  igual que en el editor.
- **No cambia el layout**: tamaño de la caja, corte en renglones (`max_chars_per_line` / `max_lines`),
  espaciado y `vertical_offset_cm` quedan igual que sin contorno.

---

## Lo que no se cubre (todavía)

### Color "De la compra"

El back **guarda** `value_mode: "dynamic"` y `dynamic_attribute_id` en `text_stroke.color`, pero al
generar el PDF **dibuja el color de respaldo** (`mode` / `value`), no el que eligió el cliente.

No es algo propio del contorno: hoy el back tampoco resuelve el color "De la compra" en `color`,
`border.color` ni `fill.color` (todos salen con el respaldo). Cuando se implemente para esos, el
contorno se resuelve con la misma lógica.

Mientras tanto, conviene que el **respaldo** (`value`) sea un color razonable para el PDF, porque es el
que va a salir impreso.

### Lista de atributos que usa el diseño

El back no arma esa lista; se calcula en el front. Lo que tocaron en `PdfDesignHelper.ts` /
`useDesignEditor.ts` alcanza.

---

## A tener en cuenta en el editor

- **Contorno interior grueso con letra fina**: si el grosor es mayor que el palo de la letra, la letra
  queda pintada entera del color del contorno (en el PDF y, debería, también en la vista previa). Por
  ejemplo, 0,08 cm por dentro sobre una letra fina de ~45 pt ya la tapa toda. No es un error, pero
  quizás valga un aviso o bajar el máximo del slider cuando la posición es "Interior".
- **Colores CMYK**: se respetan tal cual en el PDF (`"c,m,y,k"` de 0 a 1), igual que el resto de los
  colores.
