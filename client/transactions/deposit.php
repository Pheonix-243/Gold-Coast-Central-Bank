<?php
require_once('../includes/auth.php');
require_once('../classes/TransactionProcessor.php');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $amount = (float)$_POST['amount'];
    
    // Validate amount
    if ($amount <= 0) {
        $_SESSION['error'] = "Invalid deposit amount";
        header('Location: deposit.php');
        exit;
    }
    
    if ($amount > 50000) {
        $_SESSION['error'] = "Maximum single deposit is GHC50,000";
        header('Location: deposit.php');
        exit;
    }
    
    // Store pending deposit in session
    $_SESSION['pending_deposit'] = [
        'amount' => $amount
    ];
    
    // Redirect to Paystack processing
    header('Location: process_paystack.php');
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gold Coast Central Bank - Deposit Funds</title>
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <!-- Custom CSS -->
    <link rel="stylesheet" href="../dashboard/style.css">
    <link rel="stylesheet" href="deposit.css">
</head>

<body>
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

                <a href="../transactions/deposit.php" class="nav_link active" aria-label="deposit">
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

                <a href="../settings/password.php" class="nav_link" aria-label="settings">
                    <div class="nav_link_icon">
                        <i class="fas fa-lock"></i>
                    </div>
                    <div class="nav_link_text">Security</div>
                </a>
            </div>

            <div class="profile">
                <div class="img_with_name">
                    <img src="../images/default-profile.png" alt="Profile Picture">
                    <div class="profile_text">
                        <p class="name"><?= htmlspecialchars($_SESSION['client_name']) ?></p>
                        <p class="occupation"><?= htmlspecialchars($_SESSION['client_account_type'] ?? 'Account') ?></p>
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
                    <h1>Deposit Funds</h1>
                    <p class="welcome">Add money to your account securely</p>
                </div>

                <div class="topbar_icons">
                    <a href="#" aria-label="notifications" class="topbar_icon alert">
                        <i class="fas fa-bell"></i>
                    </a>
                </div>
            </div>

            <section class="deposit_section">
                <main class="deposit_main">
                    <div class="deposit_card">
                        <?php if (isset($_SESSION['error'])): ?>
                            <div class="alert alert-danger">
                                <i class="fas fa-exclamation-circle"></i> <?= $_SESSION['error'] ?>
                            </div>
                            <?php unset($_SESSION['error']); ?>
                        <?php endif; ?>
                        
                        <div class="deposit_header">
                            <div class="deposit_icon">
                                <i class="fas fa-money-bill-wave"></i>
                            </div>
                            <div>
                                <h2>Make a Deposit</h2>
                                <p>Fund your account using our secure payment methods</p>
                            </div>
                        </div>
                        
                        <form method="POST" id="depositForm" class="deposit_form">
                            <div class="form_group">
                                <label for="amount">Amount (GHC)</label>
                                <div class="input_with_icon">
                                    <i class="fas fa-ghost"></i>
                                    <input type="number" id="amount" name="amount" 
                                           min="10" step="0.01" required
                                           placeholder="Enter amount">
                                </div>
                                <small class="text_hint">Minimum deposit: GHC10</small>
                            </div>
                            
                            <div class="payment_methods">
                                <h3>Select Payment Method</h3>
                                <div class="method_grid">
                                    <div class="method_card active">
                                        <div class="method_icon">
                                            <i class="fab fa-cc-mastercard"></i>
                                        </div>
                                        <span>Card Payment</span>
                                    </div>
                                    <div class="method_card">
                                        <div class="method_icon">
                                            <i class="fas fa-mobile-alt"></i>
                                        </div>
                                        <span>Mobile Money</span>
                                    </div>
                                    <div class="method_card">
                                        <div class="method_icon">
                                            <i class="fas fa-university"></i>
                                        </div>
                                        <span>Bank Transfer</span>
                                    </div>
                                </div>
                            </div>
                            
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-lock"></i> Proceed to Secure Payment
                            </button>
                        </form>
                    </div>
                    
                    <div class="security_features">
                        <div class="security_item">
                            <div class="security_icon">
                                <i class="fas fa-shield-alt"></i>
                            </div>
                            <div class="security_details">
                                <h4>Bank-Level Security</h4>
                                <p>256-bit encryption protects all transactions</p>
                            </div>
                        </div>
                        <div class="security_item">
                            <div class="security_icon">
                                <i class="fas fa-bolt"></i>
                            </div>
                            <div class="security_details">
                                <h4>Instant Processing</h4>
                                <p>Funds available immediately after deposit</p>
                            </div>
                        </div>
                    </div>
                </main>
                
                <aside class="deposit_aside">
                    <div class="account_summary">
                        <h3><i class="fas fa-user-circle"></i> Account Summary</h3>
                        <div class="summary_item">
                            <div class="summary_icon">
                                <i class="fas fa-user"></i>
                            </div>
                            <div class="summary_details">
                                <p class="summary_label">Account Holder</p>
                                <p class="summary_value"><?= htmlspecialchars($_SESSION['client_name']) ?></p>
                            </div>
                        </div>
                        <div class="summary_item">
                            <div class="summary_icon">
                                <i class="fas fa-id-card"></i>
                            </div>
                            <div class="summary_details">
                                <p class="summary_label">Account Number</p>
                                <p class="summary_value"><?= htmlspecialchars($_SESSION['client_account']) ?></p>
                            </div>
                        </div>
                        <div class="summary_item">
                            <div class="summary_icon">
                                <i class="fas fa-wallet"></i>
                            </div>
                            <div class="summary_details">
                                <p class="summary_label">Current Balance</p>
                                <p class="summary_value">GHC<?= number_format($_SESSION['client_balance'], 2) ?></p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="deposit_info">
                        <h3><i class="fas fa-info-circle"></i> Deposit Information</h3>
                        <ul class="info_list">
                            <li>
                                <i class="fas fa-check-circle success"></i>
                                <span>Minimum: GHC10</span>
                            </li>
                            <li>
                                <i class="fas fa-check-circle success"></i>
                                <span>Maximum per transaction: GHC50,000</span>
                            </li>
                            <li>
                                <i class="fas fa-check-circle success"></i>
                                <span>Daily limit: GHC200,000</span>
                            </li>
                            <li>
                                <i class="fas fa-check-circle success"></i>
                                <span>No fees for deposits</span>
                            </li>
                        </ul>
                    </div>
                    
                    <div class="help_card">
                        <h3><i class="fas fa-headset"></i> Need Help?</h3>
                        <p>Our customer support team is available 24/7 to assist with your deposits.</p>
                        <a href="#" class="btn btn-outline">
                            <i class="fas fa-phone"></i> Contact Support
                        </a>
                    </div>
                </aside>
            </section>
        </section>
    </div>

    <!-- Custom JS -->
    <script src="../js/main.js"></script>
    <script>
        // Mobile sidebar toggle
        document.getElementById('menu_btn').addEventListener('click', function() {
            document.getElementById('sidebar').classList.toggle('show_sidebar');
        });

        document.getElementById('btn_close').addEventListener('click', function() {
            document.getElementById('sidebar').classList.remove('show_sidebar');
        });

        // Payment method selection
        const methodCards = document.querySelectorAll('.method_card');
        methodCards.forEach(card => {
            card.addEventListener('click', () => {
                methodCards.forEach(c => c.classList.remove('active'));
                card.classList.add('active');
            });
        });

        // Format amount input
        const amountInput = document.getElementById('amount');
        amountInput.addEventListener('blur', function() {
            if (this.value) {
                this.value = parseFloat(this.value).toFixed(2);
            }
        });
    </script>
</body>
</html>