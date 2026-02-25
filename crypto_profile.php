<?php
session_start();
include 'db.php';
include 'functions.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = (int)$_SESSION['user_id'];
ensureUserCryptoAddressesTable($pdo);

$message = "";
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $message = ['type' => 'error', 'text' => 'Invalid session token. Please refresh and try again.'];
    } else {
        $network = normalizeNetwork($_POST['network'] ?? '');
        $address = trim($_POST['address'] ?? '');
        try {
            saveUserCryptoAddress($pdo, $user_id, $network, $address);
            $message = ['type' => 'success', 'text' => 'Address submitted. Admin verification is required before invest/withdraw on this network.'];
        } catch (Exception $e) {
            $message = ['type' => 'error', 'text' => $e->getMessage()];
        }
    }
}

$stmt = $pdo->prepare("SELECT username, user_code FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

$addr_stmt = $pdo->prepare("SELECT network, address, status, created_at, verified_at FROM user_crypto_addresses WHERE user_id = ? ORDER BY network");
$addr_stmt->execute([$user_id]);
$addresses = $addr_stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Crypto Address Profile</title>
    <style>
        body { font-family: Arial, sans-serif; background: #f1f5f9; margin: 0; padding: 20px; }
        .container { max-width: 900px; margin: 20px auto; }
        .card { background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 18px; margin-bottom: 16px; }
        h2 { margin-top: 0; }
        input, select, button { padding: 10px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 14px; }
        input, select { width: 100%; box-sizing: border-box; margin-bottom: 10px; }
        button { background: #1e3a8a; color: #fff; border: none; cursor: pointer; font-weight: 700; }
        .alert { padding: 10px 12px; border-radius: 8px; margin-bottom: 10px; font-size: 13px; }
        .alert-success { background: #dcfce7; color: #166534; }
        .alert-error { background: #fee2e2; color: #991b1b; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #e2e8f0; padding: 10px; font-size: 13px; text-align: left; }
        th { background: #f8fafc; }
        .pending { color: #92400e; font-weight: 700; }
        .verified { color: #166534; font-weight: 700; }
        .rejected { color: #991b1b; font-weight: 700; }
        .top { display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px; }
        .link { text-decoration: none; color: #1e3a8a; font-weight: 700; }
    </style>
</head>
<body>
    <div class="container">
        <div class="top">
            <a href="dashboard.php" class="link">Back to Dashboard</a>
            <div style="color:#475569; font-size:13px;"><?php echo htmlspecialchars($user['username'] ?? 'User'); ?> (<?php echo htmlspecialchars($user['user_code'] ?? ''); ?>)</div>
        </div>
        <div class="card">
            <h2>Crypto Address Profile</h2>
            <p style="color:#64748b; font-size:13px;">Submit your address for each network. You can invest/withdraw only after admin verifies it.</p>
            <?php if (!empty($message)): ?>
                <div class="alert alert-<?php echo $message['type']; ?>"><?php echo htmlspecialchars($message['text']); ?></div>
            <?php endif; ?>
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrfToken()); ?>">
                <label>Network</label>
                <select name="network" required>
                    <option value="">Select network</option>
                    <option value="TRC20">TRC20</option>
                    <option value="BEP20">BEP20</option>
                    <option value="SOLANA">SOLANA</option>
                </select>
                <label>Wallet Address</label>
                <input type="text" name="address" placeholder="Enter your wallet address" required>
                <button type="submit">Save Address</button>
            </form>
        </div>

        <div class="card">
            <h3>Your Saved Addresses</h3>
            <table>
                <thead>
                    <tr>
                        <th>Network</th>
                        <th>Address</th>
                        <th>Status</th>
                        <th>Submitted</th>
                        <th>Verified At</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($addresses)): ?>
                        <tr><td colspan="5" style="text-align:center;">No addresses added yet.</td></tr>
                    <?php else: ?>
                        <?php foreach ($addresses as $a): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($a['network']); ?></td>
                                <td><?php echo htmlspecialchars($a['address']); ?></td>
                                <td class="<?php echo htmlspecialchars($a['status']); ?>"><?php echo strtoupper(htmlspecialchars($a['status'])); ?></td>
                                <td><?php echo date('M d, Y H:i', strtotime($a['created_at'])); ?></td>
                                <td><?php echo $a['verified_at'] ? date('M d, Y H:i', strtotime($a['verified_at'])) : '-'; ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>
