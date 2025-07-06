<?php
require_once('../includes/auth.php');
// require_once('../includes/header.php');

// Pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$perPage = 10;
$offset = ($page - 1) * $perPage;

// Filter parameters
$typeFilter = isset($_GET['type']) ? (int)$_GET['type'] : null;
$dateFrom = isset($_GET['date_from']) ? $_GET['date_from'] : null;
$dateTo = isset($_GET['date_to']) ? $_GET['date_to'] : null;
$minAmount = isset($_GET['min_amount']) ? (float)$_GET['min_amount'] : null;
$maxAmount = isset($_GET['max_amount']) ? (float)$_GET['max_amount'] : null;
$direction = isset($_GET['direction']) ? $_GET['direction'] : null;
$search = isset($_GET['search']) ? trim($_GET['search']) : null;

// Base query - Only show transactions where the current account is the owner
$sql = "SELECT SQL_CALC_FOUND_ROWS h.*, t.name as type_name, t.description as type_description 
        FROM account_history h
        JOIN transaction_types t ON h.type = t.id
        WHERE h.account = ?";

$params = [$_SESSION['client_account']];
$paramTypes = "s";

// Apply filters
if ($typeFilter) {
    $sql .= " AND h.type = ?";
    $params[] = $typeFilter;
    $paramTypes .= "i";
}

if ($dateFrom) {
    $sql .= " AND h.dt >= ?";
    $params[] = $dateFrom;
    $paramTypes .= "s";
}

if ($dateTo) {
    $sql .= " AND h.dt <= ?";
    $params[] = $dateTo;
    $paramTypes .= "s";
}

if ($minAmount) {
    $sql .= " AND h.amount >= ?";
    $params[] = $minAmount;
    $paramTypes .= "d";
}

if ($maxAmount) {
    $sql .= " AND h.amount <= ?";
    $params[] = $maxAmount;
    $paramTypes .= "d";
}

if ($direction === 'in') {
    $sql .= " AND h.reciever = ? AND h.account = h.reciever";
    $params[] = $_SESSION['client_account'];
    $paramTypes .= "s";
} elseif ($direction === 'out') {
    $sql .= " AND h.sender = ? AND h.account = h.sender";
    $params[] = $_SESSION['client_account'];
    $paramTypes .= "s";
}

if ($search) {
    $sql .= " AND (h.description LIKE ? OR h.reference_number LIKE ? OR h.s_name LIKE ? OR h.r_name LIKE ?)";
    $searchTerm = "%$search%";
    $params = array_merge($params, [$searchTerm, $searchTerm, $searchTerm, $searchTerm]);
    $paramTypes .= "ssss";
}

// Complete query
$sql .= " ORDER BY h.dt DESC, h.tm DESC LIMIT ? OFFSET ?";
$params[] = $perPage;
$params[] = $offset;
$paramTypes .= "ii";

// Execute query
$stmt = mysqli_prepare($con, $sql);
mysqli_stmt_bind_param($stmt, $paramTypes, ...$params);
mysqli_stmt_execute($stmt);
$transactions = mysqli_stmt_get_result($stmt);

// Get total count
$totalRows = mysqli_fetch_assoc(mysqli_query($con, "SELECT FOUND_ROWS() as total"))['total'];
$totalPages = ceil($totalRows / $perPage);

// Get transaction types for filter dropdown
$types = mysqli_query($con, "SELECT * FROM transaction_types ORDER BY name");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gold Coast Central Bank - Transaction History</title>
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <!-- Custom CSS -->
    <link rel="stylesheet" href="../dashboard/style.css">
    <link rel="stylesheet" href="history.css">
</head>
<body class="light">
    <div class="container">
        <nav class="sidebar" id="sidebar">
            <button id="btn_close">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="16" height="16">
                    <path fill="none" d="M0 0h24v24H0z" />
                    <path d="M12 10.586l4.95-4.95 1.414 1.414-4.95 4.95 4.95 4.95-1.414 1.414-4.95-4.95-4.95 4.95-1.414-1.414 4.95-4.95-4.95-4.95L7.05 5.636z" />
                </svg>
            </button>

            <div class="logo">
                <img src="../../gccb_logos/logo-transparent.svg" alt="">
            </div>

            <div class="nav_links">
                <a href="../dashboard/" class="nav_link" aria-label="overview">
                    <div class="nav_link_icon">
                        <i class="fas fa-home"></i>
                    </div>
                    <div class="nav_link_text">Dashboard</div>
                </a>

                <a href="../transactions/deposit.php" class="nav_link" aria-label="deposit">
                    <div class="nav_link_icon">
                        <i class="fas fa-money-bill-wave"></i>
                    </div>
                    <div class="nav_link_text">Deposit</div>
                </a>

                <a href="../transactions/withdrawal.php" class="nav_link" aria-label="withdraw">
                    <div class="nav_link_icon">
                        <i class="fas fa-hand-holding-usd"></i>
                    </div>
                    <div class="nav_link_text">Withdraw</div>
                </a>

                <a href="../transactions/transfer.php" class="nav_link" aria-label="transfer">
                    <div class="nav_link_icon">
                        <i class="fas fa-exchange-alt"></i>
                    </div>
                    <div class="nav_link_text">Transfer</div>
                </a>

                <a href="../transactions/history.php" class="nav_link active" aria-label="history">
                    <div class="nav_link_icon">
                        <i class="fas fa-history"></i>
                    </div>
                    <div class="nav_link_text">History</div>
                </a>

                <a href="../profile/view.php" class="nav_link" aria-label="profile">
                    <div class="nav_link_icon">
                        <i class="fas fa-user"></i>
                    </div>
                    <div class="nav_link_text">Profile</div>
                </a>

                <a href="../settings/password.php" class="nav_link" aria-label="settings">
                    <div class="nav_link_icon">
                        <i class="fas fa-lock"></i>
                    </div>
                    <div class="nav_link_text">Security</div>
                </a>
            </div>

            <div class="profile">
                <div class="img_with_name">
                    <img src="../images/profile_pic3.webp" alt="">
                    <div class="profile_text">
                        <p class="name"><?= htmlspecialchars($_SESSION['client_name']) ?></p>
                        <p class="occupation"><?= htmlspecialchars($_SESSION['client_account_type'] ?? 'Account') ?></p>
                    </div>
                </div>
                <a href="../scripts/logout.php" aria-label="logout">
                    <i class="fas fa-sign-out-alt"></i>
                </a>
            </div>
        </nav>

        <section class="main_content">
            <div class="topbar">
                <button id="menu_btn">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="16" height="16">
                        <path fill="none" d="M0 0h24v24H0z" />
                        <path d="M3 4h18v2H3V4zm0 7h12v2H3v-2zm0 7h18v2H3v-2z" /></svg>
                </button>
                <div class="overview_text">
                    <p class="title">Transaction History</p>
                    <p class="desc">View your account activity</p>
                </div>

                <div class="topbar_icons">
                    <a href="#" aria-label="search" class="topbar_icon">
                        <i class="fas fa-search"></i>
                    </a>
                    <a href="#" aria-label="notifications" class="topbar_icon alert">
                        <i class="fas fa-bell"></i>
                    </a>
                </div>
            </div>

            <div class="history-container">
                <div class="history-header">
                    <h1></h1>
                    <div class="header-actions">
                        <form method="GET" class="search-form">
                            <div class="search-input">
                                <i class="fas fa-search"></i>
                                <input type="text" name="search" placeholder="Search transactions..." value="<?= htmlspecialchars($search) ?>">
                                <button type="submit" class="btn-search">Search</button>
                            </div>
                        </form>
                        <button class="btn-export">
                            <i class="fas fa-download"></i> Export
                        </button>
                    </div>
                </div>

                <!-- Summary Cards -->
                <div class="summary-cards">
                    <div class="summary-card">
                        <div class="summary-icon">
                            <i class="fas fa-arrow-down"></i>
                        </div>
                        <div class="summary-content">
                            <h3>Total Incoming</h3>
                            <p>GHC<?php
                                $sql = "SELECT SUM(amount) as total FROM account_history 
                                        WHERE reciever = ? AND account = reciever";
                                $stmt = mysqli_prepare($con, $sql);
                                mysqli_stmt_bind_param($stmt, "s", $_SESSION['client_account']);
                                mysqli_stmt_execute($stmt);
                                $result = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
                                echo number_format($result['total'] ?? 0, 2);
                            ?></p>
                        </div>
                    </div>
                    
                    <div class="summary-card">
                        <div class="summary-icon">
                            <i class="fas fa-arrow-up"></i>
                        </div>
                        <div class="summary-content">
                            <h3>Total Outgoing</h3>
                            <p>GHC<?php
                                $sql = "SELECT SUM(amount) as total FROM account_history 
                                        WHERE sender = ? AND account = sender";
                                $stmt = mysqli_prepare($con, $sql);
                                mysqli_stmt_bind_param($stmt, "s", $_SESSION['client_account']);
                                mysqli_stmt_execute($stmt);
                                $result = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
                                echo number_format(abs($result['total'] ?? 0), 2);
                            ?></p>
                        </div>
                    </div>
                    
                    <div class="summary-card">
                        <div class="summary-icon">
                            <i class="fas fa-exchange-alt"></i>
                        </div>
                        <div class="summary-content">
                            <h3>Total Transactions</h3>
                            <p><?= number_format($totalRows) ?></p>
                        </div>
                    </div>
                </div>

                <!-- Filters -->
                <div class="filters-card">
                    <h3>Filter Transactions</h3>
                    <form method="GET" class="filter-form">
                        <div class="filter-row">
                            <div class="filter-group">
                                <label>Transaction Type</label>
                                <select name="type">
                                    <option value="">All Types</option>
                                    <?php while ($type = mysqli_fetch_assoc($types)): ?>
                                        <option value="<?= $type['id'] ?>" <?= $typeFilter == $type['id'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($type['name']) ?>
                                        </option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                            
                            <div class="filter-group">
                                <label>Direction</label>
                                <select name="direction">
                                    <option value="">All</option>
                                    <option value="in" <?= $direction === 'in' ? 'selected' : '' ?>>Incoming</option>
                                    <option value="out" <?= $direction === 'out' ? 'selected' : '' ?>>Outgoing</option>
                                </select>
                            </div>
                            
                            <div class="filter-group">
                                <label>Date From</label>
                                <input type="date" name="date_from" value="<?= htmlspecialchars($dateFrom) ?>">
                            </div>
                            
                            <div class="filter-group">
                                <label>Date To</label>
                                <input type="date" name="date_to" value="<?= htmlspecialchars($dateTo) ?>">
                            </div>
                        </div>
                        
                        <div class="filter-row">
                            <div class="filter-group">
                                <label>Min Amount (GHC)</label>
                                <input type="number" name="min_amount" step="0.01" value="<?= htmlspecialchars($minAmount) ?>">
                            </div>
                            
                            <div class="filter-group">
                                <label>Max Amount (GHC)</label>
                                <input type="number" name="max_amount" step="0.01" value="<?= htmlspecialchars($maxAmount) ?>">
                            </div>
                            
                            <div class="filter-actions">
                                <button type="submit" class="btn-filter">
                                    <i class="fas fa-filter"></i> Apply Filters
                                </button>
                                <a href="history.php" class="btn-reset">
                                    <i class="fas fa-redo"></i> Reset
                                </a>
                            </div>
                        </div>
                    </form>
                </div>

                <!-- Transactions Table -->
                <div class="transactions-card">
                    <div class="table-responsive">
                        <table class="transactions-table">
                            <thead>
                                <tr>
                                    <th>Date & Time</th>
                                    <th>Type</th>
                                    <th>Counterparty</th>
                                    <th>Description</th>
                                    <th>Amount</th>
                                    <th>Reference</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (mysqli_num_rows($transactions) > 0): ?>
                                    <?php while ($row = mysqli_fetch_assoc($transactions)): ?>
                                        <?php
                                        $isOutgoing = $row['sender'] == $_SESSION['client_account'];
                                        $isIncoming = $row['reciever'] == $_SESSION['client_account'];
                                        $typeName = $row['type_name'];
                                        $peerName = $isOutgoing ? $row['r_name'] : $row['s_name'];
                                        $directionText = $isOutgoing ? 'To' : 'From';
                                        $amountClass = $isOutgoing ? 'outgoing' : 'incoming';
                                        $amountSign = $isOutgoing ? '-' : '+';
                                        
                                        // Get transaction type color
                                        $typeColors = [
                                            'Transfer' => $isOutgoing ? 'type-outgoing' : 'type-incoming',
                                            'Payment Received' => 'type-incoming',
                                            'Deposit' => 'type-incoming',
                                            'Withdrawal' => 'type-withdrawal',
                                            'Bill Payment' => 'type-bill',
                                            'Online Payment' => 'type-online',
                                            'Interest' => 'type-interest',
                                            'Fee' => 'type-fee'
                                        ];
                                        $typeClass = $typeColors[$typeName] ?? 'type-default';
                                        ?>
                                        <tr>
                                            <td>
                                                <div class="transaction-date">
                                                    <?= date('M j, Y', strtotime($row['dt'])) ?>
                                                </div>
                                                <div class="transaction-time">
                                                    <?= date('h:i A', strtotime($row['tm'])) ?>
                                                </div>
                                            </td>
                                            <td>
                                                <span class="transaction-type <?= $typeClass ?>">
                                                    <?= htmlspecialchars($typeName) ?>
                                                </span>
                                            </td>
                                            <td>
                                                <?php if ($peerName): ?>
                                                    <div class="counterparty">
                                                        <span class="direction"><?= $directionText ?></span>
                                                        <?= htmlspecialchars($peerName) ?>
                                                    </div>
                                                <?php else: ?>
                                                    <span class="text-muted">N/A</span>
                                                <?php endif; ?>
                                            </td>
                                            <td><?= htmlspecialchars($row['description']) ?></td>
                                            <td class="amount <?= $amountClass ?>">
                                                <?= $amountSign ?>GHC<?= number_format($row['amount'], 2) ?>
                                            </td>
                                            <td>
                                                <a href="receipt.php?id=<?= $row['no'] ?>" class="reference-link">
                                                    <?= htmlspecialchars($row['reference_number']) ?>
                                                </a>
                                            </td>
                                            <td>
                                                <div class="transaction-actions">
                                                    <a href="receipt.php?id=<?= $row['no'] ?>" class="action-btn" title="View Receipt">
                                                        <i class="fas fa-receipt"></i>
                                                    </a>
                                                    <button class="action-btn" title="Report Issue">
                                                        <i class="fas fa-flag"></i>
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="7" class="no-transactions">
                                            <i class="fas fa-info-circle"></i>
                                            No transactions found matching your criteria
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Pagination -->
                    <?php if ($totalPages > 1): ?>
                        <div class="pagination">
                            <?php if ($page > 1): ?>
                                <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page - 1])) ?>" class="page-link">
                                    <i class="fas fa-chevron-left"></i> Previous
                                </a>
                            <?php endif; ?>
                            
                            <div class="page-numbers">
                                <?php
                                $startPage = max(1, $page - 2);
                                $endPage = min($totalPages, $page + 2);
                                
                                if ($startPage > 1) {
                                    echo '<a href="?'.http_build_query(array_merge($_GET, ['page' => 1])).'" class="page-link">1</a>';
                                    if ($startPage > 2) echo '<span class="page-dots">...</span>';
                                }
                                
                                for ($i = $startPage; $i <= $endPage; $i++): ?>
                                    <a href="?<?= http_build_query(array_merge($_GET, ['page' => $i])) ?>" class="page-link <?= $i == $page ? 'active' : '' ?>">
                                        <?= $i ?>
                                    </a>
                                <?php endfor;
                                
                                if ($endPage < $totalPages) {
                                    if ($endPage < $totalPages - 1) echo '<span class="page-dots">...</span>';
                                    echo '<a href="?'.http_build_query(array_merge($_GET, ['page' => $totalPages])).'" class="page-link">'.$totalPages.'</a>';
                                }
                                ?>
                            </div>
                            
                            <?php if ($page < $totalPages): ?>
                                <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page + 1])) ?>" class="page-link">
                                    Next <i class="fas fa-chevron-right"></i>
                                </a>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </section>
    </div>

    <?php require_once('../includes/footer.php'); ?>

    <script>
        // Mobile sidebar toggle
        document.getElementById('menu_btn').addEventListener('click', function() {
            document.getElementById('sidebar').classList.toggle('show_sidebar');
        });

        document.getElementById('btn_close').addEventListener('click', function() {
            document.getElementById('sidebar').classList.remove('show_sidebar');
        });

        // Export functionality
        document.querySelector('.btn-export').addEventListener('click', function() {
            // Get current filters
            const params = new URLSearchParams(window.location.search);
            params.set('export', '1');
            
            // Redirect to export endpoint
            window.location.href = 'export_history.php?' + params.toString();
        });
    </script>
</body>
</html>