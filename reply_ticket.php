<?php
session_start();
include 'db.php';
include 'functions.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        header("Location: admin_tickets.php?error=Invalid session token");
        exit();
    }
    $id = (int)$_POST['ticket_id'];
    $reply = trim($_POST['reply']);

    $stmt = $pdo->prepare("UPDATE tickets SET admin_reply = ?, status = 'replied' WHERE id = ?");
    $stmt->execute([$reply, $id]);

    header("Location: admin_tickets.php?success=Reply Sent");
    exit();
}
?>
