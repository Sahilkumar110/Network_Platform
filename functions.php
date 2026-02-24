<?php
function distributeCommissions($pdo, $new_user_id, $investment_amount) {
    // Percentages for Level 1 to Level 5
    $percentages = [1 => 0.05, 2 => 0.04, 3 => 0.03, 4 => 0.02, 5 => 0.01];
    
    // Start with the person who referred the new user
    $current_user_id = $new_user_id;

    for ($level = 1; $level <= 5; $level++) {
        // 1. Find the referrer of the current person
        $stmt = $pdo->prepare("SELECT referrer_id FROM users WHERE id = ?");
        $stmt->execute([$current_user_id]);
        $user = $stmt->fetch();

        // If there is no referrer (top of the tree), stop the loop
        if (!$user || empty($user['referrer_id'])) {
            break;
        }

        $upline_id = $user['referrer_id'];
        $commission = $investment_amount * $percentages[$level];

        // 2. Update the Upline's wallet
        $update = $pdo->prepare("UPDATE users SET wallet_balance = wallet_balance + ? WHERE id = ?");
        $update->execute([$commission, $upline_id]);

        // 3. Record the transaction for transparency
        $log = $pdo->prepare("INSERT INTO transactions (user_id, amount, type, level, description) VALUES (?, ?, 'referral_bonus', ?, ?)");
        $desc = "Level $level bonus from User ID $new_user_id";
        $log->execute([$upline_id, $commission, $level, $desc]);

        // 4. Move up to the next person in the chain
        $current_user_id = $upline_id;
    }
}
?>