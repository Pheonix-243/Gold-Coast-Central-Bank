<?php
require_once('../includes/auth.php');
require_once(__DIR__ . '/../../vendor/autoload.php');

// Get parameters
$dateFrom = $_GET['date_from'] ?? date('Y-m-d', strtotime('-30 days'));
$dateTo = $_GET['date_to'] ?? date('Y-m-d');
$account = $_SESSION['client_account'];

// Get account information
$sql = "SELECT a.*, h.name 
        FROM accounts_info a
        JOIN accountsholder h ON a.account = h.account
        WHERE a.account = ?";
$stmt = mysqli_prepare($con, $sql);
mysqli_stmt_bind_param($stmt, "s", $account);
mysqli_stmt_execute($stmt);
$accountInfo = mysqli_fetch_assoc($stmt->get_result());

// Get transactions
$sql = "SELECT h.*, t.name as type_name 
        FROM account_history h
        JOIN transaction_types t ON h.type = t.id
        WHERE h.account = ?
        AND h.dt BETWEEN ? AND ?
        ORDER BY h.dt DESC, h.tm DESC";
$stmt = mysqli_prepare($con, $sql);
mysqli_stmt_bind_param($stmt, "sss", $account, $dateFrom, $dateTo);
mysqli_stmt_execute($stmt);
$transactions = mysqli_stmt_get_result($stmt);

// Calculate totals
$totalDeposits = 0;
$totalWithdrawals = 0;
$runningBalance = $_SESSION['client_balance']; // Simplified - should calculate properly

// Create PDF
$mpdf = new \Mpdf\Mpdf([
    'mode' => 'utf-8',
    'format' => 'A4',
    'orientation' => 'P'
]);

$html = '
<!DOCTYPE html>
<html>
<head>
    <style>
        body { font-family: Arial, sans-serif; }
        .header { text-align: center; margin-bottom: 30px; }
        .header h2 { color: #2c3e50; margin-bottom: 5px; }
        .account-info { margin-bottom: 20px; }
        .summary { margin-bottom: 30px; }
        .table { width: 100%; border-collapse: collapse; }
        .table th { background-color: #f8f9fa; text-align: left; padding: 8px; border-bottom: 2px solid #dee2e6; }
        .table td { padding: 8px; border-bottom: 1px solid #dee2e6; }
        .text-right { text-align: right; }
        .text-danger { color: #e74c3c; }
        .text-success { color: #27ae60; }
        .footer { margin-top: 50px; text-align: center; font-size: 12px; color: #777; }
    </style>
</head>
<body>
    <div class="header">
        <h2>Gold Coast Central Bank</h2>
        <p>Account Statement</p>
    </div>
    
    <div class="account-info">
        <p><strong>Account Holder:</strong> '.htmlspecialchars($accountInfo['name']).'</p>
        <p><strong>Account Number:</strong> '.htmlspecialchars($accountInfo['account']).'</p>
        <p><strong>Statement Period:</strong> '.date('M j, Y', strtotime($dateFrom)).' to '.date('M j, Y', strtotime($dateTo)).'</p>
    </div>
    
    <div class="summary">
        <table class="table">
            <tr>
                <th>Opening Balance</th>
                <th>Total Deposits</th>
                <th>Total Withdrawals</th>
                <th>Closing Balance</th>
            </tr>
            <tr>
                <td>GHC'.number_format($runningBalance, 2).'</td>
                <td>GHC'.number_format($totalDeposits, 2).'</td>
                <td>GHC'.number_format($totalWithdrawals, 2).'</td>
                <td>GHC'.number_format($runningBalance + $totalDeposits - $totalWithdrawals, 2).'</td>
            </tr>
        </table>
    </div>
    
    <table class="table">
        <thead>
            <tr>
                <th>Date</th>
                <th>Type</th>
                <th>Description</th>
                <th>Reference</th>
                <th class="text-right">Amount</th>
                <th class="text-right">Balance</th>
            </tr>
        </thead>
        <tbody>';

while ($row = mysqli_fetch_assoc($transactions)) {
    $isOutgoing = $row['sender'] == $account;
    $amountClass = $isOutgoing ? 'text-danger' : 'text-success';
    $amountSign = $isOutgoing ? '-' : '+';
    $runningBalance += ($isOutgoing ? -abs($row['amount']) : $row['amount']);
    
    $html .= '
            <tr>
                <td>'.htmlspecialchars($row['dt']).'</td>
                <td>'.htmlspecialchars($row['type_name']).'</td>
                <td>'.htmlspecialchars($row['description']).'</td>
                <td>'.htmlspecialchars($row['reference_number']).'</td>
                <td class="text-right '.$amountClass.'">'.$amountSign.' GHC'.number_format(abs($row['amount']), 2).'</td>
                <td class="text-right">GHC'.number_format($runningBalance, 2).'</td>
            </tr>';
}

$html .= '
        </tbody>
    </table>
    
    <div class="footer">
        <p>Generated on '.date('M j, Y H:i:s').'</p>
        <p>Thank you for banking with us</p>
    </div>
</body>
</html>';

$mpdf->WriteHTML($html);
$mpdf->Output('statement_'.$account.'_'.$dateFrom.'_'.$dateTo.'.pdf', 'D'); // Force download
?>