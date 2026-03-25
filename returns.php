<?php
session_start();
include 'includes/db.php';

/* SECURITY CHECK */
if(!isset($_SESSION['user']) || ($_SESSION['user']['role'] ?? '') !== 'client'){
    header("Location: ../home.php");
    exit();
}

$user = $_SESSION['user'];
$branch_id   = $user['branch_id'] ?? null;
$branch_name = $user['branch_name'] ?? 'N/A';

/* HANDLE RETURN SUBMISSION */
$success_msg = '';
$error_msg = '';

if($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['add_return'])){
    $return_type = trim($_POST['return_type'] ?? '');
    $big_trays   = intval($_POST['big_trays'] ?? 0);
    $small_trays = intval($_POST['small_trays'] ?? 0);
    $egg_pieces  = intval($_POST['egg_pieces'] ?? 0);
    $remarks     = trim($_POST['remarks'] ?? '');

    if(empty($return_type)){
        $error_msg = "Please select a return type.";
    } else {
        $conn->begin_transaction();
        try {
            $stmt = $conn->prepare("INSERT INTO returns(branch_id, return_type, big_trays, small_trays, egg_pieces, remarks, return_datetime) VALUES(?,?,?,?,?,?,NOW())");
            if(!$stmt) throw new Exception("Prepare failed: ".$conn->error);
            $stmt->bind_param("isiiis", $branch_id, $return_type, $big_trays, $small_trays, $egg_pieces, $remarks);
            if(!$stmt->execute()) throw new Exception("Failed to insert return: ".$stmt->error);

            // Update inventory
            $stmt_inv = $conn->prepare("UPDATE inventory SET big_trays = GREATEST(big_trays - ?,0), small_trays = GREATEST(small_trays - ?,0), egg_pieces = GREATEST(egg_pieces - ?,0), updated_at = NOW() WHERE branch_id=?");
            if(!$stmt_inv) throw new Exception("Prepare failed: ".$conn->error);
            $stmt_inv->bind_param("iiii", $big_trays, $small_trays, $egg_pieces, $branch_id);
            if(!$stmt_inv->execute()) throw new Exception("Failed to update inventory: ".$stmt_inv->error);

            $conn->commit();
            $success_msg = "Return recorded successfully and inventory updated!";
        } catch(Exception $e) {
            $conn->rollback();
            $error_msg = "Error processing return: ".$e->getMessage();
        }
    }
}

/* FETCH RECENT RETURNS */
$returns = [];
$stmt_ret = $conn->prepare("SELECT * FROM returns WHERE branch_id=? ORDER BY return_datetime DESC LIMIT 10");
if($stmt_ret){
    $stmt_ret->bind_param("i",$branch_id);
    $stmt_ret->execute();
    $res_ret = $stmt_ret->get_result();
    while($row=$res_ret->fetch_assoc()) $returns[] = $row;
}

/* FETCH TOTAL RETURNS FOR KPI */
$stmt_tot = $conn->prepare("SELECT SUM(big_trays) AS big_returned, SUM(small_trays) AS small_returned, SUM(egg_pieces) AS pieces_returned FROM returns WHERE branch_id=?");
$stmt_tot->bind_param("i",$branch_id);
$stmt_tot->execute();
$return_totals = $stmt_tot->get_result()->fetch_assoc();

$total_big_returned   = $return_totals['big_returned'] ?? 0;
$total_small_returned = $return_totals['small_returned'] ?? 0;
$total_pieces_returned = $return_totals['pieces_returned'] ?? 0;

$big_tray_eggs   = 12;
$small_tray_eggs = 6;

$total_eggs_returned =
    ($total_big_returned * $big_tray_eggs) +
    ($total_small_returned * $small_tray_eggs) +
    $total_pieces_returned;

/* FETCH INVENTORY */
$inventory=['big_trays'=>0,'small_trays'=>0,'egg_pieces'=>0,'updated_at'=>date('Y-m-d H:i:s')];
if($branch_id){
    $stmt_inv = $conn->prepare("SELECT * FROM inventory WHERE branch_id=? LIMIT 1");
    if($stmt_inv){
        $stmt_inv->bind_param("i",$branch_id);
        $stmt_inv->execute();
        $inv = $stmt_inv->get_result()->fetch_assoc();
        if($inv) $inventory=$inv;
    }
}

/* LOW STOCK ALERTS */
$low_big_trays   = $inventory['big_trays'] <=5;
$low_small_trays = $inventory['small_trays'] <=5;
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Returns - <?php echo htmlspecialchars($branch_name); ?></title>
<style>
*{margin:0;padding:0;box-sizing:border-box;font-family:'Segoe UI',Tahoma,Verdana,sans-serif;}
body{background:#f0fdf4;display:flex;min-height:100vh;}
.sidebar{width:220px;background:#38b000;color:#fff;height:100vh;position:fixed;left:0;top:0;display:flex;flex-direction:column;padding:20px;}
.sidebar h2{margin-bottom:40px;font-size:1.5em;text-align:center;}
.sidebar a{display:block;padding:12px 20px;margin-bottom:15px;background:#2d6a4f;border-radius:10px;color:#fff;text-decoration:none;font-weight:bold;}
.sidebar a:hover{background:#70d6ff;color:#000;}
.sidebar .logout{background:#d00000;margin-top:auto;}
.sidebar .logout:hover{background:#9d0208;}
.main-content{margin-left:220px;padding:30px;flex:1;}
.card{background:#fff;border-radius:15px;padding:25px;box-shadow:0 8px 20px rgba(0,0,0,0.12);margin-bottom:25px;}
.card h2{color:#2d6a4f;margin-bottom:20px;}
input,select,textarea,button{padding:12px;margin-bottom:15px;width:100%;border-radius:10px;border:1px solid #ccc;font-size:1em;}
button{background:#38b000;color:#fff;border:none;font-weight:bold;cursor:pointer;transition:0.3s;}
button:hover{background:#2d6a4f;}
.alert-box{background:#ffe5e5;color:#d00000;padding:15px;border-radius:12px;font-weight:bold;margin-bottom:15px;}
.success-msg{color:green;font-weight:bold;margin-bottom:15px;}
table{width:100%;border-collapse:collapse;margin-top:20px;}
th,td{padding:10px;text-align:center;border-bottom:1px solid #ccc;}
th{background:#38b000;color:#fff;}
tr:nth-child(even){background:#f6fbf7;}
tr:hover{background:#e9f5ee;}
.kpi-grid{display:flex;flex-wrap:wrap;gap:20px;margin-bottom:20px;}
.kpi-box{flex:1 1 180px;padding:20px;border-radius:15px;text-align:center;box-shadow:0 4px 10px rgba(0,0,0,0.08);}
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

<!-- Total Returns KPI -->
<div class="card">
<h2>📊 Total Returns Overview</h2>
<div class="kpi-grid">
    <div class="kpi-box" style="background:#ffe5e5;color:#d00000;">
        <h3>Big Trays Returned</h3>
        <p><?php echo $total_big_returned; ?> Trays</p>
        <small>(<?php echo $total_big_returned * $big_tray_eggs; ?> Eggs)</small>
    </div>
    <div class="kpi-box" style="background:#ffe5e5;color:#d00000;">
        <h3>Small Trays Returned</h3>
        <p><?php echo $total_small_returned; ?> Trays</p>
        <small>(<?php echo $total_small_returned * $small_tray_eggs; ?> Eggs)</small>
    </div>
    <div class="kpi-box" style="background:#ffe5e5;color:#d00000;">
        <h3>Loose Egg Pieces Returned</h3>
        <p><?php echo $total_pieces_returned; ?> Eggs</p>
    </div>
    <div class="kpi-box" style="background:#ffe5e5;color:#d00000;">
        <h3>Total Eggs Returned</h3>
        <p><?php echo $total_eggs_returned; ?> Eggs</p>
    </div>
</div>
</div>

<div class="card">
<h2>🛑 Add New Return</h2>
<?php if($success_msg) echo "<div class='success-msg'>$success_msg</div>"; ?>
<?php if($error_msg) echo "<div class='alert-box'>$error_msg</div>"; ?>

<form method="post">
    <label for="return_type">Return Type</label>
    <select name="return_type" id="return_type" required>
        <option value="">-- Select --</option>
        <option value="Expired">Expired</option>
        <option value="Cracked">Cracked</option>
        <option value="Damaged">Damaged</option>
    </select>

    <label for="big_trays">Big Trays</label>
    <input type="number" name="big_trays" id="big_trays" min="0" value="0">

    <label for="small_trays">Small Trays</label>
    <input type="number" name="small_trays" id="small_trays" min="0" value="0">

    <label for="egg_pieces">Egg Pieces</label>
    <input type="number" name="egg_pieces" id="egg_pieces" min="0" value="0">

    <label for="remarks">Remarks</label>
    <textarea name="remarks" id="remarks" placeholder="Additional details..."></textarea>

    <button type="submit" name="add_return">Add Return</button>
</form>
</div>

<?php if($low_big_trays || $low_small_trays): ?>
<div class="card">
<h2>⚠ Stock Alerts</h2>
<?php if($low_big_trays): ?><div class="alert-box">Low Big Trays: Only <?php echo $inventory['big_trays']; ?> left!</div><?php endif; ?>
<?php if($low_small_trays): ?><div class="alert-box">Low Small Trays: Only <?php echo $inventory['small_trays']; ?> left!</div><?php endif; ?>
</div>
<?php endif; ?>

<div class="card">
<h2>📋 Recent Returns</h2>
<?php if($returns): ?>
<table>
<tr>
<th>ID</th>
<th>Type</th>
<th>Big Trays</th>
<th>Small Trays</th>
<th>Egg Pieces</th>
<th>Remarks</th>
<th>Date</th>
</tr>
<?php foreach($returns as $r): ?>
<tr>
<td><?php echo $r['id']; ?></td>
<td><?php echo htmlspecialchars($r['return_type']); ?></td>
<td><?php echo $r['big_trays']; ?></td>
<td><?php echo $r['small_trays']; ?></td>
<td><?php echo $r['egg_pieces']; ?></td>
<td><?php echo htmlspecialchars($r['remarks']); ?></td>
<td><?php echo $r['return_datetime']; ?></td>
</tr>
<?php endforeach; ?>
</table>
<?php else: ?>
<p>No returns recorded yet.</p>
<?php endif; ?>
</div>

</div>
</body>
</html>