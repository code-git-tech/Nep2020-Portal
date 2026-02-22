<?php
require_once '../includes/auth.php';
require_once '../includes/auto_publish.php';

autoPublishVideos($pdo);

$current_page = basename($_SERVER['PHP_SELF']);

global $pdo;
$stmt = $pdo->prepare("SELECT id FROM users WHERE role = 'admin' ORDER BY id ASC LIMIT 1");
$stmt->execute();
$firstAdmin = $stmt->fetchColumn();
$isMainAdmin = ($_SESSION['user_id'] == $firstAdmin);
?>

<!-- MOBILE TOP BAR -->
<div class="lg:hidden flex items-center justify-between bg-gray-900 text-white px-4 py-3 shadow-md fixed top-0 left-0 right-0 z-50">
    <h2 class="text-lg font-bold">Admin Panel</h2>
    <button onclick="toggleSidebar()" class="text-white text-xl">
        <i class="fas fa-bars"></i>
    </button>
</div>

<!-- OVERLAY -->
<div id="sidebarOverlay" onclick="toggleSidebar()" 
     class="fixed inset-0 bg-black/50 z-40 hidden lg:hidden"></div>

<!-- SIDEBAR -->
<div id="sidebar" 
class="fixed top-0 left-0 z-50 
w-64 h-screen 
bg-gray-900 text-white flex flex-col 
transform -translate-x-full lg:translate-x-0 
transition duration-300">

    <!-- HEADER -->
    <div class="p-4 border-b border-gray-800">
        <h2 class="text-xl font-bold tracking-wide">Admin Panel</h2>
        <p class="text-sm text-gray-400 mt-1">
            Welcome, <?= htmlspecialchars($_SESSION['name']) ?>
        </p>

        <?php if ($isMainAdmin): ?>
            <span class="text-xs bg-purple-600 px-2 py-1 rounded mt-2 inline-block">
                Main Administrator
            </span>
        <?php endif; ?>
    </div>

    <!-- NAV -->
    <nav class="flex-1 overflow-y-auto mt-3 space-y-1 px-2">

        <!-- DASHBOARD -->
        <a href="dashboard.php"
           class="flex items-center px-4 py-3 rounded-lg transition 
           <?= $current_page == 'dashboard.php' ? 'bg-blue-600 text-white shadow' : 'hover:bg-gray-800 text-gray-300' ?>">
            <i class="fas fa-tachometer-alt w-5"></i>
            <span class="ml-3">Dashboard</span>
        </a>

        <!-- STUDENTS -->
        <a href="students.php"
           class="flex items-center px-4 py-3 rounded-lg transition 
           <?= $current_page == 'students.php' ? 'bg-blue-600 text-white shadow' : 'hover:bg-gray-800 text-gray-300' ?>">
            <i class="fas fa-users w-5"></i>
            <span class="ml-3">Students</span>
        </a>

        <!-- COURSES -->
        <a href="courses.php"
           class="flex items-center px-4 py-3 rounded-lg transition 
           <?= $current_page == 'courses.php' ? 'bg-blue-600 text-white shadow' : 'hover:bg-gray-800 text-gray-300' ?>">
            <i class="fas fa-book w-5"></i>
            <span class="ml-3">Courses</span>
        </a>

        <!-- ACADEMICS -->
        <a href="academics.php"
           class="flex items-center px-4 py-3 rounded-lg transition 
           <?= $current_page == 'academics.php' ? 'bg-blue-600 text-white shadow' : 'hover:bg-gray-800 text-gray-300' ?>">
            <i class="fas fa-graduation-cap w-5"></i>
            <span class="ml-3">Academics Courses</span>
        </a>

        <!-- CONTENT -->
        <a href="content.php"
           class="flex items-center px-4 py-3 rounded-lg transition 
           <?= $current_page == 'content.php' ? 'bg-blue-600 text-white shadow' : 'hover:bg-gray-800 text-gray-300' ?>">
            <i class="fas fa-upload w-5"></i>
            <span class="ml-3">Content Upload</span>
        </a>
        <!--Mood- REPORTS -->
        <a href="mood-reports.php"
           class="flex items-center px-4 py-3 rounded-lg transition 
           <?= $current_page == 'mood-reports.php' ? 'bg-blue-600 text-white shadow' : 'hover:bg-gray-800 text-gray-300' ?>">
            <i class="fas fa-chart-bar w-5"></i>
            <span class="ml-3">Mood-Reports</span>
        </a>
        
        <!-- Risk-monitor -->
        <a href="risk-monitor.php"
           class="flex items-center px-4 py-3 rounded-lg transition 
           <?= $current_page == 'risk-monitor.php' ? 'bg-blue-600 text-white shadow' : 'hover:bg-gray-800 text-gray-300' ?>">
            <i class="fas fa-chart-bar w-5"></i>
            <span class="ml-3">Risk Monitor</span>
        </a>
        <!--Consultations -->
        <a href="counselor/consultations.php"
           class="flex items-center px-4 py-3 rounded-lg transition 
           <?= $current_page == 'counselor/consultations.php' ? 'bg-blue-600 text-white shadow' : 'hover:bg-gray-800 text-gray-300' ?>">
            <i class="fas fa-chart-bar w-5"></i>
            <span class="ml-3">Consultations</span>
        </a>
        <!--counselors-->
        <a href="counselor/counselors.php"
           class="flex items-center px-4 py-3 rounded-lg transition 
           <?= $current_page == 'counselor/counselors.php' ? 'bg-blue-600 text-white shadow' : 'hover:bg-gray-800 text-gray-300' ?>">
            <i class="fas fa-chart-bar w-5"></i>
            <span class="ml-3">Counselors</span>
        </a>

        <!-- REPORTS -->
        <a href="reports.php"
           class="flex items-center px-4 py-3 rounded-lg transition 
           <?= $current_page == 'reports.php' ? 'bg-blue-600 text-white shadow' : 'hover:bg-gray-800 text-gray-300' ?>">
            <i class="fas fa-chart-bar w-5"></i>
            <span class="ml-3">Reports</span>
        </a>

        <!-- NOTIFICATIONS -->
        <a href="notifications.php"
           class="flex items-center px-4 py-3 rounded-lg transition 
           <?= $current_page == 'notifications.php' ? 'bg-blue-600 text-white shadow' : 'hover:bg-gray-800 text-gray-300' ?>">
            <i class="fas fa-bell w-5"></i>
            <span class="ml-3">Notifications</span>
        </a>

        <!-- SETTINGS -->
        <?php if ($isMainAdmin): ?>
        <a href="settings.php"
           class="flex items-center px-4 py-3 rounded-lg transition 
           <?= $current_page == 'settings.php' ? 'bg-blue-600 text-white shadow' : 'hover:bg-gray-800 text-gray-300' ?>">
            <i class="fas fa-cog w-5"></i>
            <span class="ml-3">System Settings</span>
        </a>
        <?php endif; ?>

    </nav>

    <!-- LOGOUT -->
    <div class="p-4 border-t border-gray-800">
        <a href="../logout.php"
           class="flex items-center px-4 py-3 rounded-lg bg-red-500/10 text-red-400 hover:bg-red-500/20 transition">
            <i class="fas fa-sign-out-alt w-5"></i>
            <span class="ml-3">Logout</span>
        </a>
    </div>

</div>

<!-- MAIN CONTENT WRAPPER -->
<div class="lg:ml-64 pt-16 lg:pt-0 p-4">
    <!-- 🔥 YOUR PAGE CONTENT HERE -->
</div>

<!-- SCRIPT -->
<script>
function toggleSidebar() {
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('sidebarOverlay');

    sidebar.classList.toggle('-translate-x-full');
    overlay.classList.toggle('hidden');
}
</script>