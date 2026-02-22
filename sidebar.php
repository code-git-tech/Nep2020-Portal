<?php
require_once '../includes/auth.php';
require_once '../includes/auto_publish.php';

// Run auto-publisher on every page load
autoPublishVideos($pdo);

$current_page = basename($_SERVER['PHP_SELF']);

// Check if this is the main admin
global $pdo;
$stmt = $pdo->prepare("SELECT id FROM users WHERE role = 'admin' ORDER BY id ASC LIMIT 1");
$stmt->execute();
$firstAdmin = $stmt->fetchColumn();
$isMainAdmin = ($_SESSION['user_id'] == $firstAdmin);
?>
<div class="w-64 bg-gray-800 text-white flex flex-col">
    <div class="p-4">
        <h2 class="text-xl font-bold">Admin Panel</h2>
        <p class="text-sm text-gray-400">Welcome, <?= htmlspecialchars($_SESSION['name']) ?></p>
        <?php if ($isMainAdmin): ?>
            <span class="text-xs bg-purple-600 px-2 py-1 rounded mt-2 inline-block">Main Administrator</span>
        <?php endif; ?>
    </div>
    
    <nav class="flex-1 mt-4">
        <a href="dashboard.php" class="flex items-center px-4 py-3 <?= $current_page == 'dashboard.php' ? 'bg-gray-700 border-l-4 border-blue-500' : 'hover:bg-gray-700' ?>">
            <i class="fas fa-tachometer-alt w-6"></i>
            <span>Dashboard</span>
        </a>
        
        <a href="students.php" class="flex items-center px-4 py-3 <?= $current_page == 'students.php' ? 'bg-gray-700 border-l-4 border-blue-500' : 'hover:bg-gray-700' ?>">
            <i class="fas fa-users w-6"></i>
            <span>Students</span>
        </a>
        
        <a href="courses.php" class="flex items-center px-4 py-3 <?= $current_page == 'courses.php' ? 'bg-gray-700 border-l-4 border-blue-500' : 'hover:bg-gray-700' ?>">
            <i class="fas fa-book w-6"></i>
            <span>Courses</span>
        </a>
        
        <a href="counselor/counselors.php" class="flex items-center px-4 py-3 <?= strpos($current_page, 'counselors.php') !== false ? 'bg-gray-700 border-l-4 border-blue-500' : 'hover:bg-gray-700' ?>">
            <i class="fas fa-users w-6"></i>
            <span>Counselors</span>
        </a>
         <a href="risk-monitor.php" class="flex items-center px-4 py-3 <?= strpos($current_page, 'risk-monitor.php') !== false ? 'bg-gray-700 border-l-4 border-blue-500' : 'hover:bg-gray-700' ?>">
            <i class="fas fa-users w-6"></i>
            <span>Risk-monitor</span>
        </a>
        <a href="content.php" class="flex items-center px-4 py-3 <?= $current_page == 'content.php' ? 'bg-gray-700 border-l-4 border-blue-500' : 'hover:bg-gray-700' ?>">
            <i class="fas fa-upload w-6"></i>
            <span>Content Upload</span>
        </a>
        <a href="mood-reports.php" class="flex items-center px-4 py-3 <?= $current_page == 'mood-reports.php' ? 'bg-gray-700 border-l-4 border-blue-500' : 'hover:bg-gray-700' ?>">
            <i class="fas fa-chart-line w-6"></i>
            <span>Mood Reports</span>
        </a>
        <a href="reports.php" class="flex items-center px-4 py-3 <?= $current_page == 'reports.php' ? 'bg-gray-700 border-l-4 border-blue-500' : 'hover:bg-gray-700' ?>">
            <i class="fas fa-chart-bar w-6"></i>
            <span>Progress Reports</span>
        </a>
        
        <a href="notifications.php" class="flex items-center px-4 py-3 <?= $current_page == 'notifications.php' ? 'bg-gray-700 border-l-4 border-blue-500' : 'hover:bg-gray-700' ?>">
            <i class="fas fa-bell w-6"></i>
            <span>Notifications</span>
        </a>
        
        <?php if ($isMainAdmin): ?>
        <!-- Settings only visible to main admin -->
        <a href="settings.php" class="flex items-center px-4 py-3 <?= $current_page == 'settings.php' ? 'bg-gray-700 border-l-4 border-blue-500' : 'hover:bg-gray-700' ?>">
            <i class="fas fa-cog w-6"></i>
            <span>System Settings</span>
        </a>
        <?php endif; ?>
    </nav>
    
    <div class="p-4 border-t border-gray-700">
        <a href="../logout.php" class="flex items-center text-red-400 hover:text-red-300">
            <i class="fas fa-sign-out-alt w-6"></i>
            <span>Logout</span>
        </a>
    </div>
</div>