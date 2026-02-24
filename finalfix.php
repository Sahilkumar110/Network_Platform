<?php
include 'db.php';

$email = 'admin@test.com';
$password = 'admin123';
// PHP generates a fresh 60-character hash
$new_hash = password_hash($password, PASSWORD_BCRYPT);

$stmt = $pdo->prepare("UPDATE users SET password = ?, role = 'admin' WHERE email = ?");
$stmt->execute([$new_hash, $email]);

echo "<h3>Fixed!</h3>";
echo "New Hash: " . $new_hash . "<br>";
echo "New Length: " . strlen($new_hash) . " characters.<br>";
echo "<a href='login.php'>Try Login Now</a>";
?>