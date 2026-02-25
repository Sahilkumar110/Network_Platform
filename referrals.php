<?php
session_start();
include 'db.php';
include 'functions.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = (int)$_SESSION['user_id'];

backfillMissingUserCodes($pdo);
updateUserRank($pdo, $user_id);

$user_stmt = $pdo->prepare("SELECT id, username, user_code FROM users WHERE id = ?");
$user_stmt->execute([$user_id]);
$current_user = $user_stmt->fetch();

if (!$current_user) {
    session_destroy();
    header("Location: login.php");
    exit();
}

$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$base_path = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/')), '/');
$referral_link = $scheme . '://' . $host . $base_path . '/register.php?ref=' . rawurlencode((string)$current_user['user_code']);

// Pull enough fields to support both tree and level views.
$all_stmt = $pdo->query("SELECT id, username, email, referrer_id, created_at, user_code FROM users ORDER BY id ASC");
$all_users = $all_stmt->fetchAll(PDO::FETCH_ASSOC);

$user_by_id = [];
$children_map = [];
foreach ($all_users as $row) {
    $id = (int)$row['id'];
    $parent = $row['referrer_id'] === null ? null : (int)$row['referrer_id'];
    $user_by_id[$id] = $row;
    if (!array_key_exists($parent, $children_map)) {
        $children_map[$parent] = [];
    }
    $children_map[$parent][] = $row;
}

// Build 5-level lists (existing style) and also collect descendants for summary cards.
$levels = [];
$descendant_ids = [];
$current_parents = [$user_id];
for ($level = 1; $level <= 5; $level++) {
    $levels[$level] = [];
    $next_parents = [];
    foreach ($current_parents as $parent_id) {
        $children = $children_map[$parent_id] ?? [];
        foreach ($children as $child) {
            $levels[$level][] = $child;
            $child_id = (int)$child['id'];
            $descendant_ids[$child_id] = true;
            $next_parents[] = $child_id;
        }
    }
    $current_parents = $next_parents;
}

// Tree stats: total descendants and maximum depth (capped at 5 for display consistency).
$total_descendants = count($descendant_ids);
$max_depth = 0;
$queue = [[$user_id, 0]];
while (!empty($queue)) {
    [$node_id, $depth] = array_shift($queue);
    $children = $children_map[$node_id] ?? [];
    if (!empty($children)) {
        $max_depth = max($max_depth, $depth + 1);
    }
    if ($depth >= 5) {
        continue;
    }
    foreach ($children as $child) {
        $queue[] = [(int)$child['id'], $depth + 1];
    }
}
$max_depth = min($max_depth, 5);

function renderTreeLevel(array $children_map, int $parent_id, int $depth, int $max_depth): void
{
    if ($depth > $max_depth) {
        return;
    }

    $children = $children_map[$parent_id] ?? [];
    if (empty($children)) {
        return;
    }

    echo '<ul class="tree-level">';
    foreach ($children as $child) {
        $child_id = (int)$child['id'];
        $grandchildren = $children_map[$child_id] ?? [];
        $has_children = !empty($grandchildren) && $depth < $max_depth;
        $join_date = date('M d, Y', strtotime($child['created_at']));

        $search_text = strtolower($child['username'] . ' ' . $child['email']);
        echo '<li class="tree-node" data-level="' . $depth . '" data-search="' . htmlspecialchars($search_text, ENT_QUOTES) . '">';
        if ($has_children) {
            echo '<details open>';
            echo '<summary>';
        }

        echo '<div class="node-card">';
        echo '<div class="node-top">';
        echo '<span class="node-name">' . htmlspecialchars($child['username']) . '</span>';
        echo '<span class="node-level">L' . $depth . '</span>';
        echo '</div>';
        $display_code = !empty($child['user_code']) ? $child['user_code'] : ('#' . $child_id);
        echo '<div class="node-meta">ID ' . htmlspecialchars($display_code) . ' | ' . htmlspecialchars($child['email']) . '</div>';
        echo '<div class="node-meta">Joined: ' . $join_date . ' | Directs: ' . count($grandchildren) . '</div>';
        echo '</div>';

        if ($has_children) {
            echo '</summary>';
            renderTreeLevel($children_map, $child_id, $depth + 1, $max_depth);
            echo '</details>';
        }
        echo '</li>';
    }
    echo '</ul>';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Referral Network</title>
<style>
        :root {
            --primary: #1e3a8a;
            --secondary: #3b82f6;
            --bg: #f8fafc;
            --text-dark: #0f172a;
            --text-light: #64748b;
            --border: #dbeafe;
        }
        body {
            margin: 0;
            font-family: 'Segoe UI', sans-serif;
            background: var(--bg);
            color: var(--text-dark);
        }
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 24px;
        }
        .top {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
            flex-wrap: wrap;
        }
        .back-link {
            text-decoration: none;
            color: var(--primary);
            font-weight: 700;
        }
        .title {
            margin: 10px 0 4px;
            font-size: 28px;
        }
        .subtitle {
            margin: 0 0 20px;
            color: var(--text-light);
            font-size: 14px;
        }
        .stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 12px;
            margin-bottom: 16px;
        }
        .stat-card {
            background: #fff;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            padding: 14px;
        }
        .stat-label {
            color: var(--text-light);
            font-size: 12px;
            text-transform: uppercase;
            font-weight: 700;
        }
        .stat-value {
            font-size: 24px;
            font-weight: 800;
            margin-top: 4px;
        }
        .tabs {
            display: flex;
            gap: 8px;
            margin: 14px 0 18px;
        }
        .controls {
            display: grid;
            gap: 10px;
            margin: 10px 0 8px;
        }
        .search-row {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }
        .search-input {
            flex: 1;
            min-width: 220px;
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            padding: 9px 10px;
            font-size: 14px;
        }
        .clear-btn {
            border: 1px solid #cbd5e1;
            background: #fff;
            border-radius: 8px;
            padding: 9px 12px;
            cursor: pointer;
            font-weight: 700;
        }
        .level-filters {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
        }
        .level-chip {
            border: 1px solid #cbd5e1;
            background: #fff;
            color: #0f172a;
            border-radius: 999px;
            padding: 6px 12px;
            cursor: pointer;
            font-size: 12px;
            font-weight: 700;
        }
        .level-chip.active {
            background: var(--secondary);
            color: #fff;
            border-color: var(--secondary);
        }
        .tab-btn {
            border: 1px solid #cbd5e1;
            background: #fff;
            color: #0f172a;
            padding: 8px 12px;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 700;
        }
        .tab-btn.active {
            background: var(--primary);
            color: #fff;
            border-color: var(--primary);
        }
        .panel {
            display: none;
        }
        .panel.active {
            display: block;
        }
        .tree-root {
            background: #fff;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            padding: 14px;
        }
        .tree-level {
            list-style: none;
            margin: 12px 0 0 24px;
            padding: 0;
            border-left: 2px dashed var(--border);
        }
        .tree-node {
            margin: 10px 0;
            padding-left: 14px;
            position: relative;
        }
        .tree-node::before {
            content: "";
            position: absolute;
            top: 22px;
            left: 0;
            width: 12px;
            border-top: 2px dashed var(--border);
        }
        details > summary {
            list-style: none;
            cursor: pointer;
        }
        details > summary::-webkit-details-marker {
            display: none;
        }
        .node-card {
            background: #fff;
            border: 1px solid #dbeafe;
            border-radius: 10px;
            padding: 10px 12px;
        }
        .node-top {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 6px;
        }
        .node-name {
            font-weight: 800;
            color: var(--primary);
        }
        .node-level {
            background: #eff6ff;
            color: var(--primary);
            font-size: 11px;
            font-weight: 800;
            border-radius: 999px;
            padding: 3px 8px;
        }
        .node-meta {
            color: var(--text-light);
            font-size: 12px;
            line-height: 1.4;
        }
        .level-container {
            margin-bottom: 18px;
        }
        .level-header {
            background: var(--primary);
            color: #fff;
            padding: 10px 14px;
            border-radius: 8px;
            display: flex;
            justify-content: space-between;
            font-weight: 700;
        }
        .user-grid {
            margin-top: 10px;
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
            gap: 10px;
        }
        .user-card {
            background: #fff;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            padding: 10px;
        }
        .user-card h4 {
            margin: 0 0 4px;
            color: var(--primary);
        }
        .user-card p {
            margin: 2px 0;
            font-size: 12px;
            color: var(--text-light);
        }
        .empty-msg {
            color: #94a3b8;
            font-size: 13px;
            margin-top: 8px;
            display: block;
        }
        @media (max-width: 768px) {
            .container {
                padding: 16px;
            }
            .tree-level {
                margin-left: 14px;
            }
        }
    </style>
    <link rel="stylesheet" href="responsive.css">
</head>
<body>
    <div class="container">
        <div class="top">
            <a href="dashboard.php" class="back-link">Back to Dashboard</a>
            <div class="subtitle">Your referral link: <code><?php echo htmlspecialchars($referral_link); ?></code></div>
        </div>

        <h1 class="title page-title">My Referral Network</h1>
        <p class="subtitle">View your team as an expandable tree or in the classic 5-level layout.</p>

        <div class="stats">
            <div class="stat-card">
                <div class="stat-label">Total Team Size</div>
                <div class="stat-value"><?php echo $total_descendants; ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Direct Referrals</div>
                <div class="stat-value"><?php echo count($children_map[$user_id] ?? []); ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Deepest Level</div>
                <div class="stat-value"><?php echo $max_depth; ?></div>
            </div>
        </div>

        <div class="controls">
            <div class="search-row">
                <input id="treeSearch" class="search-input" type="text" placeholder="Search by username or email">
                <button id="clearSearch" class="clear-btn" type="button">Clear</button>
            </div>
            <div class="level-filters">
                <button type="button" class="level-chip active" data-level="all">All Levels</button>
                <button type="button" class="level-chip" data-level="1">L1</button>
                <button type="button" class="level-chip" data-level="2">L2</button>
                <button type="button" class="level-chip" data-level="3">L3</button>
                <button type="button" class="level-chip" data-level="4">L4</button>
                <button type="button" class="level-chip" data-level="5">L5</button>
            </div>
        </div>

        <div class="tabs">
            <button class="tab-btn active" type="button" data-target="tree-panel">Tree View</button>
            <button class="tab-btn" type="button" data-target="level-panel">Level View</button>
        </div>

        <section id="tree-panel" class="panel active">
            <div class="tree-root">
                <div class="node-card">
                    <div class="node-top">
                        <span class="node-name"><?php echo htmlspecialchars($current_user['username']); ?> (You)</span>
                        <span class="node-level">Root</span>
                    </div>
                    <div class="node-meta">ID <?php echo htmlspecialchars(!empty($current_user['user_code']) ? $current_user['user_code'] : ('#' . (int)$current_user['id'])); ?></div>
                </div>
                <?php renderTreeLevel($children_map, $user_id, 1, 5); ?>
            </div>
        </section>

        <section id="level-panel" class="panel">
            <?php for ($i = 1; $i <= 5; $i++): ?>
                <div class="level-container" data-level="<?php echo $i; ?>">
                    <div class="level-header">
                        <span>Level <?php echo $i; ?></span>
                        <span><?php echo count($levels[$i]); ?> Members</span>
                    </div>
                    <div class="user-grid">
                        <?php if (empty($levels[$i])): ?>
                            <span class="empty-msg">No members at this level yet.</span>
                        <?php else: ?>
                            <?php foreach ($levels[$i] as $member): ?>
                                <div class="user-card" data-search="<?php echo htmlspecialchars(strtolower($member['username'] . ' ' . $member['email']), ENT_QUOTES); ?>">
                                    <h4><?php echo htmlspecialchars($member['username']); ?></h4>
                                    <p><?php echo htmlspecialchars($member['email']); ?></p>
                                    <p>Joined: <?php echo date('M d, Y', strtotime($member['created_at'])); ?></p>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endfor; ?>
        </section>
    </div>

    <script>
        const tabButtons = document.querySelectorAll('.tab-btn');
        const panels = document.querySelectorAll('.panel');
        const searchInput = document.getElementById('treeSearch');
        const clearSearch = document.getElementById('clearSearch');
        const levelChips = document.querySelectorAll('.level-chip');
        const treePanel = document.getElementById('tree-panel');
        const levelPanel = document.getElementById('level-panel');
        let activeLevel = 'all';

        tabButtons.forEach((button) => {
            button.addEventListener('click', () => {
                const target = button.getAttribute('data-target');

                tabButtons.forEach((b) => b.classList.remove('active'));
                panels.forEach((p) => p.classList.remove('active'));

                button.classList.add('active');
                document.getElementById(target).classList.add('active');
                applyFilters();
            });
        });

        levelChips.forEach((chip) => {
            chip.addEventListener('click', () => {
                levelChips.forEach((c) => c.classList.remove('active'));
                chip.classList.add('active');
                activeLevel = chip.getAttribute('data-level');
                applyFilters();
            });
        });

        searchInput.addEventListener('input', applyFilters);
        clearSearch.addEventListener('click', () => {
            searchInput.value = '';
            applyFilters();
        });

        function matchesLevel(levelValue) {
            return activeLevel === 'all' || String(levelValue) === activeLevel;
        }

        function applyTreeFilters() {
            const term = searchInput.value.trim().toLowerCase();

            function filterNode(node) {
                const nodeText = (node.getAttribute('data-search') || '').toLowerCase();
                const nodeLevel = node.getAttribute('data-level');
                const ownMatch = nodeText.includes(term) && matchesLevel(nodeLevel);
                const childNodes = Array.from(node.querySelectorAll(':scope > details > ul.tree-level > li.tree-node'));

                let childMatch = false;
                childNodes.forEach((child) => {
                    if (filterNode(child)) {
                        childMatch = true;
                    }
                });

                const visible = ownMatch || childMatch || (term === '' && activeLevel === 'all');
                node.style.display = visible ? '' : 'none';

                const details = node.querySelector(':scope > details');
                if (details && (term !== '' || activeLevel !== 'all')) {
                    details.open = ownMatch || childMatch;
                }

                return visible;
            }

            const roots = treePanel.querySelectorAll('.tree-root > .tree-level > .tree-node');
            roots.forEach((node) => {
                filterNode(node);
            });
        }

        function applyLevelFilters() {
            const term = searchInput.value.trim().toLowerCase();
            const containers = levelPanel.querySelectorAll('.level-container');

            containers.forEach((container) => {
                const level = container.getAttribute('data-level');
                const cards = container.querySelectorAll('.user-card');
                let anyVisible = false;

                cards.forEach((card) => {
                    const text = (card.getAttribute('data-search') || '').toLowerCase();
                    const visible = text.includes(term) && matchesLevel(level);
                    card.style.display = visible ? '' : 'none';
                    if (visible) {
                        anyVisible = true;
                    }
                });

                const emptyMsg = container.querySelector('.empty-msg');
                if (emptyMsg) {
                    emptyMsg.style.display = (matchesLevel(level) && cards.length === 0) ? '' : 'none';
                }

                const showContainer = matchesLevel(level) && (anyVisible || cards.length === 0 || term === '');
                container.style.display = showContainer ? '' : 'none';
            });
        }

        function applyFilters() {
            applyTreeFilters();
            applyLevelFilters();
        }

        applyFilters();
    </script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
