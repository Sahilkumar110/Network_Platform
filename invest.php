<?php
session_start();
include 'db.php';
include 'functions.php';

if (!isset($_SESSION['user_id'])) { die("Please login first."); }

$message = "";

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $user_id = $_SESSION['user_id'];
    $amount = (float)$_POST['amount'];

    if ($amount >= 100) {
        try {
            processInvestment($pdo, $user_id, $amount, 'Investment');
            $message = "Investment of $$amount successful. Daily 1% and referral rules are now active.";
        } catch (Exception $e) {
            $message = "Error: " . $e->getMessage();
        }
    } else {
        $message = "Minimum investment is $100.";
    }
}
?>

<form method="POST">
    <h2>Invest Money</h2>
    <p>Enter amount to invest (this will trigger bonuses for your referrers):</p>
    <input type="number" name="amount" placeholder="Amount (e.g. 1000)" required>
    <button type="submit">Invest Now</button>
</form>
<?php if ($message): ?>
<p><?php echo htmlspecialchars($message); ?></p>
<?php endif; ?>
<br>
<a href="dashboard.php">Back to Dashboard</a>
