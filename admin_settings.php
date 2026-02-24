<?php
session_start();
include 'db.php';

if ($_SESSION['role'] !== 'admin') { die("Unauthorized"); }

// 1. Handle Form Submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    foreach ($_POST['settings'] as $key => $value) {
        $stmt = $pdo->prepare("UPDATE settings SET setting_value = ? WHERE setting_key = ?");
        $stmt->execute([$value, $key]);
    }
    $success = "Settings updated successfully!";
}

// 2. Fetch All Settings
$stmt = $pdo->query("SELECT * FROM settings");
$settings = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
?>

<!DOCTYPE html>
<html>
<head>
    <title>Platform Settings</title>
    <style>
        body { font-family: sans-serif; background: #f4f7f6; padding: 40px; }
        .settings-card { background: white; padding: 30px; border-radius: 12px; box-shadow: 0 4px 10px rgba(0,0,0,0.1); max-width: 500px; margin: 0 auto; }
        .form-group { margin-bottom: 20px; }
        label { display: block; font-weight: bold; margin-bottom: 5px; color: #4a5568; }
        input { width: 100%; padding: 10px; border: 1px solid #cbd5e0; border-radius: 6px; box-sizing: border-box; }
        .save-btn { width: 100%; padding: 12px; background: #1e3a8a; color: white; border: none; border-radius: 6px; cursor: pointer; font-weight: bold; }
    </style>
</head>
<body>

<div class="settings-card">
    <h2>⚙️ Global Settings</h2>
    <?php if(isset($success)) echo "<p style='color: green;'>$success</p>"; ?>
    
    <form method="POST">
        <div class="form-group">
            <label>Platform Name</label>
            <input type="text" name="settings[site_name]" value="<?php echo htmlspecialchars($settings['site_name']); ?>">
        </div>

        <div class="form-group">
            <label>Daily Interest Rate (%)</label>
            <input type="number" step="0.1" name="settings[daily_interest]" value="<?php echo $settings['daily_interest']; ?>">
        </div>

        <div class="form-group">
            <label>Minimum Withdrawal ($)</label>
            <input type="number" step="1" name="settings[min_withdrawal]" value="<?php echo $settings['min_withdrawal']; ?>">
        </div>

        <button type="submit" class="save-btn">Save Changes</button>
    </form>
    <br>
    <a href="admin_dashboard.php" style="text-decoration: none; color: #666; font-size: 14px;">← Back to Dashboard</a>
</div>

</body>
</html>