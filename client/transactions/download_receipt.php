<?php
require_once('../includes/auth.php');
require_once(__DIR__ . '/../../vendor/autoload.php');

// Get transaction ID from URL
$transactionId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Fetch transaction details (same query as receipt.php)
$sql = "SELECT h.*, t.name as type_name, 
               sender.name as sender_fullname, 
               receiver.name as receiver_fullname
        FROM account_history h
        JOIN transaction_types t ON h.type = t.id
        LEFT JOIN accountsholder sender ON h.sender = sender.account
        LEFT JOIN accountsholder receiver ON h.reciever = receiver.account
        WHERE h.no = ? AND (h.account = ? OR h.sender = ? OR h.reciever = ?)";
$stmt = mysqli_prepare($con, $sql);
mysqli_stmt_bind_param($stmt, "isss", $transactionId, 
    $_SESSION['client_account'], 
    $_SESSION['client_account'], 
    $_SESSION['client_account']);
mysqli_stmt_execute($stmt);
$transaction = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

if (!$transaction) {
    $_SESSION['error'] = "Transaction not found or unauthorized access";
    header('Location: history.php');
    exit;
}

// Format dates
$transactionDate = date('F j, Y', strtotime($transaction['dt']));
$transactionTime = date('h:i A', strtotime($transaction['tm']));

// Create PDF
$mpdf = new \Mpdf\Mpdf([
    'mode' => 'utf-8',
    'format' => 'A5',
    'orientation' => 'P'
]);

$html = '
<!DOCTYPE html>
<html>
<head>
    <style>
        body { font-family: Arial, sans-serif; }
        .header { text-align: center; margin-bottom: 20px; }
        .header h2 { color: #2c3e50; margin-bottom: 5px; }
        .title { font-size: 18px; text-align: center; margin-bottom: 20px; color: #2c3e50; }
        .details { margin-bottom: 30px; }
        .row { display: flex; justify-content: space-between; margin-bottom: 8px; }
        .label { font-weight: bold; color: #555; }
        .amount { 
            font-size: 20px; 
            font-weight: bold; 
            text-align: center; 
            margin: 25px 0;
            color: ' . ($transaction['amount'] > 0 ? '#27ae60' : '#e74c3c') . ';
        }
        .footer { 
            margin-top: 40px; 
            text-align: center; 
            font-size: 12px; 
            color: #777; 
        }

              .logo {
    width: 100%;
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 20px 20px;
    justify-content: center;
    align-items: center;
    flex-direction: column;
}
    .logo img {
    width: 160px;
    height: 80px;
}
    </style>
</head>
<body>
    <div class="header">
       <img src="../../gccb_logos/logo-transparent.svg" alt="">
        <p>Transaction Receipt</p>
    </div>
    
    <div class="title">Transaction Confirmation</div>
    
    <div class="details">
        <div class="row">
            <span class="label">Reference Number:</span>
            <span>' . htmlspecialchars($transaction['reference_number']) . '</span>
        </div>
        <div class="row">
            <span class="label">Date & Time:</span>
            <span>' . $transactionDate . ' at ' . $transactionTime . '</span>
        </div>
        <div class="row">
            <span class="label">Transaction Type:</span>
            <span>' . htmlspecialchars($transaction['type_name']) . '</span>
        </div>';

if ($transaction['sender'] == $_SESSION['client_account']) {
    $html .= '
        <div class="row">
            <span class="label">Sent To:</span>
            <span>' . htmlspecialchars($transaction['receiver_fullname']) . '<br>' . 
              htmlspecialchars($transaction['reciever']) . '</span>
        </div>';
} else {
    $html .= '
        <div class="row">
            <span class="label">Received From:</span>
            <span>' . htmlspecialchars($transaction['sender_fullname']) . '<br>' . 
              htmlspecialchars($transaction['sender']) . '</span>
        </div>';
}

$html .= '
        <div class="row">
            <span class="label">Description:</span>
            <span>' . htmlspecialchars($transaction['description']) . '</span>
        </div>
    </div>
    
    <div class="amount">
        ' . ($transaction['amount'] > 0 ? '+' : '') . 'GHC' . number_format(abs($transaction['amount']), 2) . '
    </div>
    
    <div class="row">
        <span class="label">Status:</span>
        <span style="color: ' . ($transaction['status'] == 'completed' ? '#27ae60' : '#f39c12') . '">
            ' . ucfirst($transaction['status']) . '
        </span>
    </div>
    
    <div class="footer">
        <p>Thank you for banking with us</p>
        <p>For any inquiries, please contact customer support</p>
    </div>
</body>
</html>';

$mpdf->WriteHTML($html);
$mpdf->Output('receipt_'.$transaction['reference_number'].'.pdf', 'D'); // Force download
?>