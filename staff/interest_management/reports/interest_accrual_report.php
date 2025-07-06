<?php
require_once('../includes/conn.php');
require_once('../includes/auth.php');

// // Check permissions
// if (!hasPermission('Admin') && !hasPermission('teller')) {
//     die('Access denied');
// }

// Handle date filters
$startDate = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-01');
$endDate = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');
$accountFilter = isset($_GET['account']) ? $_GET['account'] : '';
$accountTypeFilter = isset($_GET['account_type']) ? $_GET['account_type'] : '';

// Build query
$query = "SELECT it.*, a.account_title, a.account_type, a.interest_calculation_method
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

// Get account types for filter
$typesQuery = "SELECT DISTINCT account_type FROM accounts_info";
$typesResult = $conn->query($typesQuery);
$accountTypes = [];
while ($row = $typesResult->fetch_assoc()) {
    $accountTypes[] = $row['account_type'];
}

// Calculate totals
$totalQuery = "SELECT SUM(it.amount) as total_interest, COUNT(*) as transaction_count
               FROM interest_transactions it
               JOIN accounts_info a ON it.account = a.account
               WHERE it.transaction_date BETWEEN ? AND ?";
$totalParams = [$startDate, $endDate];
$totalTypes = "ss";

if (!empty($accountFilter)) {
    $totalQuery .= " AND it.account LIKE ?";
    $totalParams[] = "%$accountFilter%";
    $totalTypes .= "s";
}

if (!empty($accountTypeFilter)) {
    $totalQuery .= " AND a.account_type = ?";
    $totalParams[] = $accountTypeFilter;
    $totalTypes .= "s";
}

$totalStmt = $conn->prepare($totalQuery);
$totalStmt->bind_param($totalTypes, ...$totalParams);
$totalStmt->execute();
$totalResult = $totalStmt->get_result();
$totals = $totalResult->fetch_assoc();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Interest Accrual Report</title>
    <link rel="stylesheet" href="../../css/bootstrap.min.css">
</head>
<body>
    <?php include('../../includes/navbar.php'); ?>
    
    <div class="container mt-4">
        <h2>Interest Accrual Report</h2>
        
        <div class="card mb-4">
            <div class="card-header">
                <h4>Filters</h4>
            </div>
            <div class="card-body">
                <form method="get" class="form-inline">
                    <div class="form-group mr-3">
                        <label for="start_date" class="mr-2">From:</label>
                        <input type="date" class="form-control" id="start_date" name="start_date" 
                               value="<?php echo $startDate; ?>">
                    </div>
                    
                    <div class="form-group mr-3">
                        <label for="end_date" class="mr-2">To:</label>
                        <input type="date" class="form-control" id="end_date" name="end_date" 
                               value="<?php echo $endDate; ?>">
                    </div>
                    
                    <div class="form-group mr-3">
                        <label for="account" class="mr-2">Account:</label>
                        <input type="text" class="form-control" id="account" name="account" 
                               value="<?php echo htmlspecialchars($accountFilter); ?>" placeholder="Account number">
                    </div>
                    
                    <div class="form-group mr-3">
                        <label for="account_type" class="mr-2">Account Type:</label>
                        <select class="form-control" id="account_type" name="account_type">
                            <option value="">All Types</option>
                            <?php foreach ($accountTypes as $type): ?>
                                <option value="<?php echo htmlspecialchars($type); ?>" 
                                    <?php echo $accountTypeFilter == $type ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($type); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <button type="submit" class="btn btn-primary">Apply Filters</button>
                    <a href="interest_accrual_report.php" class="btn btn-secondary ml-2">Reset</a>
                </form>
            </div>
        </div>
        
        <div class="card">
            <div class="card-header">
                <div class="d-flex justify-content-between">
                    <h4>Report Results</h4>
                    <div>
                        <strong>Total Interest:</strong> <?php echo number_format($totals['total_interest'], 2); ?> |
                        <strong>Transactions:</strong> <?php echo $totals['transaction_count']; ?>
                    </div>
                </div>
            </div>
            <div class="card-body">
                <?php if ($result->num_rows > 0): ?>
                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Account</th>
                                    <th>Account Title</th>
                                    <th>Type</th>
                                    <th>Method</th>
                                    <th>Rate</th>
                                    <th>Interest</th>
                                    <th>Balance Used</th>
                                    <th>Days</th>
                                    <th>Period</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($row = $result->fetch_assoc()): ?>
                                    <tr>
                                        <td><?php echo $row['transaction_date']; ?></td>
                                        <td><?php echo $row['account']; ?></td>
                                        <td><?php echo htmlspecialchars($row['account_title']); ?></td>
                                        <td><?php echo $row['account_type']; ?></td>
                                        <td><?php echo ucfirst($row['interest_calculation_method']); ?></td>
                                        <td><?php echo ($row['rate'] * 100); ?>%</td>
                                        <td><?php echo number_format($row['amount'], 2); ?></td>
                                        <td><?php echo number_format($row['balance_used'], 2); ?></td>
                                        <td><?php echo $row['days_applied']; ?></td>
                                        <td>
                                            <?php echo $row['calculation_start_date']; ?> to 
                                            <?php echo $row['calculation_end_date']; ?>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <div class="mt-3">
                        <a href="export_report.php?type=accrual&start_date=<?php echo $startDate; ?>&end_date=<?php echo $endDate; ?>&account=<?php echo urlencode($accountFilter); ?>&account_type=<?php echo urlencode($accountTypeFilter); ?>" 
                           class="btn btn-success">
                            Export to CSV
                        </a>
                    </div>
                <?php else: ?>
                    <p>No interest accruals found for the selected period.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <script src="../../js/jquery-3.5.1.min.js"></script>
    <script src="../../js/bootstrap.bundle.min.js"></script>
</body>
</html>