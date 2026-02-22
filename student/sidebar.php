<?php
require_once '../includes/auth.php';
require_once '../includes/auto_publish.php';

// Run auto-publisher on every page load
autoPublishVideos($pdo);

$current_page = basename($_SERVER['PHP_SELF']);
?>
<div class="w-72 bg-gradient-to-b from-[#1e3a5f] to-[#1e3a5f] text-white shadow-xl flex flex-col h-screen">

    <!-- Logo Area -->
    <div class="p-6 border-b border-white/10">
        <h1 class="text-2xl font-bold">
            Student<span class="text-cyan-300">Portal</span>
        </h1>
        <p class="text-sm text-white/70 mt-1">National Education Policy 2020</p>
    </div>

    <!-- User Info -->
    <div class="p-6 border-b border-white/10">
        <div class="flex items-center space-x-3">
            <div class="w-12 h-12 bg-white/20 rounded-full flex items-center justify-center text-white font-bold text-xl">
                <?= strtoupper(substr($_SESSION['name'], 0, 1)) ?>
            </div>
            <div>
                <p class="font-semibold"><?= htmlspecialchars($_SESSION['name']) ?></p>
                <p class="text-sm text-white/70">Student</p>
            </div>
        </div>
    </div>

    <!-- Navigation -->
    <nav class="flex-1 p-4">
        <ul class="space-y-2">

            <!-- Dashboard -->
            <li>
                <a href="dashboard.php"
                   class="flex items-center space-x-3 px-4 py-3 rounded-xl transition
                   <?= $current_page == 'dashboard.php'
                        ? 'bg-white/20 backdrop-blur text-white shadow'
                        : 'text-white/80 hover:bg-white/10' ?>">
                    <i class="fas fa-tachometer-alt w-5"></i>
                    <span class="font-medium">Dashboard</span>
                </a>
            </li>

            <!-- My Courses -->
            <li>
                <a href="courses.php"
                   class="flex items-center space-x-3 px-4 py-3 rounded-xl transition
                   <?= strpos($current_page, 'course') !== false
                        ? 'bg-white/20 backdrop-blur text-white shadow'
                        : 'text-white/80 hover:bg-white/10' ?>">
                    <i class="fas fa-book-open w-5"></i>
                    <span class="font-medium">My Courses</span>
                </a>
            </li>

            <!-- Browse Courses -->
            <li>
                <a href="available-courses.php"
                   class="flex items-center space-x-3 px-4 py-3 rounded-xl transition
                   <?= basename($_SERVER['PHP_SELF']) == 'available-courses.php'
                        ? 'bg-white/20 backdrop-blur text-white shadow'
                        : 'text-white/80 hover:bg-white/10' ?>">
                    <i class="fas fa-search w-5"></i>
                    <span class="font-medium">Browse Courses</span>

                    <?php
                    if (function_exists('getAvailableCourses')) {
                        $available_count = count(getAvailableCourses($_SESSION['user_id']));
                        if ($available_count > 0):
                    ?>
                        <span class="ml-auto bg-cyan-300 text-black text-xs px-2 py-1 rounded-full">
                            <?= $available_count ?>
                        </span>
                    <?php endif; } ?>
                </a>
            </li>

            <!-- Tests -->
            <li>
                <a href="tests.php"
                   class="flex items-center space-x-3 px-4 py-3 rounded-xl transition
                   <?= $current_page == 'tests.php'
                        ? 'bg-white/20 backdrop-blur text-white shadow'
                        : 'text-white/80 hover:bg-white/10' ?>">
                    <i class="fas fa-file-alt w-5"></i>
                    <span class="font-medium">Tests & Quizzes</span>
                </a>
            </li>
            <!-- Mood -->
            <li>
                <a href="mood.php"
                   class="flex items-center space-x-3 px-4 py-3 rounded-xl transition
                   <?= $current_page == 'mood.php'
                        ? 'bg-white/20 backdrop-blur text-white shadow'
                        : 'text-white/80 hover:bg-white/10' ?>">
                    <i class="fas fa-file-alt w-5"></i>
                    <span class="font-medium">My mood</span>
                </a>
            </li>


            <!-- Certificates -->
            <li>
                <a href="certificates.php"
                   class="flex items-center space-x-3 px-4 py-3 rounded-xl transition
                   <?= $current_page == 'certificates.php'
                        ? 'bg-white/20 backdrop-blur text-white shadow'
                        : 'text-white/80 hover:bg-white/10' ?>">
                    <i class="fas fa-award w-5"></i>
                    <span class="font-medium">Certificates</span>
                </a>
            </li>

            <!-- Profile -->
            <li>
                <a href="profile.php"
                   class="flex items-center space-x-3 px-4 py-3 rounded-xl transition
                   <?= $current_page == 'profile.php'
                        ? 'bg-white/20 backdrop-blur text-white shadow'
                        : 'text-white/80 hover:bg-white/10' ?>">
                    <i class="fas fa-user w-5"></i>
                    <span class="font-medium">Profile</span>
                </a>
            </li>

        </ul>
    </nav>

    <!-- Logout -->
    <div class="p-4 border-t border-white/10">
        <a href="../logout.php"
           class="flex items-center space-x-3 px-4 py-3 rounded-xl text-red-300 hover:bg-red-500/20 transition">
            <i class="fas fa-sign-out-alt w-5"></i>
            <span class="font-medium">Logout</span>
        </a>
    </div>

</div>
