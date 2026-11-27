> **Superado.** Este enfoque (resolución en memoria, nunca persistida) se reemplazó por propagación con
> persistencia al guardar la variante — ver `PDF_VARIANTES_ETIQUETA_PROPAGACION.md` y
> `PDF_VARIANTES_ETIQUETA_PROPAGACION_BACK.md`. El código de este documento ya no existe en el back
> (se borró `LabelShapeVariantResolver` y los enganches que llamaba). Se deja este archivo como
> referencia histórica de por qué se descartó.

# Variantes de etiqueta en vivo

Resuelve el pedido original: "que al editar una variante de etiqueta, ese cambio impacte en todos los
PDF que la usan" — lo que `PDF_TEMATICAS_EN_VIVO.md` NO resolvía (ver
`PDF_TEMATICAS_EN_VIVO_APLICADO.md`, el análisis que mandó el front: esa resolución es de colores/íconos
de una `PdfTheme`, no de variantes de `label_shapes.data.variants[]`).

## Decisión de comportamiento

Se preguntó explícitamente qué hacer cuando una etiqueta ya tiene retoques manuales hechos DESPUÉS de
aplicarle la variante (mover un ícono, cambiar un texto, etc.). Se eligió:

**La variante siempre gana.** Cada vez que se genera un PDF (o se abre el diseño en el editor), la
etiqueta se reconstruye desde la definición ACTUAL de la variante — cualquier retoque manual hecho
sobre esa etiqueta puntual se pierde. Es la misma lógica que ya usan las temáticas
(`PDF_TEMATICAS_EN_VIVO.md`): simple y consistente, sin un segundo estado ("¿está actualizada o no?")
que mantener.

## Qué se guarda

Al elemento `background` que representa la etiqueta (el que ya tiene `label_shape_id`) se le suma
**`variant_id`**: el id de la entrada usada dentro de `label_shapes.data.variants[]`. Nada más — los
elementos "de arriba" (textos, íconos) NO necesitan ningún campo nuevo: se identifican por geometría,
igual que ya hace el front para armar/guardar una variante (`elementsOnLabel` en
`LabelShapeVariantsHelper.ts`).

## Cómo se resuelve (`app/Services/LabelShapeVariantResolver.php`)

Para cada elemento `background` con `label_shape_id` + `variant_id`:

1. Busca la `LabelShape` y, dentro de `data.variants[]`, la entrada con ese `id`. Si la forma o la
   variante ya no existen, la etiqueta queda tal cual estaba guardada (sin romper el diseño).
2. Encuentra los elementos actuales "de arriba" de esa etiqueta (mismo criterio geométrico que
   `elementsOnLabel`: el centro del elemento, visto sin la rotación de la etiqueta, cae dentro de su
   rectángulo) y los `layout_groups` que son enteramente suyos.
3. Los borra y los reemplaza por una instancia fresca de la variante actual — ids nuevos para cada
   sub-elemento, reanclada a la posición/rotación donde está hoy esa etiqueta (mismo algoritmo que
   `instantiateLabelVariant`/`rotatePlacedLabelVariant` del front, portado a PHP).
4. La etiqueta en sí conserva: su `id`, posición, rotación y `group_id` (por si el admin la agrupó con
   algo de afuera). Todo lo demás (fill, borde, radio de esquina, etc.) sale de `variant.label`.

Es resolución en memoria, igual que `PDF_TEMATICAS_EN_VIVO.md`/las páginas prestadas: **nunca se
persiste**. Lo que se guarda en la base sigue siendo lo último que el editor guardó (la "foto" de
cuando se aplicó o se retocó la variante por última vez) — es un detalle de implementación, no algo
que el front necesite tener en cuenta más que para la sección 2 de abajo.

### Dónde se engancha

- `EtiquetaService::normalizarPaginasDesign()` — cubre generación real por-pedido, páginas combinadas,
  pliegos y el preview de layout del editor, todo de una vez (ver `paginasDeDesign()`,
  `generarEtiquetasDesdeDesign()`, etc.). Corre DESPUÉS de resolver páginas prestadas, por si una
  página prestada es justo la que trae la etiqueta.
- `AccumulatingPdfDesignService::celdaDelDesign()` — el flujo acumulado/diario. Este método lee
  `data.pages` directo, sin pasar por `normalizarPaginasDesign`, así que necesitaba su propio enganche
  (si no, una variante editada nunca se hubiera reflejado en una hoja acumulada nueva). **Nota aparte,
  no tocada en este cambio**: este mismo método tampoco resuelve páginas prestadas — es un gap previo
  y separado (el mismo que tenía `show()` antes de este arreglo), lo marco para quien lo quiera
  encarar.
- `ProductPdfDesignController::show()`/`index()` — para que el editor web muestre lo mismo que termina
  impreso. Nuevo método `resolveLabelVariants()`, hermano de `resolveSourcePages()` (misma idea: fetch
  → mutar en memoria → reasignar `$design->data`, nunca `save()`).

## Probado

- Una etiqueta con variante se reconstruye correctamente (elementos viejos desaparecen, los de la
  variante aparecen con la posición/tamaño de catálogo actual).
- Editar la variante en la biblioteca se refleja en la siguiente resolución, sin tocar el diseño.
- Un retoque manual guardado sobre esa etiqueta puntual se pierde en la siguiente resolución (el
  comportamiento elegido).
- Rotación: una etiqueta rotada reubica sus elementos de arriba correctamente alrededor de su centro.
- Si se borra la variante o la forma, la etiqueta no se rompe: queda con lo último guardado.
- Probado también el camino real de generación (`EtiquetaService::paginasDeDesign()`), no solo el
  resolver aislado.

## Pendiente del front

Ver `PDF_VARIANTES_ETIQUETA_EN_VIVO_FRONT.md` — falta que el editor guarde `variant_id` al aplicar una
variante, y que su propio preview resuelva en vivo (mismo problema de fondo que ya se encontró con las
temáticas: sin esto, el editor va a seguir mostrando la etiqueta vieja mientras el PDF ya sale
actualizado).
