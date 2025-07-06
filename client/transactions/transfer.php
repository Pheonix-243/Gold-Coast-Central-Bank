<?php
require_once('../includes/auth.php');
// require_once('../includes/header.php');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['confirm'])) {
    // First step: Validate and show confirmation
    $transferType = trim($_POST['type']);
    $recipientAccount = trim($_POST['account']);
    $amount = (float)$_POST['amount'];
    $description = trim($_POST['description'] ?? '');
    $recipientName = trim($_POST['name']);
    
    // Validate amount
    if ($amount < 100) {
        $_SESSION['error'] = "Minimum transfer amount is GHC100";
        header('Location: transfer.php');
        exit;
    }
    
    if ($amount > 100000) {
        $_SESSION['error'] = "Maximum transfer amount is GHC100,000 per transaction";
        header('Location: transfer.php');
        exit;
    }
    
    // Check sender balance
    if ($_SESSION['client_balance'] < $amount) {
        $_SESSION['error'] = "Insufficient balance for this transfer";
        header('Location: transfer.php');
        exit;
    }
    
    // For internal transfers, verify recipient account
    if ($transferType === 'internal') {
        $sql = "SELECT h.name FROM accountsholder h 
                JOIN accounts_info a ON h.account = a.account 
                WHERE h.account = ? AND a.status = 'Active'";
        $stmt = mysqli_prepare($con, $sql);
        mysqli_stmt_bind_param($stmt, "s", $recipientAccount);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $recipient = mysqli_fetch_assoc($result);
        
        if (!$recipient) {
            $_SESSION['error'] = "Recipient account not found or inactive";
            header('Location: transfer.php');
            exit;
        }
        $recipientName = $recipient['name'];
    }
    
    // Store all data in session for confirmation
    $_SESSION['transfer_data'] = [
        'transferType' => $transferType,
        'senderAccount' => $_SESSION['client_account'],
        'senderName' => $_SESSION['client_name'],
        'recipientAccount' => $recipientAccount,
        'recipientName' => $recipientName,
        'amount' => $amount,
        'description' => $description,
        'bank' => $_POST['bank'] ?? null,
        'network' => $_POST['network'] ?? null,
        'swift' => $_POST['swift'] ?? null,
        'country' => $_POST['country'] ?? null
    ];
    
    // Show confirmation page
    header('Location: confirm_transfer.php');
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gold Coast Central Bank - Transfer Funds</title>
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <!-- Custom CSS -->
    <link rel="stylesheet" href="../dashboard/style.css">
    <link rel="stylesheet" href="transfer.css">
</head>

<body class="light">
    <div class="container">
        <nav class="sidebar" id="sidebar">
            <button id="btn_close">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="16" height="16">
                    <path fill="none" d="M0 0h24v24H0z" />
                    <path d="M12 10.586l4.95-4.95 1.414 1.414-4.95 4.95 4.95 4.95-1.414 1.414-4.95-4.95-4.95 4.95-1.414-1.414 4.95-4.95-4.95-4.95L7.05 5.636z" />
                </svg>
            </button>

            <div class="logo">
                <img src="../../gccb_logos/logo-transparent.svg" alt="">
                <!-- <span>Gold Coast Central Bank</span> -->
            </div>

            <div class="nav_links">
                <a href="../dashboard/" class="nav_link active" aria-label="overview">
                    <div class="nav_link_icon">
                        <i class="fas fa-home"></i>
                    </div>
                    <div class="nav_link_text">Dashboard</div>
                </a>

                <a href="../transactions/deposit.php" class="nav_link" aria-label="deposit">
                    <div class="nav_link_icon">
                        <i class="fas fa-money-bill-wave"></i>
                    </div>
                    <div class="nav_link_text">Deposit</div>
                </a>

                <a href="../transactions/withdrawal.php" class="nav_link" aria-label="withdraw">
                    <div class="nav_link_icon">
                        <i class="fas fa-hand-holding-usd"></i>
                    </div>
                    <div class="nav_link_text">Withdraw</div>
                </a>

                <a href="../transactions/transfer.php" class="nav_link" aria-label="transfer">
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

                <a href="../profile/view.php" class="nav_link" aria-label="profile">
                    <div class="nav_link_icon">
                        <i class="fas fa-user"></i>
                    </div>
                    <div class="nav_link_text">Profile</div>
                </a>

                <a href="../settings/password.php" class="nav_link" aria-label="settings">
                    <div class="nav_link_icon">
                        <i class="fas fa-lock"></i>
                    </div>
                    <div class="nav_link_text">Security</div>
                </a>
            </div>

            <div class="profile">
                <div class="img_with_name">
                    <img src="images/profile_pic3.webp" alt="">
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



                  <section class="main_content">
            <div class="topbar">
                <button id="menu_btn">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="16" height="16">
                        <path fill="none" d="M0 0h24v24H0z" />
                        <path d="M3 4h18v2H3V4zm0 7h12v2H3v-2zm0 7h18v2H3v-2z" /></svg>
                </button>
                <div class="overview_text">
                    <p class="title">Transfer Funds</p>
                    <p class="desc">Send money to other accounts</p>
                </div>

                <div class="topbar_icons">
                    <a href="#" aria-label="search" class="topbar_icon">
                        <i class="fas fa-search"></i>
                    </a>
                    <a href="#" aria-label="notifications" class="topbar_icon alert">
                        <i class="fas fa-bell"></i>
                    </a>
                </div>
            </div>

            <div class="transfer-container">
                <div class="transfer-row">
                    <div class="transfer-main">
                        <div class="transfer-card">
                            <?php if (isset($_SESSION['error'])): ?>
                                <div class="alert alert-danger"><?= $_SESSION['error'] ?></div>
                                <?php unset($_SESSION['error']); ?>
                            <?php endif; ?>


                                <div class="transfer-type">
                                <div class="transfer-type-icon">
                                    <i class="fas fa-exchange-alt"></i>
                                </div>
                                <div>
                                    <h3>New Transfer</h3>
                                    <p>Send money to accounts and mobile wallets</p>
                                </div>
                            </div>
                            
                            <form method="POST" id="transferForm" class="transfer-form">
                                <div class="form-group">
                                    <label for="type" class="form-label">Transfer Type</label>
                                    <select class="form-control" id="type" name="type" required onchange="onTypeChange()">
                                        <option value="">-- Select Transfer Type --</option>
                                        <option value="internal">Internal (Same Bank)</option>
                                        <option value="domestic">Domestic (Other Bank Ghana)</option>
                                        <option value="international">International</option>
                                        <option value="mobile_money">Mobile Money</option>
                                    </select>
                                </div>
                                
                                <div id="dynamicFields"></div>
                                
                                <div class="form-group">
                                    <label for="amount" class="form-label">Amount (GHC)</label>
                                    <input type="number" class="form-control" id="amount" name="amount" 
                                           min="100" max="100000" step="100" required>
                                    <small class="text-muted">Available: GHC<?= number_format($_SESSION['client_balance'], 2) ?></small>
                                </div>
                                
                                <div class="form-group">
                                    <label for="description" class="form-label">Description</label>
                                    <textarea class="form-control" id="description" name="description" rows="2"></textarea>
                                </div>
                                
                                <button type="submit" class="btn-transfer">Continue</button>
                            </form>
                        </div>
                    </div>
                    
                    <!-- <div class="transfer-sidebar">
                        <div class="transfer-rules">
                            <h5>Transfer Rules</h5>
                            <ul class="list-group list-group-flush">
                                <li class="list-group-item">Minimum: GHC100</li>
                                <li class="list-group-item">Maximum: GHC100,000 per transfer</li>
                                <li class="list-group-item">Daily limit: GHC500,000</li>
                                <li class="list-group-item">Processing: Instant</li>
                            </ul>
                        </div>
                    </div>
                </div> -->





                           <div class="transfer-sidebar">
                        <div class="transfer-rules">
                            <h5><i class="fas fa-info-circle"></i> Transfer Information</h5>
                            <ul class="list-group list-group-flush">
                                <li class="list-group-item">
                                    <i class="fas fa-check-circle text-success"></i> Minimum: GHC100
                                </li>
                                <li class="list-group-item">
                                    <i class="fas fa-check-circle text-success"></i> Maximum: GHC100,000 per transfer
                                </li>
                                <li class="list-group-item">
                                    <i class="fas fa-check-circle text-success"></i> Daily limit: GHC500,000
                                </li>
                                <li class="list-group-item">
                                    <i class="fas fa-bolt text-warning"></i> Processing: Instant
                                </li>
                                <li class="list-group-item">
                                    <i class="fas fa-shield-alt text-primary"></i> Secured with SSL Encryption
                                </li>
                            </ul>
                        </div>
                        
                        <div class="transfer-rules mt-4">
                            <h5><i class="fas fa-clock"></i> Processing Times</h5>
                            <ul class="list-group list-group-flush">
                                <li class="list-group-item">
                                    <strong>Internal:</strong> Instant
                                </li>
                                <li class="list-group-item">
                                    <strong>Domestic:</strong> Within 1 hour
                                </li>
                                <li class="list-group-item">
                                    <strong>International:</strong> 1-3 business days
                                </li>
                                <li class="list-group-item">
                                    <strong>Mobile Money:</strong> Instant to 30 mins
                                </li>
                            </ul>
                        </div>
                    </div>
            </div>
        </section>




        
    </div>

    <!-- Swiper JS -->
    <script src="https://cdn.jsdelivr.net/npm/swiper/swiper-bundle.min.js"></script>

    <!-- Custom JS -->
    <script src="../js/main.js"></script>
    <script>
    // AJAX account verification for internal transfers
    function verifyInternalAccount(account) {
        if (account.length < 5) return;
        
        fetch('../scripts/get_receiver_name.php?account=' + account)
            .then(response => response.json())
            .then(data => {
                const infoDiv = document.getElementById('recipientInfo');
                if (data.error) {
                    infoDiv.innerHTML = `<div class="alert alert-danger">${data.error}</div>`;
                } else if (data.name) {
                    infoDiv.innerHTML = `
                        <div class="alert alert-success">
                            <strong>Account Verified:</strong> ${data.name}
                        </div>`;
                    // Auto-fill the name field if it exists
                    const nameField = document.querySelector('input[name="name"]');
                    if (nameField) nameField.value = data.name;
                }
            })
            .catch(error => {
                console.error('Error:', error);
            });
    }

    async function onTypeChange() {
        const type = document.getElementById('type').value;
        const container = document.getElementById('dynamicFields');
        let html = '';

        if (type === 'internal') {
            html = `
                <div class="form-group">
                    <label for="account" class="form-label">Recipient Account</label>
                    <input type="text" class="form-control" id="account" name="account" required 
                        onblur="verifyInternalAccount(this.value)">
                    <div id="recipientInfo" class="mt-2"></div>
                </div>
                <input type="hidden" name="name" value="">`;
        } 
        else if (type === 'domestic') {
            html = `
                <div class="form-group">
                    <label for="bank" class="form-label">Bank Name</label>
                    <input type="text" class="form-control" id="bank" name="bank" required>
                </div>
                <div class="form-group">
                    <label for="account" class="form-label">Recipient Account</label>
                    <input type="text" class="form-control" id="account" name="account" required>
                </div>
                <div class="form-group">
                    <label for="name" class="form-label">Recipient Full Name</label>
                    <input type="text" class="form-control" id="name" name="name" required>
                </div>`;
        }
        else if (type === 'international') {
            // Fetch countries for dropdown
            try {
                const countriesResponse = await fetch('https://restcountries.com/v3.1/all');
                const countries = await countriesResponse.json();
                const countryOptions = countries.map(c => 
                    `<option value="${c.name.common}">${c.name.common}</option>`
                ).join('');
                
                html = `
                    <div class="form-group">
                        <label for="name" class="form-label">Recipient Full Name</label>
                        <input type="text" class="form-control" id="name" name="name" required>
                    </div>
                    <div class="form-group">
                        <label for="bank" class="form-label">Bank Name</label>
                        <input type="text" class="form-control" id="bank" name="bank" required>
                    </div>
                    <div class="form-group">
                        <label for="account" class="form-label">Account/IBAN</label>
                        <input type="text" class="form-control" id="account" name="account" required>
                    </div>
                    <div class="form-group">
                        <label for="swift" class="form-label">SWIFT/BIC Code</label>
                        <input type="text" class="form-control" id="swift" name="swift" required>
                    </div>
                    <div class="form-group">
                        <label for="country" class="form-label">Country</label>
                        <select class="form-control" id="country" name="country" required>
                            <option value="">-- Select Country --</option>
                            ${countryOptions}
                        </select>
                    </div>`;
            } catch (error) {
                console.error('Failed to load countries:', error);
                html = `<div class="alert alert-danger">Failed to load country list. Please try again.</div>`;
            }
        }
        else if (type === 'mobile_money') {
            html = `
                <div class="form-group">
                    <label for="network" class="form-label">Mobile Network</label>
                    <select class="form-control" id="network" name="network" required>
                        <option value="">-- Select Network --</option>
                        <option value="MTN">MTN</option>
                        <option value="Vodafone">Vodafone</option>
                        <option value="AirtelTigo">AirtelTigo</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="account" class="form-label">Mobile Money Number</label>
                    <input type="text" class="form-control" id="account" name="account" required>
                </div>
                <div class="form-group">
                    <label for="name" class="form-label">Recipient Name</label>
                    <input type="text" class="form-control" id="name" name="name" required>
                </div>`;
        }
        
        container.innerHTML = html;
    }
    </script>
</body>
</html>