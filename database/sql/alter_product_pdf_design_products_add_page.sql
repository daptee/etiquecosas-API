-- Correr UNA VEZ contra una base que YA tiene product_pdf_design_products
-- creada con el esquema viejo (sin page_id/sort_order). No borra nada — solo
-- agrega columnas y cambia la restricción única. Ver product_pdf_design_products.sql
-- para el detalle de qué significa cada columna nueva.

ALTER TABLE `product_pdf_design_products`
  ADD COLUMN `page_id` VARCHAR(255) NULL AFTER `theme_key`,
  ADD COLUMN `sort_order` INT NOT NULL DEFAULT 0 AFTER `page_id`;

-- Antes un producto+variante solo podía tener UN vínculo (un diseño entero);
-- ahora puede tener varios (una fila por página que compone su PDF), así que
-- hay que sacar esa restricción vieja... PERO primero hay que crear la
-- restricción nueva (el índice viejo respalda la foreign key de product_id;
-- MySQL no deja borrarlo hasta que otro índice que también arranque con
-- product_id pueda tomar su lugar — por eso el ADD va ANTES que el DROP).
ALTER TABLE `product_pdf_design_products`
  ADD UNIQUE KEY `product_pdf_design_products_product_theme_design_page_unique` (`product_id`, `theme_key`, `product_pdf_design_id`, `page_id`);

ALTER TABLE `product_pdf_design_products`
  DROP INDEX `product_pdf_design_products_product_theme_unique`;
