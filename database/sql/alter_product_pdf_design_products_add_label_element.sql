-- Correr UNA VEZ contra una base que YA tiene product_pdf_design_products con
-- page_id/sort_order (ver alter_product_pdf_design_products_add_page.sql). No
-- borra nada — solo agrega una columna y cambia la restricción única.
--
-- label_element_id: id de un elemento `background` (data.pages[].elements[])
-- de un diseño ACUMULABLE con varias etiquetas en la misma página (ej. una
-- chica y una grande, cada una su propio background con su propio
-- label_shape_id) — elige CUÁL de esas etiquetas le corresponde a este
-- producto puntual. NULL = la primera etiqueta (background) que haya en la
-- página, o el diseño entero si no tiene ningún background (comportamiento de
-- siempre, diseño de una sola etiqueta). No tiene relación con `page_id`
-- (que sigue siendo "qué PÁGINA entera" para el modo normal/combinar
-- páginas) — un diseño acumulable con varias etiquetas las tiene todas en la
-- MISMA página, así que acá lo que varía es el elemento, no la página.
ALTER TABLE `product_pdf_design_products`
  ADD COLUMN `label_element_id` VARCHAR(255) NULL AFTER `page_id`;

ALTER TABLE `product_pdf_design_products`
  ADD UNIQUE KEY `product_pdf_design_products_full_unique`
    (`product_id`, `theme_key`, `product_pdf_design_id`, `page_id`, `label_element_id`);

ALTER TABLE `product_pdf_design_products`
  DROP INDEX `product_pdf_design_products_product_theme_design_page_unique`;
