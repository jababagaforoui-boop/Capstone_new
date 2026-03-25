<?php
// Start session if none exists
if(session_status() === PHP_SESSION_NONE){
    session_start();
}

// Include database
include __DIR__ . '/includes/db.php';

/* SECURITY: only client users */
if(!isset($_SESSION['user']) || ($_SESSION['user']['role'] ?? '') !== 'client'){
    header("Location: ../home.php");
    exit();
}

$user = $_SESSION['user'];
$branch_id   = $user['branch_id'];
$branch_name = $user['branch_name'] ?? 'Branch';

/* EGGS PER TRAY */
$big_tray_eggs = 12;
$small_tray_eggs = 6;

/* ================= FETCH INVENTORY ================= */
$stmt = $conn->prepare("SELECT * FROM inventory WHERE branch_id=?");
$stmt->bind_param("i",$branch_id);
$stmt->execute();
$inventory = $stmt->get_result()->fetch_assoc();

$big_remaining   = $inventory['big_trays'] ?? 0;
$small_remaining = $inventory['small_trays'] ?? 0;
$loose_eggs      = $inventory['egg_pieces'] ?? 0;

/* ================= FETCH SALES ================= */
$stmt_sales = $conn->prepare("
    SELECT 
        SUM(big_trays_sold) AS big_sold,
        SUM(small_trays_sold) AS small_sold
    FROM sales WHERE branch_id=?
");
$stmt_sales->bind_param("i",$branch_id);
$stmt_sales->execute();
$sales = $stmt_sales->get_result()->fetch_assoc();

$total_big_sold   = $sales['big_sold'] ?? 0;
$total_small_sold = $sales['small_sold'] ?? 0;

/* ================= FETCH RETURNS ================= */
$stmt_ret = $conn->prepare("
    SELECT 
        SUM(big_trays) AS big_returned,
        SUM(small_trays) AS small_returned,
        SUM(egg_pieces) AS pieces_returned
    FROM returns WHERE branch_id=?
");
$stmt_ret->bind_param("i",$branch_id);
$stmt_ret->execute();
$ret = $stmt_ret->get_result()->fetch_assoc();

$total_big_returned   = $ret['big_returned'] ?? 0;
$total_small_returned = $ret['small_returned'] ?? 0;
$total_pieces_returned = $ret['pieces_returned'] ?? 0;

/* ================= CALCULATIONS ================= */
$eggs_sold =
    ($total_big_sold * $big_tray_eggs) +
    ($total_small_sold * $small_tray_eggs);

$eggs_returned =
    ($total_big_returned * $big_tray_eggs) +
    ($total_small_returned * $small_tray_eggs) +
    $total_pieces_returned;

$total_eggs_remaining =
    ($big_remaining * $big_tray_eggs) +
    ($small_remaining * $small_tray_eggs) +
    $loose_eggs;

/* PROFIT */
$big_price = 144;
$small_price = 36;

$total_profit =
    ($total_big_sold * $big_price) +
    ($total_small_sold * $small_price);

/* MONTHLY DATA */
$six_months = [];
$big_monthly = [];
$small_monthly = [];

for($i=5;$i>=0;$i--){
    $month = date('Y-m', strtotime("-$i month"));
    $six_months[] = date('M Y', strtotime($month.'-01'));

    $stmt_month = $conn->prepare("
        SELECT 
            SUM(big_trays_sold) AS big_sold,
            SUM(small_trays_sold) AS small_sold
        FROM sales
        WHERE branch_id=? 
        AND DATE_FORMAT(sale_datetime,'%Y-%m')=?
    ");

    $stmt_month->bind_param("is",$branch_id,$month);
    $stmt_month->execute();
    $res = $stmt_month->get_result()->fetch_assoc();

    $big_monthly[] = ($res['big_sold'] ?? 0) * $big_tray_eggs;
    $small_monthly[] = ($res['small_sold'] ?? 0) * $small_tray_eggs;
}

/* STOCK ALERTS */
$stock_alerts = [];
$low_threshold = 5;
if($big_remaining <= $low_threshold) $stock_alerts[] = "⚠ Low Big Trays Stock: Only $big_remaining left!";
if($small_remaining <= $low_threshold) $stock_alerts[] = "⚠ Low Small Trays Stock: Only $small_remaining left!";
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?php echo htmlspecialchars($branch_name); ?> Dashboard</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<style>
*{margin:0;padding:0;box-sizing:border-box;font-family:'Segoe UI',sans-serif;}
body{display:flex;background:#f0fdf4;min-height:100vh;}

/* Sidebar */
.sidebar{
    width:220px;
    background:#38b000;
    color:#fff;
    height:100vh;
    position:fixed;
    display:flex;
    flex-direction:column;
    padding:20px;
}
.sidebar h2{text-align:center;margin-bottom:40px;}
.sidebar a{
    display:block;
    padding:12px 15px;
    margin-bottom:15px;
    background:#2d6a4f;
    border-radius:10px;
    color:#fff;
    text-decoration:none;
    font-weight:bold;
    transition:0.3s;
}
.sidebar a:hover{
    background:#70d6ff;
    color:#000;
    transform:translateX(5px);
}
.sidebar .logout{
    background:#d00000;
    margin-top:auto;
}
.sidebar .logout:hover{
    background:#9d0208;
    transform:translateX(5px);
}

/* Main content */
.main{
    margin-left:220px;
    padding:25px;
    flex:1;
}

/* KPI Boxes */
.kpi-grid{
    display:grid;
    grid-template-columns:repeat(4,1fr);
    gap:20px;
    margin-top:20px;
}
.kpi-box{
    padding:20px;
    border-radius:15px;
    color:#fff;
    display:flex;
    flex-direction:column;
    align-items:flex-start;
    justify-content:center;
    box-shadow:0 5px 15px rgba(0,0,0,0.1);
}
.kpi-box i{
    font-size:28px;
    margin-bottom:10px;
}
.green{background:#22c55e;}
.yellow{background:#facc15;color:#000;}
.red{background:#ef4444;}

/* Summary Cards */
.summary-grid{
    display:grid;
    grid-template-columns:repeat(auto-fit,minmax(200px,1fr));
    gap:20px;
    margin-top:20px;
}
.summary-box{
    background:#fff;
    border-radius:15px;
    padding:20px;
    box-shadow:0 5px 15px rgba(0,0,0,0.08);
    font-size:1em;
}

/* Charts */
.chart-container{
    display:grid;
    grid-template-columns:repeat(2,1fr);
    gap:20px;
    margin-top:30px;
}
.chart{
    background:#fff;
    border-radius:15px;
    padding:20px;
    height:350px;
    box-shadow:0 5px 15px rgba(0,0,0,0.08);
}
.chart canvas{
    width:100% !important;
    height:100% !important;
}

/* Responsive */
@media (max-width:1024px){.kpi-grid{grid-template-columns:repeat(2,1fr);}}
@media (max-width:600px){.kpi-grid{grid-template-columns:1fr;}}
@media(max-width:850px){.sidebar{width:100%;height:auto;position:relative;flex-direction:row;overflow-x:auto;}.main{margin-left:0;}.chart-container{grid-template-columns:1fr;}}
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

<div class="main">
<h1><?php echo $branch_name; ?> Dashboard</h1>

<!-- KPI Boxes -->
<div class="kpi-grid">
<div class="kpi-box green"><i class="fas fa-egg"></i>
<h3>Big Trays</h3>
<p><?php echo $big_remaining; ?> Trays</p>
<small><?php echo $big_remaining*$big_tray_eggs; ?> Eggs</small>
</div>

<div class="kpi-box green"><i class="fas fa-egg"></i>
<h3>Small Trays</h3>
<p><?php echo $small_remaining; ?> Trays</p>
<small><?php echo $small_remaining*$small_tray_eggs; ?> Eggs</small>
</div>

<div class="kpi-box green"><i class="fas fa-egg"></i>
<h3>Loose Eggs</h3>
<p><?php echo $loose_eggs; ?> Eggs</p>
</div>

<div class="kpi-box green"><i class="fas fa-boxes"></i>
<h3>Total Eggs</h3>
<p><?php echo $total_eggs_remaining; ?></p>
</div>

<div class="kpi-box yellow"><i class="fas fa-coins"></i>
<h3>Sold Eggs</h3>
<p><?php echo $eggs_sold; ?></p>
</div>

<div class="kpi-box red"><i class="fas fa-undo"></i>
<h3>Returned</h3>
<p><?php echo $eggs_returned; ?></p>
</div>

<div class="kpi-box yellow"><i class="fas fa-peso-sign"></i>
<h3>Profit</h3>
<p>₱<?php echo number_format($total_profit,2); ?></p>
</div>
</div>

<!-- Summary Cards -->
<div class="summary-grid">
<div class="summary-box">
<strong>📅 Monthly Sales:</strong> <?php echo array_sum($big_monthly)+array_sum($small_monthly); ?> Eggs
</div>
<div class="summary-box">
<strong>⚠ Stock Alerts:</strong>
<?php echo !empty($stock_alerts) ? implode('<br>',$stock_alerts) : "All stocks sufficient"; ?>
</div>
<div class="summary-box">
<strong>💰 Total Profit:</strong> ₱<?php echo number_format($total_profit,2); ?>
</div>
</div>

<!-- Charts -->
<div class="chart-container">
<div class="chart"><canvas id="barChart"></canvas></div>
<div class="chart"><canvas id="lineChart"></canvas></div>
<div class="chart"><canvas id="pieChart"></canvas></div>
</div>

<script>
// BAR CHART
new Chart(document.getElementById('barChart'),{
type:'bar',
data:{
labels:['Big','Small','Loose','Sold','Returned'],
datasets:[{
label:'Egg Count',
data:[
<?php echo $big_remaining*$big_tray_eggs; ?>,
<?php echo $small_remaining*$small_tray_eggs; ?>,
<?php echo $loose_eggs; ?>,
<?php echo $eggs_sold; ?>,
<?php echo $eggs_returned; ?>
],
backgroundColor:'#38b000',
borderRadius:10
}]
},
options:{plugins:{legend:{display:false}},scales:{y:{beginAtZero:true}}}
});

// LINE CHART
new Chart(document.getElementById('lineChart'),{
type:'line',
data:{
labels:<?php echo json_encode($six_months); ?>,
datasets:[
{label:'Big Eggs',data:<?php echo json_encode($big_monthly); ?>,borderColor:'#16a34a',fill:true},
{label:'Small Eggs',data:<?php echo json_encode($small_monthly); ?>,borderColor:'#2563eb',fill:true}
]
}
});

// PIE CHART
new Chart(document.getElementById('pieChart'),{
type:'pie',
data:{
labels:['Big','Small','Loose','Sold','Returned'],
datasets:[{
data:[
<?php echo $big_remaining*$big_tray_eggs; ?>,
<?php echo $small_remaining*$small_tray_eggs; ?>,
<?php echo $loose_eggs; ?>,
<?php echo $eggs_sold; ?>,
<?php echo $eggs_returned; ?>
],
backgroundColor:['#22c55e','#16a34a','#4ade80','#facc15','#ef4444']
}]
}
});
</script>

</body>
</html>