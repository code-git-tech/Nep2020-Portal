<?php
$current_page = basename($_SERVER['PHP_SELF']);

// Get unread notifications count for badge
require_once '../config/db.php';
$unread_count = 0;
if (isset($_SESSION['user_id'])) {
    try {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE target_role IN ('all', 'parent') AND id NOT IN (SELECT notification_id FROM user_notifications WHERE user_id = ?)");
        $stmt->execute([$_SESSION['user_id']]);
        $unread_count = $stmt->fetchColumn();
    } catch(PDOException $e) {
        $unread_count = 0;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap');

        :root {
            --sidebar-width: 280px;
            --sidebar-collapsed-width: 80px;
            --primary: #4361ee;
            --primary-light: #4895ef;
            --secondary: #3f37c9;
            --success: #4cc9f0;
            --danger: #f72585;
            --warning: #f8961e;
            --dark: #1b2635;
            --darker: #0f172a;
            --light: #f8f9fa;
            --gray: #6c757d;
            --sidebar-bg: linear-gradient(180deg, #1b2635 0%, #2a3a4f 100%);
            --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: #f4f7fc;
        }

        /* Sidebar Container */
        .sidebar {
            width: var(--sidebar-width);
            background: var(--sidebar-bg);
            position: fixed;
            top: 0;
            left: 0;
            height: 100vh;
            overflow-y: auto;
            overflow-x: hidden;
            z-index: 1000;
            transition: var(--transition);
            box-shadow: 4px 0 20px rgba(0, 0, 0, 0.15);
            scrollbar-width: thin;
            scrollbar-color: rgba(255, 255, 255, 0.2) rgba(255, 255, 255, 0.05);
        }

        .sidebar::-webkit-scrollbar {
            width: 5px;
        }

        .sidebar::-webkit-scrollbar-track {
            background: rgba(255, 255, 255, 0.05);
        }

        .sidebar::-webkit-scrollbar-thumb {
            background: rgba(255, 255, 255, 0.2);
            border-radius: 10px;
        }

        .sidebar::-webkit-scrollbar-thumb:hover {
            background: rgba(255, 255, 255, 0.3);
        }

        .sidebar.collapsed {
            width: var(--sidebar-collapsed-width);
        }

        .sidebar.collapsed .sidebar-header h3,
        .sidebar.collapsed .sidebar-header p,
        .sidebar.collapsed .nav-link span,
        .sidebar.collapsed .nav-label,
        .sidebar.collapsed .user-details,
        .sidebar.collapsed .sidebar-footer span {
            display: none;
        }

        .sidebar.collapsed .logo-wrapper {
            width: 50px;
            height: 50px;
            margin: 0 auto;
        }

        .sidebar.collapsed .logo-wrapper i {
            font-size: 24px;
        }

        .sidebar.collapsed .nav-link {
            justify-content: center;
            padding: 14px 0;
        }

        .sidebar.collapsed .nav-link i {
            margin-right: 0;
            font-size: 1.3rem;
        }

        .sidebar.collapsed .user-avatar {
            width: 40px;
            height: 40px;
            margin: 0 auto;
        }

        /* Sidebar Header */
        .sidebar-header {
            padding: 24px 20px;
            text-align: center;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            position: relative;
        }

        .logo-wrapper {
            width: 70px;
            height: 70px;
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            border-radius: 18px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 12px;
            box-shadow: 0 10px 30px rgba(67, 97, 238, 0.3);
            transition: var(--transition);
            position: relative;
            overflow: hidden;
        }

        .logo-wrapper::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: linear-gradient(
                45deg,
                transparent,
                rgba(255, 255, 255, 0.1),
                transparent
            );
            transform: rotate(45deg);
            animation: shimmer 3s infinite;
        }

        @keyframes shimmer {
            0% { transform: translateX(-100%) rotate(45deg); }
            100% { transform: translateX(100%) rotate(45deg); }
        }

        .logo-wrapper i {
            font-size: 32px;
            color: white;
            position: relative;
            z-index: 1;
        }

        .sidebar-header h3 {
            color: white;
            font-weight: 700;
            margin: 0;
            font-size: 1.4rem;
            letter-spacing: 0.5px;
        }

        .sidebar-header p {
            color: rgba(255, 255, 255, 0.8);
            font-size: 0.8rem;
            margin-top: 4px;
        }

        /* Toggle Button */
        .sidebar-toggle-btn {
            position: absolute;
            top: 20px;
            right: -12px;
            width: 24px;
            height: 24px;
            background: white;
            border: 2px solid var(--primary);
            border-radius: 50%;
            color: var(--primary);
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            font-size: 0.8rem;
            transition: var(--transition);
            z-index: 1001;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.2);
        }

        .sidebar-toggle-btn:hover {
            background: var(--primary);
            color: white;
            transform: scale(1.1);
        }

        .sidebar.collapsed .sidebar-toggle-btn i {
            transform: rotate(180deg);
        }

        /* User Info */
        .user-info {
            padding: 20px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .user-avatar {
            width: 48px;
            height: 48px;
            background: linear-gradient(135deg, var(--primary), var(--primary-light));
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.3rem;
            font-weight: 600;
            box-shadow: 0 5px 15px rgba(67, 97, 238, 0.3);
        }

        .user-details {
            flex: 1;
        }

        .user-name {
            color: white;
            font-weight: 600;
            font-size: 0.95rem;
            margin-bottom: 4px;
        }

        .user-role {
            color: rgba(255, 255, 255, 0.8);
            font-size: 0.8rem;
            display: flex;
            align-items: center;
            gap: 4px;
        }

        .user-role i {
            font-size: 0.7rem;
            color: var(--success);
        }

        /* Navigation Menu */
        .nav-menu {
            padding: 16px 12px;
            list-style: none;
        }

        .nav-label {
            padding: 16px 16px 8px;
            color: rgba(255, 255, 255, 0.7);
            font-size: 0.7rem;
            text-transform: uppercase;
            letter-spacing: 1px;
            font-weight: 600;
        }

        .nav-item {
            margin-bottom: 4px;
            animation: slideIn 0.3s ease forwards;
            animation-delay: calc(var(--item-index) * 0.05s);
            opacity: 0;
        }

        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateX(-10px);
            }
            to {
                opacity: 1;
                transform: translateX(0);
            }
        }

        .nav-link {
            display: flex;
            align-items: center;
            padding: 12px 16px;
            color: rgba(255, 255, 255, 0.9);
            text-decoration: none;
            border-radius: 12px;
            transition: var(--transition);
            font-weight: 500;
            position: relative;
            overflow: hidden;
        }

        .nav-link::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 0;
            height: 100%;
            background: rgba(255, 255, 255, 0.1);
            transition: width 0.3s ease;
            z-index: 0;
        }

        .nav-link:hover::before {
            width: 100%;
        }

        .nav-link i {
            width: 24px;
            font-size: 1.2rem;
            margin-right: 12px;
            transition: var(--transition);
            position: relative;
            z-index: 1;
            color: rgba(255, 255, 255, 0.9);
        }

        .nav-link span {
            font-size: 0.95rem;
            position: relative;
            z-index: 1;
            color: white;
        }

        .nav-link:hover {
            color: white;
            transform: translateX(5px);
        }

        .nav-link:hover i {
            transform: scale(1.1);
            color: white;
        }

        .nav-link.active {
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            color: white;
            box-shadow: 0 8px 20px rgba(67, 97, 238, 0.3);
        }

        .nav-link.active i,
        .nav-link.active span {
            color: white;
        }

        /* Notification Badge */
        .notification-badge {
            position: absolute;
            top: 8px;
            right: 12px;
            background: var(--danger);
            color: white;
            border-radius: 50px;
            padding: 2px 8px;
            font-size: 0.7rem;
            font-weight: 600;
            min-width: 20px;
            text-align: center;
            box-shadow: 0 2px 5px rgba(247, 37, 133, 0.3);
            animation: pulse 2s infinite;
        }

        @keyframes pulse {
            0% {
                box-shadow: 0 0 0 0 rgba(247, 37, 133, 0.7);
            }
            70% {
                box-shadow: 0 0 0 10px rgba(247, 37, 133, 0);
            }
            100% {
                box-shadow: 0 0 0 0 rgba(247, 37, 133, 0);
            }
        }

        /* Divider */
        .nav-divider {
            height: 1px;
            background: rgba(255, 255, 255, 0.2);
            margin: 16px 0;
        }

        /* Mobile Toggle Button */
        .mobile-toggle {
            position: fixed;
            top: 20px;
            left: 20px;
            z-index: 1002;
            width: 45px;
            height: 45px;
            background: var(--primary);
            border: none;
            border-radius: 12px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.1);
            color: white;
            font-size: 1.2rem;
            display: none;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: var(--transition);
        }

        .mobile-toggle:hover {
            background: var(--secondary);
            transform: scale(1.05);
        }

        /* Overlay for mobile */
        .sidebar-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.5);
            z-index: 999;
            display: none;
            backdrop-filter: blur(3px);
            opacity: 0;
            transition: opacity 0.3s ease;
        }

        .sidebar-overlay.show {
            opacity: 1;
        }

        /* Main Content Adjustment */
        .main-content {
            margin-left: var(--sidebar-width);
            padding: 20px;
            transition: var(--transition);
            min-height: 100vh;
            background: #f4f7fc;
        }

        .main-content.expanded {
            margin-left: var(--sidebar-collapsed-width);
        }

        /* Tooltip for collapsed state */
        .sidebar.collapsed .nav-link {
            position: relative;
        }

        .sidebar.collapsed .nav-link:hover span {
            display: block !important;
            position: absolute;
            left: 100%;
            top: 50%;
            transform: translateY(-50%);
            background: var(--dark);
            color: white;
            padding: 8px 12px;
            border-radius: 8px;
            font-size: 0.85rem;
            white-space: nowrap;
            margin-left: 10px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
            z-index: 1002;
        }

        .sidebar.collapsed .nav-link:hover span::before {
            content: '';
            position: absolute;
            left: -5px;
            top: 50%;
            transform: translateY(-50%);
            border-width: 5px 5px 5px 0;
            border-style: solid;
            border-color: transparent var(--dark) transparent transparent;
        }

        /* Active link indicator */
        .nav-link.active::after {
            content: '';
            position: absolute;
            right: 0;
            top: 50%;
            transform: translateY(-50%);
            width: 3px;
            height: 20px;
            background: white;
            border-radius: 3px 0 0 3px;
        }

        /* Footer */
        .sidebar-footer {
            padding: 16px;
            border-top: 1px solid rgba(255, 255, 255, 0.1);
            font-size: 0.75rem;
            color: rgba(255, 255, 255, 0.7);
            text-align: center;
        }

        .sidebar-footer i {
            color: rgba(255, 255, 255, 0.7);
            margin-right: 4px;
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .sidebar {
                left: calc(var(--sidebar-width) * -1);
                box-shadow: none;
            }

            .sidebar.mobile-open {
                left: 0;
                box-shadow: 4px 0 20px rgba(0, 0, 0, 0.2);
            }

            .sidebar.mobile-open + .sidebar-overlay {
                display: block;
                opacity: 1;
            }

            .sidebar.collapsed {
                width: var(--sidebar-width);
            }

            .sidebar.collapsed .sidebar-header h3,
            .sidebar.collapsed .sidebar-header p,
            .sidebar.collapsed .nav-link span,
            .sidebar.collapsed .nav-label,
            .sidebar.collapsed .user-details,
            .sidebar.collapsed .sidebar-footer span {
                display: block;
            }

            .sidebar.collapsed .logo-wrapper {
                width: 70px;
                height: 70px;
            }

            .sidebar.collapsed .nav-link {
                justify-content: flex-start;
                padding: 12px 16px;
            }

            .sidebar.collapsed .nav-link i {
                margin-right: 12px;
            }

            .sidebar.collapsed .user-avatar {
                width: 48px;
                height: 48px;
            }

            .main-content {
                margin-left: 0 !important;
            }

            .mobile-toggle {
                display: flex;
            }

            .sidebar-toggle-btn {
                display: none;
            }
        }

        /* Hover effects for all text */
        .nav-link:hover span,
        .nav-link:hover i,
        .sidebar-footer:hover,
        .user-info:hover .user-name,
        .user-info:hover .user-role {
            color: white;
        }
    </style>
</head>
<body>
    <!-- Mobile Toggle Button -->
    <button class="mobile-toggle" id="mobileToggle">
        <i class="fas fa-bars"></i>
    </button>

    <!-- Sidebar Overlay -->
    <div class="sidebar-overlay" id="sidebarOverlay"></div>

    <!-- Sidebar -->
    <div class="sidebar" id="sidebar">
        <!-- Toggle Button (Desktop) -->
        <div class="sidebar-toggle-btn" id="toggleSidebar">
            <i class="fas fa-chevron-left"></i>
        </div>

        <!-- Sidebar Header -->
        <div class="sidebar-header">
            <div class="logo-wrapper">
                <i class="fas fa-graduation-cap"></i>
            </div>
            <h3>EduTrack</h3>
            <p>Parent Portal</p>
        </div>

        <!-- User Info -->
        <div class="user-info">
            <div class="user-avatar">
                <?php echo strtoupper(substr($_SESSION['user_name'] ?? 'P', 0, 1)); ?>
            </div>
            <div class="user-details">
                <div class="user-name"><?php echo htmlspecialchars($_SESSION['user_name'] ?? 'Parent'); ?></div>
                <div class="user-role">
                    <i class="fas fa-circle"></i>
                    <span>Online</span>
                </div>
            </div>
        </div>

        <!-- Main Navigation -->
        <div class="nav-label">MAIN</div>
        <ul class="nav-menu">
            <li class="nav-item" style="--item-index: 1;">
                <a href="dashboard.php" class="nav-link <?php echo $current_page == 'dashboard.php' ? 'active' : ''; ?>">
                    <i class="fas fa-tachometer-alt"></i>
                    <span>Dashboard</span>
                </a>
            </li>
            <li class="nav-item" style="--item-index: 2;">
                <a href="alerts.php" class="nav-link <?php echo $current_page == 'alerts.php' ? 'active' : ''; ?>">
                    <i class="fas fa-exclamation-triangle"></i>
                    <span>Alerts</span>
                    <?php if ($unread_count > 0): ?>
                        <span class="notification-badge"><?php echo $unread_count; ?></span>
                    <?php endif; ?>
                </a>
            </li>
            <li class="nav-item" style="--item-index: 3;">
                <a href="behavior-report.php" class="nav-link <?php echo $current_page == 'behavior-report.php' ? 'active' : ''; ?>">
                    <i class="fas fa-chart-line"></i>
                    <span>Behavior Report</span>
                </a>
            </li>
            <li class="nav-item" style="--item-index: 4;">
                <a href="mood-report.php" class="nav-link <?php echo $current_page == 'mood-report.php' ? 'active' : ''; ?>">
                    <i class="fas fa-smile"></i>
                    <span>Mood Report</span>
                </a>
            </li>
        </ul>

        <div class="nav-label">CONSULTATIONS</div>
        <ul class="nav-menu">
            <li class="nav-item" style="--item-index: 5;">
                <a href="book-consultation.php" class="nav-link <?php echo $current_page == 'book-consultation.php' ? 'active' : ''; ?>">
                    <i class="fas fa-calendar-plus"></i>
                    <span>Book Consultation</span>
                </a>
            </li>
            <li class="nav-item" style="--item-index: 6;">
                <a href="consultation-history.php" class="nav-link <?php echo $current_page == 'consultation-history.php' ? 'active' : ''; ?>">
                    <i class="fas fa-history"></i>
                    <span>Consultation History</span>
                </a>
            </li>
        </ul>

        <div class="nav-label">ACCOUNT</div>
        <ul class="nav-menu">
            <li class="nav-item" style="--item-index: 7;">
                <a href="notifications.php" class="nav-link <?php echo $current_page == 'notifications.php' ? 'active' : ''; ?>">
                    <i class="fas fa-bell"></i>
                    <span>Notifications</span>
                    <?php if ($unread_count > 0): ?>
                        <span class="notification-badge"><?php echo $unread_count; ?></span>
                    <?php endif; ?>
                </a>
            </li>
            <li class="nav-item" style="--item-index: 8;">
                <a href="child-profile.php" class="nav-link <?php echo $current_page == 'child-profile.php' ? 'active' : ''; ?>">
                    <i class="fas fa-user-circle"></i>
                    <span>Child Profile</span>
                </a>
            </li>
            <li class="nav-item" style="--item-index: 9;">
                <a href="settings.php" class="nav-link <?php echo $current_page == 'settings.php' ? 'active' : ''; ?>">
                    <i class="fas fa-cog"></i>
                    <span>Settings</span>
                </a>
            </li>
            
            <li class="nav-divider"></li>
            
            <li class="nav-item" style="--item-index: 10;">
                <a href="../logout.php" class="nav-link">
                    <i class="fas fa-sign-out-alt"></i>
                    <span>Logout</span>
                </a>
            </li>
        </ul>

        <!-- Footer -->
        <div class="sidebar-footer">
            <i class="fas fa-shield-alt"></i>
            <span>Secure Portal v2.0</span>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const sidebar = document.getElementById('sidebar');
            const toggleBtn = document.getElementById('toggleSidebar');
            const mobileToggle = document.getElementById('mobileToggle');
            const overlay = document.getElementById('sidebarOverlay');
            const mainContent = document.querySelector('.main-content');

            // Check for saved sidebar state
            const sidebarCollapsed = localStorage.getItem('sidebarCollapsed') === 'true';
            if (sidebarCollapsed && window.innerWidth > 768) {
                sidebar.classList.add('collapsed');
                if (mainContent) mainContent.classList.add('expanded');
            }

            // Desktop toggle
            if (toggleBtn) {
                toggleBtn.addEventListener('click', function() {
                    sidebar.classList.toggle('collapsed');
                    if (mainContent) {
                        mainContent.classList.toggle('expanded');
                    }
                    // Save state
                    localStorage.setItem('sidebarCollapsed', sidebar.classList.contains('collapsed'));
                });
            }

            // Mobile toggle
            if (mobileToggle) {
                mobileToggle.addEventListener('click', function() {
                    sidebar.classList.toggle('mobile-open');
                    overlay.classList.toggle('show');
                });
            }

            // Overlay click
            if (overlay) {
                overlay.addEventListener('click', function() {
                    sidebar.classList.remove('mobile-open');
                    overlay.classList.remove('show');
                });
            }

            // Close sidebar on window resize
            window.addEventListener('resize', function() {
                if (window.innerWidth > 768) {
                    sidebar.classList.remove('mobile-open');
                    if (overlay) overlay.classList.remove('show');
                    
                    // Reset collapsed state based on saved preference
                    const wasCollapsed = localStorage.getItem('sidebarCollapsed') === 'true';
                    if (wasCollapsed) {
                        sidebar.classList.add('collapsed');
                        if (mainContent) mainContent.classList.add('expanded');
                    } else {
                        sidebar.classList.remove('collapsed');
                        if (mainContent) mainContent.classList.remove('expanded');
                    }
                }
            });

            // Close sidebar when clicking a nav link on mobile
            if (window.innerWidth <= 768) {
                document.querySelectorAll('.nav-link').forEach(link => {
                    link.addEventListener('click', function() {
                        sidebar.classList.remove('mobile-open');
                        if (overlay) overlay.classList.remove('show');
                    });
                });
            }

            // Keyboard navigation
            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape' && sidebar.classList.contains('mobile-open')) {
                    sidebar.classList.remove('mobile-open');
                    if (overlay) overlay.classList.remove('show');
                }
            });

            // Add active class to current page link
            const currentPage = window.location.pathname.split('/').pop();
            document.querySelectorAll('.nav-link').forEach(link => {
                if (link.getAttribute('href') === currentPage) {
                    link.classList.add('active');
                }
            });
        });
    </script>
</body>
</html>