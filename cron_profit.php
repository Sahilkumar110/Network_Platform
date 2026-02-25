<?php
include 'db.php';
include 'functions.php';

$today = date('Y-m-d');

$stmt_check = $pdo->prepare("SELECT setting_value FROM settings WHERE setting_key = 'last_cron_run'");
$stmt_check->execute();
$last_run = $stmt_check->fetchColumn();

if ($last_run === $today) {
    die("Daily profit has already been processed for today.");
}

$rate_percent = 1.0;
$rate_decimal = 0.01;

try {
    $pdo->beginTransaction();

    $users_stmt = $pdo->query("SELECT id, investment_amount FROM users WHERE investment_amount > 0");
    $users = $users_stmt->fetchAll(PDO::FETCH_ASSOC);

    $processed = 0;
    $total_profit = 0.00;

    $wallet_update = $pdo->prepare("UPDATE users SET wallet_balance = wallet_balance + ? WHERE id = ?");
    $profit_log = $pdo->prepare(
        "INSERT INTO transactions (user_id, amount, type, level, description, created_at)
         VALUES (?, ?, 'daily_profit', NULL, 'Daily 1% profit', NOW())"
    );

    foreach ($users as $user) {
        $profit = round(((float)$user['investment_amount']) * $rate_decimal, 2);
        if ($profit <= 0) {
            continue;
        }

        $wallet_update->execute([$profit, $user['id']]);
        $profit_log->execute([$user['id'], $profit]);
        updateUserRank($pdo, $user['id']);

        $processed++;
        $total_profit += $profit;
    }

    $mark_run = $pdo->prepare("UPDATE settings SET setting_value = ? WHERE setting_key = 'last_cron_run'");
    $mark_run->execute([$today]);

    $pdo->commit();
    echo "Daily profit processed for $processed users at $rate_percent%. Total credited: $" . number_format($total_profit, 2);
} catch (Exception $e) {
    $pdo->rollBack();
    die("Error processing daily profit: " . $e->getMessage());
}
?>
