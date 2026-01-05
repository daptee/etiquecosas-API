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
