<?php
/**
 * Authentication and authorization functions
 */

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Database connection
require_once('conn.php');

/**
 * Check if user is logged in
 * @return bool True if logged in, false otherwise
 */
function isLoggedIn() {
    return isset($_SESSION['loggedin']) && $_SESSION['loggedin'] === true;
}

/**
 * Check if user has specific permission
 * @param string $requiredPermission The permission to check
 * @return bool True if user has permission, false otherwise
 */
function hasPermission($requiredPermission) {
    if (!isLoggedIn()) {
        return false;
    }

    // Admin has all permissions
    if ($_SESSION['type'] === 'admin') {
        return true;
    }

    // Check specific permissions
    switch ($requiredPermission) {
        case 'teller':
            return in_array($_SESSION['type'], ['teller', 'admin']);
        case 'manager':
            return in_array($_SESSION['type'], ['manager', 'admin']);
        case 'client':
            return in_array($_SESSION['type'], ['client', 'admin']);
        default:
            return false;
    }
}

/**
 * Redirect to login if not authenticated
 */
function requireAuth() {
    if (!isLoggedIn()) {
        $_SESSION['status'] = 'Please login to access this page';
        $_SESSION['code'] = 'error';
        header('Location: ../index.php');
        exit();
    }
}

/**
 * Redirect to login if user doesn't have required permission
 * @param string $requiredPermission The required permission level
 */
function requirePermission($requiredPermission) {
    requireAuth();
    
    if (!hasPermission($requiredPermission)) {
        $_SESSION['status'] = 'You do not have permission to access this page';
        $_SESSION['code'] = 'error';
        header('Location: ../dashboard.php');
        exit();
    }
}

/**
 * Get current user's ID
 * @return int|null User ID or null if not logged in
 */
function getUserId() {
    return isLoggedIn() ? $_SESSION['id'] : null;
}

/**
 * Get current user's role
 * @return string|null User role or null if not logged in
 */
function getUserRole() {
    return isLoggedIn() ? $_SESSION['type'] : null;
}

/**
 * Logout the current user
 */
function logout() {
    // Unset all session variables
    $_SESSION = array();

    // Destroy the session
    session_destroy();

    // Redirect to login page
    header('Location: ../index.php');
    exit();
}

/**
 * Check if password meets complexity requirements
 * @param string $password Password to check
 * @return bool True if password meets requirements
 */
function isPasswordValid($password) {
    // Minimum 8 characters, at least one letter and one number
    return preg_match('/^(?=.*[A-Za-z])(?=.*\d)[A-Za-z\d]{8,}$/', $password);
}

/**
 * Verify if user can access a specific account
 * @param string $accountNumber Account number to check
 * @return bool True if user has access
 */
function canAccessAccount($accountNumber) {
    global $conn;
    
    if (!isLoggedIn()) {
        return false;
    }

    // Admins can access all accounts
    if ($_SESSION['type'] === 'admin') {
        return true;
    }

    // For clients, check if they own the account
    if ($_SESSION['type'] === 'client') {
        $query = "SELECT 1 FROM accounts_info WHERE account = ? AND account_holder_id = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("si", $accountNumber, $_SESSION['id']);
        $stmt->execute();
        $result = $stmt->get_result();
        
        return $result->num_rows > 0;
    }

    // Tellers and managers can access any account
    return true;
}

/**
 * Record user activity in log
 * @param string $activity Description of the activity
 * @param string $details Additional details (optional)
 */
function logActivity($activity, $details = '') {
    global $conn;
    
    if (!isLoggedIn()) {
        return;
    }

    $query = "INSERT INTO activity_logs (user_id, activity, details, ip_address, user_agent) 
              VALUES (?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($query);
    
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    
    $stmt->bind_param("issss", $_SESSION['id'], $activity, $details, $ip, $userAgent);
    $stmt->execute();
}

/**
 * Check if user needs to change password (e.g., if it's expired)
 * @return bool True if password change is required
 */
function isPasswordChangeRequired() {
    if (!isLoggedIn()) {
        return false;
    }

    global $conn;
    
    $query = "SELECT password_changed_at FROM users WHERE id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $_SESSION['id']);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        return false;
    }

    $user = $result->fetch_assoc();
    $passwordChangedAt = new DateTime($user['password_changed_at']);
    $now = new DateTime();
    $interval = $now->diff($passwordChangedAt);

    // Require password change every 90 days
    return $interval->days > 90;
}
?>