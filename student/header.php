<div class="bg-white border-b border-gray-100 px-6 py-4">
    <div class="flex items-center justify-between">
        <div class="flex items-center space-x-4">
            <h2 class="text-xl font-semibold text-gray-800">
                <?php
                $page_titles = [
                    'dashboard.php' => 'Dashboard',
                    'courses.php' => 'My Courses',
                    'course-view.php' => 'Course Details',
                    'video.php' => 'Watch Video',
                    'tests.php' => 'Tests & Quizzes',
                    'take-test.php' => 'Take Test',
                    'results.php' => 'Test Results',
                    'certificates.php' => 'My Certificates',
                    'profile.php' => 'Profile Settings'
                ];
                $current = basename($_SERVER['PHP_SELF']);
                echo $page_titles[$current] ?? 'Student Portal';
                ?>
            </h2>
        </div>
        
        <div class="flex items-center space-x-4">
            <!-- Notifications (simplified) -->
            <button class="relative p-2 text-gray-400 hover:text-gray-600">
                <i class="fas fa-bell text-xl"></i>
                <span class="absolute top-1 right-1 w-2 h-2 bg-red-500 rounded-full"></span>
            </button>
            
            <!-- Date/Time -->
            <div class="text-sm text-gray-500">
                <i class="far fa-calendar mr-2"></i><?= date('l, F j, Y') ?>
            </div>
        </div>
    </div>
</div>