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
  <style>
        /* Modern Classic Navbar with Transparency */
        .navbar {
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            padding: 0.6rem 0;
            transition: all 0.3s ease;
            z-index: 1030; /* Ensure navbar stays on top */
        }

        .navbar.scrolled {
            background: rgba(13, 36, 69, 0.98);
            box-shadow: 0 2px 15px rgba(0, 0, 0, 0.1);
        }

        .navbar-brand img {
            transition: transform 0.3s ease;
            /* filter: brightness(0) invert(1);  */
        }

        .navbar-brand:hover img {
            transform: scale(1.05);
        }

        .navbar-brand {
            background: rgba(255, 255, 255, 0.15);
            padding: 4px 8px;
            border-radius: 4px;
            margin-right: 10px;
            <!-- border: 4px solid rgba(231, 3, 128, 0.91); -->
            <!-- display: flex !important; -->
            <!-- align-items: center !important; -->
            <!-- justify-content: center !important; -->
            <!-- height: 100% !important; -->
        }

        .navbar-brand img {
            transition: transform 0.3s ease;
            filter: none !important;
            <!-- border: 4px solid rgba(231, 3, 3, 0.91); -->
            <!-- display: block; -->
            <!-- margin: 0 auto; -->
        }

        /* Navigation Links */
        .navbar-nav .nav-link {
            color: rgba(255, 255, 255, 0.9) !important;
            font-weight: 500;
            padding: 0.5rem 1rem;
            margin: 0 0.1rem;
            transition: all 0.2s ease;
            font-size: 0.95rem;
            outline: none !important;
            box-shadow: none !important;
        }

        .navbar-nav .nav-link:hover,
        .navbar-nav .nav-link.active {
            color: var(--white) !important;
            background-color: rgba(255, 215, 0, 0.1);
        }

        /* Remove focus styles from dropdown toggles */
        .navbar-nav .dropdown-toggle {
            outline: none !important;
            box-shadow: none !important;
        }

        /* Dropdown Container - Hover Activation for Desktop Only */
        @media (min-width: 992px) {
            .nav-item.dropdown:hover .dropdown-menu {
                display: block;
                opacity: 1;
                visibility: visible;
                transform: translateY(0);
            }

            /* Dropdown Menu Styling */
            .dropdown-menu {
                background: rgba(255, 255, 255, 0.98);
                backdrop-filter: blur(15px);
                -webkit-backdrop-filter: blur(15px);
                border: 1px solid rgba(255, 255, 255, 0.2);
                border-radius: 8px;
                padding: 0.5rem 0;
                margin-top: 0.5rem;
                box-shadow: 0 10px 30px rgba(0, 0, 0, 0.15);
                opacity: 0;
                visibility: hidden;
                transform: translateY(10px);
                transition: all 0.3s ease;
                display: block;
            }
        }

        /* Default dropdown styling for mobile */
        @media (max-width: 991px) {
            .dropdown-menu {
                background: rgba(13, 36, 69, 0.95);
                border: none;
                border-radius: 0;
                padding-left: 1rem;
                margin-top: 0;
                box-shadow: none;
                opacity: 1;
                visibility: visible;
                transform: none;
                transition: none;
            }
        }

        .dropdown-item {
            padding: 0.6rem 1.2rem;
            color: var(--dark-gray);
            transition: all 0.2s ease;
            font-size: 0.9rem;
            border-left: 3px solid transparent;
        }

        .dropdown-item:hover {
            background-color: rgba(255, 215, 0, 0.08);
            color: var(--navy-blue);
            border-left-color: var(--primary-gold);
            padding-left: 1.5rem;
        }

        .dropdown-item i {
            color: var(--primary-gold);
            width: 18px;
            font-size: 0.85rem;
        }

        .dropdown-divider {
            margin: 0.3rem 0;
            opacity: 0.3;
        }

        /* Auth Buttons - Refined */
        .navbar .btn-outline-primary {
            color: var(--white);
            border-color: rgba(255, 215, 0, 0.6);
            background: transparent;
            font-weight: 500;
            font-size: 0.9rem;
            padding: 0.4rem 0.8rem;
        }

        .navbar .btn-outline-primary:hover {
            background-color: var(--primary-gold);
            color: var(--navy-blue);
            border-color: var(--primary-gold);
        }

        .navbar .btn-primary {
            background-color: var(--primary-gold);
            border-color: var(--primary-gold);
            color: var(--navy-blue);
            font-weight: 600;
            font-size: 0.9rem;
            padding: 0.4rem 0.8rem;
        }

        .navbar .btn-primary:hover {
            background-color: var(--dark-gold);
            border-color: var(--dark-gold);
            transform: translateY(-1px);
        }

        /* Mobile Responsive */
        .navbar-toggler {
            border: 1px solid rgba(255, 255, 255, 0.3);
            padding: 0.25rem 0.5rem;
        }

        .navbar-toggler:focus {
            box-shadow: none !important;
            outline: none !important;
        }

        .navbar-toggler-icon {
            background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 30 30'%3e%3cpath stroke='rgba%28255, 255, 255, 0.9%29' stroke-linecap='round' stroke-miterlimit='10' stroke-width='2' d='M4 7h22M4 15h22M4 23h22'/%3e%3c/svg%3e");
            width: 1.2em;
            height: 1.2em;
        }

        /* Mobile dropdown adjustments */
        @media (max-width: 991px) {
            .navbar-nav {
                padding: 0.5rem 0;
            }
            
            .navbar-nav .nav-link {
                margin: 0.1rem 0;
                padding: 0.6rem 1rem;
            }
            
            .dropdown-item {
                color: rgba(255, 255, 255, 0.8);
                border-left: 2px solid rgba(255, 215, 0, 0.3);
            }
            
            .dropdown-item:hover {
                color: var(--white);
                background-color: rgba(255, 255, 255, 0.1);
            }
            
            /* Ensure dropdowns work properly on mobile */
            .dropdown-menu {
                position: static;
                float: none;
                width: auto;
            }
            
            /* Auth buttons stack on mobile */
            .navbar .d-flex {
                flex-direction: column;
                width: 100%;
                margin-top: 1rem;
            }
            
            .navbar .btn {
                width: 100%;
                margin: 0.25rem 0;
            }
            
            .navbar .btn-outline-primary {
                margin-right: 0 !important;
            }
        }

        /* Small screen adjustments */
        @media (max-width: 576px) {
            .navbar .btn {
                font-size: 0.8rem;
                padding: 0.35rem 0.7rem;
            }
            
            .navbar-brand img {
                height: 32px;
            }
            
            .container {
                padding-left: 15px;
                padding-right: 15px;
            }
        }
    </style>
</head>
<body>
    <!-- Refined Modern Classic Navbar -->
    <nav class="navbar navbar-expand-lg navbar-dark fixed-top">
        <div class="container">
            <a class="navbar-brand d-flex align-items-center" href="index.php">
                <img src="assets/images/gcc-logo.webp" alt="GCC Bank Logo" height="38" class="me-2">
            </a>
            
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav mx-auto mb-2 mb-lg-0"> <!-- Added mb-2 mb-lg-0 for better mobile spacing -->
                    <li class="nav-item">
                        <a class="nav-link active" href="index.php">Home</a>
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
                    <a href="login.php" class="btn btn-outline-primary me-2">
                        <i class="fas fa-sign-in-alt me-1"></i> Login
                    </a>
                    <a href="register.php" class="btn btn-primary">
                        <i class="fas fa-user-plus me-1"></i> Register
                    </a>
                </div>
            </div>
        </div>
    </nav>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const navbar = document.querySelector('.navbar');
            
            // Navbar scroll effect
            window.addEventListener('scroll', function() {
                if (window.scrollY > 30) {
                    navbar.classList.add('scrolled');
                } else {
                    navbar.classList.remove('scrolled');
                }
            });

            // Enhanced hover dropdown for desktop only
            function initHoverDropdowns() {
                const dropdowns = document.querySelectorAll('.nav-item.dropdown');
                
                dropdowns.forEach(dropdown => {
                    const link = dropdown.querySelector('.dropdown-toggle');
                    const menu = dropdown.querySelector('.dropdown-menu');
                    
                    // Only apply hover effects on desktop
                    if (window.innerWidth > 991) {
                        // Show on hover
                        dropdown.addEventListener('mouseenter', function() {
                            const bootstrapDropdown = bootstrap.Dropdown.getInstance(link);
                            if (!bootstrapDropdown) {
                                new bootstrap.Dropdown(link).show();
                            } else {
                                bootstrapDropdown.show();
                            }
                        });
                        
                        // Hide with delay to prevent accidental close
                        dropdown.addEventListener('mouseleave', function() {
                            setTimeout(() => {
                                const bootstrapDropdown = bootstrap.Dropdown.getInstance(link);
                                if (bootstrapDropdown) {
                                    bootstrapDropdown.hide();
                                }
                            }, 200);
                        });
                    }
                });
            }

            // Initialize hover dropdowns
            if (typeof bootstrap !== 'undefined') {
                initHoverDropdowns();
                
                // Reinitialize on window resize
                window.addEventListener('resize', function() {
                    initHoverDropdowns();
                });
            }
        });
    </script>
</body>
</html>