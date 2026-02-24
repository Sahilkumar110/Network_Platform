<?php
include 'db.php';
include 'functions.php';

// We are pretending User ID 9 (your referral) just invested $1000
$investor_id = 10; 
$amount = 1000;

// This function climbs the tree and pays the person at Level 1 (You!)
distributeCommissions($pdo, $investor_id, $amount);

echo "Success! If User 9 was referred by Admin 8, Admin 8 just got $50.";
?>
 