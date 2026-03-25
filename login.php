<?php
session_start();
require 'includes/db.php';
require 'includes/functions.php';

$error = "";

// Preset admin credentials
$PRESET_USERNAME = 'Admin';
$PRESET_EMAIL = 'admin@freshfarmegg.com';
$PRESET_PASSWORD_HASH = '$2y$10$qZZ633.Lo33PieSnJgNHNuStiZn0drV.zH9Z8/IA741Ik4Wj9rmii';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $username = sanitize($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($username) || empty($password)) {
        $error = "Please fill in all fields!";
    } else {
        if (
            ($username === $PRESET_USERNAME || $username === $PRESET_EMAIL) &&
            password_verify($password, $PRESET_PASSWORD_HASH)
        ) {
            // ✅ FIXED SESSION
            $_SESSION['user'] = [
                'id' => 1,
                'username' => $PRESET_USERNAME,
                'email' => $PRESET_EMAIL,
                'role' => 'admin'
            ];

            header("Location: /freshfarmegg/admin/dashboard.php");
            exit;
        } else {
            $error = "Invalid username or password!";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Admin Login | FreshFarmEgg</title>
<style>
*{margin:0;padding:0;box-sizing:border-box;font-family:'Segoe UI', Tahoma, sans-serif;}
body.login-body{background:#d8f3dc;min-height:100vh;display:flex;justify-content:center;align-items:center;}
.login-container{display:flex;width:900px;max-width:95%;height:500px;background:#fff;border-radius:20px;overflow:hidden;box-shadow:0 10px 25px rgba(0,0,0,0.15);}
.left-panel{flex:1;background:linear-gradient(135deg,#38b000,#70d6ff);color:#fff;padding:40px;display:flex;flex-direction:column;justify-content:center;}
.left-panel h1{font-size:2.5em;margin-bottom:15px;}
.left-panel p{font-size:1.1em;margin-bottom:25px;}
.btn-outline{border:2px solid #fff;color:#fff;padding:12px 30px;border-radius:25px;font-weight:bold;text-decoration:none;width:fit-content;transition:0.3s;}
.btn-outline:hover{background:#fff;color:#38b000;}
.right-panel{flex:1;padding:50px 40px;display:flex;flex-direction:column;justify-content:center;background:#e6f4ea;}
.right-panel h2{margin-bottom:20px;color:#2d6a4f;font-size:2em;text-align:center;}
.error{color:#d00000;margin-bottom:15px;text-align:center;font-weight:bold;}
.right-panel form{display:flex;flex-direction:column;}
.right-panel input{padding:15px;margin-bottom:20px;border-radius:10px;border:1px solid #ccc;font-size:1em;}
.right-panel button{padding:15px;border:none;background:#38b000;color:#fff;font-weight:bold;font-size:1em;border-radius:25px;cursor:pointer;}
.right-panel button:hover{background:#2d6a4f;}
@media(max-width:850px){.login-container{flex-direction:column;height:auto;}}
</style>
</head>
<body class="login-body">

<div class="login-container">

    <div class="left-panel">
        <h1>Welcome 👋</h1>
        <p>FreshFarmEgg Admin Portal</p>
        <a href="../home.php" class="btn-outline">Home</a>
    </div>

    <div class="right-panel">
        <h2>Admin Login</h2>

        <?php if ($error): ?>
            <p class="error"><?php echo $error; ?></p>
        <?php endif; ?>

        <form method="POST">
            <input type="text" name="username" placeholder="Username or Email" required>
            <input type="password" name="password" placeholder="Password" required>
            <button type="submit">Login</button>
        </form>
    </div>

</div>

</body>
</html>