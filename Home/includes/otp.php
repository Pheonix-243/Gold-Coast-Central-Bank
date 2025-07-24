<?php
/**
 * Enhanced OTP System - Bank Grade Security
 * Modular, Reusable OTP Verification System
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../config/database.php';
require_once 'emails.php';
require_once 'security_logger.php';

// Enhanced OTP Configuration
define('OTP_LENGTH', 6);
define('OTP_EXPIRY_MINUTES', 5);
define('OTP_COOLDOWN_SECONDS', 60);
define('MAX_OTP_ATTEMPTS', 3);
define('OTP_ATTEMPT_WINDOW_MINUTES', 15);
define('OTP_LOCKOUT_MINUTES', 30);
define('MAX_HOURLY_OTPS', 5);
define('MAX_DAILY_OTPS', 20);

class EnhancedOTPSystem {
    private $db;
    private $emailService;
    private $logger;
    
    public function __construct() {
        $this->db = DatabaseConnection::getInstance();
        $this->emailService = new SecureEmailService();
        $this->logger = new SecurityLogger();
    }
    
    /**
     * Generate and send OTP - Modular function for any verification flow
     */
    public function generateAndSendOTP($email, $action, $sessionKey = null) {
        try {
            // Input validation
            $email = filter_var(trim($email), FILTER_SANITIZE_EMAIL);
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                return $this->errorResponse('Invalid email address');
            }
            
            $action = preg_replace('/[^a-zA-Z0-9_]/', '', $action);
            if (empty($action)) {
                return $this->errorResponse('Invalid action');
            }
            
            $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
            
            // Security checks
            $securityCheck = $this->performSecurityChecks($email, $ip);
            if (!$securityCheck['allowed']) {
                return $this->errorResponse($securityCheck['message']);
            }
            
            // Invalidate all previous OTPs for this email/action combination
            $this->invalidatePreviousOTPs($email, $action);
            
            // Generate cryptographically secure OTP
            $otp = $this->generateSecureOTP();
            $hashedOTP = password_hash($otp, PASSWORD_ARGON2ID, [
                'memory_cost' => 65536, // 64 MB
                'time_cost' => 4,       // 4 iterations
                'threads' => 3          // 3 threads
            ]);
            
            $expiry = date('Y-m-d H:i:s', time() + (OTP_EXPIRY_MINUTES * 60));
            
            // Store OTP in database
            $conn = $this->db->getConnection();
            $stmt = $conn->prepare("
                INSERT INTO otp_verifications (email, otp, action, ip_address, expires_at, session_key) 
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            
            if (!$stmt->execute([$email, $hashedOTP, $action, $ip, $expiry, $sessionKey])) {
                throw new Exception("Failed to store OTP in database");
            }
            
            // Send OTP via email
            $emailResult = $this->emailService->sendOTP($email, $otp, $action, OTP_EXPIRY_MINUTES);
            
            if ($emailResult['status'] !== 'success') {
                throw new Exception("Failed to send OTP email: " . $emailResult['message']);
            }
            
            // Set session variables for verification process
            $_SESSION['otp_context'] = [
                'email' => $email,
                'action' => $action,
                'session_key' => $sessionKey,
                'attempts' => 0,
                'generated_at' => time(),
                'expires_at' => time() + (OTP_EXPIRY_MINUTES * 60)
            ];
            
            $this->logger->logOTPGeneration($email, $action, $ip);
            
            return $this->successResponse('OTP sent successfully', [
                'cooldown_seconds' => OTP_COOLDOWN_SECONDS,
                'expiry_minutes' => OTP_EXPIRY_MINUTES
            ]);
            
        } catch (Exception $e) {
            $this->logger->logEvent('otp_generation_failed', [
                'email' => $email,
                'action' => $action,
                'error' => $e->getMessage()
            ], 'ERROR');
            
            return $this->errorResponse('Failed to generate OTP: ' . $e->getMessage());
        }
    }
    
    /**
     * Verify OTP - Returns verification result without handling redirects
     */
    public function verifyOTP($otp, $email = null, $action = null) {
        try {
            // Get verification context from session or parameters
            $context = $_SESSION['otp_context'] ?? null;
            if (!$context && (!$email || !$action)) {
                return $this->errorResponse('Invalid verification context');
            }
            
            $email = $email ?? $context['email'];
            $action = $action ?? $context['action'];
            
            // Check if OTP verification is still valid
            if ($context && time() > $context['expires_at']) {
                $this->clearOTPContext();
                return $this->errorResponse('OTP session has expired');
            }
            
            // Check attempt limits
            $attempts = $context['attempts'] ?? 0;
            if ($attempts >= MAX_OTP_ATTEMPTS) {
                $this->lockoutUser($email, $action);
                return $this->errorResponse('Maximum verification attempts exceeded. Account temporarily locked.');
            }
            
            // Increment attempt counter
            $_SESSION['otp_context']['attempts'] = $attempts + 1;
            
            // Verify OTP against database
            $verificationResult = $this->verifyOTPFromDatabase($otp, $email, $action);
            
            if ($verificationResult['success']) {
                // Mark OTP as used and clear context
                $this->markOTPAsUsed($email, $action);
                $this->clearOTPContext();
                
                $this->logger->logOTPVerification($email, $action, true, $attempts + 1);
                
                return $this->successResponse('OTP verified successfully', [
                    'verified' => true,
                    'email' => $email,
                    'action' => $action
                ]);
            } else {
                $this->logger->logOTPVerification($email, $action, false, $attempts + 1);
                
                return $this->errorResponse('Invalid OTP code', [
                    'attempts_remaining' => MAX_OTP_ATTEMPTS - ($attempts + 1)
                ]);
            }
            
        } catch (Exception $e) {
            $this->logger->logEvent('otp_verification_failed', [
                'email' => $email,
                'action' => $action,
                'error' => $e->getMessage()
            ], 'ERROR');
            
            return $this->errorResponse('Verification failed: ' . $e->getMessage());
        }
    }
    
    /**
     * Check if resend is allowed based on cooldown
     */
    public function getResendStatus($email = null, $action = null) {
        $context = $_SESSION['otp_context'] ?? null;
        if (!$context && (!$email || !$action)) {
            return ['allowed' => false, 'remaining_seconds' => 0];
        }
        
        $email = $email ?? $context['email'];
        $action = $action ?? $context['action'];
        
        $conn = $this->db->getConnection();
        $stmt = $conn->prepare("
            SELECT created_at 
            FROM otp_verifications 
            WHERE email = ? AND action = ? 
            ORDER BY created_at DESC 
            LIMIT 1
        ");
        
        $stmt->execute([$email, $action]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($row) {
            $lastSent = strtotime($row['created_at']);
            $cooldownRemaining = OTP_COOLDOWN_SECONDS - (time() - $lastSent);
            
            return [
                'allowed' => $cooldownRemaining <= 0,
                'remaining_seconds' => max(0, $cooldownRemaining)
            ];
        }
        
        return ['allowed' => true, 'remaining_seconds' => 0];
    }
    
    /**
     * Check if user has a valid OTP context
     */
    public function hasValidOTPContext() {
        $context = $_SESSION['otp_context'] ?? null;
        return $context && time() <= $context['expires_at'];
    }
    
    /**
     * Get current OTP context information
     */
    public function getOTPContext() {
        return $_SESSION['otp_context'] ?? null;
    }
    
    /**
     * Clear OTP verification context
     */
    public function clearOTPContext() {
        unset($_SESSION['otp_context']);
    }
    
    private function generateSecureOTP() {
        // Use cryptographically secure random number generation
        $otp = '';
        for ($i = 0; $i < OTP_LENGTH; $i++) {
            $otp .= random_int(0, 9);
        }
        return $otp;
    }
    
    private function performSecurityChecks($email, $ip) {
        $conn = $this->db->getConnection();
        
        // Check hourly OTP limit (5 OTPs per hour)
        $stmt = $conn->prepare("
            SELECT COUNT(*) as count 
            FROM otp_verifications 
            WHERE email = ? AND created_at >= NOW() - INTERVAL 1 HOUR
        ");
        $stmt->execute([$email]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($row['count'] >= MAX_HOURLY_OTPS) {
            $this->logger->logSecurityViolation('hourly_otp_limit_exceeded', [
                'email' => $email,
                'count' => $row['count'],
                'limit' => MAX_HOURLY_OTPS
            ]);
            return ['allowed' => false, 'message' => 'Hourly OTP limit exceeded. Maximum 5 OTPs per hour allowed.'];
        }
        
        // Check daily OTP limit
        $stmt = $conn->prepare("
            SELECT COUNT(*) as count 
            FROM otp_verifications 
            WHERE email = ? AND created_at >= NOW() - INTERVAL 24 HOUR
        ");
        $stmt->execute([$email]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($row['count'] >= MAX_DAILY_OTPS) {
            $this->logger->logSecurityViolation('daily_otp_limit_exceeded', [
                'email' => $email,
                'count' => $row['count'],
                'limit' => MAX_DAILY_OTPS
            ]);
            return ['allowed' => false, 'message' => 'Daily OTP limit exceeded'];
        }
        
        // Check cooldown period
        $stmt = $conn->prepare("
            SELECT created_at 
            FROM otp_verifications 
            WHERE (email = ? OR ip_address = ?) 
            AND created_at > NOW() - INTERVAL ? SECOND
            ORDER BY created_at DESC 
            LIMIT 1
        ");
        $stmt->execute([$email, $ip, OTP_COOLDOWN_SECONDS]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($result) {
            return ['allowed' => false, 'message' => 'Please wait before requesting another OTP'];
        }
        
        // Check for lockout
        $stmt = $conn->prepare("
            SELECT lockout_until 
            FROM otp_lockouts 
            WHERE email = ? AND lockout_until > NOW()
        ");
        $stmt->execute([$email]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($result) {
            return ['allowed' => false, 'message' => 'Account temporarily locked due to security violations'];
        }
        
        return ['allowed' => true, 'message' => 'Security checks passed'];
    }
    
    private function invalidatePreviousOTPs($email, $action) {
        $conn = $this->db->getConnection();
        $stmt = $conn->prepare("
            UPDATE otp_verifications 
            SET used = 1, invalidated_at = NOW() 
            WHERE email = ? AND action = ? AND used = 0
        ");
        $stmt->execute([$email, $action]);
    }
    
    private function verifyOTPFromDatabase($otp, $email, $action) {
        $conn = $this->db->getConnection();
        $stmt = $conn->prepare("
            SELECT otp, expires_at 
            FROM otp_verifications 
            WHERE email = ? AND action = ? AND used = 0 AND invalidated_at IS NULL
            ORDER BY created_at DESC 
            LIMIT 1
        ");
        
        $stmt->execute([$email, $action]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($row) {
            if (strtotime($row['expires_at']) < time()) {
                return ['success' => false, 'message' => 'OTP has expired'];
            }
            
            if (password_verify($otp, $row['otp'])) {
                return ['success' => true, 'message' => 'OTP verified'];
            }
        }
        
        return ['success' => false, 'message' => 'Invalid OTP'];
    }
    
    private function markOTPAsUsed($email, $action) {
        $conn = $this->db->getConnection();
        $stmt = $conn->prepare("
            UPDATE otp_verifications 
            SET used = 1, used_at = NOW() 
            WHERE email = ? AND action = ? AND used = 0
        ");
        $stmt->execute([$email, $action]);
    }
    
    private function lockoutUser($email, $action) {
        $conn = $this->db->getConnection();
        $lockoutUntil = date('Y-m-d H:i:s', time() + (OTP_LOCKOUT_MINUTES * 60));
        
        $stmt = $conn->prepare("
            INSERT INTO otp_lockouts (email, action, lockout_until, created_at) 
            VALUES (?, ?, ?, NOW())
            ON DUPLICATE KEY UPDATE 
                lockout_until = VALUES(lockout_until),
                attempt_count = attempt_count + 1,
                created_at = NOW()
        ");
        
        $stmt->execute([$email, $action, $lockoutUntil]);
        
        $this->logger->logSecurityViolation('otp_lockout', [
            'email' => $email,
            'action' => $action,
            'lockout_until' => $lockoutUntil
        ]);
    }
    
    private function successResponse($message, $data = []) {
        return array_merge(['status' => 'success', 'message' => $message], $data);
    }
    
    private function errorResponse($message, $data = []) {
        return array_merge(['status' => 'error', 'message' => $message], $data);
    }
}

// Legacy functions for backward compatibility
function generateAndSendOTP($email, $action, $redirect_url = '') {
    $otpSystem = new EnhancedOTPSystem();
    return $otpSystem->generateAndSendOTP($email, $action);
}

function verifyOTP($email, $otp, $action) {
    $otpSystem = new EnhancedOTPSystem();
    return $otpSystem->verifyOTP($otp, $email, $action);
}

function isOTPVerified() {
    $otpSystem = new EnhancedOTPSystem();
    return $otpSystem->hasValidOTPContext();
}

function clearOTPSession() {
    $otpSystem = new EnhancedOTPSystem();
    $otpSystem->clearOTPContext();
}
?>