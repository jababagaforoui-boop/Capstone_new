<?php
session_start();

// Protect page - admin only
if(!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin'){
    header("Location: ../login.php");
    exit();
}

// You can include your database connection here
// include '../config/db.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Admin Panel</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<style>
*{margin:0;padding:0;box-sizing:border-box;font-family:'Segoe UI',Tahoma,Verdana,sans-serif;}
body{background:#e6f4ea;color:#2d6a4f;}

/* Wrapper */
.wrapper{display:flex; min-height:100vh;}

/* Sidebar */
.sidebar{
    width:240px;background:#38b000;color:#fff;padding:25px;display:flex;flex-direction:column;
}
.sidebar h2{text-align:center;font-size:1.8rem;margin-bottom:30px;font-weight:700;}
.sidebar a{display:flex;align-items:center;gap:10px;padding:12px 18px;margin-bottom:10px;background:#2d6a4f;color:#fff;border-radius:10px;font-weight:600;text-decoration:none;transition:0.3s;text-align:left;}
.sidebar a i{width:20px;text-align:center;}
.sidebar a.active,.sidebar a:hover{background:#70d6ff;color:#000;}
.sidebar .logout{background:#d90429;margin-top:auto;}
.sidebar .logout:hover{background:#9b0a20;}

/* Main content */
.main-content{flex:1;padding:30px;}
.header{display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;}
.header h1{font-size:2.2rem;color:#2d6a4f;}
.header p{color:#52796f;font-size:1rem;}

/* Dark Mode Button */
#darkToggle{
    padding:8px 16px;
    border:none;
    border-radius:6px;
    background:#334155;
    color:#fff;
    cursor:pointer;
    font-weight:600;
    transition:0.3s;
}
#darkToggle:hover{background:#1e293b;}

/* Logout Modal */
#logoutModal{
    display:none;
    position:fixed;
    top:0; left:0;
    width:100%; height:100%;
    background:rgba(0,0,0,0.6);
    z-index:9999;
    justify-content:center;
    align-items:center;
}
#logoutModal .modal-content{
    background:#fff;
    padding:30px;
    border-radius:12px;
    max-width:400px;
    width:90%;
    text-align:center;
    box-shadow:0 10px 25px rgba(0,0,0,0.2);
}
#logoutModal h2{margin-bottom:20px; color:#2d6a4f;}
#logoutModal p{margin-bottom:25px; color:#52796f;}
#logoutModal button{
    padding:10px 20px;
    border:none;
    border-radius:6px;
    cursor:pointer;
    font-weight:600;
}
#logoutModal .yes{background:#38b000;color:#fff;margin-right:10px;}
#logoutModal .no{background:#d90429;color:#fff;}

/* Dark Mode */
body.dark{
    background:#121821;
    color:#e0e0e0;
}
body.dark .main-content,
body.dark .sidebar,
body.dark #logoutModal .modal-content{
    background-color:#1e293b;
    color:#e0e0e0;
}
body.dark .sidebar a{color:#e0e0e0;}
body.dark .sidebar a.active, body.dark .sidebar a:hover{background-color:#2563eb;color:#fff;}
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
    <a href="stocks.php"><i class="fas fa-boxes"></i> Stocks</a>
    <a href="users.php"><i class="fas fa-users"></i> Users</a>
    <a href="#" class="logout" onclick="openLogoutModal()"><i class="fas fa-sign-out-alt"></i> Logout</a>
</div>

<!-- Main Content -->
<div class="main-content">
    <div class="header">
        <div>
            <h1>Welcome, <?= htmlspecialchars($_SESSION['user']['name'] ?? 'Admin') ?></h1>
            <p>Admin panel dashboard</p>
        </div>
        <button id="darkToggle">🌙 Dark Mode</button>
    </div>
</div>

</div>

<!-- Logout Modal -->
<div id="logoutModal">
    <div class="modal-content">
        <h2>Confirm Logout</h2>
        <p>Are you sure you want to log out?</p>
        <button class="yes" onclick="confirmLogout()">Yes</button>
        <button class="no" onclick="closeLogoutModal()">No</button>
    </div>
</div>

<script>
// Open and close logout modal
function openLogoutModal(){ 
    document.getElementById('logoutModal').style.display='flex'; 
}
function closeLogoutModal(){ 
    document.getElementById('logoutModal').style.display='none'; 
}
// CHANGE REDIRECT URL HERE
function confirmLogout(){ 
    window.location.href='http://localhost/freshfarmegg/home.php'; 
}

// Dark Mode
const body = document.body;
const darkToggle = document.getElementById("darkToggle");
if(localStorage.getItem("darkMode")==="enabled"){ 
    body.classList.add("dark"); 
    darkToggle.textContent="☀️ Light Mode"; 
}
darkToggle.addEventListener("click", ()=>{
    body.classList.toggle("dark");
    if(body.classList.contains("dark")){
        localStorage.setItem("darkMode","enabled");
        darkToggle.textContent="☀️ Light Mode";
    }else{
        localStorage.setItem("darkMode","disabled");
        darkToggle.textContent="🌙 Dark Mode";
    }
});
</script>
</body>
</html>