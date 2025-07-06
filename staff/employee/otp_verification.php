<?php
session_start();
if(!isset($_SESSION['temp_user']) || !isset($_SESSION['otp'])) {
    header("Location: index.php");
    exit();
}

if(isset($_POST['verify_otp'])) {
    $user_otp = $_POST['otp'];
    
    if(time() > $_SESSION['otp_expiry']) {
        $_SESSION["status"] = "OTP has expired. Please login again.";
        $_SESSION["code"] = "error";
        unset($_SESSION['temp_user']);
        unset($_SESSION['otp']);
        unset($_SESSION['otp_expiry']);
        header("Location: index.php");
        exit();
    }
    
    if($user_otp == $_SESSION['otp']) {
        // OTP verified, log the user in
        $row = $_SESSION['temp_user'];
        $_SESSION["loggedin"] = true;
        $_SESSION["type"] = $row['role'];
        $_SESSION["pass"] = $row['password'];
        $_SESSION["name"] = $row['name'];
        $_SESSION['img'] = $row['image'];
        $_SESSION["id"] = $row['id'];
        $_SESSION["email"] = $row['username'];
        $_SESSION["status"] = "Welcome ".$row['name'];
        $_SESSION["code"] = "success";
        
        date_default_timezone_set('Asia/Karachi');
        $tms1 = date("Y-m-d h:i:s");
        $_SESSION["time"] = $tms1;
        
        $idd = $row['id'];
        mysqli_query($con, "INSERT INTO emp_history values('$idd','$tms1','logged still')");
        
        // Clear temp session data
        unset($_SESSION['temp_user']);
        unset($_SESSION['otp']);
        unset($_SESSION['otp_expiry']);
        
        header("location: dashboard.php");
        exit();
    } else {
        $_SESSION["status"] = "Invalid OTP. Please try again.";
        $_SESSION["code"] = "error";
        header("Location: otp_verification.php");
        exit();
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>SKY BANK | OTP Verification</title>
    <link rel="icon" href="images/icc.ico" type="image/x-icon">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" type="text/css" href="css/native-toast.css">
    <meta charset="utf-8">
    <style type="text/css">
        @import url('https://fonts.googleapis.com/css?family=Source+Sans+Pro:400,700&display=swap');

        body {
            background: url('images/ub.jpg');
            margin: 15px;
            margin-top: 80px;
            background-position: center center;
            background-repeat: no-repeat;
            background-attachment: fixed;
            background-size: cover;
            background-color: #464646;
        }

        .login-form,
        .login-form * {
            box-sizing: border-box;
            font-family: 'Source Sans Pro';
        }

        .login-form {
            max-width: 350px;
            margin: 0 auto;
            border-radius: 5px;
            overflow: hidden;
            box-shadow: 0 0 15px rgba(0, 0, 0, 0.15);
        }

        .login-form__logo-container {
            padding: 30px;
            background: rgba(0, 0, 0, 0.25);
        }

        .login-form__content {
            padding: 30px;
            background: #eeeeee;
        }

        .login-form__header {
            margin-bottom: 15px;
            text-align: center;
            color: #333333;
        }

        .login-form__input {
            width: 100%;
            margin-bottom: 10px;
            padding: 10px;
            border-radius: 5px;
            border: 2px solid #dddddd;
            background: #ffffff;
            outline: none;
            transition: border-color 0.5s;
        }

        .login-form__input:focus {
            border-color: #009578;
        }

        .login-form__button {
            padding: 10px;
            color: #ffffff;
            font-weight: bold;
            background: #009578;
            width: 100%;
            border: none;
            outline: none;
            border-radius: 5px;
            cursor: pointer;
        }

        .login-form__button:active {
            background: #008067;
        }
    </style>
</head>
<body>
    <form class="login-form" method="POST">
        <div class="login-form__logo-container">
            <h3 style="color: white; text-align: center;">SKY BANK LIMITED PAKISTAN</h3>
        </div>
        <div class="login-form__content">
            <div class="login-form__header">OTP Verification</div>
            <p style="text-align: center;">We've sent a 6-digit OTP to your email. Please enter it below:</p>
            <input class="login-form__input" type="number" name="otp" placeholder="Enter OTP" required autofocus>
            <button class="login-form__button" type="submit" name="verify_otp">Verify</button>
            <p style="text-align: center; margin-top: 15px;">
                <a href="index.php">Back to Login</a>
            </p>
        </div>
    </form>
    
    <script src="js/native-toast.min.js"></script>
    <?php
    if(isset($_SESSION['status']) && $_SESSION['status']!='') {
    ?>
    <script type="text/javascript">
        nativeToast({
            message: '<?php echo $_SESSION['status']?>',
            position: 'center',
            timeout: 4000,
            type: '<?php echo $_SESSION['code']?>',
            closeOnClick: true
        })
    </script>
    <?php
        unset($_SESSION['status']);
    }
    ?>
</body>
</html>