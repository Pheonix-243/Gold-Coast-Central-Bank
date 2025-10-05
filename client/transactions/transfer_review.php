<?php
require_once('../includes/auth.php');
require_once('../includes/conn.php');

// Check if user is logged in
if (!isset($_SESSION['client_account'])) {
    header('Location: ../auth/login.php');
    exit;
}

// Check if form was submitted
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $_SESSION['error'] = "Please complete the transfer form first";
    header('Location: transfer.php');
    exit;
}

// Validate CSRF token
if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    $_SESSION['error'] = "Security validation failed";
    header('Location: transfer.php');
    exit;
}

// Process form data
$transferType = $_POST['type'] ?? 'internal';
$amount = floatval($_POST['amount'] ?? 0);
$description = $_POST['description'] ?? '';
$terms = isset($_POST['terms']) ? true : false;

// Initialize form data array
$formData = [
    'type' => $transferType,
    'amount' => $amount,
    'description' => $description,
    'terms' => $terms
];

// Validate required fields based on transfer type
$errors = [];

// Validate amount
if ($amount < 10) {
    $errors[] = "Minimum transfer amount is GHC10";
} elseif ($amount > 100000) {
    $errors[] = "Maximum transfer amount is GHC100,000 per transaction";
} elseif ($amount > $_SESSION['client_balance']) {
    $errors[] = "Insufficient balance for this transfer";
}

// Validate terms
if (!$terms) {
    $errors[] = "You must accept the terms and conditions";
}

// Validate transfer type specific fields
switch ($transferType) {
    case 'internal':
        $internalAccount = $_POST['internal_account'] ?? '';
        $formData['internal_account'] = $internalAccount;
        
        if (empty($internalAccount)) {
            $errors[] = "Recipient account number is required";
        } else {
            // Validate account exists and get name
            $sql = "SELECT name FROM accountsholder WHERE account = ?";
            $stmt = mysqli_prepare($con, $sql);
            if ($stmt) {
                mysqli_stmt_bind_param($stmt, "s", $internalAccount);
                mysqli_stmt_execute($stmt);
                mysqli_stmt_bind_result($stmt, $recipientName);
                if (!mysqli_stmt_fetch($stmt)) {
                    $errors[] = "Recipient account not found";
                } else {
                    $formData['internal_name'] = $recipientName;
                }
                mysqli_stmt_close($stmt);
            }
        }
        break;
        
    case 'mobile':
        $mobileNetwork = $_POST['mobile_network'] ?? '';
        $mobileNumber = $_POST['mobile_number'] ?? '';
        $mobileName = $_POST['mobile_name'] ?? '';
        
        $formData['mobile_network'] = $mobileNetwork;
        $formData['mobile_number'] = $mobileNumber;
        $formData['mobile_name'] = $mobileName;
        
        if (empty($mobileNetwork)) $errors[] = "Mobile network is required";
        if (empty($mobileNumber)) $errors[] = "Mobile number is required";
        if (empty($mobileName)) $errors[] = "Recipient name is required";
        break;
        
    case 'bank':
        $bankBank = $_POST['bank_bank'] ?? '';
        $bankAccount = $_POST['bank_account'] ?? '';
        $bankName = $_POST['bank_name'] ?? '';
        
        $formData['bank_bank'] = $bankBank;
        $formData['bank_account'] = $bankAccount;
        $formData['bank_name'] = $bankName;
        
        if (empty($bankBank)) $errors[] = "Bank name is required";
        if (empty($bankAccount)) $errors[] = "Account number is required";
        if (empty($bankName)) $errors[] = "Account name is required";
        break;
        
    case 'international':
        $intlBank = $_POST['intl_bank'] ?? '';
        $intlAccount = $_POST['intl_account'] ?? '';
        $intlName = $_POST['intl_name'] ?? '';
        $intlSwift = $_POST['intl_swift'] ?? '';
        $intlCountry = $_POST['intl_country'] ?? '';
        $intlCurrency = $_POST['intl_currency'] ?? '';
        
        $formData['intl_bank'] = $intlBank;
        $formData['intl_account'] = $intlAccount;
        $formData['intl_name'] = $intlName;
        $formData['intl_swift'] = $intlSwift;
        $formData['intl_country'] = $intlCountry;
        $formData['intl_currency'] = $intlCurrency;
        
        if (empty($intlBank)) $errors[] = "Bank name is required";
        if (empty($intlAccount)) $errors[] = "Account number/IBAN is required";
        if (empty($intlName)) $errors[] = "Account name is required";
        if (empty($intlSwift)) $errors[] = "SWIFT/BIC code is required";
        if (empty($intlCountry)) $errors[] = "Country is required";
        if (empty($intlCurrency)) $errors[] = "Currency is required";
        break;
        
    default:
        $errors[] = "Invalid transfer type";
        break;
}

// If there are errors, redirect back to form
if (!empty($errors)) {
    $_SESSION['error'] = implode("<br>", $errors);
    $_SESSION['transfer_data'] = $formData;
    header('Location: transfer.php');
    exit;
}

// Calculate transfer fee and tax
$fee = 0;
$tax = 0;
$feePercentage = 0;
$netAmount = $amount;

// Get transfer fee based on type
$sql = "SELECT fee_percentage, fixed_fee, min_fee, max_fee, tax_percentage FROM transfer_fees WHERE transfer_type = ? AND is_active = 1";
$stmt = mysqli_prepare($con, $sql);
if ($stmt) {
    mysqli_stmt_bind_param($stmt, "s", $transferType);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    if ($row = mysqli_fetch_assoc($result)) {
        $feePercentage = floatval($row['fee_percentage']);
        $fixedFee = floatval($row['fixed_fee']);
        $minFee = floatval($row['min_fee']);
        $maxFee = floatval($row['max_fee']);
        $taxPercentage = floatval($row['tax_percentage']);
        
        // Calculate fee
        $fee = ($amount * $feePercentage / 100) + $fixedFee;
        
        // Apply min/max limits
        if ($fee < $minFee) $fee = $minFee;
        if ($fee > $maxFee) $fee = $maxFee;
        
        // Calculate tax
        $tax = $fee * ($taxPercentage / 100);
        
        $netAmount = $amount - $fee - $tax;
        $totalDebit = $amount + $fee + $tax;
    }
    mysqli_stmt_close($stmt);
}

// Store in session for processing
$_SESSION['transfer_data'] = $formData;
$_SESSION['transfer_fees'] = [
    'fee' => $fee,
    'tax' => $tax,
    'net_amount' => $netAmount,
    'total_debit' => $totalDebit
];

// Get profile picture for sidebar
$profilePic = '';
$sql = "SELECT image FROM accountsholder WHERE account = ?";
$stmt = mysqli_prepare($con, $sql);
if ($stmt) {
    mysqli_stmt_bind_param($stmt, "s", $_SESSION['client_account']);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_bind_result($stmt, $profilePic);
    mysqli_stmt_fetch($stmt);
    mysqli_stmt_close($stmt);
}

// Get account info for sidebar
$accountInfo = [];
$sql = "SELECT account_type FROM accounts_info WHERE account = ?";
$stmt = mysqli_prepare($con, $sql);
if ($stmt) {
    mysqli_stmt_bind_param($stmt, "s", $_SESSION['client_account']);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $accountInfo = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);
}
?>
<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Review Transfer | Gold Coast Central Bank</title>
    <meta name="description" content="Review your transfer details before confirming with Gold Coast Central Bank.">
    <meta name="theme-color" content="#0f172a">
    
    <!-- Favicons -->
    <link rel="apple-touch-icon" sizes="180x180" href="../../assets/favicon/apple-touch-icon.png">
    <link rel="icon" type="image/png" sizes="32x32" href="../../assets/favicon/favicon-32x32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="../../assets/favicon/favicon-16x16.png">
    <link rel="manifest" href="../../assets/favicon/site.webmanifest">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <link rel="stylesheet" href="transfer.css">
    <link rel="stylesheet" href="../dashboard/style.css">
    
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
</head>

<body class="premium-dashboard">
    <div class="container">     
        <nav class="sidebar" id="sidebar">
            <button id="btn_close">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="16" height="16">
                    <path fill="none" d="M0 0h24v24H0z" />
                    <path d="M12 10.586l4.95-4.95 1.414 1.414-4.95 4.95 4.95 4.95-1.414 1.414-4.95-4.95-4.95 4.95-1.414-1.414 4.95-4.95-4.95-4.95L7.05 5.636z" />
                </svg>
            </button>

            <div class="logo">
                <img src="../../gccb_logos/logo-transparent.svg" alt="Gold Coast Central Bank">
            </div>

            <div class="nav_links">
                <a href="../dashboard/" class="nav_link" aria-label="overview">
                    <div class="nav_link_icon">
                        <i class="fas fa-home"></i>
                    </div>
                    <div class="nav_link_text">Dashboard</div>
                </a>

                <a href="../transactions/deposit.php" class="nav_link" aria-label="deposit">
                    <div class="nav_link_icon">
                        <i class="fas fa-money-bill"></i>
                    </div>
                    <div class="nav_link_text">Load</div>
                </a>

                <a href="../transactions/transfer.php" class="nav_link active" aria-label="transfer">
                    <div class="nav_link_icon">
                        <i class="fas fa-exchange-alt"></i>
                    </div>
                    <div class="nav_link_text">Transfer</div>
                </a>

                <a href="../transactions/history.php" class="nav_link" aria-label="history">
                    <div class="nav_link_icon">
                        <i class="fas fa-history"></i>
                    </div>
                    <div class="nav_link_text">History</div>
                </a>
                <a href="../pages/analytics.php" class="nav_link" aria-label="history">
                    <div class="nav_link_icon">
                        <i class="fas fa-chart-line"></i>
                    </div>
                    <div class="nav_link_text">Analytics</div>
                </a>

                <a href="../pages/security.php" class="nav_link" aria-label="settings">
                    <div class="nav_link_icon">
                        <i class="fas fa-lock"></i>
                    </div>
                    <div class="nav_link_text">Security</div>
                </a>
            </div>

            <div class="profile">
                <div class="img_with_name">
                    <a href="../pages/profile.php">
                        <?php if (!empty($profilePic)): ?>
                            <img src="data:image/jpeg;base64,<?= base64_encode($profilePic) ?>" alt="Profile Picture">
                        <?php else: ?>
                            <img src="../images/default-profile.png" alt="Profile Picture">
                        <?php endif; ?>
                    </a>
                    <div class="profile_text">
                        <p class="name"><?= htmlspecialchars($_SESSION['client_name']) ?></p>
                        <p class="occupation"><?= htmlspecialchars($accountInfo['account_type'] ?? 'Account') ?></p>
                    </div>
                </div>
                <a href="../scripts/logout.php" aria-label="logout">
                    <i class="fas fa-sign-out-alt"></i>
                </a>
            </div>
        </nav>

        
        <!-- Main Content -->
           <section class="main_content">
            <div class="topbar">
                <button id="menu_btn">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="16" height="16">
                        <path fill="none" d="M0 0h24v24H0z" />
                        <path d="M3 4h18v2H3V4zm0 7h12v2H3v-2zm0 7h18v2H3v-2z" /></svg>
                </button>
                <div class="overview_text">
                    <h1>Review Transfer</h1>
                    <p class="welcome">Please review your transfer details before confirming</p>
                </div>

                <div class="topbar_icons">
                    <a href="#" aria-label="notifications" class="topbar_icon alert">
                        <i class="fas fa-bell"></i>
                    </a>
                </div>
            </div>
            
            <!-- Page Content -->
            <div class="content_section">
                <main>
                    <!-- Transfer Card -->
                    <div class="transfer-card">
                        <div class="transfer-header">
                            <div class="transfer-icon">
                                <i class="fas fa-exchange-alt"></i>
                            </div>
                            <div>
                                <h2>Review Transfer</h2>
                                <p>Please review your transfer details before confirming</p>
                            </div>
                        </div>

                        <!-- Transfer Progress -->
                        <div class="step-indicator">
                            <div class="step completed" data-step="1">
                                <div class="step-number">1</div>
                                <span class="step-label">Transfer Details</span>
                            </div>
                            <div class="step active" data-step="2">
                                <div class="step-number">2</div>
                                <span class="step-label">Review & Confirm</span>
                            </div>
                            <div class="step" data-step="3">
                                <div class="step-number">3</div>
                                <span class="step-label">Complete</span>
                            </div>
                        </div>
                            
                        <!-- Transfer Details -->
                        <div class="review-container">
                            <div class="review-section">
                                <h3>Transfer Details</h3>
                                <div class="review-details">
                                    <div class="detail-row">
                                        <span class="detail-label">Transfer Type</span>
                                        <span class="detail-value">
                                            <?php 
                                            switch($transferType) {
                                                case 'internal': echo 'Internal Transfer'; break;
                                                case 'mobile': echo 'Mobile Money'; break;
                                                case 'bank': echo 'Local Bank Transfer'; break;
                                                case 'international': echo 'International Transfer'; break;
                                                default: echo 'Transfer'; break;
                                            }
                                            ?>
                                        </span>
                                    </div>
                                    
                                    <?php if ($transferType === 'internal'): ?>
                                        <div class="detail-row">
                                            <span class="detail-label">Recipient Account</span>
                                            <span class="detail-value"><?= htmlspecialchars($formData['internal_account']) ?></span>
                                        </div>
                                        <div class="detail-row">
                                            <span class="detail-label">Recipient Name</span>
                                            <span class="detail-value"><?= htmlspecialchars($formData['internal_name']) ?></span>
                                        </div>
                                    <?php elseif ($transferType === 'mobile'): ?>
                                        <div class="detail-row">
                                            <span class="detail-label">Mobile Network</span>
                                            <span class="detail-value">
                                                <?php 
                                                switch($formData['mobile_network']) {
                                                    case 'mtn': echo 'MTN Mobile Money'; break;
                                                    case 'vodafone': echo 'Vodafone Cash'; break;
                                                    case 'airteltigo': echo 'AirtelTigo Money'; break;
                                                    default: echo htmlspecialchars($formData['mobile_network']); break;
                                                }
                                                ?>
                                            </span>
                                        </div>
                                        <div class="detail-row">
                                            <span class="detail-label">Mobile Number</span>
                                            <span class="detail-value"><?= htmlspecialchars($formData['mobile_number']) ?></span>
                                        </div>
                                        <div class="detail-row">
                                            <span class="detail-label">Recipient Name</span>
                                            <span class="detail-value"><?= htmlspecialchars($formData['mobile_name']) ?></span>
                                        </div>
                                    <?php elseif ($transferType === 'bank'): ?>
                                        <div class="detail-row">
                                            <span class="detail-label">Bank Name</span>
                                            <span class="detail-value">
                                                <?php 
                                                switch($formData['bank_bank']) {
                                                    case 'ecobank': echo 'Ecobank Ghana'; break;
                                                    case 'gcb': echo 'GCB Bank'; break;
                                                    case 'fidelity': echo 'Fidelity Bank'; break;
                                                    case 'absa': echo 'Absa Bank'; break;
                                                    case 'stanbic': echo 'Stanbic Bank'; break;
                                                    default: echo htmlspecialchars($formData['bank_bank']); break;
                                                }
                                                ?>
                                            </span>
                                        </div>
                                        <div class="detail-row">
                                            <span class="detail-label">Account Number</span>
                                            <span class="detail-value"><?= htmlspecialchars($formData['bank_account']) ?></span>
                                        </div>
                                        <div class="detail-row">
                                            <span class="detail-label">Account Name</span>
                                            <span class="detail-value"><?= htmlspecialchars($formData['bank_name']) ?></span>
                                        </div>
                                    <?php elseif ($transferType === 'international'): ?>
                                        <div class="detail-row">
                                            <span class="detail-label">Bank Name</span>
                                            <span class="detail-value"><?= htmlspecialchars($formData['intl_bank']) ?></span>
                                        </div>
                                        <div class="detail-row">
                                            <span class="detail-label">Account Number/IBAN</span>
                                            <span class="detail-value"><?= htmlspecialchars($formData['intl_account']) ?></span>
                                        </div>
                                        <div class="detail-row">
                                            <span class="detail-label">Account Name</span>
                                            <span class="detail-value"><?= htmlspecialchars($formData['intl_name']) ?></span>
                                        </div>
                                        <div class="detail-row">
                                            <span class="detail-label">SWIFT/BIC Code</span>
                                            <span class="detail-value"><?= htmlspecialchars($formData['intl_swift']) ?></span>
                                        </div>
                                        <div class="detail-row">
                                            <span class="detail-label">Country</span>
                                            <span class="detail-value">
                                                <?php 
                                                switch($formData['intl_country']) {
                                                    case 'US': echo 'United States'; break;
                                                    case 'GB': echo 'United Kingdom'; break;
                                                    case 'CA': echo 'Canada'; break;
                                                    case 'NG': echo 'Nigeria'; break;
                                                    case 'ZA': echo 'South Africa'; break;
                                                    case 'KE': echo 'Kenya'; break;
                                                    case 'FR': echo 'France'; break;
                                                    case 'DE': echo 'Germany'; break;
                                                    default: echo htmlspecialchars($formData['intl_country']); break;
                                                }
                                                ?>
                                            </span>
                                        </div>
                                        <div class="detail-row">
                                            <span class="detail-label">Currency</span>
                                            <span class="detail-value">
                                                <?php 
                                                switch($formData['intl_currency']) {
                                                    case 'GHC': echo 'Ghana Cedi (GHC)'; break;
                                                    case 'USD': echo 'US Dollar (USD)'; break;
                                                    case 'GBP': echo 'British Pound (GBP)'; break;
                                                    case 'EUR': echo 'Euro (EUR)'; break;
                                                    case 'NGN': echo 'Nigerian Naira (NGN)'; break;
                                                    default: echo htmlspecialchars($formData['intl_currency']); break;
                                                }
                                                ?>
                                            </span>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <div class="detail-row">
                                        <span class="detail-label">Amount</span>
                                        <span class="detail-value">GHC <?= number_format($amount, 2) ?></span>
                                    </div>
                                    
                                    <?php if (!empty($description)): ?>
                                        <div class="detail-row">
                                            <span class="detail-label">Description</span>
                                            <span class="detail-value"><?= htmlspecialchars($description) ?></span>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <!-- Fee Breakdown -->
                            <div class="review-section">
                                <h3>Fee Breakdown</h3>
                                <div class="fee-breakdown">
                                    <div class="fee-row">
                                        <span class="fee-label">Transfer Amount</span>
                                        <span class="fee-value">GHC <?= number_format($amount, 2) ?></span>
                                    </div>
                                    <div class="fee-row">
                                        <span class="fee-label">Transfer Fee</span>
                                        <span class="fee-value">GHC <?= number_format($fee, 2) ?></span>
                                    </div>
                                    <div class="fee-row total">
                                        <span class="fee-label">Total Deduction</span>
                                        <span class="fee-value">GHC <?= number_format($amount + $fee, 2) ?></span>
                                    </div>
                                    <div class="fee-row net">
                                        <span class="fee-label">Recipient Receives</span>
                                        <span class="fee-value">GHC <?= number_format($netAmount, 2) ?></span>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Account Balance -->
                            <div class="review-section">
                                <h3>Account Balance</h3>
                                <div class="balance-summary">
                                    <div class="balance-row">
                                        <span class="balance-label">Current Balance</span>
                                        <span class="balance-value">GHC <?= number_format($_SESSION['client_balance'], 2) ?></span>
                                    </div>
                                    <div class="balance-row">
                                        <span class="balance-label">After Transfer</span>
                                        <span class="balance-value">GHC <?= number_format($_SESSION['client_balance'] - $amount - $fee, 2) ?></span>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Confirmation Actions -->
                            <div class="review-actions">
                                <form method="POST" action="transfer_process.php" id="confirmForm">
                                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                    <input type="hidden" name="confirm_transfer" value="1">
                                    
                                    <div class="action-buttons">
                                        <a href="transfer.php" class="btn btn-secondary">
                                            <i class="fas fa-arrow-left"></i> Edit Details
                                        </a>
                                        <button type="submit" class="btn btn-primary" id="confirmButton">
                                            <i class="fas fa-check-circle"></i> Confirm Transfer
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </main>

                <!-- Transfer Information Sidebar -->
                <aside class="transfer-aside">
                    <!-- Security Notice -->
                    <div class="info-card security-notice">
                        <h3><i class="fas fa-shield-alt"></i> Security Notice</h3>
                        <ul class="security-list">
                            <li>
                                <i class="fas fa-check-circle success"></i>
                                <span>Always verify recipient details before confirming</span>
                            </li>
                            <li>
                                <i class="fas fa-check-circle success"></i>
                                <span>This transaction is secured with bank-level encryption</span>
                            </li>
                            <li>
                                <i class="fas fa-check-circle success"></i>
                                <span>You will receive an email confirmation</span>
                            </li>
                        </ul>
                    </div>
                    
                    <!-- Contact Support -->
                    <div class="info-card support-card">
                        <h3><i class="fas fa-headset"></i> Need Help?</h3>
                        <p class="text-hint">Our support team is available 24/7 to assist you</p>
                        <div class="support-options">
                            <a href="tel:+233244000000" class="support-option">
                                <i class="fas fa-phone"></i>
                                <span>+233 24 400 0000</span>
                            </a>
                            <a href="mailto:support@goldcoastcentralbank.com" class="support-option">
                                <i class="fas fa-envelope"></i>
                                <span>Email Support</span>
                            </a>
                        </div>
                    </div>
                </aside>
            </div>
        </section>
    </div>

    <!-- JavaScript -->
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const confirmForm = document.getElementById('confirmForm');
        const confirmButton = document.getElementById('confirmButton');
        
        confirmForm?.addEventListener('submit', function(e) {
            // Prevent double submission
            if (confirmButton) {
                confirmButton.disabled = true;
                confirmButton.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';
            }
        });
    });
    </script>
</body>
</html>