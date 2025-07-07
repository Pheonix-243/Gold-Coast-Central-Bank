<?php
require_once('../includes/auth.php');
require_once('../includes/notification.php');

// Add this to fetch the profile picture:
$sql = "SELECT image FROM accountsholder WHERE account = ?";
$stmt = mysqli_prepare($con, $sql);
mysqli_stmt_bind_param($stmt, "s", $account);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$profilePic = mysqli_fetch_assoc($result)['image'];

// Get account summary
$account = $_SESSION['client_account'];
$sql = "SELECT a.balance, a.account_type, h.name 
        FROM accounts_info a
        JOIN accountsholder h ON a.account = h.account
        WHERE a.account = ?";
$stmt = mysqli_prepare($con, $sql);
mysqli_stmt_bind_param($stmt, "s", $account);
mysqli_stmt_execute($stmt);
$accountInfo = mysqli_fetch_assoc($stmt->get_result());

// Get recent transactions - updated query to get more transactions
$sql = "SELECT h.*, t.name as type_name 
        FROM account_history h
        JOIN transaction_types t ON h.type = t.id
        WHERE h.account = ?
        ORDER BY h.dt DESC, h.tm DESC LIMIT 10";
$stmt = mysqli_prepare($con, $sql);
mysqli_stmt_bind_param($stmt, "s", $account);
mysqli_stmt_execute($stmt);
$allTransactions = mysqli_stmt_get_result($stmt);

// Separate transactions into today and yesterday
$todayTransactions = [];
$yesterdayTransactions = [];
$todayDate = date('Y-m-d');
$yesterdayDate = date('Y-m-d', strtotime('-1 day'));

while ($row = mysqli_fetch_assoc($allTransactions)) {
    $transactionDate = substr($row['dt'], 0, 10); // Extract date part
    if ($transactionDate === $todayDate) {
        $todayTransactions[] = $row;
    } elseif ($transactionDate === $yesterdayDate) {
        $yesterdayTransactions[] = $row;
    }
    
    // Limit to 3 transactions per day
    if (count($todayTransactions) >= 3 && count($yesterdayTransactions) >= 3) {
        break;
    }
}

// Get monthly summary
$currentMonth = date('Y-m');
$sql = "SELECT 
            SUM(CASE WHEN h.reciever = ? AND h.account = h.reciever THEN h.amount ELSE 0 END) as income,
            SUM(CASE WHEN h.sender = ? AND h.account = h.sender THEN h.amount ELSE 0 END) as expenses,
            COUNT(*) as count
        FROM account_history h
        WHERE h.account = ?
        AND h.dt LIKE ?";
$stmt = mysqli_prepare($con, $sql);
$monthPattern = $currentMonth . '%';
mysqli_stmt_bind_param($stmt, "ssss", $account, $account, $account, $monthPattern);
mysqli_stmt_execute($stmt);
$monthlySummary = mysqli_fetch_assoc($stmt->get_result());
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gold Coast Central Bank - Dashboard</title>

    <meta name="title" content="manage your finance with us">
    <meta name="description" content="Banking dashboard. all in one. manage everything using our web app">
    <meta name="keywords" content="Banking,finance,dashboards,finance website">
    <meta name="robots" content="index, follow">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8">
    <meta name="language" content="English">
    <meta name="revisit-after" content="30 days">
    <meta name="author" content="Gold Coast Central Bank">

    <!-- favicons -->
    <link rel="apple-touch-icon" sizes="180x180" href="/apple-touch-icon.png">
    <link rel="icon" type="image/png" sizes="32x32" href="/favicon-32x32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="/favicon-16x16.png">
    <link rel="manifest" href="/site.webmanifest">

    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">

    <!-- Custom CSS -->
    <link rel="stylesheet" href="style.css">
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
                <a href="../dashboard/" class="nav_link active" aria-label="overview">
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

                <a href="../transactions/history.php" class="nav_link" aria-label="history">
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

                <a href="../settings/password.php" class="nav_link" aria-label="settings">
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
                        <p class="occupation"><?= htmlspecialchars($accountInfo['account_type'] ?? 'Account') ?></p>
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
                    <h1>Dashboard</h1>
                    <p class="welcome">Hi <?= htmlspecialchars(explode(' ', $_SESSION['client_name'])[0]) ?>, welcome back!</p>
                </div>

                <div class="topbar_icons">
                    <a href="#" aria-label="notifications" class="topbar_icon alert">
                        <i class="fas fa-bell"></i>
                    </a>
                </div>
            </div>

            <section class="content_section">
                <main>
                    <div class="balance_card">
                        <div class="balance_header">
                            <span>Your Balance</span>
                            <span><?= htmlspecialchars($accountInfo['account_type'] ?? 'Account') ?></span>
                        </div>
                        <div class="balance_amount">
                            GHC<?= number_format($_SESSION['client_balance'], 2) ?>
                        </div>
                        <div class="account_details">
                            <span>Account: <span class="account_no"><?= htmlspecialchars($_SESSION['client_account']) ?></span></span>
                            <button class="view_account_no" aria-label="show_ac">
                                <i class="fas fa-eye-slash"></i>
                            </button>
                        </div>
                    </div>

                    <div class="action_buttons">
                        <a href="../transactions/history.php" class="btn btn-primary">
                            <i class="fas fa-exchange-alt"></i>
                            <span>Recent Transactions</span>
                        </a>
                        <a href="../transactions/statement.php" class="btn btn-secondary">
                            <i class="fas fa-file-invoice-dollar"></i>
                            <span>Account Statement</span>
                        </a>
                    </div>

                    <div class="transactions_section">
                        <h3>Recent Transactions</h3>
                        
                        <div class="transaction_day_group">
                            <h4>Today - <?= date('F j, Y') ?></h4>
                            <?php if (empty($todayTransactions)): ?>
                                <div class="no_transactions">
                                    <p>No transactions today</p>
                                </div>
                            <?php else: ?>
                                <?php foreach ($todayTransactions as $row): ?>
                                    <?php
                                    $isOutgoing = $row['sender'] == $account;
                                    $amountClass = $isOutgoing ? 'outgoing' : 'incoming';
                                    $amountSign = $isOutgoing ? '-' : '+';
                                    $transactionText = '';
                                    
                                    switch($row['type_name']) {
                                        case 'Transfer':
                                            $transactionText = "Transfer to {$row['r_name']}";
                                            break;
                                        case 'Payment Recieved':
                                            $transactionText = "Payment received from {$row['s_name']}";
                                            break;
                                        case 'Withdrawal':
                                            $transactionText = "Cash withdrawal";
                                            break;
                                        case 'Deposit':
                                            $transactionText = "Deposit";
                                            break;
                                        default:
                                            $transactionText = $row['type_name'];
                                    }
                                    ?>
                                    <div class="transaction_item <?= $amountClass ?>">
                                        <div class="transaction_icon">
                                            <?php if($isOutgoing): ?>
                                                <i class="fas fa-arrow-up"></i>
                                            <?php else: ?>
                                                <i class="fas fa-arrow-down"></i>
                                            <?php endif; ?>
                                        </div>
                                        <div class="transaction_details">
                                            <p class="transaction_title"><?= htmlspecialchars($transactionText) ?></p>
                                            <p class="transaction_desc"><?= htmlspecialchars($row['description']) ?></p>
                                        </div>
                                        <div class="transaction_amount">
                                            <?= $amountSign ?> GHC<?= number_format($row['amount'], 2) ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>

                        <?php if (!empty($yesterdayTransactions)): ?>
                            <div class="transaction_day_group">
                                <h4>Yesterday - <?= date('F j, Y', strtotime('-1 day')) ?></h4>
                                <?php foreach ($yesterdayTransactions as $row): ?>
                                    <?php
                                    $isOutgoing = $row['sender'] == $account;
                                    $amountClass = $isOutgoing ? 'outgoing' : 'incoming';
                                    $amountSign = $isOutgoing ? '-' : '+';
                                    $transactionText = '';
                                    
                                    switch($row['type_name']) {
                                        case 'Transfer':
                                            $transactionText = "Transfer to {$row['r_name']}";
                                            break;
                                        case 'Payment Recieved':
                                            $transactionText = "Payment received from {$row['s_name']}";
                                            break;
                                        case 'Withdrawal':
                                            $transactionText = "Cash withdrawal";
                                            break;
                                        case 'Deposit':
                                            $transactionText = "Deposit";
                                            break;
                                        default:
                                            $transactionText = $row['type_name'];
                                    }
                                    ?>
                                    <div class="transaction_item <?= $amountClass ?>">
                                        <div class="transaction_icon">
                                            <?php if($isOutgoing): ?>
                                                <i class="fas fa-arrow-up"></i>
                                            <?php else: ?>
                                                <i class="fas fa-arrow-down"></i>
                                            <?php endif; ?>
                                        </div>
                                        <div class="transaction_details">
                                            <p class="transaction_title"><?= htmlspecialchars($transactionText) ?></p>
                                            <p class="transaction_desc"><?= htmlspecialchars($row['description']) ?></p>
                                        </div>
                                        <div class="transaction_amount">
                                            <?= $amountSign ?> GHC<?= number_format($row['amount'], 2) ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>

                        <a href="../transactions/history.php" class="view_all_transactions">
                            <i class="fas fa-chevron-down"></i> View All Transactions
                        </a>
                    </div>
                </main>

                <aside>
                    <div class="quick_actions">
                        <h3>Quick Actions</h3>
                        <div class="action_buttons">
                            <a href="../transactions/deposit.php" class="btn btn-primary">
                                <i class="fas fa-money-bill-wave"></i>
                                <span>Deposit</span>
                            </a>
                            <a href="../transactions/withdrawal.php" class="btn btn-secondary">
                                <i class="fas fa-hand-holding-usd"></i>
                                <span>Withdraw</span>
                            </a>
                        </div>

                        <form action="../transactions/transfer.php" method="GET" class="transfer_form">
                            <div class="form_group">
                                <label for="account_no"><i class="fas fa-user"></i> Recipient Account</label>
                                <input type="text" name="account" id="account_no" placeholder="Enter account number">
                            </div>

                            <div class="form_group">
                                <label for="cedis">Amount (GHC)</label>
                                <input type="number" name="amount" id="cedis" placeholder="0.00" min="1" step="0.01">
                            </div>

                            <button type="submit" class="btn btn-primary full-width">
                                <i class="fas fa-paper-plane"></i> Transfer Funds
                            </button>
                        </form>
                    </div>

                    <div class="account_summary">
                        <h3>Account Summary</h3>
                        <div class="summary_items">
                            <div class="summary_item">
                                <div class="summary_icon income">
                                    <i class="fas fa-wallet"></i>
                                </div>
                                <div class="summary_details">
                                    <p>Monthly Income</p>
                                    <p>GHC<?= number_format($monthlySummary['income'] ?? 0, 2) ?></p>
                                </div>
                            </div>
                            <div class="summary_item">
                                <div class="summary_icon expense">
                                    <i class="fas fa-shopping-cart"></i>
                                </div>
                                <div class="summary_details">
                                    <p>Monthly Expenses</p>
                                    <p>GHC<?= number_format(abs($monthlySummary['expenses'] ?? 0), 2) ?></p>
                                </div>
                            </div>
                            <div class="summary_item">
                                <div class="summary_icon transactions">
                                    <i class="fas fa-exchange-alt"></i>
                                </div>
                                <div class="summary_details">
                                    <p>Total Transactions</p>
                                    <p><?= $monthlySummary['count'] ?? 0 ?></p>
                                </div>
                            </div>
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

        // Account number toggle
        document.querySelector('.view_account_no').addEventListener('click', function() {
            const accountNo = document.querySelector('.account_no');
            const icon = this.querySelector('i');
            
            if(accountNo.style.filter === 'blur(4px)') {
                accountNo.style.filter = 'none';
                icon.classList.replace('fa-eye', 'fa-eye-slash');
            } else {
                accountNo.style.filter = 'blur(4px)';
                icon.classList.replace('fa-eye-slash', 'fa-eye');
            }
        });

// Replace your existing notification dropdown code with this:

// Notification system
document.addEventListener('DOMContentLoaded', function() {
    const notificationBell = document.querySelector('.topbar_icon.alert');
    const notificationDropdown = document.createElement('div');
    notificationDropdown.className = 'notification-dropdown';
    notificationDropdown.innerHTML = `
        <div class="notification-header">
            <h4>Notifications</h4>
            <button id="mark-all-read">Mark all as read</button>
        </div>
        <div class="notification-list"></div>
        <div class="notification-footer">
            <a href="../pages/notifications.php">View all notifications</a>
        </div>
    `;
    notificationBell.appendChild(notificationDropdown);
    
    // Load notifications and update badge
    function updateNotifications() {
        fetch('../scripts/get_unread_count.php')
            .then(response => response.json())
            .then(data => {
                updateBadge(data.count || 0);
            });
    }
    
    // Update badge count
    function updateBadge(count) {
        let badge = notificationBell.querySelector('.notification-badge');
        
        if (count > 0) {
            if (!badge) {
                badge = document.createElement('span');
                badge.className = 'notification-badge';
                notificationBell.appendChild(badge);
            }
            badge.textContent = count > 9 ? '9+' : count;
        } else if (badge) {
            badge.remove();
        }
    }
    
    // Toggle dropdown
    notificationBell.addEventListener('click', function(e) {
        e.stopPropagation();
        
        if (notificationDropdown.style.display === 'block') {
            notificationDropdown.style.display = 'none';
        } else {
            // When opening dropdown
            notificationDropdown.style.display = 'block';
            loadNotifications(false);
            
            // Clear badge immediately
            updateBadge(0);
            
            // Mark all as read in backend
            fetch('../scripts/mark_all_read.php', {
                method: 'POST'
            }).catch(error => console.error('Error:', error));
        }
    });
    
    // Close when clicking outside
    document.addEventListener('click', function() {
        notificationDropdown.style.display = 'none';
    });
    
    // Initial load
    updateNotifications();
    
    // Poll for new notifications every 60 seconds
    setInterval(updateNotifications, 60000);
    
    // Function to load notifications
    function loadNotifications(unreadOnly = false) {
        fetch('../scripts/get_notifications.php?unread=' + (unreadOnly ? '1' : '0'))
            .then(response => response.json())
            .then(data => {
                const list = notificationDropdown.querySelector('.notification-list');
                list.innerHTML = '';
                
                if (data.length === 0) {
                    list.innerHTML = '<div class="no-notifications">No notifications</div>';
                    return;
                }
                
                data.forEach(notif => {
                    const item = document.createElement('div');
                    item.className = `notification-item ${notif.is_read ? '' : 'unread'}`;
                    item.dataset.id = notif.id;
                    item.innerHTML = `
                        <div class="notification-icon">
                            ${getNotificationIcon(notif.type)}
                        </div>
                        <div class="notification-content">
                            <div class="notification-title">${notif.title}</div>
                            <div class="notification-message">${notif.message}</div>
                            <div class="notification-time">${notif.time_ago}</div>
                        </div>
                        <button class="notification-delete" ${notif.is_deletable ? '' : 'disabled'}>
                            <i class="fas fa-times"></i>
                        </button>
                    `;
                    list.appendChild(item);
                    
                    // Click handler for individual notifications
                    item.addEventListener('click', function(e) {
                        if (!e.target.classList.contains('notification-delete')) {
                            if (!notif.is_read) {
                                markAsRead(notif.id);
                                item.classList.remove('unread');
                            }
                        }
                    });
                    
                    // Delete button handler
                    const deleteBtn = item.querySelector('.notification-delete');
                    deleteBtn.addEventListener('click', function(e) {
                        e.stopPropagation();
                        deleteNotification(notif.id);
                        item.remove();
                    });
                });
            });
    }
    
    // Helper functions
    function getNotificationIcon(type) {
        const icons = {
            'transaction': '<i class="fas fa-exchange-alt"></i>',
            'login': '<i class="fas fa-sign-in-alt"></i>',
            'security': '<i class="fas fa-shield-alt"></i>',
            'profile_update': '<i class="fas fa-user-edit"></i>',
            'system': '<i class="fas fa-info-circle"></i>'
        };
        return icons[type] || '<i class="fas fa-bell"></i>';
    }
    
    function markAsRead(id) {
        fetch('../scripts/mark_notification_read.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({ id: id })
        });
    }
    
    function deleteNotification(id) {
        fetch('../scripts/delete_notification.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({ id: id })
        });
    }
});
    </script>
</body>
</html>