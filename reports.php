<?php
// Start session only if none exists
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include database
include __DIR__ . '/includes/db.php'; // __DIR__ ensures it looks in the same folder as reports.php

// Security check
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
    header("Location: ../login.php"); // Assuming login.php is in freshfarmegg/
    exit();
}

/* HANDLE REPLY */
if(isset($_POST['reply_request'])){
    $request_id = (int)$_POST['request_id'];
    $reply_msg  = trim($_POST['reply_msg']);
    $status     = $_POST['status'];

    if(!empty($reply_msg) && in_array($status, ['confirmed','rejected'])){
        $stmt = $conn->prepare("UPDATE requests SET admin_reply=?, status=? WHERE id=?");
        $stmt->bind_param("ssi", $reply_msg, $status, $request_id);
        $stmt->execute();
        $success_reply = "Reply sent successfully!";
    } else {
        $error_reply = "Please fill all fields.";
    }
}

/* FETCH DATA */
$requests = $conn->query("
    SELECT r.*, b.branch_name 
    FROM requests r 
    JOIN branches b ON r.branch_id = b.id 
    ORDER BY r.request_datetime DESC
")->fetch_all(MYSQLI_ASSOC);

$pending_count = $confirmed_count = $rejected_count = 0;
foreach($requests as $r){
    if($r['status']=='pending') $pending_count++;
    if($r['status']=='confirmed') $confirmed_count++;
    if($r['status']=='rejected') $rejected_count++;
}

$returns = $conn->query("
    SELECT ret.*, b.branch_name 
    FROM returns ret 
    JOIN branches b ON ret.branch_id = b.id
    ORDER BY ret.return_datetime DESC
")->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Reports - Admin Panel</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

<style>
*{margin:0;padding:0;box-sizing:border-box;font-family:'Segoe UI';}
body{background:#f0fdf4;color:#2d6a4f;transition:0.3s;}
body.dark{background:#121821;color:#e0e0e0;}

.wrapper{display:flex;min-height:100vh;}

/* SIDEBAR */
.sidebar{
    width:240px;background:#38b000;color:#fff;
    padding:25px;position:fixed;height:100%;
    display:flex;flex-direction:column;
}
.sidebar h2{text-align:center;margin-bottom:30px;}
.sidebar a{
    display:flex;align-items:center;gap:10px;
    padding:12px;margin-bottom:10px;
    background:#2d6a4f;color:#fff;
    border-radius:10px;text-decoration:none;
    transition:0.3s;
}
.sidebar a.active,.sidebar a:hover{background:#70d6ff;color:#000;}
.sidebar .logout{background:#d90429;margin-top:auto;}
.sidebar .logout:hover{background:#9b0a20;}

body.dark .sidebar{background:#0f172a;}
body.dark .sidebar a.active,body.dark .sidebar a:hover{background:#2563eb;color:#fff;}

/* MAIN */
.main-content{margin-left:260px;padding:30px;flex:1;}

.header{display:flex;justify-content:space-between;align-items:center;margin-bottom:30px;}
.header h1{font-size:2rem;}
.header p{color:#52796f;}

#darkToggle{
    padding:8px 15px;background:#334155;
    color:#fff;border:none;border-radius:6px;
    cursor:pointer;
}

/* CARDS */
.cards{
    display:grid;
    grid-template-columns:repeat(auto-fit,minmax(200px,1fr));
    gap:20px;margin-bottom:25px;
}
.card{
    background:#fff;padding:20px;
    border-radius:12px;
    box-shadow:0 10px 25px rgba(0,0,0,0.08);
    text-align:center;
}
.card h3{margin-bottom:10px;}
.card p{font-size:1.5rem;font-weight:bold;}

body.dark .card{background:#1e293b;}

/* TABLE */
.table-card{
    background:#fff;padding:20px;
    border-radius:12px;
    box-shadow:0 10px 25px rgba(0,0,0,0.08);
    margin-bottom:25px;
}
table{width:100%;border-collapse:collapse;}
th,td{padding:12px;text-align:center;border-bottom:1px solid #ddd;}
th{background:#38b000;color:#fff;}
tr:nth-child(even){background:#f0fdf4;}

body.dark .table-card{background:#1e293b;}
body.dark table{color:#e0e0e0;}
body.dark th{background:#2563eb;}
body.dark tr:nth-child(even){background:#1e293b;}

/* STATUS COLORS */
.pending{background:#fff3cd !important;}
.confirmed{background:#d1fae5 !important;}
.rejected{background:#fcd5ce !important;}

textarea,select{
    width:100%;padding:8px;margin-bottom:5px;
    border-radius:6px;border:1px solid #ccc;
}
button{
    padding:8px 12px;background:#38b000;
    color:#fff;border:none;border-radius:6px;
    cursor:pointer;
}
button:hover{background:#2d6a4f;}
</style>
</head>
<body>

<div class="wrapper">

<!-- SIDEBAR -->
<div class="sidebar">
    <h2>Admin Panel</h2>
    <a href="dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
    <a href="branches.php"><i class="fas fa-store"></i> Branches</a>
    <a href="deliveries.php"><i class="fas fa-truck"></i> Deliveries</a>
    <a href="sales.php"><i class="fas fa-chart-line"></i> Sales Report</a>
    <a href="reports.php" class="active"><i class="fas fa-file-alt"></i> Reports</a>
    <a href="stocks.php"><i class="fas fa-boxes"></i> Stocks</a>
    <a href="users.php"><i class="fas fa-users"></i> Users</a>
    <a href="../home.php" class="logout"><i class="fas fa-sign-out-alt"></i> Logout</a>
</div>

<div class="main-content">

<div class="header">
    <div>
        <h1>Reports</h1>
        <p>Manage requests and returned eggs</p>
    </div>
    <button id="darkToggle">🌙 Dark Mode</button>
</div>

<!-- SUMMARY -->
<div class="cards">
    <div class="card"><h3>Pending</h3><p><?= $pending_count ?></p></div>
    <div class="card"><h3>Confirmed</h3><p><?= $confirmed_count ?></p></div>
    <div class="card"><h3>Rejected</h3><p><?= $rejected_count ?></p></div>
</div>

<!-- REQUESTS -->
<div class="table-card">
<h2>Branch Requests</h2>
<table>
<tr>
<th>ID</th><th>Branch</th><th>Big</th><th>Small</th>
<th>Message</th><th>Status</th><th>Reply</th><th>Action</th>
</tr>

<?php foreach($requests as $r): ?>
<tr class="<?= $r['status']; ?>">
<td><?= $r['id'] ?></td>
<td><?= htmlspecialchars($r['branch_name']) ?></td>
<td><?= $r['big_trays'] ?></td>
<td><?= $r['small_trays'] ?></td>
<td><?= htmlspecialchars($r['message']) ?></td>
<td><?= ucfirst($r['status']) ?></td>
<td><?= htmlspecialchars($r['admin_reply']) ?></td>
<td>
<?php if($r['status']=='pending'): ?>
<form method="post">
<input type="hidden" name="request_id" value="<?= $r['id'] ?>">
<textarea name="reply_msg" required></textarea>
<select name="status">
<option value="confirmed">Confirm</option>
<option value="rejected">Reject</option>
</select>
<button name="reply_request">Send</button>
</form>
<?php else: ?>✔ Done<?php endif; ?>
</td>
</tr>
<?php endforeach; ?>

</table>
</div>

<!-- RETURNS -->
<div class="table-card">
<h2>Returned Eggs</h2>
<table>
<tr>
<th>ID</th><th>Branch</th><th>Type</th>
<th>Big</th><th>Small</th><th>Eggs</th>
<th>Remarks</th><th>Date</th>
</tr>

<?php foreach($returns as $r): ?>
<tr>
<td><?= $r['id'] ?></td>
<td><?= htmlspecialchars($r['branch_name']) ?></td>
<td><?= $r['return_type'] ?></td>
<td><?= $r['big_trays'] ?></td>
<td><?= $r['small_trays'] ?></td>
<td><?= $r['egg_pieces'] ?></td>
<td><?= htmlspecialchars($r['remarks']) ?></td>
<td><?= $r['return_datetime'] ?></td>
</tr>
<?php endforeach; ?>

</table>
</div>

</div>
</div>

<script>
const body = document.body;
const toggle = document.getElementById("darkToggle");

if(localStorage.getItem("darkMode")==="enabled"){
    body.classList.add("dark");
    toggle.textContent="☀️ Light Mode";
}

toggle.onclick=()=>{
    body.classList.toggle("dark");
    if(body.classList.contains("dark")){
        localStorage.setItem("darkMode","enabled");
        toggle.textContent="☀️ Light Mode";
    } else {
        localStorage.setItem("darkMode","disabled");
        toggle.textContent="🌙 Dark Mode";
    }
};
</script>

</body>
</html>