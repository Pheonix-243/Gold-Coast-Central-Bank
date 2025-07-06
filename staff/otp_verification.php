<?php
session_start();
include 'conn.php';

// Redirect if not coming from login flow
if (!isset($_SESSION['temp_user_id'])) {
    header("Location: index.php");
    exit();
}

// Resend OTP if requested
if (isset($_POST['resend_otp'])) {
    $user_id = $_SESSION['temp_user_id'];
    $new_otp = rand(100000, 999999);
    $new_expiry = date("Y-m-d H:i:s", strtotime("+5 minutes"));

    $update_sql = "UPDATE users SET otp=?, otp_expiry=? WHERE id=?";
    $update_stmt = mysqli_prepare($con, $update_sql);
    mysqli_stmt_bind_param($update_stmt, "sss", $new_otp, $new_expiry, $user_id);
    mysqli_stmt_execute($update_stmt);

    // Fetch email to resend OTP
    $email_sql = "SELECT username FROM users WHERE id=?";
    $email_stmt = mysqli_prepare($con, $email_sql);
    mysqli_stmt_bind_param($email_stmt, "s", $user_id);
    mysqli_stmt_execute($email_stmt);
    $email_result = mysqli_stmt_get_result($email_stmt);
    $user = mysqli_fetch_assoc($email_result);

    include 'email.php';
    send_otp_email($user['username'], $new_otp);

    $_SESSION["status"] = "New OTP sent!";
    $_SESSION["code"] = "success";
    header("Location: otp_verification.php");
    exit();
}

// Verify OTP if submitted
if (isset($_POST['verify_otp'])) {
    $user_otp = $_POST['otp'];
    $user_id = $_SESSION['temp_user_id'];

    // Fetch stored OTP and expiry
    $sql = "SELECT otp, otp_expiry FROM users WHERE id=?";
    $stmt = mysqli_prepare($con, $sql);
    mysqli_stmt_bind_param($stmt, "s", $user_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $row = mysqli_fetch_assoc($result);

    if (!$row || $row['otp'] != $user_otp) {
        $_SESSION['otp_attempts']++;
        if ($_SESSION['otp_attempts'] >= 3) {
            $_SESSION["status"] = "Too many failed attempts. Login again.";
            $_SESSION["code"] = "error";
            unset($_SESSION['temp_user_id']);
            header("Location: index.php");
            exit();
        }
        $_SESSION["status"] = "Invalid OTP. " . (3 - $_SESSION['otp_attempts']) . " attempts left.";
        $_SESSION["code"] = "error";
        header("Location: otp_verification.php");
        exit();
    }

    if (strtotime($row['otp_expiry']) < time()) {
        $_SESSION["status"] = "OTP expired. Resend a new one.";
        $_SESSION["code"] = "error";
        header("Location: otp_verification.php");
        exit();
    }

    // OTP is valid, log the user in
    $user_sql = "SELECT u.*, e.* FROM users u, emp_details e WHERE u.id=? AND e.id=u.id";
    $user_stmt = mysqli_prepare($con, $user_sql);
    mysqli_stmt_bind_param($user_stmt, "s", $user_id);
    mysqli_stmt_execute($user_stmt);
    $user_result = mysqli_stmt_get_result($user_stmt);
    $user_data = mysqli_fetch_assoc($user_result);

    // Set session variables
    $_SESSION["loggedin"] = true;
    $_SESSION["id"] = $user_data['id'];
    $_SESSION["email"] = $user_data['username'];
    $_SESSION["name"] = $user_data['name'];
    $_SESSION["type"] = $user_data['role'];
    $_SESSION["img"] = $user_data['image'];

    // Clear OTP data from DB
    $clear_sql = "UPDATE users SET otp=NULL, otp_expiry=NULL WHERE id=?";
    $clear_stmt = mysqli_prepare($con, $clear_sql);
    mysqli_stmt_bind_param($clear_stmt, "s", $user_id);
    mysqli_stmt_execute($clear_stmt);

    // Clear temp session
    unset($_SESSION['temp_user_id']);
    unset($_SESSION['otp_attempts']);

    // Redirect to dashboard
    header("Location: dashboard.php");
    exit();
}

// Fetch OTP expiry for JavaScript timer
$expiry_sql = "SELECT otp_expiry FROM users WHERE id=?";
$expiry_stmt = mysqli_prepare($con, $expiry_sql);
mysqli_stmt_bind_param($expiry_stmt, "s", $_SESSION['temp_user_id']);
mysqli_stmt_execute($expiry_stmt);
$expiry_result = mysqli_stmt_get_result($expiry_stmt);
$expiry_row = mysqli_fetch_assoc($expiry_result);
$otp_expiry = strtotime($expiry_row['otp_expiry']);
$remaining_time = $otp_expiry - time();
?>

<!DOCTYPE html>
<html>
<head>
    <title>OTP Verification | SKY BANK</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <script>
        let timeLeft = <?php echo $remaining_time; ?>;
        
        function updateTimer() {
            const minutes = Math.floor(timeLeft / 60);
            const seconds = timeLeft % 60;
            document.getElementById('timer').textContent = `${minutes}:${seconds < 10 ? '0' : ''}${seconds}`;
            
            if (timeLeft <= 0) {
                document.getElementById('resend-btn').style.display = 'block';
                document.getElementById('timer').textContent = "Expired";
            } else {
                timeLeft--;
                setTimeout(updateTimer, 1000);
            }
        }
        
        window.onload = updateTimer;
    </script>
    <style>
        body { font-family: Arial, sans-serif; background: #f5f5f5; }
        .otp-container { max-width: 400px; margin: 50px auto; padding: 20px; background: white; border-radius: 8px; box-shadow: 0 0 10px rgba(0,0,0,0.1); }
        .otp-input { width: 100%; padding: 10px; margin: 10px 0; font-size: 16px; }
        .verify-btn { background: #009578; color: white; border: none; padding: 10px; width: 100%; cursor: pointer; }
        .resend-btn { background: #ff6b6b; color: white; border: none; padding: 10px; width: 100%; cursor: pointer; display: none; }
        .timer { text-align: center; font-size: 18px; margin: 10px 0; }
    </style>
</head>
<body>
    <div class="otp-container">
        <h2>OTP Verification</h2>
        <p>Enter the 6-digit code sent to your email</p>
        
        <form method="POST">
            <input type="number" name="otp" class="otp-input" placeholder="123456" required>
            <div class="timer">Time remaining: <span id="timer"></span></div>
            <button type="submit" name="verify_otp" class="verify-btn">Verify</button>
            <button type="submit" name="resend_otp" id="resend-btn" class="resend-btn">Resend OTP</button>
        </form>
    </div>
</body>
</html>