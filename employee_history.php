<?php
session_start();
include 'db.php';
if (!isset($_SESSION['user'])) { header("Location: index.php"); exit(); }
$user = $_SESSION['user'];

// Gather Metric Values
$res_user = $conn->query("SELECT COUNT(*) as total FROM internal WHERE uploaded_by = " . $user['id']);
$user_total = $res_user->fetch_assoc()['total'];

$res_all = $conn->query("SELECT COUNT(*) as total FROM internal");
$grand_total = $res_all->fetch_assoc()['total'];

$other_total = $grand_total - $user_total;

// Calculate Math Percentages for Chart Segments
$user_percent = $grand_total > 0 ? round(($user_total / $grand_total) * 100, 1) : 0;
$others_percent = $grand_total > 0 ? round(($other_total / $grand_total) * 100, 1) : 0;

// SVG Arc Math Calculations 
$dash_array_user = $user_percent * 1.57; // 1.57 matches perimeter proportions based on a radius of 25
$dash_array_others = 157 - $dash_array_user;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title>Employee Status History</title>
    <style>
        body { font-family: Arial, sans-serif; background:#f4f6f9; text-align:center; padding:40px; }
        .chart-box { background:white; padding:30px; border-radius:8px; display:inline-block; box-shadow:0 4px 8px rgba(0,0,0,0.05); }
        .legend { display:flex; justify-content:center; gap:20px; margin-top:20px; }
        .legend-item { display:flex; align-items:center; gap:5px; }
    </style>
</head>
<body>
    <div class="chart-box">
        <h2>Your Contribution Metric Analysis</h2>
        <p>Total Documents In Database: <strong><?php echo $grand_total; ?></strong></p>
        
        <svg width="200" height="200" viewBox="0 0 64 64" style="transform: rotate(-90deg); border-radius: 50%;">
            <circle cx="32" cy="32" r="25" fill="transparent" stroke="#e9ecef" stroke-width="12"/>
            <circle cx="32" cy="32" r="25" fill="transparent" stroke="#007bff" stroke-width="12"
                    stroke-dasharray="<?php echo $dash_array_user; ?> 157" />
        </svg>

        <div class="legend">
            <div class="legend-item"><span style="width:15px; height:15px; background:#007bff; display:inline-block;"></span> You: <?php echo $user_total; ?> (<?php echo $user_percent; ?>%)</div>
            <div class="legend-item"><span style="width:15px; height:15px; background:#e9ecef; display:inline-block; border:1px solid #ccc;"></span> Others: <?php echo $other_total; ?> (<?php echo $others_percent; ?>%)</div>
        </div>
        <br><br>
        <a href="user_dashboard.php" style="color:#007bff; text-decoration:none;">Back to Main Dashboard</a>
    </div>
</body>
</html>