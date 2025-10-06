<?php
session_start();


// Handle form submission from deposit step
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['step']) && $_POST['step'] == '5') {
    // Store deposit data in session for PayStack processing
    if (!isset($_SESSION['pending_registration'])) {
        $_SESSION['pending_registration'] = $_SESSION['registration_data'];
    }
    $_SESSION['pending_registration']['initialDeposit'] = floatval($_POST['initialDeposit']);
    
    // Return JSON response for AJAX call
    header('Content-Type: application/json');
    echo json_encode(['success' => true, 'message' => 'Payment initialized']);
    exit;
}

// Check if registration data exists
if (!isset($_SESSION['pending_registration'])) {
    header('Location: register.php?error=no_registration_data');
    exit;
}

$registrationData = $_SESSION['pending_registration'];
$initialDeposit = $registrationData['initialDeposit'];

// Paystack configuration
$paystackSecretKey = 'sk_test_32062853cfea07c46202bba1b3600995f67c4c40'; // Replace with your actual Paystack secret key

$paystackPublicKey = 'pk_test_7090654890c9b9e49ccd73414cf46791275afd28'; // Replace with your actual Paystack public key
 
// Generate unique reference
$reference = 'REG_' . uniqid() . '_' . time();

// Store reference in session for verification
$_SESSION['paystack_reference'] = $reference;
$_SESSION['paystack_amount'] = $initialDeposit * 100; // Paystack expects amount in kobo

// Customer email
$customerEmail = $registrationData['email'];
$customerName = $registrationData['firstName'] . ' ' . $registrationData['lastName'];

// Paystack payment data
$paymentData = [
    'email' => $customerEmail,
    'amount' => $initialDeposit * 100, // Convert to kobo
    'reference' => $reference,
    'callback_url' => 'http://localhost/gccb/verify_registration_paystack.php',
    'metadata' => [
        'custom_fields' => [
            [
                'display_name' => "Customer Name",
                'variable_name' => "customer_name",
                'value' => $customerName
            ],
            [
                'display_name' => "Registration Type",
                'variable_name' => "registration_type",
                'value' => "New Account Opening"
            ]
        ]
    ]
];

// Initialize cURL session for Paystack
$ch = curl_init();

curl_setopt_array($ch, [
    CURLOPT_URL => "https://api.paystack.co/transaction/initialize",
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_CUSTOMREQUEST => "POST",
    CURLOPT_POSTFIELDS => json_encode($paymentData),
    CURLOPT_HTTPHEADER => [
        "Authorization: Bearer " . $paystackSecretKey,
        "Content-Type: application/json",
    ],
]);

$response = curl_exec($ch);
$error = curl_error($ch);
curl_close($ch);

if ($error) {
    // Handle cURL error
    header('Location: register.php?error=payment_gateway_error&message=' . urlencode($error));
    exit;
}

$responseData = json_decode($response, true);

if ($responseData['status'] && isset($responseData['data']['authorization_url'])) {
    // Redirect to Paystack payment page
    header('Location: ' . $responseData['data']['authorization_url']);
    exit;
} else {
    // Handle Paystack error
    $errorMessage = $responseData['message'] ?? 'Unknown payment gateway error';
    header('Location: register.php?error=payment_initialization_failed&message=' . urlencode($errorMessage));
    exit;
}
?>