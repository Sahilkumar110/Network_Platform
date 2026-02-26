<?php
require_once __DIR__ . '/config/bootstrap.php';
require_once __DIR__ . '/lang/bootstrap.php';

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
    appLog('error', 'Database connection failed', [
        'host' => $host,
        'port' => $port,
        'database' => $db,
        'message' => $e->getMessage()
    ]);
    if (envBool('APP_DEBUG', false)) {
        die("Connection failed: " . $e->getMessage());
    }
    die("Connection failed. Check logs.");
}
?>
