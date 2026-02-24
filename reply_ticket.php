<?php
session_start();
include 'db.php';

if ($_SESSION['role'] === 'admin' && $_SERVER['REQUEST_METHOD'] == 'POST') {
    $id = $_POST['ticket_id'];
    $reply = $_POST['reply'];

    $stmt = $pdo->prepare("UPDATE tickets SET admin_reply = ?, status = 'replied' WHERE id = ?");
    $stmt->execute([$reply, $id]);

    header("Location: admin_tickets.php?success=Reply Sent");
}
?>