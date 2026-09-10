# Variantes de producto: crear, editar y edición masiva ("Todos")

Guía completa para el front: cómo crear/editar variantes, qué pasa cuando se usa "Todos" para un atributo, y cómo filtrar el listado para mostrar solo las variantes que corresponden a una selección puntual.

Ejemplos de esta guía usan un producto con 2 atributos:
- **Color** (`attribute_id = 1`): Rojo (`id 10`), Azul (`id 11`), Verde (`id 12`)
- **Talle** (`attribute_id = 2`): S (`id 20`), M (`id 21`), L (`id 22`)

---

## 1. Requisito previo: `attributes_values` del producto

Antes de poder generar/editar variantes de un atributo, el producto tiene que tener sus valores asociados a nivel producto (no alcanza con que el valor exista en el catálogo):

```
attributes[0][id]            1     // Color
attributes[0][order]         1
attributes[1][id]            2     // Talle
attributes[1][order]         2

attributes_values[0][id]     10    // Rojo
attributes_values[1][id]     11    // Azul
attributes_values[2][id]     12    // Verde
attributes_values[3][id]     20    // S
attributes_values[4][id]     21    // M
attributes_values[5][id]     22    // L
```

**⚠️ Este campo (`attributes_values`) reemplaza el set completo cada vez que se manda** (es un `sync()`, no un merge). Si en un `POST /products/{id}` mandás `attributes_values` con solo los valores de Color y te olvidás los de Talle, **se desasocian los de Talle** en ese mismo request. Regla simple: **mandá siempre el set completo** (todos los atributos del producto, no solo el que estás tocando en esa pantalla) cada vez que incluyas este campo.

Si intentás usar "Todos" (sección 3) para un atributo sin valores asociados al producto, el request falla con 422 en vez de generar algo incompleto en silencio:
```json
{"variants.0.attributes": ["El producto no tiene valores asociados para el atributo \"Talle\". Asociá primero sus attribute_values al producto antes de usar \"Todos\" con ese atributo."]}
```

---

## 2. Crear / editar variantes — comportamiento base

Endpoint: `POST /products` (alta) o `POST /products/{id}` (edición), campo `variants[]`.

### 2.1 Reemplazo completo, no parche

**`variants[]` es el estado completo deseado, no una lista de cambios.** Cada vez que mandás este campo:
- Toda variante existente cuyo `id` **no** venga en el array se **elimina**.
- Toda variante cuyo `id` **sí** venga se actualiza con **exactamente** lo que mandaste para ella — no solo el campo que cambiaste, todos.

Esto es así desde siempre (no es parte de lo de "Todos"), pero es la causa más común de "edité 1 variante y me cambió/desapareció otra":

❌ **Mal** — el front solo precarga la variante que el usuario tocó, y manda las demás con valores en blanco/default:
```
variants[0][id]      2919
variants[0][price]   1233          // la que el usuario realmente quiso cambiar
variants[1][id]      2920
variants[1][price]   0             // ⚠️ esto VA A PISAR el precio real de 2920 con 0
```

✅ **Bien** — el front precarga cada variante con su valor **actual** (tal como vino del último `GET` del producto), y solo cambia el campo que el usuario tocó:
```
variants[0][id]      2919
variants[0][price]   1233          // cambiado
variants[1][id]      2920
variants[1][price]   21530         // el valor real que ya tenía, sin tocar
```

Si necesitás cambiar precio/stock de **una sola variante puntual** sin tener que rearmar y reenviar el array completo de variantes, usá en cambio:

```
POST /products/{id}/variants/bulk-update-price
{ "variant_ids": [2919], "type": "fixed", "operation": "increase", "value": 1233 }
```
(`type: percentage|fixed`, `operation: increase|decrease`). Para stock existe el equivalente `POST /products/{id}/variants/bulk-update-stock` (`variant_ids`, `quantity`, `note`, `channel_id`, `stock_alert`).

### 2.2 Editar una variante puntual (sin "Todos")

```
variants[0][id]                       2919
variants[0][price]                    12000
variants[0][stock_quantity]           10
variants[0][sku]                      COLOR-ROJO-TALLE-M
variants[0][attributesvalues][0][id]  10    // Rojo
variants[0][attributesvalues][1][id]  21    // M
```

- `attributesvalues` acá son valores **puntuales**: cada entrada es `{ "id": <attribute_value_id> }`.
- Solo se toca **esa** variante. `sku`, `name`, `price`, todo se actualiza tal cual lo mandaste.

### 2.3 Crear una variante nueva

Igual que 2.2 pero sin `variants[0][id]` — se crea una fila nueva.

---

## 3. Edición masiva ("Todos")

### 3.1 Qué es

En vez de armar variante por variante, se manda **un solo ítem** en `variants[]` marcando con `attributes[X][attribute_id]` el/los atributo(s) para los que querés que aplique a **todos** los valores que el producto tiene asociados (sección 1):

```
variants[0][attributes][0][attribute_id]   1        // Color: sus 3 valores (Rojo, Azul, Verde)
variants[0][attributes][1][attribute_id]   2        // Talle: sus 3 valores (S, M, L)
variants[0][price]                         12000
variants[0][stock_status]                  1
variants[0][stock_quantity]                5
```

**No se manda `variants[0][id]`** — un solo id no puede representar 9 variantes. El backend detecta que es edición masiva porque `attributes` generó más de una combinación (3×3 = 9 en este caso).

### 3.2 Qué hace, paso a paso

1. Calcula el producto cartesiano de los valores que el producto tiene asociados para cada atributo marcado (3 × 3 = 9 combinaciones: Rojo-S, Rojo-M, Rojo-L, Azul-S, ..., Verde-L).
2. Para cada combinación:
   - **Si ya existe una variante de ese producto con exactamente esos 2 valores de atributo** → la **actualiza**: `price`, `stock_status`, `stock_quantity`, `discounted_price`, `wholesale_price`, `stock_channels`, `order`, etc. pasan a ser los que mandaste en el ítem.
   - **Si no existe** → la **crea**, con todos los campos del ítem (acá el `sku` que hayas mandado, si mandaste alguno, se usa tal cual — es una fila nueva).
3. **Nunca pisa `sku`, `name` ni `img` de una variante que ya existía** — esos quedan exactamente como estaban, aunque el ítem "Todos" no los mande o mande otra cosa. Es la única excepción a la regla de "reemplazo completo" de la sección 2.1 — a propósito, porque estos son datos que tienen que seguir siendo únicos por variante.

### 3.3 Ejemplo completo

Producto con Color × Talle, ya existen las 9 variantes (cada una con su sku: `ROJ-S`, `ROJ-M`, ..., `VER-L`).

Mandás:
```
variants[0][attributes][0][attribute_id]   1
variants[0][attributes][1][attribute_id]   2
variants[0][price]                         15000
variants[0][stock_status]                  1
```

Resultado: las 9 variantes existentes pasan a `price: 15000`, `stock_status: 1`. Cada una conserva su propio `sku` (`ROJ-S` sigue siendo `ROJ-S`, etc.) y su imagen. No se crea ninguna variante nueva porque las 9 combinaciones ya existían.

### 3.4 "Todos" en un atributo + valor fijo en otro

Se puede combinar `attributes` (todos los valores de un atributo) con `attributesvalues` (un valor puntual fijo para el otro atributo):

```
variants[0][attributes][0][attribute_id]        1    // Color: todos sus valores (Rojo, Azul, Verde)
variants[0][attributesvalues][0][id]            21   // Talle = M, fijo
variants[0][price]                              15000
```

Esto genera/actualiza **solo 3 combinaciones** (Rojo-M, Azul-M, Verde-M) — las variantes de Talle S y Talle L **no se tocan**.

### 3.5 Formato viejo — ya NO se soporta

Si mandás el `attribute_id` de un atributo dentro de `attributesvalues` (sin `id`), en vez de usar `attributes`, el request falla con 422:
```
❌ variants[0][attributesvalues][0][attribute_id]   1
```
```json
{"variants.0.attributesvalues": ["Formato inválido: para \"Todos\" en un atributo hay que mandarlo en variants[0][attributes][][attribute_id], no en attributesvalues. attributesvalues solo acepta valores puntuales con \"id\"."]}
```
Usá siempre `attributes` para "Todos" (3.1) y `attributesvalues` solo para valores puntuales, con `id` (2.2, 3.4).

---

## 4. El flag `is_bulk_todos`

Cada variante que devuelve la API trae `variant.is_bulk_todos` (booleano):

```json
{
  "id": 4212,
  "variant": {
    "sku": "ROJ-M",
    "is_bulk_todos": true,
    "attributesvalues": [
      { "id": 10, "value": "Rojo", "attribute": { "id": 1, "name": "Color" } },
      { "id": 21, "value": "M", "attribute": { "id": 2, "name": "Talle" } }
    ]
  }
}
```

- `true` → la **última vez** que se guardó esta variante fue vía una edición masiva "Todos" (creada así, o matcheada/actualizada por una).
- `false` → la última vez que se guardó fue una edición puntual (sección 2.2), sin `attributes`.

**No es un origen permanente, es el último tipo de guardado.** Si después alguien guarda la grilla completa variante por variante (sección 2.1), aunque no cambie nada a mano, cada fila se manda individualmente y el flag se apaga para todas. Sirve para "¿esto se tocó por última vez vía Todos?", no para taggear el historial completo de la variante.

---

## 5. Filtrar el listado para mostrar solo las variantes que corresponden

**Problema:** el producto puede tener 9 variantes reales (3×3), pero en un momento dado el admin solo quiere ver/trabajar con un subconjunto — por ejemplo, después de hacer "Todos" para Color con Talle fijo en M (sección 3.4), lo lógico es que la grilla muestre **3 variantes** (Rojo-M, Azul-M, Verde-M), no las 9 que tiene el producto en total.

Esto es **responsabilidad del front** — la API siempre devuelve las 9 variantes completas en `product.variants[]`; el filtrado para mostrar el subconjunto relevante se hace ahí, con los datos que ya vienen en la respuesta. No hace falta pedirle nada especial al backend ni hay un query param para esto.

### Cómo filtrar

Cada variante trae su combinación real en `variant.attributesvalues`, con el `id` del `AttributeValue` y el `attribute.id` al que pertenece. Para mostrar solo las variantes que tienen un valor puntual fijado (ej. Talle = M, `attribute_value_id = 21`):

```js
function filtrarPorValorFijo(variants, attributeValueId) {
  return variants.filter(v =>
    v.variant.attributesvalues.some(av => av.id === attributeValueId)
  );
}

// De las 9 variantes del producto, mostrar solo las 3 con Talle = M
const soloTalleM = filtrarPorValorFijo(product.variants, 21);
// -> Rojo-M, Azul-M, Verde-M
```

Para filtrar por **varios** valores fijos a la vez (ej. Talle = M **y** algún otro atributo con valor fijo), extendé la condición a que la variante contenga **todos** los `attribute_value_id` que estás fijando:

```js
function filtrarPorValoresFijos(variants, attributeValueIds) {
  return variants.filter(v => {
    const idsDeLaVariante = v.variant.attributesvalues.map(av => av.id);
    return attributeValueIds.every(id => idsDeLaVariante.includes(id));
  });
}
```

### Ejemplo concreto pedido: 3×3 → mostrar solo 3

Producto con Color (3 valores) × Talle (3 valores) = 9 variantes reales. El admin entra a "editar Talle = M para todos los colores" (como en 3.4). La grilla que se le muestra en pantalla **antes de guardar, y también después, al revisar el resultado**, tiene que ser el subconjunto de 3 (Rojo-M, Azul-M, Verde-M) — no las 9. Se logra tomando `product.variants` (9 elementos) y aplicándole el filtro de arriba con `attributeValueId = 21` (el id de "M").

Esto es puramente de presentación: las otras 6 variantes (Talle S y L) siguen existiendo en la base igual, el filtro solo decide qué mostrar en pantalla en ese momento.

---

## 6. Resumen de errores 422 posibles

| Mensaje | Causa | Qué hacer |
|---|---|---|
| `El producto no tiene valores asociados para el atributo "X"` | Se usó `attributes[][attribute_id]` para un atributo sin `attributes_values` sincronizados en el producto | Sincronizar los `attributes_values` de ese atributo en el mismo request (sección 1) |
| `Formato inválido: para "Todos" en un atributo hay que mandarlo en variants[X][attributes][][attribute_id]...` | Se mandó `attributesvalues[][attribute_id]` sin `id` (formato viejo) | Usar `attributes[][attribute_id]` para "Todos" (sección 3.5) |

---

## 7. Qué NO cambió

- El storefront sigue viendo y comprando variantes reales de siempre — cada variante trae sus valores puntuales de atributo. No existe ningún concepto de "variante comodín" en las respuestas de la API.
- El checkout (`POST /sales`) no necesita mandar nada especial — el `variant_id` identifica la combinación exacta comprada, sin ambigüedad.
