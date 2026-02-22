<!-- My Courses Preview -->
<div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
    <div class="p-6 border-b border-gray-100">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="text-xl font-bold text-gray-800">My Courses</h2>
                <p class="text-sm text-gray-500 mt-1">Continue where you left off</p>
            </div>
            <a href="../courses/index.php" class="text-blue-600 hover:text-blue-700 font-medium text-sm flex items-center">
                View All
                <i class="fas fa-arrow-right ml-2 text-xs"></i>
            </a>
        </div>
    </div>
    
    <div class="divide-y divide-gray-100">
        <?php if(empty($coursePreviews)): ?>
            <div class="p-8 text-center">
                <div class="w-16 h-16 bg-gray-100 rounded-full mx-auto mb-3 flex items-center justify-center">
                    <i class="fas fa-book-open text-gray-400 text-xl"></i>
                </div>
                <p class="text-gray-500">You haven't enrolled in any courses yet.</p>
                <a href="#" class="inline-block mt-4 text-blue-600 hover:text-blue-700 font-medium">
                    Browse Courses →
                </a>
            </div>
        <?php else: ?>
            <?php foreach($coursePreviews as $index => $course): ?>
                <div class="p-6 hover:bg-gray-50 transition">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center space-x-4">
                            <div class="w-12 h-12 rounded-xl flex items-center justify-center text-white font-bold text-lg"
                                 style="background: linear-gradient(135deg, <?= $index % 3 == 0 ? '#667eea' : ($index % 3 == 1 ? '#f59e0b' : '#10b981') ?> 0%, <?= $index % 3 == 0 ? '#764ba2' : ($index % 3 == 1 ? '#d97706' : '#059669') ?> 100%)">
                                <?= strtoupper(substr($course['title'], 0, 2)) ?>
                            </div>
                            <div>
                                <h3 class="font-semibold text-gray-800"><?= htmlspecialchars($course['title']) ?></h3>
                                <p class="text-sm text-gray-500 mt-1"><?= htmlspecialchars($course['instructor']) ?></p>
                            </div>
                        </div>
                        <a href="../courses/view.php?id=<?= $course['id'] ?>" 
                           class="px-4 py-2 text-blue-600 hover:text-blue-700 font-medium text-sm">
                            Continue <i class="fas fa-arrow-right ml-1 text-xs"></i>
                        </a>
                    </div>
                    
                    <!-- Progress Bar -->
                    <div class="mt-4">
                        <div class="flex items-center justify-between text-sm mb-2">
                            <span class="text-gray-500">Progress</span>
                            <span class="font-semibold text-gray-800"><?= round($course['progress'] ?? 0) ?>%</span>
                        </div>
                        <div class="w-full bg-gray-100 rounded-full h-2">
                            <div class="bg-gradient-to-r from-blue-500 to-blue-600 h-2 rounded-full" 
                                 style="width: <?= $course['progress'] ?? 0 ?>%"></div>
                        </div>
                        <p class="text-xs text-gray-400 mt-2">
                            <?= $course['completed_videos'] ?? 0 ?>/<?= $course['total_videos'] ?? 0 ?> videos completed
                        </p>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>