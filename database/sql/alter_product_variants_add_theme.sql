-- Asociación OPCIONAL de UNA variante puntual a una temática de PDF
-- (pdf_themes) — tiene prioridad sobre la temática general del producto
-- (products.theme_id, ver alter_products_add_theme.sql) cuando está seteada.
ALTER TABLE `product_variants`
  ADD COLUMN `theme_id` BIGINT UNSIGNED NULL AFTER `img`,
  ADD CONSTRAINT `product_variants_theme_id_foreign`
    FOREIGN KEY (`theme_id`) REFERENCES `pdf_themes` (`id`);
