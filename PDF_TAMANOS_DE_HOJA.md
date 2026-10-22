# Tamaños de hoja (ABM para el editor de PDF)

Catálogo de "tamaños de hoja" (ej. A4, Oficio) para elegir al crear o editar una página en el editor de PDFs, en vez de tipear ancho/alto en cm a mano cada vez. Mismo criterio que `label_shapes` (formas de etiqueta), pero para el tamaño de la HOJA entera.

**No hay ninguna referencia (FK)** desde `product_pdf_designs` hacia acá — es un catálogo de sugerencia: el front lo usa para completar `sheet.width_cm`/`sheet.height_cm` de esa página al elegir un tamaño, nada más. Después se puede editar el ancho/alto de esa página libremente sin que quede atado al tamaño elegido.

---

## Esquema

- `pdf_sheet_sizes`: `id`, `name` (único), `width_cm`, `height_cm`, `is_system` (precargado vs. creado por un admin), `status_id`, soft deletes.

Precargados (`is_system = 1`): A4 (21×29.7cm — el que ya usan casi todos los diseños existentes), A3, A5, Oficio, Carta.

---

## ABM — `PdfSheetSizeController`

```
GET    /pdf-sheet-sizes            Listado (search por name, paginado opcional con ?quantity=&page=)
GET    /pdf-sheet-sizes/{id}       Ver uno
POST   /pdf-sheet-sizes            Crear
POST   /pdf-sheet-sizes/{id}       Editar
PATCH  /pdf-sheet-sizes/{id}/toggle-status   Activar/desactivar
DELETE /pdf-sheet-sizes/{id}       Eliminar (soft delete)
```

### Crear/editar

```json
POST /pdf-sheet-sizes
{
  "name": "A4",
  "widthCm": 21,
  "heightCm": 29.7,
  "isSystem": false,
  "statusId": 1
}
```

| Campo | Tipo | Obligatorio | Descripción |
|---|---|---|---|
| `name` | string | Sí | Único. |
| `widthCm` / `heightCm` | number | Sí | En cm. |
| `isSystem` | bool | No | Default `false` — para distinguir tamaños precargados de los que carga un admin. |
| `statusId` | id | No | Default `1` (activo). |

---

## Uso en el editor

No hay un endpoint de "aplicar" — el front simplemente lista `GET /pdf-sheet-sizes`, el admin elige uno al crear/editar una página, y el front copia `width_cm`/`height_cm` al `sheet` de esa página. Es edición normal de `product_pdf_designs.data` (`POST`/`PUT` de siempre), no hay nada nuevo que tocar ahí.
