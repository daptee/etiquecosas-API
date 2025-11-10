ALTER TABLE shipping_templates
ADD name VARCHAR(200) NULL;

SET SQL_SAFE_UPDATES = 0;

UPDATE shipping_templates
SET name = CONCAT('template_', id);

SET SQL_SAFE_UPDATES = 1;

ALTER TABLE shipping_templates
MODIFY name VARCHAR(200) NOT NULL UNIQUE;
