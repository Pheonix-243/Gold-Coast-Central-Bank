<?php
require_once('../includes/auth.php');

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['confirm'])) {
    header('Location: transfer.php');
    exit;
}

// Check if transfer data exists in session
if (!isset($_SESSION['transfer_data'])) {
    $_SESSION['error'] = "Transfer session expired. Please start over.";
    header('Location: transfer.php');
    exit;
}

$transferData = $_SESSION['transfer_data'];
unset($_SESSION['transfer_data']); // Clear session data immediately

// Extract all data
$senderAccount = $transferData['senderAccount'];
$senderName = $transferData['senderName'];
$recipientAccount = $transferData['recipientAccount'];
$recipientName = $transferData['recipientName'];
$amount = (float)$transferData['amount'];
$description = $transferData['description'];
$transferType = $transferData['transferType'];

// Re-validate everything (security measure)
if ($amount < 100 || $amount > 100000) {
    $_SESSION['error'] = "Invalid amount";
    header('Location: transfer.php');
    exit;
}

if ($_SESSION['client_balance'] < $amount) {
    $_SESSION['error'] = "Insufficient balance for this transfer";
    header('Location: transfer.php');
    exit;
}

// For internal transfers, verify recipient account
if ($transferType === 'internal') {
    $sql = "SELECT 1 FROM accounts_info WHERE account = ? AND status = 'Active'";
    $stmt = mysqli_prepare($con, $sql);
    mysqli_stmt_bind_param($stmt, "s", $recipientAccount);
    mysqli_stmt_execute($stmt);

    if (mysqli_num_rows(mysqli_stmt_get_result($stmt)) === 0) {
        $_SESSION['error'] = "Recipient account no longer active";
        header('Location: transfer.php');
        exit;
    }
}

$currentDateTime = date('Y-m-d H:i:s');
list($dt, $tm) = explode(' ', $currentDateTime);
$reference = 'TRF-' . strtoupper(uniqid()); // Keep original format
$status = 'completed';

// Start DB transaction
mysqli_begin_transaction($con);

try {
    // 1. Deduct from sender
    $sql = "UPDATE accounts_info SET balance = balance - ? WHERE account = ?";
    $stmt = mysqli_prepare($con, $sql);
    mysqli_stmt_bind_param($stmt, "is", $amount, $senderAccount);
    mysqli_stmt_execute($stmt);

    // For internal transfers, credit the recipient
    if ($transferType === 'internal') {
        // 2. Add to recipient
        $sql = "UPDATE accounts_info SET balance = balance + ? WHERE account = ?";
        $stmt = mysqli_prepare($con, $sql);
        mysqli_stmt_bind_param($stmt, "is", $amount, $recipientAccount);
        mysqli_stmt_execute($stmt);
    }

    // 3. Sender's record (Transfer)
    $senderType = 1; // Transfer
    
    $sql = "INSERT INTO account_history 
            (account, sender, s_name, reciever, r_name, type, amount, reference_number, status, description, dt, tm)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    $stmt = mysqli_prepare($con, $sql);
    
    mysqli_stmt_bind_param($stmt, "sssssiisssss", 
        $senderAccount,
        $senderAccount,
        $senderName,
        $recipientAccount,
        $recipientName,
        $senderType,
        $amount,
        $reference,
        $status,
        $description, // Keep original description without type prefix
        $dt,
        $tm
    );
    mysqli_stmt_execute($stmt);

    // For internal transfers, create receiver's record
    if ($transferType === 'internal') {
        $receiverType = 2; // Payment Received
        $sql = "INSERT INTO account_history 
                (account, sender, s_name, reciever, r_name, type, amount, reference_number, status, description, dt, tm)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = mysqli_prepare($con, $sql);
        
        mysqli_stmt_bind_param($stmt, "sssssiisssss",
            $recipientAccount,
            $senderAccount,
            $senderName,
            $recipientAccount,
            $recipientName,
            $receiverType,
            $amount,
            $reference,
            $status,
            $description, // Keep original description without type prefix
            $dt,
            $tm
        );
        mysqli_stmt_execute($stmt);
    }

    // Commit transaction
    mysqli_commit($con);

    // Update session balance
    $_SESSION['client_balance'] -= $amount;

    $_SESSION['success'] = "Transfer of GHC" . number_format($amount, 2) . " to $recipientName ($recipientAccount) was successful!";
    header('Location: ../dashboard/');
    exit;

} catch (Exception $e) {
    mysqli_rollback($con);
    $_SESSION['error'] = "Transfer failed: " . $e->getMessage();
    header('Location: transfer.php');
    exit;
}
?>