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
7.1. **También matchea la tipografía real de esa vista** (ver sección siguiente) — el texto ya no sale siempre con `font_id: null`.
8. Borra el/los PDF de muestra generados — son solo un paso intermedio, no quedan en disco.
9. Crea el `ProductPdfDesign` **sin publicar** (`is_published: false`) y lo **vincula** al `productId`/`variantId` (`product_pdf_design_products`, con `theme_key = variantId` o `null` si no se mandó).

---

## La tipografía real de la vista, no una genérica

Cada vista legacy tiene su fuente fija en el código (`@font-face` + una clase CSS, ver `resources/views/tematica/**/*.blade.php` y `public/css/etiquetas.css`) — no es aleatoria ni configurable por producto:
- 113 de 115 vistas (`principal/*`, `vinilo/*`, `super-mini/*`, `super-maxi/*`, `maxi/*`, etc.) heredan **Oswald** del CSS compartido, aplicada por igual a TODO el texto.
- Un puñado de vistas (`stars/TRANSFER MAYUSCULA` -> **Antonio**, `stars/TRANSFER BOLD` -> **TitanOne**) declaran la suya propia para el nombre del cliente.

El importador detecta esa familia leyendo el `.blade.php` de la vista (mismo criterio que ya se usa para los íconos: leer el código fuente, no adivinar) y busca una `Typography` del catálogo nuevo con ese nombre (o, si no hay ninguna con ese nombre exacto, por el nombre de archivo original que se subió, ej. `Oswald-Regular.ttf`). Si encuentra match, el texto se guarda con ese `font_id` en vez de `null`.

**Limitación**: si la tipografía detectada (ej. "Antonio", "TitanOne") todavía no está cargada en el catálogo (`typographies`), no hay nada para matchear y el texto queda con `font_id: null` como antes — hay que cargarla una vez en el ABM de tipografías para que, de ahí en más, se matchee sola. Además, se aplica UNA fuente por página/vista a TODO su texto (igual que pasa realmente en casi todas las vistas) — en los pocos casos (`stars/*`) donde el texto secundario (ej. "PEDIDO #") usa una fuente distinta a la del nombre (ahí es Helvetica, sin archivo propio), ese texto puntual puede quedar con la fuente del nombre en vez de la suya — revisar a mano en esos casos específicos.

---

## Las celdas no quedan sueltas: agrupadas y con "Generar grilla" ya armado

Una temática con varias secciones (ej. Unicornio: maxi 4×4, vertical 6×2, super-maxi 3×2, super-mini 6×3) importa decenas de celdas (fondo+ícono+texto cada una) — para no tener que tocarlas una por una en el editor:

- **`group_id`**: fondo e ícono de UNA celda comparten un `group_id` — en el editor, clickear o mover cualquiera de los dos selecciona/mueve la celda entera de una (igual que si se hubiera generado a mano con "Agrupar selección"). El **texto queda afuera del grupo a propósito**: el editor trata una selección de elementos de distinto tipo como "mixta" y ahí deja de mostrar la sección Tipografía en el panel lateral — si el texto compartiera grupo con el fondo, un clic simple sobre el texto recién importado abriría una selección mixta y no se podría tocar su fuente/tamaño/alineación sin desagruparlo primero a mano.
- **`grids`** (`data.pages[].grids[]`): además, las celdas que comparten tamaño y forma (misma combinación fondo/ícono/texto) se detectan como una grilla y se arma el mismo formato que usa "Generar grilla" en el editor (`PdfGrid`: `cols`, `rows`, `gap_x_cm`, `gap_y_cm`, `template`, `members`) — el diseño importado llega con una entrada de `grids` por cada sección distinta (ej. Unicornio importa 4). Esto permite abrir "Generar grilla" después de importar y cambiar cols/filas/separación de TODA una sección de una, en vez de reacomodar cada celda a mano.
  - El `sig` de cada celda (que el editor usa para saber si una celda fue modificada a mano y no tocarla al reescalar) se calcula con el mismo algoritmo que el front (`PdfDesignHelper.elementSignature`, normalizar+ordenar+hash djb2) — coincide exacto para todo lo que generamos (números, hex, texto en español). Si en algún caso puntual no coincidiera (texto con caracteres fuera del rango normal), lo peor que pasa es que el editor trate esa celda como "ya modificada" y no la toque al reescalar — no rompe ni pierde nada.
  - Esto es **metadata nueva que el backend no usaba hasta ahora** (`EtiquetaService` genera el PDF leyendo `elements` igual que siempre, ignora `grids` por completo) — es pura comodidad para el editor, no cambia en nada cómo se genera el PDF final.
  - **Limitación conocida (del editor, no del importador)**: si las celdas de una sección tienen cada una un color de fondo distinto a propósito (ej. las vistas "COLOR RANGE"), usar "Generar grilla" para cambiar filas/columnas/separación le pisa el color propio de TODAS las celdas por el de la celda molde (template) — "Generar grilla" siempre clona la celda molde, no sabe de colores por-celda. No es algo que el importador pueda evitar desde los datos que manda; el fix real es en `applyGridParams` del editor (ver `useDesignEditor.ts`), para que una celda ya existente conserve su propio color en vez de clonarlo del template al regenerarse.

---

## Por qué es seguro vincularlo sin publicar

`ProductPdfResolverService::resolveAndGenerate()` solo usa un vínculo si su diseño tiene `is_published = true`. Un diseño recién importado por este endpoint queda sin publicar, así que **no cambia nada en la generación real de ninguna venta** hasta que alguien lo revise en el editor y lo publique a mano — igual que con un diseño armado o importado manualmente. Si ya había otro diseño publicado para ese mismo producto/variante, conviven los dos vínculos sin pisarse: el viejo sigue siendo el que se usa hasta que se publique el nuevo (y ahí sí conviene despublicar o desvincular el anterior).

---

## Qué NO hace (mismas limitaciones que `import-from-pdf`, salvo el ícono)

- El ícono sale exacto **solo cuando la vista Blade usa el patrón `$plantilla['imagen'][N]`** (las de `tematica/principal/*` lo usan) **y** el archivo correspondiente ya está cargado en `personalization_icons`. Para vistas sin ese patrón (ej. "sin dibujo", gama de colores), o un archivo nuevo que todavía no se cargó al catálogo, cae al fallback por nombre — que sigue siendo aproximado.
- La tipografía (ver sección de arriba) sale exacta **solo si ya hay una `Typography` cargada en el catálogo con ese nombre** — si no, queda con `font_id: null` como antes. Y se aplica una sola fuente por página a todo su texto, así que el texto secundario de las pocas vistas con una fuente distinta para él (`stars/*`) puede necesitar corregirse a mano.
- Las temáticas **"PERSONALIZABLE"** (vistas `tematica/*-and-*/PERSONALIZABLE*`, ej. combos "Maxi - Spot") no tienen un color ni un ícono real detrás — en una venta real el cliente los elige al comprar. El PDF de muestra usa un gris de relleno (`#CCCCCC`) y un ícono cualquiera del catálogo (`icon_id: 1`, el mismo placeholder de siempre) solo para que la vista "con ícono" tenga algo real que dibujar — el admin tiene que reemplazar ambos a mano en el editor por el color/ícono reales de la temática. Si el `pdf-url` guardado ya apunta a la variante "SIN ICONO" de la vista, no se simula ningún ícono (ese producto no tiene esa opción).
- Las vistas con una fila **"spot"** (celdas circulares — `spot/*`, `spot-and-maxi/*`, `maxi-and-spot/*`, `spot-and-super-mini/*`) no le pintan fondo propio a esas celdas (el "fondo" que se detecta es, en realidad, el color del ícono, y el nombre queda sin alinear con su ícono) — el importador recalcula el fondo de cada una de esas celdas, centrado en el punto medio entre ícono y nombre, con `label_shape_id` apuntando al label_shape circular `"Spot"` del catálogo (así el editor la muestra/recorta como círculo) y recentra el nombre debajo del ícono. El **tamaño** de ese fondo es el tamaño FIJO del label_shape `"Spot"` del catálogo (3×3cm — el mismo que declara `.circulo-personaje` en el CSS de estas vistas), no un cálculo a partir de ícono+texto (eso podía dar un círculo más grande que el real). El nombre también se agrega recién ahí a `data.pages[].grids[]` (no formaba parte de la grilla cuando se armó, más arriba, porque todavía no se sabía qué ícono le correspondía) — sin esto, usar "Generar grilla" en el editor para cambiar cols/filas/separación de una fila "spot" regeneraba bien el fondo+ícono pero dejaba el nombre sin tocar. Además, el `gap_x_cm`/`gap_y_cm` de esa fila (calculado antes, con la posición de los fondos chicos originales) se **recalcula de nuevo** acá, ya con los fondos agrandados a 3×3cm — si no, quedaba un espacio mucho mayor al real entre columnas, y "Generar grilla" terminaba empujando columnas enteras fuera de la hoja al cambiar filas/columnas. El recálculo no compara contra el lado del círculo (3cm) sino contra el rectángulo que realmente engloba fondo+ícono+texto de la celda molde — el nombre es más ANCHO que el círculo (sobresale un poco a los costados, aunque entra bien en alto), así que el "ancho de celda" horizontal que usa el editor al regenerar columnas (`PdfGrid`, ver `applyGridParams` en `useDesignEditor.ts`) es mayor a 3cm; calcular el gap contra 3cm dejaba de más y corría las columnas de su lugar original. Si todavía no existe un label_shape con ese nombre exacto, no se toca nada (quedan como antes).
- El nombre del cliente se detecta como dinámico (`dynamic_field: "nombre_apellido"`). El texto "PEDIDO # ..." (pie de página/costado, cuando la temática lo trae) usa el otro mecanismo disponible para esto: queda con `value_mode: "fixed"` pero el número de muestra se reemplaza por el placeholder `{{numero_pedido}}` dentro de `content` (ej. `"PEDIDO # {{numero_pedido}}"`) — el mismo esquema de placeholders que ya resuelve `EtiquetaService::resolverElementoDesign()`, que preserva el prefijo fijo "PEDIDO #" y solo sustituye el número por el de la venta real al generar el PDF.
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
