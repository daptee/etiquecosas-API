ALTER TABLE client_wholesales
    ADD COLUMN postal_code VARCHAR(20) NULL

ALTER TABLE client_addresses
    ADD COLUMN postal_code VARCHAR(20) NULL,
    ADD COLUMN name VARCHAR(150) NULL,
    ADD COLUMN observations TEXT NULL