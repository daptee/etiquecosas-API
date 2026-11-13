-- Productos cuya variante trae VARIOS atributos de color, uno por cada
-- unidad física del pack (ej. "Color banda 1"/"Color banda 2" en el producto
-- de 2 bandas — mismo caso que hoy resuelve a mano
-- BandaService::obtenerColoresDeVariante() para el sistema viejo).
--
-- accumulation_split_attributes: JSON con la lista de nombres de atributo
--   (ej. ["Color banda 1", "Color banda 2"]) — AccumulatingPdfDesignService
--   parte el pedido en UN ítem POR CADA atributo de la lista que tenga valor
--   en la variante, cada uno con el color de SU propio atributo (si dos
--   atributos dieron el mismo color, salen dos etiquetas idénticas en ese
--   color, a propósito). NULL/[] = comportamiento de siempre, 1 solo ítem
--   por pedido con el color de customization_data.
ALTER TABLE `product_pdf_designs`
  ADD COLUMN `accumulation_split_attributes` JSON NULL AFTER `accumulation_group_attribute`;
