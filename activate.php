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

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $message = "<p style='color:red;'>Invalid session token. Please refresh and try again.</p>";
    } else {
    $amount = (float)$_POST['amount'];

    if ($amount >= 100) {
        try {
            processInvestment($pdo, $user_id, $amount, 'Activation');
            $message = "<p style='color:green;'>Account activated with $$amount. Daily 1% profit and referral bonuses are now active.</p>";
        } catch (Exception $e) {
            $message = "<p style='color:red;'>Error: " . $e->getMessage() . "</p>";
        }
    } else {
        $message = "<p style='color:red;'>Minimum activation amount is $100.</p>";
    }
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Activate Account</title>
    <style>
        body { font-family: Arial; padding: 40px; background: #f4f4f4; }
        .box { background: white; padding: 20px; border-radius: 10px; max-width: 400px; margin: auto; }
        input { width: 90%; padding: 10px; margin: 10px 0; }
        button { width: 100%; padding: 10px; background: #27ae60; color: white; border: none; cursor: pointer; }
    </style>
</head>
<body>
    <div class="box">
        <h2>Activate Investment</h2>
        <?php echo $message; ?>
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrfToken()); ?>">
            <label>Investment Amount ($):</label>
            <input type="number" name="amount" placeholder="e.g. 1000" required>
            <button type="submit">Pay & Activate</button>
        </form>
        <br>
        <a href="dashboard.php">Back to Dashboard</a>
    </div>
</body>
</html>
