<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'parent') {
    header('Location: login.php');
    exit();
}

require_once '../config/db.php';
require_once '../includes/parent-functions.php';

// Get unread notifications count
try {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE target_role IN ('all', 'parent') AND id NOT IN (SELECT notification_id FROM user_notifications WHERE user_id = ?)");
    $stmt->execute([$_SESSION['user_id']]);
    $unread_count = $stmt->fetchColumn();
} catch(PDOException $e) {
    $unread_count = 0;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .navbar-top {
            background: white;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            padding: 10px 20px;
            position: fixed;
            top: 0;
            right: 0;
            left: 250px;
            z-index: 1000;
            transition: left 0.3s;
        }
        .navbar-top .dropdown-menu {
            border-radius: 10px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
            border: none;
            padding: 10px 0;
        }
        .navbar-top .dropdown-item {
            padding: 8px 20px;
            color: #333;
        }
        .navbar-top .dropdown-item:hover {
            background: #f8f9fa;
            color: #667eea;
        }
        .notification-badge {
            position: absolute;
            top: 0;
            right: 0;
            background: #dc3545;
            color: white;
            border-radius: 50%;
            padding: 3px 6px;
            font-size: 10px;
            transform: translate(25%, -25%);
        }
        .profile-dropdown {
            display: flex;
            align-items: center;
            cursor: pointer;
            padding: 5px 10px;
            border-radius: 10px;
            transition: background 0.3s;
        }
        .profile-dropdown:hover {
            background: #f8f9fa;
        }
        .profile-dropdown img {
            width: 35px;
            height: 35px;
            border-radius: 50%;
            margin-right: 10px;
            object-fit: cover;
        }
        .profile-dropdown .profile-info {
            margin-right: 10px;
        }
        .profile-dropdown .profile-name {
            font-weight: 600;
            color: #333;
            line-height: 1.2;
        }
        .profile-dropdown .profile-role {
            font-size: 12px;
            color: #666;
        }
        @media (max-width: 768px) {
            .navbar-top {
                left: 0;
            }
        }
    </style>
</head>
<body>
    <nav class="navbar-top">
        <div class="d-flex justify-content-end align-items-center">
            <div class="dropdown me-3">
                <a href="#" class="text-dark position-relative" data-bs-toggle="dropdown">
                    <i class="fas fa-bell fa-lg"></i>
                    <?php if ($unread_count > 0): ?>
                        <span class="notification-badge"><?php echo $unread_count; ?></span>
                    <?php endif; ?>
                </a>
                <div class="dropdown-menu dropdown-menu-end">
                    <a href="notifications.php" class="dropdown-item">View All Notifications</a>
                    <?php if ($unread_count > 0): ?>
                        <a href="mark-notifications-read.php" class="dropdown-item">Mark All as Read</a>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="dropdown">
                <div class="profile-dropdown" data-bs-toggle="dropdown">
                    <img src="../uploads/avatars/default-avatar.png" alt="Profile" onerror="this.src='https://via.placeholder.com/35'">
                    <div class="profile-info">
                        <div class="profile-name"><?php echo htmlspecialchars($_SESSION['user_name']); ?></div>
                        <div class="profile-role">Parent</div>
                    </div>
                    <i class="fas fa-chevron-down"></i>
                </div>
                <div class="dropdown-menu dropdown-menu-end">
                    <a href="profile.php" class="dropdown-item"><i class="fas fa-user me-2"></i>My Profile</a>
                    <a href="child-profile.php" class="dropdown-item"><i class="fas fa-child me-2"></i>Child Profile</a>
                    <div class="dropdown-divider"></div>
                    <a href="settings.php" class="dropdown-item"><i class="fas fa-cog me-2"></i>Settings</a>
                    <a href="../logout.php" class="dropdown-item text-danger"><i class="fas fa-sign-out-alt me-2"></i>Logout</a>
                </div>
            </div>
        </div>
    </nav>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>