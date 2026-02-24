<?php
session_start();
include 'db.php';
include 'functions.php';

$error_message = "";

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $error_message = "Invalid session token. Please refresh and try again.";
    } else {
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $mode = $_POST['mode']; 

    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
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
                    header("Location: admin_dashboard.php");
                    exit();
                } else {
                    $error_message = "ACCESS DENIED: You do not have Admin rights.";
                }
            } else {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['role'] = $user['role'];
                header("Location: dashboard.php");
                exit();
            }
        }
    } else {
        $error_message = "Invalid email or password.";
    }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
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

        .input-group { text-align: left; margin-bottom: 15px; position: relative; }
        .input-group label { display: block; font-size: 13px; font-weight: 600; color: #475569; margin-bottom: 6px; }

        input[type="email"], input[type="password"], input[type="text"] { 
            width: 100%; padding: 14px; border: 1.5px solid #e2e8f0; 
            border-radius: 12px; box-sizing: border-box; font-size: 16px; 
            transition: 0.3s; background: #f8fafc;
        }
        input:focus { outline: none; border-color: var(--secondary); background: white; box-shadow: 0 0 0 4px rgba(59, 130, 246, 0.1); }

        /* Eye Button Styling */
        .password-wrapper { position: relative; }
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
            <input type="email" name="email" placeholder="Enter your email" required>
        </div>

        <div class="input-group">
            <label>Password</label>
            <div class="password-wrapper">
                <input type="password" name="password" id="password" placeholder="••••••••" required>
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

</body>
</html>
