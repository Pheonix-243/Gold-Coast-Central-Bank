<?php
require_once 'email.php'; 
require_once 'conn.php'; 

class OTPService {
    private $db;
    private $otpExpiryMinutes = 10; // Configurable expiry time
    private $maxAttemptsPerHour = 5; // Rate limiting
    
    public function __construct($dbConnection) {
        $this->db = $dbConnection;
    }
    
    /**
     * Generate a secure OTP
     * @param int $length Length of OTP (default 6)
     * @return string Generated OTP
     */
    public function generateOTP($length = 6) {
        // Ensure length is between 4 and 10 for security/usability balance
        $length = max(4, min(10, (int)$length));
        
        // Generate cryptographically secure random digits
        $otp = '';
        for ($i = 0; $i < $length; $i++) {
            $otp .= random_int(0, 9);
        }
        
        return $otp;
    }
    
    /**
 * Store OTP in database (hashed)
 * @param string $userIdentifier Email or account number
 * @param string $otp Raw OTP value
 * @param string $purpose Purpose of OTP
 * @param string|null $ipAddress User's IP for rate limiting
 * @param array|null $metadata Additional data to store
 * @return bool True on success
 */
public function storeOTP($userIdentifier, $otp, $purpose, $ipAddress = null, $metadata = null) {
    $this->invalidateExistingOTPs($userIdentifier, $purpose);
    
    $tokenHash = password_hash($otp, PASSWORD_DEFAULT);
    $expiresAt = new DateTime("+{$this->otpExpiryMinutes} minutes");
    
    // Properly handle NULL metadata
    $metaJson = null;
    if ($metadata !== null) {
        $metaJson = json_encode($metadata);
        if ($metaJson === false) {
            $metaJson = null; // Fallback to NULL if JSON encoding fails
        }
    }

    $stmt = $this->db->prepare("
        INSERT INTO otp_tokens 
        (user_identifier, token_hash, purpose, expires_at, ip_address, metadata)
        VALUES (?, ?, ?, ?, ?, ?)
    ");
    
    // Bind parameters with proper type specifiers
    $stmt->bind_param(
        "ssssss", 
        $userIdentifier,
        $tokenHash,
        $purpose,
        $expiresAt->format('Y-m-d H:i:s'),
        $ipAddress,
        $metaJson
    );
    
    $result = $stmt->execute();
    $stmt->close();
    
    return $result;
}
    
    /**
     * Send OTP via email
     * @param string $email Recipient email
     * @param string $otp Raw OTP value
     * @param string $purpose Purpose of OTP
     * @return bool True on success
     */
    public function sendOTPByEmail($email, $otp, $purpose) {
        $subject = "Your Gold Coast Central Bank OTP";
        
        // Customize message based on purpose
        $messages = [
            'login' => "Your login verification code is: $otp\n\nThis code will expire in {$this->otpExpiryMinutes} minutes.",
            'reset' => "Your password reset code is: $otp\n\nIf you didn't request this, please contact support immediately.",
            'transaction' => "Your transaction authorization code is: $otp\n\nThis code is required to complete your banking transaction.",
            'default' => "Your verification code is: $otp\n\nThis code will expire in {$this->otpExpiryMinutes} minutes."
        ];
        
        $message = $messages[$purpose] ?? $messages['default'];
        
        // Add security notice
        $message .= "\n\nSecurity notice: Never share this code with anyone. Gold Coast Central Bank will never ask for this code.";
        
        // Use your existing email function
        return email_send($email, $subject, $message);
    }
    
    /**
 * Verify OTP against stored hash
 * @param string $userIdentifier Email or account number
 * @param string $otp Raw OTP value
 * @param string $purpose Purpose of OTP
 * @return array ['success' => bool, 'message' => string]
 */
public function verifyOTP($userIdentifier, $otp, $purpose) {
    $this->cleanExpiredOTPs();
    
    $stmt = $this->db->prepare("
        SELECT id, token_hash, expires_at, used_at 
        FROM otp_tokens 
        WHERE user_identifier = ? 
        AND purpose = ? 
        AND used_at IS NULL
        ORDER BY created_at DESC 
        LIMIT 1
    ");
    $stmt->bind_param("ss", $userIdentifier, $purpose);
    $stmt->execute();
    $result = $stmt->get_result();
    $storedOTP = $result->fetch_assoc();
    $stmt->close();

    if (!$storedOTP) {
        return ['success' => false, 'message' => 'No active OTP found'];
    }

    $now = new DateTime();
    $expiresAt = new DateTime($storedOTP['expires_at']);

    // First check if expired
    if ($now > $expiresAt) {
        return ['success' => false, 'message' => 'OTP has expired'];
    }

    // Then verify the code
    if (password_verify($otp, $storedOTP['token_hash'])) {
        $this->markOTPAsUsed($storedOTP['id']);
        return ['success' => true, 'message' => 'OTP verified successfully'];
    }

    return ['success' => false, 'message' => 'Invalid OTP'];
}
/**
 * Check if user has reached OTP generation limit
 * @param string $userIdentifier Email or account number
 * @param string $ipAddress User's IP
 * @return bool True if rate limited
 */
public function isRateLimited($userIdentifier, $ipAddress) {
    $hourAgo = (new DateTime('-1 hour'))->format('Y-m-d H:i:s');
    
    // Check by user identifier - MySQLi version
    $stmt = $this->db->prepare("
        SELECT COUNT(*) 
        FROM otp_tokens 
        WHERE (user_identifier = ? OR ip_address = ?)
        AND created_at > ?
    ");
    $stmt->bind_param("sss", $userIdentifier, $ipAddress, $hourAgo);
    $stmt->execute();
    $stmt->bind_result($count);
    $stmt->fetch();
    $stmt->close();
    
    return $count >= $this->maxAttemptsPerHour;
}
    

/**
 * Mark OTP as used
 * @param int $otpId OTP record ID
 * @return bool True on success
 */
private function markOTPAsUsed($otpId) {
    $stmt = $this->db->prepare("
        UPDATE otp_tokens 
        SET used_at = NOW() 
        WHERE id = ?
    ");
    $stmt->bind_param("i", $otpId);
    $result = $stmt->execute();
    $stmt->close();
    return $result;
}

    
/**
 * Invalidate existing active OTPs for a user/purpose
 * @param string $userIdentifier Email or account number
 * @param string $purpose Purpose of OTP
 * @return bool True on success
 */
private function invalidateExistingOTPs($userIdentifier, $purpose) {
    $stmt = $this->db->prepare("
        UPDATE otp_tokens 
        SET used_at = NOW() 
        WHERE user_identifier = ? 
        AND purpose = ? 
        AND used_at IS NULL
    ");
    $stmt->bind_param("ss", $userIdentifier, $purpose);
    $result = $stmt->execute();
    $stmt->close();
    return $result;
}

    
/**
 * Clean up expired OTPs
 * @return bool True on success
 */
private function cleanExpiredOTPs() {
    $stmt = $this->db->prepare("
        UPDATE otp_tokens 
        SET used_at = NOW() 
        WHERE expires_at < NOW() 
        AND used_at IS NULL
    ");
    $result = $stmt->execute();
    $stmt->close();
    return $result;
}
}