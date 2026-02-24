<?php
include 'db.php';
include 'functions.php';

// Change this to the ID of the NEW user you just created
$new_user_id = 9; 
$investment = 1000;

distributeCommissions($pdo, $new_user_id, $investment);

echo "Payment processed for User $new_user_id. Check Admin Dashboard!";
?>