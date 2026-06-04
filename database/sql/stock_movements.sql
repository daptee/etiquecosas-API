CREATE TABLE stock_movements (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    product_id BIGINT UNSIGNED NOT NULL,
    product_variant_id BIGINT UNSIGNED NULL,
    quantity INT NOT NULL,
    note TEXT NOT NULL,
    user_id BIGINT UNSIGNED NULL,
    sale_id BIGINT NULL,
    channel_id BIGINT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_stock_movements_product
        FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,

    CONSTRAINT fk_stock_movements_variant
        FOREIGN KEY (product_variant_id) REFERENCES product_variants(id) ON DELETE SET NULL,

    CONSTRAINT fk_stock_movements_user
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,

    CONSTRAINT fk_stock_movements_sale
        FOREIGN KEY (sale_id) REFERENCES sales(id) ON DELETE SET NULL,

    CONSTRAINT fk_stock_movements_channel
        FOREIGN KEY (channel_id) REFERENCES channels(id) ON DELETE SET NULL
);
