<?php
// Remove session_start() from here since it's already in auth.php
require_once('../includes/auth.php');
require_once('../includes/conn.php');
require_once('../includes/notification.php');
require_once('../includes/CategorizationEngine.php'); // Add this line

// Security headers
header("X-Frame-Options: DENY");
header("X-Content-Type-Options: nosniff");
header("X-XSS-Protection: 1; mode=block");
header("Referrer-Policy: strict-origin-when-cross-origin");

// Check if user is logged in
if (!isset($_SESSION['client_account'])) {
    $_SESSION['error'] = "Session expired. Please login again";
    header('Location: ../auth/login.php');
    exit;
}

// Validate request method
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $_SESSION['error'] = "Invalid request method";
    header('Location: transfer.php');
    exit;
}

// Get transfer data from session
if (!isset($_SESSION['transfer_data']) || !isset($_SESSION['transfer_fees'])) {
    $_SESSION['error'] = "Transfer data not found. Please start over.";
    header('Location: transfer.php');
    exit;
}

$transferData = $_SESSION['transfer_data'];
$feesData = $_SESSION['transfer_fees'];

$transferType = $transferData['type'];
$senderAccount = $_SESSION['client_account'];
$senderName = $_SESSION['client_name'];
$amount = (float)$transferData['amount'];
$description = $transferData['description'] ?? '';
$fee = $feesData['fee'];
$tax = $feesData['tax'];
$totalDebit = $feesData['total_debit'];
$netAmount = $feesData['net_amount'];

// Get recipient details based on transfer type
switch ($transferType) {
    case 'internal':
        $recipientAccount = $transferData['internal_account'];
        $recipientName = $transferData['internal_name'];
        break;
    case 'mobile':
        $recipientAccount = $transferData['mobile_number'];
        $recipientName = $transferData['mobile_name'];
        break;
    case 'bank':
        $recipientAccount = $transferData['bank_account'];
        $recipientName = $transferData['bank_name'];
        break;
    case 'international':
        $recipientAccount = $transferData['intl_account'];
        $recipientName = $transferData['intl_name'];
        break;
    default:
        $recipientAccount = '';
        $recipientName = '';
        break;
}

// Verify sender has sufficient balance (double-check)
if ($_SESSION['client_balance'] < $totalDebit) {
    $_SESSION['error'] = "Insufficient balance for this transfer";
    header('Location: transfer.php');
    exit;
}


$categorizationEngine = new CategorizationEngine($con);

// Handle categorization PROPERLY - FIXED VERSION
$categoryId = $transferData['category_id'] ?? null;
$categorySource = null;
$categoryConfidence = null;

error_log("Initial category from form: " . ($categoryId ?? 'NULL'));

// If user manually selected a category
if (!empty($categoryId)) {
    $categorySource = 'manual';
    $categoryConfidence = 1.0;
    error_log("Using MANUAL category: ID {$categoryId}");
} 
// Otherwise try auto-categorization
else {
    error_log("No manual category, attempting auto-categorization...");
    
    $autoCategory = $categorizationEngine->autoCategorize(
        $description, 
        $transferType, 
        $recipientName, 
        $amount
    );
    
    if ($autoCategory) {
        $categoryId = $autoCategory['category_id'];
        $categorySource = 'auto';
        $categoryConfidence = $autoCategory['confidence'];
        error_log("Auto-categorization SUCCESS: {$autoCategory['category_name']} (ID: {$categoryId}, Confidence: {$categoryConfidence})");
    } else {
        error_log("Auto-categorization FAILED: No matches found");
        // Leave everything as null - uncategorized transaction
    }
}

// DEBUG: Log what we're about to save
error_log("Final categorization - ID: " . ($categoryId ?? 'NULL') . ", Source: " . ($categorySource ?? 'NULL') . ", Confidence: " . ($categoryConfidence ?? 'NULL'));

// Generate reference and timestamps
$currentDateTime = date('Y-m-d H:i:s');
$dt = date('Y-m-d'); // Proper date format for database
$tm = date('H:i:s'); // Proper time format
$reference = 'TRF-' . strtoupper(uniqid());
$status = 'completed';

// Start transaction
mysqli_begin_transaction($con);

try {
    // 1. Deduct from sender (amount + fee + tax)
    $sql = "UPDATE accounts_info SET balance = balance - ? WHERE account = ?";
    $stmt = mysqli_prepare($con, $sql);
    if (!$stmt) {
        throw new Exception("Failed to prepare sender balance update: " . mysqli_error($con));
    }
    
    mysqli_stmt_bind_param($stmt, "ds", $totalDebit, $senderAccount);
    if (!mysqli_stmt_execute($stmt)) {
        throw new Exception("Failed to update sender balance: " . mysqli_stmt_error($stmt));
    }
    
    if (mysqli_stmt_affected_rows($stmt) !== 1) {
        throw new Exception("No rows affected when updating sender balance");
    }
    mysqli_stmt_close($stmt);

    // For internal transfers, credit the recipient
    if ($transferType === 'internal') {
        $sql = "UPDATE accounts_info SET balance = balance + ? WHERE account = ?";
        $stmt = mysqli_prepare($con, $sql);
        if (!$stmt) {
            throw new Exception("Failed to prepare recipient balance update: " . mysqli_error($con));
        }
        
        mysqli_stmt_bind_param($stmt, "ds", $amount, $recipientAccount);
        if (!mysqli_stmt_execute($stmt)) {
            throw new Exception("Failed to update recipient balance: " . mysqli_stmt_error($stmt));
        }
        
        if (mysqli_stmt_affected_rows($stmt) !== 1) {
            throw new Exception("No rows affected when updating recipient balance");
        }
        mysqli_stmt_close($stmt);
    }

    // Record sender transaction with fee and tax included
    $senderType = 1; // Transfer
    $balanceAfter = $_SESSION['client_balance'] - $totalDebit;
    
    // Updated SQL to include category fields
    $sql = "INSERT INTO account_history 
            (account, sender, s_name, receiver, r_name, type, amount, dt, tm, 
             balance_after, description, reference_number, status, fee, tax,
             category_id, category_source, category_confidence)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    $stmt = mysqli_prepare($con, $sql);
    if (!$stmt) {
        throw new Exception("Failed to prepare transaction history insert: " . mysqli_error($con));
    }
    
    // FIXED: Use individual variables instead of passing by reference
    // mysqli_stmt_bind_param($stmt, "sssssisssdsssddisd", 
    //     $senderAccount,
    //     $senderAccount,
    //     $senderName,
    //     $recipientAccount,
    //     $recipientName,
    //     $senderType,
    //     $amount,
    //     $dt,
    //     $tm,
    //     $balanceAfter,
    //     $description,
    //     $reference,
    //     $status,
    //     $fee,
    //     $tax,
    //     $categoryId,
    //     $categorySource,
    //     $categoryConfidence
    // );


    // In transfer_process.php - FIX THE FORMAT STRING
mysqli_stmt_bind_param($stmt, "sssssisssdsssddisd", 
    $senderAccount,
    $senderAccount,
    $senderName,
    $recipientAccount,
    $recipientName,
    $senderType,
    $amount,
    $dt,
    $tm,
    $balanceAfter,
    $description,
    $reference,
    $status,
    $fee,
    $tax,
    $categoryId,        // i - integer
    $categorySource,    // s - string (FIXED from 'd')
    $categoryConfidence // d - double (FIXED from 's')
);
    
    if (!mysqli_stmt_execute($stmt)) {
        throw new Exception("Failed to record transaction: " . mysqli_stmt_error($stmt));
    }
    mysqli_stmt_close($stmt);

    // For internal transfers, record recipient transaction
    if ($transferType === 'internal') {
        $receiverType = 2; // Payment Received
        
        // Get recipient's new balance
        $sql = "SELECT balance FROM accounts_info WHERE account = ? LIMIT 1";
        $stmt = mysqli_prepare($con, $sql);
        if (!$stmt) {
            throw new Exception("Failed to prepare recipient balance query: " . mysqli_error($con));
        }
        
        mysqli_stmt_bind_param($stmt, "s", $recipientAccount);
        if (!mysqli_stmt_execute($stmt)) {
            throw new Exception("Failed to get recipient balance: " . mysqli_stmt_error($stmt));
        }
        
        mysqli_stmt_bind_result($stmt, $recipientBalance);
        if (!mysqli_stmt_fetch($stmt)) {
            throw new Exception("Failed to fetch recipient balance");
        }
        mysqli_stmt_close($stmt);
        
        // Updated SQL for recipient transaction (no fees/tax for recipient)
        $sql = "INSERT INTO account_history 
                (account, sender, s_name, receiver, r_name, type, amount, dt, tm, 
                 balance_after, description, reference_number, status, fee, tax,
                 category_id, category_source, category_confidence)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = mysqli_prepare($con, $sql);
        if (!$stmt) {
            throw new Exception("Failed to prepare recipient history insert: " . mysqli_error($con));
        }
        
        // For recipient, fee and tax are 0 since they're not paying it
        $recipientFee = 0;
        $recipientTax = 0;
        $recipientCategoryId = null;
        $recipientCategorySource = null;
        $recipientCategoryConfidence = null;
        
        mysqli_stmt_bind_param($stmt, "sssssisssdsssddids", 
            $recipientAccount,
            $senderAccount,
            $senderName,
            $recipientAccount,
            $recipientName,
            $receiverType,
            $amount,
            $dt,
            $tm,
            $recipientBalance,
            $description,
            $reference,
            $status,
            $recipientFee,
            $recipientTax,
            $recipientCategoryId,
            $recipientCategorySource,
            $recipientCategoryConfidence
        );
        
        if (!mysqli_stmt_execute($stmt)) {
            throw new Exception("Failed to record recipient transaction: " . mysqli_stmt_error($stmt));
        }
        mysqli_stmt_close($stmt);
    }

    // Commit transaction
    mysqli_commit($con);

    // Update session balance
    $_SESSION['client_balance'] = $balanceAfter;

    // Send notifications
    $notification = new NotificationSystem($con);
    
    // Sender notification
    $notification->sendNotification(
        $senderAccount,
        'transaction',
        'Transfer Completed',
        "You transferred GHC" . number_format($amount, 2) . " to $recipientName ($recipientAccount). Fee: GHC" . number_format($fee, 2) . ", Tax: GHC" . number_format($tax, 2),
        [
            'amount' => $amount,
            'recipient' => $recipientName,
            'account' => $recipientAccount,
            'reference' => $reference,
            'fee' => $fee,
            'tax' => $tax,
            'total_debit' => $totalDebit
        ],
        true,
        true
    );

    // Receiver notification (only for internal transfers)
    if ($transferType === 'internal') {
        $notification->sendNotification(
            $recipientAccount,
            'transaction',
            'Payment Received',
            "You received GHC" . number_format($amount, 2) . " from $senderName ($senderAccount)",
            [
                'amount' => $amount,
                'sender' => $senderName,
                'account' => $senderAccount,
                'reference' => $reference
            ],
            true,
            true
        );
    }

    // Store success data in session
    $_SESSION['transfer_success'] = [
        'transaction_id' => $reference,
        'amount' => $amount,
        'recipient_name' => $recipientName,
        'recipient_account' => $recipientAccount,
        'description' => $description,
        'type' => $transferType,
        'fee' => $fee,
        'tax' => $tax,
        'net_amount' => $netAmount,
        'total_debit' => $totalDebit,
        'category_id' => $categoryId
    ];

    // Clear transfer data from session
    unset($_SESSION['transfer_data']);
    unset($_SESSION['transfer_fees']);
    unset($_SESSION['csrf_token']);

    // Redirect to completion step
    header('Location: transfer_complete.php');
    exit;

} catch (Exception $e) {
    mysqli_rollback($con);
    $_SESSION['error'] = "Transfer failed: " . $e->getMessage();
    error_log("Transfer failed: " . $e->getMessage());
    header('Location: transfer.php');
    exit;
} finally {
    // Remove the problematic mysqli_stmt_close from finally block
    // Statements are already closed in the try block
}
?>