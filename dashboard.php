<?php
// =====================
// Admin Dashboard Logic
// =====================
if(session_status() === PHP_SESSION_NONE){
    session_start();
}

// Include DB connection
include __DIR__ . '/includes/db.php';

// Security: only admin users
if(!isset($_SESSION['user']) || ($_SESSION['user']['role'] ?? '') !== 'admin'){
    header("Location: ../login.php");
    exit();
}

// =====================
// METRICS: SALES & ORDERS
// =====================
$big_price = 144;
$small_price = 36;

// Total Sales & Total Orders
$sales_res = $conn->query("
    SELECT 
        SUM(big_trays_sold * $big_price + small_trays_sold * $small_price) AS total_sales,
        SUM(big_trays_sold + small_trays_sold) AS total_orders
    FROM sales
")->fetch_assoc();

$total_sales  = $sales_res['total_sales'] ?? 0;
$total_orders = $sales_res['total_orders'] ?? 0;

// Total Eggs in Inventory
$inventory_res = $conn->query("
    SELECT SUM(big_trays*12 + small_trays*6 + egg_pieces) AS total_eggs
    FROM inventory
")->fetch_assoc();
$total_eggs_inventory = $inventory_res['total_eggs'] ?? 0;

// Active Branches
$branches_res = $conn->query("SELECT COUNT(*) AS active_branches FROM branches")->fetch_assoc();
$active_branches = $branches_res['active_branches'] ?? 0;

// =====================
// CHART DATA
// =====================

// Last 6 months sales by month
$months = [];
$big_monthly = [];
$small_monthly = [];
for($i=5;$i>=0;$i--){
    $month = date('Y-m', strtotime("-$i month"));
    $months[] = date('M Y', strtotime($month.'-01'));

    $stmt_month = $conn->prepare("
        SELECT SUM(big_trays_sold) AS big_sold, SUM(small_trays_sold) AS small_sold
        FROM sales
        WHERE DATE_FORMAT(sale_datetime,'%Y-%m') = ?
    ");
    $stmt_month->bind_param("s",$month);
    $stmt_month->execute();
    $res = $stmt_month->get_result()->fetch_assoc();
    $big_monthly[] = $res['big_sold'] ?? 0;
    $small_monthly[] = $res['small_sold'] ?? 0;
}

// Sales distribution by branch (Doughnut chart)
$branch_chart_res = $conn->query("
    SELECT b.branch_name, 
        SUM(s.big_trays_sold*12 + s.small_trays_sold*6) AS eggs_sold
    FROM branches b
    LEFT JOIN sales s ON s.branch_id = b.id
    GROUP BY b.id
");
$branch_labels = [];
$branch_data   = [];
while($row = $branch_chart_res->fetch_assoc()){
    $branch_labels[] = $row['branch_name'];
    $branch_data[]   = $row['eggs_sold'] ?? 0;
}

// Goals: Completion % based on thresholds
$goals = [
    'Orders'      => min(100, round(($total_orders/5000)*100)), // example target 5000 orders
    'Deliveries'  => min(100, round(($total_orders/5000)*100)), // same as orders for demo
    'Inventory'   => min(100, round(($total_eggs_inventory/100000)*100)), // target 100k eggs
    'Revenue'     => min(100, round(($total_sales/500000)*100)), // target 500k revenue
];
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Fresh Farm Egg | Admin Dashboard</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<style>
/* ===== GLOBAL ===== */
*{margin:0;padding:0;box-sizing:border-box;font-family:'Segoe UI',Tahoma,Geneva,Verdana,sans-serif;}
body{display:flex;background:#e6f4ea;color:#2d6a4f;transition:0.3s;overflow-x:hidden;}

/* DARK MODE */
body.dark{background:#121821;color:#e0e0e0;}
body.dark .sidebar{background:#0f172a;}
body.dark .sidebar h2{color:#fff;}
body.dark .sidebar a.active,body.dark .sidebar a:hover{background:#2563eb;color:#fff;}
body.dark .logout a{background:#b91c1c;}
body.dark .card,body.dark .section,body.dark .channel,body.dark .goal{background:#1e293b;color:#e0e0e0;}
body.dark .dark-toggle{background:#334155;color:#fff;}
body.dark .dark-toggle:hover{background:#1e293b;}

/* SIDEBAR */
.sidebar{width:250px;background:#38b000;color:#fff;display:flex;flex-direction:column;height:100vh;padding:25px;position:fixed;left:0;top:0;}
.sidebar h2{text-align:center;font-size:1.8rem;margin-bottom:30px;font-weight:700;}
.sidebar a{display:flex;align-items:center;gap:12px;padding:12px 18px;margin-bottom:10px;background:#2d6a4f;color:#fff;border-radius:10px;font-weight:600;text-decoration:none;transition:0.3s;}
.sidebar a i{width:22px;text-align:center;}
.sidebar a.active,.sidebar a:hover{background:#70d6ff;color:#000;}
.sidebar .logout{background:#d90429;margin-top:auto;}
.sidebar .logout:hover{background:#9b0a20;}

/* MAIN CONTENT */
.main{flex:1;margin-left:250px;padding:30px;transition:0.3s;}
.header{display:flex;justify-content:space-between;align-items:center;margin-bottom:25px;}
.header h1{font-size:2.4rem;color:#2d6a4f;}
.header p{color:#52796f;font-size:1rem;}
.dark-toggle{padding:10px 20px;border:none;border-radius:6px;background:#334155;color:#fff;cursor:pointer;font-weight:600;transition:0.3s;}
.dark-toggle:hover{background:#1e293b;}

/* DASHBOARD CARDS */
.cards{display:grid;grid-template-columns:repeat(4,1fr);gap:20px;margin-bottom:30px;}
.card{padding:20px;border-radius:15px;box-shadow:0 8px 20px rgba(0,0,0,0.1);text-align:center;transition:0.3s;color:#fff;position:relative;overflow:hidden;}
.card:hover{transform:translateY(-6px);}
.card h3{font-size:14px;margin-bottom:10px;font-weight:600;}
.card p{font-size:28px;font-weight:bold;margin-bottom:5px;transition:0.3s;}
.card .kpi{font-size:0.9rem;margin-top:5px;display:flex;align-items:center;justify-content:center;gap:5px;}
.card-icon{font-size:28px;margin-bottom:8px;display:block;}

/* Gradient Cards */
.card.sales{background:linear-gradient(135deg,#16a34a,#4ade80);}
.card.orders{background:linear-gradient(135deg,#2563eb,#60a5fa);}
.card.inventory{background:linear-gradient(135deg,#f97316,#fb923c);}
.card.branches{background:linear-gradient(135deg,#7c3aed,#a78bfa);}

/* SECTIONS */
.section{background:#f8fafc;padding:20px;border-radius:15px;margin-bottom:30px;box-shadow:0 8px 20px rgba(0,0,0,0.08);}
.section h2{margin-bottom:15px;color:#2d6a4f;font-size:18px;font-weight:600;}
.combined-charts{display:flex;gap:15px;flex-wrap:wrap;margin-bottom:15px;}
.combined-charts .chart-container{background:#fff;padding:15px;border-radius:12px;box-shadow:0 8px 20px rgba(0,0,0,0.05);flex:1;min-width:300px;height:250px;}
.combined-charts canvas.chart{height:100% !important;}

/* GOALS GRID */
.goals{display:grid;grid-template-columns:repeat(4,1fr);gap:15px;margin-top:15px;}
.goal{background:#e0f2f1;padding:10px;border-radius:10px;text-align:center;transition:0.3s;font-size:0.9rem;position:relative;}
.goal span{font-size:18px;font-weight:bold;display:block;margin-bottom:3px;}
.progress-bar{width:100%;height:12px;background:#d1d5db;border-radius:10px;margin-top:8px;overflow:hidden;}
.progress-bar-fill{height:100%;background:#16a34a;width:0%;border-radius:10px;transition:width 1s ease-in-out;}
</style>
</head>
<body>

<!-- SIDEBAR -->
<div class="sidebar">
    <h2>Admin Panel</h2>
    <a href="dashboard.php" class="active"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
    <a href="branches.php"><i class="fas fa-store"></i> Branches</a>
    <a href="deliveries.php"><i class="fas fa-truck"></i> Deliveries</a>
    <a href="sales.php"><i class="fas fa-chart-line"></i> Sales Report</a>
    <a href="reports.php"><i class="fas fa-file-alt"></i> Reports</a>
    <a href="stocks.php"><i class="fas fa-boxes"></i> Stocks</a>
    <a href="users.php"><i class="fas fa-users"></i> Users</a>
    <a href="logout.php" class="logout"><i class="fas fa-sign-out-alt"></i> Logout</a>
</div>

<!-- MAIN -->
<div class="main">
    <div class="header">
        <div>
            <h1>Admin Dashboard</h1>
            <p>Real-time KPIs and Sales Overview</p>
        </div>
        <button class="dark-toggle" onclick="toggleDark()">🌙 Dark Mode</button>
    </div>

    <!-- METRICS CARDS -->
    <div class="cards">
        <div class="card sales"><i class="fas fa-coins card-icon"></i>
            <h3>Total Sales</h3>
            <p>₱<?php echo number_format($total_sales,2); ?></p>
        </div>
        <div class="card orders"><i class="fas fa-shopping-cart card-icon"></i>
            <h3>Total Orders</h3>
            <p><?php echo $total_orders; ?></p>
        </div>
        <div class="card inventory"><i class="fas fa-egg card-icon"></i>
            <h3>Total Eggs</h3>
            <p><?php echo $total_eggs_inventory; ?></p>
        </div>
        <div class="card branches"><i class="fas fa-store-alt card-icon"></i>
            <h3>Active Branches</h3>
            <p><?php echo $active_branches; ?></p>
        </div>
    </div>

    <!-- COMBINED CHARTS -->
    <div class="section">
        <h2>Monthly Sales (Last 6 Months)</h2>
        <div class="combined-charts">
            <div class="chart-container">
                <canvas id="monthlySalesChart" class="chart"></canvas>
            </div>
            <div class="chart-container">
                <canvas id="branchDistributionChart" class="chart"></canvas>
            </div>
        </div>

        <h2>Goals & Completion</h2>
        <div class="goals">
            <?php foreach($goals as $goal_name => $goal_val): ?>
                <div class="goal">
                    <strong><?php echo $goal_name; ?></strong>
                    <span><?php echo $goal_val; ?>%</span>
                    <div class="progress-bar">
                        <div class="progress-bar-fill" style="width:<?php echo $goal_val; ?>%"></div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<script>
// Dark mode
function toggleDark(){
    document.body.classList.toggle("dark");
    localStorage.setItem('darkMode', document.body.classList.contains('dark') ? 'enabled' : 'disabled');
}
if(localStorage.getItem('darkMode')==='enabled'){document.body.classList.add('dark');}

// Monthly Sales Line Chart
new Chart(document.getElementById('monthlySalesChart'),{
    type:'line',
    data:{
        labels:<?php echo json_encode($months); ?>,
        datasets:[
            {label:'Big Eggs', data:<?php echo json_encode($big_monthly); ?>, borderColor:'#16a34a', fill:false, tension:0.3, pointBackgroundColor:'#16a34a'},
            {label:'Small Eggs', data:<?php echo json_encode($small_monthly); ?>, borderColor:'#2563eb', fill:false, tension:0.3, pointBackgroundColor:'#2563eb'}
        ]
    },
    options:{plugins:{legend:{position:'top'}},animation:{duration:1000}}
});

// Branch Distribution Doughnut Chart
new Chart(document.getElementById('branchDistributionChart'),{
    type:'doughnut',
    data:{
        labels:<?php echo json_encode($branch_labels); ?>,
        datasets:[{
            data:<?php echo json_encode($branch_data); ?>,
            backgroundColor:['#16a34a','#2563eb','#f97316','#7c3aed','#4ade80','#fb923c']
        }]
    },
    options:{plugins:{legend:{position:'bottom'}},animation:{animateScale:true,animateRotate:true}}
});
</script>

</body>
</html>