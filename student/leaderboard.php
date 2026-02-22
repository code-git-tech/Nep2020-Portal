<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Debug session
error_log("Session data: " . print_r($_SESSION, true));

// Define base path
define('BASE_PATH', dirname(__DIR__));

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    error_log("No user_id in session - redirecting to login");
    header('Location: login.php');
    exit();
}

// Database connection
require_once '../config/db.php';

try {
    // Verify database connection
    if (!isset($pdo)) {
        throw new Exception("Database connection failed");
    }
    
    // Get user details with error handling
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch();
    
    if (!$user) {
        error_log("User not found in database for ID: " . $_SESSION['user_id']);
        session_destroy();
        header('Location: login.php?error=user_not_found');
        exit();
    }
    
    // Check if user is student
    if ($user['role'] !== 'student') {
        error_log("User is not a student. Role: " . $user['role']);
        header('Location: ../dashboard.php');
        exit();
    }
    
} catch (Exception $e) {
    error_log("Database error in leaderboard.php: " . $e->getMessage());
    die("An error occurred. Please try again later.");
}

// Get filter parameters with validation
$time_filter = isset($_GET['time']) && in_array($_GET['time'], ['weekly', 'monthly']) ? $_GET['time'] : 'all';
$course_filter = isset($_GET['course']) && is_numeric($_GET['course']) ? $_GET['course'] : 'all';

// Get courses for filter
try {
    $courses = $pdo->query("SELECT id, title FROM courses WHERE status = 'active' ORDER BY title")->fetchAll();
} catch (Exception $e) {
    error_log("Error fetching courses: " . $e->getMessage());
    $courses = [];
}

// Build the leaderboard query
$query = "
    SELECT 
        u.id,
        u.name,
        u.avatar,
        u.email,
        COALESCE((
            SELECT SUM(t.score) 
            FROM test_attempts t 
            WHERE t.student_id = u.id AND t.passed = 1
    ";

if ($time_filter === 'weekly') {
    $query .= " AND t.completed_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
} elseif ($time_filter === 'monthly') {
    $query .= " AND t.completed_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
}

$query .= "
        ), 0) as total_score,
        COUNT(DISTINCT CASE 
            WHEN t.completed_at IS NOT NULL AND t.passed = 1 THEN t.test_id 
        END) as tests_passed,
        COALESCE((
            SELECT COUNT(DISTINCT vp.video_id) 
            FROM video_progress vp 
            WHERE vp.student_id = u.id AND vp.completed = 1
    ";

if ($time_filter === 'weekly') {
    $query .= " AND vp.last_watched >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
} elseif ($time_filter === 'monthly') {
    $query .= " AND vp.last_watched >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
}

$query .= "
        ), 0) as videos_completed,
        COALESCE((
            SELECT COUNT(DISTINCT c.id) 
            FROM courses c
            JOIN enrollments e ON c.id = e.course_id
            WHERE e.student_id = u.id AND e.status = 'completed'
        ), 0) as courses_completed,
        COALESCE((
            SELECT COUNT(DISTINCT m.id)
            FROM materials m
            JOIN courses c ON m.course_id = c.id
            JOIN enrollments e ON c.id = e.course_id
            WHERE e.student_id = u.id
        ), 0) as materials_accessed
    FROM users u
    LEFT JOIN test_attempts t ON u.id = t.student_id
    WHERE u.role = 'student' AND u.status = 'active'
";

if ($course_filter !== 'all') {
    $query .= " AND u.id IN (
        SELECT DISTINCT student_id 
        FROM enrollments 
        WHERE course_id = :course_id
    )";
}

$query .= " GROUP BY u.id, u.name, u.avatar, u.email 
            ORDER BY total_score DESC, tests_passed DESC, videos_completed DESC";

try {
    $stmt = $pdo->prepare($query);
    
    if ($course_filter !== 'all') {
        $stmt->bindParam(':course_id', $course_filter, PDO::PARAM_INT);
    }
    
    $stmt->execute();
    $leaderboard = $stmt->fetchAll();
} catch (Exception $e) {
    error_log("Error fetching leaderboard: " . $e->getMessage());
    $leaderboard = [];
}

// Calculate ranks
foreach ($leaderboard as $index => &$entry) {
    $entry['rank'] = $index + 1;
}

// Get current user's stats
$current_user_stats = null;
foreach ($leaderboard as $entry) {
    if ($entry['id'] == $user['id']) {
        $current_user_stats = $entry;
        break;
    }
}

// Get user's recent achievements
try {
    $achievements = $pdo->prepare("
        SELECT 
            t.title as test_name,
            t.score,
            t.passed,
            t.completed_at,
            c.title as course_title
        FROM test_attempts t
        JOIN tests ts ON t.test_id = ts.id
        JOIN courses c ON ts.course_id = c.id
        WHERE t.student_id = ? AND t.passed = 1
        ORDER BY t.completed_at DESC
        LIMIT 5
    ");
    $achievements->execute([$user['id']]);
    $recent_achievements = $achievements->fetchAll();
} catch (Exception $e) {
    error_log("Error fetching achievements: " . $e->getMessage());
    $recent_achievements = [];
}

// Get enrolled courses with progress
try {
    $course_progress = $pdo->prepare("
        SELECT 
            c.id,
            c.title,
            e.status,
            e.enrolled_at,
            (
                SELECT COUNT(DISTINCT v.id) 
                FROM videos v 
                WHERE v.course_id = c.id AND v.status = 'published'
            ) as total_videos,
            (
                SELECT COUNT(DISTINCT vp.video_id) 
                FROM video_progress vp 
                JOIN videos v ON vp.video_id = v.id
                WHERE vp.student_id = ? AND v.course_id = c.id AND vp.completed = 1
            ) as completed_videos,
            (
                SELECT COUNT(DISTINCT t.id)
                FROM tests t
                WHERE t.course_id = c.id
            ) as total_tests,
            (
                SELECT COUNT(DISTINCT ta.test_id)
                FROM test_attempts ta
                JOIN tests t ON ta.test_id = t.id
                WHERE ta.student_id = ? AND t.course_id = c.id AND ta.passed = 1
            ) as passed_tests
        FROM courses c
        JOIN enrollments e ON c.id = e.course_id
        WHERE e.student_id = ? AND e.status = 'active'
        ORDER BY e.enrolled_at DESC
    ");
    $course_progress->execute([$user['id'], $user['id'], $user['id']]);
    $enrolled_courses = $course_progress->fetchAll();
} catch (Exception $e) {
    error_log("Error fetching course progress: " . $e->getMessage());
    $enrolled_courses = [];
}

// Get overall stats
try {
    $stats_query = "
        SELECT 
            COUNT(DISTINCT student_id) as total_students,
            AVG(total_score) as avg_score,
            MAX(total_score) as highest_score
        FROM (
            SELECT 
                student_id,
                SUM(score) as total_score
            FROM test_attempts
            WHERE passed = 1
            GROUP BY student_id
        ) as student_scores
    ";
    $stats = $pdo->query($stats_query)->fetch();
} catch (Exception $e) {
    error_log("Error fetching stats: " . $e->getMessage());
    $stats = ['total_students' => 0, 'avg_score' => 0, 'highest_score' => 0];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Leaderboard - NEP 2020 Student Dashboard</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .rank-1 { background: linear-gradient(135deg, #FFD700 0%, #FFA500 100%); }
        .rank-2 { background: linear-gradient(135deg, #C0C0C0 0%, #A0A0A0 100%); }
        .rank-3 { background: linear-gradient(135deg, #CD7F32 0%, #B87333 100%); }
        .podium-card { transition: transform 0.3s ease; }
        .podium-card:hover { transform: translateY(-5px); }
        .progress-bar { transition: width 0.5s ease; }
    </style>
</head>
<body class="bg-gray-50">
   
        
    <!-- Header -->
    <header class="bg-white shadow-sm sticky top-0 z-10">
      
        <div class="flex items-center justify-between px-8 py-4">
            <div>
                <h1 class="text-2xl font-bold text-gray-800">
                    <i class="fas fa-trophy text-yellow-500 mr-2"></i>
                    Leaderboard
                </h1>
                <p class="text-gray-500 mt-1">Compete, learn, and excel with NEP 2020</p>
            </div>
            <div class="flex items-center space-x-4">
                <a href="dashboard.php" class="text-gray-600 hover:text-gray-800">
                    <i class="fas fa-arrow-left mr-2"></i>Back to Dashboard
                </a>
            </div>
        </div>
    </header>

    <main class="max-w-7xl mx-auto px-8 py-6">
        <!-- Quick Stats -->
<div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
    <div class="bg-white rounded-xl shadow-sm p-6">
        <div class="flex items-center">
            <div class="p-3 bg-blue-100 rounded-lg">
                <i class="fas fa-users text-2xl text-blue-600"></i>
            </div>
            <div class="ml-4">
                <p class="text-sm text-gray-500">Total Students</p>
                <p class="text-2xl font-bold text-gray-800"><?= $stats['total_students'] ?? 0 ?></p>
            </div>
        </div>
    </div>
    <div class="bg-white rounded-xl shadow-sm p-6">
        <div class="flex items-center">
            <div class="p-3 bg-green-100 rounded-lg">
                <i class="fas fa-chart-line text-2xl text-green-600"></i>
            </div>
            <div class="ml-4">
                <p class="text-sm text-gray-500">Average Score</p>
                <p class="text-2xl font-bold text-gray-800"><?= isset($stats['avg_score']) && $stats['avg_score'] !== null ? number_format((float)$stats['avg_score'], 1) : '0.0' ?></p>
            </div>
        </div>
    </div>
    <div class="bg-white rounded-xl shadow-sm p-6">
        <div class="flex items-center">
            <div class="p-3 bg-purple-100 rounded-lg">
                <i class="fas fa-crown text-2xl text-purple-600"></i>
            </div>
            <div class="ml-4">
                <p class="text-sm text-gray-500">Highest Score</p>
                <p class="text-2xl font-bold text-gray-800"><?= isset($stats['highest_score']) && $stats['highest_score'] !== null ? number_format((float)$stats['highest_score']) : '0' ?></p>
            </div>
        </div>
    </div>
</div><!-- Quick Stats -->
        

        <!-- Current User Stats Card -->
        <?php if ($current_user_stats): ?>
        <div class="bg-gradient-to-r from-blue-600 to-purple-600 rounded-xl shadow-lg p-6 mb-8 text-white">
            <div class="flex items-center justify-between flex-wrap gap-4">
                <div class="flex items-center space-x-4">
                    <div class="w-16 h-16 bg-white rounded-full flex items-center justify-center">
                        <span class="text-2xl font-bold text-blue-600">#<?= $current_user_stats['rank'] ?></span>
                    </div>
                    <div>
                        <h2 class="text-xl font-semibold">Your Performance</h2>
                        <p class="text-blue-100">Keep learning to climb the ranks!</p>
                    </div>
                </div>
                <div class="flex space-x-6">
                    <div class="text-center">
                        <div class="text-2xl font-bold"><?= number_format($current_user_stats['total_score']) ?></div>
                        <div class="text-sm text-blue-100">Total Score</div>
                    </div>
                    <div class="text-center">
                        <div class="text-2xl font-bold"><?= $current_user_stats['tests_passed'] ?></div>
                        <div class="text-sm text-blue-100">Tests Passed</div>
                    </div>
                    <div class="text-center">
                        <div class="text-2xl font-bold"><?= $current_user_stats['videos_completed'] ?></div>
                        <div class="text-sm text-blue-100">Videos Watched</div>
                    </div>
                    <div class="text-center">
                        <div class="text-2xl font-bold"><?= $current_user_stats['courses_completed'] ?></div>
                        <div class="text-sm text-blue-100">Courses Completed</div>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Filters -->
        <div class="bg-white rounded-xl shadow-sm p-6 mb-8">
            <form method="GET" class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Time Period</label>
                    <select name="time" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <option value="all" <?= $time_filter == 'all' ? 'selected' : '' ?>>All Time</option>
                        <option value="weekly" <?= $time_filter == 'weekly' ? 'selected' : '' ?>>This Week</option>
                        <option value="monthly" <?= $time_filter == 'monthly' ? 'selected' : '' ?>>This Month</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Course</label>
                    <select name="course" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <option value="all">All Courses</option>
                        <?php foreach ($courses as $course): ?>
                            <option value="<?= $course['id'] ?>" <?= $course_filter == $course['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($course['title']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="flex items-end">
                    <button type="submit" class="w-full bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 transition">
                        <i class="fas fa-filter mr-2"></i>Apply Filters
                    </button>
                </div>
            </form>
        </div>

        <!-- Podium (Top 3) -->
        <?php if (!empty($leaderboard) && count($leaderboard) >= 3): ?>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
            <!-- 2nd Place -->
            <div class="podium-card order-2">
                <div class="bg-white rounded-xl shadow-lg overflow-hidden">
                    <div class="rank-2 h-2"></div>
                    <div class="p-6 text-center">
                        <div class="inline-block p-3 bg-gray-100 rounded-full mb-4">
                            <i class="fas fa-crown text-3xl text-gray-400"></i>
                        </div>
                        <h3 class="text-xl font-bold text-gray-800"><?= htmlspecialchars($leaderboard[1]['name']) ?></h3>
                        <p class="text-gray-500 text-sm mb-2"><?= htmlspecialchars($leaderboard[1]['email']) ?></p>
                        <div class="text-3xl font-bold text-gray-800 mb-2">#2</div>
                        <div class="flex justify-center space-x-4 text-sm">
                            <span class="text-green-600 font-semibold"><?= number_format($leaderboard[1]['total_score']) ?> pts</span>
                            <span class="text-gray-500"><?= $leaderboard[1]['tests_passed'] ?> tests</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- 1st Place -->
            <div class="podium-card order-1 md:order-2">
                <div class="bg-white rounded-xl shadow-lg overflow-hidden transform scale-105">
                    <div class="rank-1 h-3"></div>
                    <div class="p-6 text-center">
                        <div class="inline-block p-3 bg-yellow-100 rounded-full mb-4">
                            <i class="fas fa-crown text-3xl text-yellow-500"></i>
                        </div>
                        <h3 class="text-xl font-bold text-gray-800"><?= htmlspecialchars($leaderboard[0]['name']) ?></h3>
                        <p class="text-gray-500 text-sm mb-2"><?= htmlspecialchars($leaderboard[0]['email']) ?></p>
                        <div class="text-4xl font-bold text-yellow-600 mb-2">#1</div>
                        <div class="flex justify-center space-x-4 text-sm">
                            <span class="text-green-600 font-semibold"><?= number_format($leaderboard[0]['total_score']) ?> pts</span>
                            <span class="text-gray-500"><?= $leaderboard[0]['tests_passed'] ?> tests</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- 3rd Place -->
            <div class="podium-card order-3">
                <div class="bg-white rounded-xl shadow-lg overflow-hidden">
                    <div class="rank-3 h-2"></div>
                    <div class="p-6 text-center">
                        <div class="inline-block p-3 bg-orange-100 rounded-full mb-4">
                            <i class="fas fa-crown text-3xl text-orange-700"></i>
                        </div>
                        <h3 class="text-xl font-bold text-gray-800"><?= htmlspecialchars($leaderboard[2]['name']) ?></h3>
                        <p class="text-gray-500 text-sm mb-2"><?= htmlspecialchars($leaderboard[2]['email']) ?></p>
                        <div class="text-3xl font-bold text-gray-800 mb-2">#3</div>
                        <div class="flex justify-center space-x-4 text-sm">
                            <span class="text-green-600 font-semibold"><?= number_format($leaderboard[2]['total_score']) ?> pts</span>
                            <span class="text-gray-500"><?= $leaderboard[2]['tests_passed'] ?> tests</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Full Leaderboard Table -->
        <div class="bg-white rounded-xl shadow-sm overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-200 bg-gray-50">
                <h2 class="text-lg font-semibold text-gray-800">
                    <i class="fas fa-list-ol mr-2 text-blue-600"></i>
                    Complete Rankings
                </h2>
            </div>
            
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead class="bg-gray-100">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Rank</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Student</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Tests Passed</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Videos Completed</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Courses Completed</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Materials</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Total Score</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        <?php foreach ($leaderboard as $entry): ?>
                            <?php 
                            $isCurrentUser = $entry['id'] == $user['id'];
                            $rowClass = $isCurrentUser ? 'bg-blue-50 hover:bg-blue-100' : 'hover:bg-gray-50';
                            ?>
                            <tr class="<?= $rowClass ?> transition">
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <?php if ($entry['rank'] <= 3): ?>
                                        <span class="inline-flex items-center justify-center w-8 h-8 rounded-full 
                                            <?= $entry['rank'] == 1 ? 'bg-yellow-100 text-yellow-600' : ($entry['rank'] == 2 ? 'bg-gray-200 text-gray-600' : 'bg-orange-100 text-orange-600') ?> 
                                            font-bold">
                                            #<?= $entry['rank'] ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="text-gray-500 font-medium">#<?= $entry['rank'] ?></span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="flex items-center">
                                        <div class="flex-shrink-0 h-10 w-10">
                                            <?php if (!empty($entry['avatar'])): ?>
                                                <img class="h-10 w-10 rounded-full" src="<?= htmlspecialchars($entry['avatar']) ?>" alt="">
                                            <?php else: ?>
                                                <div class="h-10 w-10 rounded-full bg-gradient-to-r from-blue-500 to-purple-500 flex items-center justify-center">
                                                    <span class="text-white font-medium">
                                                        <?= strtoupper(substr($entry['name'], 0, 2)) ?>
                                                    </span>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                        <div class="ml-4">
                                            <div class="text-sm font-medium text-gray-900">
                                                <?= htmlspecialchars($entry['name']) ?>
                                                <?php if ($isCurrentUser): ?>
                                                    <span class="ml-2 px-2 py-0.5 text-xs bg-blue-600 text-white rounded-full">You</span>
                                                <?php endif; ?>
                                            </div>
                                            <div class="text-xs text-gray-500"><?= htmlspecialchars($entry['email']) ?></div>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    <?= $entry['tests_passed'] ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    <?= $entry['videos_completed'] ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    <?= $entry['courses_completed'] ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    <?= $entry['materials_accessed'] ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="text-sm font-semibold text-green-600">
                                        <?= number_format($entry['total_score']) ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>

                        <?php if (empty($leaderboard)): ?>
                            <tr>
                                <td colspan="7" class="px-6 py-12 text-center text-gray-500">
                                    <i class="fas fa-trophy text-4xl mb-3 text-gray-300"></i>
                                    <p class="text-lg">No data available for the selected filters</p>
                                    <p class="text-sm mt-2">Try adjusting your filters or check back later</p>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Recent Achievements -->
        <?php if (!empty($recent_achievements)): ?>
        <div class="mt-8">
            <h3 class="text-lg font-semibold text-gray-800 mb-4">
                <i class="fas fa-star mr-2 text-yellow-500"></i>
                Your Recent Achievements
            </h3>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                <?php foreach ($recent_achievements as $achievement): ?>
                <div class="bg-white rounded-lg shadow-sm p-4 hover:shadow-md transition">
                    <div class="flex items-start space-x-3">
                        <div class="text-2xl">🏆</div>
                        <div>
                            <h4 class="font-medium text-gray-800"><?= htmlspecialchars($achievement['test_name']) ?></h4>
                            <p class="text-sm text-gray-500">Course: <?= htmlspecialchars($achievement['course_title']) ?></p>
                            <div class="flex items-center mt-2">
                                <span class="text-xs bg-green-100 text-green-600 px-2 py-1 rounded-full">
                                    Score: <?= $achievement['score'] ?>%
                                </span>
                                <span class="text-xs text-gray-400 ml-2">
                                    <?= date('M d, Y', strtotime($achievement['completed_at'])) ?>
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Course Progress -->
        <?php if (!empty($enrolled_courses)): ?>
        <div class="mt-8">
            <h3 class="text-lg font-semibold text-gray-800 mb-4">
                <i class="fas fa-book-open mr-2 text-blue-600"></i>
                Your Course Progress
            </h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <?php foreach ($enrolled_courses as $course): ?>
                <div class="bg-white rounded-lg shadow-sm p-4">
                    <div class="flex justify-between items-start mb-2">
                        <h4 class="font-medium text-gray-800"><?= htmlspecialchars($course['title']) ?></h4>
                        <span class="text-xs bg-blue-100 text-blue-600 px-2 py-1 rounded-full">
                            <?= $course['total_videos'] ?> videos
                        </span>
                    </div>
                    <?php 
                    $video_progress = $course['total_videos'] > 0 
                        ? round(($course['completed_videos'] / $course['total_videos']) * 100) 
                        : 0;
                    $test_progress = $course['total_tests'] > 0
                        ? round(($course['passed_tests'] / $course['total_tests']) * 100)
                        : 0;
                    ?>
                    <div class="space-y-2">
                        <div>
                            <div class="flex justify-between text-xs text-gray-500 mb-1">
                                <span>Videos Progress</span>
                                <span><?= $video_progress ?>%</span>
                            </div>
                            <div class="w-full bg-gray-200 rounded-full h-2">
                                <div class="bg-blue-600 h-2 rounded-full progress-bar" style="width: <?= $video_progress ?>%"></div>
                            </div>
                        </div>
                        <div>
                            <div class="flex justify-between text-xs text-gray-500 mb-1">
                                <span>Tests Progress</span>
                                <span><?= $test_progress ?>%</span>
                            </div>
                            <div class="w-full bg-gray-200 rounded-full h-2">
                                <div class="bg-green-600 h-2 rounded-full progress-bar" style="width: <?= $test_progress ?>%"></div>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
    </main>

    <script>
        // Add loading state to filter button
        document.querySelector('form').addEventListener('submit', function() {
            const button = this.querySelector('button[type="submit"]');
            button.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Loading...';
            button.disabled = true;
        });
    </script>
</body>
</html>