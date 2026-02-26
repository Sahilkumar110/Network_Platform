CREATE TABLE IF NOT EXISTS cron_runs (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    job_name VARCHAR(80) NOT NULL,
    status ENUM('success','failed') NOT NULL,
    details VARCHAR(255) DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_job_created (job_name, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
