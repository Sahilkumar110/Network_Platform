<?php
session_start();
include 'db.php';
if (!isset($_SESSION['user_id'])) { header("Location: login.php"); exit(); }

$user_id = $_SESSION['user_id'];

// Function to get users at a specific level
function getLevelUsers($pdo, $parent_ids) {
    if (empty($parent_ids)) return [];
    $in = str_repeat('?,', count($parent_ids) - 1) . '?';
    $stmt = $pdo->prepare("SELECT id, username, email, created_at FROM users WHERE referrer_id IN ($in)");
    $stmt->execute($parent_ids);
    return $stmt->fetchAll();
}

// Fetch Levels
$levels = [];
$current_parents = [$user_id];

for ($i = 1; $i <= 5; $i++) {
    $found_users = getLevelUsers($pdo, $current_parents);
    $levels[$i] = $found_users;
    $current_parents = array_column($found_users, 'id'); // Next level's parents
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>My Referral Network</title>
    <style>
        body { font-family: 'Segoe UI', sans-serif; background: #f0f2f5; padding: 20px; }
        .level-container { margin-bottom: 30px; }
        .level-header { 
            background: #1e3a8a; color: white; padding: 10px 20px; 
            border-radius: 8px; font-weight: bold; margin-bottom: 10px;
            display: flex; justify-content: space-between;
        }
        .user-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 15px; }
        .user-card { 
            background: white; padding: 15px; border-radius: 10px; 
            box-shadow: 0 2px 5px rgba(0,0,0,0.05); border-left: 4px solid #3b82f6;
        }
        .user-card h4 { margin: 0 0 5px 0; color: #1e293b; }
        .user-card p { margin: 0; font-size: 12px; color: #64748b; }
        .empty-msg { color: #94a3b8; font-style: italic; padding: 10px; }
    </style>
</head>
<body>

    <h1>My Network (5 Levels)</h1>
    <p>Your Referral Link: <code>http://yourdomain.com/register.php?ref=<?php echo $user_id; ?></code></p>

    <?php for ($i = 1; $i <= 5; $i++): ?>
        <div class="level-container">
            <div class="level-header">
                Level <?php echo $i; ?> 
                <span><?php echo count($levels[$i]); ?> Members</span>
            </div>
            
            <div class="user-grid">
                <?php if (empty($levels[$i])): ?>
                    <span class="empty-msg">No members at this level yet.</span>
                <?php else: ?>
                    <?php foreach ($levels[$i] as $member): ?>
                        <div class="user-card">
                            <h4><?php echo htmlspecialchars($member['username']); ?></h4>
                            <p><?php echo htmlspecialchars($member['email']); ?></p>
                            <p>Joined: <?php echo date('M d, Y', strtotime($member['created_at'])); ?></p>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    <?php endfor; ?>

</body>
</html>