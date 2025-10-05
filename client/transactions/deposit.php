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
    <style>
        /* Premium Deposit Page Styles */
        .deposit_section {
            display: grid;
            grid-template-columns: 1fr 350px;
            gap: var(--space-xl);
            padding: var(--space-xl);
            max-width: 1400px;
            margin: 0 auto;
            width: 100%;
        }
        
        .deposit_main {
            display: flex;
            flex-direction: column;
            gap: var(--space-lg);
        }
        
        .deposit_card {
            background: var(--bg-white);
            border-radius: var(--radius-2xl);
            padding: var(--space-2xl);
            box-shadow: var(--shadow-md);
            transition: var(--transition);
            position: relative;
            overflow: hidden;
        }
        
        .deposit_card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: linear-gradient(90deg, var(--primary) 0%, var(--gold-primary) 100%);
        }
        
        .deposit_card:hover {
            box-shadow: var(--shadow-lg);
            transform: translateY(-2px);
        }
        
        .deposit_header {
            display: flex;
            align-items: center;
            gap: var(--space-lg);
            margin-bottom: var(--space-2xl);
        }
        
        .deposit_icon {
            width: 70px;
            height: 70px;
            border-radius: var(--radius-xl);
            background: linear-gradient(135deg, var(--navy-primary) 0%, var(--navy-secondary) 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--gold-primary);
            font-size: var(--font-size-2xl);
            box-shadow: var(--shadow-gold);
        }
        
        .deposit_header h2 {
            font-size: var(--font-size-2xl);
            font-weight: 700;
            color: var(--text-primary);
            margin-bottom: 4px;
        }
        
        .deposit_header p {
            color: var(--text-secondary);
            font-size: var(--font-size-sm);
        }
        
        .deposit_form .form_group {
            margin-bottom: var(--space-lg);
        }
        
        .deposit_form label {
            display: block;
            margin-bottom: var(--space-sm);
            font-weight: 600;
            color: var(--text-primary);
            font-size: var(--font-size-sm);
        }
        
        .input_with_icon {
            position: relative;
        }
        
        .input_with_icon i {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-muted);
            z-index: 2;
        }
        
        .deposit_form input {
            width: 100%;
            padding: 14px 15px 14px 45px;
            border-radius: var(--radius-lg);
            border: 1px solid var(--border-light);
            background-color: var(--bg-secondary);
            font-size: var(--font-size-base);
            transition: var(--transition);
            position: relative;
            z-index: 1;
        }
        
        .deposit_form input:focus {
            outline: none;
            border-color: var(--primary);
            background-color: var(--bg-white);
            box-shadow: 0 0 0 3px rgba(30, 64, 175, 0.1);
        }
        
        .text_hint {
            display: block;
            margin-top: var(--space-xs);
            font-size: var(--font-size-xs);
            color: var(--text-muted);
        }
        
        /* Payment Methods */
        .payment_methods {
            margin: var(--space-xl) 0;
        }
        
        .payment_methods h3 {
            font-size: var(--font-size-base);
            color: var(--text-primary);
            margin-bottom: var(--space-md);
            font-weight: 600;
        }
        
        .method_grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: var(--space-md);
            margin-bottom: var(--space-md);
        }
        
        .method_card {
            background: var(--bg-secondary);
            border: 1px solid var(--border-light);
            border-radius: var(--radius-lg);
            padding: var(--space-lg);
            text-align: center;
            cursor: pointer;
            transition: var(--transition);
            position: relative;
            overflow: hidden;
        }
        
        .method_card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: linear-gradient(135deg, rgba(30, 64, 175, 0.05) 0%, rgba(212, 175, 55, 0.05) 100%);
            opacity: 0;
            transition: var(--transition);
        }
        
        .method_card:hover {
            border-color: var(--primary);
            transform: translateY(-2px);
            box-shadow: var(--shadow-sm);
        }
        
        .method_card:hover::before {
            opacity: 1;
        }
        
        .method_card.active {
            border-color: var(--primary);
            background: linear-gradient(135deg, rgba(30, 64, 175, 0.1) 0%, rgba(212, 175, 55, 0.1) 100%);
            box-shadow: 0 0 0 1px var(--primary);
        }
        
        .method_icon {
            width: 50px;
            height: 50px;
            margin: 0 auto var(--space-sm);
            border-radius: var(--radius-lg);
            background-color: rgba(27, 54, 93, 0.1);
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--navy-primary);
            font-size: var(--font-size-lg);
            transition: var(--transition);
        }
        
        .method_card.active .method_icon {
            background-color: var(--primary);
            color: var(--text-white);
        }
        
        .method_card span {
            font-size: var(--font-size-sm);
            font-weight: 500;
            color: var(--text-primary);
        }
        
        .method_card.active span {
            font-weight: 600;
        }
        
        /* Security Features */
        .security_features {
            background: var(--bg-white);
            border-radius: var(--radius-xl);
            padding: var(--space-lg);
            box-shadow: var(--shadow-md);
            transition: var(--transition);
        }
        
        .security_features:hover {
            box-shadow: var(--shadow-lg);
            transform: translateY(-2px);
        }
        
        .security_item {
            display: flex;
            gap: var(--space-lg);
            margin-bottom: var(--space-lg);
            align-items: flex-start;
        }
        
        .security_item:last-child {
            margin-bottom: 0;
        }
        
        .security_icon {
            width: 50px;
            height: 50px;
            border-radius: var(--radius-lg);
            background: linear-gradient(135deg, var(--navy-primary) 0%, var(--navy-secondary) 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--gold-primary);
            font-size: var(--font-size-lg);
            flex-shrink: 0;
        }
        
        .security_details h4 {
            font-size: var(--font-size-base);
            color: var(--text-primary);
            margin-bottom: 4px;
            font-weight: 600;
        }
        
        .security_details p {
            font-size: var(--font-size-sm);
            color: var(--text-secondary);
            margin: 0;
        }
        
        /* Sidebar Styles */
        .deposit_aside {
            display: flex;
            flex-direction: column;
            gap: var(--space-lg);
        }
        
        .account_summary, .deposit_info, .help_card {
            background: var(--bg-white);
            border-radius: var(--radius-xl);
            padding: var(--space-lg);
            box-shadow: var(--shadow-md);
            transition: var(--transition);
        }
        
        .account_summary {
              background: linear-gradient(135deg, var(--navy-primary) 0%, var(--navy-secondary) 100%);
            color: var(--text-white);
            position: relative;
            overflow: hidden;
        }
        
        .account_summary::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -50%;
            width: 150px;
            height: 150px;
            background: radial-gradient(circle, rgba(212, 175, 55, 0.1) 0%, transparent 70%);
            border-radius: 50%;
        }
        
        .account_summary h3, .deposit_info h3, .help_card h3 {
            color: var(--text-white);
            font-size: var(--font-size-lg);
            margin-bottom: var(--space-lg);
            display: flex;
            align-items: center;
            gap: var(--space-sm);
            position: relative;
            z-index: 2;
        }
        
        .deposit_info h3, .help_card h3 {
            color: var(--text-primary);
        }
        
        .summary_item {
            display: flex;
            gap: var(--space-md);
            margin-bottom: var(--space-lg);
            align-items: center;
            position: relative;
            z-index: 2;
        }
        
        .summary_item:last-child {
            margin-bottom: 0;
        }
        
        .summary_icon {
            width: 40px;
            height: 40px;
            border-radius: var(--radius-lg);
            background-color: rgba(255, 255, 255, 0.1);
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--text-white);
            font-size: var(--font-size-sm);
            flex-shrink: 0;
        }
        
        .summary_details .summary_label {
            font-size: var(--font-size-xs);
            color: rgba(255, 255, 255, 0.8);
            margin-bottom: 4px;
        }
        
        .summary_details .summary_value {
            font-size: var(--font-size-sm);
            font-weight: 600;
            color: var(--text-white);
        }
        
        .info_list {
            list-style: none;
        }
        
        .info_list li {
            display: flex;
            align-items: center;
            gap: var(--space-sm);
            padding: var(--space-sm) 0;
            border-bottom: 1px solid var(--border-light);
        }
        
        .info_list li:last-child {
            border-bottom: none;
        }
        
        .info_list i {
            font-size: var(--font-size-sm);
        }
        
        .info_list i.success {
            color: var(--success);
        }
        
        .info_list span {
            font-size: var(--font-size-sm);
            color: var(--text-primary);
        }
        
        .help_card p {
            font-size: var(--font-size-sm);
            color: var(--text-secondary);
            margin-bottom: var(--space-lg);
        }
        

        
        /* Button Styles */
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: var(--space-sm);
            padding: 14px 24px;
            border-radius: var(--radius-lg);
            font-weight: 600;
            font-size: var(--font-size-sm);
            cursor: pointer;
            transition: var(--transition);
            text-decoration: none;
            border: none;
            width: 100%;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-light) 100%);
            color: var(--text-white);
            box-shadow: var(--shadow-md);
        }
        
        .btn-primary:hover {
            background: linear-gradient(135deg, var(--primary-dark) 0%, var(--primary) 100%);
            transform: translateY(-2px);
            box-shadow: var(--shadow-lg);
        }
        
        .btn-outline {
            background: transparent;
            border: 1px solid var(--border-medium);
            color: var(--text-primary);
        }
        
        .btn-outline:hover {
            background-color: var(--bg-secondary);
            border-color: var(--primary);
            color: var(--primary);
        }
        
        /* Responsive Styles */
        @media (max-width: 1024px) {
            .deposit_section {
                grid-template-columns: 1fr;
            }
            
            .deposit_aside {
                margin-top: var(--space-lg);
            }
        }
        
        @media (max-width: 768px) {
            .method_grid {
                grid-template-columns: 1fr;
            }
            
            .deposit_card {
                padding: var(--space-lg);
            }
            
            .deposit_header {
                flex-direction: column;
                align-items: flex-start;
                gap: var(--space-md);
            }
            
            .deposit_icon {
                width: 60px;
                height: 60px;
                font-size: var(--font-size-xl);
            }
        }
    </style>
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

                <a href="../pages/security.php" class="nav_link" aria-label="settings">
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