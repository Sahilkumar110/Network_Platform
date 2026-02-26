<?php
session_start();
include 'db.php';
include 'functions.php';
ensureWalletLedgerTable($pdo);

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$message = "";
ensureUserCryptoAddressesTable($pdo);

// Fetch current user data
$stmt = $pdo->prepare("SELECT username, wallet_balance, last_withdrawal_date FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $message = ["type" => "error", "text" => "Invalid session token. Please refresh and try again."];
    } else {
    $amount = (float)$_POST['amount'];
    $network = normalizeNetwork($_POST['network'] ?? '');
    $min_balance_required = 200;
    $eligibility = getWithdrawalEligibility(
        (float)$user['wallet_balance'],
        (float)$amount,
        $user['last_withdrawal_date'],
        $min_balance_required,
        7
    );

    if (!isSupportedNetwork($network)) {
        $message = ["type" => "error", "text" => "Please select a valid network."];
    } elseif (empty(getVerifiedUserCryptoAddress($pdo, $user_id, $network))) {
        $message = ["type" => "error", "text" => "Your {$network} address is not verified. Please verify it first in Crypto Address Profile."];
    } elseif (!$eligibility['eligible'] && $eligibility['reason'] === 'amount_invalid') {
        $message = ["type" => "error", "text" => "Withdrawal amount must be greater than $0."];
    } elseif (!$eligibility['eligible'] && $eligibility['reason'] === 'below_minimum_balance') {
        $message = ["type" => "error", "text" => "You need at least $$min_balance_required in wallet to request withdrawal."];
    } elseif (!$eligibility['eligible'] && $eligibility['reason'] === 'insufficient_balance') {
        $message = ["type" => "error", "text" => "Insufficient balance in your wallet!"];
    } elseif (!$eligibility['eligible'] && $eligibility['reason'] === 'cooldown') {
        $days_left = (int)($eligibility['days_left'] ?? 1);
        $message = ["type" => "error", "text" => "Weekly limit reached. Please wait $days_left more day(s)."];
    } else {
        try {
            $pdo->beginTransaction();

            // 1. Save withdrawal request in dedicated withdrawals table
            $verified_address = getVerifiedUserCryptoAddress($pdo, $user_id, $network);
            $log = $pdo->prepare("INSERT INTO withdrawals (user_id, amount, method, details, status, created_at) VALUES (?, ?, ?, ?, 'pending', NOW())");
            $log->execute([$user_id, $amount, $network, $verified_address]);
            $withdrawal_id = (int)$pdo->lastInsertId();

            // 2. Atomic deduction + ledger row
            applyWalletDelta(
                $pdo,
                $user_id,
                -$amount,
                'withdrawal_request',
                "Withdrawal request submitted via {$network}",
                'withdrawals',
                $withdrawal_id
            );

            $set_date = $pdo->prepare("UPDATE users SET last_withdrawal_date = NOW() WHERE id = ?");
            $set_date->execute([$user_id]);

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
}

// Fetch recent withdrawal history for current logged-in user only
$history_stmt = $pdo->prepare("SELECT id, amount, status, created_at FROM withdrawals WHERE user_id = ? ORDER BY created_at DESC LIMIT 10");
$history_stmt->execute([$user_id]);
$withdrawals = $history_stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
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
    <link rel="stylesheet" href="responsive.css">
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
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrfToken()); ?>">
            <label style="display:block; text-align:left; font-size:13px; font-weight:600; margin-bottom:5px;">Network</label>
            <select name="network" required style="width:100%; padding:14px; border:2px solid #e2e8f0; border-radius:12px; font-size:16px; margin-bottom:10px;">
                <option value="">Select network</option>
                <option value="TRC20">TRC20</option>
                <option value="BEP20">BEP20</option>
                <option value="SOLANA">SOLANA</option>
            </select>
            <label style="display:block; text-align:left; font-size:13px; font-weight:600; margin-bottom:5px;">Amount to Withdraw ($)</label>
            <input type="number" name="amount" min="0.01" step="0.01" placeholder="0.00" required>
            <button type="submit">Submit Withdrawal Request</button>
        </form>
        <p style="font-size:12px; color:#64748b; margin-top:8px;">
            Need verification? <a href="crypto_profile.php">Manage & Verify Your Crypto Addresses</a>
        </p>
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
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
