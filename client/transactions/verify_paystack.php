<?php
require_once('../includes/auth.php');
require_once('../includes/header.php');
require_once('../classes/TransactionProcessor.php');

// Verify Paystack payment
if (!isset($_GET['reference'])) {
    $_SESSION['error'] = "No payment reference provided";
    header('Location: deposit.php');
    exit;
}

$reference = $_GET['reference'];

// Verify with Paystack API
$curl = curl_init();
curl_setopt_array($curl, [
    CURLOPT_URL => "https://api.paystack.co/transaction/verify/" . rawurlencode($reference),
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => [
        "accept: application/json",
        "authorization: Bearer sk_test_32062853cfea07c46202bba1b3600995f67c4c40", // Replace with your secret key
        "cache-control: no-cache"
    ],
]);

$response = curl_exec($curl);
$err = curl_error($curl);
curl_close($curl);

if ($err) {
    $_SESSION['error'] = "Payment verification failed: " . $err;
    header('Location: deposit.php');
    exit;
}

$result = json_decode($response, true);

if (!$result || !$result['status']) {
    $_SESSION['error'] = "Payment verification failed";
    header('Location: deposit.php');
    exit;
}

// Check if payment was successful
if ($result['data']['status'] !== 'success') {
    $_SESSION['error'] = "Payment was not successful";
    header('Location: deposit.php');
    exit;
}

// Verify amount matches what we expect
$expectedAmount = $_SESSION['pending_deposit']['amount'] * 100; // Compare in kobo/cent units
if ($result['data']['amount'] != $expectedAmount) {
    $_SESSION['error'] = "Payment amount doesn't match requested deposit";
    header('Location: deposit.php');
    exit;
}

// Generate dynamic description based on payment method
$paymentMethod = $result['data']['channel'] ?? 'unknown';
$paymentDetails = '';

switch (strtolower($paymentMethod)) {
    case 'card':
        $paymentDetails = 'Card ending with ' . ($result['data']['authorization']['last4'] ?? '****');
        break;
    case 'bank':
        $paymentDetails = $result['data']['authorization']['bank'] ?? 'Bank transfer';
        break;
    case 'mobile_money':
        $paymentDetails = $result['data']['authorization']['mobile'] ?? 'Mobile money';
        break;
    default:
        $paymentDetails = $paymentMethod;
}

$description = "Deposit via " . ucfirst(str_replace('_', ' ', $paymentMethod)) . " - " . $paymentDetails;

// Process the deposit
$transactionProcessor = new TransactionProcessor($con);
$result = $transactionProcessor->processDeposit(
    $_SESSION['client_account'],
    $_SESSION['pending_deposit']['amount'],
    'Paystack ' . ucfirst($paymentMethod),
    $description
);

if ($result['status'] === 'success') {
    $_SESSION['success'] = "Deposit of GHC" . 
        number_format($_SESSION['pending_deposit']['amount'], 2) . 
        " was successful!";
    $_SESSION['client_balance'] += $_SESSION['pending_deposit']['amount'];
    
    // Clear session variables
    unset($_SESSION['pending_deposit']);
    unset($_SESSION['paystack_reference']);
    
    header('Location: ../dashboard/');
    exit;
} else {
    $_SESSION['error'] = "Deposit failed: " . $result['message'];
    header('Location: deposit.php');
    exit;
}
?>