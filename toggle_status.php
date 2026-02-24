<?php
session_start();
include 'db.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

if (isset($_GET['id'])) {
    $user_id = (int)$_GET['id'];
    
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
}
exit();
