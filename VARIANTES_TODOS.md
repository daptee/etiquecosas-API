# Variante comodín ("Todos") en atributos de producto

## Descripción

Hasta ahora, cuando el admin agregaba un atributo a una variante vía `variants[X][attributes][0][attribute_id]`, el backend **explotaba automáticamente** esa variante en una por cada valor posible del atributo (producto cartesiano). Si un producto tenía 2 atributos con 3 valores cada uno, terminaban generándose 9 variantes reales en `product_variants`, aunque precio/stock/etc. fueran iguales para todas.

Esta funcionalidad nueva agrega una forma alternativa de cargar una variante: marcarla como **comodín ("Todos")** para uno o más atributos, sin explotarla. El resultado es **una sola variante** que en la respuesta de la API aparece con el valor `"Todos"` en vez de un valor puntual, para cada atributo marcado así.

Regla clave: esto es un **modo alternativo**, no reemplaza nada. La explosión automática con `attributes[X][attribute_id]` sigue funcionando exactamente igual que antes, para cuando sí se necesita precio/stock diferenciado por combinación.

---

## Cómo pedir "Todos" para un atributo en una variante

En vez de usar la key `attributes` (que dispara la explosión), se usa `attributesvalues`, pero **sin mandar `id`**, solo `attribute_id`:

```
variants[0][attributesvalues][0][attribute_id]   1
```

Esa entrada significa: "esta variante aplica a **todos** los valores del atributo 1".

### Ejemplos

**Un solo atributo comodín** (ej. Color → Todos):
```
variants[0][attributesvalues][0][attribute_id]   1
variants[0][price]                               10890
variants[0][stock_quantity]                      5
...
```
→ genera **1 variante**, con `attributesvalues: [{ id: null, value: "Todos", attribute: { id: 1, name: "Color" } }]`.

**Dos atributos comodín a la vez** (ej. Color → Todos, Talle → Todos):
```
variants[0][attributesvalues][0][attribute_id]   1
variants[0][attributesvalues][1][attribute_id]   2
```
→ genera **1 variante**, con `attributesvalues` conteniendo un `"Todos"` por cada atributo (Color y Talle).

**Mezclar un valor fijo con un comodín** (ej. Color = Rojo puntual, Talle = Todos):
```
variants[0][attributesvalues][0][id]             15
variants[0][attributesvalues][1][attribute_id]   2
```
→ genera **1 variante** con `attributesvalues: [{id: 15, value: "Rojo", ...}, {id: null, value: "Todos", attribute: {id: 2, name: "Talle"}}]`.

### Importante

- **No mandar `attributes[X][attribute_id]`** para el/los atributo(s) que quieras como comodín. Si mandás `attributes` junto con `attributesvalues` para el mismo atributo, `attributes` va a explotar en combinaciones y el comodín se va a pegar como fijo a cada una de esas combinaciones — no es lo que se busca.
- Cada entrada de `attributesvalues` es o bien `{ id: <attribute_value_id> }` (valor puntual, comportamiento de siempre) o bien `{ attribute_id: <id> }` sin `id` (comodín "Todos" para ese atributo). No mandar ambos (`id` e `attribute_id`) en la misma entrada — si `id` viene con valor, gana ese camino y se ignora el comodín.
- El campo `attributes` (a nivel producto, fuera de `variants`) que sincroniza qué atributos tiene el producto **no cambia** — sigue mandándose igual.

---

## Qué devuelve la API

En la respuesta de un `ProductVariant`, cada elemento de `variant.attributesvalues` ahora puede ser:

**Valor puntual (como siempre):**
```json
{
  "id": 15,
  "value": "Rojo",
  "attribute": { "id": 1, "name": "Color" }
}
```

**Comodín "Todos":**
```json
{
  "id": null,
  "value": "Todos",
  "attribute": { "id": 2, "name": "Talle" }
}
```

El front debe distinguir estos dos casos por `id === null` (no por el texto `"Todos"`, que podría cambiar). Cuando `id` es `null`, esa variante aplica a **cualquier** valor de ese `attribute.id` para ese producto.

---

## Impacto en selección de variante en el storefront

**Este backend no resuelve "qué variante corresponde a la selección del cliente"** — esa lógica hoy vive en el front, comparando `attribute_value_id`s elegidos contra el listado completo de variantes del producto (`GET` de producto ya trae todas con sus `attributesvalues`).

Con variantes comodín, esa lógica de matching en el front tiene que actualizarse: al buscar la variante que matchea la selección del usuario, un atributo con `id: null` (Todos) en la variante **matchea cualquier valor elegido** para ese `attribute.id`, no requiere que el valor elegido coincida con nada puntual.

Ejemplo: si el producto tiene 2 atributos (Color: 3 valores, Talle: 3 valores) y existe una única variante con `attributesvalues: [{id: null, attribute: {id: 1}}, {id: null, attribute: {id: 2}}]`, esa variante debe ser la que se use sin importar qué Color/Talle elija el cliente (siempre que el producto no tenga *otra* variante más específica que sí matchee esa combinación puntual — si conviven variantes puntuales y comodín para el mismo producto, priorizar la puntual si existe).

