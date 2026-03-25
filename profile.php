<?php
session_start();
include 'includes/db.php';

/* SECURITY CHECK */
if(!isset($_SESSION['user']) || ($_SESSION['user']['role'] ?? '') !== 'client'){
    header("Location: ../home.php");
    exit();
}

$user = $_SESSION['user'];
$user_id     = $user['id'] ?? null;

$success_msg = '';
$error_msg = '';

/* HANDLE PROFILE UPDATE */
if($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])){
    $username = trim($_POST['username'] ?? '');
    $fullname = trim($_POST['fullname'] ?? '');
    $email    = trim($_POST['email'] ?? '');
    $contact  = trim($_POST['contact'] ?? '');
    $password = trim($_POST['password'] ?? '');
    $confirm  = trim($_POST['confirm_password'] ?? '');
    
    if(empty($username)){
        $error_msg = "Username cannot be empty.";
    } elseif(!empty($password) && $password !== $confirm){
        $error_msg = "Passwords do not match.";
    } else {
        // Handle photo upload
        $photo_path = $user['photo'] ?? null;
        if(!empty($_FILES['photo']['name'])){
            $upload_dir = 'uploads/';
            if(!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);
            $ext = pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION);
            $new_name = 'user_'.$user_id.'_'.time().'.'.$ext;
            $target_file = $upload_dir.$new_name;
            if(move_uploaded_file($_FILES['photo']['tmp_name'], $target_file)){
                $photo_path = $target_file;
            } else {
                $error_msg = "Failed to upload photo.";
            }
        }

        if(!$error_msg && $user_id){
            if(!empty($password)){
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $conn->prepare("UPDATE user SET username=?, fullname=?, email=?, contact=?, password=?, photo=? WHERE id=?");
                if(!$stmt) die("Prepare failed: ".$conn->error);
                $stmt->bind_param("ssssssi", $username, $fullname, $email, $contact, $hash, $photo_path, $user_id);
            } else {
                $stmt = $conn->prepare("UPDATE user SET username=?, fullname=?, email=?, contact=?, photo=? WHERE id=?");
                if(!$stmt) die("Prepare failed: ".$conn->error);
                $stmt->bind_param("sssssi", $username, $fullname, $email, $contact, $photo_path, $user_id);
            }

            if($stmt->execute()){
                $success_msg = "Profile updated successfully!";
                // Update session data
                $_SESSION['user']['username'] = $username;
                $_SESSION['user']['fullname'] = $fullname;
                $_SESSION['user']['email'] = $email;
                $_SESSION['user']['contact'] = $contact;
                $_SESSION['user']['photo'] = $photo_path;
                $user = $_SESSION['user']; // refresh user info
            } else {
                $error_msg = "Update failed: ".$conn->error;
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Client Profile - <?php echo htmlspecialchars($user['fullname'] ?? $user['username']); ?></title>
<style>
*{margin:0;padding:0;box-sizing:border-box;font-family:'Segoe UI',Tahoma,Verdana,sans-serif;}
body{background:#f0fdf4; min-height:100vh; display:flex;}
.sidebar{width:220px; background:#38b000; color:#fff; height:100vh; position:fixed; left:0; top:0; display:flex; flex-direction:column; padding:20px;}
.sidebar h2{margin-bottom:40px; font-size:1.5em; text-align:center;}
.sidebar a{display:block; padding:12px 20px; margin-bottom:15px; background:#2d6a4f; border-radius:10px; color:#fff; text-decoration:none; font-weight:bold;}
.sidebar a:hover{background:#70d6ff; color:#000;}
.sidebar .logout{background:#d00000; margin-top:auto;}
.sidebar .logout:hover{background:#9d0208;}
.main-content{margin-left:220px; padding:30px; flex:1;}
.card{background:#fff; border-radius:15px; padding:25px; box-shadow:0 8px 20px rgba(0,0,0,0.12); margin-bottom:25px;}
.card h2{color:#2d6a4f; margin-bottom:10px;}
input,button{width:100%; padding:12px; margin-bottom:15px; border-radius:10px; border:1px solid #ccc; font-size:1em;}
button{background:#38b000;color:#fff;border:none;font-weight:bold;cursor:pointer;transition:0.3s;}
button:hover{background:#2d6a4f;}
.success-msg{color:green;font-weight:bold;margin-bottom:15px;}
.error-msg{color:red;font-weight:bold;margin-bottom:15px;}
.profile-photo{width:120px;height:120px;border-radius:50%;object-fit:cover;margin-bottom:15px;border:2px solid #2d6a4f;}
.info-row{margin-bottom:15px;}
.info-label{font-weight:bold;margin-bottom:5px; display:block;}
.branch-info{color:#2d6a4f;font-weight:bold;margin-bottom:15px;}
</style>
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
<h2>👤 Client Profile - <?php echo htmlspecialchars($user['fullname'] ?? $user['username']); ?></h2>
<?php if(!empty($user['branch'])): ?>
<p class="branch-info">Branch: <?php echo htmlspecialchars($user['branch']); ?></p>
<?php endif; ?>

<?php if($success_msg): ?><div class="success-msg"><?php echo $success_msg; ?></div><?php endif; ?>
<?php if($error_msg): ?><div class="error-msg"><?php echo $error_msg; ?></div><?php endif; ?>

<!-- Profile Photo -->
<img src="<?php echo !empty($user['photo']) ? htmlspecialchars($user['photo']) : 'https://via.placeholder.com/120?text=No+Photo'; ?>" alt="Profile Photo" class="profile-photo" id="profilePreview">

<form method="post" enctype="multipart/form-data">
    <div class="info-row">
        <label class="info-label" for="photo">Profile Photo</label>
        <input type="file" name="photo" id="photo" accept="image/*" onchange="previewPhoto(event)">
    </div>

    <div class="info-row">
        <label class="info-label" for="username">Username</label>
        <input type="text" name="username" id="username" value="<?php echo htmlspecialchars($user['username']); ?>" required>
    </div>

    <div class="info-row">
        <label class="info-label" for="fullname">Full Name</label>
        <input type="text" name="fullname" id="fullname" value="<?php echo htmlspecialchars($user['fullname'] ?? ''); ?>">
    </div>

    <div class="info-row">
        <label class="info-label" for="email">Email</label>
        <input type="email" name="email" id="email" value="<?php echo htmlspecialchars($user['email'] ?? ''); ?>">
    </div>

    <div class="info-row">
        <label class="info-label" for="contact">Contact Number</label>
        <input type="text" name="contact" id="contact" value="<?php echo htmlspecialchars($user['contact'] ?? ''); ?>">
    </div>

    <div class="info-row">
        <label class="info-label" for="password">New Password (leave blank to keep current)</label>
        <input type="password" name="password" id="password">
    </div>

    <div class="info-row">
        <label class="info-label" for="confirm_password">Confirm New Password</label>
        <input type="password" name="confirm_password" id="confirm_password">
    </div>

    <button type="submit" name="update_profile">Update Profile</button>
</form>
</div>
</div>

<script>
function previewPhoto(event){
    const reader = new FileReader();
    reader.onload = function(){
        document.getElementById('profilePreview').src = reader.result;
    }
    reader.readAsDataURL(event.target.files[0]);
}
</script>

</body>
</html>