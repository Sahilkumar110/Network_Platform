<?php
include 'db.php';

$email = 'admin@test.com';
$pass = 'admin123';
$hashed = password_hash($pass, PASSWORD_BCRYPT);

// 1. Force update the user
$sql = "UPDATE users SET password = ?, role = 'admin' WHERE email = ?";
$stmt = $pdo->prepare($sql);
$stmt->execute([$hashed, $email]);

echo "<h3>Step 1: Database Updated</h3>";

// 2. Immediately test the verification
$check = $pdo->prepare("SELECT password FROM users WHERE email = ?");
$check->execute([$email]);
$user = $check->fetch();

echo "Step 2: Testing Password Verification...<br>";
if (password_verify($pass, $user['password'])) {
    echo "<b style='color:green;'>SUCCESS:</b> The PHP password_verify function matches the database hash!";
} else {
    echo "<b style='color:red;'>FAILED:</b> The hash in the DB is corrupted or too short.";
}
?>