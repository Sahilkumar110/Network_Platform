CREATE TABLE IF NOT EXISTS ledger_reconciliation_reports (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    total_users INT NOT NULL,
    mismatched_users INT NOT NULL,
    total_mismatch_amount DECIMAL(15,2) NOT NULL DEFAULT 0,
    status ENUM('ok','warning') NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS ledger_reconciliation_items (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    report_id BIGINT NOT NULL,
    user_id INT NOT NULL,
    wallet_balance DECIMAL(15,2) NOT NULL,
    ledger_balance DECIMAL(15,2) NOT NULL,
    difference DECIMAL(15,2) NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_report_user (report_id, user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
