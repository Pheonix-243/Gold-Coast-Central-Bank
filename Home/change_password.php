<?php
session_start();
require_once 'config/database.php';
require_once 'includes/security_logger.php';

$logger = new SecurityLogger();
$page_title = 'New Password - Gold Coast Central Bank';

// Only allow if OTP was verified
if (!isset($_SESSION['password_reset_verified']) || !isset($_SESSION['password_reset_email'])) {
    header('Location: password_reset.php?msg=Invalid password reset request&type=danger');
    exit;
}

$email = $_SESSION['password_reset_email'];
$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    // Validate passwords
    if (empty($password) || empty($confirm_password)) {
        $message = "Please fill in all fields";
        $message_type = 'danger';
    } elseif ($password !== $confirm_password) {
        $message = "Passwords do not match";
        $message_type = 'danger';
    } elseif (strlen($password) < 12) {
        $message = "Password must be at least 12 characters long";
        $message_type = 'danger';
    } elseif (!preg_match('/[A-Z]/', $password)) {
        $message = "Password must contain at least one uppercase letter";
        $message_type = 'danger';
    } elseif (!preg_match('/[a-z]/', $password)) {
        $message = "Password must contain at least one lowercase letter";
        $message_type = 'danger';
    } elseif (!preg_match('/[0-9]/', $password)) {
        $message = "Password must contain at least one number";
        $message_type = 'danger';
    } elseif (!preg_match('/[^A-Za-z0-9]/', $password)) {
        $message = "Password must contain at least one special character";
        $message_type = 'danger';
    } else {
        // Update password in database
        $conn = DatabaseConnection::getInstance()->getConnection();
        
        try {
            $conn->beginTransaction();
            
            // Check for lockout status first
            $stmt = $conn->prepare("SELECT lockout_until FROM otp_lockouts WHERE email = ? AND lockout_until > NOW()");
            $stmt->execute([$email]);
            $lockout = $stmt->fetch();
            
            if ($lockout) {
                throw new Exception("Account is temporarily locked. Please try again later.");
            }
            
            // Hash new password with strong algorithm
            $hashed_password = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
            
            // Get account number
            $stmt = $conn->prepare("SELECT account FROM accountsholder WHERE email = ? LIMIT 1");
            $stmt->execute([$email]);
            $account = $stmt->fetchColumn();
            
            if (!$account) {
                throw new Exception("Account not found for email: $email");
            }
            
            // Update password
            $stmt = $conn->prepare("UPDATE accounts_info SET password = ? WHERE account = ?");
            $update_result = $stmt->execute([$hashed_password, $account]);
            
            if (!$update_result || $stmt->rowCount() === 0) {
                throw new Exception("Password update failed for account: $account");
            }
            
            // Invalidate all sessions for this account
            $stmt = $conn->prepare("UPDATE client_login_history SET logout_time = NOW() WHERE account = ? AND logout_time IS NULL");
            $stmt->execute([$account]);
            
            // Clear any lockouts for this email
            $stmt = $conn->prepare("DELETE FROM otp_lockouts WHERE email = ?");
            $stmt->execute([$email]);
            
            // Log password change
            $logger->logEvent('password_changed', [
                'email' => $email,
                'account' => $account,
                'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
            ]);
            
            $conn->commit();
            
            // Clear reset context
            unset($_SESSION['password_reset_email']);
            unset($_SESSION['password_reset_verified']);
            
            // Redirect to login with success message
            header('Location: login.php?msg=Your password has been updated successfully. Please login with your new password.&type=success');
            exit;
        } catch (Exception $e) {
            $conn->rollBack();
            $message = $e->getMessage(); // Show the actual error message to user
            $message_type = 'danger';
            error_log("Password reset error: " . $e->getMessage());
            $logger->logEvent('password_reset_failed', [
                'email' => $email,
                'error' => $e->getMessage(),
                'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
            ], 'ERROR');
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
                            <h2 class="text-navy">Create New Password</h2>
                            <p class="text-muted">Choose a strong, unique password</p>
                        </div>

                        <?php if (!empty($message)): ?>
                            <div class="alert alert-<?= $message_type ?>"><?= $message ?></div>
                        <?php endif; ?>

                        <form method="POST" class="needs-validation" novalidate>
                            <div class="mb-3">
                                <div class="form-floating position-relative">
                                    <input type="password" class="form-control" id="password" name="password" 
                                           placeholder="New Password" required minlength="12">
                                    <label for="password">New Password</label>
                                    <div class="invalid-feedback">Password must be at least 12 characters</div>
                                </div>
                            </div>

                            <div class="mb-4">
                                <div class="form-floating position-relative">
                                    <input type="password" class="form-control" id="confirm_password" name="confirm_password" 
                                           placeholder="Confirm Password" required minlength="12">
                                    <label for="confirm_password">Confirm Password</label>
                                    <div class="invalid-feedback">Passwords must match</div>
                                </div>
                            </div>

                            <button type="submit" class="btn btn-primary btn-lg w-100 mb-3">
                                <i class="fas fa-save me-2"></i>Update Password
                            </button>

                            <div class="password-requirements">
                                <h6>Password Requirements:</h6>
                                <ul class="list-unstyled">
                                    <li><i class="fas fa-check-circle text-success me-2"></i>Minimum 12 characters</li>
                                    <li><i class="fas fa-check-circle text-success me-2"></i>At least one uppercase letter</li>
                                    <li><i class="fas fa-check-circle text-success me-2"></i>At least one lowercase letter</li>
                                    <li><i class="fas fa-check-circle text-success me-2"></i>At least one number</li>
                                    <li><i class="fas fa-check-circle text-success me-2"></i>At least one special character</li>
                                </ul>
                            </div>
                        </form>
                    </div>
                </div>

                <div class="col-lg-6 d-none d-lg-block">
                    <div class="auth-brand">
                        <div class="auth-brand-content">
                            <h2>Password Security</h2>
                            <p class="mb-4">Your password is the first line of defense for your account. Follow these best practices:</p>

                            <div class="security-tips">
                                <div class="tip-item">
                                    <i class="fas fa-ban"></i>
                                    <span>Don't reuse passwords from other sites</span>
                                </div>
                                <div class="tip-item">
                                    <i class="fas fa-user-secret"></i>
                                    <span>Avoid personal information in passwords</span>
                                </div>
                                <div class="tip-item">
                                    <i class="fas fa-random"></i>
                                    <span>Use a mix of character types</span>
                                </div>
                                <div class="tip-item">
                                    <i class="fas fa-sync-alt"></i>
                                    <span>Change passwords periodically</span>
                                </div>
                            </div>

                            <p class="text-light mt-4"><i class="fas fa-shield-alt me-2"></i>All passwords are encrypted using bank-grade security.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Password confirmation validation
document.getElementById('confirm_password').addEventListener('input', function(e) {
    const password = document.getElementById('password').value;
    const confirmPassword = e.target.value;
    
    if (confirmPassword && password !== confirmPassword) {
        e.target.setCustomValidity("Passwords do not match");
    } else {
        e.target.setCustomValidity("");
    }
});
</script>

<?php include 'includes/footer.php'; ?>