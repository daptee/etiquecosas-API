-- Asociación OPCIONAL de un producto (a nivel general) a una temática de PDF
-- (pdf_themes). Si la variante tiene su propia temática (ver
-- alter_product_variants_add_theme.sql), esa tiene prioridad — este campo es
-- el fallback general del producto.
ALTER TABLE `products`
  ADD COLUMN `theme_id` BIGINT UNSIGNED NULL AFTER `tag_id`,
  ADD CONSTRAINT `products_theme_id_foreign`
    FOREIGN KEY (`theme_id`) REFERENCES `pdf_themes` (`id`);
