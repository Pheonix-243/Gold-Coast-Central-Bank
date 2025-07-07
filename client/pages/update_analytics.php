<?php
require_once('../includes/auth.php');
require_once('../../conn.php');

header('Content-Type: application/json');

if (!isset($_SESSION['client_account'])) {
    echo json_encode(['status' => 'error', 'message' => 'Not authenticated']);
    exit;
}

$account = $_SESSION['client_account'];

try {
    // Get updated account summary
    $sql = "SELECT 
                balance,
                (SELECT COUNT(*) FROM account_history WHERE account = ?) AS total_transactions,
                (SELECT IFNULL(SUM(amount),0) FROM account_history WHERE account = ? AND sender = ?) AS total_spent,
                (SELECT IFNULL(SUM(amount),0) FROM account_history WHERE account = ? AND reciever = ? AND sender != ?) AS total_received
            FROM accounts_info WHERE account = ?";
    
    $stmt = $con->prepare($sql);
    $stmt->bind_param("ssssss", $account, $account, $account, $account, $account, $account);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    echo json_encode([
        'status' => 'success',
        'data' => $result
    ]);
} catch (Exception $e) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}