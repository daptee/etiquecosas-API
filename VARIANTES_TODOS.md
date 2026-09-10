# Edición masiva de variantes ("Todos")

## Descripción

Cuando un producto tiene atributos con varios valores (ej. Color: 3 valores, Talle: 3 valores), el sistema arma **una `ProductVariant` real por cada combinación posible** (3×3 = 9 variantes). Esto no cambió — sigue siendo así, y es necesario porque el storefront resuelve la variante comprada comparando la selección puntual del cliente contra estas 9 filas reales.

Lo que sí es nuevo es una forma de **editar en un solo paso** los campos que comparten todas esas variantes (precio, stock, descuentos, etc.), en vez de tener que entrar variante por variante a cambiarlos a mano. A esto lo llamamos edición "Todos": se manda **un solo** ítem en `variants[]` marcando los atributos con `attributes[X][attribute_id]` (sin fijar un valor puntual) y el backend:

1. Genera todas las combinaciones reales de los valores que **el producto tiene seleccionados** para esos atributos (igual que siempre) — ver más abajo, esto es importante.
2. Para cada combinación, si **ya existe** una variante de ese producto con exactamente esos valores de atributo, la **actualiza** (precio, stock, descuentos, etc. — los campos que mandaste en el ítem).
3. Si la combinación **no existe todavía**, la crea (igual que la explosión de siempre).
4. **No pisa los datos que tienen que ser únicos por variante** al actualizar: `sku`, `name` e imagen (`img`) de cada variante existente se mantienen tal cual estaban, aunque el ítem "Todos" no los mande o mande otra cosa.

### ⚠️ "Todos" usa los valores del producto, no todos los del sistema

`attributes[X][attribute_id]` expande **únicamente a los `AttributeValue` que el producto tiene asociados** para ese atributo (el `attributes_values` que se sincroniza a nivel producto, fuera de `variants` — ver `syncProductRelations`/`syncProductUpdateRelations`). **No** trae todos los valores que existan para ese atributo en el resto del catálogo.

Ejemplo: el atributo "Color" puede tener 10 valores en todo el sistema, pero si este producto en particular solo tiene asociados Rojo, Azul y Verde (vía `attributes_values` del producto), "Todos" para Color genera combinaciones únicamente con esos 3 — nunca con los otros 7 que existen para otros productos.

Por eso, para que la edición "Todos" tenga algo con qué armar combinaciones, el producto ya tiene que tener sincronizados sus `attributes_values` para ese atributo (normalmente se mandan en el mismo request, y se procesan antes que los `variants`).

**Si el producto no tiene ningún valor asociado a ese atributo, el request falla con un 422** (`variants.X.attributes: "El producto no tiene valores asociados para el atributo \"...\""`) — no se genera nada a medias. Esto es a propósito: antes fallaba en silencio (el atributo sin valores quedaba afuera y las variantes se creaban igual, pero con un atributo de menos), lo cual era mucho peor porque no se notaba hasta mirar el resultado.

Ojo con esto si el mismo request que manda `variants` con "Todos" **también** manda `attributes_values` a nivel producto: como ese campo hace `sync()` (reemplaza todo lo que había), si en esa request solo van los valores de un atributo y no los del otro, el otro atributo se queda sin valores en ese mismo golpe — y ahí "Todos" para ese atributo va a fallar. Mandá siempre el set completo de `attributes_values` (todos los atributos del producto, no solo el que estás tocando) en el mismo request que dispara "Todos".

## Cómo se manda

Exactamente igual que la explosión de toda la vida — **no hay ningún campo nuevo**:

```
variants[0][attributes][0][attribute_id]   1     // Color: aplica a sus 3 valores
variants[0][attributes][1][attribute_id]   2     // Talle: aplica a sus 3 valores
variants[0][price]                         10890
variants[0][stock_quantity]                5
variants[0][stock_status]                  1
...
```

No se manda `variants[0][id]` (no tiene sentido: un solo id no puede representar 9 variantes). El backend detecta que es una edición masiva porque `attributes` generó **más de una combinación**.

### Qué pasa con cada combinación resultante

- **Si el producto ya tenía una variante con esa combinación exacta de Color+Talle** → se actualiza: precio, stock, descuentos, `stock_channels`, `order`, etc. cambian al valor que mandaste en el ítem. **`sku`, `name` e `img` de esa variante NO se tocan** — quedan como estaban.
- **Si no existía** → se crea una variante nueva para esa combinación, con todos los campos del ítem (acá sí, como es una fila nueva, el `sku`/`name` que hayas mandado se usa tal cual — igual que la explosión de siempre en alta).

### Ejemplo

Producto con Color (Rojo, Azul, Verde) y Talle (S, M) → 6 variantes reales. Las 6 ya existen, cada una con su propio sku (`ROJ-S`, `ROJ-M`, `AZU-S`, etc.) cargado a mano en algún momento.

Mandás:
```
variants[0][attributes][0][attribute_id]   1   // Color
variants[0][attributes][1][attribute_id]   2   // Talle
variants[0][price]                         12000
variants[0][stock_status]                  1
```

Resultado: las 6 variantes existentes pasan a tener `price: 12000` y `stock_status: 1`, pero cada una conserva su propio `sku` (`ROJ-S`, `ROJ-M`, etc.) y su propia imagen. No se crea ninguna variante nueva porque las 6 combinaciones ya existían.

### Mezclando "Todos" en un atributo con un valor fijo en otro

Se puede combinar `attributes` (explota) con `attributesvalues` (fija un valor concreto para todas las combinaciones), igual que siempre:

```
variants[0][attributes][0][attribute_id]        1    // Color: todos sus valores
variants[0][attributesvalues][0][id]            28   // Talle = M, fijo, para todas
```

Esto afecta solo a las variantes Color×Talle=M existentes (o las crea si falta alguna), dejando intactas las de otros talles.

## Cómo identificar en el front qué variantes vinieron de una carga "Todos"

Cada variante devuelta por la API trae un campo nuevo, `variant.is_bulk_todos` (booleano):

```json
{
  "id": 4212,
  "img": "...",
  "variant": {
    "sku": "ROJ-S",
    "is_bulk_todos": true,
    ...
  }
}
```

- `true`: la última vez que se guardó esta variante fue a través de una edición masiva "Todos" (ya sea porque se creó así, o porque una edición "Todos" posterior la matcheó y actualizó).
- `false`: la última vez que se guardó fue con una edición puntual de esa variante (mandándola individualmente, con o sin `id`, sin usar `attributes` para explotar).

Importante: **este flag refleja el último tipo de guardado, no un origen permanente**. Si el admin abre la grilla completa de variantes y la guarda (aunque no cambie nada a mano), cada fila se manda como un ítem individual — eso también cuenta como edición puntual y apaga el flag de todas. Sirve para saber "¿esto se tocó por última vez vía Todos?", no para taggear de forma indeleble el origen histórico de la variante.

## Qué NO cambia

- El storefront sigue viendo y comprando variantes reales de siempre — no hay ningún concepto de "variante comodín" ni de valores `"Todos"` en las respuestas de la API. Cada variante devuelta trae sus valores puntuales de atributo, como siempre.
- El checkout (`POST /sales`) no necesita mandar nada adicional — el `variant_id` que ya usa el front identifica la combinación exacta comprada, sin ambigüedad.
- Una edición normal (un solo `variants[X]` sin `attributes`, con o sin `id`) funciona exactamente igual que antes — la edición masiva solo se activa cuando `attributes` genera más de una combinación.
