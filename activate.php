<?php
session_start();
include 'db.php';
include 'functions.php'; // Required for the 5-level commission logic

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$message = "";

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $amount = (float)$_POST['amount'];

    if ($amount >= 100) { // Let's assume minimum investment is $100
        try {
            // 1. Update the user's investment amount
            $stmt = $pdo->prepare("UPDATE users SET investment_amount = investment_amount + ? WHERE id = ?");
            $stmt->execute([$amount, $user_id]);

            // 2. TRIGGER THE COMMISSION to uplines
            distributeCommissions($pdo, $user_id, $amount);

            $message = "<p style='color:green;'>Account Activated with $$amount! Your uplines have been paid.</p>";
        } catch (Exception $e) {
            $message = "<p style='color:red;'>Error: " . $e->getMessage() . "</p>";
        }
    } else {
        $message = "<p style='color:red;'>Minimum activation amount is $100.</p>";
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
            <label>Investment Amount ($):</label>
            <input type="number" name="amount" placeholder="e.g. 1000" required>
            <button type="submit">Pay & Activate</button>
        </form>
        <br>
        <a href="dashboard.php">Back to Dashboard</a>
    </div>
</body>
</html>