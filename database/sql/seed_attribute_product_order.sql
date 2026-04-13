-- Asigna el campo `order` a los atributos ya cargados en cada producto.
-- El orden se asigna por attribute_id ASC dentro de cada producto.
-- Ejecutar una sola vez luego de agregar la columna `order`.

SET @product_id = 0;
SET @rank = -1;

UPDATE attribute_product
JOIN (
    SELECT
        id,
        product_id,
        (@rank := IF(@product_id = product_id, @rank + 1, 0)) AS computed_order,
        (@product_id := product_id) AS _pid
    FROM attribute_product
    ORDER BY product_id ASC, attribute_id ASC
) AS ranked ON attribute_product.id = ranked.id
SET attribute_product.order = ranked.computed_order;
