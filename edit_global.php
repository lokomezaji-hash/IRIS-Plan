<?php
session_start();
include 'db.php';
if (!isset($_SESSION['user'])) { exit("Unauthorized access"); }

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_GET['type'])) {
    $table = ($_GET['type'] === 'incoming') ? 'incoming' : 'outgoing';
    $id = intval($_POST['id']);
    $subject = $conn->real_escape_string($_POST['subject']);
    $action_taken = $conn->real_escape_string($_POST['action_taken']);
    $remarks = $conn->real_escape_string($_POST['remarks']);

    $sql = "UPDATE $table SET subject='$subject', action_taken='$action_taken', remarks='$remarks' WHERE id=$id";
    $conn->query($sql);
    header("Location: " . $table . "_transactions.php");
    exit();
}
?>