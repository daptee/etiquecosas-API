# Importar un diseño desde el PDF legacy de un producto

Variante de [PDF_IMPORTAR_DESDE_PDF.md](PDF_IMPORTAR_DESDE_PDF.md): en vez de subir un PDF a mano, acá se elige un **producto** (y una **variante**, si ese producto tiene más de una temática) y el backend arma el PDF de muestra él solo, a partir de lo que ya está cargado en el sistema viejo (`product_pdf` / vistas de `tematica/*`) — no hay que generar el PDF a mano desde una venta ni subirlo después.

---

## Endpoint

```
POST /product-pdf-designs/import-from-product
Content-Type: application/json
```

| Campo | Tipo | Obligatorio | Descripción |
|---|---|---|---|
| `productId` | id | Sí | Producto con PDF legacy a convertir. |
| `variantId` | id | Solo si el producto tiene más de una temática | La variante (`product_variants.id`) que determina CUÁL temática importar. |
| `name` | string | No | Nombre del diseño nuevo. Si no se manda, usa `"{nombre del producto} (importado)"`. |
| `labelShapeId` | id | No | Forma de etiqueta a asociar (como en el alta normal). |

Respuesta: el `ProductPdfDesign` recién creado (mismo formato que `POST /product-pdf-designs` y que `import-from-pdf`), ya **vinculado** a `productId`/`variantId`.

```json
{
  "success": true,
  "message": "Diseño importado del PDF legacy (139 elementos en 1 página(s))",
  "data": { "id": 15, "name": "...", "data": { "pages": [ ... ] }, "is_published": false, ... }
}
```

Errores (todos `422` salvo el último):
- `"Este producto no tiene un PDF legacy asociado (tabla product_pdf)"` — no hay fila en `product_pdf` para ese `productId`.
- `"Este producto no tiene temáticas cargadas en su PDF legacy"` — la fila existe pero `data.tematicas` está vacío.
- `"Este producto tiene N temáticas distintas — hay que elegir una variante (variantId)..."` — falta `variantId` y hay más de una temática posible.
- `"La variante elegida no tiene una temática asociada en este producto"` — se mandó `variantId` pero ninguna temática matchea su atributo.
- `"No se pudo generar el PDF de muestra: ..."` (`500`) — la vista Blade de esa temática no existe o tiró un error al renderizar.

---

## Qué hace

1. Busca `product_pdf` por `productId` y lee `data.tematicas[]` (cada una trae `name`, `pdf-url` — la(s) vista(s) Blade a usar — y opcionalmente `color-range`/`images`/etc., exactamente lo que ya usa `EtiquetaService::generarEtiquetas()` para una venta real).
2. Si el producto tiene una sola temática, la usa directo. Si tiene varias, matchea `variantId` contra el `attribute_values.id` del primer atributo de esa variante (misma lógica que usa `ProductPdfResolverService::resolveLegacy()` para ventas reales).
3. Genera un PDF **de muestra** con esa temática (nombre placeholder `"NOMBRE APELLIDO"`, sin venta real detrás) usando `EtiquetaService::generarEtiquetas()` — el mismo motor de siempre, solo que con datos de mentira en vez de los de una venta.
   - Ese placeholder se usa a propósito para que, al detectarlo en el resultado, el elemento de texto quede guardado como **`value_mode: "dynamic"`, `dynamic_field: "nombre_apellido"`** — igual que sale en una venta real, no como texto fijo que diga literalmente `"NOMBRE APELLIDO"`.
4. Cada vista que haya generado esa temática (puede ser una o varias, ej. `principal` + `vinilo`) se analiza con `PdfDesignImportService::import()` (igual que `import-from-pdf`) y se agrega como una **página** distinta del mismo diseño.
5. **El texto nunca se superpone con el ícono de la misma celda**: cuando hay ambos, el elemento `text` no ocupa la celda entera (eso lo haría pasar por encima del ícono) — se recorta al espacio libre, igual que hace la tabla HTML del sistema viejo (`<td>ícono</td><td>texto</td>`, lado a lado o apilados según el diseño).
6. **Reconstruye el ícono EXACTO de cada celda** (no una aproximación): las vistas `tematica/principal/*.blade.php` referencian un índice fijo tipo `$plantilla['imagen'][2]` por celda — no es aleatorio, está escrito literal en el `.blade.php`. Leyendo ese archivo y extrayendo esos índices en orden (mismo orden en que dompdf dibuja las celdas), más la misma carpeta `storage/app/pdf/Iconos/Tematicas/{TEMATICA}/` que arma `EtiquetaService::generarEtiquetas()`, se sabe EXACTAMENTE qué archivo SVG va en cada celda. Ese archivo YA está cargado en el catálogo nuevo (`personalization_icons`, ver [database/sql/personalization_icons_tematicas.sql](database/sql/personalization_icons_tematicas.sql) — un ícono por cada archivo de `public/icons/Tematicas/*`), así que el elemento se guarda con el `icon_id` correspondiente — **nunca copiando el SVG como archivo propio del elemento** (`custom_icon_path`/`pdf-element-images`): un mismo ícono de catálogo se reusa en todos los diseños que lo necesiten, sin duplicar archivos.
7. Si para alguna celda no se pudo determinar el archivo exacto (la vista no tiene ese patrón, la temática no tiene carpeta de íconos — pasa con vistas "sin dibujo" —, o el archivo todavía no está cargado en el catálogo), esa celda puntual cae al fallback de antes: buscar en el catálogo un ícono con nombre parecido al de algún archivo de la carpeta, repartiendo los que encuentre entre las celdas sin match exacto. Si tampoco hay fallback, se queda con el placeholder `icon_id: 1`.
8. Borra el/los PDF de muestra generados — son solo un paso intermedio, no quedan en disco.
9. Crea el `ProductPdfDesign` **sin publicar** (`is_published: false`) y lo **vincula** al `productId`/`variantId` (`product_pdf_design_products`, con `theme_key = variantId` o `null` si no se mandó).

---

## Las celdas no quedan sueltas: agrupadas y con "Generar grilla" ya armado

Una temática con varias secciones (ej. Unicornio: maxi 4×4, vertical 6×2, super-maxi 3×2, super-mini 6×3) importa decenas de celdas (fondo+ícono+texto cada una) — para no tener que tocarlas una por una en el editor:

- **`group_id`**: los 2-3 elementos de UNA celda comparten un `group_id` — en el editor, clickear o mover cualquiera de los tres selecciona/mueve la celda entera de una (igual que si se hubiera generado a mano con "Agrupar selección").
- **`grids`** (`data.pages[].grids[]`): además, las celdas que comparten tamaño y forma (misma combinación fondo/ícono/texto) se detectan como una grilla y se arma el mismo formato que usa "Generar grilla" en el editor (`PdfGrid`: `cols`, `rows`, `gap_x_cm`, `gap_y_cm`, `template`, `members`) — el diseño importado llega con una entrada de `grids` por cada sección distinta (ej. Unicornio importa 4). Esto permite abrir "Generar grilla" después de importar y cambiar cols/filas/separación de TODA una sección de una, en vez de reacomodar cada celda a mano.
  - El `sig` de cada celda (que el editor usa para saber si una celda fue modificada a mano y no tocarla al reescalar) se calcula con el mismo algoritmo que el front (`PdfDesignHelper.elementSignature`, normalizar+ordenar+hash djb2) — coincide exacto para todo lo que generamos (números, hex, texto en español). Si en algún caso puntual no coincidiera (texto con caracteres fuera del rango normal), lo peor que pasa es que el editor trate esa celda como "ya modificada" y no la toque al reescalar — no rompe ni pierde nada.
  - Esto es **metadata nueva que el backend no usaba hasta ahora** (`EtiquetaService` genera el PDF leyendo `elements` igual que siempre, ignora `grids` por completo) — es pura comodidad para el editor, no cambia en nada cómo se genera el PDF final.

---

## Por qué es seguro vincularlo sin publicar

`ProductPdfResolverService::resolveAndGenerate()` solo usa un vínculo si su diseño tiene `is_published = true`. Un diseño recién importado por este endpoint queda sin publicar, así que **no cambia nada en la generación real de ninguna venta** hasta que alguien lo revise en el editor y lo publique a mano — igual que con un diseño armado o importado manualmente. Si ya había otro diseño publicado para ese mismo producto/variante, conviven los dos vínculos sin pisarse: el viejo sigue siendo el que se usa hasta que se publique el nuevo (y ahí sí conviene despublicar o desvincular el anterior).

---

## Qué NO hace (mismas limitaciones que `import-from-pdf`, salvo el ícono)

- El ícono sale exacto **solo cuando la vista Blade usa el patrón `$plantilla['imagen'][N]`** (las de `tematica/principal/*` lo usan) **y** el archivo correspondiente ya está cargado en `personalization_icons`. Para vistas sin ese patrón (ej. "sin dibujo", gama de colores), o un archivo nuevo que todavía no se cargó al catálogo, cae al fallback por nombre — que sigue siendo aproximado.
- No matchea tipografías contra el catálogo: el texto queda siempre con `font_id: null`.
- Solo el nombre del cliente se detecta como dinámico (`nombre_apellido`). El texto "PEDIDO # ..." (pie de página, cuando la temática lo trae) queda como texto FIJO con el número de muestra literal — no se convierte a `dynamic_field: "numero_pedido"` porque ese campo reemplaza todo el contenido del elemento por el número solo, y perdería el prefijo "PEDIDO #" que es parte del diseño; separarlo en dos elementos (uno fijo + uno dinámico) queda pendiente si hace falta.
- No reconoce ni evita duplicados: si se llama dos veces para el mismo producto/variante, crea dos diseños distintos (ambos sin publicar) — conviene borrar el anterior si era una prueba.

---

## Ejemplo de uso

```bash
# Producto con una sola temática
curl -X POST https://tu-dominio/api/product-pdf-designs/import-from-product \
  -H "Authorization: Bearer {token}" \
  -H "Content-Type: application/json" \
  -d '{"productId": 912}'

# Producto con varias temáticas: hay que indicar la variante
curl -X POST https://tu-dominio/api/product-pdf-designs/import-from-product \
  -H "Authorization: Bearer {token}" \
  -H "Content-Type: application/json" \
  -d '{"productId": 965, "variantId": 1020, "name": "Blanco y Negro (importado)"}'
```

Después de importar, conviene revisar en el editor: los íconos que matchearon exacto (vistas tipo `principal/*` con el archivo ya en el catálogo) ya deberían salir idénticos al PDF original y no necesitan tocarse; los que cayeron al fallback por nombre o al placeholder sí hay que reemplazarlos a mano. En cualquier caso falta asignar tipografías reales y pasar textos a dinámicos donde corresponda (ver [PDF_IMPORTAR_DESDE_PDF.md](PDF_IMPORTAR_DESDE_PDF.md#ejemplo-de-uso)) antes de publicar el diseño.
