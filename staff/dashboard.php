<?php
session_start();
include '../conn.php';
include 'number_fomt.php';
include 'strength.php';

if(!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true){
    $_SESSION["status"]="Please login your account here";
    $_SESSION["code"]="warning";
    header("location: index.php");
    exit;
}

// Get security notifications
$security_alerts = [];
$security_query = "SELECT COUNT(*) as count FROM security_logs 
                  WHERE severity IN ('WARNING', 'ERROR', 'CRITICAL') 
                  AND created_at >= NOW() - INTERVAL 24 HOUR";
$result = mysqli_query($con, $security_query);
$security_data = mysqli_fetch_assoc($result);
$security_alerts_count = $security_data ? $security_data['count'] : 0;

// Get recent suspicious activities
$suspicious_query = "SELECT event_type, created_at FROM security_logs 
                    WHERE severity IN ('ERROR', 'CRITICAL') 
                    ORDER BY created_at DESC LIMIT 5";
$suspicious_result = mysqli_query($con, $suspicious_query);
if($suspicious_result) {
    while($row = mysqli_fetch_assoc($suspicious_result)) {
        $security_alerts[] = $row;
    }
}

// Get system status
$system_status = 'normal';
$system_message = 'All systems operational';

// Check for any critical issues
$critical_issues = mysqli_query($con, "SELECT COUNT(*) as count FROM security_logs 
                                      WHERE severity = 'CRITICAL' 
                                      AND created_at >= NOW() - INTERVAL 1 HOUR");
if($critical_issues) {
    $critical_count = mysqli_fetch_assoc($critical_issues)['count'];
    if($critical_count > 0) {
        $system_status = 'critical';
        $system_message = "$critical_count critical issue(s) detected in the last hour";
    }
}

// Get user's recent activity
$user_activity = [];
if(isset($_SESSION['id'])) {
    $activity_query = "SELECT event_type, created_at FROM security_logs 
                      WHERE session_id = '" . session_id() . "' 
                      ORDER BY created_at DESC LIMIT 5";
    $activity_result = mysqli_query($con, $activity_query);
    if($activity_result) {
        while($row = mysqli_fetch_assoc($activity_result)) {
            $user_activity[] = $row;
        }
    }
}

// Get transaction stats
$transaction_stats = [
    'total' => 0,
    'withdrawals' => 0,
    'deposits' => 0
];

$total_transactions = mysqli_query($con, "SELECT COUNT(*) as total FROM account_history");
if($total_transactions) {
    $transaction_stats['total'] = mysqli_fetch_assoc($total_transactions)['total'];
}

$withdrawal_stats = mysqli_query($con, "SELECT COUNT(*) as total FROM account_history WHERE type = 3");
if($withdrawal_stats) {
    $transaction_stats['withdrawals'] = mysqli_fetch_assoc($withdrawal_stats)['total'];
}

$deposit_stats = mysqli_query($con, "SELECT COUNT(*) as total FROM account_history WHERE type = 4");
if($deposit_stats) {
    $transaction_stats['deposits'] = mysqli_fetch_assoc($deposit_stats)['total'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=Edge">
    <meta content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no" name="viewport">
    <title>Gold Coast Central Bank - Dashboard</title>
    
    <!-- Favicon -->
    <link rel="icon" href="images/icc.ico" type="image/x-icon">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Modern CSS Framework -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- DataTables -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
    
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
    <!-- Custom CSS -->
    <style>
        :root {
            /* Dark Theme Base */
            --dark-primary: #1a1f2c;
            --dark-secondary: #242b3d;
            --dark-tertiary: #2f3648;
            --dark-bg: #0f131c;
            --dark-surface: #1a1f2c;
            --dark-elevated: #242b3d;
            
            /* Gold Accents */
            --gold-primary: #d4af37;
            --gold-secondary: #b8941f;
            --gold-light: #f4e4a6;
            --gold-dark: #9d7c0d;
            
            /* Enhanced Color System */
            --primary: #3b82f6;
            --primary-dark: #1e40af;
            --secondary: #10b981;
            --secondary-dark: #059669;
            --accent: #06b6d4;
            --accent-dark: #0891b2;
            --success: #22c55e;
            --warning: #eab308;
            --danger: #ef4444;
            --info: #3b82f6;
            
            /* Text Colors */
            --text-primary: #f1f5f9;
            --text-secondary: #cbd5e1;
            --text-muted: #94a3b8;
            --text-dim: #64748b;
            --text-white: #ffffff;
            
            /* Status Colors */
            --status-normal: var(--success);
            --status-warning: var(--warning);
            --status-critical: var(--danger);
            
            /* Borders and Shadows */
            --border-light: rgba(255,255,255,0.1);
            --border-medium: rgba(255,255,255,0.15);
            --shadow-sm: 0 2px 4px rgba(0,0,0,0.2);
            --shadow-md: 0 4px 8px rgba(0,0,0,0.25);
            --shadow-lg: 0 8px 16px rgba(0,0,0,0.3);
            --shadow-xl: 0 12px 24px rgba(0,0,0,0.35);
            --shadow-gold: 0 4px 20px rgba(212,175,55,0.2);
            
            /* Animation */
            --transition-speed: 0.3s;
        }
        
        body {
            font-family: 'Inter', sans-serif;
            background-color: var(--dark-bg);
            color: var(--text-primary);
            overflow-x: hidden;
            line-height: 1.6;
        }
        
        /* System Status Bar */
        .system-status {
            background: var(--dark-surface);
            padding: 8px 0;
            border-bottom: 1px solid var(--border-light);
            font-size: 14px;
            position: sticky;
            top: 0;
            z-index: 1030;
        }
        
        .system-status.normal {
            background: rgba(34, 197, 94, 0.1);
            color: var(--status-normal);
            border-bottom-color: var(--status-normal);
        }
        
        .system-status.warning {
            background: rgba(234, 179, 8, 0.1);
            color: var(--status-warning);
            border-bottom-color: var(--status-warning);
        }
        
        .system-status.critical {
            background: rgba(239, 68, 68, 0.1);
            color: var(--status-critical);
            border-bottom-color: var(--status-critical);
            animation: pulse 2s infinite;
        }
        
        @keyframes pulse {
            0% { opacity: 1; }
            50% { opacity: 0.7; }
            100% { opacity: 1; }
        }
        
        /* Sidebar Styles */
        .sidebar {
            position: fixed;
            top: 33px;
            left: 0;
            height: calc(100% - 33px);
            width: 280px;
            background: linear-gradient(180deg, var(--dark-primary) 0%, var(--dark-surface) 100%);
            z-index: 1000;
            padding-top: 20px;
            transition: all var(--transition-speed) ease;
            box-shadow: var(--shadow-xl);
            border-right: 1px solid var(--border-light);
            overflow-y: auto;
        }
        
        .sidebar-brand {
            padding: 0 20px 20px;
            text-align: center;
            border-bottom: 1px solid var(--border-light);
            margin-bottom: 20px;
        }
        
        .sidebar-brand h2 {
            color: var(--gold-light);
            font-size: 24px;
            font-weight: 700;
            margin: 0;
            background: linear-gradient(45deg, var(--text-white), var(--gold-light));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        
        .sidebar-brand p {
            color: var(--text-muted);
            font-size: 14px;
            margin: 5px 0 0;
        }
        
        .user-info {
            padding: 0 20px 20px;
            text-align: center;
            border-bottom: 1px solid var(--border-light);
            margin-bottom: 20px;
        }
        
        .user-img {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid var(--gold-primary);
            margin: 0 auto 15px;
            box-shadow: var(--shadow-gold);
            transition: all var(--transition-speed) ease;
        }
        
        .user-img:hover {
            transform: scale(1.05);
            box-shadow: 0 0 20px rgba(212, 175, 55, 0.3);
        }
        
        .user-name {
            color: var(--text-primary);
            font-weight: 600;
            margin-bottom: 5px;
        }
        
        .user-email {
            color: var(--text-muted);
            font-size: 14px;
        }
        
        .user-role {
            display: inline-block;
            padding: 3px 8px;
            background: rgba(212, 175, 55, 0.2);
            color: var(--gold-primary);
            border-radius: 12px;
            font-size: 12px;
            margin-top: 5px;
        }
        
        .sidebar-menu {
            list-style: none;
            padding: 0;
            margin: 0;
        }
        
        .sidebar-menu li {
            margin-bottom: 5px;
        }
        
        .sidebar-menu a {
            display: flex;
            align-items: center;
            padding: 12px 20px;
            color: var(--text-secondary);
            text-decoration: none;
            transition: all var(--transition-speed);
            border-left: 3px solid transparent;
            position: relative;
            overflow: hidden;
        }
        
        .sidebar-menu a:before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.05), transparent);
            transition: all 0.5s;
        }
        
        .sidebar-menu a:hover:before, 
        .sidebar-menu a.active:before {
            left: 100%;
        }
        
        .sidebar-menu a:hover, 
        .sidebar-menu a.active {
            background: rgba(255, 255, 255, 0.05);
            color: var(--text-primary);
            border-left: 3px solid var(--gold-primary);
        }
        
        .sidebar-menu i {
            margin-right: 12px;
            font-size: 18px;
            width: 24px;
            text-align: center;
            color: var(--gold-primary);
        }
        
        .sub-menu {
            list-style: none;
            padding-left: 30px;
            margin: 5px 0;
            display: none;
        }
        
        .sub-menu.active {
            display: block;
            animation: fadeIn 0.3s ease;
        }
        
        .sub-menu a {
            padding: 10px 15px;
            font-size: 14px;
            color: var(--text-muted);
        }
        
        .sub-menu a:hover {
            color: var(--text-primary);
        }
        
        .menu-toggle {
            position: relative;
        }
        
        .menu-toggle::after {
            content: '\f107';
            font-family: 'Font Awesome 6 Free';
            font-weight: 900;
            position: absolute;
            right: 20px;
            transition: transform var(--transition-speed);
            color: var(--text-muted);
        }
        
        .menu-toggle.active::after {
            transform: rotate(180deg);
        }
        
        /* Main Content */
        .main-content {
            margin-left: 280px;
            padding: 20px;
            transition: all var(--transition-speed);
            background-color: var(--dark-bg);
            min-height: 100vh;
            padding-top: 53px; /* Account for system status bar */
        }
        
        /* Header */
        .top-header {
            background: var(--dark-surface);
            padding: 15px 25px;
            border-radius: 12px;
            box-shadow: var(--shadow-md);
            margin-bottom: 25px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border: 1px solid var(--border-light);
            transition: all var(--transition-speed) ease;
        }
        
        .top-header:hover {
            box-shadow: var(--shadow-lg);
        }
        
        .page-title h1 {
            font-size: 24px;
            font-weight: 700;
            color: var(--text-primary);
            margin: 0;
        }
        
        .page-title p {
            color: var(--text-muted);
            margin: 5px 0 0;
            font-size: 14px;
        }
        
        .header-actions {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .header-actions a {
            color: var(--text-secondary);
            text-decoration: none;
            font-weight: 500;
            transition: all var(--transition-speed) ease;
            position: relative;
            display: flex;
            align-items: center;
        }
        
        .header-actions a:hover {
            color: var(--gold-primary);
            transform: translateY(-2px);
        }
        
        .header-actions a .badge {
            position: absolute;
            top: -8px;
            right: -8px;
            background: var(--danger);
            color: var(--text-white);
            border-radius: 50%;
            width: 18px;
            height: 18px;
            font-size: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        /* Quick Actions */
        .quick-actions {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 25px;
        }
        
        .quick-action-btn {
            background: var(--dark-surface);
            border: 1px solid var(--border-light);
            border-radius: 12px;
            padding: 15px;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            text-align: center;
            transition: all var(--transition-speed) ease;
            cursor: pointer;
            text-decoration: none;
            color: var(--text-secondary);
        }
        
        .quick-action-btn:hover {
            transform: translateY(-3px);
            box-shadow: var(--shadow-md);
            border-color: var(--gold-primary);
            color: var(--text-primary);
        }
        
        .quick-action-btn i {
            font-size: 24px;
            margin-bottom: 10px;
            color: var(--gold-primary);
        }
        
        .quick-action-btn span {
            font-size: 14px;
            font-weight: 500;
        }
        
        /* Stats Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 20px;
            margin-bottom: 25px;
        }
        
        .stat-card {
            background: var(--dark-surface);
            border-radius: 16px;
            padding: 24px;
            box-shadow: var(--shadow-md);
            transition: all var(--transition-speed) ease;
            border: 1px solid var(--border-light);
            position: relative;
            overflow: hidden;
        }
        
        .stat-card:after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            width: 100%;
            height: 4px;
            background: linear-gradient(90deg, var(--gold-primary), var(--gold-dark));
            transform: scaleX(0);
            transform-origin: left;
            transition: transform var(--transition-speed) ease;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: var(--shadow-lg);
        }
        
        .stat-card:hover:after {
            transform: scaleX(1);
        }
        
        .stat-icon {
            width: 56px;
            height: 56px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 15px;
            font-size: 22px;
            transition: all var(--transition-speed) ease;
            background: rgba(255,255,255,0.05);
            color: var(--gold-primary);
        }
        
        .stat-card:hover .stat-icon {
            transform: scale(1.1);
            background: rgba(212,175,55,0.1);
        }
        
        .stat-title {
            font-size: 14px;
            color: var(--text-muted);
            margin-bottom: 8px;
            font-weight: 500;
        }
        
        .stat-value {
            font-size: 26px;
            font-weight: 700;
            color: var(--text-primary);
            margin-bottom: 0;
            transition: all var(--transition-speed) ease;
        }
        
        .stat-card:hover .stat-value {
            color: var(--gold-primary);
        }
        
        .stat-card a {
            color: var(--text-muted);
            font-size: 13px;
            font-weight: 500;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            transition: all var(--transition-speed) ease;
        }
        
        .stat-card a:hover {
            color: var(--gold-primary);
            transform: translateX(3px);
        }
        
        /* Charts */
        .chart-container {
            background: var(--dark-surface);
            border-radius: 16px;
            padding: 25px;
            box-shadow: var(--shadow-md);
            margin-bottom: 25px;
            border: 1px solid var(--border-light);
            transition: all var(--transition-speed) ease;
            height: 350px;
        }
        
        .chart-container:hover {
            box-shadow: var(--shadow-lg);
        }
        
        /* Data Tables */
        .data-card {
            background: var(--dark-surface);
            border-radius: 16px;
            padding: 25px;
            box-shadow: var(--shadow-md);
            margin-bottom: 25px;
            border: 1px solid var(--border-light);
            transition: all var(--transition-speed) ease;
        }
        
        .data-card:hover {
            box-shadow: var(--shadow-lg);
        }
        
        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 1px solid var(--border-light);
        }
        
        .card-title {
            font-size: 18px;
            font-weight: 600;
            color: var(--text-primary);
            margin: 0;
            position: relative;
            padding-left: 15px;
        }
        
        .card-title:before {
            content: '';
            position: absolute;
            left: 0;
            top: 50%;
            transform: translateY(-50%);
            height: 20px;
            width: 4px;
            background: var(--gold-primary);
            border-radius: 2px;
        }
        
        .table {
            color: var(--text-secondary);
        }
        
        .table th {
            background-color: var(--dark-elevated);
            color: var(--text-primary);
            font-weight: 600;
            padding: 12px 15px;
            border: none;
            border-bottom: 1px solid var(--border-light);
        }
        
        .table td {
            padding: 12px 15px;
            vertical-align: middle;
            border-color: var(--border-light);
        }
        
        .table-hover tbody tr {
            transition: all var(--transition-speed) ease;
        }
        
        .table-hover tbody tr:hover {
            background-color: rgba(255,255,255,0.05);
        }
        
        .badge {
            background-color: rgba(255,255,255,0.1) !important;
            color: var(--text-primary) !important;
            border: 1px solid var(--border-medium);
        }
        
        .bg-primary { background-color: var(--primary-dark) !important; }
        .bg-success { background-color: var(--secondary-dark) !important; }
        .bg-warning { background-color: rgba(234,179,8,0.2) !important; }
        .bg-danger { background-color: rgba(239,68,68,0.2) !important; }
        .bg-info { background-color: rgba(59,130,246,0.2) !important; }
        
        /* Security Alert */
        .security-alert {
            background: rgba(239, 68, 68, 0.1);
            border: 1px solid rgba(239, 68, 68, 0.3);
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
        }
        
        .security-alert i {
            color: var(--danger);
            font-size: 20px;
            margin-right: 15px;
        }
        
        .security-alert-content {
            flex: 1;
        }
        
        .security-alert h5 {
            margin: 0 0 5px 0;
            color: var(--danger);
            font-size: 16px;
        }
        
        .security-alert p {
            margin: 0;
            color: var(--text-secondary);
            font-size: 14px;
        }
        
        /* DataTables Pagination Styling */
        .dataTables_wrapper {
            margin-top: 15px;
            color: var(--text-muted);
        }
        
        .dataTables_paginate {
            margin-top: 20px !important;
        }
        
        .dataTables_paginate .paginate_button {
            padding: 6px 12px;
            margin: 0 3px;
            border: 1px solid var(--border-light);
            border-radius: 6px;
            color: var(--text-secondary) !important;
            background: var(--dark-elevated) !important;
            transition: all var(--transition-speed) ease;
            text-decoration: none !important;
        }
        
        .dataTables_paginate .paginate_button:hover {
            background: var(--gold-primary) !important;
            color: var(--dark-primary) !important;
            border-color: var(--gold-primary);
            transform: translateY(-2px);
        }
        
        .dataTables_paginate .paginate_button.current {
            background: var(--gold-primary) !important;
            color: var(--dark-primary) !important;
            border-color: var(--gold-primary);
        }
        
        .dataTables_paginate .paginate_button.disabled,
        .dataTables_paginate .paginate_button.disabled:hover {
            background: var(--dark-secondary) !important;
            color: var(--text-dim) !important;
            cursor: not-allowed;
            transform: none;
        }
        
        .dataTables_info {
            color: var(--text-muted);
            padding-top: 12px !important;
        }
        
        .dataTables_length select,
        .dataTables_filter input {
            background: var(--dark-elevated);
            border: 1px solid var(--border-light);
            color: var(--text-primary);
            border-radius: 6px;
            padding: 5px 10px;
        }
        
        /* Footer */
        .footer {
            background: var(--dark-primary);
            color: var(--text-secondary);
            padding: 40px 0;
            margin-top: 40px;
            border-top: 1px solid var(--border-light);
        }
        
        .footer h5 {
            font-size: 18px;
            font-weight: 600;
            margin-bottom: 20px;
            color: var(--gold-primary);
        }
        
        .footer-links {
            list-style: none;
            padding: 0;
        }
        
        .footer-links li {
            margin-bottom: 10px;
        }
        
        .footer-links a {
            color: var(--text-secondary);
            text-decoration: none;
            transition: all var(--transition-speed);
            display: inline-flex;
            align-items: center;
        }
        
        .footer-links a i {
            margin-right: 8px;
            font-size: 14px;
        }
        
        .footer-links a:hover {
            color: var(--gold-primary);
            transform: translateX(5px);
        }
        
        .social-links a {
            margin-right: 15px;
            color: var(--text-muted);
            font-size: 18px;
            transition: all var(--transition-speed);
        }
        
        .social-links a:hover {
            color: var(--gold-primary);
            transform: translateY(-3px);
        }
        
        .copyright {
            border-top: 1px solid var(--border-light);
            padding-top: 20px;
            margin-top: 20px;
            text-align: center;
            color: var(--text-muted);
        }
        
        /* Dropdowns */
        .dropdown-menu {
            background: var(--dark-surface);
            border: 1px solid var(--border-light);
            box-shadow: var(--shadow-lg);
        }
        
        .dropdown-item {
            color: var(--text-secondary);
        }
        
        .dropdown-item:hover {
            background: rgba(255,255,255,0.05);
            color: var(--text-primary);
        }
        
        /* Buttons */
        .btn-outline-primary {
            border-color: var(--border-medium);
            color: var(--text-secondary);
        }
        
        .btn-outline-primary:hover {
            background: var(--gold-primary);
            border-color: var(--gold-primary);
            color: var(--dark-primary);
        }
        
        /* Animations */
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        /* Mobile Responsive */
        @media (max-width: 992px) {
            .sidebar {
                left: -280px;
                transition: left var(--transition-speed) ease;
            }
            
            .sidebar.active {
                left: 0;
            }
            
            .main-content {
                margin-left: 0;
            }
            
            #sidebarToggle {
                display: block !important;
            }
            
            .stats-grid {
                grid-template-columns: 1fr;
            }
            
            .quick-actions {
                grid-template-columns: repeat(2, 1fr);
            }
        }
        
        @media (max-width: 576px) {
            .quick-actions {
                grid-template-columns: 1fr;
            }
            
            .header-actions {
                gap: 10px;
            }
        }
    </style>
</head>
<body>
    <!-- System Status Bar -->
    <div class="system-status <?php echo $system_status; ?>">
        <div class="container d-flex justify-content-between align-items-center">
            <div>
                <i class="fas fa-circle me-2"></i>
                <span>System Status: <?php echo ucfirst($system_status); ?> - <?php echo $system_message; ?></span>
            </div>
            <div>
                <small>Last updated: <?php echo date('Y-m-d H:i:s'); ?></small>
            </div>
        </div>
    </div>

    <!-- Sidebar -->
    <nav class="sidebar" id="sidebar">
        <div class="sidebar-brand">
            <h2>Gold Coast Central Bank</h2>
            <p>Secure Banking Solutions</p>
        </div>
        
        <div class="user-info">
            <?php echo '<img src="data:image/jpeg;base64,'.base64_encode($_SESSION['img']).'" class="user-img" alt="User"/>' ?>
            <div class="user-name"><?php echo $_SESSION['name'];?></div>
            <div class="user-email"><?php echo $_SESSION['email'];?></div>
            <div class="user-role"><?php echo $_SESSION['type'];?></div>
        </div>
        
        <ul class="sidebar-menu">
            <li><a href="dashboard.php" class="active"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
            <li><a href="#" class="menu-toggle"><i class="fas fa-users"></i> Employees</a>
                <ul class="sub-menu">
                    <li><a href="employee/emp_details.php?type=Admin">Admins</a></li>
                    <li><a href="employee/emp_details.php?type=Employee">Employees</a></li>
                </ul>
            </li>
            <li><a href="#" class="menu-toggle"><i class="fas fa-piggy-bank"></i> Accounts</a>
                <ul class="sub-menu">
                    <li><a href="employee/account_details.php?id=Current">Current Accounts</a></li>
                    <li><a href="employee/account_details.php?id=Saving">Saving Accounts</a></li>
                    <li><a href="employee/search.php">Search Accounts</a></li>
                </ul>
            </li>
            <li><a href="employee/bank_balance.php"><i class="fas fa-landmark"></i> Bank Balance</a></li>
            <li><a href="#" class="menu-toggle"><i class="fas fa-history"></i> History</a>
                <ul class="sub-menu">
                    <li><a href="employee/history_details.php?ty=Withdraw">Withdrawals</a></li>
                    <li><a href="employee/history_details.php?ty=Deposit">Deposits</a></li>
                    <li><a href="employee/history_details.php?ty=Transection">Transactions</a></li>
                </ul>
            </li>
            <li><a href="security_logs.php"><i class="fas fa-shield-alt"></i> Security Center</a></li>
            <li><a href="reports.php"><i class="fas fa-chart-bar"></i> Reports & Analytics</a></li>
            <li><a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
        </ul>
    </nav>

    <!-- Main Content -->
    <div class="main-content">
        <!-- Header -->
        <header class="top-header">
            <div class="page-title">
                <h1>Dashboard</h1>
                <p>Overview of banking operations - <?php echo date('l, F j, Y'); ?></p>
            </div>
            <div class="header-actions">
                <a href="#" id="sidebarToggle"><i class="fas fa-bars"></i></a>
                <a href="notifications.php"><i class="fas fa-bell"></i> 
                    <?php if($security_alerts_count > 0): ?>
                    <span class="badge"><?php echo $security_alerts_count; ?></span>
                    <?php endif; ?>
                </a>
                <a href="profile.php"><i class="fas fa-user-cog"></i></a>
                <a href="logout.php" title="Logout"><i class="fas fa-sign-out-alt"></i></a>
            </div>
        </header>

        <!-- Quick Actions -->
        <div class="quick-actions">
            <a href="employee/search.php" class="quick-action-btn">
                <i class="fas fa-search"></i>
                <span>Search Accounts</span>
            </a>
            <a href="employee/account_create.php" class="quick-action-btn">
                <i class="fas fa-plus-circle"></i>
                <span>Create Account</span>
            </a>
            <a href="transactions.php" class="quick-action-btn">
                <i class="fas fa-exchange-alt"></i>
                <span>New Transaction</span>
            </a>
            <a href="reports.php" class="quick-action-btn">
                <i class="fas fa-chart-pie"></i>
                <span>Generate Report</span>
            </a>
        </div>

        <!-- Security Alert -->
        <?php if($security_alerts_count > 0): ?>
        <div class="security-alert">
            <i class="fas fa-exclamation-triangle"></i>
            <div class="security-alert-content">
                <h5>Security Alert</h5>
                <p><?php echo $security_alerts_count; ?> security issue(s) detected in the last 24 hours. <a href="security_logs.php" style="color: var(--danger); text-decoration: underline;">Review now</a></p>
            </div>
        </div>
        <?php endif; ?>

        <!-- Stats Grid -->
        <div class="stats-grid">
            <?php if($_SESSION["type"]=="Admin" || $_SESSION["type"]=="Default"){ ?>
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-users"></i>
                </div>
                <p class="stat-title">TOTAL ACCOUNTS</p>
                <h3 class="stat-value"><?php echo r_format("SELECT count(account) as total FROM accounts_info") ?></h3>
                <a href="employee/search.php" class="small">View Details <i class="fas fa-arrow-right"></i></a>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-user-tie"></i>
                </div>
                <p class="stat-title">TOTAL ADMINS</p>
                <h3 class="stat-value"><?php echo r_format("SELECT count(role) as total FROM users WHERE role='Admin'") ?></h3>
                <a href="employee/emp_details.php?type=Admin" class="small">View Details <i class="fas fa-arrow-right"></i></a>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-user-friends"></i>
                </div>
                <p class="stat-title">TOTAL EMPLOYEES</p>
                <h3 class="stat-value"><?php echo r_format("SELECT count(role) as total FROM users WHERE role='Employee'") ?></h3>
                <a href="employee/emp_details.php?type=Employee" class="small">View Details <i class="fas fa-arrow-right"></i></a>
            </div>
            <?php } ?>
            
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-building"></i>
                </div>
                <p class="stat-title">CURRENT ACCOUNTS</p>
                <h3 class="stat-value"><?php echo r_format("SELECT count(account_type) as total FROM accounts_info where account_type='Current'") ?></h3>
                <a href="employee/account_details.php?id=Current" class="small">View Details <i class="fas fa-arrow-right"></i></a>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-piggy-bank"></i>
                </div>
                <p class="stat-title">SAVING ACCOUNTS</p>
                <h3 class="stat-value"><?php echo r_format("SELECT count(account_type) as total FROM accounts_info where account_type='Saving'") ?></h3>
                <a href="employee/account_details.php?id=Saving" class="small">View Details <i class="fas fa-arrow-right"></i></a>
            </div>
            
            <?php if($_SESSION["type"]=="Admin" || $_SESSION["type"]=="Default" || $_SESSION["type"]=="Employee"){ ?>
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-money-bill-wave"></i>
                </div>
                <p class="stat-title">TOTAL TRANSACTIONS</p>
                <h3 class="stat-value"><?php echo $transaction_stats['total']; ?></h3>
                <a href="employee/history_details.php?ty=Transection" class="small">View Details <i class="fas fa-arrow-right"></i></a>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-hand-holding-usd"></i>
                </div>
                <p class="stat-title">TOTAL WITHDRAWALS</p>
                <h3 class="stat-value"><?php echo $transaction_stats['withdrawals']; ?></h3>
                <a href="employee/history_details.php?ty=Withdraw" class="small">View Details <i class="fas fa-arrow-right"></i></a>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-money-check"></i>
                </div>
                <p class="stat-title">TOTAL DEPOSITS</p>
                <h3 class="stat-value"><?php echo $transaction_stats['deposits']; ?></h3>
                <a href="employee/history_details.php?ty=Deposit" class="small">View Details <i class="fas fa-arrow-right"></i></a>
            </div>
            <?php } ?>
        </div>

        <!-- Charts Section -->
        <div class="row">
            <div class="col-lg-8">
                <div class="chart-container">
                    <div class="card-header">
                        <h3 class="card-title">Transaction Overview</h3>
                        <div class="dropdown">
                            <button class="btn btn-sm btn-outline-primary dropdown-toggle" type="button" id="chartDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                                Last 7 Days
                            </button>
                            <ul class="dropdown-menu" aria-labelledby="chartDropdown">
                                <li><a class="dropdown-item" href="#">Last 7 Days</a></li>
                                <li><a class="dropdown-item" href="#">Last 30 Days</a></li>
                                <li><a class="dropdown-item" href="#">Last 90 Days</a></li>
                            </ul>
                        </div>
                    </div>
                    <canvas id="transactionChart"></canvas>
                </div>
            </div>
            <div class="col-lg-4">
                <div class="chart-container">
                    <div class="card-header">
                        <h3 class="card-title">Account Distribution</h3>
                    </div>
                    <canvas id="accountDistributionChart"></canvas>
                </div>
            </div>
        </div>

        <!-- Recent Transactions -->
        <div class="data-card">
            <div class="card-header">
                <h3 class="card-title">Recent Transactions</h3>
                <a href="employee/history_details.php?ty=Transection" class="btn btn-sm btn-outline-primary">View All</a>
            </div>
            <div class="table-responsive">
                <table class="table table-hover" id="recentTransactions">
                    <thead>
                        <tr>
                            <th>Transaction ID</th>
                            <th>Account</th>
                            <th>Type</th>
                            <th>Amount</th>
                            <th>Date</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $query = "SELECT h.*, t.name as type_name 
                                  FROM account_history h 
                                  JOIN transaction_types t ON h.type = t.id 
                                  ORDER BY h.no DESC LIMIT 10";
                        $result = mysqli_query($con, $query);
                        if($result && mysqli_num_rows($result) > 0) {
                            while($row = mysqli_fetch_assoc($result)){
                                echo "<tr>";
                                echo "<td>".$row['no']."</td>";
                                echo "<td>".$row['account']."</td>";
                                echo "<td>".$row['type_name']."</td>";
                                echo "<td>$".number_format($row['amount'], 2)."</td>";
                                echo "<td>".$row['dt']."</td>";
                                echo "<td><span class='badge bg-success'>".$row['status']."</span></td>";
                                echo "</tr>";
                            }
                        } else {
                            echo "<tr><td colspan='6' class='text-center'>No transactions found</td></tr>";
                        }
                        ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Recent Security Events -->
        <?php if(!empty($security_alerts)): ?>
        <div class="data-card">
            <div class="card-header">
                <h3 class="card-title">Recent Security Events</h3>
                <a href="security_logs.php" class="btn btn-sm btn-outline-primary">View All</a>
            </div>
            <div class="table-responsive">
                <table class="table table-hover" id="securityEvents">
                    <thead>
                        <tr>
                            <th>Event Type</th>
                            <th>Description</th>
                            <th>Severity</th>
                            <th>Timestamp</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($security_alerts as $alert): ?>
                        <tr>
                            <td><?php echo $alert['event_type']; ?></td>
                            <td><?php echo $alert['event_type']; ?> detected</td>
                            <td><span class="badge bg-danger">Critical</span></td>
                            <td><?php echo $alert['created_at']; ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <!-- Footer -->
    <footer class="footer">
        <div class="container">
            <div class="row">
                <div class="col-md-4">
                    <h5>Gold Coast Central Bank</h5>
                    <p>Providing secure banking solutions since 1985. Your financial security is our top priority.</p>
                    <div class="social-links">
                        <a href="#"><i class="fab fa-facebook-f"></i></a>
                        <a href="#"><i class="fab fa-twitter"></i></a>
                        <a href="#"><i class="fab fa-linkedin-in"></i></a>
                        <a href="#"><i class="fab fa-instagram"></i></a>
                    </div>
                </div>
                <div class="col-md-2">
                    <h5>Quick Links</h5>
                    <ul class="footer-links">
                        <li><a href="#"><i class="fas fa-chevron-right"></i> About Us</a></li>
                        <li><a href="#"><i class="fas fa-chevron-right"></i> Services</a></li>
                        <li><a href="#"><i class="fas fa-chevron-right"></i> Contact</a></li>
                        <li><a href="#"><i class="fas fa-chevron-right"></i> Support</a></li>
                    </ul>
                </div>
                <div class="col-md-3">
                    <h5>Banking Services</h5>
                    <ul class="footer-links">
                        <li><a href="#"><i class="fas fa-chevron-right"></i> Personal Banking</a></li>
                        <li><a href="#"><i class="fas fa-chevron-right"></i> Business Banking</a></li>
                        <li><a href="#"><i class="fas fa-chevron-right"></i> Loans & Mortgages</a></li>
                        <li><a href="#"><i class="fas fa-chevron-right"></i> Investment Services</a></li>
                    </ul>
                </div>
                <div class="col-md-3">
                    <h5>Contact Info</h5>
                    <ul class="footer-links">
                        <li><i class="fas fa-map-marker-alt"></i> 123 Financial District, Gold Coast</li>
                        <li><i class="fas fa-phone"></i> (123) 456-7890</li>
                        <li><i class="fas fa-envelope"></i> support@goldcoastbank.com</li>
                        <li><i class="fas fa-clock"></i> Mon-Fri: 9AM-5PM</li>
                    </ul>
                </div>
            </div>
            <div class="copyright">
                <p>&copy; 2023 Gold Coast Central Bank. All rights reserved. | <a href="#">Privacy Policy</a> | <a href="#">Terms of Service</a></p>
            </div>
        </div>
    </footer>

    <!-- Scripts -->
    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
    
    <script>
        $(document).ready(function() {
            // Initialize DataTables
            $('#recentTransactions').DataTable({
                "pageLength": 5,
                "lengthMenu": [5, 10, 25, 50],
                "order": [[0, "desc"]],
                "language": {
                    "search": "Search transactions:",
                    "lengthMenu": "Show _MENU_ entries",
                    "info": "Showing _START_ to _END_ of _TOTAL_ entries",
                    "paginate": {
                        "previous": "Previous",
                        "next": "Next"
                    }
                }
            });
            
            $('#securityEvents').DataTable({
                "pageLength": 5,
                "lengthMenu": [5, 10, 25, 50],
                "order": [[3, "desc"]],
                "language": {
                    "search": "Search events:",
                    "lengthMenu": "Show _MENU_ entries",
                    "info": "Showing _START_ to _END_ of _TOTAL_ entries",
                    "paginate": {
                        "previous": "Previous",
                        "next": "Next"
                    }
                }
            });
            
            // Toggle sidebar on mobile
            $('#sidebarToggle').click(function(e) {
                e.preventDefault();
                $('#sidebar').toggleClass('active');
            });
            
            // Toggle submenus
            $('.menu-toggle').click(function(e) {
                e.preventDefault();
                $(this).toggleClass('active').next('.sub-menu').toggleClass('active');
            });
            
            // Transaction Chart
            const transactionCtx = document.getElementById('transactionChart').getContext('2d');
            const transactionChart = new Chart(transactionCtx, {
                type: 'line',
                data: {
                    labels: ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'],
                    datasets: [
                        {
                            label: 'Deposits',
                            data: [12500, 19000, 18000, 22000, 21000, 15000, 18000],
                            borderColor: '#10b981',
                            backgroundColor: 'rgba(16, 185, 129, 0.1)',
                            tension: 0.3,
                            fill: true
                        },
                        {
                            label: 'Withdrawals',
                            data: [10000, 12000, 15000, 13000, 17000, 14000, 16000],
                            borderColor: '#ef4444',
                            backgroundColor: 'rgba(239, 68, 68, 0.1)',
                            tension: 0.3,
                            fill: true
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'top',
                            labels: {
                                color: '#cbd5e1'
                            }
                        }
                    },
                    scales: {
                        x: {
                            grid: {
                                color: 'rgba(255, 255, 255, 0.05)'
                            },
                            ticks: {
                                color: '#94a3b8'
                            }
                        },
                        y: {
                            grid: {
                                color: 'rgable(255, 255, 255, 0.05)'
                            },
                            ticks: {
                                color: '#94a3b8',
                                callback: function(value) {
                                    return '$' + value.toLocaleString();
                                }
                            }
                        }
                    }
                }
            });
            
            // Account Distribution Chart
            const accountCtx = document.getElementById('accountDistributionChart').getContext('2d');
            const accountChart = new Chart(accountCtx, {
                type: 'doughnut',
                data: {
                    labels: ['Current Accounts', 'Saving Accounts'],
                    datasets: [{
                        data: [65, 35],
                        backgroundColor: [
                            '#3b82f6',
                            '#10b981'
                        ],
                        borderColor: [
                            '#1e40af',
                            '#059669'
                        ],
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'bottom',
                            labels: {
                                color: '#cbd5e1',
                                padding: 15
                            }
                        }
                    },
                    cutout: '70%'
                }
            });
            
            // Auto-refresh dashboard every 5 minutes
            setInterval(function() {
                // Just reload the page for simplicity
                location.reload();
            }, 300000);
        });
    </script>
</body>
</html>