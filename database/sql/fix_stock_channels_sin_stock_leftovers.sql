-- Completa remove_sin_stock_status.sql: ese script solo corrigió products[0..1] y
-- product_variants[0], y comparaba contra el número 3, así que quedaron canales con
-- stock_status = 3 / "3" ("Sin Stock", ya eliminado) que rompen la validación
-- exists:product_stock_statuses al editar el producto. Cubre índices 0..3, número o string.
SET SQL_SAFE_UPDATES = 0;

UPDATE products
SET stock_channels = JSON_REPLACE(stock_channels, '$[0].stock_status', 2, '$[0].stock_status_name', 'Gestión de Stock')
WHERE JSON_UNQUOTE(JSON_EXTRACT(stock_channels, '$[0].stock_status')) = '3';

UPDATE products
SET stock_channels = JSON_REPLACE(stock_channels, '$[1].stock_status', 2, '$[1].stock_status_name', 'Gestión de Stock')
WHERE JSON_UNQUOTE(JSON_EXTRACT(stock_channels, '$[1].stock_status')) = '3';

UPDATE products
SET stock_channels = JSON_REPLACE(stock_channels, '$[2].stock_status', 2, '$[2].stock_status_name', 'Gestión de Stock')
WHERE JSON_UNQUOTE(JSON_EXTRACT(stock_channels, '$[2].stock_status')) = '3';

UPDATE products
SET stock_channels = JSON_REPLACE(stock_channels, '$[3].stock_status', 2, '$[3].stock_status_name', 'Gestión de Stock')
WHERE JSON_UNQUOTE(JSON_EXTRACT(stock_channels, '$[3].stock_status')) = '3';

UPDATE product_variants
SET stock_channels = JSON_REPLACE(stock_channels, '$[0].stock_status', 2, '$[0].stock_status_name', 'Gestión de Stock')
WHERE JSON_UNQUOTE(JSON_EXTRACT(stock_channels, '$[0].stock_status')) = '3';

UPDATE product_variants
SET stock_channels = JSON_REPLACE(stock_channels, '$[1].stock_status', 2, '$[1].stock_status_name', 'Gestión de Stock')
WHERE JSON_UNQUOTE(JSON_EXTRACT(stock_channels, '$[1].stock_status')) = '3';

UPDATE product_variants
SET stock_channels = JSON_REPLACE(stock_channels, '$[2].stock_status', 2, '$[2].stock_status_name', 'Gestión de Stock')
WHERE JSON_UNQUOTE(JSON_EXTRACT(stock_channels, '$[2].stock_status')) = '3';

UPDATE product_variants
SET stock_channels = JSON_REPLACE(stock_channels, '$[3].stock_status', 2, '$[3].stock_status_name', 'Gestión de Stock')
WHERE JSON_UNQUOTE(JSON_EXTRACT(stock_channels, '$[3].stock_status')) = '3';

SET SQL_SAFE_UPDATES = 1;
