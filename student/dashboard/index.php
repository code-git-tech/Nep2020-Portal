<?php
require_once '../../includes/auth.php';
require_once '../../includes/student-functions.php';
requireStudent();

$student_id = $_SESSION['user_id'];
$student_name = $_SESSION['name'];

// Update streak on dashboard visit
updateStudentStreak($student_id);

// Get all dashboard data
$stats = getStudentDashboardStats($student_id);
$todayClass = getTodaysClass($student_id);
$upcomingClasses = getUpcomingClasses($student_id, 3);
$notifications = getStudentNotifications($student_id, 5);
$unreadCount = getUnreadNotificationsCount($student_id);
$coursePreviews = getCoursePreviews($student_id, 3);

// Format date
$currentDate = date('l, F j, Y');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Dashboard</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            font-family: 'Inter', sans-serif;
        }
        .gradient-bg {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
        .card-hover {
            transition: all 0.3s ease;
        }
        .card-hover:hover {
            transform: translateY(-5px);
            box-shadow: 0 20px 25px -5px rgba(0,0,0,0.1), 0 10px 10px -5px rgba(0,0,0,0.04);
        }
        .progress-ring {
            transition: stroke-dashoffset 0.35s;
            transform: rotate(-90deg);
            transform-origin: 50% 50%;
        }
        .stat-card {
            backdrop-filter: blur(10px);
            background: rgba(255,255,255,0.9);
        }
        .notification-badge {
            animation: pulse 2s infinite;
        }
        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.1); }
            100% { transform: scale(1); }
        }
    </style>
</head>
<body class="bg-gray-50">
    <div class="flex h-screen overflow-hidden">
        <!-- Sidebar Component -->
        <?php include 'components/sidebar.php'; ?>

        <!-- Main Content Area -->
        <div class="flex-1 overflow-auto bg-gray-50">
            <!-- Header Component -->
            <?php include 'components/header.php'; ?>

            <!-- Main Dashboard Content -->
            <main class="p-6 lg:p-8">
                <!-- Welcome Section -->
                <div class="mb-8">
                    <h1 class="text-3xl font-bold text-gray-800">Good <?= (int)date('H') < 12 ? 'Morning' : ((int)date('H') < 17 ? 'Afternoon' : 'Evening') ?>, <?= htmlspecialchars($student_name) ?>! 🎉</h1>
                    <p class="text-gray-500 mt-1"><?= $currentDate ?> - You have <span class="font-semibold text-blue-600"><?= count($upcomingClasses) ?> class<?= count($upcomingClasses) != 1 ? 'es' : '' ?> scheduled</span> today</p>
                </div>

                <!-- Stats Cards Component -->
                <?php include 'components/stats-cards.php'; ?>

                <!-- Main Grid - 2 Columns -->
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-8 mt-8">
                    <!-- Left Column - Today's Class & Courses -->
                    <div class="lg:col-span-2 space-y-8">
                        <!-- Today's Class Component -->
                        <?php include 'components/today-class.php'; ?>

                        <!-- My Courses Preview Component -->
                        <?php include 'components/courses-preview.php'; ?>
                    </div>

                    <!-- Right Column - Activity & Notifications -->
                    <div class="space-y-8">
                        <!-- Global Rank Card -->
                        <div class="bg-gradient-to-br from-purple-600 to-indigo-700 rounded-2xl p-6 text-white shadow-xl">
                            <div class="flex items-center justify-between mb-4">
                                <h3 class="text-lg font-semibold opacity-90">Global Ranking</h3>
                                <i class="fas fa-trophy text-2xl opacity-75"></i>
                            </div>
                            <div class="flex items-end justify-between">
                                <div>
                                    <span class="text-5xl font-bold">#<?= $stats['global_rank'] ?></span>
                                    <span class="text-lg opacity-75 ml-2">/<?= $stats['total_students'] ?></span>
                                </div>
                                <div class="text-right">
                                    <span class="text-sm opacity-75">Top <?= $stats['rank_percentile'] ?>%</span>
                                    <div class="text-xs opacity-60 mt-1">Among all students</div>
                                </div>
                            </div>
                            <div class="mt-4 bg-white bg-opacity-20 rounded-full h-2">
                                <div class="bg-white h-2 rounded-full" style="width: <?= 100 - $stats['rank_percentile'] ?>%"></div>
                            </div>
                        </div>

                        <!-- Streak Card -->
                        <div class="bg-white rounded-2xl p-6 shadow-sm border border-gray-100">
                            <div class="flex items-center justify-between mb-4">
                                <h3 class="text-lg font-semibold text-gray-800">Learning Streak</h3>
                                <i class="fas fa-fire text-orange-500 text-2xl"></i>
                            </div>
                            <div class="flex items-center justify-between">
                                <div>
                                    <span class="text-5xl font-bold text-gray-800"><?= $stats['current_streak'] ?></span>
                                    <span class="text-gray-500 ml-2">days</span>
                                </div>
                                <div class="text-right">
                                    <span class="text-sm text-gray-500">Longest: <?= $stats['longest_streak'] ?> days</span>
                                    <div class="flex mt-2 space-x-1">
                                        <?php for($i = 0; $i < min(7, $stats['current_streak']); $i++): ?>
                                            <div class="w-2 h-2 bg-green-500 rounded-full"></div>
                                        <?php endfor; ?>
                                        <?php for($i = $stats['current_streak']; $i < 7; $i++): ?>
                                            <div class="w-2 h-2 bg-gray-200 rounded-full"></div>
                                        <?php endfor; ?>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- XP Card -->
                        <div class="bg-white rounded-2xl p-6 shadow-sm border border-gray-100">
                            <div class="flex items-center justify-between mb-4">
                                <h3 class="text-lg font-semibold text-gray-800">XP Points</h3>
                                <i class="fas fa-star text-yellow-500 text-2xl"></i>
                            </div>
                            <div class="flex items-end justify-between">
                                <div>
                                    <span class="text-5xl font-bold text-gray-800"><?= number_format($stats['xp_points']) ?></span>
                                    <span class="text-gray-500 ml-2">XP</span>
                                </div>
                                <div class="text-right">
                                    <span class="text-sm text-gray-500">Level <?= $stats['level'] ?></span>
                                    <div class="text-xs text-green-600 font-semibold mt-1">Top 0.3% this week</div>
                                </div>
                            </div>
                            <div class="mt-4 bg-gray-100 rounded-full h-2">
                                <?php 
                                $nextLevelXp = pow(($stats['level'] + 1) * 10, 2);
                                $currentLevelXp = pow($stats['level'] * 10, 2);
                                $xpProgress = (($stats['xp_points'] - $currentLevelXp) / ($nextLevelXp - $currentLevelXp)) * 100;
                                ?>
                                <div class="bg-yellow-500 h-2 rounded-full" style="width: <?= min(100, $xpProgress) ?>%"></div>
                            </div>
                        </div>

                        <!-- Activity Feed Component -->
                        <?php include 'components/activity-feed.php'; ?>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script>
        // Mark notifications as read when clicked
        document.querySelectorAll('.notification-item').forEach(item => {
            item.addEventListener('click', function() {
                const id = this.dataset.id;
                if (id) {
                    fetch('../../api/student/mark-notification.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                        },
                        body: JSON.stringify({id: id})
                    });
                }
            });
        });

        // Auto-refresh stats every 5 minutes
        setInterval(() => {
            location.reload();
        }, 300000);
    </script>
</body>
</html>