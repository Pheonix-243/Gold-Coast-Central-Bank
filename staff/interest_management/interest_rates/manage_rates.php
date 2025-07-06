<?php
require_once('../includes/conn.php');
require_once('../includes/auth.php');

// Check admin permissions
if (!hasPermission('Admin')) {
    die('Access denied');
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['add_rate'])) {
        // Add new interest rate
        $accountType = $_POST['account_type'];
        $minBalance = $_POST['min_balance'];
        $maxBalance = !empty($_POST['max_balance']) ? $_POST['max_balance'] : null;
        $rate = $_POST['rate'];
        $effectiveDate = $_POST['effective_date'];
        $endDate = !empty($_POST['end_date']) ? $_POST['end_date'] : null;
        
        $query = "INSERT INTO interest_rates 
                 (account_type, min_balance, max_balance, rate, effective_date, end_date)
                 VALUES (?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("sdddss", $accountType, $minBalance, $maxBalance, $rate, $effectiveDate, $endDate);
        
        if ($stmt->execute()) {
            $success = "Interest rate added successfully";
        } else {
            $error = "Error adding interest rate: " . $conn->error;
        }
    } elseif (isset($_POST['update_rate'])) {
        // Update existing rate
        $id = $_POST['rate_id'];
        $accountType = $_POST['account_type'];
        $minBalance = $_POST['min_balance'];
        $maxBalance = !empty($_POST['max_balance']) ? $_POST['max_balance'] : null;
        $rate = $_POST['rate'];
        $effectiveDate = $_POST['effective_date'];
        $endDate = !empty($_POST['end_date']) ? $_POST['end_date'] : null;
        
        $query = "UPDATE interest_rates 
                 SET account_type = ?, min_balance = ?, max_balance = ?, 
                     rate = ?, effective_date = ?, end_date = ?
                 WHERE id = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("sdddssi", $accountType, $minBalance, $maxBalance, $rate, $effectiveDate, $endDate, $id);
        
        if ($stmt->execute()) {
            $success = "Interest rate updated successfully";
        } else {
            $error = "Error updating interest rate: " . $conn->error;
        }
    } elseif (isset($_POST['delete_rate'])) {
        // Delete rate
        $id = $_POST['rate_id'];
        
        $query = "DELETE FROM interest_rates WHERE id = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("i", $id);
        
        if ($stmt->execute()) {
            $success = "Interest rate deleted successfully";
        } else {
            $error = "Error deleting interest rate: " . $conn->error;
        }
    }
}

// Get current rates
$ratesQuery = "SELECT * FROM interest_rates ORDER BY account_type, min_balance, effective_date";
$ratesResult = $conn->query($ratesQuery);

// Get account types
$typesQuery = "SELECT DISTINCT account_type FROM accounts_info";
$typesResult = $conn->query($typesQuery);
$accountTypes = [];
while ($row = $typesResult->fetch_assoc()) {
    $accountTypes[] = $row['account_type'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Interest Rates</title>
    <link rel="stylesheet" href="../../css/bootstrap.min.css">
    <style>
        .rate-card {
            border-left: 4px solid #007bff;
        }
        .tier-card {
            background-color: #f8f9fa;
        }
    </style>
</head>
<body>
    <?php include('../../includes/navbar.php'); ?>
    
    <div class="container mt-4">
        <h2>Manage Interest Rates</h2>
        
        <?php if (isset($success)): ?>
            <div class="alert alert-success"><?php echo $success; ?></div>
        <?php endif; ?>
        
        <?php if (isset($error)): ?>
            <div class="alert alert-danger"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <div class="row">
            <div class="col-md-8">
                <div class="card mb-4">
                    <div class="card-header">
                        <h4>Current Interest Rates</h4>
                    </div>
                    <div class="card-body">
                        <?php if ($ratesResult->num_rows > 0): ?>
                            <?php 
                            $currentType = null;
                            while ($rate = $ratesResult->fetch_assoc()): 
                                if ($currentType != $rate['account_type']):
                                    if ($currentType !== null):
                                        echo '</div></div>';
                                    endif;
                                    $currentType = $rate['account_type'];
                            ?>
                                <div class="card mb-3 rate-card">
                                    <div class="card-header">
                                        <h5><?php echo htmlspecialchars($rate['account_type']); ?></h5>
                                    </div>
                                    <div class="card-body">
                            <?php endif; ?>
                                        
                                        <div class="card mb-2 tier-card">
                                            <div class="card-body">
                                                <div class="d-flex justify-content-between">
                                                    <div>
                                                        <strong>Balance Tier:</strong> 
                                                        <?php echo number_format($rate['min_balance'], 2); ?> - 
                                                        <?php echo $rate['max_balance'] ? number_format($rate['max_balance'], 2) : '∞'; ?>
                                                    </div>
                                                    <div>
                                                        <strong>Rate:</strong> <?php echo ($rate['rate'] * 100); ?>%
                                                    </div>
                                                    <div>
                                                        <strong>Effective:</strong> <?php echo $rate['effective_date']; ?>
                                                        <?php if ($rate['end_date']): ?>
                                                            to <?php echo $rate['end_date']; ?>
                                                        <?php endif; ?>
                                                    </div>
                                                    <div>
                                                        <button class="btn btn-sm btn-primary edit-rate" 
                                                                data-id="<?php echo $rate['id']; ?>"
                                                                data-type="<?php echo htmlspecialchars($rate['account_type']); ?>"
                                                                data-min="<?php echo $rate['min_balance']; ?>"
                                                                data-max="<?php echo $rate['max_balance']; ?>"
                                                                data-rate="<?php echo $rate['rate']; ?>"
                                                                data-effective="<?php echo $rate['effective_date']; ?>"
                                                                data-end="<?php echo $rate['end_date']; ?>">
                                                            Edit
                                                        </button>
                                                        <form method="post" style="display:inline;">
                                                            <input type="hidden" name="rate_id" value="<?php echo $rate['id']; ?>">
                                                            <button type="submit" name="delete_rate" class="btn btn-sm btn-danger" 
                                                                    onclick="return confirm('Are you sure you want to delete this rate?')">
                                                                Delete
                                                            </button>
                                                        </form>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                            
                            <?php endwhile; ?>
                                </div></div> <!-- Close last card -->
                            </div>
                        <?php else: ?>
                            <p>No interest rates configured yet.</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <div class="col-md-4">
                <div class="card">
                    <div class="card-header">
                        <h4 id="form-title">Add New Interest Rate</h4>
                    </div>
                    <div class="card-body">
                        <form method="post" id="rate-form">
                            <input type="hidden" name="rate_id" id="rate_id" value="">
                            
                            <div class="form-group">
                                <label for="account_type">Account Type</label>
                                <select class="form-control" id="account_type" name="account_type" required>
                                    <option value="">Select Account Type</option>
                                    <?php foreach ($accountTypes as $type): ?>
                                        <option value="<?php echo htmlspecialchars($type); ?>"><?php echo htmlspecialchars($type); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label for="min_balance">Minimum Balance</label>
                                <input type="number" class="form-control" id="min_balance" name="min_balance" 
                                       step="0.01" min="0" required>
                            </div>
                            
                            <div class="form-group">
                                <label for="max_balance">Maximum Balance (leave blank for no max)</label>
                                <input type="number" class="form-control" id="max_balance" name="max_balance" 
                                       step="0.01" min="0">
                            </div>
                            
                            <div class="form-group">
                                <label for="rate">Interest Rate (%)</label>
                                <input type="number" class="form-control" id="rate" name="rate" 
                                       step="0.01" min="0" max="100" required>
                            </div>
                            
                            <div class="form-group">
                                <label for="effective_date">Effective Date</label>
                                <input type="date" class="form-control" id="effective_date" name="effective_date" required>
                            </div>
                            
                            <div class="form-group">
                                <label for="end_date">End Date (optional)</label>
                                <input type="date" class="form-control" id="end_date" name="end_date">
                            </div>
                            
                            <button type="submit" name="add_rate" id="submit-btn" class="btn btn-primary">Add Rate</button>
                            <button type="button" id="cancel-edit" class="btn btn-secondary" style="display:none;">Cancel</button>
                        </form>
                    </div>
                </div>
                
                <div class="card mt-4">
                    <div class="card-header">
                        <h4>Interest Configuration</h4>
                    </div>
                    <div class="card-body">
                        <form method="post" action="update_config.php">
                            <div class="form-group">
                                <label for="posting_frequency">Posting Frequency</label>
                                <select class="form-control" id="posting_frequency" name="posting_frequency" required>
                                    <option value="daily" <?php echo getConfigValue('posting_frequency') == 'daily' ? 'selected' : ''; ?>>Daily</option>
                                    <option value="monthly" <?php echo getConfigValue('posting_frequency') == 'monthly' ? 'selected' : ''; ?>>Monthly</option>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label for="posting_day">Posting Day (if monthly)</label>
                                <input type="number" class="form-control" id="posting_day" name="posting_day" 
                                       min="1" max="31" value="<?php echo getConfigValue('posting_day'); ?>" required>
                            </div>
                            
                            <div class="form-group">
                                <label for="calculation_frequency">Calculation Frequency</label>
                                <select class="form-control" id="calculation_frequency" name="calculation_frequency" required>
                                    <option value="daily" <?php echo getConfigValue('calculation_frequency') == 'daily' ? 'selected' : ''; ?>>Daily</option>
                                    <option value="monthly" <?php echo getConfigValue('calculation_frequency') == 'monthly' ? 'selected' : ''; ?>>Monthly</option>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label for="minimum_balance_for_interest">Minimum Balance for Interest</label>
                                <input type="number" class="form-control" id="minimum_balance_for_interest" 
                                       name="minimum_balance_for_interest" step="0.01" min="0" 
                                       value="<?php echo getConfigValue('minimum_balance_for_interest'); ?>" required>
                            </div>
                            
                            <div class="form-group">
                                <label for="interest_calendar">Interest Calendar</label>
                                <select class="form-control" id="interest_calendar" name="interest_calendar" required>
                                    <option value="actual" <?php echo getConfigValue('interest_calendar') == 'actual' ? 'selected' : ''; ?>>Actual/Actual</option>
                                    <option value="360" <?php echo getConfigValue('interest_calendar') == '360' ? 'selected' : ''; ?>>360-day year</option>
                                    <option value="365" <?php echo getConfigValue('interest_calendar') == '365' ? 'selected' : ''; ?>>365-day year</option>
                                </select>
                            </div>
                            
                            <button type="submit" class="btn btn-primary">Update Configuration</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="../../js/jquery-3.5.1.min.js"></script>
    <script src="../../js/bootstrap.bundle.min.js"></script>
    <script>
        $(document).ready(function() {
            // Set effective date to today by default
            $('#effective_date').val(new Date().toISOString().substr(0, 10));
            
            // Edit rate button handler
            $('.edit-rate').click(function() {
                const rateId = $(this).data('id');
                const accountType = $(this).data('type');
                const minBalance = $(this).data('min');
                const maxBalance = $(this).data('max');
                const rate = $(this).data('rate') * 100;
                const effectiveDate = $(this).data('effective');
                const endDate = $(this).data('end');
                
                $('#rate_id').val(rateId);
                $('#account_type').val(accountType);
                $('#min_balance').val(minBalance);
                $('#max_balance').val(maxBalance || '');
                $('#rate').val(rate);
                $('#effective_date').val(effectiveDate);
                $('#end_date').val(endDate || '');
                
                $('#form-title').text('Edit Interest Rate');
                $('#submit-btn').attr('name', 'update_rate').text('Update Rate');
                $('#cancel-edit').show();
                
                $('html, body').animate({
                    scrollTop: $('#rate-form').offset().top - 20
                }, 500);
            });
            
            // Cancel edit button handler
            $('#cancel-edit').click(function() {
                $('#rate_id').val('');
                $('#account_type').val('');
                $('#min_balance').val('');
                $('#max_balance').val('');
                $('#rate').val('');
                $('#effective_date').val(new Date().toISOString().substr(0, 10));
                $('#end_date').val('');
                
                $('#form-title').text('Add New Interest Rate');
                $('#submit-btn').attr('name', 'add_rate').text('Add Rate');
                $(this).hide();
            });
        });
    </script>
</body>
</html>