-- Table for instructives management
CREATE TABLE instructives (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL COMMENT 'Instructive name',
    link TEXT NOT NULL COMMENT 'Instructive URL',
    status_id BIGINT UNSIGNED NOT NULL DEFAULT 1 COMMENT 'Foreign key to general_statuses',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL DEFAULT NULL COMMENT 'Soft delete',
    FOREIGN KEY (status_id) REFERENCES general_statuses(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Indexes
CREATE INDEX idx_status_id ON instructives(status_id);
CREATE INDEX idx_deleted_at ON instructives(deleted_at);
CREATE INDEX idx_name ON instructives(name);
CREATE INDEX idx_created_at ON instructives(created_at);

-- Agregar columnas description y position a la tabla instructives existente
ALTER TABLE instructives
ADD COLUMN description TEXT NULL COMMENT 'Instructive description' AFTER name,
ADD COLUMN position INT NOT NULL DEFAULT 0 COMMENT 'Display order position' AFTER link;

-- Crear índice para position
CREATE INDEX idx_position ON instructives(position);

-- Actualizar posiciones existentes (asignar position basado en created_at)
-- Opción 1: Desactivar temporalmente el modo seguro
SET SQL_SAFE_UPDATES = 0;

UPDATE instructives i
JOIN (
    SELECT id, ROW_NUMBER() OVER (ORDER BY created_at ASC) - 1 AS new_position
    FROM instructives
) AS ordered ON i.id = ordered.id
SET i.position = ordered.new_position;

SET SQL_SAFE_UPDATES = 1;
