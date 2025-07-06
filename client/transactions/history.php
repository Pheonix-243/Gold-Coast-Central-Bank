<?php
require_once('../includes/auth.php');

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

// Base query
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
<body>
    <div class="container">
        <nav class="sidebar" id="sidebar">
            <button id="btn_close">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="16" height="16">
                    <path fill="none" d="M0 0h24v24H0z" />
                    <path d="M12 10.586l4.95-4.95 1.414 1.414-4.95 4.95 4.95 4.95-1.414 1.414-4.95-4.95-4.95 4.95-1.414-1.414 4.95-4.95-4.95-4.95L7.05 5.636z" />
                </svg>
            </button>

            <div class="logo">
                <img src="../../gccb_logos/logo-transparent.svg" alt="Gold Coast Central Bank">
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

                <a href="../settings/password.php" class="nav_link" aria-label="settings">
                    <div class="nav_link_icon">
                        <i class="fas fa-lock"></i>
                    </div>
                    <div class="nav_link_text">Security</div>
                </a>
            </div>

            <div class="profile">
                <div class="img_with_name">
                    <img src="../images/default-profile.png" alt="Profile Picture">
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
                    <h1>Transaction History</h1>
                    <p class="welcome">View your account activity and transactions</p>
                </div>

                <div class="topbar_icons">
                    <a href="#" aria-label="notifications" class="topbar_icon alert">
                        <i class="fas fa-bell"></i>
                    </a>
                </div>
            </div>

            <section class="transfer_section">
                <main class="transfer_main">
                    <div class="transfer_card">
                        <div class="transfer_header">
                            <div class="transfer_icon">
                                <i class="fas fa-history"></i>
                            </div>
                            <div>
                                <h2>Transaction History</h2>
                                <p>View and filter your account transactions</p>
                            </div>
                        </div>

                        <!-- Summary Cards -->
                        <div class="summary-cards">
                            <div class="info_card">
                                <h3><i class="fas fa-arrow-down success"></i> Total Incoming</h3>
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
                            
                            <div class="info_card">
                                <h3><i class="fas fa-arrow-up warning"></i> Total Outgoing</h3>
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
                            
                            <div class="info_card">
                                <h3><i class="fas fa-exchange-alt primary"></i> Total Transactions</h3>
                                <p><?= number_format($totalRows) ?></p>
                            </div>
                        </div>

                        <!-- Filters -->
                        <div class="transfer_card">
                            <h3><i class="fas fa-filter"></i> Filter Transactions</h3>
                            <form method="GET" class="transfer_form">
                                <div class="form_group">
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
                                
                                <div class="form_group">
                                    <label>Direction</label>
                                    <select name="direction">
                                        <option value="">All</option>
                                        <option value="in" <?= $direction === 'in' ? 'selected' : '' ?>>Incoming</option>
                                        <option value="out" <?= $direction === 'out' ? 'selected' : '' ?>>Outgoing</option>
                                    </select>
                                </div>
                                
                                <div class="form_group">
                                    <label>Date From</label>
                                    <input type="date" name="date_from" value="<?= htmlspecialchars($dateFrom) ?>">
                                </div>
                                
                                <div class="form_group">
                                    <label>Date To</label>
                                    <input type="date" name="date_to" value="<?= htmlspecialchars($dateTo) ?>">
                                </div>
                                
                                <div class="form_group">
                                    <label>Min Amount (GHC)</label>
                                    <input type="number" name="min_amount" step="0.01" value="<?= htmlspecialchars($minAmount) ?>">
                                </div>
                                
                                <div class="form_group">
                                    <label>Max Amount (GHC)</label>
                                    <input type="number" name="max_amount" step="0.01" value="<?= htmlspecialchars($maxAmount) ?>">
                                </div>
                                
                                <div class="form_group">
                                    <label>Search</label>
                                    <input type="text" name="search" placeholder="Search transactions..." value="<?= htmlspecialchars($search) ?>">
                                </div>
                                
                                <div class="form_group" style="display: flex; gap: 15px; margin-top: 20px;">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-filter"></i> Apply Filters
                                    </button>
                                    <a href="history.php" class="btn btn-outline">
                                        <i class="fas fa-redo"></i> Reset
                                    </a>
                                </div>
                            </form>
                        </div>

                        <!-- Transactions Table -->
                        <div class="transfer_card">
                            <div class="transfer_header" style="margin-bottom: 20px;">
                                <h3><i class="fas fa-list"></i> Transaction List</h3>
                                <button class="btn btn-outline">
                                    <i class="fas fa-download"></i> Export
                                </button>
                            </div>
                            
                            <?php if (mysqli_num_rows($transactions) > 0): ?>
                                <ul class="info_list">
                                    <?php while ($row = mysqli_fetch_assoc($transactions)): ?>
                                        <?php
                                        $isOutgoing = $row['sender'] == $_SESSION['client_account'];
                                        $isIncoming = $row['reciever'] == $_SESSION['client_account'];
                                        $typeName = $row['type_name'];
                                        $peerName = $isOutgoing ? $row['r_name'] : $row['s_name'];
                                        $directionText = $isOutgoing ? 'To' : 'From';
                                        $amountClass = $isOutgoing ? 'outgoing' : 'incoming';
                                        $amountSign = $isOutgoing ? '-' : '+';
                                        ?>
                                        <li style="padding: 15px 0; border-bottom: 1px solid var(--light-gray);">
                                            <div style="display: flex; justify-content: space-between; width: 100%;">
                                                <div style="flex: 1;">
                                                    <div style="display: flex; align-items: center; gap: 15px;">
                                                        <div style="width: 40px; height: 40px; border-radius: 50%; background: <?= $isOutgoing ? 'rgba(220, 53, 69, 0.1)' : 'rgba(40, 167, 69, 0.1)'; ?>; display: flex; align-items: center; justify-content: center; color: <?= $isOutgoing ? 'var(--danger-red)' : 'var(--success-green)'; ?>;">
                                                            <i class="fas fa-<?= $isOutgoing ? 'arrow-up' : 'arrow-down' ?>"></i>
                                                        </div>
                                                        <div>
                                                            <strong><?= htmlspecialchars($typeName) ?></strong>
                                                            <p style="margin: 5px 0 0 0; color: var(--medium-gray); font-size: 14px;">
                                                                <?= date('M j, Y h:i A', strtotime($row['dt'] . ' ' . $row['tm'])) ?>
                                                            </p>
                                                            <?php if ($peerName): ?>
                                                                <p style="margin: 5px 0 0 0; color: var(--medium-gray); font-size: 14px;">
                                                                    <?= $directionText ?> <?= htmlspecialchars($peerName) ?>
                                                                </p>
                                                            <?php endif; ?>
                                                            <p style="margin: 5px 0 0 0; color: var(--medium-gray); font-size: 14px;">
                                                                <?= htmlspecialchars($row['description']) ?>
                                                            </p>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div style="text-align: right;">
                                                    <strong style="color: <?= $isOutgoing ? 'var(--danger-red)' : 'var(--success-green)'; ?>;">
                                                        <?= $amountSign ?>GHC<?= number_format($row['amount'], 2) ?>
                                                    </strong>
                                                    <p style="margin: 5px 0 0 0; color: var(--medium-gray); font-size: 14px;">
                                                        Ref: <?= htmlspecialchars($row['reference_number']) ?>
                                                    </p>
                                                    <div style="display: flex; gap: 10px; justify-content: flex-end; margin-top: 10px;">
                                                        <a href="receipt.php?id=<?= $row['no'] ?>" class="btn btn-outline" style="padding: 5px 10px; font-size: 12px;">
                                                            <i class="fas fa-receipt"></i> Receipt
                                                        </a>
                                                    </div>
                                                </div>
                                            </div>
                                        </li>
                                    <?php endwhile; ?>
                                </ul>
                            <?php else: ?>
                                <div class="alert alert-danger">
                                    <i class="fas fa-info-circle"></i> No transactions found matching your criteria
                                </div>
                            <?php endif; ?>

                            <!-- Pagination -->
                            <?php if ($totalPages > 1): ?>
                                <div style="display: flex; justify-content: center; margin-top: 20px; gap: 10px;">
                                    <?php if ($page > 1): ?>
                                        <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page - 1])) ?>" class="btn btn-outline">
                                            <i class="fas fa-chevron-left"></i> Previous
                                        </a>
                                    <?php endif; ?>
                                    
                                    <div style="display: flex; gap: 5px;">
                                        <?php
                                        $startPage = max(1, $page - 2);
                                        $endPage = min($totalPages, $page + 2);
                                        
                                        if ($startPage > 1) {
                                            echo '<a href="?'.http_build_query(array_merge($_GET, ['page' => 1])).'" class="btn btn-outline">1</a>';
                                            if ($startPage > 2) echo '<span class="btn btn-outline" style="pointer-events: none;">...</span>';
                                        }
                                        
                                        for ($i = $startPage; $i <= $endPage; $i++): ?>
                                            <a href="?<?= http_build_query(array_merge($_GET, ['page' => $i])) ?>" class="btn <?= $i == $page ? 'btn-primary' : 'btn-outline' ?>">
                                                <?= $i ?>
                                            </a>
                                        <?php endfor;
                                        
                                        if ($endPage < $totalPages) {
                                            if ($endPage < $totalPages - 1) echo '<span class="btn btn-outline" style="pointer-events: none;">...</span>';
                                            echo '<a href="?'.http_build_query(array_merge($_GET, ['page' => $totalPages])).'" class="btn btn-outline">'.$totalPages.'</a>';
                                        }
                                        ?>
                                    </div>
                                    
                                    <?php if ($page < $totalPages): ?>
                                        <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page + 1])) ?>" class="btn btn-outline">
                                            Next <i class="fas fa-chevron-right"></i>
                                        </a>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </main>
                
                <aside class="transfer_aside">
                    <div class="info_card">
                        <h3><i class="fas fa-info-circle"></i> Transaction Help</h3>
                        <p>Filter your transactions by type, date range, amount, or search for specific transactions.</p>
                    </div>
                    
                    <div class="info_card">
                        <h3><i class="fas fa-clock"></i> Processing Times</h3>
                        <ul class="info_list">
                            <li>
                                <strong>Transfers:</strong> Instant to 2 hours
                            </li>
                            <li>
                                <strong>Deposits:</strong> Instant to 30 minutes
                            </li>
                            <li>
                                <strong>Withdrawals:</strong> Instant
                            </li>
                        </ul>
                    </div>
                    
                    <div class="info_card">
                        <h3><i class="fas fa-headset"></i> Need Help?</h3>
                        <p>Contact our 24/7 customer support for assistance with your transactions.</p>
                        <a href="#" class="btn btn-outline">
                            <i class="fas fa-phone"></i> Contact Support
                        </a>
                    </div>
                </aside>
            </section>
        </section>
    </div>

    <!-- Custom JS -->
    <script src="../js/main.js"></script>
    <script>
        // Mobile sidebar toggle
        document.getElementById('menu_btn').addEventListener('click', function() {
            document.getElementById('sidebar').classList.toggle('show_sidebar');
        });

        document.getElementById('btn_close').addEventListener('click', function() {
            document.getElementById('sidebar').classList.remove('show_sidebar');
        });
    </script>
</body>
</html>