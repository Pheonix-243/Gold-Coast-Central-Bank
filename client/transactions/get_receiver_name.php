<?php
require_once('../includes/conn.php');
header('Content-Type: application/json');

// Validate input
if (!isset($_GET['account']) || empty(trim($_GET['account']))) {
    http_response_code(400);
    echo json_encode(['error' => 'Account number is required']);
    exit;
}

$account = trim($_GET['account']);

// Prepare statement to prevent SQL injection
$sql = "SELECT h.name 
        FROM accountsholder h 
        JOIN accounts_info a ON h.account = a.account 
        WHERE h.account = ? AND a.status = 'Active' 
        LIMIT 1";

$stmt = mysqli_prepare($con, $sql);
if (!$stmt) {
    error_log("Prepare failed: " . mysqli_error($con));
    http_response_code(500);
    echo json_encode(['error' => 'Database error']);
    exit;
}

mysqli_stmt_bind_param($stmt, "s", $account);
if (!mysqli_stmt_execute($stmt)) {
    error_log("Execute failed: " . mysqli_stmt_error($stmt));
    http_response_code(500);
    echo json_encode(['error' => 'Database error']);
    mysqli_stmt_close($stmt);
    exit;
}

$result = mysqli_stmt_get_result($stmt);

if ($row = mysqli_fetch_assoc($result)) {
    echo json_encode(['name' => $row['name']]);
} else {
    http_response_code(404);
    echo json_encode(['error' => 'Account not found or inactive']);
}

mysqli_stmt_close($stmt);
mysqli_close($con);
?>