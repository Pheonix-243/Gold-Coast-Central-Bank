<?php
require_once('../includes/auth.php');
require_once('../includes/notification.php');

// Fetch profile picture
$sql = "SELECT image FROM accountsholder WHERE account = ?";
$stmt = mysqli_prepare($con, $sql);
mysqli_stmt_bind_param($stmt, "s", $account);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$profilePic = mysqli_fetch_assoc($result)['image'];

// Get account info
$account = $_SESSION['client_account'];
$sql = "SELECT a.*, h.name 
        FROM accounts_info a
        JOIN accountsholder h ON a.account = h.account
        WHERE a.account = ?";
$stmt = mysqli_prepare($con, $sql);
mysqli_stmt_bind_param($stmt, "s", $account);
mysqli_stmt_execute($stmt);
$accountInfo = mysqli_fetch_assoc($stmt->get_result());

// Get security settings
$sql = "SELECT two_factor_enabled, last_login_device, last_login 
        FROM accounts_info 
        WHERE account = ?";
$stmt = mysqli_prepare($con, $sql);
mysqli_stmt_bind_param($stmt, "s", $account);
mysqli_stmt_execute($stmt);
$securitySettings = mysqli_fetch_assoc($stmt->get_result());

// Get trusted devices
$sql = "SELECT id, browser, os, device_type, ip_address, last_used 
        FROM device_fingerprints 
        WHERE account = ? 
        ORDER BY last_used DESC";
$stmt = mysqli_prepare($con, $sql);
mysqli_stmt_bind_param($stmt, "s", $account);
mysqli_stmt_execute($stmt);
$trustedDevices = mysqli_fetch_all($stmt->get_result(), MYSQLI_ASSOC);

// Get recent security logs
$sql = "SELECT event_type, ip_address, severity, created_at 
        FROM security_logs 
        WHERE session_id LIKE CONCAT(?, '%') 
        ORDER BY created_at DESC 
        LIMIT 10";
$stmt = mysqli_prepare($con, $sql);
$sessionId = session_id();
mysqli_stmt_bind_param($stmt, "s", $sessionId);
mysqli_stmt_execute($stmt);
$securityLogs = mysqli_fetch_all($stmt->get_result(), MYSQLI_ASSOC);

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['toggle_2fa'])) {
        // Get current status first
        $sql = "SELECT two_factor_enabled FROM accounts_info WHERE account = ?";
        $stmt = mysqli_prepare($con, $sql);
        mysqli_stmt_bind_param($stmt, "s", $account);
        mysqli_stmt_execute($stmt);
        $currentStatus = mysqli_fetch_assoc($stmt->get_result())['two_factor_enabled'];
        
        // Toggle the status (1 becomes 0, 0 becomes 1)
        $newStatus = $currentStatus ? 0 : 1;
        
        $sql = "UPDATE accounts_info SET two_factor_enabled = ? WHERE account = ?";
        $stmt = mysqli_prepare($con, $sql);
        mysqli_stmt_bind_param($stmt, "is", $newStatus, $account);
        
        if (mysqli_stmt_execute($stmt)) {
            $_SESSION['success_message'] = "Two-factor authentication " . ($newStatus ? "enabled" : "disabled") . " successfully.";
            // Refresh security settings
            $sql = "SELECT two_factor_enabled, last_login_device, last_login 
                    FROM accounts_info 
                    WHERE account = ?";
            $stmt = mysqli_prepare($con, $sql);
            mysqli_stmt_bind_param($stmt, "s", $account);
            mysqli_stmt_execute($stmt);
            $securitySettings = mysqli_fetch_assoc($stmt->get_result());
            
            // Redirect to avoid form resubmission
            header("Location: security.php");
            exit();
        } else {
            $_SESSION['error_message'] = "Failed to update two-factor authentication settings.";
        }
    }
    
    if (isset($_POST['revoke_device'])) {
        $deviceId = $_POST['device_id'];
        
        $sql = "DELETE FROM device_fingerprints WHERE id = ? AND account = ?";
        $stmt = mysqli_prepare($con, $sql);
        mysqli_stmt_bind_param($stmt, "is", $deviceId, $account);
        
        if (mysqli_stmt_execute($stmt)) {
            $_SESSION['success_message'] = "Device access revoked successfully.";
            // Refresh devices list
            $sql = "SELECT id, browser, os, device_type, ip_address, last_used 
                    FROM device_fingerprints 
                    WHERE account = ? 
                    ORDER BY last_used DESC";
            $stmt = mysqli_prepare($con, $sql);
            mysqli_stmt_bind_param($stmt, "s", $account);
            mysqli_stmt_execute($stmt);
            $trustedDevices = mysqli_fetch_all($stmt->get_result(), MYSQLI_ASSOC);
        } else {
            $_SESSION['error_message'] = "Failed to revoke device access.";
        }
    }
    
    if (isset($_POST['change_password'])) {
        $currentPassword = $_POST['current_password'];
        $newPassword = $_POST['new_password'];
        $confirmPassword = $_POST['confirm_password'];
        
        // Verify current password
        $sql = "SELECT password FROM accounts_info WHERE account = ?";
        $stmt = mysqli_prepare($con, $sql);
        mysqli_stmt_bind_param($stmt, "s", $account);
        mysqli_stmt_execute($stmt);
        $result = mysqli_fetch_assoc($stmt->get_result());
        
        if (password_verify($currentPassword, $result['password'])) {
            if ($newPassword === $confirmPassword) {
                if (strlen($newPassword) >= 8) {
                    $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
                    
                    $sql = "UPDATE accounts_info SET password = ? WHERE account = ?";
                    $stmt = mysqli_prepare($con, $sql);
                    mysqli_stmt_bind_param($stmt, "ss", $hashedPassword, $account);
                    
                    if (mysqli_stmt_execute($stmt)) {
                        $_SESSION['success_message'] = "Password changed successfully.";
                        
                        // Log security event
                        $sql = "INSERT INTO security_logs (event_type, ip_address, user_agent, session_id, severity, data) 
                                VALUES (?, ?, ?, ?, 'INFO', ?)";
                        $stmt = mysqli_prepare($con, $sql);
                        $eventType = "password_change";
                        $ipAddress = $_SERVER['REMOTE_ADDR'];
                        $userAgent = $_SERVER['HTTP_USER_AGENT'];
                        $sessionId = session_id();
                        $data = json_encode(['account' => $account]);
                        mysqli_stmt_bind_param($stmt, "sssss", $eventType, $ipAddress, $userAgent, $sessionId, $data);
                        mysqli_stmt_execute($stmt);
                    } else {
                        $_SESSION['error_message'] = "Failed to update password.";
                    }
                } else {
                    $_SESSION['error_message'] = "Password must be at least 8 characters long.";
                }
            } else {
                $_SESSION['error_message'] = "New passwords do not match.";
            }
        } else {
            $_SESSION['error_message'] = "Current password is incorrect.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gold Coast Central Bank - Security Settings</title>
    
    <meta name="description" content="Security settings for Gold Coast Central Bank">
    <meta name="theme-color" content="#0f172a">
    
    <!-- Favicons -->
    <link rel="apple-touch-icon" sizes="180x180" href="apple-touch-icon.png">
    <link rel="icon" type="image/png" sizes="32x32" href="favicon-32x32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="favicon-16x16.png">
    <link rel="manifest" href="site.webmanifest">
    
    <!-- Preload critical fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    
    <!-- Font Awesome Pro -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Google Fonts - Inter with optimized loading -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    
    <!-- Custom Premium CSS -->
    <link rel="stylesheet" href="../dashboard/style.css">
    
    <style>
        .security-section {
            background: var(--bg-white);
            border-radius: var(--radius-xl);
            padding: var(--space-lg);
            box-shadow: var(--shadow-md);
            margin-bottom: var(--space-lg);
            transition: var(--transition);
        }
        
        .security-section:hover {
            box-shadow: var(--shadow-lg);
            transform: translateY(-2px);
        }
        
        .security-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: var(--space-lg);
            padding-bottom: var(--space-md);
            border-bottom: 1px solid var(--border-light);
        }
        
        .security-header h3 {
            font-size: var(--font-size-xl);
            font-weight: 600;
            color: var(--text-primary);
        }
        
        .toggle-switch {
            position: relative;
            display: inline-block;
            width: 60px;
            height: 30px;
        }
        
        .toggle-switch input {
            opacity: 0;
            width: 0;
            height: 0;
        }
        
        .toggle-slider {
            position: absolute;
            cursor: pointer;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: var(--border-medium);
            transition: var(--transition);
            border-radius: var(--radius-full);
        }
        
        .toggle-slider:before {
            position: absolute;
            content: "";
            height: 22px;
            width: 22px;
            left: 4px;
            bottom: 4px;
            background-color: var(--bg-white);
            transition: var(--transition);
            border-radius: 50%;
        }
        
        input:checked + .toggle-slider {
            background-color: var(--success);
        }
        
        input:checked + .toggle-slider:before {
            transform: translateX(30px);
        }
        
        .device-item {
            display: flex;
            align-items: center;
            gap: var(--space-md);
            padding: var(--space-md);
            border-radius: var(--radius-lg);
            border: 1px solid var(--border-light);
            margin-bottom: var(--space-sm);
            transition: var(--transition);
        }
        
        .device-item:hover {
            border-color: var(--primary);
            box-shadow: var(--shadow-sm);
        }
        
        .device-icon {
            width: 40px;
            height: 40px;
            border-radius: var(--radius-lg);
            display: flex;
            align-items: center;
            justify-content: center;
            background: var(--bg-secondary);
            color: var(--primary);
            font-size: var(--font-size-lg);
        }
        
        .device-info {
            flex: 1;
        }
        
        .device-name {
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 2px;
        }
        
        .device-details {
            font-size: var(--font-size-xs);
            color: var(--text-secondary);
            margin-bottom: 2px;
        }
        
        .device-last-used {
            font-size: var(--font-size-xs);
            color: var(--text-muted);
        }
        
        .current-device {
            background: rgba(16, 185, 129, 0.05);
            border-color: rgba(16, 185, 129, 0.2);
        }
        
        .security-log-item {
            display: flex;
            align-items: center;
            gap: var(--space-md);
            padding: var(--space-sm);
            border-radius: var(--radius-md);
            margin-bottom: var(--space-xs);
            transition: var(--transition);
        }
        
        .security-log-item:hover {
            background: var(--bg-secondary);
        }
        
        .log-icon {
            width: 32px;
            height: 32px;
            border-radius: var(--radius-md);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: var(--font-size-sm);
        }
        
        .log-icon.info {
            background: rgba(59, 130, 246, 0.1);
            color: var(--info);
        }
        
        .log-icon.warning {
            background: rgba(245, 158, 11, 0.1);
            color: var(--warning);
        }
        
        .log-icon.error {
            background: rgba(239, 68, 68, 0.1);
            color: var(--danger);
        }
        
        .log-icon.critical {
            background: rgba(220, 38, 38, 0.1);
            color: #dc2626;
        }
        
        .log-content {
            flex: 1;
        }
        
        .log-event {
            font-weight: 500;
            color: var(--text-primary);
            margin-bottom: 2px;
        }
        
        .log-details {
            font-size: var(--font-size-xs);
            color: var(--text-secondary);
            margin-bottom: 2px;
        }
        
        .log-time {
            font-size: var(--font-size-xs);
            color: var(--text-muted);
        }
        
        .form-group {
            margin-bottom: var(--space-lg);
        }
        
        .form-label {
            display: block;
            font-weight: 500;
            color: var(--text-primary);
            margin-bottom: var(--space-sm);
        }
        
        .form-input {
            width: 100%;
            padding: 12px 16px;
            border: 1px solid var(--border-medium);
            border-radius: var(--radius-lg);
            background: var(--bg-white);
            color: var(--text-primary);
            font-size: var(--font-size-sm);
            transition: var(--transition);
        }
        
        .form-input:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(30, 64, 175, 0.1);
        }
        
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 12px 24px;
            border: none;
            border-radius: var(--radius-lg);
            font-size: var(--font-size-sm);
            font-weight: 500;
            cursor: pointer;
            transition: var(--transition);
            text-decoration: none;
        }
        
        .btn-primary {
            background: var(--primary);
            color: var(--text-white);
        }
        
        .btn-primary:hover {
            background: var(--primary-dark);
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
        }
        
        .btn-danger {
            background: var(--danger);
            color: var(--text-white);
        }
        
        .btn-danger:hover {
            background: #dc2626;
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
        }
        
        .btn-outline {
            background: transparent;
            color: var(--text-primary);
            border: 1px solid var(--border-medium);
        }
        
        .btn-outline:hover {
            background: var(--bg-secondary);
            border-color: var(--border-dark);
        }
        
        .alert {
            padding: var(--space-md);
            border-radius: var(--radius-lg);
            margin-bottom: var(--space-lg);
            display: flex;
            align-items: center;
            gap: var(--space-sm);
        }
        
        .alert-success {
            background: rgba(16, 185, 129, 0.1);
            color: var(--success);
            border: 1px solid rgba(16, 185, 129, 0.2);
        }
        
        .alert-error {
            background: rgba(239, 68, 68, 0.1);
            color: var(--danger);
            border: 1px solid rgba(239, 68, 68, 0.2);
        }
        
        .empty-state {
            text-align: center;
            padding: var(--space-2xl);
            color: var(--text-muted);
        }
        
        .empty-state i {
            font-size: 48px;
            margin-bottom: var(--space-md);
            opacity: 0.5;
        }
        
        .security-score {
            display: flex;
            align-items: center;
            gap: var(--space-md);
            padding: var(--space-lg);
            background: linear-gradient(135deg, var(--navy-primary) 0%, var(--navy-secondary) 100%);
            border-radius: var(--radius-xl);
            color: var(--text-white);
            margin-bottom: var(--space-lg);
        }
        
        .score-circle {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            background: conic-gradient(var(--success) 0% 85%, var(--border-medium) 85% 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
        }
        
        .score-value {
            font-size: var(--font-size-xl);
            font-weight: 700;
        }
        
        .score-text h3 {
            margin-bottom: 4px;
        }
        
        .score-text p {
            opacity: 0.8;
            margin: 0;
        }

        .security-features {
            margin-top: var(--space-md);
        }

        .feature-item {
            display: flex;
            align-items: center;
            gap: var(--space-sm);
            margin-bottom: var(--space-sm);
            color: var(--text-secondary);
        }

        .feature-item i {
            color: var(--success);
            width: 16px;
        }

        .badge.current {
            background: var(--success);
            color: white;
            padding: 4px 8px;
            border-radius: var(--radius-md);
            font-size: var(--font-size-xs);
            font-weight: 500;
        }

        .tips-list {
            display: flex;
            flex-direction: column;
            gap: var(--space-sm);
        }

        .tip-item {
            display: flex;
            align-items: center;
            gap: var(--space-sm);
            padding: var(--space-sm);
            border-radius: var(--radius-md);
            background: var(--bg-secondary);
        }

        .tip-item i {
            width: 16px;
        }

        .text-success {
            color: var(--success);
        }

        .quick-actions {
            display: flex;
            flex-direction: column;
            gap: var(--space-sm);
        }
    </style>
</head>

<body class="premium-dashboard">
    <div class="container">
        <nav class="sidebar" id="sidebar">
            <button id="btn_close">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="16" height="16">
                    <path fill="none" d="M0 0h24v24H0z" />
                    <path d="M12 10.586l4.95-4.95 1.414 1.414-4.95 4.95 4.95 4.95-1.414 1.414-4.95-4.95-4.95 4.95-1.414-1.414 4.95-4.95-4.95-4.95L7.05 5.636z" />
                </svg>
            </button>

            <div class="logo">
                <img src="../../gccb_logos/logo-transparent.svg" alt="Gold Coast Central Bank">
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
                        <i class="fas fa-money-bill"></i>
                    </div>
                    <div class="nav_link_text">Load</div>
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
                <a href="../pages/analytics.php" class="nav_link" aria-label="history">
                    <div class="nav_link_icon">
                        <i class="fas fa-chart-line"></i>
                    </div>
                    <div class="nav_link_text">Analytics</div>
                </a>

                <a href="../pages/security.php" class="nav_link active" aria-label="settings">
                    <div class="nav_link_icon">
                        <i class="fas fa-lock"></i>
                    </div>
                    <div class="nav_link_text">Security</div>
                </a>
            </div>

            <div class="profile">
                <div class="img_with_name">
                    <a href="../pages/profile.php">
                        <?php if (!empty($profilePic)): ?>
                            <img src="data:image/jpeg;base64,<?= base64_encode($profilePic) ?>" alt="Profile Picture">
                        <?php else: ?>
                            <img src="../images/default-profile.png" alt="Profile Picture">
                        <?php endif; ?>
                    </a>
                    <div class="profile_text">
                        <p class="name"><?= htmlspecialchars($_SESSION['client_name']) ?></p>
                        <p class="occupation"><?= htmlspecialchars($accountInfo['account_type'] ?? 'Account') ?></p>
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
                    <h1>Security Settings</h1>
                    <p class="welcome">Manage your account security and preferences</p>
                </div>

                <div class="topbar_icons">
                    <a href="#" aria-label="notifications" class="topbar_icon alert">
                        <i class="fas fa-bell"></i>
                    </a>
                </div>
            </div>

            <section class="content_section">
                <main>
                    <!-- Security Score -->
                    <div class="security-score">
                        <div class="score-circle">
                            <div class="score-value">85%</div>
                        </div>
                        <div class="score-text">
                            <h3>Security Score: Good</h3>
                            <p>Your account security is strong. Keep up the good practices!</p>
                        </div>
                    </div>

                    <!-- Display Messages -->
                    <?php if (isset($_SESSION['success_message'])): ?>
                        <div class="alert alert-success">
                            <i class="fas fa-check-circle"></i>
                            <?= $_SESSION['success_message'] ?>
                        </div>
                        <?php unset($_SESSION['success_message']); ?>
                    <?php endif; ?>
                    
                    <?php if (isset($_SESSION['error_message'])): ?>
                        <div class="alert alert-error">
                            <i class="fas fa-exclamation-circle"></i>
                            <?= $_SESSION['error_message'] ?>
                        </div>
                        <?php unset($_SESSION['error_message']); ?>
                    <?php endif; ?>

                    <!-- Two-Factor Authentication -->
                    <div class="security-section">
                        <div class="security-header">
                            <h3>Two-Factor Authentication</h3>
                            <form method="POST" class="toggle-form">
                                <input type="hidden" name="toggle_2fa" value="1">
                                <label class="toggle-switch">
                                    <input type="checkbox" <?= $securitySettings['two_factor_enabled'] ? 'checked' : '' ?> onchange="this.form.submit()">
                                    <span class="toggle-slider"></span>
                                </label>
                            </form>
                        </div>
                        <p>Add an extra layer of security to your account by requiring a verification code in addition to your password when signing in.</p>
                        <div class="security-features">
                            <div class="feature-item">
                                <i class="fas fa-mobile-alt"></i>
                                <span>Receive codes via SMS or authenticator app</span>
                            </div>
                            <div class="feature-item">
                                <i class="fas fa-shield-alt"></i>
                                <span>Protects against unauthorized access</span>
                            </div>
                        </div>
                    </div>

                    <!-- Password Management -->
                    <div class="security-section">
                        <div class="security-header">
                            <h3>Password Management</h3>
                        </div>
                        <form method="POST">
                            <input type="hidden" name="change_password" value="1">
                            <div class="form-group">
                                <label class="form-label" for="current_password">Current Password</label>
                                <input type="password" id="current_password" name="current_password" class="form-input" required>
                            </div>
                            <div class="form-group">
                                <label class="form-label" for="new_password">New Password</label>
                                <input type="password" id="new_password" name="new_password" class="form-input" required minlength="8">
                                <small>Password must be at least 8 characters long</small>
                            </div>
                            <div class="form-group">
                                <label class="form-label" for="confirm_password">Confirm New Password</label>
                                <input type="password" id="confirm_password" name="confirm_password" class="form-input" required>
                            </div>
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-key"></i>
                                Change Password
                            </button>
                        </form>
                    </div>

                    <!-- Trusted Devices -->
                    <div class="security-section">
                        <div class="security-header">
                            <h3>Trusted Devices</h3>
                        </div>
                        <p>These are devices that you've used to access your account. You can revoke access to any device you no longer use.</p>
                        
                        <?php if (!empty($trustedDevices)): ?>
                            <?php foreach ($trustedDevices as $device): ?>
                                <div class="device-item <?= $device['last_used'] === $securitySettings['last_login'] ? 'current-device' : '' ?>">
                                    <div class="device-icon">
                                        <?php if ($device['device_type'] === 'mobile'): ?>
                                            <i class="fas fa-mobile-alt"></i>
                                        <?php elseif ($device['device_type'] === 'tablet'): ?>
                                            <i class="fas fa-tablet-alt"></i>
                                        <?php else: ?>
                                            <i class="fas fa-desktop"></i>
                                        <?php endif; ?>
                                    </div>
                                    <div class="device-info">
                                        <div class="device-name"><?= htmlspecialchars($device['browser']) ?> on <?= htmlspecialchars($device['os']) ?></div>
                                        <div class="device-details"><?= htmlspecialchars($device['ip_address']) ?> • <?= ucfirst($device['device_type']) ?></div>
                                        <div class="device-last-used">Last used: <?= date('M j, Y g:i A', strtotime($device['last_used'])) ?></div>
                                    </div>
                                    <?php if ($device['last_used'] !== $securitySettings['last_login']): ?>
                                        <form method="POST">
                                            <input type="hidden" name="device_id" value="<?= $device['id'] ?>">
                                            <button type="submit" name="revoke_device" class="btn btn-outline">
                                                <i class="fas fa-times"></i>
                                                Revoke
                                            </button>
                                        </form>
                                    <?php else: ?>
                                        <span class="badge current">Current Device</span>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="empty-state">
                                <i class="fas fa-laptop"></i>
                                <p>No trusted devices found</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </main>

                <aside>
                    <!-- Security Activity -->
                    <div class="security-section">
                        <div class="security-header">
                            <h3>Recent Security Activity</h3>
                        </div>
                        
                        <?php if (!empty($securityLogs)): ?>
                            <?php foreach ($securityLogs as $log): ?>
                                <div class="security-log-item">
                                    <div class="log-icon <?= strtolower($log['severity']) ?>">
                                        <?php 
                                        switch($log['event_type']) {
                                            case 'login':
                                                echo '<i class="fas fa-sign-in-alt"></i>';
                                                break;
                                            case 'password_change':
                                                echo '<i class="fas fa-key"></i>';
                                                break;
                                            case 'failed_login':
                                                echo '<i class="fas fa-exclamation-triangle"></i>';
                                                break;
                                            default:
                                                echo '<i class="fas fa-info-circle"></i>';
                                        }
                                        ?>
                                    </div>
                                    <div class="log-content">
                                        <div class="log-event"><?= ucfirst(str_replace('_', ' ', $log['event_type'])) ?></div>
                                        <div class="log-details">IP: <?= htmlspecialchars($log['ip_address']) ?></div>
                                        <div class="log-time"><?= date('M j, g:i A', strtotime($log['created_at'])) ?></div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="empty-state">
                                <i class="fas fa-shield-alt"></i>
                                <p>No recent security activity</p>
                            </div>
                        <?php endif; ?>
                        
                        <div style="margin-top: var(--space-md);">
                            <a href="../pages/security-logs.php" class="btn btn-outline" style="width: 100%; text-align: center;">
                                View All Activity
                            </a>
                        </div>
                    </div>

                    <!-- Security Tips -->
                    <div class="security-section">
                        <div class="security-header">
                            <h3>Security Tips</h3>
                        </div>
                        <div class="tips-list">
                            <div class="tip-item">
                                <i class="fas fa-check-circle text-success"></i>
                                <span>Use a strong, unique password</span>
                            </div>
                            <div class="tip-item">
                                <i class="fas fa-check-circle text-success"></i>
                                <span>Enable two-factor authentication</span>
                            </div>
                            <div class="tip-item">
                                <i class="fas fa-check-circle text-success"></i>
                                <span>Regularly review trusted devices</span>
                            </div>
                            <div class="tip-item">
                                <i class="fas fa-check-circle text-success"></i>
                                <span>Be cautious of phishing attempts</span>
                            </div>
                            <div class="tip-item">
                                <i class="fas fa-check-circle text-success"></i>
                                <span>Keep your contact information updated</span>
                            </div>
                        </div>
                    </div>

                    <!-- Quick Actions -->
                    <div class="security-section">
                        <div class="security-header">
                            <h3>Quick Actions</h3>
                        </div>
                        <div class="quick-actions">
                            <a href="../pages/contact.php" class="btn btn-outline" style="width: 100%; margin-bottom: var(--space-sm);">
                                <i class="fas fa-flag"></i>
                                Report Suspicious Activity
                            </a>
                            <a href="../pages/help.php" class="btn btn-outline" style="width: 100%;">
                                <i class="fas fa-question-circle"></i>
                                Get Security Help
                            </a>
                        </div>
                    </div>
                </aside>
            </section>
        </section>
    </div>

    <!-- Custom JS -->
    <script src="../js/main.js"></script>
    <script>
        // Mobile sidebar toggle
        document.getElementById('menu_btn').addEventListener('click', function() {
            document.getElementById('sidebar').classList.toggle('show_sidebar');
        });

        document.getElementById('btn_close').addEventListener('click', function() {
            document.getElementById('sidebar').classList.remove('show_sidebar');
        });

        // Password strength indicator
        const newPassword = document.getElementById('new_password');
        const confirmPassword = document.getElementById('confirm_password');
        
        if (newPassword && confirmPassword) {
            newPassword.addEventListener('input', function() {
                const password = this.value;
                let strength = 0;
                
                // Length check
                if (password.length >= 8) strength += 25;
                
                // Contains lowercase
                if (/[a-z]/.test(password)) strength += 25;
                
                // Contains uppercase
                if (/[A-Z]/.test(password)) strength += 25;
                
                // Contains numbers or symbols
                if (/[0-9]/.test(password) || /[^A-Za-z0-9]/.test(password)) strength += 25;
                
                // Update security score visually if needed
                // This is a simplified version - you might want to implement a more visual indicator
            });
            
            confirmPassword.addEventListener('input', function() {
                if (this.value !== newPassword.value) {
                    this.style.borderColor = 'var(--danger)';
                } else {
                    this.style.borderColor = 'var(--success)';
                }
            });
        }
    </script>
</body>
</html>