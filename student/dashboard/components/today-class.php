<!-- Today's Class Section -->
<div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden card-hover">
    <div class="p-6 border-b border-gray-100">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="text-xl font-bold text-gray-800">Today's Class</h2>
                <p class="text-sm text-gray-500 mt-1">Continue your learning journey</p>
            </div>
            <?php if($todayClass): ?>
                <span class="px-3 py-1 bg-green-100 text-green-600 text-sm rounded-full font-medium">
                    <i class="fas fa-circle text-xs mr-1"></i> Available Now
                </span>
            <?php endif; ?>
        </div>
    </div>
    
    <?php if($todayClass): ?>
        <div class="p-6">
            <div class="flex items-start space-x-4">
                <!-- Course Icon -->
                <div class="w-16 h-16 gradient-bg rounded-2xl flex items-center justify-center text-white text-2xl font-bold shadow-lg">
                    <?= strtoupper(substr($todayClass['course_title'], 0, 2)) ?>
                </div>
                
                <div class="flex-1">
                    <span class="text-sm text-blue-600 font-semibold">Week <?= $todayClass['week_number'] ?> · Class <?= $todayClass['class_number'] ?></span>
                    <h3 class="text-xl font-bold text-gray-800 mt-1"><?= htmlspecialchars($todayClass['title']) ?></h3>
                    <p class="text-gray-500 mt-2"><?= htmlspecialchars($todayClass['description']) ?></p>
                    
                    <!-- Progress Steps -->
                    <div class="flex items-center space-x-6 mt-4">
                        <div class="flex items-center">
                            <div class="w-8 h-8 rounded-full <?= $todayClass['video_completed'] ? 'bg-green-100' : 'bg-gray-100' ?> flex items-center justify-center">
                                <i class="fas fa-video <?= $todayClass['video_completed'] ? 'text-green-600' : 'text-gray-400' ?>"></i>
                            </div>
                            <span class="ml-2 text-sm <?= $todayClass['video_completed'] ? 'text-green-600' : 'text-gray-500' ?>">Video</span>
                        </div>
                        <div class="flex items-center">
                            <div class="w-8 h-8 rounded-full <?= $todayClass['mcq_completed'] ? 'bg-green-100' : 'bg-gray-100' ?> flex items-center justify-center">
                                <i class="fas fa-question-circle <?= $todayClass['mcq_completed'] ? 'text-green-600' : 'text-gray-400' ?>"></i>
                            </div>
                            <span class="ml-2 text-sm <?= $todayClass['mcq_completed'] ? 'text-green-600' : 'text-gray-500' ?>">MCQ</span>
                        </div>
                        <div class="flex items-center">
                            <div class="w-8 h-8 rounded-full <?= $todayClass['lab_completed'] ? 'bg-green-100' : 'bg-gray-100' ?> flex items-center justify-center">
                                <i class="fas fa-code <?= $todayClass['lab_completed'] ? 'text-green-600' : 'text-gray-400' ?>"></i>
                            </div>
                            <span class="ml-2 text-sm <?= $todayClass['lab_completed'] ? 'text-green-600' : 'text-gray-500' ?>">Lab</span>
                        </div>
                    </div>
                    
                    <!-- Duration Badges -->
                    <div class="flex flex-wrap gap-3 mt-4">
                        <span class="px-3 py-1 bg-gray-100 text-gray-600 text-sm rounded-full">
                            <i class="far fa-clock mr-1"></i><?= $todayClass['video_duration'] ?> min Video
                        </span>
                        <span class="px-3 py-1 bg-gray-100 text-gray-600 text-sm rounded-full">
                            <i class="far fa-clock mr-1"></i><?= $todayClass['mcq_duration'] ?> min MCQ
                        </span>
                        <span class="px-3 py-1 bg-gray-100 text-gray-600 text-sm rounded-full">
                            <i class="far fa-clock mr-1"></i><?= $todayClass['lab_duration'] ?> min Lab
                        </span>
                    </div>
                </div>
            </div>
            
            <!-- Action Buttons -->
            <div class="flex items-center space-x-4 mt-6 pt-4 border-t border-gray-100">
                <a href="../courses/view.php?id=<?= $todayClass['course_id'] ?>&class=<?= $todayClass['id'] ?>" 
                   class="flex-1 px-6 py-3 bg-gradient-to-r from-blue-600 to-blue-700 text-white rounded-xl font-semibold hover:from-blue-700 hover:to-blue-800 transition text-center">
                    <i class="fas fa-play-circle mr-2"></i>Start Class →
                </a>
                <a href="#" class="px-6 py-3 border border-gray-200 text-gray-600 rounded-xl font-semibold hover:bg-gray-50 transition">
                    <i class="fas fa-file-pdf mr-2"></i>View Materials
                </a>
            </div>
        </div>
    <?php else: ?>
        <div class="p-12 text-center">
            <div class="w-20 h-20 bg-gray-100 rounded-full mx-auto mb-4 flex items-center justify-center">
               <i class="fas fa-calendar-check text-gray-400 text-3xl"></i>
                </div>
            </div>
            <h3 class="text-lg font-semibold text-gray-800 mb-2">No Class Scheduled Today</h3>
            <p class="text-gray-500 mb-6">Take a moment to review your previous lessons or explore new courses.</p>
            <div class="flex justify-center space-x-4">
                <a href="../courses/index.php" class="px-6 py-3 bg-blue-600 text-white rounded-xl font-semibold hover:bg-blue-700 transition">
                    <i class="fas fa-book-open mr-2"></i>My Courses
                </a>
                <a href="#" class="px-6 py-3 border border-gray-200 text-gray-600 rounded-xl font-semibold hover:bg-gray-50 transition">
                    <i class="fas fa-search mr-2"></i>Browse Courses
                </a>
            </div>
        </div>
    <?php endif; ?>
</div>