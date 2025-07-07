<?php
session_start();
require_once('../conn.php');

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

    if (empty($email) || empty($password)) {
        $error = "Please fill in all fields.";
    } else {
        $sql = "SELECT a.account, a.balance, a.status, a.password,
                       h.name, h.email, h.image 
                FROM accounts_info a
                JOIN accountsholder h ON a.account = h.account
                WHERE h.email = ? LIMIT 1";

        $stmt = mysqli_prepare($con, $sql);
        if (!$stmt) {
            $error = "Database error.";
        } else {
            mysqli_stmt_bind_param($stmt, "s", $email);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);

            if ($result && mysqli_num_rows($result) === 1) {
                $client = mysqli_fetch_assoc($result);

                if (password_verify($password, $client['password'])) {
                    if ($client['status'] === 'Active') {
                        // Set session
                        $_SESSION['client_loggedin'] = true;
                        $_SESSION['client_account'] = $client['account'];
                        $_SESSION['client_name'] = $client['name'];
                        $_SESSION['client_email'] = $client['email'];
                        $_SESSION['client_balance'] = $client['balance'];
                        $_SESSION['client_image'] = $client['image'];

                        // Insert login history
                        $ip = $_SERVER['REMOTE_ADDR'];
                        $loginTime = date('Y-m-d H:i:s');
                        $insert_sql = "INSERT INTO client_login_history (account, login_time, ip_address) 
                                       VALUES (?, ?, ?)";
                        $insert_stmt = mysqli_prepare($con, $insert_sql);

                        if ($insert_stmt) {
                            mysqli_stmt_bind_param($insert_stmt, "sss", $client['account'], $loginTime, $ip);
                            mysqli_stmt_execute($insert_stmt);
                            $_SESSION['login_id'] = mysqli_insert_id($con);
                        }

                        header('Location: dashboard/index.php');
                        exit;
                    } else {
                        $error = "Your account is inactive. Please contact support.";
                    }
                } else {
                    $error = "Invalid email or password.";
                }
            } else {
                $error = "Invalid email or password.";
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

<?php include 'includes/footer.php'; ?>
