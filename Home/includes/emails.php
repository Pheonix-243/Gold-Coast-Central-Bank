<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
require_once 'phpmailer/Exception.php';
require_once 'phpmailer/PHPMailer.php';
require_once 'phpmailer/SMTP.php';

require_once 'security_logger.php';

class SecureEmailService {
    private $logger;
    
    public function __construct() {
        $this->logger = new SecurityLogger();
    }
    
    public function sendOTP($email, $otp, $action, $expiryMinutes = 5) {
        try {
            // Validate email
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                throw new Exception("Invalid email address");
            }
            
            $mail = new PHPMailer(true);
            
            // Server settings
            $mail->isSMTP();
            $mail->Host = "smtp.gmail.com";
            $mail->Port = 465;
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
            $mail->SMTPAuth = true;
            $mail->Username = "myphp0068@gmail.com";
            $mail->Password = "bbenmzofviglbmzb";
            
            // Recipients
            $mail->setFrom("myphp0068@gmail.com", "Gold Coast Central Bank");
            $mail->addAddress($email);
            
            // Content
            $subject = $this->getOTPSubject($action);
            $body = $this->getOTPEmailBody($otp, $action, $expiryMinutes);
            $plainBody = $this->getOTPEmailBodyPlain($otp, $action, $expiryMinutes);
            
            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body = $body;
            $mail->AltBody = $plainBody;
            
            // Send email
            $mail->send();
            
            $this->logger->logEvent('otp_email_sent', [
                'email' => $email,
                'action' => $action,
                'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
            ]);
            
            return ['status' => 'success', 'message' => 'OTP sent successfully'];
            
        } catch (Exception $e) {
            $this->logger->logEvent('otp_email_failed', [
                'email' => $email,
                'action' => $action,
                'error' => $e->getMessage(),
                'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
            ], 'ERROR');
            return ['status' => 'error', 'message' => 'Failed to send OTP email: ' . $e->getMessage()];
        }
    }
    
    private function getOTPSubject($action) {
        $subjects = [
            'login_verification' => 'Your Login Verification Code',
            'transaction_verification' => 'Transaction Verification Required',
            'email_change' => 'Email Change Verification',
            'password_reset' => 'Password Reset Verification',
            'account_unlock' => 'Account Unlock Verification'
        ];
        
        return $subjects[$action] ?? 'Your Verification Code';
    }
    
    private function getOTPEmailBody($otp, $action, $expiryMinutes) {
        $actionText = str_replace('_', ' ', ucwords($action));
        
        return "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='UTF-8'>
            <title>OTP Verification</title>
            <style>
                body { font-family: Arial, sans-serif; margin: 0; padding: 20px; background-color: #f5f5f5; }
                .container { max-width: 600px; margin: 0 auto; background-color: white; padding: 30px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
                .header { text-align: center; border-bottom: 2px solid #1e40af; padding-bottom: 20px; margin-bottom: 30px; }
                .logo { color: #1e40af; font-size: 24px; font-weight: bold; }
                .otp-code { background-color: #f8fafc; border: 2px dashed #1e40af; padding: 20px; text-align: center; margin: 30px 0; border-radius: 8px; }
                .otp-number { font-size: 32px; font-weight: bold; color: #1e40af; letter-spacing: 4px; }
                .warning { background-color: #fef2f2; border-left: 4px solid #ef4444; padding: 15px; margin: 20px 0; }
                .footer { text-align: center; margin-top: 30px; padding-top: 20px; border-top: 1px solid #e5e7eb; color: #6b7280; font-size: 14px; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <div class='logo'>Gold Coast Central Bank</div>
                </div>
                
                <h2>Security Verification Required</h2>
                <p>Hello,</p>
                <p>We received a request for <strong>{$actionText}</strong> on your account. Please use the following verification code to proceed:</p>
                
                <div class='otp-code'>
                    <div class='otp-number'>{$otp}</div>
                    <p style='margin: 10px 0 0 0; color: #6b7280;'>This code expires in {$expiryMinutes} minutes</p>
                </div>
                
                <div class='warning'>
                    <strong>Security Notice:</strong> Never share this code with anyone. Our staff will never ask for your verification code.
                </div>
                
                <p>If you didn't request this verification, please contact our security team immediately.</p>
                
                <div class='footer'>
                    <p>Gold Coast Central Bank - Secure Banking Solutions</p>
                    <p>This is an automated message. Please do not reply to this email.</p>
                </div>
            </div>
        </body>
        </html>";
    }
    
    private function getOTPEmailBodyPlain($otp, $action, $expiryMinutes) {
        $actionText = str_replace('_', ' ', ucwords($action));
        
        return "Gold Coast Central Bank\n\n" .
               "Security Verification Required\n\n" .
               "We received a request for {$actionText} on your account.\n\n" .
               "Your verification code is: {$otp}\n\n" .
               "This code expires in {$expiryMinutes} minutes.\n\n" .
               "SECURITY NOTICE: Never share this code with anyone. Our staff will never ask for your verification code.\n\n" .
               "If you didn't request this verification, please contact our security team immediately.\n\n" .
               "Gold Coast Central Bank - Secure Banking Solutions";
    }
}

// Legacy function for backward compatibility
function email_send($address, $header, $msg) {
    try {
        $emailService = new SecureEmailService();
        $mail = new PHPMailer(true);
        
        $mail->isSMTP();
        $mail->Host = "smtp.gmail.com";
        $mail->Port = 465;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        $mail->SMTPAuth = true;
        $mail->Username = "myphp0068@gmail.com";
        $mail->Password = "bbenmzofviglbmzb";
        $mail->addAddress($address);
        $mail->setFrom("myphp0068@gmail.com", "Gold Coast Central Bank");
        $mail->Subject = $header;
        $mail->Body = $msg;
        
        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log('Email send failed: ' . $e->getMessage());
        return false;
    }
}
?>