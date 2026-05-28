<?php
session_start();
include 'db.php';
if (!isset($_SESSION['user'])) { exit("Access Denied"); }

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($id === 0) {
    echo "Invalid document request parameter.";
    exit();
}

// 1. Fetch the CURRENT actual data of the document to compare against history
$current_sql = "SELECT subject, action_taken, remarks FROM incoming WHERE id = ?";
$curr_stmt = $conn->prepare($current_sql);
$curr_stmt->bind_param("i", $id);
$curr_stmt->execute();
$current_doc = $curr_stmt->get_result()->fetch_assoc();

if (!$current_doc) {
    echo "Document not found.";
    exit();
}

// 2. Select historical markers along with individual modifier profiles
$sql = "SELECT h.*, u.name as editor_name, u.position as editor_position, u.profile_pic as editor_pic 
        FROM incoming_history h
        LEFT JOIN user u ON h.edited_by = u.id 
        WHERE h.incoming_id = ? 
        ORDER BY h.edit_time DESC";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo "<p style='color:#6c757d; text-align:center; padding: 10px;'>This document remains in its original layout state. No previous modifications tracked.</p>";
    exit();
}

while ($log = $result->fetch_assoc()):
    $avatar = !empty($log['editor_pic']) ? $log['editor_pic'] : 'default.png';
    $name = !empty($log['editor_name']) ? $log['editor_name'] : 'Unknown Operator/Deleted User';
    $timestamp = date("F j, Y, g:i a", strtotime($log['edit_time'] ?? 'now'));
    
    // Check what actually changed during this edit step
    $has_changes = false;
    $change_output = "";

    // Compare Subject
    if ($log['old_subject'] !== $current_doc['subject']) {
        $has_changes = true;
        $change_output .= "<div style='margin-bottom: 6px;'><strong>Subject changed from:</strong> <span style='color: #dc3545; text-decoration: line-through;'>" . htmlspecialchars($log['old_subject']) . "</span></div>";
    }

    // Compare Action Taken
    if ($log['old_action'] !== $current_doc['action_taken']) {
        $has_changes = true;
        $change_output .= "<div style='margin-bottom: 6px;'><strong>Action Taken changed from:</strong> <span style='color: #dc3545; text-decoration: line-through;'>" . htmlspecialchars($log['old_action']) . "</span></div>";
    }

    // Compare Remarks
    if ($log['old_remarks'] !== $current_doc['remarks']) {
        $has_changes = true;
        $old_rem = !empty($log['old_remarks']) ? htmlspecialchars($log['old_remarks']) : '<em>Empty</em>';
        $change_output .= "<div style='margin-bottom: 6px;'><strong>Remarks changed from:</strong> <span style='color: #dc3545; text-decoration: line-through;'>" . $old_rem . "</span></div>";
    }

    // After analyzing this log, we update our comparison base value to step backwards in time sequentially
    $current_doc['subject'] = $log['old_subject'];
    $current_doc['action_taken'] = $log['old_action'];
    $current_doc['remarks'] = $log['old_remarks'];

    // Only output the card layout if a variation actually happened in this edit cycle
    if ($has_changes):
?>
    <div style="border-bottom: 1px dashed #dee2e6; padding: 12px 0; margin-bottom: 10px; font-size: 13px;">
        <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 8px;">
            <img src="uploads/<?php echo htmlspecialchars($avatar); ?>" style="width: 32px; height: 32px; border-radius: 50%; object-fit: cover; border:1px dashed #ced4da;">
            <div>
                <strong><?php echo htmlspecialchars($name); ?></strong> 
                <small style="color: #6c757d;">(<?php echo htmlspecialchars($log['editor_position'] ?? 'Staff'); ?>)</small>
                <div style="font-size: 11px; color:#a1a1a1;"><?php echo $timestamp; ?></div>
            </div>
        </div>
        
        <div style="background: #fdfdfd; padding: 10px; border-radius: 4px; border-left: 3px solid #ffc107; line-height: 1.4;">
            <?php echo $change_output; ?>
        </div>
    </div>
<?php 
    endif;
endwhile; 
?>