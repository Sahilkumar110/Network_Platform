<?php
include 'db.php';
include 'functions.php';
ensureWalletLedgerTable($pdo);
requireCronAccess();
ensureCronRunsTable($pdo);

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

    $users_stmt = $pdo->query(
        "SELECT id, investment_amount, wallet_balance
         FROM users
         WHERE investment_amount > 0 OR wallet_balance > 0"
    );
    $users = $users_stmt->fetchAll(PDO::FETCH_ASSOC);

    $processed = 0;
    $skipped = 0;
    $total_profit = 0.00;
    $daily_description = "Daily 1% fixed on wallet + investment amount [{$today}]";

    $profit_log = $pdo->prepare(
        "INSERT INTO transactions (user_id, amount, type, level, description, created_at)
         VALUES (?, ?, 'daily_profit', NULL, ?, NOW())"
    );
    $profit_exists = $pdo->prepare(
        "SELECT id
         FROM transactions
         WHERE user_id = ?
           AND type = 'daily_profit'
           AND DATE(created_at) = ?
         LIMIT 1"
    );

    foreach ($users as $user) {
        $base_amount = (float)$user['investment_amount'] + (float)$user['wallet_balance'];
        $profit = round($base_amount * $rate_decimal, 2);
        if ($profit <= 0) {
            continue;
        }

        // Idempotent guard: ensure this user is credited only once per day.
        $profit_exists->execute([(int)$user['id'], $today]);
        if ($profit_exists->fetchColumn()) {
            $skipped++;
            continue;
        }

        applyWalletDelta(
            $pdo,
            (int)$user['id'],
            $profit,
            'daily_profit',
            $daily_description,
            'cron_profit',
            null
        );
        $profit_log->execute([$user['id'], $profit, $daily_description]);

        $processed++;
        $total_profit += $profit;
    }

    // Keep all ranks consistent in one pass after profit distribution.
    recalculateAllUserRanks($pdo);

    $mark_run = $pdo->prepare("UPDATE settings SET setting_value = ? WHERE setting_key = 'last_cron_run'");
    $mark_run->execute([$today]);

    $pdo->commit();
    $msg = "Daily profit processed for $processed users at $rate_percent%. Skipped (already credited today): $skipped. Total credited: $" . number_format($total_profit, 2);
    logCronRun($pdo, 'cron_profit', 'success', $msg);
    echo $msg;
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    logCronRun($pdo, 'cron_profit', 'failed', $e->getMessage());
    die("Error processing daily profit: " . $e->getMessage());
}
?>
