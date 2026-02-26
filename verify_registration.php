<?php
session_start();
include 'db.php';
include 'functions.php';
ensureRegistrationOtpsTable($pdo);
ensureNotificationQueueTable($pdo);

$email = strtolower(trim((string)($_GET['email'] ?? $_POST['email'] ?? ($_SESSION['register_verify_email'] ?? ''))));
$message = '';
$message_type = '';
$resend_wait_seconds = 0;
$resend_attempts_left = 3;
$registration_done = false;

function sendRegistrationOtpForEmail($pdo, $email) {
    $email = strtolower(trim((string)$email));
    $src = $pdo->prepare(
        "SELECT username, email, password_hash, referrer_id, user_code
         FROM registration_otps
         WHERE email = ?
         ORDER BY id DESC
         LIMIT 1"
    );
    $src->execute([$email]);
    $base = $src->fetch(PDO::FETCH_ASSOC);
    if (!$base) {
        throw new Exception('No active registration found.');
    }

    $deactivate = $pdo->prepare(
        "UPDATE registration_otps
         SET used_at = NOW()
         WHERE email = ? AND used_at IS NULL"
    );
    $deactivate->execute([$email]);

    $otp = (string)random_int(100000, 999999);
    $otp_hash = hash('sha256', $otp);
    $ip = getClientIpAddress();

    $ins = $pdo->prepare(
        "INSERT INTO registration_otps
         (username, email, password_hash, referrer_id, user_code, otp_hash, requested_ip, expires_at, used_at, created_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, DATE_ADD(NOW(), INTERVAL 60 SECOND), NULL, NOW())"
    );
    $ins->execute([
        (string)$base['username'],
        (string)$base['email'],
        (string)$base['password_hash'],
        !empty($base['referrer_id']) ? (int)$base['referrer_id'] : null,
        (string)$base['user_code'],
        $otp_hash,
        $ip
    ]);

    queueNotification(
        $pdo,
        'email',
        'registration_otp',
        'Your Registration OTP',
        "Your registration OTP is {$otp}. It is valid for 60 seconds.",
        null,
        $email,
        ['ip' => $ip]
    );

    processNotificationQueue(
        $pdo,
        5,
        "channel = 'email' AND event_type = 'registration_otp' AND email_to = ?",
        [$email]
    );
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $message = 'Invalid session token. Please refresh and try again.';
        $message_type = 'error';
    } else {
        $action = (string)($_POST['action'] ?? 'verify_otp');
        $email = strtolower(trim((string)($_POST['email'] ?? $email)));

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $message = 'Please enter a valid email.';
            $message_type = 'error';
        } else {
            if ($action === 'resend_otp') {
                $countStmt = $pdo->prepare(
                    "SELECT COUNT(*) FROM registration_otps WHERE email = ? AND DATE(created_at) = CURDATE()"
                );
                $countStmt->execute([$email]);
                $todayCount = (int)$countStmt->fetchColumn();
                $resend_attempts_left = max(0, 4 - $todayCount);

                if ($resend_attempts_left <= 0) {
                    $message = 'You reached 3 resend attempts today. Please try again tomorrow.';
                    $message_type = 'error';
                } else {
                    try {
                        sendRegistrationOtpForEmail($pdo, $email);
                        $_SESSION['register_verify_email'] = $email;
                        $message = 'New OTP sent. Please verify your email.';
                        $message_type = 'success';
                        $resend_wait_seconds = 60;
                    } catch (Exception $e) {
                        $message = 'Unable to resend OTP right now. Please try again.';
                        $message_type = 'error';
                    }
                }
            } else {
                $otp = trim((string)($_POST['otp'] ?? ''));
                if (!preg_match('/^\d{6}$/', $otp)) {
                    $message = 'Enter a valid 6-digit OTP.';
                    $message_type = 'error';
                } else {
                    $stmt = $pdo->prepare(
                        "SELECT *
                         FROM registration_otps
                         WHERE email = ?
                           AND used_at IS NULL
                           AND expires_at > NOW()
                         ORDER BY id DESC
                         LIMIT 1"
                    );
                    $stmt->execute([$email]);
                    $row = $stmt->fetch(PDO::FETCH_ASSOC);

                    if (!$row || !hash_equals((string)$row['otp_hash'], hash('sha256', $otp))) {
                        $message = 'Invalid OTP or OTP expired.';
                        $message_type = 'error';
                    } else {
                        try {
                            $pdo->beginTransaction();

                            $markUsed = $pdo->prepare("UPDATE registration_otps SET used_at = NOW() WHERE id = ?");
                            $markUsed->execute([(int)$row['id']]);

                            $exists = $pdo->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
                            $exists->execute([$email]);
                            $existingUserId = (int)$exists->fetchColumn();

                            if ($existingUserId <= 0) {
                                $referrer = !empty($row['referrer_id']) ? (int)$row['referrer_id'] : null;
                                if (!empty($referrer)) {
                                    $chk = $pdo->prepare("SELECT id FROM users WHERE id = ? LIMIT 1");
                                    $chk->execute([$referrer]);
                                    if (!(int)$chk->fetchColumn()) {
                                        $referrer = null;
                                    }
                                }

                                $insUser = $pdo->prepare(
                                    "INSERT INTO users (username, email, password, referrer_id, user_code)
                                     VALUES (?, ?, ?, ?, ?)"
                                );
                                $insUser->execute([
                                    (string)$row['username'],
                                    $email,
                                    (string)$row['password_hash'],
                                    $referrer,
                                    (string)$row['user_code']
                                ]);
                                $newUserId = (int)$pdo->lastInsertId();

                                if (!empty($referrer)) {
                                    applyMilestoneBonus($pdo, $referrer);
                                    updateUserRank($pdo, $referrer);
                                }

                                // Optional notification to new user.
                                queueUserNotification(
                                    $pdo,
                                    $newUserId,
                                    'registration_success',
                                    'Welcome to Network Platform',
                                    'Your email is verified and registration is complete.'
                                );
                            }

                            $closeAll = $pdo->prepare(
                                "UPDATE registration_otps
                                 SET used_at = NOW()
                                 WHERE email = ? AND used_at IS NULL"
                            );
                            $closeAll->execute([$email]);

                            $pdo->commit();
                            unset($_SESSION['register_verify_email']);
                            $registration_done = true;
                            $message = 'Email verified successfully. Registration completed. You can now login.';
                            $message_type = 'success';
                        } catch (Exception $e) {
                            if ($pdo->inTransaction()) {
                                $pdo->rollBack();
                            }
                            $message = 'Unable to complete registration right now. Please try again.';
                            $message_type = 'error';
                        }
                    }
                }
            }
        }
    }
}

if ($email !== '' && !$registration_done) {
    $countStmt = $pdo->prepare(
        "SELECT COUNT(*) FROM registration_otps WHERE email = ? AND DATE(created_at) = CURDATE()"
    );
    $countStmt->execute([$email]);
    $todayCount = (int)$countStmt->fetchColumn();
    $resend_attempts_left = max(0, 4 - $todayCount);

    $tokStmt = $pdo->prepare(
        "SELECT expires_at
         FROM registration_otps
         WHERE email = ? AND used_at IS NULL
         ORDER BY id DESC
         LIMIT 1"
    );
    $tokStmt->execute([$email]);
    $expiresAt = $tokStmt->fetchColumn();
    if (!empty($expiresAt)) {
        $diff = strtotime((string)$expiresAt) - time();
        if ($diff > 0) {
            $resend_wait_seconds = min(60, (int)$diff);
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verify Registration | Network Platform</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="responsive.css">
    <style>
        :root { --primary: #1e3a8a; --secondary: #3b82f6; }
        body {
            font-family: 'Inter', system-ui, sans-serif;
            background: #f3f4f6;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            margin: 0;
            padding: 16px;
        }
        .card-box {
            width: 100%;
            max-width: 460px;
            background: #fff;
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
            padding: 30px;
            text-align: center;
        }
        .logo { font-size: 24px; font-weight: 800; color: var(--primary); text-decoration: none; display: inline-block; margin-bottom: 8px; }
        .logo span { color: var(--secondary); }
        .sub { color: #64748b; font-size: 14px; margin-bottom: 20px; }
        .alert-success, .alert-error {
            padding: 12px;
            border-radius: 10px;
            margin-bottom: 14px;
            font-size: 13px;
            font-weight: 600;
        }
        .alert-success { background: #dcfce7; color: #166534; border: 1px solid #86efac; }
        .alert-error { background: #fee2e2; color: #991b1b; border: 1px solid #fecaca; }
        .field { text-align: left; margin-bottom: 14px; }
        .field label { display: block; font-size: 13px; font-weight: 700; color: #475569; margin-bottom: 6px; }
        .field input {
            width: 100%;
            height: 48px;
            padding: 10px 12px;
            border: 1.5px solid #e2e8f0;
            border-radius: 12px;
            font-size: 15px;
            background: #f8fafc;
        }
        .field input:focus { outline: none; border-color: var(--secondary); background: #fff; box-shadow: 0 0 0 4px rgba(59, 130, 246, 0.12); }
        .submit-btn {
            width: 100%;
            border: none;
            border-radius: 12px;
            padding: 13px;
            background: var(--primary);
            color: #fff;
            font-weight: 700;
            font-size: 15px;
            cursor: pointer;
        }
        .resend-wrap { margin-top: 14px; }
        .resend-btn {
            width: 100%;
            border: 1px solid #cbd5e1;
            border-radius: 12px;
            padding: 11px;
            background: #94a3b8;
            color: #ffffff;
            font-weight: 700;
            font-size: 14px;
            cursor: pointer;
            transition: all 0.2s ease;
        }
        .resend-btn.active {
            background: linear-gradient(135deg, #2563eb, #1e40af);
            border-color: #1d4ed8;
            box-shadow: 0 8px 18px rgba(37, 99, 235, 0.28);
        }
        .resend-btn:disabled { opacity: 0.82; cursor: not-allowed; box-shadow: none; }
        .attempts-left {
            margin-top: 10px;
            font-size: 13px;
            color: #475569;
            font-weight: 600;
            text-align: center;
        }
        .links { margin-top: 16px; font-size: 14px; color: #64748b; }
        .links a { color: var(--secondary); text-decoration: none; font-weight: 600; }
    </style>
</head>
<body>
<div class="card-box">
    <a href="index.php" class="logo">NETWORK<span>PLATFORM</span></a>
    <div class="sub">Enter OTP sent to your email to complete registration.</div>

    <?php if ($message !== ''): ?>
        <div class="alert-<?php echo htmlspecialchars($message_type); ?>"><?php echo htmlspecialchars($message); ?></div>
    <?php endif; ?>

    <?php if (!$registration_done): ?>
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrfToken()); ?>">
            <input type="hidden" name="action" value="verify_otp">
            <div class="field">
                <label>Registered Email</label>
                <input type="email" name="email" value="<?php echo htmlspecialchars($email); ?>" required>
            </div>
            <div class="field">
                <label>OTP Code (6 digits)</label>
                <input type="text" name="otp" maxlength="6" pattern="\d{6}" placeholder="Enter OTP" required>
            </div>
            <button type="submit" class="submit-btn">Verify & Complete Registration</button>
        </form>

        <form method="POST" class="resend-wrap">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrfToken()); ?>">
            <input type="hidden" name="action" value="resend_otp">
            <input type="hidden" name="email" value="<?php echo htmlspecialchars($email); ?>">
            <button type="submit" id="resendBtn" class="resend-btn">Resend OTP</button>
            <div class="attempts-left" id="attemptsLeftText">
                You have <?php echo (int)$resend_attempts_left; ?> resend attempts left today.
            </div>
        </form>
    <?php endif; ?>

    <div class="links">
        Back to <a href="login.php">Login</a>
    </div>
</div>

<script>
    (function () {
        var seconds = <?php echo (int)$resend_wait_seconds; ?>;
        var attemptsLeft = <?php echo (int)$resend_attempts_left; ?>;
        var resendBtn = document.getElementById('resendBtn');
        var attemptsLeftText = document.getElementById('attemptsLeftText');
        if (!resendBtn) return;

        function render() {
            if (attemptsLeft <= 0) {
                resendBtn.disabled = true;
                resendBtn.classList.remove('active');
                resendBtn.textContent = 'Resend Limit Reached';
            } else if (seconds > 0) {
                resendBtn.disabled = true;
                resendBtn.classList.remove('active');
                resendBtn.textContent = 'Resend OTP (' + String(seconds) + 's)';
            } else {
                resendBtn.disabled = false;
                resendBtn.classList.add('active');
                resendBtn.textContent = 'Resend OTP';
            }
            if (attemptsLeftText) {
                attemptsLeftText.textContent = 'You have ' + String(attemptsLeft) + ' resend attempts left today.';
            }
        }

        render();
        if (seconds > 0) {
            var timer = setInterval(function () {
                seconds--;
                render();
                if (seconds <= 0) {
                    clearInterval(timer);
                }
            }, 1000);
        }
    })();
</script>
</body>
</html>
