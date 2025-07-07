<?php
require_once('../includes/auth.php');
require_once('../includes/notification.php');

$unreadOnly = isset($_GET['unread']) && $_GET['unread'] == '1';
$notification = new NotificationSystem($con);
$notifications = $notification->getNotifications($_SESSION['client_account'], 10, $unreadOnly);

header('Content-Type: application/json');
echo json_encode($notifications);
?>