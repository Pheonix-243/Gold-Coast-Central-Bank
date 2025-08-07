<?php
session_start();
require_once 'config/database.php';
require_once 'includes/otp.php';
require_once 'includes/security_logger.php';

$logger = new SecurityLogger();
$page_title = 'Password Reset - Gold Coast Central Bank';

// Redirect if already logged in
if (isset($_SESSION['client_loggedin'])) {
    header('Location: ../dashboard/index.php');
    exit;
}

$message = '';
$message_type = 'danger';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');

    if (empty($email)) {
        $message = "Please enter your email address.";
        $logger->logEvent('password_reset_empty_email', ['ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'], 'WARNING');
    } else {
        $conn = DatabaseConnection::getInstance()->getConnection();
        $stmt = $conn->prepare("SELECT a.account, h.email FROM accounts_info a JOIN accountsholder h ON a.account = h.account WHERE h.email = ? LIMIT 1");
        $stmt->execute([$email]);
        $account = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($account) {
            $otpSystem = new EnhancedOTPSystem();
            $result = $otpSystem->generateAndSendOTP($email, 'password_reset');

            if ($result['status'] === 'success') {
                $_SESSION['password_reset_email'] = $email;
                header('Location: includes/otp_verification.php?action=password_reset');
                exit;
            } else {
                $message = "Failed to send OTP: " . $result['message'];
            }
        } else {
            // Prevent email enumeration
            $message = "If your email exists, you'll receive an OTP shortly.";
            // sleep(1); 
        }
    }
}
?>


<?php include 'includes/header.php'; ?>
<div class="auth-container">
    <div class="container">
        <div class="auth-card">
            <div class="row g-0">
                <div class="col-lg-6">
                    <div class="auth-form-section">
                        <div class="text-center mb-4">
                            <img src="assets/images/gcc-logo.webp" alt="GCC Bank Logo" height="60" class="mb-3">
                            <h2 class="text-navy">Reset Password</h2>
                            <p class="text-muted">Enter your email to receive a verification code</p>
                        </div>

                        <?php if ($message): ?>
                            <div class="alert alert-<?= $message_type ?>"><?= $message ?></div>
                        <?php endif; ?>

                        <form method="POST" class="needs-validation" novalidate>
                            <div class="mb-3">
                                <div class="form-floating">
                                    <input type="email" class="form-control" id="email" name="email" 
                                           placeholder="name@example.com" required 
                                           value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
                                    <label for="email">Email Address</label>
                                    <div class="invalid-feedback">Please provide a valid email address.</div>
                                </div>
                            </div>

                            <button type="submit" class="btn btn-primary btn-lg w-100 mb-3">
                                <i class="fas fa-key me-2"></i>Request Reset Code
                            </button>

                            <div class="text-center">
                                <p class="text-muted mb-0">Remember your password?</p>
                                <a href="login.php" class="btn btn-outline-primary">
                                    <i class="fas fa-sign-in-alt me-2"></i>Back to Login
                                </a>
                            </div>
                        </form>
                    </div>
                </div>

                <div class="col-lg-6 d-none d-lg-block">
                    <div class="auth-brand">
                        <div class="auth-brand-content">
                            <h2>Account Security</h2>
                            <p class="mb-4">We take your account security seriously. All password resets require identity verification.</p>

                            <div class="row g-3 text-center">
                                <div class="col-6"><i class="fas fa-shield-alt text-gold fs-2 mb-2"></i><h6 class="text-white">Bank-Grade Security</h6></div>
                                <div class="col-6"><i class="fas fa-lock text-gold fs-2 mb-2"></i><h6 class="text-white">End-to-End Encryption</h6></div>
                                <div class="col-6"><i class="fas fa-clock text-gold fs-2 mb-2"></i><h6 class="text-white">Time-Limited Codes</h6></div>
                                <div class="col-6"><i class="fas fa-history text-gold fs-2 mb-2"></i><h6 class="text-white">Activity Monitoring</h6></div>
                            </div>

                            <p class="text-light mt-4"><i class="fas fa-info-circle me-2"></i>For your security, verification codes expire after 5 minutes.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
