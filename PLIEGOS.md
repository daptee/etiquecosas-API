# Pliegos

Un **pliego** es una plancha grande de imprenta (ej. 1 m × 60 cm) dividida en una grilla de **lugares** (columnas × filas). En cada lugar va **una hoja**: una página de un PDF ya generado de una venta (`storage/app/pdf/planchas/{d-m-Y}/{venta}-{saleProduct}-….pdf`, los mismos que lista el módulo de directorios).

Flujo: elegir hoja y distribución → ubicar hojas de ventas → guardar como borrador → finalizar (opcionalmente pasando las ventas a "En producción") → descargar el PDF para imprenta.

---

## Base de datos (correr en este orden)

1. `database/sql/alter_pdf_sheet_sizes_add_default_grid.sql`: agrega `default_columns` y `default_rows` a `pdf_sheet_sizes` y precarga los 3 tamaños de tipo "Pliego" (1 m × 60 cm 5×2, 1 m × 70 cm 5×2, 70 cm × 1 m 3×3).
2. `database/sql/pliegos.sql`: tablas `pliegos` y `pliego_slots`.
3. `database/sql/alter_pliegos_add_cut_lines.sql`: columna `cut_lines` (JSON) en `pliegos`.

- `pliegos`: nombre, `sheet_size_id` (solo informativo), `width_cm`, `height_cm`, `columns`, `rows`, `margin_mm`, `gap_mm`, `cut_lines`, `status` (`borrador` | `finalizado`), `finalized_at`, `finalized_by`, `created_by`, soft delete.
- `pliego_slots`: un lugar **ocupado** (los vacíos no tienen fila): `position` (0-based, izquierda→derecha, arriba→abajo), `sale_id`, `sale_product_id`, `pdf_folder`, `pdf_file`, `page_number`.
- **Una hoja solo puede estar en un pliego a la vez**: restricción única en (`pdf_folder`, `pdf_file`, `page_number`). Al eliminar un pliego se borran sus lugares y las hojas vuelven a estar disponibles.

---

## Hojas

Una hoja se identifica por `folder` + `file` + `page`. El back también devuelve `key` = `"{folder}/{file}#{page}"` para usar como id en el front.

- Qué PDFs cuentan: los de la carpeta de la fecha de aprobación y los de la fecha de creación de la venta, con nombre `{venta}-{saleProduct}-*.pdf`. Se excluyen los `-CORTE-`, los temporales y los `preview`.
- **No aparecen como hojas de la venta:** cintas, bandas, sellos y diseños acumulables, porque van a PDFs consolidados por día y no a un PDF por venta. Esos productos salen con `sheets: []` ("Sin PDF").
- **Vista previa:** cada hoja trae `view_url` (`/api/pdf-directories/{folder}/view/{file}`), que es el endpoint de directorios que ya existe. El front renderiza la página `page` con pdf.js.
- **Medidas:** `width_mm` y `height_mm` son el tamaño real de esa página.
- **Escala:** al ubicar una hoja en un lugar se recortan 7,5 mm por lado: el contenido de un A4 ocupa unos 195 mm de ancho. La hoja **nunca se agranda**, solo se achica si no entra: `escala = min(1, anchoLugar / (anchoHoja − 15), altoLugar / altoHoja)`. Así, en un pliego de 1 m × 60 cm en 5×2 (lugares de 200 × 300 mm), un A4 entra a tamaño real.
- **PDF regenerado:** si se regenera el PDF de una venta, normalmente se conserva el nombre del archivo y el pliego toma la versión nueva. Si el archivo desaparece, el lugar vuelve con `missing: true` y la descarga falla con 422 hasta que se saque esa hoja.

---

## Endpoints (`jwt.auth`)

```
GET    /pliegos                     Listado (search, status, paginado opcional quantity/page)
GET    /pliegos/available-sheets    Ventas con sus hojas (panel lateral del armado)
GET    /pliegos/{id}                Detalle con lugares enriquecidos
POST   /pliegos                     Crear (borrador)
POST   /pliegos/{id}                Editar (nombre, hoja, distribución y/o lugares)
GET    /pliegos/{id}/involved-sales Ventas para el modal "Finalizar"
POST   /pliegos/{id}/finalize       Finalizar + pasar ventas a "En producción"
POST   /pliegos/{id}/reopen         Volver a borrador
DELETE /pliegos/{id}                Eliminar (libera las hojas)
GET    /pliegos/{id}/download       PDF del pliego (?mode=art|cut|both, ?guides=1)
POST   /pliegos/download            ZIP con varios pliegos  { "ids": [1, 2], "mode": "art" }
GET    /pliegos/{id}/cut-lines      Líneas de corte del pliego en JSON (mm del pliego)
GET    /pliegos/sheet-cut-lines     Líneas de corte de UNA hoja (?folder=&file=&page=)
GET    /pliegos/design-cut-lines    Líneas de corte de CUALQUIER diseño (?designId=&pageId=)
```

### Convenciones

- **Respuestas:** usan el formato de siempre, `{ message, data, metaData }`. Una validación fallida devuelve 422 con `{ message, errors: { campo: [mensajes] } }`, y los demás errores `{ message, data? }`.
- **Booleanos en query string:** mandar `1` o `0`, por ejemplo `hideAssigned=0`. Los strings `"true"` y `"false"` no pasan la validación.
- **Arrays en query string:** `statusIds[]=1&statusIds[]=3`.
- **`view_url` necesita el token:** pdf.js no puede abrir la URL tal cual. Hay que pedirla con el header `Authorization` (`httpHeaders` de pdf.js, o un `fetch` a blob) igual que hoy en directorios.
- **Descargas** (`download`, `POST /download`): si salen bien, son un blob. Si fallan, son un JSON con status 422 o 500. Con `responseType: 'blob'`, leer el blob como texto y parsearlo para mostrar `message`. El nombre del archivo viene en `Content-Disposition`.

### Cómo corresponde cada acción del prototipo

| Pantalla | Acción | Endpoint |
|---|---|---|
| Listado | Tarjetas, búsqueda | `GET /pliegos?search=` |
| Listado | Renombrar | `POST /pliegos/{id}` `{ name }` |
| Listado | Marcar como finalizado | Modal: `GET /pliegos/{id}/involved-sales`, luego `POST /pliegos/{id}/finalize` |
| Listado | Volver a borrador | `POST /pliegos/{id}/reopen` |
| Listado | Eliminar | `DELETE /pliegos/{id}` → `data: { id }` |
| Listado | Descargar uno / varios seleccionados | `GET /pliegos/{id}/download` / `POST /pliegos/download` |
| Nuevo pliego | Tamaños | `GET /pdf-sheet-sizes?typeId=…` |
| Editar | Abrir en el paso 2 | `GET /pliegos/{id}` |
| Paso 2 | Panel lateral | `GET /pliegos/available-sheets?pliegoId=…` |
| Paso 2 | Arrastrar, intercambiar, sacar, Autocompletar, Vaciar | Solo en el front. Se envía todo al guardar, en `slots`. |
| Paso 2 | Separaciones | Solo en el front, con la geometría de abajo. |
| Paso 2 | Líneas de corte | `GET /pliegos/{id}/cut-lines`, o `sheet-cut-lines` / `design-cut-lines` antes de guardar. |
| Asistente | Guardar como borrador | `POST /pliegos` (nuevo) o `POST /pliegos/{id}` |
| Asistente | Guardar y finalizar | Guardar, luego el modal de finalizar (igual que en el listado) |
| Asistente | Guardar (pliego ya finalizado) | `POST /pliegos/{id}` |
| Asistente | Cancelar | Solo en el front: descarta los cambios. |

### Geometría (para dibujar igual que el PDF)

Todo en mm. `W = width_cm × 10` y `H = height_cm × 10`.

```
anchoLugar = (W − 2·margin_mm − (columns − 1)·gap_mm) / columns
altoLugar  = (H − 2·margin_mm − (rows − 1)·gap_mm) / rows
lugar i:  col = i % columns,  fila = floor(i / columns)
          x = margin_mm + col·(anchoLugar + gap_mm)
          y = margin_mm + fila·(altoLugar + gap_mm)
```

**Ubicación de una hoja** (`placement`) de `anchoHoja × altoHoja` en un lugar:

```
contenido = anchoHoja > 16 ? anchoHoja − 15 : anchoHoja        // 7,5 mm por lado
escala    = min(1, anchoLugar / contenido, altoLugar / altoHoja)
x         = lugar.x + (anchoLugar − anchoHoja·escala) / 2
y         = lugar.y + (altoLugar − altoHoja·escala) / 2
```

- La hoja se dibuja en `(x, y)` con tamaño `anchoHoja·escala × altoHoja·escala`, **recortada al rectángulo del lugar**.
- Las líneas de corte de esa hoja, que vienen en mm sobre la hoja, se dibujan en `(x + px·escala, y + py·escala)`.
- **Rotaciones** (`rotation_deg` de `designs` y `shapes`): en grados, en sentido horario con Y hacia abajo, igual que `transform: rotate()` de CSS y SVG. Se aplican alrededor del centro.

### Tamaños de pliego

Los tamaños se cargan con `GET /pdf-sheet-sizes?typeId={id del tipo "Pliego"}`. Cada uno trae `width_cm`, `height_cm`, `default_columns` y `default_rows`. Para la opción "Personalizada", el front manda ancho y alto a mano con `sheetSizeId: null`. En el ABM de tamaños se pueden mandar también `defaultColumns` y `defaultRows`.

### Listado: `GET /pliegos`

| Parámetro | Descripción |
|---|---|
| `search` | Busca por nombre. |
| `status` | `borrador` o `finalizado`. |
| `quantity`, `page` | Paginado (opcional). |

Cada pliego trae sus campos más:
- `total_slots` y `filled_slots`: para el texto "8/10 hojas".
- `sheet_size`.
- `slots[]` con `position`, `key`, `folder`, `file`, `page`, `sale_id` y `view_url`: para la miniatura de la tarjeta.

### Panel lateral: `GET /pliegos/available-sheets`

| Parámetro | Default | Descripción |
|---|---|---|
| `pliegoId` | — | El pliego que se está editando: sus hojas vuelven con `state: "here"`. |
| `search` | — | Nº de venta, nombre o apellido del cliente, o nombre de producto. |
| `hideAssigned` | `true` | Oculta las ventas con **todas** sus hojas en otros pliegos. |
| `statusIds[]` | `[1, 2]` | Estados de venta puntuales. Sin mandar nada: Aprobado y En producción. |
| `allStatuses` | `false` | `1` = ventas en **cualquier** estado (ignora `statusIds`). Los estados salen de `GET /sales/statuses`, el mismo catálogo de ventas. |
| `onlyMine` | `false` | `1` = solo las ventas asignadas al usuario logueado (`sales.user_id`). |
| `userId` | — | Solo las ventas asignadas a ese usuario (si no viene `onlyMine`). |
| `fromDate`, `toDate` | — | Formato `YYYY-MM-DD`, hora de Argentina. Igual que en directorios: fecha de **aprobación** de la venta, o de creación si nunca se aprobó. Es la fecha de la carpeta donde está su PDF. |
| `hideEmpty` | `true` | Oculta las ventas que no tienen ningún PDF de hoja. Con `0` aparecen, con `sheets: []` en cada producto. |
| `order` | `desc` | `desc` = más nuevas primero; `asc` = más viejas primero (cola de producción). |
| `quantity`, `page` | `20`, `1` | Paginado de ventas. `quantity` va de 1 a 100. |

Por defecto vienen **todas** las ventas, de todos los usuarios, incluso para un diseñador. Para que un diseñador arranque viendo solo las suyas, el front manda `onlyMine=1` y deja que lo destilde.

```json
{
  "data": [{
    "id": 95254, "client": "Clara Aldman", "date": "2025-12-01",
    "sale_status_id": 1, "sale_status": "Aprobado",
    "sheets_count": 4, "free_count": 3, "all_assigned_elsewhere": false,
    "products": [{
      "sale_product_id": 2232, "product_id": 10, "name": "Combo PRIMARIA", "quantity": 1,
      "sheets": [{
        "key": "01-12-2025/95254-2232-Combo PRIMARIA-PERSONALIZABLE-1.pdf#1",
        "folder": "01-12-2025", "file": "95254-2232-Combo PRIMARIA-PERSONALIZABLE-1.pdf", "page": 1,
        "index": 1, "total": 2, "width_mm": 210, "height_mm": 297,
        "state": "free",
        "view_url": "/api/pdf-directories/01-12-2025/view/95254-2232-Combo%20PRIMARIA-PERSONALIZABLE-1.pdf"
      }]
    }]
  }],
  "metaData": { "available_count": 37, "current_page": 1, "per_page": 20, "has_more": true }
}
```

**Paginado con `has_more`:** el endpoint no devuelve el total de ventas ni la última página, porque las hojas se leen del disco y filtros como `hideAssigned` no se pueden resolver en SQL. Calcular el total obligaría a revisar todas las ventas en cada request. El front pide la página siguiente mientras `has_more` sea `true`, con scroll infinito o un botón "Cargar más".

`available_count` es la cantidad de hojas libres **de las ventas devueltas en esa página**.

`state` puede ser:
- `free`: se puede agregar.
- `here`: está en este pliego. Trae además `position`.
- `assigned`: está en otro pliego. Trae `pliego: { id, name }`, y el front no debería dejar arrastrarla.

`index`/`total` es la numeración "H1/2" dentro del producto. El botón "Agregar N" de la venta corresponde a las hojas con `state: "free"`. "Autocompletar" se resuelve en el front: llena los lugares vacíos con las hojas libres, en el orden en que vienen.

### Crear y editar: `POST /pliegos`, `POST /pliegos/{id}`

```json
{
  "name": "Pliego 016",
  "sheetSizeId": 6,
  "widthCm": 100,
  "heightCm": 60,
  "columns": 5,
  "rows": 2,
  "marginMm": 0,
  "gapMm": 0,
  "cutLines": { "enabled": true, "width_cm": 0.03, "color": { "mode": "hex", "value": "#FF00FF" }, "labels": true, "slots": false },
  "slots": [
    { "position": 0, "folder": "01-12-2025", "file": "95254-2232-Combo PRIMARIA-PERSONALIZABLE-1.pdf", "page": 1 }
  ]
}
```

| Campo | Crear | Editar | Notas |
|---|---|---|---|
| `name` | opcional | opcional | Si no se manda al crear, se genera "Pliego NNN" con el siguiente número. |
| `widthCm` / `heightCm` | sí | opcional | De 1 a 500. |
| `columns` / `rows` | sí | opcional | De 1 a 12. |
| `marginMm` / `gapMm` | opcional | opcional | De 0 a 80, default 0. |
| `cutLines` | opcional | opcional | Ver [Líneas de corte](#líneas-de-corte). Si no se manda al editar, no cambia; `null` las deshabilita. |
| `slots` | opcional | opcional | **Reemplaza todos los lugares.** Solo se mandan los ocupados. |

- Para **renombrar** desde la tarjeta: `POST /pliegos/{id}` con `{ "name": "…" }`.
- Si se **edita sin mandar `slots`** y la grilla queda más chica, los lugares se compactan en orden y los que no entran se liberan. La respuesta trae `metaData.dropped_sheets` con la cantidad, para mostrar el aviso "N hojas volvieron a la lista…". Es la misma regla que aplica el paso 1 del front.
- Se puede editar un pliego finalizado (botón "Guardar" del front); el estado no cambia.
- **Validaciones (422, `errors["slots.N"]`):**
  - el lugar existe en la grilla;
  - no se repiten lugares ni hojas;
  - el PDF y la página existen;
  - la hoja no está en **otro** pliego. Si dos personas ubican la misma hoja al mismo tiempo, la segunda recibe un 422 en `errors.slots`.

La respuesta es el **detalle** (ver abajo).

### Detalle: `GET /pliegos/{id}`

Trae los campos del pliego (`id`, `name`, `sheet_size_id`, `width_cm`, `height_cm`, `columns`, `rows`, `margin_mm`, `gap_mm`, `status`, `finalized_at`, `created_at`, `updated_at`) más:
- `cut_lines`: la configuración tal como se guardó, o `null`. Se usa para llenar el panel de líneas de corte al editar; los defaults están en [Configuración](#configuración-cutlines-al-crear-o-editar).
- `sheet_size`, `creator: { id, name }`, `finalizer: { id, name }`, que puede ser `null`.
- `total_slots`, `filled_slots`.
- `slot_width_mm`, `slot_height_mm`.
- `min_scale`: la escala de la hoja que más se achica. Si es menor a 1, mostrar "Las hojas no entran a tamaño real: se achican al X %".
- `missing_count`.
- `slots[]`, cada uno con:
  - `position`, `key`, `folder`, `file`, `page`, `page_count`;
  - `width_mm`, `height_mm`, `scale`, `missing`, `view_url`;
  - `sale_id`, `sale_product_id`, `client`, `product`, `sale_status_id`, `sale_status`.

`sale_id`, `client`, `product`, `page` y `page_count` sirven para el tooltip de cada lugar.

### Finalizar

1. Guardar el pliego, con `POST /pliegos` o `POST /pliegos/{id}`.
2. `GET /pliegos/{id}/involved-sales` → `[{ id, client, products_count, sheets_in_pliego, sheets_total, sale_status_id, sale_status, already_in_production }]`. Preseleccionar las ventas con `already_in_production: false`.
3. `POST /pliegos/{id}/finalize` con `{ "saleIds": [95254, 95310] }`.
   - Para "Finalizar sin cambiar estados", mandar `{ "saleIds": [] }`.
   - Las ventas pasan a "En producción" (estado 2), con historial y el mismo mail que se envía hoy al pasar una venta a producción desde ventas.
   - Se ignoran las ventas que ya estaban en producción.
   - Responde 422 si se manda una venta que no tiene hojas en el pliego.
   - `metaData.sales_to_production` trae las ventas que efectivamente cambiaron.

`POST /pliegos/{id}/reopen` vuelve el pliego a borrador. **No revierte el estado de las ventas.**

### Descargar

`GET /pliegos/{id}/download` devuelve un PDF de una sola página del tamaño del pliego. `?mode=` elige qué trae:

| `mode` | Contenido | Nombre del archivo |
|---|---|---|
| `art` (default) | Solo el arte: cada hoja centrada en su lugar, recortada a los bordes del lugar y achicada solo si no entra. | `Pliego 016.pdf` |
| `cut` | **Solo las líneas de corte**, mismo tamaño, para el plotter o para superponer 1 a 1 con el de arte. | `Pliego 016 - corte.pdf` |
| `both` | Arte con las líneas de corte encima. | `Pliego 016 - arte y corte.pdf` |

- `?guides=1` dibuja el contorno de cada lugar en rosa. Es solo visual, no es línea de corte, y aplica a `art` y `both`.
- Si falta algún PDF de origen, responde 422 con el detalle.
- `cut` y `both` responden 422 si el pliego no tiene `cut_lines.enabled`.
- Las hojas que salieron sin líneas, y el motivo, vienen en el header `X-Pliego-Warnings` como JSON: `[{ "position": 3, "reason": "…" }]`. El front puede avisar "N hojas no tienen líneas de corte".

`POST /pliegos/download` con `{ "ids": [...], "mode": "cut" }` devuelve un ZIP. Si falla algún pliego, no se genera el ZIP y la respuesta 422 trae el error de cada uno.

---

## Líneas de corte

Funcionan igual que en el editor de PDFs (ver [PDF_LINEAS_DE_CORTE.md](PDF_LINEAS_DE_CORTE.md)):
- trazos vectoriales sin relleno;
- color directo (spot) `CutContour`, el que leen los plotters;
- en las etiquetas de un diseño, un trazo por etiqueta (elementos `background`), respetando `cut_line: "hidden"`, `cut_vertical_cm` / `cut_horizontal_cm`, la rotación, la forma y el radio.

### De dónde pueden salir las líneas

Se pueden combinar todas las fuentes en el mismo pliego:

| Fuente | Campo de `cutLines` | Qué dibuja |
|---|---|---|
| Diseño de cada venta | `labels: true` | En cada lugar con hoja, las etiquetas del diseño con el que se generó **esa** hoja. |
| **Otro diseño en un lugar** | `slot_sources[]` | Ese lugar usa las líneas de **cualquier** diseño (no hace falta que esté en el pliego), en vez de las de su venta. Sirve también para un lugar **vacío**. |
| **Otro diseño suelto** | `designs[]` | Las líneas de cualquier diseño ubicadas libremente en el pliego, con posición, escala y rotación. |
| **Líneas creadas en el pliego** | `shapes[]` | Rectángulos (con o sin esquinas redondeadas), elipses o círculos, líneas rectas y paths libres. |
| Contorno de los lugares | `slots: true` | Un rectángulo por lugar. |

### Configuración (`cutLines` al crear o editar)

```json
"cutLines": {
  "enabled": true,
  "width_cm": 0.03,
  "color": { "mode": "hex", "value": "#FF00FF" },
  "labels": true,
  "slots": false,
  "slot_sources": [
    { "position": 4, "design_id": 240, "page_id": "p1" }
  ],
  "designs": [
    { "design_id": 241, "page_id": null, "x_mm": 250, "y_mm": 330, "scale": 0.8, "rotation_deg": 90 }
  ],
  "shapes": [
    { "type": "rect", "x_mm": 820, "y_mm": 330, "w_mm": 150, "h_mm": 100, "radius_mm": 10, "rotation_deg": 0 },
    { "type": "ellipse", "x_mm": 820, "y_mm": 450, "w_mm": 120, "h_mm": 120 },
    { "type": "line", "x1_mm": 0, "y1_mm": 300, "x2_mm": 1000, "y2_mm": 300 },
    { "type": "path", "d": "M 30 340 L 180 340 L 105 560 Z" }
  ]
}
```

| Campo | Default | Descripción |
|---|---|---|
| `enabled` | `false` | Si el pliego tiene líneas de corte. Sin esto, `mode=cut` y `mode=both` dan 422. |
| `width_cm` | `0.03` | Grosor del trazo. |
| `color` | `{ "mode": "hex", "value": "#FF00FF" }` | O `{ "mode": "cmyk", "value": "0,1,0,0" }` (C, M, Y, K de 0 a 1, igual que en el editor). |
| `labels` | `true` | Líneas del diseño de la venta de cada hoja. |
| `slots` | `false` | Contorno de cada lugar. |
| `slot_sources[]` | `[]` | `position` (0-based), `design_id` y `page_id`. Sin `page_id` se usa la primera página. Máximo uno por lugar. |
| `designs[]` | `[]` | `design_id`, `page_id`, `x_mm` / `y_mm` (esquina superior izquierda de la hoja del diseño en el pliego), `scale` (default 1) y `rotation_deg` (default 0, alrededor del centro). |
| `shapes[]` | `[]` | Ver la tabla de abajo. |

Todas las medidas de `designs` y `shapes` son **mm del pliego**, con origen arriba a la izquierda.

| `type` | Campos |
|---|---|
| `rect` | `x_mm`, `y_mm`, `w_mm`, `h_mm`; opcionales `radius_mm` (esquinas redondeadas) y `rotation_deg`. |
| `ellipse` | `x_mm`, `y_mm`, `w_mm`, `h_mm` (la caja; un círculo es `w_mm = h_mm`); opcional `rotation_deg`. |
| `line` | `x1_mm`, `y1_mm`, `x2_mm`, `y2_mm`. Trazo abierto, por ejemplo un corte recto de lado a lado. |
| `path` | `d`: path SVG con `M`, `L`, `H`, `V` y `Z`, absolutos o relativos. No admite curvas, igual que las formas custom del editor. |

El grosor y el color del pliego valen para todas las líneas. Así sale una sola pasada de plotter, aunque las líneas vengan de diseños con colores distintos.

**Validaciones (422):**
- el lugar de `slot_sources` existe en la grilla y no se repite;
- el diseño y la página existen;
- el `path` se puede leer.

**Ubicación y recorte:**
- Las líneas de `slot_sources` se ubican como una hoja: centradas en el lugar, achicadas solo si la hoja del diseño no entra, con el mismo recorte de 7,5 mm por lado.
- Las líneas de un lugar se recortan al borde de ese lugar.
- `designs` y `shapes` **no** se recortan.

### Líneas que vienen de un diseño

- **Diseño de la venta (`labels`):** el back vuelve a resolver las páginas del diseño vinculado a esa línea de venta, con el mismo nombre, ícono, color y `layout_groups` con que se generó el PDF. Toma la página que corresponde a esa hoja.
- **Cualquier diseño (`slot_sources` y `designs`):** se resuelve igual que el preview del editor con `?cut_lines=1`, con "NOMBRE APELLIDO" de ejemplo.

En los dos casos, las líneas se toman **aunque la página del diseño no tenga `cut_lines.enabled`**. En el pliego, si hay líneas o no lo decide el pliego (`cutLines.enabled` / `labels`), no cada diseño. El editor muestra el contorno de las etiquetas aunque la página no tenga ese interruptor activado. Sí se respetan las etiquetas con `cut_line: "hidden"`. `sheet-cut-lines` devuelve además `design_enabled`, que dice si la página tiene el interruptor activado en el editor; es solo informativo.

Una hoja con `labels` **sale sin líneas** (y aparece en `warnings`) cuando:
- la página del diseño no tiene etiquetas (`background`) con línea, o todas están ocultas;
- el producto no tiene un diseño del editor vinculado (PDF legacy), así que no hay geometría de etiquetas. **Para estos casos, usar `slot_sources`** con el diseño que corresponda;
- el diseño vinculado hoy ya no coincide con el PDF generado (cambió la cantidad de hojas o el tamaño). Se arregla regenerando el PDF de la venta.

### Endpoints para el armado

- **Pliego guardado:** `GET /pliegos/{id}/cut-lines` trae todo junto, ya ubicado en mm del pliego:

  ```json
  {
    "config": { ... },
    "width_mm": 1000, "height_mm": 600,
    "slots": [{
      "position": 0,
      "source": { "type": "sale", "sale_id": 95254, "key": "…" },
      "clip": { "x": 0, "y": 0, "w": 200, "h": 300 },
      "paths": [[["M", 9, 9.2], ["L", 60.9, 9.2], ["L", 60.9, 28.2], ["L", 9, 28.2], ["Z"]]]
    }, {
      "position": 4,
      "source": { "type": "design", "design_id": 240, "design_name": "…", "page_id": "p1" },
      "clip": { ... },
      "paths": [ ... ]
    }],
    "slot_paths": [],
    "design_paths": [{ "index": 0, "design_id": 241, "design_name": "…", "page_id": "…",
                       "box": { "x": 250, "y": 330, "w": 168, "h": 237.6 }, "paths": [ ... ] }],
    "shape_paths": [{ "index": 0, "paths": [ ... ] }],
    "warnings": [{ "position": 3, "source": "sale", "reason": "…" }]
  }
  ```

  - Segmentos: `M x y`, `L x y`, `C x1 y1 x2 y2 x y` y `Z`. Se pasan directo a un `<path>` SVG.
  - Hay que recortar `slots[].paths` con su `clip`.
  - En `warnings`, `position` corresponde a un lugar, `design_index` a un elemento de `designs[]` y `shape_index` a uno de `shapes[]`.

- **Elegir un diseño para usar sus líneas:** `GET /pliegos/design-cut-lines?designId=240&pageId=p1` devuelve:

  ```json
  { "available": true, "design_id": 240, "design_name": "…", "page_id": "p1",
    "width_mm": 210, "height_mm": 297, "paths": [ ... ],
    "pages": [{ "id": "p1", "index": 1, "width_mm": 210, "height_mm": 297 }],
    "reason": null }
  ```

  - `paths` viene en mm sobre la hoja del diseño.
  - `pages` sirve para elegir la página.
  - Si no se manda `pageId`, devuelve la primera página.
  - Si el diseño o la página no existen, responde 422 con `reason`.
  - Para listar los diseños, el front usa el `GET /product-pdf-designs` que ya tiene.

- **Hoja de una venta antes de guardar:** `GET /pliegos/sheet-cut-lines?folder=…&file=…&page=…` devuelve `{ width_mm, height_mm, available, enabled, paths, reason }`, con `paths` en mm sobre la hoja.
  - Si `enabled` es `false`, la hoja no tiene líneas y `reason` dice por qué.
  - `design_enabled`: si la página tiene `cut_lines.enabled` en el editor. Es solo informativo y no condiciona nada.
  - Responde 404 si no existen la venta o la hoja.
  - Responde 422 si `folder` o `file` son inválidos.

Antes de guardar, el front puede dibujar todo por su cuenta:
- las `shapes` las dibuja directamente;
- las de un diseño (`slot_sources` y `designs`) las pide a `design-cut-lines`;
- las de las hojas de venta las pide a `sheet-cut-lines`.

Las escala y ubica igual que el back: `placement` para un lugar, y `x_mm`, `y_mm`, `scale` y `rotation_deg` para los diseños sueltos.
