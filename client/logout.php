<?php
/**
 * Secure Logout - Gold Coast Central Bank
 * Destroys session and redirects to login
 */
require_once '../Home/includes/security_logger.php';
require_once '../Home/config/database.php';
session_start();

// Log the logout event if user was logged in
if (isset($_SESSION['client_loggedin'])) {
    require_once 'config/database.php';
    require_once 'includes/security_logger.php';
    
    $logger = new SecurityLogger();
    $logger->logEvent('user_logout', [
        'account' => $_SESSION['client_account'] ?? 'unknown',
        'email' => $_SESSION['client_email'] ?? 'unknown',
        'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
    ]);
    
    // Update logout time in login history
    if (isset($_SESSION['login_id'])) {
        try {
            $conn = DatabaseConnection::getInstance()->getConnection();
            $stmt = $conn->prepare("
                UPDATE client_login_history 
                SET logout_time = NOW() 
                WHERE id = ?
            ");
            $stmt->execute([$_SESSION['login_id']]);
        } catch (Exception $e) {
            error_log("Logout history update failed: " . $e->getMessage());
            // Continue with logout even if history update fails
        }
    }
}

// Destroy all session data
$_SESSION = array();

// If it's desired to kill the session, also delete the session cookie
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(), 
        '', 
        time() - 42000,
        $params["path"], 
        $params["domain"],
        $params["secure"], 
        $params["httponly"]
    );
}

session_destroy();

// Redirect to login with logout message
header('Location: /gccb/Home/login.php?msg=You+have+been+securely+logged+out');
exit;
?>
