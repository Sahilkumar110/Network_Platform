<?php
session_start();
include 'db.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    die("Unauthorized");
}

// Configuration: Daily Profit Percentage (e.g., 1% = 0.01)
$daily_rate = 0.01; 
$today = date('Y-m-d');

try {
    $pdo->beginTransaction();

    // 1. Get all users with an active investment
    $stmt = $pdo->query("SELECT id, investment_amount FROM users WHERE investment_amount > 0");
    $users = $stmt->fetchAll();

    $count = 0;
    foreach ($users as $user) {
        $profit = $user['investment_amount'] * $daily_rate;

        if ($profit > 0) {
            // 2. Add profit to wallet
            $upd = $pdo->prepare("UPDATE users SET wallet_balance = wallet_balance + ? WHERE id = ?");
            $upd->execute([$profit, $user['id']]);

            // 3. Log the transaction so user sees "Daily Profit" in their history
            $log = $pdo->prepare("INSERT INTO transaction_logs (user_id, action_type, amount, created_at) VALUES (?, 'add', ?, NOW())");
            $log->execute([$user['id'], $profit]);
            
            $count++;
        }
    }

    $pdo->commit();
    header("Location: admin_dashboard.php?success=Distributed profit to $count users for $today");

} catch (Exception $e) {
    $pdo->rollBack();
    die("Automation Error: " . $e->getMessage());
}
?>