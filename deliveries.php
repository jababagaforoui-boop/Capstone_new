<?php
session_start();
include 'includes/db.php';

/* ===== AUTO ADMIN LOGIN (LOCAL TESTING) ===== */
if(!isset($_SESSION['user'])){
    $_SESSION['user'] = [
        "role"=>"admin",
        "name"=>"Administrator"
    ];
}

/* ===== SETTINGS ===== */
$month = date('Y-m');

/* ===== FETCH BRANCHES ===== */
$branches_list = [];
$result_branches = $conn->query("SELECT id, branch_name FROM branches ORDER BY branch_name ASC");
while($row = $result_branches->fetch_assoc()){
    $branches_list[$row['id']] = $row['branch_name'];
}

/* ===== FETCH OR CREATE MONTHLY STOCK ===== */
$stock_query = $conn->query("SELECT * FROM stocks WHERE month='$month' LIMIT 1");
if($stock_query->num_rows === 0){
    $conn->query("INSERT INTO stocks (month,big_trays,small_trays) VALUES ('$month',0,0)");
    $stock_query = $conn->query("SELECT * FROM stocks WHERE month='$month' LIMIT 1");
}
$stock = $stock_query->fetch_assoc();

$success="";
$error="";

/* ===== ADD STOCK ===== */
if($_SERVER['REQUEST_METHOD']=="POST" && isset($_POST['add_stock'])){
    $add_big=max(0,(int)$_POST['add_big_trays']);
    $add_small=max(0,(int)$_POST['add_small_trays']);
    if($add_big==0 && $add_small==0){
        $error="Please enter trays.";
    } else {
        $stock['big_trays'] += $add_big;
        $stock['small_trays'] += $add_small;
        $conn->query("UPDATE stocks SET big_trays={$stock['big_trays']}, small_trays={$stock['small_trays']} WHERE month='$month'");
        $success="Stock added successfully!";
    }
}

/* ===== RECORD DELIVERY ===== */
if($_SERVER['REQUEST_METHOD']=="POST" && isset($_POST['record_delivery'])){
    $branch=(int)$_POST['branch'];
    $bigTrays=max(0,(int)$_POST['big_trays']);      // 1 dozen
    $smallTrays=max(0,(int)$_POST['small_trays']);  // half dozen

    if(!isset($branches_list[$branch])){
        $error="Invalid branch.";
    } elseif($bigTrays==0 && $smallTrays==0){
        $error="Enter trays.";
    } elseif($bigTrays>$stock['big_trays'] || $smallTrays>$stock['small_trays']){
        $error="Not enough stock!";
    } else {
        // Insert into deliveries
        $stmt=$conn->prepare("INSERT INTO deliveries (branch_id,big_trays,small_trays,delivery_datetime,created_at) VALUES(?,?,?,NOW(),NOW())");
        $stmt->bind_param("iii",$branch,$bigTrays,$smallTrays);
        $stmt->execute(); $stmt->close();

        // Update branch inventory
        $stmt=$conn->prepare("SELECT big_trays,small_trays FROM inventory WHERE branch_id=? LIMIT 1");
        $stmt->bind_param("i",$branch); $stmt->execute(); $res=$stmt->get_result(); $inv=$res->fetch_assoc();

        if($inv){
            $new_big=$inv['big_trays']+$bigTrays;
            $new_small=$inv['small_trays']+$smallTrays;
            $stmt2=$conn->prepare("UPDATE inventory SET big_trays=?,small_trays=?,updated_at=NOW() WHERE branch_id=?");
            $stmt2->bind_param("iii",$new_big,$new_small,$branch); $stmt2->execute();
        } else {
            $stmt2=$conn->prepare("INSERT INTO inventory (branch_id,big_trays,small_trays,created_at,updated_at) VALUES(?,?,?,NOW(),NOW())");
            $stmt2->bind_param("iii",$branch,$bigTrays,$smallTrays); $stmt2->execute();
        }

        // Update admin stock
        $stock['big_trays']-=$bigTrays;
        $stock['small_trays']-=$smallTrays;
        $conn->query("UPDATE stocks SET big_trays={$stock['big_trays']}, small_trays={$stock['small_trays']} WHERE month='$month'");
        $success="Delivery recorded!";
    }
}

/* ===== FETCH DELIVERIES ===== */
$deliveries=[];
$result=$conn->query("SELECT d.*,b.branch_name FROM deliveries d JOIN branches b ON d.branch_id=b.id ORDER BY d.created_at DESC");
while($row=$result->fetch_assoc()){$deliveries[]=$row;}

/* ===== MONTHLY TOTAL ===== */
$totalData=$conn->query("SELECT COUNT(*) as total_deliveries,SUM(big_trays) as total_big,SUM(small_trays) as total_small FROM deliveries WHERE DATE_FORMAT(created_at,'%Y-%m')='$month'")->fetch_assoc();
$total_deliveries=$totalData['total_deliveries'] ?? 0;
$total_big=$totalData['total_big'] ?? 0;
$total_small=$totalData['total_small'] ?? 0;
$totalEggsMonth=($total_big*12)+($total_small*6);

/* ===== DELIVERIES PER BRANCH ===== */
$branchDeliveries=[];$chartLabels=[];$chartBig=[];$chartSmall=[];$chartTotal=[];
foreach($branches_list as $id=>$name){
    $data = $conn->query("SELECT SUM(big_trays) as big, SUM(small_trays) as small, COUNT(*) as total FROM deliveries WHERE branch_id=$id AND DATE_FORMAT(created_at,'%Y-%m')='$month'")->fetch_assoc();
    $branchDeliveries[$id] = [
        'branch_name'=>$name,
        'big'=> $data['big'] ?? 0,
        'small'=> $data['small'] ?? 0,
        'total_eggs'=> (($data['big'] ?? 0)*12)+(($data['small'] ?? 0)*6),
        'deliveries'=> $data['total'] ?? 0
    ];
    $chartLabels[]=$name;
    $chartBig[]=$data['big'] ?? 0;
    $chartSmall[]=$data['small'] ?? 0;
    $chartTotal[]=(($data['big'] ?? 0)*12)+(($data['small'] ?? 0)*6);
}

/* ===== DELIVERIES PER DAY ===== */
$dailyLabels=[];$dailyBig=[];$dailySmall=[];$dailyTotal=[];
$daysInMonth = date('t');
for($d=1;$d<=$daysInMonth;$d++){
    $day = str_pad($d,2,'0',STR_PAD_LEFT);
    $dailyLabels[] = "$month-$day";
    $row = $conn->query("SELECT SUM(big_trays) as big,SUM(small_trays) as small FROM deliveries WHERE DATE(created_at)='$month-$day'")->fetch_assoc();
    $dailyBig[] = $row['big'] ?? 0;
    $dailySmall[] = $row['small'] ?? 0;
    $dailyTotal[] = (($row['big'] ?? 0)*12)+(($row['small'] ?? 0)*6);
}

/* ===== GOALS & CONVERSIONS ===== */
$goalOrders = 100; 
$goalDeliveredPercent = min(100, round(($total_deliveries/$goalOrders)*100));
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Deliveries - Admin Panel</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
/* --- Global Styles --- */
*{margin:0;padding:0;box-sizing:border-box;font-family:'Segoe UI',Tahoma,Verdana;}
body{background:#f0fdf4;color:#2d6a4f;transition:0.3s;}
body.dark{background:#121821;color:#e0e0e0;}
body.dark .sidebar{background:#0f172a;}
body.dark .sidebar a.active,body.dark .sidebar a:hover{background:#2563eb;color:#fff;}
body.dark .dashboard-card,body.dark .chart-container,body.dark table{background:#1e293b;color:#e0e0e0;}
body.dark table th{background:#2563eb;color:#fff;}
body.dark form input,body.dark form select{background:#334155;color:#e0e0e0;border:1px solid #555;}
body.dark form button{background:#2563eb;color:#fff;}
.wrapper{display:flex;min-height:100vh;}
.sidebar{width:240px;background:#38b000;color:#fff;padding:25px;display:flex;flex-direction:column;justify-content:space-between;position:fixed;top:0;left:0;height:100vh;}
.sidebar a{padding:12px;margin-bottom:12px;background:#2d6a4f;color:#fff;text-decoration:none;border-radius:12px;font-weight:bold;display:flex;align-items:center;gap:10px;transition:0.3s;}
.sidebar a.active{background:#70d6ff;color:#000;}
.sidebar a:hover{transform:translateX(5px);}
.sidebar .logout{background:#d00000;margin-top:20px;}
.sidebar .logout:hover{background:#9d0208;}
.main-content{flex:1;margin-left:260px;padding:30px;}
h1{margin-bottom:20px;}
.alert{padding:12px;border-radius:12px;margin-bottom:20px;font-weight:600;}
.alert.success{background:#d1fae5;color:#16a34a;}
.alert.error{background:#fcd5ce;color:#b91c1c;}
.dashboard-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:20px;margin-bottom:25px;}
.dashboard-card{background:#fff;padding:25px;border-radius:20px;box-shadow:0 10px 30px rgba(0,0,0,0.08);text-align:center;transition:0.3s;}
.dashboard-card:hover{box-shadow:0 12px 35px rgba(0,0,0,0.12);}
.dashboard-card h2{font-size:28px;margin-bottom:8px;color:#2563eb;}
.dashboard-card p{font-weight:600;}
.chart-container{background:#fff;padding:25px;border-radius:20px;box-shadow:0 10px 30px rgba(0,0,0,0.08);margin-bottom:25px;height:400px;}
.progress-bar-container{background:#e5e7eb;border-radius:12px;overflow:hidden;margin-bottom:15px;}
.progress-bar{height:25px;border-radius:12px;transition:0.3s;}
.progress-orders{background:#2563eb;width:<?= $goalDeliveredPercent ?>%;}

form input,form select{width:100%;padding:12px;border-radius:12px;border:1px solid #ccc;margin-bottom:12px;font-size:16px;}
form button{padding:12px 20px;background:#38b000;color:#fff;border:none;border-radius:12px;font-weight:bold;cursor:pointer;transition:0.3s;font-size:16px;}
form button:hover{background:#2d6a4f;}

table{width:100%;border-collapse:collapse;margin-top:20px;background:#fff;border-radius:12px;overflow:hidden;}
th,td{padding:12px;text-align:center;border-bottom:1px solid #ddd;font-size:14px;}
th{background:#38b000;color:#fff;}
tr:nth-child(even){background:#f0fdf4;}
#darkToggle{position:fixed;top:15px;right:15px;padding:10px 18px;background:#334155;color:#fff;border:none;border-radius:10px;cursor:pointer;z-index:1000;}
#darkToggle:hover{background:#2563eb;}
@media(max-width:768px){.main-content{margin-left:0;padding:20px;}.dashboard-grid{grid-template-columns:repeat(auto-fit,minmax(140px,1fr));}}
</style>
</head>
<body>
<button id="darkToggle">🌙 Dark Mode</button>
<div class="wrapper">
    <div class="sidebar">
        <div>
            <h2>Admin Panel</h2>
            <a href="dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
            <a href="branches.php"><i class="fas fa-store"></i> Branches</a>
            <a href="deliveries.php" class="active"><i class="fas fa-truck"></i> Deliveries</a>
            <a href="sales.php"><i class="fas fa-file-invoice"></i> Sales Report</a>
            <a href="reports.php"><i class="fas fa-file-alt"></i> Reports</a>
            <a href="stocks.php"><i class="fas fa-boxes"></i> Stocks</a>
            <a href="users.php"><i class="fas fa-users"></i> Users</a>
        </div>
        <a href="../home.php" class="logout"><i class="fas fa-sign-out-alt"></i> Logout</a>
    </div>

    <div class="main-content">
        <h1>Delivery Management</h1>
        <?php if($success) echo "<div class='alert success'>$success</div>"; ?>
        <?php if($error) echo "<div class='alert error'>$error</div>"; ?>

        <!-- KPI CARDS -->
        <div class="dashboard-grid">
            <div class="dashboard-card"><h2><?= $total_deliveries ?></h2><p>Total Deliveries</p></div>
            <div class="dashboard-card"><h2><?= $stock['big_trays'] ?></h2><p>Big Trays Remaining</p></div>
            <div class="dashboard-card"><h2><?= $stock['small_trays'] ?></h2><p>Small Trays Remaining</p></div>
            <div class="dashboard-card"><h2><?= $totalEggsMonth ?></h2><p>Total Eggs Delivered</p></div>
        </div>

        <!-- ADD EGGS FORM -->
        <div class="card">
            <h2>Add Eggs to Branch</h2>
            <form method="post">
                <label>Branch:</label>
                <select name="branch">
                    <?php foreach($branches_list as $id=>$name): ?>
                        <option value="<?= $id ?>"><?= htmlspecialchars($name) ?></option>
                    <?php endforeach; ?>
                </select>

                <label>1 Dozen (Big Tray):</label>
                <input type="number" name="big_trays" min="0" max="<?= $stock['big_trays'] ?>" value="0">

                <label>Half Dozen (Small Tray):</label>
                <input type="number" name="small_trays" min="0" max="<?= $stock['small_trays'] ?>" value="0">

                <button type="submit" name="record_delivery">Deliver Eggs</button>
            </form>
        </div>

        <!-- MONTHLY CHART -->
        <h2>Monthly Deliveries Chart (All Branches)</h2>
        <div class="chart-container"><canvas id="branchChart"></canvas></div>

        <!-- DAILY DELIVERIES CHART -->
        <h2>Deliveries Per Day</h2>
        <div class="chart-container"><canvas id="dailyChart"></canvas></div>

        <!-- GOALS & CONVERSIONS -->
        <h2>Delivery Completion Goals</h2>
        <div class="progress-bar-container">
            <div class="progress-bar progress-orders"></div>
        </div>
        <p><?= $goalDeliveredPercent ?>% of target deliveries completed (Target: <?= $goalOrders ?>)</p>

        <!-- RECENT DELIVERIES -->
        <h2>Recent Deliveries by Branch</h2>
        <table>
            <tr><th>ID</th><th>Branch</th><th>Big Trays</th><th>Small Trays</th><th>Total Eggs</th><th>Date</th></tr>
            <?php if(!empty($deliveries)): foreach($deliveries as $d):
                $total_eggs = ($d['big_trays'] * 12) + ($d['small_trays'] * 6);
            ?>
            <tr>
                <td><?= $d['id'] ?></td>
                <td><?= htmlspecialchars($d['branch_name']) ?></td>
                <td><?= $d['big_trays'] ?></td>
                <td><?= $d['small_trays'] ?></td>
                <td><?= $total_eggs ?> pcs</td>
                <td><?= date("Y-m-d H:i", strtotime($d['created_at'])) ?></td>
            </tr>
            <?php endforeach; else: ?>
            <tr><td colspan="6">No deliveries recorded.</td></tr>
            <?php endif; ?>
        </table>
    </div>
</div>

<script>
// Dark Mode Toggle
const toggle = document.getElementById('darkToggle');
toggle.addEventListener('click',()=>{document.body.classList.toggle('dark');});

// Charts
const ctx = document.getElementById('branchChart').getContext('2d');
new Chart(ctx, {type:'bar',data:{labels:<?= json_encode($chartLabels) ?>,datasets:[
    {label:'Big Trays',data:<?= json_encode($chartBig) ?>,backgroundColor:'#38b000'},
    {label:'Small Trays',data:<?= json_encode($chartSmall) ?>,backgroundColor:'#70d6ff'},
    {label:'Total Eggs',data:<?= json_encode($chartTotal) ?>,backgroundColor:'#ffba08'}
]},options:{responsive:true,plugins:{legend:{position:'top'},title:{display:true,text:'Branch Deliveries This Month'}},scales:{y:{beginAtZero:true}}}});

const ctxDaily = document.getElementById('dailyChart').getContext('2d');
new Chart(ctxDaily,{type:'line',data:{labels:<?= json_encode($dailyLabels) ?>,datasets:[
    {label:'Big Trays',data:<?= json_encode($dailyBig) ?>,borderColor:'#38b000',fill:false,tension:0.2},
    {label:'Small Trays',data:<?= json_encode($dailySmall) ?>,borderColor:'#70d6ff',fill:false,tension:0.2},
    {label:'Total Eggs',data:<?= json_encode($dailyTotal) ?>,borderColor:'#ffba08',fill:false,tension:0.2}
]},options:{responsive:true,plugins:{legend:{position:'top'},title:{display:true,text:'Daily Deliveries'}}}});
</script>
</body>
</html>