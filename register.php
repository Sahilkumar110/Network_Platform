<?php
session_start();
include 'db.php'; 
include 'functions.php'; 
ensureWalletLedgerTable($pdo);
ensureNotificationQueueTable($pdo);
ensureRegistrationOtpsTable($pdo);
ensureGoogleAuthColumns($pdo);

$ref_input = trim((string)($_GET['ref'] ?? ''));
$ref_id = null;
$ref_code = null;
if ($ref_input !== '') {
    if (ctype_digit($ref_input)) {
        $candidate_id = (int)$ref_input;
        if ($candidate_id > 0) {
            $check_ref = $pdo->prepare("SELECT id, user_code FROM users WHERE id = ? LIMIT 1");
            $check_ref->execute([$candidate_id]);
            $ref_user = $check_ref->fetch(PDO::FETCH_ASSOC);
            if ($ref_user) {
                $ref_id = (int)$ref_user['id'];
                $ref_code = (string)$ref_user['user_code'];
            }
        }
    } else {
        $check_ref = $pdo->prepare("SELECT id, user_code FROM users WHERE user_code = ? LIMIT 1");
        $check_ref->execute([$ref_input]);
        $ref_user = $check_ref->fetch(PDO::FETCH_ASSOC);
        if ($ref_user) {
            $ref_id = (int)$ref_user['id'];
            $ref_code = (string)$ref_user['user_code'];
        }
    }
}
$error = "";
$success = "";
$google_client_id = trim((string)envValue('GOOGLE_CLIENT_ID', ''));

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $error = "Invalid session token. Please refresh and try again.";
    } else {
        $action = trim((string)($_POST['action'] ?? 'password_register'));
        if ($action === 'google_signup') {
            if ($google_client_id === '') {
                $error = "Google sign up is not configured. Please contact admin.";
            } else {
                $verify = verifyGoogleIdToken((string)($_POST['google_id_token'] ?? ''), $google_client_id);
                if (empty($verify['ok'])) {
                    $error = "Google sign up failed. Please try again.";
                } else {
                    $email = strtolower(trim((string)$verify['email']));
                    $google_sub = (string)$verify['sub'];
                    $display_name = trim((string)$verify['name']);
                    $referrer = !empty($_POST['google_referrer_id']) ? (int)$_POST['google_referrer_id'] : null;
                    if (!empty($referrer)) {
                        $check_ref = $pdo->prepare("SELECT id FROM users WHERE id = ? LIMIT 1");
                        $check_ref->execute([$referrer]);
                        if (!$check_ref->fetchColumn()) {
                            $referrer = null;
                        }
                    }

                    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? LIMIT 1");
                    $stmt->execute([$email]);
                    $user = $stmt->fetch(PDO::FETCH_ASSOC);

                    if (!$user) {
                        $base_username = $display_name !== '' ? $display_name : strstr($email, '@', true);
                        $base_username = trim(preg_replace('/\s+/', ' ', (string)$base_username));
                        if ($base_username === '') {
                            $base_username = 'GoogleUser';
                        }
                        $base_username = preg_replace('/[^a-zA-Z0-9 _.-]/', '', $base_username);
                        if ($base_username === '') {
                            $base_username = 'GoogleUser';
                        }
                        $username = $base_username . random_int(100, 999);

                        $temp_password_hash = password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT);
                        $user_code = generateUniqueUserCode($pdo);
                        $ins = $pdo->prepare(
                            "INSERT INTO users (username, email, google_sub, password, referrer_id, user_code)
                             VALUES (?, ?, ?, ?, ?, ?)"
                        );
                        $ins->execute([$username, $email, $google_sub, $temp_password_hash, $referrer ?: null, $user_code]);

                        $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ? LIMIT 1");
                        $stmt->execute([(int)$pdo->lastInsertId()]);
                        $user = $stmt->fetch(PDO::FETCH_ASSOC);
                    } else {
                        if (isset($user['google_sub']) && ((string)$user['google_sub'] === '' || $user['google_sub'] === null)) {
                            $up = $pdo->prepare("UPDATE users SET google_sub = ? WHERE id = ?");
                            $up->execute([$google_sub, (int)$user['id']]);
                            $user['google_sub'] = $google_sub;
                        }
                    }

                    if ($user && isset($user['status']) && $user['status'] === 'banned') {
                        $error = "Your account has been suspended. Please contact support.";
                    } elseif ($user) {
                        $_SESSION['user_id'] = $user['id'];
                        $_SESSION['role'] = $user['role'];
                        session_regenerate_id(true);
                        header("Location: dashboard.php");
                        exit();
                    } else {
                        $error = "Google sign up failed. Please try again.";
                    }
                }
            }
        } else {
            $username = trim($_POST['username']);
            $email = filter_var($_POST['email'], FILTER_SANITIZE_EMAIL);
            $raw_password = (string)($_POST['password'] ?? '');
            $referrer = !empty($_POST['referrer_id']) ? (int)$_POST['referrer_id'] : null;
            if (!empty($referrer)) {
                $check_ref = $pdo->prepare("SELECT id FROM users WHERE id = ? LIMIT 1");
                $check_ref->execute([$referrer]);
                if (!$check_ref->fetchColumn()) {
                    $referrer = null;
                }
            }

            $checkEmail = $pdo->prepare("SELECT id FROM users WHERE email = ?");
            $checkEmail->execute([$email]);

            if ($checkEmail->rowCount() > 0) {
                $error = "Email already registered!";
            } elseif (!isStrongPassword($raw_password)) {
                $error = strongPasswordRuleText();
            } else {
                try {
                    $password = password_hash($raw_password, PASSWORD_DEFAULT);
                    $user_code = generateUniqueUserCode($pdo);
                    $otp = (string)random_int(100000, 999999);
                    $otp_hash = hash('sha256', $otp);
                    $ip = getClientIpAddress();

                    $deactivate = $pdo->prepare(
                        "UPDATE registration_otps
                         SET used_at = NOW()
                         WHERE email = ? AND used_at IS NULL"
                    );
                    $deactivate->execute([strtolower(trim((string)$email))]);

                    $ins = $pdo->prepare(
                        "INSERT INTO registration_otps
                         (username, email, password_hash, referrer_id, user_code, otp_hash, requested_ip, expires_at, used_at, created_at)
                         VALUES (?, ?, ?, ?, ?, ?, ?, DATE_ADD(NOW(), INTERVAL 60 SECOND), NULL, NOW())"
                    );
                    $ins->execute([
                        $username,
                        strtolower(trim((string)$email)),
                        $password,
                        $referrer ?: null,
                        $user_code,
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
                        strtolower(trim((string)$email)),
                        ['ip' => $ip]
                    );

                    processNotificationQueue(
                        $pdo,
                        5,
                        "channel = 'email' AND event_type = 'registration_otp' AND email_to = ?",
                        [strtolower(trim((string)$email))]
                    );

                    $_SESSION['register_verify_email'] = strtolower(trim((string)$email));
                    header("Location: verify_registration.php?email=" . rawurlencode(strtolower(trim((string)$email))));
                    exit();
                } catch (Exception $e) {
                    $error = "Unable to send OTP right now. Please try again.";
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
    <title>Join Network Platform | Create Account</title>
    <script src="https://unpkg.com/lucide@latest"></script>
<style>
        :root { --primary: #1e3a8a; --secondary: #3b82f6; --bg: #f8fafc; }
        body { font-family: 'Inter', 'Segoe UI', sans-serif; background: #f3f4f6; display: flex; align-items: center; justify-content: center; min-height: 100vh; margin: 0; }
        
        .register-card { 
            background: white; padding: 40px; border-radius: 24px; 
            box-shadow: 0 20px 40px rgba(0,0,0,0.1); width: 100%; max-width: 400px; 
        }

        /* Clickable Logo */
        .logo { text-align: center; font-weight: 800; font-size: 24px; color: var(--primary); margin-bottom: 30px; text-decoration: none; display: block; }
        .logo span { color: var(--secondary); }
        .logo:hover { opacity: 0.8; }

        .form-group { margin-bottom: 20px; text-align: left; }
        label { display: block; margin-bottom: 8px; font-weight: 600; font-size: 13px; color: #475569; }
        
        input { 
            width: 100%; padding: 12px; border: 1.5px solid #e2e8f0; border-radius: 12px; 
            box-sizing: border-box; font-size: 16px; transition: 0.3s; background: #f8fafc;
        }
        input:focus { outline: none; border-color: var(--secondary); background: white; box-shadow: 0 0 0 4px rgba(59, 130, 246, 0.1); }
        
        /* Password Wrapper for Eye button */
        .password-wrapper { position: relative; }
        .toggle-password {
            position: absolute; right: 14px; top: 50%; transform: translateY(-50%);
            background: none; border: none; color: #94a3b8; cursor: pointer;
            padding: 0; display: flex; align-items: center;
        }
        .toggle-password:hover { color: var(--secondary); }

        .btn-register { 
            width: 100%; padding: 14px; background: var(--primary); color: white; 
            border: none; border-radius: 12px; font-weight: 700; font-size: 16px; 
            cursor: pointer; transition: 0.3s; margin-top: 10px;
        }
        .btn-register:hover { background: #1e40af; transform: translateY(-1px); box-shadow: 0 10px 15px -3px rgba(30, 58, 138, 0.3); }

        .alert { padding: 12px; border-radius: 12px; margin-bottom: 20px; font-size: 14px; text-align: center; }
        .alert-error { background: #fee2e2; color: #dc2626; border: 1px solid #fecaca; }
        .alert-success { background: #dcfce7; color: #16a34a; border: 1px solid #bbf7d0; }
        
        .footer-link { text-align: center; margin-top: 25px; font-size: 14px; color: #64748b; }
        .footer-link a { color: var(--secondary); text-decoration: none; font-weight: 600; }
        .auth-divider {
            margin: 16px 0;
            display: flex;
            align-items: center;
            gap: 10px;
            color: #94a3b8;
            font-size: 12px;
            font-weight: 600;
        }
        .auth-divider::before, .auth-divider::after {
            content: "";
            height: 1px;
            background: #e2e8f0;
            flex: 1;
        }
        .google-signup-wrap {
            display: flex;
            justify-content: center;
            margin-top: 10px;
        }
        
        .ref-badge { 
            background: #f1f5f9; padding: 10px; border-radius: 12px; 
            font-size: 12px; color: #64748b; text-align: center; margin-bottom: 25px;
            border: 1px dashed #cbd5e1;
        }
    </style>
    <link rel="stylesheet" href="responsive.css">
</head>
<body>

<div class="register-card">
    <a href="index.php" class="logo">NETWORK<span>PLATFORM</span></a>
    
    <?php if($error): ?> <div class="alert alert-error"><?php echo $error; ?></div> <?php endif; ?>
    <?php if($success): ?> <div class="alert alert-success"><?php echo $success; ?></div> <?php endif; ?>

    <?php if($ref_id): ?>
        <div class="ref-badge">
            🔗 You were invited by <?php echo htmlspecialchars($ref_code ?: ("User #" . $ref_id)); ?>
        </div>
    <?php endif; ?>

    <form method="POST">
        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrfToken()); ?>">
        <div class="form-group">
            <label>Username</label>
            <input type="text" name="username" placeholder="johndoe123" required>
        </div>
        
        <div class="form-group">
            <label>Email Address</label>
            <input type="email" name="email" placeholder="name@example.com" required>
        </div>
        
        <div class="form-group">
            <label>Password</label>
            <div class="password-wrapper">
                <input type="password" name="password" id="password" placeholder="Enter strong password" minlength="8" pattern="(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[^A-Za-z0-9]).{8,}" title="At least 8 chars with uppercase, lowercase, number, and special character." required>
                <button type="button" class="toggle-password" onclick="togglePassword()">
                    <i id="eye-icon" data-lucide="eye"></i>
                </button>
            </div>
            <small style="display:block;margin-top:8px;color:#64748b;font-size:12px;">At least 8 chars, 1 uppercase, 1 lowercase, 1 number, 1 special character.</small>
        </div>

        <input type="hidden" name="referrer_id" value="<?php echo $ref_id; ?>">
        
        <button type="submit" class="btn-register">Create Account</button>
    </form>

    <?php if ($google_client_id !== ''): ?>
    <div class="auth-divider">or</div>
    <form method="POST" id="googleSignupForm">
        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrfToken()); ?>">
        <input type="hidden" name="action" value="google_signup">
        <input type="hidden" name="google_id_token" id="googleSignupTokenField" value="">
        <input type="hidden" name="google_referrer_id" value="<?php echo (int)$ref_id; ?>">
        <div class="google-signup-wrap" id="googleSignupButton"></div>
    </form>
    <?php endif; ?>

    <div class="footer-link">
        Already have an account? <a href="login.php">Log In</a>
    </div>
</div>

<script>
    // Initialize Icons
    lucide.createIcons();

    function togglePassword() {
        const passwordInput = document.getElementById('password');
        const eyeIcon = document.getElementById('eye-icon');
        
        if (passwordInput.type === 'password') {
            passwordInput.type = 'text';
            eyeIcon.setAttribute('data-lucide', 'eye-off');
        } else {
            passwordInput.type = 'password';
            eyeIcon.setAttribute('data-lucide', 'eye');
        }
        // Refresh icons
        lucide.createIcons();
    }
</script>
<?php if ($google_client_id !== ''): ?>
<script src="https://accounts.google.com/gsi/client" async defer></script>
<script>
    function handleGoogleSignupCredential(response) {
        if (!response || !response.credential) {
            return;
        }
        var tokenField = document.getElementById('googleSignupTokenField');
        var form = document.getElementById('googleSignupForm');
        if (!tokenField || !form) {
            return;
        }
        tokenField.value = response.credential;
        form.submit();
    }

    window.addEventListener('load', function () {
        if (!window.google || !google.accounts || !google.accounts.id) {
            return;
        }
        google.accounts.id.initialize({
            client_id: "<?php echo htmlspecialchars($google_client_id, ENT_QUOTES); ?>",
            callback: handleGoogleSignupCredential
        });
        google.accounts.id.renderButton(
            document.getElementById("googleSignupButton"),
            { theme: "outline", size: "large", shape: "pill", text: "signup_with", width: 320 }
        );
    });
</script>
<?php endif; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="scroll_top.js"></script>
</body>
</html>

