<?php
session_start();
include 'db.php';
include 'functions.php'; // This has the distributeCommissions logic

if (!isset($_SESSION['user_id'])) { die("Please login first."); }

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $user_id = $_SESSION['user_id'];
    $amount = (float)$_POST['amount'];

    if ($amount > 0) {
        // 1. Update the user's own investment total
        $stmt = $pdo->prepare("UPDATE users SET investment_amount = investment_amount + ? WHERE id = ?");
        $stmt->execute([$amount, $user_id]);

        // 2. Trigger the 5-Level Commission Engine
        // This will find the person who referred THIS user and pay them.
        distributeCommissions($pdo, $user_id, $amount);

        echo "Investment of $$amount successful! Commissions sent to uplines.";
    }
}
?>

<form method="POST">
    <h2>Invest Money</h2>
    <p>Enter amount to invest (this will trigger bonuses for your referrers):</p>
    <input type="number" name="amount" placeholder="Amount (e.g. 1000)" required>
    <button type="submit">Invest Now</button>
</form>
<br>
<a href="dashboard.php">Back to Dashboard</a>