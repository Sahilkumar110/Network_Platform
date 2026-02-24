<?php
function getReferralCountAtLevel($pdo, $root_user_id, $target_level) {
    $current_ids = [(int)$root_user_id];
    for ($level = 1; $level <= (int)$target_level; $level++) {
        if (empty($current_ids)) {
            return 0;
        }

        $placeholders = implode(',', array_fill(0, count($current_ids), '?'));
        $stmt = $pdo->prepare("SELECT id FROM users WHERE referrer_id IN ($placeholders)");
        $stmt->execute($current_ids);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $current_ids = array_map(
            function ($r) { return (int)$r['id']; },
            $rows
        );
    }
    return count($current_ids);
}

function updateUserRank($pdo, $user_id) {
    $stmt = $pdo->prepare("SELECT wallet_balance FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $wallet_balance = (float)$stmt->fetchColumn();

    $level_1_count = getReferralCountAtLevel($pdo, $user_id, 1);
    $level_2_count = getReferralCountAtLevel($pdo, $user_id, 2);
    $level_3_count = getReferralCountAtLevel($pdo, $user_id, 3);
    $level_4_count = getReferralCountAtLevel($pdo, $user_id, 4);

    if ($wallet_balance >= 2000 && $level_4_count >= 10) {
        $rank = 'Diamond';
    } elseif ($wallet_balance >= 1500 && $level_3_count >= 7) {
        $rank = 'Gold';
    } elseif ($wallet_balance >= 1000 && $level_2_count >= 5) {
        $rank = 'Silver';
    } elseif ($level_1_count >= 3) {
        $rank = 'Silver';
    } else {
        $rank = 'Basic';
    }

    $update = $pdo->prepare("UPDATE users SET user_rank = ? WHERE id = ?");
    $update->execute([$rank, $user_id]);
}

function ensureMilestoneTable($pdo) {
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS milestone_rewards (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            milestone_code VARCHAR(50) NOT NULL,
            amount DECIMAL(15,2) NOT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uniq_user_milestone (user_id, milestone_code)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );
}

function applyMilestoneBonus($pdo, $user_id) {
    ensureMilestoneTable($pdo);

    $direct_stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE referrer_id = ?");
    $direct_stmt->execute([$user_id]);
    $direct_count = (int)$direct_stmt->fetchColumn();

    if ($direct_count < 10) {
        return;
    }

    $code = 'DIRECT_10';
    $check = $pdo->prepare("SELECT id FROM milestone_rewards WHERE user_id = ? AND milestone_code = ?");
    $check->execute([$user_id, $code]);
    if ($check->fetch()) {
        return;
    }

    $bonus_amount = 100.00;
    $wallet = $pdo->prepare("UPDATE users SET wallet_balance = wallet_balance + ? WHERE id = ?");
    $wallet->execute([$bonus_amount, $user_id]);

    $save = $pdo->prepare("INSERT INTO milestone_rewards (user_id, milestone_code, amount) VALUES (?, ?, ?)");
    $save->execute([$user_id, $code, $bonus_amount]);

    $log = $pdo->prepare(
        "INSERT INTO transactions (user_id, amount, type, level, description, created_at) VALUES (?, ?, 'referral_bonus', NULL, ?, NOW())"
    );
    $log->execute([$user_id, $bonus_amount, 'Milestone bonus: 10 direct referrals']);
}

function distributeCommissions($pdo, $investor_user_id, $investment_amount) {
    $percentages = [1 => 0.05, 2 => 0.04, 3 => 0.03, 4 => 0.02, 5 => 0.01];
    $current_user_id = $investor_user_id;

    for ($level = 1; $level <= 5; $level++) {
        $stmt = $pdo->prepare("SELECT referrer_id FROM users WHERE id = ?");
        $stmt->execute([$current_user_id]);
        $user = $stmt->fetch();

        if (!$user || empty($user['referrer_id'])) {
            break;
        }

        $upline_id = (int)$user['referrer_id'];
        $commission = (float)$investment_amount * $percentages[$level];

        $update = $pdo->prepare("UPDATE users SET wallet_balance = wallet_balance + ? WHERE id = ?");
        $update->execute([$commission, $upline_id]);

        $log = $pdo->prepare(
            "INSERT INTO transactions (user_id, amount, type, level, description, created_at) VALUES (?, ?, 'referral_bonus', ?, ?, NOW())"
        );
        $desc = "Level $level bonus from User ID $investor_user_id";
        $log->execute([$upline_id, $commission, $level, $desc]);

        $current_user_id = $upline_id;
    }
}

function processInvestment($pdo, $user_id, $amount, $source_label = 'Investment') {
    $amount = (float)$amount;
    if ($amount <= 0) {
        throw new Exception("Amount must be greater than 0.");
    }

    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare("UPDATE users SET investment_amount = investment_amount + ? WHERE id = ?");
        $stmt->execute([$amount, $user_id]);

        $log = $pdo->prepare(
            "INSERT INTO transactions (user_id, amount, type, level, description, created_at) VALUES (?, ?, 'deposit', NULL, ?, NOW())"
        );
        $log->execute([$user_id, $amount, $source_label . " credited"]);

        distributeCommissions($pdo, $user_id, $amount);
        updateUserRank($pdo, $user_id);

        $pdo->commit();
    } catch (Exception $e) {
        $pdo->rollBack();
        throw $e;
    }
}
?>
