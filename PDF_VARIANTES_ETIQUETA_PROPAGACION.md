# Propagar los cambios de una variante de etiqueta a los diseños que la usan

Pedido del front al back. Hoy, si se edita una **variante de etiqueta** (`label_shapes.data.variants[]`,
ver `PDF_VARIANTES_ETIQUETA.md`), ningún diseño que ya la usaba cambia, ni en el editor ni en el PDF.
La propuesta: **cuando se guarda una variante, el back busca todos los diseños que la usan y les
reescribe esa etiqueta y sus elementos con la versión nueva.** Los diseños quedan guardados ya
actualizados, así que la generación del PDF y el editor no cambian.

---

## Por qué hoy no funciona

Al insertar o aplicar una variante, el editor **copia** su etiqueta y sus elementos dentro del diseño
(`product_pdf_designs.data.pages[].elements[]`), con ids nuevos y posiciones absolutas
(`instantiateLabelVariant` en `utils/LabelShapeVariantsHelper.ts`). La etiqueta copiada solo guarda
`label_shape_id`, que es la **etiqueta base** y no la variante. No queda ningún dato que diga
"esta etiqueta y estos elementos salieron de la variante X", así que el back no tiene forma de
encontrarlos.

---

## 1. Lo que agrega el front: una referencia a la variante en el diseño

Al insertar o aplicar una variante, el front va a guardar un campo nuevo, `label_variant_ref`, en la
etiqueta y en cada elemento copiado. A cada aplicación sobre una etiqueta se la llama **instancia** y
tiene su propio `instance_id`.

```json
// La etiqueta (type "background") donde se aplicó la variante
{
  "id": "el-1763100000000-1-1111",
  "type": "background",
  "label_shape_id": 7,
  "x_cm": 2, "y_cm": 3, "width_cm": 4, "height_cm": 2.5, "rotation_deg": 90,
  "...": "resto de la etiqueta",
  "label_variant_ref": {
    "label_shape_id": 7,
    "variant_id": "variant-1763050000000-0-1234",
    "instance_id": "lvi-1763100000000-2-2222",
    "label_parts": ["fill", "border", "corners", "padding", "cut"],
    "element_ids": ["el-1763049000000-4-9012", "el-1763049000000-5-3456"],
    "all_elements": true
  }
}

// Cada elemento que vino de la variante (texto, ícono, forma)
{
  "id": "el-1763100000000-3-3333",
  "type": "text",
  "...": "resto del elemento",
  "label_variant_ref": {
    "instance_id": "lvi-1763100000000-2-2222",
    "variant_element_id": "el-1763049000000-4-9012"
  }
}
```

| Campo | Dónde | Significado |
|---|---|---|
| `label_shape_id` | etiqueta | Etiqueta base que tiene la variante. |
| `variant_id` | etiqueta | `id` de la variante en `label_shapes.data.variants[]`. |
| `instance_id` | etiqueta y elementos | Une la etiqueta con sus elementos. Es único por cada etiqueta donde se aplicó la variante. |
| `label_parts` | etiqueta | Partes de la etiqueta que se tomaron de la variante (ver tabla abajo). Las que no están las define el diseño y no se tocan. |
| `element_ids` | etiqueta | Ids **dentro de la variante** de los elementos que se importaron. |
| `all_elements` | etiqueta | `true` si se importaron todos los elementos de la variante. Sirve para decidir si un elemento que se agrega después a la variante aparece en el diseño (ver punto 2.4). |
| `variant_element_id` | elemento | Id del elemento **dentro de la variante** (`variants[].elements[].id`). |

Partes de la etiqueta (`label_parts`) y qué claves de la etiqueta abarca cada una (es
`LABEL_VARIANT_PARTS` en el front):

| Parte | Claves |
|---|---|
| `fill` | `fill`, `color`, `background_image` |
| `border` | `border` |
| `corners` | `radius_mode`, `radius_pct` |
| `padding` | `padding_vertical_cm`, `padding_horizontal_cm`, `padding_cm` |
| `cut` | `cut_line`, `cut_vertical_cm`, `cut_horizontal_cm` |

**Desvinculación (la maneja el front):** cuando el admin modifica a mano algo que vino de la variante,
el front corta el vínculo de eso puntual para que la propagación no pise un cambio hecho a propósito:

- Si edita un **elemento** (texto, posición, color, etc.), se le borra `label_variant_ref` y queda fijo.
- Si edita una **parte de la etiqueta** (por ejemplo el color), esa parte se saca de `label_parts`.
  Mover o rotar la etiqueta **no** la desvincula: su posición y rotación siempre son del diseño.
- Si borra un elemento, simplemente deja de estar.
- Al duplicar, copiar o generar una grilla con etiquetas vinculadas, cada copia recibe un
  `instance_id` nuevo.

**Lo que tiene que hacer el back con estos campos:** guardarlos y devolverlos tal cual en el
alta/edición/lectura de diseños. Si el sanitizer de `data` descarta claves desconocidas en los
elementos, hay que sumar `label_variant_ref`.

---

## 2. Lo que hace el back al guardar una variante

Disparador: `POST /label-shapes/{id}` cuando cambia alguna variante de `data.variants`. El front ya
manda `data` completo. Para saber qué variantes cambiaron, el back puede comparar cada variante con la
versión que tenía guardada (o recorrer todas, como sea más simple).

Para cada variante que cambió:

### 2.1 Encontrar los diseños

Los diseños que tengan, en alguna página, una etiqueta con
`label_variant_ref.label_shape_id = {id}` y `label_variant_ref.variant_id = {variantId}`.

Dos formas posibles:

- **Simple:** buscar en el JSON de `data` (por ejemplo `data LIKE '%"variant-1763...%'` o
  `JSON_SEARCH`) como pre-filtro, y después recorrer cada diseño encontrado.
- **Recomendada si hay muchos diseños:** una tabla auxiliar
  `product_pdf_design_label_variants (design_id, label_shape_id, variant_id)` que se actualiza cada vez
  que se guarda un diseño. La búsqueda queda indexada.

### 2.2 Recorrer las instancias de cada diseño

En cada página, cada etiqueta (`type: "background"`) con `label_variant_ref` de esa variante es una
instancia. Sus elementos son los de la **misma página** con `label_variant_ref.instance_id` igual al de
la etiqueta.

### 2.3 Actualizar la etiqueta

Se parte de la etiqueta actual del diseño (`L`) y de la etiqueta de la variante nueva (`V.label`):

1. Las claves de las partes que están en `label_parts` se toman de `V.label`, y si no existen en
   `V.label`, se borran de `L`.
2. Lo demás no se toca: `id`, `x_cm`, `y_cm`, `width_cm`, `height_cm`, `rotation_deg`, `group_id`,
   `z_index`, `label_shape_id`, las partes que no están en `label_parts` y el propio
   `label_variant_ref`.

### 2.4 Actualizar los elementos

Datos de entrada:

- `L`: la etiqueta del diseño, con `x_cm`, `y_cm`, `width_cm`, `height_cm` y `rotation_deg`
  (`0` si no tiene).
- `V`: la variante nueva. Sus elementos tienen posiciones **relativas** a la esquina superior izquierda
  de su etiqueta, que siempre está en `0, 0`.
- `delta = L.rotation_deg - (V.label.rotation_deg ?? 0)`: cuánto hay que girar la variante para que
  quede como la etiqueta del diseño. Hay que tener en cuenta `V.label.rotation_deg`, porque una variante
  se puede haber guardado desde una etiqueta rotada.

Para cada `variantElementId` de `element_ids`:

| Situación | Qué hacer |
|---|---|
| Está en `V.elements` y en el diseño hay un elemento vinculado con ese `variant_element_id` | Se reemplaza por la versión nueva (colocación abajo), **conservando su `id`** y su `label_variant_ref`. |
| Está en `V.elements` pero en el diseño no hay elemento vinculado (el admin lo borró o lo desvinculó) | No se hace nada. |
| Ya no está en `V.elements` (se borró de la variante) | Se borra del diseño el elemento vinculado, si lo hay, y se saca el id de `element_ids`. |

Si `all_elements` es `true`, los elementos de `V.elements` que **no** están en `element_ids` (son
nuevos en la variante) se agregan al diseño con un `id` nuevo, un `label_variant_ref` y se suman a
`element_ids`. Si es `false`, no se agregan, porque el admin eligió importar solo algunos.

**Colocación de un elemento** `E` de la variante (es lo que hace hoy el front con
`instantiateLabelVariant` + `rotatePlacedLabelVariant`):

```text
1. Copia profunda de E.
2. x = E.x_cm + L.x_cm
   y = E.y_cm + L.y_cm
3. Si delta != 0, girar el centro del elemento alrededor del centro de la etiqueta:
     lcx = L.x_cm + L.width_cm / 2          lcy = L.y_cm + L.height_cm / 2
     cx  = x + E.width_cm / 2               cy  = y + E.height_cm / 2
     rad = delta * PI / 180
     ncx = lcx + (cx - lcx) * cos(rad) - (cy - lcy) * sin(rad)
     ncy = lcy + (cx - lcx) * sin(rad) + (cy - lcy) * cos(rad)
     x = ncx - E.width_cm / 2
     y = ncy - E.height_cm / 2
     rotation_deg = normalizar((E.rotation_deg ?? 0) + delta)
4. Redondear x_cm / y_cm a 2 decimales.
   normalizar(a) lleva el ángulo al rango -180..180 (redondeado a 2 decimales, 0 si queda en ~0).
5. id: el del elemento que se reemplaza (o uno nuevo si se agrega).
   group_id: ver 2.5.
```

(Si la etiqueta base cambió de tamaño y `L` no coincide con el de la variante, el front centra la
variante en la etiqueta: `x = L.x_cm + (L.width_cm - shape.widthCm) / 2`. Para la propagación alcanza
con usar la esquina de `L`, porque la etiqueta toma el tamaño de su etiqueta base.)

### 2.5 Grupos y relaciones

- **`group_id` (Agrupar):** dentro de la variante, los elementos pueden compartir un `group_id`. En el
  diseño ese id tiene que ser distinto por instancia. Propuesta: que tanto el front al aplicar como el
  back al propagar usen un id determinístico, `"{instance_id}:{group_id de la variante}"`. Así el back
  lo recalcula sin tener que guardar ningún mapa.
- **`layout_groups` (Relaciones)**, que están en `pages[].layout_groups`:
  1. Borrar de la página las relaciones de la instancia. Son las que tienen
     `label_variant_instance_id = instance_id`, un campo nuevo que el front también va a guardar en las
     relaciones que crea al aplicar.
  2. Volver a crearlas a partir de `V.layout_groups`, solo las que tengan **todos** sus `members` (y su
     `container_element_id`, si tiene) entre la etiqueta y los elementos vinculados que quedaron en el
     diseño.
     - `members` y `container_element_id` se traducen del id en la variante al id en el diseño.
     - `anchor` (si tiene): se le suma `L.x_cm` / `L.y_cm` y se gira con `delta` igual que el centro
       de un elemento.
     - `links` se copian tal cual.

### 2.6 Guardar

Todo en una transacción: el `label_shape` y todos los diseños que se actualizaron. Si un diseño falla,
lo mejor es no dejar la mitad aplicada.

---

## 3. Endpoints

- **`POST /label-shapes/{id}`**: además de lo de siempre, devuelve cuántos diseños se actualizaron:

  ```json
  { "data": { "...": "etiqueta base" }, "updated_designs": [{ "id": 12, "name": "Etiquetas escolares" }] }
  ```

- **Nuevo, `GET /label-shapes/{id}/variants/{variantId}/usage`**: los diseños que usan la variante,
  sin modificar nada. El front lo llama antes de guardar para avisar "Esto va a actualizar 12 diseños"
  y pedir confirmación.

- **Eliminar una variante** (llega como un `POST /label-shapes/{id}` sin esa variante): los diseños que
  la usaban **se quedan como están**, y se les borra `label_variant_ref` de la etiqueta, de sus elementos
  y de sus relaciones. Lo mismo si se elimina la etiqueta base.

---

## 4. Diseños que ya existen

No tienen `label_variant_ref`, así que la propagación no los alcanza. Solo quedan vinculados los
diseños donde la variante se aplique **después** de este cambio. Para vincular uno existente, el admin
vuelve a aplicarle la variante. No proponemos emparejarlos por heurística: es frágil y podría pisar
diseños que no tienen nada que ver.

---

## 5. Puntos a tener en cuenta

- **Diseño abierto en el editor:** si alguien tiene abierto un diseño mientras se propaga una
  variante, al guardar pisaría la versión propagada. Si el diseño ya tiene `updated_at`, el back podría
  rechazar un guardado hecho sobre una versión vieja (409) y el front pediría recargar. Si no, se acepta
  el riesgo.
- **Pedidos ya hechos:** ¿un PDF de un pedido viejo se vuelve a generar desde el diseño actual? Si es
  así, un pedido ya hecho saldría con la variante nueva. Confirmar si eso está bien o si los pedidos
  guardan su propia copia.
- **Imágenes:** la propagación copia las mismas rutas de storage que tiene la variante. Vale lo mismo
  que ya dice `PDF_VARIANTES_ETIQUETA.md` sobre no borrar imágenes que siga usando alguna variante.

---

## Preguntas para el back

1. ¿Les sirven los nombres de campos (`label_variant_ref`, `instance_id`, `variant_element_id`,
   `label_variant_instance_id`, `element_ids`, `all_elements`) o prefieren otros?
2. Para encontrar los diseños: ¿búsqueda en el JSON o tabla auxiliar?
3. ¿Los PDFs de pedidos ya hechos se regeneran desde el diseño actual?
4. ¿Hay `updated_at` en los diseños para detectar un guardado sobre una versión vieja?
5. ¿Está bien hacer todo dentro del `POST /label-shapes/{id}`, o prefieren un endpoint aparte, o un
   job en segundo plano si son muchos diseños?

Con esto definido, el front hace su parte: guardar `label_variant_ref` al insertar o aplicar,
desvincular al editar, ids nuevos al duplicar, los ids determinísticos de grupos y relaciones, y el
aviso con `usage` antes de guardar una variante.

---

## Respuesta del back

Ver `PDF_VARIANTES_ETIQUETA_PROPAGACION_BACK.md` — implementado tal cual se propuso acá, con los
nombres de campo que pidieron. Respuestas puntuales a las 5 preguntas en ese documento.
