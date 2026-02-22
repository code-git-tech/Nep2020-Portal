<?php
require_once 'header.php';
require_once 'sidebar.php';

$notifications = getParentNotifications($_SESSION['user_id']);
$unread_count = count(array_filter($notifications, function($n) { return !$n['is_read']; }));

if (isset($_GET['mark_read'])) {
    markNotificationRead($_GET['mark_read']);
    header('Location: notifications.php');
    exit();
}

if (isset($_GET['mark_all_read'])) {
    markAllNotificationsRead($_SESSION['user_id']);
    header('Location: notifications.php');
    exit();
}

function timeAgo($timestamp) {
    $time_ago = strtotime($timestamp);
    $current_time = time();
    $time_difference = $current_time - $time_ago;
    $seconds = $time_difference;
    
    $minutes = round($seconds / 60);
    $hours = round($seconds / 3600);
    $days = round($seconds / 86400);
    $weeks = round($seconds / 604800);
    $months = round($seconds / 2629440);
    $years = round($seconds / 31553280);
    
    if ($seconds <= 60) {
        return "Just Now";
    } else if ($minutes <= 60) {
        return ($minutes == 1) ? "1 minute ago" : "$minutes minutes ago";
    } else if ($hours <= 24) {
        return ($hours == 1) ? "1 hour ago" : "$hours hours ago";
    } else if ($days <= 7) {
        return ($days == 1) ? "yesterday" : "$days days ago";
    } else if ($weeks <= 4.3) {
        return ($weeks == 1) ? "1 week ago" : "$weeks weeks ago";
    } else if ($months <= 12) {
        return ($months == 1) ? "1 month ago" : "$months months ago";
    } else {
        return ($years == 1) ? "1 year ago" : "$years years ago";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notifications - EduTrack</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .notification-card {
            background: white;
            border-radius: 15px;
            padding: 20px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.05);
            margin-bottom: 15px;
            transition: all 0.3s;
            border-left: 4px solid transparent;
        }
        .notification-card.unread {
            background: #f0f3ff;
            border-left-color: #667eea;
        }
        .notification-card:hover {
            transform: translateX(5px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.1);
        }
        .notification-icon {
            width: 50px;
            height: 50px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
        }
        .icon-info { background: #17a2b820; color: #17a2b8; }
        .icon-success { background: #28a74520; color: #28a745; }
        .icon-warning { background: #ffc10720; color: #ffc107; }
        .icon-danger { background: #dc354520; color: #dc3545; }
        
        .notification-time {
            font-size: 0.85rem;
            color: #999;
        }
        .notification-actions {
            opacity: 0;
            transition: opacity 0.3s;
        }
        .notification-card:hover .notification-actions {
            opacity: 1;
        }
        .btn-mark-read {
            padding: 5px 10px;
            border-radius: 5px;
            font-size: 0.85rem;
            color: #667eea;
            cursor: pointer;
            text-decoration: none;
        }
        .btn-mark-read:hover {
            background: #667eea20;
        }
        .notification-header {
            background: white;
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 30px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.05);
        }
        .unread-badge {
            background: #dc3545;
            color: white;
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 0.85rem;
        }
    </style>
</head>
<body>
    <div class="main-content">
        <div class="container-fluid">
            <!-- Page Header -->
            <div class="row mb-4">
                <div class="col-12">
                    <div class="notification-header d-flex justify-content-between align-items-center">
                        <div>
                            <h2 class="mb-1">Notifications</h2>
                            <p class="text-muted mb-0">Stay updated with alerts and reminders</p>
                        </div>
                        <div class="d-flex align-items-center">
                            <?php if ($unread_count > 0): ?>
                                <span class="unread-badge me-3"><?php echo $unread_count; ?> unread</span>
                                <a href="?mark_all_read=1" class="btn btn-outline-primary">
                                    <i class="fas fa-check-double"></i> Mark All as Read
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Notifications List -->
            <div class="row">
                <div class="col-12">
                    <?php if (empty($notifications)): ?>
                        <div class="alert alert-info">
                            <i class="fas fa-bell-slash"></i> No notifications found.
                        </div>
                    <?php else: ?>
                        <?php foreach ($notifications as $notification): ?>
                        <div class="notification-card <?php echo !$notification['is_read'] ? 'unread' : ''; ?>">
                            <div class="row align-items-center">
                                <div class="col-auto">
                                    <div class="notification-icon icon-<?php echo $notification['type']; ?>">
                                        <i class="fas 
                                            <?php 
                                            echo $notification['type'] == 'info' ? 'fa-info-circle' : 
                                                ($notification['type'] == 'success' ? 'fa-check-circle' : 
                                                ($notification['type'] == 'warning' ? 'fa-exclamation-triangle' : 'fa-times-circle')); 
                                            ?>">
                                        </i>
                                    </div>
                                </div>
                                
                                <div class="col">
                                    <div class="d-flex justify-content-between align-items-start">
                                        <div>
                                            <h6 class="mb-1"><?php echo htmlspecialchars($notification['title']); ?></h6>
                                            <p class="mb-2"><?php echo htmlspecialchars($notification['message']); ?></p>
                                            <span class="notification-time">
                                                <i class="far fa-clock me-1"></i>
                                                <?php echo timeAgo($notification['created_at']); ?>
                                            </span>
                                        </div>
                                        
                                        <?php if (!$notification['is_read']): ?>
                                        <div class="notification-actions">
                                            <a href="?mark_read=<?php echo $notification['id']; ?>" class="btn-mark-read">
                                                <i class="fas fa-check me-1"></i>Mark as read
                                            </a>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</body>
</html>