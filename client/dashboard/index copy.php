<?php
require_once('../includes/auth.php');
require_once('../includes/notification.php');

// Add this to fetch the profile picture:
$sql = "SELECT image FROM accountsholder WHERE account = ?";
$stmt = mysqli_prepare($con, $sql);
mysqli_stmt_bind_param($stmt, "s", $account);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$profilePic = mysqli_fetch_assoc($result)['image'];

// Get account summary
$account = $_SESSION['client_account'];
$sql = "SELECT a.balance, a.account_type, h.name 
        FROM accounts_info a
        JOIN accountsholder h ON a.account = h.account
        WHERE a.account = ?";
$stmt = mysqli_prepare($con, $sql);
mysqli_stmt_bind_param($stmt, "s", $account);
mysqli_stmt_execute($stmt);
$accountInfo = mysqli_fetch_assoc($stmt->get_result());

// Get recent transactions - updated query to get more transactions
$sql = "SELECT h.*, t.name as type_name 
        FROM account_history h
        JOIN transaction_types t ON h.type = t.id
        WHERE h.account = ?
        ORDER BY h.dt DESC, h.tm DESC LIMIT 10";
$stmt = mysqli_prepare($con, $sql);
mysqli_stmt_bind_param($stmt, "s", $account);
mysqli_stmt_execute($stmt);
$allTransactions = mysqli_stmt_get_result($stmt);

// Separate transactions into today and yesterday
$todayTransactions = [];
$yesterdayTransactions = [];
$todayDate = date('Y-m-d');
$yesterdayDate = date('Y-m-d', strtotime('-1 day'));

while ($row = mysqli_fetch_assoc($allTransactions)) {
    $transactionDate = substr($row['dt'], 0, 10); // Extract date part
    if ($transactionDate === $todayDate) {
        $todayTransactions[] = $row;
    } elseif ($transactionDate === $yesterdayDate) {
        $yesterdayTransactions[] = $row;
    }
    
    // Limit to 3 transactions per day
    if (count($todayTransactions) >= 3 && count($yesterdayTransactions) >= 3) {
        break;
    }
}

// Get monthly summary
$currentMonth = date('Y-m');
$sql = "SELECT 
            SUM(CASE WHEN h.reciever = ? AND h.account = h.reciever THEN h.amount ELSE 0 END) as income,
            SUM(CASE WHEN h.sender = ? AND h.account = h.sender THEN h.amount ELSE 0 END) as expenses,
            COUNT(*) as count
        FROM account_history h
        WHERE h.account = ?
        AND h.dt LIKE ?";
$stmt = mysqli_prepare($con, $sql);
$monthPattern = $currentMonth . '%';
mysqli_stmt_bind_param($stmt, "ssss", $account, $account, $account, $monthPattern);
mysqli_stmt_execute($stmt);
$monthlySummary = mysqli_fetch_assoc($stmt->get_result());
?>


<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gold Coast Central Bank - Premium Dashboard</title>
    
    <meta name="description" content="Premium banking dashboard for Gold Coast Central Bank clients">
    <meta name="theme-color" content="#0f172a">
    
    <!-- Favicons -->
    <link rel="apple-touch-icon" sizes="180x180" href="apple-touch-icon.png">
    <link rel="icon" type="image/png" sizes="32x32" href="favicon-32x32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="favicon-16x16.png">
    <link rel="manifest" href="site.webmanifest">
    
    <!-- Preload critical fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    
    <!-- Font Awesome Pro -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Google Fonts - Inter with optimized loading -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    
    <!-- Chart.js for premium analytics -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
    <!-- Custom Premium CSS -->
    <link rel="stylesheet" href="style.css">
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
                <a href="../dashboard/" class="nav_link active" aria-label="overview">
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

        <section class="main_content">
            <div class="topbar">
                <button id="menu_btn">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="16" height="16">
                        <path fill="none" d="M0 0h24v24H0z" />
                        <path d="M3 4h18v2H3V4zm0 7h12v2H3v-2zm0 7h18v2H3v-2z" /></svg>
                </button>
                <div class="overview_text">
                    <h1>Dashboard</h1>
                    <p class="welcome">Hi <?= htmlspecialchars(explode(' ', $_SESSION['client_name'])[0]) ?>, welcome back!</p>
                </div>

                <div class="topbar_icons">
<a href="#" aria-label="notifications" class="topbar_icon alert" id="notificationBell">
    <i class="fas fa-bell"></i>
    <div class="notification-dropdown" id="notificationDropdown">
        <div class="notification-header">
            <h4>Notifications</h4>
            <button id="mark-all-read">Mark all as read</button>
        </div>
        <div class="notification-list"></div>
        <div class="notification-footer">
            <a href="../pages/notifications.php">View all notifications</a>
        </div>
    </div>
</a>
                </div>
            </div>

            <section class="content_section">
                <main>
                    <!-- Premium Balance Card with Enhanced Design -->
                    <div class="premium-balance-card">
                        <div class="balance-card-header">
                            <div class="balance-info">
                                <h2 class="balance-title">Available Balance</h2>
                                <p class="account-type"><?= htmlspecialchars($accountInfo['account_type'] ?? 'Premium Account') ?></p>
                            </div>
                            <div class="balance-actions">
                                <button class="balance-visibility-btn" aria-label="toggle balance visibility">
                                    <i class="fas fa-eye"></i>
                                </button>
                                <div class="balance-menu">
                                    <button class="menu-trigger">
                                        <i class="fas fa-ellipsis-h"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                        
                        <div class="balance-amount-section">
                            <div class="main-balance">
                                <span class="currency">GHC</span>
                                <span class="amount" data-balance="<?= $_SESSION['client_balance'] ?>"><?= number_format($_SESSION['client_balance'], 2) ?></span>
                            </div>
                            <div class="balance-change">
                                <span class="change-indicator positive">
                                    <i class="fas fa-arrow-up"></i>
                                    +2.4% from last month
                                </span>
                            </div>
                        </div>
                        
                        <div class="account-details-premium">
                            <div class="account-number">
                                <span class="label">Account Number</span>
                                <span class="number" data-account="<?= htmlspecialchars($_SESSION['client_account']) ?>"><?= htmlspecialchars($_SESSION['client_account']) ?></span>
                            </div>
                            <div class="account-status">
                                <span class="status-badge active">
                                    <i class="fas fa-check-circle"></i>
                                    Active
                                </span>
                            </div>
                        </div>
                        
                        <div class="balance-card-gradient"></div>
                    </div>

                    <!-- Premium Analytics Overview -->
                    <div class="analytics-overview">
                        <div class="analytics-header">
                            <h3>Financial Overview</h3>
                            <div class="period-selector">
                                <button class="period-btn active" data-period="month">This Month</button>
                                <button class="period-btn" data-period="quarter">Quarter</button>
                                <button class="period-btn" data-period="year">Year</button>
                            </div>
                        </div>
                        
                        <div class="analytics-cards">
                            <div class="analytics-card income">
                                <div class="card-icon">
                                    <i class="fas fa-arrow-down"></i>
                                </div>
                                <div class="card-content">
                                    <h4>Income</h4>
                                    <p class="amount">GHC<?= number_format($monthlySummary['income'] ?? 0, 2) ?></p>
                                    <span class="change positive">+12.5%</span>
                                </div>
                            </div>
                            
                            <div class="analytics-card expenses">
                                <div class="card-icon">
                                    <i class="fas fa-arrow-up"></i>
                                </div>
                                <div class="card-content">
                                    <h4>Expenses</h4>
                                    <p class="amount">GHC<?= number_format($monthlySummary['expenses'] ?? 0, 2) ?></p>
                                    <span class="change negative">-3.2%</span>
                                </div>
                            </div>
                            
                            <div class="analytics-card transactions">
                                <div class="card-icon">
                                    <i class="fas fa-exchange-alt"></i>
                                </div>
                                <div class="card-content">
                                    <h4>Transactions</h4>
                                    <p class="amount"><?= $monthlySummary['count'] ?? 0 ?></p>
                                    <span class="change positive">+8.1%</span>
                                </div>
                            </div>
                            
                            <div class="analytics-card savings">
                                <div class="card-icon">
                                    <i class="fas fa-piggy-bank"></i>
                                </div>
                                <div class="card-content">
                                    <h4>Net Savings</h4>
                                    <p class="amount">GHC<?= number_format(($monthlySummary['income'] ?? 0) - ($monthlySummary['expenses'] ?? 0), 2) ?></p>
                                    <span class="change positive">+15.7%</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Premium Quick Actions -->
                    <div class="premium-quick-actions">
                        <div class="section-header">
                            <h3>Quick Actions</h3>
                            <p class="section-subtitle">Manage your finances efficiently</p>
                        </div>
                        
                        <div class="quick-actions-grid">
                            <a href="../transactions/transfer.php" class="quick-action-card primary">
                                <div class="action-icon">
                                    <i class="fas fa-paper-plane"></i>
                                </div>
                                <div class="action-content">
                                    <h4>Send Money</h4>
                                    <p>Transfer funds instantly</p>
                                </div>
                                <div class="action-arrow">
                                    <i class="fas fa-arrow-right"></i>
                                </div>
                            </a>
                            
                            <a href="../transactions/deposit.php" class="quick-action-card secondary">
                                <div class="action-icon">
                                    <i class="fas fa-plus-circle"></i>
                                </div>
                                <div class="action-content">
                                    <h4>Add Funds</h4>
                                    <p>Deposit to your account</p>
                                </div>
                                <div class="action-arrow">
                                    <i class="fas fa-arrow-right"></i>
                                </div>
                            </a>
                            
                            <a href="../transactions/history.php" class="quick-action-card tertiary">
                                <div class="action-icon">
                                    <i class="fas fa-history"></i>
                                </div>
                                <div class="action-content">
                                    <h4>View History</h4>
                                    <p>Transaction records</p>
                                </div>
                                <div class="action-arrow">
                                    <i class="fas fa-arrow-right"></i>
                                </div>
                            </a>
                            
                            <a href="../pages/analytics.php" class="quick-action-card accent">
                                <div class="action-icon">
                                    <i class="fas fa-chart-line"></i>
                                </div>
                                <div class="action-content">
                                    <h4>Analytics</h4>
                                    <p>Financial insights</p>
                                </div>
                                <div class="action-arrow">
                                    <i class="fas fa-arrow-right"></i>
                                </div>
                            </a>
                        </div>
                    </div>

                    <div class="transactions_section">
                        <div class="section_header">
                            <h3>Recent Transactions</h3>
                            <a href="../transactions/history.php" class="view_all">View All</a>
                        </div>
                        
                        <div class="transaction_day_group">
                            <h4>Today - <?= date('F j, Y') ?></h4>
                            <?php if (empty($todayTransactions)): ?>
                                <div class="no_transactions">
                                    <p>No transactions today</p>
                                </div>
                            <?php else: ?>
                                <?php foreach ($todayTransactions as $row): ?>
                                    <?php
                                    $isOutgoing = $row['sender'] == $account;
                                    $amountClass = $isOutgoing ? 'outgoing' : 'incoming';
                                    $amountSign = $isOutgoing ? '-' : '+';
                                    $transactionText = '';
                                    
                                    switch($row['type_name']) {
                                        case 'Transfer':
                                            $transactionText = "Transfer to {$row['r_name']}";
                                            break;
                                        case 'Payment Recieved':
                                            $transactionText = "Payment received from {$row['s_name']}";
                                            break;
                                        case 'Withdrawal':
                                            $transactionText = "Cash withdrawal";
                                            break;
                                        case 'Deposit':
                                            $transactionText = "Deposit";
                                            break;
                                        default:
                                            $transactionText = $row['type_name'];
                                    }
                                    ?>
                                    <div class="transaction_item <?= $amountClass ?>">
                                        <div class="transaction_icon">
                                            <?php if($isOutgoing): ?>
                                                <i class="fas fa-arrow-up"></i>
                                            <?php else: ?>
                                                <i class="fas fa-arrow-down"></i>
                                            <?php endif; ?>
                                        </div>
                                        <div class="transaction_details">
                                            <p class="transaction_title"><?= htmlspecialchars($transactionText) ?></p>
                                            <p class="transaction_desc"><?= htmlspecialchars($row['description']) ?></p>
                                            <p class="transaction_time"><?= date('h:i A', strtotime($row['tm'])) ?></p>
                                        </div>
                                        <div class="transaction_amount">
                                            <?= $amountSign ?> GHC<?= number_format($row['amount'], 2) ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>

                        <?php if (!empty($yesterdayTransactions)): ?>
                            <div class="transaction_day_group">
                                <h4>Yesterday - <?= date('F j, Y', strtotime('-1 day')) ?></h4>
                                <?php foreach ($yesterdayTransactions as $row): ?>
                                    <?php
                                    $isOutgoing = $row['sender'] == $account;
                                    $amountClass = $isOutgoing ? 'outgoing' : 'incoming';
                                    $amountSign = $isOutgoing ? '-' : '+';
                                    $transactionText = '';
                                    
                                    switch($row['type_name']) {
                                        case 'Transfer':
                                            $transactionText = "Transfer to {$row['r_name']}";
                                            break;
                                        case 'Payment Recieved':
                                            $transactionText = "Payment received from {$row['s_name']}";
                                            break;
                                        case 'Withdrawal':
                                            $transactionText = "Cash withdrawal";
                                            break;
                                        case 'Deposit':
                                            $transactionText = "Deposit";
                                            break;
                                        default:
                                            $transactionText = $row['type_name'];
                                    }
                                    ?>
                                    <div class="transaction_item <?= $amountClass ?>">
                                        <div class="transaction_icon">
                                            <?php if($isOutgoing): ?>
                                                <i class="fas fa-arrow-up"></i>
                                            <?php else: ?>
                                                <i class="fas fa-arrow-down"></i>
                                            <?php endif; ?>
                                        </div>
                                        <div class="transaction_details">
                                            <p class="transaction_title"><?= htmlspecialchars($transactionText) ?></p>
                                            <p class="transaction_desc"><?= htmlspecialchars($row['description']) ?></p>
                                            <p class="transaction_time"><?= date('h:i A', strtotime($row['tm'])) ?></p>
                                        </div>
                                        <div class="transaction_amount">
                                            <?= $amountSign ?> GHC<?= number_format($row['amount'], 2) ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </main>

                <aside>
                    <!-- Premium Financial Insights -->
                    <div class="premium-insights-card">
                        <div class="insights-header">
                            <h3>Financial Insights</h3>
                            <div class="insights-badge">
                                <i class="fas fa-lightbulb"></i>
                                AI Powered
                            </div>
                        </div>
                        
                        <div class="insights-content">
                            <div class="insight-item featured">
                                <div class="insight-icon">
                                    <i class="fas fa-chart-line"></i>
                                </div>
                                <div class="insight-text">
                                    <h4>Spending Trend</h4>
                                    <p>Your spending decreased by 8% this month. Great job managing your finances!</p>
                                    <span class="insight-action">View Details</span>
                                </div>
                            </div>
                            
                            <div class="insight-item">
                                <div class="insight-icon">
                                    <i class="fas fa-piggy-bank"></i>
                                </div>
                                <div class="insight-text">
                                    <h4>Savings Goal</h4>
                                    <p>You're 73% towards your monthly savings target of GHC 2,000</p>
                                </div>
                            </div>
                            
                            <div class="insight-item">
                                <div class="insight-icon">
                                    <i class="fas fa-trophy"></i>
                                </div>
                                <div class="insight-text">
                                    <h4>Achievement</h4>
                                    <p>Congratulations! You've maintained a positive balance for 6 months</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Premium Banking Services -->
                    <div class="premium-services-card">
                        <div class="services-header">
                            <h3>Premium Services</h3>
                            <span class="premium-badge">Gold Member</span>
                        </div>
                        
                        <div class="services-grid">
                            <div class="service-item">
                                <div class="service-icon">
                                    <i class="fas fa-credit-card"></i>
                                </div>
                                <div class="service-content">
                                    <h4>Virtual Cards</h4>
                                    <p>Create instant virtual cards for online shopping</p>
                                    <button class="service-btn">Create Card</button>
                                </div>
                            </div>
                            
                            <div class="service-item">
                                <div class="service-icon">
                                    <i class="fas fa-shield-alt"></i>
                                </div>
                                <div class="service-content">
                                    <h4>Account Protection</h4>
                                    <p>Advanced security features and fraud monitoring</p>
                                    <button class="service-btn">Learn More</button>
                                </div>
                            </div>
                            
                            <div class="service-item">
                                <div class="service-icon">
                                    <i class="fas fa-percentage"></i>
                                </div>
                                <div class="service-content">
                                    <h4>Investment Options</h4>
                                    <p>Grow your wealth with our premium investment plans</p>
                                    <button class="service-btn">Explore</button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Enhanced Monthly Summary -->
                    <div class="enhanced-monthly-summary">
                        <div class="summary-header">
                            <h3>Monthly Overview</h3>
                            <div class="month-selector">
                                <button class="month-nav prev">
                                    <i class="fas fa-chevron-left"></i>
                                </button>
                                <span class="current-month"><?= date('F Y') ?></span>
                                <button class="month-nav next">
                                    <i class="fas fa-chevron-right"></i>
                                </button>
                            </div>
                        </div>
                        
                        <div class="summary-chart">
                            <canvas id="monthlyChart" width="300" height="200"></canvas>
                        </div>
                        
                        <div class="summary-stats-enhanced">
                            <div class="stat-item-enhanced income">
                                <div class="stat-header">
                                    <div class="stat-icon">
                                        <i class="fas fa-arrow-down"></i>
                                    </div>
                                    <span class="stat-label">Income</span>
                                </div>
                                <div class="stat-amount">GHC<?= number_format($monthlySummary['income'] ?? 0, 2) ?></div>
                                <div class="stat-change positive">+12.5%</div>
                            </div>
                            
                            <div class="stat-item-enhanced expenses">
                                <div class="stat-header">
                                    <div class="stat-icon">
                                        <i class="fas fa-arrow-up"></i>
                                    </div>
                                    <span class="stat-label">Expenses</span>
                                </div>
                                <div class="stat-amount">GHC<?= number_format($monthlySummary['expenses'] ?? 0, 2) ?></div>
                                <div class="stat-change negative">-3.2%</div>
                            </div>
                            
                            <div class="stat-item-enhanced net">
                                <div class="stat-header">
                                    <div class="stat-icon">
                                        <i class="fas fa-balance-scale"></i>
                                    </div>
                                    <span class="stat-label">Net</span>
                                </div>
                                <div class="stat-amount">GHC<?= number_format(($monthlySummary['income'] ?? 0) - ($monthlySummary['expenses'] ?? 0), 2) ?></div>
                                <div class="stat-change positive">+18.7%</div>
                            </div>
                        </div>
                    </div>

                    <!-- Premium Support -->
                    <div class="premium-support-card">
                        <div class="support-header">
                            <div class="support-icon">
                                <i class="fas fa-headset"></i>
                            </div>
                            <div class="support-text">
                                <h3>24/7 Premium Support</h3>
                                <p>Get instant help from our banking experts</p>
                            </div>
                        </div>
                        
                        <div class="support-actions">
                            <button class="support-btn primary">
                                <i class="fas fa-comments"></i>
                                Live Chat
                            </button>
                            <button class="support-btn secondary">
                                <i class="fas fa-phone"></i>
                                Call Now
                            </button>
                        </div>
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

        // Account number toggle
        document.querySelector('.view_account_no').addEventListener('click', function() {
            const accountNo = document.querySelector('.account_no');
            const icon = this.querySelector('i');
            
            if(accountNo.style.filter === 'blur(4px)') {
                accountNo.style.filter = 'none';
                icon.classList.replace('fa-eye', 'fa-eye-slash');
            } else {
                accountNo.style.filter = 'blur(4px)';
                icon.classList.replace('fa-eye-slash', 'fa-eye');
            }
        });


// Replace your existing notification dropdown code with this:

// Notification system
document.addEventListener('DOMContentLoaded', function() {
    const notificationBell = document.getElementById('notificationBell');
    const notificationDropdown = document.getElementById('notificationDropdown');
    
    // Load notifications and update badge
    function updateNotifications() {
        fetch('../scripts/get_unread_count.php')
            .then(response => response.json())
            .then(data => {
                updateBadge(data.count || 0);
            })
            .catch(error => console.error('Error:', error));
    }
    
    // Update badge count
    function updateBadge(count) {
        let badge = notificationBell.querySelector('.notification-badge');
        
        if (count > 0) {
            if (!badge) {
                badge = document.createElement('span');
                badge.className = 'notification-badge';
                notificationBell.appendChild(badge);
            }
            badge.textContent = count > 9 ? '9+' : count;
        } else if (badge) {
            badge.remove();
        }
    }
    
    // Toggle dropdown
    notificationBell.addEventListener('click', function(e) {
        e.preventDefault();
        e.stopPropagation();
        
        if (notificationDropdown.style.display === 'block') {
            notificationDropdown.style.display = 'none';
        } else {
            notificationDropdown.style.display = 'block';
            loadNotifications(false);
            
            // Clear badge immediately
            updateBadge(0);
            
            // Mark all as read in backend
            fetch('../scripts/mark_all_read.php', {
                method: 'POST'
            }).catch(error => console.error('Error:', error));
        }
    });
    
    // Close when clicking outside
    document.addEventListener('click', function() {
        notificationDropdown.style.display = 'none';
    });
    
    // Initial load
    updateNotifications();
    
    // Poll for new notifications every 60 seconds
    setInterval(updateNotifications, 60000);
    
    // Function to load notifications
    function loadNotifications(unreadOnly = false) {
        fetch('../scripts/get_notifications.php?unread=' + (unreadOnly ? '1' : '0'))
            .then(response => response.json())
            .then(data => {
                const list = notificationDropdown.querySelector('.notification-list');
                list.innerHTML = '';
                
                if (data.length === 0) {
                    list.innerHTML = '<div class="no-notifications">No notifications</div>';
                    return;
                }
                
                data.forEach(notif => {
                    const item = document.createElement('div');
                    item.className = `notification-item ${notif.is_read ? '' : 'unread'}`;
                    item.dataset.id = notif.id;
                    item.innerHTML = `
                        <div class="notification-icon">
                            ${getNotificationIcon(notif.type)}
                        </div>
                        <div class="notification-content">
                            <div class="notification-title">${notif.title}</div>
                            <div class="notification-message">${notif.message}</div>
                            <div class="notification-time">${notif.time_ago}</div>
                        </div>
                        <button class="notification-delete" ${notif.is_deletable ? '' : 'disabled'}>
                            <i class="fas fa-times"></i>
                        </button>
                    `;
                    list.appendChild(item);
                    
                    // Click handler for individual notifications
                    item.addEventListener('click', function(e) {
                        if (!e.target.classList.contains('notification-delete')) {
                            if (!notif.is_read) {
                                markAsRead(notif.id);
                                item.classList.remove('unread');
                            }
                        }
                    });
                    
                    // Delete button handler
                    const deleteBtn = item.querySelector('.notification-delete');
                    deleteBtn.addEventListener('click', function(e) {
                        e.stopPropagation();
                        deleteNotification(notif.id);
                        item.remove();
                    });
                });
            })
            .catch(error => console.error('Error:', error));
    }
    
    // Helper functions
    function getNotificationIcon(type) {
        const icons = {
            'transaction': '<i class="fas fa-exchange-alt"></i>',
            'login': '<i class="fas fa-sign-in-alt"></i>',
            'security': '<i class="fas fa-shield-alt"></i>',
            'profile_update': '<i class="fas fa-user-edit"></i>',
            'system': '<i class="fas fa-info-circle"></i>'
        };
        return icons[type] || '<i class="fas fa-bell"></i>';
    }
    
    function markAsRead(id) {
        fetch('../scripts/mark_notification_read.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({ id: id })
        }).catch(error => console.error('Error:', error));
    }
    
    function deleteNotification(id) {
        fetch('../scripts/delete_notification.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({ id: id })
        }).catch(error => console.error('Error:', error));
    }
});
    </script>
</body>
</html>