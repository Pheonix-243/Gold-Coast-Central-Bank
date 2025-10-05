<?php
require_once('../includes/auth.php');

// Fetch profile data
$account = $_SESSION['client_account'];
$sql = "SELECT * FROM accountsholder WHERE account = ?";
$stmt = mysqli_prepare($con, $sql);
mysqli_stmt_bind_param($stmt, "s", $account);
mysqli_stmt_execute($stmt);
$profile = mysqli_fetch_assoc($stmt->get_result());

// Fetch account info
$sql = "SELECT * FROM accounts_info WHERE account = ?";
$stmt = mysqli_prepare($con, $sql);
mysqli_stmt_bind_param($stmt, "s", $account);
mysqli_stmt_execute($stmt);
$accountInfo = mysqli_fetch_assoc($stmt->get_result());

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $errors = [];
    $success = false;
    
    // Get current values as defaults
    $name = trim($_POST['name'] ?? $profile['name']);
    $email = trim($_POST['email'] ?? $profile['email']);
    $contact = trim($_POST['contact'] ?? $profile['contect']);
    $postal = trim($_POST['postal'] ?? $profile['postal']);
    $city = trim($_POST['city'] ?? $profile['city']);
    $houseAddress = trim($_POST['houseaddress'] ?? $profile['houseaddress']);
    
    // Remove leading zero from contact if present
    $contact = ltrim($contact, '0');
    
    // Validation
    if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Valid email is required";
    }
    
    if (!empty($contact) && !preg_match('/^[0-9]{10,15}$/', $contact)) {
        $errors[] = "Valid contact number is required (10-15 digits)";
    }
    
    if (empty($errors)) {
        // Handle profile picture upload - SIMPLE VERSION
        $imageData = $profile['image'];
        if (!empty($_FILES['profile_picture']['tmp_name']) && $_FILES['profile_picture']['error'] === UPLOAD_ERR_OK) {
            $allowedTypes = ['image/jpeg', 'image/jpg', 'image/png'];
            $maxFileSize = 2 * 1024 * 1024; // 2MB
            
            // Simple file type check by extension
            $fileName = $_FILES['profile_picture']['name'];
            $fileExt = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
            $allowedExt = ['jpeg', 'jpg', 'png'];
            
            if (!in_array($fileExt, $allowedExt)) {
                $errors[] = "Only JPEG and PNG images are allowed";
            } elseif ($_FILES['profile_picture']['size'] > $maxFileSize) {
                $errors[] = "Image size must be less than 2MB";
            } else {
                // Simply read the file without processing
                $imageData = file_get_contents($_FILES['profile_picture']['tmp_name']);
            }
        }
        
        if (empty($errors)) {
            // Update profile in database
            $sql = "UPDATE accountsholder SET 
                    name = ?, 
                    email = ?, 
                    contect = ?, 
                    postal = ?, 
                    city = ?, 
                    houseaddress = ?, 
                    image = ?,
                    updated_at = CURRENT_TIMESTAMP
                    WHERE account = ?";
            
            $stmt = mysqli_prepare($con, $sql);
            mysqli_stmt_bind_param($stmt, "ssssssss", 
                $name, $email, $contact, $postal, $city, $houseAddress, $imageData, $account);
            
            if (mysqli_stmt_execute($stmt)) {
                $success = true;
                // Update session data
                $_SESSION['client_name'] = $name;
                // Refresh profile data
                $profile = array_merge($profile, [
                    'name' => $name,
                    'email' => $email,
                    'contect' => $contact,
                    'postal' => $postal,
                    'city' => $city,
                    'houseaddress' => $houseAddress,
                    'image' => $imageData
                ]);
            } else {
                $errors[] = "Failed to update profile: " . mysqli_error($con);
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gold Coast Central Bank - Profile Management</title>
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <!-- Custom CSS -->
    <link rel="stylesheet" href="../dashboard/style.css">
    <style>
        /* Enhanced Profile Styles */
        .profile-container {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            padding: 20px;
            width: 100%;
            margin: 0 auto;
            animation: fadeIn 0.6s ease-out;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .profile-header {
            margin-bottom: 30px;
        }

        .profile-header h1 {
            font-weight: 700;
            font-size: 36px;
            color: var(--text-primary);
            margin-bottom: 5px;
            background: linear-gradient(135deg, var(--text-primary) 0%, var(--primary) 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            animation: slideInFromLeft 0.8s ease-out;
        }

        @keyframes slideInFromLeft {
            from { opacity: 0; transform: translateX(-30px); }
            to { opacity: 1; transform: translateX(0); }
        }

        .profile-header .subtitle {
            color: var(--text-secondary);
            font-size: 16px;
            animation: slideInFromLeft 0.8s ease-out 0.1s both;
        }

        .profile-card {
            background: var(--bg-white);
            border-radius: 20px;
            padding: 30px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.08);
            margin-bottom: 30px;
            transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            border: 1px solid var(--border-light);
            position: relative;
            overflow: hidden;
        }

        .profile-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, var(--gold-primary), var(--primary));
        }

        .profile-card:hover {
            box-shadow: 0 20px 60px rgba(0,0,0,0.12);
            transform: translateY(-5px);
        }

        .profile-form .form-group {
            margin-bottom: 25px;
            position: relative;
        }

        .profile-form label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: var(--text-dark);
            font-size: 14px;
            transition: all 0.3s ease;
        }

        .profile-form .form-control {
            width: 100%;
            padding: 14px 16px;
            border: 2px solid var(--border-medium);
            border-radius: 12px;
            font-family: 'Inter', sans-serif;
            font-size: 15px;
            transition: all 0.3s ease;
            background: var(--bg-white);
            color: var(--text-primary);
        }

        .profile-form .form-control:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 4px rgba(30, 64, 175, 0.1);
            transform: translateY(-2px);
        }

        .profile-form .form-control[readonly] {
            background-color: var(--bg-secondary);
            cursor: not-allowed;
            color: var(--text-muted);
            border-color: var(--border-light);
        }

        .btn-profile {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: var(--text-white);
            border: none;
            padding: 14px 28px;
            border-radius: 25px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            box-shadow: 0 4px 15px rgba(30, 64, 175, 0.3);
            position: relative;
            overflow: hidden;
        }

        .btn-profile::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
            transition: left 0.5s;
        }

        .btn-profile:hover::before {
            left: 100%;
        }

        .btn-profile:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(30, 64, 175, 0.4);
        }

        .btn-profile:active {
            transform: translateY(-1px);
        }

        .profile-picture-container {
            display: flex;
            flex-direction: column;
            align-items: center;
            margin-bottom: 40px;
            position: relative;
        }

        .profile-picture-wrapper {
            position: relative;
            display: inline-block;
            border-radius: 50%;
            padding: 5px;
            background: linear-gradient(135deg, var(--gold-primary), var(--primary));
            animation: float 6s ease-in-out infinite;
        }

        @keyframes float {
            0%, 100% { transform: translateY(0px); }
            50% { transform: translateY(-10px); }
        }

        .profile-picture {
            width: 160px;
            height: 160px;
            border-radius: 50%;
            object-fit: cover;
            border: 4px solid var(--bg-white);
            box-shadow: 0 10px 30px rgba(0,0,0,0.15);
            transition: all 0.4s ease;
            display: block;
        }

        .profile-picture:hover {
            transform: scale(1.05) rotate(5deg);
            box-shadow: 0 15px 40px rgba(0,0,0,0.2);
        }

        .profile-picture-upload {
            display: flex;
            flex-direction: column;
            align-items: center;
            margin-top: 15px;
        }

        .profile-picture-upload input[type="file"] {
            display: none;
        }

        .upload-btn {
            background: linear-gradient(135deg, var(--gold-primary), var(--gold-secondary));
            color: var(--navy-primary);
            border: none;
            padding: 10px 20px;
            border-radius: 25px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 8px;
            box-shadow: 0 4px 15px rgba(212, 175, 55, 0.3);
        }

        .upload-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(212, 175, 55, 0.4);
        }

        .profile-row {
            display: flex;
            gap: 30px;
        }

        .profile-main {
            flex: 2;
        }

        .profile-sidebar {
            flex: 1;
        }

        .security-info {
            background: var(--bg-white);
            border-radius: 18px;
            padding: 25px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.08);
            transition: all 0.3s ease;
            border: 1px solid var(--border-light);
        }

        .security-info:hover {
            box-shadow: 0 15px 50px rgba(0,0,0,0.12);
            transform: translateY(-3px);
        }

        .security-item {
            display: flex;
            gap: 15px;
            margin-bottom: 20px;
            padding: 15px;
            border-radius: 12px;
            transition: all 0.3s ease;
            border-left: 4px solid transparent;
        }

        .security-item:hover {
            background: var(--bg-secondary);
            border-left-color: var(--gold-primary);
            transform: translateX(5px);
        }

        .security-item:last-child {
            margin-bottom: 0;
        }

        .security-item i {
            font-size: 24px;
            color: var(--gold-primary);
            margin-top: 3px;
            transition: all 0.3s ease;
        }

        .security-item:hover i {
            transform: scale(1.2);
        }

        .security-item h4 {
            font-size: 16px;
            color: var(--text-primary);
            margin-bottom: 5px;
            font-weight: 600;
        }

        .security-item p {
            font-size: 14px;
            color: var(--text-secondary);
        }

        .account-summary {
            background: linear-gradient(135deg, var(--navy-primary) 0%, var(--navy-secondary) 100%);
            color: white;
            border-radius: 20px;
            padding: 25px;
            margin-bottom: 25px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.15);
            position: relative;
            overflow: hidden;
            animation: slideInFromRight 0.8s ease-out;
        }

        @keyframes slideInFromRight {
            from { opacity: 0; transform: translateX(30px); }
            to { opacity: 1; transform: translateX(0); }
        }

        .account-summary::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -50%;
            width: 200px;
            height: 200px;
            background: radial-gradient(circle, rgba(212, 175, 55, 0.15) 0%, transparent 70%);
            border-radius: 50%;
            animation: rotate 20s linear infinite;
        }

        @keyframes rotate {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }

        .account-summary h3 {
            font-size: 18px;
            margin-bottom: 20px;
            color: white;
            font-weight: 600;
            position: relative;
            z-index: 2;
        }

        .summary-item {
            display: flex;
            gap: 15px;
            margin-bottom: 18px;
            align-items: center;
            position: relative;
            z-index: 2;
            transition: all 0.3s ease;
        }

        .summary-item:hover {
            transform: translateX(5px);
        }

        .summary-item:last-child {
            margin-bottom: 0;
        }

        .summary-item i {
            font-size: 20px;
            color: var(--gold-light);
            transition: all 0.3s ease;
        }

        .summary-item:hover i {
            transform: scale(1.2);
            color: var(--gold-primary);
        }

        .summary-item .label {
            font-size: 12px;
            color: rgba(255, 255, 255, 0.8);
            margin-bottom: 3px;
        }

        .summary-item .value {
            font-size: 14px;
            font-weight: 600;
            color: white;
        }

        .alert {
            padding: 16px 20px;
            border-radius: 12px;
            margin-bottom: 25px;
            font-weight: 500;
            animation: slideInDown 0.5s ease-out;
            border: none;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }

        @keyframes slideInDown {
            from { opacity: 0; transform: translateY(-20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .alert-success {
            background: linear-gradient(135deg, #10b981, #059669);
            color: white;
        }

        .alert-danger {
            background: linear-gradient(135deg, #ef4444, #dc2626);
            color: white;
        }

        .text-muted {
            color: var(--text-muted);
            font-size: 12px;
            display: block;
            margin-top: 8px;
            text-align: center;
        }

        .form-section {
            margin-bottom: 35px;
            padding-bottom: 25px;
            border-bottom: 1px solid var(--border-light);
            animation: fadeInUp 0.6s ease-out;
        }

        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .form-section:last-of-type {
            border-bottom: none;
            margin-bottom: 0;
            padding-bottom: 0;
        }

        .form-section-title {
            font-size: 20px;
            font-weight: 700;
            color: var(--text-primary);
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 12px;
            padding-bottom: 10px;
            border-bottom: 2px solid var(--gold-primary);
        }

        .form-section-title i {
            color: var(--gold-primary);
            font-size: 24px;
        }

        .profile-actions {
            display: flex;
            gap: 15px;
            margin-top: 40px;
            padding-top: 25px;
            border-top: 1px solid var(--border-light);
        }

        .btn-secondary {
            background: var(--bg-white);
            color: var(--text-primary);
            border: 2px solid var(--border-medium);
            padding: 14px 28px;
            border-radius: 25px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }

        .btn-secondary:hover {
            background: var(--bg-secondary);
            border-color: var(--primary);
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.1);
        }

        .input-with-icon {
            position: relative;
        }

        .input-with-icon i {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-muted);
            transition: all 0.3s ease;
        }

        .input-with-icon .form-control {
            padding-left: 45px;
        }

        .input-with-icon .form-control:focus + i {
            color: var(--primary);
        }

        @media (max-width: 992px) {
            .profile-row {
                flex-direction: column;
            }
        }

        @media (max-width: 768px) {
            .profile-header h1 {
                font-size: 28px;
            }
            
            .profile-card {
                padding: 20px;
                border-radius: 16px;
            }
            
            .profile-actions {
                flex-direction: column;
            }
            
            .profile-picture {
                width: 140px;
                height: 140px;
            }
            
            .form-section-title {
                font-size: 18px;
            }
        }

        /* Loading animation */
        .loading {
            display: inline-block;
            width: 20px;
            height: 20px;
            border: 3px solid rgba(255,255,255,.3);
            border-radius: 50%;
            border-top-color: #fff;
            animation: spin 1s ease-in-out infinite;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }
    </style>
</head>

<body class="light">
    <div class="container">
        <nav class="sidebar" id="sidebar">
            <button id="btn_close">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="16" height="16">
                    <path fill="none" d="M0 0h24v24H0z" />
                    <path d="M12 10.586l4.95-4.95 1.414 1.414-4.95 4.95 4.95 4.95-1.414 1.414-4.95-4.95-4.95 4.95-1.414-1.414 4.95-4.95-4.95-4.95L7.05 5.636z" />
                </svg>
            </button>

            <div class="logo">
                <img src="../../gccb_logos/logo-transparent.svg" alt="">
            </div>

            <div class="nav_links">
                <a href="../dashboard/" class="nav_link" aria-label="overview">
                    <div class="nav_link_icon">
                        <i class="fas fa-home"></i>
                    </div>
                    <div class="nav_link_text">Dashboard</div>
                </a>

                <a href="../transactions/deposit.php" class="nav_link" aria-label="deposit">
                    <div class="nav_link_icon">
                        <i class="fas fa-money-bill-wave"></i>
                    </div>
                    <div class="nav_link_text">Deposit</div>
                </a>

                <a href="../transactions/transfer.php" class="nav_link" aria-label="transfer">
                    <div class="nav_link_icon">
                        <i class="fas fa-exchange-alt"></i>
                    </div>
                    <div class="nav_link_text">Transfer</div>
                </a>

                <a href="../transactions/history.php" class="nav_link" aria-label="history">
                    <div class="nav_link_icon">
                        <i class="fas fa-history"></i>
                    </div>
                    <div class="nav_link_text">History</div>
                </a>

                <a href="../profile/view.php" class="nav_link active" aria-label="profile">
                    <div class="nav_link_icon">
                        <i class="fas fa-user"></i>
                    </div>
                    <div class="nav_link_text">Profile</div>
                </a>

                <a href="../settings/password.php" class="nav_link" aria-label="settings">
                    <div class="nav_link_icon">
                        <i class="fas fa-lock"></i>
                    </div>
                    <div class="nav_link_text">Security</div>
                </a>
            </div>

            <div class="profile">
                <div class="img_with_name">
                    <img src="../images/profile_pic3.webp" alt="">
                    <div class="profile_text">
                        <p class="name"><?= htmlspecialchars($_SESSION['client_name']) ?></p>
                        <p class="occupation"><?= htmlspecialchars($_SESSION['client_account_type'] ?? 'Account') ?></p>
                    </div>
                </div>
                <a href="../scripts/logout.php" aria-label="logout">
                    <i class="fas fa-sign-out-alt"></i>
                </a>
            </div>
        </nav>

        <section class="main_content">
            <div class="topbar">
                <button id="menu_btn">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="16" height="16">
                        <path fill="none" d="M0 0h24v24H0z" />
                        <path d="M3 4h18v2H3V4zm0 7h12v2H3v-2zm0 7h18v2H3v-2z" /></svg>
                </button>
                <div class="overview_text">
                    <h1>My Profile</h1>
                    <p class="welcome">Update your personal information and account details</p>
                </div>

            

                <div class="topbar_icons">
                    <a href="#" aria-label="search" class="topbar_icon">
                        <i class="fas fa-search"></i>
                    </a>
                    <a href="#" aria-label="notifications" class="topbar_icon alert">
                        <i class="fas fa-bell"></i>
                    </a>
                </div>
            </div>

            <div class="profile-container">
                <?php if (!empty($errors)): ?>
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-triangle"></i>
                        <?php foreach ($errors as $error): ?>
                            <p><?= htmlspecialchars($error) ?></p>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
                
                <?php if (isset($success) && $success): ?>
                    <div class="alert alert-success">
                        <i class="fas fa-check-circle"></i> Profile updated successfully!
                    </div>
                <?php endif; ?>
                
                <div class="profile-row">
                    <div class="profile-main">
                        <div class="profile-card">
                            <form method="POST" class="profile-form" enctype="multipart/form-data" id="profileForm">
                                <div class="profile-picture-container">
                                    <div class="profile-picture-wrapper">
                                        <?php if (!empty($profile['image'])): ?>
                                            <img src="data:image/jpeg;base64,<?= base64_encode($profile['image']) ?>" 
                                                 alt="Profile Picture" class="profile-picture" id="profilePicture">
                                        <?php else: ?>
                                            <img src="../images/default-profile.png" 
                                                 alt="Profile Picture" class="profile-picture" id="profilePicture">
                                        <?php endif; ?>
                                    </div>
                                    
                                    <div class="profile-picture-upload">
                                        <label for="profile_picture" class="upload-btn">
                                            <i class="fas fa-camera"></i> Change Photo
                                        </label>
                                        <input type="file" id="profile_picture" name="profile_picture" 
                                               accept="image/jpeg,image/png">
                                        <small class="text-muted">Max 2MB • JPEG or PNG</small>
                                    </div>
                                </div>
                                
                                <div class="form-section">
                                    <div class="form-section-title">
                                        <i class="fas fa-user-circle"></i>
                                        Personal Information
                                    </div>
                                    
                                    <div class="form-group">
                                        <label for="name">Full Name</label>
                                        <div class="input-with-icon">
                                            <input type="text" class="form-control" id="name" name="name" 
                                                   value="<?= htmlspecialchars($profile['name'] ?? '') ?>">
                                            <i class="fas fa-user"></i>
                                        </div>
                                    </div>
                                    
                                    <div class="form-group">
                                        <label for="email">Email Address</label>
                                        <div class="input-with-icon">
                                            <input type="email" class="form-control" id="email" name="email" 
                                                   value="<?= htmlspecialchars($profile['email'] ?? '') ?>">
                                            <i class="fas fa-envelope"></i>
                                        </div>
                                    </div>
                                    
                                    <div class="form-group">
                                        <label for="contact">Phone Number</label>
                                        <div class="input-with-icon">
                                            <input type="tel" class="form-control" id="contact" name="contact" 
                                                   value="<?= htmlspecialchars($profile['contect'] ?? '') ?>" 
                                                   pattern="[0-9]{10,15}" title="10-15 digit phone number">
                                            <i class="fas fa-phone"></i>
                                        </div>
                                        <small class="text-muted">Enter your phone number without leading zero</small>
                                    </div>
                                </div>
                                
                                <div class="form-section">
                                    <div class="form-section-title">
                                        <i class="fas fa-map-marker-alt"></i>
                                        Address Information
                                    </div>
                                    
                                    <div class="form-group">
                                        <label for="postal">Postal Code</label>
                                        <div class="input-with-icon">
                                            <input type="text" class="form-control" id="postal" name="postal" 
                                                   value="<?= htmlspecialchars($profile['postal'] ?? '') ?>">
                                            <i class="fas fa-mail-bulk"></i>
                                        </div>
                                    </div>
                                    
                                    <div class="form-group">
                                        <label for="city">City</label>
                                        <div class="input-with-icon">
                                            <input type="text" class="form-control" id="city" name="city" 
                                                   value="<?= htmlspecialchars($profile['city'] ?? '') ?>">
                                            <i class="fas fa-city"></i>
                                        </div>
                                    </div>
                                    
                                    <div class="form-group">
                                        <label for="houseaddress">House Address</label>
                                        <div class="input-with-icon">
                                            <textarea class="form-control" id="houseaddress" name="houseaddress" 
                                                      rows="3" style="padding-left: 45px;"><?= htmlspecialchars($profile['houseaddress'] ?? '') ?></textarea>
                                            <i class="fas fa-home"></i>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="profile-actions">
                                    <button type="submit" class="btn-profile" id="submitBtn">
                                        <i class="fas fa-save"></i> Save Changes
                                    </button>
                                    <button type="reset" class="btn-secondary">
                                        <i class="fas fa-undo"></i> Reset Changes
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                    
                    <div class="profile-sidebar">
                        <div class="account-summary">
                            <h3>Account Summary</h3>
                            <div class="summary-item">
                                <i class="fas fa-user"></i>
                                <div>
                                    <p class="label">Account Holder</p>
                                    <p class="value"><?= htmlspecialchars($profile['name'] ?? '') ?></p>
                                </div>
                            </div>
                            <div class="summary-item">
                                <i class="fas fa-id-card"></i>
                                <div>
                                    <p class="label">Account Number</p>
                                    <p class="value"><?= htmlspecialchars($account) ?></p>
                                </div>
                            </div>
                            <div class="summary-item">
                                <i class="fas fa-wallet"></i>
                                <div>
                                    <p class="label">Account Type</p>
                                    <p class="value"><?= htmlspecialchars($accountInfo['account_type'] ?? '') ?></p>
                                </div>
                            </div>
                            <div class="summary-item">
                                <i class="fas fa-calendar-alt"></i>
                                <div>
                                    <p class="label">Member Since</p>
                                    <p class="value"><?= date('M d, Y', strtotime($accountInfo['registerdate'] ?? '')) ?></p>
                                </div>
                            </div>
                        </div>
                        
                        <div class="security-info">
                            <div class="security-item">
                                <i class="fas fa-shield-alt"></i>
                                <div>
                                    <h4>Account Security</h4>
                                    <p>Your account is protected with bank-level security</p>
                                </div>
                            </div>
                            <div class="security-item">
                                <i class="fas fa-lock"></i>
                                <div>
                                    <h4>Last Login</h4>
                                    <p><?= !empty($accountInfo['last_login']) ? date('M d, Y H:i', strtotime($accountInfo['last_login'])) : 'Never' ?></p>
                                </div>
                            </div>
                            <div class="security-item">
                                <i class="fas fa-key"></i>
                                <div>
                                    <h4>Password</h4>
                                    <p>Last changed: <?= date('M d, Y', strtotime('-3 months')) ?></p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </div>

    <script>
        // Mobile sidebar toggle
        document.getElementById('menu_btn').addEventListener('click', function() {
            document.getElementById('sidebar').classList.toggle('show_sidebar');
        });

        document.getElementById('btn_close').addEventListener('click', function() {
            document.getElementById('sidebar').classList.remove('show_sidebar');
        });

        // Preview profile picture before upload
        document.getElementById('profile_picture').addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (file) {
                // Check file size
                if (file.size > 2 * 1024 * 1024) {
                    alert('File size must be less than 2MB');
                    this.value = '';
                    return;
                }
                
                // Check file type
                const validTypes = ['image/jpeg', 'image/jpg', 'image/png'];
                if (!validTypes.includes(file.type)) {
                    alert('Only JPEG and PNG images are allowed');
                    this.value = '';
                    return;
                }
                
                const reader = new FileReader();
                reader.onload = function(event) {
                    document.getElementById('profilePicture').src = event.target.result;
                }
                reader.readAsDataURL(file);
            }
        });

        // Form submission loading state
        document.getElementById('profileForm').addEventListener('submit', function() {
            const submitBtn = document.getElementById('submitBtn');
            submitBtn.innerHTML = '<span class="loading"></span> Saving...';
            submitBtn.disabled = true;
        });

        // Auto-remove leading zero from contact number
        document.getElementById('contact').addEventListener('input', function(e) {
            this.value = this.value.replace(/^0+/, '');
        });

        // Add subtle animations on scroll
        const observerOptions = {
            threshold: 0.1,
            rootMargin: '0px 0px -50px 0px'
        };

        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.style.animationPlayState = 'running';
                }
            });
        }, observerOptions);

        // Observe animated elements
        document.querySelectorAll('.profile-card, .account-summary, .security-info').forEach(el => {
            el.style.animationPlayState = 'paused';
            observer.observe(el);
        });
    </script>
</body>
</html>