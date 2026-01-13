-- Table for customer service records management
CREATE TABLE customer_services (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL COMMENT 'Customer service name',
    status_id BIGINT UNSIGNED NOT NULL DEFAULT 1 COMMENT 'Foreign key to general_statuses',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL DEFAULT NULL COMMENT 'Soft delete',
    FOREIGN KEY (status_id) REFERENCES general_statuses(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table for customer service steps
CREATE TABLE customer_service_steps (
    id INT AUTO_INCREMENT PRIMARY KEY,
    customer_service_id INT NOT NULL COMMENT 'Foreign key to customer_services',
    step_number INT NOT NULL COMMENT 'Step number/order',
    title VARCHAR(255) NOT NULL COMMENT 'Step title',
    description TEXT NOT NULL COMMENT 'Step description',
    icon VARCHAR(255) NULL COMMENT 'Icon file path',
    image_1 VARCHAR(255) NULL COMMENT 'First image file path',
    image_2 VARCHAR(255) NULL COMMENT 'Second image file path',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL DEFAULT NULL COMMENT 'Soft delete',
    FOREIGN KEY (customer_service_id) REFERENCES customer_services(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Indexes for customer_services
CREATE INDEX idx_status_id ON customer_services(status_id);
CREATE INDEX idx_deleted_at ON customer_services(deleted_at);
CREATE INDEX idx_name ON customer_services(name);
CREATE INDEX idx_created_at ON customer_services(created_at);

-- Indexes for customer_service_steps
CREATE INDEX idx_customer_service_id ON customer_service_steps(customer_service_id);
CREATE INDEX idx_step_number ON customer_service_steps(step_number);
CREATE INDEX idx_deleted_at_steps ON customer_service_steps(deleted_at);

-- Agregar columna icon a la tabla customer_services
ALTER TABLE customer_services 
ADD COLUMN icon VARCHAR(255) NULL COMMENT 'Icon file path for the service' AFTER name;
