<?php
session_start();
include 'db.php';
include 'functions.php';
ensurePasswordResetTokensTable($pdo);
ensureNotificationQueueTable($pdo);

$email = strtolower(trim((string)($_GET['email'] ?? $_POST['email'] ?? ($_SESSION['reset_email'] ?? ''))));
$message = '';
$message_type = '';
$resend_wait_seconds = 0;
$resend_attempts_left = 3;

$verified_email = (string)($_SESSION['pwd_reset_verified_email'] ?? '');
$verified_until = (int)($_SESSION['pwd_reset_verified_until'] ?? 0);
$otp_verified = ($verified_email !== '' && $verified_email === $email && $verified_until > time());

if (isset($_GET['sent']) && $_GET['sent'] === '1') {
    $message = 'OTP has been sent to your email.';
    $message_type = 'success';
}

function sendPasswordResetOtp($pdo, $user_id, $email, $ip) {
    $deactivate = $pdo->prepare(
        "UPDATE password_reset_tokens
         SET used_at = NOW()
         WHERE user_id = ? AND used_at IS NULL"
    );
    $deactivate->execute([(int)$user_id]);

    $otp = (string)random_int(100000, 999999);
    $otp_hash = hash('sha256', $otp);

    try {
        $ins = $pdo->prepare(
            "INSERT INTO password_reset_tokens (user_id, token_hash, requested_ip, expires_at, used_at, created_at)
             VALUES (?, ?, ?, DATE_ADD(NOW(), INTERVAL 60 SECOND), NULL, NOW())"
        );
        $ins->execute([(int)$user_id, $otp_hash, $ip]);
    } catch (Exception $e) {
        // Fallback for older schemas without requested_ip column.
        $ins = $pdo->prepare(
            "INSERT INTO password_reset_tokens (user_id, token_hash, expires_at, used_at, created_at)
             VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 60 SECOND), NULL, NOW())"
        );
        $ins->execute([(int)$user_id, $otp_hash]);
    }

    queueNotification(
        $pdo,
        'email',
        'password_reset_otp',
        'Your Password Reset OTP',
        "Your OTP code is {$otp}. It is valid for 60 seconds.",
        (int)$user_id,
        (string)$email,
        ['ip' => $ip]
    );
    // Try instant delivery so OTP arrives immediately.
    processNotificationQueue(
        $pdo,
        5,
        "channel = 'email' AND event_type = 'password_reset_otp' AND email_to = ?",
        [(string)$email]
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
            $message = 'Please enter a valid registered email.';
            $message_type = 'error';
        } else {
            $today = date('Y-m-d');
            if (($_SESSION['pwd_resend_date'] ?? '') !== $today) {
                $_SESSION['pwd_resend_date'] = $today;
                $_SESSION['pwd_resend_count'] = 0;
            }

            $user_stmt = $pdo->prepare("SELECT id, email FROM users WHERE email = ? LIMIT 1");
            $user_stmt->execute([$email]);
            $user = $user_stmt->fetch(PDO::FETCH_ASSOC);

            if (!$user) {
                $message = 'Email not registered.';
                $message_type = 'error';
                $otp_verified = false;
                unset($_SESSION['pwd_reset_verified_email'], $_SESSION['pwd_reset_verified_until']);
            } elseif ($action === 'resend_otp') {
                $dailyStmt = $pdo->prepare(
                    "SELECT COUNT(*)
                     FROM password_reset_tokens
                     WHERE user_id = ?
                       AND DATE(created_at) = CURDATE()"
                );
                $dailyStmt->execute([(int)$user['id']]);
                $todayOtpCount = (int)$dailyStmt->fetchColumn();
                // 1 initial send + max 3 resend = 4 total OTP issues per day.
                $remainingResends = max(0, 4 - $todayOtpCount);
                $resend_attempts_left = $remainingResends;
                if ($remainingResends <= 0) {
                    $message = 'You reached 3 resend attempts today. Please try again tomorrow.';
                    $message_type = 'error';
                } else {
                try {
                    sendPasswordResetOtp($pdo, (int)$user['id'], (string)$user['email'], getClientIpAddress());
                    $_SESSION['reset_email'] = (string)$user['email'];
                    $message = 'New OTP sent. It expires in 60 seconds.';
                    $message_type = 'success';
                    $otp_verified = false;
                    $resend_wait_seconds = 60;
                } catch (Exception $e) {
                    $message = 'Unable to resend OTP right now. Please try again.';
                    $message_type = 'error';
                }
                }
            } elseif ($action === 'verify_otp') {
                $otp = trim((string)($_POST['otp'] ?? ''));
                if (!preg_match('/^\d{6}$/', $otp)) {
                    $message = 'Enter a valid 6-digit OTP.';
                    $message_type = 'error';
                } else {
                    $otp_hash = hash('sha256', $otp);
                    $otp_stmt = $pdo->prepare(
                        "SELECT id
                         FROM password_reset_tokens
                         WHERE user_id = ?
                           AND token_hash = ?
                           AND used_at IS NULL
                           AND expires_at > NOW()
                         ORDER BY id DESC
                         LIMIT 1"
                    );
                    $otp_stmt->execute([(int)$user['id'], $otp_hash]);
                    $otp_row = $otp_stmt->fetch(PDO::FETCH_ASSOC);

                    if (!$otp_row) {
                        $message = 'Invalid OTP or OTP expired.';
                        $message_type = 'error';
                        $otp_verified = false;
                    } else {
                        $mark_used = $pdo->prepare("UPDATE password_reset_tokens SET used_at = NOW() WHERE id = ?");
                        $mark_used->execute([(int)$otp_row['id']]);

                        $_SESSION['pwd_reset_verified_email'] = $email;
                        $_SESSION['pwd_reset_verified_until'] = time() + 300;
                        $otp_verified = true;
                        $message = 'OTP verified. Now set your new password.';
                        $message_type = 'success';
                    }
                }
            } elseif ($action === 'save_password') {
                $session_verified_email = (string)($_SESSION['pwd_reset_verified_email'] ?? '');
                $session_verified_until = (int)($_SESSION['pwd_reset_verified_until'] ?? 0);
                if ($session_verified_email !== $email || $session_verified_until <= time()) {
                    $message = 'OTP verification expired. Please verify OTP again.';
                    $message_type = 'error';
                    $otp_verified = false;
                    unset($_SESSION['pwd_reset_verified_email'], $_SESSION['pwd_reset_verified_until']);
                } else {
                    $password = (string)($_POST['password'] ?? '');
                    $confirm = (string)($_POST['confirm_password'] ?? '');
                    if (!isStrongPassword($password)) {
                        $message = strongPasswordRuleText();
                        $message_type = 'error';
                    } elseif ($password !== $confirm) {
                        $message = 'Confirm password does not match.';
                        $message_type = 'error';
                    } else {
                        try {
                            $pdo->beginTransaction();

                            $update_user = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
                            $update_user->execute([password_hash($password, PASSWORD_DEFAULT), (int)$user['id']]);

                            $invalidate = $pdo->prepare(
                                "UPDATE password_reset_tokens
                                 SET used_at = NOW()
                                 WHERE user_id = ? AND used_at IS NULL"
                            );
                            $invalidate->execute([(int)$user['id']]);

                            $pdo->commit();
                            $message = 'Password reset successful. You can now login.';
                            $message_type = 'success';
                            $otp_verified = false;
                            unset($_SESSION['pwd_reset_verified_email'], $_SESSION['pwd_reset_verified_until'], $_SESSION['reset_email']);
                        } catch (Exception $e) {
                            if ($pdo->inTransaction()) {
                                $pdo->rollBack();
                            }
                            $message = 'Unable to reset password right now. Please try again.';
                            $message_type = 'error';
                        }
                    }
                }
            }
        }
    }
}

if ($email !== '' && !$otp_verified) {
    $user_stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
    $user_stmt->execute([$email]);
    $user_for_timer = $user_stmt->fetch(PDO::FETCH_ASSOC);
    if ($user_for_timer) {
        $count_stmt = $pdo->prepare(
            "SELECT COUNT(*)
             FROM password_reset_tokens
             WHERE user_id = ?
               AND DATE(created_at) = CURDATE()"
        );
        $count_stmt->execute([(int)$user_for_timer['id']]);
        $todayOtpCount = (int)$count_stmt->fetchColumn();
        $resend_attempts_left = max(0, 4 - $todayOtpCount);

        $tok_stmt = $pdo->prepare(
            "SELECT expires_at
             FROM password_reset_tokens
             WHERE user_id = ? AND used_at IS NULL
             ORDER BY id DESC
             LIMIT 1"
        );
        $tok_stmt->execute([(int)$user_for_timer['id']]);
        $expires_at = $tok_stmt->fetchColumn();
        if (!empty($expires_at)) {
            $diff = strtotime((string)$expires_at) - time();
            if ($diff > 0) {
                $resend_wait_seconds = max($resend_wait_seconds, min(60, (int)$diff));
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password | Network Platform</title>
    <style>
        :root { --primary: #1e3a8a; --secondary: #3b82f6; --bg: #f8fafc; }
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
        .links { margin-top: 16px; font-size: 14px; color: #64748b; }
        .links a { color: var(--secondary); text-decoration: none; font-weight: 600; }
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
        .resend-btn.active:hover {
            filter: brightness(1.03);
        }
        .resend-btn:disabled {
            opacity: 0.82;
            cursor: not-allowed;
            box-shadow: none;
        }
        .timer-text {
            margin-top: 10px;
            font-size: 13px;
            color: #64748b;
            text-align: center;
        }
        .attempts-left {
            margin-top: 10px;
            font-size: 13px;
            color: #475569;
            font-weight: 600;
            text-align: center;
        }
    </style>
    <link rel="stylesheet" href="responsive.css">
</head>
<body>
<div class="card-box">
    <a href="index.php" class="logo">NETWORK<span>PLATFORM</span></a>
    <div class="sub">Verify OTP, then set a new password.</div>

    <?php if ($message !== ''): ?>
        <div class="alert-<?php echo htmlspecialchars($message_type); ?>"><?php echo htmlspecialchars($message); ?></div>
    <?php endif; ?>

    <?php if (!$otp_verified): ?>
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
            <button type="submit" class="submit-btn">Verify OTP</button>
        </form>
        <form method="POST" class="resend-wrap" id="resendForm">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrfToken()); ?>">
            <input type="hidden" name="action" value="resend_otp">
            <input type="hidden" name="email" value="<?php echo htmlspecialchars($email); ?>">
            <button type="submit" id="resendBtn" class="resend-btn">Resend OTP</button>
            <div class="attempts-left" id="attemptsLeftText">
                You have <?php echo (int)$resend_attempts_left; ?> resend attempts left today.
            </div>
        </form>
    <?php else: ?>
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrfToken()); ?>">
            <input type="hidden" name="action" value="save_password">
            <input type="hidden" name="email" value="<?php echo htmlspecialchars($email); ?>">
            <div class="field">
                <label>New Password</label>
                <input type="password" name="password" minlength="8" pattern="(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[^A-Za-z0-9]).{8,}" title="At least 8 chars with uppercase, lowercase, number, and special character." required>
            </div>
            <div class="field">
                <label>Confirm Password</label>
                <input type="password" name="confirm_password" minlength="8" pattern="(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[^A-Za-z0-9]).{8,}" title="At least 8 chars with uppercase, lowercase, number, and special character." required>
            </div>
            <button type="submit" class="submit-btn">Save New Password</button>
        </form>
    <?php endif; ?>

    <div class="links">
        Back to <a href="login.php">Login</a>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="scroll_top.js"></script>
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
