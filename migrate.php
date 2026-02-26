<?php
declare(strict_types=1);

require_once __DIR__ . '/config/bootstrap.php';

$host = (string)envValue('DB_HOST', 'localhost');
$port = (string)envValue('DB_PORT', '3306');
$db   = (string)envValue('DB_NAME', 'network_platform');
$user = (string)envValue('DB_USER', 'root');
$pass = (string)envValue('DB_PASS', '');
$charset = (string)envValue('DB_CHARSET', 'utf8mb4');

try {
    $dsn = "mysql:host={$host};port={$port};dbname={$db};charset={$charset}";
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
} catch (PDOException $e) {
    appLog('error', 'Migration DB connection failed', ['message' => $e->getMessage()]);
    fwrite(STDERR, "DB connection failed.\n");
    exit(1);
}

$pdo->exec(
    "CREATE TABLE IF NOT EXISTS schema_migrations (
        id INT AUTO_INCREMENT PRIMARY KEY,
        migration VARCHAR(255) NOT NULL UNIQUE,
        applied_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
);

$migrationDir = __DIR__ . '/migrations';
if (!is_dir($migrationDir)) {
    fwrite(STDOUT, "No migrations directory found.\n");
    exit(0);
}

$files = glob($migrationDir . '/*.sql');
sort($files);

$appliedStmt = $pdo->query("SELECT migration FROM schema_migrations");
$applied = [];
foreach ($appliedStmt->fetchAll(PDO::FETCH_COLUMN) as $name) {
    $applied[(string)$name] = true;
}

$appliedCount = 0;
foreach ($files as $file) {
    $name = basename($file);
    if (isset($applied[$name])) {
        continue;
    }

    $sql = file_get_contents($file);
    if ($sql === false) {
        fwrite(STDERR, "Could not read migration: {$name}\n");
        exit(1);
    }

    try {
        $pdo->beginTransaction();
        $pdo->exec($sql);
        $ins = $pdo->prepare("INSERT INTO schema_migrations (migration) VALUES (?)");
        $ins->execute([$name]);
        $pdo->commit();
        $appliedCount++;
        fwrite(STDOUT, "Applied: {$name}\n");
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        appLog('error', 'Migration failed', ['migration' => $name, 'message' => $e->getMessage()]);
        fwrite(STDERR, "Migration failed: {$name}\n");
        fwrite(STDERR, $e->getMessage() . "\n");
        exit(1);
    }
}

fwrite(STDOUT, "Done. {$appliedCount} new migration(s) applied.\n");
exit(0);

