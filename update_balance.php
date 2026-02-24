<?php
session_start();
include 'db.php';
include 'functions.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        header("Location: admin_dashboard.php?error=Invalid session token");
        exit();
    }
    $user_id = (int)$_POST['user_id'];
    $amount = (float)$_POST['amount'];
    $action = $_POST['action'];
    $admin_id = $_SESSION['user_id'];

    if ($amount <= 0 || !in_array($action, ['add', 'subtract'], true)) {
        header("Location: admin_dashboard.php?error=Invalid request");
        exit();
    }

    // 1. Update the User's Balance
    $final_amount = ($action === 'subtract') ? -$amount : $amount;
    $stmt = $pdo->prepare("UPDATE users SET wallet_balance = wallet_balance + ? WHERE id = ?");
    
    if ($stmt->execute([$final_amount, $user_id])) {
        updateUserRank($pdo, $user_id);
        
        // 2. NEW: Record the Transaction in the Log
        $log_stmt = $pdo->prepare("INSERT INTO transaction_logs (admin_id, user_id, action_type, amount) VALUES (?, ?, ?, ?)");
        $log_stmt->execute([$admin_id, $user_id, $action, $amount]);

        header("Location: admin_dashboard.php?success=Balance Updated and Logged");
    } else {
        header("Location: admin_dashboard.php?error=Update Failed");
    }
    exit();
}
