<?php
session_start();
include 'db.php';
if (!isset($_SESSION['user'])) { header("Location: index.php"); exit(); }
$user = $_SESSION['user'];

// 1. Generate Auto Tracking ID (Fixed to prevent duplicate sequencing on deletions)
$date_today = date('Ymd');
$prefix = "OUT-" . $date_today . "-";

// Find the highest sequence tracking number saved today
$res = $conn->query("SELECT tracking_number FROM outgoing WHERE tracking_number LIKE '$prefix%' ORDER BY tracking_number DESC LIMIT 1");

if ($res && $res->num_rows > 0) {
    $row = $res->fetch_assoc();
    // Extract the last 3 digits from the tracking number (e.g., "003" from "OUT-20260526-003")
    $last_sequence = intval(substr($row['tracking_number'], -3));
    $next_sequence = $last_sequence + 1;
} else {
    // No documents uploaded yet today, start at 1
    $next_sequence = 1;
}

$next_num = str_pad($next_sequence, 3, '0', STR_PAD_LEFT);
$auto_tracking_num = $prefix . $next_num;

// 2. Process Form Insertion
if (isset($_POST['add_document'])) {
    $tn = $_POST['tracking_number'];
    $subject = $_POST['subject'];
    $doc_type = $_POST['doc_type'];
    $doc_date = $_POST['doc_date'];
    $action_taken = $_POST['action_taken'];
    $remarks = $_POST['remarks'];
    
    $filename = ""; 
    if (isset($_FILES['file']) && $_FILES['file']['error'] == 0) {
        $filename = time() . '_' . basename($_FILES['file']['name']);
        $target = "uploads/" . $filename;
        move_uploaded_file($_FILES['file']['tmp_name'], $target);
    }

    $stmt = $conn->prepare("INSERT INTO outgoing (tracking_number, subject, doc_type, doc_date, action_taken, remarks, file_path, uploaded_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("sssssssi", $tn, $subject, $doc_type, $doc_date, $action_taken, $remarks, $filename, $user['id']);
    $stmt->execute();
    header("Location: outgoing_transactions.php");
    exit();
}

// 3. Search and Pagination Engine
$search = isset($_GET['search']) ? $conn->real_escape_string($_GET['search']) : '';
$limit = 10;
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$offset = ($page - 1) * $limit;

$where_clause = "";
if (!empty($search)) {
    $where_clause = " WHERE outgoing.tracking_number LIKE '%$search%' 
                      OR outgoing.subject LIKE '%$search%' 
                      OR outgoing.doc_type LIKE '%$search%' 
                      OR outgoing.action_taken LIKE '%$search%' 
                      OR user.name LIKE '%$search%'";
}

$count_sql = "SELECT COUNT(*) as total FROM outgoing LEFT JOIN user ON outgoing.uploaded_by = user.id" . $where_clause;
$total_rows = $conn->query($count_sql)->fetch_assoc()['total'];
$total_pages = ceil($total_rows / $limit);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Outgoing Transactions</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 0; display: flex; background: #f4f6f9; transition: all 0.3s ease; }
        .sidebar { width: 260px; background: #343a40; color: white; min-height: 100vh; padding: 20px 10px; box-sizing: border-box; flex-shrink: 0; }
        .sidebar img { width: 80px; height: 80px; border-radius: 50%; display:block; margin: 0 auto 10px; object-fit: cover; border: 2px solid #007bff; }
        .sidebar a { display: block; color: #c2c7d0; padding: 12px; text-decoration: none; border-radius: 4px; margin-bottom: 5px; }
        .sidebar a:hover, .sidebar a.active { background: #007bff; color: white; }
        
        /* Layout Structure Wrappers */
        .app-container { flex: 1; display: flex; flex-direction: column; min-width: 0; }
        .main-content { display: flex; padding: 20px; gap: 20px; box-sizing: border-box; overflow: hidden; }
        
        /* Moving Light Functional Header Component */
        .aims-header {
            background: linear-gradient(-45deg, #1e3c72, #2a5298, #3a7bd5, #3a6073);
            background-size: 400% 400%;
            animation: movingLight 12s ease infinite;
            padding: 20px 30px;
            color: white;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }
        .aims-header h1 {
            margin: 0;
            font-size: 24px;
            font-weight: 700;
            letter-spacing: 0.5px;
            text-shadow: 0 2px 4px rgba(0,0,0,0.2);
        }
        .aims-header p {
            margin: 4px 0 0 0;
            font-size: 13px;
            color: rgba(255, 255, 255, 0.85);
        }

        @keyframes movingLight {
            0% { background-position: 0% 50%; }
            50% { background-position: 100% 50%; }
            100% { background-position: 0% 50%; }
        }

        .form-section { width: 300px; background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 5px rgba(0,0,0,0.05); height: fit-content; flex-shrink: 0; }
        .table-section { flex: 1; background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 5px rgba(0,0,0,0.05); overflow-x: auto; }
        .search-container { margin-bottom: 15px; display: flex; gap: 10px; }
        .search-container input[type="text"] { flex: 1; padding: 8px; border: 1px solid #ccc; border-radius: 4px; }
        .search-container button { padding: 8px 15px; background: #007bff; color: white; border: none; border-radius: 4px; cursor: pointer; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        table, th, td { border: 1px solid #dee2e6; }
        th, td { padding: 10px; text-align: left; font-size: 14px; }
        th { background: #f8f9fa; }
        
        /* Interactive Text Truncation Rules */
        .truncate-text { max-width: 150px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; cursor: pointer; color: #333; font-weight: 500; }
        .truncate-text:hover { color: #007bff; text-decoration: underline; }
        .full-text-bubble { display: none; background: #f8f9fa; border-left: 3px solid #007bff; padding: 6px; margin-top: 5px; font-size: 12px; color: #555; word-break: break-all; border-radius: 4px; }

        .uploader-info { display: flex; align-items: center; gap: 8px; }
        .uploader-thumb { width: 30px; height: 30px; border-radius: 50%; object-fit: cover; border: 1px solid #007bff; }
        .btn-sm { padding: 5px 10px; font-size: 12px; border: none; cursor: pointer; border-radius: 3px; margin-right: 2px; text-decoration: none; display: inline-block; }
        .btn-edit { background: #ffc107; color: #000; }
        .btn-view { background: #17a2b8; color: white; }
        .btn-disabled { background: #ced4da; color: #6c757d; cursor: not-allowed; pointer-events: none; }
        .btn-delete { background: #dc3545; color: white; }
        .form-group { margin-bottom: 12px; }
        .form-group label { display: block; font-weight: bold; margin-bottom: 4px; font-size: 14px; }
        .form-group input, .form-group textarea { width: 100%; padding: 6px; box-sizing: border-box; }
        .pagination { margin-top: 15px; display: flex; justify-content: center; gap: 5px; }
        .pagination a { padding: 8px 12px; border: 1px solid #dee2e6; color: #007bff; text-decoration: none; border-radius: 4px; }
        .pagination a.active { background: #007bff; color: white; border-color: #007bff; }
        .modal { display:none; position: fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); justify-content:center; align-items:center; z-index: 1000; }
        .modal-content { background:white; padding:20px; border-radius:8px; width:400px; max-height: 85vh; overflow-y: auto; }

        /* Media Queries for Window Minimizing / Maximizing */
        @media (max-width: 992px) {
            body { flex-direction: column; }
            .sidebar { width: 100%; min-height: auto; padding: 15px; text-align: center; }
            .sidebar img { margin: 0 auto 10px; }
            .aims-header { text-align: center; padding: 15px; }
            .main-content { flex-direction: column; padding: 10px; }
            .form-section { width: 100%; }
            .truncate-text { max-width: none; white-space: normal; }
        }
    </style>
</head>
<body>

    <div class="sidebar">
        <img src="uploads/<?php echo htmlspecialchars($user['profile_pic']); ?>" alt="Profile">
        <div style="text-align:center; font-weight:bold; margin-bottom:20px;">
            <?php echo htmlspecialchars($user['name']); ?><br>
            <small style="color:#a8a8a8;"><?php echo htmlspecialchars($user['position']); ?></small>
        </div>
        <a href="user_dashboard.php">Dashboard</a>
        <a href="internal_transactions.php">Internal Transactions</a>
        <a href="outgoing_transactions.php" class="active">Outgoing Transactions</a>
        <a href="incoming_transactions.php">Incoming Transactions</a>
        <a href="#">About Us</a>
        <a href="employee_history.php">Employee Status</a>
        <a href="logout.php" style="color: #ff6b6b; margin-top:30px;">Log Out</a>
    </div>

    <div class="app-container">
        
        <header class="aims-header">
            <h1>Administrative Information Management System 2.0 (AIMS2.0)</h1>
            <p>Office of Provincial Planning and Development Coordinator - Province of Antique</p>
        </header>

        <div class="main-content">
            <div class="form-section">
                <h3>Add Document</h3>
                <form method="POST" action="" enctype="multipart/form-data">
                    <div class="form-group"><label>Tracking Number</label><input type="text" name="tracking_number" value="<?php echo $auto_tracking_num; ?>" readonly></div>
                    <div class="form-group"><label>Subject</label><input type="text" name="subject" required></div>
                    <div class="form-group"><label>Document Type</label><input type="text" name="doc_type" required></div>
                    <div class="form-group"><label>Date</label><input type="date" name="doc_date" value="<?php echo date('Y-m-d'); ?>" required></div>
                    <div class="form-group"><label>Action Taken</label><input type="text" name="action_taken" required></div>
                    <div class="form-group"><label>Remarks</label><textarea name="remarks" rows="3"></textarea></div>
                    <div class="form-group"><label>Upload File (Optional)</label><input type="file" name="file"></div>
                    <button type="submit" name="add_document" style="width:100%; padding:8px; background:#28a745; color:white; border:none; font-weight:bold; cursor:pointer;">Submit Document</button>
                </form>
            </div>

            <div class="table-section">
                <h3>Outgoing Transactions</h3>
                
                <form method="GET" action="" class="search-container">
                    <input type="text" name="search" placeholder="Search parameters..." value="<?php echo htmlspecialchars($search); ?>">
                    <button type="submit">Search</button>
                    <?php if(!empty($search)): ?>
                        <a href="outgoing_transactions.php" style="padding:8px; background:#6c757d; color:white; text-decoration:none; border-radius:4px; font-size:14px;">Clear</a>
                    <?php endif; ?>
                </form>

                <table>
                    <thead>
                        <tr>
                            <th>Tracking No.</th>
                            <th>Subject</th>
                            <th>Doc Type</th>
                            <th>Date</th>
                            <th>Uploaded By</th>
                            <th>Action Taken</th>
                            <th>Remarks</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $sql = "SELECT outgoing.*, user.name as uploader_name, user.profile_pic as uploader_pic 
                                FROM outgoing 
                                LEFT JOIN user ON outgoing.uploaded_by = user.id 
                                $where_clause 
                                ORDER BY outgoing.id DESC LIMIT $limit OFFSET $offset";
                        $records = $conn->query($sql);
                        
                        while($row = $records->fetch_assoc()):
                            $pic = !empty($row['uploader_pic']) ? $row['uploader_pic'] : 'default.png';
                            $uploader_name = !empty($row['uploader_name']) ? $row['uploader_name'] : 'System/Deleted';
                        ?>
                        <tr>
                            <td><?php echo htmlspecialchars($row['tracking_number']); ?></td>
                            <td>
                                <div class="truncate-text" onclick="toggleTextCollapse(this)"><?php echo htmlspecialchars($row['subject']); ?></div>
                                <div class="full-text-bubble"><?php echo htmlspecialchars($row['subject']); ?></div>
                            </td>
                            <td><?php echo htmlspecialchars($row['doc_type']); ?></td>
                            <td><?php echo htmlspecialchars($row['doc_date']); ?></td>
                            <td>
                                <div class="uploader-info">
                                    <img src="uploads/<?php echo htmlspecialchars($pic); ?>" class="uploader-thumb" alt="User">
                                    <span><?php echo htmlspecialchars($uploader_name); ?></span>
                                </div>
                            </td>
                            <td>
                                <div class="truncate-text" onclick="toggleTextCollapse(this)"><?php echo htmlspecialchars($row['action_taken']); ?></div>
                                <div class="full-text-bubble"><?php echo htmlspecialchars($row['action_taken']); ?></div>
                            </td>
                            <td>
                                <div class="truncate-text" onclick="toggleTextCollapse(this)"><?php echo htmlspecialchars($row['remarks']); ?></div>
                                <div class="full-text-bubble"><?php echo !empty($row['remarks']) ? htmlspecialchars($row['remarks']) : '<em>No remarks</em>'; ?></div>
                            </td>
                            <td>
                                <button class="btn-sm btn-edit" onclick='openEditModal(<?php echo json_encode($row); ?>)'>Edit</button>
                                <?php if(!empty($row['file_path'])): ?>
                                    <a href="uploads/<?php echo htmlspecialchars($row['file_path']); ?>" target="_blank" class="btn-sm btn-view">View</a>
                                <?php else: ?>
                                    <button class="btn-sm btn-disabled" title="No file uploaded">None</button>
                                <?php endif; ?>
                                <a href="delete_doc_out.php?id=<?php echo $row['id']; ?>" class="btn-sm btn-delete" onclick="return confirm('Are you sure?')">Delete</a>
                                <button class="btn-sm" style="background:#6c757d; color:white;" onclick="openHistoryModal(<?php echo $row['id']; ?>)">History</button>
                            </td>  
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>

                <?php if($total_pages > 1): ?>
                <div class="pagination">
                    <?php for($i = 1; $i <= $total_pages; $i++): ?>
                        <a href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>" class="<?php echo ($page == $i) ? 'active' : ''; ?>"><?php echo $i; ?></a>
                    <?php endfor; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div id="editModal" class="modal">
        <div class="modal-content">
            <h3>Edit Document Reference</h3>
            <form method="POST" action="edit_doc_out.php">
                <input type="hidden" name="id" id="edit_id">
                <div class="form-group"><label>Subject</label><input type="text" name="subject" id="edit_subject"></div>
                <div class="form-group"><label>Action Taken</label><input type="text" name="action_taken" id="edit_action_taken"></div>
                <div class="form-group"><label>Remarks</label><textarea name="remarks" id="edit_remarks"></textarea></div>
                <button type="submit" class="btn-sm btn-edit" style="width:100%; padding:10px;">Save System Updates</button>
                <button type="button" onclick="closeModal('editModal')" style="width:100%; margin-top:5px; padding:5px;">Cancel</button>
            </form>
        </div>
    </div>

    <div id="historyModal" class="modal">
        <div class="modal-content" style="width: 500px;">
            <h3>Document Revision History</h3>
            <div id="history_logs_content" style="margin-bottom: 15px;">Loading log profiles...</div>
            <button type="button" onclick="closeModal('historyModal')" style="width:100%; padding:8px; background:#6c757d; color:white; border:none; cursor:pointer; font-weight:bold; border-radius:4px;">Close History</button>
        </div>
    </div>

    <script>
        function toggleTextCollapse(element) {
            var fullBubble = element.nextElementSibling;
            if (fullBubble.style.display === "block") {
                fullBubble.style.display = "none";
                element.style.whiteSpace = "nowrap";
            } else {
                fullBubble.style.display = "block";
                element.style.whiteSpace = "normal";
            }
        }
        
        function openEditModal(data) {
            document.getElementById('edit_id').value = data.id;
            document.getElementById('edit_subject').value = data.subject;
            document.getElementById('edit_action_taken').value = data.action_taken;
            document.getElementById('edit_remarks').value = data.remarks;
            document.getElementById('editModal').style.display = 'flex';
        }
        
        function openHistoryModal(id) {
            document.getElementById('historyModal').style.display = 'flex';
            document.getElementById('history_logs_content').innerHTML = "<p style='color:#6c757d; text-align:center;'>Loading revisions...</p>";
            fetch('get_history_out.php?id=' + id)
                .then(response => response.text())
                .then(html => { document.getElementById('history_logs_content').innerHTML = html; });
        }

        function closeModal(id) { 
            document.getElementById(id).style.display = 'none'; 
        }
    </script>
</body>
</html>