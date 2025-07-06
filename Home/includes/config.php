<?php
// Configuration file for GCC Bank website
session_start();


// Site configuration
define('SITE_NAME', 'Gold Coast Central Bank');
define('SITE_URL', getenv('SITE_URL') ?: 'http://localhost:5000');

// Security settings
define('CSRF_TOKEN_NAME', 'csrf_token');

// Generate CSRF token if not exists
if (!isset($_SESSION[CSRF_TOKEN_NAME])) {
    $_SESSION[CSRF_TOKEN_NAME] = bin2hex(random_bytes(32));
}

// Function to generate CSRF token input
function csrf_token_input() {
    return '<input type="hidden" name="' . CSRF_TOKEN_NAME . '" value="' . $_SESSION[CSRF_TOKEN_NAME] . '">';
}

// Function to verify CSRF token
function verify_csrf_token($token) {
    return isset($_SESSION[CSRF_TOKEN_NAME]) && hash_equals($_SESSION[CSRF_TOKEN_NAME], $token);
}

// Function to sanitize input
function sanitize_input($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}

// Function to validate email
function validate_email($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL);
}

// Function to validate phone number (Ghana format)
function validate_ghana_phone($phone) {
    // Remove all non-digits
    $phone = preg_replace('/[^0-9]/', '', $phone);
    
    // Check if it's a valid Ghana phone number format
    if (preg_match('/^(0|233)?[2-9][0-9]{8}$/', $phone)) {
        return true;
    }
    return false;
}

// Error and success message handling
function set_message($type, $message) {
    $_SESSION['message'] = ['type' => $type, 'text' => $message];
}

function get_message() {
    if (isset($_SESSION['message'])) {
        $message = $_SESSION['message'];
        unset($_SESSION['message']);
        return $message;
    }
    return null;
}
?>
