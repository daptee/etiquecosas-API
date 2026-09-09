# Edición masiva de variantes ("Todos")

## Descripción

Cuando un producto tiene atributos con varios valores (ej. Color: 3 valores, Talle: 3 valores), el sistema arma **una `ProductVariant` real por cada combinación posible** (3×3 = 9 variantes). Esto no cambió — sigue siendo así, y es necesario porque el storefront resuelve la variante comprada comparando la selección puntual del cliente contra estas 9 filas reales.

Lo que sí es nuevo es una forma de **editar en un solo paso** los campos que comparten todas esas variantes (precio, stock, descuentos, etc.), en vez de tener que entrar variante por variante a cambiarlos a mano. A esto lo llamamos edición "Todos": se manda **un solo** ítem en `variants[]` marcando los atributos con `attributes[X][attribute_id]` (sin fijar un valor puntual) y el backend:

1. Genera todas las combinaciones reales de esos atributos (igual que siempre).
2. Para cada combinación, si **ya existe** una variante de ese producto con exactamente esos valores de atributo, la **actualiza** (precio, stock, descuentos, etc. — los campos que mandaste en el ítem).
3. Si la combinación **no existe todavía**, la crea (igual que la explosión de siempre).
4. **No pisa los datos que tienen que ser únicos por variante** al actualizar: `sku`, `name` e imagen (`img`) de cada variante existente se mantienen tal cual estaban, aunque el ítem "Todos" no los mande o mande otra cosa.

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

## Qué NO cambia

- El storefront sigue viendo y comprando variantes reales de siempre — no hay ningún concepto de "variante comodín" ni de valores `"Todos"` en las respuestas de la API. Cada variante devuelta trae sus valores puntuales de atributo, como siempre.
- El checkout (`POST /sales`) no necesita mandar nada adicional — el `variant_id` que ya usa el front identifica la combinación exacta comprada, sin ambigüedad.
- Una edición normal (un solo `variants[X]` sin `attributes`, con o sin `id`) funciona exactamente igual que antes — la edición masiva solo se activa cuando `attributes` genera más de una combinación.
