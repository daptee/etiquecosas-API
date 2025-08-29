CREATE TABLE sale_status (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL
);

-- Precarga
INSERT INTO sale_status (name) VALUES 
('Aprobado'),
('En producción'),
('Pedido listo'),
('Entregado'),
('Cancelado'),
('Pedido casi listo'),
('Pedido retirado'),
('Pendiente de pago');

CREATE TABLE shipping_methods (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL
);

-- Ejemplo de precarga
INSERT INTO shipping_methods (name) VALUES ('Retiro en local'), ('Correo'), ('Envío propio');

CREATE TABLE channels (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL
);

-- Precarga
INSERT INTO channels (name) VALUES ('Web'), ('Local Comercial'), ('MercadoLibre');

CREATE TABLE sales (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    client_id BIGINT UNSIGNED NOT NULL, -- referencia a cliente
    channel_id BIGINT NOT NULL, -- canal de venta
    external_id VARCHAR(255) NULL, -- id externo (ej: MercadoLibre)

    -- Datos de envío
    address VARCHAR(255) NOT NULL,
    locality_id BIGINT NOT NULL,
    postal_code VARCHAR(20) NOT NULL,
    client_shipping_id BIGINT NULL, -- si usó dirección guardada del cliente

    subtotal DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    shipping_cost DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    shipping_method_id BIGINT NOT NULL,

    customer_notes TEXT NULL,
    internal_comments TEXT NULL,

    sale_status_id BIGINT NOT NULL, -- estado actual de la venta
    sale_id BIGINT NULL, -- referencia a otra venta (envío agrupado)

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    CONSTRAINT fk_sales_client FOREIGN KEY (client_id) REFERENCES clients(id),
    CONSTRAINT fk_sales_channel FOREIGN KEY (channel_id) REFERENCES channels(id),
    CONSTRAINT fk_sales_shipping_method FOREIGN KEY (shipping_method_id) REFERENCES shipping_methods(id),
    CONSTRAINT fk_sales_status FOREIGN KEY (sale_status_id) REFERENCES sale_status(id),
    CONSTRAINT fk_sales_parent FOREIGN KEY (sale_id) REFERENCES sales(id)
);

CREATE TABLE sales_products (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    sale_id BIGINT NOT NULL,
    product_id BIGINT UNSIGNED NOT NULL,
    variant_id BIGINT UNSIGNED NULL,
    customization_data JSON NULL, -- customización hecha por el cliente
    quantity INT NOT NULL DEFAULT 1,
    unit_price DECIMAL(12,2) NOT NULL,
    comment TEXT NULL, -- comentarios del cliente

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    CONSTRAINT fk_sales_products_sale FOREIGN KEY (sale_id) REFERENCES sales(id),
    CONSTRAINT fk_sales_products_product FOREIGN KEY (product_id) REFERENCES products(id),
    CONSTRAINT fk_product_variants FOREIGN KEY (variant_id) REFERENCES product_variants(id)
);

CREATE TABLE sales_status_history (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    sale_id BIGINT NOT NULL,
    sale_status_id BIGINT NOT NULL,
    date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_status_history_sale FOREIGN KEY (sale_id) REFERENCES sales(id),
    CONSTRAINT fk_status_history_status FOREIGN KEY (sale_status_id) REFERENCES sale_status(id)
);

