<?php
// require_once('../includes/auth.php');
require_once('../classes/FraudDetectionSystem.php');

// Only allow admins
// if ($_SESSION['user_role'] !== 'admin') {
//     header('Location: ../dashboard/');
//     exit;
// }

$fraudDetection = new FraudDetectionSystem($con);

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['resolve_alert'])) {
        $alertId = (int)$_POST['alert_id'];
        $status = $_POST['status'];
        $actionTaken = $_POST['action_taken'] ?? '';
        
        $sql = "UPDATE fraud_alerts 
                SET status = ?, action_taken = ?, reviewed_by = ?, reviewed_at = NOW()
                WHERE id = ?";
        $stmt = mysqli_prepare($con, $sql);
        mysqli_stmt_bind_param($stmt, "sssi", $status, $actionTaken, $_SESSION['user_id'], $alertId);
        mysqli_stmt_execute($stmt);
        
        $_SESSION['success'] = "Alert #$alertId has been marked as $status";
    } elseif (isset($_POST['freeze_account'])) {
        $account = $_POST['account'];
        $reason = $_POST['reason'];
        
        $fraudDetection->freezeAccount($account, $reason);
        $_SESSION['success'] = "Account $account has been frozen";
    } elseif (isset($_POST['unfreeze_account'])) {
        $account = $_POST['account'];
        
        $sql = "UPDATE accounts_info 
                SET is_frozen = 0, frozen_reason = NULL, frozen_at = NULL
                WHERE account = ?";
        $stmt = mysqli_prepare($con, $sql);
        mysqli_stmt_bind_param($stmt, "s", $account);
        mysqli_stmt_execute($stmt);
        
        $_SESSION['success'] = "Account $account has been unfrozen";
    } elseif (isset($_POST['add_rule'])) {
        $name = $_POST['name'];
        $description = $_POST['description'];
        $conditions = [];
        
        // Build conditions array from form data
        if (!empty($_POST['amount_greater_than'])) {
            $conditions['amount_greater_than'] = (float)$_POST['amount_greater_than'];
        }
        if (!empty($_POST['unusual_location'])) {
            $conditions['unusual_location'] = true;
        }
        if (!empty($_POST['unusual_time'])) {
            $conditions['unusual_time'] = true;
        }
        if (!empty($_POST['new_device'])) {
            $conditions['new_device'] = true;
        }
        if (!empty($_POST['velocity_high'])) {
            $conditions['velocity_high'] = (int)$_POST['velocity_high'];
        }
        
        $riskScore = (int)$_POST['risk_score'];
        $action = $_POST['action'];
        
        $sql = "INSERT INTO fraud_detection_rules 
                (name, description, rule_condition, risk_score, action)
                VALUES (?, ?, ?, ?, ?)";
        $stmt = mysqli_prepare($con, $sql);
        $conditionsJson = json_encode($conditions);
        mysqli_stmt_bind_param($stmt, "sssis", $name, $description, $conditionsJson, $riskScore, $action);
        mysqli_stmt_execute($stmt);
        
        $_SESSION['success'] = "New fraud detection rule added";
    }
}

// Get all fraud alerts
$alerts = [];
$sql = "SELECT f.*, a.account_title, r.name as rule_name 
        FROM fraud_alerts f
        LEFT JOIN accounts_info a ON f.account = a.account
        LEFT JOIN fraud_detection_rules r ON f.rule_id = r.id
        ORDER BY f.created_at DESC LIMIT 100";
$result = mysqli_query($con, $sql);
while ($row = mysqli_fetch_assoc($result)) {
    $alerts[] = $row;
}

// Get frozen accounts
$frozenAccounts = [];
$sql = "SELECT a.account, a.account_title, a.frozen_reason, a.frozen_at, h.name 
        FROM accounts_info a
        JOIN accountsholder h ON a.account = h.account
        WHERE a.is_frozen = 1";
$result = mysqli_query($con, $sql);
while ($row = mysqli_fetch_assoc($result)) {
    $frozenAccounts[] = $row;
}

// Get fraud detection rules
$rules = [];
$sql = "SELECT * FROM fraud_detection_rules ORDER BY is_active DESC, name";
$result = mysqli_query($con, $sql);
while ($row = mysqli_fetch_assoc($result)) {
    $row['rule_condition'] = json_decode($row['rule_condition'], true);
    $rules[] = $row;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Fraud Detection Dashboard</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="../dashboard/style.css">
    <style>
        .fraud-alert {
            border-left: 4px solid #e74c3c;
            margin-bottom: 15px;
        }
        .fraud-alert.low-risk {
            border-left-color: #f39c12;
        }
        .fraud-alert.medium-risk {
            border-left-color: #e67e22;
        }
        .fraud-alert.high-risk {
            border-left-color: #e74c3c;
        }
        .risk-badge {
            padding: 3px 8px;
            border-radius: 3px;
            font-size: 12px;
            font-weight: bold;
        }
        .risk-low {
            background-color: #f39c12;
        }
        .risk-medium {
            background-color: #e67e22;
            color: white;
        }
        .risk-high {
            background-color: #e74c3c;
            color: white;
        }
        .status-badge {
            padding: 3px 8px;
            border-radius: 3px;
            font-size: 12px;
            font-weight: bold;
        }
        .status-pending {
            background-color: #f39c12;
        }
        .status-reviewed {
            background-color: #2ecc71;
            color: white;
        }
        .status-false_positive {
            background-color: #3498db;
            color: white;
        }
        .status-confirmed_fraud {
            background-color: #e74c3c;
            color: white;
        }
    </style>
</head>
<body>
    <div class="container">
        <nav class="sidebar" id="sidebar">
            <!-- Your existing sidebar code -->
        </nav>

        <section class="main_content">
            <div class="topbar">
                <!-- Your existing topbar code -->
                <div class="overview_text">
                    <h1>Fraud Detection Dashboard</h1>
                    <p class="welcome">Monitor and manage suspicious activities</p>
                </div>
            </div>

            <?php if (isset($_SESSION['success'])): ?>
                <div class="alert alert-success"><?= $_SESSION['success'] ?></div>
                <?php unset($_SESSION['success']); ?>
            <?php endif; ?>

            <div class="fraud-tabs">
                <button class="tab-btn active" data-tab="alerts">Fraud Alerts</button>
                <button class="tab-btn" data-tab="frozen">Frozen Accounts</button>
                <button class="tab-btn" data-tab="rules">Detection Rules</button>
                <button class="tab-btn" data-tab="stats">Statistics</button>
            </div>

            <div class="tab-content active" id="alerts-tab">
                <h2>Recent Fraud Alerts</h2>
                
                <div class="filter-controls">
                    <select id="status-filter">
                        <option value="">All Statuses</option>
                        <option value="pending">Pending</option>
                        <option value="reviewed">Reviewed</option>
                        <option value="false_positive">False Positive</option>
                        <option value="confirmed_fraud">Confirmed Fraud</option>
                    </select>
                    
                    <select id="risk-filter">
                        <option value="">All Risk Levels</option>
                        <option value="low">Low (0-30)</option>
                        <option value="medium">Medium (31-70)</option>
                        <option value="high">High (71-100)</option>
                    </select>
                    
                    <input type="date" id="date-filter">
                </div>
                
                <div class="fraud-alerts-list">
                    <?php foreach ($alerts as $alert): ?>
                        <?php
                        $riskClass = '';
                        if ($alert['risk_score'] >= 70) {
                            $riskClass = 'high-risk';
                        } elseif ($alert['risk_score'] >= 30) {
                            $riskClass = 'medium-risk';
                        } else {
                            $riskClass = 'low-risk';
                        }
                        
                        $riskBadgeClass = '';
                        if ($alert['risk_score'] >= 70) {
                            $riskBadgeClass = 'risk-high';
                        } elseif ($alert['risk_score'] >= 30) {
                            $riskBadgeClass = 'risk-medium';
                        } else {
                            $riskBadgeClass = 'risk-low';
                        }
                        
                        $statusBadgeClass = 'status-' . $alert['status'];
                        ?>
                        
                        <div class="fraud-alert <?= $riskClass ?>">
                            <div class="alert-header">
                                <h3>
                                    Alert #<?= $alert['id'] ?> 
                                    <span class="risk-badge <?= $riskBadgeClass ?>">
                                        Risk: <?= $alert['risk_score'] ?>
                                    </span>
                                    <span class="status-badge <?= $statusBadgeClass ?>">
                                        <?= ucfirst(str_replace('_', ' ', $alert['status'])) ?>
                                    </span>
                                </h3>
                                <div class="alert-meta">
                                    <span><i class="fas fa-user"></i> <?= $alert['account'] ?> (<?= $alert['account_title'] ?>)</span>
                                    <span><i class="fas fa-calendar"></i> <?= date('M j, Y H:i', strtotime($alert['created_at'])) ?></span>
                                </div>
                            </div>
                            
                            <div class="alert-details">
                                <p><strong>Rule Triggered:</strong> <?= $alert['rule_name'] ?></p>
                                
                                <?php if ($alert['transaction_id']): ?>
                                    <p><strong>Transaction ID:</strong> <?= $alert['transaction_id'] ?></p>
                                <?php endif; ?>
                                
                                <?php if ($alert['login_id']): ?>
                                    <p><strong>Login ID:</strong> <?= $alert['login_id'] ?></p>
                                <?php endif; ?>
                                
                                <?php if ($alert['action_taken']): ?>
                                    <p><strong>Action Taken:</strong> <?= $alert['action_taken'] ?></p>
                                <?php endif; ?>
                                
                                <?php if ($alert['reviewed_by']): ?>
                                    <p><strong>Reviewed By:</strong> <?= $alert['reviewed_by'] ?> on <?= date('M j, Y H:i', strtotime($alert['reviewed_at'])) ?></p>
                                <?php endif; ?>
                            </div>
                            
                            <?php if ($alert['status'] === 'pending'): ?>
                                <div class="alert-actions">
                                    <form method="POST" class="resolve-form">
                                        <input type="hidden" name="alert_id" value="<?= $alert['id'] ?>">
                                        <select name="status" required>
                                            <option value="">Select resolution</option>
                                            <option value="false_positive">False Positive</option>
                                            <option value="confirmed_fraud">Confirmed Fraud</option>
                                        </select>
                                        <input type="text" name="action_taken" placeholder="Action taken">
                                        <button type="submit" name="resolve_alert" class="btn btn-primary">Resolve</button>
                                    </form>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="tab-content" id="frozen-tab">
                <h2>Frozen Accounts</h2>
                
                <div class="frozen-accounts-list">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Account</th>
                                <th>Account Title</th>
                                <th>Name</th>
                                <th>Reason</th>
                                <th>Frozen At</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($frozenAccounts as $account): ?>
                                <tr>
                                    <td><?= $account['account'] ?></td>
                                    <td><?= $account['account_title'] ?></td>
                                    <td><?= $account['name'] ?></td>
                                    <td><?= $account['frozen_reason'] ?></td>
                                    <td><?= date('M j, Y H:i', strtotime($account['frozen_at'])) ?></td>
                                    <td>
                                        <form method="POST" style="display: inline;">
                                            <input type="hidden" name="account" value="<?= $account['account'] ?>">
                                            <button type="submit" name="unfreeze_account" class="btn btn-success">Unfreeze</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="tab-content" id="rules-tab">
                <h2>Fraud Detection Rules</h2>
                
                <button id="add-rule-btn" class="btn btn-primary">
                    <i class="fas fa-plus"></i> Add New Rule
                </button>
                
                <div class="rules-list">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Description</th>
                                <th>Risk Score</th>
                                <th>Action</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($rules as $rule): ?>
                                <tr>
                                    <td><?= $rule['name'] ?></td>
                                    <td><?= $rule['description'] ?></td>
                                    <td><?= $rule['risk_score'] ?></td>
                                    <td><?= ucfirst(str_replace('_', ' ', $rule['action'])) ?></td>
                                    <td>
                                        <?php if ($rule['is_active']): ?>
                                            <span class="badge badge-success">Active</span>
                                        <?php else: ?>
                                            <span class="badge badge-secondary">Inactive</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <a href="#" class="btn btn-sm btn-info view-rule" data-id="<?= $rule['id'] ?>">View</a>
                                        <a href="#" class="btn btn-sm btn-warning edit-rule" data-id="<?= $rule['id'] ?>">Edit</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                
                <div id="add-rule-form" style="display: none; margin-top: 20px;">
                    <h3>Add New Fraud Detection Rule</h3>
                    <form method="POST">
                        <div class="form-group">
                            <label>Rule Name</label>
                            <input type="text" name="name" required class="form-control">
                        </div>
                        
                        <div class="form-group">
                            <label>Description</label>
                            <textarea name="description" required class="form-control"></textarea>
                        </div>
                        
                        <div class="form-group">
                            <label>Conditions</label>
                            <div class="conditions-list">
                                <div class="condition-item">
                                    <label>
                                        <input type="checkbox" name="amount_greater_than"> Amount greater than
                                    </label>
                                    <input type="number" name="amount_greater_than_value" class="form-control" style="width: 100px; display: inline-block;">
                                </div>
                                
                                <div class="condition-item">
                                    <label>
                                        <input type="checkbox" name="unusual_location"> Unusual location
                                    </label>
                                </div>
                                
                                <div class="condition-item">
                                    <label>
                                        <input type="checkbox" name="unusual_time"> Unusual time (outside normal hours)
                                    </label>
                                </div>
                                
                                <div class="condition-item">
                                    <label>
                                        <input type="checkbox" name="new_device"> New device
                                    </label>
                                </div>
                                
                                <div class="condition-item">
                                    <label>
                                        <input type="checkbox" name="velocity_high"> High velocity (more than
                                    </label>
                                    <input type="number" name="velocity_high_value" class="form-control" style="width: 100px; display: inline-block;">
                                    transactions in 1 minute
                                </div>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label>Risk Score</label>
                            <input type="number" name="risk_score" min="0" max="100" required class="form-control">
                        </div>
                        
                        <div class="form-group">
                            <label>Action</label>
                            <select name="action" required class="form-control">
                                <option value="flag">Flag for review</option>
                                <option value="require_verification">Require additional verification</option>
                                <option value="freeze_account">Freeze account</option>
                                <option value="notify_admin">Notify admin</option>
                            </select>
                        </div>
                        
                        <button type="submit" name="add_rule" class="btn btn-primary">Save Rule</button>
                    </form>
                </div>
            </div>

            <div class="tab-content" id="stats-tab">
                <h2>Fraud Detection Statistics</h2>
                
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-value"><?= count($alerts) ?></div>
                        <div class="stat-label">Total Alerts</div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-value">
                            <?= count(array_filter($alerts, function($a) { return $a['status'] === 'pending'; })) ?>
                        </div>
                        <div class="stat-label">Pending Alerts</div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-value">
                            <?= count(array_filter($alerts, function($a) { return $a['status'] === 'confirmed_fraud'; })) ?>
                        </div>
                        <div class="stat-label">Confirmed Fraud</div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-value">
                            <?= count(array_filter($alerts, function($a) { return $a['status'] === 'false_positive'; })) ?>
                        </div>
                        <div class="stat-label">False Positives</div>
                    </div>
                </div>
                
                <div class="chart-container">
                    <canvas id="fraudChart"></canvas>
                </div>
            </div>
        </section>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        // Tab functionality
        document.querySelectorAll('.tab-btn').forEach(btn => {
            btn.addEventListener('click', () => {
                // Remove active class from all tabs and buttons
                document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
                document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
                
                // Add active class to clicked button and corresponding content
                btn.classList.add('active');
                const tabId = btn.getAttribute('data-tab') + '-tab';
                document.getElementById(tabId).classList.add('active');
            });
        });
        
        // Add rule form toggle
        document.getElementById('add-rule-btn').addEventListener('click', () => {
            const form = document.getElementById('add-rule-form');
            form.style.display = form.style.display === 'none' ? 'block' : 'none';
        });
        
        // Condition checkboxes
        document.querySelectorAll('.conditions-list input[type="checkbox"]').forEach(cb => {
            cb.addEventListener('change', function() {
                const valueInput = this.parentElement.querySelector('input[type="number"]');
                if (valueInput) {
                    valueInput.disabled = !this.checked;
                }
            });
        });
        
        // Fraud chart
        const ctx = document.getElementById('fraudChart').getContext('2d');
        const fraudChart = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: ['Confirmed Fraud', 'False Positives', 'Pending Review'],
                datasets: [{
                    label: 'Fraud Alerts by Status',
                    data: [
                        <?= count(array_filter($alerts, function($a) { return $a['status'] === 'confirmed_fraud'; })) ?>,
                        <?= count(array_filter($alerts, function($a) { return $a['status'] === 'false_positive'; })) ?>,
                        <?= count(array_filter($alerts, function($a) { return $a['status'] === 'pending'; })) ?>
                    ],
                    backgroundColor: [
                        'rgba(231, 76, 60, 0.7)',
                        'rgba(52, 152, 219, 0.7)',
                        'rgba(241, 196, 15, 0.7)'
                    ],
                    borderColor: [
                        'rgba(231, 76, 60, 1)',
                        'rgba(52, 152, 219, 1)',
                        'rgba(241, 196, 15, 1)'
                    ],
                    borderWidth: 1
                }]
            },
            options: {
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });
        
        // Filter functionality
        document.getElementById('status-filter').addEventListener('change', function() {
            const status = this.value;
            document.querySelectorAll('.fraud-alert').forEach(alert => {
                const alertStatus = alert.querySelector('.status-badge').textContent.toLowerCase().replace(' ', '_');
                if (!status || alertStatus === status) {
                    alert.style.display = 'block';
                } else {
                    alert.style.display = 'none';
                }
            });
        });
        
        document.getElementById('risk-filter').addEventListener('change', function() {
            const risk = this.value;
            document.querySelectorAll('.fraud-alert').forEach(alert => {
                const riskScore = parseInt(alert.querySelector('.risk-badge').textContent.replace('Risk: ', ''));
                let show = false;
                
                if (!risk) {
                    show = true;
                } else if (risk === 'low' && riskScore <= 30) {
                    show = true;
                } else if (risk === 'medium' && riskScore > 30 && riskScore <= 70) {
                    show = true;
                } else if (risk === 'high' && riskScore > 70) {
                    show = true;
                }
                
                alert.style.display = show ? 'block' : 'none';
            });
        });
    </script>
</body>
</html>