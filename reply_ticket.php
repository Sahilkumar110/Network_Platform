<?php
session_start();
include 'db.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $id = (int)$_POST['ticket_id'];
    $reply = trim($_POST['reply']);

    $stmt = $pdo->prepare("UPDATE tickets SET admin_reply = ?, status = 'replied' WHERE id = ?");
    $stmt->execute([$reply, $id]);

    header("Location: admin_tickets.php?success=Reply Sent");
    exit();
}
?>
