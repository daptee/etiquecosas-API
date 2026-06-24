-- 1. Tipo de atributo en la tabla attributes
ALTER TABLE `attributes`
ADD COLUMN `type` ENUM('image', 'color', 'icon', 'tipo', 'text') NOT NULL DEFAULT 'text' AFTER `name`;

-- 2. Metadata adicional en attribute_values (colores, iconos, imagenes, etc.)
ALTER TABLE `attribute_values`
ADD COLUMN `metadata` JSON NULL AFTER `value`;

-- 3. Override de metadata por producto en la tabla pivot attribute_value_product
ALTER TABLE `attribute_value_product`
ADD COLUMN `metadata_override` JSON NULL;
