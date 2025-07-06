<?php
require_once('includes/conn.php');
require_once('includes/interest_functions.php');

// Only allow this to run from command line or cron
if (php_sapi_name() !== 'cli' && !isset($_SERVER['HTTP_X_CRON_TOKEN'])) {
    die('Access denied');
}

// Get calculation date (default to yesterday)
$calculationDate = date('Y-m-d', strtotime('-1 day'));
if (isset($argv[1])) {
    $calculationDate = date('Y-m-d', strtotime($argv[1]));
}

echo "Starting interest calculation for $calculationDate\n";

// Get all accounts eligible for interest
$accountsQuery = "SELECT a.account 
                 FROM accounts_info a
                 WHERE a.balance >= (SELECT config_value FROM interest_configuration WHERE config_name = 'minimum_balance_for_interest')
                 AND a.interest_rate > 0";
$accountsResult = $conn->query($accountsQuery);

$totalAccounts = 0;
$totalInterest = 0;
$successCount = 0;
$errorCount = 0;
$errors = [];

while ($accountRow = $accountsResult->fetch_assoc()) {
    $account = $accountRow['account'];
    $result = calculateDailyInterest($account, $calculationDate);
    
    if ($result['success']) {
        $successCount++;
        $totalInterest += $result['interest'];
        echo "Calculated interest for {$account}: {$result['interest']}\n";
    } else {
        $errorCount++;
        $errors[$account] = $result['message'];
        echo "Error for {$account}: {$result['message']}\n";
    }
    
    $totalAccounts++;
}

// Log the calculation run
$logQuery = "INSERT INTO system_logs 
            (log_type, description, details, created_at)
            VALUES ('interest_calculation', 'Daily interest calculation', ?, NOW())";
$stmt = $conn->prepare($logQuery);

$details = json_encode([
    'calculation_date' => $calculationDate,
    'total_accounts' => $totalAccounts,
    'success_count' => $successCount,
    'error_count' => $errorCount,
    'total_interest' => $totalInterest,
    'errors' => $errors
]);

$stmt->bind_param("s", $details);
$stmt->execute();

echo "\nCalculation complete:\n";
echo "Total accounts processed: $totalAccounts\n";
echo "Successful calculations: $successCount\n";
echo "Failed calculations: $errorCount\n";
echo "Total interest accrued: $totalInterest\n";

if (!empty($errors)) {
    echo "\nErrors:\n";
    foreach ($errors as $account => $message) {
        echo "$account: $message\n";
    }
}
?>