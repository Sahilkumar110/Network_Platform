<?php
session_start();
include 'db.php';
include 'functions.php';
ensureLoginAttemptsTable($pdo);
ensureNotificationQueueTable($pdo);
ensureGoogleAuthColumns($pdo);

$error_message = "";
$lockout_max_attempts = 5;
$lockout_window_minutes = 15;
$lockout_minutes = 15;
$google_client_id = trim((string)envValue('GOOGLE_CLIENT_ID', ''));

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $error_message = "Invalid session token. Please refresh and try again.";
    } else {
    $action = trim((string)($_POST['action'] ?? 'password_login'));
    if ($action === 'google_login') {
    $mode = trim((string)($_POST['mode'] ?? 'user'));
    if ($mode === 'admin') {
        $error_message = "Google login is available for Member Login only.";
    } elseif ($google_client_id === '') {
        $error_message = "Google login is not configured. Please contact admin.";
    } else {
        $verify = verifyGoogleIdToken((string)($_POST['google_id_token'] ?? ''), $google_client_id);
        if (empty($verify['ok'])) {
            $error_message = "Google login failed. Please try again.";
        } else {
            $email = (string)$verify['email'];
            $google_sub = (string)$verify['sub'];
            $display_name = trim((string)$verify['name']);

            $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? LIMIT 1");
            $stmt->execute([$email]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$user) {
                $base_username = $display_name !== '' ? $display_name : strstr($email, '@', true);
                $base_username = trim(preg_replace('/\s+/', ' ', (string)$base_username));
                if ($base_username === '') {
                    $base_username = 'GoogleUser';
                }

                $temp_password_hash = password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT);
                $user_code = generateUniqueUserCode($pdo);
                $ins = $pdo->prepare(
                    "INSERT INTO users (username, email, google_sub, password, referrer_id, user_code)
                     VALUES (?, ?, ?, ?, NULL, ?)"
                );
                $ins->execute([$base_username, $email, $google_sub, $temp_password_hash, $user_code]);

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
                $error_message = "Your account has been suspended. Please contact support.";
            } elseif ($user) {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['role'] = $user['role'];
                session_regenerate_id(true);
                header("Location: dashboard.php");
                exit();
            } else {
                $error_message = "Google login failed. Please try again.";
            }
        }
    }
    } else {
    $email = strtolower(trim($_POST['email']));
    $password = $_POST['password'];
    $mode = $_POST['mode']; 
    $client_ip = getClientIpAddress();

    $lock = getLoginLockStatus(
        $pdo,
        $email,
        $client_ip,
        $lockout_max_attempts,
        $lockout_window_minutes,
        $lockout_minutes
    );
    if (!empty($lock['locked'])) {
        $minutes_left = (int)ceil(((int)$lock['seconds_left']) / 60);
        $error_message = "Too many failed attempts. Try again in {$minutes_left} minute(s).";
        queueNotification(
            $pdo,
            'email',
            'security_alert',
            'Security Alert: Login Lockout',
            "A login lockout was triggered for email {$email} from IP {$client_ip}.",
            null,
            $email,
            ['ip' => $client_ip, 'minutes_left' => $minutes_left]
        );
    } else {

    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? LIMIT 1");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password'])) {
        if (isset($user['status']) && $user['status'] === 'banned') {
            $error_message = "Your account has been suspended. Please contact support.";
        } 
        else {
            if ($mode === 'admin') {
                if ($user['role'] === 'admin') {
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['role'] = 'admin';
                    session_regenerate_id(true);
                    recordLoginAttempt($pdo, $email, $client_ip, true);
                    header("Location: admin_dashboard.php");
                    exit();
                } else {
                    recordLoginAttempt($pdo, $email, $client_ip, false);
                    $error_message = "ACCESS DENIED: You do not have Admin rights.";
                }
            } else {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['role'] = $user['role'];
                session_regenerate_id(true);
                recordLoginAttempt($pdo, $email, $client_ip, true);
                header("Location: dashboard.php");
                exit();
            }
        }
    } else {
        recordLoginAttempt($pdo, $email, $client_ip, false);
        $error_message = "Invalid email or password.";
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
    <title>Login | Network Platform</title>
    <script src="https://unpkg.com/lucide@latest"></script>
<style>
        :root { --primary: #1e3a8a; --secondary: #3b82f6; --bg: #f8fafc; }
        
        body { 
            font-family: 'Inter', system-ui, sans-serif; 
            background: #f3f4f6; 
            display: flex; justify-content: center; align-items: center; 
            height: 100vh; margin: 0; 
        }

        .login-card { 
            background: white; padding: 40px; border-radius: 24px; 
            box-shadow: 0 20px 40px rgba(0,0,0,0.1); width: 100%; max-width: 400px; 
            text-align: center; 
        }

        /* Clickable Logo */
        .logo { font-size: 24px; font-weight: 800; color: var(--primary); margin-bottom: 10px; text-decoration: none; display: inline-block; }
        .logo span { color: var(--secondary); }
        .logo:hover { opacity: 0.8; }
        
        .subtitle { color: #64748b; font-size: 14px; margin-bottom: 30px; }

        .error-popup { 
            background: #fee2e2; color: #dc2626; padding: 12px; border-radius: 12px; 
            margin-bottom: 20px; border: 1px solid #fecaca; font-size: 13px; font-weight: 500;
        }

        .mode-selector { 
            display: flex; background: #f1f5f9; border-radius: 14px; 
            margin-bottom: 25px; padding: 6px; 
        }
        .mode-selector label { flex: 1; cursor: pointer; }
        .mode-selector input { display: none; }
        .mode-selector span { 
            display: block; padding: 10px; font-size: 14px; font-weight: 600; 
            color: #64748b; transition: 0.3s; border-radius: 10px;
        }
        .mode-selector input:checked + span { background: white; color: var(--primary); box-shadow: 0 2px 4px rgba(0,0,0,0.05); }

        .input-group { text-align: left; margin-bottom: 15px; display: block; }
        .input-group label { display: block; font-size: 13px; font-weight: 600; color: #475569; margin-bottom: 6px; }

        .input-control { 
            width: 100%;
            height: 50px;
            padding: 12px 14px;
            border: 1.5px solid #e2e8f0;
            border-radius: 12px;
            box-sizing: border-box;
            font-size: 16px;
            transition: 0.3s;
            background: #f8fafc;
        }
        .input-control:focus { outline: none; border-color: var(--secondary); background: white; box-shadow: 0 0 0 4px rgba(59, 130, 246, 0.1); }
        .input-group > .input-control {
            border-radius: 12px !important;
        }

        /* Eye Button Styling */
        .password-wrapper { position: relative; }
        .password-wrapper .input-control { padding-right: 46px; }
        .toggle-password {
            position: absolute; right: 14px; top: 50%; transform: translateY(-50%);
            background: none; border: none; color: #94a3b8; cursor: pointer;
            padding: 0; display: flex; align-items: center;
        }
        .toggle-password:hover { color: var(--secondary); }

        button.submit-btn { 
            width: 100%; padding: 14px; background: var(--primary); color: white; 
            border: none; border-radius: 12px; cursor: pointer; font-weight: 700; 
            font-size: 16px; margin-top: 10px; transition: 0.3s;
        }
        button.submit-btn:hover { background: #1e40af; transform: translateY(-1px); }

        .footer-text { margin-top: 25px; font-size: 14px; color: #64748b; }
        .footer-text a { color: var(--secondary); text-decoration: none; font-weight: 600; }
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
        .google-login-wrap {
            display: flex;
            justify-content: center;
            margin-top: 10px;
        }
        .forgot-link {
            display: block;
            text-align: right;
            margin-top: -6px;
            margin-bottom: 12px;
            font-size: 13px;
        }
        .forgot-link a {
            color: var(--secondary);
            text-decoration: none;
            font-weight: 600;
        }
    </style>
    <link rel="stylesheet" href="responsive.css">
</head>
<body>

<div class="login-card">
    <a href="index.php" class="logo">NETWORK<span>PLATFORM</span></a>
    <p class="subtitle">Secure Access to Your Portfolio</p>
    
    <?php if ($error_message): ?>
        <div class="error-popup"><?php echo $error_message; ?></div>
    <?php endif; ?>

    <form method="POST" id="passwordLoginForm">
        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrfToken()); ?>">
        <input type="hidden" name="action" value="password_login">
        <div class="mode-selector">
            <label>
                <input type="radio" name="mode" value="user" checked id="modeUser">
                <span>Member Login</span>
            </label>
            <label>
                <input type="radio" name="mode" value="admin" id="modeAdmin">
                <span>Admin Panel</span>
            </label>
        </div>

        <div class="input-group">
            <label>Email Address</label>
            <input type="email" name="email" class="input-control" placeholder="Enter your email" required>
        </div>

        <div class="input-group">
            <label>Password</label>
            <div class="password-wrapper">
                <input type="password" name="password" id="password" class="input-control" placeholder="Enter your password" required>
                <button type="button" class="toggle-password" onclick="togglePassword()">
                    <i id="eye-icon" data-lucide="eye"></i>
                </button>
            </div>
        </div>
        <div class="forgot-link">
            <a href="forgot_password.php">Forgot Password?</a>
        </div>
        
        <button type="submit" class="submit-btn">Sign In to Dashboard</button>
    </form>
    <?php if ($google_client_id !== ''): ?>
    <div class="auth-divider">OR</div>
    <form method="POST" id="googleLoginForm">
        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrfToken()); ?>">
        <input type="hidden" name="action" value="google_login">
        <input type="hidden" name="mode" id="googleModeField" value="user">
        <input type="hidden" name="google_id_token" id="googleIdTokenField" value="">
        <div class="google-login-wrap" id="googleButton"></div>
    </form>
    <?php endif; ?>

    <div class="footer-text">
        Don't have an account? <a href="register.php">Create one</a>
    </div>
</div>

<?php if ($google_client_id !== ''): ?>
<script src="https://accounts.google.com/gsi/client" async defer></script>
<?php endif; ?>
<script>
    // Initialize Lucide Icons
    lucide.createIcons();

    function togglePassword() {
        const passwordInput = document.getElementById('password');
        const eyeIcon = document.getElementById('eye-icon');
        
        if (passwordInput.type === 'password') {
            passwordInput.type = 'text';
            // Change icon to eye-off
            eyeIcon.setAttribute('data-lucide', 'eye-off');
        } else {
            passwordInput.type = 'password';
            // Change icon to eye
            eyeIcon.setAttribute('data-lucide', 'eye');
        }
        // Refresh icons
        lucide.createIcons();
    }

    (function () {
        var modeUser = document.getElementById('modeUser');
        var modeAdmin = document.getElementById('modeAdmin');
        var googleModeField = document.getElementById('googleModeField');
        var googleWrap = document.getElementById('googleButton');
        if (!googleModeField || !modeUser || !modeAdmin || !googleWrap) return;

        function syncMode() {
            googleModeField.value = modeAdmin.checked ? 'admin' : 'user';
            googleWrap.style.display = modeAdmin.checked ? 'none' : 'flex';
        }

        modeUser.addEventListener('change', syncMode);
        modeAdmin.addEventListener('change', syncMode);
        syncMode();
    })();
</script>
<?php if ($google_client_id !== ''): ?>
<script>
    function handleGoogleCredentialResponse(response) {
        if (!response || !response.credential) {
            return;
        }
        var tokenField = document.getElementById('googleIdTokenField');
        var form = document.getElementById('googleLoginForm');
        if (!tokenField || !form) {
            return;
        }
        tokenField.value = response.credential;
        form.submit();
    }

    window.onload = function () {
        if (!window.google || !google.accounts || !google.accounts.id) {
            return;
        }
        google.accounts.id.initialize({
            client_id: "<?php echo htmlspecialchars($google_client_id, ENT_QUOTES); ?>",
            callback: handleGoogleCredentialResponse
        });
        google.accounts.id.renderButton(
            document.getElementById("googleButton"),
            { theme: "outline", size: "large", width: 320, text: "signin_with" }
        );
    };
</script>
<?php endif; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="scroll_top.js"></script>
</body>
</html>
