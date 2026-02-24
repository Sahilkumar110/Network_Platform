<?php
include 'db.php';

$email = 'admin@test.com';
$password = password_hash('admin123', PASSWORD_BCRYPT);
$username = 'SuperAdmin';
$role = 'admin';

// Delete existing admin to avoid duplicates
$pdo->prepare("DELETE FROM users WHERE email = ?")->execute([$email]);

// Insert fresh admin
$sql = "INSERT INTO users (username, email, password, role, wallet_balance) VALUES (?, ?, ?, ?, 0.00)";
$stmt = $pdo->prepare($sql);

if($stmt->execute([$username, $email, $password, $role])) {
    echo "Admin created successfully! You can now login with admin@test.com and admin123";
} else {
    echo "Error creating admin.";
}
?>