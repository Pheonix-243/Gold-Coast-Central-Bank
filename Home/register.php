<?php
session_start();
require_once 'config/database.php';
require_once 'includes/emails.php';

// Initialize registration data in session
if (!isset($_SESSION['registration_data'])) {
    $_SESSION['registration_data'] = [];
}

$error = '';
$success = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['step'])) {
        $step = $_POST['step'];
        
        // Store data in session based on current step
        switch ($step) {
            case '1':
                $_SESSION['registration_data']['personal'] = $_POST;
                break;
            case '2':
                $_SESSION['registration_data']['account'] = $_POST;
                break;
            case '3':
                $_SESSION['registration_data']['security'] = $_POST;
                break;
            case '4':
                // Handle file upload
                if (isset($_FILES['passportImage']) && $_FILES['passportImage']['error'] === UPLOAD_ERR_OK) {
                    $_SESSION['registration_data']['photo'] = handleImageUpload($_FILES['passportImage']);
                } else {
                    $error = "Passport photo is required";
                }
                break;
            case '5':
                $_SESSION['registration_data']['deposit'] = $_POST;
                
                // Complete registration
                $result = completeRegistration();
                if ($result['success']) {
                    $success = $result['message'];
                    // Clear session data
                    unset($_SESSION['registration_data']);
                } else {
                    $error = $result['message'];
                }
                break;
        }
        
        // If there's an error, stay on current step, otherwise proceed
        if (empty($error)) {
            $nextStep = min($step + 1, 5);
            header("Location: ?step=$nextStep");
            exit;
        }
    }
}

// Get current step from URL or default to 1
$currentStep = isset($_GET['step']) ? intval($_GET['step']) : 1;
$currentStep = max(1, min($currentStep, 5)); // Ensure step is between 1-5

function handleImageUpload($file) {
    $allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
    $maxSize = 2 * 1024 * 1024; // 2MB
    
    if (!in_array($file['type'], $allowedTypes)) {
        throw new Exception('Only JPG, PNG, and GIF images are allowed');
    }
    
    if ($file['size'] > $maxSize) {
        throw new Exception('Image size must be less than 2MB');
    }

    // Verify it's actually an image
    $imageInfo = getimagesize($file['tmp_name']);
    if (!$imageInfo) {
        throw new Exception('Uploaded file is not a valid image');
    }

    // Convert to binary data for database storage
    return file_get_contents($file['tmp_name']);
}

function generateAccountNumber() {
    $db = DatabaseConnection::getInstance()->getConnection();
    do {
        $account = 'GC' . date('Y') . str_pad(mt_rand(1, 9999999999), 10, '0', STR_PAD_LEFT);
        $stmt = $db->prepare("SELECT account FROM accounts_info WHERE account = ?");
        $stmt->execute([$account]);
    } while ($stmt->fetch());
    
    return $account;
}

function generateSecurePassword() {
    $generator = "1357902468";
    $result = "";
    for ($i = 1; $i <= 8; $i++) {
        $result .= substr($generator, (rand()%(strlen($generator))), 1);
    }
    return $result;
}

function completeRegistration() {
    $db = DatabaseConnection::getInstance()->getConnection();
    $data = $_SESSION['registration_data'];
    
    // Validate all data is present
    if (!isset($data['personal'], $data['account'], $data['security'], $data['photo'], $data['deposit'])) {
        return ['success' => false, 'message' => 'Incomplete registration data'];
    }
    
    $personal = $data['personal'];
    $account = $data['account'];
    $security = $data['security'];
    $photo = $data['photo'];
    $deposit = $data['deposit'];
    
    try {
        $db->beginTransaction();
        
        // Generate account details
        $accountNumber = generateAccountNumber();
        $tempPassword = generateSecurePassword();
        $hashedPassword = password_hash($tempPassword, PASSWORD_DEFAULT);
        $registerDate = date('Y-m-d H:i:s');
        
        // Insert into accountsholder table
        $stmt = $db->prepare("
            INSERT INTO accountsholder 
            (account, name, fname, cnic, contect, dob, gender, email, image, postal, city, houseaddress) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $cnic = (int) str_replace('-', '', $personal['cnic']);
        
        $stmt->execute([
            $accountNumber,
            $personal['firstName'] . ' ' . $personal['lastName'],
            $personal['fname'] ?? '',
            $cnic,
            preg_replace('/\D/', '', $personal['phone']),
            $personal['dob'],
            $personal['gender'],
            $personal['email'],
            $photo,
            $personal['postalCode'],
            $personal['city'],
            $personal['address']
        ]);
        
        // Insert into accounts_info table
        $stmt = $db->prepare("
            INSERT INTO accounts_info 
            (account, account_title, account_type, balance, registerdate, password) 
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        
        $stmt->execute([
            $accountNumber,
            $personal['firstName'] . ' ' . $personal['lastName'],
            $account['accountType'],
            $deposit['initialDeposit'],
            $registerDate,
            $hashedPassword
        ]);
        
        // Store security questions
        $stmt = $db->prepare("
            INSERT INTO security_questions (account, question, answer_hash) 
            VALUES (?, ?, ?)
        ");
        
        $stmt->execute([
            $accountNumber,
            $security['securityQuestion1'],
            password_hash(strtolower(trim($security['securityAnswer1'])), PASSWORD_DEFAULT)
        ]);
        
        $stmt->execute([
            $accountNumber,
            $security['securityQuestion2'],
            password_hash(strtolower(trim($security['securityAnswer2'])), PASSWORD_DEFAULT)
        ]);
        
        $db->commit();
        
        // Send welcome email
        sendWelcomeEmail(
            $personal['email'],
            $accountNumber,
            $tempPassword,
            $personal['firstName'] . ' ' . $personal['lastName'],
            $deposit['initialDeposit']
        );
        
        return [
            'success' => true,
            'message' => "Account created successfully! Your account number is: $accountNumber. Check your email for temporary password."
        ];
        
    } catch (Exception $e) {
        $db->rollback();
        return ['success' => false, 'message' => 'Registration failed: ' . $e->getMessage()];
    }
}

function sendWelcomeEmail($email, $accountNumber, $tempPassword, $clientName, $initialDeposit) {
    $subject = "Welcome to Gold Coast Central Bank - Your Account Details";
    
    $message = "
    Welcome to Gold Coast Central Bank!

    Your account has been created successfully.

    Account Number: $accountNumber
    Temporary Password: $tempPassword
    Email: $email
    Initial Deposit: $" . number_format($initialDeposit, 2) . "

    IMPORTANT: You must change your temporary password on first login.

    Login at: http://localhost/gccb/login.php

    Gold Coast Central Bank - Secure Banking Solutions
    ";
    
    email_send($email, $subject, $message);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register Account - Gold Coast Central Bank</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Playfair+Display:wght@600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary-gold: #FFD700;
            --dark-gold: #DAA520;
            --navy-blue: #1B365D;
            --light-navy: #2C4F7C;
            --dark-navy: #0F1B2C;
            --white: #FFFFFF;
            --light-gray: #F8F9FA;
            --medium-gray: #6C757D;
            --dark-gray: #343A40;
            --success-green: #28A745;
            --danger-red: #DC3545;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, var(--light-gray) 0%, var(--white) 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            color: var(--dark-gray);
            line-height: 1.6;
        }

        h1, h2, h3, h4, h5, h6 {
            font-family: 'Playfair Display', serif;
            font-weight: 600;
            line-height: 1.2;
        }

        .text-gold {
            color: var(--primary-gold) !important;
        }

        .bg-gold {
            background-color: var(--primary-gold) !important;
        }

        .text-navy {
            color: var(--navy-blue) !important;
        }

        .bg-navy {
            background-color: var(--navy-blue) !important;
        }

        .auth-container {
            width: 100%;
            padding: 2rem 0;
        }

        .auth-card {
            background: var(--white);
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
            overflow: hidden;
            max-width: 1000px;
            margin: 0 auto;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }

        .auth-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 25px 50px rgba(0, 0, 0, 0.15);
        }

        .auth-brand {
            background: linear-gradient(135deg, var(--navy-blue) 0%, var(--light-navy) 100%);
            color: var(--white);
            padding: 3rem;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            text-align: center;
            position: relative;
            overflow: hidden;
        }

        .auth-brand::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url("data:image/svg+xml,%3Csvg width='40' height='40' viewBox='0 0 40 40' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='none' fill-rule='evenodd'%3E%3Cg fill='%23FFD700' fill-opacity='0.1'%3E%3Cpath d='M20 20c0-11.046-8.954-20-20-20s-20 8.954-20 20 8.954 20 20 20 20-8.954 20-20z'/%3E%3C/g%3E%3C/svg%3E") repeat;
            opacity: 0.1;
        }

        .auth-brand-content {
            position: relative;
            z-index: 2;
        }

        .auth-brand h2 {
            color: var(--primary-gold);
            margin-bottom: 1rem;
            font-size: 2.5rem;
        }

        .auth-brand p {
            color: rgba(255, 255, 255, 0.9);
            font-size: 1.1rem;
            line-height: 1.6;
        }

        .auth-form-section {
            padding: 3rem;
        }

        .step-indicator {
            display: flex;
            justify-content: space-between;
            margin-bottom: 2rem;
            position: relative;
        }

        .step-indicator::before {
            content: '';
            position: absolute;
            top: 15px;
            left: 0;
            right: 0;
            height: 2px;
            background: #e5e7eb;
            z-index: 1;
        }

        .step {
            display: flex;
            flex-direction: column;
            align-items: center;
            position: relative;
            z-index: 2;
        }

        .step-number {
            width: 30px;
            height: 30px;
            border-radius: 50%;
            background: #e5e7eb;
            color: #6b7280;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            margin-bottom: 0.5rem;
            transition: all 0.3s ease;
        }

        .step.active .step-number {
            background: var(--navy-blue);
            color: white;
            transform: scale(1.1);
            box-shadow: 0 4px 8px rgba(27, 54, 93, 0.3);
        }

        .step.completed .step-number {
            background: var(--primary-gold);
            color: var(--dark-navy);
        }

        .step-label {
            font-size: 0.85rem;
            font-weight: 500;
            color: var(--medium-gray);
            transition: all 0.3s ease;
        }

        .step.active .step-label {
            color: var(--navy-blue);
            font-weight: 600;
        }

        .step-content {
            display: none;
            animation: fadeIn 0.5s ease;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .step-content.active {
            display: block;
        }

        .image-preview {
            width: 150px;
            height: 150px;
            border: 2px dashed #dee2e6;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            margin: 0 auto 1rem;
            background: #f8f9fa;
            transition: all 0.3s ease;
        }

        .image-preview:hover {
            border-color: var(--primary-gold);
        }

        .image-preview img {
            max-width: 100%;
            max-height: 100%;
        }

        .form-control, .form-select {
            border: 2px solid #E9ECEF;
            border-radius: 8px;
            padding: 0.75rem 1rem;
            font-size: 1rem;
            transition: all 0.3s ease;
        }

        .form-control:focus, .form-select:focus {
            border-color: var(--primary-gold);
            box-shadow: 0 0 0 0.2rem rgba(255, 215, 0, 0.25);
        }

        .btn-primary {
            background-color: var(--navy-blue);
            border-color: var(--navy-blue);
            font-weight: 500;
            transition: all 0.3s ease;
            padding: 0.75rem 1.5rem;
        }

        .btn-primary:hover {
            background-color: var(--dark-navy);
            border-color: var(--dark-navy);
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(27, 54, 93, 0.3);
        }

        .btn-gold {
            background-color: var(--primary-gold);
            border-color: var(--primary-gold);
            color: var(--dark-navy);
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .btn-gold:hover {
            background-color: var(--dark-gold);
            border-color: var(--dark-gold);
            color: var(--dark-navy);
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(255, 215, 0, 0.4);
        }

        .alert {
            border-radius: 10px;
            border: none;
            font-weight: 500;
            animation: fadeIn 0.5s ease;
        }

        .alert-success {
            background-color: rgba(40, 167, 69, 0.1);
            color: var(--success-green);
            border-left: 4px solid var(--success-green);
        }

        .alert-danger {
            background-color: rgba(220, 53, 69, 0.1);
            color: var(--danger-red);
            border-left: 4px solid var(--danger-red);
        }

        .success-container {
            text-align: center;
            padding: 3rem;
            animation: fadeIn 0.8s ease;
        }

        .success-icon {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, var(--primary-gold), var(--dark-gold));
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 2rem;
            font-size: 2.5rem;
            color: var(--dark-navy);
            animation: pulse 2s infinite;
        }

        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.05); }
            100% { transform: scale(1); }
        }

        .feature-icon {
            width: 60px;
            height: 60px;
            background: rgba(255, 215, 0, 0.2);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1rem;
            font-size: 1.5rem;
            color: var(--primary-gold);
        }

        @media (max-width: 768px) {
            .auth-card {
                margin: 1rem;
                border-radius: 15px;
            }

            .auth-form-section, .auth-brand {
                padding: 2rem;
            }

            .step-label {
                font-size: 0.75rem;
            }
        }

        /* PayStack Modal Fixes */
        .paystack-modal .modal-content {
            border: none;
            border-radius: 15px;
            overflow: hidden;
        }

        .paystack-modal .modal-header {
            background: linear-gradient(135deg, var(--navy-blue) 0%, var(--light-navy) 100%);
            color: white;
            border-bottom: none;
        }

        .paystack-modal .modal-body {
            padding: 2rem;
        }

        .paystack-modal .btn-close {
            filter: invert(1);
        }

        /* Add to the existing CSS */
.confetti-container {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    pointer-events: none;
    z-index: 9999;
}

.feature-icon-sm {
    width: 40px;
    height: 40px;
    background: rgba(255, 215, 0, 0.2);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 0.5rem;
    font-size: 1rem;
    color: var(--primary-gold);
}
    </style>
</head>
<body>
    <div class="auth-container">
        <div class="container">
            <div class="auth-card">
                <div class="row g-0">
                    <div class="col-lg-6 d-none d-lg-block">
                        <div class="auth-brand">
                            <div class="auth-brand-content">
                                <img src="assets/images/gcc-logo.webp" alt="GCC Bank Logo" height="80" class="mb-4">
                                <h2>Join Gold Coast Central Bank</h2>
                                <p class="mb-4">Experience premium banking with security, convenience, and personalized service.</p>

                                <div class="row g-3 text-center">
                                    <div class="col-6">
                                        <div class="feature-icon">
                                            <i class="fas fa-shield-alt"></i>
                                        </div>
                                        <h6 class="text-white">Bank-Grade Security</h6>
                                    </div>
                                    <div class="col-6">
                                        <div class="feature-icon">
                                            <i class="fas fa-mobile-alt"></i>
                                        </div>
                                        <h6 class="text-white">Mobile Banking</h6>
                                    </div>
                                    <div class="col-6">
                                        <div class="feature-icon">
                                            <i class="fas fa-clock"></i>
                                        </div>
                                        <h6 class="text-white">24/7 Support</h6>
                                    </div>
                                    <div class="col-6">
                                        <div class="feature-icon">
                                            <i class="fas fa-globe"></i>
                                        </div>
                                        <h6 class="text-white">Global Reach</h6>
                                    </div>
                                </div>

                                <p class="text-light mt-4"><i class="fas fa-info-circle me-2"></i>Your information is protected with industry-standard encryption.</p>
                            </div>
                        </div>
                    </div>

                    <div class="col-lg-6">
                        <div class="auth-form-section">
                            <div class="text-center mb-4">
                                <img src="assets/images/gcc-logo.webp" alt="GCC Bank Logo" height="60" class="mb-3 d-lg-none">
                                <h2 class="text-navy">Create Your Account</h2>
                                <p class="text-muted">Join Gold Coast Central Bank in a few simple steps</p>
                            </div>

                            <?php if (!empty($error)): ?>
                                <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
                            <?php endif; ?>

                         <?php if (!empty($success)): ?>
    <div class="success-container">
        <div class="success-icon">
            <i class="fas fa-check"></i>
        </div>
        <div class="confetti-container" id="confettiContainer"></div>
        <h3 class="text-navy mb-3">Welcome to Gold Coast Central Bank!</h3>
        <p class="text-muted mb-4"><?= htmlspecialchars($success) ?></p>
        <div class="row g-3 text-center mb-4">
            <div class="col-4">
                <div class="feature-icon-sm">
                    <i class="fas fa-shield-alt"></i>
                </div>
                <small class="text-muted">Secure</small>
            </div>
            <div class="col-4">
                <div class="feature-icon-sm">
                    <i class="fas fa-bolt"></i>
                </div>
                <small class="text-muted">Fast</small>
            </div>
            <div class="col-4">
                <div class="feature-icon-sm">
                    <i class="fas fa-globe"></i>
                </div>
                <small class="text-muted">Global</small>
            </div>
        </div>
        <div class="d-grid gap-2">
            <a href="login.php" class="btn btn-gold btn-lg">
                <i class="fas fa-sign-in-alt me-2"></i>Access Your Account
            </a>
        </div>
    </div>
<?php else: ?>

                            <!-- Step Indicator -->
                            <div class="step-indicator">
                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                    <div class="step <?= $i == $currentStep ? 'active' : '' ?> <?= $i < $currentStep ? 'completed' : '' ?>" data-step="<?= $i ?>">
                                        <div class="step-number">
                                            <?php if ($i < $currentStep): ?>
                                                <i class="fas fa-check"></i>
                                            <?php else: ?>
                                                <?= $i ?>
                                            <?php endif; ?>
                                        </div>
                                        <div class="step-label">
                                            <?= ['Personal', 'Account', 'Security', 'Photo', 'Deposit'][$i-1] ?>
                                        </div>
                                    </div>
                                <?php endfor; ?>
                            </div>

                            <!-- Step 1: Personal Information -->
                            <div class="step-content <?= $currentStep == 1 ? 'active' : '' ?>" id="step-1">
                                <form method="POST" enctype="multipart/form-data">
                                    <input type="hidden" name="step" value="1">
                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label class="form-label">First Name *</label>
                                            <input type="text" class="form-control" name="firstName" required 
                                                   value="<?= htmlspecialchars($_SESSION['registration_data']['personal']['firstName'] ?? '') ?>">
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <label class="form-label">Last Name *</label>
                                            <input type="text" class="form-control" name="lastName" required
                                                   value="<?= htmlspecialchars($_SESSION['registration_data']['personal']['lastName'] ?? '') ?>">
                                        </div>
                                    </div>
                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label class="form-label">Email *</label>
                                            <input type="email" class="form-control" name="email" required
                                                   value="<?= htmlspecialchars($_SESSION['registration_data']['personal']['email'] ?? '') ?>">
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <label class="form-label">Phone *</label>
                                            <input type="tel" class="form-control" name="phone" required
                                                   value="<?= htmlspecialchars($_SESSION['registration_data']['personal']['phone'] ?? '') ?>">
                                        </div>
                                    </div>
                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label class="form-label">Date of Birth *</label>
                                            <input type="date" class="form-control" name="dob" required max="<?= date('Y-m-d', strtotime('-18 years')) ?>"
                                                   value="<?= htmlspecialchars($_SESSION['registration_data']['personal']['dob'] ?? '') ?>">
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <label class="form-label">Gender *</label>
                                            <select class="form-select" name="gender" required>
                                                <option value="">Select Gender</option>
                                                <option value="Male" <?= ($_SESSION['registration_data']['personal']['gender'] ?? '') == 'Male' ? 'selected' : '' ?>>Male</option>
                                                <option value="Female" <?= ($_SESSION['registration_data']['personal']['gender'] ?? '') == 'Female' ? 'selected' : '' ?>>Female</option>
                                                <option value="Other" <?= ($_SESSION['registration_data']['personal']['gender'] ?? '') == 'Other' ? 'selected' : '' ?>>Other</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">CNIC *</label>
                                        <input type="text" class="form-control" name="cnic" placeholder="XXXXX-XXXXXXX-X" pattern="\d{5}-\d{7}-\d{1}" required
                                               value="<?= htmlspecialchars($_SESSION['registration_data']['personal']['cnic'] ?? '') ?>">
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Address *</label>
                                        <input type="text" class="form-control" name="address" required
                                               value="<?= htmlspecialchars($_SESSION['registration_data']['personal']['address'] ?? '') ?>">
                                    </div>
                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label class="form-label">City *</label>
                                            <input type="text" class="form-control" name="city" required
                                                   value="<?= htmlspecialchars($_SESSION['registration_data']['personal']['city'] ?? '') ?>">
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <label class="form-label">Postal Code *</label>
                                            <input type="text" class="form-control" name="postalCode" required
                                                   value="<?= htmlspecialchars($_SESSION['registration_data']['personal']['postalCode'] ?? '') ?>">
                                        </div>
                                    </div>
                                    <div class="d-flex justify-content-between mt-4">
                                        <div></div>
                                        <button type="submit" class="btn btn-primary">Next <i class="fas fa-arrow-right ms-2"></i></button>
                                    </div>
                                </form>
                            </div>

                            <!-- Step 2: Account Details -->
                            <div class="step-content <?= $currentStep == 2 ? 'active' : '' ?>" id="step-2">
                                <form method="POST">
                                    <input type="hidden" name="step" value="2">
                                    <div class="mb-4">
                                        <label class="form-label">Account Type *</label>
                                        <div class="row">
                                            <div class="col-md-6 mb-3">
                                                <div class="card h-100 border-2 <?= ($_SESSION['registration_data']['account']['accountType'] ?? 'checking') == 'checking' ? 'border-primary' : '' ?>" style="cursor: pointer;">
                                                    <div class="card-body text-center">
                                                        <input class="form-check-input" type="radio" name="accountType" value="checking" 
                                                               <?= ($_SESSION['registration_data']['account']['accountType'] ?? 'checking') == 'checking' ? 'checked' : '' ?> required style="display: none;">
                                                        <i class="fas fa-wallet text-primary mb-3" style="font-size: 2rem;"></i>
                                                        <h5 class="card-title">Checking Account</h5>
                                                        <p class="card-text text-muted">Ideal for everyday transactions with easy access to funds.</p>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="col-md-6 mb-3">
                                                <div class="card h-100 border-2 <?= ($_SESSION['registration_data']['account']['accountType'] ?? '') == 'savings' ? 'border-primary' : '' ?>" style="cursor: pointer;">
                                                    <div class="card-body text-center">
                                                        <input class="form-check-input" type="radio" name="accountType" value="savings"
                                                               <?= ($_SESSION['registration_data']['account']['accountType'] ?? '') == 'savings' ? 'checked' : '' ?> required style="display: none;">
                                                        <i class="fas fa-piggy-bank text-primary mb-3" style="font-size: 2rem;"></i>
                                                        <h5 class="card-title">Savings Account</h5>
                                                        <p class="card-text text-muted">Earn interest while keeping your money safe and accessible.</p>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Father's Name (Optional)</label>
                                        <input type="text" class="form-control" name="fname"
                                               value="<?= htmlspecialchars($_SESSION['registration_data']['account']['fname'] ?? '') ?>">
                                    </div>
                                    <div class="d-flex justify-content-between mt-4">
                                        <a href="?step=1" class="btn btn-outline-secondary"><i class="fas fa-arrow-left me-2"></i>Back</a>
                                        <button type="submit" class="btn btn-primary">Next <i class="fas fa-arrow-right ms-2"></i></button>
                                    </div>
                                </form>
                            </div>

                            <!-- Step 3: Security Questions -->
                            <div class="step-content <?= $currentStep == 3 ? 'active' : '' ?>" id="step-3">
                                <form method="POST">
                                    <input type="hidden" name="step" value="3">
                                    <div class="mb-4">
                                        <p class="text-muted mb-4">Please select and answer two security questions. These will be used to verify your identity if you forget your password.</p>
                                        
                                        <div class="mb-3">
                                            <label class="form-label">Security Question 1 *</label>
                                            <select class="form-select" name="securityQuestion1" required>
                                                <option value="">Select a question</option>
                                                <option value="What was the name of your first pet?" <?= ($_SESSION['registration_data']['security']['securityQuestion1'] ?? '') == 'What was the name of your first pet?' ? 'selected' : '' ?>>What was the name of your first pet?</option>
                                                <option value="What is your mother's maiden name?" <?= ($_SESSION['registration_data']['security']['securityQuestion1'] ?? "What is your mother's maiden name?") == "What is your mother's maiden name?" ? 'selected' : '' ?>>What is your mother's maiden name?</option>
                                                <option value="What city were you born in?" <?= ($_SESSION['registration_data']['security']['securityQuestion1'] ?? '') == 'What city were you born in?' ? 'selected' : '' ?>>What city were you born in?</option>
                                            </select>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">Your Answer *</label>
                                            <input type="text" class="form-control" name="securityAnswer1" required
                                                   value="<?= htmlspecialchars($_SESSION['registration_data']['security']['securityAnswer1'] ?? '') ?>">
                                        </div>
                                    </div>
                                    
                                    <div class="mb-4">
                                        <div class="mb-3">
                                            <label class="form-label">Security Question 2 *</label>
                                            <select class="form-select" name="securityQuestion2" required>
                                                <option value="">Select a question</option>
                                                <option value="What is your favorite movie?" <?= ($_SESSION['registration_data']['security']['securityQuestion2'] ?? '') == 'What is your favorite movie?' ? 'selected' : '' ?>>What is your favorite movie?</option>
                                                <option value="What was the name of your first teacher?" <?= ($_SESSION['registration_data']['security']['securityQuestion2'] ?? '') == 'What was the name of your first teacher?' ? 'selected' : '' ?>>What was the name of your first teacher?</option>
                                                <option value="What is your favorite book?" <?= ($_SESSION['registration_data']['security']['securityQuestion2'] ?? '') == 'What is your favorite book?' ? 'selected' : '' ?>>What is your favorite book?</option>
                                            </select>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">Your Answer *</label>
                                            <input type="text" class="form-control" name="securityAnswer2" required
                                                   value="<?= htmlspecialchars($_SESSION['registration_data']['security']['securityAnswer2'] ?? '') ?>">
                                        </div>
                                    </div>
                                    
                                    <div class="d-flex justify-content-between mt-4">
                                        <a href="?step=2" class="btn btn-outline-secondary"><i class="fas fa-arrow-left me-2"></i>Back</a>
                                        <button type="submit" class="btn btn-primary">Next <i class="fas fa-arrow-right ms-2"></i></button>
                                    </div>
                                </form>
                            </div>

                            <!-- Step 4: Photo Upload -->
                            <div class="step-content <?= $currentStep == 4 ? 'active' : '' ?>" id="step-4">
                                <form method="POST" enctype="multipart/form-data">
                                    <input type="hidden" name="step" value="4">
                                    <div class="mb-4">
                                        <p class="text-muted mb-4">Please upload a clear passport-style photo for identity verification.</p>
                                        
                                        <div class="mb-3">
                                            <label class="form-label">Passport Photo *</label>
                                            <input type="file" class="form-control" id="passportImage" name="passportImage" accept="image/*" required>
                                            <div class="form-text">Accepted formats: JPG, PNG, GIF. Maximum size: 2MB</div>
                                        </div>
                                        <div class="image-preview mt-3" id="imagePreview">
                                            <i class="fas fa-user-circle fa-3x text-muted"></i>
                                        </div>
                                    </div>
                                    
                                    <div class="d-flex justify-content-between mt-4">
                                        <a href="?step=3" class="btn btn-outline-secondary"><i class="fas fa-arrow-left me-2"></i>Back</a>
                                        <button type="submit" class="btn btn-primary">Next <i class="fas fa-arrow-right ms-2"></i></button>
                                    </div>
                                </form>
                            </div>

                            <!-- Step 5: Initial Deposit -->
                            <div class="step-content <?= $currentStep == 5 ? 'active' : '' ?>" id="step-5">
                                <form method="POST" id="depositForm">
                                    <input type="hidden" name="step" value="5">
                                    <div class="mb-4">
                                        <p class="text-muted mb-4">Make your initial deposit to activate your account. Minimum deposit: $50</p>
                                        
                                        <div class="mb-3">
                                            <label class="form-label">Initial Deposit Amount ($) *</label>
                                            <input type="number" class="form-control" id="initialDeposit" name="initialDeposit" min="50" step="0.01" required
                                                   value="<?= htmlspecialchars($_SESSION['registration_data']['deposit']['initialDeposit'] ?? '') ?>">
                                            <div class="form-text">Minimum deposit: $50.00</div>
                                        </div>
                                    </div>
                                    
                                    <div class="d-flex justify-content-between mt-4">
                                        <a href="?step=4" class="btn btn-outline-secondary"><i class="fas fa-arrow-left me-2"></i>Back</a>
                                        <button type="button" class="btn btn-primary" id="proceedToPayment">
                                            Proceed to Payment <i class="fas fa-lock ms-2"></i>
                                        </button>
                                    </div>
                                </form>
                            </div>

                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- PayStack Payment Modal -->
    <div class="modal fade paystack-modal" id="paymentModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title text-white">Complete Your Deposit</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="text-center mb-4">
                        <i class="fas fa-credit-card fa-3x text-navy mb-3"></i>
                        <h4 class="text-navy">Payment Details</h4>
                        <p class="text-muted">You are about to deposit: <strong>$<span id="depositAmount">0.00</span></strong></p>
                    </div>
                    
                    <div class="d-grid gap-2">
                        <button type="button" class="btn btn-gold btn-lg" id="payWithPaystack">
                            <i class="fas fa-lock me-2"></i>Pay with PayStack
                        </button>
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">
                            Cancel Payment
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://js.paystack.co/v1/inline.js"></script>
    
    <script>
        // Image preview functionality
        document.getElementById('passportImage').addEventListener('change', function(e) {
            const preview = document.getElementById('imagePreview');
            const file = e.target.files[0];
            
            if (file) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    preview.innerHTML = `<img src="${e.target.result}" alt="Preview">`;
                }
                reader.readAsDataURL(file);
            } else {
                preview.innerHTML = '<i class="fas fa-user-circle fa-3x text-muted"></i>';
            }
        });

        // Card selection for account type
        document.querySelectorAll('.card').forEach(card => {
            card.addEventListener('click', function() {
                const radio = this.querySelector('input[type="radio"]');
                if (radio) {
                    radio.checked = true;
                    document.querySelectorAll('.card').forEach(c => c.classList.remove('border-primary'));
                    this.classList.add('border-primary');
                }
            });
        });

        // Payment functionality - Fixed to work exactly like deposit page
        document.getElementById('proceedToPayment').addEventListener('click', function() {
            const depositAmount = document.getElementById('initialDeposit').value;
            
            if (!depositAmount || depositAmount < 50) {
                alert('Please enter a valid deposit amount (minimum $50)');
                return;
            }
            
            document.getElementById('depositAmount').textContent = parseFloat(depositAmount).toFixed(2);
            
            // Show modal - using Bootstrap 5 properly
            const paymentModal = new bootstrap.Modal(document.getElementById('paymentModal'));
            paymentModal.show();
        });

        // PayStack payment handler - Fixed to prevent flickering and work properly
        document.getElementById('payWithPaystack').addEventListener('click', function() {
            const depositAmount = document.getElementById('initialDeposit').value;
            const email = '<?= $_SESSION['registration_data']['personal']['email'] ?? 'customer@example.com' ?>';
            
            if (!depositAmount || depositAmount < 50) {
                alert('Invalid deposit amount');
                return;
            }

            // Convert to kobo (PayStack uses kobo for NGN, but for USD we'll use cents)
            const amountInCents = Math.round(depositAmount * 100);
            
            // Initialize PayStack
            const handler = PaystackPop.setup({
                key: 'pk_test_7090654890c9b9e49ccd73414cf46791275afd28',
                email: email,
                amount: amountInCents,
                currency: 'GHS',
                ref: 'GCCB_' + Math.floor((Math.random() * 1000000000) + 1),
                callback: function(response) {
                    // Payment successful - submit the form
                    alert('Payment completed successfully! Reference: ' + response.reference);
                    
                    // Hide modal first
                    const paymentModal = bootstrap.Modal.getInstance(document.getElementById('paymentModal'));
                    paymentModal.hide();
                    
                    // Then submit the form
                    document.getElementById('depositForm').submit();
                },
                onClose: function() {
                    alert('Payment window closed. If you have any issues, please try again.');
                }
            });
            
            // Open PayStack - This should work without flickering now
            handler.openIframe();
        });

        // Prevent form submission when pressing enter in deposit amount field
        document.getElementById('initialDeposit').addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                document.getElementById('proceedToPayment').click();
            }
        });



        // Add this script for confetti effect
function createConfetti() {
    const container = document.getElementById('confettiContainer');
    if (!container) return;
    
    const colors = ['#FFD700', '#1B365D', '#28A745', '#DC3545', '#6F42C1'];
    
    for (let i = 0; i < 150; i++) {
        const confetti = document.createElement('div');
        confetti.style.position = 'fixed';
        confetti.style.width = Math.random() * 10 + 5 + 'px';
        confetti.style.height = Math.random() * 10 + 5 + 'px';
        confetti.style.backgroundColor = colors[Math.floor(Math.random() * colors.length)];
        confetti.style.borderRadius = Math.random() > 0.5 ? '50%' : '0';
        confetti.style.left = Math.random() * 100 + 'vw';
        confetti.style.top = '-10px';
        confetti.style.opacity = Math.random() + 0.5;
        confetti.style.transform = `rotate(${Math.random() * 360}deg)`;
        
        container.appendChild(confetti);
        
        const animation = confetti.animate([
            { transform: `translateY(0) rotate(0deg)`, opacity: 1 },
            { transform: `translateY(${window.innerHeight}px) rotate(${Math.random() * 360}deg)`, opacity: 0 }
        ], {
            duration: Math.random() * 3000 + 2000,
            easing: 'cubic-bezier(0.1, 0.8, 0.3, 1)',
            delay: Math.random() * 2000
        });
        
        animation.onfinish = () => confetti.remove();
    }
}

// Trigger confetti when success page loads
if (document.querySelector('.success-container')) {
    setTimeout(createConfetti, 500);
    setTimeout(createConfetti, 1500);
    setTimeout(createConfetti, 2500);
}
    </script>
</body>
</html>