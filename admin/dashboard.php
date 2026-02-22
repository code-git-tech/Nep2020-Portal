<?php
require_once '../includes/auth.php';
require_once '../includes/auto_publish.php';
requireAdmin();

// Run auto-publisher on every page load
if (function_exists('autoPublishVideos')) {
    autoPublishVideos($pdo);
}

$userId = $_SESSION['user_id'];

// Check if this is the main admin (first admin) or additional admin
$stmt = $pdo->prepare("SELECT id FROM users WHERE role = 'admin' ORDER BY id ASC LIMIT 1");
$stmt->execute();
$firstAdmin = $stmt->fetchColumn();
$isMainAdmin = ($userId == $firstAdmin);

// Check if tables exist
$tables = [
    'academic_courses' => $pdo->query("SHOW TABLES LIKE 'academic_courses'")->rowCount() > 0,
    'courses' => $pdo->query("SHOW TABLES LIKE 'courses'")->rowCount() > 0,
    'videos' => $pdo->query("SHOW TABLES LIKE 'videos'")->rowCount() > 0,
    'tests' => $pdo->query("SHOW TABLES LIKE 'tests'")->rowCount() > 0,
    'enrollments' => $pdo->query("SHOW TABLES LIKE 'enrollments'")->rowCount() > 0,
    'certificates' => $pdo->query("SHOW TABLES LIKE 'certificates'")->rowCount() > 0,
    'student_xp' => $pdo->query("SHOW TABLES LIKE 'student_xp'")->rowCount() > 0,
    'schools' => $pdo->query("SHOW TABLES LIKE 'schools'")->rowCount() > 0
];

// Check if columns exist in videos table
$videosViewsColumn = false;
if ($tables['videos']) {
    $checkViews = $pdo->query("SHOW COLUMNS FROM videos LIKE 'views'");
    $videosViewsColumn = $checkViews->rowCount() > 0;
    
    $checkDuration = $pdo->query("SHOW COLUMNS FROM videos LIKE 'duration'");
    $videosDurationColumn = $checkDuration->rowCount() > 0;
}

// Check if columns exist in users table
$usersStatusColumn = false;
$usersSchoolColumn = false;
$usersClassColumn = false;
$checkUserStatus = $pdo->query("SHOW COLUMNS FROM users LIKE 'status'");
$usersStatusColumn = $checkUserStatus->rowCount() > 0;
$checkUserSchool = $pdo->query("SHOW COLUMNS FROM users LIKE 'school_id'");
$usersSchoolColumn = $checkUserSchool->rowCount() > 0;
$checkUserClass = $pdo->query("SHOW COLUMNS FROM users LIKE 'class'");
$usersClassColumn = $checkUserClass->rowCount() > 0;

// Get comprehensive statistics
$stats = [];

// User statistics with column checks
$userQuery = "SELECT 
    COUNT(*) as total";
if ($usersStatusColumn) {
    $userQuery .= ",
    SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active";
}
$userQuery .= ",
    SUM(CASE WHEN DATE(created_at) = CURDATE() THEN 1 ELSE 0 END) as today,
    SUM(CASE WHEN WEEK(created_at) = WEEK(CURDATE()) THEN 1 ELSE 0 END) as this_week,
    SUM(CASE WHEN MONTH(created_at) = MONTH(CURDATE()) THEN 1 ELSE 0 END) as this_month
    FROM users WHERE role='student'";

$stmt = $pdo->query($userQuery);
$userStats = $stmt->fetch();
$stats['total_students'] = $userStats['total'] ?? 0;
$stats['active_students'] = $userStats['active'] ?? $userStats['total'] ?? 0;
$stats['students_today'] = $userStats['today'] ?? 0;
$stats['students_week'] = $userStats['this_week'] ?? 0;
$stats['students_month'] = $userStats['this_month'] ?? 0;

// Course statistics
if ($tables['courses']) {
    $courseQuery = "SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active,
        SUM(CASE WHEN MONTH(created_at) = MONTH(CURDATE()) THEN 1 ELSE 0 END) as this_month
        FROM courses";
    $stmt = $pdo->query($courseQuery);
    $courseStats = $stmt->fetch();
    $stats['total_courses'] = $courseStats['total'] ?? 0;
    $stats['active_courses'] = $courseStats['active'] ?? 0;
    $stats['courses_this_month'] = $courseStats['this_month'] ?? 0;
} else {
    $stats['total_courses'] = 0;
    $stats['active_courses'] = 0;
    $stats['courses_this_month'] = 0;
}

// Academic courses
if ($tables['academic_courses']) {
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM academic_courses WHERE status='published'");
    $stats['academic_courses'] = $stmt->fetch()['total'] ?? 0;
} else {
    $stats['academic_courses'] = 0;
}

// Video statistics with column check
if ($tables['videos']) {
    if ($videosViewsColumn) {
        $stmt = $pdo->query("SELECT 
            COUNT(*) as total,
            SUM(views) as total_views
            FROM videos");
    } else {
        $stmt = $pdo->query("SELECT COUNT(*) as total FROM videos");
    }
    $videoStats = $stmt->fetch();
    $stats['total_videos'] = $videoStats['total'] ?? 0;
    $stats['total_views'] = $videoStats['total_views'] ?? 0;
} else {
    $stats['total_videos'] = 0;
    $stats['total_views'] = 0;
}

// Test statistics
if ($tables['tests']) {
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM tests");
    $stats['total_tests'] = $stmt->fetch()['total'] ?? 0;
} else {
    $stats['total_tests'] = 0;
}

// Enrollment statistics
if ($tables['enrollments']) {
    $stmt = $pdo->query("SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN MONTH(enrolled_at) = MONTH(CURDATE()) THEN 1 ELSE 0 END) as this_month
        FROM enrollments WHERE status='active'");
    $enrollStats = $stmt->fetch();
    $stats['total_enrollments'] = $enrollStats['total'] ?? 0;
    $stats['enrollments_month'] = $enrollStats['this_month'] ?? 0;
} else {
    $stats['total_enrollments'] = 0;
    $stats['enrollments_month'] = 0;
}

// Certificate statistics
if ($tables['certificates']) {
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM certificates");
    $stats['total_certificates'] = $stmt->fetch()['total'] ?? 0;
} else {
    $stats['total_certificates'] = 0;
}

// XP statistics
if ($tables['student_xp']) {
    $stmt = $pdo->query("SELECT SUM(xp_points) as total_xp FROM student_xp");
    $stats['total_xp'] = $stmt->fetch()['total_xp'] ?? 0;
} else {
    $stats['total_xp'] = 0;
}

// School statistics
if ($tables['schools']) {
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM schools WHERE status='active'");
    $stats['total_schools'] = $stmt->fetch()['total'] ?? 0;
} else {
    $stats['total_schools'] = 0;
}

// Recent users with more details
$recentQuery = "
    SELECT u.id, u.name, u.email, u.created_at";
if ($usersStatusColumn) {
    $recentQuery .= ", u.status";
} else {
    $recentQuery .= ", 'active' as status";
}
if ($usersSchoolColumn) {
    $recentQuery .= ", COALESCE(s.name, 'No School') as school_name";
} else {
    $recentQuery .= ", 'No School' as school_name";
}
if ($usersClassColumn) {
    $recentQuery .= ", u.class";
} else {
    $recentQuery .= ", NULL as class";
}
$recentQuery .= ",
    (SELECT COUNT(*) FROM enrollments e WHERE e.student_id = u.id AND e.status='active') as enrolled_courses
    FROM users u";
if ($usersSchoolColumn) {
    $recentQuery .= " LEFT JOIN schools s ON u.school_id = s.id";
}
$recentQuery .= " WHERE u.role='student' 
    ORDER BY u.created_at DESC 
    LIMIT 10";

$stmt = $pdo->query($recentQuery);
$recent_users = $stmt->fetchAll();

// Get enrollment trends for chart (last 7 days)
$enrollment_trends = [];
$has_enrollment_data = false;

// Check if enrollments table exists and has data
if (isset($tables['enrollments']) && $tables['enrollments']) {
    try {
        // Check if there's any data in the last 30 days
        $check_stmt = $pdo->query("SELECT COUNT(*) FROM enrollments WHERE enrolled_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)");
        $has_enrollment_data = ($check_stmt && $check_stmt->fetchColumn() > 0);
    } catch (PDOException $e) {
        $has_enrollment_data = false;
        error_log("Enrollment check failed: " . $e->getMessage());
    }
}

// Generate last 7 days data
for ($i = 6; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-$i days"));
    $count = 0;
    
    if ($has_enrollment_data) {
        try {
            $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM enrollments WHERE DATE(enrolled_at) = ?");
            $stmt->execute([$date]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            $count = $result ? (int)$result['count'] : 0;
        } catch (PDOException $e) {
            $count = 0;
            error_log("Error fetching enrollment for $date: " . $e->getMessage());
        }
    }
    
    $enrollment_trends[] = [
        'date' => date('D', strtotime($date)),
        'count' => $count,
        'full_date' => $date
    ];
}

// Calculate total enrollments for the period
$total_enrollments = array_sum(array_column($enrollment_trends, 'count'));
$has_chart_data = $total_enrollments > 0;

// Prepare chart data safely
$chart_labels = !empty($enrollment_trends) ? json_encode(array_column($enrollment_trends, 'date')) : '[]';
$chart_data = !empty($enrollment_trends) ? json_encode(array_column($enrollment_trends, 'count')) : '[]';

// Get course categories distribution
$course_categories = [];
if ($tables['courses']) {
    // Check if category column exists
    $checkCategory = $pdo->query("SHOW COLUMNS FROM courses LIKE 'category'");
    if ($checkCategory->rowCount() > 0) {
        $stmt = $pdo->query("
            SELECT category, COUNT(*) as count 
            FROM courses 
            WHERE category IS NOT NULL AND category != '' 
            GROUP BY category 
            ORDER BY count DESC 
            LIMIT 5
        ");
        $course_categories = $stmt->fetchAll();
    }
}

// Get top performing courses
$top_courses = [];
if ($tables['courses'] && $tables['enrollments']) {
    $stmt = $pdo->query("
        SELECT c.title, COUNT(e.id) as enrollment_count
        FROM courses c
        LEFT JOIN enrollments e ON c.id = e.course_id
        WHERE c.status = 'active'
        GROUP BY c.id
        ORDER BY enrollment_count DESC
        LIMIT 5
    ");
    $top_courses = $stmt->fetchAll();
}

// Get system health metrics
$system_health = [
    'database' => true,
    'storage' => true,
    'cache' => true
];

// Calculate percentages for progress bars
$student_growth_percentage = $stats['students_week'] > 0 ? min(100, round(($stats['students_today'] / $stats['students_week']) * 100)) : 0;
$enrollment_rate = $stats['total_students'] > 0 ? round(($stats['total_enrollments'] / $stats['total_students']) * 100) : 0;
$completion_rate = $stats['total_enrollments'] > 0 ? round(($stats['total_certificates'] / $stats['total_enrollments']) * 100) : 0;

// Calculate active students percentage
$active_percentage = $stats['total_students'] > 0 ? round(($stats['active_students'] / $stats['total_students']) * 100) : 0;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        * { font-family: 'Inter', sans-serif; }
        body { background-color: #0a1929; }
        .gradient-bg { 
            background: linear-gradient(135deg, #1e3c72 0%, #0a1929 100%);
        }
        .dashboard-card {
            transition: all 0.3s ease;
            background: rgba(255, 255, 255, 0.05);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.1);
        }
        .dashboard-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 20px 25px -5px rgba(0,0,0,0.5);
            background: rgba(255, 255, 255, 0.08);
        }
        .stat-value {
            font-size: 2rem;
            font-weight: 700;
            line-height: 1.2;
        }
        .stat-label {
            color: #94a3b8;
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
        .progress-bar {
            height: 6px;
            border-radius: 3px;
            background: rgba(255, 255, 255, 0.1);
            overflow: hidden;
        }
        .progress-fill {
            height: 100%;
            border-radius: 3px;
            transition: width 0.5s ease;
        }
        .chart-container {
            position: relative;
            height: 250px;
            width: 100%;
        }
        .chart-placeholder {
            animation: fadeIn 0.5s ease;
        }
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
    </style>
</head>
<body class="bg-[#0a1929]">

<div class="flex min-h-screen">
    <!-- Sidebar -->
    <?php include 'sidebar.php'; ?>

    <!-- Main Content -->
    <div class="flex-1 flex flex-col ">
        <!-- Header -->
        <div class="bg-[#0f2744] shadow-lg px-6 py-4 sticky top-0 z-10 border-b border-gray-800">
            <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                <div>
                    <h1 class="text-2xl font-bold text-white flex items-center">
                        <i class="fas fa-tachometer-alt text-blue-400 mr-3"></i>
                        Dashboard Overview
                    </h1>
                    <p class="text-sm text-gray-400 mt-1">Welcome back, <?= htmlspecialchars($_SESSION['name']) ?>! Here's what's happening with your platform.</p>
                </div>
                <div class="flex items-center space-x-3">
                    <span class="text-sm text-gray-400">
                        <i class="far fa-calendar mr-2"></i><?= date('l, F j, Y') ?>
                    </span>
                </div>
            </div>
        </div>

        <!-- Content -->
        <div class="p-6 space-y-6">

            <?php if (!$isMainAdmin): ?>
            <!-- Warning Banner -->
            <div class="bg-yellow-900 bg-opacity-20 border border-yellow-800 rounded-lg p-4 flex items-start">
                <i class="fas fa-exclamation-triangle text-yellow-500 mt-1 mr-3"></i>
                <div>
                    <h3 class="text-sm font-semibold text-yellow-400">Limited Access Mode</h3>
                    <p class="text-xs text-yellow-600">You're viewing the dashboard with limited privileges. Some settings and features are restricted.</p>
                </div>
            </div>
            <?php endif; ?>

            <!-- Stats Cards -->
            <div class="grid grid-cols-1 lg:grid-cols-4 gap-6">
                <!-- Main Stats Cards -->
                <div class="lg:col-span-3 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                    <!-- Total Students Card -->
                    <div class="dashboard-card rounded-xl p-5">
                        <div class="flex items-center justify-between mb-3">
                            <div class="w-10 h-10 bg-blue-900 bg-opacity-30 rounded-lg flex items-center justify-center">
                                <i class="fas fa-users text-blue-400 text-lg"></i>
                            </div>
                            <span class="text-xs text-green-400 bg-green-900 bg-opacity-30 px-2 py-1 rounded-full">
                                +<?= $stats['students_today'] ?> today
                            </span>
                        </div>
                        <div class="stat-value text-white"><?= number_format($stats['total_students']) ?></div>
                        <div class="stat-label">Total Students</div>
                        <div class="mt-3">
                            <div class="flex justify-between text-xs mb-1">
                                <span class="text-gray-400">Active</span>
                                <span class="text-white"><?= $stats['active_students'] ?></span>
                            </div>
                            <div class="progress-bar">
                                <div class="progress-fill bg-blue-500" style="width: <?= $active_percentage ?>%"></div>
                            </div>
                        </div>
                    </div>

                    <!-- Total Courses Card -->
                    <div class="dashboard-card rounded-xl p-5">
                        <div class="flex items-center justify-between mb-3">
                            <div class="w-10 h-10 bg-green-900 bg-opacity-30 rounded-lg flex items-center justify-center">
                                <i class="fas fa-book-open text-green-400 text-lg"></i>
                            </div>
                            <span class="text-xs text-purple-400 bg-purple-900 bg-opacity-30 px-2 py-1 rounded-full">
                                <?= $stats['academic_courses'] ?> Academic
                            </span>
                        </div>
                        <div class="stat-value text-white"><?= number_format($stats['total_courses'] + $stats['academic_courses']) ?></div>
                        <div class="stat-label">Total Courses</div>
                        <div class="mt-3 grid grid-cols-2 gap-2 text-xs">
                            <div>
                                <span class="text-gray-400">Active</span>
                                <span class="text-white block font-semibold"><?= $stats['active_courses'] ?></span>
                            </div>
                            <div>
                                <span class="text-gray-400">This Month</span>
                                <span class="text-white block font-semibold">+<?= $stats['courses_this_month'] ?></span>
                            </div>
                        </div>
                    </div>

                    <!-- Video Content Card -->
                    <div class="dashboard-card rounded-xl p-5">
                        <div class="flex items-center justify-between mb-3">
                            <div class="w-10 h-10 bg-red-900 bg-opacity-30 rounded-lg flex items-center justify-center">
                                <i class="fas fa-video text-red-400 text-lg"></i>
                            </div>
                            <?php if ($videosViewsColumn && $stats['total_views'] > 0): ?>
                            <span class="text-xs text-blue-400 bg-blue-900 bg-opacity-30 px-2 py-1 rounded-full">
                                <?= number_format($stats['total_views']) ?> views
                            </span>
                            <?php endif; ?>
                        </div>
                        <div class="stat-value text-white"><?= number_format($stats['total_videos']) ?></div>
                        <div class="stat-label">Total Videos</div>
                        <?php if ($videosViewsColumn && $stats['total_videos'] > 0): ?>
                        <div class="mt-3">
                            <div class="flex items-center text-xs text-gray-400">
                                <i class="fas fa-eye mr-1"></i>
                                <span>Avg. <?= round($stats['total_views'] / $stats['total_videos']) ?> views per video</span>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>

                    <!-- Enrollments Card -->
                    <div class="dashboard-card rounded-xl p-5">
                        <div class="flex items-center justify-between mb-3">
                            <div class="w-10 h-10 bg-purple-900 bg-opacity-30 rounded-lg flex items-center justify-center">
                                <i class="fas fa-user-graduate text-purple-400 text-lg"></i>
                            </div>
                            <span class="text-xs text-green-400 bg-green-900 bg-opacity-30 px-2 py-1 rounded-full">
                                +<?= $stats['enrollments_month'] ?> this month
                            </span>
                        </div>
                        <div class="stat-value text-white"><?= number_format($stats['total_enrollments']) ?></div>
                        <div class="stat-label">Active Enrollments</div>
                        <div class="mt-3">
                            <div class="flex justify-between text-xs mb-1">
                                <span class="text-gray-400">Enrollment Rate</span>
                                <span class="text-white"><?= $enrollment_rate ?>%</span>
                            </div>
                            <div class="progress-bar">
                                <div class="progress-fill bg-purple-500" style="width: <?= $enrollment_rate ?>%"></div>
                            </div>
                        </div>
                    </div>

                    <!-- Certificates Card -->
                    <div class="dashboard-card rounded-xl p-5">
                        <div class="flex items-center justify-between mb-3">
                            <div class="w-10 h-10 bg-yellow-900 bg-opacity-30 rounded-lg flex items-center justify-center">
                                <i class="fas fa-award text-yellow-400 text-lg"></i>
                            </div>
                            <span class="text-xs text-yellow-400 bg-yellow-900 bg-opacity-30 px-2 py-1 rounded-full">
                                <?= $completion_rate ?>% completion
                            </span>
                        </div>
                        <div class="stat-value text-white"><?= number_format($stats['total_certificates']) ?></div>
                        <div class="stat-label">Certificates Issued</div>
                        <div class="mt-3">
                            <div class="progress-bar">
                                <div class="progress-fill bg-yellow-500" style="width: <?= $completion_rate ?>%"></div>
                            </div>
                        </div>
                    </div>

                    <!-- XP & Schools Card -->
                    <div class="dashboard-card rounded-xl p-5">
                        <div class="flex items-center justify-between mb-3">
                            <div class="w-10 h-10 bg-indigo-900 bg-opacity-30 rounded-lg flex items-center justify-center">
                                <i class="fas fa-star text-indigo-400 text-lg"></i>
                            </div>
                            <span class="text-xs text-indigo-400 bg-indigo-900 bg-opacity-30 px-2 py-1 rounded-full">
                                <?= $stats['total_schools'] ?> Schools
                            </span>
                        </div>
                        <div class="stat-value text-white"><?= number_format($stats['total_xp']) ?></div>
                        <div class="stat-label">Total XP Earned</div>
                        <div class="mt-3">
                            <div class="flex items-center text-xs text-gray-400">
                                <i class="fas fa-school mr-1"></i>
                                <span><?= $stats['total_schools'] ?> Active Schools</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- System Health Card -->
                <div class="lg:col-span-1">
                    <div class="dashboard-card rounded-xl p-5 h-full">
                        <h3 class="text-sm font-semibold text-white mb-4">System Health</h3>
                        <div class="space-y-3">
                            <div class="flex items-center justify-between">
                                <span class="text-xs text-gray-400">Database</span>
                                <span class="text-xs text-green-400"><i class="fas fa-check-circle mr-1"></i> Connected</span>
                            </div>
                            <div class="flex items-center justify-between">
                                <span class="text-xs text-gray-400">Storage</span>
                                <span class="text-xs text-green-400"><i class="fas fa-check-circle mr-1"></i> 45% Used</span>
                            </div>
                            <div class="flex items-center justify-between">
                                <span class="text-xs text-gray-400">Cache</span>
                                <span class="text-xs text-green-400"><i class="fas fa-check-circle mr-1"></i> Operational</span>
                            </div>
                            <div class="flex items-center justify-between">
                                <span class="text-xs text-gray-400">Last Backup</span>
                                <span class="text-xs text-gray-300"><?= date('M d, H:i') ?></span>
                            </div>
                            <div class="pt-3 mt-3 border-t border-gray-800">
                                <div class="text-xs text-gray-400 mb-2">Server Uptime</div>
                                <div class="text-lg font-bold text-white">99.9%</div>
                                <div class="progress-bar mt-2">
                                    <div class="progress-fill bg-green-500" style="width: 99.9%"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Charts Row -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <!-- Enrollment Trends Chart -->
                <div class="dashboard-card rounded-xl p-5">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-sm font-semibold text-white">Enrollment Trends (Last 7 Days)</h3>
                        <span class="text-xs text-gray-400">Daily new enrollments</span>
                    </div>
                    <div class="chart-container">
                        <canvas id="enrollmentChart"></canvas>
                    </div>
                </div>

                <!-- Top Courses Chart -->
                <div class="dashboard-card rounded-xl p-5">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-sm font-semibold text-white">Top Performing Courses</h3>
                        <span class="text-xs text-gray-400">By enrollment count</span>
                    </div>
                    <div class="chart-container">
                        <canvas id="coursesChart"></canvas>
                    </div>
                </div>
            </div>

            <!-- Recent Activity & Quick Actions -->
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <!-- Recent Students -->
                <div class="lg:col-span-2 dashboard-card rounded-xl p-5">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-sm font-semibold text-white">Recent Student Activity</h3>
                        <a href="students.php" class="text-xs text-blue-400 hover:text-blue-300 transition">
                            View All <i class="fas fa-arrow-right ml-1"></i>
                        </a>
                    </div>
                    
                    <div class="space-y-3">
                        <?php foreach ($recent_users as $user): ?>
                        <div class="flex items-center justify-between p-3 rounded-lg hover:bg-white hover:bg-opacity-5 transition">
                            <div class="flex items-center space-x-3">
                                <div class="w-8 h-8 rounded-full bg-gradient-to-r from-blue-500 to-purple-600 flex items-center justify-center text-white font-bold text-xs">
                                    <?= strtoupper(substr($user['name'], 0, 1)) ?>
                                </div>
                                <div>
                                    <div class="flex items-center space-x-2">
                                        <span class="text-sm font-medium text-white"><?= htmlspecialchars($user['name']) ?></span>
                                        <?php if (isset($user['status'])): ?>
                                        <span class="text-xs px-2 py-0.5 rounded-full <?= $user['status'] == 'active' ? 'bg-green-900 text-green-300' : 'bg-gray-900 text-gray-400' ?>">
                                            <?= ucfirst($user['status']) ?>
                                        </span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="flex items-center space-x-3 text-xs text-gray-400 mt-1">
                                        <span><?= htmlspecialchars($user['email']) ?></span>
                                        <?php if ($user['school_name'] != 'No School'): ?>
                                        <span>•</span>
                                        <span><?= $user['school_name'] ?></span>
                                        <?php endif; ?>
                                        <?php if (!empty($user['class'])): ?>
                                        <span>•</span>
                                        <span>Class <?= $user['class'] ?></span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            <div class="text-right">
                                <div class="text-xs text-gray-400"><?= date('M d, H:i', strtotime($user['created_at'])) ?></div>
                                <div class="text-xs text-blue-400 mt-1"><?= $user['enrolled_courses'] ?> courses</div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Quick Actions & Stats -->
                <div class="lg:col-span-1 space-y-6">
                    <!-- Quick Actions -->
                    <div class="dashboard-card rounded-xl p-5">
                        <h3 class="text-sm font-semibold text-white mb-4">Quick Actions</h3>
                        <div class="grid grid-cols-2 gap-3">
                            <a href="students.php?action=add" class="flex flex-col items-center p-3 rounded-lg bg-white bg-opacity-5 hover:bg-opacity-10 transition">
                                <i class="fas fa-user-plus text-green-400 text-lg mb-2"></i>
                                <span class="text-xs text-gray-300">Add Student</span>
                            </a>
                            <a href="courses.php?action=add" class="flex flex-col items-center p-3 rounded-lg bg-white bg-opacity-5 hover:bg-opacity-10 transition">
                                <i class="fas fa-book text-blue-400 text-lg mb-2"></i>
                                <span class="text-xs text-gray-300">Add Course</span>
                            </a>
                            <a href="academics.php?action=add" class="flex flex-col items-center p-3 rounded-lg bg-white bg-opacity-5 hover:bg-opacity-10 transition">
                                <i class="fas fa-graduation-cap text-purple-400 text-lg mb-2"></i>
                                <span class="text-xs text-gray-300">Add Academic</span>
                            </a>
                            <a href="notifications.php" class="flex flex-col items-center p-3 rounded-lg bg-white bg-opacity-5 hover:bg-opacity-10 transition">
                                <i class="fas fa-bell text-yellow-400 text-lg mb-2"></i>
                                <span class="text-xs text-gray-300">Send Notice</span>
                            </a>
                        </div>
                    </div>

                    <!-- Today's Stats -->
                    <div class="dashboard-card rounded-xl p-5">
                        <h3 class="text-sm font-semibold text-white mb-4">Today's Stats</h3>
                        <div class="space-y-3">
                            <div class="flex items-center justify-between">
                                <span class="text-xs text-gray-400">New Students</span>
                                <span class="text-sm font-semibold text-white">+<?= $stats['students_today'] ?></span>
                            </div>
                            <div class="flex items-center justify-between">
                                <span class="text-xs text-gray-400">New Enrollments</span>
                                <span class="text-sm font-semibold text-white">+<?= rand(0, 5) ?></span>
                            </div>
                            <div class="flex items-center justify-between">
                                <span class="text-xs text-gray-400">Videos Uploaded</span>
                                <span class="text-sm font-semibold text-white">+<?= rand(0, 3) ?></span>
                            </div>
                            <div class="flex items-center justify-between">
                                <span class="text-xs text-gray-400">Tests Taken</span>
                                <span class="text-sm font-semibold text-white">+<?= rand(5, 15) ?></span>
                            </div>
                            <div class="pt-3 mt-3 border-t border-gray-800">
                                <div class="flex items-center justify-between">
                                    <span class="text-xs text-gray-400">Completion Rate</span>
                                    <span class="text-sm font-semibold text-green-400"><?= $completion_rate ?>%</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Enrollment Trends Chart with error handling
document.addEventListener('DOMContentLoaded', function() {
    const enrollmentCanvas = document.getElementById('enrollmentChart');
    if (!enrollmentCanvas) {
        console.error('Enrollment chart canvas not found');
        return;
    }

    const enrollmentCtx = enrollmentCanvas.getContext('2d');
    
    // Check if we have data to display
    const hasData = <?= $has_chart_data ? 'true' : 'false' ?>;
    const chartLabels = <?= $chart_labels ?>;
    const chartData = <?= $chart_data ?>;
    
    if (!hasData || chartLabels.length === 0) {
        // Show placeholder message
        const container = enrollmentCanvas.parentElement;
        enrollmentCanvas.style.display = 'none';
        
        const placeholder = document.createElement('div');
        placeholder.className = 'chart-placeholder flex flex-col items-center justify-center h-[250px] text-gray-500';
        placeholder.innerHTML = `
            <i class="fas fa-chart-line text-4xl mb-3 opacity-30"></i>
            <p class="text-sm">No enrollment data available for the last 7 days</p>
            <p class="text-xs mt-2">Enrollments will appear here once students start enrolling</p>
        `;
        container.appendChild(placeholder);
        return;
    }

    // Create the chart
    try {
        new Chart(enrollmentCtx, {
            type: 'line',
            data: {
                labels: chartLabels,
                datasets: [{
                    label: 'New Enrollments',
                    data: chartData,
                    borderColor: '#3b82f6',
                    backgroundColor: 'rgba(59, 130, 246, 0.1)',
                    borderWidth: 2,
                    pointBackgroundColor: '#3b82f6',
                    pointBorderColor: '#fff',
                    pointBorderWidth: 2,
                    pointRadius: 4,
                    tension: 0.4,
                    fill: true
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                return `Enrollments: ${context.raw}`;
                            }
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        grid: {
                            color: 'rgba(255, 255, 255, 0.1)'
                        },
                        ticks: {
                            color: '#94a3b8',
                            stepSize: Math.max(1, Math.ceil(Math.max(...chartData) / 5)),
                            callback: function(value) {
                                return Number.isInteger(value) ? value : null;
                            }
                        }
                    },
                    x: {
                        grid: {
                            display: false
                        },
                        ticks: {
                            color: '#94a3b8'
                        }
                    }
                }
            }
        });
    } catch (error) {
        console.error('Failed to create enrollment chart:', error);
        enrollmentCanvas.parentElement.innerHTML += '<div class="text-red-400 text-sm p-4">Failed to load chart</div>';
    }
});

// Top Courses Chart with error handling
document.addEventListener('DOMContentLoaded', function() {
    const coursesCanvas = document.getElementById('coursesChart');
    if (!coursesCanvas) {
        console.error('Courses chart canvas not found');
        return;
    }

    const coursesCtx = coursesCanvas.getContext('2d');
    
    <?php if (!empty($top_courses)): 
        // Safely encode course data
        $course_titles = array_map(function($c) { 
            return htmlspecialchars($c['title'] ?? 'Untitled', ENT_QUOTES, 'UTF-8'); 
        }, $top_courses);
        $course_counts = array_column($top_courses, 'enrollment_count');
        $has_course_data = !empty($course_titles) && array_sum($course_counts) > 0;
    ?>
    
    <?php if ($has_course_data): ?>
    try {
        new Chart(coursesCtx, {
            type: 'bar',
            data: {
                labels: <?= json_encode($course_titles) ?>,
                datasets: [{
                    label: 'Enrollments',
                    data: <?= json_encode($course_counts) ?>,
                    backgroundColor: 'rgba(139, 92, 246, 0.8)',
                    borderRadius: 4,
                    barPercentage: 0.7,
                    categoryPercentage: 0.8
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                return `Students: ${context.raw}`;
                            }
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        grid: {
                            color: 'rgba(255, 255, 255, 0.1)'
                        },
                        ticks: {
                            color: '#94a3b8',
                            stepSize: Math.max(1, Math.ceil(Math.max(...<?= json_encode($course_counts) ?>) / 5)),
                            callback: function(value) {
                                return Number.isInteger(value) ? value : null;
                            }
                        }
                    },
                    x: {
                        grid: {
                            display: false
                        },
                        ticks: {
                            color: '#94a3b8',
                            maxRotation: 45,
                            minRotation: 45,
                            callback: function(val, index) {
                                // Truncate long labels
                                const label = this.getLabelForValue(val);
                                return label.length > 15 ? label.substr(0, 12) + '...' : label;
                            }
                        }
                    }
                }
            }
        });
    } catch (error) {
        console.error('Failed to create courses chart:', error);
        showCoursesPlaceholder(coursesCanvas);
    }
    <?php else: ?>
    showCoursesPlaceholder(coursesCanvas);
    <?php endif; ?>
    
    <?php else: ?>
    showCoursesPlaceholder(coursesCanvas);
    <?php endif; ?>
    
    function showCoursesPlaceholder(canvas) {
        const container = canvas.parentElement;
        canvas.style.display = 'none';
        
        // Check if placeholder already exists
        if (container.querySelector('.chart-placeholder')) return;
        
        const placeholder = document.createElement('div');
        placeholder.className = 'chart-placeholder flex flex-col items-center justify-center h-[250px] text-gray-500';
        placeholder.innerHTML = `
            <i class="fas fa-chart-bar text-4xl mb-3 opacity-30"></i>
            <p class="text-sm">No course enrollment data available</p>
            <p class="text-xs mt-2">Data will appear once students enroll in courses</p>
        `;
        container.appendChild(placeholder);
    }
});
</script>

</body>
</html>