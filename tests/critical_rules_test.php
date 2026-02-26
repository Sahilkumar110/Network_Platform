<?php
declare(strict_types=1);

require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../functions.php';

ensureMilestoneTable($pdo);
ensureInvestmentRequestsTable($pdo);
ensureUserCryptoAddressesTable($pdo);
ensureWalletLedgerTable($pdo);

$pass = 0;
$fail = 0;

function tassert(bool $condition, string $message): void {
    if (!$condition) {
        throw new Exception($message);
    }
}

function makeTestUser(PDO $pdo, string $label, ?int $referrerId = null): int {
    $email = strtolower('test_' . $label . '_' . bin2hex(random_bytes(4)) . '@example.com');
    $username = 'test_' . $label . '_' . substr(bin2hex(random_bytes(2)), 0, 4);
    $password = password_hash('secret123', PASSWORD_DEFAULT);
    $code = generateUniqueUserCode($pdo);
    $stmt = $pdo->prepare(
        "INSERT INTO users (username, password, email, referrer_id, wallet_balance, investment_amount, user_rank, role, status, user_code)
         VALUES (?, ?, ?, ?, 0, 0, 'Basic', 'user', 'active', ?)"
    );
    $stmt->execute([$username, $password, $email, $referrerId, $code]);
    return (int)$pdo->lastInsertId();
}

function fetchOne(PDO $pdo, string $sql, array $params = []) {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchColumn();
}

function runCase(PDO $pdo, string $name, callable $fn, int &$pass, int &$fail): void {
    try {
        $pdo->beginTransaction();
        $fn($pdo);
        $pdo->rollBack();
        $pass++;
        echo "[PASS] {$name}\n";
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $fail++;
        echo "[FAIL] {$name}: " . $e->getMessage() . "\n";
    }
}

runCase($pdo, 'Weekly Withdrawal Rule + Min $200', function (PDO $pdo): void {
    $ok = getWithdrawalEligibility(500, 100, null, 200, 7);
    tassert($ok['eligible'] === true, 'Expected eligible withdrawal.');

    $minFail = getWithdrawalEligibility(199.99, 50, null, 200, 7);
    tassert($minFail['eligible'] === false && $minFail['reason'] === 'below_minimum_balance', 'Expected min-balance failure.');

    $cooldownDate = date('Y-m-d H:i:s', strtotime('-3 days'));
    $coolFail = getWithdrawalEligibility(500, 100, $cooldownDate, 200, 7);
    tassert($coolFail['eligible'] === false && $coolFail['reason'] === 'cooldown', 'Expected weekly cooldown failure.');
}, $pass, $fail);

runCase($pdo, '5-Level Referral Bonus Math', function (PDO $pdo): void {
    $u1 = makeTestUser($pdo, 'u1');
    $u2 = makeTestUser($pdo, 'u2', $u1);
    $u3 = makeTestUser($pdo, 'u3', $u2);
    $u4 = makeTestUser($pdo, 'u4', $u3);
    $u5 = makeTestUser($pdo, 'u5', $u4);
    $u6 = makeTestUser($pdo, 'u6', $u5);

    processInvestment($pdo, $u6, 1000.00, 'Test Investment');

    $b1 = (float)fetchOne($pdo, "SELECT wallet_balance FROM users WHERE id = ?", [$u5]); // 5%
    $b2 = (float)fetchOne($pdo, "SELECT wallet_balance FROM users WHERE id = ?", [$u4]); // 4%
    $b3 = (float)fetchOne($pdo, "SELECT wallet_balance FROM users WHERE id = ?", [$u3]); // 3%
    $b4 = (float)fetchOne($pdo, "SELECT wallet_balance FROM users WHERE id = ?", [$u2]); // 2%
    $b5 = (float)fetchOne($pdo, "SELECT wallet_balance FROM users WHERE id = ?", [$u1]); // 1%

    tassert(abs($b1 - 50.00) < 0.0001, 'Level 1 bonus mismatch.');
    tassert(abs($b2 - 40.00) < 0.0001, 'Level 2 bonus mismatch.');
    tassert(abs($b3 - 30.00) < 0.0001, 'Level 3 bonus mismatch.');
    tassert(abs($b4 - 20.00) < 0.0001, 'Level 4 bonus mismatch.');
    tassert(abs($b5 - 10.00) < 0.0001, 'Level 5 bonus mismatch.');
}, $pass, $fail);

runCase($pdo, 'Milestone Bonus (10 Direct Referrals = $100 once)', function (PDO $pdo): void {
    $root = makeTestUser($pdo, 'milestone_root');
    for ($i = 0; $i < 10; $i++) {
        makeTestUser($pdo, 'milestone_ref_' . $i, $root);
    }

    applyMilestoneBonus($pdo, $root);
    applyMilestoneBonus($pdo, $root);

    $wallet = (float)fetchOne($pdo, "SELECT wallet_balance FROM users WHERE id = ?", [$root]);
    $count = (int)fetchOne($pdo, "SELECT COUNT(*) FROM milestone_rewards WHERE user_id = ? AND milestone_code = 'DIRECT_10'", [$root]);

    tassert(abs($wallet - 100.00) < 0.0001, 'Milestone bonus amount mismatch.');
    tassert($count === 1, 'Milestone should be awarded only once.');
}, $pass, $fail);

runCase($pdo, 'Rank Promotion Thresholds', function (PDO $pdo): void {
    $user = makeTestUser($pdo, 'rank');
    $upd = $pdo->prepare("UPDATE users SET investment_amount = ? WHERE id = ?");

    $upd->execute([10000.00, $user]);
    tassert(updateUserRank($pdo, $user) === 'Silver', 'Expected Silver at 10000.');

    $upd->execute([15000.00, $user]);
    tassert(updateUserRank($pdo, $user) === 'Gold', 'Expected Gold at 15000.');

    $upd->execute([20000.00, $user]);
    tassert(updateUserRank($pdo, $user) === 'Diamond', 'Expected Diamond at 20000.');
}, $pass, $fail);

runCase($pdo, 'Integration: Invest -> Daily Profit -> Withdraw Request', function (PDO $pdo): void {
    $user = makeTestUser($pdo, 'lifecycle');

    $network = 'BEP20';
    $address = '0x1234567890abcdef1234567890abcdef12345678';
    $txHash = '0x' . str_repeat('a', 64);

    assertValidCryptoAddressFormat($network, $address);
    assertValidTxHashFormat($network, $txHash);
    tassert(investmentTxHashExists($pdo, $network, $txHash) === false, 'TX hash should be new.');

    $addrStmt = $pdo->prepare(
        "INSERT INTO user_crypto_addresses (user_id, network, address, status, created_at, verified_at)
         VALUES (?, ?, ?, 'verified', NOW(), NOW())"
    );
    $addrStmt->execute([$user, $network, $address]);

    $reqStmt = $pdo->prepare(
        "INSERT INTO investment_requests (user_id, amount, network, tx_hash, status, created_at)
         VALUES (?, 1000.00, ?, ?, 'pending', NOW())"
    );
    $reqStmt->execute([$user, $network, $txHash]);
    $reqId = (int)$pdo->lastInsertId();

    processInvestment($pdo, $user, 1000.00, 'Test Lifecycle Approved');
    $pdo->prepare("UPDATE investment_requests SET status='approved', processed_at=NOW() WHERE id = ?")->execute([$reqId]);

    $investment = (float)fetchOne($pdo, "SELECT investment_amount FROM users WHERE id = ?", [$user]);
    tassert(abs($investment - 1000.00) < 0.0001, 'Investment amount not credited.');

    $dailyProfit = round($investment * 0.01, 2); // 1%
    applyWalletDelta($pdo, $user, $dailyProfit, 'daily_profit', 'Test lifecycle daily profit', 'test', null);

    $walletAfterProfit = (float)fetchOne($pdo, "SELECT wallet_balance FROM users WHERE id = ?", [$user]);
    tassert(abs($walletAfterProfit - 10.00) < 0.0001, 'Daily profit credit mismatch.');

    applyWalletDelta($pdo, $user, 250.00, 'admin_credit', 'Test top-up to meet withdrawal minimum', 'test', null);
    $eligible = getWithdrawalEligibility(
        (float)fetchOne($pdo, "SELECT wallet_balance FROM users WHERE id = ?", [$user]),
        200.00,
        null,
        200,
        7
    );
    tassert($eligible['eligible'] === true, 'User should be eligible for withdrawal request.');

    $withdrawAddress = (string)getVerifiedUserCryptoAddress($pdo, $user, $network);
    tassert($withdrawAddress !== '', 'Verified withdraw address not found.');

    $wStmt = $pdo->prepare(
        "INSERT INTO withdrawals (user_id, amount, method, details, status, created_at)
         VALUES (?, 200.00, ?, ?, 'pending', NOW())"
    );
    $wStmt->execute([$user, $network, $withdrawAddress]);
    $withdrawId = (int)$pdo->lastInsertId();

    applyWalletDelta($pdo, $user, -200.00, 'withdrawal_request', 'Test lifecycle withdrawal request', 'withdrawals', $withdrawId);
    $pending = (string)fetchOne($pdo, "SELECT status FROM withdrawals WHERE id = ?", [$withdrawId]);
    tassert($pending === 'pending', 'Withdrawal request should be pending.');
}, $pass, $fail);

echo "\nSummary: {$pass} passed, {$fail} failed.\n";
exit($fail > 0 ? 1 : 0);

