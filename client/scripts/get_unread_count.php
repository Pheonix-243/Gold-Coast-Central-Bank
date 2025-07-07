<?php
require_once('../includes/auth.php');
require_once('../includes/notification.php');

$notification = new NotificationSystem($con);
$count = $notification->getUnreadCount($_SESSION['client_account']);

header('Content-Type: application/json');
echo json_encode(['count' => $count]);
?>