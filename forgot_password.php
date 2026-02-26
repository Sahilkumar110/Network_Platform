<?php
session_start();
include 'db.php';
include 'functions.php';
ensureNotificationQueueTable($pdo);
ensurePasswordResetTokensTable($pdo);

$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $message = 'Invalid session token. Please refresh and try again.';
        $message_type = 'error';
    } else {
        $email = strtolower(trim((string)($_POST['email'] ?? '')));
        $ip = getClientIpAddress();

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $message = 'Please enter a valid email address.';
            $message_type = 'error';
        } else {
            $stmt = $pdo->prepare("SELECT id, email FROM users WHERE email = ? LIMIT 1");
            $stmt->execute([$email]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$user) {
                $message = 'This email is not registered.';
                $message_type = 'error';
            } else {
                try {
                    $dailyStmt = $pdo->prepare(
                        "SELECT COUNT(*)
                         FROM password_reset_tokens
                         WHERE user_id = ?
                           AND DATE(created_at) = CURDATE()"
                    );
                    $dailyStmt->execute([(int)$user['id']]);
                    $todayOtpCount = (int)$dailyStmt->fetchColumn();
                    if ($todayOtpCount >= 4) {
                        $message = 'You reached today\'s OTP limit. Please try again tomorrow.';
                        $message_type = 'error';
                    } else {
                    $deactivate = $pdo->prepare(
                        "UPDATE password_reset_tokens
                         SET used_at = NOW()
                         WHERE user_id = ? AND used_at IS NULL"
                    );
                    $deactivate->execute([(int)$user['id']]);

                    $otp = (string)random_int(100000, 999999);
                    $otp_hash = hash('sha256', $otp);

                    $ins = $pdo->prepare(
                        "INSERT INTO password_reset_tokens (user_id, token_hash, requested_ip, expires_at, used_at, created_at)
                         VALUES (?, ?, ?, DATE_ADD(NOW(), INTERVAL 60 SECOND), NULL, NOW())"
                    );
                    $ins->execute([(int)$user['id'], $otp_hash, $ip]);

                    queueNotification(
                        $pdo,
                        'email',
                        'password_reset_otp',
                        'Your Password Reset OTP',
                        "Your OTP code is {$otp}. It is valid for 60 seconds.",
                        (int)$user['id'],
                        (string)$user['email'],
                        ['ip' => $ip]
                    );
                    // Try instant delivery so user doesn't have to wait for cron.
                    processNotificationQueue(
                        $pdo,
                        5,
                        "channel = 'email' AND event_type = 'password_reset_otp' AND email_to = ?",
                        [(string)$user['email']]
                    );

                    $message = 'OTP sent to your registered email. Enter OTP within 60 seconds.';
                    $message_type = 'success';
                    $_SESSION['reset_email'] = (string)$user['email'];
                    $_SESSION['pwd_resend_date'] = date('Y-m-d');
                    $_SESSION['pwd_resend_count'] = 0;
                    header("Location: reset_password.php?email=" . rawurlencode((string)$user['email']) . "&sent=1");
                    exit();
                    }
                } catch (Exception $e) {
                    $message = 'Unable to send OTP right now. Please try again.';
                    $message_type = 'error';
                }
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
    <title>Forgot Password | Network Platform</title>
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
            max-width: 420px;
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
    </style>
    <link rel="stylesheet" href="responsive.css">
</head>
<body>
<div class="card-box">
    <a href="index.php" class="logo">NETWORK<span>PLATFORM</span></a>
    <div class="sub">Enter your registered email to receive OTP code.</div>

    <?php if ($message !== ''): ?>
        <div class="alert-<?php echo htmlspecialchars($message_type); ?>"><?php echo htmlspecialchars($message); ?></div>
    <?php endif; ?>

    <form method="POST">
        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrfToken()); ?>">
        <div class="field">
            <label>Email Address</label>
            <input type="email" name="email" placeholder="name@example.com" required>
        </div>
        <button type="submit" class="submit-btn">Send OTP</button>
    </form>

    <div class="links">
        Remember your password? <a href="login.php">Back to Login</a>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="scroll_top.js"></script>
</body>
</html>
