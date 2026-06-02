ALTER TABLE `product_images`
ADD COLUMN `position` INT UNSIGNED NOT NULL DEFAULT 0 AFTER `is_main`;

-- Inicializar position con el orden actual (por id ascendente dentro de cada producto)
SET SQL_SAFE_UPDATES = 0;
SET @pos := -1;
SET @prod := 0;
UPDATE product_images
SET position = IF(
    @prod = product_id,
    @pos := @pos + 1,
    (@prod := product_id) * 0 + (@pos := 0)
)
ORDER BY product_id, id;
SET SQL_SAFE_UPDATES = 1;
