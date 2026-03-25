<?php
session_start();

/* DATABASE */
$conn = new mysqli("localhost","root","","freshfarmegg");
if($conn->connect_error){
    die("Connection failed ".$conn->connect_error);
}

/* SECURITY */
if(!isset($_SESSION['user']) || $_SESSION['user']['role']!=="client"){
    header("Location: login.php");
    exit();
}

$user = $_SESSION['user'];

$branch_id   = $user['branch_id'];
$branch_name = $user['branch_name'];
$user_name   = $user['name'] ?? "User";

/* ===== FETCH INVENTORY ===== */
$stmt = $conn->prepare("SELECT big_trays,small_trays,egg_pieces FROM inventory WHERE branch_id=? LIMIT 1");
$stmt->bind_param("i",$branch_id);
$stmt->execute();
$result = $stmt->get_result();
$inventory = $result->fetch_assoc();

if(!$inventory){
    $inventory = ['big_trays'=>0,'small_trays'=>0,'egg_pieces'=>0];
}

/* ===== FETCH DELIVERIES ===== */
$stmt_del = $conn->prepare("SELECT SUM(big_trays) as big, SUM(small_trays) as small FROM deliveries WHERE branch_id=?");
$stmt_del->bind_param("i",$branch_id);
$stmt_del->execute();
$res_del = $stmt_del->get_result()->fetch_assoc();

$delivered_big   = $res_del['big'] ?? 0;
$delivered_small = $res_del['small'] ?? 0;

/* ===== COMBINED INVENTORY ===== */
$big_trays_total   = $inventory['big_trays'] + $delivered_big;
$small_trays_total = $inventory['small_trays'] + $delivered_small;
$egg_pieces_total  = $inventory['egg_pieces'] ?? 0;

/* ===== DAILY MONITOR ===== */
$stmt_day=$conn->prepare("
SELECT SUM(big_trays) as day_big,
SUM(small_trays) as day_small
FROM deliveries
WHERE branch_id=? AND DATE(created_at)=CURDATE()
");
$stmt_day->bind_param("i",$branch_id);
$stmt_day->execute();
$day = $stmt_day->get_result()->fetch_assoc();

$day_big   = $day['day_big'] ?? 0;
$day_small = $day['day_small'] ?? 0;
$day_eggs  = ($day_big*12)+($day_small*6);

/* ===== WEEKLY MONITOR ===== */
$stmt_week=$conn->prepare("
SELECT SUM(big_trays) as week_big,
SUM(small_trays) as week_small
FROM deliveries
WHERE branch_id=? AND YEARWEEK(created_at,1)=YEARWEEK(CURDATE(),1)
");
$stmt_week->bind_param("i",$branch_id);
$stmt_week->execute();
$week = $stmt_week->get_result()->fetch_assoc();

$week_big   = $week['week_big'] ?? 0;
$week_small = $week['week_small'] ?? 0;
$week_eggs  = ($week_big*12)+($week_small*6);

/* ===== MONTHLY TOTAL ===== */
$current_month = date('Y-m');
$stmt_month = $conn->prepare("
SELECT SUM(big_trays) as total_big,
SUM(small_trays) as total_small
FROM deliveries
WHERE branch_id=? AND DATE_FORMAT(created_at,'%Y-%m')=?
");
$stmt_month->bind_param("is",$branch_id,$current_month);
$stmt_month->execute();
$month_result = $stmt_month->get_result()->fetch_assoc();

$total_big_month   = $month_result['total_big'] ?? 0;
$total_small_month = $month_result['total_small'] ?? 0;
$total_eggs_month  = ($total_big_month*12)+($total_small_month*6);

/* ===== RECENT DELIVERIES ===== */
$stmt_logs=$conn->prepare("
SELECT * FROM deliveries
WHERE branch_id=?
ORDER BY created_at DESC
LIMIT 5
");
$stmt_logs->bind_param("i",$branch_id);
$stmt_logs->execute();
$delivery_logs = $stmt_logs->get_result()->fetch_all(MYSQLI_ASSOC);

/* ===== CHART DATA ===== */
$chart_labels = [];
$chart_data   = [];

$chart_query = $conn->query("
SELECT DATE(created_at) as d,
SUM(big_trays*12 + small_trays*6) as eggs
FROM deliveries
WHERE branch_id=$branch_id
GROUP BY DATE(created_at)
ORDER BY DATE(created_at) ASC
LIMIT 7
");

while($row=$chart_query->fetch_assoc()){
    $chart_labels[] = $row['d'];
    $chart_data[]   = $row['eggs'];
}

/* ===== LOW STOCK ALERT ===== */
$low_stock_alert = "";
if($big_trays_total < 10){
    $low_stock_alert .= "⚠️ Low Big Tray stock ";
}
if($small_trays_total < 20){
    $low_stock_alert .= "⚠️ Low Small Tray stock";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Add Deliveries - <?php echo htmlspecialchars($branch_name); ?></title>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<style>
*{margin:0;padding:0;box-sizing:border-box;font-family:'Segoe UI',Verdana,Tahoma;}
body{background:#f0fdf4; display:flex; min-height:100vh;}
.sidebar{width:220px; background:#38b000; color:#fff; height:100vh; position:fixed; left:0; top:0; display:flex; flex-direction:column; padding:20px;}
.sidebar h2{margin-bottom:40px; font-size:1.6em; text-align:center;}
.sidebar a{display:block; padding:12px 20px; margin-bottom:15px; background:#2d6a4f; border-radius:10px; color:#fff; text-decoration:none; font-weight:bold; transition:0.3s;}
.sidebar a:hover{background:#70d6ff; color:#000; transform:translateX(5px);}
.sidebar .logout{background:#d00000; margin-top:auto;}
.sidebar .logout:hover{background:#9d0208; transform:translateX(5px);}
.main-content{margin-left:220px; padding:40px; flex:1;}
.card{background:#fff; border-radius:20px; padding:30px; box-shadow:0 10px 30px rgba(0,0,0,0.1); margin-bottom:25px;}
.card h2{color:#2d6a4f; margin-bottom:25px; font-size:1.4em;}
.success-msg{color:#16a34a; margin-bottom:15px; font-weight:bold; font-size:1.1em;}
.error-msg{color:#dc2626; margin-bottom:15px; font-weight:bold;}
.dashboard{display:flex; gap:20px; flex-wrap:wrap; margin-bottom:30px;}
.dashboard .stock-card{flex:1 1 200px; background:#d1fae5; color:#2d6a4f; border-radius:15px; padding:30px; text-align:center; box-shadow:0 4px 12px rgba(0,0,0,0.08); transition:0.3s;}
.dashboard .stock-card:hover{box-shadow:0 6px 20px rgba(0,0,0,0.15);}
.dashboard .stock-card h3{font-size:2.2em; margin-bottom:10px;}
.dashboard .stock-card p{font-size:1.1em; font-weight:bold;}
input, button{padding:14px; margin-bottom:15px; width:100%; border-radius:12px; border:1px solid #ccc; font-size:1em;}
button{background:#38b000; color:#fff; border:none; font-weight:bold; cursor:pointer; transition:0.3s;}
button:hover{background:#2d6a4f;}
form input[type="number"]{width:48%; display:inline-block; margin-right:4%;}
form input[type="number"]:last-child{margin-right:0;}
.recent-log{border-top:1px solid #ccc; margin-top:25px; padding-top:20px;}
.recent-log h3{margin-bottom:15px; color:#2d6a4f;}
.recent-log table{width:100%; border-collapse:collapse;}
.recent-log th, .recent-log td{padding:12px; border-bottom:1px solid #ddd; text-align:center;}
.alert-msg{color:#b91c1c; font-weight:bold; margin-bottom:20px;}
.stats-card{background:#e0f2fe; color:#0369a1; padding:20px; border-radius:15px; margin-bottom:20px; box-shadow:0 4px 12px rgba(0,0,0,0.08);}
.stats-card h3{margin-bottom:10px;}
</style>
</head>

<body>

<div class="sidebar">
<h2>Client Panel</h2>
<a href="dashboard.php"><i class="fas fa-home"></i> Home</a>
<a href="add_deliveries.php"><i class="fas fa-truck"></i> Deliveries</a>
<a href="orders.php"><i class="fas fa-list"></i> Orders</a>
<a href="stocks.php"><i class="fas fa-boxes"></i> Stocks</a>
<a href="returns.php"><i class="fas fa-undo"></i> Returns</a>
<a href="profile.php"><i class="fas fa-user"></i> Profile</a>
<a href="../home.php" class="logout"><i class="fas fa-sign-out-alt"></i> Logout</a>
</div>

<div class="main-content">
<div class="card">

<h2>📦 Add Deliveries - <?php echo htmlspecialchars($branch_name); ?></h2>
<p>Welcome, <strong><?php echo htmlspecialchars($user_name); ?></strong>! 👋</p>

<?php if($low_stock_alert) echo "<div class='alert-msg'>$low_stock_alert</div>"; ?>

<div class="dashboard">

<div class="stock-card">
<h3><?php echo $inventory['big_trays']; ?></h3>
<p>Big Trays</p>
</div>

<div class="stock-card">
<h3><?php echo $inventory['small_trays']; ?></h3>
<p>Small Trays</p>
</div>

<div class="stock-card">
<h3><?php echo $day_eggs; ?></h3>
<p>Eggs Today</p>
</div>

<div class="stock-card">
<h3><?php echo $week_eggs; ?></h3>
<p>Eggs This Week</p>
</div>

<div class="stock-card">
<h3><?php echo $total_eggs_month; ?></h3>
<p>Total Eggs This Month</p>
</div>

</div>

<div class="stats-card">
<h3>Delivery History (Last 7 Days)</h3>
<canvas id="deliveryChart"></canvas>
</div>

<div class="recent-log">
<h3>📝 Recent Deliveries</h3>

<table>
<tr>
<th>Date</th>
<th>Big Trays</th>
<th>Small Trays</th>
<th>Total Eggs</th>
</tr>

<?php if($delivery_logs): ?>
<?php foreach($delivery_logs as $log): ?>
<tr>
<td><?php echo date('M d, Y H:i', strtotime($log['created_at'])); ?></td>
<td><?php echo $log['big_trays']; ?></td>
<td><?php echo $log['small_trays']; ?></td>
<td><?php echo ($log['big_trays']*12)+($log['small_trays']*6); ?></td>
</tr>
<?php endforeach; ?>
<?php else: ?>
<tr><td colspan="4">No deliveries recorded yet.</td></tr>
<?php endif; ?>

</table>
</div>

</div>
</div>

<script>

const ctx=document.getElementById('deliveryChart');

new Chart(ctx,{
type:'line',
data:{
labels:<?php echo json_encode($chart_labels); ?>,
datasets:[{
label:'Eggs Delivered',
data:<?php echo json_encode($chart_data); ?>,
borderWidth:3
}]
},
options:{responsive:true}
});

</script>

</body>
</html>