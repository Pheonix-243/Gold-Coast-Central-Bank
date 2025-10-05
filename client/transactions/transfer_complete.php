<?php
session_start();
require_once('../includes/auth.php');
require_once('../includes/conn.php');

// Check if user is logged in
if (!isset($_SESSION['client_account'])) {
    header('Location: ../auth/login.php');
    exit;
}

// Check if success data exists
if (!isset($_SESSION['transfer_success'])) {
    $_SESSION['error'] = "No transfer data found";
    header('Location: transfer.php');
    exit;
}

$successData = $_SESSION['transfer_success'];

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

// Clear success data after displaying
unset($_SESSION['transfer_success']);
?>
<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Transfer Successful | Gold Coast Central Bank</title>
    <meta name="description" content="Your transfer has been successfully processed by Gold Coast Central Bank.">
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

                <a href="../settings/password.php" class="nav_link" aria-label="settings">
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
                    <h1>Transfer Complete</h1>
                    <p class="welcome">Your transfer has been successfully processed.</p>
                </div>

                <div class="topbar_icons">
                    <a href="#" aria-label="notifications" class="topbar_icon alert">
                        <i class="fas fa-bell"></i>
                    </a>
                </div>
            </div>
            
            <!-- Page Content -->
            <div class="transfer-container">
                <main>
                    <!-- Success Card -->
                    <div class="success-card">
                        <div class="success-icon">
                            <i class="fas fa-check-circle"></i>
                        </div>
                        
                        <div class="success-header">
                            <h2>Transfer Successful!</h2>
                            <p>Your transfer has been processed successfully</p>
                        </div>
                        
                        <!-- Transfer Progress -->
                        <div class="step-indicator">
                            <div class="step completed" data-step="1">
                                <div class="step-number">1</div>
                                <span class="step-label">Transfer Details</span>
                            </div>
                            <div class="step completed" data-step="2">
                                <div class="step-number">2</div>
                                <span class="step-label">Review & Confirm</span>
                            </div>
                            <div class="step completed" data-step="3">
                                <div class="step-number">3</div>
                                <span class="step-label">Complete</span>
                            </div>
                        </div>
                        
                        <!-- Transaction Details -->
                        <div class="transaction-details">
                            <div class="detail-card">
                                <h3>Transaction Details</h3>
                                
                                <div class="detail-grid">
                        <!-- In the transaction details section of transfer_complete.php -->
<div class="detail-item">
    <span class="detail-label">Amount Sent</span>
    <span class="detail-value">GHC <?= number_format($successData['amount'], 2) ?></span>
</div>

<div class="detail-item">
    <span class="detail-label">Transfer Fee</span>
    <span class="detail-value">GHC <?= number_format($successData['fee'], 2) ?></span>
</div>

<div class="detail-item">
    <span class="detail-label">Tax</span>
    <span class="detail-value">GHC <?= number_format($successData['tax'], 2) ?></span>
</div>

<div class="detail-item">
    <span class="detail-label">Total Amount</span>
    <span class="detail-value">GHC <?= number_format($successData['amount'] + $successData['fee'] + $successData['tax'], 2) ?></span>
</div>

<div class="detail-item">
    <span class="detail-label">Recipient Received</span>
    <span class="detail-value">GHC <?= number_format($successData['net_amount'], 2) ?></span>
</div>
                                    
                                    <div class="detail-item">
                                        <span class="detail-label">Recipient Name</span>
                                        <span class="detail-value"><?= htmlspecialchars($successData['recipient_name']) ?></span>
                                    </div>
                                    
                                    <div class="detail-item">
                                        <span class="detail-label">Recipient Account</span>
                                        <span class="detail-value"><?= htmlspecialchars($successData['recipient_account']) ?></span>
                                    </div>
                                    
                                    <div class="detail-item">
                                        <span class="detail-label">Transfer Type</span>
                                        <span class="detail-value">
                                            <?php 
                                            switch($successData['type']) {
                                                case 'internal': echo 'Internal Transfer'; break;
                                                case 'mobile': echo 'Mobile Money'; break;
                                                case 'bank': echo 'Local Bank Transfer'; break;
                                                case 'international': echo 'International Transfer'; break;
                                                default: echo 'Transfer'; break;
                                            }
                                            ?>
                                        </span>
                                    </div>
                                    
                                    <?php if (!empty($successData['description'])): ?>
                                        <div class="detail-item">
                                            <span class="detail-label">Description</span>
                                            <span class="detail-value"><?= htmlspecialchars($successData['description']) ?></span>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <div class="detail-item">
                                        <span class="detail-label">Date & Time</span>
                                        <span class="detail-value"><?= date('M j, Y \a\t g:i A') ?></span>
                                    </div>
                                    
                                    <div class="detail-item">
                                        <span class="detail-label">Status</span>
                                        <span class="detail-value status-completed">Completed</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Next Actions -->
                        <div class="next-actions">
                            <a href="../transactions/history.php" class="btn btn-primary">
                                <i class="fas fa-history"></i> View Transaction History
                            </a>
                            <a href="transfer.php" class="btn btn-secondary">
                                <i class="fas fa-exchange-alt"></i> Make Another Transfer
                            </a>
                            <button type="button" class="btn btn-outline" id="printReceipt">
                                <i class="fas fa-print"></i> Print Receipt
                            </button>
                        </div>
                        
                        <!-- Email Confirmation -->
                        <div class="email-confirmation">
                            <i class="fas fa-envelope"></i>
                            <p>A confirmation email has been sent to your registered email address</p>
                        </div>
                    </div>
                </main>

                <!-- Transfer Information Sidebar -->
                <aside class="transfer-aside">
                    <!-- Account Balance -->
                    <div class="info-card balance-card">
                        <h3><i class="fas fa-wallet"></i> Account Balance</h3>
                        <div class="balance-amount">
                            GHC <?= number_format($_SESSION['client_balance'], 2) ?>
                        </div>
                        <p class="text-hint">Current available balance</p>
                    </div>
                    
                    <!-- Recent Transactions -->
                    <div class="info-card">
                        <h3><i class="fas fa-clock"></i> Recent Transfers</h3>
                        <p class="text-hint">Your most recent transactions</p>
                        
                        <?php
                        // Get recent transactions
                        $recentTransactions = [];
                        $sql = "SELECT receiver, r_name, amount, dt, tm 
                                FROM account_history 
                                WHERE sender = ? AND status = 'completed'
                                ORDER BY dt DESC, tm DESC 
                                LIMIT 3";
                        $stmt = mysqli_prepare($con, $sql);
                        if ($stmt) {
                            mysqli_stmt_bind_param($stmt, "s", $_SESSION['client_account']);
                            mysqli_stmt_execute($stmt);
                            $result = mysqli_stmt_get_result($stmt);
                            while ($row = mysqli_fetch_assoc($result)) {
                                $recentTransactions[] = $row;
                            }
                            mysqli_stmt_close($stmt);
                        }
                        ?>
                        
                        <?php if (empty($recentTransactions)): ?>
                            <div class="empty-state">
                                <div class="empty-state-icon">
                                    <i class="fas fa-exchange-alt"></i>
                                </div>
                                <p>No recent transfers</p>
                            </div>
                        <?php else: ?>
                            <ul class="transaction-list">
                                <?php foreach ($recentTransactions as $transaction): ?>
                                    <li class="transaction-item">
                                        <div class="transaction-icon">
                                            <i class="fas fa-arrow-up"></i>
                                        </div>
                                        <div class="transaction-details">
                                            <span class="transaction-name"><?= htmlspecialchars($transaction['r_name']) ?></span>
                                            <span class="transaction-date"><?= date('M j', strtotime($transaction['dt'])) ?> • <?= $transaction['tm'] ?></span>
                                        </div>
                                        <div class="transaction-amount">
                                            -GHC <?= number_format($transaction['amount'], 2) ?>
                                        </div>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Support Card -->
                    <div class="info-card support-card">
                        <h3><i class="fas fa-question-circle"></i> Need Help?</h3>
                        <p class="text-hint">Contact our support team for assistance</p>
                        <div class="support-actions">
                            <a href="../pages/support.php" class="btn btn-outline">
                                <i class="fas fa-headset"></i> Contact Support
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
        // Print receipt functionality
        document.getElementById('printReceipt')?.addEventListener('click', function() {
            window.print();
        });
    });
    </script>
</body>
</html>