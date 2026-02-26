<?php
include 'db.php';
include 'functions.php';
requireCronAccess();

ensureNotificationQueueTable($pdo);
ensureCronRunsTable($pdo);
$result = processNotificationQueue($pdo, 50);
$sent = (int)$result['sent'];
$failed = (int)$result['failed'];

$msg = "Notifications processed. sent={$sent}, failed={$failed}";
logCronRun($pdo, 'cron_notifications', 'success', $msg);
echo $msg;

