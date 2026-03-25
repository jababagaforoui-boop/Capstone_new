<?php
session_start();
include 'includes/db.php';

// ===== SECURITY CHECK =====
if(!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin'){
    header("Location: ../login.php");
    exit();
}

// ===== SETTINGS =====
$month = date('Y-m');

// ===== FETCH OR INITIALIZE STOCK =====
$stock_query = $conn->query("SELECT * FROM stocks WHERE month='$month' LIMIT 1");
if($stock_query->num_rows === 0){
    $conn->query("INSERT INTO stocks (month, big_trays, small_trays) VALUES ('$month', 200, 200)");
    $stock_query = $conn->query("SELECT * FROM stocks WHERE month='$month' LIMIT 1");
}
$stock = $stock_query->fetch_assoc();

// ===== HANDLE ADDING BIG / SMALL TRAYS =====
if($_SERVER['REQUEST_METHOD'] === 'POST'){
    $add_big = intval($_POST['add_big_trays'] ?? 0);
    $add_small = intval($_POST['add_small_trays'] ?? 0);

    if($add_big > 0 || $add_small > 0){
        $stmt_update = $conn->prepare("UPDATE stocks SET big_trays = big_trays + ?, small_trays = small_trays + ? WHERE month = ?");
        $stmt_update->bind_param("iis", $add_big, $add_small, $month);
        $stmt_update->execute();
    }
    header("Location: stocks.php");
    exit();
}

// ===== CALCULATE TOTAL EGGS =====
$big_trays = intval($stock['big_trays']);
$small_trays = intval($stock['small_trays']);

$total_big_eggs = $big_trays * 12;
$total_small_eggs = $small_trays * 6;
$total_eggs = $total_big_eggs + $total_small_eggs;
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Stock Management - Admin Panel</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<style>
*{margin:0;padding:0;box-sizing:border-box;font-family:'Segoe UI',Verdana,Tahoma;}
body{background:#e6f4ea;color:#2d6a4f;}
.wrapper {display:flex; min-height:100vh; overflow:hidden;}
.sidebar{width:240px;background:#38b000;color:#fff;padding:25px;display:flex;flex-direction:column;}
.sidebar h2{text-align:center;font-size:1.8rem;margin-bottom:30px;font-weight:700;}
.sidebar a{display:flex;align-items:center;gap:10px;padding:12px 18px;margin-bottom:10px;background:#2d6a4f;color:#fff;border-radius:10px;font-weight:600;text-decoration:none;transition:0.3s;text-align:left;}
.sidebar a i{width:20px;text-align:center;}
.sidebar a.active,.sidebar a:hover{background:#70d6ff;color:#000;}
.sidebar .logout{background:#d90429;margin-top:auto;}
.sidebar .logout:hover{background:#9b0a20;}
.main-content{flex:1;padding:30px;overflow-y:auto;height:100vh;}
.header{display:flex;justify-content:space-between;align-items:center;margin-bottom:30px;}
.header h1{font-size:2.2rem;color:#2d6a4f;}
.header p{color:#52796f;font-size:1rem;margin-top:5px;}
#darkToggle{padding:8px 15px;border:none;border-radius:6px;background:#334155;color:#fff;cursor:pointer;font-weight:600;transition:0.3s;}
#darkToggle:hover{background:#1e293b;}
.dashboard-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:20px;margin-bottom:25px;}
.dashboard-card{background:#fff;padding:25px;border-radius:15px;box-shadow:0 10px 25px rgba(0,0,0,0.08);text-align:center;transition:0.3s;}
.dashboard-card:hover{transform:translateY(-5px);}
.dashboard-card .icon{font-size:3rem;margin-bottom:15px;color:#d8b48f;}
.dashboard-card h2{color:#2d6a4f;font-size:2rem;margin-bottom:8px;}
.dashboard-card p{color:#52796f;font-weight:600;}
.stock-table{width:100%;border-collapse:collapse;box-shadow:0 8px 20px rgba(0,0,0,0.1);border-radius:12px;overflow:hidden;margin-top:20px;}
.stock-table th, .stock-table td{padding:12px;text-align:center;font-size:0.95rem;}
.stock-table th{background:#38b000;color:#fff;font-weight:600;}
.stock-table tbody tr:nth-child(even){background:#f6fbf7;}
.stock-table tbody tr:hover{background:#e0f4e6;transition:0.2s;}
.chart-card{grid-column:1/-1;}
form input, form button{padding:12px;margin-bottom:10px;border-radius:10px;border:1px solid #ccc;font-size:1em;}
form input{width:48%;margin-right:4%;}
form input:last-child{margin-right:0;}
form button{background:#38b000;color:#fff;border:none;font-weight:bold;cursor:pointer;transition:0.3s;}
form button:hover{background:#2d6a4f;}
@media(max-width:768px){.sidebar{width:100%;flex-direction:row;overflow-x:auto;height:auto;padding:15px;}.sidebar a{margin-right:8px;margin-bottom:0;}.main-content{padding:20px;}.dashboard-grid{grid-template-columns:1fr;}}
body.dark{background:#121821;color:#e0e0e0;}
body.dark .main-content,.body.dark .dashboard-card,.body.dark .chart-card{background-color:#1e293b;color:#e0e0e0;}
body.dark .sidebar{background-color:#0f172a;}
body.dark .sidebar a{color:#e0e0e0;}
body.dark .sidebar a.active,body.dark .sidebar a:hover{background-color:#2563eb;color:#fff;}
body.dark .dashboard-card .icon{color:#70d6ff;}
body.dark h2{color:#e0e0e0;}
body.dark p{color:#cbd5e1;}
body.dark .stock-table th{background:#2563eb;color:#fff;}
body.dark .stock-table tbody tr:nth-child(even){background:#1f2a40;}
body.dark .stock-table tbody tr:hover{background:#334155;}
</style>
</head>
<body>
<div class="wrapper">

<!-- Sidebar -->
<div class="sidebar">
    <h2>Admin Panel</h2>
    <a href="dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
    <a href="branches.php"><i class="fas fa-store"></i> Branches</a>
    <a href="deliveries.php"><i class="fas fa-truck"></i> Deliveries</a>
    <a href="sales.php"><i class="fas fa-chart-line"></i> Sales Report</a>
    <a href="reports.php"><i class="fas fa-file-alt"></i> Reports</a>
    <a href="stocks.php" class="active"><i class="fas fa-boxes"></i> Stocks</a>
    <a href="users.php"><i class="fas fa-users"></i> Users</a>
    <a href="../home.php" class="logout"><i class="fas fa-sign-out-alt"></i> Logout</a>
</div>

<div class="main-content">
    <div class="header">
        <div>
            <h1>Stock Management</h1>
            <p>Current egg stock for the month <?= $month ?></p>
        </div>
        <button id="darkToggle">🌙 Dark Mode</button>
    </div>

    <!-- Dashboard Cards -->
    <div class="dashboard-grid">
        <div class="dashboard-card">
            <div class="icon"><i class="fas fa-egg"></i></div>
            <h2><?= $big_trays ?></h2>
            <p>Big Trays (12 eggs each)</p>
        </div>
        <div class="dashboard-card">
            <div class="icon"><i class="fas fa-egg"></i></div>
            <h2><?= $small_trays ?></h2>
            <p>Small Trays (6 eggs each)</p>
        </div>
        <div class="dashboard-card">
            <div class="icon"><i class="fas fa-egg"></i></div>
            <h2><?= $total_eggs ?></h2>
            <p>Total Eggs Available</p>
        </div>
    </div>

    <!-- Add Stock Form -->
    <div class="dashboard-card">
        <h2>Add Stock</h2>
        <form method="post" style="display:flex; flex-wrap:wrap; gap:10px; justify-content:center;">
            <input type="number" name="add_big_trays" placeholder="Add Big Trays (12 eggs each)" style="flex:1 1 45%;">
            <input type="number" name="add_small_trays" placeholder="Add Small Trays (6 eggs each)" style="flex:1 1 45%;">
            <button type="submit" style="flex:1 1 100%;">Add Stock</button>
        </form>
    </div>

    <!-- Stock Table -->
    <div class="dashboard-card chart-card">
        <h2>Current Stock Details</h2>
        <table class="stock-table">
            <thead>
                <tr>
                    <th>Type</th>
                    <th>Count</th>
                    <th>Eggs</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>Big Trays</td>
                    <td><?= $big_trays ?></td>
                    <td><?= $total_big_eggs ?></td>
                </tr>
                <tr>
                    <td>Small Trays</td>
                    <td><?= $small_trays ?></td>
                    <td><?= $total_small_eggs ?></td>
                </tr>
                <tr>
                    <td><strong>Total Eggs</strong></td>
                    <td>-</td>
                    <td><strong><?= $total_eggs ?></strong></td>
                </tr>
            </tbody>
        </table>
    </div>
</div>
</div>

<script>
const body = document.body;
const darkToggle = document.getElementById("darkToggle");
if(localStorage.getItem("darkMode") === "enabled") {
    body.classList.add("dark");
    darkToggle.textContent = "☀️ Light Mode";
}
darkToggle.addEventListener("click", () => {
    body.classList.toggle("dark");
    if(body.classList.contains("dark")){
        localStorage.setItem("darkMode","enabled");
        darkToggle.textContent = "☀️ Light Mode";
    } else {
        localStorage.setItem("darkMode","disabled");
        darkToggle.textContent = "🌙 Dark Mode";
    }
});
</script>
</body>
</html>