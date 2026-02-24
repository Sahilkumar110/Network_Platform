<?php
include 'db.php';

$email = 'admin@test.com';
$password = 'admin123';
$username = 'SuperAdmin';
$role = 'admin';

// 1. Delete the corrupted user
$pdo->prepare("DELETE FROM users WHERE email = ?")->execute([$email]);

// 2. Generate a fresh, clean hash
$new_hash = password_hash($password, PASSWORD_BCRYPT);

// 3. Insert fresh record
$sql = "INSERT INTO users (username, email, password, role, wallet_balance) VALUES (?, ?, ?, ?, 0.00)";
$stmt = $pdo->prepare($sql);

if($stmt->execute([$username, $email, $new_hash, $role])) {
    echo "<h2>Admin Account Recreated!</h2>";
    echo "Email: $email <br>";
    echo "Password: $password <br>";
    echo "Verified Hash Length: " . strlen($new_hash) . " characters.<br>";
    echo "<hr><a href='login.php'>GO TO LOGIN PAGE</a>";
} else {
    echo "Error recreating user.";
}
?>