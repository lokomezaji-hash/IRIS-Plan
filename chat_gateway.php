<?php
session_start();
include 'db.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user'])) {
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

$user = $_SESSION['user'];
$userId = $user['id'];
$action = isset($_GET['action']) ? $_GET['action'] : '';

// New Action Route: Fetch all users dynamically to pull current live statuses
if ($action === 'fetch_users') {
    $users_query = $conn->prepare("SELECT id, name, position, profile_pic, status FROM users WHERE id != ?");
    $users_query->bind_param("i", $userId);
    $users_query->execute();
    echo json_encode($users_query->get_result()->fetch_all(MYSQLI_ASSOC));
    exit();
}

if ($action === 'fetch_messages') {
    $targetUser = (int)$_GET['with'];
    
    $updateStmt = $conn->prepare("UPDATE messages SET is_read = 1 WHERE sender_id = ? AND receiver_id = ?");
    $updateStmt->bind_param("ii", $targetUser, $userId);
    $updateStmt->execute();

    $query = $conn->prepare("SELECT sender_id, receiver_id, message, is_file, file_path, timestamp FROM messages 
                             WHERE (sender_id = ? AND receiver_id = ?) 
                             OR (sender_id = ? AND receiver_id = ?) ORDER BY id ASC");
    $query->bind_param("iiii", $userId, $targetUser, $targetUser, $userId);
    $query->execute();
    echo json_encode($query->get_result()->fetch_all(MYSQLI_ASSOC));
    exit();
}

if ($action === 'send_message') {
    $receiverId = (int)$_POST['receiver_id'];
    $message = isset($_POST['message']) ? trim($_POST['message']) : '';
    $isFile = 0;
    $filePath = null;

    // Check if a file was uploaded and verify there are no hidden error flags
    if (isset($_FILES['chat_file']) && $_FILES['chat_file']['error'] == UPLOAD_ERR_OK) {
        $uploadDir = 'uploads/chat/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }
        
        $originalName = basename($_FILES['chat_file']['name']);
        $fileName = time() . '_' . $originalName;
        $targetPath = $uploadDir . $fileName;
        
        if (move_uploaded_file($_FILES['chat_file']['tmp_name'], $targetPath)) {
            $isFile = 1;
            $filePath = $targetPath;
            if (empty($message)) {
                $message = "Shared an item: " . $originalName;
            }
        } else {
            echo json_encode(['success' => false, 'error' => 'Could not save file to disk. Check permissions.']);
            exit();
        }
    } elseif (isset($_FILES['chat_file']) && $_FILES['chat_file']['error'] !== UPLOAD_ERR_NO_FILE) {
        // Return errors like exceeding php.ini max configuration limits
        echo json_encode(['success' => false, 'error' => 'File upload error code: ' . $_FILES['chat_file']['error']]);
        exit();
    }

    if (!empty($message) || $isFile === 1) {
        $stmt = $conn->prepare("INSERT INTO messages (sender_id, receiver_id, message, is_file, file_path) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("iisis", $userId, $receiverId, $message, $isFile, $filePath);
        
        if ($stmt->execute()) {
            $notiTitle = "New item from " . $user['name'];
            $notiMsg = strlen($message) > 40 ? substr($message, 0, 37) . "..." : $message;
            
            $notiStmt = $conn->prepare("INSERT INTO notifications (user_id, title, message) VALUES (?, ?, ?)");
            $notiStmt->bind_param("iss", $receiverId, $notiTitle, $notiMsg);
            $notiStmt->execute();

            echo json_encode(['success' => true]);
            exit();
        }
    }
    echo json_encode(['success' => false, 'error' => 'Empty message content submitted.']);
    exit();
}

if ($action === 'update_status') {
    $newStatus = $_POST['status'];
    if (in_array($newStatus, ['online', 'busy', 'offline'])) {
        $stmt = $conn->prepare("UPDATE users SET status = ? WHERE id = ?");
        $stmt->bind_param("si", $newStatus, $userId);
        $stmt->execute();
        echo json_encode(['success' => true]);
    }
    exit();
}

if ($action === 'unread_counts') {
    $query = $conn->prepare("SELECT sender_id, COUNT(*) as count FROM messages WHERE receiver_id = ? AND is_read = 0 GROUP BY sender_id");
    $query->bind_param("i", $userId);
    $query->execute();
    echo json_encode($query->get_result()->fetch_all(MYSQLI_ASSOC));
    exit();
}

if ($action === 'fetch_notifications') {
    $notiQuery = $conn->prepare("SELECT id, title, message, created_at, is_read FROM notifications WHERE user_id = ? ORDER BY id DESC LIMIT 10");
    $notiQuery->bind_param("i", $userId);
    $notiQuery->execute();
    echo json_encode($notiQuery->get_result()->fetch_all(MYSQLI_ASSOC));
    exit();
}