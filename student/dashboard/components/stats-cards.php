<!-- Stats Cards Grid -->
<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
    <!-- Course Progress Card -->
    <div class="bg-white rounded-2xl p-6 shadow-sm border border-gray-100 card-hover">
        <div class="flex items-center justify-between mb-4">
            <div>
                <p class="text-sm text-gray-500 mb-1">Course Progress</p>
                <h3 class="text-2xl font-bold text-gray-800"><?= $stats['course_progress'] ?>%</h3>
            </div>
            <div class="w-12 h-12 bg-blue-100 rounded-xl flex items-center justify-center">
                <i class="fas fa-chart-line text-blue-600 text-xl"></i>
            </div>
        </div>
        <div class="relative pt-1">
            <div class="flex mb-2 items-center justify-between">
                <div>
                    <span class="text-xs font-semibold inline-block text-blue-600">
                        <?= $stats['course_progress'] ?>% Complete
                    </span>
                </div>
                <div class="text-right">
                    <span class="text-xs font-semibold inline-block text-blue-600">
                        <?= $stats['enrolled_courses'] ?> Courses
                    </span>
                </div>
            </div>
            <div class="overflow-hidden h-2 text-xs flex rounded-full bg-blue-50">
                <div style="width:<?= $stats['course_progress'] ?>%" class="shadow-none flex flex-col text-center whitespace-nowrap text-white justify-center bg-gradient-to-r from-blue-500 to-blue-600 rounded-full"></div>
            </div>
        </div>
    </div>

    <!-- Global Rank Card -->
    <div class="bg-white rounded-2xl p-6 shadow-sm border border-gray-100 card-hover">
        <div class="flex items-center justify-between mb-4">
            <div>
                <p class="text-sm text-gray-500 mb-1">Global Rank</p>
                <div class="flex items-baseline">
                    <h3 class="text-2xl font-bold text-gray-800">#<?= $stats['global_rank'] ?></h3>
                    <span class="text-sm text-gray-500 ml-1">/<?= $stats['total_students'] ?></span>
                </div>
            </div>
            <div class="w-12 h-12 bg-purple-100 rounded-xl flex items-center justify-center">
                <i class="fas fa-trophy text-purple-600 text-xl"></i>
            </div>
        </div>
        <div class="flex items-center justify-between text-sm">
            <span class="text-gray-500">Top <?= $stats['rank_percentile'] ?>%</span>
            <span class="text-green-600 font-semibold">↑ 2 places</span>
        </div>
    </div>

    <!-- Streak Card -->
    <div class="bg-white rounded-2xl p-6 shadow-sm border border-gray-100 card-hover">
        <div class="flex items-center justify-between mb-4">
            <div>
                <p class="text-sm text-gray-500 mb-1">Learning Streak</p>
                <div class="flex items-baseline">
                    <h3 class="text-2xl font-bold text-gray-800"><?= $stats['current_streak'] ?></h3>
                    <span class="text-sm text-gray-500 ml-1">days</span>
                </div>
            </div>
            <div class="w-12 h-12 bg-orange-100 rounded-xl flex items-center justify-center">
                <i class="fas fa-fire text-orange-600 text-xl"></i>
            </div>
        </div>
        <div class="flex items-center justify-between text-sm">
            <span class="text-gray-500">Longest: <?= $stats['longest_streak'] ?> days</span>
            <span class="text-green-600 font-semibold">🔥 Active</span>
        </div>
    </div>

    <!-- XP Points Card -->
    <div class="bg-white rounded-2xl p-6 shadow-sm border border-gray-100 card-hover">
        <div class="flex items-center justify-between mb-4">
            <div>
                <p class="text-sm text-gray-500 mb-1">XP Points</p>
                <div class="flex items-baseline">
                    <h3 class="text-2xl font-bold text-gray-800"><?= number_format($stats['xp_points']) ?></h3>
                    <span class="text-sm text-gray-500 ml-1">XP</span>
                </div>
            </div>
            <div class="w-12 h-12 bg-yellow-100 rounded-xl flex items-center justify-center">
                <i class="fas fa-star text-yellow-600 text-xl"></i>
            </div>
        </div>
        <div class="flex items-center justify-between text-sm">
            <span class="text-gray-500">Level <?= $stats['level'] ?></span>
            <span class="text-xs bg-green-100 text-green-600 px-2 py-1 rounded-full">Top 0.3% this week</span>
        </div>
    </div>
</div>