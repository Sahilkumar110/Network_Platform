<?php
function csrfToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verifyCsrfToken($token) {
    return isset($_SESSION['csrf_token']) && is_string($token) && hash_equals($_SESSION['csrf_token'], $token);
}

function generateUniqueUserCode($pdo) {
    $year_suffix = date('y');
    for ($i = 0; $i < 50; $i++) {
        $random_part = (string)random_int(10000, 99999);
        $candidate = "NP#{$random_part}{$year_suffix}";
        $check = $pdo->prepare("SELECT COUNT(*) FROM users WHERE user_code = ?");
        $check->execute([$candidate]);
        if ((int)$check->fetchColumn() === 0) {
            return $candidate;
        }
    }
    throw new Exception("Unable to generate unique user code.");
}

function ensureUserCode($pdo, $user_id) {
    $stmt = $pdo->prepare("SELECT user_code FROM users WHERE id = ?");
    $stmt->execute([(int)$user_id]);
    $code = $stmt->fetchColumn();
    if (!empty($code)) {
        return $code;
    }

    $new_code = generateUniqueUserCode($pdo);
    $update = $pdo->prepare("UPDATE users SET user_code = ? WHERE id = ? AND (user_code IS NULL OR user_code = '')");
    $update->execute([$new_code, (int)$user_id]);

    $stmt->execute([(int)$user_id]);
    return (string)$stmt->fetchColumn();
}

function backfillMissingUserCodes($pdo) {
    $stmt = $pdo->query("SELECT id FROM users WHERE user_code IS NULL OR user_code = ''");
    $ids = $stmt->fetchAll(PDO::FETCH_COLUMN);
    foreach ($ids as $id) {
        ensureUserCode($pdo, (int)$id);
    }
}

function getUserCodeById($pdo, $user_id) {
    if (empty($user_id)) {
        return null;
    }
    $stmt = $pdo->prepare("SELECT user_code FROM users WHERE id = ?");
    $stmt->execute([(int)$user_id]);
    $code = $stmt->fetchColumn();
    if (!empty($code)) {
        return $code;
    }
    return ensureUserCode($pdo, (int)$user_id);
}

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
    $stmt = $pdo->prepare("SELECT investment_amount FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $investment_amount = (float)$stmt->fetchColumn();

    if ($investment_amount >= 20000) {
        $rank = 'Diamond';
    } elseif ($investment_amount >= 15000) {
        $rank = 'Gold';
    } elseif ($investment_amount >= 10000) {
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

function ensureInvestmentRequestsTable($pdo) {
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS investment_requests (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            amount DECIMAL(15,2) NOT NULL,
            network VARCHAR(20) NOT NULL,
            tx_hash VARCHAR(255) NOT NULL,
            status ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
            admin_note VARCHAR(255) DEFAULT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            processed_at TIMESTAMP NULL DEFAULT NULL,
            INDEX idx_user_status (user_id, status),
            INDEX idx_created_at (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );
}

function ensureUserCryptoAddressesTable($pdo) {
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS user_crypto_addresses (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            network ENUM('TRC20','BEP20','SOLANA') NOT NULL,
            address VARCHAR(255) NOT NULL,
            status ENUM('pending','verified','rejected') NOT NULL DEFAULT 'pending',
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            verified_at TIMESTAMP NULL DEFAULT NULL,
            UNIQUE KEY uniq_user_network (user_id, network),
            UNIQUE KEY uniq_network_address (network, address),
            INDEX idx_status_created (status, created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );
}

function normalizeNetwork($network) {
    return strtoupper(trim((string)$network));
}

function isSupportedNetwork($network) {
    return in_array($network, ['TRC20', 'BEP20', 'SOLANA'], true);
}

function getUserCryptoAddress($pdo, $user_id, $network) {
    $network = normalizeNetwork($network);
    $stmt = $pdo->prepare(
        "SELECT * FROM user_crypto_addresses WHERE user_id = ? AND network = ? LIMIT 1"
    );
    $stmt->execute([(int)$user_id, $network]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

function getVerifiedUserCryptoAddress($pdo, $user_id, $network) {
    $network = normalizeNetwork($network);
    $stmt = $pdo->prepare(
        "SELECT address FROM user_crypto_addresses WHERE user_id = ? AND network = ? AND status = 'verified' LIMIT 1"
    );
    $stmt->execute([(int)$user_id, $network]);
    return $stmt->fetchColumn();
}

function saveUserCryptoAddress($pdo, $user_id, $network, $address) {
    $network = normalizeNetwork($network);
    $address = trim((string)$address);
    if (!isSupportedNetwork($network)) {
        throw new Exception("Unsupported network.");
    }
    if ($address === '') {
        throw new Exception("Address is required.");
    }

    $existing = getUserCryptoAddress($pdo, (int)$user_id, $network);
    if ($existing) {
        $stmt = $pdo->prepare(
            "UPDATE user_crypto_addresses
             SET address = ?, status = 'pending', verified_at = NULL, created_at = NOW()
             WHERE user_id = ? AND network = ?"
        );
        $stmt->execute([$address, (int)$user_id, $network]);
    } else {
        $stmt = $pdo->prepare(
            "INSERT INTO user_crypto_addresses (user_id, network, address, status, created_at)
             VALUES (?, ?, ?, 'pending', NOW())"
        );
        $stmt->execute([(int)$user_id, $network, $address]);
    }
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
