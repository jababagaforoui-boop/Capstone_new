<?php
session_start();
require 'includes/db.php';
require 'includes/functions.php';

$error = "";

// Fetch active branches
$branches = [];
$branch_res = $conn->query("SELECT id, branch_name FROM branches WHERE is_active=1 ORDER BY branch_name ASC");
if($branch_res){
    while($row = $branch_res->fetch_assoc()){
        $branches[] = $row;
    }
}

// Handle registration
if(isset($_POST['register'])){
    $username  = sanitize($_POST['username'] ?? '');
    $email     = sanitize($_POST['email'] ?? '');
    $password  = $_POST['password'] ?? '';
    $branch_id = intval($_POST['branch'] ?? 0);

    if(empty($username) || empty($email) || empty($password) || !$branch_id){
        $error = "Please fill in all fields!";
    } elseif(strlen($password) < 8){
        $error = "Password must be at least 8 characters!";
    } else {
        $stmt = $conn->prepare("SELECT id FROM `user` WHERE username=? OR email=? LIMIT 1");
        $stmt->bind_param("ss", $username, $email);
        $stmt->execute();
        $res = $stmt->get_result();
        if($res->num_rows > 0){
            $error = "Username or email already exists!";
        } else {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt_insert = $conn->prepare("INSERT INTO `user` (username,email,password,branch_id) VALUES (?,?,?,?)");
            $stmt_insert->bind_param("sssi", $username, $email, $hash, $branch_id);
            if($stmt_insert->execute()){
                header("Location: login.php");
                exit;
            } else {
                $error = "Database error: " . $conn->error;
            }
            $stmt_insert->close();
        }
        $stmt->close();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>User Register | IAS</title>
<style>
*{margin:0;padding:0;box-sizing:border-box;font-family:'Segoe UI',Tahoma;}
body.login-body{background:#d8f3dc;min-height:100vh;display:flex;justify-content:center;align-items:center;}
.login-container{display:flex;width:900px;max-width:95%;height:auto;background:#fff;border-radius:20px;overflow:hidden;box-shadow:0 10px 25px rgba(0,0,0,0.15);}
.left-panel{flex:1;background:linear-gradient(135deg,#38b000,#70d6ff);color:#fff;padding:40px;display:flex;flex-direction:column;justify-content:center;text-shadow:1px 1px 6px rgba(0,0,0,0.3);}
.left-panel h1{font-size:2.5em;margin-bottom:15px;}
.left-panel p{font-size:1.1em;margin-bottom:25px;}
.btn-outline{border:2px solid #fff;color:#fff;padding:12px 30px;border-radius:25px;font-weight:bold;text-decoration:none;width:fit-content;transition:0.3s;}
.btn-outline:hover{background:#fff;color:#38b000;}
.right-panel{flex:1;padding:50px 40px;display:flex;flex-direction:column;justify-content:center;background:#e6f4ea;}
.right-panel h2{margin-bottom:20px;color:#2d6a4f;font-size:2em;text-align:center;}
.error{color:#d00000;margin-bottom:15px;text-align:center;font-weight:bold;}
.right-panel form{display:flex;flex-direction:column;}
.right-panel input, .right-panel select{padding:15px;margin-bottom:20px;border-radius:10px;border:1px solid #ccc;font-size:1em;outline:none;}
.right-panel input:focus, .right-panel select:focus{border-color:#38b000;box-shadow:0 0 5px #70d6ff;}
.right-panel button{padding:15px;border:none;background:#38b000;color:#fff;font-weight:bold;font-size:1em;border-radius:25px;cursor:pointer;transition:0.3s;}
.right-panel button:hover{background:#2d6a4f;}
.right-panel .link{text-align:center;margin-top:10px;}
.right-panel .link a{color:#38b000;text-decoration:none;font-weight:bold;}
.right-panel .link a:hover{text-decoration:underline;}
@media(max-width:850px){.login-container{flex-direction:column;height:auto;}.left-panel,.right-panel{width:100%;text-align:center;padding:30px;}.left-panel{height:220px;border-radius:20px 20px 0 0;}}
</style>
</head>

<body class="login-body">
<div class="login-container">
    <div class="left-panel">
        <h1>Welcome 👋</h1>
        <p>Create your Branch User Account</p>
        <a href="../home.php" class="btn-outline">Home</a>
    </div>

    <div class="right-panel">
        <h2>User Registration</h2>

        <?php if($error): ?>
            <p class="error"><?php echo $error; ?></p>
        <?php endif; ?>

        <form method="POST">
            <input type="text" name="username" placeholder="Username" required>
            <input type="email" name="email" placeholder="Email" required>
            <input type="password" name="password" placeholder="Password" required>

            <select name="branch" required>
                <option value="">Select Branch</option>
                <?php foreach($branches as $b): ?>
                    <option value="<?php echo intval($b['id']); ?>"><?php echo htmlspecialchars($b['branch_name']); ?></option>
                <?php endforeach; ?>
            </select>

            <button type="submit" name="register">Register</button>
        </form>

        <div class="link">
            <a href="login.php">Already have an account? Login</a>
        </div>
    </div>
</div>
</body>
</html>