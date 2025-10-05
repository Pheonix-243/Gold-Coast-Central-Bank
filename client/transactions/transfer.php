<?php
// session_start();
require_once('../includes/auth.php');
require_once('../includes/conn.php');

// Check if user is logged in
if (!isset($_SESSION['client_account'])) {
    header('Location: ../auth/login.php');
    exit;
}

// Initialize variables
$error = isset($_SESSION['error']) ? $_SESSION['error'] : '';
unset($_SESSION['error']);

// Initialize form data from session or defaults
$formData = isset($_SESSION['transfer_data']) ? $_SESSION['transfer_data'] : [
    'type' => 'internal',
    'internal_account' => '',
    'internal_name' => '',
    'mobile_number' => '',
    'mobile_name' => '',
    'mobile_network' => '',
    'bank_account' => '',
    'bank_name' => '',
    'bank_bank' => '',
    'intl_account' => '',
    'intl_name' => '',
    'intl_bank' => '',
    'intl_swift' => '',
    'intl_country' => 'GH',
    'intl_currency' => 'GHC',
    'amount' => '',
    'description' => ''
];

// In the PHP section, add this to formData initialization
$formData['category_source'] = $formData['category_source'] ?? 'manual';

// Get recent beneficiaries
$recentBeneficiaries = [];
$sql = "SELECT DISTINCT receiver as account_number, r_name as name, type 
        FROM account_history 
        WHERE sender = ? 
        ORDER BY dt DESC, tm DESC 
        LIMIT 5";
$stmt = mysqli_prepare($con, $sql);
if ($stmt) {
    mysqli_stmt_bind_param($stmt, "s", $_SESSION['client_account']);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    while ($row = mysqli_fetch_assoc($result)) {
        $recentBeneficiaries[] = $row;
    }
    mysqli_stmt_close($stmt);
}

// Get transfer fees configuration
$transferFees = [];
$sql = "SELECT * FROM transfer_fees WHERE is_active = 1";
$result = mysqli_query($con, $sql);
if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $transferFees[$row['transfer_type']] = $row;
    }
}

// Generate CSRF token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
?>
<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Transfer Funds | Gold Coast Central Bank</title>
    <meta name="description" content="Securely transfer funds with Gold Coast Central Bank's premium banking services. Fast, reliable, and secure money transfers.">
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
    <div class="container">     <nav class="sidebar" id="sidebar">
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
                    <h1>Quick transfer</h1>
                    <p class="welcome">Transact like a boss!</p>
                </div>

                <div class="topbar_icons">
                    <a href="#" aria-label="notifications" class="topbar_icon alert">
                        <i class="fas fa-bell"></i>
                    </a>
                </div>
            </div>
            
            <!-- Page Content -->
            <!-- <section class="content_section"> -->
            <div class="content_section">
                <main>
                    <?php if (!empty($error)): ?>
                        <div class="alert alert-danger fade-in">
                            <div class="alert-icon">
                                <i class="fas fa-exclamation-circle"></i>
                            </div>
                            <div class="alert-content">
                                <h4>Error Processing Request</h4>
                                <p><?= htmlspecialchars($error) ?></p>
                            </div>
                            <button type="button" class="alert-close" aria-label="Close">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>
                    <?php endif; ?>

                    <!-- Transfer Card -->
                    <div class="transfer-card">
                        <div class="transfer-header">
                            <div class="transfer-icon">
                                <i class="fas fa-exchange-alt"></i>
                            </div>
                            <div>
                                <h2>New Fund Transfer</h2>
                                <p>Securely transfer money to any bank or mobile wallet</p>
                            </div>
                        </div>

                        <!-- Transfer Progress -->
                        <div class="step-indicator">
                            <div class="step active" data-step="1">
                                <div class="step-number">1</div>
                                <span class="step-label">Transfer Details</span>
                            </div>
                            <div class="step" data-step="2">
                                <div class="step-number">2</div>
                                <span class="step-label">Review & Confirm</span>
                            </div>
                            <div class="step" data-step="3">
                                <div class="step-number">3</div>
                                <span class="step-label">Complete</span>
                            </div>
                        </div>
                            
                        <!-- Step 1: Transfer Details -->
                        <form method="POST" action="transfer_review.php" id="transferForm" class="transfer-form">
                            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                            <input type="hidden" name="step" value="1">
                            <input type="hidden" id="type" name="type" value="<?= htmlspecialchars($formData['type']) ?>">
                            
                            <div class="transfer-type-selector">
                                <div class="transfer-type-option <?= $formData['type'] === 'internal' ? 'active' : '' ?>" data-type="internal">
                                    <div class="option-icon">
                                        <i class="fas fa-building"></i>
                                    </div>
                                    <div class="option-content">
                                        <h4>Internal Transfer</h4>
                                        <p>Transfer to another Gold Coast account</p>
                                    </div>
                                    <div class="option-check">
                                        <i class="fas fa-check"></i>
                                    </div>
                                </div>
                                
                                <div class="transfer-type-option <?= $formData['type'] === 'mobile' ? 'active' : '' ?>" data-type="mobile">
                                    <div class="option-icon">
                                        <i class="fas fa-mobile-alt"></i>
                                    </div>
                                    <div class="option-content">
                                        <h4>Mobile Money</h4>
                                        <p>Send to mobile money accounts</p>
                                    </div>
                                    <div class="option-check">
                                        <i class="fas fa-check"></i>
                                    </div>
                                </div>
                                
                                <div class="transfer-type-option <?= $formData['type'] === 'bank' ? 'active' : '' ?>" data-type="bank">
                                    <div class="option-icon">
                                        <i class="fas fa-university"></i>
                                    </div>
                                    <div class="option-content">
                                        <h4>Local Bank</h4>
                                        <p>Transfer to other banks in Ghana</p>
                                    </div>
                                    <div class="option-check">
                                        <i class="fas fa-check"></i>
                                    </div>
                                </div>
                                
                                <div class="transfer-type-option <?= $formData['type'] === 'international' ? 'active' : '' ?>" data-type="international">
                                    <div class="option-icon">
                                        <i class="fas fa-globe"></i>
                                    </div>
                                    <div class="option-content">
                                        <h4>International</h4>
                                        <p>Send money abroad</p>
                                    </div>
                                    <div class="option-check">
                                        <i class="fas fa-check"></i>
                                    </div>
                                </div>
                            </div>
                                
                            <!-- Dynamic Fields Container -->
                            <div class="dynamic-fields">
                                <!-- Internal Transfer Fields -->
                                <div class="form-row <?= $formData['type'] === 'internal' ? 'active' : '' ?>" data-transfer-type="internal">
                                    <div class="form-group">
                                        <label for="internal_account">Recipient Account Number</label>
                                        <div class="input-group">
                                            <span class="input-group-text">
                                                <i class="fas fa-user"></i>
                                            </span>
                                            <input type="text" 
                                                   id="internal_account" 
                                                   name="internal_account" 
                                                   class="form-control" 
                                                   placeholder="Enter account number"
                                                   value="<?= htmlspecialchars($formData['internal_account']) ?>"
                                                   required>
                                        </div>
                                        <div class="text-hint">
                                            We'll automatically retrieve the recipient's name
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Mobile Money Fields -->
                                <div class="form-row <?= $formData['type'] === 'mobile' ? 'active' : '' ?>" data-transfer-type="mobile">
                                    <div class="form-group">
                                        <label for="mobile_network">Mobile Network</label>
                                        <div class="input-group">
                                            <span class="input-group-text">
                                                <i class="fas fa-mobile-alt"></i>
                                            </span>
                                            <select id="mobile_network" name="mobile_network" class="form-control" required>
                                                <option value="">Select Network</option>
                                                <option value="mtn" <?= ($formData['mobile_network'] === 'mtn') ? 'selected' : '' ?>>MTN Mobile Money</option>
                                                <option value="vodafone" <?= ($formData['mobile_network'] === 'vodafone') ? 'selected' : '' ?>>Vodafone Cash</option>
                                                <option value="airteltigo" <?= ($formData['mobile_network'] === 'airteltigo') ? 'selected' : '' ?>>AirtelTigo Money</option>
                                            </select>
                                        </div>
                                    </div>
                                    
                                    <div class="form-group">
                                        <label for="mobile_number">Mobile Number</label>
                                        <div class="input-group">
                                            <span class="input-group-text">
                                                <i class="fas fa-phone"></i>
                                            </span>
                                            <input type="tel" 
                                                   id="mobile_number" 
                                                   name="mobile_number" 
                                                   class="form-control" 
                                                   placeholder="e.g. 0244123456"
                                                   value="<?= htmlspecialchars($formData['mobile_number']) ?>"
                                                   required>
                                        </div>
                                    </div>
                                    
                                    <div class="form-group">
                                        <label for="mobile_name">Recipient Name</label>
                                        <div class="input-group">
                                            <span class="input-group-text">
                                                <i class="fas fa-user"></i>
                                            </span>
                                            <input type="text" 
                                                   id="mobile_name" 
                                                   name="mobile_name" 
                                                   class="form-control" 
                                                   placeholder="Recipient's full name"
                                                   value="<?= htmlspecialchars($formData['mobile_name']) ?>"
                                                   required>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Local Bank Fields -->
                                <div class="form-row <?= $formData['type'] === 'bank' ? 'active' : '' ?>" data-transfer-type="bank">
                                    <div class="form-group">
                                        <label for="bank_bank">Bank Name</label>
                                        <div class="input-group">
                                            <span class="input-group-text">
                                                <i class="fas fa-university"></i>
                                            </span>
                                            <select id="bank_bank" name="bank_bank" class="form-control" required>
                                                <option value="">Select Bank</option>
                                                <option value="ecobank" <?= ($formData['bank_bank'] === 'ecobank') ? 'selected' : '' ?>>Ecobank Ghana</option>
                                                <option value="gcb" <?= ($formData['bank_bank'] === 'gcb') ? 'selected' : '' ?>>GCB Bank</option>
                                                <option value="fidelity" <?= ($formData['bank_bank'] === 'fidelity') ? 'selected' : '' ?>>Fidelity Bank</option>
                                                <option value="absa" <?= ($formData['bank_bank'] === 'absa') ? 'selected' : '' ?>>Absa Bank</option>
                                                <option value="stanbic" <?= ($formData['bank_bank'] === 'stanbic') ? 'selected' : '' ?>>Stanbic Bank</option>
                                            </select>
                                        </div>
                                    </div>
                                    
                                    <div class="form-group">
                                        <label for="bank_account">Account Number</label>
                                        <div class="input-group">
                                            <span class="input-group-text">
                                                <i class="fas fa-credit-card"></i>
                                            </span>
                                            <input type="text" 
                                                   id="bank_account" 
                                                   name="bank_account" 
                                                   class="form-control" 
                                                   placeholder="Recipient's account number"
                                                   value="<?= htmlspecialchars($formData['bank_account']) ?>"
                                                   required>
                                        </div>
                                    </div>
                                    
                                    <div class="form-group">
                                        <label for="bank_name">Account Name</label>
                                        <div class="input-group">
                                            <span class="input-group-text">
                                                <i class="fas fa-user-tag"></i>
                                            </span>
                                            <input type="text" 
                                                   id="bank_name" 
                                                   name="bank_name" 
                                                   class="form-control" 
                                                   placeholder="Recipient's name as it appears on bank records"
                                                   value="<?= htmlspecialchars($formData['bank_name']) ?>"
                                                   required>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- International Transfer Fields -->
                                <div class="form-row <?= $formData['type'] === 'international' ? 'active' : '' ?>" data-transfer-type="international">
                                    <div class="form-group">
                                        <label for="intl_bank">Bank Name</label>
                                        <div class="input-group">
                                            <span class="input-group-text">
                                                <i class="fas fa-university"></i>
                                            </span>
                                            <input type="text" 
                                                   id="intl_bank" 
                                                   name="intl_bank" 
                                                   class="form-control" 
                                                   placeholder="Recipient's bank name"
                                                   value="<?= htmlspecialchars($formData['intl_bank']) ?>"
                                                   required>
                                        </div>
                                    </div>
                                    
                                    <div class="form-group">
                                        <label for="intl_account">Account Number/IBAN</label>
                                        <div class="input-group">
                                            <span class="input-group-text">
                                                <i class="fas fa-credit-card"></i>
                                            </span>
                                            <input type="text" 
                                                   id="intl_account" 
                                                   name="intl_account" 
                                                   class="form-control" 
                                                   placeholder="Recipient's account number or IBAN"
                                                   value="<?= htmlspecialchars($formData['intl_account']) ?>"
                                                   required>
                                        </div>
                                    </div>
                                    
                                    <div class="form-group">
                                        <label for="intl_name">Account Name</label>
                                        <div class="input-group">
                                            <span class="input-group-text">
                                                <i class="fas fa-user-tag"></i>
                                            </span>
                                            <input type="text" 
                                                   id="intl_name" 
                                                   name="intl_name" 
                                                   class="form-control" 
                                                   placeholder="Recipient's full name"
                                                   value="<?= htmlspecialchars($formData['intl_name']) ?>"
                                                   required>
                                        </div>
                                    </div>
                                    
                                    <div class="form-group">
                                        <label for="intl_swift">SWIFT/BIC Code</label>
                                        <div class="input-group">
                                            <span class="input-group-text">
                                                <i class="fas fa-code"></i>
                                            </span>
                                            <input type="text" 
                                                   id="intl_swift" 
                                                   name="intl_swift" 
                                                   class="form-control" 
                                                   placeholder="Bank's SWIFT or BIC code"
                                                   value="<?= htmlspecialchars($formData['intl_swift']) ?>"
                                                   required>
                                        </div>
                                    </div>
                                    
                                    <div class="form-group">
                                        <label for="intl_country">Country</label>
                                        <div class="input-group">
                                            <span class="input-group-text">
                                                <i class="fas fa-globe"></i>
                                            </span>
                                            <select id="intl_country" name="intl_country" class="form-control" required>
                                                <option value="">Select Country</option>
                                                <option value="US" <?= ($formData['intl_country'] === 'US') ? 'selected' : '' ?>>United States</option>
                                                <option value="GB" <?= ($formData['intl_country'] === 'GB') ? 'selected' : '' ?>>United Kingdom</option>
                                                <option value="CA" <?= ($formData['intl_country'] === 'CA') ? 'selected' : '' ?>>Canada</option>
                                                <option value="NG" <?= ($formData['intl_country'] === 'NG') ? 'selected' : '' ?>>Nigeria</option>
                                                <option value="ZA" <?= ($formData['intl_country'] === 'ZA') ? 'selected' : '' ?>>South Africa</option>
                                                <option value="KE" <?= ($formData['intl_country'] === 'KE') ? 'selected' : '' ?>>Kenya</option>
                                                <option value="FR" <?= ($formData['intl_country'] === 'FR') ? 'selected' : '' ?>>France</option>
                                                <option value="DE" <?= ($formData['intl_country'] === 'DE') ? 'selected' : '' ?>>Germany</option>
                                            </select>
                                        </div>
                                    </div>
                                    
                                    <div class="form-group">
                                        <label for="intl_currency">Currency</label>
                                        <div class="input-group">
                                            <span class="input-group-text">
                                                <i class="fas fa-money-bill-wave"></i>
                                            </span>
                                            <select id="intl_currency" name="intl_currency" class="form-control" required>
                                                <option value="GHC" <?= ($formData['intl_currency'] === 'GHC') ? 'selected' : '' ?>>Ghana Cedi (GHC)</option>
                                                <option value="USD" <?= ($formData['intl_currency'] === 'USD') ? 'selected' : '' ?>>US Dollar (USD)</option>
                                                <option value="GBP" <?= ($formData['intl_currency'] === 'GBP') ? 'selected' : '' ?>>British Pound (GBP)</option>
                                                <option value="EUR" <?= ($formData['intl_currency'] === 'EUR') ? 'selected' : '' ?>>Euro (EUR)</option>
                                                <option value="NGN" <?= ($formData['intl_currency'] === 'NGN') ? 'selected' : '' ?>>Nigerian Naira (NGN)</option>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Amount and Description -->
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="amount">Amount (GHC)</label>
                                    <div class="input-group">
                                        <span class="input-group-text">
                                            <i class="fas fa-money-bill-wave"></i>
                                        </span>
                                        <input type="number" 
                                               id="amount" 
                                               name="amount" 
                                               class="form-control" 
                                               min="10" 
                                               step="0.01" 
                                               placeholder="0.00"
                                               value="<?= $formData['amount'] ? htmlspecialchars($formData['amount']) : '' ?>"
                                               required>
                                    </div>
                                    <div class="text-hint">
                                        <span>Available: <strong>GHC <?= number_format($_SESSION['client_balance'], 2) ?></strong></span>
                                        <button type="button" class="btn-link" id="useMaxBalance">Use maximum</button>
                                    </div>
                                </div>
                                
                                <div class="form-group">
                                    <label for="description">Description (Optional)</label>
                                    <div class="input-group">
                                        <span class="input-group-text">
                                            <i class="fas fa-align-left"></i>
                                        </span>
                                        <textarea 
                                            id="description" 
                                            name="description" 
                                            class="form-control" 
                                            rows="2"
                                            placeholder="Add a note about this transfer (visible to recipient)"><?= htmlspecialchars($formData['description'] ?? '') ?></textarea>
                                    </div>
                                </div>
                            </div>
                            




                            <div class="form-group">
    <label for="category_id">Spending Category (Optional)</label>
    <div class="input-group">
        <span class="input-group-text">
            <i class="fas fa-tag"></i>
        </span>
        <select id="category_id" name="category_id" class="form-control">
            <option value="">-- Select Category --</option>
            <?php
            // Get categories
            $categoriesSql = "SELECT * FROM spending_categories WHERE is_active = 1 ORDER BY name";
            $categoriesResult = mysqli_query($con, $categoriesSql);
            if ($categoriesResult) {
                while ($category = mysqli_fetch_assoc($categoriesResult)) {
                    $selected = ($formData['category_id'] ?? '') == $category['id'] ? 'selected' : '';
                    echo "<option value=\"{$category['id']}\" {$selected}>{$category['name']}</option>";
                }
                mysqli_free_result($categoriesResult);
            }
            ?>
        </select>
    </div>
    <div class="text-hint">
        Help us understand your spending patterns. You can change this later.
    </div>
</div>

<!-- Add auto-categorization preview -->
<div id="categorySuggestion" class="category-suggestion" style="display: none;">
    <div class="suggestion-header">
        <i class="fas fa-lightbulb"></i>
        <span>Category Suggestion</span>
    </div>
    <div class="suggestion-content">
        <span id="suggestedCategoryName"></span>
        <button type="button" id="acceptSuggestion" class="btn-link">Use this</button>
        <button type="button" id="rejectSuggestion" class="btn-link">Ignore</button>
    </div>
</div>



                            <div class="form-group">
                                <div class="form-check">
                                    <input 
                                        type="checkbox" 
                                        class="form-check-input" 
                                        id="terms" 
                                        name="terms" 
                                        required
                                        <?= !empty($formData['terms']) ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="terms">
                                        I confirm that all the information provided is accurate and I understand that 
                                        this transaction is subject to our <a href="/terms" target="_blank" class="btn-link">Terms of Service</a>.
                                    </label>
                                </div>
                            </div>
                            
                            <div class="form-actions">
                                <button type="submit" class="btn btn-primary" id="submitButton">
                                    <i class="fas fa-arrow-right"></i> Continue to Review
                                </button>
                                <p class="text-hint text-center">
                                    <small>By clicking continue, you agree to our <a href="/pricing" class="btn-link">fee schedule</a>.</small>
                                </p>
                            </div>
                        </form>
                    </div>
                </main>
                
                <!-- Transfer Information Sidebar -->
                <aside class="transfer-aside">
                    <!-- Quick Transfer Section -->
                    <div class="info-card">
                        <h3><i class="fas fa-bolt"></i> Quick Transfer</h3>
                        <p class="text-hint">Send to recent beneficiaries</p>
                        
                        <?php if (empty($recentBeneficiaries)): ?>
                            <div class="empty-state">
                                <div class="empty-state-icon">
                                    <i class="fas fa-users"></i>
                                </div>
                                <p>No recent beneficiaries found</p>
                                <p class="text-hint">Your recent transfers will appear here for quick access</p>
                            </div>
                        <?php else: ?>
                            <ul class="quick-actions-list">
                                <?php foreach ($recentBeneficiaries as $beneficiary): ?>
                                    <li class="action_item" data-account="<?= htmlspecialchars($beneficiary['account_number']) ?>" data-name="<?= htmlspecialchars($beneficiary['name']) ?>">
                                        <i class="fas fa-user primary"></i>
                                        <span class="action_text"><?= htmlspecialchars($beneficiary['name']) ?></span>
                                        <i class="fas fa-chevron-right"></i>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Transfer Information Card -->
                    <div class="info-card">
                        <h3><i class="fas fa-info-circle"></i> Transfer Information</h3>
                        <ul class="info-list">
                            <li>
                                <i class="fas fa-clock primary"></i>
                                <span class="label">Processing Times</span>
                                <span class="value">Instant - 5 days</span>
                            </li>
                            <li>
                                <i class="fas fa-tag primary"></i>
                                <span class="label">Transfer Fees</span>
                                <span class="value">Free - 2%</span>
                            </li>
                            <li>
                                <i class="fas fa-shield-alt primary"></i>
                                <span class="label">Security Tips</span>
                                <span class="value">Always verify details</span>
                            </li>
                        </ul>
                    </div>
                    
                    <!-- Transfer Limits Card -->
                    <div class="info-card transfer-limits">
                        <h3><i class="fas fa-chart-line"></i> Transfer Limits</h3>
                        <div class="limits_container">
                            <div class="limit_item">
                                <div class="limit_label">Daily Limit</div>
                                <div class="limit_bar">
                                    <div class="limit_progress" style="width: 35%;"></div>
                                </div>
                                <div class="limit_amount">GHC 25,000 / GHC 100,000</div>
                            </div>
                            <div class="limit_item">
                                <div class="limit_label">Monthly Limit</div>
                                <div class="limit_bar">
                                    <div class="limit_progress" style="width: 15%;"></div>
                                </div>
                                <div class="limit_amount">GHC 75,000 / GHC 500,000</div>
                            </div>
                        </div>
                    </div>
                </aside>
            <!-- </section> -->
            </div>
        </section>
    </div>

    <!-- JavaScript -->
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // DOM Elements
        const transferTypeOptions = document.querySelectorAll('.transfer-type-option');
        const formRows = document.querySelectorAll('.form-row[data-transfer-type]');
        const typeInput = document.getElementById('type');
        const amountInput = document.getElementById('amount');
        const useMaxBtn = document.getElementById('useMaxBalance');
        const transferForm = document.getElementById('transferForm');
        const submitButton = document.getElementById('submitButton');
        
        // Set initial active form fields
        if (typeInput) {
            showFormFields(typeInput.value);
        }

        // Transfer type selection
        transferTypeOptions.forEach(option => {
            option.addEventListener('click', function() {
                const type = this.getAttribute('data-type');
                if (typeInput) typeInput.value = type;
                showFormFields(type);
                
                transferTypeOptions.forEach(opt => opt.classList.remove('active'));
                this.classList.add('active');
            });
        });
        
        // Show form fields based on transfer type
        function showFormFields(type) {
            formRows.forEach(row => {
                row.style.display = 'none';
                row.classList.remove('active');
                
                // Disable all fields in this row
                const fields = row.querySelectorAll('input, select, textarea');
                fields.forEach(field => {
                    field.disabled = true;
                });
            });
            
            const activeRow = document.querySelector(`.form-row[data-transfer-type="${type}"]`);
            if (activeRow) {
                activeRow.style.display = 'grid';
                activeRow.classList.add('active');
                
                // Enable all fields in this row
                const fields = activeRow.querySelectorAll('input, select, textarea');
                fields.forEach(field => {
                    field.disabled = false;
                });
            }
        }
        
        // Use maximum balance
        useMaxBtn?.addEventListener('click', function() {
            const maxBalance = <?= $_SESSION['client_balance'] ?>;
            if (amountInput) amountInput.value = maxBalance.toFixed(2);
        });

        // Quick transfer from recent beneficiaries
        document.querySelectorAll('.action_item').forEach(item => {
            item.addEventListener('click', function() {
                const account = this.getAttribute('data-account');
                const name = this.getAttribute('data-name');
                
                // Set transfer type to internal
                if (typeInput) typeInput.value = 'internal';
                showFormFields('internal');
                
                const accountInput = document.getElementById('internal_account');
                
                if (accountInput) accountInput.value = account;
                
                // Scroll to form
                document.querySelector('.transfer-form').scrollIntoView({
                    behavior: 'smooth'
                });
            });
        });

        // Form validation
        transferForm?.addEventListener('submit', function(e) {
            // Prevent default submission
            e.preventDefault();
            
            // Show loading state
            if (submitButton) {
                submitButton.disabled = true;
                submitButton.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';
            }
            
            const amount = amountInput ? parseFloat(amountInput.value) : 0;
            const balance = <?= $_SESSION['client_balance'] ?>;
            
            // Basic validation
            let isValid = true;
            let errorMessage = '';
            
            if (isNaN(amount)) {
                isValid = false;
                errorMessage = 'Please enter a valid amount';
            } else if (amount < 10) {
                isValid = false;
                errorMessage = 'Minimum transfer amount is GHC10';
            } else if (amount > 100000) {
                isValid = false;
                errorMessage = 'Maximum transfer amount is GHC100,000 per transaction';
            } else     if (amount > balance) {
        isValid = false;
        errorMessage = 'Insufficient balance for this transfer';
    }
            
            const terms = document.getElementById('terms');
            if (terms && !terms.checked) {
                isValid = false;
                errorMessage = 'You must accept the terms and conditions';
            }
            
            if (!isValid) {
                // Show error
                alert(errorMessage);
                
                // Reset button state
                if (submitButton) {
                    submitButton.disabled = false;
                    submitButton.innerHTML = '<i class="fas fa-arrow-right"></i> Continue to Review';
                }
                
                return false;
            }
            
            // If validation passes, submit the form
            this.submit();
            return true;
        });
    });
    </script>





<script>
// Add this JavaScript to transfer.php
document.addEventListener('DOMContentLoaded', function() {
    const descriptionInput = document.getElementById('description');
    const categorySelect = document.getElementById('category_id');
    const suggestionBox = document.getElementById('categorySuggestion');
    const suggestedCategoryName = document.getElementById('suggestedCategoryName');
    const acceptSuggestion = document.getElementById('acceptSuggestion');
    const rejectSuggestion = document.getElementById('rejectSuggestion');
    
    let currentSuggestion = null;
    
    // Debounce function to prevent too many requests
    function debounce(func, wait) {
        let timeout;
        return function executedFunction(...args) {
            const later = () => {
                clearTimeout(timeout);
                func(...args);
            };
            clearTimeout(timeout);
            timeout = setTimeout(later, wait);
        };
    }
    
    // Auto-categorize when description changes
    descriptionInput?.addEventListener('input', debounce(function() {
        const description = this.value;
        const transferType = document.getElementById('type').value;
        
        if (description.length > 2) {
            suggestCategory(description, transferType);
        } else {
            hideSuggestion();
        }
    }, 500));
    
    function suggestCategory(description, transferType) {
        // In a real implementation, this would be an AJAX call to a backend endpoint
        // For now, we'll do basic client-side matching
        const suggestions = {
            'uber': {id: 2, name: 'Transportation', confidence: 0.95},
            'taxi': {id: 2, name: 'Transportation', confidence: 0.90},
            'restaurant': {id: 1, name: 'Food & Dining', confidence: 0.95},
            'pizza': {id: 1, name: 'Food & Dining', confidence: 0.95},
            'supermarket': {id: 1, name: 'Food & Dining', confidence: 0.85},
            'movie': {id: 4, name: 'Entertainment', confidence: 0.90},
            'netflix': {id: 4, name: 'Entertainment', confidence: 0.95},
            'hospital': {id: 5, name: 'Health & Medical', confidence: 0.95}
        };
        
        const descLower = description.toLowerCase();
        let bestMatch = null;
        
        for (const [keyword, suggestion] of Object.entries(suggestions)) {
            if (descLower.includes(keyword) && (!bestMatch || suggestion.confidence > bestMatch.confidence)) {
                bestMatch = suggestion;
            }
        }
        
        if (bestMatch && bestMatch.confidence > 0.80) {
            showSuggestion(bestMatch);
        } else {
            hideSuggestion();
        }
    }
    
    function showSuggestion(suggestion) {
        currentSuggestion = suggestion;
        suggestedCategoryName.textContent = suggestion.name;
        suggestionBox.style.display = 'block';
    }
    
    function hideSuggestion() {
        suggestionBox.style.display = 'none';
        currentSuggestion = null;
    }
    
    acceptSuggestion?.addEventListener('click', function() {
        if (currentSuggestion) {
            categorySelect.value = currentSuggestion.id;
            hideSuggestion();
        }
    });
    
    rejectSuggestion?.addEventListener('click', function() {
        hideSuggestion();
    });
});
</script>
</body>
</html>