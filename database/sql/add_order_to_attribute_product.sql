ALTER TABLE `attribute_product`
ADD COLUMN `order` INT UNSIGNED NOT NULL DEFAULT 0 AFTER `attribute_id`;
