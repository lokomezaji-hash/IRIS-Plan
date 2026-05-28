<?php
session_start();
include 'db.php';
if (!isset($_SESSION['user'])) { header("Location: index.php"); exit(); }
$user = $_SESSION['user'];

// Fetch System Metrics for Counters
$internal_count = $conn->query("SELECT COUNT(*) as total FROM internal")->fetch_assoc()['total'] ?? 0;
$incoming_count = $conn->query("SELECT COUNT(*) as total FROM incoming")->fetch_assoc()['total'] ?? 0;
$outgoing_count = $conn->query("SELECT COUNT(*) as total FROM outgoing")->fetch_assoc()['total'] ?? 0;
$total_docs = $internal_count + $incoming_count + $outgoing_count;

// Fetch Today's Activity Volume Metrics
$date_today = date('Y-m-d');
$today_internal = $conn->query("SELECT COUNT(*) as total FROM internal WHERE DATE(doc_date) = '$date_today'")->fetch_assoc()['total'] ?? 0;
$today_incoming = $conn->query("SELECT COUNT(*) as total FROM incoming WHERE DATE(doc_date) = '$date_today'")->fetch_assoc()['total'] ?? 0;
$today_outgoing = $conn->query("SELECT COUNT(*) as total FROM outgoing WHERE DATE(doc_date) = '$date_today'")->fetch_assoc()['total'] ?? 0;
$today_total = $today_internal + $today_incoming + $today_outgoing;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Transactions Repository Dashboard</title>
    <style>
        :root {
            --primary: #007bff;
            --primary-hover: #0056b3;
            --success: #28a745;
            --info: #17a2b8;
            --warning: #ffc107;
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
        
        .main-content { flex: 1; padding: 30px; box-sizing: border-box; display: flex; flex-direction: column; gap: 25px; overflow-y: auto; height: 100vh; }
        .welcome-header { display: flex; justify-content: space-between; align-items: center; background: white; padding: 20px 25px; border-radius: 10px; box-shadow: var(--card-shadow); }
        .welcome-text h1 { margin: 0; font-size: 24px; color: var(--dark); }
        .welcome-text p { margin: 5px 0 0; color: var(--text-muted); font-size: 14px; }
        .live-badge { background: #e2f0d9; color: var(--success); padding: 6px 12px; border-radius: 20px; font-size: 12px; font-weight: bold; display: flex; align-items: center; gap: 6px; }
        .live-dot { width: 8px; height: 8px; background: var(--success); border-radius: 50%; display: inline-block; animation: pulse 1.5s infinite; }

        .metrics-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 20px; }
        .metric-card { background: white; border-radius: 10px; padding: 20px; box-sizing: border-box; box-shadow: var(--card-shadow); display: flex; flex-direction: column; position: relative; overflow: hidden; transition: var(--transform), box-shadow 0.25s; }
        .metric-card:hover { transform: translateY(-4px); box-shadow: 0 8px 15px rgba(0,0,0,0.1); }
        .metric-card::before { content: ''; position: absolute; top: 0; left: 0; width: 4px; height: 100%; background: #ccc; }
        .card-internal::before { background: var(--primary); }
        .card-incoming::before { background: var(--success); }
        .card-outgoing::before { background: var(--info); }
        .card-total::before { background: var(--dark); }
        
        .card-title { font-size: 12px; text-transform: uppercase; letter-spacing: 0.5px; color: var(--text-muted); font-weight: bold; margin-bottom: 10px; }
        .card-value { font-size: 28px; font-weight: bold; color: var(--dark); margin: 0; }
        .card-subtext { font-size: 11px; color: var(--text-muted); margin-top: 8px; display: flex; justify-content: space-between; }
        .trend-up { color: var(--success); font-weight: bold; }

        .dashboard-row { display: flex; gap: 25px; flex-wrap: wrap; }
        .content-panel { background: white; padding: 20px; border-radius: 10px; box-shadow: var(--card-shadow); box-sizing: border-box; }
        .panel-flex-left { flex: 2; min-width: 400px; }
        .panel-flex-right { flex: 1; min-width: 280px; }
        .panel-title { margin-top: 0; font-size: 16px; font-weight: bold; color: var(--dark); border-bottom: 1px solid #eee; padding-bottom: 12px; margin-bottom: 15px; }

        .action-grid { display: grid; grid-template-columns: 1fr; gap: 12px; }
        .action-btn { display: flex; align-items: center; justify-content: space-between; padding: 14px 20px; background: var(--bg-light); border: 1px solid #e9ecef; border-radius: 8px; text-decoration: none; color: var(--dark); font-weight: 600; font-size: 14px; transition: var(--transition); }
        .action-btn:hover { background: var(--primary); color: white; border-color: var(--primary); transform: translateX(3px); }

        .timeline { position: relative; padding-left: 20px; margin: 0; list-style: none; }
        .timeline::before { content: ''; position: absolute; left: 4px; top: 0; height: 100%; width: 2px; background: #e9ecef; }
        .timeline-item { position: relative; margin-bottom: 20px; }
        .timeline-item::before { content: ''; position: absolute; left: -20px; top: 4px; width: 10px; height: 10px; border-radius: 50%; background: #ced4da; border: 2px solid white; box-shadow: 0 0 0 2px #eee; }
        .item-internal::before { background: var(--primary); box-shadow: 0 0 0 2px #cce5ff; }
        .item-incoming::before { background: var(--success); box-shadow: 0 0 0 2px #d4edda; }
        .item-outgoing::before { background: var(--info); box-shadow: 0 0 0 2px #d1ecf1; }
        
        .timeline-time { font-size: 11px; color: var(--text-muted); }
        .timeline-body { font-size: 13px; font-weight: 500; color: #495057; margin-top: 3px; }
        .timeline-meta { font-size: 11px; color: var(--text-muted); margin-top: 2px; }

        @keyframes pulse {
            0% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(40, 167, 69, 0.4); }
            70% { transform: scale(1); box-shadow: 0 0 0 6px rgba(40, 167, 69, 0); }
            100% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(40, 167, 69, 0); }
        }
    </style>
</head>
<body>

    <div class="sidebar">
        <img src="uploads/<?php echo htmlspecialchars($user['profile_pic'] ?: 'default.png'); ?>" alt="Profile">
        <div style="text-align:center; font-weight:bold; margin-bottom:20px;">
            <?php echo htmlspecialchars($user['name']); ?><br>
            <small style="color:#a8a8a8;"><?php echo htmlspecialchars($user['position']); ?></small>
        </div>
        <a href="user_dashboard.php">Dashboard / Chat Hub</a>
        <a href="transactions_dashboard.php" class="active">Transactions Dashboard</a>
        <a href="internal_transactions.php">Internal Transactions</a>
        <a href="outgoing_transactions.php">Outgoing Transactions</a>
        <a href="incoming_transactions.php">Incoming Transactions</a>
        <a href="employee_history.php">Employee Status</a>
        <a href="logout.php" style="color: #ff6b6b; margin-top:30px;">Log Out</a>
    </div>

    <div class="main-content">
        <div class="welcome-header">
            <div class="welcome-text">
                <h1>AIMS System Transactions Matrix</h1>
                <p>Overview tracking matrices detailing file ingestion and dispatch activity updates.</p>
            </div>
            <div class="live-badge">
                <span class="live-dot"></span> Tracking Active
            </div>
        </div>

        <div class="metrics-grid">
            <div class="metric-card card-internal">
                <div class="card-title">Internal Docs</div>
                <div class="card-value"><?php echo $internal_count; ?></div>
                <div class="card-subtext">Today: <span class="trend-up">+<?php echo $today_internal; ?></span></div>
            </div>
            <div class="metric-card card-incoming">
                <div class="card-title">Incoming Docs</div>
                <div class="card-value"><?php echo $incoming_count; ?></div>
                <div class="card-subtext">Today: <span class="trend-up">+<?php echo $today_incoming; ?></span></div>
            </div>
            <div class="metric-card card-outgoing">
                <div class="card-title">Outgoing Docs</div>
                <div class="card-value"><?php echo $outgoing_count; ?></div>
                <div class="card-subtext">Today: <span class="trend-up">+<?php echo $today_outgoing; ?></span></div>
            </div>
            <div class="metric-card card-total">
                <div class="card-title">Total Repository</div>
                <div class="card-value"><?php echo $total_docs; ?></div>
                <div class="card-subtext">System Volume Total: <span><?php echo $today_total; ?> today</span></div>
            </div>
        </div>

        <div class="dashboard-row">
            <div class="content-panel panel-flex-left">
                <h3 class="panel-title">Recent Records Timeline Activity</h3>
                <ul class="timeline">
                    <?php
                    $timeline_query = "(SELECT 'internal' as src_type, tracking_number, subject, doc_date, id FROM internal)
                                       UNION
                                       (SELECT 'incoming' as src_type, tracking_number, subject, doc_date, id FROM incoming)
                                       UNION
                                       (SELECT 'outgoing' as src_type, tracking_number, subject, doc_date, id FROM outgoing)
                                       ORDER BY doc_date DESC, id DESC LIMIT 5";
                    $feed = $conn->query($timeline_query);
                    
                    if($feed && $feed->num_rows > 0):
                        while($item = $feed->fetch_assoc()):
                            $class = "item-" . $item['src_type'];
                            $lbl = ucfirst($item['src_type']);
                    ?>
                        <li class="timeline-item <?php echo $class; ?>">
                            <div class="timeline-time"><?php echo date("F j, Y", strtotime($item['doc_date'])); ?></div>
                            <div class="timeline-body"><?php echo htmlspecialchars($item['subject']); ?></div>
                            <div class="timeline-meta">Ref Track: <strong><?php echo htmlspecialchars($item['tracking_number']); ?></strong> | Scope: <?php echo $lbl; ?></div>
                        </li>
                    <?php 
                        endwhile;
                    else:
                        echo "<p style='color:var(--text-muted); text-align:center; padding:20px;'>No documented system actions saved yet.</p>";
                    endif;
                    ?>
                </ul>
            </div>

            <div class="content-panel panel-flex-right">
                <h3 class="panel-title">Quick Action Routes</h3>
                <div class="action-grid">
                    <a href="internal_transactions.php" class="action-btn">
                        <span>New Internal Entry</span> ➔
                    </a>
                    <a href="incoming_transactions.php" class="action-btn">
                        <span>Log Incoming File</span> ➔
                    </a>
                    <a href="outgoing_transactions.php" class="action-btn">
                        <span>Process Outgoing Track</span> ➔
                    </a>
                </div>
            </div>
        </div>
    </div>
</body>
</html>