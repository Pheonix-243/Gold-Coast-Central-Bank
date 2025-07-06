<?php 
$page_title = 'Login';
include 'includes/header.php';

// Process login form
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = sanitize_input($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $csrf_token = $_POST[CSRF_TOKEN_NAME] ?? '';
    
    // Validate CSRF token
    if (!verify_csrf_token($csrf_token)) {
        set_message('error', 'Invalid security token. Please try again.');
    } elseif (empty($email) || empty($password)) {
        set_message('error', 'Please provide both email and password.');
    } elseif (!validate_email($email)) {
        set_message('error', 'Please provide a valid email address.');
    } else {
        // In production, validate against database
        // For now, simulate login validation
        if ($email === 'demo@gccbank.com.gh' && $password === 'Demo123!') {
            $_SESSION['user_id'] = 1;
            $_SESSION['user_name'] = 'Demo User';
            $_SESSION['user_email'] = $email;
            set_message('success', 'Login successful! Welcome back.');
            header('Location: index.php');
            exit;
        } else {
            set_message('error', 'Invalid email or password. Please try again.');
        }
    }
}
?>

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

                        <form method="POST" class="needs-validation" novalidate>
                            <?= csrf_token_input() ?>
                            
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
                                    <button type="button" class="password-toggle">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                    <div class="invalid-feedback">Please provide your password.</div>
                                </div>
                            </div>

                            <div class="d-flex justify-content-between align-items-center mb-4">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="rememberMe" name="remember_me">
                                    <label class="form-check-label text-muted" for="rememberMe">
                                        Remember me
                                    </label>
                                </div>
                                <a href="#" class="text-decoration-none">Forgot password?</a>
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

                        <div class="mt-4 pt-4 border-top">
                            <div class="text-center">
                                <p class="text-muted mb-2"><small>Demo Login Credentials:</small></p>
                                <p class="text-muted"><small>Email: demo@gccbank.com.gh<br>Password: Demo123!</small></p>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-lg-6 d-none d-lg-block">
                    <div class="auth-brand">
                        <div class="auth-brand-content">
                            <h2>Secure Banking</h2>
                            <p class="mb-4">Experience the future of banking with GCC Bank's secure and innovative digital platform.</p>
                            
                            <div class="row g-3 text-center">
                                <div class="col-6">
                                    <div class="bg-white bg-opacity-10 rounded p-3">
                                        <i class="fas fa-shield-alt text-gold fs-2 mb-2"></i>
                                        <h6 class="text-white mb-1">Bank-Grade Security</h6>
                                        <small class="text-light">256-bit encryption</small>
                                    </div>
                                </div>
                                <div class="col-6">
                                    <div class="bg-white bg-opacity-10 rounded p-3">
                                        <i class="fas fa-mobile-alt text-gold fs-2 mb-2"></i>
                                        <h6 class="text-white mb-1">Mobile Banking</h6>
                                        <small class="text-light">Bank on the go</small>
                                    </div>
                                </div>
                                <div class="col-6">
                                    <div class="bg-white bg-opacity-10 rounded p-3">
                                        <i class="fas fa-clock text-gold fs-2 mb-2"></i>
                                        <h6 class="text-white mb-1">24/7 Support</h6>
                                        <small class="text-light">Always available</small>
                                    </div>
                                </div>
                                <div class="col-6">
                                    <div class="bg-white bg-opacity-10 rounded p-3">
                                        <i class="fas fa-globe text-gold fs-2 mb-2"></i>
                                        <h6 class="text-white mb-1">Global Reach</h6>
                                        <small class="text-light">Worldwide access</small>
                                    </div>
                                </div>
                            </div>

                            <div class="mt-4">
                                <p class="text-light mb-0">
                                    <i class="fas fa-info-circle me-2"></i>
                                    Your account is protected by multi-factor authentication and continuous monitoring.
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
