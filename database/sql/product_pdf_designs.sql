-- Diseños de PDF de producto armados desde el editor visual del front.
-- Tabla separada de product_pdf a propósito: si un producto tiene fila(s) acá,
-- la generación usa este diseño; si no, sigue el flujo legacy sin cambios.
-- Una fila por diseño-variante (mismo criterio que product_pdf.data.tematicas[]):
-- theme_key equivale al id de la temática/variante (attribute_values.id);
-- NULL = diseño único sin selector de variante.
CREATE TABLE `product_pdf_designs` (
  `id`             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `product_id`     BIGINT UNSIGNED NOT NULL,
  `label_shape_id` BIGINT UNSIGNED NULL,
  `theme_key`      BIGINT UNSIGNED NULL,
  `name`           VARCHAR(255) NOT NULL,
  `data`           JSON NOT NULL,
  `is_published`   TINYINT(1) NOT NULL DEFAULT 0,
  `status_id`      BIGINT UNSIGNED NOT NULL DEFAULT 1,
  `created_at`     TIMESTAMP NULL DEFAULT NULL,
  `updated_at`     TIMESTAMP NULL DEFAULT NULL,
  `deleted_at`     TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `product_pdf_designs_product_theme_unique` (`product_id`, `theme_key`),
  CONSTRAINT `product_pdf_designs_product_id_foreign`
    FOREIGN KEY (`product_id`) REFERENCES `products` (`id`),
  CONSTRAINT `product_pdf_designs_label_shape_id_foreign`
    FOREIGN KEY (`label_shape_id`) REFERENCES `label_shapes` (`id`),
  CONSTRAINT `product_pdf_designs_status_id_foreign`
    FOREIGN KEY (`status_id`) REFERENCES `general_statuses` (`id`)
);
