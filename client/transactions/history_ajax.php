<?php
require_once('../includes/auth.php');
require_once('../includes/conn.php');

// Same filtering logic as in history.php but only returns the transactions list
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

// Base query
$sql = "SELECT SQL_CALC_FOUND_ROWS h.*, t.name as type_name 
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

// Get total count for pagination
$totalRows = mysqli_fetch_assoc(mysqli_query($con, "SELECT FOUND_ROWS() as total"))['total'];
$totalPages = ceil($totalRows / $perPage);

// Output the transactions list
if (mysqli_num_rows($transactions) > 0): 
    while ($row = mysqli_fetch_assoc($transactions)):
        $isOutgoing = $row['sender'] == $_SESSION['client_account'];
        $isIncoming = $row['reciever'] == $_SESSION['client_account'];
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
<?php 
    endwhile;
    
    // Pagination for AJAX results
    if ($totalPages > 1):
        $currentParams = $_GET;
        unset($currentParams['page']);
?>
<div class="pagination">
    <?php if ($page > 1): 
        $currentParams['page'] = $page - 1;
    ?>
        <a href="?<?= http_build_query($currentParams) ?>" class="pagination-btn">
            <i class="fas fa-chevron-left"></i> Previous
        </a>
    <?php endif; ?>
    
    <div class="pagination-numbers">
        <?php
        $startPage = max(1, $page - 2);
        $endPage = min($totalPages, $page + 2);
        
        if ($startPage > 1) {
            $currentParams['page'] = 1;
            echo '<a href="?'.http_build_query($currentParams).'" class="pagination-btn">1</a>';
            if ($startPage > 2) echo '<span class="pagination-dots">...</span>';
        }
        
        for ($i = $startPage; $i <= $endPage; $i++): 
            $currentParams['page'] = $i;
        ?>
            <a href="?<?= http_build_query($currentParams) ?>" class="pagination-btn <?= $i == $page ? 'active' : '' ?>">
                <?= $i ?>
            </a>
        <?php endfor;
        
        if ($endPage < $totalPages) {
            if ($endPage < $totalPages - 1) echo '<span class="pagination-dots">...</span>';
            $currentParams['page'] = $totalPages;
            echo '<a href="?'.http_build_query($currentParams).'" class="pagination-btn">'.$totalPages.'</a>';
        }
        ?>
    </div>
    
    <?php if ($page < $totalPages): 
        $currentParams['page'] = $page + 1;
    ?>
        <a href="?<?= http_build_query($currentParams) ?>" class="pagination-btn">
            Next <i class="fas fa-chevron-right"></i>
        </a>
    <?php endif; ?>
</div>
<?php endif; ?>
<?php else: ?>
<div class="no_transactions">
    <div class="empty-state">
        <i class="fas fa-exchange-alt"></i>
        <h4>No transactions found</h4>
        <p>Try adjusting your filters or make your first transaction</p>
    </div>
</div>
<?php endif; ?>