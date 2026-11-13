# Diseños "acumulables": un PDF que se va completando solo con los pedidos del día

Generaliza lo que hoy hacen `BandaService`, `CintaCoserService` y `CintaPlancharService` (un PDF por día, por producto, que se va sumando a medida que entran pedidos) para que sea un **modo más del editor**, configurable por diseño — sin IDs de producto, colores ni vista Blade hardcodeados en el backend. Sirve para cualquier producto donde la "etiqueta" de cada pedido no se imprime sola, sino que se va acomodando junto a la de otros pedidos del mismo día en una sola hoja (o varias) que se va regenerando.

`BandaService`/`CintaCoserService`/`CintaPlancharService` siguen existiendo tal cual, sin tocarse — esto es un camino nuevo y en paralelo, pensado para probarse primero con un producto nuevo (o de prueba) antes de migrar alguno de los existentes. **Ojo**: si se vincula un diseño acumulable al MISMO product ID que ya maneja uno de esos tres servicios, van a convivir los dos sistemas en paralelo (cada uno generando su propio PDF del día) — no hay nada que los una automáticamente.

---

## Qué cambia conceptualmente respecto a un diseño normal

En un diseño normal, `data.pages[]` es la hoja completa que se genera **una vez por pedido** (con sus N etiquetas iguales, si el producto trae una plancha).

En un diseño **acumulable**, cada página de `data.pages[]` deja de ser una hoja — pasa a representar **una sola celda** (una banda, una etiqueta individual). El backend toma la celda que corresponda (ver sección 2.1 si el diseño tiene más de una) y la repite sola, una vez por cada pedido que fue entrando ese día, tileándolas sobre hojas estándar (21×29.7cm — A4 real, como el resto del sistema) — agrupando en páginas separadas por un atributo de la variante (o por la personalización ícono/color, ver sección 1) si así se configura.

**El tamaño real de esa celda NO sale de `sheet.width_cm`/`height_cm`** — `sheet` sigue siendo solo el lienzo de edición, y puede quedar en el tamaño de hoja completo de siempre (A4, o lo que uses normalmente): no hace falta achicarlo. El tamaño real de la etiqueta se resuelve así, en este orden (`AccumulatingPdfDesignService::resolverTamanoDeCelda()`):
1. `label_shape_id` del elemento `background` de la celda (la forma de etiqueta asignada desde el picker del editor — lo normal).
2. `label_shape_id` de nivel-diseño (`product_pdf_designs.label_shape_id`), si el `background` no trae una forma propia.
3. `width_cm`/`height_cm` del propio elemento `background`, si no hay ninguna forma de catálogo asociada.
4. Como último fallback (diseños 100% a mano, sin ningún `background`): `sheet.width_cm`/`height_cm`.

Los elementos de la celda se guardan con sus coordenadas tal cual quedaron en el lienzo (ej. el `background` puede estar en `x_cm: 1.17` si lo dejaste cerca del margen de una hoja A4 completa) — el backend resta esa posición antes de tilear cada copia, así que no hace falta que estén en `(0,0)`.

Un diseño puede tener **varias páginas/celdas distintas** (ej. una etiqueta chica y una grande) — ver sección 2.1 para cómo se elige cuál usa cada producto. Si el diseño tiene una sola página, es siempre esa (comportamiento de siempre).

---

## 1. Marcar el diseño como acumulable

`POST /product-pdf-designs/{id}` (el mismo endpoint de guardar de siempre) ahora acepta estos campos nuevos, todos opcionales:

| Campo | Tipo | Default | Qué hace |
|---|---|---|---|
| `fillMode` | `"single"` \| `"accumulating"` | `"single"` | `"accumulating"` activa este modo. Con `"single"` (o sin mandar el campo) el diseño se comporta exactamente como hoy. |
| `accumulationGroupAttribute` | string \| `null` | `null` | Nombre (o parte del nombre) de un atributo de la variante del producto, ej. `"Color banda 1"`. También acepta los valores reservados `"Ícono"`/`"Color"` (sin acento también vale) para agrupar por la personalización LIBRE de la compra en productos sin esa variante de catálogo (ej. Cinta Planchar/Coser: cada pedido trae su propio ícono/color en `customization_data`, no en `product_variants`) — ver `resolverValorDePersonalizacion()` en `AccumulatingPdfDesignService`. Si se setea, los pedidos del día se separan en una página distinta por cada valor distinto (con un encabezado de texto mostrando el valor). Si se deja `null`, todos los pedidos del día van en una sola lista continua, sin separar. |
| `accumulationCols` | int \| `null` | `null` (se calcula solo) | Cuántas celdas por fila entran en una hoja. Si no se manda, se calcula automáticamente según el tamaño de la celda. |
| `accumulationRows` | int \| `null` | `null` (se calcula solo) | Cuántas filas de celdas entran en una hoja. Igual que `accumulationCols`, se autocalcula si no se manda. |
| `accumulationGapCm` | number | `0.3` | Separación en cm entre celdas (horizontal y vertical). |
| `accumulationQuantityMode` | `"duplicate"` \| `"badge"` | `"duplicate"` | Cómo se imprime un pedido con cantidad > 1. Ver sección 1.1. |
| `accumulationSplitAttributes` | `string[]` \| `null` | `null` | Productos "de varias unidades, cada una su color" (ej. pack de 2 bandas). Ver sección 1.2. |

Ejemplo de request (editando un diseño ya creado, id `42`):

```json
POST /api/product-pdf-designs/42
{
  "name": "Banda genérica",
  "data": { "pages": [ /* ver sección siguiente */ ] },
  "fillMode": "accumulating",
  "accumulationGroupAttribute": "Color banda 1",
  "accumulationGapCm": 0.3
}
```

La respuesta es el mismo `ProductPdfDesign` de siempre (`GET`/`POST /product-pdf-designs/{id}` ya lo devuelven con estos campos incluidos).

---

### 1.1. Pedidos con cantidad > 1: `accumulationQuantityMode`

Si el campo del formulario permite pedir más de una unidad (ej. "cantidad: 3" de la misma banda/cinta), hay dos formas de que eso se vea en el PDF del día:

| Valor | Resultado |
|---|---|
| `"duplicate"` (default) | Se repite la celda esa cantidad de veces — 3 celdas físicas idénticas, una al lado de la otra, para cortar por separado. |
| `"badge"` | Una sola celda + un cartelito `"x3"` al lado (mismo criterio que usan hoy Cintas Coser/Planchar) — reserva un poco de ancho extra de cada celda para ese cartelito, así que las columnas por hoja salen un poco menos que con `"duplicate"`. |

```json
POST /api/product-pdf-designs/42
{
  "name": "Cinta para coser - x24",
  "fillMode": "accumulating",
  "accumulationQuantityMode": "badge"
}
```

Un pedido con cantidad 1 se ve exactamente igual en los dos modos (nunca sale ningún cartelito).

---

### 1.2. Productos "de varias unidades, cada una su color": `accumulationSplitAttributes`

Caso: un pack de N unidades físicas por pedido (ej. "Bandas de silicona personalizadas. 2 unidades", producto 52796 — 36 variantes, cada una una combinación de 2 colores vía los atributos de catálogo `"Color banda 1"`/`"Color banda 2"`, tipo `color`). Cada unidad del pack es una etiqueta física independiente, con SU PROPIO color — no un solo ítem con un solo color como en el resto de este documento. Esto ya lo resuelve a mano `BandaService::obtenerColoresDeVariante()` para el sistema viejo; `accumulationSplitAttributes` es el equivalente configurable para un diseño del editor.

`accumulationSplitAttributes` es una lista de nombres de atributo de la variante (orden = orden de las unidades del pack), ej.:

```json
POST /api/product-pdf-designs/42
{
  "name": "Bandas x2",
  "fillMode": "accumulating",
  "accumulationGroupAttribute": "Color",
  "accumulationSplitAttributes": ["Color banda 1", "Color banda 2"]
}
```

Con esto configurado, **cada pedido se parte en un ítem por cada atributo de la lista** que tenga valor en la variante elegida — en vez de agregar un único ítem con el color de `customization_data` (comportamiento de siempre, el de toda la sección 1). Si dos atributos de la lista resuelven el mismo color (ej. las 2 bandas del mismo color), salen dos ítems idénticos en ese color — **no se deduplican**, cada uno es una etiqueta física distinta que hay que cortar por separado.

El color de cada ítem partido sale del catálogo, no de `customization_data`: el atributo tiene que ser de tipo `color` en la tabla `attributes`, con su `metadata.value` apuntando al id de un `PersonalizationColor` (que es donde vive el hex real, `color_code[0]`) — mismo mecanismo que ya usa el sistema para resolver el ícono/tipografía "por atributo" (`ProductVariant::formatAttributeValues()`). Si el atributo no es de tipo `color`, o no tiene esa `metadata`, el ítem sale igual (con el nombre) pero sin color propio.

Si `accumulationGroupAttribute` también está configurado (cualquier valor no vacío alcanza, no hace falta que diga literalmente "Color"), cada ítem partido se agrupa por SU PROPIO color resuelto (encabezado de página con ese color de fondo, letra blanca — igual que la sección de agrupar por "Color" ya documentada) — pisa, solo para los ítems partidos, la agrupación normal por `customization_data`/atributo de variante. Sin `accumulationGroupAttribute`, los ítems partidos van todos en la misma lista continua, sin separar por color (pero cada uno sigue siendo una etiqueta física aparte).

Sin esta config (`null`/`[]`, el default), el comportamiento es exactamente el de siempre — cero impacto en los diseños existentes.

**Pendiente del front**: ver `PDF_SPLIT_ATTRIBUTES_FRONT.md` (doc aparte, para mandarle directo al equipo de front).

Requiere la migración `database/sql/alter_product_pdf_designs_add_split_attributes.sql` (agrega `product_pdf_designs.accumulation_split_attributes`, JSON) — ver sección 6.

---

## 2. Cómo armar la celda (`data.pages[0]`)

Es un diseño de UNA (o varias, ver 2.1) etiqueta chica, con el mismo editor y el mismo JSON de elementos de siempre — nada nuevo que aprender ahí. Podés seguir diagramando sobre una hoja completa como siempre (`sheet` no necesita achicarse, ver más arriba); lo único que importa es que el `background` de la etiqueta tenga asignada su forma real (`label_shape_id`) desde el picker del editor, como en cualquier diseño normal.

Elementos típicos de esta celda:
- Un texto con `value_mode: "dynamic"`, `dynamic_field: "nombre_apellido"` — ahí va el nombre del pedido.
- Opcionalmente, un ícono con `editable_by_customer: true`, `editable_field: "icon"` — el que el cliente haya elegido al comprar.
- Opcionalmente, un color con `editable_by_customer: true`, `editable_field: "color"` (en el texto, el fondo o el borde) — el color que el cliente haya elegido al comprar (personalización libre, `customization_data`) — SÍ se aplica (ver `agregarItemAlPdf()`). Esto es distinto de un color atado a un ATRIBUTO de variante de catálogo (`value_mode: "dynamic"` con `dynamic_attribute_id`), que no se soporta — ver limitaciones.
- `layout_groups` (el anclaje relativo ícono+texto) para que queden bien alineados sin importar el largo del nombre — SÍ se aplica (`EtiquetaService::resolverCeldaAcumulada()`).

**Lo que este modo NO soporta todavía dentro de la celda** (no hacía falta para reemplazar lo que hace bandas hoy, se puede sumar después si se necesita):
- Un color o ícono atado a un ATRIBUTO de variante de catálogo (`dynamic_attribute_id`) — se imprime el color/ícono de respaldo del diseño, no el de la variante elegida.
- Una tipografía "De la compra" (`font_value_mode: "dynamic"`) — se usa la tipografía fija del diseño.
- Apellido o fecha/número de pedido como campos dinámicos del nombre (`dynamic_field`) — esos datos son de un pedido puntual y no tienen sentido en un PDF que mezcla pedidos de ventas distintas. Solo `nombre_apellido` (el nombre completo del campo del formulario) está disponible ahí.

### 2.1. Un mismo diseño con varias etiquetas (mismo PDF del día)

La página 1 puede tener **más de un elemento `background`** — cada uno es una etiqueta distinta (su propio tamaño/forma vía `label_shape_id`, su propio texto/ícono). Las páginas en sí NO son la unidad acá (eligiéndolas se movería todo el contenido de la página junto, sin poder elegir una etiqueta puntual dentro) — la unidad es el elemento `background`.

Cada etiqueta de la página se arma con:
- Su `background` (con su `label_shape_id` propio).
- El texto/ícono que le pertenece, de dos formas (se pueden combinar):
  - Anclado vía `layout_groups`, con `container_element_id` igual al `id` de ESE `background` (ver `PDF_LAYOUT_GROUPS.md`) — lo normal para nombre + ícono.
  - Suelto, sin `layout_groups`, con su `x_cm`/`y_cm` cayendo DENTRO del rectángulo de ese `background` (ej. un texto fijo tipo "PARA PEGAR" puesto a mano sobre la etiqueta) — se asigna por posición.

Al vincular un producto al diseño (sección 3), el vínculo indica **qué etiqueta le corresponde** mandando `labelElementId` (el `id` de ESE `background` puntual) en vez de dejarlo vacío:

```
POST /product-pdf-designs/{id}/products
{ "productId": 500, "themeKey": null, "labelElementId": "bg-etiqueta-grande" }
```

Sin `labelElementId` (o `null`), el vínculo usa la primera etiqueta (el primer `background`) de la página — comportamiento de siempre para un diseño de una sola etiqueta.

Cada pedido que entra recuerda con qué etiqueta fue generado — el PDF consolidado del día resuelve el tamaño/layout de cada ítem según SU PROPIO `background` (y SOLO los elementos que le pertenecen a ese background — los de otra etiqueta de la misma página no se mezclan), pero todos (sin importar cuál les tocó) terminan en el **mismo archivo PDF**, cada etiqueta en sus propias hojas dentro de ese PDF (cada una arma su propia grilla, no comparten celdas entre sí).

Esto es distinto del caso de la sección 3 ("varios tipos, cada uno con su propio consolidado" — ej. Cinta Coser x24/x48): ahí cada tipo necesita un **PDF separado** y por eso se resuelve con dos diseños distintos. Acá el requisito es al revés — **un solo PDF** que mezcle etiquetas de tamaños/layouts distintos según qué se compró — por eso se resuelve con un solo diseño de varias etiquetas (varios `background` en la misma página), no con varios diseños.

Requiere la migración `database/sql/alter_product_pdf_design_products_add_label_element.sql` (agrega `product_pdf_design_products.label_element_id`) — ver sección 6.

### 2.2. Cintas Coser/Planchar: tamaño de letra según largo del nombre

`CintaCoserService`/`CintaPlancharService` achican la fuente del nombre a mano si es muy largo (si no, se sale de la etiqueta o se corta) — esto **no es automático** en el modo acumulable, hay que configurarlo en el elemento `text` del nombre con `font_size_rules` (+ `length_rules_enabled: true` para que el editor lo muestre activado), igual que cualquier otro diseño del editor (ver `PDF_EDITOR.md`, columna `font_size_rules`).

Los umbrales de las vistas legacy (`resources/views/cintas-coser/consolidado.blade.php` y `cintas-planchar/consolidado.blade.php`, idénticos en ambas) son por cantidad de caracteres del nombre ya completo, sobre una base de 12pt (16px):

| Caracteres | Legacy | `font_size_px` equivalente |
|---|---|---|
| hasta 16 | `1.1em` | `18` |
| hasta 25 | `0.9em` | `14` |
| más de 25 | `0.7em` | `11` |

```json
{
  "id": "nombre", "type": "text",
  "x_cm": 0.3, "y_cm": 1.0, "width_cm": 3.9, "height_cm": 1.0,
  "value_mode": "dynamic", "dynamic_field": "nombre_apellido",
  "max_lines": 1, "max_chars_per_line": 30,
  "font_size_px": 18,
  "length_rules_enabled": true,
  "font_size_rules": [
    { "max_chars": 16, "font_size_px": 18 },
    { "max_chars": 25, "font_size_px": 14 },
    { "max_chars": null, "font_size_px": 11 }
  ],
  "text_align": "center",
  "color": { "mode": "hex", "value": "#FFFFFF" }
}
```

`font_size_px` (el de arriba, fuera de las reglas) queda como tamaño por defecto si el nombre resuelto no matchea ninguna regla — en la práctica no pasa nunca acá porque la última regla (`max_chars: null`) ya cubre "el resto", pero conviene dejarlo en el mismo valor que la primera regla.

`CintaCoserService` tiene además una variante "grande" (etiquetas de 6×2.5cm, para pegar) con sus propios umbrales/tamaños — si se necesita replicar también esa variante **en un PDF aparte**, es otro diseño acumulable más (mismo producto, otra `variantId`/`themeKey`), con su propio `font_size_rules` (10/18 caracteres, 22/18/15pt sin ícono o 20/16/14pt con ícono — ver el bloque `$esGrande` en `cintas-coser/consolidado.blade.php`). Si en cambio tiene que caer **en el mismo PDF** que la etiqueta chica, se arma como un segundo `background` en la misma página del mismo diseño y se vincula ese producto puntual con `labelElementId` — ver sección 2.1.

---

## 3. Vincular el producto — sin cambios

Se usa exactamente el mismo endpoint de siempre, sin ningún campo nuevo (salvo `labelElementId`, opcional — ver sección 2.1 si el diseño tiene varias etiquetas en la misma página):

```
POST /product-pdf-designs/{id}/products
{ "productId": 500, "themeKey": 3067 }
```

Esto reemplaza al chequeo hardcodeado de IDs de producto que tiene hoy `BandaService::esProductoBanda()` — acá cualquier producto+variante vinculado a un diseño `accumulating` entra en este modo solo.

Un mismo producto puede tener, al mismo tiempo, un vínculo a un diseño normal (su etiqueta de siempre) Y un vínculo a un diseño acumulable (para sumarse al consolidado del día) — son dos vínculos distintos, ambos conviven sin pisarse, igual que hoy pasa con bandas (genera su etiqueta normal Y se agrega al PDF del día).

**Varios "tipos" del mismo producto, cada uno con su propio consolidado** (ej. Cintas Coser/Planchar: "x24" y "x48" son hoy dos PDFs del día completamente separados): se resuelve con **dos diseños acumulables distintos**, cada uno vinculado a su propia variante (`themeKey`) del mismo producto — no hace falta nada especial, es el mismo mecanismo de vínculos de siempre, solo que cada variante apunta a un diseño (y por lo tanto un PDF del día) diferente.

---

## 4. Qué pasa en una venta real

Nada que el front tenga que disparar a mano: en cuanto un pedido de un producto vinculado a un diseño acumulable se aprueba/factura (mismo momento en que hoy se generan todos los PDFs de una venta), el backend:
1. Agrega ese pedido al JSON del día de ESE diseño (agrupado por `accumulationGroupAttribute` si está configurado).
2. Regenera el PDF consolidado del día entero para ese diseño.

Es **idempotente por pedido**: si la misma venta se reprocesa (cambio de estado, reenvío), el pedido se reemplaza en vez de duplicarse — no hace falta "limpiar antes de reintentar".

---

## 5. Ver/descargar el PDF del día

Se lista junto con el resto de los PDFs del día, en el módulo de directorios de siempre:

```
GET /pdf-directories/{fecha}
```

Cada diseño acumulable con PDF generado ese día aparece como una entrada más de `extras`, con dos campos nuevos para identificarlo:

```json
{
  "fecha": "05-11-2026",
  "pdfs_pedidos": [ ... ],
  "extras": [
    {
      "nombre": "42-05-11-2026.pdf",
      "ruta": "...",
      "tamanio": 123456,
      "fecha_modificacion": "2026-11-05 14:30:00",
      "design_id": 42,
      "design_name": "Banda genérica"
    }
  ]
}
```

`nombre` (`"{design_id}-{fecha}.pdf"`) es el que hay que mandar tal cual a los endpoints ya existentes:

```
GET /pdf-directories/{fecha}/view/{nombre}       # preview inline
GET /pdf-directories/{fecha}/download/{nombre}   # descarga
```

**Nota de permisos**: para un usuario con perfil "diseñador" (no admin), estos dos endpoints chequean que el PDF pertenezca a una venta asignada a ese usuario — chequeo que no tiene sentido para un PDF consolidado de muchos pedidos, y que hoy **tampoco funciona bien para Bandas/Sellos/Cintas** (mismo comportamiento preexistente, no es algo nuevo de este modo). En la práctica, para ver estos PDFs conviene estar logueado como admin, igual que ya pasa con bandas/sellos hoy.

No hay endpoint de "regenerar" para estos PDFs (el que existe es específico de `pdfs_pedidos`, por línea de venta) — se regeneran solos cada vez que entra o se reprocesa un pedido.

---

## 6. Antes de poder usar esto en un entorno

Hace falta aplicar, en orden, en la base de ese entorno:
1. `database/sql/alter_product_pdf_designs_add_accumulation.sql` — agrega `fill_mode`/`accumulation_group_attribute`/`accumulation_cols`/`accumulation_rows`/`accumulation_gap_cm`. **Ya aplicado en desarrollo local** (confirmado 2026-11-11).
2. `database/sql/alter_product_pdf_designs_add_quantity_mode.sql` — agrega `accumulation_quantity_mode`. **Todavía NO aplicado en desarrollo local** — sin esto, `accumulationQuantityMode` no se puede guardar (el modo `"badge"` de la sección 1.1).
3. `database/sql/alter_product_pdf_design_products_add_label_element.sql` — agrega `product_pdf_design_products.label_element_id`. **Todavía NO aplicado en ningún entorno** (2026-11-12) — sin esto, `labelElementId` no se puede guardar (varias etiquetas en una misma página, sección 2.1).
4. `database/sql/alter_product_pdf_designs_add_split_attributes.sql` — agrega `product_pdf_designs.accumulation_split_attributes`. **Todavía NO aplicado en ningún entorno** (2026-11-13) — sin esto, `accumulationSplitAttributes` no se puede guardar (productos de varias unidades/colores, sección 1.2).

Verificar el estado real en cada entorno antes de asumir — esto puede haber cambiado desde que se escribió esta nota.

---

## Ejemplo de uso completo

```bash
# 1. Crear el diseño de la celda (una banda de 4.5x3.5cm)
POST /api/product-pdf-designs
{
  "name": "Banda genérica",
  "data": {
    "pages": [{
      "sheet": { "width_cm": 4.5, "height_cm": 3.5 },
      "elements": [{
        "id": "nombre", "type": "text",
        "x_cm": 0.2, "y_cm": 1.2, "width_cm": 4.1, "height_cm": 1.2,
        "value_mode": "dynamic", "dynamic_field": "nombre_apellido",
        "max_lines": 1, "max_chars_per_line": 14,
        "font_size_px": 28, "text_align": "center",
        "color": { "mode": "hex", "value": "#000000" }
      }]
    }]
  },
  "isPublished": true
}
# -> devuelve { "data": { "id": 42, ... } }

# 2. Activar el modo acumulable en ese diseño
POST /api/product-pdf-designs/42
{
  "name": "Banda genérica",
  "fillMode": "accumulating",
  "accumulationGroupAttribute": "Color banda 1"
}

# 3. Vincular el producto (como cualquier diseño)
POST /api/product-pdf-designs/42/products
{ "productId": 500, "themeKey": 3067 }
```

A partir de acá, cada pedido de ese producto+variante se va sumando solo al PDF del día — visible en `GET /pdf-directories/{fecha}` como un `extra` con `design_id: 42`.
