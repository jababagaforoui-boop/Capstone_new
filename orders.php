<?php
session_start();

/* ----------------------
   DATABASE CONNECTION
----------------------- */
$servername = "localhost";
$username   = "root";
$password   = ""; 
$dbname     = "freshfarmegg";

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) die("Connection failed: " . $conn->connect_error);

/* ----------------------
   SECURITY CHECK
----------------------- */
if(!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'client'){
    header("Location: orders.php"); 
    exit();
}

$user = $_SESSION['user'];
$branch_id   = $user['branch_id'];
$branch_name = $user['branch_name'];

/* ----------------------
   PRICES
----------------------- */
$price_big_tray   = 106; // updated price per big tray
$price_small_tray = 56;  // updated price per small tray

/* ----------------------
   HANDLE SALES AND UPDATE STOCK
----------------------- */
$success_sale = '';
$stock_alerts = [];

if(isset($_POST['add_sale'])){
    $big_trays_sold   = (int)$_POST['big_trays_sold'];
    $small_trays_sold = (int)$_POST['small_trays_sold'];

    $stmt = $conn->prepare("SELECT big_trays, small_trays FROM inventory WHERE branch_id=? LIMIT 1");
    $stmt->bind_param("i",$branch_id);
    $stmt->execute();
    $inventory = $stmt->get_result()->fetch_assoc();

    $current_big   = $inventory['big_trays'] ?? 0;
    $current_small = $inventory['small_trays'] ?? 0;

    $new_big   = max(0, $current_big - $big_trays_sold);
    $new_small = max(0, $current_small - $small_trays_sold);

    $stmt_update = $conn->prepare("UPDATE inventory SET big_trays=?, small_trays=?, updated_at=NOW() WHERE branch_id=?");
    $stmt_update->bind_param("iii",$new_big,$new_small,$branch_id);
    $stmt_update->execute();

    $stmt_sale = $conn->prepare("INSERT INTO sales(branch_id, big_trays_sold, small_trays_sold, sale_datetime) VALUES(?,?,?,NOW())");
    $stmt_sale->bind_param("iii",$branch_id,$big_trays_sold,$small_trays_sold);
    $stmt_sale->execute();

    $success_sale = "✅ Sale recorded successfully!";

    $low_threshold = 5;
    if($new_big <= $low_threshold) $stock_alerts[] = "⚠ Low Big Trays Stock: Only $new_big left!";
    if($new_small <= $low_threshold) $stock_alerts[] = "⚠ Low Small Trays Stock: Only $new_small left!";
}

/* ----------------------
   HANDLE REQUEST TO ADMIN
----------------------- */
$success_request = '';
$error_request = '';

if(isset($_POST['request_admin'])){
    $request_big   = (int)$_POST['request_big_trays'];
    $request_small = (int)$_POST['request_small_trays'];
    $message       = trim($_POST['message']);

    if($request_big < 0 || $request_small < 0 || empty($message)){
        $error_request = "⚠ Please fill all fields correctly.";
    } else {
        $stmt_req = $conn->prepare("INSERT INTO requests(branch_id, big_trays, small_trays, message, status, request_datetime) VALUES (?,?,?,?, 'pending', NOW())");
        $stmt_req->bind_param("iiis", $branch_id, $request_big, $request_small, $message);
        $stmt_req->execute();
        $success_request = "📨 Request sent successfully!";
    }
}

/* ----------------------
   FETCH SALES
----------------------- */
$stmt = $conn->prepare("SELECT * FROM sales WHERE branch_id=? ORDER BY sale_datetime DESC");
$stmt->bind_param("i",$branch_id);
$stmt->execute();
$result = $stmt->get_result();
$sales = $result->fetch_all(MYSQLI_ASSOC);

/* ----------------------
   CALCULATE TOTALS
----------------------- */
$total_big_trays = 0;
$total_small_trays = 0;
$total_income = 0;

foreach($sales as $s){
    $total_big_trays   += $s['big_trays_sold'];
    $total_small_trays += $s['small_trays_sold'];
    $total_income += ($s['big_trays_sold'] * $price_big_tray) + ($s['small_trays_sold'] * $price_small_tray);
}

/* NEW EGG CALCULATION */
$total_eggs = ($total_big_trays * 12) + ($total_small_trays * 6);
$dozens     = intdiv($total_eggs, 12);
$half_dozen = intdiv($total_eggs % 12, 6);

/* ----------------------
   FETCH ADMIN REPLIES
----------------------- */
$stmt_reply = $conn->prepare("SELECT * FROM requests WHERE branch_id=? AND admin_reply IS NOT NULL ORDER BY request_datetime DESC");
$stmt_reply->bind_param("i", $branch_id);
$stmt_reply->execute();
$admin_replies = $stmt_reply->get_result()->fetch_all(MYSQLI_ASSOC);

if(!isset($_SESSION['shown_replies'])) $_SESSION['shown_replies'] = [];
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Orders - <?php echo htmlspecialchars($branch_name); ?></title>
<style>
*{margin:0;padding:0;box-sizing:border-box;font-family:'Segoe UI',Verdana,Tahoma;}
body{background:#f0fdf4;display:flex;min-height:100vh;}
.sidebar{width:220px;background:#38b000;color:#fff;height:100vh;position:fixed;left:0;top:0;display:flex;flex-direction:column;padding:20px;}
.sidebar h2{margin-bottom:40px;font-size:1.5em;text-align:center;}
.sidebar a{display:block;padding:12px 20px;margin-bottom:15px;background:#2d6a4f;border-radius:10px;color:#fff;text-decoration:none;font-weight:bold;transition:0.3s;}
.sidebar a:hover{background:#70d6ff;color:#000;transform:translateX(5px);}
.sidebar .logout{background:#d00000;margin-top:auto;}
.sidebar .logout:hover{background:#9d0208;transform:translateX(5px);}
.main-content{margin-left:220px;padding:30px;flex:1;}
.card{background:#fff;border-radius:15px;padding:25px;box-shadow:0 8px 20px rgba(0,0,0,0.12);margin-bottom:25px;}
.card h2{color:#2d6a4f;margin-bottom:20px;}
input,textarea,button{padding:12px;margin-bottom:15px;width:100%;border-radius:10px;border:1px solid #ccc;font-size:1em;}
button{background:#38b000;color:#fff;border:none;font-weight:bold;cursor:pointer;transition:0.3s;}
button:hover{background:#2d6a4f;}
table{width:100%;border-collapse:collapse;margin-top:20px;}
th,td{padding:12px;border-bottom:1px solid #ccc;text-align:left;}
th{background:#38b000;color:#fff;}
tr:hover{background:#f0fdf4;}
.success-msg{color:green;margin-bottom:15px;font-weight:bold;}
.error-msg{color:red;margin-bottom:15px;font-weight:bold;}
.alert-msg{color:#d00000;font-weight:bold;margin-bottom:15px;font-size:1.1em;}
.summary-box{background:#f0fdf4;color:#2d6a4f;padding:15px;border-radius:12px;box-shadow:0 4px 12px rgba(0,0,0,0.1);font-size:1.1em;margin-bottom:10px;}
.section-header{font-size:1.5em;color:#2d6a4f;border-bottom:3px solid #38b000;padding-bottom:10px;margin-bottom:15px;}
.flex-grid{display:flex;flex-wrap:wrap;gap:20px;}
.flex-grid .summary-box{flex:1 1 45%;}
@media(max-width:850px){.sidebar{position:relative;width:100%;height:auto;flex-direction:row;overflow-x:auto;}.sidebar a{margin-bottom:0;margin-right:10px;}.main-content{margin-left:0;}}
</style>
<script>
window.addEventListener('DOMContentLoaded', () => {
    <?php foreach($admin_replies as $reply): 
        $reply_id = $reply['id'];
        if(!in_array($reply_id, $_SESSION['shown_replies'])): 
    ?>
        <?php if($reply['status']=='confirmed'): ?>
            alert("✅ Admin confirmed your request for <?php echo $reply['big_trays']; ?> Big Trays and <?php echo $reply['small_trays']; ?> Small Trays!");
        <?php elseif($reply['status']=='rejected'): ?>
            alert("❌ Admin rejected your request for <?php echo $reply['big_trays']; ?> Big Trays and <?php echo $reply['small_trays']; ?> Small Trays!");
        <?php endif; ?>
        <?php $_SESSION['shown_replies'][] = $reply_id; ?>
    <?php endif; endforeach; ?>
});
</script>
</head>
<body>

<div class="sidebar">
<h2>Dashboard</h2>
<a href="dashboard.php">Home</a>
<a href="add_deliveries.php">Deliveries</a>
<a href="orders.php">Orders</a>
<a href="stocks.php">Stocks</a>
<a href="returns.php">Returns</a>
<a href="profile.php">Profile</a>
<a href="../home.php" class="logout">Logout</a>
</div>

<div class="main-content">
    <div class="card">
        <h2>📋 Orders & Sales - <?php echo htmlspecialchars($branch_name); ?></h2>

        <!-- Stock Alerts -->
        <?php if(!empty($stock_alerts)) foreach($stock_alerts as $alert) echo "<div class='alert-msg'>$alert</div>"; ?>

        <!-- Admin Replies -->
        <?php if(!empty($admin_replies)): ?>
        <div class="flex-grid">
            <?php foreach($admin_replies as $reply): ?>
                <div class="summary-box">
                    <div class="section-header">📬 Admin Reply</div>
                    <strong>Request:</strong> Big: <?php echo $reply['big_trays']; ?>, Small: <?php echo $reply['small_trays']; ?><br>
                    <strong>Message:</strong> <?php echo htmlspecialchars($reply['message']); ?><br>
                    <strong>Status:</strong> <?php echo ucfirst($reply['status']); ?><br>
                    <strong>Admin Reply:</strong> <?php echo htmlspecialchars($reply['admin_reply']); ?><br>
                    <em>Requested on: <?php echo $reply['request_datetime']; ?></em>
                </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <!-- Place Sale -->
        <div class="summary-box">
            <div class="section-header">📦 Place Trays Sale</div>
            <?php if($success_sale) echo "<div class='success-msg'>$success_sale</div>"; ?>
            <form method="post">
                <input type="number" name="big_trays_sold" placeholder="Big Trays Sold" min="0" required>
                <input type="number" name="small_trays_sold" placeholder="Small Trays Sold" min="0" required>
                <button type="submit" name="add_sale">Add Sale</button>
            </form>
        </div>

        <!-- Request Admin -->
        <div class="summary-box">
            <div class="section-header">📨 Request Eggs to Admin</div>
            <?php if($success_request) echo "<div class='success-msg'>$success_request</div>"; ?>
            <?php if($error_request) echo "<div class='error-msg'>$error_request</div>"; ?>
            <form method="post">
                <input type="number" name="request_big_trays" placeholder="Big Trays to Request" min="0" required>
                <input type="number" name="request_small_trays" placeholder="Small Trays to Request" min="0" required>
                <textarea name="message" placeholder="Message to admin" required></textarea>
                <button type="submit" name="request_admin">Send Request</button>
            </form>
        </div>

        <!-- Sales Summary -->
        <div class="flex-grid">
            <div class="summary-box">
                <div class="section-header">📊 Total Eggs Sold & Income</div>
                <strong>Total Eggs:</strong> <?php echo $total_eggs; ?> (<?php echo $dozens; ?> dozens, <?php echo $half_dozen; ?> half-dozens)<br>
                <strong>Total Big Trays Sold:</strong> <?php echo $total_big_trays; ?><br>
                <strong>Total Small Trays Sold:</strong> <?php echo $total_small_trays; ?><br>
                <strong>💰 Total Income:</strong> ₱<?php echo number_format($total_income,2); ?>
            </div>
        </div>

        <!-- Sales History Table -->
        <?php if($sales): ?>
        <div class="summary-box">
            <div class="section-header">📝 Sales History</div>
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Big Trays</th>
                        <th>Small Trays</th>
                        <th>Eggs</th>
                        <th>Income</th>
                        <th>Date</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($sales as $s): ?>
                    <tr>
                        <td><?php echo $s['id']; ?></td>
                        <td><?php echo $s['big_trays_sold']; ?></td>
                        <td><?php echo $s['small_trays_sold']; ?></td>
                        <td><?php echo ($s['big_trays_sold']*12 + $s['small_trays_sold']*6); ?></td>
                        <td>₱<?php echo number_format(($s['big_trays_sold']*$price_big_tray)+($s['small_trays_sold']*$price_small_tray),2); ?></td>
                        <td><?php echo $s['sale_datetime']; ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>

    </div>
</div>

</body>
</html>