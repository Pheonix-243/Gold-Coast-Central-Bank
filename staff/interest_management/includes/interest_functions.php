<?php
// Database connection
require_once('../includes/db_conn.php');

/**
 * Calculate daily interest for an account
 * @param string $account Account number
 * @param string $date Calculation date (Y-m-d)
 * @return array Result of calculation
 */
function calculateDailyInterest($account, $date) {
    global $conn;
    
    // Get account details
    $accountQuery = "SELECT a.account, a.balance, a.interest_rate, a.interest_calculation_method, 
                    a.last_interest_calculation_date, a.accrued_interest
                    FROM accounts_info a 
                    WHERE a.account = ?";
    $stmt = $conn->prepare($accountQuery);
    $stmt->bind_param("s", $account);
    $stmt->execute();
    $accountResult = $stmt->get_result();
    
    if ($accountResult->num_rows == 0) {
        return ['success' => false, 'message' => 'Account not found'];
    }
    
    $accountData = $accountResult->fetch_assoc();
    
    // Check if balance meets minimum for interest
    $minBalance = getConfigValue('minimum_balance_for_interest');
    if ($accountData['balance'] < $minBalance) {
        return ['success' => false, 'message' => 'Balance below minimum for interest'];
    }
    
    // Determine calculation start date
    $startDate = $accountData['last_interest_calculation_date'] ? 
        date('Y-m-d', strtotime($accountData['last_interest_calculation_date'] . ' +1 day')) : 
        $accountData['registerdate'];
    
    // If start date is in future or same as calculation date, skip
    if ($startDate >= $date) {
        return ['success' => false, 'message' => 'No days to calculate'];
    }
    
    // Get the appropriate interest rate
    $rate = getEffectiveInterestRate($accountData['account_type'], $accountData['balance'], $date);
    if ($rate === false) {
        return ['success' => false, 'message' => 'No valid interest rate found'];
    }
    
    // Calculate days between dates
    $days = getDaysBetweenDates($startDate, $date);
    
    // Calculate interest based on method
    $interest = 0;
    $balanceUsed = $accountData['balance'];
    
    switch ($accountData['interest_calculation_method']) {
        case 'simple':
            $interest = calculateSimpleInterest($balanceUsed, $rate, $days);
            break;
        case 'compound':
            $interest = calculateCompoundInterest($balanceUsed, $rate, $days, $accountData['accrued_interest']);
            break;
        case 'tiered':
            $interest = calculateTieredInterest($account, $startDate, $date);
            break;
        default:
            return ['success' => false, 'message' => 'Invalid interest calculation method'];
    }
    
    // Record the interest transaction
    $recordResult = recordInterestTransaction($account, $date, $startDate, $date, $rate, $interest, $balanceUsed, $days, $accountData['interest_calculation_method']);
    
    if (!$recordResult['success']) {
        return $recordResult;
    }
    
    // Update account with new accrued interest and last calculation date
    $updateQuery = "UPDATE accounts_info 
                   SET accrued_interest = accrued_interest + ?, 
                       last_interest_calculation_date = ?
                   WHERE account = ?";
    $stmt = $conn->prepare($updateQuery);
    $stmt->bind_param("dss", $interest, $date, $account);
    $stmt->execute();
    
    return [
        'success' => true,
        'account' => $account,
        'start_date' => $startDate,
        'end_date' => $date,
        'days' => $days,
        'rate' => $rate,
        'interest' => $interest,
        'new_accrued_interest' => $accountData['accrued_interest'] + $interest,
        'transaction_id' => $recordResult['transaction_id']
    ];
}

/**
 * Calculate simple interest
 * @param float $principal Principal amount
 * @param float $rate Annual interest rate (as decimal)
 * @param int $days Number of days
 * @return float Interest amount
 */
function calculateSimpleInterest($principal, $rate, $days) {
    $calendarType = getConfigValue('interest_calendar');
    $divisor = $calendarType == '360' ? 360 : ($calendarType == '365' ? 365 : 365);
    
    return $principal * $rate * $days / $divisor;
}

/**
 * Calculate compound interest
 * @param float $principal Principal amount
 * @param float $rate Annual interest rate (as decimal)
 * @param int $days Number of days
 * @param float $existingAccrued Existing accrued interest
 * @return float Interest amount
 */
function calculateCompoundInterest($principal, $rate, $days, $existingAccrued = 0) {
    $calendarType = getConfigValue('interest_calendar');
    $divisor = $calendarType == '360' ? 360 : ($calendarType == '365' ? 365 : 365);
    
    $dailyRate = $rate / $divisor;
    $newPrincipal = $principal + $existingAccrued;
    
    return $newPrincipal * $dailyRate * $days;
}

/**
 * Calculate tiered interest
 * @param string $account Account number
 * @param string $startDate Start date (Y-m-d)
 * @param string $endDate End date (Y-m-d)
 * @return float Total interest for period
 */
function calculateTieredInterest($account, $startDate, $endDate) {
    global $conn;
    
    // Get daily balances for the period
    $balanceQuery = "SELECT balance, transaction_date 
                    FROM account_balance_history 
                    WHERE account = ? 
                    AND transaction_date BETWEEN ? AND ?
                    ORDER BY transaction_date";
    $stmt = $conn->prepare($balanceQuery);
    $stmt->bind_param("sss", $account, $startDate, $endDate);
    $stmt->execute();
    $balanceResult = $stmt->get_result();
    
    $balances = [];
    while ($row = $balanceResult->fetch_assoc()) {
        $balances[$row['transaction_date']] = $row['balance'];
    }
    
    // If no balances found, use current balance
    if (empty($balances)) {
        $accountQuery = "SELECT balance FROM accounts_info WHERE account = ?";
        $stmt = $conn->prepare($accountQuery);
        $stmt->bind_param("s", $account);
        $stmt->execute();
        $accountResult = $stmt->get_result();
        $accountData = $accountResult->fetch_assoc();
        
        $balances[$startDate] = $accountData['balance'];
    }
    
    // Fill in missing dates with previous balance
    $period = new DatePeriod(
        new DateTime($startDate),
        new DateInterval('P1D'),
        new DateTime($endDate)
    );
    
    $previousBalance = null;
    $dailyBalances = [];
    
    foreach ($period as $date) {
        $dateStr = $date->format('Y-m-d');
        if (isset($balances[$dateStr])) {
            $previousBalance = $balances[$dateStr];
        }
        $dailyBalances[$dateStr] = $previousBalance;
    }
    
    // Calculate interest for each day
    $totalInterest = 0;
    $previousDate = null;
    
    foreach ($dailyBalances as $date => $balance) {
        if ($balance === null) continue;
        
        $rate = getEffectiveInterestRate(null, $balance, $date);
        if ($rate === false) continue;
        
        $dailyInterest = calculateSimpleInterest($balance, $rate, 1);
        $totalInterest += $dailyInterest;
    }
    
    return $totalInterest;
}

/**
 * Get effective interest rate for an account type and balance
 * @param string $accountType Account type
 * @param float $balance Account balance
 * @param string $date Effective date
 * @return float|bool Interest rate or false if not found
 */
function getEffectiveInterestRate($accountType, $balance, $date) {
    global $conn;
    
    $rateQuery = "SELECT rate 
                 FROM interest_rates 
                 WHERE account_type = ? 
                 AND min_balance <= ? 
                 AND (max_balance IS NULL OR max_balance >= ?)
                 AND effective_date <= ? 
                 AND (end_date IS NULL OR end_date >= ?)
                 ORDER BY min_balance DESC 
                 LIMIT 1";
    $stmt = $conn->prepare($rateQuery);
    $stmt->bind_param("sddsd", $accountType, $balance, $balance, $date, $date);
    $stmt->execute();
    $rateResult = $stmt->get_result();
    
    if ($rateResult->num_rows > 0) {
        $rateData = $rateResult->fetch_assoc();
        return $rateData['rate'];
    }
    
    return false;
}

/**
 * Record interest transaction
 * @param string $account Account number
 * @param string $transactionDate Transaction date
 * @param string $startDate Calculation start date
 * @param string $endDate Calculation end date
 * @param float $rate Interest rate
 * @param float $amount Interest amount
 * @param float $balanceUsed Balance used for calculation
 * @param int $days Days applied
 * @param string $method Calculation method
 * @return array Result of operation
 */
function recordInterestTransaction($account, $transactionDate, $startDate, $endDate, $rate, $amount, $balanceUsed, $days, $method) {
    global $conn;
    
    $insertQuery = "INSERT INTO interest_transactions 
                   (account, transaction_date, calculation_start_date, calculation_end_date, 
                    rate, amount, balance_used, days_applied, calculation_method, status)
                   VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'posted')";
    $stmt = $conn->prepare($insertQuery);
    $stmt->bind_param("ssssdddis", $account, $transactionDate, $startDate, $endDate, 
                     $rate, $amount, $balanceUsed, $days, $method);
    $stmt->execute();
    
    if ($stmt->affected_rows > 0) {
        return ['success' => true, 'transaction_id' => $stmt->insert_id];
    } else {
        return ['success' => false, 'message' => 'Failed to record interest transaction'];
    }
}

/**
 * Post accrued interest to account
 * @param string $account Account number
 * @param string $postingDate Posting date (Y-m-d)
 * @return array Result of operation
 */
function postAccruedInterest($account, $postingDate) {
    global $conn;
    
    // Start transaction
    $conn->begin_transaction();
    
    try {
        // Get account details
        $accountQuery = "SELECT account, accrued_interest, balance 
                        FROM accounts_info 
                        WHERE account = ? 
                        FOR UPDATE";
        $stmt = $conn->prepare($accountQuery);
        $stmt->bind_param("s", $account);
        $stmt->execute();
        $accountResult = $stmt->get_result();
        
        if ($accountResult->num_rows == 0) {
            throw new Exception('Account not found');
        }
        
        $accountData = $accountResult->fetch_assoc();
        
        if ($accountData['accrued_interest'] <= 0) {
            throw new Exception('No accrued interest to post');
        }
        
        $interestAmount = $accountData['accrued_interest'];
        $newBalance = $accountData['balance'] + $interestAmount;
        
        // Update account balance and reset accrued interest
        $updateQuery = "UPDATE accounts_info 
                       SET balance = ?, 
                           accrued_interest = 0,
                           last_interest_calculation_date = ?
                       WHERE account = ?";
        $stmt = $conn->prepare($updateQuery);
        $stmt->bind_param("dss", $newBalance, $postingDate, $account);
        $stmt->execute();
        
        // Record the transaction in account_history
        $accountTitleQuery = "SELECT account_title FROM accounts_info WHERE account = ?";
        $stmt = $conn->prepare($accountTitleQuery);
        $stmt->bind_param("s", $account);
        $stmt->execute();
        $titleResult = $stmt->get_result();
        $titleData = $titleResult->fetch_assoc();
        
        $referenceNumber = 'INT' . date('Ymd') . str_pad(mt_rand(1, 9999), 4, '0', STR_PAD_LEFT);
        
        $insertHistoryQuery = "INSERT INTO account_history 
                             (account, sender, s_name, reciever, r_name, dt, tm, 
                              type, amount, no, balance_after, description, reference_number)
                             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NULL, ?, ?, ?)";
        $stmt = $conn->prepare($insertHistoryQuery);
        
        $dt = date('Y-m-d');
        $tm = date('H:i:s');
        $type = 8; // Interest Credit transaction type
        $description = "Interest credit for account {$account}";
        
        $stmt->bind_param("sssssssidiss", 
            $account, 
            'SYSTEM', 
            'System', 
            $account, 
            $titleData['account_title'], 
            $dt, 
            $tm, 
            $type, 
            $interestAmount, 
            $newBalance, 
            $description, 
            $referenceNumber);
        $stmt->execute();
        
        // Mark interest transactions as posted
        $updateInterestQuery = "UPDATE interest_transactions 
                              SET status = 'posted',
                                  transaction_date = ?
                              WHERE account = ? 
                              AND status = 'pending'";
        $stmt = $conn->prepare($updateInterestQuery);
        $stmt->bind_param("ss", $postingDate, $account);
        $stmt->execute();
        
        $conn->commit();
        
        return [
            'success' => true,
            'account' => $account,
            'amount' => $interestAmount,
            'new_balance' => $newBalance
        ];
    } catch (Exception $e) {
        $conn->rollback();
        return ['success' => false, 'message' => $e->getMessage()];
    }
}

/**
 * Get configuration value
 * @param string $configName Configuration name
 * @return string Configuration value
 */
function getConfigValue($configName) {
    global $conn;
    
    $query = "SELECT config_value FROM interest_configuration WHERE config_name = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("s", $configName);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        return $row['config_value'];
    }
    
    // Return defaults if not found
    $defaults = [
        'calculation_frequency' => 'daily',
        'posting_frequency' => 'monthly',
        'calculation_time' => '23:00:00',
        'posting_day' => '1',
        'minimum_balance_for_interest' => '1000',
        'interest_calendar' => 'actual'
    ];
    
    return $defaults[$configName] ?? '';
}

/**
 * Get days between two dates based on calendar type
 * @param string $startDate Start date (Y-m-d)
 * @param string $endDate End date (Y-m-d)
 * @return int Number of days
 */
function getDaysBetweenDates($startDate, $endDate) {
    $calendarType = getConfigValue('interest_calendar');
    
    $start = new DateTime($startDate);
    $end = new DateTime($endDate);
    $interval = $start->diff($end);
    
    if ($calendarType == '360') {
        return $interval->days;
    } elseif ($calendarType == '365') {
        return $interval->days;
    } else {
        // Actual/actual - account for leap years
        $years = $interval->y;
        $startYear = $start->format('Y');
        $endYear = $end->format('Y');
        
        $leapDays = 0;
        for ($year = $startYear; $year <= $endYear; $year++) {
            if (date('L', strtotime("$year-01-01"))) {
                $leapStart = ($year == $startYear) ? $start : new DateTime("$year-01-01");
                $leapEnd = ($year == $endYear) ? $end : new DateTime("$year-12-31");
                $leapDays += $leapStart->diff($leapEnd)->days + 1;
            }
        }
        
        return $interval->days + ($leapDays / 365);
    }
}
?>