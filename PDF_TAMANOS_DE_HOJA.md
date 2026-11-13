# Tamaños de hoja (ABM para el editor de PDF)

Catálogo de "tamaños de hoja" (ej. A4, Oficio) para elegir al crear o editar una página en el editor de PDFs, en vez de tipear ancho/alto en cm a mano cada vez. Mismo criterio que `label_shapes` (formas de etiqueta), pero para el tamaño de la HOJA entera.

**No hay ninguna referencia (FK)** desde `product_pdf_designs` hacia acá — es un catálogo de sugerencia: el front lo usa para completar `sheet.width_cm`/`sheet.height_cm` de esa página al elegir un tamaño, nada más. Después se puede editar el ancho/alto de esa página libremente sin que quede atado al tamaño elegido.

---

## Esquema

- `pdf_sheet_sizes`: `id`, `name` (único), `width_cm`, `height_cm`, `type_id` (FK a `pdf_sheet_size_types`), `is_system` (precargado vs. creado por un admin), `status_id`, soft deletes.
- `pdf_sheet_size_types`: `id`, `name` (único) — catálogo de "Página", "Pliego" y los que se agreguen a futuro. Es una tabla propia (no un ENUM) específicamente para poder sumar tipos nuevos sin tocar el esquema.

"Página" = una hoja de PDF normal (A4, Oficio, etc. — tal cual se imprime). "Pliego" = una hoja grande de imprenta, de la que después se recortan varias etiquetas/páginas. `type_id` es puramente informativo para el editor, no cambia en nada cómo se usan `width_cm`/`height_cm`.

Precargados: tipos `Página` (id 1) y `Pliego` (id 2); tamaños (`is_system = 1`, todos con `type_id` = Página): A4 (21×29.7cm — el que ya usan casi todos los diseños existentes), A3, A5, Oficio, Carta.

---

## ABM de tamaños — `PdfSheetSizeController`

```
GET    /pdf-sheet-sizes            Listado (search por name, filtro ?typeId=, paginado opcional con ?quantity=&page=)
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
  "typeId": 1,
  "isSystem": false,
  "statusId": 1
}
```

| Campo | Tipo | Obligatorio | Descripción |
|---|---|---|---|
| `name` | string | Sí | Único. |
| `widthCm` / `heightCm` | number | Sí | En cm. |
| `typeId` | id | No | `pdf_sheet_size_types.id`. |
| `isSystem` | bool | No | Default `false` — para distinguir tamaños precargados de los que carga un admin. |
| `statusId` | id | No | Default `1` (activo). |

La respuesta trae `type` (la relación) ya cargada. `GET /pdf-sheet-sizes?typeId=2` filtra el listado por tipo.

---

## ABM de tipos — `PdfSheetSizeTypeController`

Para agregar un tipo nuevo (ej. "Rollo") sin tocar código ni base de datos a mano:

```
GET    /pdf-sheet-size-types            Listado (search por name)
POST   /pdf-sheet-size-types            Crear   { "name": "Rollo" }
POST   /pdf-sheet-size-types/{id}       Editar  { "name": "..." }
DELETE /pdf-sheet-size-types/{id}       Eliminar (soft delete)
```

---

## Uso en el editor

No hay un endpoint de "aplicar" — el front simplemente lista `GET /pdf-sheet-sizes`, el admin elige uno al crear/editar una página, y el front copia `width_cm`/`height_cm` al `sheet` de esa página. Es edición normal de `product_pdf_designs.data` (`POST`/`PUT` de siempre), no hay nada nuevo que tocar ahí.
