<?php
require_once('../includes/auth.php');
// require_once('../includes/db.php');
// require_once('../includes/security.php');

// Validate session and permissions
if (!isset($_SESSION['client_account'])) {
    header("Location: /login.php");
    exit();
}

$account = filter_var($_SESSION['client_account'], FILTER_SANITIZE_STRING);

// Set timezone for consistent date handling
date_default_timezone_set('Africa/Accra');

// Error handling
try {
    // 1. Get comprehensive account summary with prepared statements
    $sql = "SELECT 
                a.balance, 
                a.account_type, 
                h.name,
                h.email,
                a.registerdate,
                (SELECT COUNT(*) FROM account_history WHERE account = ?) AS total_transactions,
                (SELECT SUM(amount) FROM account_history WHERE account = ? AND sender = ?) AS total_spent,
                (SELECT SUM(amount) FROM account_history WHERE account = ? AND reciever = ? AND sender != ?) AS total_received,
                (SELECT COUNT(DISTINCT DATE(dt)) FROM account_history WHERE account = ?) AS active_days
            FROM 
                accounts_info a
            JOIN 
                accountsholder h ON a.account = h.account
            WHERE 
                a.account = ?";
    
    $stmt = $con->prepare($sql);
    $stmt->bind_param("ssssssss", $account, $account, $account, $account, $account, $account, $account, $account);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        throw new Exception("Account not found");
    }
    
    $accountInfo = $result->fetch_assoc();
    $stmt->close();

    // 2. Get spending by category with transaction type filtering
    $sql = "SELECT 
                t.id AS type_id,
                t.name AS category, 
                SUM(CASE WHEN h.sender = ? THEN h.amount ELSE 0 END) AS amount,
                COUNT(CASE WHEN h.sender = ? THEN 1 END) AS count
            FROM 
                account_history h
            JOIN 
                transaction_types t ON h.type = t.id
            WHERE 
                h.account = ? AND t.id NOT IN (1, 2) /* Exclude internal transfers */
            GROUP BY 
                t.id, t.name
            HAVING 
                amount < 0 /* Only show outgoing */
            ORDER BY 
                amount ASC"; /* Show largest expenses first */
    
    $stmt = $con->prepare($sql);
    $stmt->bind_param("sss", $account, $account, $account);
    $stmt->execute();
    $categorySpending = $stmt->get_result();
    $stmt->close();

    // 3. Get monthly trends with proper date handling
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
    $stmt->bind_param("ssss", $account, $account, $account, $account);
    $stmt->execute();
    $monthlyTrends = $stmt->get_result();
    $monthlyTrendsData = [];
    
    while ($row = $monthlyTrends->fetch_assoc()) {
        $monthlyTrendsData[] = $row;
    }
    $stmt->close();

    // 4. Get transaction velocity (recent activity)
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
    $stmt->bind_param("ssss", $account, $account, $account, $account);
    $stmt->execute();
    $transactionVelocity = $stmt->get_result();
    $velocityData = [];
    
    while ($row = $transactionVelocity->fetch_assoc()) {
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
    $stmt->bind_param("ss", $account, $account);
    $stmt->execute();
    $topRecipients = $stmt->get_result();
    $stmt->close();

    // 6. Get unusual activity (transactions > 2 standard deviations from mean)
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
    $stmt->bind_param("ssssssss", $account, $account, $account, $account, $account, $account, $account, $account);
    $stmt->execute();
    $unusualActivity = $stmt->get_result();
    $stmt->close();

    // 7. Get balance history for the last 30 days
    $sql = "SELECT 
                DATE(dt) AS date,
                balance_after AS balance
            FROM 
                account_history
            WHERE 
                account = ? AND dt >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
            GROUP BY 
                DATE(dt)
            ORDER BY 
                date DESC";
    
    $stmt = $con->prepare($sql);
    $stmt->bind_param("s", $account);
    $stmt->execute();
    $balanceHistory = $stmt->get_result();
    $stmt->close();

} catch (Exception $e) {
    error_log("Analytics error: " . $e->getMessage());
    // Handle error appropriately
    die("An error occurred while generating analytics. Please try again later.");
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Advanced Banking Analytics | <?= htmlspecialchars($accountInfo['name']) ?></title>
    
    <!-- Security Headers -->
    <meta http-equiv="Content-Security-Policy" content="default-src 'self'; script-src 'self' https://cdn.jsdelivr.net; style-src 'self' https://cdn.jsdelivr.net 'unsafe-inline'; img-src 'self' data:;">
    <meta http-equiv="X-Content-Type-Options" content="nosniff">
    <meta http-equiv="X-Frame-Options" content="DENY">
    
    <!-- Favicon -->
    <link rel="icon" href="/assets/favicon.ico" type="image/x-icon">
    
    <!-- Font Awesome for icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Chart.js for visualizations -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-datalabels@2.0.0"></script>
    <script src="https://cdn.jsdelivr.net/npm/moment"></script>
    <script src="https://cdn.jsdelivr.net/npm/chartjs-adapter-moment"></script>
    
    <!-- Custom CSS -->
    <link rel="stylesheet" href="analytic.css">
</head>
<body class="analytics-container">
    <!-- Header -->
    <header class="analytics-header">
        <div class="container">
            <h1>Financial Analytics Dashboard</h1>
            <div class="account-info">
                <span class="account-name"><?= htmlspecialchars($accountInfo['name']) ?></span>
                <span class="account-number"><?= htmlspecialchars($account) ?></span>
                <span class="account-type"><?= htmlspecialchars($accountInfo['account_type']) ?></span>
            </div>
        </div>
    </header>

    <main class="container">
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
                        if (count($monthlyTrendsData) > 1) {
                            $currentBalance = $accountInfo['balance'];
                            $previousBalance = $currentBalance - $monthlyTrendsData[0]['received'] + $monthlyTrendsData[0]['spent'];
                            $balanceChange = $previousBalance != 0 ? (($currentBalance - $previousBalance) / abs($previousBalance)) * 100 : 0;
                            
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
                        if (count($monthlyTrendsData) > 1) {
                            $currentSpend = abs($monthlyTrendsData[0]['spent']);
                            $previousSpend = abs($monthlyTrendsData[1]['spent']);
                            $spendChange = $previousSpend != 0 ? (($currentSpend - $previousSpend) / $previousSpend) * 100 : 0;
                            
                            if ($spendChange > 0) {
                                echo '<span class="trend-up">▲</span> ' . number_format(abs($spendChange), 1) . '% from last month';
                            } elseif ($spendChange < 0) {
                                echo '<span class="trend-down">▼</span> ' . number_format(abs($spendChange), 1) . '% from last month';
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
                        <h2 class="analytics-card-title">Total Inflows</h2>
                        <i class="fas fa-arrow-down text-success"></i>
                    </div>
                    <div class="metric-large">GHC<?= number_format($accountInfo['total_received'], 2) ?></div>
                    <div class="metric-label">Customer since <?= date('M Y', strtotime($accountInfo['registerdate'])) ?></div>
                    <div class="metric-change">
                        <?php
                        // Calculate income change from last month
                        if (count($monthlyTrendsData) > 1) {
                            $currentIncome = $monthlyTrendsData[0]['received'];
                            $previousIncome = $monthlyTrendsData[1]['received'];
                            $incomeChange = $previousIncome != 0 ? (($currentIncome - $previousIncome) / $previousIncome) * 100 : 0;
                            
                            if ($incomeChange > 0) {
                                echo '<span class="trend-up">▲</span> ' . number_format(abs($incomeChange), 1) . '% from last month';
                            } elseif ($incomeChange < 0) {
                                echo '<span class="trend-down">▼</span> ' . number_format(abs($incomeChange), 1) . '% from last month';
                            } else {
                                echo 'No change from last month';
                            }
                        }
                        ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Charts Row -->
        <div class="analytics-grid mb-4">
            <div class="col-span-8">
                <div class="analytics-card">
                    <div class="analytics-card-header">
                        <h2 class="analytics-card-title">Monthly Cash Flow</h2>
                        <div class="chart-actions">
                            <button class="btn-period active" data-period="12">12M</button>
                            <button class="btn-period" data-period="6">6M</button>
                            <button class="btn-period" data-period="3">3M</button>
                        </div>
                    </div>
                    <div class="chart-container">
                        <canvas id="monthlyTrendsChart"></canvas>
                    </div>
                </div>
            </div>
            
            <div class="col-span-4">
                <div class="analytics-card">
                    <div class="analytics-card-header">
                        <h2 class="analytics-card-title">Spending by Category</h2>
                        <i class="fas fa-tags"></i>
                    </div>
                    <div class="chart-container">
                        <canvas id="spendingPieChart"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <!-- Insights and Activity Row -->
        <div class="analytics-grid mb-4">
            <div class="col-span-6">
                <div class="analytics-card">
                    <div class="analytics-card-header">
                        <h2 class="analytics-card-title">Transaction Activity</h2>
                        <span class="badge">Last 30 Days</span>
                    </div>
                    <div class="chart-container">
                        <canvas id="velocityChart"></canvas>
                    </div>
                </div>
            </div>
            
            <div class="col-span-6">
                <div class="analytics-card">
                    <div class="analytics-card-header">
                        <h2 class="analytics-card-title">Financial Insights</h2>
                        <i class="fas fa-lightbulb"></i>
                    </div>
                    
                    <?php
                    // Calculate average monthly spend
                    $monthsActive = max(1, round((time() - strtotime($accountInfo['registerdate'])) / (30 * 24 * 60 * 60)));
                    $avgMonthlySpend = $accountInfo['total_spent'] ? abs($accountInfo['total_spent']) / $monthsActive : 0;
                    
                    // Calculate current month spend
                    $currentMonthSpend = 0;
                    $currentMonth = date('Y-m');
                    foreach ($monthlyTrendsData as $row) {
                        if ($currentMonth === $row['month']) {
                            $currentMonthSpend = abs($row['spent']);
                            break;
                        }
                    }
                    
                    // Calculate spend vs average
                    $spendVsAverage = $avgMonthlySpend ? ($currentMonthSpend - $avgMonthlySpend) / $avgMonthlySpend * 100 : 0;
                    
                    // Get most active category
                    $topCategory = '';
                    $topCategoryAmount = 0;
                    $categorySpending->data_seek(0);
                    while ($row = $categorySpending->fetch_assoc()) {
                        if (abs($row['amount']) > $topCategoryAmount) {
                            $topCategoryAmount = abs($row['amount']);
                            $topCategory = $row['category'];
                        }
                    }
                    
                    // Calculate savings rate
                    $savingsRate = $accountInfo['total_received'] > 0 ? 
                        (($accountInfo['balance'] - $accountInfo['total_spent'] - $accountInfo['total_received']) / $accountInfo['total_received'] * 100) : 0;
                    ?>
                    
                    <div class="insight-card">
                        <div class="insight-title">Monthly Spending</div>
                        <p class="insight-value">You've spent GHC<?= number_format($currentMonthSpend, 2) ?> this month.</p>
                        <p class="insight-value">
                            <?php if ($spendVsAverage > 0): ?>
                                <span class="trend-up">▲</span> 
                                <?= number_format(abs($spendVsAverage), 1) ?>% above your average of GHC<?= number_format($avgMonthlySpend, 2) ?>
                            <?php else: ?>
                                <span class="trend-down">▼</span> 
                                <?= number_format(abs($spendVsAverage), 1) ?>% below your average of GHC<?= number_format($avgMonthlySpend, 2) ?>
                            <?php endif; ?>
                        </p>
                    </div>
                    
                    <div class="insight-card">
                        <div class="insight-title">Top Spending Category</div>
                        <p class="insight-value">Your highest spending is on <strong><?= htmlspecialchars($topCategory) ?></strong> with GHC<?= number_format($topCategoryAmount, 2) ?> total.</p>
                    </div>
                    
                    <?php if ($savingsRate > 0): ?>
                    <div class="insight-card">
                        <div class="insight-title">Savings Rate</div>
                        <p class="insight-value">You're saving <strong><?= number_format($savingsRate, 1) ?>%</strong> of your incoming funds.</p>
                    </div>
                    <?php endif; ?>
                    
                    <?php if ($unusualActivity->num_rows > 0): ?>
                    <div class="insight-card" style="border-left-color: #e74c3c;">
                        <div class="insight-title">Unusual Activity Detected</div>
                        <p class="insight-value">We've flagged <?= $unusualActivity->num_rows ?> transactions that are significantly larger than your typical activity.</p>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Detailed Breakdowns -->
        <div class="analytics-grid">
            <div class="col-span-6">
                <div class="analytics-card">
                    <div class="analytics-card-header">
                        <h2 class="analytics-card-title">Category Breakdown</h2>
                        <i class="fas fa-chart-pie"></i>
                    </div>
                    
                    <div class="progress-container">
                        <?php
                        $categorySpending->data_seek(0);
                        while ($row = $categorySpending->fetch_assoc()):
                            if (abs($row['amount']) > 0):
                                $percentage = $accountInfo['total_spent'] ? (abs($row['amount']) / abs($accountInfo['total_spent']) * 100) : 0;
                        ?>
                        <div class="mb-3">
                            <div class="progress-label">
                                <span><?= htmlspecialchars($row['category']) ?></span>
                                <span>GHC<?= number_format(abs($row['amount']), 2) ?></span>
                            </div>
                            <div class="progress-bar-bg">
                                <div class="progress-bar-fill" style="width: <?= $percentage ?>%"></div>
                            </div>
                            <small class="text-muted"><?= $row['count'] ?> transactions (<?= number_format($percentage, 1) ?>%)</small>
                        </div>
                        <?php 
                            endif;
                        endwhile; 
                        ?>
                    </div>
                </div>
            </div>
            
            <div class="col-span-6">
                <div class="analytics-card">
                    <div class="analytics-card-header">
                        <h2 class="analytics-card-title">Top Recipients</h2>
                        <i class="fas fa-users"></i>
                    </div>
                    
                    <?php if ($topRecipients->num_rows > 0): ?>
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
                            <?php while ($row = $topRecipients->fetch_assoc()): ?>
                            <tr>
                                <td><?= htmlspecialchars($row['recipient']) ?></td>
                                <td><?= $row['count'] ?></td>
                                <td>GHC<?= number_format(abs($row['total']), 2) ?></td>
                                <td><?= date('M j', strtotime($row['last_transaction'])) ?></td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                    <?php else: ?>
                    <div class="text-center text-muted mt-4">
                        <p>No recipient data available</p>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- Balance History and Unusual Activity -->
        <div class="analytics-grid mt-4">
            <div class="col-span-6">
                <div class="analytics-card">
                    <div class="analytics-card-header">
                        <h2 class="analytics-card-title">Balance History</h2>
                        <span class="badge">Last 30 Days</span>
                    </div>
                    <div class="chart-container">
                        <canvas id="balanceHistoryChart"></canvas>
                    </div>
                </div>
            </div>
            
            <div class="col-span-6">
                <div class="analytics-card">
                    <div class="analytics-card-header">
                        <h2 class="analytics-card-title">Unusual Activity</h2>
                        <i class="fas fa-exclamation-triangle text-danger"></i>
                    </div>
                    
                    <?php if ($unusualActivity->num_rows > 0): ?>
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
                            <?php while ($row = $unusualActivity->fetch_assoc()): ?>
                            <tr>
                                <td><?= date('M j', strtotime($row['dt'])) ?></td>
                                <td class="text-danger">GHC<?= number_format(abs($row['amount']), 2) ?></td>
                                <td><?= htmlspecialchars($row['recipient']) ?></td>
                                <td><?= htmlspecialchars($row['description']) ?? 'N/A' ?></td>
                            </tr>
                            <?php endwhile; ?>
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

    <!-- Footer -->
    <footer class="analytics-footer">
        <div class="container">
            <p>Analytics generated on <?= date('M j, Y \a\t H:i') ?></p>
            <p class="text-muted">Data is refreshed every 15 minutes. Last refresh: Just now</p>
        </div>
    </footer>

    <!-- Charting Scripts -->
    <script>
        // Monthly Trends Chart
        const monthlyCtx = document.getElementById('monthlyTrendsChart').getContext('2d');
        const monthlyChart = new Chart(monthlyCtx, {
            type: 'bar',
            data: {
                labels: [
                    <?php 
                    foreach ($monthlyTrendsData as $row) {
                        echo "'".date('M Y', strtotime($row['month'].'-01'))."',";
                    }
                    ?>
                ],
                datasets: [
                    {
                        label: 'Money In',
                        data: [
                            <?php foreach ($monthlyTrendsData as $row) {
                                echo $row['received'].",";
                            } ?>
                        ],
                        backgroundColor: 'rgba(46, 204, 113, 0.7)',
                        borderColor: 'rgba(46, 204, 113, 1)',
                        borderWidth: 1
                    },
                    {
                        label: 'Money Out',
                        data: [
                            <?php foreach ($monthlyTrendsData as $row) {
                                echo abs($row['spent']).",";
                            } ?>
                        ],
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
                    y: {
                        beginAtZero: true,
                        title: {
                            display: true,
                            text: 'Amount (GHC)'
                        },
                        grid: {
                            color: 'rgba(0, 0, 0, 0.05)'
                        }
                    },
                    x: {
                        grid: {
                            display: false
                        },
                        title: {
                            display: true,
                            text: 'Month'
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
                        position: 'top',
                    }
                }
            }
        });

        // Spending Pie Chart
        const pieCtx = document.getElementById('spendingPieChart').getContext('2d');
        const pieChart = new Chart(pieCtx, {
            type: 'doughnut',
            data: {
                labels: [
                    <?php 
                    $categorySpending->data_seek(0);
                    while ($row = $categorySpending->fetch_assoc()) {
                        if (abs($row['amount']) > 0) {
                            echo "'".htmlspecialchars($row['category'], ENT_QUOTES)."',";
                        }
                    }
                    ?>
                ],
                datasets: [{
                    data: [
                        <?php 
                        $categorySpending->data_seek(0);
                        while ($row = $categorySpending->fetch_assoc()) {
                            if (abs($row['amount']) > 0) {
                                echo abs($row['amount']).",";
                            }
                        }
                        ?>
                    ],
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
                    legend: {
                        position: 'right',
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
                    },
                    datalabels: {
                        formatter: (value, ctx) => {
                            const dataArr = ctx.chart.data.datasets[0].data;
                            const sum = dataArr.reduce((a, b) => a + b, 0);
                            const percentage = (value * 100 / sum).toFixed(1) + '%';
                            return percentage;
                        },
                        color: '#fff',
                        font: {
                            weight: 'bold'
                        }
                    }
                },
                cutout: '70%'
            },
            plugins: [ChartDataLabels]
        });

        // Transaction Velocity Chart
        const velocityCtx = document.getElementById('velocityChart').getContext('2d');
        const velocityChart = new Chart(velocityCtx, {
            type: 'line',
            data: {
                labels: [
                    <?php 
                    foreach ($velocityData as $row) {
                        echo "'".date('M j', strtotime($row['date']))."',";
                    }
                    ?>
                ],
                datasets: [
                    {
                        label: 'Transactions',
                        data: [
                            <?php foreach ($velocityData as $row) {
                                echo $row['count'].",";
                            } ?>
                        ],
                        backgroundColor: 'rgba(52, 152, 219, 0.2)',
                        borderColor: 'rgba(52, 152, 219, 1)',
                        borderWidth: 2,
                        tension: 0.4,
                        yAxisID: 'y1',
                        pointRadius: 4,
                        pointHoverRadius: 6
                    },
                    {
                        label: 'Money Out',
                        data: [
                            <?php foreach ($velocityData as $row) {
                                echo abs($row['spent']).",";
                            } ?>
                        ],
                        backgroundColor: 'rgba(231, 76, 60, 0.2)',
                        borderColor: 'rgba(231, 76, 60, 1)',
                        borderWidth: 2,
                        tension: 0.4,
                        yAxisID: 'y',
                        pointRadius: 4,
                        pointHoverRadius: 6
                    },
                    {
                        label: 'Money In',
                        data: [
                            <?php foreach ($velocityData as $row) {
                                echo $row['received'].",";
                            } ?>
                        ],
                        backgroundColor: 'rgba(46, 204, 113, 0.2)',
                        borderColor: 'rgba(46, 204, 113, 1)',
                        borderWidth: 2,
                        tension: 0.4,
                        yAxisID: 'y',
                        pointRadius: 4,
                        pointHoverRadius: 6
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
                            text: 'Amount (GHC)'
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
                            text: 'Transaction Count'
                        },
                        grid: {
                            drawOnChartArea: false
                        }
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
                                if (label) {
                                    label += ': ';
                                }
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
                        position: 'top',
                    }
                }
            }
        });

        // Balance History Chart
        const balanceCtx = document.getElementById('balanceHistoryChart').getContext('2d');
        const balanceChart = new Chart(balanceCtx, {
            type: 'line',
            data: {
                labels: [
                    <?php 
                    $balanceData = [];
                    while ($row = $balanceHistory->fetch_assoc()) {
                        $balanceData[] = $row;
                        echo "'".date('M j', strtotime($row['date']))."',";
                    }
                    ?>
                ],
                datasets: [{
                    label: 'Account Balance',
                    data: [
                        <?php foreach ($balanceData as $row) {
                            echo $row['balance'].",";
                        } ?>
                    ],
                    backgroundColor: 'rgba(52, 152, 219, 0.2)',
                    borderColor: 'rgba(52, 152, 219, 1)',
                    borderWidth: 2,
                    tension: 0.1,
                    fill: true,
                    pointRadius: 4,
                    pointHoverRadius: 6
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: false,
                        title: {
                            display: true,
                            text: 'Balance (GHC)'
                        },
                        grid: {
                            color: 'rgba(0, 0, 0, 0.05)'
                        }
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
                                return 'Balance: GHC' + context.raw.toFixed(2);
                            }
                        }
                    },
                    legend: {
                        display: false
                    }
                }
            }
        });

        // Period filter buttons
        document.querySelectorAll('.btn-period').forEach(button => {
            button.addEventListener('click', function() {
                document.querySelectorAll('.btn-period').forEach(btn => btn.classList.remove('active'));
                this.classList.add('active');
                
                // In a real implementation, we would fetch new data for the selected period
                // For this example, we'll just log the selection
                console.log('Selected period:', this.dataset.period + ' months');
            });
        });
    </script>
</body>
</html>

