<?php
session_start();
include 'db.php';
include 'functions.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$message = "";
ensureInvestmentRequestsTable($pdo);
ensureKycProfilesTable($pdo);

$addresses = getPlatformDepositAddresses();

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $message = "<p style='color:red;'>Invalid session token. Please refresh and try again.</p>";
    } else {
    $amount = (float)($_POST['amount'] ?? 0);
    $network = normalizeNetwork($_POST['network'] ?? '');
    $tx_hash = trim($_POST['tx_hash'] ?? '');

    if ($amount >= 100 && isset($addresses[$network]) && $tx_hash !== '' && isSupportedNetwork($network)) {
        try {
            $kyc = getUserKycStatus($pdo, $user_id);
            if (empty($kyc) || ($kyc['status'] ?? '') !== 'verified') {
                throw new Exception("KYC must be verified before submitting investment requests.");
            }
            assertValidTxHashFormat($network, $tx_hash);
            if (investmentTxHashExists($pdo, $network, $tx_hash)) {
                throw new Exception("Duplicate transaction hash. This TX hash has already been submitted.");
            }

            $verified_address = getVerifiedUserCryptoAddress($pdo, $user_id, $network);
            if (empty($verified_address)) {
                throw new Exception("Your {$network} address is not verified. Add/verify it in Crypto Address Profile first.");
            }

            $check_pending = $pdo->prepare(
                "SELECT COUNT(*) FROM investment_requests WHERE user_id = ? AND status = 'pending'"
            );
            $check_pending->execute([$user_id]);
            $pending_count = (int)$check_pending->fetchColumn();
            if ($pending_count >= 3) {
                throw new Exception("You already have multiple pending requests. Wait for admin review.");
            }

            $stmt = $pdo->prepare(
                "INSERT INTO investment_requests (user_id, amount, network, tx_hash, status, created_at)
                 VALUES (?, ?, ?, ?, 'pending', NOW())"
            );
            $stmt->execute([$user_id, $amount, $network, $tx_hash]);

            $message = "<p style='color:green;'>Deposit request submitted from your verified {$network} address. Admin will verify payment and activate investment.</p>";
        } catch (Exception $e) {
            $message = "<p style='color:red;'>Error: " . $e->getMessage() . "</p>";
        }
    } else {
        $message = "<p style='color:red;'>Amount must be at least $100, network must be valid, and TX hash is required in correct format.</p>";
    }
    }
}

$history_stmt = $pdo->prepare(
    "SELECT amount, network, tx_hash, status, created_at
     FROM investment_requests
     WHERE user_id = ?
     ORDER BY created_at DESC
     LIMIT 10"
);
$history_stmt->execute([$user_id]);
$requests = $history_stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html>
<head>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <title>Invest via Crypto</title>
<style>
        body { font-family: Arial; padding: 40px; background: #f4f4f4; }
        .box { background: white; padding: 20px; border-radius: 10px; max-width: 620px; margin: auto; }
        input, select { width: 100%; box-sizing:border-box; padding: 10px; margin: 10px 0; }
        button { width: 100%; padding: 10px; background: #27ae60; color: white; border: none; cursor: pointer; }
        .addr { background:#f8fafc; border:1px solid #e2e8f0; border-radius:8px; padding:10px; margin:6px 0; font-size:13px; word-break: break-all; }
        table { width:100%; border-collapse: collapse; margin-top:16px; }
        th, td { border:1px solid #e2e8f0; padding:8px; font-size:12px; text-align:left; }
        th { background:#f8fafc; }
    </style>
    <link rel="stylesheet" href="responsive.css">
</head>
<body>
    <div class="box">
        <h2>Invest via Crypto</h2>
        <p>Send crypto to one of these platform addresses, then submit network + transaction hash from your verified address.</p>
        <div class="addr"><strong>BEP20:</strong> <?php echo htmlspecialchars($addresses['BEP20']); ?></div>
        <div class="addr"><strong>TRC20:</strong> <?php echo htmlspecialchars($addresses['TRC20']); ?></div>
        <div class="addr"><strong>SOLANA:</strong> <?php echo htmlspecialchars($addresses['SOLANA']); ?></div>
        <?php echo $message; ?>
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrfToken()); ?>">
            <label>Investment Amount ($):</label>
            <input type="number" name="amount" placeholder="e.g. 1000" required>
            <label>Network:</label>
            <select name="network" required>
                <option value="">Select network</option>
                <option value="BEP20">BEP20</option>
                <option value="TRC20">TRC20</option>
                <option value="SOLANA">SOLANA</option>
            </select>
            <label>Transaction Hash (TXID):</label>
            <input type="text" name="tx_hash" placeholder="Paste your transaction hash" required>
            <button type="submit">Submit Deposit Request</button>
        </form>
        <p style="margin-top:8px;"><a href="crypto_profile.php">Manage & Verify Your Crypto Addresses</a></p>
        <h3>Recent Investment Requests</h3>
        <table>
            <thead>
                <tr><th>Date</th><th>Amount</th><th>Network</th><th>Status</th><th>TX Hash</th></tr>
            </thead>
            <tbody>
                <?php if (empty($requests)): ?>
                    <tr><td colspan="5" style="text-align:center;">No requests yet</td></tr>
                <?php else: foreach ($requests as $r): ?>
                    <tr>
                        <td><?php echo date('M d, Y H:i', strtotime($r['created_at'])); ?></td>
                        <td>$<?php echo number_format((float)$r['amount'], 2); ?></td>
                        <td><?php echo htmlspecialchars($r['network']); ?></td>
                        <td><?php echo htmlspecialchars($r['status']); ?></td>
                        <td><?php echo htmlspecialchars($r['tx_hash']); ?></td>
                    </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
        <br>
        <a href="dashboard.php">Back to Dashboard</a>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="scroll_top.js"></script>
</body>
</html>
