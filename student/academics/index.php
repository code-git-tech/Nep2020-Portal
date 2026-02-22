<?php
require_once '../../includes/auth.php';


// Run auto-publisher on every page load
// autoPublishVideos($pdo);

// Sidebar & Header Paths
include '../sidebar.php';
include '../dashboard/components/header.php';

// Fetch courses, enrolled courses, filters, etc. (Assume variables $courses, $enrolled_courses, $classes, $subjects, $search, $class_filter, $subject_filter exist)
?>

<body class="bg-gray-50 text-gray-900">

<div class="flex min-h-screen">

    <!-- SIDEBAR -->
    <div class="fixed lg:static top-0 left-0 z-50 w-64 h-screen bg-gray-900 text-white flex flex-col">
        <?php include '../sidebar.php'; ?>
    </div>

    <!-- MAIN CONTENT -->
    <div class="flex-1 flex flex-col lg:ml-64">

        <!-- HEADER -->
        <header class="w-full bg-white shadow-sm p-4 flex justify-between items-center sticky top-0 z-30">
            <h1 class="text-2xl font-bold text-gray-800">Academic Courses</h1>
            <span class="text-gray-500 text-sm">Welcome back 👋</span>
        </header>

        <!-- PAGE CONTENT -->
        <main class="p-6 flex-1 overflow-auto space-y-6">

            <!-- Enrolled Courses -->
            <?php if (!empty($enrolled_courses)): ?>
            <section>
                <h2 class="text-xl font-semibold mb-4">Continue Learning</h2>
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                    <?php foreach ($enrolled_courses as $course): ?>
                    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4 hover:shadow-md transition">
                        <h3 class="font-semibold text-gray-800"><?= htmlspecialchars($course['title']) ?></h3>
                        <p class="text-sm text-gray-500 mt-1">Class <?= $course['class'] ?> • <?= $course['subject'] ?></p>
                        <div class="mt-3">
                            <div class="flex justify-between text-sm mb-1">
                                <span class="text-gray-600">Progress</span>
                                <span class="font-medium"><?= $course['progress'] ?>%</span>
                            </div>
                            <div class="w-full bg-gray-100 rounded-full h-2">
                                <div class="bg-blue-600 h-2 rounded-full" style="width: <?= $course['progress'] ?>%"></div>
                            </div>
                        </div>
                        <a href="view.php?id=<?= $course['id'] ?>" 
                           class="mt-4 block text-center text-white bg-green-600 hover:bg-green-700 rounded-lg py-2 text-sm font-medium">
                           Continue Learning
                        </a>
                    </div>
                    <?php endforeach; ?>
                </div>
            </section>
            <?php endif; ?>

            <!-- Filters -->
            <section>
                <form method="GET" class="bg-white rounded-xl shadow-sm border border-gray-200 p-4 flex flex-wrap gap-4 items-center">
                    <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Search courses..." class="flex-1 min-w-[200px] px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                    <select name="class" class="px-4 py-2 border border-gray-300 rounded-lg">
                        <option value="">All Classes</option>
                        <?php foreach ($classes as $class): ?>
                        <option value="<?= $class ?>" <?= $class_filter == $class ? 'selected' : '' ?>>Class <?= $class ?></option>
                        <?php endforeach; ?>
                    </select>
                    <select name="subject" class="px-4 py-2 border border-gray-300 rounded-lg">
                        <option value="">All Subjects</option>
                        <?php foreach ($subjects as $subject): ?>
                        <option value="<?= $subject ?>" <?= $subject_filter == $subject ? 'selected' : '' ?>><?= $subject ?></option>
                        <?php endforeach; ?>
                    </select>
                    <button type="submit" class="px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">Filter</button>
                </form>
            </section>

            <!-- Courses Grid -->
            <section>
                <?php if (!empty($courses)): ?>
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
                    <?php foreach ($courses as $course): ?>
                    <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden hover:shadow-md transition">
                        <div class="h-32 bg-gradient-to-r from-blue-500 to-purple-600 relative">
                            <?php if ($course['thumbnail']): ?>
                                <img src="../../<?= $course['thumbnail'] ?>" class="w-full h-full object-cover" alt="">
                            <?php endif; ?>
                            <?php if ($course['featured']): ?>
                                <span class="absolute top-2 right-2 bg-yellow-400 text-xs px-2 py-1 rounded-full">
                                    <i class="fas fa-star mr-1"></i>Featured
                                </span>
                            <?php endif; ?>
                        </div>
                        <div class="p-4">
                            <h3 class="font-bold text-gray-800"><?= htmlspecialchars($course['title']) ?></h3>
                            <p class="text-sm text-gray-500 mb-2">Class <?= $course['class'] ?> • <?= $course['subject'] ?></p>
                            <p class="text-gray-600 text-sm line-clamp-2 mb-3"><?= htmlspecialchars(substr($course['description'] ?? '', 0, 100)) ?>...</p>
                            <div class="flex justify-between text-sm text-gray-500">
                                <span><i class="far fa-clock mr-1"></i> <?= $course['duration'] ?? 'Self-paced' ?></span>
                                <span><i class="fas fa-video mr-1"></i> <?= $course['chapters_count'] ?> chapters</span>
                            </div>
                            <a href="<?= $course['is_enrolled'] > 0 ? "view.php?id={$course['id']}" : "enroll.php?id={$course['id']}" ?>" 
                               class="mt-3 block text-center py-2 rounded-lg text-white <?= $course['is_enrolled'] > 0 ? 'bg-green-600 hover:bg-green-700' : 'bg-blue-600 hover:bg-blue-700' ?>">
                               <?= $course['is_enrolled'] > 0 ? 'Continue Learning' : 'Enroll Now' ?>
                            </a>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php else: ?>
                <div class="text-center py-12 text-gray-500">No courses found matching your criteria.</div>
                <?php endif; ?>
            </section>

        </main>

    </div>

</div>

</body>