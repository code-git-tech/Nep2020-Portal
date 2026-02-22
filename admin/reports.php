<?php
require_once '../includes/auth.php';
require_once '../includes/auto_publish.php';
requireAdmin();

// Run auto-publisher on every page load
autoPublishVideos($pdo);

// Fix 1: Get course-wise progress correctly
$stmt = $pdo->query("
    SELECT 
        c.id, 
        c.title, 
        COUNT(DISTINCT e.student_id) as students_enrolled,
        COUNT(DISTINCT v.id) as total_videos,
        COUNT(DISTINCT vp.student_id) as students_with_progress,
        COALESCE(AVG(vp.completed), 0) as avg_completion
    FROM courses c
    LEFT JOIN videos v ON c.id = v.course_id
    LEFT JOIN enrollments e ON c.id = e.course_id
    LEFT JOIN video_progress vp ON v.id = vp.video_id AND vp.completed = 1
    GROUP BY c.id, c.title
");
$course_progress = $stmt->fetchAll();

// Fix 2: Get top performing students with better metrics
$stmt = $pdo->query("
    SELECT 
        u.id, 
        u.name, 
        u.email,
        COUNT(DISTINCT vp.video_id) as videos_watched,
        COUNT(DISTINCT vp.id) as video_views,
        COUNT(DISTINCT ta.id) as tests_taken,
        AVG(ta.score) as avg_score,
        MAX(ta.score) as max_score
    FROM users u
    LEFT JOIN video_progress vp ON u.id = vp.student_id AND vp.completed = 1
    LEFT JOIN test_attempts ta ON u.id = ta.student_id
    WHERE u.role = 'student'
    GROUP BY u.id, u.name, u.email
    ORDER BY videos_watched DESC, avg_score DESC
");
$top_students = $stmt->fetchAll();

// Fix 3: Get overall statistics
$stmt = $pdo->query("
    SELECT 
        (SELECT COUNT(*) FROM users WHERE role = 'student') as total_students,
        (SELECT COUNT(*) FROM courses) as total_courses,
        (SELECT COUNT(*) FROM videos) as total_videos,
        (SELECT COUNT(*) FROM enrollments) as total_enrollments,
        (SELECT COUNT(DISTINCT student_id) FROM video_progress) as active_students
");
$stats = $stmt->fetch();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Progress Reports</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-gray-100">
    <div class="flex h-screen">
        <?php include 'sidebar.php'; ?>

        <div class="flex-1 overflow-auto">
            <div class="p-8">
                <h1 class="text-3xl font-bold mb-8">Student Progress Reports</h1>

                <!-- Statistics Cards -->
                <div class="grid grid-cols-1 md:grid-cols-5 gap-4 mb-8">
                    <div class="bg-white rounded-lg shadow p-6">
                        <div class="text-gray-500 text-sm">Total Students</div>
                        <div class="text-3xl font-bold"><?= $stats['total_students'] ?? 0 ?></div>
                    </div>
                    <div class="bg-white rounded-lg shadow p-6">
                        <div class="text-gray-500 text-sm">Active Students</div>
                        <div class="text-3xl font-bold"><?= $stats['active_students'] ?? 0 ?></div>
                    </div>
                    <div class="bg-white rounded-lg shadow p-6">
                        <div class="text-gray-500 text-sm">Total Courses</div>
                        <div class="text-3xl font-bold"><?= $stats['total_courses'] ?? 0 ?></div>
                    </div>
                    <div class="bg-white rounded-lg shadow p-6">
                        <div class="text-gray-500 text-sm">Total Videos</div>
                        <div class="text-3xl font-bold"><?= $stats['total_videos'] ?? 0 ?></div>
                    </div>
                    <div class="bg-white rounded-lg shadow p-6">
                        <div class="text-gray-500 text-sm">Enrollments</div>
                        <div class="text-3xl font-bold"><?= $stats['total_enrollments'] ?? 0 ?></div>
                    </div>
                </div>

                <!-- Course Progress Overview -->
                <div class="bg-white rounded-lg shadow p-6 mb-8">
                    <h2 class="text-xl font-bold mb-4">Course Progress Overview</h2>
                    
                    <?php if (empty($course_progress)): ?>
                        <div class="text-center py-8 text-gray-500">
                            <i class="fas fa-chart-line text-4xl mb-3"></i>
                            <p>No course data available. Add courses and enroll students to see progress.</p>
                        </div>
                    <?php else: ?>
                        <div class="overflow-x-auto">
                            <table class="min-w-full">
                                <thead>
                                    <tr class="bg-gray-50">
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Course</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Students Enrolled</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Total Videos</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Students Completed</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Progress</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-200">
                                    <?php foreach ($course_progress as $course): ?>
                                    <tr>
                                        <td class="px-6 py-4 font-medium"><?= htmlspecialchars($course['title']) ?></td>
                                        <td class="px-6 py-4"><?= $course['students_enrolled'] ?></td>
                                        <td class="px-6 py-4"><?= $course['total_videos'] ?></td>
                                        <td class="px-6 py-4"><?= $course['students_with_progress'] ?></td>
                                        <td class="px-6 py-4">
                                            <?php 
                                            $progress = $course['students_enrolled'] > 0 ? 
                                                round(($course['students_with_progress'] / $course['students_enrolled']) * 100) : 0;
                                            ?>
                                            <div class="flex items-center">
                                                <div class="w-full bg-gray-200 rounded-full h-2.5 mr-2 max-w-[200px]">
                                                    <div class="bg-blue-600 h-2.5 rounded-full" style="width: <?= $progress ?>%"></div>
                                                </div>
                                                <span class="text-sm text-gray-600"><?= $progress ?>%</span>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Top Performing Students -->
                <div class="bg-white rounded-lg shadow p-6">
                    <h2 class="text-xl font-bold mb-4">Student Activity Overview</h2>
                    
                    <?php if (empty($top_students)): ?>
                        <div class="text-center py-8 text-gray-500">
                            <i class="fas fa-users text-4xl mb-3"></i>
                            <p>No student activity data available. Students need to watch videos and take tests.</p>
                        </div>
                    <?php else: ?>
                        <div class="overflow-x-auto">
                            <table class="min-w-full">
                                <thead>
                                    <tr class="bg-gray-50">
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Name</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Email</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Videos Completed</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Tests Taken</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Avg. Score</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Best Score</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-200">
                                    <?php foreach ($top_students as $student): ?>
                                    <tr>
                                        <td class="px-6 py-4 font-medium"><?= htmlspecialchars($student['name']) ?></td>
                                        <td class="px-6 py-4"><?= htmlspecialchars($student['email']) ?></td>
                                        <td class="px-6 py-4">
                                            <span class="px-2 py-1 bg-green-100 text-green-800 rounded-full text-xs">
                                                <?= $student['videos_watched'] ?>
                                            </span>
                                        </td>
                                        <td class="px-6 py-4"><?= $student['tests_taken'] ?></td>
                                        <td class="px-6 py-4">
                                            <?php if ($student['avg_score']): ?>
                                                <div class="flex items-center">
                                                    <span class="font-medium"><?= round($student['avg_score']) ?>%</span>
                                                </div>
                                            <?php else: ?>
                                                <span class="text-gray-400">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="px-6 py-4">
                                            <?= $student['max_score'] ? round($student['max_score']) . '%' : '-' ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Debug Info (Remove in production) -->
                <?php if (empty($course_progress) && empty($top_students)): ?>
                <div class="mt-8 p-4 bg-yellow-50 border border-yellow-200 rounded-lg">
                    <h3 class="font-bold text-yellow-800 mb-2">Debug Information:</h3>
                    <ul class="text-sm text-yellow-700">
                        <li>• Total Students: <?= $stats['total_students'] ?? 0 ?></li>
                        <li>• Total Courses: <?= $stats['total_courses'] ?? 0 ?></li>
                        <li>• Total Videos: <?= $stats['total_videos'] ?? 0 ?></li>
                        <li>• Total Enrollments: <?= $stats['total_enrollments'] ?? 0 ?></li>
                        <li>• Active Students: <?= $stats['active_students'] ?? 0 ?></li>
                    </ul>
                    <p class="text-sm text-yellow-700 mt-2">
                        To see progress data:
                        <br>1. Enroll students in courses (add to enrollments table)
                        <br>2. Students should watch videos (video_progress table)
                        <br>3. Students should take tests (test_attempts table)
                    </p>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>