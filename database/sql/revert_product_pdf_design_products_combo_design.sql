-- SOLO para una base donde se corrió
-- alter_product_pdf_design_products_add_combo_design.sql (columna
-- combo_design_id). Si esa columna no existe, NO correr esto.
--
-- Esa columna se descartó: un producto vinculado a un diseño "combo"
-- (páginas prestadas, data.pages[].source) se vincula al COMBO entero
-- (product_pdf_design_id = combo, page_id NULL), no página por página en los
-- diseños de origen. Ver php artisan pdf-designs:vincular-combos.
--
-- Correr DESPUÉS de `php artisan pdf-designs:vincular-combos --apply`: si
-- quedara la misma página vinculada por dos combos para el mismo
-- producto+variante, la restricción única vieja fallaría.
--
-- No borra filas: quita la columna y vuelve a la restricción única de antes.
SET FOREIGN_KEY_CHECKS = 0;

-- El ADD va antes que el DROP (el índice único respalda la foreign key de
-- product_id).
ALTER TABLE `product_pdf_design_products`
  ADD UNIQUE KEY `product_pdf_design_products_full_unique`
    (`product_id`, `theme_key`, `product_pdf_design_id`, `page_id`, `label_element_id`);

ALTER TABLE `product_pdf_design_products`
  DROP FOREIGN KEY `product_pdf_design_products_combo_design_id_foreign`,
  DROP INDEX `product_pdf_design_products_full_combo_unique`;

ALTER TABLE `product_pdf_design_products`
  DROP COLUMN `combo_design_id`;

SET FOREIGN_KEY_CHECKS = 1;
