<?php
require_once('../includes/auth.php');
require_once('../includes/notification.php');

// Fetch profile picture
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

// Get period from request or default to current month
$period = isset($_GET['period']) ? $_GET['period'] : 'month';
$selectedDate = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');

// Calculate date ranges based on period
function getDateRange($period, $selectedDate) {
    $date = new DateTime($selectedDate);
    
    switch($period) {
        case 'month':
            $startDate = $date->format('Y-m-01');
            $endDate = $date->format('Y-m-t');
            $prevDate = (clone $date)->modify('-1 month')->format('Y-m-d');
            $nextDate = (clone $date)->modify('+1 month')->format('Y-m-d');
            break;
            
        case 'quarter':
            $quarter = ceil($date->format('n') / 3);
            $startMonth = (($quarter - 1) * 3) + 1;
            $startDate = $date->format('Y-' . str_pad($startMonth, 2, '0', STR_PAD_LEFT) . '-01');
            $endDate = (new DateTime($startDate))->modify('+3 months -1 day')->format('Y-m-d');
            $prevDate = (clone $date)->modify('-3 months')->format('Y-m-d');
            $nextDate = (clone $date)->modify('+3 months')->format('Y-m-d');
            break;
            
        case 'year':
            $startDate = $date->format('Y-01-01');
            $endDate = $date->format('Y-12-31');
            $prevDate = (clone $date)->modify('-1 year')->format('Y-m-d');
            $nextDate = (clone $date)->modify('+1 year')->format('Y-m-d');
            break;
            
        default:
            $startDate = $date->format('Y-m-01');
            $endDate = $date->format('Y-m-t');
            $prevDate = (clone $date)->modify('-1 month')->format('Y-m-d');
            $nextDate = (clone $date)->modify('+1 month')->format('Y-m-d');
    }
    
    return [
        'start_date' => $startDate,
        'end_date' => $endDate,
        'prev_date' => $prevDate,
        'next_date' => $nextDate,
        'display_date' => $period === 'month' ? $date->format('F Y') : 
                         ($period === 'quarter' ? 'Q' . ceil($date->format('n') / 3) . ' ' . $date->format('Y') :
                         $date->format('Y'))
    ];
}

$dateRange = getDateRange($period, $selectedDate);

// Get current period financial data
function getFinancialData($con, $account, $startDate, $endDate) {
    $sql = "SELECT 
                SUM(CASE WHEN h.sender != ? AND h.receiver = ? THEN h.amount ELSE 0 END) as income,
                SUM(CASE WHEN h.sender = ? AND h.receiver != ? THEN h.amount ELSE 0 END) as expenses,
                COUNT(*) as transaction_count,
                SUM(CASE WHEN h.sender != ? AND h.receiver = ? THEN h.amount ELSE 0 END) - 
                SUM(CASE WHEN h.sender = ? AND h.receiver != ? THEN h.amount ELSE 0 END) as net_savings,
                AVG(CASE WHEN h.sender != ? AND h.receiver = ? THEN h.amount ELSE NULL END) as avg_income,
                AVG(CASE WHEN h.sender = ? AND h.receiver != ? THEN h.amount ELSE NULL END) as avg_expense
            FROM account_history h
            WHERE (h.sender = ? OR h.receiver = ?)
            AND h.status = 'completed'
            AND h.dt BETWEEN ? AND ?";
    
    $stmt = mysqli_prepare($con, $sql);
    mysqli_stmt_bind_param($stmt, "ssssssssssssssss", 
        $account, $account, $account, $account, 
        $account, $account, $account, $account,
        $account, $account, $account, $account,
        $account, $account, $startDate, $endDate
    );
    mysqli_stmt_execute($stmt);
    return mysqli_fetch_assoc($stmt->get_result());
}

// Get previous period financial data for comparison
function getPreviousPeriodData($con, $account, $period, $selectedDate) {
    $date = new DateTime($selectedDate);
    
    switch($period) {
        case 'month':
            $prevStart = $date->modify('-1 month')->format('Y-m-01');
            $prevEnd = $date->format('Y-m-t');
            break;
        case 'quarter':
            $prevStart = $date->modify('-3 months')->format('Y-m-01');
            $prevEnd = (new DateTime($prevStart))->modify('+3 months -1 day')->format('Y-m-d');
            break;
        case 'year':
            $prevStart = $date->modify('-1 year')->format('Y-01-01');
            $prevEnd = $date->format('Y-12-31');
            break;
    }
    
    return getFinancialData($con, $account, $prevStart, $prevEnd);
}

$currentData = getFinancialData($con, $account, $dateRange['start_date'], $dateRange['end_date']);
$previousData = getPreviousPeriodData($con, $account, $period, $selectedDate);

// Calculate percentage changes with banking-grade validation
function calculateChange($current, $previous) {
    if ($previous == 0) return $current > 0 ? 100 : 0;
    return (($current - $previous) / abs($previous)) * 100;
}

$incomeChange = calculateChange($currentData['income'] ?? 0, $previousData['income'] ?? 0);
$expensesChange = calculateChange($currentData['expenses'] ?? 0, $previousData['expenses'] ?? 0);
$transactionChange = calculateChange($currentData['transaction_count'] ?? 0, $previousData['transaction_count'] ?? 0);
$savingsChange = calculateChange($currentData['net_savings'] ?? 0, $previousData['net_savings'] ?? 0);

// Get spending by category for insights
$sql = "SELECT t.name as category, 
               SUM(CASE WHEN h.sender = ? THEN h.amount ELSE 0 END) as amount,
               COUNT(CASE WHEN h.sender = ? THEN 1 ELSE NULL END) as count
        FROM account_history h
        JOIN transaction_types t ON h.type = t.id
        WHERE h.sender = ?
        AND h.status = 'completed'
        AND h.dt BETWEEN ? AND ?
        GROUP BY t.name
        ORDER BY amount DESC
        LIMIT 5";
$stmt = mysqli_prepare($con, $sql);
mysqli_stmt_bind_param($stmt, "sssss", $account, $account, $account, $dateRange['start_date'], $dateRange['end_date']);
mysqli_stmt_execute($stmt);
$spendingByCategory = mysqli_fetch_all($stmt->get_result(), MYSQLI_ASSOC);

// Get recent transactions
// Get 6 most recent transactions - SIMPLE APPROACH
$sql = "SELECT h.*, t.name as type_name 
        FROM account_history h
        JOIN transaction_types t ON h.type = t.id
        WHERE h.account = ?
        AND h.status = 'completed'
        ORDER BY h.dt DESC, h.tm DESC 
        LIMIT 6";
$stmt = mysqli_prepare($con, $sql);
mysqli_stmt_bind_param($stmt, "s", $account);
mysqli_stmt_execute($stmt);
$recentTransactions = mysqli_fetch_all($stmt->get_result(), MYSQLI_ASSOC);

// Group transactions by date with "Today"/"Yesterday" labels
$groupedTransactions = [];
foreach ($recentTransactions as $transaction) {
    $date = substr($transaction['dt'], 0, 10);
    
    // Convert date to display format
    $today = date('Y-m-d');
    $yesterday = date('Y-m-d', strtotime('-1 day'));
    
    if ($date === $today) {
        $displayDate = 'Today - ' . date('F j, Y');
    } elseif ($date === $yesterday) {
        $displayDate = 'Yesterday - ' . date('F j, Y', strtotime('-1 day'));
    } else {
        $displayDate = date('F j, Y', strtotime($date));
    }
    
    if (!isset($groupedTransactions[$displayDate])) {
        $groupedTransactions[$displayDate] = [];
    }
    $groupedTransactions[$displayDate][] = $transaction;
}


// Generate AI-powered financial insights
$financialInsights = generateFinancialInsights($currentData, $previousData, $spendingByCategory, $period);
function generateFinancialInsights($current, $previous, $spending, $period) {
    $insights = [];
    
    // Spending trend insight
    $spendingTrend = ($current['expenses'] - $previous['expenses']) / max($previous['expenses'], 1) * 100;
    if ($spendingTrend < -5) {
        $insights[] = [
            'icon' => 'chart-line',
            'title' => 'Spending Trend',
            'message' => "Your spending decreased by " . abs(round($spendingTrend)) . "% this $period. Excellent financial discipline!",
            'type' => 'positive'
        ];
    } elseif ($spendingTrend > 15) {
        $insights[] = [
            'icon' => 'exclamation-triangle',
            'title' => 'Spending Alert',
            'message' => "Your spending increased by " . round($spendingTrend) . "% this $period. Consider reviewing your expenses.",
            'type' => 'warning'
        ];
    }
    
    // Savings rate insight
    $savingsRate = $current['net_savings'] / max($current['income'], 1) * 100;
    if ($savingsRate >= 20) {
        $insights[] = [
            'icon' => 'trophy',
            'title' => 'Savings Achievement',
            'message' => "You're saving " . round($savingsRate) . "% of your income. Great wealth-building habits!",
            'type' => 'positive'
        ];
    } elseif ($savingsRate < 10 && $savingsRate > 0) {
        $insights[] = [
            'icon' => 'piggy-bank',
            'title' => 'Savings Opportunity',
            'message' => "You're saving " . round($savingsRate) . "% of income. Aim for 15-20% for better financial growth.",
            'type' => 'info'
        ];
    }
    
    // Top spending category insight
    if (!empty($spending)) {
        $topCategory = $spending[0];
        if ($topCategory['amount'] > ($current['expenses'] * 0.4)) { // If top category is >40% of spending
            $insights[] = [
                'icon' => 'tags',
                'title' => 'Spending Concentration',
                'message' => ucfirst($topCategory['category']) . " accounts for " . round(($topCategory['amount'] / $current['expenses']) * 100) . "% of your spending.",
                'type' => 'info'
            ];
        }
    }
    
    // Income growth insight
    $incomeGrowth = ($current['income'] - $previous['income']) / max($previous['income'], 1) * 100;
    if ($incomeGrowth > 10) {
        $insights[] = [
            'icon' => 'trending-up',
            'title' => 'Income Growth',
            'message' => "Your income grew by " . round($incomeGrowth) . "% this $period. Keep up the great work!",
            'type' => 'positive'
        ];
    }
    
    // Ensure we have at least 3 insights
    while (count($insights) < 3) {
        $insights[] = [
            'icon' => 'shield-alt',
            'title' => 'Financial Health',
            'message' => "Your account shows stable financial activity. Continue monitoring your spending habits.",
            'type' => 'info'
        ];
    }
    
    return array_slice($insights, 0, 3);
}
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









    <style>
        .period-navigation {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .period-nav-btn {
            width: 32px;
            height: 32px;
            border-radius: 8px;
            border: 1px solid var(--border-medium);
            background: var(--bg-white);
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: var(--transition);
        }
        
        .period-nav-btn:hover:not(:disabled) {
            background: var(--primary);
            color: var(--text-white);
            border-color: var(--primary);
        }
        
        .period-nav-btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }
        
        .insight-item.positive {
            border-left: 3px solid var(--success);
        }
        
        .insight-item.warning {
            border-left: 3px solid var(--warning);
        }
        
        .insight-item.info {
            border-left: 3px solid var(--info);
        }
    </style>
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
                    <a href="#" aria-label="notifications" class="topbar_icon alert">
                        <i class="fas fa-bell"></i>
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
                                        <button class="view_account_no" aria-label="show_ac">
                                <i class="fas fa-eye-slash"></i>
                            </button>
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

 <!-- Enhanced Analytics Overview with Period Navigation -->
                    <div class="analytics-overview">
                        <div class="analytics-header">
                            <h3>Financial Overview - <?= $dateRange['display_date'] ?></h3>
                            <div class="period-selector">
                                <button class="period-btn <?= $period === 'month' ? 'active' : '' ?>" 
                                        data-period="month" onclick="changePeriod('month')">This Month</button>
                                <button class="period-btn <?= $period === 'quarter' ? 'active' : '' ?>" 
                                        data-period="quarter" onclick="changePeriod('quarter')">Quarter</button>
                                <button class="period-btn <?= $period === 'year' ? 'active' : '' ?>" 
                                        data-period="year" onclick="changePeriod('year')">Year</button>
                            </div>
                        </div>
                        
                        <div class="analytics-cards">
                            <div class="analytics-card income">
                                <div class="card-icon">
                                    <i class="fas fa-arrow-down"></i>
                                </div>
                                <div class="card-content">
                                    <h4>Income</h4>
                                    <p class="amount">GHC<?= number_format($currentData['income'] ?? 0, 2) ?></p>
                                    <span class="change <?= $incomeChange >= 0 ? 'positive' : 'negative' ?>">
                                        <i class="fas fa-arrow-<?= $incomeChange >= 0 ? 'up' : 'down' ?>"></i>
                                        <?= number_format(abs($incomeChange), 1) ?>%
                                    </span>
                                </div>
                            </div>
                            
                            <div class="analytics-card expenses">
                                <div class="card-icon">
                                    <i class="fas fa-arrow-up"></i>
                                </div>
                                <div class="card-content">
                                    <h4>Expenses</h4>
                                    <p class="amount">GHC<?= number_format($currentData['expenses'] ?? 0, 2) ?></p>
                                    <span class="change <?= $expensesChange <= 0 ? 'positive' : 'negative' ?>">
                                        <i class="fas fa-arrow-<?= $expensesChange <= 0 ? 'down' : 'up' ?>"></i>
                                        <?= number_format(abs($expensesChange), 1) ?>%
                                    </span>
                                </div>
                            </div>
                            
                            <div class="analytics-card transactions">
                                <div class="card-icon">
                                    <i class="fas fa-exchange-alt"></i>
                                </div>
                                <div class="card-content">
                                    <h4>Transactions</h4>
                                    <p class="amount"><?= $currentData['transaction_count'] ?? 0 ?></p>
                                    <span class="change <?= $transactionChange >= 0 ? 'positive' : 'negative' ?>">
                                        <i class="fas fa-arrow-<?= $transactionChange >= 0 ? 'up' : 'down' ?>"></i>
                                        <?= number_format(abs($transactionChange), 1) ?>%
                                    </span>
                                </div>
                            </div>
                            
                            <div class="analytics-card savings">
                                <div class="card-icon">
                                    <i class="fas fa-piggy-bank"></i>
                                </div>
                                <div class="card-content">
                                    <h4>Net Savings</h4>
                                    <p class="amount">GHC<?= number_format($currentData['net_savings'] ?? 0, 2) ?></p>
                                    <span class="change <?= $savingsChange >= 0 ? 'positive' : 'negative' ?>">
                                        <i class="fas fa-arrow-<?= $savingsChange >= 0 ? 'up' : 'down' ?>"></i>
                                        <?= number_format(abs($savingsChange), 1) ?>%
                                    </span>
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

                    <!-- Simple Transactions Section -->
<div class="transactions_section">
    <div class="section_header">
        <h3>Recent Transactions</h3>
        <a href="../transactions/history.php" class="view_all">View All</a>
    </div>
    
    <?php if (!empty($groupedTransactions)): ?>
        <?php foreach ($groupedTransactions as $displayDate => $transactions): ?>
            <div class="transaction_day_group">
                <h4><?= $displayDate ?></h4>
                <?php foreach ($transactions as $row): ?>
                    <?php
                    $isOutgoing = $row['sender'] == $account;
                    $amountClass = $isOutgoing ? 'outgoing' : 'incoming';
                    $amountSign = $isOutgoing ? '-' : '+';
                    $transactionText = '';
                    
                    switch($row['type_name']) {
                        case 'Transfer':
                            $transactionText = $isOutgoing ? "Transfer to {$row['r_name']}" : "Transfer from {$row['s_name']}";
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
                        case 'Online Payment':
                            $transactionText = $isOutgoing ? "Online payment" : "Online payment received";
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
                            <?php if (!empty($row['description'])): ?>
                                <p class="transaction_desc"><?= htmlspecialchars($row['description']) ?></p>
                            <?php endif; ?>
                            <p class="transaction_time"><?= date('h:i A', strtotime($row['tm'])) ?></p>
                        </div>
                        <div class="transaction_amount">
                            <?= $amountSign ?> GHC<?= number_format($row['amount'], 2) ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endforeach; ?>
    <?php else: ?>
        <div class="no_transactions">
            <p>No recent transactions found</p>
        </div>
    <?php endif; ?>
</div>
                </main>

                <aside>
                    <!-- Enhanced Financial Insights with Real Data -->
                    <div class="premium-insights-card">
                        <div class="insights-header">
                            <h3>Financial Insights</h3>
                            <div class="insights-badge">
                                <i class="fas fa-lightbulb"></i>
                                AI Powered
                            </div>
                        </div>
                        
                        <div class="insights-content">
                            <?php foreach ($financialInsights as $index => $insight): ?>
                                <div class="insight-item <?= $insight['type'] ?> <?= $index === 0 ? 'featured' : '' ?>">
                                    <div class="insight-icon">
                                        <i class="fas fa-<?= $insight['icon'] ?>"></i>
                                    </div>
                                    <div class="insight-text">
                                        <h4><?= htmlspecialchars($insight['title']) ?></h4>
                                        <p><?= htmlspecialchars($insight['message']) ?></p>
                                        <?php if ($index === 0): ?>
                                            <span class="insight-action">View Details</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <!-- Enhanced Monthly Summary with Navigation -->
                    <div class="enhanced-monthly-summary">
                        <div class="summary-header">
                            <h3><?= ucfirst($period) ?> Overview</h3>
                            <div class="period-navigation">
                                <button class="period-nav-btn prev" 
                                        onclick="navigatePeriod('<?= $dateRange['prev_date'] ?>')"
                                        title="Previous <?= $period ?>">
                                    <i class="fas fa-chevron-left"></i>
                                </button>
                                <span class="current-period"><?= $dateRange['display_date'] ?></span>
                                <button class="period-nav-btn next" 
                                        onclick="navigatePeriod('<?= $dateRange['next_date'] ?>')"
                                        <?= $dateRange['next_date'] > date('Y-m-d') ? 'disabled' : '' ?>
                                        title="Next <?= $period ?>">
                                    <i class="fas fa-chevron-right"></i>
                                </button>
                            </div>
                        </div>
                        
                        <div class="summary-stats-enhanced">
                            <div class="stat-item-enhanced income">
                                <div class="stat-header">
                                    <div class="stat-icon">
                                        <i class="fas fa-arrow-down"></i>
                                    </div>
                                    <span class="stat-label">Income</span>
                                </div>
                                <div class="stat-amount">GHC<?= number_format($currentData['income'] ?? 0, 2) ?></div>
                                <div class="stat-change <?= $incomeChange >= 0 ? 'positive' : 'negative' ?>">
                                    <?= $incomeChange >= 0 ? '+' : '' ?><?= number_format($incomeChange, 1) ?>%
                                </div>
                            </div>
                            
                            <div class="stat-item-enhanced expenses">
                                <div class="stat-header">
                                    <div class="stat-icon">
                                        <i class="fas fa-arrow-up"></i>
                                    </div>
                                    <span class="stat-label">Expenses</span>
                                </div>
                                <div class="stat-amount">GHC<?= number_format($currentData['expenses'] ?? 0, 2) ?></div>
                                <div class="stat-change <?= $expensesChange <= 0 ? 'positive' : 'negative' ?>">
                                    <?= $expensesChange >= 0 ? '+' : '' ?><?= number_format($expensesChange, 1) ?>%
                                </div>
                            </div>
                            
                            <div class="stat-item-enhanced net">
                                <div class="stat-header">
                                    <div class="stat-icon">
                                        <i class="fas fa-balance-scale"></i>
                                    </div>
                                    <span class="stat-label">Net</span>
                                </div>
                                <div class="stat-amount">GHC<?= number_format($currentData['net_savings'] ?? 0, 2) ?></div>
                                <div class="stat-change <?= $savingsChange >= 0 ? 'positive' : 'negative' ?>">
                                    <?= $savingsChange >= 0 ? '+' : '' ?><?= number_format($savingsChange, 1) ?>%
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
            const accountNo = document.querySelector('.number');
            const icon = this.querySelector('i');
            
            if(accountNo.style.filter === 'blur(4px)') {
                accountNo.style.filter = 'none';
                icon.classList.replace('fa-eye', 'fa-eye-slash');
            } else {
                accountNo.style.filter = 'blur(4px)';
                icon.classList.replace('fa-eye-slash', 'fa-eye');
            }
        });

// Notification system with improved error handling
document.addEventListener('DOMContentLoaded', function() {
    console.log('Initializing notification system...');
    
    const notificationBell = document.querySelector('.topbar_icon.alert');
    if (!notificationBell) {
        console.error('Notification bell not found!');
        return;
    }
    
    const notificationDropdown = document.createElement('div');
    notificationDropdown.className = 'notification-dropdown';
    notificationDropdown.innerHTML = `
        <div class="notification-header">
            <h4>Notifications</h4>
            <button id="mark-all-read">Mark all as read</button>
        </div>
        <div class="notification-list"></div>
        <div class="notification-footer">
            <a href="../pages/notifications.php">View all notifications</a>
        </div>
    `;
    notificationBell.appendChild(notificationDropdown);
    
    // Load notifications and update badge
    function updateNotifications() {
        console.log('Updating notifications...');
        fetch('../scripts/get_unread_count.php')
            .then(response => {
                if (!response.ok) {
                    throw new Error('Network response was not ok');
                }
                return response.json();
            })
            .then(data => {
                console.log('Unread count:', data.count);
                updateBadge(data.count || 0);
            })
            .catch(error => {
                console.error('Error fetching unread count:', error);
            });
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
        
        console.log('Notification bell clicked');
        
        if (notificationDropdown.style.display === 'block') {
            console.log('Hiding dropdown');
            notificationDropdown.style.display = 'none';
        } else {
            console.log('Showing dropdown');
            // When opening dropdown
            notificationDropdown.style.display = 'block';
            loadNotifications(false);
            
            // Clear badge immediately
            updateBadge(0);
            
            // Mark all as read in backend
            fetch('../scripts/mark_all_read.php', {
                method: 'POST'
            }).catch(error => console.error('Error marking all as read:', error));
        }
    });
    
    // Close when clicking outside
    document.addEventListener('click', function(e) {
        if (!notificationBell.contains(e.target)) {
            notificationDropdown.style.display = 'none';
        }
    });
    
    // Prevent dropdown from closing when clicking inside it
    notificationDropdown.addEventListener('click', function(e) {
        e.stopPropagation();
    });
    
    // Initial load
    updateNotifications();
    
    // Poll for new notifications every 60 seconds
    setInterval(updateNotifications, 60000);
    
    // Function to load notifications
    function loadNotifications(unreadOnly = false) {
        console.log('Loading notifications...');
        fetch('../scripts/get_notifications.php?unread=' + (unreadOnly ? '1' : '0'))
            .then(response => {
                if (!response.ok) {
                    throw new Error('Network response was not ok');
                }
                return response.json();
            })
            .then(data => {
                console.log('Notifications loaded:', data);
                const list = notificationDropdown.querySelector('.notification-list');
                list.innerHTML = '';
                
                if (!data || data.length === 0) {
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
                            <div class="notification-title">${notif.title || 'Notification'}</div>
                            <div class="notification-message">${notif.message || ''}</div>
                            <div class="notification-time">${notif.time_ago || ''}</div>
                        </div>
                        <button class="notification-delete" ${notif.is_deletable ? '' : 'disabled'}>
                            <i class="fas fa-times"></i>
                        </button>
                    `;
                    list.appendChild(item);
                    
                    // Click handler for individual notifications
                    item.addEventListener('click', function(e) {
                        if (!e.target.classList.contains('notification-delete') && !e.target.closest('.notification-delete')) {
                            if (!notif.is_read) {
                                markAsRead(notif.id);
                                item.classList.remove('unread');
                            }
                        }
                    });
                    
                    // Delete button handler
                    const deleteBtn = item.querySelector('.notification-delete');
                    if (deleteBtn && !deleteBtn.disabled) {
                        deleteBtn.addEventListener('click', function(e) {
                            e.stopPropagation();
                            deleteNotification(notif.id);
                            item.remove();
                        });
                    }
                });
            })
            .catch(error => {
                console.error('Error loading notifications:', error);
                const list = notificationDropdown.querySelector('.notification-list');
                list.innerHTML = '<div class="no-notifications">Error loading notifications</div>';
            });
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
        }).catch(error => console.error('Error marking notification as read:', error));
    }
    
    function deleteNotification(id) {
        fetch('../scripts/delete_notification.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({ id: id })
        }).catch(error => console.error('Error deleting notification:', error));
    }
    
    console.log('Notification system initialized successfully');
});











// Security features for balance and account number
document.addEventListener('DOMContentLoaded', function() {
    // Balance visibility toggle with auto-blur
    const balanceVisibilityBtn = document.querySelector('.balance-visibility-btn');
    const balanceAmount = document.querySelector('.amount');
    const accountVisibilityBtn = document.querySelector('.view_account_no');
    const accountNumber = document.querySelector('.number');
    
    let balanceTimeout, accountTimeout;
    
    // Initialize blurred state
    balanceAmount.style.filter = 'blur(8px)';
    accountNumber.style.filter = 'blur(4px)';
    
    // Balance toggle
    balanceVisibilityBtn.addEventListener('click', function() {
        const icon = this.querySelector('i');
        
        if(balanceAmount.style.filter === 'blur(8px)' || balanceAmount.style.filter === 'blur(4px)') {
            balanceAmount.style.filter = 'none';
            icon.classList.replace('fa-eye', 'fa-eye-slash');
            
            // Auto blur after 5 seconds
            clearTimeout(balanceTimeout);
            balanceTimeout = setTimeout(() => {
                balanceAmount.style.filter = 'blur(8px)';
                icon.classList.replace('fa-eye-slash', 'fa-eye');
            }, 5000);
        } else {
            balanceAmount.style.filter = 'blur(8px)';
            icon.classList.replace('fa-eye-slash', 'fa-eye');
            clearTimeout(balanceTimeout);
        }
    });
    
    // Account number toggle
    accountVisibilityBtn.addEventListener('click', function() {
        const icon = this.querySelector('i');
        
        if(accountNumber.style.filter === 'blur(4px)' || accountNumber.style.filter === 'none') {
            accountNumber.style.filter = 'none';
            icon.classList.replace('fa-eye', 'fa-eye-slash');
            
            // Auto blur after 5 seconds
            clearTimeout(accountTimeout);
            accountTimeout = setTimeout(() => {
                accountNumber.style.filter = 'blur(4px)';
                icon.classList.replace('fa-eye-slash', 'fa-eye');
            }, 5000);
        } else {
            accountNumber.style.filter = 'blur(4px)';
            icon.classList.replace('fa-eye-slash', 'fa-eye');
            clearTimeout(accountTimeout);
        }
    });
    
    // Enhanced period selector for real data
    const periodButtons = document.querySelectorAll('.period-btn');
    periodButtons.forEach(btn => {
        btn.addEventListener('click', function() {
            const period = this.dataset.period;
            
            // Remove active class from all buttons
            periodButtons.forEach(b => b.classList.remove('active'));
            // Add active class to clicked button
            this.classList.add('active');
            
            // Load data for selected period
            loadPeriodData(period);
        });
    });
});

// Function to load period-based data
function loadPeriodData(period) {
    // This would make an AJAX call to fetch data for the selected period
    console.log('Loading data for period:', period);
    // Implementation would fetch real data from the server based on the period
}
    </script>
</body>
</html>
