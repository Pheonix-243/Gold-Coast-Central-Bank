<?php
session_start();

// Check if registration was successful
if (!isset($_SESSION['registration_success'])) {
    header('Location: register.php');
    exit;
}

$successData = $_SESSION['registration_success'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registration Successful - Gold Coast Central Bank</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary-gold: #FFD700;
            --dark-gold: #DAA520;
            --navy-blue: #1B365D;
            --success-green: #28A745;
        }
        
        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
        }
        
        .success-container {
            max-width: 600px;
            margin: 0 auto;
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
            padding: 3rem;
            text-align: center;
        }
        
        .success-icon {
            width: 100px;
            height: 100px;
            background: linear-gradient(135deg, var(--success-green), #20c997);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 2rem;
            font-size: 3rem;
            color: white;
            animation: pulse 2s infinite;
        }
        
        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.05); }
            100% { transform: scale(1); }
        }
        
        .account-details {
            background: #f8f9fa;
            border-radius: 15px;
            padding: 2rem;
            margin: 2rem 0;
            text-align: left;
        }
        
        .detail-item {
            display: flex;
            justify-content: space-between;
            padding: 0.5rem 0;
            border-bottom: 1px solid #dee2e6;
        }
        
        .detail-item:last-child {
            border-bottom: none;
        }
        
        .btn-primary {
            background-color: var(--navy-blue);
            border-color: var(--navy-blue);
            padding: 0.75rem 2rem;
            font-weight: 600;
        }
        
        .btn-primary:hover {
            background-color: #0F1B2C;
            border-color: #0F1B2C;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="success-container">
            <div class="success-icon">
                <i class="fas fa-check"></i>
            </div>
            
            <h1 class="text-success mb-3">Registration Successful!</h1>
            <p class="lead mb-4">Welcome to Gold Coast Central Bank. Your account has been created successfully.</p>
            
            <div class="account-details">
                <h5 class="mb-3">Your Account Details:</h5>
                <div class="detail-item">
                    <strong>Account Number:</strong>
                    <span><?= htmlspecialchars($successData['accountNumber']) ?></span>
                </div>
                <div class="detail-item">
                    <strong>Email:</strong>
                    <span><?= htmlspecialchars($successData['email']) ?></span>
                </div>
                <div class="detail-item">
                    <strong>Account Type:</strong>
                    <span><?= htmlspecialchars($successData['accountType']) ?></span>
                </div>
                <div class="detail-item">
                    <strong>Initial Deposit:</strong>
                    <span>$<?= number_format($successData['initialDeposit'], 2) ?></span>
                </div>
            </div>
            
            <div class="alert alert-info">
                <i class="fas fa-envelope me-2"></i>
                We've sent your temporary password and account details to your email address.
                Please check your inbox and spam folder.
            </div>
            
            <div class="mt-4">
                <a href="login.php" class="btn btn-primary me-3">
                    <i class="fas fa-sign-in-alt me-2"></i>Login to Your Account
                </a>
                <a href="index.php" class="btn btn-outline-secondary">
                    <i class="fas fa-home me-2"></i>Back to Home
                </a>
            </div>
        </div>
    </div>

    <?php
    // Clear the success data after displaying
    unset($_SESSION['registration_success']);
    ?>
</body>
</html>