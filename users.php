<?php
session_start();
include 'includes/db.php';

// Protect page - admin only
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}

// Dashboard counts
$total_users = $conn->query("SELECT COUNT(*) AS total FROM `user`");
if (!$total_users) { die("Query failed: " . $conn->error); }
$total_users = $total_users->fetch_assoc()['total'];

$total_branches = $conn->query("SELECT COUNT(*) AS total FROM branches");
if (!$total_branches) { die("Query failed: " . $conn->error); }
$total_branches = $total_branches->fetch_assoc()['total'];

// Fetch users with branch name via LEFT JOIN
$users_result = $conn->query("
    SELECT u.id, u.username, u.fullname, u.email, u.contact, u.created_at,
           b.branch_name AS branch
    FROM `user` u
    LEFT JOIN branches b ON u.branch_id = b.id
    ORDER BY u.created_at DESC
");
if (!$users_result) { die("Query failed: " . $conn->error); }
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Users - Admin Panel</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<style>
/* --- Your existing CSS with scroll fix --- */
*{margin:0;padding:0;box-sizing:border-box;font-family:'Segoe UI',Verdana,Tahoma;}
body{background:#e6f4ea;color:#2d6a4f;}
.wrapper{display:flex;min-height:100vh;overflow:hidden;} /* wrapper no scroll */
.sidebar{width:240px;background:#38b000;color:#fff;padding:25px;display:flex;flex-direction:column;}
.sidebar h2{text-align:center;font-size:1.8rem;margin-bottom:30px;font-weight:700;}
.sidebar a{display:flex;align-items:center;gap:10px;padding:12px 18px;margin-bottom:10px;background:#2d6a4f;color:#fff;border-radius:10px;font-weight:600;text-decoration:none;transition:0.3s;text-align:left;}
.sidebar a i{width:20px;text-align:center;}
.sidebar a.active,.sidebar a:hover{background:#70d6ff;color:#000;}
.sidebar .logout{background:#d90429;margin-top:auto;}
.sidebar .logout:hover{background:#9b0a20;}
.main-content{flex:1;padding:30px;height:100vh;overflow-y:auto;} /* scrollable main content */
.header{display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;}
.header h1{font-size:2.2rem;color:#2d6a4f;}
.header p{color:#52796f;font-size:1rem;}
#darkToggle{padding:8px 16px;border:none;border-radius:6px;background:#334155;color:#fff;cursor:pointer;font-weight:600;transition:0.3s;}
#darkToggle:hover{background:#1e293b;}
.dashboard-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(140px,1fr));gap:20px;margin-bottom:25px;}
.dashboard-card{background:#fff;padding:20px;border-radius:12px;box-shadow:0 10px 25px rgba(0,0,0,0.08);text-align:center;transition:0.3s;}
.dashboard-card:hover{transform:translateY(-5px);}
.dashboard-card .icon{font-size:2.5rem;margin-bottom:10px;color:#38b000;}
.dashboard-card h2{font-size:1.8rem;color:#2d6a4f;margin-bottom:5px;}
.dashboard-card p{font-weight:600;color:#52796f;}
.search-container{display:flex;justify-content:flex-start;margin-bottom:15px;}
#searchInput{width:300px;padding:8px 12px;border-radius:8px;border:1px solid #ccc;font-size:0.95rem;transition:0.3s;}
#searchInput:focus{outline:none; border-color:#38b000;}
table{width:100%;border-collapse:collapse;background:#fff;border-radius:12px;overflow:hidden;box-shadow:0 10px 25px rgba(0,0,0,0.1);}
th, td{padding:12px;text-align:center;font-size:1rem;border-bottom:1px solid #ddd;}
th{background:#38b000;color:#fff;font-weight:600;cursor:pointer;}
tr:nth-child(even){background:#f6fbf7;}
tr:hover{background:#e0f4e6;transition:0.2s;}
.actions button{margin:0 2px;padding:5px 8px;border:none;border-radius:6px;cursor:pointer;transition:0.2s;font-size:0.85rem;}
.actions .view{background:#2563eb;color:#fff;}
.actions .delete{background:#d90429;color:#fff;}
.actions button:hover{opacity:0.85;}
.modal{display:none;position:fixed;z-index:999;left:0;top:0;width:100%;height:100%;overflow:auto;background-color:rgba(0,0,0,0.5);}
.modal-content{background:#fff;margin:10% auto;padding:20px;border-radius:12px;width:90%;max-width:500px;position:relative;}
.modal-content h3{margin-bottom:15px;color:#2d6a4f;}
.modal-content p{margin-bottom:8px;color:#52796f;font-size:0.95rem;}
.close-modal{position:absolute;top:10px;right:15px;font-size:1.5rem;color:#333;cursor:pointer;}
.modal form button{padding:8px 12px;border:none;border-radius:6px;cursor:pointer;}
.modal form .delete{background:#d90429;color:#fff;margin-right:5px;}
.modal form .cancel{background:#52796f;color:#fff;}
body.dark{background-color:#121821;color:#e0e0e0;}
body.dark .main-content, body.dark .dashboard-card, body.dark table, body.dark .modal-content{background-color:#1e293b;color:#e0e0e0;}
body.dark .sidebar{background-color:#0f172a;}
body.dark .sidebar a{color:#e0e0e0;}
body.dark .sidebar a.active, body.dark .sidebar a:hover{background-color:#2563eb;color:#fff;}
body.dark th{background-color:#1f2937;color:#e0e0e0;}
body.dark tr:nth-child(even){background-color:#1e293b;}
body.dark tr:hover{background-color:#334155;}
body.dark #searchInput, body.dark input, body.dark select, body.dark textarea, body.dark button{background-color:#1e293b;color:#e0e0e0;border:1px solid #334155;}
</style>
</head>
<body>

<div class="wrapper">
<div class="sidebar">
    <h2>Admin Panel</h2>
    <a href="dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
    <a href="branches.php"><i class="fas fa-store"></i> Branches</a>
    <a href="deliveries.php"><i class="fas fa-truck"></i> Deliveries</a>
    <a href="sales.php"><i class="fas fa-chart-line"></i> Sales Report</a>
    <a href="reports.php"><i class="fas fa-file-alt"></i> Reports</a>
    <a href="stocks.php"><i class="fas fa-boxes"></i> Stocks</a>
    <a href="users.php" class="active"><i class="fas fa-users"></i> Users</a>
    <a href="../home.php" class="logout"><i class="fas fa-sign-out-alt"></i> Logout</a>
</div>

<div class="main-content">
<div class="header">
    <div>
        <h1>Users</h1>
        <p>All registered users and their branches</p>
        <?php if(isset($_GET['msg']) && $_GET['msg']=='deleted'): ?>
            <p style="color:green;margin-top:5px;">User deleted successfully!</p>
        <?php endif; ?>
    </div>
    <button id="darkToggle">🌙 Dark Mode</button>
</div>

<div class="dashboard-grid">
    <div class="dashboard-card">
        <div class="icon"><i class="fas fa-users"></i></div>
        <h2><?= $total_users ?></h2>
        <p>Total Users</p>
    </div>
    <div class="dashboard-card">
        <div class="icon"><i class="fas fa-store"></i></div>
        <h2><?= $total_branches ?></h2>
        <p>Branches</p>
    </div>
</div>

<div class="search-container">
    <input type="text" id="searchInput" placeholder="Search by username, fullname, email, branch..." onkeyup="filterTable()">
</div>

<table id="usersTable">
<thead>
<tr>
    <th>ID</th>
    <th>Username</th>
    <th>Full Name</th>
    <th>Email</th>
    <th>Contact</th>
    <th>Branch</th>
    <th>Actions</th>
</tr>
</thead>
<tbody>
<?php while($user = $users_result->fetch_assoc()): ?>
<tr>
    <td><?= $user['id'] ?></td>
    <td><?= htmlspecialchars($user['username']) ?></td>
    <td><?= htmlspecialchars($user['fullname'] ?? '-') ?></td>
    <td><?= htmlspecialchars($user['email']) ?></td>
    <td><?= htmlspecialchars($user['contact'] ?? '-') ?></td>
    <td><?= htmlspecialchars($user['branch'] ?? 'N/A') ?></td>
    <td class="actions">
        <button class="view" onclick='showViewModal(<?= json_encode($user) ?>)'>View</button>
        <button class="delete" onclick='showDeleteModal(<?= $user["id"] ?>, "<?= htmlspecialchars($user["username"]) ?>")'>Delete</button>
    </td>
</tr>
<?php endwhile; ?>
</tbody>
</table>
</div>
</div>

<!-- View Modal -->
<div id="viewModal" class="modal">
<div class="modal-content">
    <span class="close-modal" onclick="closeViewModal()">&times;</span>
    <h3>User Details</h3>
    <p><strong>Username:</strong> <span id="modalUsername"></span></p>
    <p><strong>Full Name:</strong> <span id="modalFullname"></span></p>
    <p><strong>Email:</strong> <span id="modalEmail"></span></p>
    <p><strong>Contact:</strong> <span id="modalContact"></span></p>
    <p><strong>Branch:</strong> <span id="modalBranch"></span></p>
    <p><strong>Created At:</strong> <span id="modalCreated"></span></p>
</div>
</div>

<!-- Delete Modal -->
<div id="deleteModal" class="modal">
  <div class="modal-content">
    <span class="close-modal" onclick="closeDeleteModal()">&times;</span>
    <h3>Delete User</h3>
    <p>Are you sure you want to delete <strong id="deleteUsername"></strong>?</p>
    <form id="deleteForm" method="POST" action="delete_user.php">
        <input type="hidden" name="id" id="deleteId">
        <button type="submit" class="delete">Yes, Delete</button>
        <button type="button" class="cancel" onclick="closeDeleteModal()">Cancel</button>
    </form>
  </div>
</div>

<script>
// Filter table
function filterTable() {
    let input = document.getElementById("searchInput").value.toUpperCase();
    let tr = document.getElementById("usersTable").getElementsByTagName("tr");
    for (let i = 1; i < tr.length; i++) {
        let tds = tr[i].getElementsByTagName("td");
        let text = tds[1].innerText + " " + tds[2].innerText + " " + tds[3].innerText + " " + tds[5].innerText;
        tr[i].style.display = text.toUpperCase().includes(input) ? "" : "none";
    }
}

// Dark Mode
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

// View Modal
const viewModal = document.getElementById("viewModal");
function showViewModal(user){
    document.getElementById("modalUsername").innerText = user.username;
    document.getElementById("modalFullname").innerText = user.fullname ?? '-';
    document.getElementById("modalEmail").innerText = user.email;
    document.getElementById("modalContact").innerText = user.contact ?? '-';
    document.getElementById("modalBranch").innerText = user.branch ?? 'N/A';
    document.getElementById("modalCreated").innerText = user.created_at;
    viewModal.style.display = "block";
}
function closeViewModal(){ viewModal.style.display = "none"; }

// Delete Modal
const deleteModal = document.getElementById("deleteModal");
function showDeleteModal(id, username){
    document.getElementById("deleteId").value = id;
    document.getElementById("deleteUsername").innerText = username;
    deleteModal.style.display = "block";
}
function closeDeleteModal(){ deleteModal.style.display = "none"; }

// Close modals on outside click
window.onclick = function(event){
    if(event.target === viewModal) closeViewModal();
    if(event.target === deleteModal) closeDeleteModal();
}
</script>
</body>
</html>