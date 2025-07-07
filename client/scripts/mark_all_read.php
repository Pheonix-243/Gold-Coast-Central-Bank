<?php
require_once('../includes/auth.php');
require_once('../includes/notification.php');

$notification = new NotificationSystem($con);
$sql = "UPDATE notifications SET is_read = 1 
        WHERE account = ? AND is_read = 0";
$stmt = mysqli_prepare($con, $sql);
mysqli_stmt_bind_param($stmt, "s", $_SESSION['client_account']);
$success = mysqli_stmt_execute($stmt);

echo json_encode(['success' => $success]);
?>