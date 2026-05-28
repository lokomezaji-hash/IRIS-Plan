<?php
session_start();
include 'db.php';

// Check for unified user session array presence
if (!isset($_SESSION['user'])) {
    header("Location: index.php");
    exit();
}

$position = isset($_SESSION['user']['position']) ? strtolower($_SESSION['user']['position']) : '';
if ($position !== 'admin' && $position !== 'administrator') {
    die("Access Denied: Admin clearance needed.");
}

$admin_user = $_SESSION['user'];

// --- PROCESS ADD NEW USER ---
$insert_success = "";
$insert_error = "";
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action_add_user'])) {
    $new_name = $conn->real_escape_string($_POST['new_name']);
    $new_username = $conn->real_escape_string($_POST['new_username']);
    $new_password = $conn->real_escape_string($_POST['new_password']);
    $new_position = $conn->real_escape_string($_POST['new_position']);
    
    $new_pic = "default.png";
    
    if (isset($_FILES['new_profile_pic']) && $_FILES['new_profile_pic']['error'] == 0) {
        $target_dir = "uploads/";
        if (!file_exists($target_dir)) {
            mkdir($target_dir, 0777, true);
        }
        $file_name = basename($_FILES["new_profile_pic"]["name"]);
        $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
        $unique_name = time() . "_" . uniqid() . "." . $file_ext;
        if (in_array($file_ext, array("jpg", "jpeg", "png", "gif"))) {
            if (move_uploaded_file($_FILES["new_profile_pic"]["tmp_name"], $target_dir . $unique_name)) {
                $new_pic = $unique_name;
            }
        }
    }
    
    $check_exist = $conn->query("SELECT id FROM user WHERE username = '$new_username'");
    if($check_exist->num_rows > 0) {
        $insert_error = "Username already taken!";
    } else {
        $insert_sql = "INSERT INTO user (name, username, password, position, profile_pic) VALUES ('$new_name', '$new_username', '$new_password', '$new_position', '$new_pic')";
        if ($conn->query($insert_sql)) {
            $insert_success = "User registered successfully!";
        } else {
            $insert_error = "Database Error: Failed to register user.";
        }
    }
}

// --- PROCESS EDIT USER ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action_edit_user'])) {
    $edit_id = intval($_POST['edit_id']);
    $edit_name = $conn->real_escape_string($_POST['edit_name']);
    $edit_username = $conn->real_escape_string($_POST['edit_username']);
    $edit_position = $conn->real_escape_string($_POST['edit_position']);
    
    // Check if password was changed
    $pass_update = "";
    if (!empty($_POST['edit_password'])) {
        $edit_password = $conn->real_escape_string($_POST['edit_password']);
        $pass_update = ", password = '$edit_password'";
    }

    // Process new image upload if present
    $pic_update = "";
    if (isset($_FILES['edit_profile_pic']) && $_FILES['edit_profile_pic']['error'] == 0) {
        $target_dir = "uploads/";
        $file_name = basename($_FILES["edit_profile_pic"]["name"]);
        $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
        $unique_name = time() . "_" . uniqid() . "." . $file_ext;
        
        if (in_array($file_ext, array("jpg", "jpeg", "png", "gif"))) {
            if (move_uploaded_file($_FILES["edit_profile_pic"]["tmp_name"], $target_dir . $unique_name)) {
                // Delete old pic if it wasn't the default
                $old_pic_res = $conn->query("SELECT profile_pic FROM user WHERE id = $edit_id");
                if ($old_pic_row = $old_pic_res->fetch_assoc()) {
                    if (!empty($old_pic_row['profile_pic']) && $old_pic_row['profile_pic'] != 'default.png' && file_exists($target_dir . $old_pic_row['profile_pic'])) {
                        unlink($target_dir . $old_pic_row['profile_pic']);
                    }
                }
                $pic_update = ", profile_pic = '$unique_name'";
            }
        }
    }

    $check_exist = $conn->query("SELECT id FROM user WHERE username = '$edit_username' AND id != $edit_id");
    if($check_exist->num_rows > 0) {
        $insert_error = "Username is already used by another user account!";
    } else {
        $update_sql = "UPDATE user SET name = '$edit_name', username = '$edit_username', position = '$edit_position' $pass_update $pic_update WHERE id = $edit_id";
        if ($conn->query($update_sql)) {
            $insert_success = "User credentials updated successfully!";
        } else {
            $insert_error = "Database Error: Failed to execute modifications.";
        }
    }
}

// --- PROCESS DELETE USER ---
if (isset($_GET['delete_user'])) {
    $delete_id = intval($_GET['delete_user']);
    
    // Prevent self deletion
    if ($delete_id == $admin_user['id']) {
        $insert_error = "Operation Aborted: You cannot delete your own logged-in admin account.";
    } else {
        // Remove profile pic from storage disk
        $pic_res = $conn->query("SELECT profile_pic FROM user WHERE id = $delete_id");
        if ($pic_row = $pic_res->fetch_assoc()) {
            if (!empty($pic_row['profile_pic']) && $pic_row['profile_pic'] != 'default.png' && file_exists("uploads/" . $pic_row['profile_pic'])) {
                unlink("uploads/" . $pic_row['profile_pic']);
            }
        }
        
        if ($conn->query("DELETE FROM user WHERE id = $delete_id")) {
            $insert_success = "User account completely purged from server index.";
        } else {
            $insert_error = "Error: Failed to clear user database profile records.";
        }
    }
}

// --- PAGINATION CONFIGURATION ENGINE ---
$limit_per_page = 5;
$current_page = isset($_GET['page']) ? intval($_GET['page']) : 1;
if ($current_page < 1) $current_page = 1;
$offset_value = ($current_page - 1) * $limit_per_page;

$count_query = "SELECT COUNT(*) as total FROM user WHERE position != 'Admin' AND position != 'administrator'";
$total_users_count = $conn->query($count_query)->fetch_assoc()['total'] ?? 0;
$total_pages = ceil($total_users_count / $limit_per_page);
if ($total_pages < 1) $total_pages = 1;
if ($current_page > $total_pages) $current_page = $total_pages;

$offset_value = ($current_page - 1) * $limit_per_page;

$users_list_query = "SELECT id, name, position, username, profile_pic FROM user 
                     WHERE position != 'Admin' AND position != 'administrator' 
                     ORDER BY name ASC LIMIT $limit_per_page OFFSET $offset_value";
$users_res = $conn->query($users_list_query);

$target_user_id = isset($_GET['inspect_user']) ? intval($_GET['inspect_user']) : 0;
$is_inspect_mode = ($target_user_id > 0);

// Adjusted filters to ignore soft deleted files in counts
$user_filter_clause = $is_inspect_mode ? " WHERE uploaded_by = $target_user_id AND deleted_at IS NULL " : " WHERE deleted_at IS NULL ";

if ($is_inspect_mode) {
    $meta_stmt = $conn->prepare("SELECT name, position, profile_pic FROM user WHERE id = ?");
    $meta_stmt->bind_param("i", $target_user_id);
    $meta_stmt->execute();
    $inspected_user_meta = $meta_stmt->get_result()->fetch_assoc();
}

$internal_count = $conn->query("SELECT COUNT(*) as total FROM internal" . $user_filter_clause)->fetch_assoc()['total'] ?? 0;
$incoming_count = $conn->query("SELECT COUNT(*) as total FROM incoming" . $user_filter_clause)->fetch_assoc()['total'] ?? 0;
$outgoing_count = $conn->query("SELECT COUNT(*) as total FROM outgoing" . $user_filter_clause)->fetch_assoc()['total'] ?? 0;
$total_docs = $internal_count + $incoming_count + $outgoing_count;
$global_user_count = $total_users_count;

// --- RETRIEVE RECENTLY DELETED DOCUMENTS ---
$deleted_filter = $is_inspect_mode ? " WHERE uploaded_by = $target_user_id AND deleted_at IS NOT NULL " : " WHERE deleted_at IS NOT NULL ";
$deleted_sql = "(SELECT 'Internal' as src_type, tracking_number, subject, deleted_at FROM internal $deleted_filter)
                UNION
                (SELECT 'Incoming' as src_type, tracking_number, subject, deleted_at FROM incoming $deleted_filter)
                UNION
                (SELECT 'Outgoing' as src_type, tracking_number, subject, deleted_at FROM outgoing $deleted_filter)
                ORDER BY deleted_at DESC LIMIT 10";
$deleted_res = $conn->query($deleted_sql);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AIMS Admin Operations Control</title>

     <style>
        :root {
            --admin-primary: #6f42c1;
            --admin-dark: #212529;
            --primary-hover: #563d7c;
            --success: #28a745;
            --info: #17a2b8;
            --warning: #ffc107;
            --danger: #dc3545;
            --bg-light: #fdfdfd;
            --text-muted: #6c757d;
            --card-shadow: 0 4px 12px rgba(0,0,0,0.08);
            --transition: all 0.2s ease-in-out;
        }

        body { font-family: 'Segoe UI', Arial, sans-serif; margin: 0; display: flex; background: #f4f5f8; color: #333; }
        
        .sidebar { width: 260px; background: var(--admin-dark); color: white; min-height: 100vh; padding: 20px 10px; box-sizing: border-box; flex-shrink: 0; }
        .sidebar img { width: 80px; height: 80px; border-radius: 50%; display:block; margin: 0 auto 10px; object-fit: cover; border: 2px solid var(--admin-primary); }
        .sidebar a { display: block; color: #c2c7d0; padding: 12px; text-decoration: none; border-radius: 4px; margin-bottom: 5px; font-size: 14px; transition: var(--transition); }
        .sidebar a:hover, .sidebar a.active { background: var(--admin-primary); color: white; }
        
        .main-content { flex: 1; padding: 30px; box-sizing: border-box; display: flex; flex-direction: column; gap: 25px; overflow-y: auto; height: 100vh; }
        
        .admin-header { display: flex; justify-content: space-between; align-items: center; background: white; padding: 20px 25px; border-radius: 12px; box-shadow: var(--card-shadow); }
        .admin-title h1 { margin: 0; font-size: 24px; color: var(--admin-dark); }
        .admin-title p { margin: 5px 0 0; color: var(--text-muted); font-size: 14px; }
        
        .inspector-control-box { background: #f8f0ff; border: 1px solid #e1ceff; padding: 15px 20px; border-radius: 10px; display: flex; align-items: center; justify-content: space-between; gap: 15px; flex-wrap: wrap; }
        .selector-group { display: flex; align-items: center; gap: 10px; }
        .selector-group label { font-size: 14px; font-weight: bold; color: var(--admin-primary); }
        .selector-group select { padding: 8px 15px; font-size: 14px; border-radius: 6px; border: 1px solid #ced4da; background: white; }
        .inspect-badge { background: var(--admin-primary); color: white; padding: 5px 12px; border-radius: 15px; font-size: 12px; font-weight: bold; }

        .metrics-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 20px; }
        .metric-card { background: white; border-radius: 12px; padding: 22px; box-sizing: border-box; box-shadow: var(--card-shadow); display: flex; flex-direction: column; position: relative; overflow: hidden; transition: var(--transition); }
        .metric-card:hover { transform: translateY(-3px); box-shadow: 0 8px 20px rgba(0,0,0,0.12); }
        .metric-card::before { content: ''; position: absolute; top: 0; left: 0; width: 4px; height: 100%; background: #ccc; }
        .card-internal::before { background: var(--admin-primary); }
        .card-incoming::before { background: var(--success); }
        .card-outgoing::before { background: var(--info); }
        .card-total::before { background: var(--admin-dark); }
        
        .card-title { font-size: 12px; text-transform: uppercase; letter-spacing: 0.5px; color: var(--text-muted); font-weight: bold; margin-bottom: 8px; }
        .card-value { font-size: 32px; font-weight: bold; color: var(--admin-dark); margin: 0; }
        .card-subtext { font-size: 11px; color: var(--text-muted); margin-top: 10px; background: #f8f9fa; padding: 4px 8px; border-radius: 4px; width: fit-content; }

        .dashboard-row { display: flex; gap: 25px; flex-wrap: wrap; }
        .content-panel { background: white; padding: 22px; border-radius: 12px; box-shadow: var(--card-shadow); box-sizing: border-box; }
        .panel-left { flex: 2; min-width: 450px; }
        .panel-right { flex: 1; min-width: 280px; }
        .panel-title { margin-top: 0; font-size: 16px; font-weight: bold; color: var(--admin-dark); border-bottom: 1px solid #f1f1f1; padding-bottom: 12px; margin-bottom: 15px; display: flex; justify-content: space-between; align-items: center; }

        .search-bar-wrapper { margin-bottom: 15px; }
        .search-input { width: 100%; padding: 10px 15px; font-size: 14px; border: 1px solid #ced4da; border-radius: 6px; box-sizing: border-box; outline: none; }
        .search-input:focus { border-color: var(--admin-primary); box-shadow: 0 0 0 3px rgba(111,66,193,0.1); }
        
        .user-table-container { overflow-x: auto; }
        .user-dir-table { width: 100%; border-collapse: collapse; font-size: 14px; text-align: left; }
        .user-dir-table th { background: #f8f9fa; padding: 12px 10px; color: var(--text-muted); font-weight: 600; border-bottom: 2px solid #dee2e6; }
        .user-dir-table td { padding: 12px 10px; border-bottom: 1px solid #eee; vertical-align: middle; }
        .user-dir-table tr:hover { background-color: #faf8ff; }
        
        .pagination-container { display: flex; align-items: center; justify-content: space-between; padding-top: 15px; border-top: 1px solid #eee; margin-top: 15px; flex-wrap: wrap; gap: 10px; }
        .pagination-info { font-size: 13px; color: var(--text-muted); }
        .pagination-buttons { display: flex; gap: 5px; }
        .page-link-btn { display: inline-block; padding: 6px 12px; font-size: 13px; text-decoration: none; border-radius: 4px; border: 1px solid #ced4da; color: var(--admin-dark); background: white; font-weight: 500; transition: var(--transition); }
        .page-link-btn:hover { background: #f8f9fa; border-color: #b5bbc1; }
        .page-link-btn.active-page { background: var(--admin-primary); color: white; border-color: var(--admin-primary); }
        .page-link-btn.disabled-btn { background: #e9ecef; color: #adb5bd; border-color: #dee2e6; pointer-events: none; }

        .action-flex-container { display: flex; gap: 5px; justify-content: flex-end; align-items: center; }
        .btn-mini-action { padding: 5px 10px; font-size: 11px; font-weight: bold; color: white; background: var(--admin-primary); text-decoration: none; border-radius: 4px; border: none; cursor: pointer; transition: var(--transition); }
        .btn-mini-action:hover { background: var(--primary-hover); }
        .btn-mini-edit { background: var(--info); }
        .btn-mini-edit:hover { opacity: 0.9; }
        .btn-mini-delete { background: var(--danger); }
        .btn-mini-delete:hover { opacity: 0.9; }
        
        .btn-add-trigger { background: var(--success); color: white; border: none; padding: 8px 14px; border-radius: 6px; font-size: 13px; font-weight: bold; cursor: pointer; transition: var(--transition); }
        .btn-add-trigger:hover { opacity: 0.9; }

        .modal-overlay { position: fixed; top:0; left:0; width:100%; height:100%; background: rgba(0,0,0,0.5); display: none; justify-content: center; align-items: center; z-index: 1000; }
        .modal-card { background: white; padding: 25px; border-radius: 10px; box-shadow: 0 10px 25px rgba(0,0,0,0.2); width: 380px; max-width: 90%; }
        .modal-card h3 { margin-top: 0; margin-bottom: 20px; color: var(--admin-dark); }
        .modal-field { margin-bottom: 15px; }
        .modal-field label { display: block; font-size: 13px; font-weight: bold; margin-bottom: 5px; color: #555; }
        .modal-field input, .modal-field select { width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 4px; box-sizing: border-box; }
        .modal-actions { display: flex; justify-content: flex-end; gap: 10px; margin-top: 20px; }
        .btn-cancel { background: #e9ecef; color: #333; border: none; padding: 8px 15px; border-radius: 4px; cursor: pointer; font-weight: bold; }
        
        .timeline { position: relative; padding-left: 20px; margin: 0; list-style: none; }
        .timeline::before { content: ''; position: absolute; left: 4px; top: 0; height: 100%; width: 2px; background: #e9ecef; }
        .timeline-item { position: relative; margin-bottom: 22px; }
        .timeline-item::before { content: ''; position: absolute; left: -20px; top: 4px; width: 10px; height: 10px; border-radius: 50%; background: #ced4da; border: 2px solid white; }
        .item-internal::before { background: var(--admin-primary); }
        .item-incoming::before { background: var(--success); }
        .item-outgoing::before { background: var(--info); }
        .timeline-time { font-size: 11px; color: var(--text-muted); }
        .timeline-body { font-size: 13px; font-weight: 600; color: #333; margin-top: 3px; }
        .timeline-meta { font-size: 11px; color: var(--text-muted); margin-top: 2px; }

        .user-identity-card { text-align: center; background: var(--bg-light); border: 1px dashed #ced4da; padding: 20px; border-radius: 10px; }
        .user-identity-card img { width: 70px; height: 70px; border-radius: 50%; object-fit: cover; margin-bottom: 10px; border: 2px solid #ccc; }
        .identity-name { font-size: 15px; font-weight: bold; margin: 0; color: var(--admin-dark); }
        .identity-pos { font-size: 12px; color: var(--text-muted); margin: 3px 0 0; }
        .btn-reset { display: block; text-align: center; margin-top: 15px; padding: 8px; background: #e9ecef; color: var(--admin-dark); text-decoration: none; border-radius: 6px; font-size: 12px; font-weight: bold; transition: var(--transition); }
        .btn-reset:hover { background: #dc3545; color: white; }

        .alert-banner { padding: 12px 20px; border-radius: 8px; margin-bottom: 10px; font-size: 14px; font-weight: bold; text-align: center; }
        .alert-success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .alert-error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }

        @media (max-width: 992px) {
            body { flex-direction: column; }
            .sidebar { width: 100%; min-height: auto; text-align: center; }
            .main-content { padding: 15px; height: auto; }
            .admin-header, .inspector-control-box { flex-direction: column; text-align: center; gap: 15px; }
            .dashboard-row { flex-direction: column; }
            .panel-left, .panel-right { min-width: 100%; }
        }
    </style>


</head>
<body>

    <div class="sidebar">
        <img src="uploads/<?php echo htmlspecialchars($admin_user['profile_pic'] ?: 'default.png'); ?>" alt="Admin Profile">
        <div style="text-align:center; font-weight:bold; margin-bottom:20px;">
            <?php echo htmlspecialchars($admin_user['name']); ?><br>
            <small style="color: #c49eff; font-weight:bold; letter-spacing:0.5px;"><?php echo htmlspecialchars($admin_user['position']); ?></small>
        </div>
        <a href="admin_dashboard.php" class="active">Admin Dashboard</a>
        <a href="logout.php" style="color: #ff6b6b; margin-top:50px;">Log Out</a>
    </div>

    <div class="main-content">
        
        <div class="admin-header">
            <div class="admin-title">
                <h1>Executive Administration Portal</h1>
                <p>System Control Panel | Total Operations Overview & Performance Metrics.</p>
            </div>
            <div style="font-size: 13px; font-weight: 600; background: #f1f3f5; padding: 8px 15px; border-radius: 8px;">
                Active Staff Volume: <span style="color:var(--admin-primary); font-weight:bold;"><?php echo $global_user_count; ?> Users</span>
            </div>
        </div>

        <?php if($insert_success): ?><div class="alert-banner alert-success"><?php echo $insert_success; ?></div><?php endif; ?>
        <?php if($insert_error): ?><div class="alert-banner alert-error"><?php echo $insert_error; ?></div><?php endif; ?>

        <div class="inspector-control-box">
            <div class="selector-group">
                <label for="userInspectSelect">View Dashboard As (Select Employee):</label>
                <select id="userInspectSelect" onchange="switchInspectionPersona(this.value)">
                    <option value="0">--- GLOBAL SYSTEM SCOPE (All Data) ---</option>
                    <?php 
                    $full_dropdown_res = $conn->query("SELECT id, name, position FROM user WHERE position != 'Admin' AND position != 'administrator' ORDER BY name ASC");
                    if($full_dropdown_res && $full_dropdown_res->num_rows > 0):
                        while($u = $full_dropdown_res->fetch_assoc()):
                            $selected = ($target_user_id === intval($u['id'])) ? 'selected' : '';
                            echo "<option value='".intval($u['id'])."' $selected>".htmlspecialchars($u['name'])." (".htmlspecialchars($u['position']).")</option>";
                        endwhile;
                    endif;
                    ?>
                </select>
            </div>
            <div>
                <?php if($is_inspect_mode): ?>
                    <span class="inspect-badge" style="background:#ffc107; color:#000;">🕵️ User Inspection View Active</span>
                <?php else: ?>
                    <span class="inspect-badge">🌐 Global Operations Scope</span>
                <?php endif; ?>
            </div>
        </div>

        <div class="metrics-grid">
            <div class="metric-card card-internal">
                <div class="card-title">Internal Transactions</div>
                <div class="card-value"><?php echo $internal_count; ?></div>
                <div class="card-subtext">Scope volume entries</div>
            </div>
            <div class="metric-card card-incoming">
                <div class="card-title">Incoming Records</div>
                <div class="card-value"><?php echo $incoming_count; ?></div>
                <div class="card-subtext">Scope volume entries</div>
            </div>
            <div class="metric-card card-outgoing">
                <div class="card-title">Outgoing Records</div>
                <div class="card-value"><?php echo $outgoing_count; ?></div>
                <div class="card-subtext">Scope volume entries</div>
            </div>
            <div class="metric-card card-total">
                <div class="card-title">Total Processed Documents</div>
                <div class="card-value"><?php echo $total_docs; ?></div>
                <div class="card-subtext">Accumulated data volume</div>
            </div>
        </div>

        <div class="content-panel" style="width: 100%; box-sizing: border-box;">
            <div class="panel-title">
                <span>Platform User Directory</span>
                <button class="btn-add-trigger" onclick="openAddUserModal()">+ Add New User</button>
            </div>
            <div class="search-bar-wrapper">
                <input type="text" id="dirSearchInput" class="search-input" onkeyup="filterUserDirectory()" placeholder="🔍 Type a user's name, username, or position to filter local page list...">
            </div>
            <div class="user-table-container">
                <table class="user-dir-table" id="directoryTable">
                    <thead>
                        <tr>
                            <th>Avatar</th>
                            <th>Employee Name</th>
                            <th>Username</th>
                            <th>Assigned Position</th>
                            <th style="text-align: right;">Action Control</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        if($users_res && $users_res->num_rows > 0):
                            while($row = $users_res->fetch_assoc()):
                                $u_pic = isset($row['profile_pic']) && $row['profile_pic'] != '' ? $row['profile_pic'] : 'default.png';
                        ?>
                            <tr>
                                <td style="width: 40px;"><img src="uploads/<?php echo htmlspecialchars($u_pic); ?>" style="width:32px; height:32px; border-radius:50%; object-fit:cover; border:1px solid #ddd; display:block;"></td>
                                <td><strong><?php echo htmlspecialchars($row['name']); ?></strong></td>
                                <td><code><?php echo htmlspecialchars($row['username']); ?></code></td>
                                <td><span style="background: #eef1f6; padding: 3px 8px; border-radius:4px; font-size:12px; font-weight:600;"><?php echo htmlspecialchars($row['position']); ?></span></td>
                                <td style="text-align: right;">
                                    <div class="action-flex-container">
                                        <button class="btn-mini-action" onclick="switchInspectionPersona(<?php echo $row['id']; ?>)">Inspect</button>
                                        <button class="btn-mini-action btn-mini-edit" onclick="openEditUserModal(<?php echo $row['id']; ?>, '<?php echo htmlspecialchars(addslashes($row['name'])); ?>', '<?php echo htmlspecialchars(addslashes($row['username'])); ?>', '<?php echo htmlspecialchars(addslashes($row['position'])); ?>')">Edit</button>
                                        <button class="btn-mini-action btn-mini-delete" onclick="confirmDeleteUser(<?php echo $row['id']; ?>, '<?php echo htmlspecialchars(addslashes($row['name'])); ?>')">Delete</button>
                                    </div>
                                </td>
                            </tr>
                        <?php 
                            endwhile;
                        else:
                            echo "<tr><td colspan='5' style='text-align:center; color:var(--text-muted); padding: 15px;'>No system users found on this page track index.</td></tr>";
                        endif;
                        ?>
                    </tbody>
                </table>
            </div>

            <div class="pagination-container">
                <div class="pagination-info">
                    Showing rows <strong><?php echo min($offset_value + 1, $global_user_count); ?></strong> to <strong><?php echo min($offset_value + $limit_per_page, $global_user_count); ?></strong> of <strong><?php echo $global_user_count; ?></strong> registered employees.
                </div>
                <div class="pagination-buttons">
                    <?php 
                    $inspect_url_param = $is_inspect_mode ? "&inspect_user=" . $target_user_id : "";
                    
                    $prev_page = $current_page - 1;
                    $prev_class = ($current_page <= 1) ? "disabled-btn" : "";
                    echo "<a href='admin_dashboard.php?page=$prev_page$inspect_url_param' class='page-link-btn $prev_class'>« Prev</a>";
                    
                    for($p = 1; $p <= $total_pages; $p++):
                        $active_class = ($p === $current_page) ? "active-page" : "";
                        echo "<a href='admin_dashboard.php?page=$p$inspect_url_param' class='page-link-btn $active_class'>$p</a>";
                    endfor;
                    
                    $next_page = $current_page + 1;
                    $next_class = ($current_page >= $total_pages) ? "disabled-btn" : "";
                    echo "<a href='admin_dashboard.php?page=$next_page$inspect_url_param' class='page-link-btn $next_class'>Next »</a>";
                    ?>
                </div>
            </div>
        </div>

     <div class="dashboard-row">
    <div class="content-panel panel-left">
        <h3 class="panel-title">
            <?php echo $is_inspect_mode ? "Recent Documents Logged By This User" : "Global System Activity Stream Feed"; ?>
        </h3>
        <ul class="timeline">
            <?php
            // Setup target query clauses
            $where_timeline = $is_inspect_mode ? " WHERE uploaded_by = $target_user_id AND deleted_at IS NULL " : " WHERE deleted_at IS NULL ";
            
            // FAIL-SAFE UNION: Casts doc_date to DATETIME explicitly to guarantee unified type formatting
            $timeline_sql = "SELECT * FROM (
                                SELECT 'internal' as src_type, tracking_number, subject, CAST(doc_date AS DATETIME) as normalized_date, id 
                                FROM internal $where_timeline
                                
                                UNION ALL
                                
                                SELECT 'incoming' as src_type, tracking_number, subject, CAST(doc_date AS DATETIME) as normalized_date, id 
                                FROM incoming $where_timeline
                                
                                UNION ALL
                                
                                SELECT 'outgoing' as src_type, tracking_number, subject, CAST(doc_date AS DATETIME) as normalized_date, id 
                                FROM outgoing $where_timeline
                             ) AS combined_stream 
                             ORDER BY normalized_date DESC, id DESC 
                             LIMIT 5";
            
            $feed_res = $conn->query($timeline_sql);
            if($feed_res && $feed_res->num_rows > 0):
                while($item = $feed_res->fetch_assoc()):
                    $class = "item-" . $item['src_type'];
                    $lbl = ucfirst($item['src_type']);
            ?>
                <li class="timeline-item <?php echo $class; ?>">
                    <div class="timeline-time"><?php echo date("F j, Y", strtotime($item['normalized_date'])); ?></div>
                    <div class="timeline-body"><?php echo htmlspecialchars($item['subject']); ?></div>
                    <div class="timeline-meta">Tracking ID: <strong><?php echo htmlspecialchars($item['tracking_number']); ?></strong> | Module: <?php echo $lbl; ?></div>
                </li>
            <?php 
                endwhile;
            else:
                echo "<p style='color:var(--text-muted); text-align:center; padding:20px;'>No database logs matching selected scope options found.</p>";
            endif;
            ?>
        </ul>
    </div>
            <div class="content-panel panel-right">
                <h3 class="panel-title">Inspection Summary Profile</h3>
                <?php if($is_inspect_mode && $inspected_user_meta): ?>
                    <div class="user-identity-card">
                        <img src="uploads/<?php echo htmlspecialchars($inspected_user_meta['profile_pic'] ?: 'default.png'); ?>" alt="User Avatar">
                        <div class="identity-name"><?php echo htmlspecialchars($inspected_user_meta['name']); ?></div>
                        <div class="identity-pos"><?php echo htmlspecialchars($inspected_user_meta['position']); ?></div>
                        <div style="margin-top: 15px; font-size:12px; background:#fff; padding:6px; border-radius:4px; border:1px solid #e1ceff; font-weight:500;">
                            Responsible for <strong style="color:var(--admin-primary);"><?php echo $total_docs; ?></strong> documents in this system.
                        </div>
                        <a href="admin_dashboard.php" class="btn-reset">Exit User View Mode</a>
                    </div>
                <?php else: ?>
                    <div style="text-align: center; color: var(--text-muted); padding: 25px 10px; font-size: 13px; line-height: 1.5;">
                        <div style="font-size: 24px; margin-bottom: 10px;">🌐</div>
                        You are currently viewing data across the entire platform. Use the menu selection or directory list above to look into a specific user's metrics.
                    </div>
                <?php endif; ?>
            </div>
        </div>
<?php
// Add this near your top PHP logic blocks where dashboard data is fetched
$deleted_res = $conn->query("SELECT src_type, tracking_number, subject, deleted_at FROM deleted_document_logs ORDER BY deleted_at DESC LIMIT 10");
?>
        <div class="content-panel" style="width: 100%; box-sizing: border-box; margin-top: 20px;">
            <div class="panel-title">
                <span>⚠️ Recently Terminated System Documents</span>
                <small style="font-weight: normal; font-size: 12px; color: #ff6b6b; margin-left: 10px;">(Last 10 Actions)</small>
            </div>
            <div class="user-table-container">
                <table class="user-dir-table" style="width: 100%;">
                    <thead>
                        <tr>
                            <th>Origin Module</th>
                            <th>Tracking Index Code</th>
                            <th>Document Subject Description</th>
                            <th style="text-align: right;">Purge Timestamp</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        if($deleted_res && $deleted_res->num_rows > 0):
                            while($del_row = $deleted_res->fetch_assoc()):
                                $badge_color = '#6c757d';
                                if($del_row['src_type'] == 'Internal') $badge_color = '#4dabf7';
                                if($del_row['src_type'] == 'Incoming') $badge_color = '#2b8a3e';
                                if($del_row['src_type'] == 'Outgoing') $badge_color = '#e67e22';
                        ?>
                            <tr>
                                <td>
                                    <span style="background: <?php echo $badge_color; ?>; color: white; padding: 3px 8px; border-radius:4px; font-size:11px; font-weight:700; text-transform: uppercase;">
                                        <?php echo $del_row['src_type']; ?>
                                    </span>
                                </td>
                                <td><code style="font-weight: bold; color: #c92a2a;"><?php echo htmlspecialchars($del_row['tracking_number']); ?></code></td>
                                <td><span style="color: #495057; font-style: italic;"><?php echo htmlspecialchars($del_row['subject']); ?></span></td>
                                <td style="text-align: right; font-size: 13px; color: #868e96; font-weight: 500;">
                                    <?php echo date("M d, Y – h:i A", strtotime($del_row['deleted_at'])); ?>
                                </td>
                            </tr>
                        <?php 
                            endwhile;
                        else:
                            echo "<tr><td colspan='4' style='text-align:center; color:#868e96; padding: 20px; font-style: italic;'>No recently deleted logs found in current scope index.</td></tr>";
                        endif;
                        ?>
                    </tbody>
                </table>
            </div>
        </div>
        
    </div>

    <div class="modal-overlay" id="addUserModal">
        <div class="modal-card">
            <h3>Register New User Account</h3>
            <form method="POST" action="admin_dashboard.php?page=<?php echo $current_page; ?>" enctype="multipart/form-data">
                <input type="hidden" name="action_add_user" value="1">
                <div class="modal-field">
                    <label>Full Employee Name</label>
                    <input type="text" name="new_name" placeholder="e.g. John Doe" required>
                </div>
                <div class="modal-field">
                    <label>System Username</label>
                    <input type="text" name="new_username" placeholder="e.g. johndoe12" required>
                </div>
                <div class="modal-field">
                    <label>Account Password</label>
                    <input type="password" name="new_password" placeholder="••••••••" required>
                </div>
                <div class="modal-field">
                    <label>Assigned Department Position</label>
                    <input type="text" name="new_position" placeholder="e.g. Clerk, Analyst" required>
                </div>
                <div class="modal-field">
                    <label>Profile Picture Upload</label>
                    <input type="file" name="new_profile_pic" accept="image/*" style="padding:5px 0;">
                </div>
                <div class="modal-actions">
                    <button type="button" class="btn-cancel" onclick="closeAddUserModal()">Cancel</button>
                    <button type="submit" class="btn-mini-action" style="padding:10px 20px; font-size:13px;">Save Account</button>
                </div>
            </form>
        </div>
    </div>

    <div class="modal-overlay" id="editUserModal">
        <div class="modal-card">
            <h3>Modify User Credentials</h3>
            <form method="POST" action="admin_dashboard.php?page=<?php echo $current_page; ?><?php echo $is_inspect_mode ? '&inspect_user='.$target_user_id : ''; ?>" enctype="multipart/form-data">
                <input type="hidden" name="action_edit_user" value="1">
                <input type="hidden" name="edit_id" id="edit_id">
                
                <div class="modal-field">
                    <label>Full Employee Name</label>
                    <input type="text" name="edit_name" id="edit_name" required>
                </div>
                <div class="modal-field">
                    <label>System Username</label>
                    <input type="text" name="edit_username" id="edit_username" required>
                </div>
                <div class="modal-field">
                    <label>Account Password (Leave blank to keep current)</label>
                    <input type="password" name="edit_password" placeholder="••••••••">
                </div>
                <div class="modal-field">
                    <label>Assigned Department Position</label>
                    <input type="text" name="edit_position" id="edit_position" required>
                </div>
                <div class="modal-field">
                    <label>Update Profile Picture (Optional)</label>
                    <input type="file" name="edit_profile_pic" accept="image/*" style="padding:5px 0;">
                </div>
                <div class="modal-actions">
                    <button type="button" class="btn-cancel" onclick="closeEditUserModal()">Cancel</button>
                    <button type="submit" class="btn-mini-action btn-mini-edit" style="padding:10px 20px; font-size:13px; color:white;">Save Changes</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function switchInspectionPersona(userId) {
            if (parseInt(userId) === 0) {
                window.location.href = 'admin_dashboard.php';
            } else {
                window.location.href = 'admin_dashboard.php?page=<?php echo $current_page; ?>&inspect_user=' + userId;
            }
        }

        function filterUserDirectory() {
            var input, filter, table, tr, td, i, j, txtValue, matchFound;
            input = document.getElementById("dirSearchInput");
            filter = input.value.toUpperCase();
            table = document.getElementById("directoryTable");
            tr = table.getElementsByTagName("tr");

            for (i = 1; i < tr.length; i++) {
                td = tr[i].getElementsByTagName("td");
                matchFound = false;
                for (j = 1; j < td.length - 1; j++) {
                    if (td[j]) {
                        txtValue = td[j].textContent || td[j].innerText;
                        if (txtValue.toUpperCase().indexOf(filter) > -1) {
                            matchFound = true;
                            break;
                        }
                    }
                }
                tr[i].style.display = matchFound ? "" : "none";
            }
        }

        function openAddUserModal() { document.getElementById('addUserModal').style.display = 'flex'; }
        function closeAddUserModal() { document.getElementById('addUserModal').style.display = 'none'; }

        function openEditUserModal(id, name, username, position) {
            document.getElementById('edit_id').value = id;
            document.getElementById('edit_name').value = name;
            document.getElementById('edit_username').value = username;
            document.getElementById('edit_position').value = position;
            document.getElementById('editUserModal').style.display = 'flex';
        }
        function closeEditUserModal() { document.getElementById('editUserModal').style.display = 'none'; }

        function confirmDeleteUser(id, name) {
            if (confirm("Are you absolutely sure you want to permanently delete user '" + name + "'? This operation cannot be undone and will purge their session credentials.")) {
                window.location.href = 'admin_dashboard.php?page=<?php echo $current_page; ?>&delete_user=' + id;
            }
        }
    </script>
</body>
</html>