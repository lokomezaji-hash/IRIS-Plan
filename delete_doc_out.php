<?php
include 'db.php';
if(isset($_GET['id'])) {
    $id = $_GET['id'];
    $conn->query("DELETE FROM outgoing WHERE id=$id");
}
header("Location: outgoing_transactions.php");
?>