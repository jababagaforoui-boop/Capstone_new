<?php
session_start();
include 'includes/db.php';

// Protect page - admin only
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}

// Fetch all sales with branch name
$sales_query = "
    SELECT s.id, s.branch_id, s.big_trays_sold, s.small_trays_sold, s.egg_pieces_sold, s.total_amount, s.sale_datetime,
           b.branch_name
    FROM sales s
    LEFT JOIN branches b ON s.branch_id = b.id
    ORDER BY s.sale_datetime ASC
";
$result = $conn->query($sales_query);
$sales = [];
if ($result) {
    while($row = $result->fetch_assoc()) $sales[] = $row;
}

// Prepare data for charts
$branch_totals = [];
$daily_totals = [];
$branch_trays = [];

$total_big_trays = 0;
$total_small_trays = 0;
$total_eggs = 0;
$total_sales_amount = 0;

foreach($sales as $s) {
    $branch = $s['branch_name'] ?? 'Unknown';
    $branch_totals[$branch] = ($branch_totals[$branch] ?? 0) + $s['total_amount'];

    $date = date("Y-m-d", strtotime($s['sale_datetime']));
    $daily_totals[$date] = ($daily_totals[$date] ?? 0) + $s['total_amount'];

    if(!isset($branch_trays[$branch])) $branch_trays[$branch] = ['big'=>0,'small'=>0];
    $branch_trays[$branch]['big'] += $s['big_trays_sold'];
    $branch_trays[$branch]['small'] += $s['small_trays_sold'];

    $total_big_trays += $s['big_trays_sold'];
    $total_small_trays += $s['small_trays_sold'];
    $total_eggs += $s['egg_pieces_sold'];
    $total_sales_amount += $s['total_amount'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Sales Report - Admin Panel</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

<style>
*{margin:0;padding:0;box-sizing:border-box;font-family:'Segoe UI',Tahoma,Verdana;}
body{background:#f0fdf4;color:#2d6a4f;transition:0.3s;}
body.dark{background:#121821;color:#e0e0e0;}
body.dark .sidebar{background:#0f172a;}
body.dark .sidebar a.active,body.dark .sidebar a:hover{background:#2563eb;color:#fff;}
body.dark .card,body.dark .card-summary,body.dark table{background:#1e293b;color:#e0e0e0;}
body.dark th{background:#2563eb;color:#fff;}
body.dark tr:nth-child(even){background:#1e293b;}
body.dark tr:hover{background:#334155;}
body.dark #darkToggle{background:#334155;color:#fff;}

.wrapper{display:flex;min-height:100vh;}
.sidebar{width:240px;background:#38b000;color:#fff;padding:25px;display:flex;flex-direction:column;justify-content:space-between;position:fixed;top:0;left:0;height:100vh;}
.sidebar h2{text-align:center;font-size:1.8rem;margin-bottom:35px;}
.sidebar a{display:flex;align-items:center;gap:10px;padding:12px 18px;margin-bottom:12px;background:#2d6a4f;color:#fff;border-radius:10px;text-decoration:none;transition:0.3s;}
.sidebar a.active,.sidebar a:hover{background:#70d6ff;color:#000;}
.sidebar .logout{background:#d90429;margin-top:auto;}
.sidebar .logout:hover{background:#9b0a20;}

.main-content{flex:1;padding:30px 40px;margin-left:260px;}
.header{display:flex;justify-content:space-between;align-items:center;margin-bottom:35px;}
.header h1{font-size:2.3rem;}
.header p{font-size:1.05rem;color:#52796f;}

#darkToggle{padding:8px 15px;border:none;border-radius:6px;background:#334155;color:#fff;cursor:pointer;}

/* Cards */
.cards-container{display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:25px;margin-bottom:35px;}
.card-summary{background:#fff;padding:25px;border-radius:12px;box-shadow:0 10px 25px rgba(0,0,0,0.08);text-align:center;}
.card-summary h3{font-size:1.05rem;margin-bottom:8px;}
.card-summary p{font-size:1.7rem;font-weight:700;}

/* General card */
.card{background:#fff;padding:25px;border-radius:12px;box-shadow:0 10px 25px rgba(0,0,0,0.08);margin-bottom:30px;}
.card h2{font-size:1.3rem;margin-bottom:15px;}
.card canvas{width:100% !important;height:320px !important;display:block;margin:auto;}

/* Table */
table{width:100%;border-collapse:collapse;border-radius:12px;overflow:hidden;}
th,td{padding:14px 12px;text-align:center;border-bottom:1px solid #ddd;font-size:0.95rem;}
th{background:#38b000;color:#fff;font-size:1rem;}
tr:nth-child(even){background:#f0fdf4;}
tr:hover{background:#e5f3e8;}

@media(max-width:768px){
    .sidebar{position:relative;width:100%;height:auto;flex-direction:row;overflow-x:auto;padding:15px;}
    .main-content{margin-left:0;padding:20px;}
    .cards-container{grid-template-columns:repeat(auto-fit,minmax(140px,1fr));}
    .header{flex-direction:column;align-items:flex-start;}
    #darkToggle{margin-top:10px;}
    .card canvas{height:250px !important;}
}
</style>
</head>
<body>

<div class="wrapper">
<div class="sidebar">
    <h2>Admin Panel</h2>
    <a href="dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
    <a href="branches.php"><i class="fas fa-store"></i> Branches</a>
    <a href="deliveries.php"><i class="fas fa-truck"></i> Deliveries</a>
    <a href="sales.php" class="active"><i class="fas fa-chart-line"></i> Sales Report</a>
    <a href="reports.php"><i class="fas fa-file-alt"></i> Reports</a>
    <a href="stocks.php"><i class="fas fa-boxes"></i> Stocks</a>
    <a href="users.php"><i class="fas fa-users"></i> Users</a>
    <a href="../home.php" class="logout"><i class="fas fa-sign-out-alt"></i> Logout</a>
</div>

<div class="main-content">
    <div class="header">
        <div>
            <h1>Sales Report</h1>
            <p>Overview of all sales transactions, totals, and analytics.</p>
        </div>
        <button id="darkToggle">🌙 Dark Mode</button>
    </div>

    <div class="cards-container">
        <div class="card-summary"><h3>Total Sales (₱)</h3><p><?= number_format($total_sales_amount,2) ?></p></div>
        <div class="card-summary"><h3>Total Big Trays Sold</h3><p><?= $total_big_trays ?></p></div>
        <div class="card-summary"><h3>Total Small Trays Sold</h3><p><?= $total_small_trays ?></p></div>
        <div class="card-summary"><h3>Total Eggs Sold</h3><p><?= $total_eggs ?></p></div>
    </div>

    <div class="card"><h2>Total Sales per Branch</h2><canvas id="branchChart"></canvas></div>
    <div class="card"><h2>Daily Sales Overview</h2><canvas id="dailyChart"></canvas></div>
    <div class="card"><h2>Big vs Small Trays Sold per Branch</h2><canvas id="traysChart"></canvas></div>

    <div class="card">
        <h2>Sales Records</h2>
        <table>
            <tr>
                <th>ID</th>
                <th>Branch</th>
                <th>Big Trays Sold</th>
                <th>Small Trays Sold</th>
                <th>Total Eggs Sold</th>
                <th>Total Amount (₱)</th>
                <th>Date & Time</th>
            </tr>
            <?php if(!empty($sales)): foreach($sales as $row): ?>
            <tr>
                <td><?= $row['id'] ?></td>
                <td><?= htmlspecialchars($row['branch_name'] ?? 'No Branch') ?></td>
                <td><?= $row['big_trays_sold'] ?></td>
                <td><?= $row['small_trays_sold'] ?></td>
                <td><?= $row['egg_pieces_sold'] ?></td>
                <td>₱<?= number_format($row['total_amount'],2) ?></td>
                <td><?= date("Y-m-d H:i", strtotime($row['sale_datetime'])) ?></td>
            </tr>
            <?php endforeach; else: ?>
            <tr><td colspan="7">No sales records found.</td></tr>
            <?php endif; ?>
        </table>
    </div>
</div>
</div>

<script>
// Dark Mode
const body = document.body;
const toggle = document.getElementById('darkToggle');
if(localStorage.getItem('darkMode')==='enabled'){body.classList.add('dark'); toggle.textContent='☀️ Light Mode';}
toggle.addEventListener('click',()=>{
    body.classList.toggle('dark');
    if(body.classList.contains('dark')){localStorage.setItem('darkMode','enabled'); toggle.textContent='☀️ Light Mode';}
    else{localStorage.setItem('darkMode','disabled'); toggle.textContent='🌙 Dark Mode';}
});

// Charts
new Chart(document.getElementById('branchChart').getContext('2d'),{
    type:'bar',
    data:{labels:<?= json_encode(array_keys($branch_totals)) ?>,datasets:[{label:'Total Sales (₱)',data:<?= json_encode(array_values($branch_totals)) ?>,backgroundColor:'#38b000'}]},
    options:{responsive:true,plugins:{legend:{display:false},title:{display:true,text:'Total Sales by Branch'}},scales:{y:{beginAtZero:true}}}
});
new Chart(document.getElementById('dailyChart').getContext('2d'),{
    type:'line',
    data:{labels:<?= json_encode(array_keys($daily_totals)) ?>,datasets:[{label:'Daily Sales (₱)',data:<?= json_encode(array_values($daily_totals)) ?>,borderColor:'#38b000',backgroundColor:'rgba(56,176,0,0.2)',fill:true,tension:0.2}]},
    options:{responsive:true,plugins:{legend:{display:false},title:{display:true,text:'Daily Sales Over Time'}},scales:{y:{beginAtZero:true}}}
});
new Chart(document.getElementById('traysChart').getContext('2d'),{
    type:'bar',
    data:{labels:<?= json_encode(array_keys($branch_trays)) ?>,datasets:[
        {label:'Big Trays Sold',data:<?= json_encode(array_map(fn($b)=>$b['big'],$branch_trays)) ?>,backgroundColor:'#70d6ff'},
        {label:'Small Trays Sold',data:<?= json_encode(array_map(fn($b)=>$b['small'],$branch_trays)) ?>,backgroundColor:'#ffba08'}
    ]},
    options:{responsive:true,plugins:{title:{display:true,text:'Big vs Small Trays Sold per Branch'}},scales:{x:{stacked:true},y:{stacked:true,beginAtZero:true}}}
});
</script>

</body>
</html>