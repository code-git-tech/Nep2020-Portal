<?php
$currentDate = date('l, F j, Y');
$currentTime = date('h:i A');
?>
<header class="bg-white border-b border-gray-100 sticky top-0 z-10">
    <div class="flex items-center justify-between px-6 py-4">
        <!-- Search Bar -->
        <div class="flex-1 max-w-lg">
            <div class="relative">
                <i class="fas fa-search absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400 text-sm"></i>
                <input type="text" 
                       placeholder="Search courses, lessons, or materials..." 
                       class="w-full pl-10 pr-4 py-2.5 bg-gray-50 border border-gray-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent">
            </div>
        </div>

        <!-- Header Right -->
        <div class="flex items-center space-x-6">
            <!-- Date/Time -->
            <div class="text-right hidden md:block">
                <div class="text-sm font-medium text-gray-800"><?= $currentDate ?></div>
                <div class="text-xs text-gray-500"><?= $currentTime ?></div>
            </div>

            <!-- Notifications -->
            <div class="relative">
                <button class="relative p-2 text-gray-400 hover:text-gray-600 transition">
                    <i class="fas fa-bell text-xl"></i>
                    <?php if($unreadCount > 0): ?>
                        <span class="absolute top-0 right-0 w-2.5 h-2.5 bg-red-500 rounded-full notification-badge"></span>
                    <?php endif; ?>
                </button>
                
                <!-- Notifications Dropdown -->
                <div class="absolute right-0 mt-2 w-80 bg-white rounded-xl shadow-xl border border-gray-100 hidden group-hover:block">
                    <div class="p-4 border-b border-gray-100">
                        <h3 class="font-semibold text-gray-800">Notifications</h3>
                    </div>
                    <div class="max-h-96 overflow-y-auto">
                        <?php if(empty($notifications)): ?>
                            <div class="p-4 text-center text-gray-500 text-sm">
                                No new notifications
                            </div>
                        <?php else: ?>
                            <?php foreach($notifications as $note): ?>
                                <a href="<?= $note['link'] ?? '#' ?>" class="block p-4 hover:bg-gray-50 border-b border-gray-100 last:border-0 notification-item" data-id="<?= $note['id'] ?>">
                                    <div class="flex items-start space-x-3">
                                        <div class="flex-shrink-0">
                                            <div class="w-8 h-8 rounded-full bg-<?= $note['type'] == 'reminder' ? 'yellow' : ($note['type'] == 'success' ? 'green' : 'blue') ?>-100 flex items-center justify-center">
                                                <i class="fas fa-<?= $note['type'] == 'reminder' ? 'clock' : ($note['type'] == 'success' ? 'check' : 'info') ?> text-<?= $note['type'] == 'reminder' ? 'yellow' : ($note['type'] == 'success' ? 'green' : 'blue') ?>-600 text-xs"></i>
                                            </div>
                                        </div>
                                        <div class="flex-1">
                                            <p class="text-sm font-medium text-gray-800"><?= htmlspecialchars($note['title']) ?></p>
                                            <p class="text-xs text-gray-500 mt-1"><?= htmlspecialchars($note['message']) ?></p>
                                            <p class="text-xs text-gray-400 mt-2"><?= date('h:i A', strtotime($note['created_at'])) ?></p>
                                        </div>
                                        <?php if(!$note['is_read']): ?>
                                            <span class="w-2 h-2 bg-blue-600 rounded-full"></span>
                                        <?php endif; ?>
                                    </div>
                                </a>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                    <div class="p-3 border-t border-gray-100">
                        <a href="#" class="block text-center text-sm text-blue-600 hover:text-blue-700">
                            View all notifications
                        </a>
                    </div>
                </div>
            </div>

            <!-- User Menu -->
            <div class="relative group">
                <button class="flex items-center space-x-3">
                    <div class="w-10 h-10 gradient-bg rounded-xl flex items-center justify-center text-white font-semibold">
                        <?= strtoupper(substr($_SESSION['name'], 0, 1)) ?>
                    </div>
                    <div class="hidden md:block text-left">
                        <p class="text-sm font-medium text-gray-800"><?= htmlspecialchars($_SESSION['name']) ?></p>
                        <p class="text-xs text-gray-500">Student</p>
                    </div>
                    <i class="fas fa-chevron-down text-xs text-gray-400"></i>
                </button>
                
                <!-- Dropdown Menu -->
                <div class="absolute right-0 mt-2 w-48 bg-white rounded-xl shadow-xl border border-gray-100 hidden group-hover:block">
                    <a href="../profile/index.php" class="flex items-center space-x-3 px-4 py-3 hover:bg-gray-50">
                        <i class="fas fa-user text-gray-500 w-5"></i>
                        <span class="text-sm text-gray-700">Profile</span>
                    </a>
                    <a href="#" class="flex items-center space-x-3 px-4 py-3 hover:bg-gray-50">
                        <i class="fas fa-cog text-gray-500 w-5"></i>
                        <span class="text-sm text-gray-700">Settings</span>
                    </a>
                    <div class="border-t border-gray-100"></div>
                    <a href="../../logout.php" class="flex items-center space-x-3 px-4 py-3 hover:bg-gray-50 text-red-500">
                        <i class="fas fa-sign-out-alt w-5"></i>
                        <span class="text-sm">Logout</span>
                    </a>
                </div>
            </div>
        </div>
    </div>
</header>