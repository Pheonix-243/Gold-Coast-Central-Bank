<?php
/**
 * Enhanced OTP Verification Interface
 * Modular verification page with real-time countdown and security features
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/database.php';
require_once 'otp.php';

// Initialize OTP system
$otpSystem = new EnhancedOTPSystem();

// Check if user has valid OTP context
if (!$otpSystem->hasValidOTPContext()) {
    header('Location: ../login.php?msg=Invalid verification session');
    exit;
}

$context = $otpSystem->getOTPContext();
$status_message = '';
$verification_success = false;

// Handle OTP verification
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['verify_otp'])) {
    $otp = preg_replace('/[^0-9]/', '', $_POST['otp'] ?? '');
    
    if (strlen($otp) === OTP_LENGTH) {
        $result = $otpSystem->verifyOTP($otp);
        $status_message = $result['message'];
        
        if ($result['status'] === 'success') {
            $verification_success = true;
            
            // Handle different verification flows
            switch ($context['action']) {
                case 'login_verification':
                    if (isset($_SESSION['pending_login'])) {
                        // Complete login process
                        $_SESSION['client_loggedin'] = true;
                        $_SESSION['client_account'] = $_SESSION['pending_login']['account'];
                        $_SESSION['client_name'] = $_SESSION['pending_login']['name'];
                        $_SESSION['client_email'] = $_SESSION['pending_login']['email'];
                        $_SESSION['client_balance'] = $_SESSION['pending_login']['balance'];
                        $_SESSION['client_image'] = $_SESSION['pending_login']['image'];
                        
                        // Log login
                        $conn = DatabaseConnection::getInstance()->getConnection();
                        $ip = $_SERVER['REMOTE_ADDR'];
                        $loginTime = date('Y-m-d H:i:s');
                        $stmt = $conn->prepare("
                            INSERT INTO client_login_history (account, login_time, ip_address) 
                            VALUES (?, ?, ?)
                        ");
                        $stmt->execute([$_SESSION['client_account'], $loginTime, $ip]);
                        $_SESSION['login_id'] = $conn->lastInsertId();
                        
                        unset($_SESSION['pending_login']);
                        
                        // Clear OTP context after successful verification
                        $otpSystem->clearOTPContext();
                        
                        // Redirect to dashboard immediately
                        header('Location: /gccb/client/dashboard/index.php');
                        exit;
                    }
                    break;


                    // ▼ ADD THIS NEW CASE ▼
    case 'password_reset':
        $_SESSION['password_reset_verified'] = true;
        echo "<script>
            setTimeout(function() {
                window.location.href = '../change_password.php';
            }, 2000);
        </script>";
        break;
                    
                case 'transaction_verification':
                    // Set verification flag for transaction to continue
                    $_SESSION['transaction_verified'] = true;
                    $_SESSION['transaction_verify_time'] = time();
                    
                    echo "<script>
                        setTimeout(function() {
                            window.location.href = '../transfer.php?verified=1';
                        }, 2000);
                    </script>";
                    break;
                    
                case 'email_change':
                    $_SESSION['email_change_verified'] = true;
                    
                    echo "<script>
                        setTimeout(function() {
                            window.location.href = '../profile.php?email_verified=1';
                        }, 2000);
                    </script>";
                    break;
                    
                default:
                    // Generic verification success
                    $_SESSION['otp_verification_complete'] = true;
                    $_SESSION['verified_action'] = $context['action'];
            }
        }
    } else {
        $status_message = 'Please enter a valid 6-digit OTP';
    }
}

// Handle OTP resend
if (isset($_POST['resend_otp'])) {
    // CRITICAL: Use generateAndSendOTP which includes rate limiting checks
    $result = $otpSystem->generateAndSendOTP($context['email'], $context['action'], $context['session_key']);
    $status_message = $result['message'];
    
    // Update context with new generation time
    if ($result['status'] === 'success') {
        $_SESSION['otp_context']['generated_at'] = time();
        $_SESSION['otp_context']['expires_at'] = time() + (OTP_EXPIRY_MINUTES * 60);
        $_SESSION['otp_context']['attempts'] = 0;
    } elseif ($result['status'] === 'error' && strpos($result['message'], 'limit') !== false) {
        // Rate limit exceeded - clear session and redirect to login
        $otpSystem->clearOTPContext();
        header('Location: ../login.php?msg=' . urlencode($result['message']));
        exit;
    }
}

// Get current expiry time
$expiryTime = $context['expires_at'] ?? 0;
// Fixed cooldown time (60 seconds as per OTP_COOLDOWN_SECONDS)
$cooldownTime = OTP_COOLDOWN_SECONDS;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>OTP Verification - Gold Coast Central Bank</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>

    </style>
      <!-- Custom CSS -->
      <link rel="stylesheet" href="../assets/css/otp-styles.css">
</head>
<body>
    <div class="otp-container">
        <div class="otp-header">
            <div class="security-icon">🛡️</div>
            <h2 class="otp-title">Security Verification</h2>
            <p class="otp-subtitle">Enter the 6-digit code sent to your email</p>
        </div>

        <?php if ($verification_success): ?>
            <div class="success-message">
                <div class="success-icon">✅</div>
                <h3>Verification Successful!</h3>
                <p>Redirecting you now...</p>
            </div>
        <?php else: ?>
            <?php if ($status_message): ?>
                <?php if (strpos($status_message, 'success') !== false || strpos($status_message, 'sent') !== false): ?>
                    <div class="success-message-alt">
                        <?= htmlspecialchars($status_message) ?>
                    </div>
                <?php else: ?>
                    <div class="error-message">
                        <?= htmlspecialchars($status_message) ?>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
            
            <div class="email-info">
                <p>Code sent to: <span class="email-address"><?= htmlspecialchars($context['email']) ?></span></p>
            </div>
            
            <form method="POST">
                <div class="form-group">
                    <label for="otp" class="form-label">Verification Code</label>
                    <input 
                        type="text" 
                        name="otp" 
                        id="otp" 
                        class="otp-input" 
                        maxlength="6" 
                        placeholder="000000"
                        autocomplete="off"
                    >
                    <div class="input-hint">Enter the 6-digit code</div>
                </div>
                
                <button 
                    type="submit" 
                    name="verify_otp"
                    class="verify-button"
                >
                    Verify Code
                </button>
            </form>
            
            <div class="resend-section">
                <form method="POST">
                    <button 
                        type="submit" 
                        name="resend_otp"
                        class="resend-button"
                        id="resendButton"
                    >
                        🔄 Resend Code
                    </button>
                </form>
            </div>

            <div class="timer-section">
                <p class="timer-text">Code expires in: <span id="timer" class="timer"></span></p>
            </div>
            
        <?php endif; ?>
    </div>

    <script src="../assets/js/otp-countdown.js?v=<?= time() ?>"></script>
    
    <script>
        // Initialize countdown timers
        const expiryTime = <?= $expiryTime ?>;
        const cooldownTime = <?= $cooldownTime ?>;
        
        // Only initialize if we have valid times
        if (expiryTime > 0 || cooldownTime > 0) {
            initializeOTPCountdown(expiryTime, cooldownTime);
        }
        
        // Auto-focus OTP input
        document.addEventListener('DOMContentLoaded', function() {
            const otpInput = document.getElementById('otp');
            otpInput.focus();
            
            // Format OTP input (numbers only) - no validation, just formatting
            otpInput.addEventListener('input', function(e) {
                e.target.value = e.target.value.replace(/[^0-9]/g, '');
            });
        });
    </script>
</body>
</html>