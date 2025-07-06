<?php
require_once('../../includes/conn.php');
require_once('../../includes/auth.php');

// Check admin permissions
if (!hasPermission('admin')) {
    die('Access denied');
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $configs = [
        'calculation_frequency' => $_POST['calculation_frequency'],
        'posting_frequency' => $_POST['posting_frequency'],
        'posting_day' => $_POST['posting_day'],
        'minimum_balance_for_interest' => $_POST['minimum_balance_for_interest'],
        'interest_calendar' => $_POST['interest_calendar']
    ];
    
    $success = true;
    
    foreach ($configs as $name => $value) {
        $query = "INSERT INTO interest_configuration (config_name, config_value) 
                 VALUES (?, ?)
                 ON DUPLICATE KEY UPDATE config_value = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("sss", $name, $value, $value);
        
        if (!$stmt->execute()) {
            $success = false;
            break;
        }
    }
    
    if ($success) {
        $_SESSION['success'] = "Configuration updated successfully";
    } else {
        $_SESSION['error'] = "Error updating configuration";
    }
    
    header("Location: manage_rates.php");
    exit();
}
?>