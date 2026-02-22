<?php
$current_page = basename($_SERVER['PHP_SELF']);
$base_url = '/New/student/dashboard/';
?>
<div class="w-72 bg-white shadow-xl flex flex-col h-screen overflow-y-auto">
    <!-- Logo -->
    <div class="p-6 border-b border-gray-100">
        <div class="flex items-center space-x-3">
            <div class="w-10 h-10 gradient-bg rounded-xl flex items-center justify-center">
                <i class="fas fa-graduation-cap text-white text-xl"></i>
            </div>
            <div>
                <h1 class="text-xl font-bold text-gray-800">Student<span class="text-blue-600">Portal</span></h1>
                <p class="text-xs text-gray-500">Learning Management System</p>
            </div>
        </div>
    </div>

    <!-- User Profile Summary -->
    <div class="p-6 border-b border-gray-100">
        <div class="flex items-center space-x-4">
            <div class="relative">
                <div class="w-16 h-16 gradient-bg rounded-2xl flex items-center justify-center text-white font-bold text-2xl shadow-lg">
                    <?= strtoupper(substr($_SESSION['name'], 0, 1)) ?>
                </div>
                <div class="absolute -bottom-1 -right-1 w-5 h-5 bg-green-500 border-4 border-white rounded-full"></div>
            </div>
            <div class="flex-1">
                <h3 class="font-semibold text-gray-800"><?= htmlspecialchars($_SESSION['name']) ?></h3>
                <p class="text-sm text-gray-500">Student</p>
                <div class="flex items-center mt-2 space-x-2">
                    <span class="px-2 py-1 bg-blue-50 text-blue-600 text-xs rounded-full">Level <?= $stats['level'] ?? 1 ?></span>
                    <span class="px-2 py-1 bg-purple-50 text-purple-600 text-xs rounded-full"><?= number_format($stats['xp_points'] ?? 0) ?> XP</span>
                </div>
            </div>
        </div>
    </div>

    <!-- Navigation -->
    <nav class="flex-1 p-4">
        <div class="mb-6">
            <p class="text-xs font-semibold text-gray-400 uppercase tracking-wider mb-3 px-4">Main</p>
            <ul class="space-y-1">
                <li>
                    <a href="index.php" class="flex items-center space-x-3 px-4 py-3 rounded-xl transition-all duration-200 <?= $current_page == 'index.php' ? 'bg-blue-50 text-blue-600' : 'text-gray-600 hover:bg-gray-50' ?>">
                        <i class="fas fa-tachometer-alt w-5"></i>
                        <span class="font-medium">Dashboard</span>
                        <?php if($current_page == 'index.php'): ?>
                            <span class="ml-auto w-1.5 h-1.5 bg-blue-600 rounded-full"></span>
                        <?php endif; ?>
                    </a>
                </li>
                <li>
                    <a href="../courses/index.php" class="flex items-center space-x-3 px-4 py-3 rounded-xl transition-all duration-200 text-gray-600 hover:bg-gray-50">
                        <i class="fas fa-book-open w-5"></i>
                        <span class="font-medium">My Courses</span>
                    </a>
                </li>
                <li>
                    <a href="#" class="flex items-center space-x-3 px-4 py-3 rounded-xl transition-all duration-200 text-gray-600 hover:bg-gray-50">
                        <i class="fas fa-search w-5"></i>
                        <span class="font-medium">Browse Courses</span>
                    </a>
                </li>
            </ul>
        </div>

        <div class="mb-6">
            <p class="text-xs font-semibold text-gray-400 uppercase tracking-wider mb-3 px-4">Learning</p>
            <ul class="space-y-1">
                <li>
                    <a href="../tests/index.php" class="flex items-center space-x-3 px-4 py-3 rounded-xl transition-all duration-200 text-gray-600 hover:bg-gray-50">
                        <i class="fas fa-file-alt w-5"></i>
                        <span class="font-medium">Tests & Quizzes</span>
                    </a>
                </li>
                <li>
                    <a href="../certificates/index.php" class="flex items-center space-x-3 px-4 py-3 rounded-xl transition-all duration-200 text-gray-600 hover:bg-gray-50">
                        <i class="fas fa-award w-5"></i>
                        <span class="font-medium">Certificates</span>
                        <?php 
                        $certCount = $pdo->prepare("SELECT COUNT(*) FROM certificates WHERE student_id = ?");
                        $certCount->execute([$student_id]);
                        if($certCount->fetchColumn() > 0): ?>
                            <span class="ml-auto bg-yellow-100 text-yellow-600 text-xs px-2 py-1 rounded-full">
                                <?= $certCount->fetchColumn() ?> new
                            </span>
                        <?php endif; ?>
                    </a>
                </li>
            </ul>
        </div>

        <div class="mb-6">
            <p class="text-xs font-semibold text-gray-400 uppercase tracking-wider mb-3 px-4">Account</p>
            <ul class="space-y-1">
                <li>
                    <a href="../profile/index.php" class="flex items-center space-x-3 px-4 py-3 rounded-xl transition-all duration-200 text-gray-600 hover:bg-gray-50">
                        <i class="fas fa-user w-5"></i>
                        <span class="font-medium">Profile</span>
                    </a>
                </li>
                <li>
                    <a href="../../logout.php" class="flex items-center space-x-3 px-4 py-3 rounded-xl transition-all duration-200 text-red-500 hover:bg-red-50">
                        <i class="fas fa-sign-out-alt w-5"></i>
                        <span class="font-medium">Logout</span>
                    </a>
                </li>
            </ul>
        </div>
    </nav>

    <!-- Upgrade Card -->
    <div class="p-4 m-4 gradient-bg rounded-2xl text-white">
        <div class="flex items-center space-x-3 mb-3">
            <div class="w-10 h-10 bg-white bg-opacity-20 rounded-xl flex items-center justify-center">
                <i class="fas fa-crown text-yellow-300"></i>
            </div>
            <div>
                <h4 class="font-semibold">Go Premium</h4>
                <p class="text-xs opacity-80">Unlock all features</p>
            </div>
        </div>
        <a href="#" class="block text-center py-2 bg-white text-blue-600 rounded-xl text-sm font-semibold hover:bg-opacity-90 transition">
            Upgrade Now
        </a>
    </div>
</div>