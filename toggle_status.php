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

$user_id = (int)($_POST['id'] ?? 0);
if ($user_id <= 0) {
    header("Location: admin_dashboard.php?error=Invalid user");
    exit();
}

// 1. Get current status
$stmt = $pdo->prepare("SELECT status FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

if ($user) {
    // 2. Flip the status
    $new_status = ($user['status'] === 'active') ? 'banned' : 'active';
    
    $update = $pdo->prepare("UPDATE users SET status = ? WHERE id = ?");
    $update->execute([$new_status, $user_id]);
    
    header("Location: admin_dashboard.php?success=User status updated to $new_status");
}
exit();
