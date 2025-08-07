<?php
require_once('../includes/auth.php');
require_once('../includes/device_intelligence.php');
require_once('../includes/notification.php');

$account = $_SESSION['client_account'];
$error = '';
$success = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $conn = DatabaseConnection::getInstance()->getConnection();
        
        // Toggle 2FA
        if (isset($_POST['toggle_2fa'])) {
            $newStatus = $_POST['two_factor_enabled'] ? 1 : 0;
            $stmt = $conn->prepare("UPDATE accounts_info SET two_factor_enabled = ? WHERE account = ?");
            $stmt->execute([$newStatus, $account]);
            
            // Log this action
            $logger = new SecurityLogger();
            $logger->logEvent('2fa_' . ($newStatus ? 'enabled' : 'disabled'), [
                'account' => $account,
                'ip' => $_SERVER['REMOTE_ADDR']
            ], 'INFO');
            
            $success = "Two-factor authentication " . ($newStatus ? "enabled" : "disabled");
        }
        
        // Update notification preferences
        if (isset($_POST['update_notifications'])) {
            $securityAlerts = isset($_POST['security_alerts']) ? 1 : 0;
            $loginNotifications = isset($_POST['login_notifications']) ? 1 : 0;
            
            $stmt = $conn->prepare("UPDATE accounts_info SET security_alerts = ?, login_notifications = ? WHERE account = ?");
            $stmt->execute([$securityAlerts, $loginNotifications, $account]);
            
            $success = "Notification preferences updated";
        }
        
        // Revoke device
        if (isset($_POST['revoke_device'])) {
            $deviceId = (int)$_POST['device_id'];
            $stmt = $conn->prepare("DELETE FROM device_fingerprints WHERE id = ? AND account = ?");
            $stmt->execute([$deviceId, $account]);
            
            if ($stmt->rowCount() > 0) {
                $logger = new SecurityLogger();
                $logger->logEvent('device_revoked', [
                    'account' => $account,
                    'device_id' => $deviceId
                ], 'WARNING');
                
                $success = "Device access revoked";
            }
        }
    } catch (Exception $e) {
        $error = "An error occurred: " . $e->getMessage();
    }
}

// Get current settings
$conn = DatabaseConnection::getInstance()->getConnection();
$stmt = $conn->prepare("SELECT two_factor_enabled, security_alerts, login_notifications FROM accounts_info WHERE account = ?");
$stmt->execute([$account]);
$settings = $stmt->fetch(PDO::FETCH_ASSOC);

// Get trusted devices
$stmt = $conn->prepare("
    SELECT id, browser, os, device_type, ip_address, ip_country, last_used 
    FROM device_fingerprints 
    WHERE account = ? 
    ORDER BY last_used DESC
");
$stmt->execute([$account]);
$devices = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get security logs
$stmt = $conn->prepare("
    SELECT event_type, severity, created_at, data 
    FROM security_logs 
    WHERE data LIKE ? 
    ORDER BY created_at DESC 
    LIMIT 50
");
$stmt->execute(['%"account":"' . $account . '"%']);
$logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Security Settings - Gold Coast Central Bank</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root {
            --primary: #1e40af;
            --danger: #dc2626;
            --warning: #d97706;
            --success: #059669;
        }
        
        .security-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 2rem;
        }
        
        .security-header {
            margin-bottom: 2rem;
        }
        
        .security-section {
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            padding: 1.5rem;
            margin-bottom: 2rem;
        }
        
        .section-title {
            font-size: 1.25rem;
            font-weight: 600;
            margin-bottom: 1.5rem;
            color: var(--primary);
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .toggle-switch {
            position: relative;
            display: inline-block;
            width: 50px;
            height: 24px;
        }
        
        .toggle-switch input {
            opacity: 0;
            width: 0;
            height: 0;
        }
        
        .slider {
            position: absolute;
            cursor: pointer;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: #ccc;
            transition: .4s;
            border-radius: 24px;
        }
        
        .slider:before {
            position: absolute;
            content: "";
            height: 16px;
            width: 16px;
            left: 4px;
            bottom: 4px;
            background-color: white;
            transition: .4s;
            border-radius: 50%;
        }
        
        input:checked + .slider {
            background-color: var(--primary);
        }
        
        input:checked + .slider:before {
            transform: translateX(26px);
        }
        
        .form-group {
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        
        .form-label {
            font-weight: 500;
        }
        
        .form-description {
            color: #6b7280;
            font-size: 0.875rem;
            margin-top: 0.25rem;
        }
        
        .device-card {
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            padding: 1rem;
            margin-bottom: 1rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .device-info {
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        
        .device-icon {
            font-size: 1.5rem;
            color: #6b7280;
        }
        
        .device-meta {
            display: flex;
            gap: 0.5rem;
            font-size: 0.875rem;
            color: #6b7280;
        }
        
        .device-meta span {
            display: flex;
            align-items: center;
            gap: 0.25rem;
        }
        
        .current-device {
            background-color: #f0fdf4;
            border-color: #86efac;
        }
        
        .btn {
            padding: 0.5rem 1rem;
            border-radius: 6px;
            font-weight: 500;
            cursor: pointer;
            border: none;
            transition: all 0.2s;
        }
        
        .btn-danger {
            background-color: var(--danger);
            color: white;
        }
        
        .btn-danger:hover {
            background-color: #b91c1c;
        }
        
        .log-entry {
            padding: 0.75rem 0;
            border-bottom: 1px solid #e5e7eb;
        }
        
        .log-entry:last-child {
            border-bottom: none;
        }
        
        .log-severity {
            display: inline-block;
            padding: 0.25rem 0.5rem;
            border-radius: 4px;
            font-size: 0.75rem;
            font-weight: 500;
            margin-right: 0.5rem;
        }
        
        .severity-info {
            background-color: #dbeafe;
            color: #1e40af;
        }
        
        .severity-warning {
            background-color: #fef3c7;
            color: #d97706;
        }
        
        .severity-error, .severity-critical {
            background-color: #fee2e2;
            color: #dc2626;
        }
        
        .log-time {
            color: #6b7280;
            font-size: 0.875rem;
        }
        
        .log-details {
            margin-top: 0.25rem;
            font-size: 0.875rem;
        }
        
        .alert {
            padding: 1rem;
            border-radius: 6px;
            margin-bottom: 1.5rem;
        }
        
        .alert-error {
            background-color: #fee2e2;
            color: #dc2626;
            border-left: 4px solid #dc2626;
        }
        
        .alert-success {
            background-color: #ecfdf5;
            color: #059669;
            border-left: 4px solid #059669;
        }
    </style>
</head>
<body>
    <div class="security-container">
        <div class="security-header">
            <h1><i class="fas fa-shield-alt"></i> Security Center</h1>
            <p>Manage your account security settings and preferences</p>
        </div>
        
        <?php if ($error): ?>
            <div class="alert alert-error">
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="alert alert-success">
                <?= htmlspecialchars($success) ?>
            </div>
        <?php endif; ?>
        
        <div class="security-section">
            <h2 class="section-title"><i class="fas fa-lock"></i> Two-Factor Authentication</h2>
            <form method="POST">
                <div class="form-group">
                    <div>
                        <label for="two_factor_enabled" class="form-label">Enable Two-Factor Authentication</label>
                        <p class="form-description">
                            Adds an extra layer of security by requiring a verification code during login
                        </p>
                    </div>
                    <label class="toggle-switch">
                        <input type="checkbox" name="two_factor_enabled" id="two_factor_enabled" 
                               <?= $settings['two_factor_enabled'] ? 'checked' : '' ?>>
                        <span class="slider"></span>
                    </label>
                </div>
                <button type="submit" name="toggle_2fa" class="btn btn-primary">Save Changes</button>
            </form>
        </div>
        
        <div class="security-section">
            <h2 class="section-title"><i class="fas fa-bell"></i> Notification Preferences</h2>
            <form method="POST">
                <div class="form-group">
                    <div>
                        <label for="security_alerts" class="form-label">Security Alerts</label>
                        <p class="form-description">
                            Receive notifications for important security events
                        </p>
                    </div>
                    <label class="toggle-switch">
                        <input type="checkbox" name="security_alerts" id="security_alerts" 
                               <?= $settings['security_alerts'] ? 'checked' : '' ?>>
                        <span class="slider"></span>
                    </label>
                </div>
                
                <div class="form-group">
                    <div>
                        <label for="login_notifications" class="form-label">Login Notifications</label>
                        <p class="form-description">
                            Get notified when your account is accessed from a new device
                        </p>
                    </div>
                    <label class="toggle-switch">
                        <input type="checkbox" name="login_notifications" id="login_notifications" 
                               <?= $settings['login_notifications'] ? 'checked' : '' ?>>
                        <span class="slider"></span>
                    </label>
                </div>
                <button type="submit" name="update_notifications" class="btn btn-primary">Save Preferences</button>
            </form>
        </div>
        
        <div class="security-section">
            <h2 class="section-title"><i class="fas fa-laptop"></i> Trusted Devices</h2>
            <p class="form-description">
                Devices that have access to your account. Revoke any you don't recognize.
            </p>
            
            <?php if (empty($devices)): ?>
                <p>No trusted devices found.</p>
            <?php else: ?>
                <?php 
                $currentDevice = (new DeviceIntelligence())->getDeviceFingerprint();
                $currentFingerprint = $currentDevice['fingerprint_hash'];
                ?>
                
                <?php foreach ($devices as $device): ?>
                    <div class="device-card <?= ($device['fingerprint_hash'] === $currentFingerprint) ? 'current-device' : '' ?>">
                        <div class="device-info">
                            <div class="device-icon">
                                <?php switch($device['device_type']): 
                                    case 'mobile': ?>
                                        <i class="fas fa-mobile-alt"></i>
                                        <?php break; ?>
                                    <?php case 'tablet': ?>
                                        <i class="fas fa-tablet-alt"></i>
                                        <?php break; ?>
                                    <?php default: ?>
                                        <i class="fas fa-laptop"></i>
                                <?php endswitch; ?>
                            </div>
                            <div>
                                <strong><?= htmlspecialchars($device['browser']) ?> on <?= htmlspecialchars($device['os']) ?></strong>
                                <div class="device-meta">
                                    <span title="IP Address"><i class="fas fa-globe"></i> <?= htmlspecialchars($device['ip_address']) ?></span>
                                    <?php if ($device['ip_country']): ?>
                                        <span title="Country"><i class="fas fa-flag"></i> <?= htmlspecialchars($device['ip_country']) ?></span>
                                    <?php endif; ?>
                                    <span title="Last Used"><i class="far fa-clock"></i> <?= date('M j, Y g:i A', strtotime($device['last_used'])) ?></span>
                                </div>
                            </div>
                        </div>
                        
                        <?php if ($device['fingerprint_hash'] !== $currentFingerprint): ?>
                            <form method="POST" onsubmit="return confirm('Revoke access for this device?');">
                                <input type="hidden" name="device_id" value="<?= $device['id'] ?>">
                                <button type="submit" name="revoke_device" class="btn btn-danger">Revoke</button>
                            </form>
                        <?php else: ?>
                            <span class="badge">Current Device</span>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
        
        <div class="security-section">
            <h2 class="section-title"><i class="fas fa-history"></i> Security History</h2>
            <p class="form-description">
                Recent security events related to your account
            </p>
            
            <?php if (empty($logs)): ?>
                <p>No security events found.</p>
            <?php else: ?>
                <div class="log-list">
                    <?php foreach ($logs as $log): 
                        $data = json_decode($log['data'], true);
                        ?>
                        <div class="log-entry">
                            <div>
                                <span class="log-severity severity-<?= strtolower($log['severity']) ?>">
                                    <?= $log['severity'] ?>
                                </span>
                                <strong><?= ucwords(str_replace('_', ' ', $log['event_type'])) ?></strong>
                                <span class="log-time">
                                    <?= date('M j, Y g:i A', strtotime($log['created_at'])) ?>
                                </span>
                            </div>
                            <div class="log-details">
                                <?php if (!empty($data)): ?>
                                    <?php foreach ($data as $key => $value): 
                                        if (is_array($value)) continue;
                                        ?>
                                        <?= ucfirst($key) ?>: <strong><?= htmlspecialchars($value) ?></strong>
                                        <?php if (!end($data) === $value): ?> • <?php endif; ?>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>