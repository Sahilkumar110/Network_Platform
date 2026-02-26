CREATE TABLE IF NOT EXISTS compliance_events (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    event_type VARCHAR(60) NOT NULL,
    severity ENUM('info','medium','high') NOT NULL DEFAULT 'info',
    details VARCHAR(255) DEFAULT NULL,
    payload_json TEXT DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user_created (user_id, created_at),
    INDEX idx_severity_created (severity, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
