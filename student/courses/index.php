<?php
require_once '../../includes/auth.php';
require_once '../../includes/student-functions.php';
requireStudent();

$student_id = $_SESSION['user_id'];

// Get all enrolled courses with progress
$stmt = $pdo->prepare("
    SELECT 
        c.*,
        COUNT(DISTINCT v.id) as total_videos,
        SUM(CASE WHEN vp.completed THEN 1 ELSE 0 END) as completed_videos,
        (SUM(CASE WHEN vp.completed THEN 1 ELSE 0 END) / COUNT(DISTINCT v.id)) * 100 as progress,
        e.enrolled_at
    FROM courses c
    JOIN enrollments e ON c.id = e.course_id
    LEFT JOIN videos v ON c.id = v.course_id
    LEFT JOIN video_progress vp ON v.id = vp.video_id AND vp.student_id = ?
    WHERE e.student_id = ? AND e.status = 'active'
    GROUP BY c.id
    ORDER BY e.enrolled_at DESC
");
$stmt->execute([$student_id, $student_id]);
$courses = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Courses</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-gray-50">
    <div class="flex h-screen">
        <?php include '../dashboard/components/sidebar.php'; ?>
        
        <div class="flex-1 overflow-auto">
            <?php include '../dashboard/components/header.php'; ?>
            
            <main class="p-8">
                <div class="mb-8">
                    <h1 class="text-3xl font-bold text-gray-800">My Courses</h1>
                    <p class="text-gray-500 mt-2">Continue your learning journey</p>
                </div>
                
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                    <?php foreach($courses as $course): ?>
                        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden card-hover">
                            <div class="h-40 gradient-bg relative">
                                <div class="absolute inset-0 bg-black opacity-10"></div>
                                <div class="absolute bottom-4 left-4 text-white">
                                    <span class="text-sm font-medium bg-white bg-opacity-20 px-3 py-1 rounded-full">
                                        <?= $course['total_videos'] ?? 0 ?> Lessons
                                    </span>
                                </div>
                            </div>
                            
                            <div class="p-6">
                                <h3 class="text-xl font-bold text-gray-800 mb-2"><?= htmlspecialchars($course['title']) ?></h3>
                                <p class="text-sm text-gray-500 mb-4"><?= htmlspecialchars($course['instructor']) ?></p>
                                
                                <div class="mb-4">
                                    <div class="flex justify-between text-sm mb-1">
                                        <span class="text-gray-600">Progress</span>
                                        <span class="font-semibold text-gray-800"><?= round($course['progress'] ?? 0) ?>%</span>
                                    </div>
                                    <div class="w-full bg-gray-100 rounded-full h-2">
                                        <div class="bg-blue-600 h-2 rounded-full" style="width: <?= $course['progress'] ?? 0 ?>%"></div>
                                    </div>
                                </div>
                                
                                <a href="view.php?id=<?= $course['id'] ?>" 
                                   class="block w-full text-center px-4 py-3 bg-blue-600 text-white rounded-xl hover:bg-blue-700 transition font-medium">
                                    Continue Learning
                                </a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </main>
        </div>
    </div>
</body>
</html>