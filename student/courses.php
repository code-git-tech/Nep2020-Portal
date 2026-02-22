<?php
require_once '../includes/auth.php';
require_once '../includes/student-functions.php';
require_once '../includes/auto_publish.php';
requireStudent();

autoPublishVideos($pdo);

$user_id = $_SESSION['user_id'];
$enrolled_courses = getEnrolledCourses($user_id);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Courses - Student Portal</title>

    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <style>
        .glass {
            backdrop-filter: blur(12px);
            background: rgba(255,255,255,0.7);
        }
    </style>
</head>

<body class="bg-gradient-to-br from-gray-50 via-blue-50 to-indigo-50">

<div class="flex h-screen">
    <?php include 'sidebar.php'; ?>

    <div class="flex-1 overflow-auto">
        <?php include 'header.php'; ?>

        <div class="p-6">

            <!-- HEADER -->
            <div class="flex flex-col md:flex-row justify-between md:items-center gap-4 mb-8">
                <div>
                    <h1 class="text-3xl font-bold text-gray-800">📚 My Courses</h1>
                    <p class="text-gray-500 mt-1">Track and continue your learning journey</p>
                </div>

                <div class="flex gap-3">
                    <select class="px-4 py-2 border border-gray-200 rounded-xl text-sm bg-white shadow-sm focus:ring-2 focus:ring-blue-400"
                            onchange="filterCourses(this.value)">
                        <option value="all">All Courses</option>
                        <option value="in-progress">In Progress</option>
                        <option value="completed">Completed</option>
                    </select>

                    <a href="available-courses.php"
                       class="px-5 py-2 bg-gradient-to-r from-blue-600 to-indigo-600 text-white rounded-xl shadow hover:scale-105 transition flex items-center">
                        <i class="fas fa-plus-circle mr-2"></i> Browse
                    </a>
                </div>
            </div>

            <!-- EMPTY STATE -->
            <?php if (empty($enrolled_courses)): ?>
                <div class="glass rounded-2xl shadow-lg p-12 text-center border border-white/40">
                    <div class="w-24 h-24 bg-blue-100 rounded-full mx-auto mb-4 flex items-center justify-center">
                        <i class="fas fa-book-open text-blue-600 text-3xl"></i>
                    </div>
                    <h3 class="text-xl font-semibold text-gray-700 mb-2">No Courses Yet</h3>
                    <p class="text-gray-500 mb-6">Start learning something new today 🚀</p>

                    <a href="available-courses.php"
                       class="px-6 py-3 bg-blue-600 text-white rounded-xl shadow hover:bg-blue-700 transition">
                        Browse Courses
                    </a>
                </div>

            <?php else: ?>

                <!-- COURSE GRID -->
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">

                    <?php foreach ($enrolled_courses as $course): ?>
                    <div class="course-card glass rounded-2xl shadow-lg border border-white/40 overflow-hidden hover:shadow-2xl hover:-translate-y-1 transition duration-300">

                        <!-- TOP -->
                        <div class="h-40 bg-gradient-to-r from-blue-500 via-indigo-500 to-purple-500 relative">

                            <div class="absolute bottom-4 left-4 text-white text-sm">
                                <span class="bg-white/20 px-3 py-1 rounded-full">
                                    <i class="fas fa-user mr-1"></i>
                                    <?= htmlspecialchars($course['instructor'] ?? 'Instructor') ?>
                                </span>
                            </div>

                            <?php if (($course['progress'] ?? 0) == 100): ?>
                                <div class="absolute top-4 right-4">
                                    <span class="bg-green-500 text-white text-xs px-3 py-1 rounded-full shadow">
                                        ✓ Completed
                                    </span>
                                </div>
                            <?php endif; ?>
                        </div>

                        <!-- BODY -->
                        <div class="p-5">

                            <h3 class="font-semibold text-lg text-gray-800 mb-2">
                                <?= htmlspecialchars($course['title']) ?>
                            </h3>

                            <p class="text-sm text-gray-500 mb-4">
                                <?= htmlspecialchars(substr($course['description'] ?? '', 0, 90)) ?>...
                            </p>

                            <!-- PROGRESS -->
                            <div class="mb-4">
                                <div class="flex justify-between text-sm mb-1">
                                    <span class="text-gray-600">Progress</span>
                                    <span class="font-semibold text-blue-600">
                                        <?= $course['progress'] ?? 0 ?>%
                                    </span>
                                </div>

                                <div class="w-full bg-gray-200 rounded-full h-2 overflow-hidden">
                                    <div class="bg-gradient-to-r from-blue-500 to-indigo-600 h-2 rounded-full transition-all duration-500"
                                         style="width: <?= $course['progress'] ?? 0 ?>%">
                                    </div>
                                </div>

                                <p class="text-xs text-gray-500 mt-2">
                                    🎥 <?= $course['completed_videos'] ?? 0 ?>/<?= $course['total_videos'] ?? 0 ?> completed
                                </p>
                            </div>

                            <!-- BUTTON -->
                            <a href="course-view.php?id=<?= $course['id'] ?>"
                               class="block text-center px-4 py-2 rounded-xl font-medium text-white bg-gradient-to-r from-blue-600 to-indigo-600 hover:scale-105 transition">
                                <i class="fas fa-play mr-2"></i>
                                <?= ($course['progress'] ?? 0) > 0 ? 'Continue' : 'Start' ?>
                            </a>
                        </div>
                    </div>
                    <?php endforeach; ?>

                </div>
            <?php endif; ?>

        </div>
    </div>
</div>

<!-- FILTER SCRIPT -->
<script>
function filterCourses(filter) {
    const courses = document.querySelectorAll('.course-card');

    courses.forEach(course => {
        const progressBar = course.querySelector('[style*="width"]');
        const progress = parseInt(progressBar.style.width);

        if (filter === 'all') {
            course.style.display = 'block';
        } else if (filter === 'in-progress') {
            course.style.display = (progress > 0 && progress < 100) ? 'block' : 'none';
        } else {
            course.style.display = (progress >= 100) ? 'block' : 'none';
        }
    });
}
</script>

</body>
</html>