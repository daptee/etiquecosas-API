# Variante comodín ("Todos")

## Cambio de diseño (2026-09-11)

Este documento reemplaza versiones anteriores. Ya no existe la "edición masiva que explota en N variantes reales" — ahora, cuando se marca un atributo como "Todos", se crea **una sola variante** que representa todos los valores disponibles de ese atributo. Se acabó la confusión de versiones anteriores: esta es la única forma de "Todos" que existe en el backend hoy.

Ejemplos de esta guía usan un producto con 2 atributos:
- **Color** (`attribute_id = 1`): Rojo (`id 10`), Azul (`id 11`), Verde (`id 12`)
- **Talle** (`attribute_id = 2`): S (`id 20`), M (`id 21`), L (`id 22`)

---

## 1. Requisito previo: `attributes_values` del producto

Igual que siempre: antes de poder usar "Todos" para un atributo, el producto tiene que tener valores asociados a ese atributo a nivel producto.

```
attributes[0][id]            1     // Color
attributes[1][id]            2     // Talle
attributes_values[0][id]     10    // Rojo
attributes_values[1][id]     11    // Azul
attributes_values[2][id]     12    // Verde
attributes_values[3][id]     20    // S
attributes_values[4][id]     21    // M
attributes_values[5][id]     22    // L
```

**⚠️ Este campo reemplaza el set completo cada vez que se manda** (es un `sync()`). Mandá siempre el set completo de todos los atributos del producto, no solo el que estás tocando.

Si se usa "Todos" para un atributo sin valores asociados al producto, falla con 422:
```json
{"variants.0.attributes": ["El producto no tiene valores asociados para el atributo \"Talle\". Asociá primero sus attribute_values al producto antes de usar \"Todos\" con ese atributo."]}
```

---

## 2. Crear/editar una variante puntual (sin "Todos")

Sin cambios respecto a siempre — esto es para variantes reales con un valor concreto por atributo:

```
variants[0][id]                       2919          // omitir para crear una nueva
variants[0][price]                    12000
variants[0][stock_quantity]           10
variants[0][sku]                      COLOR-ROJO-TALLE-M
variants[0][attributesvalues][0][id]  10    // Rojo
variants[0][attributesvalues][1][id]  21    // M
```

**`variants[]` es el estado completo deseado del producto — lo que no venga en el array se borra.** Si mandás 5 variantes puntuales y omitís una que ya existía, esa se elimina. Si vas a editar una sola variante puntual sin rearmar el array completo, usá:
```
POST /products/{id}/variants/bulk-update-price
{ "variant_ids": [2919], "type": "fixed", "operation": "increase", "value": 1233 }
```
(equivalente `bulk-update-stock` para stock).

---

## 3. Variante comodín ("Todos")

### 3.1 Cómo se manda

Se marca el atributo con `attributes[X][attribute_id]` (sin fijar un valor puntual):

```
variants[0][attributes][0][attribute_id]   1        // Color: comodín, todos sus valores
variants[0][price]                         12000
variants[0][stock_quantity]                5
```

Esto crea **UNA SOLA** `ProductVariant` — nunca explota en una por cada valor. Si el mismo ítem marca 2 atributos como comodín a la vez, sigue siendo **una sola variante**, con un listado de valores disponibles por cada atributo marcado.

Se puede fijar un valor puntual para otro atributo en el mismo ítem, con `attributesvalues` (igual que siempre, con `id`):

```
variants[0][attributes][0][attribute_id]        1    // Color: comodín
variants[0][attributesvalues][0][id]            21   // Talle = M, fijo
variants[0][price]                              15000
```

Ejemplo (el del pedido original): "todos los colores + Talle XL" y "todas las etiquetas + Color Azul" en el mismo request →
```
variants[0][attributes][0][attribute_id]   1     // Color: comodín
variants[0][attributesvalues][0][id]       22    // Talle = XL, fijo
variants[0][price]                         1000

variants[1][attributes][0][attribute_id]   2     // Talle: comodín
variants[1][attributesvalues][0][id]       10    // Color = Rojo, fijo
variants[1][price]                         2000
```
→ **2 variantes creadas**, una por cada ítem. Probado con datos reales.

### 3.2 Qué guarda y qué devuelve la API

La variante comodín guarda, además de los campos de siempre, un array `available_attributes` con los ids de `AttributeValue` disponibles para cada atributo marcado. La API lo devuelve ya resuelto:

```json
{
  "id": 2910,
  "variant": {
    "sku": null,
    "price": "12000",
    "attributesvalues": [
      { "id": 21, "value": "M", "attribute": { "id": 2, "name": "Talle" } }
    ],
    "available_attributes": [
      {
        "attribute": { "id": 1, "name": "Color" },
        "values": [
          { "id": 10, "value": "Rojo" },
          { "id": 11, "value": "Azul" },
          { "id": 12, "value": "Verde" }
        ]
      }
    ]
  }
}
```

- `attributesvalues`: valores puntuales fijos (como siempre).
- `available_attributes`: por cada atributo comodín, el atributo y **todos** los valores que el producto tiene asociados para ese atributo — esto es lo que el front usa para armar el selector de opciones (ej. mostrar un dropdown de Color con Rojo/Azul/Verde) y para saber, al momento de la venta, qué `attribute_value_id` puede llegar a comprar el cliente.

### 3.3 Editar una variante comodín ya existente

Igual que cualquier variante: mandala con su `id`. No hace falta "matchear" nada — es una fila más, como cualquier otra:

```
variants[0][id]                             2910
variants[0][attributes][0][attribute_id]    1
variants[0][attributesvalues][0][id]        21
variants[0][price]                          15000
```

### 3.4 Validación: no se puede solapar con una variante puntual existente

Antes de crear/editar una variante comodín, el backend chequea que **ninguna variante puntual ya existente** del producto caiga dentro del rango que ese "Todos" cubriría. Si el producto ya tiene, por ejemplo, una variante real "Azul-M" cargada a mano, e intentás crear "Color = Todos (incluye Azul) + Talle = M fijo", falla:

```json
{"variants.0": ["Ya existe una variante puntual (ID 4271) dentro del rango de esta edición \"Todos\". Eliminá o editá esa variante puntual antes de crear el comodín."]}
```

Solución: eliminar o reasignar esa variante puntual antes de crear el comodín. (La dirección inversa — crear una variante puntual que caiga dentro de un comodín ya existente — no está bloqueada.)

**No hace falta hacerlo en 2 pasos.** Si en el mismo `POST /products/{id}` sacás del array las variantes puntuales que estaban en conflicto (simplemente no las mandás, sin necesidad de un delete previo) y en ese mismo request creás el comodín que las reemplaza, funciona en un solo guardado — el backend sabe que esas puntuales se van a borrar por esta misma request (por full-replace, sección 2) y no las cuenta como conflicto. El error 422 solo aparece si la variante puntual en conflicto **se sigue mandando** (con su `id`) en el mismo request que el comodín que la solapa.

### 3.5 Formato viejo — ya NO se soporta

```
❌ variants[0][attributesvalues][0][attribute_id]   1
```
```json
{"variants.0.attributesvalues": ["Formato inválido: para \"Todos\" en un atributo hay que mandarlo en variants[0][attributes][][attribute_id], no en attributesvalues. attributesvalues solo acepta valores puntuales con \"id\"."]}
```

---

## 4. Checkout: `selected_attributes`

Como una variante comodín es **una sola fila** cubriendo varios valores reales, el `variant_id` que se manda al crear la venta es el mismo sin importar qué opción eligió el cliente. Por eso, **cada vez que el `variant_id` elegido sea una variante comodín**, hay que mandar también qué valor concreto se seleccionó, en la misma línea de producto de la venta:

```
products[0][product_id]        123
products[0][variant_id]        2910
products[0][quantity]          1
products[0][unit_price]        12000
products[0][selected_attributes][0][attribute_id]         1
products[0][selected_attributes][0][attribute_value_id]   10
```

- Una entrada por cada atributo comodín de la variante (`attribute_id` = el que viene en `available_attributes[].attribute.id`; `attribute_value_id` = el id puntual que el cliente eligió, de entre `available_attributes[].values`).
- Si un atributo de la variante no es comodín (ya viene en `attributesvalues` con valor fijo), no hace falta mandar nada para ese atributo.
- Aplica a `POST /v1/sales` (checkout) y a los endpoints de venta local (`store-local-sale`/`update-local-sale`).
- **Es opcional a nivel validación**, pero funcionalmente necesario: sin esto, todo lo que dependa de saber qué se compró (PDF de etiqueta, emails, bandas) va a mostrar/usar `"Todos"` en vez de la elección real del cliente. Tratarlo como obligatorio en el front siempre que el `variant_id` elegido sea comodín.

Cada línea de venta (`SaleProduct`) expone `resolved_attributes_values`, con la misma resolución (puntual → tal cual; comodín con selección → el valor elegido; comodín sin selección → `"Todos"`).

---

## 5. Resumen de errores 422 posibles

| Mensaje | Causa | Qué hacer |
|---|---|---|
| `El producto no tiene valores asociados para el atributo "X"` | `attributes[][attribute_id]` para un atributo sin `attributes_values` sincronizados en el producto | Sincronizar los `attributes_values` de ese atributo en el mismo request |
| `Formato inválido: para "Todos"... no en attributesvalues` | Se mandó `attributesvalues[][attribute_id]` sin `id` (formato viejo) | Usar `attributes[][attribute_id]` |
| `Ya existe una variante puntual (ID N) dentro del rango de esta edición "Todos"` | El producto ya tiene una variante real con un valor dentro del rango que cubriría el comodín | Eliminar/editar esa variante puntual antes de crear el comodín |

---

## 6. Qué NO cambió

- Una edición puntual (variante real, sin `attributes`) funciona exactamente igual que siempre — full-replace del array, sin excepciones.
- El storefront ve variantes reales con sus valores — la diferencia es que una variante comodín trae, además, `available_attributes` con las opciones para que el front arme el selector.
