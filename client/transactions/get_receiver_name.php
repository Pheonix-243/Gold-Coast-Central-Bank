<?php
require_once('../includes/db.php');
header('Content-Type: application/json');

$account = isset($_GET['account']) ? trim($_GET['account']) : '';

if (empty($account)) {
    echo json_encode(['error' => 'Account number is required']);
    exit;
}

$sql = "SELECT h.name FROM accountsholder h 
        JOIN accounts_info a ON h.account = a.account 
        WHERE h.account = ? AND a.status = 'Active'";
$stmt = mysqli_prepare($con, $sql);
mysqli_stmt_bind_param($stmt, "s", $account);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

if ($row = mysqli_fetch_assoc($result)) {
    echo json_encode(['name' => $row['name']]);
} else {
    echo json_encode(['error' => 'Account not found or inactive']);
}
?>