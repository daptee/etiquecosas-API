# Grupos con distribución (`layout_groups`)

Un grupo hace que varios elementos de una etiqueta (texto e íconos) mantengan siempre la **misma distancia entre sí** y queden **centrados en su etiqueta**, aunque el texto "De la compra" salga más corto o más largo que el de ejemplo.

> **Estado:** fase 1 implementada (grupos **verticales**). Los grupos **horizontales** se aceptan al guardar, pero al generar el PDF todavía se ignoran (los miembros quedan en su posición de diseño y se registra un `warning`). Llegan en la fase 2.

Sin `layout_groups` todo funciona exactamente igual que antes.

## Formato

Cada página (`data.pages[]`) admite una lista opcional `layout_groups`:

```json
{
  "pages": [
    {
      "sheet": { "width_cm": 18.5, "height_cm": 29 },
      "elements": [
        { "id": "bg-1", "type": "background", "x_cm": 0.5, "y_cm": 0.5, "width_cm": 5, "height_cm": 4 },
        { "id": "icon-1", "type": "icon", "icon_id": 34, "x_cm": 2.4, "y_cm": 1, "width_cm": 1.2, "height_cm": 1.2 },
        { "id": "text-1", "type": "text", "value_mode": "dynamic", "dynamic_field": "nombre_apellido", "x_cm": 0.5, "y_cm": 2.5, "width_cm": 5, "height_cm": 1, "font_size_px": 32 }
      ],
      "layout_groups": [
        {
          "id": "group-1",
          "container_element_id": "bg-1",
          "direction": "vertical",
          "gap_cm": 0.3,
          "align": "center",
          "members": ["icon-1", "text-1"]
        }
      ]
    }
  ]
}
```

| Campo | Obligatorio | Descripción |
|---|---|---|
| `id` | sí | Único dentro de la página |
| `container_element_id` | sí | `id` de un elemento `background` de la misma página (la etiqueta) |
| `direction` | sí | `vertical` \| `horizontal` (horizontal: fase 2) |
| `gap_cm` | sí | Distancia fija entre miembros, de 0 a 50 |
| `align` | no (default `center`) | `start` \| `center` \| `end`: alineación de cada miembro en el eje transversal |
| `members` | sí | `id`s de elementos `text` o `icon` de la página, **en orden** (de arriba hacia abajo en vertical) |

- Cada elemento puede estar en **un solo** grupo.
- Los miembros conservan su `x_cm`/`y_cm`/`width_cm`/`height_cm` de diseño; el backend los reemplaza al generar.
- **Columnas × filas:** el backend no replica nada. El frontend manda **un grupo por cada copia** de la etiqueta, con los elementos de esa copia y su propia etiqueta como contenedor.

## Validación al guardar

`POST /product-pdf-designs` y `POST /product-pdf-designs/{id}` responden **422** si un grupo es inválido, con el error en `errors["data.pages.{i}.layout_groups.{j}"]`:

```json
{
  "message": "Error de validacion",
  "errors": {
    "data.pages.0.layout_groups.1": ["Grupo \"group-2\": el elemento \"text-1\" ya está en el grupo \"group-1\"."]
  }
}
```

Es inválido cuando: falta `id` o está repetido en la página; `container_element_id` no existe o no es `background`; `members` está vacío o tiene un `id` que no existe o que no es `text`/`icon`; un elemento está en dos grupos; `direction` o `align` traen un valor desconocido; `gap_cm` no es un número entre 0 y 50.

Al **generar** el PDF (datos guardados antes, o un elemento borrado después), un grupo inválido se descarta en silencio con un `warning` en el log y sus miembros quedan en su posición de diseño. Si un elemento está en dos grupos, se queda en el primero.

## Cómo se calcula (vertical)

1. **Miembros ausentes:** un ícono sin imagen resuelta (por ejemplo, el cliente no eligió ícono) o un texto que queda vacío (por ejemplo, `apellido` sin dato) no ocupa lugar, no suma `gap` y no se dibuja.
2. **Alto de cada miembro:**
   - ícono: su `height_cm`;
   - texto: `renglones × font_size_px × line_height × 2,54 / 96` cm, con el texto ya sustituido y los valores ya resueltos por sus reglas por longitud. `min_lines` **no** reserva espacio.
3. **Total** = suma de altos + `gap_cm × (presentes − 1)`.
4. El conjunto se **centra verticalmente** en la caja completa de la etiqueta (`x/y/width/height` del contenedor, **sin** restar `padding_cm`).
5. **Eje horizontal:** el bloque mide lo del miembro más ancho (el ancho de un texto es el `width_cm` de su caja) y se centra en la etiqueta; cada miembro se ubica en el bloque según `align`.
6. Si el conjunto **no entra**, no se escala: desborda y se registra un `warning` con cuánto.
7. Los miembros se dibujan **sin rotación** (se ignora `rotation_deg`). `z_index` y el orden de los elementos no cambian.

### Cómo se dibuja un texto dentro de un grupo

- Caja: `left`/`top` calculados, `width = width_cm`, `height` = el alto calculado.
- Sin `vertical_align`, sin `vertical_offset_cm` y sin ajustes ópticos: el primer renglón empieza arriba de la caja, como en CSS estándar.
- `white-space: nowrap`: los renglones son exactamente los del corte por caracteres. Si uno es más ancho que la caja, desborda hacia los costados.
- `text_align` sigue aplicando dentro del `width_cm`.

Medido contra el PDF real (Liberation Sans, 32 px, `line_height` 1.15): la línea base de cada renglón queda a menos de 0,01 cm de donde la ubica CSS.

## Comparar con el editor: `?format=layout`

`GET /product-pdf-designs/{id}/preview?format=layout` acepta los mismos parámetros que el preview y, en vez del PDF, devuelve las posiciones finales:

```json
{
  "pages": [
    {
      "index": 0,
      "id": "page-1",
      "groups": [
        {
          "id": "group-1",
          "container": { "x_cm": 0.5, "y_cm": 0.5, "width_cm": 5, "height_cm": 4 },
          "overflow_cm": 0,
          "members": [
            { "id": "icon-1", "type": "icon", "present": true, "x_cm": 2.4, "y_cm": 0.78, "width_cm": 1.2, "height_cm": 1.2 },
            { "id": "text-1", "type": "text", "present": true, "x_cm": 0.5, "y_cm": 2.28, "width_cm": 5, "height_cm": 1.95, "lines": ["NOMBRE", "APELLIDO"] }
          ]
        }
      ]
    }
  ]
}
```

- Números en cm con 2 decimales. `lines` solo en los textos; los ausentes vienen con `present: false` y sin posición.
- Solo aparecen los grupos que se aplicaron (los inválidos y los horizontales no).

## Cambios relacionados (aplican a todos los diseños del editor)

- **Corte de renglones:** se normalizan los espacios (espacios dobles, `\n` y `\t` pasan a un espacio) antes de cortar y de contar caracteres para las reglas por longitud, y una primera palabra de `max_chars_per_line` caracteres o más ya no deja un renglón vacío arriba. Ver [FORMATO_NOMBRES_PDF.md](FORMATO_NOMBRES_PDF.md).
- **Tokens en texto fijo:** `content` también sustituye `{{fecha}}` (`d/m/Y`, Buenos Aires, fecha de aprobación del pago), `{{numero_pedido}}` (`sales.id`) y `{{id_producto}}` (`products.id` del producto comprado).
- **Preview sin venta real:** `numeroPedido` vale `111111` y `idProducto` (parámetro nuevo `?idProducto=`) vale `222222` si no se mandan.
- **Escapado:** el texto se escapa antes de insertarlo en el HTML; un nombre con `<` o `&` sale tal cual.
- **Fuente de respaldo:** todos los textos usan Liberation Sans (métricas de Arial) como respaldo, y es la fuente cuando `font_id` está vacío (antes salía Helvetica). Una tipografía subida que no carga cae en Liberation Sans en vez de Times.
- **Interlineado:** la corrección por métricas de la fuente ahora incluye el `lineGap` del archivo, igual que hace dompdf. Las fuentes con `lineGap` mayor que cero tenían cerca de un 3% más de interlineado que en el navegador; ahora coinciden.
