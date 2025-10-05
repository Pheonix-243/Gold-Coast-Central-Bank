<?php
require_once('../includes/auth.php');
$account = isset($_SESSION['client_account']) ? $_SESSION['client_account'] : null;
if (!$account) {
    header('Location: ../login.php');
    exit;
}

// Enhanced error handling with logging
function db_error($stmt, $query = '') {
    if (!$stmt) {
        error_log("Database error in analytics: " . mysqli_error($GLOBALS['con']) . " - Query: " . $query);
        return true;
    }
    return false;
}

// Get period parameter with validation
$period = isset($_GET['period']) ? $_GET['period'] : '30d';
$allowed_periods = ['7d', '30d', '90d', '1y'];
if (!in_array($period, $allowed_periods)) {
    $period = '30d';
}

// Calculate date conditions
$dateConditions = [
    '7d' => "AND dt >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)",
    '30d' => "AND dt >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)", 
    '90d' => "AND dt >= DATE_SUB(CURDATE(), INTERVAL 90 DAY)",
    '1y' => "AND dt >= DATE_SUB(CURDATE(), INTERVAL 1 YEAR)"
];
$dateCondition = $dateConditions[$period];

// Previous period for comparisons
$prevDateConditions = [
    '7d' => "AND dt >= DATE_SUB(CURDATE(), INTERVAL 14 DAY) AND dt < DATE_SUB(CURDATE(), INTERVAL 7 DAY)",
    '30d' => "AND dt >= DATE_SUB(CURDATE(), INTERVAL 60 DAY) AND dt < DATE_SUB(CURDATE(), INTERVAL 30 DAY)",
    '90d' => "AND dt >= DATE_SUB(CURDATE(), INTERVAL 180 DAY) AND dt < DATE_SUB(CURDATE(), INTERVAL 90 DAY)",
    '1y' => "AND dt >= DATE_SUB(CURDATE(), INTERVAL 2 YEAR) AND dt < DATE_SUB(CURDATE(), INTERVAL 1 YEAR)"
];
$prevDateCondition = $prevDateConditions[$period];

// 1. Enhanced Account Summary with Error Handling
$sql = "SELECT 
            COALESCE(a.balance, 0) as balance, 
            COALESCE(a.account_type, 'Current') as account_type, 
            COALESCE(h.name, 'User') as name,
            COALESCE(h.email, '') as email,
            COALESCE(a.registerdate, CURDATE()) as registerdate,
            (SELECT COUNT(*) FROM account_history WHERE account = ? $dateCondition) AS total_transactions,
            (SELECT IFNULL(SUM(amount), 0) FROM account_history WHERE account = ? AND sender = ? $dateCondition) AS total_spent,
            (SELECT IFNULL(SUM(amount), 0) FROM account_history WHERE account = ? AND receiver = ? AND sender != ? $dateCondition) AS total_received,
            (SELECT COUNT(DISTINCT DATE(dt)) FROM account_history WHERE account = ? $dateCondition) AS active_days,
            (SELECT MAX(dt) FROM account_history WHERE account = ? $dateCondition) AS last_transaction_date
        FROM 
            accounts_info a
        LEFT JOIN 
            accountsholder h ON a.account = h.account
        WHERE 
            a.account = ?";

$stmt = $con->prepare($sql);
if (db_error($stmt, $sql)) {
    $accountInfo = [
        'balance' => 0,
        'account_type' => 'Current',
        'name' => 'User',
        'email' => '',
        'registerdate' => date('Y-m-d'),
        'total_transactions' => 0,
        'total_spent' => 0,
        'total_received' => 0,
        'active_days' => 0,
        'last_transaction_date' => null
    ];
} else {
    $stmt->bind_param("sssssssss", $account, $account, $account, $account, $account, $account, $account, $account, $account);
    $stmt->execute();
    $accountInfo = $stmt->get_result()->fetch_assoc() ?? [
        'balance' => 0,
        'account_type' => 'Current',
        'name' => 'User',
        'email' => '',
        'registerdate' => date('Y-m-d'),
        'total_transactions' => 0,
        'total_spent' => 0,
        'total_received' => 0,
        'active_days' => 0,
        'last_transaction_date' => null
    ];
    $stmt->close();
}

// 2. Enhanced Category Spending with Proper Categorization
$sql = "SELECT 
            COALESCE(sc.name, 'Uncategorized') AS category, 
            COALESCE(sc.id, 0) AS category_id,
            COALESCE(sc.color, '#6B7280') AS color,
            COALESCE(sc.icon, 'fas fa-tag') AS icon,
            SUM(CASE WHEN h.sender = ? THEN h.amount ELSE 0 END) AS amount,
            COUNT(CASE WHEN h.sender = ? THEN 1 END) AS count,
            AVG(CASE WHEN h.sender = ? THEN ABS(h.amount) ELSE NULL END) AS avg_amount,
            MIN(CASE WHEN h.sender = ? THEN ABS(h.amount) ELSE NULL END) AS min_amount,
            MAX(CASE WHEN h.sender = ? THEN ABS(h.amount) ELSE NULL END) AS max_amount
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
$categorySpendingArr = [];
if (!$db_error($stmt, $sql)) {
    $stmt->bind_param("ssssss", $account, $account, $account, $account, $account, $account);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $categorySpendingArr[] = $row;
    }
    $stmt->close();
}

// 3. Enhanced Monthly Trends with Running Balance
$sql = "SELECT 
            DATE_FORMAT(STR_TO_DATE(dt, '%Y-%m-%d'), '%Y-%m') AS month,
            SUM(CASE WHEN sender = ? THEN amount ELSE 0 END) AS spent,
            SUM(CASE WHEN receiver = ? AND sender != ? THEN amount ELSE 0 END) AS received,
            COUNT(*) AS transactions,
            (SUM(CASE WHEN receiver = ? AND sender != ? THEN amount ELSE 0 END) + SUM(CASE WHEN sender = ? THEN amount ELSE 0 END)) AS net_flow
        FROM 
            account_history
        WHERE 
            account = ? AND dt >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
        GROUP BY 
            month
        ORDER BY 
            month ASC";

$stmt = $con->prepare($sql);
$monthlyTrendsData = [];
if (!$db_error($stmt, $sql)) {
    $stmt->bind_param("sssssss", $account, $account, $account, $account, $account, $account, $account);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $monthlyTrendsData[] = $row;
    }
    $stmt->close();
}

// 4. Enhanced Transaction Velocity with Daily Patterns
$sql = "SELECT 
            DATE(dt) AS date,
            DAYNAME(dt) AS day_name,
            COUNT(*) AS count,
            SUM(CASE WHEN sender = ? THEN amount ELSE 0 END) AS spent,
            SUM(CASE WHEN receiver = ? AND sender != ? THEN amount ELSE 0 END) AS received,
            AVG(ABS(amount)) AS avg_amount,
            STDDEV(ABS(amount)) AS std_amount
        FROM 
            account_history
        WHERE 
            account = ? $dateCondition
        GROUP BY 
            date, day_name
        ORDER BY 
            date ASC";

$stmt = $con->prepare($sql);
$velocityData = [];
if (!$db_error($stmt, $sql)) {
    $stmt->bind_param("ssss", $account, $account, $account, $account);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $velocityData[] = $row;
    }
    $stmt->close();
}

// 5. Enhanced Top Recipients with Relationship Analysis
$sql = "SELECT 
            receiver AS account,
            r_name AS recipient,
            COUNT(*) AS count,
            SUM(amount) AS total,
            MAX(dt) AS last_transaction,
            MIN(dt) AS first_transaction,
            AVG(ABS(amount)) AS avg_amount,
            STDDEV(ABS(amount)) AS std_amount,
            COUNT(DISTINCT DATE(dt)) AS unique_days,
            DATEDIFF(MAX(dt), MIN(dt)) AS relationship_days,
            (SUM(amount) / DATEDIFF(MAX(dt), MIN(dt))) AS daily_rate
        FROM 
            account_history
        WHERE 
            account = ? AND sender = ? AND r_name != 'System' AND r_name != 'self' AND r_name != 'null' $dateCondition
        GROUP BY 
            receiver, r_name
        HAVING 
            count >= 1
        ORDER BY 
            ABS(total) DESC
        LIMIT 15";

$stmt = $con->prepare($sql);
$topRecipientsArr = [];
if (!$db_error($stmt, $sql)) {
    $stmt->bind_param("ss", $account, $account);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $topRecipientsArr[] = $row;
    }
    $stmt->close();
}

// 6. Enhanced Category Trends with Momentum
$sql = "SELECT 
            COALESCE(sc.name, 'Uncategorized') AS category,
            COALESCE(sc.id, 0) AS category_id,
            SUM(CASE WHEN h.sender = ? THEN h.amount ELSE 0 END) AS current_amount,
            COUNT(CASE WHEN h.sender = ? THEN 1 END) AS current_count,
            (SELECT SUM(amount) FROM account_history h2 
             LEFT JOIN spending_categories sc2 ON h2.category_id = sc2.id 
             WHERE h2.account = ? AND h2.sender = ? AND COALESCE(sc2.id, 0) = COALESCE(sc.id, 0)
             $prevDateCondition) AS previous_amount,
            (SELECT COUNT(*) FROM account_history h2 
             LEFT JOIN spending_categories sc2 ON h2.category_id = sc2.id 
             WHERE h2.account = ? AND h2.sender = ? AND COALESCE(sc2.id, 0) = COALESCE(sc.id, 0)
             $prevDateCondition) AS previous_count
        FROM 
            account_history h
        LEFT JOIN 
            spending_categories sc ON h.category_id = sc.id
        WHERE 
            h.account = ? $dateCondition
        GROUP BY 
            sc.id, sc.name
        HAVING 
            current_amount < 0";

$stmt = $con->prepare($sql);
$categoryTrends = [];
if (!$db_error($stmt, $sql)) {
    $stmt->bind_param("sssssss", $account, $account, $account, $account, $account, $account, $account);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $categoryTrends[] = $row;
    }
    $stmt->close();
}

// 7. Enhanced Velocity Metrics with Percentiles
$sql = "SELECT 
            COUNT(*) AS total_tx,
            SUM(CASE WHEN sender = ? THEN 1 ELSE 0 END) AS outgoing_tx,
            SUM(CASE WHEN receiver = ? AND sender != ? THEN 1 ELSE 0 END) AS incoming_tx,
            AVG(CASE WHEN sender = ? THEN ABS(amount) ELSE NULL END) AS avg_outgoing,
            AVG(CASE WHEN receiver = ? AND sender != ? THEN amount ELSE NULL END) AS avg_incoming,
            MIN(ABS(amount)) AS min_amount,
            MAX(ABS(amount)) AS max_amount,
            STDDEV(ABS(amount)) AS std_amount
        FROM 
            account_history
        WHERE 
            account = ? $dateCondition";

$stmt = $con->prepare($sql);
$velocityMetrics = [
    'total_tx' => 0,
    'outgoing_tx' => 0,
    'incoming_tx' => 0,
    'avg_outgoing' => 0,
    'avg_incoming' => 0,
    'min_amount' => 0,
    'max_amount' => 0,
    'std_amount' => 0,
    'median_amount' => 0
];

if (!$db_error($stmt, $sql)) {
    $stmt->bind_param("sssssss", $account, $account, $account, $account, $account, $account, $account);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    if ($result) {
        $velocityMetrics = array_merge($velocityMetrics, $result);
    }
    $stmt->close();
}

// Calculate median manually
$sql = "SELECT ABS(amount) as abs_amount 
        FROM account_history 
        WHERE account = ? AND (sender = ? OR (receiver = ? AND sender != ?)) 
        $dateCondition 
        ORDER BY abs_amount";
$stmt = $con->prepare($sql);
if (!$db_error($stmt, $sql)) {
    $stmt->bind_param("ssss", $account, $account, $account, $account);
    $stmt->execute();
    $amounts = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

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
        $velocityMetrics['median_amount'] = $medianAmount;
    }
}

// 8. Enhanced Daily Patterns Analysis
$sql = "SELECT 
            DAYNAME(dt) AS day_name,
            DAYOFWEEK(dt) AS day_number,
            COUNT(*) AS total_transactions,
            SUM(CASE WHEN sender = ? THEN ABS(amount) ELSE 0 END) AS total_spent,
            AVG(CASE WHEN sender = ? THEN ABS(amount) ELSE NULL END) AS avg_spent,
            COUNT(CASE WHEN sender = ? THEN 1 END) AS outgoing_count
        FROM 
            account_history
        WHERE 
            account = ? $dateCondition
        GROUP BY 
            day_name, day_number
        ORDER BY 
            day_number";

$stmt = $con->prepare($sql);
$dailyPatterns = [];
if (!$db_error($stmt, $sql)) {
    $stmt->bind_param("ssss", $account, $account, $account, $account);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $dailyPatterns[] = $row;
    }
    $stmt->close();
}

// Calculate enhanced financial metrics
$currentPeriodSpend = abs($accountInfo['total_spent'] ?? 0);
$currentPeriodIncome = $accountInfo['total_received'] ?? 0;
$netCashFlow = $currentPeriodIncome - $currentPeriodSpend;

// Calculate previous period for comparison
$sql = "SELECT 
            SUM(CASE WHEN sender = ? THEN amount ELSE 0 END) AS prev_spent,
            SUM(CASE WHEN receiver = ? AND sender != ? THEN amount ELSE 0 END) AS prev_received
        FROM 
            account_history
        WHERE 
            account = ? $prevDateCondition";

$stmt = $con->prepare($sql);
$previousPeriodSpend = 0;
$previousPeriodIncome = 0;
if (!$db_error($stmt, $sql)) {
    $stmt->bind_param("ssss", $account, $account, $account, $account);
    $stmt->execute();
    $prevPeriodData = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    $previousPeriodSpend = abs($prevPeriodData['prev_spent'] ?? 0);
    $previousPeriodIncome = $prevPeriodData['prev_received'] ?? 0;
}

// Calculate percentage changes safely
$spendChange = $previousPeriodSpend > 0 ? (($currentPeriodSpend - $previousPeriodSpend) / $previousPeriodSpend) * 100 : ($currentPeriodSpend > 0 ? 100 : 0);
$incomeChange = $previousPeriodIncome > 0 ? (($currentPeriodIncome - $previousPeriodIncome) / $previousPeriodIncome) * 100 : ($currentPeriodIncome > 0 ? 100 : 0);

// Calculate savings rate
$savingsRate = $currentPeriodIncome > 0 ? ($netCashFlow / $currentPeriodIncome) * 100 : 0;

// Calculate transaction frequency
$daysInPeriod = [
    '7d' => 7,
    '30d' => 30, 
    '90d' => 90,
    '1y' => 365
][$period];

$txFrequency = $accountInfo['total_transactions'] > 0 ? $daysInPeriod / $accountInfo['total_transactions'] : 0;
$dailySpendRate = $currentPeriodSpend / max($accountInfo['active_days'], 1);

// Enhanced insights calculations
$topCategory = '';
$topCategoryAmount = 0;
$categoryDiversity = count($categorySpendingArr);
$avgCategorySpend = $categoryDiversity > 0 ? $currentPeriodSpend / $categoryDiversity : 0;

foreach ($categorySpendingArr as $row) {
    if (abs($row['amount']) > $topCategoryAmount) {
        $topCategoryAmount = abs($row['amount']);
        $topCategory = $row['category'];
    }
}

// Calculate financial health score (simplified)
$financialHealthScore = 0;
if ($currentPeriodIncome > 0) {
    $savingsScore = min(max($savingsRate / 20 * 100, 0), 100); // Max 20% savings rate = 100 points
    $spendingStabilityScore = $velocityMetrics['std_amount'] > 0 ? min(100, 100 / ($velocityMetrics['std_amount'] / $velocityMetrics['avg_outgoing'])) : 100;
    $diversityScore = min($categoryDiversity * 20, 100); // 5+ categories = 100 points
    
    $financialHealthScore = ($savingsScore + $spendingStabilityScore + $diversityScore) / 3;
}

// Prepare enhanced data for frontend
$enhancedData = [
    'financial_health_score' => round($financialHealthScore),
    'category_diversity' => $categoryDiversity,
    'avg_category_spend' => $avgCategorySpend,
    'daily_patterns' => $dailyPatterns,
    'has_sufficient_data' => $accountInfo['total_transactions'] > 5
];

?>