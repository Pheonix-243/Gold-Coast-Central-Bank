<?php
require_once('../includes/auth.php');
require_once('../includes/header.php');

// Get transaction ID from URL
$transactionId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Fetch transaction details
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

// Format dates nicely
$transactionDate = date('F j, Y', strtotime($transaction['dt']));
$transactionTime = date('h:i A', strtotime($transaction['tm']));
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Transaction Receipt</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f8f9fa;
            color: #333;
        }
        .receipt-container {
            max-width: 600px;
            margin: 2rem auto;
            background: white;
            box-shadow: 0 0 20px rgba(0,0,0,0.1);
            border-radius: 10px;
            overflow: hidden;
        }
        .receipt-header {
            background: #2c3e50;
            color: white;
            padding: 1.5rem;
            text-align: center;
        }
        .receipt-body {
            padding: 2rem;
        }
        .receipt-title {
            font-size: 1.8rem;
            margin-bottom: 1rem;
            color: #2c3e50;
            text-align: center;
        }
        .receipt-details {
            margin-bottom: 2rem;
        }
        .detail-row {
            display: flex;
            justify-content: space-between;
            padding: 0.8rem 0;
            border-bottom: 1px solid #eee;
        }
        .detail-label {
            font-weight: 600;
            color: #555;
        }
        .detail-value {
            text-align: right;
        }
        .amount-highlight {
            font-size: 1.5rem;
            font-weight: 700;
            color: <?= $transaction['amount'] > 0 ? '#27ae60' : '#e74c3c' ?>;
            text-align: center;
            margin: 1.5rem 0;
        }
        .receipt-footer {
            text-align: center;
            padding: 1rem;
            background: #f8f9fa;
            font-size: 0.9rem;
            color: #777;
        }
        .action-buttons {
            display: flex;
            justify-content: center;
            gap: 1rem;
            margin-top: 2rem;
        }
        .btn {
            padding: 0.6rem 1.2rem;
            border-radius: 5px;
            text-decoration: none;
            font-weight: 500;
            transition: all 0.3s;
        }
        .btn-print {
            background: #3498db;
            color: white;
            border: 1px solid #2980b9;
        }
        .btn-download {
            background: #2ecc71;
            color: white;
            border: 1px solid #27ae60;
        }
        .btn-back {
            background: #95a5a6;
            color: white;
            border: 1px solid #7f8c8d;
        }
        .btn:hover {
            opacity: 0.9;
            transform: translateY(-2px);
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
        @media print {
            .action-buttons {
                display: none;
            }
            body {
                background: white;
            }
            .receipt-container {
                box-shadow: none;
                margin: 0;
                border-radius: 0;
            }
        }
    </style>
</head>
<body>
    <div class="receipt-container">
        <div class="receipt-header logo">
            <!-- <h2>Gold Coast Central Bank</h2> -->
             <img src="../../gccb_logos/logo-transparent.svg" alt="">
            <!-- <p>Transaction Receipt</p> -->
        </div>
        
        <div class="receipt-body">
            <h3 class="receipt-title">Transaction Receipt</h3>
            
            <div class="receipt-details">
                <div class="detail-row">
                    <span class="detail-label">Reference Number:</span>
                    <span class="detail-value"><?= htmlspecialchars($transaction['reference_number']) ?></span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Date & Time:</span>
                    <span class="detail-value"><?= $transactionDate ?> at <?= $transactionTime ?></span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Transaction Type:</span>
                    <span class="detail-value"><?= htmlspecialchars($transaction['type_name']) ?></span>
                </div>
                
                <?php if ($transaction['sender'] == $_SESSION['client_account']): ?>
                <div class="detail-row">
                    <span class="detail-label">Sent To:</span>
                    <span class="detail-value">
                        <?= htmlspecialchars($transaction['receiver_fullname']) ?><br>
                        <?= htmlspecialchars($transaction['reciever']) ?>
                    </span>
                </div>
                <?php else: ?>
                <div class="detail-row">
                    <span class="detail-label">Received From:</span>
                    <span class="detail-value">
                        <?= htmlspecialchars($transaction['sender_fullname']) ?><br>
                        <?= htmlspecialchars($transaction['sender']) ?>
                    </span>
                </div>
                <?php endif; ?>
                
                <div class="detail-row">
                    <span class="detail-label">Description:</span>
                    <span class="detail-value"><?= htmlspecialchars($transaction['description']) ?></span>
                </div>
            </div>
            
            <div class="amount-highlight">
                <?= $transaction['amount'] > 0 ? '+' : '' ?>
                GHC<?= number_format(abs($transaction['amount']), 2) ?>
            </div>
            
            <div class="detail-row">
                <span class="detail-label">Status:</span>
                <span class="detail-value">
                    <span style="color: <?= $transaction['status'] == 'completed' ? '#27ae60' : '#f39c12' ?>">
                        <?= ucfirst($transaction['status']) ?>
                    </span>
                </span>
            </div>
        </div>
        
        <div class="receipt-footer">
            <p>Thank you for banking with us</p>
            <p>For any inquiries, please contact customer support</p>
        </div>
    </div>
    
    <div class="action-buttons">
        <button onclick="window.print()" class="btn btn-print">Print Receipt</button>
        <a href="download_receipt.php?id=<?= $transactionId ?>" class="btn btn-download">Download PDF</a>
        <a href="history.php" class="btn btn-back">Back to History</a>
    </div>
</body>
</html>

<?php require_once('../includes/footer.php'); ?>