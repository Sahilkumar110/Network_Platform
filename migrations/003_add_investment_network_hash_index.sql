SET @idx_exists := (
    SELECT COUNT(1)
    FROM information_schema.statistics
    WHERE table_schema = DATABASE()
      AND table_name = 'investment_requests'
      AND index_name = 'idx_network_hash'
);
SET @idx_sql := IF(
    @idx_exists = 0,
    'CREATE INDEX idx_network_hash ON investment_requests (network, tx_hash)',
    'SELECT 1'
);
PREPARE idx_stmt FROM @idx_sql;
EXECUTE idx_stmt;
DEALLOCATE PREPARE idx_stmt;
