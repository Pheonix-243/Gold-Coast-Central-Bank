<?php
require_once('../includes/auth.php');
$account = isset($_SESSION['client_account']) ? $_SESSION['client_account'] : null;
if (!$account) {
    header('Location: ../login.php');
    exit;
}

// Helper function for error handling
function db_error($stmt) {
    if (!$stmt) {
        die('Database error: ' . htmlspecialchars(mysqli_error($GLOBALS['con'])));
    }
}

// Get period parameter (default to 30 days)
$period = isset($_GET['period']) ? $_GET['period'] : '30d';
$dateCondition = '';
switch($period) {
    case '7d': $dateCondition = "AND dt >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)"; break;
    case '30d': $dateCondition = "AND dt >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)"; break;
    case '90d': $dateCondition = "AND dt >= DATE_SUB(CURDATE(), INTERVAL 90 DAY)"; break;
    case '1y': $dateCondition = "AND dt >= DATE_SUB(CURDATE(), INTERVAL 1 YEAR)"; break;
    default: $dateCondition = "AND dt >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)";
}

// 1. Get comprehensive account summary
$sql = "SELECT 
            a.balance, 
            a.account_type, 
            h.name,
            h.email,
            a.registerdate,
            (SELECT COUNT(*) FROM account_history WHERE account = ? $dateCondition) AS total_transactions,
            (SELECT IFNULL(SUM(amount),0) FROM account_history WHERE account = ? AND sender = ? $dateCondition) AS total_spent,
            (SELECT IFNULL(SUM(amount),0) FROM account_history WHERE account = ? AND receiver = ? AND sender != ? $dateCondition) AS total_received,
            (SELECT COUNT(DISTINCT DATE(dt)) FROM account_history WHERE account = ? $dateCondition) AS active_days
        FROM 
            accounts_info a
        JOIN 
            accountsholder h ON a.account = h.account
        WHERE 
            a.account = ?";
$stmt = $con->prepare($sql);
db_error($stmt);
$stmt->bind_param("ssssssss", $account, $account, $account, $account, $account, $account, $account, $account);
$stmt->execute();
$accountInfo = $stmt->get_result()->fetch_assoc();
$stmt->close();

// 2. Get spending by category with proper categorization
$sql = "SELECT 
            COALESCE(sc.name, 'Uncategorized') AS category, 
            COALESCE(sc.id, 0) AS category_id,
            COALESCE(sc.color, '#6B7280') AS color,
            COALESCE(sc.icon, 'fas fa-tag') AS icon,
            SUM(CASE WHEN h.sender = ? THEN h.amount ELSE 0 END) AS amount,
            COUNT(CASE WHEN h.sender = ? THEN 1 END) AS count,
            AVG(CASE WHEN h.sender = ? THEN ABS(h.amount) ELSE NULL END) AS avg_amount
        FROM 
            account_history h
        LEFT JOIN 
            spending_categories sc ON h.category_id = sc.id
        WHERE 
            h.account = ? $dateCondition
        GROUP BY 
            sc.id, sc.name, sc.color, sc.icon
        HAVING 
            amount < 0
        ORDER BY 
            ABS(amount) DESC";

$stmt = $con->prepare($sql);
db_error($stmt);
$stmt->bind_param("ssss", $account, $account, $account, $account);
$stmt->execute();
$categorySpendingArr = [];
$res = $stmt->get_result();
while ($row = $res->fetch_assoc()) {
    $categorySpendingArr[] = $row;
}
$stmt->close();

// 3. Get monthly trends (last 12 months)
$sql = "SELECT 
            DATE_FORMAT(STR_TO_DATE(dt, '%Y-%m-%d'), '%Y-%m') AS month,
            SUM(CASE WHEN sender = ? THEN amount ELSE 0 END) AS spent,
            SUM(CASE WHEN receiver = ? AND sender != ? THEN amount ELSE 0 END) AS received,
            COUNT(*) AS transactions
        FROM 
            account_history
        WHERE 
            account = ? AND dt >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
        GROUP BY 
            month
        ORDER BY 
            month DESC";
$stmt = $con->prepare($sql);
db_error($stmt);
$stmt->bind_param("ssss", $account, $account, $account, $account);
$stmt->execute();
$monthlyTrendsData = [];
$res = $stmt->get_result();
while ($row = $res->fetch_assoc()) {
    $monthlyTrendsData[] = $row;
}
$stmt->close();

// 4. Get transaction velocity (recent activity based on period)
$sql = "SELECT 
            DATE(dt) AS date,
            COUNT(*) AS count,
            SUM(CASE WHEN sender = ? THEN amount ELSE 0 END) AS spent,
            SUM(CASE WHEN receiver = ? AND sender != ? THEN amount ELSE 0 END) AS received
        FROM 
            account_history
        WHERE 
            account = ? $dateCondition
        GROUP BY 
            date
        ORDER BY 
            date ASC";

$stmt = $con->prepare($sql);
db_error($stmt);
$stmt->bind_param("ssss", $account, $account, $account, $account);
$stmt->execute();
$velocityData = [];
$res = $stmt->get_result();
while ($row = $res->fetch_assoc()) {
    $velocityData[] = $row;
}
$stmt->close();

// 5. Get top recipients with enhanced metrics
$sql = "SELECT 
            receiver AS account,
            r_name AS recipient,
            COUNT(*) AS count,
            SUM(amount) AS total,
            MAX(dt) AS last_transaction,
            MIN(dt) AS first_transaction,
            (SUM(amount)/COUNT(*)) AS avg_amount,
            CASE 
                WHEN COUNT(*) > 1 THEN DATEDIFF(MAX(dt), MIN(dt)) / (COUNT(*) - 1)
                ELSE NULL 
            END AS days_between_tx
        FROM 
            account_history
        WHERE 
            account = ? AND sender = ? AND r_name != 'System' $dateCondition
        GROUP BY 
            receiver, r_name
        ORDER BY 
            total ASC
        LIMIT 10";

$stmt = $con->prepare($sql);
db_error($stmt);
$stmt->bind_param("ss", $account, $account);
$stmt->execute();
$topRecipientsArr = [];
$res = $stmt->get_result();
while ($row = $res->fetch_assoc()) {
    $topRecipientsArr[] = $row;
}
$stmt->close();

// 6. Get unusual activity (transactions > 2 stddev from mean)
$sql = "SELECT 
            amount, 
            dt, 
            tm, 
            r_name AS recipient,
            description,
            (SELECT STDDEV(amount) FROM account_history WHERE account = ? AND sender = ? $dateCondition) AS stddev,
            (SELECT AVG(amount) FROM account_history WHERE account = ? AND sender = ? $dateCondition) AS avg_amount
        FROM 
            account_history
        WHERE 
            account = ? AND sender = ? $dateCondition
            AND amount < ( 
                SELECT AVG(amount) - 2*STDDEV(amount) 
                FROM account_history 
                WHERE account = ? AND sender = ? $dateCondition
            )
        ORDER BY 
            amount ASC
        LIMIT 5";

$stmt = $con->prepare($sql);
db_error($stmt);
$stmt->bind_param("ssssssss", $account, $account, $account, $account, $account, $account, $account, $account);
$stmt->execute();
$unusualActivityArr = [];
$res = $stmt->get_result();
while ($row = $res->fetch_assoc()) {
    $unusualActivityArr[] = $row;
}
$stmt->close();

// 7. Get velocity and frequency metrics
$sql = "SELECT 
            COUNT(*) AS total_tx,
            SUM(CASE WHEN sender = ? THEN 1 ELSE 0 END) AS outgoing_tx,
            SUM(CASE WHEN receiver = ? AND sender != ? THEN 1 ELSE 0 END) AS incoming_tx,
            AVG(CASE WHEN sender = ? THEN ABS(amount) ELSE NULL END) AS avg_outgoing,
            AVG(CASE WHEN receiver = ? AND sender != ? THEN amount ELSE NULL END) AS avg_incoming,
            MIN(ABS(amount)) AS min_amount,
            MAX(ABS(amount)) AS max_amount
        FROM 
            account_history
        WHERE 
            account = ? $dateCondition";

$stmt = $con->prepare($sql);
db_error($stmt);
$stmt->bind_param("sssssss", $account, $account, $account, $account, $account, $account, $account);
$stmt->execute();
$velocityMetrics = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Calculate median manually (since PERCENTILE_CONT not available in MySQL)
$sql = "SELECT ABS(amount) as abs_amount 
        FROM account_history 
        WHERE account = ? AND (sender = ? OR (receiver = ? AND sender != ?)) 
        $dateCondition 
        ORDER BY abs_amount";
$stmt = $con->prepare($sql);
db_error($stmt);
$stmt->bind_param("ssss", $account, $account, $account, $account);
$stmt->execute();
$amounts = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Calculate median
$medianAmount = 0;
if (!empty($amounts)) {
    $values = array_column($amounts, 'abs_amount');
    sort($values);
    $count = count($values);
    $middle = floor(($count - 1) / 2);
    
    if ($count % 2) {
        $medianAmount = $values[$middle];
    } else {
        $medianAmount = ($values[$middle] + $values[$middle + 1]) / 2;
    }
}
$velocityMetrics['median_amount'] = $medianAmount;

// Helper: get top category
$topCategory = '';
$topCategoryAmount = 0;
foreach ($categorySpendingArr as $row) {
    if (abs($row['amount']) > $topCategoryAmount && $row['amount'] < 0) {
        $topCategoryAmount = abs($row['amount']);
        $topCategory = $row['category'];
    }
}

// Helper: get current and previous period spend/income
$currentPeriodSpend = abs($accountInfo['total_spent']);
$currentPeriodIncome = $accountInfo['total_received'];

// Calculate previous period for comparison
$prevPeriodCondition = str_replace('CURDATE()', 'DATE_SUB(CURDATE(), INTERVAL 1 MONTH)', $dateCondition);
$sql = "SELECT 
            SUM(CASE WHEN sender = ? THEN amount ELSE 0 END) AS prev_spent,
            SUM(CASE WHEN receiver = ? AND sender != ? THEN amount ELSE 0 END) AS prev_received
        FROM 
            account_history
        WHERE 
            account = ? $prevPeriodCondition";

$stmt = $con->prepare($sql);
db_error($stmt);
$stmt->bind_param("ssss", $account, $account, $account, $account);
$stmt->execute();
$prevPeriodData = $stmt->get_result()->fetch_assoc();
$stmt->close();

$previousPeriodSpend = abs($prevPeriodData['prev_spent'] ?? 0);
$previousPeriodIncome = $prevPeriodData['prev_received'] ?? 0;

// Calculate changes
$spendChange = $previousPeriodSpend != 0 ? (($currentPeriodSpend - $previousPeriodSpend) / $previousPeriodSpend) * 100 : 0;
$incomeChange = $previousPeriodIncome != 0 ? (($currentPeriodIncome - $previousPeriodIncome) / $previousPeriodIncome) * 100 : 0;

// Calculate net cash flow
$netCashFlow = $currentPeriodIncome - $currentPeriodSpend;

// Calculate savings rate
$savingsRate = $currentPeriodIncome > 0 ? ($netCashFlow / $currentPeriodIncome) * 100 : 0;

// Calculate transaction frequency
$daysInPeriod = 30; // Default for 30d
if ($period === '7d') $daysInPeriod = 7;
if ($period === '90d') $daysInPeriod = 90;
if ($period === '1y') $daysInPeriod = 365;

$txFrequency = $accountInfo['total_transactions'] > 0 ? $daysInPeriod / $accountInfo['total_transactions'] : 0;
$dailySpendRate = $currentPeriodSpend / $daysInPeriod;

// Prepare data for recipient relationship visualization
$recipientData = [];
foreach ($topRecipientsArr as $recipient) {
    $recipientData[] = [
        'name' => $recipient['recipient'],
        'value' => abs($recipient['total']),
        'count' => $recipient['count'],
        'avg' => abs($recipient['avg_amount'])
    ];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Gold Coast Central Bank - Financial Analytics</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-datalabels@2.0.0"></script>
    <script src="https://cdn.jsdelivr.net/npm/moment"></script>
    <script src="https://cdn.jsdelivr.net/npm/chartjs-adapter-moment"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <link rel="stylesheet" href="../dashboard/style.css">
    <link rel="stylesheet" href="analytic.css">
</head>
<body>
<div class="container">
    <!-- Navigation Sidebar (unchanged from original) -->
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
            <a href="../pages/analytics.php" class="nav_link active" aria-label="history">
                <div class="nav_link_icon">
                    <i class="fas fa-chart-line"></i>
                </div>
                <div class="nav_link_text">Analytics</div>
            </a>

            <a href="security.php" class="nav_link" aria-label="settings">
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
                <h1>Financial Analytics</h1>
                <p class="welcome">Detailed insights into your account activity</p>
            </div>

            <div class="topbar_icons">
                <div class="real-time-update">
                    <span id="last-updated">Updating...</span>
                    <i class="fas fa-sync-alt refresh-btn" id="refresh-data"></i>
                </div>
                <div class="period-selector">
                    <button class="period-btn <?= $period === '7d' ? 'active' : '' ?>" data-period="7d">7D</button>
                    <button class="period-btn <?= $period === '30d' ? 'active' : '' ?>" data-period="30d">30D</button>
                    <button class="period-btn <?= $period === '90d' ? 'active' : '' ?>" data-period="90d">90D</button>
                    <button class="period-btn <?= $period === '1y' ? 'active' : '' ?>" data-period="1y">1Y</button>
                </div>
                <a href="#" aria-label="notifications" class="topbar_icon alert">
                    <i class="fas fa-bell"></i>
                </a>
            </div>
        </div>

        <section class="content_section">
            <main class="analytics-container">
                <!-- Key Metrics Row -->
                <div class="analytics-grid">
                    <div class="col-span-3">
                        <div class="analytics-card">
                            <div class="analytics-card-header">
                                <h2 class="analytics-card-title">Current Balance</h2>
                                <i class="fas fa-wallet" style="color: var(--primary-gold);"></i>
                            </div>
                            <div class="metric-large" id="current-balance">GHC<?= number_format($accountInfo['balance'], 2) ?></div>
                            <div class="metric-label">As of <span id="balance-time"><?= date('M j, Y H:i') ?></span></div>
                        </div>
                    </div>
                    
                    <div class="col-span-3">
                        <div class="analytics-card">
                            <div class="analytics-card-header">
                                <h2 class="analytics-card-title">Net Cash Flow</h2>
                                <i class="fas fa-exchange-alt" style="color: var(--info-blue);"></i>
                            </div>
                            <div class="metric-large <?= $netCashFlow >= 0 ? 'text-success' : 'text-danger' ?>" id="net-cashflow">
                                GHC<?= number_format($netCashFlow, 2) ?>
                            </div>
                            <div class="metric-label">Income vs Spending</div>
                            <div class="metric-change">
                                <?php if($netCashFlow >= 0): ?>
                                    <span class="trend-up">▲</span> Positive cash flow
                                <?php else: ?>
                                    <span class="trend-down">▼</span> Negative cash flow
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-span-3">
                        <div class="analytics-card">
                            <div class="analytics-card-header">
                                <h2 class="analytics-card-title">Spending Rate</h2>
                                <i class="fas fa-chart-line" style="color: var(--danger-red);"></i>
                            </div>
                            <div class="metric-large">GHC<?= number_format($dailySpendRate, 2) ?></div>
                            <div class="metric-label">Per day average</div>
                            <div class="metric-change" id="spend-change">
                                <?php if($spendChange > 0): ?>
                                    <span class="trend-up">▲</span> <?= number_format(abs($spendChange), 1) ?>% from last period
                                <?php elseif($spendChange < 0): ?>
                                    <span class="trend-down">▼</span> <?= number_format(abs($spendChange), 1) ?>% from last period
                                <?php else: ?>
                                    <span class="trend-neutral">●</span> No change from last period
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-span-3">
                        <div class="analytics-card">
                            <div class="analytics-card-header">
                                <h2 class="analytics-card-title">Savings Rate</h2>
                                <i class="fas fa-piggy-bank" style="color: var(--success-green);"></i>
                            </div>
                            <div class="metric-large <?= $savingsRate >= 0 ? 'text-success' : 'text-danger' ?>">
                                <?= number_format($savingsRate, 1) ?>%
                            </div>
                            <div class="metric-label">Of total income</div>
                            <div class="metric-change">
                                <?php if($savingsRate >= 20): ?>
                                    <span class="trend-up">▲</span> Strong savings
                                <?php elseif($savingsRate >= 0): ?>
                                    <span class="trend-neutral">●</span> Moderate savings
                                <?php else: ?>
                                    <span class="trend-down">▼</span> Drawing down savings
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Transaction Statistics -->
                <div class="analytics-card">
                    <div class="analytics-card-header">
                        <h2 class="analytics-card-title">Transaction Statistics</h2>
                        <div class="analytics-card-subtitle">Based on selected period</div>
                    </div>
                    <div class="stats-grid">
                        <div class="stat-item">
                            <div class="stat-value"><?= $accountInfo['total_transactions'] ?></div>
                            <div class="stat-label">Total Transactions</div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-value"><?= $velocityMetrics['outgoing_tx'] ?? 0 ?></div>
                            <div class="stat-label">Outgoing</div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-value"><?= $velocityMetrics['incoming_tx'] ?? 0 ?></div>
                            <div class="stat-label">Incoming</div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-value"><?= number_format($txFrequency, 1) ?>d</div>
                            <div class="stat-label">Avg. Between TX</div>
                        </div>
                    </div>
                </div>

                <!-- Charts Row -->
                <div class="analytics-grid">
                    <div class="col-span-8">
                        <div class="analytics-card">
                            <div class="analytics-card-header">
                                <h2 class="analytics-card-title">Cash Flow Timeline</h2>
                            </div>
                            <div class="chart-container">
                                <canvas id="cashFlowChart"></canvas>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-span-4">
                        <div class="analytics-card">
                            <div class="analytics-card-header">
                                <h2 class="analytics-card-title">Spending by Category</h2>
                            </div>
                            <div class="chart-container">
                                <canvas id="spendingPieChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Category Breakdown & Velocity -->
                <div class="analytics-grid">
                    <div class="col-span-6">
                        <div class="analytics-card">
                            <div class="analytics-card-header">
                                <h2 class="analytics-card-title">Category Distribution</h2>
                            </div>
                            <div class="chart-container">
                                <canvas id="categoryBarChart"></canvas>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-span-6">
                        <div class="analytics-card">
                            <div class="analytics-card-header">
                                <h2 class="analytics-card-title">Transaction Velocity</h2>
                            </div>
                            <div class="chart-container">
                                <canvas id="velocityChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Detailed Category Breakdown -->
                <div class="analytics-card">
                    <div class="analytics-card-header">
                        <h2 class="analytics-card-title">Detailed Category Analysis</h2>
                    </div>
                    <div class="analytics-grid">
                        <?php 
                        $hasCategoryData = false;
                        foreach ($categorySpendingArr as $row): 
                            if (abs($row['amount']) > 0 && $row['amount'] < 0): 
                                $hasCategoryData = true;
                                $percentage = $currentPeriodSpend ? (abs($row['amount']) / $currentPeriodSpend * 100) : 0;
                        ?>
                        <div class="col-span-4">
                            <div class="progress-container">
                                <div class="progress-label">
                                    <span><?= htmlspecialchars($row['category']) ?></span>
                                    <span>GHC<?= number_format(abs($row['amount']), 2) ?></span>
                                </div>
                                <div class="progress-bar-bg">
                                    <div class="progress-bar-fill" style="width: <?= $percentage ?>%"></div>
                                </div>
                                <div class="metric-label">
                                    <?= number_format($percentage, 1) ?>% of spending • <?= $row['count'] ?> transactions
                                </div>
                            </div>
                        </div>
                        <?php endif; endforeach; ?>
                        
                        <?php if (!$hasCategoryData): ?>
                        <div class="col-span-12">
                            <div class="no-data">
                                <i class="fas fa-chart-pie"></i>
                                <p>No categorized spending data available</p>
                                <p class="text-muted">Transactions will appear here once categorized</p>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Top Recipients -->
                <div class="analytics-card">
                    <div class="analytics-card-header">
                        <h2 class="analytics-card-title">Top Recipients</h2>
                        <div class="analytics-card-subtitle">People & businesses you send money to most</div>
                    </div>
                    
                    <?php if (!empty($topRecipientsArr)): ?>
                    <div class="analytics-grid">
                        <div class="col-span-6">
                            <table class="analytics-table">
                                <thead>
                                    <tr>
                                        <th>Recipient</th>
                                        <th>Total</th>
                                        <th>Frequency</th>
                                        <th>Avg. Amount</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($topRecipientsArr as $recipient): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($recipient['recipient']) ?></td>
                                        <td>GHC<?= number_format(abs($recipient['total']), 2) ?></td>
                                        <td><?= $recipient['count'] ?> tx</td>
                                        <td>GHC<?= number_format(abs($recipient['avg_amount']), 2) ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <div class="col-span-6">
                            <div class="chart-container-small">
                                <canvas id="recipientsChart"></canvas>
                            </div>
                        </div>
                    </div>
                    <?php else: ?>
                    <div class="no-data">
                        <i class="fas fa-users"></i>
                        <p>No recipient data available for analysis</p>
                        <p class="text-muted">Outgoing transfers will appear here</p>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- Key Insights -->
                <div class="analytics-card">
                    <div class="analytics-card-header">
                        <h2 class="analytics-card-title">Key Financial Insights</h2>
                    </div>
                    <div class="analytics-grid">
                        <?php if ($topCategory): ?>
                        <div class="col-span-4">
                            <div class="insight-card">
                                <div class="insight-title">
                                    <i class="fas fa-tags"></i>
                                    <span>Top Spending Category</span>
                                </div>
                                <div class="insight-value">
                                    Your largest expense category is <strong><?= htmlspecialchars($topCategory) ?></strong>, 
                                    accounting for GHC<?= number_format($topCategoryAmount, 2) ?> of your spending.
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <?php if ($txFrequency > 0): ?>
                        <div class="col-span-4">
                            <div class="insight-card">
                                <div class="insight-title">
                                    <i class="fas fa-history"></i>
                                    <span>Transaction Frequency</span>
                                </div>
                                <div class="insight-value">
                                    You make transactions approximately every <strong><?= number_format($txFrequency, 1) ?> days</strong> 
                                    on average.
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <?php if ($velocityMetrics['median_amount'] > 0): ?>
                        <div class="col-span-4">
                            <div class="insight-card">
                                <div class="insight-title">
                                    <i class="fas fa-balance-scale"></i>
                                    <span>Typical Transaction Size</span>
                                </div>
                                <div class="insight-value">
                                    Your median transaction amount is <strong>GHC<?= number_format($velocityMetrics['median_amount'], 2) ?></strong>, 
                                    with most transactions falling in this range.
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <?php if ($savingsRate > 0): ?>
                        <div class="col-span-4">
                            <div class="insight-card">
                                <div class="insight-title">
                                    <i class="fas fa-piggy-bank"></i>
                                    <span>Savings Performance</span>
                                </div>
                                <div class="insight-value">
                                    You're saving <strong><?= number_format($savingsRate, 1) ?>%</strong> of your income, 
                                    which is a <?= $savingsRate >= 20 ? 'strong' : ($savingsRate >= 10 ? 'moderate' : 'modest') ?> savings rate.
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($unusualActivityArr)): ?>
                        <div class="col-span-4">
                            <div class="insight-card">
                                <div class="insight-title">
                                    <i class="fas fa-exclamation-triangle"></i>
                                    <span>Unusual Activity</span>
                                </div>
                                <div class="insight-value">
                                    We detected <strong><?= count($unusualActivityArr) ?> unusual transactions</strong> 
                                    that were significantly different from your typical spending patterns.
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <?php if (empty($categorySpendingArr) || count($categorySpendingArr) === 0 || (count($categorySpendingArr) === 1 && $categorySpendingArr[0]['category'] === 'Uncategorized')): ?>
                        <div class="col-span-4">
                            <div class="insight-card">
                                <div class="insight-title">
                                    <i class="fas fa-lightbulb"></i>
                                    <span>Improve Your Insights</span>
                                </div>
                                <div class="insight-value">
                                    Categorize your transactions to get more detailed insights into your spending patterns and trends.
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </main>
        </section>
    </section>
</div>

<script>
// Data from PHP backend
const analyticsData = {
    velocityData: <?= json_encode($velocityData) ?>,
    categorySpending: <?= json_encode($categorySpendingArr) ?>,
    monthlyTrends: <?= json_encode($monthlyTrendsData) ?>,
    topRecipients: <?= json_encode($topRecipientsArr) ?>,
    period: '<?= $period ?>',
    accountInfo: <?= json_encode($accountInfo) ?>
};

// Chart colors
const chartColors = {
    primary: '#1B365D',
    secondary: '#FFD700',
    success: '#28A745',
    danger: '#DC3545',
    warning: '#FF9800',
    info: '#17A2B8',
    light: '#F5F5F5',
    gray: '#999999'
};

// Category color mapping
const categoryColors = [
    '#1B365D', '#FFD700', '#28A745', '#DC3545', '#FF9800', 
    '#17A2B8', '#6F42C1', '#E83E8C', '#20C997', '#FD7E14'
];
</script>
<script>
    // Analytics Charts Implementation
document.addEventListener("DOMContentLoaded", function () {
  // Initialize all charts
  initializeCashFlowChart();
  initializeSpendingPieChart();
  initializeCategoryBarChart();
  initializeVelocityChart();
  initializeRecipientsChart();

  // Set up period selector
  setupPeriodSelector();

  // Set up refresh button
  setupRefreshButton();

  // Update last updated time
  updateLastUpdatedTime();
});

// Period selector functionality
function setupPeriodSelector() {
  const periodBtns = document.querySelectorAll(".period-btn");
  periodBtns.forEach((btn) => {
    btn.addEventListener("click", function () {
      const period = this.getAttribute("data-period");
      // Update active state
      periodBtns.forEach((b) => b.classList.remove("active"));
      this.classList.add("active");
      // Reload page with new period
      const url = new URL(window.location);
      url.searchParams.set("period", period);
      window.location.href = url.toString();
    });
  });
}

// Refresh button functionality
function setupRefreshButton() {
  const refreshBtn = document.getElementById("refresh-data");
  if (refreshBtn) {
    refreshBtn.addEventListener("click", function () {
      // Add loading animation
      this.classList.add("fa-spin");
      // Reload page after a short delay
      setTimeout(() => {
        window.location.reload();
      }, 500);
    });
  }
}

// Update last updated time
function updateLastUpdatedTime() {
  const lastUpdatedEl = document.getElementById("last-updated");
  if (lastUpdatedEl) {
    const now = new Date();
    lastUpdatedEl.textContent = `Updated: ${now.toLocaleTimeString()}`;
  }
}

// Cash Flow Timeline Chart
function initializeCashFlowChart() {
  const ctx = document.getElementById("cashFlowChart");
  if (!ctx) return;

  const data = analyticsData.velocityData;
  if (!data || data.length === 0) {
    ctx.parentElement.innerHTML = '<div class="no-data"><i class="fas fa-chart-line"></i><p>No cash flow data available</p></div>';
    return;
  }

  const dates = data.map((item) => item.date);
  const spent = data.map((item) => Math.abs(item.spent || 0));
  const received = data.map((item) => Math.abs(item.received || 0));

  new Chart(ctx, {
    type: "line",
    data: {
      labels: dates,
      datasets: [
        {
          label: "Money Out",
          data: spent,
          borderColor: chartColors.danger,
          backgroundColor: "rgba(220, 53, 69, 0.1)",
          tension: 0.4,
          fill: true,
        },
        {
          label: "Money In",
          data: received,
          borderColor: chartColors.success,
          backgroundColor: "rgba(40, 167, 69, 0.1)",
          tension: 0.4,
          fill: true,
        },
      ],
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      plugins: {
        legend: {
          position: "top",
        },
        tooltip: {
          mode: "index",
          intersect: false,
          callbacks: {
            label: function (context) {
              return `${context.dataset.label}: GHC${context.raw.toFixed(2)}`;
            },
          },
        },
      },
      scales: {
        x: {
          display: true,
          title: {
            display: true,
            text: "Date",
          },
        },
        y: {
          display: true,
          title: {
            display: true,
            text: "Amount (GHC)",
          },
          beginAtZero: true,
        },
      },
    },
  });
}

// Spending by Category Pie Chart
function initializeSpendingPieChart() {
  const ctx = document.getElementById("spendingPieChart");
  if (!ctx) return;

  const categoryData = analyticsData.categorySpending;
  if (!categoryData || categoryData.length === 0) {
    ctx.parentElement.innerHTML = '<div class="no-data"><i class="fas fa-chart-pie"></i><p>No category data available</p></div>';
    return;
  }

  const labels = categoryData.map((item) => item.category);
  const amounts = categoryData.map((item) => Math.abs(item.amount));
  const backgroundColors = categoryData.map((item, index) => item.color || categoryColors[index % categoryColors.length]);

  new Chart(ctx, {
    type: "pie",
    data: {
      labels: labels,
      datasets: [
        {
          data: amounts,
          backgroundColor: backgroundColors,
          borderWidth: 2,
          borderColor: "#fff",
        },
      ],
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      plugins: {
        legend: {
          position: "right",
        },
        tooltip: {
          callbacks: {
            label: function (context) {
              const total = amounts.reduce((a, b) => a + b, 0);
              const percentage = ((context.raw / total) * 100).toFixed(1);
              return `${context.label}: GHC${context.raw.toFixed(2)} (${percentage}%)`;
            },
          },
        },
      },
    },
  });
}

// Category Distribution Bar Chart
function initializeCategoryBarChart() {
  const ctx = document.getElementById("categoryBarChart");
  if (!ctx) return;

  const categoryData = analyticsData.categorySpending;
  if (!categoryData || categoryData.length === 0) {
    ctx.parentElement.innerHTML = '<div class="no-data"><i class="fas fa-chart-bar"></i><p>No category data available</p></div>';
    return;
  }

  const labels = categoryData.map((item) => item.category);
  const amounts = categoryData.map((item) => Math.abs(item.amount));
  const backgroundColors = categoryData.map((item, index) => item.color || categoryColors[index % categoryColors.length]);

  new Chart(ctx, {
    type: "bar",
    data: {
      labels: labels,
      datasets: [
        {
          label: "Amount Spent",
          data: amounts,
          backgroundColor: backgroundColors,
          borderWidth: 0,
        },
      ],
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      plugins: {
        legend: {
          display: false,
        },
        tooltip: {
          callbacks: {
            label: function (context) {
              return `GHC${context.raw.toFixed(2)}`;
            },
          },
        },
      },
      scales: {
        y: {
          beginAtZero: true,
          title: {
            display: true,
            text: "Amount (GHC)",
          },
        },
        x: {
          ticks: {
            maxRotation: 45,
          },
        },
      },
    },
  });
}

// Transaction Velocity Chart
function initializeVelocityChart() {
  const ctx = document.getElementById("velocityChart");
  if (!ctx) return;

  const velocityData = analyticsData.velocityData;
  if (!velocityData || velocityData.length === 0) {
    ctx.parentElement.innerHTML = '<div class="no-data"><i class="fas fa-running"></i><p>No velocity data available</p></div>';
    return;
  }

  const dates = velocityData.map((item) => item.date);
  const counts = velocityData.map((item) => item.count);

  new Chart(ctx, {
    type: "bar",
    data: {
      labels: dates,
      datasets: [
        {
          label: "Transactions per Day",
          data: counts,
          backgroundColor: chartColors.primary,
          borderWidth: 0,
        },
      ],
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      plugins: {
        legend: {
          display: false,
        },
        tooltip: {
          callbacks: {
            label: function (context) {
              return `${context.raw} transactions`;
            },
          },
        },
      },
      scales: {
        y: {
          beginAtZero: true,
          title: {
            display: true,
            text: "Number of Transactions",
          },
        },
        x: {
          title: {
            display: true,
            text: "Date",
          },
        },
      },
    },
  });
}

// Top Recipients Chart
function initializeRecipientsChart() {
  const ctx = document.getElementById("recipientsChart");
  if (!ctx) return;

  const recipientsData = analyticsData.topRecipients;
  if (!recipientsData || recipientsData.length === 0) {
    return;
  }

  const labels = recipientsData.map((item) => item.recipient);
  const amounts = recipientsData.map((item) => Math.abs(item.total));

  new Chart(ctx, {
    type: "doughnut",
    data: {
      labels: labels,
      datasets: [
        {
          data: amounts,
          backgroundColor: categoryColors,
          borderWidth: 2,
          borderColor: "#fff",
        },
      ],
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      plugins: {
        legend: {
          position: "bottom",
        },
        tooltip: {
          callbacks: {
            label: function (context) {
              return `${context.label}: GHC${context.raw.toFixed(2)}`;
            },
          },
        },
      },
    },
  });
}
</script>
</body>
</html>