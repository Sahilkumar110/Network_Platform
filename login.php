<?php
session_start();
include 'db.php';
include 'functions.php';
ensureLoginAttemptsTable($pdo);
ensureNotificationQueueTable($pdo);

$error_message = "";
$lockout_max_attempts = 5;
$lockout_window_minutes = 15;
$lockout_minutes = 15;

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $error_message = "Invalid session token. Please refresh and try again.";
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

    <form method="POST">
        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrfToken()); ?>">
        <div class="mode-selector">
            <label>
                <input type="radio" name="mode" value="user" checked>
                <span>Member Login</span>
            </label>
            <label>
                <input type="radio" name="mode" value="admin">
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
        
        <button type="submit" class="submit-btn">Sign In to Dashboard</button>
    </form>

    <div class="footer-text">
        Don't have an account? <a href="register.php">Create one</a>
    </div>
</div>

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
</script>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="scroll_top.js"></script>
</body>
</html>
