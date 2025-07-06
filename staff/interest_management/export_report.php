<?php
require_once('includes/conn.php');
require_once('../../includes/auth.php');

// Check permissions
if (!hasPermission('admin') && !hasPermission('teller')) {
    die('Access denied');
}

$type = $_GET['type'] ?? '';
$startDate = $_GET['start_date'] ?? '';
$endDate = $_GET['end_date'] ?? '';
$accountFilter = $_GET['account'] ?? '';
$accountTypeFilter = $_GET['account_type'] ?? '';

if (!in_array($type, ['accrual', 'posting'])) {
    die('Invalid report type');
}

// Set headers for CSV download
header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="interest_' . $type . '_report_' . date('Ymd') . '.csv"');

$output = fopen('php://output', 'w');

if ($type == 'accrual') {
    // Build query for accrual report
    $query = "SELECT it.transaction_date, it.account, a.account_title, a.account_type, 
                     a.interest_calculation_method, it.rate, it.amount, it.balance_used, 
                     it.days_applied, it.calculation_start_date, it.calculation_end_date
              FROM interest_transactions it
              JOIN accounts_info a ON it.account = a.account
              WHERE it.transaction_date BETWEEN ? AND ?";
    $params = [$startDate, $endDate];
    $types = "ss";

    if (!empty($accountFilter)) {
        $query .= " AND it.account LIKE ?";
        $params[] = "%$accountFilter%";
        $types .= "s";
    }

    if (!empty($accountTypeFilter)) {
        $query .= " AND a.account_type = ?";
        $params[] = $accountTypeFilter;
        $types .= "s";
    }

    $query .= " ORDER BY it.transaction_date DESC, it.account";

    $stmt = $conn->prepare($query);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();

    // Write CSV headers
    fputcsv($output, [
        'Date', 'Account', 'Account Title', 'Account Type', 'Calculation Method', 
        'Rate (%)', 'Interest', 'Balance Used', 'Days', 'Start Date', 'End Date'
    ]);

    // Write data rows
    while ($row = $result->fetch_assoc()) {
        fputcsv($output, [
            $row['transaction_date'],
            $row['account'],
            $row['account_title'],
            $row['account_type'],
            ucfirst($row['interest_calculation_method']),
            $row['rate'] * 100,
            $row['amount'],
            $row['balance_used'],
            $row['days_applied'],
            $row['calculation_start_date'],
            $row['calculation_end_date']
        ]);
    }
} else {
    // Build query for posting report
    $query = "SELECT ah.dt, ah.tm, ah.account, a.account_title, a.account_type, 
                     ah.amount, ah.balance_after, ah.reference_number
              FROM account_history ah
              JOIN accounts_info a ON ah.account = a.account
              WHERE ah.type = 8
              AND ah.dt BETWEEN ? AND ?";
    $params = [$startDate, $endDate];
    $types = "ss";

    if (!empty($accountFilter)) {
        $query .= " AND ah.account LIKE ?";
        $params[] = "%$accountFilter%";
        $types .= "s";
    }

    if (!empty($accountTypeFilter)) {
        $query .= " AND a.account_type = ?";
        $params[] = $accountTypeFilter;
        $types .= "s";
    }

    $query .= " ORDER BY ah.dt DESC, ah.account";

    $stmt = $conn->prepare($query);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();

    // Write CSV headers
    fputcsv($output, [
        'Date', 'Time', 'Account', 'Account Title', 'Account Type', 
        'Amount', 'New Balance', 'Reference Number'
    ]);

    // Write data rows
    while ($row = $result->fetch_assoc()) {
        fputcsv($output, [
            $row['dt'],
            $row['tm'],
            $row['account'],
            $row['account_title'],
            $row['account_type'],
            $row['amount'],
            $row['balance_after'],
            $row['reference_number']
        ]);
    }
}

fclose($output);
exit();
?>