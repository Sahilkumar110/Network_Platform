<?php
include 'db.php';
include 'functions.php';
requireCronAccess();

try {
    $pdo->beginTransaction();
    $affected = recalculateAllUserRanks($pdo);
    $pdo->commit();
    echo "Rank recalculation complete. Rows affected: " . (int)$affected;
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    die("Rank recalculation failed: " . $e->getMessage());
}
?>
