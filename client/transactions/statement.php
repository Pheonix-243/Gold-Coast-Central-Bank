<?php
require_once('../includes/auth.php');
require_once('../includes/header.php');

// Set default date range (last 30 days)
$dateFrom = isset($_GET['date_from']) ? $_GET['date_from'] : date('Y-m-d', strtotime('-30 days'));
$dateTo = isset($_GET['date_to']) ? $_GET['date_to'] : date('Y-m-d');

// Get account information
$account = $_SESSION['client_account'];
$sql = "SELECT a.*, h.name 
        FROM accounts_info a
        JOIN accountsholder h ON a.account = h.account
        WHERE a.account = ?";
$stmt = mysqli_prepare($con, $sql);
mysqli_stmt_bind_param($stmt, "s", $account);
mysqli_stmt_execute($stmt);
$accountInfo = mysqli_fetch_assoc($stmt->get_result());

// Get transactions for the period
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
$openingBalance = $_SESSION['client_balance']; // This would need proper calculation
$closingBalance = $openingBalance;

while ($row = mysqli_fetch_assoc($transactions)) {
    if ($row['amount'] > 0) {
        $totalDeposits += $row['amount'];
    } else {
        $totalWithdrawals += abs($row['amount']);
    }
}
mysqli_data_seek($transactions, 0); // Reset pointer for display
?>

<main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
    <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
        <h1 class="h2">Account Statement</h1>
        <div class="btn-toolbar mb-2 mb-md-0">
            <div class="btn-group me-2">
                <button onclick="window.print()" class="btn btn-sm btn-outline-secondary">
                    <i class="fas fa-print"></i> Print
                </button>
                <a href="download_statement.php?<?= http_build_query($_GET) ?>" class="btn btn-sm btn-outline-secondary">
                    <i class="fas fa-download"></i> Download PDF
                </a>
            </div>
        </div>
    </div>

    <!-- Date Range Selector -->
    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" class="row g-3">
                <div class="col-md-4">
                    <label class="form-label">From Date</label>
                    <input type="date" name="date_from" class="form-control" value="<?= htmlspecialchars($dateFrom) ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label">To Date</label>
                    <input type="date" name="date_to" class="form-control" value="<?= htmlspecialchars($dateTo) ?>">
                </div>
                <div class="col-md-4 align-self-end">
                    <button type="submit" class="btn btn-primary">Generate Statement</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Account Summary -->
    <div class="card mb-4">
        <div class="card-body">
            <div class="row">
                <div class="col-md-6">
                    <h5 class="card-title">Account Information</h5>
                    <p><strong>Account Holder:</strong> <?= htmlspecialchars($accountInfo['name']) ?></p>
                    <p><strong>Account Number:</strong> <?= htmlspecialchars($accountInfo['account']) ?></p>
                    <p><strong>Account Type:</strong> <?= htmlspecialchars($accountInfo['account_type']) ?></p>
                </div>
                <div class="col-md-6">
                    <h5 class="card-title">Statement Summary</h5>
                    <p><strong>Statement Period:</strong> <?= date('M j, Y', strtotime($dateFrom)) ?> to <?= date('M j, Y', strtotime($dateTo)) ?></p>
                    <p><strong>Total Deposits:</strong> GHC<?= number_format($totalDeposits, 2) ?></p>
                    <p><strong>Total Withdrawals:</strong> GHC<?= number_format($totalWithdrawals, 2) ?></p>
                </div>
            </div>
        </div>
    </div>

    <!-- Transactions Table -->
    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Type</th>
                            <th>Description</th>
                            <th>Reference</th>
                            <th>Amount</th>
                            <th>Balance</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $runningBalance = $openingBalance;
                        while ($row = mysqli_fetch_assoc($transactions)): 
                            $isOutgoing = $row['sender'] == $account;
                            $amountClass = $isOutgoing ? 'text-danger' : 'text-success';
                            $amountSign = $isOutgoing ? '-' : '+';
                            $runningBalance += ($isOutgoing ? -abs($row['amount']) : $row['amount']);
                        ?>
                            <tr>
                                <td><?= htmlspecialchars($row['dt']) ?></td>
                                <td><?= htmlspecialchars($row['type_name']) ?></td>
                                <td><?= htmlspecialchars($row['description']) ?></td>
                                <td><?= htmlspecialchars($row['reference_number']) ?></td>
                                <td class="<?= $amountClass ?> fw-bold">
                                    <?= $amountSign ?> GHC<?= number_format(abs($row['amount']), 2) ?>
                                </td>
                                <td>GHC<?= number_format($runningBalance, 2) ?></td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</main>

<style>
@media print {
    body * {
        visibility: hidden;
    }
    .card, .card * {
        visibility: visible;
    }
    .card {
        position: absolute;
        left: 0;
        top: 0;
        width: 100%;
        border: none;
    }
    .no-print {
        display: none !important;
    }
}
</style>

<?php require_once('../includes/footer.php'); ?>