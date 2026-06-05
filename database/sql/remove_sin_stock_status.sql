SET SQL_SAFE_UPDATES = 0;

-- 1. Reasignar TODOS los productos (incluidos soft-deleted) con stock_status_id = 3 → 2
UPDATE products
SET product_stock_status_id = 2
WHERE product_stock_status_id = 3;

-- 2. Actualizar stock_channels en productos (índice 0)
UPDATE products
SET stock_channels = JSON_REPLACE(
    stock_channels,
    '$[0].stock_status', 2,
    '$[0].stock_status_name', 'Gestión de Stock'
)
WHERE id IN (
    SELECT id FROM (
        SELECT id FROM products
        WHERE JSON_EXTRACT(stock_channels, '$[0].stock_status') = 3
    ) AS tmp
);

-- 3. Actualizar stock_channels en productos (índice 1)
UPDATE products
SET stock_channels = JSON_REPLACE(
    stock_channels,
    '$[1].stock_status', 2,
    '$[1].stock_status_name', 'Gestión de Stock'
)
WHERE id IN (
    SELECT id FROM (
        SELECT id FROM products
        WHERE JSON_EXTRACT(stock_channels, '$[1].stock_status') = 3
    ) AS tmp
);

-- 4. Actualizar stock_channels en variantes (índice 0)
UPDATE product_variants
SET stock_channels = JSON_REPLACE(
    stock_channels,
    '$[0].stock_status', 2,
    '$[0].stock_status_name', 'Gestión de Stock'
)
WHERE id IN (
    SELECT id FROM (
        SELECT id FROM product_variants
        WHERE JSON_EXTRACT(stock_channels, '$[0].stock_status') = 3
    ) AS tmp
);

-- 5. Actualizar stock_status dentro del JSON variant en variantes
UPDATE product_variants
SET variant = JSON_SET(variant, '$.stock_status', 2)
WHERE JSON_EXTRACT(variant, '$.stock_status') = 3;

-- 6. Eliminar "Sin Stock" de product_stock_statuses
DELETE FROM product_stock_statuses WHERE id = 3;

SET SQL_SAFE_UPDATES = 1;
