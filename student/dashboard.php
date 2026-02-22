<?php
require_once '../includes/auth.php';
require_once '../includes/student-functions.php';
requireStudent();

$user_id = $_SESSION['user_id'];

// Initialize default values
$student = [
    'name' => $_SESSION['name'] ?? 'Student',
    'current_streak' => 0,
    'longest_streak' => 0,
    'total_xp' => 0,
    'level' => 1,
    'global_rank' => 1,
    'total_students' => 1,
    'certificates_count' => 0
];

try {
    // Get student data with error handling for missing tables
    $stmt = $pdo->prepare("
        SELECT 
            u.*,
            COALESCE(ss.current_streak, 0) as current_streak,
            COALESCE(ss.longest_streak, 0) as longest_streak,
            COALESCE(sx.xp_points, 0) as total_xp,
            COALESCE(sx.level, 1) as level,
            sr.global_rank,
            (SELECT COUNT(*) FROM users WHERE role = 'student') as total_students,
            (SELECT COUNT(*) FROM enrollments WHERE student_id = u.id AND status = 'active') as enrolled_courses_count,
            (SELECT COUNT(*) FROM certificates WHERE student_id = u.id) as certificates_count
        FROM users u
        LEFT JOIN student_streaks ss ON u.id = ss.student_id
        LEFT JOIN student_xp sx ON u.id = sx.student_id
        LEFT JOIN student_rankings sr ON u.id = sr.student_id
        WHERE u.id = ?
    ");
    $stmt->execute([$user_id]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($result) {
        $student = array_merge($student, $result);
    }
} catch (PDOException $e) {
    error_log("Dashboard query error: " . $e->getMessage());
}

// Calculate percentile
$total_students = $student['total_students'] ?? 1;
$global_rank = $student['global_rank'] ?? 1;
$percentile = $total_students > 0 ? round((($total_students - $global_rank + 1) / $total_students) * 100, 1) : 0;

// Get today's classes
$today_class = null;
try {
    $tables = $pdo->query("SHOW TABLES LIKE 'classes'")->rowCount();
    if ($tables > 0) {
        $stmt = $pdo->prepare("
            SELECT c.*, crs.title as course_title, crs.id as course_id,
                   ca.attended, ca.completed_video, ca.completed_mcq, ca.completed_lab
            FROM classes c
            JOIN courses crs ON c.course_id = crs.id
            LEFT JOIN class_attendance ca ON c.id = ca.class_id AND ca.student_id = ?
            WHERE c.class_date = CURDATE()
            ORDER BY c.start_time
            LIMIT 1
        ");
    } else {
        $tables = $pdo->query("SHOW TABLES LIKE 'class_schedule'")->rowCount();
        if ($tables > 0) {
            $stmt = $pdo->prepare("
                SELECT cs.*, crs.title as course_title, crs.id as course_id,
                       ca.attended, ca.completed_video, ca.completed_mcq, ca.completed_lab
                FROM class_schedule cs
                JOIN courses crs ON cs.course_id = crs.id
                LEFT JOIN class_attendance ca ON cs.id = ca.class_id AND ca.student_id = ?
                WHERE cs.scheduled_date = CURDATE()
                ORDER BY cs.start_time
                LIMIT 1
            ");
        } else {
            $stmt = null;
        }
    }
    
    if ($stmt) {
        $stmt->execute([$user_id]);
        $today_class = $stmt->fetch(PDO::FETCH_ASSOC);
    }
} catch (PDOException $e) {
    error_log("Class query error: " . $e->getMessage());
}

// Get coaching courses (My Courses)
$coaching_courses = [];
try {
    $stmt = $pdo->prepare("
        SELECT 
            c.*,
            e.enrolled_at,
            e.status as enrollment_status,
            (SELECT COUNT(*) FROM videos WHERE course_id = c.id) as total_videos,
            (SELECT COUNT(*) FROM video_progress vp 
             JOIN videos v ON vp.video_id = v.id 
             WHERE v.course_id = c.id AND vp.student_id = ? AND vp.completed = 1) as completed_videos,
            'coaching' as course_type
        FROM courses c
        JOIN enrollments e ON c.id = e.course_id
        WHERE e.student_id = ? AND e.status = 'active'
        ORDER BY e.enrolled_at DESC
        LIMIT 3
    ");
    $stmt->execute([$user_id, $user_id]);
    $coaching_courses = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Coaching courses query error: " . $e->getMessage());
}

// Get academic courses (School/College)
$academic_courses = [];
try {
    // Check if academic_courses table exists
    $academic_table = $pdo->query("SHOW TABLES LIKE 'academic_courses'")->rowCount();
    if ($academic_table > 0) {
        $stmt = $pdo->prepare("
            SELECT 
                ac.*,
                aep.progress as progress_percentage,
                aep.completed_topics,
                aep.total_topics
            FROM academic_courses ac
            JOIN academic_enrollment aep ON ac.id = aep.course_id
            WHERE aep.student_id = ? AND aep.status = 'active'
            ORDER BY aep.enrolled_at DESC
            LIMIT 3
        ");
        $stmt->execute([$user_id]);
        $academic_courses = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (PDOException $e) {
    error_log("Academic courses query error: " . $e->getMessage());
}

// Calculate progress for first coaching course
$coaching_progress = 0;
if (!empty($coaching_courses)) {
    $first_course = $coaching_courses[0];
    $coaching_progress = ($first_course['total_videos'] ?? 0) > 0 
        ? round((($first_course['completed_videos'] ?? 0) / ($first_course['total_videos'] ?? 1)) * 100) 
        : 0;
}

// Get notifications
$notifications = [];
$unread_count = 0;
try {
    $stmt = $pdo->prepare("
        SELECT * FROM student_notifications 
        WHERE student_id = ? AND is_read = 0
        ORDER BY created_at DESC
        LIMIT 5
    ");
    $stmt->execute([$user_id]);
    $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM student_notifications WHERE student_id = ? AND is_read = 0");
    $stmt->execute([$user_id]);
    $unread_count = $stmt->fetchColumn();
} catch (PDOException $e) {
    error_log("Notifications query error: " . $e->getMessage());
}

// Get weekly XP
$week_xp = 0;
try {
    $week_start = date('Y-m-d', strtotime('monday this week'));
    $stmt = $pdo->prepare("
        SELECT SUM(xp_amount) as week_xp 
        FROM xp_transactions 
        WHERE student_id = ? AND DATE(created_at) >= ?
    ");
    $stmt->execute([$user_id, $week_start]);
    $week_xp = $stmt->fetchColumn() ?: 0;
} catch (PDOException $e) {
    error_log("XP query error: " . $e->getMessage());
}

// Get weekly rank
$weekly_rank = 1;
try {
    $stmt = $pdo->prepare("
        SELECT weekly_rank 
        FROM student_rankings 
        WHERE student_id = ?
    ");
    $stmt->execute([$user_id]);
    $weekly_rank_data = $stmt->fetch(PDO::FETCH_ASSOC);
    $weekly_rank = $weekly_rank_data['weekly_rank'] ?? 1;
} catch (PDOException $e) {
    error_log("Rank query error: " . $e->getMessage());
}

// Get top students
$top_students_list = [];
try {
    $top_students = $pdo->prepare("
        SELECT u.name, COALESCE(sx.weekly_xp, 0) as weekly_xp,
               u.avatar, u.id
        FROM users u
        LEFT JOIN student_xp sx ON u.id = sx.student_id
        WHERE u.role = 'student'
        ORDER BY sx.weekly_xp DESC
        LIMIT 5
    ");
    $top_students->execute();
    $top_students_list = $top_students->fetchAll();
} catch (PDOException $e) {
    error_log("Top students query error: " . $e->getMessage());
}

// Get academic stats
$academic_stats = [
    'total_subjects' => 6,
    'completed_topics' => 24,
    'total_topics' => 45,
    'average_score' => 85,
    'attendance' => 92
];

// Get weekly schedule
$weekly_schedule = [
    ['day' => 'Mon', 'subject' => 'Mathematics', 'time' => '10:00 AM', 'type' => 'Lecture'],
    ['day' => 'Tue', 'subject' => 'Physics', 'time' => '11:30 AM', 'type' => 'Lab'],
    ['day' => 'Wed', 'subject' => 'Chemistry', 'time' => '09:00 AM', 'type' => 'Lecture'],
    ['day' => 'Thu', 'subject' => 'English', 'time' => '02:00 PM', 'type' => 'Tutorial'],
    ['day' => 'Fri', 'subject' => 'Computer Science', 'time' => '10:00 AM', 'type' => 'Practical']
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Dashboard</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap');
        
        * {
            font-family: 'Inter', sans-serif;
        }
        
        .gradient-bg {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
        
        .progress-ring {
            transition: stroke-dashoffset 0.35s;
            transform: rotate(-90deg);
            transform-origin: 50% 50%;
        }
        
        .stat-card {
            transition: all 0.3s ease;
            border: 1px solid rgba(255,255,255,0.2);
            backdrop-filter: blur(10px);
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 20px 25px -5px rgba(0,0,0,0.2), 0 10px 10px -5px rgba(0,0,0,0.1);
        }
        
        .coaching-card {
            border: 1px solid rgba(59, 130, 246, 0.2);
            transition: all 0.3s ease;
        }
        
        .coaching-card:hover {
            border-color: #3b82f6;
            box-shadow: 0 10px 25px -5px rgba(59, 130, 246, 0.3);
        }
        
        .academic-card {
            border: 1px solid rgba(16, 185, 129, 0.2);
            transition: all 0.3s ease;
        }
        
        .academic-card:hover {
            border-color: #10b981;
            box-shadow: 0 10px 25px -5px rgba(16, 185, 129, 0.3);
        }
        
        .notification-badge {
            animation: pulse 2s infinite;
        }
        
        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.1); }
            100% { transform: scale(1); }
        }
        
        .section-title {
            position: relative;
            display: inline-block;
        }
        
        .section-title:after {
            content: '';
            position: absolute;
            bottom: -8px;
            left: 0;
            width: 60px;
            height: 4px;
            background: linear-gradient(90deg, #3b82f6, #8b5cf6);
            border-radius: 2px;
        }
    </style>
</head>
<body class="bg-gray-100">
    <div class="flex h-screen">
        <!-- Sidebar -->
        <?php include 'sidebar.php'; ?>

        <!-- Main Content -->
        <div class=" bg-gray-100 overflow-auto">
            <!-- Header -->
           <header class="bg-white shadow-sm sticky top-0 z-10">
    <div class="flex items-center justify-between px-8 py-4">
        <div>
            <h2 class="text-2xl font-bold text-gray-800">
                <?php
                // Get current hour (24-hour format)
                $hour = date('H');
                
                // Determine greeting based on time
                if ($hour >= 5 && $hour < 12) {
                    $greeting = "Good morning";
                } elseif ($hour >= 12 && $hour < 17) {
                    $greeting = "Good afternoon";
                } elseif ($hour >= 17 && $hour < 21) {
                    $greeting = "Good evening";
                } else {
                    $greeting = "Good night";
                }
                
                // Add emoji based on time
                $emoji = match(true) {
                    $hour >= 5 && $hour < 12 => "🌅", // Morning
                    $hour >= 12 && $hour < 17 => "☀️", // Afternoon
                    $hour >= 17 && $hour < 21 => "🌆", // Evening
                    default => "🌙" // Night
                };
                
                echo htmlspecialchars("$greeting, {$student['name']}! $emoji");
                ?>
            </h2>
            <p class="text-gray-500 mt-1">
                <?= date('l, F j, Y') ?> • 
                <span class="text-blue-600 font-medium"><?= $today_class ? '1 class scheduled' : 'No classes today' ?></span>
            </p>
            <!-- NEP 2020 Badge/Quote - Added for context -->
            <p class="text-xs text-purple-600 mt-2 font-medium">
                <i class="fas fa-graduation-cap mr-1"></i>
                National Education Policy 2020 • Holistic & Multidisciplinary Learning
            </p>
        </div>
        <div class="flex items-center space-x-6">
            <!-- Notifications -->
            <div class="relative">
                <button class="relative p-2 text-gray-400 hover:text-gray-600 transition" onclick="toggleNotifications()">
                    <i class="fas fa-bell text-xl"></i>
                    <?php if ($unread_count > 0): ?>
                        <span class="absolute top-0 right-0 w-5 h-5 bg-red-500 text-white text-xs rounded-full flex items-center justify-center notification-badge">
                            <?= $unread_count > 5 ? '5+' : $unread_count ?>
                        </span>
                    <?php endif; ?>
                </button>
                
                <!-- Notifications Dropdown -->
                <div id="notifications-dropdown" class="hidden absolute right-0 mt-3 w-80 bg-white rounded-xl shadow-xl border border-gray-200 overflow-hidden z-50">
                    <div class="p-4 border-b border-gray-100 bg-gradient-to-r from-blue-600 to-purple-600">
                        <h3 class="font-semibold text-white">Notifications</h3>
                    </div>
                    <div class="max-h-96 overflow-y-auto">
                        <?php if (empty($notifications)): ?>
                            <div class="p-4 text-center text-gray-500">
                                <i class="far fa-bell text-3xl mb-2"></i>
                                <p>No new notifications</p>
                            </div>
                        <?php else: ?>
                            <?php foreach ($notifications as $note): ?>
                                <div class="p-4 hover:bg-gray-50 border-b border-gray-100 last:border-0">
                                    <div class="flex items-start space-x-3">
                                        <div class="flex-shrink-0">
                                            <?php
                                            $icon = match($note['type'] ?? 'info') {
                                                'success' => 'fa-check-circle text-green-500',
                                                'warning' => 'fa-exclamation-triangle text-yellow-500',
                                                'reminder' => 'fa-clock text-blue-500',
                                                default => 'fa-info-circle text-gray-500'
                                            };
                                            ?>
                                            <i class="fas <?= $icon ?>"></i>
                                        </div>
                                        <div>
                                            <p class="text-sm font-medium text-gray-800"><?= htmlspecialchars($note['title'] ?? 'Notification') ?></p>
                                            <p class="text-xs text-gray-500 mt-1"><?= htmlspecialchars($note['message'] ?? '') ?></p>
                                            <p class="text-xs text-gray-400 mt-2"><?= isset($note['created_at']) ? date('g:i A', strtotime($note['created_at'])) : '' ?></p>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                    <div class="p-3 border-t border-gray-100 bg-gray-50 text-center">
                        <a href="notifications.php" class="text-sm text-blue-600 hover:text-blue-700 font-medium">View all notifications →</a>
                    </div>
                </div>
            </div>
            
            <!-- Weather Widget (Optional) -->
            <div class="flex items-center space-x-2 bg-gray-100 px-3 py-2 rounded-lg">
                <?php
                // Dynamic weather icon based on time
                $weatherIcon = match(true) {
                    $hour >= 5 && $hour < 12 => 'fa-sun text-yellow-500',
                    $hour >= 12 && $hour < 17 => 'fa-sun text-orange-500',
                    $hour >= 17 && $hour < 21 => 'fa-cloud-sun text-purple-500',
                    default => 'fa-moon text-indigo-500'
                };
                ?>
                <i class="fas <?= $weatherIcon ?>"></i>
                <span class="text-sm text-gray-600">24°C</span>
            </div>
        </div>
    </div>
</header>

            <!-- Main Content Area -->
            <div class="p-8">
                <!-- Stats Grid with Gradient Cards -->
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                    <!-- Course Progress Card -->
                    <div class="stat-card bg-gradient-to-br from-blue-500 to-blue-600 rounded-2xl shadow-lg p-6 text-white">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-blue-100 mb-1">Course Progress</p>
                                <p class="text-3xl font-bold"><?= $coaching_progress ?>%</p>
                                <p class="text-sm text-blue-100 mt-2">
                                    <?= !empty($coaching_courses) ? htmlspecialchars($coaching_courses[0]['title']) : 'No courses' ?>
                                </p>
                            </div>
                            <div class="relative w-20 h-20">
                                <svg class="w-20 h-20 transform -rotate-90">
                                    <circle cx="40" cy="40" r="36" stroke="rgba(255,255,255,0.2)" stroke-width="4" fill="transparent"></circle>
                                    <circle cx="40" cy="40" r="36" stroke="white" stroke-width="4" fill="transparent" 
                                            stroke-dasharray="<?= 2 * pi() * 36 ?>" 
                                            stroke-dashoffset="<?= 2 * pi() * 36 * (1 - $coaching_progress / 100) ?>"
                                            class="progress-ring"></circle>
                                </svg>
                                <div class="absolute inset-0 flex items-center justify-center">
                                    <i class="fas fa-code text-white text-xl"></i>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Global Rank Card -->
                    <div class="stat-card bg-gradient-to-br from-purple-500 to-purple-600 rounded-2xl shadow-lg p-6 text-white">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-purple-100 mb-1">Global Rank</p>
                                <p class="text-3xl font-bold">#<?= number_format($global_rank) ?></p>
                                <p class="text-sm text-purple-100 mt-2">Among <?= number_format($total_students) ?> students</p>
                            </div>
                            <div class="w-14 h-14 bg-white bg-opacity-20 rounded-xl flex items-center justify-center">
                                <i class="fas fa-trophy text-white text-2xl"></i>
                            </div>
                        </div>
                        <div class="mt-4 pt-3 border-t border-white border-opacity-20">
                            <div class="flex items-center justify-between text-sm">
                                <span class="text-purple-100">Top <?= $percentile ?>%</span>
                                <span class="text-white">↑ #<?= $weekly_rank ?> this week</span>
                            </div>
                        </div>
                    </div>

                    <!-- Streak Card -->
                    <div class="stat-card bg-gradient-to-br from-orange-500 to-orange-600 rounded-2xl shadow-lg p-6 text-white">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-orange-100 mb-1">Learning Streak</p>
                                <p class="text-3xl font-bold"><?= $student['current_streak'] ?></p>
                                <p class="text-sm text-orange-100 mt-2">Days in a row</p>
                            </div>
                            <div class="w-14 h-14 bg-white bg-opacity-20 rounded-xl flex items-center justify-center">
                                <i class="fas fa-fire text-white text-2xl"></i>
                            </div>
                        </div>
                        <div class="mt-4 pt-3 border-t border-white border-opacity-20">
                            <p class="text-sm text-orange-100">Best: <?= $student['longest_streak'] ?> days</p>
                        </div>
                    </div>

                    <!-- XP Points Card -->
                    <div class="stat-card bg-gradient-to-br from-green-500 to-green-600 rounded-2xl shadow-lg p-6 text-white">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-green-100 mb-1">XP Points</p>
                                <p class="text-3xl font-bold"><?= number_format($student['total_xp']) ?></p>
                                <p class="text-sm text-green-100 mt-2">Level <?= $student['level'] ?></p>
                            </div>
                            <div class="w-14 h-14 bg-white bg-opacity-20 rounded-xl flex items-center justify-center">
                                <i class="fas fa-bolt text-white text-2xl"></i>
                            </div>
                        </div>
                        <div class="mt-4 pt-3 border-t border-white border-opacity-20">
                            <div class="flex items-center justify-between text-sm">
                                <span class="text-green-100">Weekly XP</span>
                                <span class="font-semibold"><?= number_format($week_xp) ?></span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Today's Class Banner -->
                <?php if ($today_class): ?>
                <div class="bg-gradient-to-r from-indigo-600 via-purple-600 to-pink-600 rounded-2xl shadow-xl p-8 mb-8 text-white">
                    <div class="flex items-start justify-between flex-wrap gap-4">
                        <div>
                            <span class="inline-block px-4 py-1 bg-white bg-opacity-20 rounded-full text-sm mb-4 backdrop-blur-sm">
                                <i class="fas fa-video mr-2"></i>Today's Class
                            </span>
                            <h2 class="text-3xl font-bold mb-3"><?= htmlspecialchars($today_class['title'] ?? 'List Comprehension & Loops') ?></h2>
                            <p class="text-indigo-100 mb-6 max-w-2xl">
                                <?= htmlspecialchars($today_class['course_title'] ?? 'Python with AI') ?> • Week 8
                            </p>
                            <div class="flex items-center space-x-4 mb-6">
                                <span class="flex items-center text-sm bg-white bg-opacity-10 px-3 py-1 rounded-full">
                                    <i class="fas fa-clock mr-2"></i>20 min Video
                                </span>
                                <span class="flex items-center text-sm bg-white bg-opacity-10 px-3 py-1 rounded-full">
                                    <i class="fas fa-file-alt mr-2"></i>10 min MCQ
                                </span>
                                <span class="flex items-center text-sm bg-white bg-opacity-10 px-3 py-1 rounded-full">
                                    <i class="fas fa-flask mr-2"></i>10 min Lab
                                </span>
                            </div>
                            <div class="flex space-x-4">
                                <a href="class.php?id=<?= $today_class['id'] ?? 1 ?>" class="px-6 py-3 bg-white text-indigo-600 rounded-xl font-semibold hover:shadow-lg transition transform hover:scale-105">
                                    <i class="fas fa-play-circle mr-2"></i>Start Class
                                </a>
                                <a href="#" class="px-6 py-3 bg-transparent border border-white text-white rounded-xl font-semibold hover:bg-white hover:bg-opacity-10 transition">
                                    <i class="fas fa-file-pdf mr-2"></i>Materials
                                </a>
                            </div>
                        </div>
                        <div class="hidden lg:block">
                            <div class="w-32 h-32 bg-white bg-opacity-10 backdrop-blur-sm rounded-2xl flex items-center justify-center border border-white border-opacity-20">
                                <i class="fas fa-play-circle text-5xl text-white opacity-70"></i>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Two Column Layout for Courses -->
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 mb-8">




                 <!-- My Academics Section (School/College) -->
                    <div class="bg-white rounded-2xl shadow-xl border border-gray-200 overflow-hidden">
                        <div class="bg-gradient-to-r from-green-600 to-emerald-600 px-6 py-4">
                            <div class="flex items-center justify-between">
                                <h2 class="text-xl font-bold text-white flex items-center">
                                    <i class="fas fa-graduation-cap mr-3"></i>My Academics
                                </h2>
                                <span class="text-xs text-white bg-white bg-opacity-20 px-3 py-1 rounded-full">School/College</span>
                            </div>
                        </div>
                        
                        <div class="p-6">
                            <!-- Academic Stats -->
                            <div class="grid grid-cols-2 gap-4 mb-6">
                                <div class="bg-gradient-to-br from-green-50 to-emerald-50 rounded-xl p-4 border border-green-100">
                                    <p class="text-sm text-gray-500 mb-1">Subjects</p>
                                    <p class="text-2xl font-bold text-gray-800"><?= $academic_stats['total_subjects'] ?></p>
                                </div>
                                <div class="bg-gradient-to-br from-blue-50 to-indigo-50 rounded-xl p-4 border border-blue-100">
                                    <p class="text-sm text-gray-500 mb-1">Avg. Score</p>
                                    <p class="text-2xl font-bold text-gray-800"><?= $academic_stats['average_score'] ?>%</p>
                                </div>
                                <div class="bg-gradient-to-br from-purple-50 to-pink-50 rounded-xl p-4 border border-purple-100">
                                    <p class="text-sm text-gray-500 mb-1">Topics Done</p>
                                    <p class="text-2xl font-bold text-gray-800"><?= $academic_stats['completed_topics'] ?>/<?= $academic_stats['total_topics'] ?></p>
                                </div>
                                <div class="bg-gradient-to-br from-orange-50 to-amber-50 rounded-xl p-4 border border-orange-100">
                                    <p class="text-sm text-gray-500 mb-1">Attendance</p>
                                    <p class="text-2xl font-bold text-gray-800"><?= $academic_stats['attendance'] ?>%</p>
                                </div>
                            </div>

                            <!-- Academic Courses/Subjects -->
                            <?php if (!empty($academic_courses)): ?>
                                <div class="space-y-4">
                                    <?php foreach ($academic_courses as $course): ?>
                                    <div class="academic-card border rounded-xl p-4 hover:shadow-lg transition">
                                        <div class="flex items-start justify-between">
                                            <div class="flex-1">
                                                <div class="flex items-center space-x-3 mb-2">
                                                    <div class="w-8 h-8 rounded-lg bg-gradient-to-r from-green-500 to-emerald-500 flex items-center justify-center">
                                                        <i class="fas fa-flask text-white text-sm"></i>
                                                    </div>
                                                    <h3 class="font-semibold text-gray-800"><?= htmlspecialchars($course['title'] ?? 'Physics') ?></h3>
                                                </div>
                                                <p class="text-sm text-gray-500 mb-3">Grade 12 • CBSE</p>
                                                
                                                <div class="space-y-2">
                                                    <div class="flex items-center justify-between text-sm">
                                                        <span class="text-gray-500">Syllabus Progress</span>
                                                        <span class="font-semibold text-green-600"><?= $course['progress_percentage'] ?? 65 ?>%</span>
                                                    </div>
                                                    <div class="w-full bg-gray-200 rounded-full h-2">
                                                        <div class="bg-gradient-to-r from-green-500 to-emerald-500 h-2 rounded-full" style="width: <?= $course['progress_percentage'] ?? 65 ?>%"></div>
                                                    </div>
                                                    <div class="flex items-center justify-between text-xs text-gray-500">
                                                        <span><i class="fas fa-book mr-1"></i><?= $course['completed_topics'] ?? 8 ?>/<?= $course['total_topics'] ?? 12 ?> topics</span>
                                                        <span><i class="fas fa-calendar mr-1"></i>Next: Wave Optics</span>
                                                    </div>
                                                </div>
                                            </div>
                                            <a href="academic-view.php?id=<?= $course['id'] ?? 1 ?>" class="ml-4 px-4 py-2 bg-gradient-to-r from-green-500 to-emerald-500 text-white rounded-xl text-sm font-medium hover:shadow-lg transition whitespace-nowrap">
                                                Study <i class="fas fa-arrow-right ml-1"></i>
                                            </a>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php else: ?>
                                <!-- Sample Academic Courses -->
                                <div class="space-y-4">
                                    <div class="academic-card border rounded-xl p-4 hover:shadow-lg transition">
                                        <div class="flex items-start justify-between">
                                            <div class="flex-1">
                                                <div class="flex items-center space-x-3 mb-2">
                                                    <div class="w-8 h-8 rounded-lg bg-gradient-to-r from-green-500 to-emerald-500 flex items-center justify-center">
                                                        <i class="fas fa-calculator text-white text-sm"></i>
                                                    </div>
                                                    <h3 class="font-semibold text-gray-800">Mathematics</h3>
                                                </div>
                                                <p class="text-sm text-gray-500 mb-3">Grade 12 • CBSE</p>
                                                
                                                <div class="space-y-2">
                                                    <div class="flex items-center justify-between text-sm">
                                                        <span class="text-gray-500">Syllabus Progress</span>
                                                        <span class="font-semibold text-green-600">72%</span>
                                                    </div>
                                                    <div class="w-full bg-gray-200 rounded-full h-2">
                                                        <div class="bg-gradient-to-r from-green-500 to-emerald-500 h-2 rounded-full" style="width: 72%"></div>
                                                    </div>
                                                    <div class="flex items-center justify-between text-xs text-gray-500">
                                                        <span><i class="fas fa-book mr-1"></i>9/13 topics</span>
                                                        <span><i class="fas fa-calendar mr-1"></i>Next: Calculus</span>
                                                    </div>
                                                </div>
                                            </div>
                                            <a href="#" class="ml-4 px-4 py-2 bg-gradient-to-r from-green-500 to-emerald-500 text-white rounded-xl text-sm font-medium hover:shadow-lg transition">
                                                Study <i class="fas fa-arrow-right ml-1"></i>
                                            </a>
                                        </div>
                                    </div>
                                    
                                    <div class="academic-card border rounded-xl p-4 hover:shadow-lg transition">
                                        <div class="flex items-start justify-between">
                                            <div class="flex-1">
                                                <div class="flex items-center space-x-3 mb-2">
                                                    <div class="w-8 h-8 rounded-lg bg-gradient-to-r from-blue-500 to-cyan-500 flex items-center justify-center">
                                                        <i class="fas fa-flask text-white text-sm"></i>
                                                    </div>
                                                    <h3 class="font-semibold text-gray-800">Physics</h3>
                                                </div>
                                                <p class="text-sm text-gray-500 mb-3">Grade 12 • CBSE</p>
                                                
                                                <div class="space-y-2">
                                                    <div class="flex items-center justify-between text-sm">
                                                        <span class="text-gray-500">Syllabus Progress</span>
                                                        <span class="font-semibold text-blue-600">58%</span>
                                                    </div>
                                                    <div class="w-full bg-gray-200 rounded-full h-2">
                                                        <div class="bg-gradient-to-r from-blue-500 to-cyan-500 h-2 rounded-full" style="width: 58%"></div>
                                                    </div>
                                                    <div class="flex items-center justify-between text-xs text-gray-500">
                                                        <span><i class="fas fa-book mr-1"></i>7/12 topics</span>
                                                        <span><i class="fas fa-calendar mr-1"></i>Next: Electrostatics</span>
                                                    </div>
                                                </div>
                                            </div>
                                            <a href="#" class="ml-4 px-4 py-2 bg-gradient-to-r from-blue-500 to-cyan-500 text-white rounded-xl text-sm font-medium hover:shadow-lg transition">
                                                Study <i class="fas fa-arrow-right ml-1"></i>
                                            </a>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="mt-6 text-center">
                                    <a href="academics.php" class="inline-flex items-center text-green-600 hover:text-green-700 font-medium">
                                        View All Academic Subjects <i class="fas fa-arrow-right ml-2"></i>
                                    </a>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <!-- My Courses Section (Coaching/Institute) -->
                    <div class="bg-white rounded-2xl shadow-xl border border-gray-200 overflow-hidden">
                        <div class="bg-gradient-to-r from-blue-600 to-blue-700 px-6 py-4">
                            <div class="flex items-center justify-between">
                                <h2 class="text-xl font-bold text-white flex items-center">
                                    <i class="fas fa-book-open mr-3"></i>My Courses
                                </h2>
                                <span class="text-xs text-white bg-white bg-opacity-20 px-3 py-1 rounded-full">Coaching</span>
                            </div>
                        </div>
                        
                        <div class="p-6">
                            <?php if (!empty($coaching_courses)): ?>
                                <div class="space-y-4">
                                    <?php foreach ($coaching_courses as $index => $course): 
                                        $progress = ($course['total_videos'] ?? 0) > 0 
                                            ? round((($course['completed_videos'] ?? 0) / ($course['total_videos'] ?? 1)) * 100) 
                                            : 0;
                                        $gradients = ['from-blue-500 to-blue-600', 'from-purple-500 to-purple-600', 'from-indigo-500 to-indigo-600'];
                                        $gradient = $gradients[$index % 3];
                                    ?>
                                    <div class="coaching-card border rounded-xl p-4 hover:shadow-lg transition">
                                        <div class="flex items-start justify-between">
                                            <div class="flex-1">
                                                <div class="flex items-center space-x-3 mb-2">
                                                    <div class="w-8 h-8 rounded-lg bg-gradient-to-r <?= $gradient ?> flex items-center justify-center">
                                                        <i class="fas fa-code text-white text-sm"></i>
                                                    </div>
                                                    <h3 class="font-semibold text-gray-800"><?= htmlspecialchars($course['title'] ?? 'Untitled Course') ?></h3>
                                                </div>
                                                <p class="text-sm text-gray-500 mb-3"><?= htmlspecialchars($course['instructor'] ?? 'Self-paced') ?></p>
                                                
                                                <!-- Progress Bar -->
                                                <div class="space-y-2">
                                                    <div class="flex items-center justify-between text-sm">
                                                        <span class="text-gray-500">Progress</span>
                                                        <span class="font-semibold text-blue-600"><?= $progress ?>%</span>
                                                    </div>
                                                    <div class="w-full bg-gray-200 rounded-full h-2">
                                                        <div class="bg-gradient-to-r <?= $gradient ?> h-2 rounded-full" style="width: <?= $progress ?>%"></div>
                                                    </div>
                                                    <div class="flex items-center justify-between text-xs text-gray-500">
                                                        <span><i class="fas fa-video mr-1"></i><?= $course['completed_videos'] ?? 0 ?>/<?= $course['total_videos'] ?? 0 ?></span>
                                                        <span><i class="fas fa-clock mr-1"></i>Last activity 2h ago</span>
                                                    </div>
                                                </div>
                                            </div>
                                            <a href="course-view.php?id=<?= $course['id'] ?? 0 ?>" class="ml-4 px-4 py-2 bg-gradient-to-r <?= $gradient ?> text-white rounded-xl text-sm font-medium hover:shadow-lg transition whitespace-nowrap">
                                                Continue <i class="fas fa-arrow-right ml-1"></i>
                                            </a>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                                
                                <div class="mt-6 text-center">
                                    <a href="courses.php" class="inline-flex items-center text-blue-600 hover:text-blue-700 font-medium">
                                        View All Coaching Courses <i class="fas fa-arrow-right ml-2"></i>
                                    </a>
                                </div>
                            <?php else: ?>
                                <div class="text-center py-8">
                                    <div class="w-20 h-20 bg-blue-100 rounded-full mx-auto mb-4 flex items-center justify-center">
                                        <i class="fas fa-book-open text-blue-600 text-2xl"></i>
                                    </div>
                                    <p class="text-gray-500 mb-4">No coaching courses enrolled yet</p>
                                    <a href="browse-courses.php" class="inline-block px-6 py-2 bg-gradient-to-r from-blue-600 to-blue-700 text-white rounded-xl hover:shadow-lg">
                                        Browse Courses
                                    </a>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                   
                </div>

                <!-- Bottom Section - Global Leaderboard and Schedule -->
               <!-- Bottom Section - Global Leaderboard and Recent Test Scores -->
<div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
    <!-- Recent Test Scores - Colorful Table -->
    <div class="lg:col-span-2">
        <div class="bg-white rounded-2xl shadow-xl border border-gray-200 overflow-hidden">
            <div class="bg-gradient-to-r from-blue-600 to-blue-700 px-6 py-4">
                <h2 class="text-xl font-bold text-white flex items-center">
                    <i class="fas fa-chart-line mr-3"></i>Recent Test Scores
                </h2>
            </div>
            
            <div class="p-6">
                <!-- Test Scores Table - Colorful Design -->
                <table class="w-full">
                    <thead>
                        <tr class="border-b-2 border-gray-200">
                            <th class="text-left pb-3 text-sm font-bold text-gray-700 uppercase tracking-wider">SUBJECT</th>
                            <th class="text-left pb-3 text-sm font-bold text-gray-700 uppercase tracking-wider">TEST</th>
                            <th class="text-right pb-3 text-sm font-bold text-gray-700 uppercase tracking-wider">SCORE</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <!-- Python L2 - MCQ #13 - 94/100 (Green) -->
                        <tr class="hover:bg-green-50 transition-colors duration-200">
                            <td class="py-4">
                                <div class="flex items-center">
                                    <div class="w-8 h-8 rounded-lg bg-gradient-to-r from-green-400 to-green-500 flex items-center justify-center text-white mr-3">
                                        <i class="fab fa-python text-sm"></i>
                                    </div>
                                    <span class="font-semibold text-gray-800">Python L2</span>
                                </div>
                            </td>
                            <td class="py-4">
                                <span class="text-gray-600">MCQ #13</span>
                            </td>
                            <td class="py-4 text-right">
                                <div class="inline-flex items-center px-4 py-2 rounded-xl bg-gradient-to-r from-green-400 to-green-500 text-white font-bold shadow-md">
                                    94/100
                                </div>
                            </td>
                        </tr>
                        
                        <!-- Mathematics - Monthly - 88/100 (Blue) -->
                        <tr class="hover:bg-blue-50 transition-colors duration-200">
                            <td class="py-4">
                                <div class="flex items-center">
                                    <div class="w-8 h-8 rounded-lg bg-gradient-to-r from-blue-400 to-blue-500 flex items-center justify-center text-white mr-3">
                                        <i class="fas fa-calculator text-sm"></i>
                                    </div>
                                    <span class="font-semibold text-gray-800">Mathematics</span>
                                </div>
                            </td>
                            <td class="py-4">
                                <span class="text-gray-600">Monthly</span>
                            </td>
                            <td class="py-4 text-right">
                                <div class="inline-flex items-center px-4 py-2 rounded-xl bg-gradient-to-r from-blue-400 to-blue-500 text-white font-bold shadow-md">
                                    88/100
                                </div>
                            </td>
                        </tr>
                        
                        <!-- Science - Weekly - 72/100 (Orange) -->
                        <tr class="hover:bg-orange-50 transition-colors duration-200">
                            <td class="py-4">
                                <div class="flex items-center">
                                    <div class="w-8 h-8 rounded-lg bg-gradient-to-r from-orange-400 to-orange-500 flex items-center justify-center text-white mr-3">
                                        <i class="fas fa-flask text-sm"></i>
                                    </div>
                                    <span class="font-semibold text-gray-800">Science</span>
                                </div>
                            </td>
                            <td class="py-4">
                                <span class="text-gray-600">Weekly</span>
                            </td>
                            <td class="py-4 text-right">
                                <div class="inline-flex items-center px-4 py-2 rounded-xl bg-gradient-to-r from-orange-400 to-orange-500 text-white font-bold shadow-md">
                                    72/100
                                </div>
                            </td>
                        </tr>
                        
                        <!-- Python L2 - Practical - 100/100 (Purple) -->
                        <tr class="hover:bg-purple-50 transition-colors duration-200">
                            <td class="py-4">
                                <div class="flex items-center">
                                    <div class="w-8 h-8 rounded-lg bg-gradient-to-r from-purple-400 to-purple-500 flex items-center justify-center text-white mr-3">
                                        <i class="fab fa-python text-sm"></i>
                                    </div>
                                    <span class="font-semibold text-gray-800">Python L2</span>
                                </div>
                            </td>
                            <td class="py-4">
                                <span class="text-gray-600">Practical</span>
                            </td>
                            <td class="py-4 text-right">
                                <div class="inline-flex items-center px-4 py-2 rounded-xl bg-gradient-to-r from-purple-400 to-purple-500 text-white font-bold shadow-md">
                                    100/100
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>

                <!-- View All Tests Link -->
                <div class="mt-6 text-center">
                    <a href="test-scores.php" class="inline-flex items-center text-blue-600 hover:text-blue-700 font-semibold text-sm bg-blue-50 px-4 py-2 rounded-lg hover:bg-blue-100 transition">
                        View All Test Scores <i class="fas fa-arrow-right ml-2"></i>
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Global Leaderboard with Mood -->
    <div class="lg:col-span-1">
        <div class="bg-white rounded-2xl shadow-xl border border-gray-200 overflow-hidden">
            <!-- Header with Mood -->
            <div class="bg-gradient-to-r from-yellow-400 via-orange-400 to-pink-400 px-6 py-4">
               
                
                <!-- How are you feeling today? / AI TRACKS -->
                <div class="mt-2">
    <p class="text-white/90 text-sm mb-2">How are you feeling today?</p>
    <div class="flex flex-wrap gap-2">
        <?php 
        $mood_emojis = ['😊', '😌', '🤔', '😴', '⚡', '📚', '🎯', '😕'];
        foreach ($mood_emojis as $emoji): 
        ?>
        <form method="POST" action="mood.php" class="inline">
            <input type="hidden" name="mood" value="<?= htmlspecialchars($emoji) ?>">
            <button type="submit" 
                class="w-8 h-8 rounded-lg bg-white/20 backdrop-blur hover:bg-white/30 transition flex items-center justify-center text-white text-lg 
                <?= (isset($student_mood) && $student_mood === $emoji) ? 'ring-2 ring-white scale-110' : '' ?>">
                <?= $emoji ?>
            </button>
        </form>
        <?php endforeach; ?>
    </div>
    <p class="text-white/80 text-xs mt-2 font-medium">AI TRACKS</p>
</div>
            </div>
            
            <!-- Leaderboard Content -->
            <div class="p-6">
                 <div class="flex items-center justify-between mb-3">
                    <h2 class="text-xl font-bold text-gredient-to-r from-yellow-400 to-orange-500 flex items-center">
                        <i class="fas fa-trophy mr-3"></i>Global Rank
                    </h2>
                    <span class="text-xs text-white bg-white/20 px-3 py-1 rounded-full">This Week</span>
                </div>
                <!-- Rank #1 - Riya S. -->
                <div class="flex items-center justify-between p-4 rounded-xl bg-gradient-to-r from-yellow-50 to-orange-50 border border-yellow-200 mb-3">
                    
                    <div class="flex items-center space-x-3">
                        <div class="relative">
                            <div class="w-12 h-12 rounded-full bg-gradient-to-r from-yellow-400 to-yellow-500 flex items-center justify-center text-white font-bold text-xl shadow-md">
                                R
                            </div>
                            <div class="absolute -top-1 -right-1 w-6 h-6 bg-yellow-500 rounded-full flex items-center justify-center text-white text-xs font-bold border-2 border-white">
                                1
                            </div>
                        </div>
                        <div>
                            <p class="font-bold text-gray-800 text-lg">Riya S.</p>
                            <p class="text-sm text-gray-500">Mumbai</p>
                        </div>
                    </div>
                    <div class="text-right">
                        <p class="font-bold text-gray-800 text-2xl">988</p>
                    </div>
                </div>
                
                <!-- Rank #2 - Aadesh K. -->
                <div class="flex items-center justify-between p-4 rounded-xl bg-gradient-to-r from-gray-50 to-gray-100 border border-gray-200 mb-3">
                    <div class="flex items-center space-x-3">
                        <div class="relative">
                            <div class="w-12 h-12 rounded-full bg-gradient-to-r from-gray-400 to-gray-500 flex items-center justify-center text-white font-bold text-xl shadow-md">
                                A
                            </div>
                            <div class="absolute -top-1 -right-1 w-6 h-6 bg-gray-500 rounded-full flex items-center justify-center text-white text-xs font-bold border-2 border-white">
                                2
                            </div>
                        </div>
                        <div>
                            <p class="font-bold text-gray-800 text-lg">Aadesh K.</p>
                            <p class="text-sm text-gray-500">Delhi</p>
                        </div>
                    </div>
                    <div class="text-right">
                        <p class="font-bold text-gray-800 text-2xl">962</p>
                    </div>
                </div>
                
                <!-- Rank #12 - Current User (Arjun T.) -->
                <div class="flex items-center justify-between p-4 rounded-xl bg-gradient-to-r from-blue-50 to-purple-50 border border-blue-200">
                    <div class="flex items-center space-x-3">
                        <div class="relative">
                            <div class="w-12 h-12 rounded-full bg-gradient-to-r from-blue-400 to-purple-400 flex items-center justify-center text-white font-bold text-xl shadow-md">
                                A
                            </div>
                            <div class="absolute -top-1 -right-1 w-6 h-6 bg-purple-500 rounded-full flex items-center justify-center text-white text-xs font-bold border-2 border-white">
                                12
                            </div>
                        </div>
                        <div>
                            <p class="font-bold text-gray-800 text-lg">Arjun T.</p>
                            <p class="text-sm text-gray-500">Pune</p>
                        </div>
                    </div>
                    <div class="text-right">
                        <p class="font-bold text-gray-800 text-2xl">874</p>
                    </div>
                </div>
                
                <!-- View Full Leaderboard Link -->
                <div class="mt-6 text-center">
                    <a href="leaderboard.php" class="inline-flex items-center text-purple-600 hover:text-purple-700 font-semibold text-sm bg-purple-50 px-4 py-2 rounded-lg hover:bg-purple-100 transition">
                        View Full Leaderboard <i class="fas fa-arrow-right ml-2"></i>
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Today's Class Banner - Separate Section -->
<div class="mt-8">
    <div class="bg-gradient-to-r from-indigo-600 via-purple-600 to-pink-600 rounded-2xl shadow-xl p-8 text-white">
        <div class="flex items-start justify-between flex-wrap gap-4">
            <div>
                <span class="inline-block px-4 py-1 bg-white/20 backdrop-blur rounded-full text-sm mb-4">
                    <i class="fas fa-video mr-2"></i>Today's Class — Python L2
                </span>
                <h2 class="text-3xl font-bold mb-3">Class 14: List Comprehension & Loops</h2>
                <p class="text-indigo-100 mb-4">Complete after class: MCQ + Practical Exercise to unlock Class 15</p>
                <div class="flex items-center space-x-4 mb-4">
                    <span class="flex items-center text-sm bg-white/20 px-3 py-1.5 rounded-full">
                        <i class="fas fa-clock mr-2"></i>20 min Video
                    </span>
                    <span class="flex items-center text-sm bg-white/20 px-3 py-1.5 rounded-full">
                        <i class="fas fa-file-alt mr-2"></i>10 min MCQ
                    </span>
                    <span class="flex items-center text-sm bg-white/20 px-3 py-1.5 rounded-full">
                        <i class="fas fa-flask mr-2"></i>10 min Lab
                    </span>
                </div>
                <a href="class.php" class="inline-flex items-center px-6 py-3 bg-white text-indigo-600 rounded-xl font-semibold hover:shadow-lg transition transform hover:scale-105">
                    <i class="fas fa-play-circle mr-2"></i>Start Class
                </a>
            </div>
            <div class="hidden lg:block">
                <div class="w-24 h-24 bg-white/20 backdrop-blur rounded-2xl flex items-center justify-center border-2 border-white/30">
                    <i class="fab fa-python text-5xl text-white"></i>
                </div>
            </div>
        </div>
    </div>
</div>
            </div>
        </div>
    </div>

    <script>
        // Toggle notifications dropdown
        function toggleNotifications() {
            const dropdown = document.getElementById('notifications-dropdown');
            dropdown.classList.toggle('hidden');
        }

        // Close notifications when clicking outside
        document.addEventListener('click', function(event) {
            const dropdown = document.getElementById('notifications-dropdown');
            const bellButton = event.target.closest('.fa-bell') || event.target.closest('button');
            
            if (!bellButton && !dropdown.classList.contains('hidden')) {
                dropdown.classList.add('hidden');
            }
        });

        // Auto-refresh stats every 60 seconds
        setInterval(function() {
            // You can implement AJAX refresh here
            console.log('Auto-refresh would happen here');
        }, 60000);
    </script>
</body>
</html>