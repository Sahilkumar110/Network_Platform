<?php
include 'db.php';
include 'functions.php';
requireCronAccess();
ensureCronRunsTable($pdo);

try {
    $pdo->beginTransaction();
    $affected = recalculateAllUserRanks($pdo);
    $pdo->commit();
    $msg = "Rank recalculation complete. Rows affected: " . (int)$affected;
    logCronRun($pdo, 'cron_ranks', 'success', $msg);
    echo $msg;
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    logCronRun($pdo, 'cron_ranks', 'failed', $e->getMessage());
    die("Rank recalculation failed: " . $e->getMessage());
}
?>
