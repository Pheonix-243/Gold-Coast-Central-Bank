<?php
require_once('../includes/auth.php');
// require_once('../includes/header.php');
require_once('../classes/TransactionProcessor.php');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $amount = (float)$_POST['amount'];
    $method = $_POST['method'] ?? 'Mobile Money';
    $destination = $_POST['destination'] ?? '';
    $description = $_POST['description'] ?? '';
    
    // Validate amount
    if ($amount < 100) {
        $_SESSION['error'] = "Minimum withdrawal amount is GHC100";
        header('Location: withdrawal.php');
        exit;
    }
    
    if ($amount > 50000) {
        $_SESSION['error'] = "Maximum withdrawal amount is GHC50,000 per transaction";
        header('Location: withdrawal.php');
        exit;
    }
    
    if ($_SESSION['client_balance'] < $amount) {
        $_SESSION['error'] = "Insufficient balance for this withdrawal";
        header('Location: withdrawal.php');
        exit;
    }
    
    // Process withdrawal
    $transactionProcessor = new TransactionProcessor($con);
    $result = $transactionProcessor->processWithdrawal(
        $_SESSION['client_account'],
        $amount,
        $method,
        $destination,
        $description
    );
    
    if ($result['status'] === 'success') {
        $_SESSION['success'] = "Withdrawal of GHC" . number_format($amount, 2) . " was successful!";
        $_SESSION['client_balance'] -= $amount;
        header('Location: ../dashboard/');
        exit;
    } else {
        $_SESSION['error'] = "Withdrawal failed: " . $result['message'];
        header('Location: withdrawal.php');
        exit;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gold Coast Central Bank - Withdraw Funds</title>
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <!-- Custom CSS -->
    <link rel="stylesheet" href="../dashboard/style.css">
    <link rel="stylesheet" href="withdrawal.css">
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
                        <i class="fas fa-money-bill-wave"></i>
                    </div>
                    <div class="nav_link_text">Deposit</div>
                </a>

                <a href="../transactions/withdrawal.php" class="nav_link active" aria-label="withdraw">
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
                    <img src="../images/profile_pic3.webp" alt="">
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
                    <p class="title">Withdraw Funds</p>
                    <p class="desc">Transfer money from your account</p>
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

            <div class="withdrawal-container">
                <div class="withdrawal-header">
                    <h1>Withdraw Funds</h1>
                    <p class="subtitle">Transfer money from your account to your preferred destination</p>
                </div>
                
                <div class="withdrawal-row">
                    <div class="withdrawal-main">
                        <div class="withdrawal-card">
                            <?php if (isset($_SESSION['error'])): ?>
                                <div class="alert alert-danger"><?= $_SESSION['error'] ?></div>
                                <?php unset($_SESSION['error']); ?>
                            <?php endif; ?>
                            
                            <form method="POST" id="withdrawalForm" class="withdrawal-form">
                                <div class="form-group">
                                    <label for="amount" class="form-label">Amount (GHC)</label>
                                    <div class="input-with-icon">
                                        <i class="fas fa-money-bill-wave"></i>
                                        <input type="number" class="form-control" id="amount" name="amount" 
                                               min="100" step="100" max="50000" required
                                               value="<?= isset($_POST['amount']) ? htmlspecialchars($_POST['amount']) : '' ?>">
                                    </div>
                                    <small class="text-muted">Available: GHC<?= number_format($_SESSION['client_balance'], 2) ?></small>
                                </div>
                                
                                <div class="form-group">
                                    <label for="method" class="form-label">Withdrawal Method</label>
                                    <div class="method-options">
                                        <div class="method-option" data-method="Mobile Money">
                                            <i class="fas fa-mobile-alt"></i>
                                            <span>Mobile Money</span>
                                        </div>
                                        <div class="method-option" data-method="Bank Transfer">
                                            <i class="fas fa-university"></i>
                                            <span>Bank Transfer</span>
                                        </div>
                                    </div>
                                    <input type="hidden" name="method" id="method" value="Mobile Money">
                                </div>
                                
                                <div class="form-group" id="destinationField">
                                    <label for="destination" class="form-label">Destination Details</label>
                                    <div class="input-with-icon">
                                        <i class="fas fa-wallet"></i>
                                        <input type="text" class="form-control" id="destination" name="destination" 
                                               placeholder="e.g., MTN - 024XXXXXXX or UBA - 3030****"
                                               value="<?= isset($_POST['destination']) ? htmlspecialchars($_POST['destination']) : '' ?>">
                                    </div>
                                </div>
                                
                                <div class="form-group">
                                    <label for="description" class="form-label">Description (Optional)</label>
                                    <textarea class="form-control" id="description" name="description" rows="2"><?= isset($_POST['description']) ? htmlspecialchars($_POST['description']) : '' ?></textarea>
                                </div>
                                
                                <button type="submit" class="btn-withdrawal">
                                    <i class="fas fa-paper-plane"></i> Process Withdrawal
                                </button>
                            </form>
                        </div>
                        
                        <div class="security-info">
                            <div class="security-item">
                                <i class="fas fa-shield-alt"></i>
                                <div>
                                    <h4>Secure Transactions</h4>
                                    <p>All withdrawals are protected with bank-level security</p>
                                </div>
                            </div>
                            <div class="security-item">
                                <i class="fas fa-clock"></i>
                                <div>
                                    <h4>Fast Processing</h4>
                                    <p>Most withdrawals are processed within minutes</p>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="withdrawal-sidebar">
                        <div class="account-summary">
                            <h3>Account Summary</h3>
                            <div class="summary-item">
                                <i class="fas fa-user"></i>
                                <div>
                                    <p class="label">Account Holder</p>
                                    <p class="value"><?= htmlspecialchars($_SESSION['client_name']) ?></p>
                                </div>
                            </div>
                            <div class="summary-item">
                                <i class="fas fa-id-card"></i>
                                <div>
                                    <p class="label">Account Number</p>
                                    <p class="value"><?= htmlspecialchars($_SESSION['client_account']) ?></p>
                                </div>
                            </div>
                            <div class="summary-item">
                                <i class="fas fa-wallet"></i>
                                <div>
                                    <p class="label">Current Balance</p>
                                    <p class="value">GHC<?= number_format($_SESSION['client_balance'], 2) ?></p>
                                </div>
                            </div>
                        </div>
                        
                        <div class="withdrawal-limits">
                            <h3>Withdrawal Limits</h3>
                            <ul class="limits-list">
                                <li>
                                    <i class="fas fa-check-circle"></i>
                                    <span>Minimum: GHC100</span>
                                </li>
                                <li>
                                    <i class="fas fa-check-circle"></i>
                                    <span>Maximum per transaction: GHC50,000</span>
                                </li>
                                <li>
                                    <i class="fas fa-check-circle"></i>
                                    <span>Daily limit: GHC200,000</span>
                                </li>
                                <li>
                                    <i class="fas fa-check-circle"></i>
                                    <span>No fees for withdrawals</span>
                                </li>
                            </ul>
                        </div>
                        
                        <div class="recent-withdrawals">
                            <h3>Recent Withdrawals</h3>
                            <?php
                            $recentSql = "SELECT amount, dt, tm, method 
                                          FROM account_history 
                                          WHERE account = ? AND sender = account 
                                          ORDER BY dt DESC, tm DESC LIMIT 3";
                            $stmt = mysqli_prepare($con, $recentSql);
                            mysqli_stmt_bind_param($stmt, "s", $_SESSION['client_account']);
                            mysqli_stmt_execute($stmt);
                            $recentWithdrawals = mysqli_stmt_get_result($stmt);
                            
                            if (mysqli_num_rows($recentWithdrawals) > 0): ?>
                                <ul class="withdrawals-list">
                                    <?php while ($withdrawal = mysqli_fetch_assoc($recentWithdrawals)): ?>
                                        <li>
                                            <div class="withdrawal-method">
                                                <i class="<?= $withdrawal['method'] === 'Mobile Money' ? 'fas fa-mobile-alt' : 'fas fa-university' ?>"></i>
                                                <span><?= htmlspecialchars($withdrawal['method']) ?></span>
                                            </div>
                                            <div class="withdrawal-details">
                                                <span class="amount">GHC<?= number_format($withdrawal['amount'], 2) ?></span>
                                                <span class="date"><?= date('M j, Y', strtotime($withdrawal['dt'])) ?></span>
                                            </div>
                                        </li>
                                    <?php endwhile; ?>
                                </ul>
                            <?php else: ?>
                                <p class="no-withdrawals">No recent withdrawals</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </div>

    <?php require_once('../includes/footer.php'); ?>

    <script>
        // Mobile sidebar toggle
        document.getElementById('menu_btn').addEventListener('click', function() {
            document.getElementById('sidebar').classList.toggle('show_sidebar');
        });

        document.getElementById('btn_close').addEventListener('click', function() {
            document.getElementById('sidebar').classList.remove('show_sidebar');
        });

        // Method selection
        const methodOptions = document.querySelectorAll('.method-option');
        const methodInput = document.getElementById('method');
        
        methodOptions.forEach(option => {
            option.addEventListener('click', function() {
                // Remove active class from all options
                methodOptions.forEach(opt => opt.classList.remove('active'));
                
                // Add active class to clicked option
                this.classList.add('active');
                
                // Update hidden input value
                methodInput.value = this.dataset.method;
                
                // Update destination placeholder based on method
                const destinationInput = document.getElementById('destination');
                if (this.dataset.method === 'Mobile Money') {
                    destinationInput.placeholder = 'e.g., MTN - 024XXXXXXX';
                } else {
                    destinationInput.placeholder = 'e.g., UBA - 3030****';
                }
            });
        });

        // Initialize first method as active
        document.querySelector('.method-option').classList.add('active');
    </script>
</body>
</html>