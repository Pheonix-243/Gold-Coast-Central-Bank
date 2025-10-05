<?php
require_once('../includes/auth.php');
require_once('../includes/conn.php');

// Get profile picture
$sql = "SELECT image FROM accountsholder WHERE account = ?";
$stmt = mysqli_prepare($con, $sql);
mysqli_stmt_bind_param($stmt, "s", $_SESSION['client_account']);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$profilePic = mysqli_fetch_assoc($result)['image'];

// Pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$perPage = 10;
$offset = ($page - 1) * $perPage;

// Filter parameters with defaults
$typeFilter = isset($_GET['type']) ? (int)$_GET['type'] : null;
$dateFrom = isset($_GET['date_from']) ? $_GET['date_from'] : null;
$dateTo = isset($_GET['date_to']) ? $_GET['date_to'] : null;
$minAmount = isset($_GET['min_amount']) ? (float)$_GET['min_amount'] : null;
$maxAmount = isset($_GET['max_amount']) ? (float)$_GET['max_amount'] : null;
$direction = isset($_GET['direction']) ? $_GET['direction'] : null;
$search = isset($_GET['search']) ? trim($_GET['search']) : null;

// Base query with totals calculation
$sql = "SELECT SQL_CALC_FOUND_ROWS h.*, t.name as type_name, 
               SUM(CASE WHEN h.receiver = ? AND h.account = h.receiver THEN h.amount ELSE 0 END) as incoming_total,
               SUM(CASE WHEN h.sender = ? AND h.account = h.sender THEN h.amount ELSE 0 END) as outgoing_total
        FROM account_history h
        JOIN transaction_types t ON h.type = t.id
        WHERE h.account = ?";

$params = [$_SESSION['client_account'], $_SESSION['client_account'], $_SESSION['client_account']];
$paramTypes = "sss";

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
    $sql .= " AND h.receiver = ? AND h.account = h.receiver";
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
$sql .= " GROUP BY h.no ORDER BY h.dt DESC, h.tm DESC LIMIT ? OFFSET ?";
$params[] = $perPage;
$params[] = $offset;
$paramTypes .= "ii";

// Execute query
$stmt = mysqli_prepare($con, $sql);
mysqli_stmt_bind_param($stmt, $paramTypes, ...$params);
mysqli_stmt_execute($stmt);
$transactions = mysqli_stmt_get_result($stmt);

// Get totals and row count
$totals = mysqli_fetch_assoc(mysqli_query($con, "SELECT 
    SUM(CASE WHEN receiver = '{$_SESSION['client_account']}' AND account = receiver THEN amount ELSE 0 END) as incoming_total,
    SUM(CASE WHEN sender = '{$_SESSION['client_account']}' AND account = sender THEN amount ELSE 0 END) as outgoing_total
    FROM account_history WHERE account = '{$_SESSION['client_account']}'"));

$incomingTotal = $totals['incoming_total'] ?? 0;
$outgoingTotal = $totals['outgoing_total'] ?? 0;

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
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gold Coast Central Bank - Transaction History</title>
    
    <meta name="description" content="Premium transaction history for Gold Coast Central Bank clients">
    <meta name="theme-color" content="#0f172a">
    
    <!-- Favicons -->
    <link rel="apple-touch-icon" sizes="180x180" href="apple-touch-icon.png">
    <link rel="icon" type="image/png" sizes="32x32" href="favicon-32x32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="favicon-16x16.png">
    <link rel="manifest" href="site.webmanifest">
    
    <!-- Preload critical fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    
    <!-- Font Awesome Pro -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Google Fonts - Inter with optimized loading -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    
    <!-- Custom Premium CSS -->
    <link rel="stylesheet" href="../dashboard/style.css">
    <link rel="stylesheet" href="history.css">
</head>

<body class="premium-dashboard">
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
                        <i class="fas fa-money-bill"></i>
                    </div>
                    <div class="nav_link_text">Load</div>
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
                <a href="../pages/analytics.php" class="nav_link" aria-label="history">
                    <div class="nav_link_icon">
                        <i class="fas fa-chart-line"></i>
                    </div>
                    <div class="nav_link_text">Analytics</div>
                </a>

                <a href="../pages/security.php" class="nav_link" aria-label="settings">
                    <div class="nav_link_icon">
                        <i class="fas fa-lock"></i>
                    </div>
                    <div class="nav_link_text">Security</div>
                </a>
            </div>

            <div class="profile">
                <div class="img_with_name">
                    <a href="../pages/profile.php">
                        <?php if (!empty($profilePic)): ?>
                            <img src="data:image/jpeg;base64,<?= base64_encode($profilePic) ?>" alt="Profile Picture">
                        <?php else: ?>
                            <img src="../images/default-profile.png" alt="Profile Picture">
                        <?php endif; ?>
                    </a>
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
                    <p class="welcome">View and analyze your account activity</p>
                </div>

                <div class="topbar_icons">
                    <a href="#" aria-label="notifications" class="topbar_icon alert">
                        <i class="fas fa-bell"></i>
                    </a>
                </div>
            </div>

            <section class="content_section">
                <main>
                    <!-- Premium Summary Cards -->
                    <div class="analytics-overview">
                        <div class="analytics-header">
                            <h3>Transaction Summary</h3>
                        </div>
                        
                        <div class="analytics-cards">
                            <div class="analytics-card income">
                                <div class="card-icon">
                                    <i class="fas fa-arrow-down"></i>
                                </div>
                                <div class="card-content">
                                    <h4>Total Incoming</h4>
                                    <p class="amount">GHC<?= number_format($incomingTotal, 2) ?></p>
                                    <span class="change positive">+<?= $totalRows > 0 ? round(($incomingTotal / ($incomingTotal + abs($outgoingTotal))) * 100, 1) : 0 ?>%</span>
                                </div>
                            </div>
                            
                            <div class="analytics-card expenses">
                                <div class="card-icon">
                                    <i class="fas fa-arrow-up"></i>
                                </div>
                                <div class="card-content">
                                    <h4>Total Outgoing</h4>
                                    <p class="amount">GHC<?= number_format(abs($outgoingTotal), 2) ?></p>
                                    <span class="change negative">
                                        -<?php echo ($totalRows > 0) ? round((abs($outgoingTotal) / ($incomingTotal + abs($outgoingTotal))) * 100, 1) : 0; ?>%
                                    </span>
                                </div>
                            </div>
                            
                            <div class="analytics-card transactions">
                                <div class="card-icon">
                                    <i class="fas fa-exchange-alt"></i>
                                </div>
                                <div class="card-content">
                                    <h4>Total Transactions</h4>
                                    <p class="amount"><?= number_format($totalRows) ?></p>
                                    <span class="change positive">+100%</span>
                                </div>
                            </div>
                            
                            <div class="analytics-card net">
                                <div class="card-icon">
                                    <i class="fas fa-piggy-bank"></i>
                                </div>
                                <div class="card-content">
                                    <h4>Net Flow</h4>
                                    <p class="amount">GHC<?= number_format($incomingTotal + $outgoingTotal, 2) ?></p>
                                    <span class="change <?= ($incomingTotal + $outgoingTotal) >= 0 ? 'positive' : 'negative' ?>">
                                        <?= ($incomingTotal + $outgoingTotal) >= 0 ? '+' : '' ?><?= number_format($incomingTotal + $outgoingTotal, 2) ?>
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Premium Filter Section -->
                    <div class="premium-quick-actions">
                        <div class="section-header">
                            <h3>Filter Transactions</h3>
                            <p class="section-subtitle">Refine your transaction history</p>
                        </div>
                        
                        <form id="historyFilters" class="filter-form">
                            <div class="quick-actions-grid">
                                <div class="filter-group">
                                    <label>Transaction Type</label>
                                    <select name="type" class="filter-select">
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
                                    <select name="direction" class="filter-select">
                                        <option value="">All</option>
                                        <option value="in" <?= $direction === 'in' ? 'selected' : '' ?>>Incoming</option>
                                        <option value="out" <?= $direction === 'out' ? 'selected' : '' ?>>Outgoing</option>
                                    </select>
                                </div>
                                
                                <div class="filter-group">
                                    <label>Date Range</label>
                                    <div class="date-range">
                                        <input type="date" name="date_from" value="<?= htmlspecialchars($dateFrom) ?>" class="filter-input" placeholder="From">
                                        <span>to</span>
                                        <input type="date" name="date_to" value="<?= htmlspecialchars($dateTo) ?>" class="filter-input" placeholder="To">
                                    </div>
                                </div>
                                
                                <div class="filter-group">
                                    <label>Amount Range</label>
                                    <div class="amount-range">
                                        <input type="number" name="min_amount" step="0.01" value="<?= htmlspecialchars($minAmount) ?>" class="filter-input" placeholder="Min">
                                        <span>to</span>
                                        <input type="number" name="max_amount" step="0.01" value="<?= htmlspecialchars($maxAmount) ?>" class="filter-input" placeholder="Max">
                                    </div>
                                </div>
                                
                                <div class="filter-group full-width">
                                    <label>Search</label>
                                    <div class="search-input">
                                        <i class="fas fa-search"></i>
                                        <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" class="filter-input" placeholder="Search transactions...">
                                        <?php if ($search): ?>
                                            <button type="button" class="clear-search" aria-label="Clear search">
                                                <i class="fas fa-times"></i>
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </form>
                    </div>

                    <!-- Transactions List -->
                    <div class="transactions_section">
                        <div class="section_header">
                            <h3>Recent Transactions</h3>
                            <div class="actions">
                                <button id="exportBtn" class="btn-icon">
                                    <i class="fas fa-download"></i> Export
                                </button>
                                <a href="history.php" class="btn-icon">
                                    <i class="fas fa-redo"></i> Reset
                                </a>
                            </div>
                        </div>
                        
                        <div id="transactionsContainer">
                            <?php if (mysqli_num_rows($transactions) > 0): ?>
                                <?php while ($row = mysqli_fetch_assoc($transactions)): ?>
                                    <?php
                                    $isOutgoing = $row['sender'] == $_SESSION['client_account'];
                                    $isIncoming = $row['receiver'] == $_SESSION['client_account'];
                                    $typeName = $row['type_name'];
                                    $peerName = $isOutgoing ? $row['r_name'] : $row['s_name'];
                                    $directionText = $isOutgoing ? 'To' : 'From';
                                    $amountClass = $isOutgoing ? 'outgoing' : 'incoming';
                                    $amountSign = $isOutgoing ? '-' : '+';
                                    ?>
                                    <div class="transaction_day_group">
                                        <h4><?= date('F j, Y', strtotime($row['dt'])) ?></h4>
                                        <div class="transaction_item <?= $amountClass ?>">
                                            <div class="transaction_icon">
                                                <?php if($isOutgoing): ?>
                                                    <i class="fas fa-arrow-up"></i>
                                                <?php else: ?>
                                                    <i class="fas fa-arrow-down"></i>
                                                <?php endif; ?>
                                            </div>
                                            <div class="transaction_details">
                                                <p class="transaction_title"><?= htmlspecialchars($typeName) ?></p>
                                                <?php if ($peerName): ?>
                                                    <p class="transaction_desc"><?= $directionText ?> <?= htmlspecialchars($peerName) ?></p>
                                                <?php endif; ?>
                                                <p class="transaction_time"><?= date('h:i A', strtotime($row['tm'])) ?></p>
                                                <?php if ($row['description']): ?>
                                                    <p class="transaction_notes"><?= htmlspecialchars($row['description']) ?></p>
                                                <?php endif; ?>
                                            </div>
                                            <div class="transaction_amount">
                                                <?= $amountSign ?> GHC<?= number_format($row['amount'], 2) ?>
                                            </div>
                                            <div class="transaction_actions">
                                                <a href="receipt.php?id=<?= $row['no'] ?>" class="action-btn" title="View Receipt">
                                                    <i class="fas fa-receipt"></i>
                                                </a>
                                            </div>
                                        </div>
                                    </div>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <div class="no_transactions">
                                    <div class="empty-state">
                                        <i class="fas fa-exchange-alt"></i>
                                        <h4>No transactions found</h4>
                                        <p>Try adjusting your filters or make your first transaction</p>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>

                        <!-- Pagination -->
                        <?php if ($totalPages > 1): ?>
                            <div class="pagination">
                                <?php if ($page > 1): ?>
                                    <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page - 1])) ?>" class="pagination-btn">
                                        <i class="fas fa-chevron-left"></i> Previous
                                    </a>
                                <?php endif; ?>
                                
                                <div class="pagination-numbers">
                                    <?php
                                    $startPage = max(1, $page - 2);
                                    $endPage = min($totalPages, $page + 2);
                                    
                                    if ($startPage > 1) {
                                        echo '<a href="?'.http_build_query(array_merge($_GET, ['page' => 1])).'" class="pagination-btn">1</a>';
                                        if ($startPage > 2) echo '<span class="pagination-dots">...</span>';
                                    }
                                    
                                    for ($i = $startPage; $i <= $endPage; $i++): ?>
                                        <a href="?<?= http_build_query(array_merge($_GET, ['page' => $i])) ?>" class="pagination-btn <?= $i == $page ? 'active' : '' ?>">
                                            <?= $i ?>
                                        </a>
                                    <?php endfor;
                                    
                                    if ($endPage < $totalPages) {
                                        if ($endPage < $totalPages - 1) echo '<span class="pagination-dots">...</span>';
                                        echo '<a href="?'.http_build_query(array_merge($_GET, ['page' => $totalPages])).'" class="pagination-btn">'.$totalPages.'</a>';
                                    }
                                    ?>
                                </div>
                                
                                <?php if ($page < $totalPages): ?>
                                    <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page + 1])) ?>" class="pagination-btn">
                                        Next <i class="fas fa-chevron-right"></i>
                                    </a>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </main>

                <aside>
                    <!-- Premium Insights -->
                    <div class="premium-insights-card">
                        <div class="insights-header">
                            <h3>Transaction Insights</h3>
                            <div class="insights-badge">
                                <i class="fas fa-lightbulb"></i>
                                AI Powered
                            </div>
                        </div>
                        
                        <div class="insights-content">
                            <div class="insight-item featured">
                                <div class="insight-icon">
                                    <i class="fas fa-chart-line"></i>
                                </div>
                                <div class="insight-text">
                                    <h4>Spending Pattern</h4>
                                    <p>Your spending has decreased by 15% compared to last month. Keep it up!</p>
                                    <span class="insight-action">View Details</span>
                                </div>
                            </div>
                            
                            <div class="insight-item">
                                <div class="insight-icon">
                                    <i class="fas fa-calendar"></i>
                                </div>
                                <div class="insight-text">
                                    <h4>Recurring Payments</h4>
                                    <p>You have 3 recurring payments scheduled this month</p>
                                </div>
                            </div>
                            
                            <div class="insight-item">
                                <div class="insight-icon">
                                    <i class="fas fa-tags"></i>
                                </div>
                                <div class="insight-text">
                                    <h4>Top Categories</h4>
                                    <p>Transfers (45%), Utilities (25%), Shopping (15%)</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Export Options -->
                    <div class="premium-services-card">
                        <div class="services-header">
                            <h3>Export Options</h3>
                            <span class="premium-badge">Premium</span>
                        </div>
                        
                        <div class="services-grid">
                            <div class="service-item">
                                <div class="service-icon">
                                    <i class="fas fa-file-pdf"></i>
                                </div>
                                <div class="service-content">
                                    <h4>PDF Report</h4>
                                    <p>Generate a detailed PDF report of your transactions</p>
                                    <button class="service-btn" id="exportPdf">Generate</button>
                                </div>
                            </div>
                            
                            <div class="service-item">
                                <div class="service-icon">
                                    <i class="fas fa-file-csv"></i>
                                </div>
                                <div class="service-content">
                                    <h4>CSV Export</h4>
                                    <p>Download your transaction data in CSV format</p>
                                    <button class="service-btn" id="exportCsv">Download</button>
                                </div>
                            </div>
                            
                            <div class="service-item">
                                <div class="service-icon">
                                    <i class="fas fa-file-excel"></i>
                                </div>
                                <div class="service-content">
                                    <h4>Excel Export</h4>
                                    <p>Export your transaction history to Excel</p>
                                    <button class="service-btn" id="exportExcel">Export</button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Help Card -->
                    <div class="premium-support-card">
                        <div class="support-header">
                            <div class="support-icon">
                                <i class="fas fa-question-circle"></i>
                            </div>
                            <div class="support-text">
                                <h3>Need Help?</h3>
                                <p>Our support team is available 24/7</p>
                            </div>
                        </div>
                        
                        <div class="support-actions">
                            <button class="support-btn primary">
                                <i class="fas fa-comments"></i>
                                Live Chat
                            </button>
                            <button class="support-btn secondary">
                                <i class="fas fa-phone"></i>
                                Call Now
                            </button>
                        </div>
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

        // Enhanced filtering with debounce and proper state management
        const filterForm = document.getElementById('historyFilters');
        const filterInputs = document.querySelectorAll('.filter-input, .filter-select');
        let filterTimeout;

        function applyFilters() {
            clearTimeout(filterTimeout);
            filterTimeout = setTimeout(() => {
                const formData = new FormData(filterForm);
                const params = new URLSearchParams();
                
                // Add all form data to params
                for (const [key, value] of formData.entries()) {
                    if (value) params.append(key, value);
                }
                
                // Reset to page 1 when filters change
                params.delete('page');
                
                // Update URL without reload
                history.replaceState(null, '', '?' + params.toString());
                
                // Fetch new results
                fetchTransactions(params.toString());
            }, 300);
        }

        // Fetch transactions via AJAX with error handling
        function fetchTransactions(queryString) {
            fetch('history_ajax.php?' + queryString, {
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })
                .then(response => {
                    if (!response.ok) throw new Error('Network response was not ok');
                    return response.text();
                })
                .then(html => {
                    document.getElementById('transactionsContainer').innerHTML = html;
                    updatePaginationLinks(queryString);
                })
                .catch(error => {
                    console.error('Error:', error);
                    document.getElementById('transactionsContainer').innerHTML = `
                        <div class="empty-state">
                            <i class="fas fa-exclamation-triangle"></i>
                            <h4>Error loading transactions</h4>
                            <p>Please try again later</p>
                        </div>
                    `;
                });
        }

        // Update pagination links to maintain filter state
        function updatePaginationLinks(queryString) {
            const paginationLinks = document.querySelectorAll('.pagination a');
            if (paginationLinks.length > 0) {
                const baseParams = new URLSearchParams(queryString);
                baseParams.delete('page');
                
                paginationLinks.forEach(link => {
                    const href = new URL(link.href);
                    const page = href.searchParams.get('page');
                    if (page) {
                        const newParams = new URLSearchParams(baseParams.toString());
                        newParams.set('page', page);
                        link.href = '?' + newParams.toString();
                    }
                });
            }
        }

        // Clear search button
        document.querySelector('.clear-search')?.addEventListener('click', function(e) {
            e.preventDefault();
            document.querySelector('input[name="search"]').value = '';
            applyFilters();
        });

        // Export buttons
        document.getElementById('exportPdf')?.addEventListener('click', function() {
            const params = new URLSearchParams(window.location.search);
            window.open('export.php?type=pdf&' + params.toString(), '_blank');
        });

        document.getElementById('exportCsv')?.addEventListener('click', function() {
            const params = new URLSearchParams(window.location.search);
            window.open('export.php?type=csv&' + params.toString(), '_blank');
        });

        document.getElementById('exportExcel')?.addEventListener('click', function() {
            const params = new URLSearchParams(window.location.search);
            window.open('export.php?type=excel&' + params.toString(), '_blank');
        });

        // Initialize filter event listeners
        filterInputs.forEach(input => {
            input.addEventListener('change', applyFilters);
            if (input.type === 'text' || input.type === 'number') {
                input.addEventListener('input', applyFilters);
            }
        });

        // Initialize date pickers with max date as today
        const today = new Date().toISOString().split('T')[0];
        document.querySelector('input[name="date_to"]')?.setAttribute('max', today);
        document.querySelector('input[name="date_from"]')?.setAttribute('max', today);

        // Apply filters on page load if any parameters exist
        if (window.location.search) {
            applyFilters();
        }
    </script>
</body>
</html>