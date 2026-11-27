# Propagación de variantes de etiqueta — confirmación del back

Implementado tal cual lo propuso el front en `PDF_VARIANTES_ETIQUETA_PROPAGACION.md`. **Reemplaza** el
intento anterior de "resolución en vivo" (`PDF_VARIANTES_ETIQUETA_EN_VIVO.md`/`_FRONT.md`, marcados
como superados): esos nunca llegaron a usarse en producción, y este modelo (persistir al guardar la
variante, con desvinculación parcial por parte/elemento) es el que el front terminó necesitando.

## Respuestas a las 5 preguntas

1. **Nombres de campo:** se usan tal cual los propuso el front — `label_variant_ref`, `instance_id`,
   `variant_element_id`, `label_variant_instance_id`, `element_ids`, `all_elements`, `label_parts`.
2. **Cómo se encuentran los diseños:** búsqueda simple (`data LIKE '%{variantId}%'` como pre-filtro +
   verificación real parseando el JSON), no tabla auxiliar. Con la base actual (191 diseños, 20 formas
   de etiqueta, 1 con variantes) no hace falta la complejidad de una tabla indexada. Si el catálogo
   crece mucho, se puede sumar después sin cambiar el contrato con el front.
3. **Pedidos ya hechos:** sí, el PDF de un pedido se regenera siempre desde el diseño actual
   (`ProductPdfResolverService::resolveAndGenerate()` carga el diseño vigente, no una copia; `reimprimir`
   un pedido viejo ya salía con cambios posteriores del diseño, de forma independiente a esto). Lo único
   que queda fijo al momento de la venta es `SaleProduct::customization_data` (nombre/color/ícono que
   eligió el cliente), no el diseño en sí. No es un riesgo nuevo que introduzca la propagación.
4. **`updated_at` para detectar un guardado viejo:** sí existe (`product_pdf_designs.updated_at`), pero
   **no se implementó** el rechazo 409 por ahora. Es una app de uso interno con pocos admins editando a
   la vez; agregar ese chequeo es fácil de sumar después si en la práctica se pisan ediciones, pero no
   tiene sentido construirlo de entrada para un riesgo que todavía no se vio. Si pasa, avisen y se agrega.
5. **Dónde corre:** dentro del mismo `POST /label-shapes/{id}`, síncrono (nada de job en segundo plano).
   Con el volumen actual tarda milisegundos; si en el futuro un catálogo tiene cientos de diseños usando
   la misma variante, se puede pasar a una cola sin cambiar la respuesta que ya devuelve `updated_designs`.

## Qué cambia en la práctica

- `PdfDesignSanitizer::sanitizeElements()` ahora whitelistea `label_variant_ref` (dos formas: completa
  en la etiqueta, solo `instance_id`+`variant_element_id` en un elemento — ver
  `sanitizeLabelVariantRef()`). El campo viejo `variant_id` suelto (del intento de "en vivo") ya no se
  usa ni se sanea.
- `PdfDesignSanitizer::sanitizeLayoutGroups()` ahora deja pasar `label_variant_instance_id`.
- `App\Services\LabelShapeVariantPropagator` (nuevo, reemplaza al `LabelShapeVariantResolver` que se
  borró) tiene tres entradas:
  - `propagateVariant(LabelShape $shape, array $variant): array` — reescribe y persiste
    (`$design->save()`) cada diseño que use esa variante. Devuelve `[{id, name}, ...]`.
  - `usageForVariant(int $labelShapeId, string $variantId): array` — solo lectura, para el endpoint de
    aviso.
  - `unlinkVariant(int $labelShapeId, ?string $variantId): array` — saca `label_variant_ref` (etiqueta,
    elementos y relaciones) sin tocar nada más. `$variantId = null` es "se borró la forma entera".
- `LabelShapeController::update()`: compara `data.variants[]` antes/después del guardado. Por cada
  variante que cambió (existía antes y es distinta) llama a `propagateVariant`; por cada variante que
  desapareció llama a `unlinkVariant`. La respuesta ahora trae `metaData.updated_designs`.
- `LabelShapeController::delete()`: antes de borrar la forma, desvincula todas sus variantes de
  cualquier diseño que las use (igual criterio que borrar una variante puntual).
- Nuevo endpoint `GET /label-shapes/{id}/variants/{variantId}/usage` →
  `LabelShapeController::variantUsage()`.
- Se sacaron los enganches de la "resolución en vivo" que ya no hacen falta: `EtiquetaService`,
  `AccumulatingPdfDesignService`, `ProductPdfDesignController` y `ProductController` volvieron a leer
  `data` tal cual está guardado — ahora siempre viene al día porque se persiste en el momento de guardar
  la variante, no hace falta reconstruir nada en cada lectura.

## Probado

- Editar una variante (cambiar el fill de la etiqueta y el texto de un elemento) propaga al diseño:
  conserva `id`/posición/rotación/`group_id` de la etiqueta, reemplaza el elemento vinculado
  conservando su `id` y su `label_variant_ref`, devuelve `updated_designs` con el diseño afectado.
- Agregar un elemento nuevo a una variante con `all_elements: true` lo suma al diseño en la posición
  correcta, con un `id` e `instance_id` nuevos.
- Etiqueta rotada 90°: el elemento y la relación (`layout_groups`, con `anchor`) se reubican
  correctamente alrededor del centro de la etiqueta — verificado a mano contra el cálculo esperado.
- `group_id` de un elemento de la variante queda `"{instance_id}:{group_id}"`, sin colisionar entre
  instancias.
- `GET .../usage` devuelve los diseños que usan la variante sin modificar nada.
- Borrar la variante (sacarla de `data.variants`) desvincula la etiqueta y sus elementos
  (`label_variant_ref` desaparece) sin tocar ningún otro dato — el diseño queda como estaba al momento
  de la desvinculación.

## Pendiente del front

Guardar `label_variant_ref` al insertar/aplicar una variante (y al duplicar, con `instance_id` nuevo),
desvincular al editar a mano (borrar el campo del elemento, o sacar la parte de `label_parts`), los ids
determinísticos de `group_id` y `label_variant_instance_id` en las relaciones que crea, y llamar a
`GET .../usage` antes de guardar una variante para mostrar el aviso de cuántos diseños se van a
actualizar — todo esto ya lo tenían especificado en su propio documento, no cambia nada de lo que
armaron.
