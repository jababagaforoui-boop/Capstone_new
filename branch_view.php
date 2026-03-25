<?php
session_start();
include '../config/db.php';

// Protect page
if(!isset($_SESSION['user']) || $_SESSION['user']['role'] != 'admin'){
     header("Location: ../login.php");
    exit();
}

// Get branch ID
if(!isset($_GET['id'])){
    header("Location: branches.php");
    exit();
}
$branch_id = (int)$_GET['id'];

// Branch info
$branch = $conn->query("SELECT * FROM branches WHERE id=$branch_id")->fetch_assoc();
if(!$branch) die("Branch not found");

// Inventory - create if missing
$inventory = $conn->query("SELECT * FROM inventory WHERE branch_id=$branch_id")->fetch_assoc();
if(!$inventory){
    $conn->query("INSERT INTO inventory(branch_id,big_trays,small_trays,egg_pieces) VALUES($branch_id,0,0,0)");
    $inventory = $conn->query("SELECT * FROM inventory WHERE branch_id=$branch_id")->fetch_assoc();
}

// Recent deliveries
$recent_deliveries = $conn->query("SELECT * FROM deliveries WHERE branch_id=$branch_id ORDER BY created_at DESC LIMIT 5");

// Low stock alert
$lowStock = ($inventory['big_trays'] <=5 || $inventory['small_trays'] <=10);

// Handle delivery form
$success_message = "";
if(isset($_POST['big_trays']) || isset($_POST['small_trays'])){
    $big = isset($_POST['big_trays']) ? (int)$_POST['big_trays'] : 0;
    $small = isset($_POST['small_trays']) ? (int)$_POST['small_trays'] : 0;

    if($big > 0 || $small > 0){
        // Calculate total eggs
        $eggs_from_big = $big*20*12;   // 20 small trays in 1 big tray, 12 eggs each
        $eggs_from_small = $small*6;   // 6 eggs per small tray
        $total_eggs = $eggs_from_big + $eggs_from_small;

        // Insert delivery
        $conn->query("INSERT INTO deliveries(branch_id,big_trays,small_trays,egg_pieces,created_at) VALUES($branch_id,$big,$small,$total_eggs,NOW())");

        // Update inventory
        $conn->query("UPDATE inventory SET big_trays=big_trays+$big, small_trays=small_trays+$small, egg_pieces=egg_pieces+$total_eggs WHERE branch_id=$branch_id");

        $success_message = "✅ Delivery recorded! Big Trays: $big, Small Trays: $small, Total Amount: ₱".(($big*100)+($small*50));

        // Refresh inventory & deliveries
        $inventory = $conn->query("SELECT * FROM inventory WHERE branch_id=$branch_id")->fetch_assoc();
        $recent_deliveries = $conn->query("SELECT * FROM deliveries WHERE branch_id=$branch_id ORDER BY created_at DESC LIMIT 5");
    } else {
        $success_message = "⚠ Enter at least one tray!";
    }
}

// Total delivered today
$today = date('Y-m-d');
$todayTotals = $conn->query("SELECT SUM(big_trays) AS big_trays, SUM(small_trays) AS small_trays, SUM(egg_pieces) AS egg_pieces FROM deliveries WHERE branch_id=$branch_id AND DATE(created_at)='$today'")->fetch_assoc();
$todayAmount = ($todayTotals['big_trays']*100)+($todayTotals['small_trays']*50);
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title><?= $branch['name'] ?> | Branch Overview</title>
<style>
*{box-sizing:border-box;font-family:'Segoe UI',Tahoma;}
body{margin:0;background:#f3f8f5;}
.wrapper{display:flex;min-height:100vh;}
.sidebar{width:250px;background:#fff;padding:25px;border-right:1px solid #ddd;}
.sidebar h2{text-align:center;color:#2d6a4f;margin-bottom:30px;font-size:1.8rem;}
.sidebar a{display:block;padding:14px 18px;margin-bottom:12px;background:#38b000;color:#fff;border-radius:10px;font-weight:600;text-decoration:none;text-align:center;transition:0.3s;}
.sidebar a.active,.sidebar a:hover{background:#2d6a4f;}
.sidebar .logout{background:#d90429;}
.sidebar .logout:hover{background:#9b0a20;}
.main-content{flex:1;padding:40px;}
.header{text-align:center;margin-bottom:35px;}
.header h1{font-size:2.6rem;color:#2d6a4f;margin-bottom:5px;}
.header p{color:#52796f;font-size:1.1rem;}
.stats{display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:20px;margin-bottom:30px;}
.stat-card{background:#f6fbf7;padding:25px;border-radius:15px;text-align:center;box-shadow:0 5px 15px rgba(0,0,0,0.05);}
.stat-card h3{font-size:1.2rem;color:#2d6a4f;margin-bottom:10px;}
.stat-card span{font-size:2.3rem;font-weight:bold;color:#1b4332;}
.alert{padding:18px;border-radius:12px;font-weight:600;margin-bottom:25px;text-align:center;}
.alert.success{background:#d4edda;color:#155724;}
.alert.danger{background:#f8d7da;color:#721c24;}
.back-btn{display:inline-block;padding:12px 25px;background:#38b000;color:#fff;text-decoration:none;border-radius:12px;font-weight:600;margin-bottom:20px;}
.back-btn:hover{background:#2d6a4f;}
.delivery-card{background:#fff;padding:25px;border-radius:15px;box-shadow:0 6px 20px rgba(0,0,0,0.08);margin-bottom:30px;}
.delivery-card h3{color:#2d6a4f;margin-bottom:20px;}
.delivery-card form{display:flex;gap:15px;flex-wrap:wrap;}
.delivery-card input{padding:12px;border-radius:10px;border:1px solid #ccc;flex:1;}
.delivery-card button{padding:12px 25px;background:#38b000;color:#fff;border:none;border-radius:10px;font-weight:600;cursor:pointer;transition:0.3s;}
.delivery-card button:hover{background:#2d6a4f;}
table{width:100%;border-collapse:collapse;background:#fff;border-radius:10px;overflow:hidden;box-shadow:0 5px 15px rgba(0,0,0,0.05);margin-bottom:30px;}
th,td{padding:12px;text-align:center;border-bottom:1px solid #ddd;}
th{background:#38b000;color:#fff;}
tr:nth-child(even){background:#f6fbf7;}
tr:hover{background:#e9f5ee;}
.total-box{background:#d4edda;color:#155724;padding:12px;margin-top:10px;border-radius:10px;font-weight:600;text-align:center;}
</style>
</head>
<body>
<div class="wrapper">
    <!-- Sidebar -->
<div class="sidebar">
    <h2>Admin Panel</h2>

    <a href="dashboard.php">
        <i class="fas fa-tachometer-alt"></i> Dashboard
    </a>

    <a href="branches.php">
        <i class="fas fa-store"></i> Branches
    </a>

    <a href="deliveries.php">
        <i class="fas fa-truck"></i> Deliveries
    </a>

    <a href="sales.php">
        <i class="fas fa-chart-line"></i> Sales Report
    </a>

    <a href="reports.php">
        <i class="fas fa-file-alt"></i> Reports
    </a>

    <a href="stocks.php">
        <i class="fas fa-boxes"></i> Stocks
    </a>

    <!-- ACTIVE PAGE -->
    <a href="users.php" class="active">
        <i class="fas fa-users"></i> Users
    </a>

    <a href="../auth/logout.php" class="logout">
        <i class="fas fa-sign-out-alt"></i> Logout
    </a>
</div>


    <div class="main-content">
        <div class="header">
            <h1><?= $branch['name'] ?></h1>
            <p>Branch Inventory & Sales Overview</p>
        </div>

        <?php if($lowStock): ?>
            <div class="alert danger">⚠ LOW STOCK ALERT — Please schedule delivery immediately</div>
        <?php endif; ?>

        <?php if($success_message): ?>
            <div class="alert success"><?= $success_message ?></div>
        <?php endif; ?>

        <!-- Inventory Stats -->
        <div class="stats">
            <div class="stat-card"><h3>Big Trays</h3><span><?= $inventory['big_trays'] ?></span></div>
            <div class="stat-card"><h3>Small Trays</h3><span><?= $inventory['small_trays'] ?></span></div>
            <div class="stat-card"><h3>Egg Pieces</h3><span><?= $inventory['egg_pieces'] ?></span></div>
            <div class="stat-card total-box">
                <h3>Total Amount</h3>
                <span>₱<?= ($inventory['big_trays']*100)+($inventory['small_trays']*50) ?></span>
            </div>
        </div>

        <!-- Delivery Form -->
        <div class="delivery-card">
            <h3>Add Delivery</h3>
            <form method="POST">
                <input type="number" name="big_trays" placeholder="Big Trays (₱100 each)" min="0" value="0" required>
                <input type="number" name="small_trays" placeholder="Small Trays (₱50 each)" min="0" value="0" required>
                <button type="submit">Record Delivery</button>
            </form>
        </div>

        <!-- Recent Deliveries -->
        <div class="delivery-card">
            <h3>Recent Deliveries</h3>
            <table>
                <tr>
                    <th>ID</th>
                    <th>Big Trays</th>
                    <th>Small Trays</th>
                    <th>Egg Pieces</th>
                    <th>Total Amount</th>
                    <th>Date</th>
                </tr>
                <?php while($d=$recent_deliveries->fetch_assoc()):
                    $amount = ($d['big_trays']*100)+($d['small_trays']*50);
                ?>
                <tr>
                    <td><?= $d['id'] ?></td>
                    <td><?= $d['big_trays'] ?></td>
                    <td><?= $d['small_trays'] ?></td>
                    <td><?= $d['egg_pieces'] ?></td>
                    <td>₱<?= $amount ?></td>
                    <td><?= date('M d, Y H:i',strtotime($d['created_at'])) ?></td>
                </tr>
                <?php endwhile; ?>
            </table>
        </div>

        <a href="branches.php" class="back-btn">← Back to Branches</a>
    </div>
</div>
</body>
</html>
