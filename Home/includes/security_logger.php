<?php
/**
 * Security Event Logger for Banking System
 * Comprehensive audit trail for all security events
 */

class SecurityLogger {
    private $db;
    private $logFile;
    
    public function __construct() {
        $this->db = DatabaseConnection::getInstance();
        $this->logFile = __DIR__ . '/../logs/security_' . date('Y-m-d') . '.log';
        $this->ensureLogDirectory();
    }
    
    private function ensureLogDirectory() {
        $logDir = dirname($this->logFile);
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }
    }
    
    public function logEvent($eventType, $data = [], $severity = 'INFO') {
        $logEntry = [
            'timestamp' => date('Y-m-d H:i:s'),
            'event_type' => $eventType,
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
            'session_id' => session_id(),
            'severity' => $severity,
            'data' => $data
        ];
        
        // Log to file
        $this->logToFile($logEntry);
        
        // Log to database for critical events
        if (in_array($severity, ['WARNING', 'ERROR', 'CRITICAL'])) {
            $this->logToDatabase($logEntry);
        }
    }
    
    private function logToFile($entry) {
        $logLine = sprintf(
            "[%s] %s - %s - IP: %s - Data: %s\n",
            $entry['timestamp'],
            $entry['severity'],
            $entry['event_type'],
            $entry['ip_address'],
            json_encode($entry['data'])
        );
        
        file_put_contents($this->logFile, $logLine, FILE_APPEND | LOCK_EX);
    }
    
    private function logToDatabase($entry) {
        try {
            $conn = $this->db->getConnection();
            $stmt = $conn->prepare("
                INSERT INTO security_logs (event_type, ip_address, user_agent, session_id, severity, data, created_at) 
                VALUES (?, ?, ?, ?, ?, ?, NOW())
            ");
            
            $stmt->execute([
                $entry['event_type'],
                $entry['ip_address'],
                $entry['user_agent'],
                $entry['session_id'],
                $entry['severity'],
                json_encode($entry['data'])
            ]);
        } catch (Exception $e) {
            error_log("Failed to log to database: " . $e->getMessage());
        }
    }
    
    public function logOTPGeneration($email, $action, $ip) {
        $this->logEvent('otp_generated', [
            'email' => $email,
            'action' => $action,
            'ip' => $ip
        ], 'INFO');
    }
    
    public function logOTPVerification($email, $action, $success, $attempts = 0) {
        $this->logEvent('otp_verification', [
            'email' => $email,
            'action' => $action,
            'success' => $success,
            'attempts' => $attempts
        ], $success ? 'INFO' : 'WARNING');
    }
    
    public function logSuspiciousActivity($type, $details) {
        $this->logEvent('suspicious_activity', [
            'type' => $type,
            'details' => $details
        ], 'WARNING');
    }
    
    public function logSecurityViolation($violation, $details) {
        $this->logEvent('security_violation', [
            'violation' => $violation,
            'details' => $details
        ], 'CRITICAL');
    }
}
?>
