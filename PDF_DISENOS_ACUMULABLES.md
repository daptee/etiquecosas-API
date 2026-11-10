# Diseños "acumulables": un PDF que se va completando solo con los pedidos del día

Generaliza lo que hoy hacen `BandaService`, `CintaCoserService` y `CintaPlancharService` (un PDF por día, por producto, que se va sumando a medida que entran pedidos) para que sea un **modo más del editor**, configurable por diseño — sin IDs de producto, colores ni vista Blade hardcodeados en el backend. Sirve para cualquier producto donde la "etiqueta" de cada pedido no se imprime sola, sino que se va acomodando junto a la de otros pedidos del mismo día en una sola hoja (o varias) que se va regenerando.

`BandaService`/`CintaCoserService`/`CintaPlancharService` siguen existiendo tal cual, sin tocarse — esto es un camino nuevo y en paralelo, pensado para probarse primero con un producto nuevo (o de prueba) antes de migrar alguno de los existentes. **Ojo**: si se vincula un diseño acumulable al MISMO product ID que ya maneja uno de esos tres servicios, van a convivir los dos sistemas en paralelo (cada uno generando su propio PDF del día) — no hay nada que los una automáticamente.

---

## Qué cambia conceptualmente respecto a un diseño normal

En un diseño normal, `data.pages[]` es la hoja completa que se genera **una vez por pedido** (con sus N etiquetas iguales, si el producto trae una plancha).

En un diseño **acumulable**, `data.pages[0]` deja de ser la hoja — pasa a representar **una sola celda** (una banda, una etiqueta individual). Su `sheet.width_cm`/`height_cm` es el tamaño físico de ESA celda, no de la hoja de impresión. El backend toma esa celda y la repite sola, una vez por cada pedido que fue entrando ese día, tileándolas sobre hojas estándar (18.5×29cm, como el resto del sistema) — agrupando en páginas separadas por un atributo de la variante si así se configura (ej. una página por color, igual que hace bandas hoy).

**Solo se usa la página 1** del diseño — si el diseño tiene más páginas, se ignoran en este modo.

---

## 1. Marcar el diseño como acumulable

`POST /product-pdf-designs/{id}` (el mismo endpoint de guardar de siempre) ahora acepta estos campos nuevos, todos opcionales:

| Campo | Tipo | Default | Qué hace |
|---|---|---|---|
| `fillMode` | `"single"` \| `"accumulating"` | `"single"` | `"accumulating"` activa este modo. Con `"single"` (o sin mandar el campo) el diseño se comporta exactamente como hoy. |
| `accumulationGroupAttribute` | string \| `null` | `null` | Nombre (o parte del nombre) de un atributo de la variante del producto, ej. `"Color banda 1"`. Si se setea, los pedidos del día se separan en una página distinta por cada valor distinto de ese atributo (con un encabezado de texto mostrando el valor). Si se deja `null`, todos los pedidos del día van en una sola lista continua, sin separar. |
| `accumulationCols` | int \| `null` | `null` (se calcula solo) | Cuántas celdas por fila entran en una hoja. Si no se manda, se calcula automáticamente según el tamaño de la celda. |
| `accumulationRows` | int \| `null` | `null` (se calcula solo) | Cuántas filas de celdas entran en una hoja. Igual que `accumulationCols`, se autocalcula si no se manda. |
| `accumulationGapCm` | number | `0.3` | Separación en cm entre celdas (horizontal y vertical). |
| `accumulationQuantityMode` | `"duplicate"` \| `"badge"` | `"duplicate"` | Cómo se imprime un pedido con cantidad > 1. Ver sección 1.1. |

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

## 2. Cómo armar la celda (`data.pages[0]`)

Es un diseño de UNA sola etiqueta chica, con el mismo editor y el mismo JSON de elementos de siempre — nada nuevo que aprender ahí. Lo único que cambia es la intención: en vez de diagramar la hoja completa, acá `sheet.width_cm`/`height_cm` tienen que ser el tamaño real de una sola celda (ej. `4.5` × `3.5`, como la banda actual).

Elementos típicos de esta celda:
- Un texto con `value_mode: "dynamic"`, `dynamic_field: "nombre_apellido"` — ahí va el nombre del pedido.
- Opcionalmente, un ícono con `editable_by_customer: true`, `editable_field: "icon"` — el que el cliente haya elegido al comprar.
- Fondo/forma fijos del diseño, si corresponde (no se pintan con el color de un atributo automáticamente — ver limitaciones).

**Lo que este modo NO soporta todavía dentro de la celda** (no hacía falta para reemplazar lo que hace bandas hoy, se puede sumar después si se necesita):
- `layout_groups` (el anclaje relativo ícono+texto) — las posiciones de los elementos se usan tal cual se guardaron.
- Apellido, color elegido por el cliente o fecha/número de pedido como campos dinámicos del nombre — esos datos son de un pedido puntual y no tienen sentido en un PDF que mezcla pedidos de ventas distintas. Solo `nombre_apellido` (al nombre completo del campo del formulario) e ícono están disponibles.

### 2.1. Cintas Coser/Planchar: tamaño de letra según largo del nombre

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

`CintaCoserService` tiene además una variante "grande" (etiquetas de 6×2.5cm, para pegar) con sus propios umbrales/tamaños — si se necesita replicar también esa variante, es **otro diseño acumulable más** (mismo producto, otra `variantId`/`themeKey`), con su propio `font_size_rules` (10/18 caracteres, 22/18/15pt sin ícono o 20/16/14pt con ícono — ver el bloque `$esGrande` en `cintas-coser/consolidado.blade.php`).

---

## 3. Vincular el producto — sin cambios

Se usa exactamente el mismo endpoint de siempre, sin ningún campo nuevo:

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
1. `database/sql/alter_product_pdf_designs_add_accumulation.sql` — agrega `fill_mode`/`accumulation_group_attribute`/`accumulation_cols`/`accumulation_rows`/`accumulation_gap_cm`.
2. `database/sql/alter_product_pdf_designs_add_quantity_mode.sql` — agrega `accumulation_quantity_mode`.

Todavía no están aplicados en ningún lado. Sin esto, ninguno de estos campos se puede guardar.

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
