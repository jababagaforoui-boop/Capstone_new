<?php
session_start();
include '../config/db.php';

/* ================= SECURITY ================= */
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}

/* ================= BRANCH LIST ================= */
$branches = [];
$res = $conn->query("SELECT id, branch_name FROM branches ORDER BY branch_name ASC");
while ($row = $res->fetch_assoc()) {
    $branches[$row['id']] = $row['branch_name'];
}

/* ================= DAILY DELIVERIES PER BRANCH ================= */
$q = $conn->query("
    SELECT branch_id, DATE(created_at) as d,
           SUM(big_trays) as big,
           SUM(small_trays) as small
    FROM deliveries
    GROUP BY branch_id, DATE(created_at)
    ORDER BY d ASC
");

$dates = [];
$datasets = [];
$colors = ['red','green','blue','orange','brown','purple','teal','gold'];
$c = 0;

foreach ($branches as $id => $name) {
    $datasets["big_$id"] = [
        "label" => "$name - Big",
        "backgroundColor" => $colors[$c++ % count($colors)],
        "data" => []
    ];
    $datasets["small_$id"] = [
        "label" => "$name - Small",
        "backgroundColor" => $colors[$c++ % count($colors)],
        "data" => []
    ];
}

while ($r = $q->fetch_assoc()) {
    $dates[$r['d']] = true;
    $datasets["big_{$r['branch_id']}"]['data'][$r['d']] = (int)$r['big'];
    $datasets["small_{$r['branch_id']}"]['data'][$r['d']] = (int)$r['small'];
}

$labels = array_keys($dates);
sort($labels);

foreach ($datasets as &$ds) {
    $filled = [];
    foreach ($labels as $d) {
        $filled[] = $ds['data'][$d] ?? 0;
    }
    $ds['data'] = $filled;
}
unset($ds);

/* ================= DAILY TOTAL ================= */
$daily = $conn->query("
    SELECT DATE(created_at) as d,
           SUM(big_trays + small_trays) as total
    FROM deliveries
    GROUP BY DATE(created_at)
");

$dailyLabels = [];
$dailyValues = [];
while ($r = $daily->fetch_assoc()) {
    $dailyLabels[] = $r['d'];
    $dailyValues[] = (int)$r['total'];
}

/* ================= MONTHLY TOTAL ================= */
$monthly = $conn->query("
    SELECT DATE_FORMAT(created_at,'%Y-%m') as m,
           SUM(big_trays + small_trays) as total
    FROM deliveries
    GROUP BY m
");

$monthLabels = [];
$monthValues = [];
while ($r = $monthly->fetch_assoc()) {
    $monthLabels[] = $r['m'];
    $monthValues[] = (int)$r['total'];
}

/* ================= SCATTER ================= */
$scatterQ = $conn->query("
    SELECT SUM(big_trays) as big, SUM(small_trays) as small
    FROM deliveries
    GROUP BY DATE(created_at)
");

$scatterData = [];
while ($r = $scatterQ->fetch_assoc()) {
    $scatterData[] = ["x" => (int)$r['big'], "y" => (int)$r['small']];
}
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>Analytics - Fresh Farm Egg</title>
<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/2.9.4/Chart.js"></script>
<style>
body{margin:0;font-family:Segoe UI;background:#f3f8f5}
.wrapper{display:flex;min-height:100vh}
.sidebar{width:240px;background:#fff;padding:25px}
.sidebar a{display:block;padding:12px;margin-bottom:10px;background:#38b000;color:#fff;border-radius:10px;text-decoration:none}
.sidebar a:hover{background:#2d6a4f}
.main{flex:1;padding:30px}
.card{background:#fff;padding:25px;border-radius:15px;margin-bottom:30px;box-shadow:0 5px 15px rgba(0,0,0,.08)}
h2{color:#2d6a4f}
canvas{margin-top:15px}
</style>
</head>

<body>
<div class="wrapper">

<div class="sidebar">
    <a href="dashboard.php">⬅ Dashboard</a>
    <a href="../auth/logout.php" style="background:#d90429">Logout</a>
</div>

<div class="main">

<div class="card">
<h2>Daily Deliveries per Branch</h2>
<canvas id="branchChart"></canvas>
</div>

<div class="card">
<h2>Total Deliveries per Day</h2>
<canvas id="dailyChart"></canvas>
</div>

<div class="card">
<h2>Monthly Deliveries</h2>
<canvas id="monthlyChart"></canvas>
</div>

<div class="card">
<h2>Big vs Small Tray Analysis</h2>
<canvas id="scatterChart"></canvas>
</div>

</div>
</div>

<script>
new Chart("branchChart",{
    type:"bar",
    data:{labels:<?=json_encode($labels)?>,datasets:<?=json_encode(array_values($datasets))?>},
    options:{title:{display:true,text:"Daily Branch Deliveries"},scales:{xAxes:[{stacked:true}],yAxes:[{stacked:true}]}}
});

new Chart("dailyChart",{
    type:"line",
    data:{labels:<?=json_encode($dailyLabels)?>,datasets:[{
        label:"Total Trays",
        data:<?=json_encode($dailyValues)?>,
        borderColor:"green",
        fill:false
    }]},
    options:{title:{display:true,text:"Daily Total Deliveries"}}
});

new Chart("monthlyChart",{
    type:"bar",
    data:{labels:<?=json_encode($monthLabels)?>,datasets:[{
        backgroundColor:"orange",
        data:<?=json_encode($monthValues)?>
    }]},
    options:{legend:{display:false},title:{display:true,text:"Monthly Deliveries"}}
});

new Chart("scatterChart",{
    type:"scatter",
    data:{datasets:[{
        pointRadius:4,
        pointBackgroundColor:"blue",
        data:<?=json_encode($scatterData)?>
    }]},
    options:{title:{display:true,text:"Big vs Small Trays"}}
});
</script>

</body>
</html>
