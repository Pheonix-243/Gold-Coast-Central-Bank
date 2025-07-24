<?php 
$page_title = 'Register';
include 'includes/header.php';

// Process registration form
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $first_name = sanitize_input($_POST['first_name'] ?? '');
    $last_name = sanitize_input($_POST['last_name'] ?? '');
    $email = sanitize_input($_POST['email'] ?? '');
    $phone = sanitize_input($_POST['phone'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $referral_code = sanitize_input($_POST['referral_code'] ?? '');
    $terms_accepted = isset($_POST['terms_accepted']);
    $csrf_token = $_POST[CSRF_TOKEN_NAME] ?? '';
    
    $errors = [];
    
    // Validate CSRF token
    if (!verify_csrf_token($csrf_token)) {
        $errors[] = 'Invalid security token. Please try again.';
    }
    
    // Validate required fields
    if (empty($first_name)) $errors[] = 'First name is required.';
    if (empty($last_name)) $errors[] = 'Last name is required.';
    if (empty($email)) $errors[] = 'Email address is required.';
    if (empty($phone)) $errors[] = 'Phone number is required.';
    if (empty($password)) $errors[] = 'Password is required.';
    if (empty($confirm_password)) $errors[] = 'Password confirmation is required.';
    if (!$terms_accepted) $errors[] = 'You must accept the terms and conditions.';
    
    // Validate email format
    if (!empty($email) && !validate_email($email)) {
        $errors[] = 'Please provide a valid email address.';
    }
    
    // Validate phone number
    if (!empty($phone) && !validate_ghana_phone($phone)) {
        $errors[] = 'Please provide a valid Ghana phone number.';
    }
    
    // Validate password strength
    if (!empty($password)) {
        if (strlen($password) < 8) {
            $errors[] = 'Password must be at least 8 characters long.';
        }
        if (!preg_match('/[A-Z]/', $password)) {
            $errors[] = 'Password must contain at least one uppercase letter.';
        }
        if (!preg_match('/[a-z]/', $password)) {
            $errors[] = 'Password must contain at least one lowercase letter.';
        }
        if (!preg_match('/\d/', $password)) {
            $errors[] = 'Password must contain at least one number.';
        }
        if (!preg_match('/[!@#$%^&*(),.?":{}|<>]/', $password)) {
            $errors[] = 'Password must contain at least one special character.';
        }
    }
    
    // Validate password confirmation
    if (!empty($password) && !empty($confirm_password) && $password !== $confirm_password) {
        $errors[] = 'Passwords do not match.';
    }
    
    if (empty($errors)) {
        // In production, save to database and send verification email
        // For now, simulate successful registration
        $_SESSION['user_id'] = 2;
        $_SESSION['user_name'] = $first_name . ' ' . $last_name;
        $_SESSION['user_email'] = $email;
        set_message('success', 'Registration successful! Welcome to GCC Bank.');
        header('Location: index.php');
        exit;
    } else {
        foreach ($errors as $error) {
            set_message('error', $error);
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
                            <h2 class="text-navy">Join GCC Bank</h2>
                            <p class="text-muted">Create your account and start banking today</p>
                        </div>

                        <form method="POST" class="needs-validation" novalidate>
                            <?= csrf_token_input() ?>
                            
                            <div class="row g-3 mb-3">
                                <div class="col-md-6">
                                    <div class="form-floating">
                                        <input type="text" class="form-control" id="firstName" name="first_name" 
                                               placeholder="First Name" required 
                                               value="<?= htmlspecialchars($_POST['first_name'] ?? '') ?>">
                                        <label for="firstName">First Name</label>
                                        <div class="invalid-feedback">Please provide your first name.</div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-floating">
                                        <input type="text" class="form-control" id="lastName" name="last_name" 
                                               placeholder="Last Name" required 
                                               value="<?= htmlspecialchars($_POST['last_name'] ?? '') ?>">
                                        <label for="lastName">Last Name</label>
                                        <div class="invalid-feedback">Please provide your last name.</div>
                                    </div>
                                </div>
                            </div>

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
                                <div class="form-floating">
                                    <input type="tel" class="form-control" id="phone" name="phone" 
                                           placeholder="Phone Number" required 
                                           value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>">
                                    <label for="phone">Phone Number</label>
                                    <div class="invalid-feedback">Please provide a valid Ghana phone number.</div>
                                    <div class="form-text">Format: 0244123456 or +233244123456</div>
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
                                    <div class="invalid-feedback">Please provide a strong password.</div>
                                </div>
                            </div>

                            <div class="mb-3">
                                <div class="form-floating position-relative">
                                    <input type="password" class="form-control" id="confirmPassword" name="confirm_password" 
                                           placeholder="Confirm Password" required>
                                    <label for="confirmPassword">Confirm Password</label>
                                    <button type="button" class="password-toggle">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                    <div class="invalid-feedback">Passwords do not match.</div>
                                </div>
                            </div>

                            <div class="mb-3">
                                <div class="form-floating">
                                    <input type="text" class="form-control" id="referralCode" name="referral_code" 
                                           placeholder="Referral Code (Optional)" 
                                           value="<?= htmlspecialchars($_POST['referral_code'] ?? '') ?>">
                                    <label for="referralCode">Referral Code (Optional)</label>
                                    <div class="form-text">Enter a referral code if you have one</div>
                                </div>
                            </div>

                            <div class="mb-4">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="termsAccepted" 
                                           name="terms_accepted" required <?= isset($_POST['terms_accepted']) ? 'checked' : '' ?>>
                                    <label class="form-check-label text-muted" for="termsAccepted">
                                        I agree to the <a href="#" class="text-decoration-none">Terms and Conditions</a> 
                                        and <a href="#" class="text-decoration-none">Privacy Policy</a>
                                    </label>
                                    <div class="invalid-feedback">You must accept the terms and conditions.</div>
                                </div>
                            </div>

                            <button type="submit" class="btn btn-primary btn-lg w-100 mb-3">
                                <i class="fas fa-user-plus me-2"></i>Create Account
                            </button>

                            <div class="text-center">
                                <p class="text-muted mb-3">Already have an account?</p>
                                <a href="login.php" class="btn btn-outline-primary">
                                    <i class="fas fa-sign-in-alt me-2"></i>Sign In
                                </a>
                            </div>
                        </form>

                        <div class="mt-4 pt-4 border-top">
                            <div class="text-center">
                                <p class="text-muted mb-0">
                                    <small>
                                        <i class="fas fa-shield-alt me-1"></i>
                                        Your information is protected with bank-grade security
                                    </small>
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-lg-6 d-none d-lg-block">
                    <div class="auth-brand">
                        <div class="auth-brand-content">
                            <h2>Start Your Journey</h2>
                            <p class="mb-4">Join thousands of satisfied customers who trust GCC Bank for their financial needs.</p>
                            
                            <div class="mb-4">
                                <div class="bg-white bg-opacity-10 rounded p-4">
                                    <h5 class="text-gold mb-3">Account Benefits</h5>
                                    <ul class="list-unstyled text-light">
                                        <li class="mb-2"><i class="fas fa-check text-gold me-2"></i>No monthly maintenance fees</li>
                                        <li class="mb-2"><i class="fas fa-check text-gold me-2"></i>Free online and mobile banking</li>
                                        <li class="mb-2"><i class="fas fa-check text-gold me-2"></i>24/7 customer support</li>
                                        <li class="mb-2"><i class="fas fa-check text-gold me-2"></i>Competitive interest rates</li>
                                        <li class="mb-2"><i class="fas fa-check text-gold me-2"></i>Mobile money integration</li>
                                        <li class="mb-0"><i class="fas fa-check text-gold me-2"></i>International transfer services</li>
                                    </ul>
                                </div>
                            </div>

                            <div class="row g-3 text-center">
                                <div class="col-6">
                                    <div class="bg-white bg-opacity-10 rounded p-3">
                                        <h4 class="text-gold mb-1">50K+</h4>
                                        <small class="text-light">Happy Customers</small>
                                    </div>
                                </div>
                                <div class="col-6">
                                    <div class="bg-white bg-opacity-10 rounded p-3">
                                        <h4 class="text-gold mb-1">4.8★</h4>
                                        <small class="text-light">Customer Rating</small>
                                    </div>
                                </div>
                            </div>

                            <div class="mt-4">
                                <p class="text-light mb-0">
                                    <i class="fas fa-info-circle me-2"></i>
                                    Account verification usually takes 24-48 hours. You'll receive email updates throughout the process.
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
