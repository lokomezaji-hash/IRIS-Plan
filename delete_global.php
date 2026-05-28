<?php
session_start();
include 'db.php';
if (!isset($_SESSION['user'])) { exit("Unauthorized access"); }

if (isset($_GET['type']) && isset($_GET['id'])) {
    $table = ($_GET['type'] === 'incoming') ? 'incoming' : 'outgoing';
    $id = intval($_GET['id']);
    
    $conn->query("DELETE FROM $table WHERE id=$id");
    header("Location: " . $table . "_transactions.php");
    exit();
}
?>