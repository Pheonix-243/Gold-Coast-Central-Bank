<?php
session_start();
require_once 'config/database.php';
require_once 'includes/otp.php';
require_once 'includes/security_logger.php';
require_once 'includes/device_intelligence.php';
require_once 'includes/notification.php';
require_once 'includes/location_service.php';
require_once('includes/conn.php');

// Initialize services
$logger = new SecurityLogger();
$deviceIntel = new DeviceIntelligence();
$page_title = 'Secure Login';

// Already logged in? Redirect.
if (isset($_SESSION['client_loggedin']) && $_SESSION['client_loggedin'] === true) {
    header('Location: dashboard/index.php');
    exit;
}

// Error message handler
$error = '';
if (isset($_GET['msg'])) {
    $error = htmlspecialchars($_GET['msg']);
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $rememberMe = isset($_POST['remember_me']);

    if (empty($email) || empty($password)) {
        $error = "Please fill in all fields.";
        $logger->logEvent('login_attempt_empty_fields', [
            'email' => $email,
            'ip' => $_SERVER['REMOTE_ADDR']
        ], 'WARNING');
    } else {
        $conn = DatabaseConnection::getInstance()->getConnection();
        $sql = "SELECT a.account, a.balance, a.status, a.password, a.two_factor_enabled,
                       h.name, h.email, h.image
                FROM accounts_info a
                JOIN accountsholder h ON a.account = h.account
                WHERE h.email = ? LIMIT 1";

        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            $error = "Database error.";
            $logger->logEvent('login_db_error', [
                'email' => $email,
                'error' => 'Failed to prepare statement'
            ], 'ERROR');
        } else {
            $stmt->execute([$email]);
            $client = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($client) {
                // Check account status
                if ($client['status'] !== 'Active') {
                    $error = "Your account is inactive. Please contact support.";
                    $logger->logEvent('login_inactive_account', [
                        'email' => $email,
                        'account' => $client['account']
                    ], 'WARNING');
                } elseif (password_verify($password, $client['password'])) {
    // Password correct - check if we need OTP
    $deviceData = $deviceIntel->getDeviceFingerprint();
    $requireOTP = $deviceIntel->shouldTriggerOTP($client['account'], $deviceData);

    // Record device (even if we don't require OTP)
    $deviceIntel->recordDevice($client['account'], $deviceData);

    // Get location information
    $locationService = new LocationService(); // Add your IPInfo.io token if you have one
    $location = $locationService->getLocationFromIP($_SERVER['REMOTE_ADDR']);

    if ($requireOTP) {
                        // Initiate OTP verification
                        $otpSystem = new EnhancedOTPSystem();
                        
                        // Store login data in session temporarily
                        $_SESSION['pending_login'] = [
                            'account' => $client['account'],
                            'name' => $client['name'],
                            'email' => $client['email'],
                            'balance' => $client['balance'],
                            'image' => $client['image'],
                            'remember_me' => $rememberMe,
                            'device_data' => $deviceData
                        ];
                        
                        // Generate and send OTP
                        $result = $otpSystem->generateAndSendOTP($client['email'], 'login_verification');
                        
                        if ($result['status'] === 'success') {
                            $logger->logEvent('login_otp_sent', [
                                'email' => $client['email'],
                                'account' => $client['account'],
                                'reason' => $requireOTP
                            ]);
                            
                            header('Location: includes/otp_verification.php');
                            exit;
                        } else {
                            $error = "Failed to send verification code: " . $result['message'];
                            unset($_SESSION['pending_login']);
                            $logger->logEvent('login_otp_failed', [
                                'email' => $client['email'],
                                'error' => $result['message']
                            ], 'ERROR');
                        }
                    }  else {
        // Complete login
        $_SESSION['client_loggedin'] = true;
        $_SESSION['client_account'] = $client['account'];
        $_SESSION['client_name'] = $client['name'];
        $_SESSION['client_email'] = $client['email'];
        $_SESSION['client_balance'] = $client['balance'];
        $_SESSION['client_image'] = $client['image'];
        
        // Log login
        $ip = $_SERVER['REMOTE_ADDR'];
        $loginTime = date('Y-m-d H:i:s');
        $stmt = $conn->prepare("
            INSERT INTO client_login_history (account, login_time, ip_address) 
            VALUES (?, ?, ?)
        ");
        $stmt->execute([$_SESSION['client_account'], $loginTime, $ip]);
        $_SESSION['login_id'] = $conn->lastInsertId();

        // Send login notification email
        $emailService = new SecureEmailService();
        $emailService->sendLoginNotification(
            $client['email'],
            $ip,
            $_SERVER['HTTP_USER_AGENT'],
            $location
        );

        // Send in-app notification
        try {
            $notification = new NotificationSystem($con);
            $notification->sendNotification(
                $_SESSION['client_account'],
                'login',
                'New Login Detected',
                "Your account was accessed from " . $ip . " on " . date('Y-m-d H:i:s'),
                [
                    'ip' => $ip,
                    'device' => $_SERVER['HTTP_USER_AGENT'],
                    'location' => $location
                ],
                false, // Don't
                false // Not deletable
            );
        } catch (Exception $e) {
            error_log("Notification error: " . $e->getMessage());
        }
        
        header('Location: /gccb/client/dashboard/index.php');
        exit;
    }
} else {
                    // Invalid password
                    $error = "Invalid email or password.";
                    
                    // Log failed attempt
                    $logger->logEvent('login_failed', [
                        'email' => $email,
                        'account' => $client['account'] ?? 'unknown',
                        'reason' => 'invalid_password',
                        'ip' => $_SERVER['REMOTE_ADDR']
                    ], 'WARNING');
                }
            } else {
                // User not found
                $error = "Invalid email or password.";
                $logger->logEvent('login_failed', [
                    'email' => $email,
                    'reason' => 'user_not_found',
                    'ip' => $_SERVER['REMOTE_ADDR']
                ], 'WARNING');
            }
        }
    }
}


?>
<!-- HTML Starts here -->
<?php include 'includes/header.php'; ?>

<div class="auth-container">
    <div class="container">
        <div class="auth-card">
            <div class="row g-0">
                <div class="col-lg-6">
                    <div class="auth-form-section">
                        <div class="text-center mb-4">
                            <img src="assets/images/gcc-logo.webp" alt="GCC Bank Logo" height="60" class="mb-3">
                            <h2 class="text-navy">Welcome Back</h2>
                            <p class="text-muted">Sign in to your GCC Bank account</p>
                        </div>

                        <?php if (!empty($error)): ?>
                            <div class="alert alert-danger"><?= $error ?></div>
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

                            <div class="mb-3">
                                <div class="form-floating position-relative">
                                    <input type="password" class="form-control" id="password" name="password" 
                                           placeholder="Password" required>
                                    <label for="password">Password</label>
                                    <div class="invalid-feedback">Please provide your password.</div>
                                </div>
                            </div>

                            <div class="d-flex justify-content-between align-items-center mb-4">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="rememberMe" name="remember_me">
                                    <label class="form-check-label text-muted" for="rememberMe">Remember me</label>
                                </div>
                                <a href="password_reset.php" class="text-decoration-none">Forgot password?</a>
                            </div>

                            <button type="submit" class="btn btn-primary btn-lg w-100 mb-3">
                                <i class="fas fa-sign-in-alt me-2"></i>Sign In
                            </button>

                            <div class="text-center">
                                <p class="text-muted mb-3">Don't have an account?</p>
                                <a href="register.php" class="btn btn-outline-primary">
                                    <i class="fas fa-user-plus me-2"></i>Create Account
                                </a>
                            </div>
                        </form>

                    
                    </div>
                </div>

                <div class="col-lg-6 d-none d-lg-block">
                    <div class="auth-brand">
                        <div class="auth-brand-content">
                            <h2>Secure Banking</h2>
                            <p class="mb-4">Experience the future of banking with GCC Bank's secure and innovative platform.</p>

                            <div class="row g-3 text-center">
                                <div class="col-6"><i class="fas fa-shield-alt text-gold fs-2 mb-2"></i><h6 class="text-white">Bank-Grade Security</h6></div>
                                <div class="col-6"><i class="fas fa-mobile-alt text-gold fs-2 mb-2"></i><h6 class="text-white">Mobile Banking</h6></div>
                                <div class="col-6"><i class="fas fa-clock text-gold fs-2 mb-2"></i><h6 class="text-white">24/7 Support</h6></div>
                                <div class="col-6"><i class="fas fa-globe text-gold fs-2 mb-2"></i><h6 class="text-white">Global Reach</h6></div>
                            </div>

                            <p class="text-light mt-4"><i class="fas fa-info-circle me-2"></i>Your account is protected by multi-factor authentication.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>


