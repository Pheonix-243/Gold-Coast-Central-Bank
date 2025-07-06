<?php
require_once('../includes/auth.php');
$account = $_SESSION['client_account'];

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
    <title>Advanced Analytics Dashboard</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <!-- Chart.js and plugins -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-datalabels@2.0.0"></script>
    <script src="https://cdn.jsdelivr.net/npm/moment"></script>
    <script src="https://cdn.jsdelivr.net/npm/chartjs-adapter-moment"></script>
    <link rel="stylesheet" href="analytic.css">
    <style>
        /* ... (keep your CSS as before, or improve as needed) ... */
    </style>
</head>
<body class="light">
<div class="container">
    <section class="main_content">
        <div class="topbar">
            <div class="overview_text">
                <p class="title">Advanced Analytics Dashboard</p>
                <p class="desc">Deep insights into your financial activities</p>
            </div>
        </div>
        <section>
            <main>
                <!-- Summary Cards -->
                <div class="analytics-grid mb-4">
                    <div class="col-span-4">
                        <div class="analytics-card">
                            <div class="analytics-card-header">
                                <h2 class="analytics-card-title">Current Balance</h2>
                                <i class="fas fa-wallet"></i>
                            </div>
                            <div class="metric-large">GHC<?= number_format($accountInfo['balance'], 2) ?></div>
                            <div class="metric-label">As of <?= date('M j, Y H:i') ?></div>
                            <div class="metric-change">
                                <?php
                                // Calculate balance change from last month
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
                                <i class="fas fa-arrow-up text-danger"></i>
                            </div>
                            <div class="metric-large">GHC<?= number_format(abs($accountInfo['total_spent']), 2) ?></div>
                            <div class="metric-label">Across <?= $accountInfo['total_transactions'] ?> transactions</div>
                            <div class="metric-change">
                                <?php
                                // Calculate spending change from last month
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
                                <i class="fas fa-arrow-down text-success"></i>
                            </div>
                            <div class="metric-large">GHC<?= number_format($accountInfo['total_received'], 2) ?></div>
                            <div class="metric-label">Customer since <?= date('M Y', strtotime($accountInfo['registerdate'])) ?></div>
                            <div class="metric-change">
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
                <!-- Monthly Trends Chart -->
                <div class="row mb-4">
                    <div class="col-md-8">
                        <div class="analytics-card">
                            <div class="analytics-title">Monthly Cash Flow</div>
                            <div class="chart-container">
                                <canvas id="monthlyTrendsChart"></canvas>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="analytics-card">
                            <div class="analytics-title">Spending by Category</div>
                            <div class="chart-container">
                                <canvas id="spendingPieChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
                <!-- Transaction Velocity and Insights -->
                <div class="row mb-4">
                    <div class="col-md-6">
                        <div class="analytics-card">
                            <div class="analytics-title">Transaction Activity (30 Days)</div>
                            <div class="chart-container">
                                <canvas id="velocityChart"></canvas>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="analytics-card">
                            <div class="analytics-title">Financial Insights</div>
                            <div class="insight-card">
                                <div class="insight-title">Monthly Spending</div>
                                <p>You've spent GHC<?= number_format($currentMonthSpend, 2) ?> this month.</p>
                                <p>
                                    <?php if ($spendVsAverage > 0): ?>
                                        <span class="trend-up">▲</span> 
                                        <?= number_format(abs($spendVsAverage), 1) ?>% above your average
                                    <?php else: ?>
                                        <span class="trend-down">▼</span> 
                                        <?= number_format(abs($spendVsAverage), 1) ?>% below your average
                                    <?php endif; ?>
                                </p>
                            </div>
                            <div class="insight-card">
                                <div class="insight-title">Top Spending Category</div>
                                <p>Your highest spending is on <strong><?= htmlspecialchars($topCategory) ?></strong> with GHC<?= number_format($topCategoryAmount, 2) ?> total.</p>
                            </div>
                            <?php if (count($topRecipientsArr) > 0): $topRecipient = $topRecipientsArr[0]; ?>
                            <div class="insight-card">
                                <div class="insight-title">Most Frequent Recipient</div>
                                <p>You've sent <strong><?= number_format($topRecipient['total'], 2) ?></strong> to <strong><?= htmlspecialchars($topRecipient['recipient']) ?></strong> across <?= $topRecipient['count'] ?> transactions.</p>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <!-- Detailed Category Breakdown -->
                <div class="row">
                    <div class="col-md-12">
                        <div class="analytics-card">
                            <div class="analytics-title">Detailed Category Breakdown</div>
                            <div class="row">
                                <?php foreach ($categorySpendingArr as $row):
                                    if (abs($row['amount']) > 0):
                                        $percentage = $accountInfo['total_spent'] ? (abs($row['amount']) / abs($accountInfo['total_spent']) * 100) : 0;
                                ?>
                                <div class="col-md-4 mb-3">
                                    <div class="d-flex justify-content-between">
                                        <span><?= htmlspecialchars($row['category']) ?></span>
                                        <span>GHC<?= number_format(abs($row['amount']), 2) ?></span>
                                    </div>
                                    <div class="progress mt-1" style="height: 8px;">
                                        <div class="progress-bar" role="progressbar" style="width: <?= $percentage ?>%" 
                                             aria-valuenow="<?= $percentage ?>" aria-valuemin="0" aria-valuemax="100"></div>
                                    </div>
                                    <small class="text-muted"><?= $row['count'] ?> transactions (<?= number_format($percentage, 1) ?>%)</small>
                                </div>
                                <?php endif; endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>
                <!-- Top Recipients Table -->
                <div class="col-span-6">
                    <div class="analytics-card">
                        <div class="analytics-card-header">
                            <h2 class="analytics-card-title">Top Recipients</h2>
                            <i class="fas fa-users"></i>
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
                <!-- Unusual Activity Table -->
                <div class="col-span-6">
                    <div class="analytics-card">
                        <div class="analytics-card-header">
                            <h2 class="analytics-card-title">Unusual Activity</h2>
                            <i class="fas fa-exclamation-triangle text-danger"></i>
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
            </main>
            <aside>
                <!-- Enhanced Account Summary -->
                <div class="cards">
                    <div class="title_with_button">
                        <p class="title">Advanced Account Metrics</p>
                    </div>
                    <div class="account_summary">
                        <div class="summary_item">
                            <i class="fas fa-chart-line"></i>
                            <div>
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
                            <i class="fas fa-piggy-bank"></i>
                            <div>
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
                            <i class="fas fa-exchange-alt"></i>
                            <div>
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
                <!-- Budget Tracking -->
                <div class="cards mt-4">
                    <div class="title_with_button">
                        <p class="title">Budget Tracking</p>
                    </div>
                    <div class="analytics-card">
                        <div class="progress mb-2">
                            <div class="progress-bar bg-success" role="progressbar" style="width: 65%" 
                                 aria-valuenow="65" aria-valuemin="0" aria-valuemax="100"></div>
                        </div>
                        <div class="d-flex justify-content-between">
                            <small>GHC1,200 of GHC2,000</small>
                            <small>65%</small>
                        </div>
                        <div class="mt-3">
                            <button class="btn btn-sm btn-outline-primary w-100">Set Budget</button>
                        </div>
                    </div>
                </div>
            </aside>
        </section>
    </section>
</div>
<footer class="analytics-footer">
    <div class="container">
        <p>Analytics generated on <?= date('M j, Y \a\t H:i') ?></p>
        <p class="text-muted">Data is refreshed every 15 minutes. Last refresh: Just now</p>
    </div>
</footer>
<script>
    // Monthly Trends Chart
    const monthlyTrendsData = <?= json_encode(array_reverse($monthlyTrendsData)) ?>;
    const monthlyLabels = monthlyTrendsData.map(row => moment(row.month + '-01').format('MMM YYYY'));
    const monthlyReceived = monthlyTrendsData.map(row => parseFloat(row.received));
    const monthlySpent = monthlyTrendsData.map(row => Math.abs(row.spent));
    const monthlyCtx = document.getElementById('monthlyTrendsChart').getContext('2d');
    new Chart(monthlyCtx, {
        type: 'bar',
        data: {
            labels: monthlyLabels,
            datasets: [
                {
                    label: 'Money In',
                    data: monthlyReceived,
                    backgroundColor: 'rgba(46, 204, 113, 0.7)',
                    borderColor: 'rgba(46, 204, 113, 1)',
                    borderWidth: 1
                },
                {
                    label: 'Money Out',
                    data: monthlySpent,
                    backgroundColor: 'rgba(231, 76, 60, 0.7)',
                    borderColor: 'rgba(231, 76, 60, 1)',
                    borderWidth: 1
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: { beginAtZero: true, title: { display: true, text: 'Amount (GHC)' } },
                x: { title: { display: true, text: 'Month' } }
            },
            plugins: {
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            return context.dataset.label + ': GHC' + context.raw.toFixed(2);
                        }
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
    new Chart(pieCtx, {
        type: 'doughnut',
        data: {
            labels: pieLabels,
            datasets: [{
                data: pieData,
                backgroundColor: [
                    '#3498db', '#2ecc71', '#e74c3c', '#f39c12', '#9b59b6',
                    '#1abc9c', '#d35400', '#34495e', '#16a085', '#c0392b'
                ],
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { position: 'right' },
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
                },
                datalabels: {
                    formatter: (value, ctx) => {
                        const dataArr = ctx.chart.data.datasets[0].data;
                        const sum = dataArr.reduce((a, b) => a + b, 0);
                        const percentage = (value * 100 / sum).toFixed(1) + '%';
                        return percentage;
                    },
                    color: '#fff',
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
    new Chart(velocityCtx, {
        type: 'line',
        data: {
            labels: velocityLabels,
            datasets: [
                {
                    label: 'Transactions',
                    data: velocityCounts,
                    backgroundColor: 'rgba(52, 152, 219, 0.2)',
                    borderColor: 'rgba(52, 152, 219, 1)',
                    borderWidth: 2,
                    tension: 0.4,
                    yAxisID: 'y1'
                },
                {
                    label: 'Money Out',
                    data: velocitySpent,
                    backgroundColor: 'rgba(231, 76, 60, 0.2)',
                    borderColor: 'rgba(231, 76, 60, 1)',
                    borderWidth: 2,
                    tension: 0.4,
                    yAxisID: 'y'
                },
                {
                    label: 'Money In',
                    data: velocityReceived,
                    backgroundColor: 'rgba(46, 204, 113, 0.2)',
                    borderColor: 'rgba(46, 204, 113, 1)',
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
                    title: { display: true, text: 'Amount (GHC)' }
                },
                y1: {
                    type: 'linear',
                    display: true,
                    position: 'right',
                    title: { display: true, text: 'Transaction Count' },
                    grid: { drawOnChartArea: false }
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
                }
            }
        }
    });
</script>
</body>
</html>
