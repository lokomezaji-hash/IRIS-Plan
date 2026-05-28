<?php
session_start();
include 'db.php';
if (!isset($_SESSION['user'])) { header("Location: index.php"); exit(); }
$user = $_SESSION['user']; // Active session user context

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = intval($_POST['id']);
    $subject = $_POST['subject'];
    $action_taken = $_POST['action_taken'];
    $remarks = $_POST['remarks'];
    $editor_id = $user['id']; // ID of who is editing right now

    // 1. Fetch current info before rewriting to record what it looked like *prior* to changes
    $stmt = $conn->prepare("SELECT subject, action_taken, remarks FROM incoming WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $current = $stmt->get_result()->fetch_assoc();

    // 2. Commit log metrics to history tables containing the editor's identification sequence
    // (Ensure your table structure includes an `edited_by` or `user_id` integer column)
    $log_stmt = $conn->prepare("INSERT INTO incoming_history (incoming_id, old_subject, old_action, old_remarks, edited_by) VALUES (?, ?, ?, ?, ?)");
    $log_stmt->bind_param("isssi", $id, $current['subject'], $current['action_taken'], $current['remarks'], $editor_id);
    $log_stmt->execute();

    // 3. Complete system updates for current transaction values
    $update_stmt = $conn->prepare("UPDATE incoming SET subject = ?, action_taken = ?, remarks = ? WHERE id = ?");
    $update_stmt->bind_param("sssi", $subject, $action_taken, $remarks, $id);
    $update_stmt->execute();

    header("Location: incoming_transactions.php");
    exit();
}
?>