<?php
require_once('../configs/db.php');

header('Content-Type: application/json');

if (!isset($_GET['account'])) {
    echo json_encode(['error' => 'Account number required']);
    exit;
}

$account = trim($_GET['account']);

$sql = "SELECT a.*, h.name, h.email 
        FROM accounts_info a 
        JOIN accountsholder h ON a.account = h.account 
        WHERE a.account = ? AND a.status = 'Active'";
$stmt = mysqli_prepare($con, $sql);
mysqli_stmt_bind_param($stmt, "s", $account);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

if (mysqli_num_rows($result) === 1) {
    $data = mysqli_fetch_assoc($result);
    echo json_encode([
        'name' => $data['name'],
        'email' => $data['email'],
        'account_type' => $data['account_type'],
        'status' => $data['status']
    ]);
} else {
    echo json_encode(['error' => 'Account not found or inactive']);
}
?>