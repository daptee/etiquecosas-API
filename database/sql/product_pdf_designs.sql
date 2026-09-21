-- Diseños de PDF armados desde el editor visual del front.
-- Un diseño es una entidad independiente (no pertenece a un solo producto):
-- se puede reutilizar en varios productos a la vez, ver product_pdf_design_products.sql.
-- Tabla separada de product_pdf a propósito: si un producto tiene un diseño
-- vinculado, la generación usa ese diseño; si no, sigue el flujo legacy sin cambios.
CREATE TABLE `product_pdf_designs` (
  `id`             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `label_shape_id` BIGINT UNSIGNED NULL,
  `name`           VARCHAR(255) NOT NULL,
  `data`           JSON NOT NULL,
  `is_published`   TINYINT(1) NOT NULL DEFAULT 0,
  `status_id`      BIGINT UNSIGNED NOT NULL DEFAULT 1,
  `created_at`     TIMESTAMP NULL DEFAULT NULL,
  `updated_at`     TIMESTAMP NULL DEFAULT NULL,
  `deleted_at`     TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  CONSTRAINT `product_pdf_designs_label_shape_id_foreign`
    FOREIGN KEY (`label_shape_id`) REFERENCES `label_shapes` (`id`),
  CONSTRAINT `product_pdf_designs_status_id_foreign`
    FOREIGN KEY (`status_id`) REFERENCES `general_statuses` (`id`)
);
