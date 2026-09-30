-- Vincula un diseño (product_pdf_designs) con los productos que lo usan.
-- Un mismo diseño puede reutilizarse en varios productos; theme_key equivale
-- al id de la variante (product_variants.id, NO attribute_values.id) que
-- selecciona ese diseño DENTRO de ese producto puntual (puede variar de un
-- producto a otro). theme_key NULL = ese producto usa el diseño sin selector
-- de variante.
--
-- page_id: si se manda, el vínculo usa SOLO esa página puntual del diseño
-- (data.pages[].id) en vez de todas sus páginas. NULL = usa todas las páginas
-- del diseño (comportamiento original, antes de que existiera esta columna).
--
-- Un mismo producto+variante puede tener VARIOS vínculos (uno por cada página
-- que compone su PDF final, pudiendo venir de diseños distintos) — ya no hay
-- un único vínculo por producto+variante. sort_order define el orden en el
-- que se arman esas páginas en el PDF final.
CREATE TABLE `product_pdf_design_products` (
  `id`                    BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `product_pdf_design_id` BIGINT UNSIGNED NOT NULL,
  `product_id`            BIGINT UNSIGNED NOT NULL,
  `theme_key`             BIGINT UNSIGNED NULL,
  `page_id`               VARCHAR(255) NULL,
  `sort_order`            INT NOT NULL DEFAULT 0,
  `created_at`            TIMESTAMP NULL DEFAULT NULL,
  `updated_at`            TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `product_pdf_design_products_product_theme_design_page_unique` (`product_id`, `theme_key`, `product_pdf_design_id`, `page_id`),
  CONSTRAINT `product_pdf_design_products_design_id_foreign`
    FOREIGN KEY (`product_pdf_design_id`) REFERENCES `product_pdf_designs` (`id`) ON DELETE CASCADE,
  CONSTRAINT `product_pdf_design_products_product_id_foreign`
    FOREIGN KEY (`product_id`) REFERENCES `products` (`id`)
);
