<?php
session_start();
include 'includes/db.php';

/* SECURITY CHECK */
if(!isset($_SESSION['user']) || ($_SESSION['user']['role'] ?? '') !== 'client'){
    header("Location: ../home.php");
    exit();
}

$user = $_SESSION['user'];
$branch_id   = $user['branch_id'];
$branch_name = $user['branch_name'];

/* FETCH INVENTORY */
$stmt = $conn->prepare("SELECT * FROM inventory WHERE branch_id=?");
$stmt->bind_param("i",$branch_id);
$stmt->execute();
$inventory = $stmt->get_result()->fetch_assoc();

$big_remaining   = $inventory['big_trays'] ?? 0;
$small_remaining = $inventory['small_trays'] ?? 0;
$loose_eggs      = $inventory['egg_pieces'] ?? 0;

/* FETCH SALES */
$stmt_sales = $conn->prepare("
SELECT SUM(big_trays_sold) AS big_sold, 
       SUM(small_trays_sold) AS small_sold
FROM sales WHERE branch_id=?
");
$stmt_sales->bind_param("i",$branch_id);
$stmt_sales->execute();
$sales_totals = $stmt_sales->get_result()->fetch_assoc();
$total_big_sold   = $sales_totals['big_sold'] ?? 0;
$total_small_sold = $sales_totals['small_sold'] ?? 0;

/* FETCH RETURNS */
$stmt_ret = $conn->prepare("
SELECT SUM(big_trays) AS big_returned, 
       SUM(small_trays) AS small_returned, 
       SUM(egg_pieces) AS pieces_returned
FROM returns WHERE branch_id=?
");
$stmt_ret->bind_param("i",$branch_id);
$stmt_ret->execute();
$return_totals = $stmt_ret->get_result()->fetch_assoc();

$total_big_returned   = $return_totals['big_returned'] ?? 0;
$total_small_returned = $return_totals['small_returned'] ?? 0;
$total_pieces_returned = $return_totals['pieces_returned'] ?? 0;

/* CALCULATE EGGS */
$big_tray_eggs   = 12;
$small_tray_eggs = 6;

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

$low_big_trays   = $big_remaining <=5;
$low_small_trays = $small_remaining <=5;

/* CARD COLOR FUNCTION */
function cardColor($value, $type='big') {
    if($type==='big'){
        if($value <= 5) return ['#ff4d4f','#fff'];   // red
        if($value <= 10) return ['#ffd666','#000'];  // yellow
        return ['#d1fae5','#2d6a4f'];                // green
    } else { // small
        if($value <= 5) return ['#ff4d4f','#fff'];
        if($value <= 10) return ['#ffd666','#000'];
        return ['#d1fae5','#2d6a4f'];
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Stocks - <?php echo htmlspecialchars($branch_name); ?></title>
<style>
/* Your original styling */
*{margin:0;padding:0;box-sizing:border-box;font-family:'Segoe UI',Verdana,Tahoma;}
body{background:#f0fdf4;display:flex;min-height:100vh;}
.sidebar{width:220px;background:#38b000;color:#fff;height:100vh;position:fixed;left:0;top:0;display:flex;flex-direction:column;padding:20px;}
.sidebar h2{margin-bottom:40px;font-size:1.5em;text-align:center;}
.sidebar a{display:block;padding:12px 20px;margin-bottom:15px;background:#2d6a4f;border-radius:10px;color:#fff;text-decoration:none;font-weight:bold;transition:0.3s;}
.sidebar a:hover{background:#70d6ff;color:#000;transform:translateX(5px);}
.sidebar .logout{background:#d00000;margin-top:auto;}
.sidebar .logout:hover{background:#9d0208;transform:translateX(5px);}
.main-content{margin-left:220px;padding:30px;flex:1;}
.card{background:#fff;border-radius:15px;padding:25px;box-shadow:0 8px 20px rgba(0,0,0,0.12);margin-bottom:25px;}
.card h2{color:#2d6a4f;margin-bottom:20px;}
.kpi-grid{display:flex;flex-wrap:wrap;gap:20px;}
.kpi-box{flex:1 1 220px;padding:20px;border-radius:15px;text-align:center;box-shadow:0 4px 10px rgba(0,0,0,0.08);}
.kpi-box h3{font-size:1.2em;margin-bottom:10px;}
.kpi-box p{font-size:1.5em;font-weight:bold;margin-top:5px;}
.kpi-green{background:#d1fae5;color:#2d6a4f;}
.kpi-yellow{background:#fff3cd;color:#856404;}
.kpi-red{background:#ffe5e5;color:#d00000;}
.alert-box{background:#ffe5e5;color:#d00000;padding:15px;border-radius:12px;font-weight:bold;margin-bottom:15px;}
</style>
</head>
<body>

<div class="sidebar">
<h2>Dashboard</h2>
<a href="dashboard.php">Home</a>
<a href="add_deliveries.php">Add Deliveries</a>
<a href="orders.php">Orders</a>
<a href="stocks.php">Stocks</a>
<a href="returns.php">Returns</a>
<a href="profile.php">Profile</a>
<a href="../home.php" class="logout">Logout</a>
</div>

<div class="main-content">

<!-- KPI CARDS -->
<div class="card">
<h2>📊 Stocks & Sales Overview</h2>
<div class="kpi-grid">
    <!-- Remaining Stock -->
    <div class="kpi-box kpi-green">
        <h3>Big Trays Remaining</h3>
        <p><?php echo $big_remaining; ?> Trays</p>
        <small>(<?php echo $big_remaining * $big_tray_eggs; ?> Eggs)</small>
    </div>
    <div class="kpi-box kpi-green">
        <h3>Small Trays Remaining</h3>
        <p><?php echo $small_remaining; ?> Trays</p>
        <small>(<?php echo $small_remaining * $small_tray_eggs; ?> Eggs)</small>
    </div>
    <div class="kpi-box kpi-green">
        <h3>Loose Eggs Remaining</h3>
        <p><?php echo $loose_eggs; ?> Eggs</p>
    </div>
    <div class="kpi-box kpi-green">
        <h3>Total Eggs Remaining</h3>
        <p><?php echo $total_eggs_remaining; ?> Eggs</p>
    </div>

    <!-- Sold Eggs -->
    <div class="kpi-box kpi-yellow">
        <h3>Total Eggs Sold</h3>
        <p><?php echo $eggs_sold; ?> Eggs</p>
    </div>

    <!-- Returned Eggs -->
    <div class="kpi-box kpi-red">
        <h3>Big Trays Returned</h3>
        <p><?php echo $total_big_returned; ?> Trays</p>
        <small>(<?php echo $total_big_returned * $big_tray_eggs; ?> Eggs)</small>
    </div>
    <div class="kpi-box kpi-red">
        <h3>Small Trays Returned</h3>
        <p><?php echo $total_small_returned; ?> Trays</p>
        <small>(<?php echo $total_small_returned * $small_tray_eggs; ?> Eggs)</small>
    </div>
    <div class="kpi-box kpi-red">
        <h3>Loose Egg Pieces Returned</h3>
        <p><?php echo $total_pieces_returned; ?> Eggs</p>
    </div>
</div>
</div>

<!-- Low Stock Alerts -->
<?php if($low_big_trays || $low_small_trays): ?>
<div class="card">
<h2>⚠ Low Stock Alerts</h2>
<?php if($low_big_trays): ?><div class="alert-box">Low Big Trays: Only <?php echo $big_remaining; ?> left!</div><?php endif; ?>
<?php if($low_small_trays): ?><div class="alert-box">Low Small Trays: Only <?php echo $small_remaining; ?> left!</div><?php endif; ?>
</div>
<?php endif; ?>

</div>
</body>
</html>