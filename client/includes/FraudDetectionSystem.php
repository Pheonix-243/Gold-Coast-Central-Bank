<?php
// includes/fraud/FraudDetection.php

require_once('conn.php');
require_once('notification.php');

class FraudDetection {
    private $con;
    private $account;
    private $ipAddress;
    private $userAgent;
    private $deviceHash;
    private $currentRiskScore = 0;
    private $triggeredRules = [];
    
    public function __construct($dbConnection, $account = null) {
        $this->con = $dbConnection;
        $this->account = $account;
        $this->ipAddress = $_SERVER['REMOTE_ADDR'] ?? null;
        $this->userAgent = $_SERVER['HTTP_USER_AGENT'] ?? null;
        $this->deviceHash = $this->generateDeviceHash();
    }
    
    /**
     * Generate a device fingerprint hash
     */
    private function generateDeviceHash() {
        $components = [
            $this->userAgent,
            $_SERVER['HTTP_ACCEPT'] ?? '',
            $_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '',
            $_SERVER['HTTP_ACCEPT_ENCODING'] ?? '',
            // Add more headers as needed
        ];
        
        return hash('sha256', implode('|', $components));
    }
    
    /**
     * Check IP against known proxies/VPNs using IPHub
     */
    private function checkIpReputation($ip) {
        // Check cache first
        $sql = "SELECT * FROM ip_intelligence WHERE ip_address = ?";
        $stmt = mysqli_prepare($this->con, $sql);
        mysqli_stmt_bind_param($stmt, "s", $ip);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        
        if (mysqli_num_rows($result) > 0) {
            return mysqli_fetch_assoc($result);
        }
    
        // Call IPHub API if not in cache
        $apiKey = 'MjkwNDE6ZnFMUjNRdUl2eHVjTnhOMkxYdUg1cWpYS2lTYUVuenE=';
        $url = "http://v2.api.iphub.info/ip/{$ip}";
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ["X-Key: {$apiKey}"]);
        $response = curl_exec($ch);
        curl_close($ch);
        
        $data = json_decode($response, true);
        
        if ($data && isset($data['countryCode'])) {
            // Assign expressions to variables before binding
            $countryCode = $data['countryCode'];
            $countryName = $data['countryName'] ?? '';
            $isProxy = ($data['block'] == 1) ? 1 : 0;
            $asnType = $data['asn']['type'] ?? '';
            $isVpn = ($asnType === 'hosting') ? 1 : 0;
            $isTor = ($asnType === 'tor') ? 1 : 0;
    
            // Cache the result
            $sql = "INSERT INTO ip_intelligence 
                    (ip_address, country_code, country_name, is_proxy, is_vpn, is_tor) 
                    VALUES (?, ?, ?, ?, ?, ?)";
            $stmt = mysqli_prepare($this->con, $sql);
            mysqli_stmt_bind_param($stmt, "sssiii", 
                $ip,
                $countryCode,
                $countryName,
                $isProxy,
                $isVpn,
                $isTor
            );
            mysqli_stmt_execute($stmt);
    
            return $data;
        }
    
        return null;
    }
    
    /**
     * Analyze login attempt for fraud
     */
    public function analyzeLogin($success) {
        $this->currentRiskScore = 0;
        $this->triggeredRules = [];
        
        // 1. Check if device is known
        $this->checkDeviceAnomaly();
        
        // 2. Check IP reputation
        $ipInfo = $this->checkIpReputation($this->ipAddress);
        if ($ipInfo && ($ipInfo['is_proxy'] || $ipInfo['is_vpn'] || $ipInfo['is_tor'])) {
            $this->addRiskScore(30, "Login from suspicious IP (Proxy/VPN/Tor)");
            $this->triggeredRules[] = 'suspicious_ip';
        }
        
        // 3. Check time anomaly
        $this->checkTimeAnomaly();
        
        // 4. Check failed login attempts
        if (!$success) {
            $this->checkFailedLoginPattern();
        }
        
        // 5. Check geo-velocity (if previous login exists)
        $this->checkGeoVelocity();
        
        // Log the fraud analysis
        if ($this->currentRiskScore > 0) {
            $this->logFraudEvent('login', null, [
                'ip_address' => $this->ipAddress,
                'device_hash' => $this->deviceHash,
                'user_agent' => $this->userAgent,
                'risk_score' => $this->currentRiskScore,
                'triggered_rules' => $this->triggeredRules
            ]);
        }
        
        return [
            'risk_score' => $this->currentRiskScore,
            'triggered_rules' => $this->triggeredRules
        ];
    }
    
    /**
     * Analyze transaction for fraud
     */
    public function analyzeTransaction($type, $amount, $recipient = null) {
        $this->currentRiskScore = 0;
        $this->triggeredRules = [];
        
        // 1. Check transaction amount anomalies
        $this->checkAmountAnomaly($amount);
        
        // 2. Check recipient anomalies
        if ($recipient) {
            $this->checkRecipientAnomaly($recipient);
        }
        
        // 3. Check transaction frequency
        $this->checkTransactionFrequency($type);
        
        // 4. Check time anomaly
        $this->checkTimeAnomaly();
        
        // 5. Check device/IP anomalies
        $this->checkDeviceAnomaly();
        $ipInfo = $this->checkIpReputation($this->ipAddress);
        if ($ipInfo && ($ipInfo['is_proxy'] || $ipInfo['is_vpn'] || $ipInfo['is_tor'])) {
            $this->addRiskScore(20, "Transaction from suspicious IP");
            $this->triggeredRules[] = 'suspicious_ip_transaction';
        }
        
        // Log the fraud analysis
        if ($this->currentRiskScore > 0) {
            $this->logFraudEvent($type, $amount, [
                'recipient' => $recipient,
                'ip_address' => $this->ipAddress,
                'device_hash' => $this->deviceHash,
                'risk_score' => $this->currentRiskScore,
                'triggered_rules' => $this->triggeredRules
            ]);
        }
        
        return [
            'risk_score' => $this->currentRiskScore,
            'triggered_rules' => $this->triggeredRules,
            'recommended_action' => $this->getRecommendedAction()
        ];
    }
    
    /**
     * Check if the current device is new/untrusted
     */
    private function checkDeviceAnomaly() {
        if (!$this->account) return;
        
        $sql = "SELECT id, is_trusted FROM user_devices 
                WHERE account = ? AND device_hash = ?";
        $stmt = mysqli_prepare($this->con, $sql);
        mysqli_stmt_bind_param($stmt, "ss", $this->account, $this->deviceHash);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        
        if (mysqli_num_rows($result) === 0) {
            // New device
            $this->addRiskScore(25, "New device detected");
            $this->triggeredRules[] = 'new_device';
            
            // Record the new device
            $this->recordDevice();
        } else {
            $device = mysqli_fetch_assoc($result);
            if (!$device['is_trusted']) {
                $this->addRiskScore(15, "Untrusted device detected");
                $this->triggeredRules[] = 'untrusted_device';
            }
            
            // Update last seen
            $sql = "UPDATE user_devices SET last_seen = NOW() 
                    WHERE id = ?";
            $stmt = mysqli_prepare($this->con, $sql);
            mysqli_stmt_bind_param($stmt, "i", $device['id']);
            mysqli_stmt_execute($stmt);
        }
    }
    
    /**
     * Record a new device in the database
     */
    private function recordDevice() {
        if (!$this->account) return;
        
        $ipInfo = $this->checkIpReputation($this->ipAddress);
        $location = $ipInfo ? ($ipInfo['city'] . ', ' . $ipInfo['country_name']) : 'Unknown';
        
        $sql = "INSERT INTO user_devices 
                (account, device_hash, user_agent, os, browser, ip_address, location) 
                VALUES (?, ?, ?, ?, ?, ?, ?)";
        
        // Parse user agent for OS and browser (simplified)
        $os = 'Unknown';
        $browser = 'Unknown';
        if (preg_match('/\((.*?)\)/', $this->userAgent, $matches)) {
            $os = $matches[1];
        }
        if (preg_match('/(Firefox|Chrome|Safari|Edge|Opera)/', $this->userAgent, $matches)) {
            $browser = $matches[1];
        }
        
        $stmt = mysqli_prepare($this->con, $sql);
        mysqli_stmt_bind_param($stmt, "sssssss", 
            $this->account,
            $this->deviceHash,
            $this->userAgent,
            $os,
            $browser,
            $this->ipAddress,
            $location
        );
        mysqli_stmt_execute($stmt);
    }
    
    /**
     * Check if the current time is unusual for the user
     */
    private function checkTimeAnomaly() {
        if (!$this->account) return;
        
        // Get user's typical login times
        $sql = "SELECT typical_login_time FROM user_behavior_profiles 
                WHERE account = ?";
        $stmt = mysqli_prepare($this->con, $sql);
        mysqli_stmt_bind_param($stmt, "s", $this->account);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        
        if (mysqli_num_rows($result) > 0) {
            $profile = mysqli_fetch_assoc($result);
            $typicalTime = $profile['typical_login_time'];
            
            if ($typicalTime) {
                $currentHour = date('H');
                $typicalHour = date('H', strtotime($typicalTime));
                
                // If activity is between 1AM-5AM and not typical for user
                if ($currentHour >= 1 && $currentHour <= 5 && abs($currentHour - $typicalHour) > 3) {
                    $this->addRiskScore(20, "Unusual activity time detected");
                    $this->triggeredRules[] = 'unusual_time';
                }
            }
        }
    }
    
    /**
     * Check for failed login patterns
     */
    private function checkFailedLoginPattern() {
        if (!$this->account) return;
        
        // Count failed attempts in last 30 minutes
        $sql = "SELECT COUNT(*) as attempts FROM client_login_history 
                WHERE account = ? AND logout_time IS NULL 
                AND login_time > DATE_SUB(NOW(), INTERVAL 30 MINUTE)";
        $stmt = mysqli_prepare($this->con, $sql);
        mysqli_stmt_bind_param($stmt, "s", $this->account);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $data = mysqli_fetch_assoc($result);
        
        if ($data['attempts'] > 3) {
            $this->addRiskScore(10 * $data['attempts'], "Multiple failed login attempts");
            $this->triggeredRules[] = 'failed_login_attempts';
        }
    }
    
    /**
     * Check for impossible travel/geo-velocity
     */
private function checkGeoVelocity() {
        if (!$this->account) return;
        
        $ipInfo = $this->checkIpReputation($this->ipAddress);
        if (!$ipInfo || !isset($ipInfo['country_code'])) return;
        
// Step 1: Get the last successful IP for the account
$sql1 = "SELECT ip_address FROM client_login_history 
         WHERE account = ? AND logout_time IS NOT NULL
         ORDER BY login_time DESC LIMIT 1";
$stmt1 = mysqli_prepare($this->con, $sql1);
mysqli_stmt_bind_param($stmt1, "s", $this->account);
mysqli_stmt_execute($stmt1);
$result1 = mysqli_stmt_get_result($stmt1);

if (mysqli_num_rows($result1) > 0) {
    $row = mysqli_fetch_assoc($result1);
    $lastIp = $row['ip_address'];

    // Step 2: Get the country_code for that IP
    $sql2 = "SELECT country_code FROM ip_intelligence WHERE ip_address = ?";
    $stmt2 = mysqli_prepare($this->con, $sql2);
    mysqli_stmt_bind_param($stmt2, "s", $lastIp);
    mysqli_stmt_execute($stmt2);
    $result2 = mysqli_stmt_get_result($stmt2);

    if (mysqli_num_rows($result2) > 0) {
        $lastLogin = mysqli_fetch_assoc($result2);
        if ($lastLogin['country_code'] !== $ipInfo['country_code']) {
            $this->addRiskScore(40, "Login from different country");
            $this->triggeredRules[] = 'geo_velocity';
        }
    }
}}

    
    /**
     * Check for transaction amount anomalies
     */
    private function checkAmountAnomaly($amount) {
        if (!$this->account) return;
        
        // Get user's typical transaction amounts
        $sql = "SELECT avg_transaction_amount, max_transaction_amount 
                FROM user_behavior_profiles WHERE account = ?";
        $stmt = mysqli_prepare($this->con, $sql);
        mysqli_stmt_bind_param($stmt, "s", $this->account);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        
        if (mysqli_num_rows($result) > 0) {
            $profile = mysqli_fetch_assoc($result);
            $avgAmount = $profile['avg_transaction_amount'];
            $maxAmount = $profile['max_transaction_amount'];
            
            if ($amount > $maxAmount * 1.5) {
                $this->addRiskScore(30, "Transaction amount significantly higher than usual");
                $this->triggeredRules[] = 'amount_anomaly';
            }
            
            if ($amount > 10000) { // 10,000 GHS threshold
                $this->addRiskScore(20, "High-value transaction");
                $this->triggeredRules[] = 'high_value';
            }
        } else {
            // No profile yet - use conservative defaults
            if ($amount > 5000) {
                $this->addRiskScore(20, "High-value transaction for new user");
                $this->triggeredRules[] = 'high_value_new_user';
            }
        }
    }
    
    /**
     * Check for recipient anomalies
     */
    private function checkRecipientAnomaly($recipient) {
        if (!$this->account) return;
        
        // Count transactions to this recipient in last 24 hours
        $sql = "SELECT COUNT(*) as count FROM account_history 
                WHERE account = ? AND reciever = ? 
                AND dt = CURDATE()";
        $stmt = mysqli_prepare($this->con, $sql);
        mysqli_stmt_bind_param($stmt, "ss", $this->account, $recipient);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $data = mysqli_fetch_assoc($result);
        
        if ($data['count'] == 0) {
            // First transaction to this recipient
            $this->addRiskScore(15, "First transaction to new recipient");
            $this->triggeredRules[] = 'new_recipient';
        }
        
        // Count distinct recipients in last 24 hours
        $sql = "SELECT COUNT(DISTINCT reciever) as count FROM account_history 
                WHERE account = ? AND dt = CURDATE()";
        $stmt = mysqli_prepare($this->con, $sql);
        mysqli_stmt_bind_param($stmt, "s", $this->account);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $data = mysqli_fetch_assoc($result);
        
        if ($data['count'] >= 3) {
            $this->addRiskScore(25, "Multiple new recipients in one day");
            $this->triggeredRules[] = 'multiple_recipients';
        }
    }
    
    /**
     * Check for transaction frequency anomalies
     */
    private function checkTransactionFrequency($type) {
        if (!$this->account) return;
        
        // Count transactions of this type in last hour
        $sql = "SELECT COUNT(*) as count FROM account_history 
                WHERE account = ? AND type = ? 
                AND dt = CURDATE() AND tm > DATE_SUB(NOW(), INTERVAL 1 HOUR)";
        $stmt = mysqli_prepare($this->con, $sql);
        $typeId = $this->getTransactionTypeId($type);
        mysqli_stmt_bind_param($stmt, "si", $this->account, $typeId);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $data = mysqli_fetch_assoc($result);
        
        if ($data['count'] >= 5) {
            $this->addRiskScore(35, "High frequency of transactions");
            $this->triggeredRules[] = 'high_frequency';
        }
    }
    
    /**
     * Get transaction type ID from name
     */
    private function getTransactionTypeId($typeName) {
        $sql = "SELECT id FROM transaction_types WHERE name = ?";
        $stmt = mysqli_prepare($this->con, $sql);
        mysqli_stmt_bind_param($stmt, "s", $typeName);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        
        if (mysqli_num_rows($result) > 0) {
            $row = mysqli_fetch_assoc($result);
            return $row['id'];
        }
        
        return 0;
    }
    
    /**
     * Add to the current risk score
     */
    private function addRiskScore($points, $reason) {
        $this->currentRiskScore += $points;
        $this->triggeredRules[] = $reason;
    }
    
    /**
     * Log a fraud event
     */
    private function logFraudEvent($eventType, $amount = null, $metadata = []) {
        if (!$this->account) return;
        
        $sql = "INSERT INTO fraud_events 
                (account, event_type, risk_score, triggered_rules, metadata) 
                VALUES (?, ?, ?, ?, ?)";
        
        $triggeredRulesJson = json_encode($this->triggeredRules);
        $metadataJson = json_encode($metadata);
        
        $stmt = mysqli_prepare($this->con, $sql);
        mysqli_stmt_bind_param($stmt, "ssiss", 
            $this->account,
            $eventType,
            $this->currentRiskScore,
            $triggeredRulesJson,
            $metadataJson
        );
        mysqli_stmt_execute($stmt);
        
        return mysqli_insert_id($this->con);
    }
    
    /**
     * Get recommended action based on risk score
     */
    private function getRecommendedAction() {
        if ($this->currentRiskScore >= 70) {
            return 'block';
        } elseif ($this->currentRiskScore >= 30) {
            return 'require_verification';
        }
        return 'allow';
    }
    
    /**
     * Update user behavior profile
     */
    public function updateBehaviorProfile() {
        if (!$this->account) return;
        
        // Calculate typical login time (mode of login hours)
        $sql = "SELECT HOUR(login_time) as hour, COUNT(*) as count 
                FROM client_login_history 
                WHERE account = ? 
                GROUP BY HOUR(login_time) 
                ORDER BY count DESC LIMIT 1";
        $stmt = mysqli_prepare($this->con, $sql);
        mysqli_stmt_bind_param($stmt, "s", $this->account);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $typicalHour = mysqli_num_rows($result) > 0 ? mysqli_fetch_assoc($result)['hour'] . ':00:00' : null;
        
        // Calculate average/max transaction amounts
        $sql = "SELECT AVG(amount) as avg_amount, MAX(amount) as max_amount 
                FROM account_history 
                WHERE account = ? AND amount > 0";
        $stmt = mysqli_prepare($this->con, $sql);
        mysqli_stmt_bind_param($stmt, "s", $this->account);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $amounts = mysqli_fetch_assoc($result);
        
        // Get most common location
        $sql = "SELECT country_name, COUNT(*) as count 
                FROM ip_intelligence 
                WHERE ip_address IN (
                    SELECT ip_address FROM client_login_history 
                    WHERE account = ?
                ) 
                GROUP BY country_name 
                ORDER BY count DESC LIMIT 1";
        $stmt = mysqli_prepare($this->con, $sql);
        mysqli_stmt_bind_param($stmt, "s", $this->account);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $location = mysqli_num_rows($result) > 0 ? mysqli_fetch_assoc($result)['country_name'] : null;
        
        // Insert or update profile
        $sql = "INSERT INTO user_behavior_profiles 
                (account, typical_login_time, usual_location, avg_transaction_amount, max_transaction_amount) 
                VALUES (?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE 
                typical_login_time = VALUES(typical_login_time),
                usual_location = VALUES(usual_location),
                avg_transaction_amount = VALUES(avg_transaction_amount),
                max_transaction_amount = VALUES(max_transaction_amount),
                updated_at = NOW()";
        
        $stmt = mysqli_prepare($this->con, $sql);
        mysqli_stmt_bind_param($stmt, "sssdd", 
            $this->account,
            $typicalHour,
            $location,
            $amounts['avg_amount'],
            $amounts['max_amount']
        );
        mysqli_stmt_execute($stmt);
    }
}