<?php
if (!isset($_POST['submit'])) {
    header('Location: login.php');
    exit;
}

// Include database connection properly
require_once('configs/db.php');

// Check if connection exists
if (!$conn) {
    header('Location: login.php?msg=Database connection failed');
    exit;
}

$email = $_POST['email'];
$password = $_POST['password'];

// Using prepared statement to prevent SQL injection
$sql = "SELECT a.*, h.name, h.email 
        FROM accounts_info a
        JOIN accountsholder h ON a.account = h.account
        WHERE h.email = ?";

$stmt = mysqli_prepare($conn, $sql);
if (!$stmt) {
    header('Location: login.php?msg=Database error');
    exit;
}

mysqli_stmt_bind_param($stmt, "s", $email);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

if (!$result) {
    header('Location: login.php?msg=Database query failed');
    exit;
}

$data = mysqli_fetch_assoc($result);

if ($data) {
    if (password_verify($password, $data['password'])) {
        session_start();
        $_SESSION['AccNo'] = $data['account'];
        $_SESSION['Name'] = $data['name'];
        $_SESSION['Email'] = $data['email'];
        header('Location: dashboard/index.php');
        exit;
    } else {
        header('Location: login.php?msg=Invalid email or password');
        exit;
    }
} else {
    header('Location: login.php?msg=Account not found');
    exit;
}