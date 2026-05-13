CREATE TABLE product_client_exclusions (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    product_id BIGINT UNSIGNED NOT NULL,
    client_id BIGINT UNSIGNED NOT NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    UNIQUE KEY unique_product_client (product_id, client_id),
    CONSTRAINT fk_pce_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
    CONSTRAINT fk_pce_client FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE CASCADE
);
