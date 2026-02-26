CREATE TABLE IF NOT EXISTS wallet_ledger (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    delta_amount DECIMAL(15,2) NOT NULL,
    balance_before DECIMAL(15,2) NOT NULL,
    balance_after DECIMAL(15,2) NOT NULL,
    entry_type VARCHAR(50) NOT NULL,
    description VARCHAR(255) DEFAULT NULL,
    reference_type VARCHAR(50) DEFAULT NULL,
    reference_id BIGINT DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user_created (user_id, created_at),
    INDEX idx_entry_type (entry_type),
    CONSTRAINT wallet_ledger_ibfk_1 FOREIGN KEY (user_id) REFERENCES users (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
