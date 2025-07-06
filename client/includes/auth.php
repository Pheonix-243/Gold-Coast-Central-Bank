<?php
// client/includes/auth.php
session_start();

if (!isset($_SESSION['client_loggedin']) || $_SESSION['client_loggedin'] !== true) {
    header('Location: ../login.php');
    exit;
}

require_once('../../conn.php');

// Verify session data exists
if (!isset($_SESSION['client_account'])) {
    session_destroy();
    header('Location: ../login.php?msg=Session expired');
    exit;
}

// Get current account info
$account = $_SESSION['client_account'];
$sql = "SELECT a.balance, a.status, h.name, h.email, h.image 
        FROM accounts_info a
        JOIN accountsholder h ON a.account = h.account
        WHERE a.account = ?";
$stmt = mysqli_prepare($con, $sql);
mysqli_stmt_bind_param($stmt, "s", $account);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$client = mysqli_fetch_assoc($result);

if (!$client || $client['status'] != 'Active') {
    session_destroy();
    header('Location: ../login.php?msg=Account not found or inactive');
    exit;
}

// Update session with latest data
$_SESSION['client_balance'] = $client['balance'];
$_SESSION['client_name'] = $client['name'];
$_SESSION['client_email'] = $client['email'];
$_SESSION['client_image'] = $client['image'];