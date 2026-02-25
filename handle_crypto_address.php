<?php
session_start();
include 'db.php';
include 'functions.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: admin_dashboard.php?error=Invalid request");
    exit();
}
if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
    header("Location: admin_dashboard.php?error=Invalid session token");
    exit();
}

ensureUserCryptoAddressesTable($pdo);

$row_id = (int)($_POST['row_id'] ?? 0);
$decision = $_POST['decision'] ?? '';
if ($row_id <= 0 || !in_array($decision, ['approve', 'reject'], true)) {
    header("Location: admin_dashboard.php?error=Invalid request");
    exit();
}

$stmt = $pdo->prepare("SELECT id, user_id FROM user_crypto_addresses WHERE id = ? LIMIT 1");
$stmt->execute([$row_id]);
$row = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$row) {
    header("Location: admin_dashboard.php?error=Address record not found");
    exit();
}

if ($decision === 'approve') {
    $upd = $pdo->prepare("UPDATE user_crypto_addresses SET status = 'verified', verified_at = NOW() WHERE id = ?");
    $upd->execute([$row_id]);
    header("Location: admin_dashboard.php?success=Crypto address verified");
    exit();
}

$upd = $pdo->prepare("UPDATE user_crypto_addresses SET status = 'rejected', verified_at = NULL WHERE id = ?");
$upd->execute([$row_id]);
header("Location: admin_dashboard.php?success=Crypto address rejected");
exit();
?>
