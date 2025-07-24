<?php require_once 'config.php'; ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Gold Coast Central Bank - Empowering Ghana's Financial Future. Professional banking services including personal banking, business banking, and digital financial solutions.">
    <meta name="keywords" content="Ghana bank, Gold Coast Central Bank, GCC Bank, banking services, financial services, mobile money, digital wallet">
    <meta name="author" content="Gold Coast Central Bank">
    
    <!-- Open Graph Meta Tags -->
    <meta property="og:title" content="<?= isset($page_title) ? $page_title . ' - ' . SITE_NAME : SITE_NAME ?>">
    <meta property="og:description" content="Professional banking services in Ghana. Secure, reliable, and innovative financial solutions.">
    <meta property="og:type" content="website">
    <meta property="og:url" content="<?= SITE_URL ?>">
    
    <title><?= isset($page_title) ? $page_title . ' - ' . SITE_NAME : SITE_NAME ?></title>
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Playfair+Display:wght@400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Custom CSS -->
    <link rel="stylesheet" href="assets/css/styles.css">
    
    <!-- Favicon -->
    <link rel="icon" type="image/x-icon" href="assets/images/logo.svg">
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-light bg-white shadow-sm fixed-top">
        <div class="container">
            <a class="navbar-brand d-flex align-items-center" href="index.php">
                 <img src="assets/images/gcc-logo.webp" alt="GCC Bank Logo" height="40" class="me-2"> 
                <!-- <span class="brand-text d-none d-lg-block">Gold Coast Central Bank</span> -->
            </a>
            
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav mx-auto">
                    <li class="nav-item">
                        <a class="nav-link <?= basename($_SERVER['PHP_SELF']) == 'index.php' ? 'active' : '' ?>" href="index.php">Home</a>
                    </li>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="personalBankingDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            Personal Banking
                        </a>
                        <ul class="dropdown-menu" aria-labelledby="personalBankingDropdown">
                            <li><a class="dropdown-item" href="personal-accounts.php"><i class="fas fa-piggy-bank me-2"></i>Accounts</a></li>
                            <li><a class="dropdown-item" href="loans.php"><i class="fas fa-hand-holding-usd me-2"></i>Loans</a></li>
                            <li><a class="dropdown-item" href="cards.php"><i class="fas fa-credit-card me-2"></i>Cards</a></li>
                            <li><a class="dropdown-item" href="investments.php"><i class="fas fa-chart-line me-2"></i>Investments</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="digital-banking.php"><i class="fas fa-mobile-alt me-2"></i>Digital Banking</a></li>
                        </ul>
                    </li>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="businessBankingDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            Business Banking
                        </a>
                        <ul class="dropdown-menu" aria-labelledby="businessBankingDropdown">
                            <li><a class="dropdown-item" href="business-accounts.php"><i class="fas fa-building me-2"></i>Business Accounts</a></li>
                            <li><a class="dropdown-item" href="trade-finance.php"><i class="fas fa-ship me-2"></i>Trade Finance</a></li>
                            <li><a class="dropdown-item" href="cash-management.php"><i class="fas fa-money-bill-wave me-2"></i>Cash Management</a></li>
                            <li><a class="dropdown-item" href="corporate-finance.php"><i class="fas fa-handshake me-2"></i>Corporate Finance</a></li>
                        </ul>
                    </li>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="aboutDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            About Us
                        </a>
                        <ul class="dropdown-menu" aria-labelledby="aboutDropdown">
                            <li><a class="dropdown-item" href="about.php"><i class="fas fa-info-circle me-2"></i>Our Story</a></li>
                            <li><a class="dropdown-item" href="leadership.php"><i class="fas fa-users me-2"></i>Leadership</a></li>
                            <li><a class="dropdown-item" href="careers.php"><i class="fas fa-briefcase me-2"></i>Careers</a></li>
                            <li><a class="dropdown-item" href="news.php"><i class="fas fa-newspaper me-2"></i>Newsroom</a></li>
                        </ul>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="rates.php">Rates</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="contact.php">Contact</a>
                    </li>
                </ul>
                
                <div class="d-flex">
                    <?php if (isset($_SESSION['user_id'])): ?>
                        <div class="dropdown">
                            <button class="btn btn-outline-primary dropdown-toggle" type="button" id="userDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                                <i class="fas fa-user me-1"></i> <?= htmlspecialchars($_SESSION['user_name'] ?? 'User') ?>
                            </button>
                            <ul class="dropdown-menu" aria-labelledby="userDropdown">
                                <li><a class="dropdown-item" href="dashboard.php"><i class="fas fa-tachometer-alt me-2"></i>Dashboard</a></li>
                                <li><a class="dropdown-item" href="profile.php"><i class="fas fa-user-circle me-2"></i>Profile</a></li>
                                <li><a class="dropdown-item" href="accounts.php"><i class="fas fa-wallet me-2"></i>Accounts</a></li>
                                <li><a class="dropdown-item" href="transfers.php"><i class="fas fa-exchange-alt me-2"></i>Transfers</a></li>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item" href="logout.php"><i class="fas fa-sign-out-alt me-2"></i>Logout</a></li>
                            </ul>
                        </div>
                    <?php else: ?>
                        <a href="login.php" class="btn btn-outline-primary me-2">
                            <i class="fas fa-sign-in-alt me-1"></i> Login
                        </a>
                        <a href="register.php" class="btn btn-primary">
                            <i class="fas fa-user-plus me-1"></i> Register
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </nav>

    <!-- Main Content Wrapper -->
    <main class="main-content">
        <?php 
        // Display messages if any
        $message = get_message();
        if ($message): 
        ?>
        <div class="alert alert-<?= $message['type'] == 'error' ? 'danger' : $message['type'] ?> alert-dismissible fade show" role="alert">
            <?= htmlspecialchars($message['text']) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
        <?php endif; ?>