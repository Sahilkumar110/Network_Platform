<?php
include 'db.php';

$today = date('Y-m-d');
$stmt_check = $pdo->prepare("SELECT setting_value FROM settings WHERE setting_key = 'last_cron_run'");
$stmt_check->execute();
$last_run = $stmt_check->fetchColumn();

if ($last_run === $today) {
    die("Daily profit has already been processed for today!");
}

// ... rest of the script ...

// At the very end of the script, update the date:
$pdo->prepare("UPDATE settings SET setting_value = ? WHERE setting_key = 'last_cron_run'")->execute([$today]);
// 1. Get the current Daily Interest Rate from settings
$stmt_rate = $pdo->prepare("SELECT setting_value FROM settings WHERE setting_key = 'daily_interest'");
$stmt_rate->execute();
$rate_percent = $stmt_rate->fetchColumn();
$rate_decimal = $rate_percent / 100;


try {
    $pdo->beginTransaction();

    // 2. Calculate and add profit to all users who have an investment > 0
    // formula: wallet_balance = wallet_balance + (investment_amount * rate)
    $sql = "UPDATE users 
            SET wallet_balance = wallet_balance + (investment_amount * ?) 
            WHERE investment_amount > 0";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$rate_decimal]);
    $affected_rows = $stmt->rowCount();

    // 3. (Optional) Log this global event in your transaction_logs
    $log_sql = "INSERT INTO transaction_logs (admin_id, user_id, action_type, amount, reason) 
                SELECT 0, id, 'add', (investment_amount * ?), 'Daily Profit' 
                FROM users WHERE investment_amount > 0";
    $pdo->prepare($log_sql)->execute([$rate_decimal]);

    $pdo->commit();
    echo "Successfully processed profit for $affected_rows users at $rate_percent%.";

} catch (Exception $e) {
    $pdo->rollBack();
    die("Error processing daily profit: " . $e->getMessage());
}
?>