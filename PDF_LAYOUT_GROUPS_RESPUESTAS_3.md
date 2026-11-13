# Respuesta del backend (3): relaciones entre elementos (`layout_groups` v2)

La v2 ya está implementada tal como la describe el documento del frontend. El contrato actualizado quedó en [PDF_LAYOUT_GROUPS.md](PDF_LAYOUT_GROUPS.md). Abajo: las respuestas a las preguntas, y las decisiones que tomamos donde el documento dejaba margen.

## Respuestas a las preguntas

### 1. ¿El ancho del texto se puede medir siempre? ¿Cambia el tiempo de generación?

Sí, se mide siempre, en cualquier relación. Se usa la misma función con la que dompdf dibuja el texto (`FontMetrics::getTextWidth`), con la fuente, el tamaño y el letter-spacing ya resueltos. Comparado contra el PDF real, el primer glifo de cada renglón empieza a menos de 0,005 cm del `x_cm` calculado, también con letter-spacing.

El tiempo casi no cambia:

- la primera medición de cada proceso tarda unos 110 ms, porque arma el medidor y carga las fuentes;
- después, medir y acomodar 15 páginas tarda unos 5 ms;
- como referencia, el render mínimo de un PDF con dompdf ya tarda unos 80 ms.

### 2. ¿Hay problema con que `container_element_id` apunte a un miembro de otro grupo?

No. El centro se toma siempre de la **caja de diseño** del contenedor (la que está guardada), no de la posición ya acomodada. Por eso el orden de los grupos no importa.

Si el contenedor es un texto, se usa su caja de diseño (`x/y/width_cm/height_cm`), no el ancho medido de su texto.

### 3. ¿`links` y los campos del modelo 1 juntos durante la transición?

Nos resulta cómodo, sin problema. Si hay `links`, `direction`, `gap_cm` y `align` se ignoran y **no se validan**, así que no pueden generar un 422. Con esta versión desplegada, el editor ya puede dejar de mandarlos. Los grupos viejos sin `links` siguen funcionando.

### 4. Íconos con fondo transparente

Se puede, pero con una limitación importante: **casi todos los íconos son SVG** (localmente, 169 de 173 archivos).

- **PNG, WebP y JPG:** se puede calcular el rectángulo visible con GD (que está instalado), recorriendo la transparencia una vez por archivo y guardando el resultado en caché. Costo: alrededor de 1 día.
- **SVG:** GD no los lee. Habría dos caminos:
  - instalar Imagick en el servidor para rasterizarlos (hoy no está);
  - calcular los límites leyendo los paths del SVG, que es frágil con transformaciones, trazos y recortes.

Además, el editor tendría que calcular exactamente lo mismo (por ejemplo, con `getImageData` sobre un canvas) para que coincidan.

**Propuesta:** dejarlo fuera por ahora. Si es importante, que sea opcional por elemento (por ejemplo, `measure_visible_bounds: true`) y empezar solo con imágenes rasterizadas. Antes de avanzar necesitamos saber si los íconos donde molesta son SVG del catálogo o imágenes subidas.

## Decisiones donde el documento dejaba margen

- **Cada link necesita las dos reglas** (`x` e `y`). Si falta una, es 422.
- **Modelo 1 con `align: start/end`:** el resultado cambia respecto de la versión anterior, porque ahora la alineación es contra el ancho **medido** del texto y no contra el ancho de su caja. Con `align: center` (el caso habitual) el resultado es idéntico.
- **Desborde (`overflow_cm`):** ahora es cuánto se sale el conjunto, por el lado que más se salga, de su etiqueta (si el contenedor es un `background`) o de la hoja (en los demás casos). Antes era el exceso total en el eje principal. Por ejemplo, un conjunto de 2,47 cm en una etiqueta de 1 cm antes daba `1.47`; ahora da `0.74` (sale 0,74 cm por arriba y 0,74 cm por abajo).
- **`?format=layout`:** además de `center`, cada grupo trae `container` con el `id` y la caja del contenedor usado, o `null` si se centró en `anchor`.

## Casos de prueba

Los casos de la sección 7 ya están cubiertos por tests automáticos del backend: ícono arriba y abajo, horizontal a cada lado, `start`/`end`, distancia negativa, centrado en etiqueta, en otro elemento o en `anchor`, miembros ausentes y equivalencia del modelo 1.

Para la comparación final contra el editor, pueden armar el diseño de prueba y pasarnos el id. Les devolvemos el PDF y el JSON de `?format=layout`, con tolerancia de ±0,05 cm.
