-- Correr UNA VEZ contra una base que YA tiene product_pdf_design_products con
-- label_element_id (ver alter_product_pdf_design_products_add_label_element.sql).
-- No borra nada — agrega una columna y cambia la restricción única.
--
-- combo_design_id: el diseño "combo" que armó este vínculo por página. Un
-- diseño que usa páginas de otros (data.pages[].source) vincula cada página
-- por separado — las prestadas en SU diseño de origen, las propias en sí
-- mismo — y hasta ahora esas filas no decían de qué combo venían:
--   * al comprar no se podía leer el pages_output_mode del combo (salía
--     siempre un único PDF, aunque el combo dijera "separadas");
--   * dos combos que usan las mismas páginas mezclaban sus productos.
-- NULL = vínculo de siempre (diseño entero, o página suelta sin combo).
--
-- ON DELETE CASCADE: si el combo se borra físicamente, sus vínculos por
-- página se van con él. El borrado desde el admin es soft delete — ese caso
-- lo maneja ProductPdfDesignController::delete().
--
-- FOREIGN_KEY_CHECKS=0: agregar una FK reconstruye la tabla y MySQL vuelve a
-- validar las FKs que YA tiene — si hay vínculos huérfanos viejos (de
-- diseños borrados a mano; en la base local había 2, a los diseños 97 y 102)
-- el ALTER falla. No borra ni cambia esos datos. Para verlos:
--   SELECT l.* FROM product_pdf_design_products l
--   LEFT JOIN product_pdf_designs d ON d.id = l.product_pdf_design_id
--   WHERE d.id IS NULL;
SET FOREIGN_KEY_CHECKS = 0;

ALTER TABLE `product_pdf_design_products`
  ADD COLUMN `combo_design_id` BIGINT UNSIGNED NULL AFTER `label_element_id`,
  ADD CONSTRAINT `product_pdf_design_products_combo_design_id_foreign`
    FOREIGN KEY (`combo_design_id`) REFERENCES `product_pdf_designs` (`id`) ON DELETE CASCADE;

-- La misma página puede estar en DOS combos para el mismo producto+variante:
-- el combo entra en la restricción única. El ADD va antes que el DROP (el
-- índice viejo respalda la foreign key de product_id).
ALTER TABLE `product_pdf_design_products`
  ADD UNIQUE KEY `product_pdf_design_products_full_combo_unique`
    (`product_id`, `theme_key`, `product_pdf_design_id`, `page_id`, `label_element_id`, `combo_design_id`);

ALTER TABLE `product_pdf_design_products`
  DROP INDEX `product_pdf_design_products_full_unique`;

SET FOREIGN_KEY_CHECKS = 1;

-- Después de correr esto: `php artisan pdf-designs:asignar-combos` (primero
-- sin --apply para ver qué haría) completa combo_design_id en los vínculos
-- por página que ya existen.
