<?php
session_start();
include 'includes/db.php';

/* ===== AUTO LOGIN (LOCAL TESTING) ===== */
if(!isset($_SESSION['admin'])){
    $_SESSION['admin'] = 1;
}

/* ===== AJAX VIEW DELIVERIES ===== */
if(isset($_GET['ajax_branch'])){
    $id = (int)$_GET['ajax_branch'];

    $branch = $conn->query("SELECT * FROM branches WHERE id=$id")->fetch_assoc();
    if(!$branch){ exit; }

    $deliveries = [];
    $summary = ['big'=>0,'small'=>0,'eggs'=>0];

    $res = $conn->query("SELECT * FROM deliveries WHERE branch_id=$id ORDER BY delivery_datetime DESC");
    while($d = $res->fetch_assoc()){
        $deliveries[] = $d;
        $summary['big'] += $d['big_trays'];
        $summary['small'] += $d['small_trays'];
        $summary['eggs'] += ($d['big_trays']*12)+($d['small_trays']*6); // Corrected
    }

    echo json_encode([
        'branch'=>$branch,
        'summary'=>$summary,
        'deliveries'=>$deliveries
    ]);
    exit;
}

/* ===== PREDEFINED BRANCHES ===== */
$predefined_branches = [
    'Iloilo Supermart Villa',
    'Iloilo Supermart Molo',
    'Iloilo Supermart Atrium',
    'Iloilo Supermart GQ',
    'Iloilo Supermart Washington'
];

/* ===== DASHBOARD COUNTS ===== */
$total_deliveries = $conn->query("SELECT COUNT(*) t FROM deliveries")->fetch_assoc()['t'];
$total_eggs = $conn->query("SELECT SUM(big_trays*12 + small_trays*6) t FROM deliveries")->fetch_assoc()['t']; // Corrected
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Branches | Admin Panel</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

<style>
/* ===== GLOBAL ===== */
*{margin:0;padding:0;box-sizing:border-box;font-family:'Segoe UI',Tahoma,Verdana,sans-serif;}
body{background:#e6f4ea;color:#2d6a4f;transition:0.3s;}

/* DARK MODE */
body.dark{background:#121821;color:#e0e0e0;}
body.dark .sidebar{background:#0f172a;}
body.dark .sidebar a.active, body.dark .sidebar a:hover{background:#2563eb;color:#fff;}
body.dark .cards .card, body.dark .section, body.dark .summary-card, body.dark .modal-content{background:#1e293b;color:#e0e0e0;}
body.dark .dark-toggle{background:#334155;color:#fff;}
body.dark .dark-toggle:hover{background:#1e293b;}

/* WRAPPER */
.wrapper{display:flex;min-height:100vh}

/* SIDEBAR */
.sidebar{
    width:250px;
    background:#38b000;
    color:#fff;
    display:flex;
    flex-direction:column;
    padding:25px;
}
.sidebar h2{text-align:center;margin-bottom:30px;font-size:1.8rem;font-weight:700;}
.sidebar a{display:flex;align-items:center;gap:12px;padding:12px 18px;margin-bottom:10px;background:#2d6a4f;color:#fff;border-radius:10px;font-weight:600;text-decoration:none;transition:0.3s;}
.sidebar a i{width:22px;text-align:center;}
.sidebar a.active, .sidebar a:hover{background:#70d6ff;color:#000;}
.sidebar .logout{margin-top:auto;background:#d90429;}
.sidebar .logout:hover{background:#9b0a20;}

/* MAIN CONTENT */
.main-content{flex:1;padding:30px;transition:0.3s;}
.header{display:flex;justify-content:space-between;align-items:center;margin-bottom:25px;}
.header h1{font-size:2.4rem;}
.dark-toggle{padding:10px 20px;border:none;border-radius:6px;background:#334155;color:#fff;cursor:pointer;font-weight:600;transition:0.3s;}
.dark-toggle:hover{background:#1e293b;}

/* DASHBOARD CARDS */
.cards{display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:20px;margin-bottom:30px;}
.card{padding:20px;border-radius:15px;box-shadow:0 8px 20px rgba(0,0,0,0.1);text-align:center;transition:0.3s;color:#fff;position:relative;overflow:hidden;}
.card:hover{transform:translateY(-6px);}
.card h3{font-size:14px;margin-bottom:10px;font-weight:600;}
.card p{font-size:28px;font-weight:bold;margin-bottom:5px;}
.card-icon{font-size:28px;margin-bottom:8px;display:block;}

/* Gradient Cards */
.card.branches{background:linear-gradient(135deg,#7c3aed,#a78bfa);}
.card.deliveries{background:linear-gradient(135deg,#2563eb,#60a5fa);}
.card.inventory{background:linear-gradient(135deg,#f97316,#fb923c);}

/* SECTION */
.section{background:#f8fafc;padding:20px;border-radius:15px;margin-bottom:30px;box-shadow:0 8px 20px rgba(0,0,0,0.08);}
.section table{width:100%;border-collapse:collapse;border-radius:12px;overflow:hidden;background:#fff;box-shadow:0 8px 20px rgba(0,0,0,0.05);}
.section th, .section td{padding:12px;text-align:center;}
.section th{background:#38b000;color:#fff;}
.section tr:nth-child(even){background:#f6fbf7;}
.view-btn{background:#38b000;color:#fff;border:none;padding:6px 14px;border-radius:8px;cursor:pointer;transition:0.3s;}
.view-btn:hover{background:#70d6ff;color:#000;}

/* MODAL */
.modal{display:none;position:fixed;inset:0;background:rgba(0,0,0,0.55);justify-content:center;align-items:center;z-index:1000;}
.modal-content{background:#fff;width:90%;max-width:900px;max-height:85vh;overflow-y:auto;border-radius:15px;padding:25px;position:relative;}
.close-btn{position:absolute;top:15px;right:20px;font-size:26px;cursor:pointer;color:#d90429;}

/* SUMMARY CARDS */
.summary-box{display:flex;gap:15px;justify-content:center;flex-wrap:wrap;margin:20px 0;}
.summary-card{background:#e0f2f1;padding:15px;border-radius:12px;min-width:160px;text-align:center;transition:0.3s;}
.summary-card:hover{background:#b2dfdb;}

/* RESPONSIVE */
@media(max-width:768px){
    .main-content{padding:20px;}
    .sidebar{width:100%;flex-direction:row;overflow-x:auto;padding:15px;}
    .sidebar a{margin-right:8px;margin-bottom:0;}
    .cards{grid-template-columns:repeat(auto-fit,minmax(140px,1fr));}
}
</style>
</head>

<body>
<div class="wrapper">

<!-- SIDEBAR -->
<div class="sidebar">
    <h2>Admin Panel</h2>
    <a href="dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
    <a href="branches.php" class="active"><i class="fas fa-store"></i> Branches</a>
    <a href="deliveries.php"><i class="fas fa-truck"></i> Deliveries</a>
    <a href="sales.php"><i class="fas fa-chart-line"></i> Sales Report</a>
    <a href="reports.php"><i class="fas fa-file-alt"></i> Reports</a>
    <a href="stocks.php"><i class="fas fa-boxes"></i> Stocks</a>
    <a href="users.php"><i class="fas fa-users"></i> Users</a>
    <a href="logout.php" class="logout"><i class="fas fa-sign-out-alt"></i> Logout</a>
</div>

<!-- MAIN CONTENT -->
<div class="main-content">
    <div class="header">
        <div>
            <h1>Branches Management</h1>
            <p>Click “View Deliveries” to see branch details</p>
        </div>
        <button id="darkToggle" class="dark-toggle">🌙 Dark Mode</button>
    </div>

    <!-- METRICS CARDS -->
    <div class="cards">
        <div class="card branches">
            <i class="fas fa-store-alt card-icon"></i>
            <h3>Total Branches</h3>
            <p><?=count($predefined_branches)?></p>
        </div>
        <div class="card deliveries">
            <i class="fas fa-truck card-icon"></i>
            <h3>Total Deliveries</h3>
            <p><?=$total_deliveries?></p>
        </div>
        <div class="card inventory">
            <i class="fas fa-egg card-icon"></i>
            <h3>Total Eggs</h3>
            <p><?=$total_eggs?></p>
        </div>
    </div>

    <!-- BRANCHES TABLE -->
    <div class="section">
        <table>
            <tr><th>#</th><th>Branch Name</th><th>Action</th></tr>
            <?php $i=1; foreach($predefined_branches as $b):
            $r=$conn->query("SELECT id FROM branches WHERE branch_name='".$conn->real_escape_string($b)."'")->fetch_assoc();
            if(!$r) continue; ?>
            <tr>
                <td><?=$i++?></td>
                <td><?=htmlspecialchars($b)?></td>
                <td>
                    <button class="view-btn" onclick="openDeliveries(<?=$r['id']?>)">
                        <i class="fas fa-eye"></i> View Deliveries
                    </button>
                </td>
            </tr>
            <?php endforeach; ?>
        </table>
    </div>
</div>

<!-- MODAL -->
<div id="deliveriesModal" class="modal">
    <div class="modal-content">
        <span class="close-btn" onclick="closeModal()">&times;</span>
        <h2 id="modalBranchName"></h2>
        <div class="summary-box" id="modalSummary"></div>
        <div id="modalTable"></div>
    </div>
</div>

<script>
// DARK MODE
const body=document.body;
document.getElementById("darkToggle").onclick=()=>{
    body.classList.toggle("dark");
    localStorage.setItem('darkMode', body.classList.contains('dark') ? 'enabled' : 'disabled');
};
if(localStorage.getItem('darkMode')==='enabled'){body.classList.add('dark');}

// OPEN MODAL FUNCTION
function openDeliveries(id){
    fetch("?ajax_branch="+id)
    .then(r=>r.json())
    .then(d=>{
        document.getElementById("modalBranchName").innerText=d.branch.branch_name+" – Delivery Details";
        document.getElementById("modalSummary").innerHTML=`
            <div class="summary-card"><h3>Big Trays</h3><p>${d.summary.big}</p></div>
            <div class="summary-card"><h3>Small Trays</h3><p>${d.summary.small}</p></div>
            <div class="summary-card"><h3>Total Eggs</h3><p>${d.summary.eggs}</p></div>`;
        
        let t=`<table><tr><th>ID</th><th>Big</th><th>Small</th><th>Total Eggs</th><th>Date</th></tr>`;
        if(d.deliveries.length){
            d.deliveries.forEach(x=>{
                t+=`<tr>
                    <td>${x.id}</td>
                    <td>${x.big_trays}</td>
                    <td>${x.small_trays}</td>
                    <td>${x.big_trays*12+x.small_trays*6}</td>
                    <td>${x.delivery_datetime}</td>
                </tr>`;
            });
        } else {
            t+=`<tr><td colspan="5">No deliveries found</td></tr>`;
        }
        t+=`</table>`;
        document.getElementById("modalTable").innerHTML=t;
        document.getElementById("deliveriesModal").style.display="flex";
    });
}

function closeModal(){document.getElementById("deliveriesModal").style.display="none";}
</script>

</body>
</html>