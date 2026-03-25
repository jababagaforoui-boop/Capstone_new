<?php
session_start();
require 'includes/db.php';
require 'includes/functions.php';

$error = "";

if($_SERVER['REQUEST_METHOD'] === 'POST'){
    $username = sanitize($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if(empty($username) || empty($password)){
        $error = "Please fill in all fields!";
    } else {
        // Wrap user table in backticks
        $stmt = $conn->prepare("SELECT * FROM `user` WHERE username = ? LIMIT 1");
        if(!$stmt){
            die("DB prepare error: " . $conn->error);
        }

        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();
        $stmt->close();

        if($user && password_verify($password, $user['password'])){
            // Fetch branch name
            $branch_name = 'N/A';
            if(!empty($user['branch_id'])){
                $stmt_branch = $conn->prepare("SELECT branch_name FROM branches WHERE id=? LIMIT 1");
                if($stmt_branch){
                    $stmt_branch->bind_param("i", $user['branch_id']);
                    $stmt_branch->execute();
                    $res_branch = $stmt_branch->get_result();
                    $branch = $res_branch->fetch_assoc();
                    $branch_name = $branch['branch_name'] ?? 'N/A';
                    $stmt_branch->close();
                }
            }

            $_SESSION['user'] = [
                'id' => $user['id'],
                'username' => $user['username'],
                'email' => $user['email'],
                'branch_id' => $user['branch_id'],
                'branch_name' => $branch_name,
                'role' => 'client'
            ];

            header("Location: dashboard.php");
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
<title>User Login | IAS</title>

<style>
*{margin:0;padding:0;box-sizing:border-box;font-family:'Segoe UI',Tahoma;}
body.login-body{background:#d8f3dc;min-height:100vh;display:flex;justify-content:center;align-items:center;}
.login-container{display:flex;width:900px;max-width:95%;height:500px;background:#fff;border-radius:20px;overflow:hidden;box-shadow:0 10px 25px rgba(0,0,0,0.15);}
.left-panel{flex:1;background:linear-gradient(135deg,#38b000,#70d6ff);color:#fff;padding:40px;display:flex;flex-direction:column;justify-content:center;text-shadow:1px 1px 6px rgba(0,0,0,0.3);}
.left-panel h1{font-size:2.5em;margin-bottom:15px;}
.left-panel p{font-size:1.1em;margin-bottom:25px;}

.btn-outline{
    border:2px solid #fff;
    color:#fff;
    padding:12px 30px;
    border-radius:25px;
    font-weight:bold;
    text-decoration:none;
    width:fit-content;
    transition:0.3s;
}
.btn-outline:hover{
    background:#fff;
    color:#38b000;
}

.right-panel{flex:1;padding:50px 40px;display:flex;flex-direction:column;justify-content:center;background:#e6f4ea;}
.right-panel h2{margin-bottom:20px;color:#2d6a4f;font-size:2em;text-align:center;}
.error{color:#d00000;margin-bottom:15px;text-align:center;font-weight:bold;}
.right-panel form{display:flex;flex-direction:column;}
.right-panel input{padding:15px;margin-bottom:20px;border-radius:10px;border:1px solid #ccc;font-size:1em;outline:none;}
.right-panel input:focus{border-color:#38b000;box-shadow:0 0 5px #70d6ff;}
.right-panel button, .right-panel .login-btn{
    padding:15px;
    border:none;
    background:#38b000;
    color:#fff;
    font-weight:bold;
    font-size:1em;
    border-radius:25px;
    cursor:pointer;
    transition:0.3s;
    text-align: center;
    text-decoration: none;
}
.right-panel button:hover, .right-panel .login-btn:hover{background:#2d6a4f;}
.right-panel .link{text-align:center;margin-top:10px;}
.right-panel .link a{color:#38b000;text-decoration:none;font-weight:bold;}
.right-panel .link a:hover{text-decoration:underline;}

@media(max-width:850px){
    .login-container{flex-direction:column;height:auto;}
    .left-panel,.right-panel{width:100%;text-align:center;padding:30px;}
    .left-panel{height:220px;border-radius:20px 20px 0 0;}
}
</style>
</head>

<body class="login-body">

<div class="login-container">

    <div class="left-panel">
        <h1>Welcome Back 👋</h1>
        <p>Login to your Branch account</p>
        <a href="../home.php" class="btn-outline">Home</a>
    </div>

    <div class="right-panel">
        <h2>User Login</h2>

        <?php if ($error): ?>
            <p class="error"><?php echo $error; ?></p>
        <?php endif; ?>

        <form method="POST">
            <input type="text" name="username" placeholder="Username" required>
            <input type="password" name="password" placeholder="Password" required>
            <button type="submit">Login</button>
            <!-- Optional: direct link button for demo -->
            <!-- <a href="/freshfarmegg/client/dashboard.php" class="login-btn">Login (Demo)</a> -->
        </form>

        <div class="link">
            <a href="register.php">Don't have an account? Register</a>
        </div>
    </div>

</div>

</body>
</html>