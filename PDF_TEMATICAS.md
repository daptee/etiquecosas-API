# Temáticas de PDF (ABM + sugerencias en el editor)

Catálogo nuevo de "temáticas" para el editor de PDF: nombre + paleta de colores + un set de íconos. Un producto (a nivel general) y/o una variante puntual pueden asociarse opcionalmente a una — es **completamente opcional** y no afecta nada si no se usa. Cuando está asociada, el editor puede pedir esa temática para sugerir sus colores/íconos al armar un diseño.

**No tiene relación con la tabla legacy `tematicas`** (la que usa el sistema viejo de `product_pdf`/vistas por temática) — son dos conceptos independientes.

---

## Esquema

- `pdf_themes`: `id`, `name` (único), `colors` (JSON, array de hex, ej. `["#FF9C8F", "#0000FF"]`), `status_id`, soft deletes.
- `pdf_theme_icons`: pivote `pdf_themes` ↔ `personalization_icons` (el catálogo de íconos ya existente), con `sort_order`.
- `products.theme_id`: FK nullable a `pdf_themes` — la temática general del producto.
- `product_variants.theme_id`: FK nullable a `pdf_themes` — la temática de ESA variante puntual. Si está seteada, **tiene prioridad** sobre la del producto.

---

## ABM — `PdfThemeController`

```
GET    /pdf-themes            Listado (search por name, paginado opcional con ?quantity=&page=)
POST   /pdf-themes            Crear
PUT    /pdf-themes/{id}       Editar
DELETE /pdf-themes/{id}       Eliminar (soft delete)
```

### Crear/editar

```json
POST /pdf-themes
{
  "name": "Fútbol",
  "colors": ["#FF0000", "#0000FF", "#00FF00"],
  "iconIds": [74, 12, 23]
}
```

| Campo | Tipo | Obligatorio | Descripción |
|---|---|---|---|
| `name` | string | Sí | Único. |
| `colors` | array de string | No | Colores hex, en el orden que se quieran sugerir. |
| `iconIds` | array de id | No | Ids de `personalization_icons` ya existentes. El orden del array define `sort_order`. |
| `statusId` | id | No | Default `1` (activo). |

La respuesta trae la temática con sus `icons` ya cargados (`id`, `name`, `icon`).

En `update`, si no se manda `iconIds` en el body, no se tocan los íconos ya asociados (solo se resincronizan si la clave está presente, aunque venga vacía — mandar `"iconIds": []` sí los saca a todos).

---

## Asociar a un producto o variante

Es un campo más del alta/edición normal de producto — no hay un endpoint aparte.

- **Producto (general)**: mandar `theme_id` en el body de `POST /products` o `PUT /products/{id}`, igual que `tag_id`.
- **Variante puntual**: mandar `theme_id` dentro de cada objeto del array `variants[]`, igual que `sku`/`price`/etc.

Ambos son opcionales e independientes — un producto puede tener su `theme_id` general y, aparte, algunas variantes con su propio `theme_id` distinto (esas pisan a la del producto para esa variante puntual).

---

## Sugerencia para el editor — `GET /pdf-themes/suggest`

```
GET /pdf-themes/suggest?productId=912&variantId=1059
```

| Parámetro | Obligatorio | Descripción |
|---|---|---|
| `productId` | Sí | — |
| `variantId` | No | Si se manda y esa variante tiene `theme_id`, se usa esa. Si no, se usa la del producto. |

Devuelve la `PdfTheme` completa (con `colors` e `icons`) o `data: null` si ni el producto ni la variante tienen una temática asociada.

**Esto es solo una sugerencia** — el front la usa para mostrar "elegí uno de estos colores/íconos" al armar un diseño nuevo para ese producto/variante, pero nada se aplica automáticamente a ningún elemento. No modifica `product_pdf_designs` ni genera nada por sí solo.

---

## Ejemplo de uso

```bash
# 1) Crear la temática
curl -X POST https://tu-dominio/api/pdf-themes \
  -H "Authorization: Bearer {token}" -H "Content-Type: application/json" \
  -d '{"name": "Fútbol", "colors": ["#FF0000", "#0000FF"], "iconIds": [74, 12]}'

# 2) Asociarla a un producto
curl -X PUT https://tu-dominio/api/products/92900 \
  -H "Authorization: Bearer {token}" -H "Content-Type: application/json" \
  -d '{"theme_id": 1, ...resto de los campos del producto...}'

# 3) El editor pide la sugerencia antes de armar/editar un diseño
curl https://tu-dominio/api/pdf-themes/suggest?productId=92900 \
  -H "Authorization: Bearer {token}"
```
