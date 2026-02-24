<?php
session_start();
include 'db.php';
include 'functions.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = (int)$_SESSION['user_id'];
$message = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $amount = (float)$_POST['amount'];

    if ($amount <= 0) {
        $message = ["type" => "error", "text" => "Amount must be greater than $0."];
    } else {
        try {
            $pdo->beginTransaction();

            $update = $pdo->prepare("UPDATE users SET wallet_balance = wallet_balance + ? WHERE id = ?");
            $update->execute([$amount, $user_id]);

            $log = $pdo->prepare(
                "INSERT INTO transactions (user_id, amount, type, level, description, created_at)
                 VALUES (?, ?, 'deposit', NULL, 'User wallet top-up', NOW())"
            );
            $log->execute([$user_id, $amount]);

            updateUserRank($pdo, $user_id);

            $pdo->commit();
            $message = ["type" => "success", "text" => "Wallet balance updated successfully."];
        } catch (Exception $e) {
            $pdo->rollBack();
            $message = ["type" => "error", "text" => "System error: " . $e->getMessage()];
        }
    }
}

$stmt = $pdo->prepare("SELECT username, wallet_balance, user_rank FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Wallet Balance</title>
    <style>
        :root { --primary: #1e3a8a; --secondary: #3b82f6; --success: #10b981; --danger: #ef4444; }
        body { font-family: Arial, sans-serif; background: #f1f5f9; margin: 0; padding: 20px; }
        .container { max-width: 500px; margin: 40px auto; }
        .card { background: #fff; border-radius: 14px; padding: 24px; box-shadow: 0 8px 20px rgba(0,0,0,0.08); }
        .back { text-decoration: none; color: var(--primary); font-weight: 700; display: inline-block; margin-bottom: 14px; }
        .balance { font-size: 32px; font-weight: 800; color: var(--primary); margin: 8px 0; }
        .rank { color: #64748b; margin-bottom: 18px; }
        .alert { padding: 10px 12px; border-radius: 8px; margin-bottom: 12px; }
        .alert-success { background: #dcfce7; color: #166534; }
        .alert-error { background: #fee2e2; color: #b91c1c; }
        input { width: 100%; box-sizing: border-box; padding: 12px; border: 1px solid #cbd5e1; border-radius: 8px; margin-bottom: 10px; }
        button { width: 100%; padding: 12px; border: none; border-radius: 8px; background: var(--secondary); color: #fff; font-weight: 700; cursor: pointer; }
        button:hover { background: #2563eb; }
    </style>
</head>
<body>
    <div class="container">
        <a href="dashboard.php" class="back">Back to Dashboard</a>
        <div class="card">
            <h2>Add Wallet Balance</h2>
            <p>Hello, <?php echo htmlspecialchars($user['username']); ?></p>
            <div class="balance">$<?php echo number_format($user['wallet_balance'], 2); ?></div>
            <div class="rank">Current Rank: <strong><?php echo htmlspecialchars($user['user_rank']); ?></strong></div>

            <?php if (!empty($message)): ?>
                <div class="alert alert-<?php echo $message['type']; ?>">
                    <?php echo htmlspecialchars($message['text']); ?>
                </div>
            <?php endif; ?>

            <form method="POST">
                <label for="amount">Amount ($)</label>
                <input id="amount" type="number" name="amount" step="0.01" min="0.01" placeholder="Enter amount" required>
                <button type="submit">Add to Wallet</button>
            </form>
        </div>
    </div>
</body>
</html>
