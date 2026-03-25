<?php
session_start();
include '../config/db.php';

// Protect page - admin only
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}

// Safe session variables
$user       = $_SESSION['user'];
$user_id    = $user['id'] ?? 0;
$user_name  = $user['name'] ?? 'Admin';
$user_email = $user['email'] ?? 'admin@example.com';
$user_role  = $user['role'] ?? 'admin';
$profile_pic = $user['profile_pic'] ?? 'default.png';

$success = '';
$error   = '';

// --------------------
// Profile Picture Upload
// --------------------
if (isset($_POST['upload'])) {
    if (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] === 0) {
        $file = $_FILES['profile_image'];
        $allowed = ['jpg','jpeg','png','gif'];
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

        if (!in_array($ext, $allowed)) {
            $error = "Only JPG, PNG, GIF files are allowed.";
        } elseif ($file['size'] > 2*1024*1024) {
            $error = "File size must be less than 2MB.";
        } else {
            if (!is_dir('../uploads')) mkdir('../uploads', 0777, true);
            $new_name = uniqid('profile_') . '.' . $ext;
            $destination = "../uploads/" . $new_name;
            if (move_uploaded_file($file['tmp_name'], $destination)) {

                $_SESSION['user']['profile_pic'] = $new_name;
                $profile_pic = $new_name;

                if ($user_id > 0) {
                    $stmt = $conn->prepare("UPDATE users SET profile_pic=? WHERE id=?");
                    if ($stmt) {
                        $stmt->bind_param("si", $new_name, $user_id);
                        $stmt->execute();
                        $stmt->close();
                        $success = "Profile picture updated successfully!";
                    } else {
                        $error = "Database error: ".$conn->error;
                    }
                } else {
                    $error = "User ID not found. Cannot update profile picture.";
                }

            } else {
                $error = "Failed to upload image.";
            }
        }
    } else {
        $error = "No file selected.";
    }
}

// --------------------
// Profile Info Update
// --------------------
if (isset($_POST['update_profile'])) {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = trim($_POST['password'] ?? '');
    $confirm_password = trim($_POST['confirm_password'] ?? '');

    if (empty($name) || empty($email)) {
        $error = "Name and Email are required.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Invalid email address.";
    } elseif (!empty($password) && $password !== $confirm_password) {
        $error = "Password and confirmation do not match.";
    } else {
        if ($user_id > 0) {
            if (!empty($password)) {
                $hashed_pass = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $conn->prepare("UPDATE users SET name=?, email=?, password=? WHERE id=?");
                if ($stmt) {
                    $stmt->bind_param("sssi", $name, $email, $hashed_pass, $user_id);
                    $stmt->execute();
                    $stmt->close();
                    $_SESSION['user']['name'] = $name;
                    $_SESSION['user']['email'] = $email;
                    $user_name = $name;
                    $user_email = $email;
                    $success = "Profile updated successfully!";
                } else {
                    $error = "Database error: ".$conn->error;
                }
            } else {
                $stmt = $conn->prepare("UPDATE users SET name=?, email=? WHERE id=?");
                if ($stmt) {
                    $stmt->bind_param("ssi", $name, $email, $user_id);
                    $stmt->execute();
                    $stmt->close();
                    $_SESSION['user']['name'] = $name;
                    $_SESSION['user']['email'] = $email;
                    $user_name = $name;
                    $user_email = $email;
                    $success = "Profile updated successfully!";
                } else {
                    $error = "Database error: ".$conn->error;
                }
            }
        } else {
            $error = "User ID not found. Cannot update profile info.";
        }
    }
}

// --------------------
// Handle logout
// --------------------
if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    session_destroy();
    header("Location: ../home.php?logout=success");
    exit();
}

// --------------------
// Info Panel Data
// --------------------
$total_branches = $conn->query("SELECT COUNT(*) as cnt FROM branches")->fetch_assoc()['cnt'] ?? 0;
$total_users    = $conn->query("SELECT COUNT(*) as cnt FROM users")->fetch_assoc()['cnt'] ?? 0;
$total_sales    = $conn->query("SELECT SUM(total_amount) as total FROM sales")->fetch_assoc()['total'] ?? 0;
$total_eggs     = $conn->query("SELECT SUM(egg_pieces_sold) as total_eggs FROM sales")->fetch_assoc()['total_eggs'] ?? 0;

?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Admin Profile & Dashboard - Fresh Farm Egg</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
*{margin:0;padding:0;box-sizing:border-box;font-family:'Segoe UI',Verdana,Tahoma;}
body{background:#e6f4ea;}
.wrapper{display:flex;min-height:100vh;}
/* Sidebar */
.sidebar{
    width:240px;
    background:#38b000;
    color:#fff;
    padding:25px;
    display:flex;
    flex-direction:column;
}
.sidebar h2{text-align:center;font-size:1.8rem;margin-bottom:30px;font-weight:700;}
.sidebar a{
    display:flex; align-items:center; gap:10px;
    padding:12px 18px;
    margin-bottom:10px;
    background:#2d6a4f;
    color:#fff;
    border-radius:10px;
    font-weight:600;
    text-decoration:none;
    transition:0.3s;
}
.sidebar a i{width:20px;text-align:center;}
.sidebar a.active, .sidebar a:hover{background:#70d6ff;color:#000;}
.sidebar .logout{background:#d90429;margin-top:auto;}
.sidebar .logout:hover{background:#9b0a20;}
/* Main */
.main-content{flex:1;padding:30px;}
.header h1{font-size:2.2rem;color:#2d6a4f;margin-bottom:5px;}
.header p{color:#52796f;font-size:1rem;margin-bottom:20px;}
/* Flex cards */
.cards-container{display:flex;gap:30px;flex-wrap:wrap;justify-content:center;}
/* Profile Card */
.profile-card, .info-card{
    background:#fff;
    padding:30px;
    border-radius:15px;
    box-shadow:0 10px 25px rgba(0,0,0,0.1);
    flex:1;
    min-width:300px;
}
.profile-card{text-align:center;}
.profile-img{
    width:150px;height:150px;border-radius:50%;object-fit:cover;border:3px solid #38b000;margin-bottom:20px;
}
.profile-info p{font-size:1.1rem;margin:6px 0;}
form{width:100%; margin-top:15px;}
input[type="text"], input[type="email"], input[type="password"], input[type="file"]{
    width:100%; padding:12px; border-radius:8px; border:1px solid #ccc; margin-bottom:12px; font-size:1rem;
}
button.upload-btn, button.update-btn, .btn-profile{
    display:inline-block; padding:12px 25px; background:#38b000; color:#fff; border-radius:25px; font-weight:bold; cursor:pointer; transition:0.3s; margin-top:10px; border:none;
}
button.upload-btn:hover, button.update-btn:hover, .btn-profile:hover{background:#2d6a4f; transform:translateY(-2px);}
.success-msg{color:green;font-weight:bold;margin-bottom:10px;}
.error-msg{color:red;font-weight:bold;margin-bottom:10px;}
/* Info Card */
.info-card h3{margin-bottom:15px;color:#2d6a4f;text-align:center;}
.info-item{display:flex;justify-content:space-between;padding:12px 15px;background:#f3f8f5;margin-bottom:10px;border-radius:8px;font-weight:600;}
/* Responsive */
@media(max-width:768px){
    .sidebar{width:100%;flex-direction:row;overflow-x:auto;height:auto;padding:15px;}
    .sidebar a{margin-right:8px;margin-bottom:0;}
    .main-content{padding:20px;}
    .cards-container{flex-direction:column;align-items:center;}
}
</style>
</head>
<body>

<div class="wrapper">

    <!-- Sidebar -->
    <div class="sidebar">
        <h2>Admin Panel</h2>
        <a href="dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
        <a href="profile.php" class="active"><i class="fas fa-user"></i> Profile</a>
        <a href="../home.php" class="logout"><i class="fas fa-sign-out-alt"></i> Logout</a>
    </div>

    <!-- Main -->
    <div class="main-content">
        <div class="header">
            <h1>Admin Profile & Dashboard</h1>
            <p>Manage your profile and view key information</p>
        </div>

        <?php if($success) echo "<p class='success-msg'>$success</p>"; ?>
        <?php if($error) echo "<p class='error-msg'>$error</p>"; ?>

        <div class="cards-container">

            <!-- Profile Card -->
            <div class="profile-card">
                <img src="../uploads/<?= htmlspecialchars($profile_pic) ?>" alt="Profile Picture" class="profile-img">
                <div class="profile-info">
                    <p><strong>Name:</strong> <?= htmlspecialchars($user_name) ?></p>
                    <p><strong>Email:</strong> <?= htmlspecialchars($user_email) ?></p>
                    <p><strong>Role:</strong> <?= htmlspecialchars($user_role) ?></p>
                </div>

                <form method="post" enctype="multipart/form-data">
                    <input type="file" name="profile_image" accept="image/*" required>
                    <button type="submit" name="upload" class="upload-btn">Upload Profile Picture</button>
                </form>

                <form method="post">
                    <input type="text" name="name" placeholder="Name" value="<?= htmlspecialchars($user_name) ?>" required>
                    <input type="email" name="email" placeholder="Email" value="<?= htmlspecialchars($user_email) ?>" required>
                    <input type="password" name="password" placeholder="New Password">
                    <input type="password" name="confirm_password" placeholder="Confirm New Password">
                    <button type="submit" name="update_profile" class="update-btn">Update Profile</button>
                </form>
            </div>

            <!-- Info Panel -->
            <div class="info-card">
                <h3>Key Information</h3>
                <div class="info-item"><span>Total Branches</span><span><?= $total_branches ?></span></div>
                <div class="info-item"><span>Total Users</span><span><?= $total_users ?></span></div>
                <div class="info-item"><span>Total Sales (₱)</span><span><?= number_format($total_sales,2) ?></span></div>
                <div class="info-item"><span>Total Eggs Sold</span><span><?= $total_eggs ?></span></div>
            </div>

        </div>
    </div>

</div>

</body>
</html>
