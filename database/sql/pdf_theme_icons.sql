-- Íconos asociados a una temática (pdf_themes) — muchos a muchos con el
-- catálogo ya existente personalization_icons. sort_order define el orden
-- en que se sugieren en el editor.
CREATE TABLE `pdf_theme_icons` (
  `id`                      BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `pdf_theme_id`            BIGINT UNSIGNED NOT NULL,
  `personalization_icon_id` BIGINT UNSIGNED NOT NULL,
  `sort_order`              INT NOT NULL DEFAULT 0,
  `created_at`              TIMESTAMP NULL DEFAULT NULL,
  `updated_at`              TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `pdf_theme_icons_theme_icon_unique` (`pdf_theme_id`, `personalization_icon_id`),
  CONSTRAINT `pdf_theme_icons_pdf_theme_id_foreign`
    FOREIGN KEY (`pdf_theme_id`) REFERENCES `pdf_themes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `pdf_theme_icons_personalization_icon_id_foreign`
    FOREIGN KEY (`personalization_icon_id`) REFERENCES `personalization_icons` (`id`) ON DELETE CASCADE
);
