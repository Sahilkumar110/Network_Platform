<?php
session_start();
include 'db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$message = "";

// Fetch current user data
$stmt = $pdo->prepare("SELECT username, wallet_balance, last_withdrawal_date FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $amount = (float)$_POST['amount'];
    $min_withdraw = 200;
    
    // Check Weekly Constraint (7 days)
    $can_withdraw_date = true;
    if ($user['last_withdrawal_date']) {
        $last_date = strtotime($user['last_withdrawal_date']);
        $next_allowed_date = strtotime('+7 days', $last_date);
        if (time() < $next_allowed_date) {
            $can_withdraw_date = false;
            $days_left = ceil(($next_allowed_date - time()) / 86400);
        }
    }

    if ($amount < $min_withdraw) {
        $message = ["type" => "error", "text" => "Minimum withdrawal is $$min_withdraw."];
    } elseif ($amount > $user['wallet_balance']) {
        $message = ["type" => "error", "text" => "Insufficient balance in your wallet!"];
    } elseif (!$can_withdraw_date) {
        $message = ["type" => "error", "text" => "Weekly limit reached. Please wait $days_left more day(s)."];
    } else {
        try {
            $pdo->beginTransaction();

            // 1. Deduct from balance immediately (Locking the funds)
            $update = $pdo->prepare("UPDATE users SET wallet_balance = wallet_balance - ?, last_withdrawal_date = NOW() WHERE id = ?");
            $update->execute([$amount, $user_id]);

            // 2. Log transaction with PENDING status
            $log = $pdo->prepare("INSERT INTO transactions (user_id, amount, type, description, status, created_at) VALUES (?, ?, 'withdrawal', 'Withdrawal request to bank/crypto', 'pending', NOW())");
            $log->execute([$user_id, $amount]);

            $pdo->commit();
            
            // The specific message you requested
            $message = ["type" => "success", "text" => "Request sent! Admin will review it shortly. Processing takes up to 5 business days."];
            
            // Refresh local balance
            $user['wallet_balance'] -= $amount;
        } catch (Exception $e) {
            $pdo->rollBack();
            $message = ["type" => "error", "text" => "System Error: " . $e->getMessage()];
        }
    }
}

// Fetch recent withdrawal history
$history_stmt = $pdo->prepare("SELECT * FROM transactions WHERE user_id = ? AND type = 'withdrawal' ORDER BY created_at DESC LIMIT 5");
$history_stmt->execute([$user_id]);
$withdrawals = $history_stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Withdraw Funds | Network Platform</title>
    <script src="https://unpkg.com/lucide@latest"></script>
    <style>
        :root { --primary: #1e3a8a; --secondary: #3b82f6; --success: #10b981; --warning: #f59e0b; --danger: #ef4444; }
        body { font-family: 'Inter', sans-serif; background: #f1f5f9; margin: 0; padding: 20px; color: #1e293b; }
        .container { max-width: 600px; margin: 40px auto; }
        
        .header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 20px; }
        .back-link { text-decoration: none; color: var(--primary); font-weight: 600; display: flex; align-items: center; gap: 5px; }
        
        .card { background: white; padding: 30px; border-radius: 20px; box-shadow: 0 10px 15px -3px rgba(0,0,0,0.1); text-align: center; }
        .balance-badge { background: #eff6ff; padding: 20px; border-radius: 15px; margin-bottom: 25px; border: 1px solid #dbeafe; }
        .balance-amount { font-size: 32px; font-weight: 800; color: var(--primary); }
        
        .info-box { background: #fffbeb; border-left: 4px solid var(--warning); padding: 15px; margin-bottom: 20px; text-align: left; font-size: 14px; color: #92400e; }
        
        input { width: 100%; padding: 14px; border: 2px solid #e2e8f0; border-radius: 12px; font-size: 18px; text-align: center; box-sizing: border-box; margin-bottom: 15px; }
        input:focus { outline: none; border-color: var(--secondary); }
        
        button { width: 100%; padding: 16px; background: var(--primary); color: white; border: none; border-radius: 12px; font-weight: 700; font-size: 16px; cursor: pointer; transition: 0.3s; }
        button:hover { background: #1e40af; transform: translateY(-1px); }

        .alert { padding: 15px; border-radius: 12px; margin-bottom: 20px; font-weight: 500; }
        .alert-error { background: #fee2e2; color: #dc2626; }
        .alert-success { background: #dcfce7; color: #16a34a; }

        .history-section { margin-top: 40px; }
        .history-table { width: 100%; border-collapse: collapse; background: white; border-radius: 15px; overflow: hidden; }
        .history-table th, .history-table td { padding: 15px; text-align: left; border-bottom: 1px solid #f1f5f9; }
        .status { padding: 4px 10px; border-radius: 20px; font-size: 12px; font-weight: bold; text-transform: uppercase; }
        .status-pending { background: #fef3c7; color: #d97706; }
        .status-approved { background: #dcfce7; color: #16a34a; }
    </style>
</head>
<body>

<div class="container">
    <div class="header">
        <a href="dashboard.php" class="back-link"><i data-lucide="arrow-left"></i> Dashboard</a>
        <a href="index.php" style="font-weight: bold; text-decoration:none; color: var(--primary);">NETWORK<span>PLATFORM</span></a>
    </div>

    <div class="card">
        <h2>Withdraw Funds</h2>
        
        <div class="balance-badge">
            <div style="font-size: 14px; color: #64748b;">Available to Withdraw</div>
            <div class="balance-amount">$<?php echo number_format($user['wallet_balance'], 2); ?></div>
        </div>

        <?php if($message): ?>
            <div class="alert alert-<?php echo $message['type']; ?>">
                <?php echo $message['text']; ?>
            </div>
        <?php endif; ?>

        <div class="info-box">
            <strong><i data-lucide="clock" style="width:16px; height:16px; vertical-align:middle;"></i> Processing Time:</strong>
            Standard withdrawals take 5 business days to reach your account after admin approval.
        </div>

        <form method="POST">
            <label style="display:block; text-align:left; font-size:13px; font-weight:600; margin-bottom:5px;">Amount to Withdraw ($)</label>
            <input type="number" name="amount" min="200" step="0.01" placeholder="0.00" required>
            <button type="submit">Submit Withdrawal Request</button>
        </form>
    </div>

    <div class="history-section">
        <h3>Recent Requests</h3>
        <table class="history-table">
            <thead>
                <tr style="background: #f8fafc;">
                    <th>Date</th>
                    <th>Amount</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach($withdrawals as $row): ?>
                <tr>
                    <td><?php echo date('M d, Y', strtotime($row['created_at'])); ?></td>
                    <td>$<?php echo number_format($row['amount'], 2); ?></td>
                    <td><span class="status status-<?php echo $row['status']; ?>"><?php echo $row['status']; ?></span></td>
                </tr>
                <?php endforeach; if(empty($withdrawals)) echo "<tr><td colspan='3' style='text-align:center;'>No history yet</td></tr>"; ?>
            </tbody>
        </table>
    </div>
</div>

<script>lucide.createIcons();</script>
</body>
</html>