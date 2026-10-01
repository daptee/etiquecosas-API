# Relaciones entre elementos (`layout_groups`)

Una relación hace que dos (o más) elementos de una página —texto e íconos— mantengan siempre la **misma distancia y alineación entre sí**, y que el conjunto quede **centrado en un punto**, aunque el texto "De la compra" salga más corto o más largo que el de ejemplo.

- **v2 (actual):** cada grupo trae `links` (una regla por eje entre cada par de miembros consecutivos) y se centra en `anchor` o en el centro de cualquier elemento (`container_element_id`).
- **Modelo 1 (compatibilidad):** grupos sin `links`, con `direction`/`gap_cm`/`align` y una etiqueta como contenedor. Siguen funcionando: se convierten a `links` equivalentes.

Sin `layout_groups`, todo funciona exactamente igual que antes.

## Formato

Cada página (`data.pages[]`) admite una lista opcional `layout_groups`:

```json
{
  "id": "layout-1759300000000-1234",
  "members": ["icon-1", "text-1"],
  "links": [
    {
      "x": { "mode": "align", "value": "center" },
      "y": { "mode": "gap", "side": "after", "cm": 0.3 }
    }
  ],
  "anchor": { "x_cm": 3.0, "y_cm": 2.4 },
  "container_element_id": "bg-1"
}
```

| Campo | Obligatorio | Descripción |
|---|---|---|
| `id` | sí | Único dentro de la página |
| `members` | sí | `id`s de elementos `text` o `icon` de la página, en orden. Cada elemento puede estar en **un solo** grupo |
| `links` | no | `links[i]` ubica a `members[i+1]` respecto de `members[i]`. Largo = `members.length − 1`. Si falta, se usan `direction`/`gap_cm`/`align` (modelo 1) |
| `anchor` | sí, salvo que haya `container_element_id` | Punto `{x_cm, y_cm}` en el que se centra el conjunto |
| `container_element_id` | no | `id` de **cualquier** elemento de la página que no sea miembro del grupo. Si existe, el centro de su caja reemplaza a `anchor`. Puede ser `null` |
| `direction`, `gap_cm`, `align` | no | Solo modelo 1. **Si hay `links`, se ignoran** (no se validan) |

### Regla de un eje (`x` o `y` de un link)

```json
{ "mode": "gap",   "side": "after" | "before", "cm": -50..50 }
{ "mode": "align", "value": "start" | "center" | "end" }
```

- `gap`: distancia fija con el anterior en ese eje. `after` = a la derecha (X) o abajo (Y); `before` = a la izquierda o arriba. `cm` **puede ser negativo**: los elementos se superponen esa cantidad.
- `align`: alineado con el anterior por su borde inicial (izquierdo/superior), su centro o su borde final (derecho/inferior).
- Cada link necesita las dos reglas (`x` e `y`), con cualquier combinación de modos.

### Modelo 1 → `links`

| `direction` | `x` | `y` |
|---|---|---|
| `vertical` | `align(align)` | `gap after (gap_cm)` |
| `horizontal` | `gap after (gap_cm)` | `align(align)` |

Para `align: center` el resultado es idéntico al de la versión anterior. Para `start`/`end` cambia: ahora la alineación es contra el **ancho medido** del texto, no contra el ancho de su caja.

## Validación al guardar

`POST /product-pdf-designs` y `POST /product-pdf-designs/{id}` responden **422** con el error en `errors["data.pages.{i}.layout_groups.{j}"]` cuando:

- falta `id` o está repetido en la página;
- `members` está vacío, o tiene un `id` que no existe o que no es `text`/`icon`; un elemento aparece en dos grupos;
- hay `links` y su largo no es `members.length − 1`, o una regla es inválida (falta `x` o `y`; `mode`, `side` o `value` desconocidos; `cm` no numérico o fuera de −50..50);
- no hay `links` y `direction`, `align` o `gap_cm` son inválidos (modelo 1: `gap_cm` de 0 a 50);
- faltan a la vez `anchor` y `container_element_id`, o `anchor` no tiene `x_cm` e `y_cm` numéricos;
- `container_element_id` (no `null`/vacío) no existe en la página o es uno de los `members`.

```json
{
  "message": "Error de validacion",
  "errors": {
    "data.pages.0.layout_groups.1": ["Grupo \"r2\": links[0].y tiene un side inválido (after o before)."]
  }
}
```

**Al generar el PDF** (datos guardados antes, o un elemento borrado después): un grupo inválido se descarta con un `warning` en el log y sus miembros quedan en su posición de diseño. Si `container_element_id` ya no existe (o es miembro) pero hay `anchor`, se usa el `anchor`. Si un elemento está en dos grupos, se queda en el primero.

## Cómo se calcula

1. **Ausentes:** un ícono sin imagen resuelta o un texto que queda vacío (por ejemplo, `apellido` sin dato) no ocupa lugar, no cuenta para la distancia y no se dibuja.
2. **Tamaño de cada presente:**
   - ícono: su `width_cm` × `height_cm`;
   - texto: **ancho** = el de su renglón más ancho, medido con su fuente, tamaño y `letter_spacing_px` ya resueltos (con tope en el `width_cm` de su caja); **alto** = `renglones × font_size_px × line_height × 2,54 / 96` cm. `min_lines` no reserva espacio.
3. **Cadena:** el primer presente va en (0,0). Cada siguiente se ubica respecto del **anterior presente** con su propio link (`links[i−1]`), eje por eje:

   | Regla | Posición |
   |---|---|
   | `gap after` | `prev.pos + prev.size + cm` |
   | `gap before` | `prev.pos − cm − size` |
   | `align start` | `prev.pos` |
   | `align end` | `prev.pos + prev.size − size` |
   | `align center` | `prev.pos + (prev.size − size) / 2` |

4. **Centrado:** el rectángulo que envuelve a los presentes se traslada para que su centro coincida con el punto de centrado, en **los dos ejes**:
   - el centro de la caja **de diseño** de `container_element_id` (aunque ese elemento esté en otro grupo y se haya movido);
   - si no, `anchor`.
5. Un solo presente queda centrado en el punto de centrado.
6. **Nunca se escala.** Si el conjunto se sale de su etiqueta (cuando el contenedor es un `background`) o de la hoja, se registra un `warning` con cuánto.
7. Los miembros se dibujan **sin rotación**; `z_index` y el orden de los elementos no cambian.

### Cómo se dibuja un texto dentro de un grupo

- Caja: `left`/`top` calculados, `width` = el ancho medido, `height` = el alto calculado.
- Sin `vertical_align`, sin `vertical_offset_cm`, sin ajustes ópticos y sin relleno lateral.
- `white-space: nowrap`: los renglones son exactamente los del corte por caracteres. Si uno es más ancho que el tope (`width_cm` de la caja), desborda hacia los costados.
- `text_align` aplica dentro de la caja (afecta a los renglones más cortos que el más ancho).

El ancho se mide con la misma función que usa dompdf para dibujar (`FontMetrics::getTextWidth`). Medido contra el PDF real, el primer glifo de cada renglón empieza a menos de 0,005 cm del `x_cm` calculado, y la línea base, a menos de 0,01 cm de donde la ubica CSS.

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
          "id": "r1",
          "center": { "x_cm": 3.5, "y_cm": 3 },
          "container": { "id": "bg-1", "x_cm": 1, "y_cm": 1, "width_cm": 5, "height_cm": 4 },
          "overflow_cm": 0,
          "members": [
            { "id": "icon-1", "type": "icon", "present": true, "x_cm": 2.9, "y_cm": 1.76, "width_cm": 1.2, "height_cm": 1.2 },
            { "id": "text-1", "type": "text", "present": true, "x_cm": 2.75, "y_cm": 3.26, "width_cm": 1.51, "height_cm": 0.97, "lines": ["Ana"] }
          ]
        }
      ]
    }
  ]
}
```

- Números en cm con 2 decimales. `center` es el punto de centrado usado; `container` es `null` cuando se centró en `anchor`.
- `lines` solo en los textos; los ausentes vienen con `present: false` y sin posición.
- Solo aparecen los grupos que se aplicaron (los inválidos no).

## Cambios relacionados (aplican a todos los diseños del editor)

- **Corte de renglones:** se normalizan los espacios (espacios dobles, `\n` y `\t` pasan a un espacio) antes de cortar y de contar caracteres para las reglas por longitud, y una primera palabra de `max_chars_per_line` caracteres o más ya no deja un renglón vacío arriba. Ver [FORMATO_NOMBRES_PDF.md](FORMATO_NOMBRES_PDF.md).
- **Tokens en texto fijo:** `content` también sustituye `{{fecha}}` (`d/m/Y`, Buenos Aires, fecha de aprobación del pago), `{{numero_pedido}}` (`sales.id`) y `{{id_producto}}` (`products.id` del producto comprado).
- **Preview sin venta real:** `numeroPedido` vale `111111` y `idProducto` (parámetro `?idProducto=`) vale `222222` si no se mandan.
- **Escapado:** el texto se escapa antes de insertarlo en el HTML; un nombre con `<` o `&` sale tal cual.
- **Fuente de respaldo:** todos los textos usan Liberation Sans (métricas de Arial) como respaldo, y es la fuente cuando `font_id` está vacío. Una tipografía subida que no carga cae en Liberation Sans en vez de Times.
- **Interlineado:** la corrección por métricas de la fuente incluye el `lineGap` del archivo, igual que hace dompdf.
