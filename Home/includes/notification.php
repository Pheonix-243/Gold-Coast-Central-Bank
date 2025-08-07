<?php
require_once('conn.php');
require_once 'emails.php';

class NotificationSystem {
    private $con;
    
    public function __construct($connection) {
        $this->con = $connection;
    }
    
    /**
     * Send a notification to a user
     * 
     * @param string $account The account number to notify
     * @param string $type Notification type (transaction, login, security, profile_update, system)
     * @param string $title Notification title
     * @param string $message Notification message
     * @param array $metadata Additional data (optional)
     * @param bool $sendEmail Whether to send an email notification
     * @param bool $isDeletable Whether the notification can be deleted
     * @return bool True on success
     */
    public function sendNotification(
        $account, 
        $type, 
        $title, 
        $message, 
        $metadata = null, 
        $sendEmail = false, 
        $isDeletable = true
    ) {
        // Insert into database
        $sql = "INSERT INTO notifications 
                (account, type, title, message, is_deletable, metadata) 
                VALUES (?, ?, ?, ?, ?, ?)";
        
        $stmt = mysqli_prepare($this->con, $sql);
        $jsonMetadata = $metadata ? json_encode($metadata) : null;
        
        mysqli_stmt_bind_param($stmt, "ssssis", 
            $account, $type, $title, $message, $isDeletable, $jsonMetadata);
        
        $dbSuccess = mysqli_stmt_execute($stmt);
        
        // Send email if requested
        if ($sendEmail) {
            $this->sendEmailNotification($account, $title, $message);
        }
        
        return $dbSuccess;
    }
    
    /**
     * Get unread notifications count for a user
     */
    public function getUnreadCount($account) {
        $sql = "SELECT COUNT(*) as count FROM notifications 
                WHERE account = ? AND is_read = 0";
        $stmt = mysqli_prepare($this->con, $sql);
        mysqli_stmt_bind_param($stmt, "s", $account);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $row = mysqli_fetch_assoc($result);
        return $row['count'] ?? 0;
    }
    
    /**
     * Get recent notifications for a user
     */
    public function getNotifications($account, $limit = 10, $unreadOnly = false) {
        $sql = "SELECT * FROM notifications 
                WHERE account = ? " . 
                ($unreadOnly ? "AND is_read = 0 " : "") . 
                "ORDER BY created_at DESC LIMIT ?";
        
        $stmt = mysqli_prepare($this->con, $sql);
        mysqli_stmt_bind_param($stmt, "si", $account, $limit);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        
        $notifications = [];
        while ($row = mysqli_fetch_assoc($result)) {
            $notifications[] = [
                'id' => $row['id'],
                'type' => $row['type'],
                'title' => $row['title'],
                'message' => $row['message'],
                'is_read' => (bool)$row['is_read'],
                'is_deletable' => (bool)$row['is_deletable'],
                'metadata' => $row['metadata'] ? json_decode($row['metadata'], true) : null,
                'created_at' => $row['created_at'],
                'time_ago' => $this->timeAgo($row['created_at'])
            ];
        }
        
        return $notifications;
    }
    
    /**
     * Mark notification as read
     */
    public function markAsRead($notificationId, $account) {
        $sql = "UPDATE notifications SET is_read = 1 
                WHERE id = ? AND account = ?";
        $stmt = mysqli_prepare($this->con, $sql);
        mysqli_stmt_bind_param($stmt, "is", $notificationId, $account);
        return mysqli_stmt_execute($stmt);
    }
    
    /**
     * Delete a notification (if deletable)
     */
    public function deleteNotification($notificationId, $account) {
        $sql = "DELETE FROM notifications 
                WHERE id = ? AND account = ? AND is_deletable = 1";
        $stmt = mysqli_prepare($this->con, $sql);
        mysqli_stmt_bind_param($stmt, "is", $notificationId, $account);
        return mysqli_stmt_execute($stmt);
    }
    
    /**
     * Send email notification
     */
    private function sendEmailNotification($account, $subject, $message) {
        // Get user email
        $sql = "SELECT email FROM accountsholder WHERE account = ?";
        $stmt = mysqli_prepare($this->con, $sql);
        mysqli_stmt_bind_param($stmt, "s", $account);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $user = mysqli_fetch_assoc($result);
        
        if ($user && !empty($user['email'])) {
            email_send($user['email'], $subject, $message);
        }
    }
    
    /**
     * Format time as "X minutes/hours/days ago"
     */
    private function timeAgo($datetime) {
        $time = strtotime($datetime);
        $timeDiff = time() - $time;
        
        if ($timeDiff < 60) {
            return "Just now";
        } elseif ($timeDiff < 3600) {
            $mins = floor($timeDiff / 60);
            return $mins . " min" . ($mins > 1 ? "s" : "") . " ago";
        } elseif ($timeDiff < 86400) {
            $hours = floor($timeDiff / 3600);
            return $hours . " hour" . ($hours > 1 ? "s" : "") . " ago";
        } else {
            $days = floor($timeDiff / 86400);
            return $days . " day" . ($days > 1 ? "s" : "") . " ago";
        }
    }
}
?>