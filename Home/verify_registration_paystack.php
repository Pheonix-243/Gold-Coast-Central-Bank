<?php
session_start();

require_once 'config/database.php';
require_once 'includes/emails.php';
require_once 'includes/security_logger.php';

// Check if payment reference exists
if (!isset($_GET['reference']) || !isset($_SESSION['pending_registration'])) {
    header('Location: register.php?error=invalid_verification');
    exit;
}

$reference = $_GET['reference'];
$registrationData = $_SESSION['pending_registration'];

// Paystack configuration
$paystackSecretKey = 'sk_test_32062853cfea07c46202bba1b3600995f67c4c40'; // Replace with your actual Paystack secret key

// Verify payment with Paystack
$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL => "https://api.paystack.co/transaction/verify/" . rawurlencode($reference),
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => [
        "Authorization: Bearer " . $paystackSecretKey,
    ],
]);

$response = curl_exec($ch);
$error = curl_error($ch);
curl_close($ch);

if ($error) {
    // Handle cURL error
    header('Location: register.php?error=verification_failed&message=' . urlencode($error));
    exit;
}

$responseData = json_decode($response, true);

if ($responseData['status'] && $responseData['data']['status'] === 'success') {
    // Payment successful - Complete registration
    try {
        $db = DatabaseConnection::getInstance()->getConnection();
        $emailService = new SecureEmailService();
        $logger = new SecurityLogger();
        
        // Begin transaction
        $db->beginTransaction();
        
        // Generate account details
        $accountNumber = generateAccountNumber($db);
        $tempPassword = generateSecurePassword();
        $hashedPassword = password_hash($tempPassword, PASSWORD_DEFAULT);
        $registerDate = date('Y-m-d H:i:s');
        
        // Convert CNIC to integer (remove dashes)
        $cnic = (int) str_replace('-', '', $registrationData['cnic']);
        
        // Handle image upload
        $imageData = null;
        if (isset($registrationData['passportImage']) && 
            isset($registrationData['passportImage']['tmp_name']) && 
            file_exists($registrationData['passportImage']['tmp_name'])) {
            $imageData = file_get_contents($registrationData['passportImage']['tmp_name']);
        }
        
        // Insert into accountsholder table
        $stmt = $db->prepare("
            INSERT INTO accountsholder 
            (account, name, fname, cnic, contect, dob, gender, email, image, postal, city, houseaddress, created_at, updated_at) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
        ");
        
        $stmt->execute([
            $accountNumber,
            $registrationData['firstName'] . ' ' . $registrationData['lastName'],
            $registrationData['fname'] ?? '',
            $cnic,
            preg_replace('/\D/', '', $registrationData['phone']),
            $registrationData['dob'],
            $registrationData['gender'],
            $registrationData['email'],
            $imageData,
            $registrationData['postalCode'],
            $registrationData['city'],
            $registrationData['address']
        ]);
        
        // Insert into accounts_info table with initial deposit
        $stmt = $db->prepare("
            INSERT INTO accounts_info 
            (account, account_title, account_type, balance, registerdate, password, created_at, updated_at) 
            VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())
        ");
        
        $stmt->execute([
            $accountNumber,
            $registrationData['firstName'] . ' ' . $registrationData['lastName'],
            $registrationData['accountType'],
            $registrationData['initialDeposit'],
            $registerDate,
            $hashedPassword
        ]);
        
        // Store security questions
        if (isset($registrationData['securityQuestions'])) {
            $stmt = $db->prepare("
                INSERT INTO security_questions (account, question, answer_hash, created_at) 
                VALUES (?, ?, ?, NOW())
            ");
            
            foreach ($registrationData['securityQuestions'] as $question) {
                $stmt->execute([
                    $accountNumber,
                    $question['question'],
                    password_hash(strtolower(trim($question['answer'])), PASSWORD_DEFAULT)
                ]);
            }
        }
        
        // Record the transaction
        $stmt = $db->prepare("
            INSERT INTO transactions (account, type, amount, balance_after, description, reference, created_at) 
            VALUES (?, 'deposit', ?, ?, 'Initial Deposit - Account Opening', ?, NOW())
        ");
        
        $stmt->execute([
            $accountNumber,
            $registrationData['initialDeposit'],
            $registrationData['initialDeposit'],
            $reference
        ]);
        
        // Commit transaction
        $db->commit();
        
        // Log successful registration
        $logger->logEvent('account_registered', [
            'account' => $accountNumber,
            'email' => $registrationData['email'],
            'type' => $registrationData['accountType'],
            'payment_reference' => $reference
        ]);
        
        // Send welcome email
        sendWelcomeEmail($emailService, $registrationData['email'], $accountNumber, $tempPassword, $registrationData['firstName'] . ' ' . $registrationData['lastName']);
        
        // Clear session data
        unset($_SESSION['pending_registration']);
        unset($_SESSION['paystack_reference']);
        unset($_SESSION['paystack_amount']);
        
        // Store success data for confirmation page
        $_SESSION['registration_success'] = [
            'accountNumber' => $accountNumber,
            'email' => $registrationData['email'],
            'accountType' => $registrationData['accountType'],
            'initialDeposit' => $registrationData['initialDeposit']
        ];
        
        // Redirect to success page
        header('Location: register.php?success=1&step=6');
        exit;
        
    } catch (Exception $e) {
        // Rollback transaction on error
        if (isset($db)) {
            $db->rollback();
        }
        
        error_log('Registration completion error: ' . $e->getMessage());
        header('Location: register.php?error=registration_failed&message=' . urlencode($e->getMessage()));
        exit;
    }
    
} else {
    // Payment failed
    header('Location: register.php?error=payment_failed&message=' . urlencode($responseData['message'] ?? 'Payment verification failed'));
    exit;
}

// Helper functions
function generateAccountNumber($db) {
    do {
        $account = 'GC' . date('Y') . str_pad(mt_rand(1, 9999999999), 10, '0', STR_PAD_LEFT);
        $stmt = $db->prepare("SELECT account FROM accounts_info WHERE account = ?");
        $stmt->execute([$account]);
    } while ($stmt->fetch());
    
    return $account;
}

function generateSecurePassword() {
    $length = 12;
    $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*';
    $password = '';
    for ($i = 0; $i < $length; $i++) {
        $password .= $chars[random_int(0, strlen($chars) - 1)];
    }
    return $password;
}

function sendWelcomeEmail($emailService, $email, $accountNumber, $tempPassword, $clientName) {
    $subject = "Welcome to Gold Coast Central Bank - Your Account Details";
    
    $body = "
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset='UTF-8'>
        <title>Welcome to GCC Bank</title>
        <style>
            body { font-family: Arial, sans-serif; margin: 0; padding: 20px; background-color: #f5f5f5; }
            .container { max-width: 600px; margin: 0 auto; background-color: white; padding: 30px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
            .header { text-align: center; border-bottom: 2px solid #1e40af; padding-bottom: 20px; margin-bottom: 30px; }
            .logo { color: #1e40af; font-size: 24px; font-weight: bold; }
            .info-box { background-color: #f8fafc; border: 1px solid #e5e7eb; padding: 20px; margin: 20px 0; border-radius: 8px; }
            .warning { background-color: #fef2f2; border-left: 4px solid #ef4444; padding: 15px; margin: 20px 0; }
            .footer { text-align: center; margin-top: 30px; padding-top: 20px; border-top: 1px solid #e5e7eb; color: #6b7280; font-size: 14px; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <div class='logo'>Gold Coast Central Bank</div>
            </div>
            
            <h2>Welcome to GCC Bank, {$clientName}!</h2>
            <p>Dear {$clientName},</p>
            <p>Your Gold Coast Central Bank account has been successfully created. Here are your account details:</p>
            
            <div class='info-box'>
                <h4>Account Information</h4>
                <p><strong>Account Number:</strong> {$accountNumber}</p>
                <p><strong>Temporary Password:</strong> {$tempPassword}</p>
                <p><strong>Email:</strong> {$email}</p>
            </div>
            
            <div class='warning'>
                <strong>Important Security Notice:</strong>
                <ul>
                    <li>This is a temporary password - you must change it on your first login</li>
                    <li>Never share your password with anyone</li>
                    <li>Our staff will never ask for your password</li>
                    <li>Enable two-factor authentication for added security</li>
                </ul>
            </div>
            
            <p>To access your account, please visit: <a href='http://localhost/gccb/login.php'>http://localhost/gccb/login.php</a></p>
            
            <div class='footer'>
                <p>Gold Coast Central Bank - Secure Banking Solutions</p>
                <p>This is an automated message. Please do not reply to this email.</p>
            </div>
        </div>
    </body>
    </html>
    ";
    
    $plainBody = "Welcome to Gold Coast Central Bank!\n\n" .
               "Your account has been created successfully.\n\n" .
               "Account Number: {$accountNumber}\n" .
               "Temporary Password: {$tempPassword}\n" .
               "Email: {$email}\n\n" .
               "IMPORTANT: You must change your temporary password on first login.\n\n" .
               "Login at: http://localhost/gccb/login.php\n\n" .
               "Gold Coast Central Bank - Secure Banking Solutions";
    
    return $emailService->sendOTP($email, $tempPassword, 'account_creation');
}
?>