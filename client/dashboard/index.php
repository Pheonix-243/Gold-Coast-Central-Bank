<?php
require_once('../includes/auth.php');
// require_once('../includes/header.php');



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
    <meta name="author" content="Your Name">

    <!-- favicons -->
<link rel="apple-touch-icon" sizes="180x180" href="/apple-touch-icon.png">
<link rel="icon" type="image/png" sizes="32x32" href="/favicon-32x32.png">
<link rel="icon" type="image/png" sizes="16x16" href="/favicon-16x16.png">
<link rel="manifest" href="/site.webmanifest">

    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">

    <!-- Link Swiper's CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/swiper/swiper-bundle.min.css" />

    <!-- Custom CSS -->
    <link rel="stylesheet" href="style.css">
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
                <!-- <span>Gold Coast Central Bank</span> -->
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

                <!-- <a href="../transactions/withdrawal.php" class="nav_link" aria-label="withdraw">
                    <div class="nav_link_icon">
                        <i class="fas fa-hand-holding-usd"></i>
                    </div>
                    <div class="nav_link_text">Withdraw</div>
                </a> -->

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
<!-- 
                <a href="../profile/view.php" class="nav_link" aria-label="profile">
                    <div class="nav_link_icon">
                        <i class="fas fa-user"></i>
                    </div>
                    <div class="nav_link_text">Profile</div>
                </a> -->

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
                    <p class="title">Dashboard</p>
                    <p class="desc">Hi <?= htmlspecialchars(explode(' ', $_SESSION['client_name'])[0]) ?>, welcome back!</p>
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

            <section>
                <main>
                    <!-- <div class="overview_text">
                        <p class="title">Dashboard</p>
                        <p class="desc">Hi <?= htmlspecialchars(explode(' ', $_SESSION['client_name'])[0]) ?>, welcome back!</p>
                    </div> -->

                    <div class="bank_balance_card">
                        <p>your balance</p>
                        <p class="balance">GHC<?= number_format($_SESSION['client_balance'], 2) ?></p>
                        <div class="account_no">
                            <span><?= htmlspecialchars($accountInfo['account_type'] ?? 'Account') ?> : <span class="no"><?= htmlspecialchars($_SESSION['client_account']) ?></span></span>
                            <button class="view_account_no" aria-label="show_ac">
                                <i class="fas fa-eye-slash"></i>
                            </button>
                        </div>
                    </div>

                    <div class="button_group">
                        <a href="../transactions/history.php" class="r_transaction" aria-label="show_transaction">
                            <i class="fas fa-exchange-alt"></i>
                            <span>recent transactions</span>
                        </a>

                        <a href="../transactions/statement.php" class="s_analysis" aria-label="show_analysis">
                            <i class="fas fa-file-invoice-dollar"></i>
                            <span>account statement</span>
                        </a>
                    </div>

<div class="transaction_info">
    <div class="transaction_date">
        <!-- Recent Transactions -->
        <hr class="transaction_divider">
        <div class="transaction_day_label">Today - <?= date('F j, Y') ?></div>
    </div>

    <?php if (empty($todayTransactions)): ?>
        <div class="transaction_data">
            <p>No transactions today</p>
        </div>
    <?php else: ?>
        <?php foreach ($todayTransactions as $row): ?>
            <?php
            $isOutgoing = $row['sender'] == $account;
            $amountClass = $isOutgoing ? 'text-danger' : 'text-success';
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
            <div class="transaction_data">
                <div class="get_send_money">
                    <span class="icon <?= $isOutgoing ? 'outgoing' : 'incoming' ?>">
                        <?php if($isOutgoing): ?>
                            <i class="fas fa-arrow-up"></i>
                        <?php else: ?>
                            <i class="fas fa-arrow-down"></i>
                        <?php endif; ?>
                    </span>
                    <div class="trasaction_details">
                        <p class="transaction_metadata"><?= htmlspecialchars($transactionText) ?></p>
                        <p><?= htmlspecialchars($row['description']) ?></p>
                    </div>
                </div>
                <p class="transaction_value <?= $amountClass ?>">
                    <?= $amountSign ?> GHC<?= number_format($row['amount'], 2) ?>
                </p>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>

    <?php if (!empty($yesterdayTransactions)): ?>
        <hr class="transaction_divider">
        <div class="transaction_day_label">Yesterday - <?= date('F j, Y', strtotime('-1 day')) ?></div>
        
        <?php foreach ($yesterdayTransactions as $row): ?>
            <?php
            $isOutgoing = $row['sender'] == $account;
            $amountClass = $isOutgoing ? 'text-danger' : 'text-success';
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
            <div class="transaction_data">
                <div class="get_send_money">
                    <span class="icon <?= $isOutgoing ? 'outgoing' : 'incoming' ?>">
                        <?php if($isOutgoing): ?>
                            <i class="fas fa-arrow-up"></i>
                        <?php else: ?>
                            <i class="fas fa-arrow-down"></i>
                        <?php endif; ?>
                    </span>
                    <div class="trasaction_details">
                        <p class="transaction_metadata"><?= htmlspecialchars($transactionText) ?></p>
                        <p><?= htmlspecialchars($row['description']) ?></p>
                    </div>
                </div>
                <p class="transaction_value <?= $amountClass ?>">
                    <?= $amountSign ?> GHC<?= number_format($row['amount'], 2) ?>
                </p>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

                    <a href="../transactions/history.php" class="show_more" aria-label="show_more">
                        <i class="fas fa-chevron-down"></i> View All Transactions
                    </a>
                </main>

                <aside>
                    <div class="transfer_money_section">
                        <p class="title">quick actions</p>
                        <div class="button_group">
                            <a href="../transactions/deposit.php" class="via_no" aria-label="deposit">Deposit</a>
                            <a href="../transactions/withdrawal.php" class="via_ac" aria-label="withdraw">Withdraw</a>
                        </div>

                        <form action="../transactions/transfer.php" method="GET">
                            <div class="phone_no_info">
                                <label for="account_no"><i class="fas fa-user"></i></label>
                                <input type="text" name="account" id="account_no" placeholder="Recipient Account">
                            </div>

                            <div class="amount">
                                <div>
                                    <label for="cedis">GHC</label>
                                    <input type="number" name="amount" id="cedis" placeholder="Amount" min="1" step="0.01">
                                </div>
                                <span>₵</span>
                            </div>

                            <input type="submit" value="Transfer Funds" />
                        </form>
                    </div>

                    <div class="cards">
                        <div class="title_with_button">
                            <p class="title">account summary</p>
                        </div>

                        <div class="account_summary">
                            <div class="summary_item">
                                <i class="fas fa-wallet"></i>
                                <div>
                                    <p>Monthly Income</p>
                                    <p>GHC<?= number_format($monthlySummary['income'] ?? 0, 2) ?></p>
                                </div>
                            </div>
                            <div class="summary_item">
                                <i class="fas fa-shopping-cart"></i>
                                <div>
                                    <p>Monthly Expenses</p>
                                    <p>GHC<?= number_format(abs($monthlySummary['expenses'] ?? 0), 2) ?></p>
                                </div>
                            </div>
                            <div class="summary_item">
                                <i class="fas fa-exchange-alt"></i>
                                <div>
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

    <!-- Swiper JS -->
    <script src="https://cdn.jsdelivr.net/npm/swiper/swiper-bundle.min.js"></script>

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
            const accountNo = document.querySelector('.account_no .no');
            const icon = this.querySelector('i');
            
            if(accountNo.style.filter === 'blur(4px)') {
                accountNo.style.filter = 'none';
                icon.classList.replace('fa-eye', 'fa-eye-slash');
            } else {
                accountNo.style.filter = 'blur(4px)';
                icon.classList.replace('fa-eye-slash', 'fa-eye');
            }
        });
    </script>
</body>
</html>