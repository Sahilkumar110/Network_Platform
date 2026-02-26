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

function ensureLoginAttemptsTable($pdo) {
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS login_attempts (
            id BIGINT AUTO_INCREMENT PRIMARY KEY,
            email VARCHAR(255) NOT NULL,
            ip_address VARCHAR(64) NOT NULL,
            is_success TINYINT(1) NOT NULL DEFAULT 0,
            attempted_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_email_time (email, attempted_at),
            INDEX idx_ip_time (ip_address, attempted_at),
            INDEX idx_success_time (is_success, attempted_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );
}

function getClientIpAddress() {
    $keys = ['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR'];
    foreach ($keys as $key) {
        if (!empty($_SERVER[$key])) {
            $raw = trim((string)$_SERVER[$key]);
            if ($key === 'HTTP_X_FORWARDED_FOR') {
                $parts = explode(',', $raw);
                return trim((string)$parts[0]);
            }
            return $raw;
        }
    }
    return 'unknown';
}

function getLoginLockStatus($pdo, $email, $ip, $max_attempts = 5, $window_minutes = 15, $lockout_minutes = 15) {
    $email = strtolower(trim((string)$email));
    $ip = trim((string)$ip);
    $window_minutes = max(1, (int)$window_minutes);
    $max_attempts = max(1, (int)$max_attempts);
    $lockout_minutes = max(1, (int)$lockout_minutes);

    $by_email = $pdo->prepare(
        "SELECT COUNT(*) AS fail_count, MAX(attempted_at) AS last_fail
         FROM login_attempts
         WHERE is_success = 0
           AND email = ?
           AND attempted_at >= (NOW() - INTERVAL {$window_minutes} MINUTE)"
    );
    $by_email->execute([$email]);
    $email_row = $by_email->fetch(PDO::FETCH_ASSOC) ?: ['fail_count' => 0, 'last_fail' => null];

    $by_ip = $pdo->prepare(
        "SELECT COUNT(*) AS fail_count, MAX(attempted_at) AS last_fail
         FROM login_attempts
         WHERE is_success = 0
           AND ip_address = ?
           AND attempted_at >= (NOW() - INTERVAL {$window_minutes} MINUTE)"
    );
    $by_ip->execute([$ip]);
    $ip_row = $by_ip->fetch(PDO::FETCH_ASSOC) ?: ['fail_count' => 0, 'last_fail' => null];

    $is_email_locked = ((int)$email_row['fail_count'] >= $max_attempts) && !empty($email_row['last_fail']);
    $is_ip_locked = ((int)$ip_row['fail_count'] >= $max_attempts) && !empty($ip_row['last_fail']);

    if (!$is_email_locked && !$is_ip_locked) {
        return ['locked' => false, 'seconds_left' => 0];
    }

    $last_fail_ts = 0;
    if ($is_email_locked) {
        $last_fail_ts = max($last_fail_ts, strtotime((string)$email_row['last_fail']));
    }
    if ($is_ip_locked) {
        $last_fail_ts = max($last_fail_ts, strtotime((string)$ip_row['last_fail']));
    }

    $unlock_ts = $last_fail_ts + ($lockout_minutes * 60);
    $seconds_left = max(0, $unlock_ts - time());
    if ($seconds_left <= 0) {
        return ['locked' => false, 'seconds_left' => 0];
    }

    return ['locked' => true, 'seconds_left' => $seconds_left];
}

function recordLoginAttempt($pdo, $email, $ip, $is_success) {
    $email = strtolower(trim((string)$email));
    $ip = trim((string)$ip);
    $ins = $pdo->prepare(
        "INSERT INTO login_attempts (email, ip_address, is_success, attempted_at)
         VALUES (?, ?, ?, NOW())"
    );
    $ins->execute([$email, $ip, $is_success ? 1 : 0]);

    if ($is_success) {
        // Remove recent failures for this identity after successful login.
        $clear = $pdo->prepare(
            "DELETE FROM login_attempts
             WHERE is_success = 0
               AND (email = ? OR ip_address = ?)"
        );
        $clear->execute([$email, $ip]);
    } else {
        // Keep table size under control.
        $cleanup = $pdo->prepare(
            "DELETE FROM login_attempts
             WHERE attempted_at < (NOW() - INTERVAL 30 DAY)"
        );
        $cleanup->execute();
    }
}

function isProductionEnv() {
    $env = strtolower(trim((string)(getenv('APP_ENV') ?: 'development')));
    return in_array($env, ['prod', 'production'], true);
}

function requireCronAccess() {
    if (PHP_SAPI === 'cli') {
        return;
    }
    if (!isProductionEnv()) {
        return;
    }

    $expected_token = trim((string)(getenv('CRON_SECRET') ?: ''));
    $provided_token = trim((string)($_GET['token'] ?? ''));
    if ($expected_token === '' || !hash_equals($expected_token, $provided_token)) {
        http_response_code(403);
        exit('Forbidden');
    }
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

function getRankFromInvestmentAmount($investment_amount) {
    $investment_amount = (float)$investment_amount;
    if ($investment_amount >= 20000) {
        return 'Diamond';
    }
    if ($investment_amount >= 15000) {
        return 'Gold';
    }
    if ($investment_amount >= 10000) {
        return 'Silver';
    }
    return 'Basic';
}

function updateUserRank($pdo, $user_id) {
    $stmt = $pdo->prepare("SELECT investment_amount, user_rank FROM users WHERE id = ?");
    $stmt->execute([(int)$user_id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        throw new Exception("User not found.");
    }

    $calculated_rank = getRankFromInvestmentAmount((float)$row['investment_amount']);
    if ((string)$row['user_rank'] !== $calculated_rank) {
        $update = $pdo->prepare("UPDATE users SET user_rank = ? WHERE id = ?");
        $update->execute([$calculated_rank, (int)$user_id]);
        queueUserNotification(
            $pdo,
            (int)$user_id,
            'rank_upgrade',
            'Rank Updated',
            "Your rank is now {$calculated_rank}."
        );
    }
    return $calculated_rank;
}

function recalculateAllUserRanks($pdo) {
    $sql = "UPDATE users
            SET user_rank = CASE
                WHEN investment_amount >= 20000 THEN 'Diamond'
                WHEN investment_amount >= 15000 THEN 'Gold'
                WHEN investment_amount >= 10000 THEN 'Silver'
                ELSE 'Basic'
            END";
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    return $stmt->rowCount();
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
            INDEX idx_created_at (created_at),
            INDEX idx_network_hash (network, tx_hash)
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

function ensureWalletLedgerTable($pdo) {
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS wallet_ledger (
            id BIGINT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            delta_amount DECIMAL(15,2) NOT NULL,
            balance_before DECIMAL(15,2) NOT NULL,
            balance_after DECIMAL(15,2) NOT NULL,
            entry_type VARCHAR(50) NOT NULL,
            description VARCHAR(255) DEFAULT NULL,
            reference_type VARCHAR(50) DEFAULT NULL,
            reference_id BIGINT DEFAULT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_user_created (user_id, created_at),
            INDEX idx_entry_type (entry_type)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );
}

function ensureNotificationQueueTable($pdo) {
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS notification_queue (
            id BIGINT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NULL,
            email_to VARCHAR(255) DEFAULT NULL,
            channel ENUM('email','telegram') NOT NULL,
            event_type VARCHAR(60) NOT NULL,
            subject VARCHAR(255) DEFAULT NULL,
            message TEXT NOT NULL,
            payload_json TEXT DEFAULT NULL,
            status ENUM('pending','sent','failed') NOT NULL DEFAULT 'pending',
            attempts INT NOT NULL DEFAULT 0,
            last_error VARCHAR(255) DEFAULT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            sent_at TIMESTAMP NULL DEFAULT NULL,
            INDEX idx_status_created (status, created_at),
            INDEX idx_event_type (event_type)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );
}

function ensureKycProfilesTable($pdo) {
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS kyc_profiles (
            id BIGINT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            full_name VARCHAR(120) NOT NULL,
            date_of_birth DATE NOT NULL,
            country_code VARCHAR(2) NOT NULL,
            document_type VARCHAR(40) NOT NULL,
            document_number VARCHAR(120) NOT NULL,
            document_ref VARCHAR(255) DEFAULT NULL,
            status ENUM('pending','verified','rejected') NOT NULL DEFAULT 'pending',
            review_note VARCHAR(255) DEFAULT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            reviewed_at TIMESTAMP NULL DEFAULT NULL,
            UNIQUE KEY uniq_user_kyc (user_id),
            INDEX idx_status_created (status, created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );
}

function ensureComplianceEventsTable($pdo) {
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS compliance_events (
            id BIGINT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            event_type VARCHAR(60) NOT NULL,
            severity ENUM('info','medium','high') NOT NULL DEFAULT 'info',
            details VARCHAR(255) DEFAULT NULL,
            payload_json TEXT DEFAULT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_user_created (user_id, created_at),
            INDEX idx_severity_created (severity, created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );
}

function ensureCronRunsTable($pdo) {
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS cron_runs (
            id BIGINT AUTO_INCREMENT PRIMARY KEY,
            job_name VARCHAR(80) NOT NULL,
            status ENUM('success','failed') NOT NULL,
            details VARCHAR(255) DEFAULT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_job_created (job_name, created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );
}

function ensureLedgerReconciliationTable($pdo) {
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS ledger_reconciliation_reports (
            id BIGINT AUTO_INCREMENT PRIMARY KEY,
            total_users INT NOT NULL,
            mismatched_users INT NOT NULL,
            total_mismatch_amount DECIMAL(15,2) NOT NULL DEFAULT 0,
            status ENUM('ok','warning') NOT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS ledger_reconciliation_items (
            id BIGINT AUTO_INCREMENT PRIMARY KEY,
            report_id BIGINT NOT NULL,
            user_id INT NOT NULL,
            wallet_balance DECIMAL(15,2) NOT NULL,
            ledger_balance DECIMAL(15,2) NOT NULL,
            difference DECIMAL(15,2) NOT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_report_user (report_id, user_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );
}

function queueNotification($pdo, $channel, $event_type, $subject, $message, $user_id = null, $email_to = null, $payload = null) {
    ensureNotificationQueueTable($pdo);
    $stmt = $pdo->prepare(
        "INSERT INTO notification_queue (user_id, email_to, channel, event_type, subject, message, payload_json, status, attempts, created_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, 'pending', 0, NOW())"
    );
    $stmt->execute([
        $user_id ? (int)$user_id : null,
        $email_to,
        (string)$channel,
        (string)$event_type,
        $subject,
        $message,
        $payload ? json_encode($payload) : null
    ]);
}

function queueUserNotification($pdo, $user_id, $event_type, $subject, $message, $payload = null) {
    $stmt = $pdo->prepare("SELECT email FROM users WHERE id = ? LIMIT 1");
    $stmt->execute([(int)$user_id]);
    $email = $stmt->fetchColumn();
    if (!empty($email)) {
        queueNotification($pdo, 'email', $event_type, $subject, $message, (int)$user_id, (string)$email, $payload);
    }

    $telegramEnabled = strtolower((string)(getenv('NOTIFY_TELEGRAM_ENABLED') ?: 'false'));
    if (in_array($telegramEnabled, ['1', 'true', 'yes', 'on'], true)) {
        queueNotification($pdo, 'telegram', $event_type, $subject, $message, (int)$user_id, null, $payload);
    }
}

function logComplianceEvent($pdo, $user_id, $event_type, $severity = 'info', $details = null, $payload = null) {
    ensureComplianceEventsTable($pdo);
    $stmt = $pdo->prepare(
        "INSERT INTO compliance_events (user_id, event_type, severity, details, payload_json, created_at)
         VALUES (?, ?, ?, ?, ?, NOW())"
    );
    $stmt->execute([
        (int)$user_id,
        (string)$event_type,
        (string)$severity,
        $details,
        $payload ? json_encode($payload) : null
    ]);
}

function getUserKycStatus($pdo, $user_id) {
    ensureKycProfilesTable($pdo);
    $stmt = $pdo->prepare("SELECT status, country_code FROM kyc_profiles WHERE user_id = ? LIMIT 1");
    $stmt->execute([(int)$user_id]);
    return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

function isCountryRestricted($country_code) {
    $country_code = strtoupper(trim((string)$country_code));
    if ($country_code === '') {
        return false;
    }
    $raw = (string)(getenv('RESTRICTED_COUNTRIES') ?: 'KP,IR,SY,CU');
    $list = array_filter(array_map('trim', explode(',', strtoupper($raw))));
    return in_array($country_code, $list, true);
}

function assessWithdrawalRisk($pdo, $user_id, $amount) {
    $score = 0;
    $reasons = [];
    $amount = (float)$amount;
    if ($amount >= (float)(getenv('WITHDRAWAL_HIGH_RISK_AMOUNT') ?: 1000)) {
        $score += 2;
        $reasons[] = 'large_amount';
    }

    $stmt = $pdo->prepare("SELECT created_at FROM users WHERE id = ? LIMIT 1");
    $stmt->execute([(int)$user_id]);
    $created = $stmt->fetchColumn();
    if ($created && strtotime((string)$created) > strtotime('-7 days')) {
        $score += 1;
        $reasons[] = 'new_account';
    }

    $pending = $pdo->prepare("SELECT COUNT(*) FROM withdrawals WHERE user_id = ? AND status = 'pending'");
    $pending->execute([(int)$user_id]);
    $pendingCount = (int)$pending->fetchColumn();
    if ($pendingCount >= 2) {
        $score += 1;
        $reasons[] = 'multiple_pending_withdrawals';
    }

    $level = $score >= 3 ? 'high' : ($score >= 1 ? 'medium' : 'low');
    return ['level' => $level, 'score' => $score, 'reasons' => $reasons];
}

function logCronRun($pdo, $job_name, $status, $details = null) {
    ensureCronRunsTable($pdo);
    $stmt = $pdo->prepare("INSERT INTO cron_runs (job_name, status, details, created_at) VALUES (?, ?, ?, NOW())");
    $stmt->execute([(string)$job_name, (string)$status, $details]);
}

function runWalletReconciliation($pdo) {
    ensureLedgerReconciliationTable($pdo);
    ensureWalletLedgerTable($pdo);

    $usersStmt = $pdo->query("SELECT id, wallet_balance FROM users");
    $users = $usersStmt->fetchAll(PDO::FETCH_ASSOC);
    $totalUsers = count($users);
    $mismatchCount = 0;
    $totalMismatchAmount = 0.0;
    $items = [];

    $ledgerStmt = $pdo->prepare(
        "SELECT balance_after FROM wallet_ledger WHERE user_id = ? ORDER BY id DESC LIMIT 1"
    );

    foreach ($users as $u) {
        $uid = (int)$u['id'];
        $wallet = round((float)$u['wallet_balance'], 2);
        $ledgerStmt->execute([$uid]);
        $ledgerBalRaw = $ledgerStmt->fetchColumn();
        $ledgerBal = $ledgerBalRaw === false ? 0.0 : round((float)$ledgerBalRaw, 2);
        $diff = round($wallet - $ledgerBal, 2);
        if (abs($diff) > 0.009) {
            $mismatchCount++;
            $totalMismatchAmount += abs($diff);
            $items[] = ['user_id' => $uid, 'wallet' => $wallet, 'ledger' => $ledgerBal, 'diff' => $diff];
        }
    }

    $status = $mismatchCount > 0 ? 'warning' : 'ok';
    $rep = $pdo->prepare(
        "INSERT INTO ledger_reconciliation_reports (total_users, mismatched_users, total_mismatch_amount, status, created_at)
         VALUES (?, ?, ?, ?, NOW())"
    );
    $rep->execute([$totalUsers, $mismatchCount, round($totalMismatchAmount, 2), $status]);
    $reportId = (int)$pdo->lastInsertId();

    if (!empty($items)) {
        $ins = $pdo->prepare(
            "INSERT INTO ledger_reconciliation_items (report_id, user_id, wallet_balance, ledger_balance, difference, created_at)
             VALUES (?, ?, ?, ?, ?, NOW())"
        );
        foreach ($items as $it) {
            $ins->execute([$reportId, $it['user_id'], $it['wallet'], $it['ledger'], $it['diff']]);
        }
    }

    return ['report_id' => $reportId, 'status' => $status, 'mismatched_users' => $mismatchCount];
}

function getPlatformDepositAddresses() {
    return [
        'TRC20' => trim((string)(getenv('DEPOSIT_ADDRESS_TRC20') ?: 'TXYZ1234567890abcdefghijklmnopqrstuvwxy')),
        'BEP20' => trim((string)(getenv('DEPOSIT_ADDRESS_BEP20') ?: '0x1234567890abcdef1234567890abcdef12345678')),
        'SOLANA' => trim((string)(getenv('DEPOSIT_ADDRESS_SOLANA') ?: 'HN7cABqLq46Es1jh92dQQisAq662SmxELLLsHHe4YWrH')),
    ];
}

function httpJsonRequest($method, $url, $payload = null, $headers = []) {
    $method = strtoupper((string)$method);
    $defaultHeaders = ["Accept: application/json"];
    if ($payload !== null) {
        $defaultHeaders[] = "Content-Type: application/json";
    }
    $allHeaders = array_merge($defaultHeaders, $headers);
    $opts = [
        'http' => [
            'method' => $method,
            'header' => implode("\r\n", $allHeaders),
            'timeout' => 20,
            'ignore_errors' => true
        ]
    ];
    if ($payload !== null) {
        $opts['http']['content'] = json_encode($payload);
    }
    $ctx = stream_context_create($opts);
    $raw = @file_get_contents($url, false, $ctx);
    if ($raw === false) {
        return [null, null];
    }
    $decoded = json_decode($raw, true);
    $status = null;
    if (!empty($http_response_header[0]) && preg_match('/\s(\d{3})\s/', $http_response_header[0], $m)) {
        $status = (int)$m[1];
    }
    return [$status, $decoded];
}

function verifyOnChainTransaction($network, $tx_hash, $expected_to_address = null) {
    $network = normalizeNetwork($network);
    $tx_hash = trim((string)$tx_hash);
    if (!validateTxHashFormat($network, $tx_hash)) {
        return ['ok' => false, 'reason' => 'invalid_tx_hash_format'];
    }

    if ($network === 'BEP20') {
        $apiKey = trim((string)(getenv('BSCSCAN_API_KEY') ?: ''));
        $base = trim((string)(getenv('BSCSCAN_API_BASE') ?: 'https://api.bscscan.com/api'));
        if ($apiKey === '') {
            return ['ok' => false, 'reason' => 'missing_bsc_api_key'];
        }

        $txUrl = $base . '?module=proxy&action=eth_getTransactionByHash&txhash=' . urlencode($tx_hash) . '&apikey=' . urlencode($apiKey);
        $rcpUrl = $base . '?module=proxy&action=eth_getTransactionReceipt&txhash=' . urlencode($tx_hash) . '&apikey=' . urlencode($apiKey);
        [, $txRes] = httpJsonRequest('GET', $txUrl);
        [, $rcpRes] = httpJsonRequest('GET', $rcpUrl);
        $tx = $txRes['result'] ?? null;
        $rcp = $rcpRes['result'] ?? null;
        if (empty($tx) || empty($rcp)) {
            return ['ok' => false, 'reason' => 'tx_not_found'];
        }
        if (strtolower((string)($rcp['status'] ?? '0x0')) !== '0x1') {
            return ['ok' => false, 'reason' => 'tx_failed'];
        }
        if (!empty($expected_to_address)) {
            $to = strtolower((string)($tx['to'] ?? ''));
            if ($to !== strtolower((string)$expected_to_address)) {
                $input = strtolower((string)($tx['input'] ?? ''));
                $isTransfer = substr($input, 0, 10) === '0xa9059cbb';
                if (!$isTransfer) {
                    return ['ok' => false, 'reason' => 'destination_mismatch'];
                }
            }
        }
        return ['ok' => true, 'reason' => 'verified'];
    }

    if ($network === 'TRC20') {
        $base = trim((string)(getenv('TRONGRID_API_BASE') ?: 'https://api.trongrid.io'));
        $url = rtrim($base, '/') . '/v1/transactions/' . urlencode($tx_hash);
        [, $res] = httpJsonRequest('GET', $url);
        $row = $res['data'][0] ?? null;
        $status = strtoupper((string)($row['ret'][0]['contractRet'] ?? ''));
        if (empty($row) || $status !== 'SUCCESS') {
            return ['ok' => false, 'reason' => 'tx_not_success'];
        }
        return ['ok' => true, 'reason' => 'verified'];
    }

    if ($network === 'SOLANA') {
        $rpc = trim((string)(getenv('SOLANA_RPC_URL') ?: 'https://api.mainnet-beta.solana.com'));
        $payload = [
            'jsonrpc' => '2.0',
            'id' => 1,
            'method' => 'getTransaction',
            'params' => [$tx_hash, ['maxSupportedTransactionVersion' => 0]]
        ];
        [, $res] = httpJsonRequest('POST', $rpc, $payload);
        $tx = $res['result'] ?? null;
        if (empty($tx) || !empty($tx['meta']['err'])) {
            return ['ok' => false, 'reason' => 'tx_not_success'];
        }
        return ['ok' => true, 'reason' => 'verified'];
    }

    return ['ok' => false, 'reason' => 'unsupported_network'];
}

function applyWalletDelta(
    $pdo,
    $user_id,
    $delta_amount,
    $entry_type,
    $description = null,
    $reference_type = null,
    $reference_id = null
) {
    $delta_amount = round((float)$delta_amount, 2);
    if ($delta_amount == 0.0) {
        throw new Exception("Wallet delta cannot be zero.");
    }

    $stmt = $pdo->prepare("SELECT wallet_balance FROM users WHERE id = ? FOR UPDATE");
    $stmt->execute([(int)$user_id]);
    $current_balance = $stmt->fetchColumn();

    if ($current_balance === false) {
        throw new Exception("User not found.");
    }

    $before = round((float)$current_balance, 2);
    $after = round($before + $delta_amount, 2);
    if ($after < 0) {
        throw new Exception("Insufficient wallet balance.");
    }

    $update = $pdo->prepare("UPDATE users SET wallet_balance = ? WHERE id = ?");
    $update->execute([$after, (int)$user_id]);

    $ledger = $pdo->prepare(
        "INSERT INTO wallet_ledger
        (user_id, delta_amount, balance_before, balance_after, entry_type, description, reference_type, reference_id, created_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())"
    );
    $ledger->execute([
        (int)$user_id,
        $delta_amount,
        $before,
        $after,
        (string)$entry_type,
        $description,
        $reference_type,
        $reference_id
    ]);

    return $after;
}

function normalizeNetwork($network) {
    return strtoupper(trim((string)$network));
}

function isSupportedNetwork($network) {
    return in_array($network, ['TRC20', 'BEP20', 'SOLANA'], true);
}

function validateCryptoAddressFormat($network, $address) {
    $network = normalizeNetwork($network);
    $address = trim((string)$address);
    if ($address === '') {
        return false;
    }

    if ($network === 'TRC20') {
        return (bool)preg_match('/^T[1-9A-HJ-NP-Za-km-z]{33}$/', $address);
    }
    if ($network === 'BEP20') {
        return (bool)preg_match('/^0x[a-fA-F0-9]{40}$/', $address);
    }
    if ($network === 'SOLANA') {
        return (bool)preg_match('/^[1-9A-HJ-NP-Za-km-z]{32,44}$/', $address);
    }
    return false;
}

function assertValidCryptoAddressFormat($network, $address) {
    if (!validateCryptoAddressFormat($network, $address)) {
        throw new Exception("Invalid {$network} wallet address format.");
    }
}

function validateTxHashFormat($network, $tx_hash) {
    $network = normalizeNetwork($network);
    $tx_hash = trim((string)$tx_hash);
    if ($tx_hash === '') {
        return false;
    }

    if ($network === 'TRC20') {
        return (bool)preg_match('/^[a-fA-F0-9]{64}$/', $tx_hash);
    }
    if ($network === 'BEP20') {
        return (bool)preg_match('/^0x[a-fA-F0-9]{64}$/', $tx_hash);
    }
    if ($network === 'SOLANA') {
        return (bool)preg_match('/^[1-9A-HJ-NP-Za-km-z]{43,88}$/', $tx_hash);
    }
    return false;
}

function assertValidTxHashFormat($network, $tx_hash) {
    if (!validateTxHashFormat($network, $tx_hash)) {
        throw new Exception("Invalid {$network} transaction hash format.");
    }
}

function investmentTxHashExists($pdo, $network, $tx_hash, $exclude_request_id = null) {
    $network = normalizeNetwork($network);
    $tx_hash = trim((string)$tx_hash);
    $exclude_request_id = (int)$exclude_request_id;

    $sql = "SELECT id FROM investment_requests WHERE network = ? AND LOWER(tx_hash) = LOWER(?)";
    $params = [$network, $tx_hash];
    if ($exclude_request_id > 0) {
        $sql .= " AND id <> ?";
        $params[] = $exclude_request_id;
    }
    $sql .= " LIMIT 1";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return (bool)$stmt->fetchColumn();
}

function getWithdrawalEligibility($wallet_balance, $amount, $last_withdrawal_date, $min_balance_required = 200, $cooldown_days = 7) {
    $wallet_balance = (float)$wallet_balance;
    $amount = (float)$amount;
    $min_balance_required = (float)$min_balance_required;
    $cooldown_days = max(1, (int)$cooldown_days);

    if ($amount <= 0) {
        return ['eligible' => false, 'reason' => 'amount_invalid'];
    }
    if ($wallet_balance < $min_balance_required) {
        return ['eligible' => false, 'reason' => 'below_minimum_balance'];
    }
    if ($amount > $wallet_balance) {
        return ['eligible' => false, 'reason' => 'insufficient_balance'];
    }

    if (!empty($last_withdrawal_date)) {
        $last_ts = strtotime((string)$last_withdrawal_date);
        if ($last_ts !== false) {
            $next_allowed = strtotime("+{$cooldown_days} days", $last_ts);
            if ($next_allowed !== false && time() < $next_allowed) {
                $days_left = (int)ceil(($next_allowed - time()) / 86400);
                return ['eligible' => false, 'reason' => 'cooldown', 'days_left' => max(1, $days_left)];
            }
        }
    }

    return ['eligible' => true, 'reason' => 'ok'];
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
    assertValidCryptoAddressFormat($network, $address);

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
    $bonus_amount = 100.00;
    $owns_transaction = !$pdo->inTransaction();
    if ($owns_transaction) {
        $pdo->beginTransaction();
    }

    try {
        $save = $pdo->prepare("INSERT INTO milestone_rewards (user_id, milestone_code, amount) VALUES (?, ?, ?)");
        $save->execute([(int)$user_id, $code, $bonus_amount]);
        $milestone_id = (int)$pdo->lastInsertId();

        applyWalletDelta(
            $pdo,
            (int)$user_id,
            $bonus_amount,
            'milestone_bonus',
            'Milestone bonus: 10 direct referrals',
            'milestone_rewards',
            $milestone_id
        );

        $log = $pdo->prepare(
            "INSERT INTO transactions (user_id, amount, type, level, description, created_at) VALUES (?, ?, 'referral_bonus', NULL, ?, NOW())"
        );
        $log->execute([$user_id, $bonus_amount, 'Milestone bonus: 10 direct referrals']);

        if ($owns_transaction) {
            $pdo->commit();
        }
    } catch (PDOException $e) {
        if ($owns_transaction && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        // Duplicate milestone insert means already rewarded; ignore safely.
        if ($e->getCode() === '23000') {
            return;
        }
        throw $e;
    } catch (Exception $e) {
        if ($owns_transaction && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
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

        applyWalletDelta(
            $pdo,
            $upline_id,
            $commission,
            'referral_bonus',
            "Level $level bonus from User ID $investor_user_id",
            'investment',
            (int)$investor_user_id
        );

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

    $owns_transaction = !$pdo->inTransaction();
    if ($owns_transaction) {
        $pdo->beginTransaction();
    }
    try {
        $stmt = $pdo->prepare("UPDATE users SET investment_amount = investment_amount + ? WHERE id = ?");
        $stmt->execute([$amount, $user_id]);

        $log = $pdo->prepare(
            "INSERT INTO transactions (user_id, amount, type, level, description, created_at) VALUES (?, ?, 'deposit', NULL, ?, NOW())"
        );
        $log->execute([$user_id, $amount, $source_label . " credited"]);

        distributeCommissions($pdo, $user_id, $amount);
        updateUserRank($pdo, $user_id);

        if ($owns_transaction) {
            $pdo->commit();
        }
    } catch (Exception $e) {
        if ($owns_transaction && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}
?>
