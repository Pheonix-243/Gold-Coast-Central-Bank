<?php
require_once('../includes/auth.php');
$account = isset($_SESSION['client_account']) ? $_SESSION['client_account'] : null;
if (!$account) {
    // Redirect to login or show an error
    header('Location: ../login.php');
    exit;
}

// Helper function for error handling
function db_error($stmt) {
    if (!$stmt) {
        die('Database error: ' . htmlspecialchars(mysqli_error($GLOBALS['con'])));
    }
}

// 1. Get comprehensive account summary
$sql = "SELECT 
            a.balance, 
            a.account_type, 
            h.name,
            h.email,
            a.registerdate,
            (SELECT COUNT(*) FROM account_history WHERE account = ?) AS total_transactions,
            (SELECT IFNULL(SUM(amount),0) FROM account_history WHERE account = ? AND sender = ?) AS total_spent,
            (SELECT IFNULL(SUM(amount),0) FROM account_history WHERE account = ? AND reciever = ? AND sender != ?) AS total_received,
            (SELECT COUNT(DISTINCT DATE(dt)) FROM account_history WHERE account = ?) AS active_days
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

// 2. Get spending by category
$sql = "SELECT 
            t.name AS category, 
            SUM(CASE WHEN h.sender = ? THEN h.amount ELSE 0 END) AS amount,
            COUNT(CASE WHEN h.sender = ? THEN 1 END) AS count
        FROM 
            account_history h
        JOIN 
            transaction_types t ON h.type = t.id
        WHERE 
            h.account = ?
        GROUP BY 
            t.name
        ORDER BY 
            amount DESC";
$stmt = $con->prepare($sql);
db_error($stmt);
$stmt->bind_param("sss", $account, $account, $account);
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
            SUM(CASE WHEN reciever = ? AND sender != ? THEN amount ELSE 0 END) AS received,
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

// 4. Get transaction velocity (recent activity, last 30 days)
$sql = "SELECT 
            DATE(dt) AS date,
            COUNT(*) AS count,
            SUM(CASE WHEN sender = ? THEN amount ELSE 0 END) AS spent,
            SUM(CASE WHEN reciever = ? AND sender != ? THEN amount ELSE 0 END) AS received
        FROM 
            account_history
        WHERE 
            account = ? AND dt >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
        GROUP BY 
            date
        ORDER BY 
            date DESC";
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

// 5. Get top recipients with fraud detection
$sql = "SELECT 
            reciever AS account,
            r_name AS recipient,
            COUNT(*) AS count,
            SUM(amount) AS total,
            MAX(dt) AS last_transaction,
            (SUM(amount)/COUNT(*)) AS avg_amount
        FROM 
            account_history
        WHERE 
            account = ? AND sender = ? AND r_name != 'System'
        GROUP BY 
            reciever, r_name
        ORDER BY 
            total DESC
        LIMIT 5";
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
            (SELECT STDDEV(amount) FROM account_history WHERE account = ? AND sender = ?) AS stddev,
            (SELECT AVG(amount) FROM account_history WHERE account = ? AND sender = ?) AS avg_amount
        FROM 
            account_history
        WHERE 
            account = ? AND sender = ?
            AND amount > (
                SELECT AVG(amount) + 2*STDDEV(amount) 
                FROM account_history 
                WHERE account = ? AND sender = ?
            )
        ORDER BY 
            dt DESC
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

// Helper: get top category
$topCategory = '';
$topCategoryAmount = 0;
foreach ($categorySpendingArr as $row) {
    if (abs($row['amount']) > $topCategoryAmount) {
        $topCategoryAmount = abs($row['amount']);
        $topCategory = $row['category'];
    }
}

// Helper: get current and previous month spend/income
$currentMonth = date('Y-m');
$currentMonthSpend = 0;
$currentMonthIncome = 0;
$previousMonthSpend = 0;
$previousMonthIncome = 0;
if (count($monthlyTrendsData) > 0) {
    foreach ($monthlyTrendsData as $i => $row) {
        if ($i == 0) {
            $currentMonthSpend = abs($row['spent']);
            $currentMonthIncome = $row['received'];
        } elseif ($i == 1) {
            $previousMonthSpend = abs($row['spent']);
            $previousMonthIncome = $row['received'];
        }
    }
}
$avgMonthlySpend = $accountInfo['total_spent'] ? abs($accountInfo['total_spent']) / max(count($monthlyTrendsData), 1) : 0;
$spendVsAverage = $avgMonthlySpend ? ($currentMonthSpend - $avgMonthlySpend) / $avgMonthlySpend * 100 : 0;
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
                <h1>Financial Analytics</h1>
                <p class="welcome">Detailed insights into your account activity</p>
            </div>

            <div class="topbar_icons">
                <div class="real-time-update">
                    <span id="last-updated">Updating...</span>
                    <i class="fas fa-sync-alt refresh-btn" id="refresh-data"></i>
                </div>
                <a href="#" aria-label="notifications" class="topbar_icon alert">
                    <i class="fas fa-bell"></i>
                </a>
            </div>
        </div>

        <section class="content_section">
            <main class="analytics-container">
                <div class="analytics-grid">
                    <div class="col-span-4">
                        <div class="analytics-card">
                            <div class="analytics-card-header">
                                <h2 class="analytics-card-title">Current Balance</h2>
                                <i class="fas fa-wallet" style="color: var(--primary-gold);"></i>
                            </div>
                            <div class="metric-large" id="current-balance">GHC<?= number_format($accountInfo['balance'], 2) ?></div>
                            <div class="metric-label">As of <span id="balance-time"><?= date('M j, Y H:i') ?></span></div>
                            <div class="metric-change" id="balance-change">
                                <?php
                                if ($previousMonthIncome || $previousMonthSpend) {
                                    $previousBalance = $accountInfo['balance'] - $currentMonthIncome + $currentMonthSpend;
                                    $balanceChange = $previousBalance != 0 ? (($accountInfo['balance'] - $previousBalance) / abs($previousBalance)) * 100 : 0;
                                    if ($balanceChange > 0) {
                                        echo '<span class="trend-up">▲</span> ' . number_format(abs($balanceChange), 1) . '% from last month';
                                    } elseif ($balanceChange < 0) {
                                        echo '<span class="trend-down">▼</span> ' . number_format(abs($balanceChange), 1) . '% from last month';
                                    } else {
                                        echo 'No change from last month';
                                    }
                                }
                                ?>
                            </div>
                        </div>
                    </div>
                    <div class="col-span-4">
                        <div class="analytics-card">
                            <div class="analytics-card-header">
                                <h2 class="analytics-card-title">Total Outflows</h2>
                                <i class="fas fa-arrow-up" style="color: var(--danger-red);"></i>
                            </div>
                            <div class="metric-large" id="total-outflows">GHC<?= number_format(abs($accountInfo['total_spent']), 2) ?></div>
                            <div class="metric-label">Across <span id="total-transactions"><?= $accountInfo['total_transactions'] ?></span> transactions</div>
                            <div class="metric-change" id="spend-change">
                                <?php
                                $spendChange = $previousMonthSpend != 0 ? (($currentMonthSpend - $previousMonthSpend) / $previousMonthSpend) * 100 : 0;
                                if ($spendChange > 0) {
                                    echo '<span class="trend-up">▲</span> ' . number_format(abs($spendChange), 1) . '% from last month';
                                } elseif ($spendChange < 0) {
                                    echo '<span class="trend-down">▼</span> ' . number_format(abs($spendChange), 1) . '% from last month';
                                } else {
                                    echo 'No change from last month';
                                }
                                ?>
                            </div>
                        </div>
                    </div>
                    <div class="col-span-4">
                        <div class="analytics-card">
                            <div class="analytics-card-header">
                                <h2 class="analytics-card-title">Total Inflows</h2>
                                <i class="fas fa-arrow-down" style="color: var(--success-green);"></i>
                            </div>
                            <div class="metric-large" id="total-inflows">GHC<?= number_format($accountInfo['total_received'], 2) ?></div>
                            <div class="metric-label">Customer since <?= date('M Y', strtotime($accountInfo['registerdate'])) ?></div>
                            <div class="metric-change" id="income-change">
                                <?php
                                $incomeChange = $previousMonthIncome != 0 ? (($currentMonthIncome - $previousMonthIncome) / $previousMonthIncome) * 100 : 0;
                                if ($incomeChange > 0) {
                                    echo '<span class="trend-up">▲</span> ' . number_format(abs($incomeChange), 1) . '% from last month';
                                } elseif ($incomeChange < 0) {
                                    echo '<span class="trend-down">▼</span> ' . number_format(abs($incomeChange), 1) . '% from last month';
                                } else {
                                    echo 'No change from last month';
                                }
                                ?>
                            </div>
                        </div>
                    </div>
                </div>



                
                
                                <div class="analytics-card mb-4">
                                    <div class="col-span-6">
                                        <div class="analytics-card">
                                            <div class="analytics-card-header">
                                                <h2 class="analytics-card-title">Transaction Activity (30 Days)</h2>
                                            </div>
                                            <div class="chart-container">
                                                <canvas id="velocityChart"></canvas>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                <div class="analytics-card mb-4">
                    <div class="col-span-8">
                        <div class="analytics-card">
                            <div class="analytics-card-header">
                                <h2 class="analytics-card-title">Monthly Cash Flow</h2>
                            </div>
                            <div class="chart-container">
                                <canvas id="monthlyTrendsChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="analytics-card mb-4">
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






                <div class="analytics-card mb-4">
                    <div class="analytics-card-header">
                        <h2 class="analytics-card-title">Detailed Category Breakdown</h2>
                    </div>
                    <div class="analytics-grid">
                        <?php foreach ($categorySpendingArr as $row):
                            if (abs($row['amount']) > 0):
                                $percentage = $accountInfo['total_spent'] ? (abs($row['amount']) / abs($accountInfo['total_spent']) * 100) : 0;
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
                                <div class="metric-label"><?= $row['count'] ?> transactions (<?= number_format($percentage, 1) ?>%)</div>
                            </div>
                        </div>
                        <?php endif; endforeach; ?>
                    </div>
                </div>

                <div class="analytics-grid col-span-6">
                    <div class="col-span-6">
                        <div class="analytics-card">
                            <div class="analytics-card-header">
                                <h2 class="analytics-card-title">Top Recipients</h2>
                                <i class="fas fa-users" style="color: var(--navy-blue);"></i>
                            </div>
                            <?php if (count($topRecipientsArr) > 0): ?>
                            <table class="analytics-table">
                                <thead>
                                    <tr>
                                        <th>Recipient</th>
                                        <th>Transactions</th>
                                        <th>Total</th>
                                        <th>Last Sent</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($topRecipientsArr as $row): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($row['recipient']) ?></td>
                                        <td><?= $row['count'] ?></td>
                                        <td>GHC<?= number_format(abs($row['total']), 2) ?></td>
                                        <td><?= date('M j', strtotime($row['last_transaction'])) ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                            <?php else: ?>
                            <div class="text-center text-muted mt-4">
                                <p>No recipient data available</p>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="col-span-6">
                        <div class="analytics-card">
                            <div class="analytics-card-header">
                                <h2 class="analytics-card-title">Unusual Activity</h2>
                                <i class="fas fa-exclamation-triangle" style="color: var(--danger-red);"></i>
                            </div>
                            <?php if (count($unusualActivityArr) > 0): ?>
                            <table class="analytics-table">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Amount</th>
                                        <th>Recipient</th>
                                        <th>Description</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($unusualActivityArr as $row): ?>
                                    <tr>
                                        <td><?= date('M j', strtotime($row['dt'])) ?></td>
                                        <td class="text-danger">GHC<?= number_format(abs($row['amount']), 2) ?></td>
                                        <td><?= htmlspecialchars($row['recipient']) ?></td>
                                        <td><?= htmlspecialchars($row['description'] ?? 'N/A') ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                            <?php else: ?>
                            <div class="text-center text-muted mt-4">
                                <p>No unusual activity detected</p>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </main>

            <aside>
                <div class="account_summary">
                    <h3>Account Metrics</h3>
                    <div class="summary_items">
                        <div class="summary_item">
                            <div class="summary_icon" style="background-color: var(--navy-blue);">
                                <i class="fas fa-chart-line"></i>
                            </div>
                            <div class="summary_details">
                                <p>Spending Velocity</p>
                                <p>
                                    <?php
                                    $dailyRate = $accountInfo['total_spent'] ? abs($accountInfo['total_spent']) / 30 : 0;
                                    echo 'GHC'.number_format($dailyRate, 2).'/day';
                                    ?>
                                </p>
                            </div>
                        </div>
                        <div class="summary_item">
                            <div class="summary_icon" style="background-color: var(--success-green);">
                                <i class="fas fa-piggy-bank"></i>
                            </div>
                            <div class="summary_details">
                                <p>Savings Rate</p>
                                <p>
                                    <?php
                                    $savingsRate = $accountInfo['total_received'] ? 
                                        (($accountInfo['total_received'] + $accountInfo['total_spent']) / $accountInfo['total_received']) * 100 : 0;
                                    echo number_format($savingsRate, 1).'%';
                                    ?>
                                </p>
                            </div>
                        </div>
                        <div class="summary_item">
                            <div class="summary_icon" style="background-color: var(--primary-gold);">
                                <i class="fas fa-exchange-alt"></i>
                            </div>
                            <div class="summary_details">
                                <p>Transaction Frequency</p>
                                <p>
                                    <?php
                                    $frequency = $accountInfo['total_transactions'] ? 
                                        30 / $accountInfo['total_transactions'] : 0;
                                    echo 'Every '.number_format($frequency, 1).' days';
                                    ?>
                                </p>
                            </div>
                        </div>
                    </div>
                </div>






                <div class="col-span-6" style="margin-top: 30px;">
                        <div class="analytics-card">
                            <div class="analytics-card-header">
                                <h2 class="analytics-card-title">Financial Insights</h2>
                            </div>
                            <div class="insight-card">
                                <div class="insight-title"><i class="fas fa-chart-pie"></i> Monthly Spending</div>
                                <div class="insight-value">
                                    You've spent GHC<?= number_format($currentMonthSpend, 2) ?> this month.
                                    <?php if ($spendVsAverage > 0): ?>
                                        <span class="trend-up">▲</span> 
                                        <?= number_format(abs($spendVsAverage), 1) ?>% above your average
                                    <?php else: ?>
                                        <span class="trend-down">▼</span> 
                                        <?= number_format(abs($spendVsAverage), 1) ?>% below your average
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="insight-card">
                                <div class="insight-title"><i class="fas fa-tags"></i> Top Spending Category</div>
                                <div class="insight-value">
                                    Your highest spending is on <strong><?= htmlspecialchars($topCategory) ?></strong> with GHC<?= number_format($topCategoryAmount, 2) ?> total.
                                </div>
                            </div>
                            <?php if (count($topRecipientsArr) > 0): $topRecipient = $topRecipientsArr[0]; ?>
                            <div class="insight-card">
                                <div class="insight-title"><i class="fas fa-user-tie"></i> Most Frequent Recipient</div>
                                <div class="insight-value">
                                    You've sent <strong>GHC<?= number_format($topRecipient['total'], 2) ?></strong> to <strong><?= htmlspecialchars($topRecipient['recipient']) ?></strong> across <?= $topRecipient['count'] ?> transactions.
                                </div>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>



                <div class="quick_actions" style="margin-top: 30px;">
                    <h3>Budget Tracking</h3>
                    <div class="progress-container">
                        <div class="progress-label">
                            <span>Monthly Budget</span>
                            <span>65% used</span>
                        </div>
                        <div class="progress-bar-bg">
                            <div class="progress-bar-fill" style="width: 65%"></div>
                        </div>
                        <div class="metric-label">GHC1,200 of GHC2,000</div>
                    </div>
                    <button class="btn btn-secondary full-width" style="margin-top: 15px;">
                        <i class="fas fa-edit"></i> Set Budget
                    </button>
                </div>





                
            </aside>
        </section>
    </section>
</div>

<script>
    // Mobile sidebar toggle
    document.getElementById('menu_btn').addEventListener('click', function() {
        document.getElementById('sidebar').classList.toggle('show_sidebar');
    });

    document.getElementById('btn_close').addEventListener('click', function() {
        document.getElementById('sidebar').classList.remove('show_sidebar');
    });

    // Monthly Trends Chart
    const monthlyTrendsData = <?= json_encode(array_reverse($monthlyTrendsData)) ?>;
    const monthlyLabels = monthlyTrendsData.map(row => moment(row.month + '-01').format('MMM YYYY'));
    const monthlyReceived = monthlyTrendsData.map(row => parseFloat(row.received));
    const monthlySpent = monthlyTrendsData.map(row => Math.abs(row.spent));
    const monthlyCtx = document.getElementById('monthlyTrendsChart').getContext('2d');
    const monthlyTrendsChart = new Chart(monthlyCtx, {
        type: 'bar',
        data: {
            labels: monthlyLabels,
            datasets: [
                {
                    label: 'Money In',
                    data: monthlyReceived,
                    backgroundColor: 'rgba(40, 167, 69, 0.7)',
                    borderColor: 'rgba(40, 167, 69, 1)',
                    borderWidth: 1
                },
                {
                    label: 'Money Out',
                    data: monthlySpent,
                    backgroundColor: 'rgba(220, 53, 69, 0.7)',
                    borderColor: 'rgba(220, 53, 69, 1)',
                    borderWidth: 1
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: { 
                    beginAtZero: true, 
                    title: { 
                        display: true, 
                        text: 'Amount (GHC)',
                        color: 'rgba(27, 54, 93, 0.8)'
                    },
                    grid: {
                        color: 'rgba(0, 0, 0, 0.05)'
                    }
                },
                x: { 
                    title: { 
                        display: true, 
                        text: 'Month',
                        color: 'rgba(27, 54, 93, 0.8)'
                    },
                    grid: {
                        display: false
                    }
                }
            },
            plugins: {
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            return context.dataset.label + ': GHC' + context.raw.toFixed(2);
                        }
                    }
                },
                legend: {
                    labels: {
                        color: 'rgba(27, 54, 93, 0.8)'
                    }
                }
            }
        }
    });

    // Spending Pie Chart
    const categorySpendingArr = <?= json_encode($categorySpendingArr) ?>;
    const pieLabels = categorySpendingArr.filter(row => Math.abs(row.amount) > 0).map(row => row.category);
    const pieData = categorySpendingArr.filter(row => Math.abs(row.amount) > 0).map(row => Math.abs(row.amount));
    const pieCtx = document.getElementById('spendingPieChart').getContext('2d');
    const spendingPieChart = new Chart(pieCtx, {
        type: 'doughnut',
        data: {
            labels: pieLabels,
            datasets: [{
                data: pieData,
                backgroundColor: [
                    '#1B365D', '#2C4F7C', '#FFD700', '#DAA520', '#28A745',
                    '#DC3545', '#FFC107', '#6C757D', '#343A40', '#E6F2FF'
                ],
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { 
                    position: 'right',
                    labels: {
                        color: 'rgba(27, 54, 93, 0.8)'
                    }
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            const label = context.label || '';
                            const value = context.raw || 0;
                            const total = context.dataset.data.reduce((a, b) => a + b, 0);
                            const percentage = Math.round((value / total) * 100);
                            return `${label}: GHC${value.toFixed(2)} (${percentage}%)`;
                        }
                    }
                }
            }
        }
    });

    // Transaction Velocity Chart
    const velocityData = <?= json_encode(array_reverse($velocityData)) ?>;
    const velocityLabels = velocityData.map(row => moment(row.date).format('MMM D'));
    const velocityCounts = velocityData.map(row => parseInt(row.count));
    const velocitySpent = velocityData.map(row => Math.abs(row.spent));
    const velocityReceived = velocityData.map(row => parseFloat(row.received));
    const velocityCtx = document.getElementById('velocityChart').getContext('2d');
    const velocityChart = new Chart(velocityCtx, {
        type: 'line',
        data: {
            labels: velocityLabels,
            datasets: [
                {
                    label: 'Transactions',
                    data: velocityCounts,
                    backgroundColor: 'rgba(27, 54, 93, 0.1)',
                    borderColor: 'rgba(27, 54, 93, 1)',
                    borderWidth: 2,
                    tension: 0.4,
                    yAxisID: 'y1'
                },
                {
                    label: 'Money Out',
                    data: velocitySpent,
                    backgroundColor: 'rgba(220, 53, 69, 0.1)',
                    borderColor: 'rgba(220, 53, 69, 1)',
                    borderWidth: 2,
                    tension: 0.4,
                    yAxisID: 'y'
                },
                {
                    label: 'Money In',
                    data: velocityReceived,
                    backgroundColor: 'rgba(40, 167, 69, 0.1)',
                    borderColor: 'rgba(40, 167, 69, 1)',
                    borderWidth: 2,
                    tension: 0.4,
                    yAxisID: 'y'
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: {
                    type: 'linear',
                    display: true,
                    position: 'left',
                    title: { 
                        display: true, 
                        text: 'Amount (GHC)',
                        color: 'rgba(27, 54, 93, 0.8)'
                    },
                    grid: {
                        color: 'rgba(0, 0, 0, 0.05)'
                    }
                },
                y1: {
                    type: 'linear',
                    display: true,
                    position: 'right',
                    title: { 
                        display: true, 
                        text: 'Transaction Count',
                        color: 'rgba(27, 54, 93, 0.8)'
                    },
                    grid: { drawOnChartArea: false }
                },
                x: {
                    grid: {
                        display: false
                    }
                }
            },
            plugins: {
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            let label = context.dataset.label || '';
                            if (label) label += ': ';
                            if (context.dataset.label === 'Transactions') {
                                label += context.raw;
                            } else {
                                label += 'GHC' + context.raw.toFixed(2);
                            }
                            return label;
                        }
                    }
                },
                legend: {
                    labels: {
                        color: 'rgba(27, 54, 93, 0.8)'
                    }
                }
            }
        }
    });

    // Real-time update functionality
    $(document).ready(function() {
        // Function to update the dashboard
        function updateDashboard() {
            $.ajax({
                url: 'update_analytics.php',
                type: 'POST',
                dataType: 'json',
                data: { account: '<?= $account ?>' },
                success: function(response) {
                    if(response.status === 'success') {
                        // Update summary cards
                        $('#current-balance').text('GHC' + parseFloat(response.data.balance).toFixed(2));
                        $('#total-outflows').text('GHC' + parseFloat(Math.abs(response.data.total_spent)).toFixed(2));
                        $('#total-inflows').text('GHC' + parseFloat(response.data.total_received).toFixed(2));
                        $('#total-transactions').text(response.data.total_transactions);
                        
                        // Update time
                        const now = new Date();
                        $('#balance-time').text(now.toLocaleString('en-US', { 
                            month: 'short', 
                            day: 'numeric', 
                            year: 'numeric', 
                            hour: '2-digit', 
                            minute: '2-digit' 
                        }));
                        $('#last-updated').text('Updated: ' + now.toLocaleTimeString());
                    }
                },
                error: function(xhr, status, error) {
                    console.error("Error updating dashboard: " + error);
                    $('#last-updated').text('Update failed');
                }
            });
        }

        // Manual refresh button
        $('#refresh-data').click(function(e) {
            e.preventDefault();
            $(this).addClass('fa-spin');
            updateDashboard();
            setTimeout(() => {
                $(this).removeClass('fa-spin');
            }, 1000);
        });

        // Auto-update every 60 seconds
        setInterval(updateDashboard, 60000);
        
        // Initial update time display
        $('#last-updated').text('Updated: ' + new Date().toLocaleTimeString());
    });
</script>
</body>
</html>