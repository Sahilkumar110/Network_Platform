<?php
include 'db.php';
include 'functions.php';
requireCronAccess();

ensureLedgerReconciliationTable($pdo);
ensureCronRunsTable($pdo);

try {
    $pdo->beginTransaction();
    $result = runWalletReconciliation($pdo);
    $pdo->commit();
    $msg = "Reconciliation complete. report_id={$result['report_id']}, mismatches={$result['mismatched_users']}, status={$result['status']}";
    logCronRun($pdo, 'cron_reconcile', 'success', $msg);
    echo $msg;
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    logCronRun($pdo, 'cron_reconcile', 'failed', $e->getMessage());
    die("Reconciliation failed: " . $e->getMessage());
}

