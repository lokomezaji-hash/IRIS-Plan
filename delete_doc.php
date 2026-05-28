<?php
// delete_doc.php snippet
session_start();
include 'db.php';

if (isset($_GET['id'])) {
    $id = intval($_GET['id']);

    // 1. Fetch the document details before erasing them
    $stmt = $conn->prepare("SELECT tracking_number, subject FROM internal WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $doc = $result->fetch_assoc();
        
        // 2. Insert into your deleted logs table
        $log_stmt = $conn->prepare("INSERT INTO deleted_document_logs (src_type, tracking_number, subject) VALUES ('Internal', ?, ?)");
        $log_stmt->bind_param("ss", $doc['tracking_number'], $doc['subject']);
        $log_stmt->execute();

        // 3. Now perform the hard delete safely
        $delete_stmt = $conn->prepare("DELETE FROM internal WHERE id = ?");
        $delete_stmt->bind_param("i", $id);
        $delete_stmt->execute();
    }
}

header("Location: internal_transactions.php");
exit();