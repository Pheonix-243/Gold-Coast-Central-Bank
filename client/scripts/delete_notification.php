<?php
require_once('../includes/auth.php');
require_once('../includes/notification.php');

$data = json_decode(file_get_contents('php://input'), true);
$notification = new NotificationSystem($con);

if (isset($data['id'])) {
    $success = $notification->deleteNotification($data['id'], $_SESSION['client_account']);
    echo json_encode(['success' => $success]);
} else {
    echo json_encode(['success' => false]);
}
?>