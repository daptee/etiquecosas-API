# Ningún elemento puede quedar fuera de los límites de la hoja

## El problema

El diseño **111** ("Etiquetas temáticas - BOSQUE (importado)") terminó con una fila entera de su grilla (fondo + ícono + texto de varias etiquetas) posicionada **por debajo del borde físico de la hoja** — esas etiquetas nunca se iban a imprimir bien (quedan cortadas o directamente fuera del papel), y nada en el backend lo impedía al guardar.

## Causa raíz

La herramienta **"Generar grilla"** del editor (`PdfDesignHelper.computeGridCells()`, front) arma `cols × rows` celdas apiladas a partir de la selección, **sin comparar nunca el resultado contra el tamaño de la hoja** (`page.sheet.width_cm`/`height_cm`) — si se le pide más filas/columnas de las que entran físicamente, agrega igual todas las que se le pidan, cada vez más abajo/a la derecha, sin ningún tope. Confirmado leyendo el código: no hay ninguna comparación contra `sheet` en toda la función.

El backend tampoco validaba nunca la posición de un elemento contra el tamaño de la hoja al guardar un diseño — por eso esa grilla mal armada se pudo guardar sin problema.

Ver `PDF_LIMITES_DE_HOJA_FRONT.md` para el pedido al front (avisar/frenar esto ANTES de guardar, en la propia herramienta).

## Fix en el backend (lo que ya no puede volver a pasar)

`PdfDesignSanitizer::validateElementsWithinSheet(array $elements, array $sheet, float $tolCm = 0.05): array` — por cada elemento de la página, calcula su caja ya rotada (si tiene `rotation_deg`, mismo cálculo que `PdfDesignImportService::clampCenter()`/`rotatedTextElement()`: un elemento girado cuya caja SIN girar nominalmente se "sale" puede entrar perfecto una vez rotado) y la compara contra `sheet.width_cm`/`height_cm`. `$tolCm` es solo margen de redondeo — un elemento realmente fuera de la hoja siempre es un error, no hay tolerancia para "bleed" intencional (nada se imprime fuera del papel físico).

`ProductPdfDesignController::store()`/`update()`/la importación de un diseño combinado desde otro entorno (`PdfDesignTransferService`) ahora llaman a esto (`sheetBoundsErrors()`) junto con la validación de `layout_groups` que ya existía — **un diseño con cualquier elemento fuera de la hoja ya no se puede guardar** (422, mismo formato de error que un `layout_groups` inválido: `"data.pages.{i}.elements.{j}": ["mensaje"]`).

Verificado: el diseño 111 real (snapshot local) pasa sin errores (0 falsos positivos); un elemento sintético más allá del borde sí se detecta, con coordenadas exactas en el mensaje.

## Reparar diseños que ya quedaron mal guardados (de antes de este fix)

Nuevo comando: `php artisan pdf-designs:corregir-etiquetas-fuera-de-hoja {--design=* : solo estos ids} {--apply : escribir, si no solo muestra qué haría}`.

- Por cada elemento fuera de la hoja, borra la **celda de grilla entera** a la que pertenece (todos los elementos de ese mismo slot cols/rows — fondo+ícono+texto suelen desbordar juntos, nunca uno solo), no solo el elemento puntual que disparó el error, para no dejar huérfanos.
- Si el elemento fuera de la hoja es parte de la PLANTILLA de la grilla (`grid.template`), se borra la grilla entera (no puede seguir sin su plantilla).
- `layout_groups` que se quedan con un solo miembro (o ninguno) tras el borrado se eliminan — mismo criterio que ya usa el editor del front con una "Relación" rota.
- Por defecto es una simulación (tabla con lo que haría, sin tocar nada); con `--apply` escribe los cambios dentro de una transacción.

Probado end-to-end con un diseño sintético (grilla de 1x3, la 3ra fila se salía de una hoja de 10x10cm): la simulación detectó exactamente esa fila (3 elementos), `--apply` la borró junto con su entrada en `grids` y su `layout_group`, dejando las otras 2 filas intactas, y una segunda corrida de `validateElementsWithinSheet()` ya no reportó errores.

**Para reparar el diseño 111 en producción**: correr primero sin `--apply` para ver exactamente qué va a borrar, confirmar que es la fila de más (y no algo que en realidad sí entra, por un margen/tolerancia distinto al de acá), y recién después correr con `--apply`.

```
php artisan pdf-designs:corregir-etiquetas-fuera-de-hoja --design=111
php artisan pdf-designs:corregir-etiquetas-fuera-de-hoja --design=111 --apply
```
