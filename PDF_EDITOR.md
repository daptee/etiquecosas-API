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

## 2. Diseños de PDF (`product_pdf_designs`)

Un diseño **no pertenece a un solo producto**: es una entidad independiente (una plantilla) que se puede crear sin asignarla a nada todavía, y después vincular a uno o varios productos. El vínculo entre un diseño y un producto vive en una tabla aparte, `product_pdf_design_products`, porque la variante/temática que selecciona ese diseño puede ser distinta en cada producto donde se usa.

### Modelo — `product_pdf_designs` (el diseño en sí)

| Campo | Tipo | Descripción |
|---|---|---|
| `labelShapeId` | number \| null | Forma/tamaño de etiqueta usada (`label_shapes.id`) |
| `name` | string | Nombre visible del diseño, ej. "Basquet - Maxi" |
| `data` | object | **El diseño en sí** (ver esquema abajo) |
| `isPublished` | boolean | `false` = borrador (el admin lo está editando). `true` = terminado, listo para producción |
| `statusId` | 1 \| 2 | 1 = activo, 2 = inactivo |

**Para que un diseño se use realmente al generar el PDF de una venta, tienen que darse las dos cosas: `isPublished: true` Y `statusId: 1`.** Cualquiera de las dos que falte (borrador sin publicar, o publicado pero desactivado) hace que el sistema caiga al flujo legacy para ese producto, como si no hubiera diseño nuevo. Esto te permite "pausar" un diseño ya publicado con `statusId: 2` sin perder el estado de publicado (y sin tener que despublicarlo y volver a publicarlo después).
| `products` | array | (solo lectura, viene incluido en las respuestas) los productos vinculados a este diseño, cada uno con su `pivot.themeKey` y `pivot.id` (el id del vínculo, para poder desvincularlo) |

Un diseño se puede crear **sin ningún producto vinculado** — sirve como borrador o como plantilla para reutilizar más adelante. No es necesario mandar `productId` al crearlo.

### Modelo — vínculo diseño↔producto (`product_pdf_design_products`)

| Campo | Tipo | Descripción |
|---|---|---|
| `productId` | number | Producto que usa este diseño |
| `themeKey` | number \| null | El `id` de `product_variants` (**no** de `attribute_values`) que, **en ese producto**, selecciona este diseño. `null` = ese producto usa el diseño sin selector de variante |
| `pageId` | string \| null | Si se manda, usa SOLO esa página de este diseño (`data.pages[].id`) en vez de todas sus páginas. Ver [PDF_PAGINAS_COMBINADAS.md](PDF_PAGINAS_COMBINADAS.md) |
| `sortOrder` | number (default `0`) | Orden de armado cuando un producto+variante combina páginas de varios vínculos/diseños. Ver [PDF_PAGINAS_COMBINADAS.md](PDF_PAGINAS_COMBINADAS.md) |

> Un mismo `productId` + `themeKey` solo puede estar vinculado a **un** diseño a la vez (si ya hay un vínculo con esa combinación, hay que desvincularlo antes de crear otro).

### Esquema de `data`

`data` es una lista de **páginas** (`pages`). Cada página tiene su propia hoja (`sheet`) y su propia lista de elementos. Un diseño con una sola página es el caso normal (un PDF de una hoja); si se necesita más de una página en el mismo PDF (por ejemplo, más copias de las que entran en una hoja, o combinar más de un layout en un mismo archivo), simplemente se agregan más entradas a `pages` — cada una se renderiza como una página nueva, en el orden en que aparecen en el array.

```json
{
  "pages": [
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
  ]
}
```

Un diseño de **dos páginas** es simplemente dos entradas en `pages`, cada una con su `sheet` + `elements` (pueden repetir el mismo layout o ser completamente distintos):

```json
{
  "pages": [
    { "sheet": { "width_cm": 18.5, "height_cm": 29 }, "elements": [ /* página 1 */ ] },
    { "sheet": { "width_cm": 18.5, "height_cm": 29 }, "elements": [ /* página 2 */ ] }
  ]
}
```

#### Campos de cada elemento (dentro de `pages[].elements`)

| Campo | Obligatorio | Descripción |
|---|---|---|
| `id` | recomendado | Identificador propio del elemento (útil para el editor, no lo usa el backend) |
| `type` | sí | `background` \| `icon` \| `text` \| `shape` |
| `x_cm`, `y_cm` | sí | Posición dentro de la hoja, en cm |
| `width_cm`, `height_cm` | sí | Tamaño del elemento, en cm |
| `z_index` | no (default 0) | Orden de apilado |
| `icon_id` | solo en `icon` | ID de `personalization_icons` (catálogo existente, `GET /api/icons`) |
| `font_id` | solo en `text` | ID de `typographies` (catálogo existente, `GET /api/typographies`). Se ignora si `dynamic_attribute_id` matchea un atributo de tipo tipografía de la variante |
| `dynamic_attribute_id` | opcional en `text`/`icon` | ID de un **atributo** (no de un valor) — usa la tipografía/ícono que trae el valor elegido de ese atributo en la variante de la venta, en vez de `font_id`/`icon_id` fijos. Ver [PDF_TIPOGRAFIA_POR_ATRIBUTO.md](PDF_TIPOGRAFIA_POR_ATRIBUTO.md) / [PDF_ICONO_PERSONALIZADO.md](PDF_ICONO_PERSONALIZADO.md) |
| `font_size_px` | solo en `text` | Tamaño de fuente por defecto (se usa si no hay `font_size_rules`, o si ninguna regla matchea) |
| `content` | solo en `text` | Texto literal. Si `value_mode` es `"fixed"` (o no se manda `value_mode`), se usa tal cual — también acepta los placeholders `{{customer_name}}` / `{{customer_first_name}}` / `{{customer_last_name}}` dentro del string (ver nota abajo), y `{{fecha}}` / `{{numero_pedido}}` / `{{id_producto}}` (`products.id` del producto comprado) con los datos de la venta. **Se respeta mayúscula/minúscula tal cual se tipea** (a diferencia de las vistas legacy, que siempre fuerzan mayúsculas) — si querés "CIRO" en mayúscula, escribilo así. **Nunca puede llevar HTML** — se limpia en el servidor |
| `value_mode` | solo en `text`, opcional (default `"fixed"`) | `"fixed"` = usar `content` literal. Cualquier otro valor (ej. `"dynamic"`) + `dynamic_field` = ignorar `content` y usar el dato real del cliente |
| `dynamic_field` | solo en `text`, requerido si `value_mode` no es `"fixed"` | `nombre_apellido` (nombre completo) \| `nombre` (solo nombre) \| `apellido` (solo apellido) \| `fecha` (fecha de la venta, `d/m/Y`) \| `numero_pedido` (id de la venta). Cualquier otro valor se descarta en el servidor (queda `null`, se usa `content` como fallback) |
| `max_lines` | solo en `text`, opcional (default `3`) | Máximo de renglones en los que se puede partir el texto. Se clampea entre 1 y 20 |
| `min_lines` | solo en `text`, opcional (default `1`) | Mínimo de renglones — si el texto entra en menos, se completa con renglones vacíos para reservar el espacio. Se clampea entre 1 y 20 |
| `max_chars_per_line` | solo en `text`, opcional (default `10`) | Caracteres por renglón antes de cortar a la siguiente palabra. Se clampea entre 1 y 200 |
| `font_size_rules` (alias: `length_rules` + `length_rules_enabled`) | solo en `text`, opcional | Escala de tamaño de fuente según la cantidad de caracteres del texto resuelto — ver ejemplo abajo. Si `length_rules_enabled` es `false`, se ignoran las reglas aunque estén cargadas (útil para guardarlas armadas pero desactivadas). Si no se manda nada de esto, se usa siempre `font_size_px` |
| `text_align` | solo en `text`, opcional (default `center`) | `left` \| `center` \| `right` |
| `vertical_align` | solo en `text`, opcional (default `middle`) | `top` \| `middle` \| `bottom` — centrado real dentro de la caja del elemento |
| `vertical_offset_cm` | solo en `text`, opcional (default `0`) | Corrimiento fino vertical adicional (en cm), para ajustar ópticamente sin mover `y_cm`. Se clampea entre -50 y 50 |
| `font_weight` | solo en `text`, opcional (default `400`) | Grosor de fuente (100 a 900, como CSS `font-weight`) |
| `line_height` | solo en `text`, opcional (default `1.15`) | Interlineado fijo — mismo número que CSS `line-height` (sin unidad, multiplica el tamaño de fuente). Se usa si no hay `line_height_rules`, o si ninguna regla matchea. Se clampea entre 0.5 y 5. Ver [PDF_INTERLINEADO_ESPACIADO.md](PDF_INTERLINEADO_ESPACIADO.md) |
| `line_height_rules` | solo en `text`, opcional | Interlineado según la cantidad de caracteres del texto resuelto — mismo esquema que `font_size_rules`. Ver [PDF_INTERLINEADO_ESPACIADO.md](PDF_INTERLINEADO_ESPACIADO.md) |
| `letter_spacing_px` | solo en `text`, opcional (default `0`) | Espacio extra entre LETRAS, en px, además del espacio normal de la fuente. Se usa si no hay `letter_spacing_rules`, o si ninguna regla matchea. Se clampea entre -50 y 200. Ver [PDF_INTERLINEADO_ESPACIADO.md](PDF_INTERLINEADO_ESPACIADO.md) |
| `letter_spacing_rules` | solo en `text`, opcional | Espaciado entre letras según la cantidad de caracteres del texto resuelto — mismo esquema que `font_size_rules`/`line_height_rules`. Ver [PDF_INTERLINEADO_ESPACIADO.md](PDF_INTERLINEADO_ESPACIADO.md) |
| `padding_cm` | opcional en `background`, opcional (default `0`) | Insetea el color hacia adentro de su propia caja (por ejemplo, para dejar un borde/margen visible alrededor). Se clampea entre 0 y 50 |
| `rotation_deg` | opcional en cualquier tipo (default `0`) | Rotación del elemento en grados, sobre su propio centro. Se clampea entre -360 y 360 |
| `color` | en `background`/`text` | `{ "mode": "hex" \| "cmyk", "value": "..." }`. Para `cmyk`, `value` es `"c,m,y,k"` (0 a 1), igual que ya usan las vistas legacy |
| `label_shape_id` | opcional en `background` | Referencia a `label_shapes.id` si ese elemento representa una forma del catálogo |
| `background_image` | opcional en `background` | Ruta de una imagen propia de ESTE elemento (subida por `POST .../element-image`) — reemplaza el `color` como relleno. Ver [PDF_IMAGENES_ETIQUETA_E_ICONO.md](PDF_IMAGENES_ETIQUETA_E_ICONO.md) |
| `custom_icon_path` | opcional en `icon` | Ruta de una imagen propia de ESTE elemento (subida por `POST .../element-image`, NO aparece en `GET /api/icons`) — reemplaza `icon_id`. Ver [PDF_IMAGENES_ETIQUETA_E_ICONO.md](PDF_IMAGENES_ETIQUETA_E_ICONO.md) |
| `group_id` | opcional | Puramente organizativo para el editor (agrupar los elementos de una misma etiqueta física); el backend no lo usa. Para que varios elementos mantengan una distancia fija entre sí y queden centrados en su etiqueta, ver `layout_groups` en [PDF_LAYOUT_GROUPS.md](PDF_LAYOUT_GROUPS.md) |
| `editable_by_customer` | opcional (default `false`) | Si es `true`, el cliente puede modificar este elemento en el checkout |
| `editable_field` | requerido si `editable_by_customer` es `true` | `text` \| `color` \| `icon` — qué puede cambiar el cliente en ese elemento |

⚠️ **Importante sobre `dynamic_field` + cajas angostas**: si el texto real del cliente (nombre o apellido) es más largo que el string de prueba que usaste al diseñar, y no entra en `width_cm` al `font_size_px` configurado, el texto se corta horizontalmente (una palabra sin espacios no se puede partir en renglones). Para evitar esto con apellidos largos, activá `length_rules_enabled: true` con reglas que reduzcan la fuente según la cantidad de caracteres, o dejá el `width_cm` con margen de sobra.

**Nombre y apellido en posiciones separadas**: si querés poner el nombre en un extremo de la etiqueta y el apellido en otro, usá **dos elementos `text` distintos**, cada uno con su propio `x_cm`/`y_cm`. Hay dos formas equivalentes de decirle al backend qué mostrar en cada uno:

- Con `value_mode`/`dynamic_field` (la forma que usa hoy el editor):
  ```json
  { "type": "text", "value_mode": "dynamic", "dynamic_field": "nombre", "content": "NOMBRE EJEMPLO", "x_cm": 0.3, "y_cm": 0.3, "width_cm": 2, "height_cm": 1, "font_size_px": 40 },
  { "type": "text", "value_mode": "dynamic", "dynamic_field": "apellido", "content": "APELLIDO EJEMPLO", "x_cm": 3.5, "y_cm": 1.8, "width_cm": 2, "height_cm": 1, "font_size_px": 40 }
  ```
  `content` acá es solo un texto de referencia para cuando se previsualiza/edita sin datos reales — al generar el PDF real se ignora y se usa el nombre/apellido del cliente.

- Con placeholders dentro de `content` (forma anterior, se sigue soportando):
  ```json
  { "type": "text", "content": "{{customer_first_name}}", "x_cm": 0.3, "y_cm": 0.3, "width_cm": 2, "height_cm": 1, "font_size_px": 40 },
  { "type": "text", "content": "{{customer_last_name}}", "x_cm": 3.5, "y_cm": 1.8, "width_cm": 2, "height_cm": 1, "font_size_px": 40 }
  ```

`{{customer_name}}` / `dynamic_field: "nombre_apellido"` siguen existiendo para cuando querés nombre y apellido juntos en un solo bloque (con el corte automático nombre/apellido de `formatName()`). El nombre o el apellido solos (`nombre`/`apellido`/`{{customer_first_name}}`/`{{customer_last_name}}`) son texto plano — cada uno se corta solo por palabras si no entra en `max_chars_per_line` (normalmente no hace falta, un nombre o apellido solo rara vez necesita más de un renglón, pero si es más largo que la caja, conviene `length_rules` — ver la advertencia arriba).

**`max_lines` / `min_lines` / `max_chars_per_line`**: el corte de renglones usa `formatName()` — solo el nombre completo (`dynamic_field: "nombre_apellido"` o `content: "{{customer_name}}"`) activa el corte especial de nombre/apellido (máximo 2 renglones reales aunque `max_lines` sea mayor); cualquier otro texto se corta por palabras completas. El detalle completo con ejemplos está en [FORMATO_NOMBRES_PDF.md](FORMATO_NOMBRES_PDF.md).

**`font_size_rules`** — lista ordenada por `max_chars` (de menor a mayor); se usa la primera regla cuyo `max_chars` sea mayor o igual a la cantidad de caracteres del texto. Una regla sin `max_chars` (o `null`) actúa como "para el resto" y conviene ponerla al final:

```json
{
  "type": "text",
  "content": "{{customer_name}}",
  "font_id": 5,
  "font_size_px": 40,
  "max_lines": 2,
  "min_lines": 1,
  "max_chars_per_line": 10,
  "font_size_rules": [
    { "max_chars": 5, "font_size_px": 80 },
    { "max_chars": 10, "font_size_px": 50 },
    { "max_chars": null, "font_size_px": 30 }
  ]
}
```
Con esa config: "ANA" (3 caracteres) sale con `font_size_px: 80`, "GUILLERMINA" (11 caracteres) con `30`.

**Importante sobre `editable_by_customer`**: esto reemplaza, de forma explícita por elemento, lo que hoy el checkout manda de forma implícita en `customization_data` (`form.name`, `color.color_code`, `icon.icon`). El front del storefront debe:
- Mostrar el selector de color solo si algún elemento tiene `editable_field: "color"`.
- Mostrar el selector de ícono solo si algún elemento tiene `editable_field: "icon"`.
- El campo de nombre del cliente sigue siendo siempre editable (se inyecta vía `{{customer_name}}`).

Un elemento **sin** `editable_by_customer: true` siempre se renderiza con lo que definió el admin en el editor, sin importar qué mande el checkout.

### Endpoints

Base: `/api/product-pdf-designs` (JWT). Lectura pública sin auth: `GET /api/v1/product-pdf-designs`.

| Método | Ruta | Acción |
|---|---|---|
| GET | `/product-pdf-designs?productId=123` | Listar (opcionalmente filtra solo los diseños vinculados a ese producto) |
| GET | `/product-pdf-designs/{id}` | Detalle (incluye `products` con sus vínculos) |
| POST | `/product-pdf-designs` | Crear el diseño (sin producto todavía) |
| POST | `/product-pdf-designs/{id}` | Actualizar el diseño |
| PATCH | `/product-pdf-designs/{id}/toggle-status` | Activar/desactivar |
| DELETE | `/product-pdf-designs/{id}` | Eliminar (soft delete; borra en cascada sus vínculos con productos) |
| GET | `/product-pdf-designs/{id}/preview?name=JUAN` | Genera y devuelve un PDF de muestra con ese diseño |
| POST | `/product-pdf-designs/{id}/products` | Vincular el diseño a un producto |
| DELETE | `/product-pdf-designs/{id}/products/{linkId}` | Desvincular (por el id del vínculo, no del producto) |

**1. Crear el diseño** — `POST /api/product-pdf-designs`

```json
{
  "labelShapeId": 12,
  "name": "Basquet - Maxi",
  "data": {
    "pages": [
      {
        "sheet": { "width_cm": 18.5, "height_cm": 29, "columns": 3, "rows": 5 },
        "elements": [
          { "type": "background", "x_cm": 0.5, "y_cm": 0.5, "width_cm": 4.4, "height_cm": 2.4, "color": { "mode": "hex", "value": "#F5D033" } },
          { "type": "icon", "icon_id": 34, "x_cm": 0.6, "y_cm": 0.6, "width_cm": 1.2, "height_cm": 1.2 },
          { "type": "text", "content": "{{customer_name}}", "font_id": 5, "font_size_px": 46, "x_cm": 2, "y_cm": 0.6, "width_cm": 2.2, "height_cm": 1.6, "color": { "mode": "hex", "value": "#FFFFFF" }, "editable_by_customer": true, "editable_field": "text" }
        ]
      }
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
    "labelShapeId": 12,
    "name": "Basquet - Maxi",
    "data": { "...": "..." },
    "isPublished": false,
    "statusId": 1,
    "products": [],
    "labelShape": { "...": "..." },
    "generalStatus": { "...": "..." }
  },
  "metaData": null
}
```

**2. Vincularlo a uno o más productos** — `POST /api/product-pdf-designs/1/products`

```json
{ "productId": 12409, "themeKey": 137 }
```

Repetir la llamada con otro `productId` para reutilizar el mismo diseño en otro producto (puede llevar un `themeKey` distinto, o ninguno). La respuesta trae el diseño con `products` actualizado:

```json
{
  "message": "Producto vinculado al diseño",
  "data": {
    "id": 1,
    "name": "Basquet - Maxi",
    "products": [
      { "id": 12409, "name": "...", "pivot": { "id": 1, "themeKey": 137 } }
    ]
  }
}
```

`pivot.id` es el `linkId` que hay que usar para desvincular: `DELETE /api/product-pdf-designs/1/products/1`.

**3. Publicarlo** — cuando el admin termina de editar y quiere que empiece a usarse en ventas reales: `POST /product-pdf-designs/{id}` con `{"isPublished": true}`. Hasta que no está publicado, el diseño puede estar vinculado a productos pero no se usa para generar PDFs reales.

**Preview**: `GET /api/product-pdf-designs/{id}/preview?name=JUAN` devuelve directamente el archivo PDF renderizado (no un JSON), usando el mismo motor que se usa en la generación real — sirve para que el editor muestre "así queda" antes de publicar. No requiere que el diseño esté vinculado a ningún producto todavía.

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

1. Se busca en `product_pdf_design_products` un vínculo para ese producto (y esa variante, si tiene) cuyo diseño esté `isPublished: true` y `statusId: 1` (activo).
2. Si existe → se genera el PDF con el diseño nuevo (reemplazando `{{customer_name}}`, resolviendo íconos/tipografías, aplicando los overrides del cliente solo en los campos marcados como editables).
3. Si no existe → se sigue generando el PDF exactamente como antes, con `product_pdf` y las vistas por temática.

No hace falta que el front haga nada especial acá: esto pasa automáticamente según si el producto tiene o no un vínculo a un diseño publicado.
