<?php
require_once('../includes/auth.php');
require_once('../includes/notification.php');

$notification = new NotificationSystem($con);
$notifications = $notification->getNotifications($_SESSION['client_account'], 50, false);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gold Coast Central Bank - Notifications</title>
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <!-- Custom CSS -->
    <link rel="stylesheet" href="../dashboard/style.css">
    <style>
        .notifications-container {
            max-width: 800px;
            margin: 0 auto;
        }
        
        .notifications-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        
        .notification-filters {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
        }
        
        .notification-filters button {
            padding: 8px 15px;
            border-radius: 20px;
            border: 1px solid var(--light-gray);
            background: white;
            cursor: pointer;
        }
        
        .notification-filters button.active {
            background: var(--primary-gold);
            color: var(--navy-blue);
            border-color: var(--primary-gold);
        }
        
        .notification-item {
            display: flex;
            padding: 15px;
            margin-bottom: 15px;
            background: white;
            border-radius: 8px;
            box-shadow: var(--shadow-sm);
        }
        
        .notification-item.unread {
            border-left: 3px solid var(--primary-gold);
        }
        
        .notification-icon {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background-color: rgba(218, 165, 32, 0.1);
            color: var(--primary-gold);
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 15px;
            flex-shrink: 0;
        }
        
        .notification-content {
            flex: 1;
        }
        
        .notification-title {
            font-weight: 600;
            color: var(--navy-blue);
            margin-bottom: 5px;
        }
        
        .notification-message {
            color: var(--medium-gray);
            margin-bottom: 5px;
        }
        
        .notification-meta {
            display: flex;
            justify-content: space-between;
            font-size: 12px;
            color: var(--medium-gray);
        }
        
        .notification-actions {
            display: flex;
            gap: 10px;
        }
        
        .notification-actions button {
            background: none;
            border: none;
            color: var(--medium-gray);
            cursor: pointer;
            font-size: 12px;
        }
        
        .notification-actions button:hover {
            color: var(--navy-blue);
        }
        
        .no-notifications {
            text-align: center;
            padding: 40px;
            color: var(--medium-gray);
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Include your sidebar/navigation here (same as other pages) -->
        
        <section class="main_content">
            <div class="topbar">
                <!-- Include your topbar here (same as other pages) -->
            </div>
            
            <section class="content_section">
                <div class="notifications-container">
                    <div class="notifications-header">
                        <h1>Notifications</h1>
                        <button id="mark-all-read" class="btn btn-primary">
                            <i class="fas fa-check-circle"></i> Mark all as read
                        </button>
                    </div>
                    
                    <div class="notification-filters">
                        <button class="active" data-filter="all">All</button>
                        <button data-filter="unread">Unread</button>
                        <button data-filter="transaction">Transactions</button>
                        <button data-filter="security">Security</button>
                    </div>
                    
                    <div class="notifications-list">
                        <?php if (empty($notifications)): ?>
                            <div class="no-notifications">
                                <i class="fas fa-bell-slash" style="font-size: 24px; margin-bottom: 10px;"></i>
                                <p>No notifications yet</p>
                            </div>
                        <?php else: ?>
                            <?php foreach ($notifications as $notif): ?>
                                <div class="notification-item <?= $notif['is_read'] ? '' : 'unread' ?>" 
                                     data-type="<?= $notif['type'] ?>">
                                    <div class="notification-icon">
                                        <?php 
                                            $icons = [
                                                'transaction' => 'fa-exchange-alt',
                                                'login' => 'fa-sign-in-alt',
                                                'security' => 'fa-shield-alt',
                                                'profile_update' => 'fa-user-edit',
                                                'system' => 'fa-info-circle'
                                            ];
                                            $icon = $icons[$notif['type']] ?? 'fa-bell';
                                        ?>
                                        <i class="fas <?= $icon ?>"></i>
                                    </div>
                                    <div class="notification-content">
                                        <div class="notification-title"><?= htmlspecialchars($notif['title']) ?></div>
                                        <div class="notification-message"><?= htmlspecialchars($notif['message']) ?></div>
                                        <div class="notification-meta">
                                            <span><?= $notif['time_ago'] ?></span>
                                            <div class="notification-actions">
                                                <?php if (!$notif['is_read']): ?>
                                                    <button class="mark-read" data-id="<?= $notif['id'] ?>">
                                                        <i class="fas fa-check"></i> Mark as read
                                                    </button>
                                                <?php endif; ?>
                                                <?php if ($notif['is_deletable']): ?>
                                                    <button class="delete-notification" data-id="<?= $notif['id'] ?>">
                                                        <i class="fas fa-trash"></i> Delete
                                                    </button>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </section>
        </section>
    </div>
    
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Filter notifications
        document.querySelectorAll('.notification-filters button').forEach(btn => {
            btn.addEventListener('click', function() {
                document.querySelector('.notification-filters button.active').classList.remove('active');
                this.classList.add('active');
                
                const filter = this.dataset.filter;
                const items = document.querySelectorAll('.notification-item');
                
                items.forEach(item => {
                    if (filter === 'all') {
                        item.style.display = 'flex';
                    } else if (filter === 'unread') {
                        item.style.display = item.classList.contains('unread') ? 'flex' : 'none';
                    } else {
                        item.style.display = item.dataset.type === filter ? 'flex' : 'none';
                    }
                });
            });
        });
        
        // Mark as read
        document.querySelectorAll('.mark-read').forEach(btn => {
            btn.addEventListener('click', function(e) {
                e.stopPropagation();
                const id = this.dataset.id;
                const item = this.closest('.notification-item');
                
                fetch('../scripts/mark_notification_read.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({ id: id })
                }).then(response => response.json())
                  .then(data => {
                      if (data.success) {
                          item.classList.remove('unread');
                          this.remove();
                      }
                  });
            });
        });
        
        // Delete notification
        document.querySelectorAll('.delete-notification').forEach(btn => {
            btn.addEventListener('click', function(e) {
                e.stopPropagation();
                const id = this.dataset.id;
                const item = this.closest('.notification-item');
                
                fetch('../scripts/delete_notification.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({ id: id })
                }).then(response => response.json())
                  .then(data => {
                      if (data.success) {
                          item.remove();
                      }
                  });
            });
        });
        
        // Mark all as read
        document.getElementById('mark-all-read').addEventListener('click', function() {
            fetch('../scripts/mark_all_read.php', {
                method: 'POST'
            }).then(response => response.json())
              .then(data => {
                  if (data.success) {
                      document.querySelectorAll('.notification-item.unread').forEach(item => {
                          item.classList.remove('unread');
                          const markReadBtn = item.querySelector('.mark-read');
                          if (markReadBtn) markReadBtn.remove();
                      });
                  }
              });
        });
    });
    </script>
</body>
</html>