<?php
session_start();
include 'db.php';
if (!isset($_SESSION['user'])) { header("Location: index.php"); exit(); }
$user = $_SESSION['user'];
$current_user_id = $user['id'];

// Fetch other system users along with their real-time status values
$users_query = $conn->prepare("SELECT id, name, position, profile_pic, status FROM user WHERE id != ?");
$users_query->bind_param("i", $current_user_id);
$users_query->execute();
$other_users = $users_query->get_result();

$active_chat_user_id = isset($_GET['chat_with']) ? (int)$_GET['chat_with'] : 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Communication Hub Dashboard</title>
    <style>
        :root {
            --primary: #007bff;
            --primary-hover: #0056b3;
            --success: #28a745;
            --info: #17a2b8;
            --warning: #ffc107;
            --danger: #dc3545;
            --dark: #343a40;
            --bg-light: #f8f9fa;
            --text-muted: #6c757d;
            --card-shadow: 0 4px 6px rgba(0, 0, 0, 0.05), 0 1px 3px rgba(0, 0, 0, 0.1);
            --transition: all 0.25s ease-in-out;
        }

        body { font-family: 'Segoe UI', Arial, sans-serif; margin: 0; display: flex; background: #f1f3f6; color: #333; }
        
        .sidebar { width: 260px; background: var(--dark); color: white; min-height: 100vh; padding: 20px 10px; box-sizing: border-box; flex-shrink: 0; }
        .sidebar img { width: 80px; height: 80px; border-radius: 50%; display:block; margin: 0 auto 10px; object-fit: cover; border: 2px solid var(--primary); }
        .sidebar a { display: block; color: #c2c7d0; padding: 12px; text-decoration: none; border-radius: 4px; margin-bottom: 5px; font-size: 14px; transition: var(--transition); }
        .sidebar a:hover, .sidebar a.active { background: var(--primary); color: white; }
        
        /* Dropdown Status Selector Menu */
        .status-select { background: #495057; color: white; border: 1px solid #6c757d; padding: 5px; border-radius: 4px; font-size: 12px; margin-top: 5px; width: 80%; cursor: pointer; }

        .main-content { flex: 1; padding: 30px; box-sizing: border-box; display: flex; flex-direction: column; gap: 25px; overflow-y: auto; height: 100vh; }
        .welcome-header { display: flex; justify-content: space-between; align-items: center; background: white; padding: 20px 25px; border-radius: 10px; box-shadow: var(--card-shadow); }
        
        .dashboard-row { display: flex; gap: 25px; flex-wrap: wrap; }
        .content-panel { background: white; padding: 20px; border-radius: 10px; box-shadow: var(--card-shadow); box-sizing: border-box; }
        .panel-flex-left { flex: 2; min-width: 400px; display: flex; flex-direction: column; height: 530px; }
        .panel-flex-right { flex: 1; min-width: 280px; height: 530px; overflow-y: auto; }
        .panel-title { margin-top: 0; font-size: 16px; font-weight: bold; color: var(--dark); border-bottom: 1px solid #eee; padding-bottom: 12px; margin-bottom: 15px; }

        /* Integrated Chat Layout components */
        .chat-container { display: flex; flex: 1; overflow: hidden; border: 1px solid #e9ecef; border-radius: 8px; }
        .chat-sidebar { width: 220px; background: var(--bg-light); border-right: 1px solid #e9ecef; overflow-y: auto; }
        
        /* User item containing statuses and notification badges */
        .user-tab { padding: 12px; font-size: 13px; cursor: pointer; border-bottom: 1px solid #e2e5e8; display: flex; align-items: center; justify-content: space-between; color: var(--dark); text-decoration: none; position: relative; }
        .user-tab:hover, .user-tab.active { background: #e2e8f0; }
        
        /* Status Dot Indicator Classes */
        .status-dot { width: 10px; height: 10px; border-radius: 50%; display: inline-block; margin-right: 8px; }
        .status-online { background-color: var(--success); }
        .status-busy { background-color: var(--warning); }
        .status-offline { background-color: var(--danger); }

        /* Unread Notification Red Badges */
        .msg-badge { background-color: var(--danger); color: white; border-radius: 10px; padding: 2px 7px; font-size: 10px; font-weight: bold; display: none; }

        .chat-window { flex: 1; display: flex; flex-direction: column; background: #fff; }
        .chat-messages { flex: 1; padding: 15px; overflow-y: auto; background: #fdfdfd; display: flex; flex-direction: column; gap: 10px; }
        
        .msg-bubble { max-width: 70%; padding: 10px 14px; border-radius: 14px; font-size: 13px; line-height: 1.4; word-wrap: break-word; }
        .msg-sent { background: var(--primary); color: white; align-self: flex-end; border-bottom-right-radius: 2px; }
        .msg-received { background: #e9ecef; color: var(--dark); align-self: flex-start; border-bottom-left-radius: 2px; }
        .msg-file-link { color: inherit; font-weight: bold; text-decoration: underline; display: block; margin-top: 5px; }

        .chat-input-area { padding: 10px; border-top: 1px solid #e9ecef; display: flex; gap: 8px; align-items: center; }
        .chat-input-area input[type="text"] { flex: 1; padding: 10px; border: 1px solid #ced4da; border-radius: 4px; outline: none; }
        .chat-input-area button { background: var(--primary); color: white; border: none; padding: 10px 15px; border-radius: 4px; cursor: pointer; font-weight: bold; }
        .file-upload-label { font-size: 18px; cursor: pointer; padding: 5px 10px; color: var(--text-muted); border: 1px solid #ced4da; border-radius: 4px; background: #fff; }
        .file-upload-label:hover { color: var(--dark); background: #eee; }
        #chatFileInput { display: none; }
        #fileAttachedIndicator { font-size: 11px; color: var(--success); padding: 0 5px; display: none; }
    </style>
</head>
<body>

    <div class="sidebar">
        <img src="uploads/<?php echo htmlspecialchars($user['profile_pic'] ?: 'default.png'); ?>" alt="Profile">
        <div style="text-align:center; font-weight:bold; margin-bottom:15px;">
            <?php echo htmlspecialchars($user['name']); ?><br>
            <small style="color:#a8a8a8;"><?php echo htmlspecialchars($user['position']); ?></small>
            
            <select class="status-select" id="myStatusSelector" onchange="changeMyStatus(this.value)">
                <option value="online">🟢 Online</option>
                <option value="busy">🟡 Busy</option>
                <option value="offline">🔴 Offline</option>
            </select>
        </div>
        
        <a href="user_dashboard.php" class="active">Dashboard / Chat Hub</a>
        <a href="transactions_dashboard.php">Transactions Dashboard</a>
        <a href="internal_transactions.php">Internal Transactions</a>
        <a href="outgoing_transactions.php">Outgoing Transactions</a>
        <a href="incoming_transactions.php">Incoming Transactions</a>
        <a href="employee_history.php">Employee Status</a>
        <a href="logout.php" style="color: #ff6b6b; margin-top:30px;">Log Out</a>
    </div>

    <div class="main-content">
        <div class="welcome-header">
            <div class="welcome-text">
                <h1>Welcome Back, <?php echo htmlspecialchars(explode(' ', $user['name'])[0]); ?>!</h1>
                <p>Collaborate in real-time with status filters, unread track badges, and file drop support.</p>
            </div>
        </div>

        <div class="dashboard-row">
            <div class="content-panel panel-flex-left">
                <h3 class="panel-title">Direct Messages Workspace</h3>
                <div class="chat-container">
                    <div class="chat-sidebar">
                        <?php 
                        $first_user_id = 0;
                        while($row = $other_users->fetch_assoc()): 
                            if($first_user_id === 0) $first_user_id = $row['id'];
                            $active_cls = ($active_chat_user_id == $row['id'] || ($active_chat_user_id == 0 && $first_user_id == $row['id'])) ? 'active' : '';
                            if($active_cls === 'active' && $active_chat_user_id == 0) $active_chat_user_id = $row['id'];
                            
                            // Map SQL database status string to style colors
                            $status_class = 'status-' . ($row['status'] ?: 'online');
                        ?>
                            <a href="user_dashboard.php?chat_with=<?php echo $row['id']; ?>" class="user-tab <?php echo $active_cls; ?>">
                                <div style="display:flex; align-items:center;">
                                    <span class="status-dot <?php echo $status_class; ?>"></span>
                                    <div>
                                        <strong><?php echo htmlspecialchars($row['name']); ?></strong>
                                        <div style="font-size:10px; color:var(--text-muted);"><?php echo htmlspecialchars($row['position']); ?></div>
                                    </div>
                                </div>
                                <span class="msg-badge" id="badge_user_<?php echo $row['id']; ?>">0</span>
                            </a>
                        <?php endwhile; ?>
                    </div>
                    
                    <div class="chat-window">
                        <div class="chat-messages" id="chatBoxContainer">
                            </div>
                        
                        <form class="chat-input-area" id="chatInputForm" enctype="multipart/form-data">
                            <input type="hidden" id="receiverId" value="<?php echo $active_chat_user_id; ?>">
                            
                            <label for="chatFileInput" class="file-upload-label" title="Attach file">&#128190;</label>
                            <input type="file" id="chatFileInput" name="chat_file" onchange="indicateFileSelected()">
                            <span id="fileAttachedIndicator" title="Click to clear file" onclick="clearFileSelection()">📄 File Ready</span>

                            <input type="text" id="messageText" placeholder="Type your message here..." autocomplete="off">
                            <button type="submit">Send</button>
                        </form>
                    </div>
                </div>
            </div>

            <div class="content-panel panel-flex-right">
                <h3 class="panel-title">System Activity Alerts</h3>
                <div id="notificationListWrapper"></div>
            </div>
        </div>
    </div>

    <script>
        const currentUserId = <?php echo $current_user_id; ?>;
        const chatWithId = document.getElementById('receiverId').value;

        // Update the user's status indicator dropdown selection box
        function changeMyStatus(val) {
            const fd = new FormData();
            fd.append('status', val);
            fetch('chat_gateway.php?action=update_status', { method: 'POST', body: fd });
        }

        // Display text notice confirming standard attachment processing
        function indicateFileSelected() {
            const input = document.getElementById('chatFileInput');
            const fileIndicator = document.getElementById('fileAttachedIndicator');
            if(input.files.length > 0) {
                fileIndicator.style.display = 'inline-block';
                fileIndicator.innerText = "📄 " + input.files[0].name;
            }
        }

        // Wipe and cancel selected file attachment configurations
        function clearFileSelection() {
            document.getElementById('chatFileInput').value = "";
            document.getElementById('fileAttachedIndicator').style.display = 'none';
        }

        // Pull active chat timeline records tracking links
        function refreshChatMessages() {
            if(!chatWithId) return;
            fetch(`chat_gateway.php?action=fetch_messages&with=${chatWithId}`)
                .then(res => res.json())
                .then(messages => {
                    const container = document.getElementById('chatBoxContainer');
                    let htmlPayload = "";
                    messages.forEach(msg => {
                        const classStyle = (msg.sender_id == currentUserId) ? 'msg-sent' : 'msg-received';
                        let fileMarkup = "";
                        
                        // Parse inside chat frame if structural indicator confirms data is a file link object
                        if(msg.is_file == 1) {
                            const filename = msg.file_path.split('/').pop().substring(11); // Cleans out timestamp payload
                            fileMarkup = `<br><a href="${msg.file_path}" target="_blank" class="msg-file-link">&#128190; Download: ${filename}</a>`;
                        }
                        
                        htmlPayload += `<div class="msg-bubble ${classStyle}">${msg.message} ${fileMarkup}</div>`;
                    });
                    
                    const isAtBottom = container.scrollHeight - container.clientHeight <= container.scrollTop + 60;
                    container.innerHTML = htmlPayload;
                    if(isAtBottom) container.scrollTop = container.scrollHeight;
                });
        }

        // Loop checks mapping any active closed channels containing unread messages to red badges
        function checkUnreadBadges() {
            fetch('chat_gateway.php?action=unread_counts')
                .then(res => res.json())
                .then(counts => {
                    // Reset all counters to zero visibility defaults 
                    document.querySelectorAll('.msg-badge').forEach(badge => badge.style.display = 'none');
                    
                    counts.forEach(item => {
                        // Display unread notification badge if chat with that specific user is not currently open
                        if(item.sender_id != chatWithId) {
                            const targetBadge = document.getElementById(`badge_user_${item.sender_id}`);
                            if(targetBadge) {
                                targetBadge.innerText = item.count;
                                targetBadge.style.display = 'inline-block';
                            }
                        }
                    });
                });
        }

        function refreshNotificationAlerts() {
            fetch('chat_gateway.php?action=fetch_notifications')
                .then(res => res.json())
                .then(data => {
                    const listWrapper = document.getElementById('notificationListWrapper');
                    if(data.length === 0) {
                        listWrapper.innerHTML = '<p style="color:var(--text-muted); text-align:center; padding:20px;">No alerts logged.</p>';
                        return;
                    }
                    let markup = '<ul style="list-style:none; padding:0; margin:0;">';
                    data.forEach(item => {
                        markup += `<li style="padding:12px; border-bottom:1px solid #f1f3f6; font-size:13px;">
                                    <strong>${item.title}</strong><div>${item.message}</div>
                                   </li>`;
                    });
                    markup += '</ul>';
                    listWrapper.innerHTML = markup;
                });
        }

        // Form submission logic handling clean routing of files and strings over fetch API
        document.getElementById('chatInputForm').addEventListener('submit', function(e) {
            e.preventDefault();
            const txtInput = document.getElementById('messageText');
            const fileInput = document.getElementById('chatFileInput');
            
            if(!txtInput.value.trim() && fileInput.files.length === 0) return;

            const formData = new FormData();
            formData.append('receiver_id', chatWithId);
            formData.append('message', txtInput.value.trim());
            if(fileInput.files.length > 0) {
                formData.append('chat_file', fileInput.files[0]);
            }

            fetch('chat_gateway.php?action=send_message', { method: 'POST', body: formData })
                .then(res => res.json())
                .then(status => {
                    if(status.success) {
                        txtInput.value = '';
                        clearFileSelection();
                        refreshChatMessages();
                    }
                });
        });

        // Initialize polling timers
        if(chatWithId > 0) {
            refreshChatMessages();
            setInterval(refreshChatMessages, 2000);
        }
        
        checkUnreadBadges();
        setInterval(checkUnreadBadges, 3000);

        refreshNotificationAlerts();
        setInterval(refreshNotificationAlerts, 5000);
    </script>
</body>
</html>