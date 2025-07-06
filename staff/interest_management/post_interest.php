<?php
require_once('../includes/conn.php');
require_once('includes/interest_functions.php');

// Only allow this to run from command line or cron
if (php_sapi_name() !== 'cli' && !isset($_SERVER['HTTP_X_CRON_TOKEN'])) {
    die('Access denied');
}

// Get posting date (default to today)
$postingDate = date('Y-m-d');
if (isset($argv[1])) {
    $postingDate = date('Y-m-d', strtotime($argv[1]));
}

echo "Starting interest posting for $postingDate\n";

// Check if it's the correct day for posting
$postingDay = getConfigValue('posting_day');
if (date('j') != $postingDay && !isset($argv[2])) {
    die("Today is not the configured posting day (day $postingDay of month)\n");
}

// Get all accounts with accrued interest
$accountsQuery = "SELECT a.account, a.accrued_interest 
                 FROM accounts_info a
                 WHERE a.accrued_interest > 0";
$accountsResult = $conn->query($accountsQuery);

$totalAccounts = 0;
$totalPosted = 0;
$successCount = 0;
$errorCount = 0;
$errors = [];

while ($accountRow = $accountsResult->fetch_assoc()) {
    $account = $accountRow['account'];
    $result = postAccruedInterest($account, $postingDate);
    
    if ($result['success']) {
        $successCount++;
        $totalPosted += $result['amount'];
        echo "Posted interest for {$account}: {$result['amount']}\n";
    } else {
        $errorCount++;
        $errors[$account] = $result['message'];
        echo "Error for {$account}: {$result['message']}\n";
    }
    
    $totalAccounts++;
}

// Log the posting run
$logQuery = "INSERT INTO system_logs 
            (log_type, description, details, created_at)
            VALUES ('interest_posting', 'Monthly interest posting', ?, NOW())";
$stmt = $conn->prepare($logQuery);

$details = json_encode([
    'posting_date' => $postingDate,
    'total_accounts' => $totalAccounts,
    'success_count' => $successCount,
    'error_count' => $errorCount,
    'total_posted' => $totalPosted,
    'errors' => $errors
]);

$stmt->bind_param("s", $details);
$stmt->execute();

echo "\nPosting complete:\n";
echo "Total accounts processed: $totalAccounts\n";
echo "Successful postings: $successCount\n";
echo "Failed postings: $errorCount\n";
echo "Total interest posted: $totalPosted\n";

if (!empty($errors)) {
    echo "\nErrors:\n";
    foreach ($errors as $account => $message) {
        echo "$account: $message\n";
    }
}
?>