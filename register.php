<?php
session_start();
include 'db.php'; 
include 'functions.php'; 

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

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $error = "Invalid session token. Please refresh and try again.";
    } else {
    $username = trim($_POST['username']);
    $email = filter_var($_POST['email'], FILTER_SANITIZE_EMAIL);
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
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
    } else {
        $user_code = generateUniqueUserCode($pdo);
        $sql = "INSERT INTO users (username, email, password, referrer_id, user_code) VALUES (?, ?, ?, ?, ?)";
        $stmt = $pdo->prepare($sql);
        
        if ($stmt->execute([$username, $email, $password, $referrer, $user_code])) {
            if (!empty($referrer)) {
                applyMilestoneBonus($pdo, $referrer);
                updateUserRank($pdo, $referrer);
            }
            $success = "Registration successful! <a href='login.php'>Login here</a>";
        } else {
            $error = "Something went wrong. Please try again.";
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
                <input type="password" name="password" id="password" placeholder="••••••••" required>
                <button type="button" class="toggle-password" onclick="togglePassword()">
                    <i id="eye-icon" data-lucide="eye"></i>
                </button>
            </div>
        </div>

        <input type="hidden" name="referrer_id" value="<?php echo $ref_id; ?>">
        
        <button type="submit" class="btn-register">Create Account</button>
    </form>

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

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
