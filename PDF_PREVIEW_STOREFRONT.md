# Vista previa de la etiqueta en la ficha de producto (storefront)

## Qué es esto

La ficha pública de un producto (`GET /api/v1/products/slug/{slug}` y `GET /api/v1/products/{id}`) ahora trae, además de todo lo que ya traía, la lista de **diseños de PDF del editor** vinculados a ese producto — para que el front pueda mostrarle al cliente una vista previa de cómo va a quedar su etiqueta antes de comprar.

No hace falta ningún parámetro nuevo para pedirlo: viene incluido siempre que el producto tenga al menos un diseño publicado.

---

## 1. Qué viene en la respuesta del producto

Ejemplo real: `GET https://api.etiquecosas.com.ar/api/v1/products/slug/combo-ya-sello-etiquetas-para-escribir-92900`

```json
{
  "message": "Producto obtenido exitosamente",
  "data": {
    "id": 92900,
    "name": "¡COMBO YA! Sello + etiquetas para escribir",
    "...": "...",
    "variants": [
      { "id": 1, "variant": { "attributesvalues": [{ "id": 137, "attribute_id": 5 }] }, "...": "..." },
      { "id": 2, "variant": { "attributesvalues": [{ "id": 142, "attribute_id": 5 }] }, "...": "..." }
    ],
    "pdf_designs": [
      {
        "id": 1,
        "label_shape_id": 12,
        "name": "Basquet - Maxi",
        "data": { "pages": [ { "sheet": { "...": "..." }, "elements": [ "..." ] } ] },
        "label_shape": { "id": 12, "name": "Maxi rectangular", "shape_type": "rect", "width_cm": 4.4, "height_cm": 2.4, "data": { "...": "..." } },
        "pivot": { "id": 5, "theme_key": 137 }
      },
      {
        "id": 2,
        "label_shape_id": 12,
        "name": "Basquet - Vertical",
        "data": { "...": "..." },
        "label_shape": { "...": "..." },
        "pivot": { "id": 6, "theme_key": 142 }
      }
    ]
  }
}
```

> Ojo: esta respuesta va en **snake_case** (`pdf_designs`, `label_shape_id`, `theme_key`), como el resto de esta API — no confundir con los endpoints de administración del editor (`/product-pdf-designs`), cuyos **requests** van en camelCase pero cuyas **respuestas** también son snake_case (ver `PDF_EDITOR.md`).

### Qué significa cada cosa

- `pdf_designs` es un array porque un producto puede tener **más de un diseño**, uno por variante (o uno único sin variante). Solo aparecen acá los diseños **publicados y activos** — un borrador que el admin todavía está editando en el editor nunca aparece en la ficha pública del producto.
- `pdf_designs[].pivot.theme_key` es el dato clave: indica a qué variante corresponde ese diseño. Es el mismo `id` que aparece en `variants[].variant.attributesvalues[0].id`.
- Si `pivot.theme_key` es `null`, ese diseño no depende de ninguna variante — es el único diseño de ese producto.
- `pdf_designs[].data` es el JSON completo del diseño (páginas, elementos, colores, etc. — mismo esquema que `PDF_EDITOR.md`), por si el front quiere armar su propia previsualización en HTML/canvas en vez de pedir el PDF ya renderizado (ver sección 3).

---

## 2. Cómo saber qué diseño le corresponde a la variante que el cliente eligió

1. El cliente selecciona una variante en el producto (como ya hace hoy).
2. Sacá el `id` de esa variante desde `variants[].variant.attributesvalues[0].id`.
3. Buscá en `pdf_designs` el que tenga `pivot.theme_key` igual a ese id.
4. Si el producto no tiene variantes (o el diseño no depende de ninguna), buscá el que tenga `pivot.theme_key: null`.
5. Si no encontrás ningún diseño para esa variante, es que ese producto no tiene editor nuevo configurado — no hay vista previa que mostrar (es normal, la mayoría de los productos todavía usan el sistema viejo).

```js
function encontrarDiseño(producto, varianteSeleccionada) {
  const themeKey = varianteSeleccionada?.variant?.attributesvalues?.[0]?.id ?? null;
  return producto.pdf_designs.find(d => d.pivot.theme_key === themeKey) ?? null;
}
```

---

## 3. Cómo mostrar la vista previa

### Opción A (recomendada): pedirle al backend el PDF ya renderizado

`GET https://api.etiquecosas.com.ar/api/v1/product-pdf-designs/{id}/preview?firstName=Juan&lastName=Perez`

- `{id}` es el `pdf_designs[].id` que encontraste en el paso anterior (no el `pivot.id`).
- `firstName`/`lastName` son lo que el cliente va escribiendo en el formulario de personalización — mandalos tal cual los tipea, para que la vista previa sea igual a lo que va a salir impreso.
- Esta ruta es pública (no necesita login de cliente ni de admin).
- La respuesta **no es JSON** — es el archivo PDF en sí (`Content-Type: application/pdf`). El front puede:
  - Mostrarlo en un `<iframe src="...">` o `<embed>` dentro de la página.
  - O simplemente ofrecer un botón "Ver vista previa" que abra esa URL en una pestaña nueva.
- Es el mismo motor que se usa para generar el PDF real de una compra — lo que ve el cliente en la vista previa es exactly igual a lo que va a recibir impreso.
- Si el cliente todavía no escribió nada, se puede pedir sin `firstName`/`lastName` (quedan "NOMBRE"/"APELLIDO" de ejemplo) o mandar valores placeholder propios.

### Opción B: armar la vista previa en el propio front (HTML/canvas)

Si prefieren no pegarle al backend por cada tecla que el cliente escribe (por rendimiento, o para una preview más instantánea), pueden usar directamente `pdf_designs[].data` (mismo JSON documentado en `PDF_EDITOR.md`) y dibujar ustedes mismos el rectángulo con sus elementos (`type: background/icon/text`, posiciones en `x_cm`/`y_cm`/`width_cm`/`height_cm`), reemplazando a mano los elementos con `dynamic_field: "nombre"/"apellido"/"nombre_apellido"` por lo que el cliente va tipeando. Es más trabajo de implementar, pero da una preview instantánea sin ida y vuelta al servidor. La Opción A sigue siendo la que garantiza que se vea *exactamente* igual al PDF final (fuentes, íconos, colores CMYK, etc.).

---

## 4. Resumen rápido

1. Pedís el producto como siempre (`/products/slug/{slug}`).
2. Si `pdf_designs` no viene vacío, ese producto tiene vista previa disponible.
3. Cuando el cliente elige variante + escribe su nombre, matcheás por `pivot.theme_key` y llamás a `GET /product-pdf-designs/{id}/preview?firstName=...&lastName=...`.
4. Mostrás el PDF que te devuelve esa URL.
