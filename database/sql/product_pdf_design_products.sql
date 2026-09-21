-- Vincula un diseño (product_pdf_designs) con los productos que lo usan.
-- Un mismo diseño puede reutilizarse en varios productos; theme_key equivale
-- al id de la variante/temática (attribute_values.id) que selecciona ese
-- diseño DENTRO de ese producto puntual (puede variar de un producto a otro).
-- theme_key NULL = ese producto usa el diseño sin selector de variante.
CREATE TABLE `product_pdf_design_products` (
  `id`                    BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `product_pdf_design_id` BIGINT UNSIGNED NOT NULL,
  `product_id`            BIGINT UNSIGNED NOT NULL,
  `theme_key`             BIGINT UNSIGNED NULL,
  `created_at`            TIMESTAMP NULL DEFAULT NULL,
  `updated_at`            TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `product_pdf_design_products_product_theme_unique` (`product_id`, `theme_key`),
  CONSTRAINT `product_pdf_design_products_design_id_foreign`
    FOREIGN KEY (`product_pdf_design_id`) REFERENCES `product_pdf_designs` (`id`) ON DELETE CASCADE,
  CONSTRAINT `product_pdf_design_products_product_id_foreign`
    FOREIGN KEY (`product_id`) REFERENCES `products` (`id`)
);
