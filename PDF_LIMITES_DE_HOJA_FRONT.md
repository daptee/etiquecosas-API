# Front: avisar/frenar en "Generar grilla" ANTES de que las celdas se salgan de la hoja

Contexto completo en `PDF_LIMITES_DE_HOJA.md` — el diseño 111 terminó con una fila entera de su grilla (fondo+ícono+texto de varias etiquetas) posicionada por debajo del borde físico de la hoja, porque **`PdfDesignHelper.computeGridCells()` (`utils/PdfDesignHelper.ts`, la función que arma "Generar grilla") nunca compara el resultado contra el tamaño de la hoja** — con `cols`/`rows` más grandes de lo que entra físicamente, agrega igual todas las celdas pedidas, cada vez más abajo/a la derecha, sin ningún tope.

El backend ahora **rechaza** guardar cualquier diseño con un elemento fuera de la hoja (422, mismo formato que un `layout_groups` inválido) — así que esto ya no se puede volver a guardar silenciosamente. Pero sin un aviso en la propia herramienta, el admin va a seguir pudiendo armar una grilla que se sale de la hoja (la ve perfecto en el preview mientras edita cols/rows, porque nada se lo impide ahí) y recién se entera al tratar de GUARDAR el diseño entero, con un error que no dice "la grilla que armaste hace rato se sale" sino una lista de ids de elementos — mala experiencia, mejor cortar el problema en el origen.

## Qué hay que agregar

**`composables/productPdfDesigns/useDesignEditor.ts`**, en `onPreviewGrid()` (línea ~1017) y/o `pendingGridPreviewElements` (línea ~1011-1015) — ambas ya tienen `activePage.value.sheet.width_cm`/`height_cm` disponible (es la página activa):

- Calcular la caja que ENGLOBA todas las celdas de `pendingGridPreviewElements.value` (min/max de `x_cm`/`y_cm`/`x_cm+width_cm`/`y_cm+height_cm` de todos los elementos generados) y compararla contra `activePage.value.sheet.width_cm`/`height_cm`.
- Si se sale, mostrar un aviso visible en el panel de "Generar grilla" (ej. "Esta grilla se sale de la hoja — bajá cols/rows o el gap") y, si el componente que llama a `onPreviewGrid` tiene un botón "Aceptar"/"Generar", deshabilitarlo mientras el preview esté fuera de los límites — mejor prevenir ahí que dejar que el admin confirme algo que el backend va a terminar rechazando igual al guardar el diseño completo.
- El preview ghost (las celdas fantasma que ya se dibujan mientras se ajustan cols/rows, ver el comentario en el propio archivo: "rendered as ghost elements but never pushed into the design") es un buen lugar para además pintarlas en rojo/con un borde de advertencia si se salen, en vez de solo bloquear el botón sin indicar cuál celda es el problema.

No hace falta tocar `computeGridCells()` en sí (seguiría generando las celdas igual, es una función pura) — el chequeo va en la capa que YA tiene el tamaño de la hoja a mano (`useDesignEditor.ts`), antes de permitir `commitPendingGrid()`.

## Backend — ya está listo

- Cualquier guardado (`POST`/`PUT` de un diseño) con un elemento fuera de `sheet.width_cm`/`height_cm` ahora devuelve 422 con `data.pages.{i}.elements.{j}: ["mensaje con las coordenadas exactas"]` — mismo shape que un error de `layout_groups`, así que si el formulario ya muestra esos errores genéricamente no hace falta nada especial para mostrarlos, pero conviene el aviso preventivo de arriba para no depender de llegar hasta ahí.
