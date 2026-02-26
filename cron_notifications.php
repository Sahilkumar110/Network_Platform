<?php
include 'db.php';
include 'functions.php';
requireCronAccess();

ensureNotificationQueueTable($pdo);
ensureCronRunsTable($pdo);

$limit = 50;
$stmt = $pdo->prepare(
    "SELECT * FROM notification_queue
     WHERE status = 'pending'
     ORDER BY created_at ASC
     LIMIT {$limit}"
);
$stmt->execute();
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

$sent = 0;
$failed = 0;

foreach ($rows as $row) {
    $id = (int)$row['id'];
    $ok = false;
    $error = '';
    try {
        if ($row['channel'] === 'email') {
            $to = trim((string)$row['email_to']);
            if ($to === '') {
                throw new Exception('Missing email_to');
            }
            $subject = (string)($row['subject'] ?: 'Network Platform Notification');
            $headers = "From: " . (string)(getenv('MAIL_FROM') ?: 'no-reply@network.local');
            $ok = @mail($to, $subject, (string)$row['message'], $headers);
            if (!$ok) {
                throw new Exception('mail() failed');
            }
        } elseif ($row['channel'] === 'telegram') {
            $bot = trim((string)(getenv('TELEGRAM_BOT_TOKEN') ?: ''));
            $chat = trim((string)(getenv('TELEGRAM_CHAT_ID') ?: ''));
            if ($bot === '' || $chat === '') {
                throw new Exception('Telegram token/chat not configured');
            }
            $url = "https://api.telegram.org/bot{$bot}/sendMessage";
            [$status, $res] = httpJsonRequest('POST', $url, [
                'chat_id' => $chat,
                'text' => (string)$row['message']
            ]);
            if ($status !== 200 || empty($res['ok'])) {
                throw new Exception('Telegram API send failed');
            }
            $ok = true;
        } else {
            throw new Exception('Unsupported channel');
        }
    } catch (Exception $e) {
        $error = $e->getMessage();
        $ok = false;
    }

    if ($ok) {
        $up = $pdo->prepare("UPDATE notification_queue SET status='sent', attempts=attempts+1, sent_at=NOW(), last_error=NULL WHERE id = ?");
        $up->execute([$id]);
        $sent++;
    } else {
        $up = $pdo->prepare("UPDATE notification_queue SET status='failed', attempts=attempts+1, last_error=? WHERE id = ?");
        $up->execute([$error, $id]);
        $failed++;
    }
}

$msg = "Notifications processed. sent={$sent}, failed={$failed}";
logCronRun($pdo, 'cron_notifications', 'success', $msg);
echo $msg;

