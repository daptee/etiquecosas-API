# Editor de PDFs de etiquetas por producto

## Descripción

Antes, el diseño del PDF de cada producto vivía "a mano" en `product_pdf` (JSON armado a mano) apuntando a una vista Blade fija por temática. Esta funcionalidad nueva permite armar ese diseño **desde un editor visual en el front**: elegir íconos, colores, tipografías y la forma/tamaño de la etiqueta ("tag"), y guardar todo en dos tablas nuevas que **no reemplazan ni modifican `product_pdf`**.

Regla clave: si un producto tiene un diseño publicado en la tabla nueva, se usa ese diseño al generar el PDF de una venta. Si no tiene, el sistema sigue el flujo legacy exactamente igual que antes. Cero riesgo para los productos que ya existen.

El diseño se guarda como **JSON estructurado** (una lista de elementos con posición/tamaño/color/fuente/ícono), nunca como HTML o código. Esto es intencional: el backend nunca ejecuta ni persiste markup que venga del front, solo datos que una única vista Blade genérica interpreta.

---

## 1. Formas de etiqueta (`label_shapes`)

Catálogo de tamaños/formas de etiqueta ("tags"). Puede haber formas precargadas (`isSystem: true`) o creadas por un admin desde el editor (`isSystem: false`).

### Modelo

| Campo | Tipo | Descripción |
|---|---|---|
| `name` | string | Nombre visible, ej. "Maxi rectangular" |
| `shapeType` | `rect` \| `circle` \| `custom` | Forma base |
| `widthCm` / `heightCm` | number | Tamaño físico en cm |
| `isSystem` | boolean | `true` = precargada, `false` = creada por un admin |
| `data` | object | Config adicional (grilla por defecto, radio de esquina, SVG si es `custom`) |
| `statusId` | 1 \| 2 | 1 = activo, 2 = inactivo |

### Ejemplo de `data`

```json
{
  "corner_radius_cm": 0.2,
  "default_grid": { "columns": 3, "rows": 5, "gap_x_cm": 0.4, "gap_y_cm": 0.4 },
  "outline_svg": null
}
```

Si `shapeType` es `custom`, `outline_svg` puede llevar un path SVG (solo primitivos `path`/`rect`/`circle`/`ellipse`/`polygon`/`polyline`/`g` — cualquier otra etiqueta o atributo `on*=`/`href` se elimina en el servidor).

### Endpoints

Base: `/api/label-shapes` (JWT). Lectura pública sin auth: `GET /api/v1/label-shapes`.

| Método | Ruta | Acción |
|---|---|---|
| GET | `/label-shapes` | Listar (soporta `?search=`, `?statusId=`, `?quantity=` y `?page=` para paginar) |
| GET | `/label-shapes/{id}` | Detalle |
| POST | `/label-shapes` | Crear |
| POST | `/label-shapes/{id}` | Actualizar |
| PATCH | `/label-shapes/{id}/toggle-status` | Activar/desactivar |
| DELETE | `/label-shapes/{id}` | Eliminar (soft delete) |

**POST `/api/label-shapes`**

```json
{
  "name": "Maxi rectangular",
  "shapeType": "rect",
  "widthCm": 4.4,
  "heightCm": 2.4,
  "isSystem": false,
  "data": {
    "corner_radius_cm": 0.2,
    "default_grid": { "columns": 3, "rows": 5 }
  },
  "statusId": 1
}
```

---

## 2. Diseños de PDF por producto (`product_pdf_designs`)

Un diseño armado en el editor para un producto puntual. **Una fila por diseño-variante** — igual que hoy un producto puede tener varias temáticas seleccionables, acá puede tener varios diseños (uno por variante, o uno único sin variante).

### Modelo

| Campo | Tipo | Descripción |
|---|---|---|
| `productId` | number | Producto al que pertenece (requerido) |
| `labelShapeId` | number \| null | Forma/tamaño de etiqueta usada (`label_shapes.id`) |
| `themeKey` | number \| null | ID de la variante/temática que selecciona este diseño (el mismo id que hoy usan las `attribute_values` de una variante). `null` = diseño único, sin selector |
| `name` | string | Nombre visible del diseño, ej. "Basquet - Maxi" |
| `data` | object | **El diseño en sí** (ver esquema abajo) |
| `isPublished` | boolean | `false` = borrador (el admin lo está editando, no se usa para generar PDFs reales). `true` = se usa en la generación real de la próxima venta |
| `statusId` | 1 \| 2 | 1 = activo, 2 = inactivo |

> Un producto solo puede tener **un** diseño por combinación de `productId` + `themeKey` (incluyendo un único diseño sin `themeKey`).

### Esquema de `data`

```json
{
  "sheet": {
    "width_cm": 18.5,
    "height_cm": 29,
    "columns": 3,
    "rows": 5
  },
  "elements": [
    {
      "id": "el-1",
      "type": "background",
      "label_shape_id": 12,
      "x_cm": 0.5,
      "y_cm": 0.5,
      "width_cm": 4.4,
      "height_cm": 2.4,
      "z_index": 0,
      "color": { "mode": "cmyk", "value": "0.01,0.25,0.23,0" }
    },
    {
      "id": "el-2",
      "type": "icon",
      "icon_id": 34,
      "x_cm": 0.6,
      "y_cm": 0.6,
      "width_cm": 1.2,
      "height_cm": 1.2,
      "z_index": 1,
      "editable_by_customer": true,
      "editable_field": "icon"
    },
    {
      "id": "el-3",
      "type": "text",
      "content": "{{customer_name}}",
      "x_cm": 2.0,
      "y_cm": 0.6,
      "width_cm": 2.2,
      "height_cm": 1.6,
      "font_id": 5,
      "font_size_px": 46,
      "z_index": 2,
      "color": { "mode": "hex", "value": "#FFFFFF" },
      "editable_by_customer": true,
      "editable_field": "text"
    }
  ]
}
```

#### Campos de cada elemento

| Campo | Obligatorio | Descripción |
|---|---|---|
| `id` | recomendado | Identificador propio del elemento (útil para el editor, no lo usa el backend) |
| `type` | sí | `background` \| `icon` \| `text` \| `shape` |
| `x_cm`, `y_cm` | sí | Posición dentro de la hoja, en cm |
| `width_cm`, `height_cm` | sí | Tamaño del elemento, en cm |
| `z_index` | no (default 0) | Orden de apilado |
| `icon_id` | solo en `icon` | ID de `personalization_icons` (catálogo existente, `GET /api/icons`) |
| `font_id` | solo en `text` | ID de `typographies` (catálogo existente, `GET /api/typographies`) |
| `font_size_px` | solo en `text` | Tamaño de fuente |
| `content` | solo en `text` | Texto o placeholder. Hoy el único placeholder soportado es `{{customer_name}}` (se reemplaza por el nombre que puso el cliente en el checkout). **Nunca puede llevar HTML** — se limpia en el servidor |
| `color` | en `background`/`text` | `{ "mode": "hex" \| "cmyk", "value": "..." }`. Para `cmyk`, `value` es `"c,m,y,k"` (0 a 1), igual que ya usan las vistas legacy |
| `label_shape_id` | opcional en `background` | Referencia a `label_shapes.id` si ese elemento representa una forma del catálogo |
| `editable_by_customer` | opcional (default `false`) | Si es `true`, el cliente puede modificar este elemento en el checkout |
| `editable_field` | requerido si `editable_by_customer` es `true` | `text` \| `color` \| `icon` — qué puede cambiar el cliente en ese elemento |

**Importante sobre `editable_by_customer`**: esto reemplaza, de forma explícita por elemento, lo que hoy el checkout manda de forma implícita en `customization_data` (`form.name`, `color.color_code`, `icon.icon`). El front del storefront debe:
- Mostrar el selector de color solo si algún elemento tiene `editable_field: "color"`.
- Mostrar el selector de ícono solo si algún elemento tiene `editable_field: "icon"`.
- El campo de nombre del cliente sigue siendo siempre editable (se inyecta vía `{{customer_name}}`).

Un elemento **sin** `editable_by_customer: true` siempre se renderiza con lo que definió el admin en el editor, sin importar qué mande el checkout.

### Endpoints

Base: `/api/product-pdf-designs` (JWT). Lectura pública sin auth: `GET /api/v1/product-pdf-designs`.

| Método | Ruta | Acción |
|---|---|---|
| GET | `/product-pdf-designs?productId=123` | Listar (filtra por producto) |
| GET | `/product-pdf-designs/{id}` | Detalle |
| POST | `/product-pdf-designs` | Crear |
| POST | `/product-pdf-designs/{id}` | Actualizar |
| PATCH | `/product-pdf-designs/{id}/toggle-status` | Activar/desactivar |
| DELETE | `/product-pdf-designs/{id}` | Eliminar (soft delete) |
| GET | `/product-pdf-designs/{id}/preview?name=JUAN` | Genera y devuelve un PDF de muestra con ese diseño |

**POST `/api/product-pdf-designs`**

```json
{
  "productId": 12409,
  "labelShapeId": 12,
  "themeKey": 137,
  "name": "Basquet - Maxi",
  "data": {
    "sheet": { "width_cm": 18.5, "height_cm": 29, "columns": 3, "rows": 5 },
    "elements": [
      { "type": "background", "x_cm": 0.5, "y_cm": 0.5, "width_cm": 4.4, "height_cm": 2.4, "color": { "mode": "hex", "value": "#F5D033" } },
      { "type": "icon", "icon_id": 34, "x_cm": 0.6, "y_cm": 0.6, "width_cm": 1.2, "height_cm": 1.2 },
      { "type": "text", "content": "{{customer_name}}", "font_id": 5, "font_size_px": 46, "x_cm": 2, "y_cm": 0.6, "width_cm": 2.2, "height_cm": 1.6, "color": { "mode": "hex", "value": "#FFFFFF" }, "editable_by_customer": true, "editable_field": "text" }
    ]
  },
  "isPublished": false,
  "statusId": 1
}
```

Respuesta (200):

```json
{
  "message": "Diseño de PDF creado",
  "data": {
    "id": 1,
    "productId": 12409,
    "labelShapeId": 12,
    "themeKey": 137,
    "name": "Basquet - Maxi",
    "data": { "...": "..." },
    "isPublished": false,
    "statusId": 1,
    "product": { "...": "..." },
    "labelShape": { "...": "..." },
    "generalStatus": { "...": "..." }
  },
  "metaData": null
}
```

Cuando el admin termina de editar y quiere que ese diseño empiece a usarse en ventas reales, se marca `isPublished: true` (vía `POST /product-pdf-designs/{id}` con `{"isPublished": true}`).

**Preview**: `GET /api/product-pdf-designs/{id}/preview?name=JUAN` devuelve directamente el archivo PDF renderizado (no un JSON), usando el mismo motor que se usa en la generación real — sirve para que el editor muestre "así queda" antes de publicar.

---

## 3. Catálogos que ya existen (el editor los reutiliza, no hay tablas nuevas)

| Qué | Catálogo | Endpoint de lectura |
|---|---|---|
| Íconos | `personalization_icons` | `GET /api/icons` |
| Colores predefinidos | `personalization_colors` | `GET /api/colors` |
| Tipografías | `typographies` (+ archivos de fuente) | `GET /api/typographies` |

El front debe usar los `id` que devuelven estos endpoints para completar `icon_id` y `font_id` en los elementos del diseño.

---

## 4. Qué pasa al momento de generar el PDF (informativo, no es una API)

Al aprobarse/generarse una venta, por cada producto comprado:

1. Se busca si el producto (y su variante, si tiene) tiene un `product_pdf_designs` con `isPublished: true`.
2. Si existe → se genera el PDF con el diseño nuevo (reemplazando `{{customer_name}}`, resolviendo íconos/tipografías, aplicando los overrides del cliente solo en los campos marcados como editables).
3. Si no existe → se sigue generando el PDF exactamente como antes, con `product_pdf` y las vistas por temática.

No hace falta que el front haga nada especial acá: esto pasa automáticamente según si el producto tiene o no un diseño publicado.
