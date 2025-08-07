<?php
// device_intelligence.php
class DeviceIntelligence {
    private $db;
    private $iphubApiKey;
    private $logger;
    
    public function __construct() {
        $this->db = DatabaseConnection::getInstance();
        $this->iphubApiKey = 'MjkwNDE6ZnFMUjNRdUl2eHVjTnhOMkxYdUg1cWpYS2lTYUVuenE=';
        $this->logger = new SecurityLogger();
    }
    
    public function getDeviceFingerprint() {
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
        
        // Basic device detection
        $browser = $this->parseUserAgent($userAgent)['browser'] ?? 'unknown';
        $os = $this->parseUserAgent($userAgent)['os'] ?? 'unknown';
        $deviceType = $this->getDeviceType($userAgent);
        
        // Create fingerprint hash
        $fingerprintData = [
            'ip' => $ip,
            'user_agent' => $userAgent,
            'browser' => $browser,
            'os' => $os,
            'accept_language' => $_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '',
            'screen_resolution' => $_POST['screen_res'] ?? '' // Can be sent via JS
        ];
        
        $fingerprintHash = hash('sha256', json_encode($fingerprintData));
        
        // Get IP intelligence
        $ipIntel = $this->getIpIntelligence($ip);
        
        return [
            'fingerprint_hash' => $fingerprintHash,
            'user_agent' => $userAgent,
            'browser' => $browser,
            'os' => $os,
            'device_type' => $deviceType,
            'ip_address' => $ip,
            'ip_country' => $ipIntel['country'] ?? null,
            'ip_is_proxy' => $ipIntel['is_proxy'] ?? false,
            'ip_threat_level' => $ipIntel['threat_level'] ?? 0,
            'raw_data' => $fingerprintData
        ];
    }
    
    private function getIpIntelligence($ip) {
        if ($ip === 'unknown' || $ip === '127.0.0.1') {
            return ['country' => null, 'is_proxy' => false, 'threat_level' => 0];
        }
        
        try {
            $url = "http://v2.api.iphub.info/ip/{$ip}";
            $options = [
                'http' => [
                    'header' => "X-Key: {$this->iphubApiKey}\r\n",
                    'timeout' => 2 // 2 second timeout
                ]
            ];
            
            $context = stream_context_create($options);
            $response = @file_get_contents($url, false, $context);
            
            if ($response === false) {
                throw new Exception("IPHub request failed");
            }
            
            $data = json_decode($response, true);
            
            return [
                'country' => $data['countryCode'] ?? null,
                'is_proxy' => $data['proxy'] === 1,
                'threat_level' => $data['block'] ?? 0
            ];
            
        } catch (Exception $e) {
            $this->logger->logEvent('ip_intel_failed', [
                'ip' => $ip,
                'error' => $e->getMessage()
            ], 'WARNING');
            
            return ['country' => null, 'is_proxy' => false, 'threat_level' => 0];
        }
    }
    
    private function parseUserAgent($userAgent) {
        $browser = 'unknown';
        $os = 'unknown';
        
        // Simple parsing - consider using a library like whichbrowser/parser for production
        if (stripos($userAgent, 'Firefox') !== false) {
            $browser = 'Firefox';
        } elseif (stripos($userAgent, 'Chrome') !== false) {
            $browser = 'Chrome';
        } elseif (stripos($userAgent, 'Safari') !== false) {
            $browser = 'Safari';
        } elseif (stripos($userAgent, 'Edge') !== false) {
            $browser = 'Edge';
        } elseif (stripos($userAgent, 'MSIE') !== false || stripos($userAgent, 'Trident') !== false) {
            $browser = 'IE';
        }
        
        if (stripos($userAgent, 'Windows') !== false) {
            $os = 'Windows';
        } elseif (stripos($userAgent, 'Mac') !== false) {
            $os = 'MacOS';
        } elseif (stripos($userAgent, 'Linux') !== false) {
            $os = 'Linux';
        } elseif (stripos($userAgent, 'Android') !== false) {
            $os = 'Android';
        } elseif (stripos($userAgent, 'iPhone') !== false || stripos($userAgent, 'iPad') !== false) {
            $os = 'iOS';
        }
        
        return ['browser' => $browser, 'os' => $os];
    }
    
    private function getDeviceType($userAgent) {
        if (stripos($userAgent, 'Mobile') !== false) {
            return 'mobile';
        } elseif (stripos($userAgent, 'Tablet') !== false) {
            return 'tablet';
        } elseif (stripos($userAgent, 'bot') !== false || stripos($userAgent, 'crawler') !== false) {
            return 'bot';
        }
        return 'desktop';
    }
    
    public function isKnownDevice($account, $fingerprintHash) {
        try {
            $conn = $this->db->getConnection();
            $stmt = $conn->prepare("
                SELECT COUNT(*) as count 
                FROM device_fingerprints 
                WHERE account = ? AND fingerprint_hash = ?
            ");
            $stmt->execute([$account, $fingerprintHash]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            return $result['count'] > 0;
        } catch (Exception $e) {
            $this->logger->logEvent('device_check_failed', [
                'account' => $account,
                'error' => $e->getMessage()
            ], 'ERROR');
            return false;
        }
    }
    
    public function recordDevice($account, $deviceData) {
        try {
            $conn = $this->db->getConnection();
            $stmt = $conn->prepare("
                INSERT INTO device_fingerprints (
                    account, fingerprint_hash, user_agent, browser, os, device_type, 
                    ip_address, ip_country, ip_is_proxy, ip_threat_level, last_used
                ) VALUES (
                    ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW()
                ) ON DUPLICATE KEY UPDATE 
                    last_used = NOW(),
                    ip_address = VALUES(ip_address),
                    ip_country = VALUES(ip_country),
                    ip_is_proxy = VALUES(ip_is_proxy),
                    ip_threat_level = VALUES(ip_threat_level)
            ");
            
            $stmt->execute([
                $account,
                $deviceData['fingerprint_hash'],
                $deviceData['user_agent'],
                $deviceData['browser'],
                $deviceData['os'],
                $deviceData['device_type'],
                $deviceData['ip_address'],
                $deviceData['ip_country'],
                $deviceData['ip_is_proxy'] ? 1 : 0,
                $deviceData['ip_threat_level']
            ]);
            
            return true;
        } catch (Exception $e) {
            $this->logger->logEvent('device_record_failed', [
                'account' => $account,
                'error' => $e->getMessage()
            ], 'ERROR');
            return false;
        }
    }
    
    public function shouldTriggerOTP($account, $deviceData) {
        // Always trigger OTP for suspicious IPs
        if ($deviceData['ip_threat_level'] > 0 || $deviceData['ip_is_proxy']) {
            return true;
        }
        
        // Check if this is a known device
        $isKnownDevice = $this->isKnownDevice($account, $deviceData['fingerprint_hash']);
        
        // If not known device, trigger OTP
        if (!$isKnownDevice) {
            return true;
        }
        
        // Check for recent failed logins
        $failedAttempts = $this->getRecentFailedAttempts($account);
        if ($failedAttempts >= 3) { // Threshold for failed attempts
            return true;
        }
        
        // Check if user has 2FA enabled (you'll need to add this column to accounts_info)
        $has2FA = $this->hasTwoFactorEnabled($account);
        if ($has2FA) {
            return true;
        }
        
        return false;
    }
    
    private function getRecentFailedAttempts($account) {
        try {
            $conn = $this->db->getConnection();
            $stmt = $conn->prepare("
                SELECT COUNT(*) as count 
                FROM security_logs 
                WHERE event_type = 'login_failed' 
                AND data LIKE ? 
                AND created_at >= NOW() - INTERVAL 15 MINUTE
            ");
            $stmt->execute(['%"account":"' . $account . '"%']);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            return $result['count'] ?? 0;
        } catch (Exception $e) {
            $this->logger->logEvent('failed_attempts_check_failed', [
                'account' => $account,
                'error' => $e->getMessage()
            ], 'ERROR');
            return 0;
        }
    }
    
    private function hasTwoFactorEnabled($account) {
        try {
            $conn = $this->db->getConnection();
            $stmt = $conn->prepare("
                SELECT two_factor_enabled 
                FROM accounts_info 
                WHERE account = ? 
                LIMIT 1
            ");
            $stmt->execute([$account]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            return $result['two_factor_enabled'] ?? false;
        } catch (Exception $e) {
            $this->logger->logEvent('2fa_check_failed', [
                'account' => $account,
                'error' => $e->getMessage()
            ], 'ERROR');
            return false;
        }
    }
}