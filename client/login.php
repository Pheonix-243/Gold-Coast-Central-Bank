<?php
session_start();
require_once('../conn.php');

// Redirect if already logged in
if(isset($_SESSION['client_loggedin'])) {
    header('Location: dashboard/index.php');
    exit;
}

$error = '';
if(isset($_GET['msg'])) {
    $error = htmlspecialchars($_GET['msg']);
}

if(isset($_POST['submit'])) {  
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    
    // Get user by email only
    $sql = "SELECT a.account, a.balance, a.status, a.password, 
                   h.name, h.email, h.image 
            FROM accounts_info a
            JOIN accountsholder h ON a.account = h.account
            WHERE h.email = ?";
    
    $stmt = mysqli_prepare($con, $sql);
    mysqli_stmt_bind_param($stmt, "s", $email);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    if(mysqli_num_rows($result) == 1) {
        $client = mysqli_fetch_assoc($result);
        
        if(password_verify($password, $client['password'])) {
            if($client['status'] == 'Active') {
                // Set all session variables
                $_SESSION['client_loggedin'] = true;
                $_SESSION['client_account'] = $client['account'];
                $_SESSION['client_name'] = $client['name'];
                $_SESSION['client_email'] = $client['email'];
                $_SESSION['client_balance'] = $client['balance'];
                $_SESSION['client_image'] = $client['image'];
                
                // Record login
                $ip = $_SERVER['REMOTE_ADDR'];
                $loginTime = date('Y-m-d H:i:s');
                $insert_sql = "INSERT INTO client_login_history (account, login_time, ip_address) 
                              VALUES (?, ?, ?)";
                $insert_stmt = mysqli_prepare($con, $insert_sql);
                mysqli_stmt_bind_param($insert_stmt, "sss", $client['account'], $loginTime, $ip);
                mysqli_stmt_execute($insert_stmt);
                $_SESSION['login_id'] = mysqli_insert_id($con);
                
                header('Location: dashboard/index.php');
                exit;
            } else {
                $error = "Your account is inactive. Please contact support.";
            }
        } else {
            $error = "Invalid email or password";
        }
    } else {
        $error = "Invalid email or password";
    }
}
?>


<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Gold Coast Central Bank</title>
    <link rel="icon" href="../assets/img/logo.png" type="image/x-icon">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/styles.css">
    <link rel="stylesheet" href="assets/css/logres.css">
    <link rel="stylesheet" href="assets/css/bootstrap.min.css">
</head>

<body class="body-color body-whole">

    <!-- Top Left Ellipse -->
    <div class="position-absolute start-0 m-0 p-0"><img src="assets/img/login-top-left.png" alt=""></div>

    <div class="container">
        <div class="row">
            <!-- Login Form Section -->
            <div class="col-lg-6 d-flex align-items-center justify-content-center" style="height: 100vh;">
                <div class="col-7">
                    <div class="text-center mb-5 d-block d-lg-none">
                        <a href="home.php"><img src="assets/img/logo.png" height="80px"
                            class="zoom-on-hover" alt="logo"></a>
                    </div>
                    <div class="col-md-12 fw-bold mb-5 text-center login-header">Welcome to Gold Coast Central Bank!</div>
<!-- Change just the form part (rest remains the same) -->
<form action="login.php" method="POST">
    <div class="mb-2">
        <label for="email" class="form-label text-black login-label">Email</label>
        <input type="email" class="form-control rounded-4 textfield" id="email" name="email" required>
        <small id="error-email" class="text-danger error-font"></small>
    </div>
    <div class="mb-5 password-field">
        <label for="password" class="form-label text-black login-label">Password</label>
        <input type="password" class="form-control rounded-4 textfield" id="password" name="password" required>
        <img src="assets/img/eye-open.png" class="password-icon" id="eye-login">
        <small id="error-password" class="mt-1 text-danger error-font">
            <?php echo $error ?>
        </small>
    </div>
    <div class="text-center">
        <button type="submit" name="submit" class="btn btn-primary rounded-4 mb-4 elevatedButton">Login</button>
    </div>
</form>
                    <p class="mt-4 login-label text-center">Don't have an account? <a href="register.php"
                            class="no-underline">Register</a></p>
                    <!-- Social Icons -->
                    <div class="container mt-4">
                        <div class="row justify-content-center">
                            <div class="col-auto">
                                <a href="https://facebook.com/sawongam">
                                    <img src="../assets/img/fb-icon.png" height="24px" class="zoom-on-hover"
                                        alt="facebook">
                                </a>
                            </div>
                            <div class="col-auto">
                                <a href="#">
                                    <img src="assets/img/whats-icon.png" height="24px" class="zoom-on-hover"
                                        alt="whatsapp">
                                </a>
                            </div>
                            <div class="col-auto">
                                <a href="#">
                                    <img src="assets/img/tel-icon.png" height="24px" class="zoom-on-hover"
                                        alt="telegram">
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Hidden Logo Section -->
            <div class="col-lg-6 align-items-center justify-content-center pb-5 d-none d-lg-flex"
                style="height: 100vh; background-position: right;">
                <a href="index.php"><img src="assets/img/logo.png" class="zoom-on-hover" alt="Sawongam Logo"></a>
                <!-- Login Side Block -->
                <div class="login-block"></div>
            </div>
        </div>
    </div>

    <!-- Bottom Left Ellipse -->
    <div class="position-absolute fixed-bottom m-0 p-0"><img src="assets/img/login-btm-left.png" alt=""></div>


    <script src="assets/js/login.js"></script>
    <script src="assets/js/bootstrap.min.js"></script>
</body>

</html>