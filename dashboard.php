<?php
require_once '../includes/auth.php';
require_once '../includes/auto_publish.php';
requireAdmin();

// Run auto-publisher on every page load
autoPublishVideos($pdo);

$userId = $_SESSION['user_id'];

// Check if this is the main admin (first admin) or additional admin
$stmt = $pdo->prepare("SELECT id FROM users WHERE role = 'admin' ORDER BY id ASC LIMIT 1");
$stmt->execute();
$firstAdmin = $stmt->fetchColumn();

$isMainAdmin = ($userId == $firstAdmin);

// Get statistics
$stats = [];

// Total students
$stmt = $pdo->query("SELECT COUNT(*) as total FROM users WHERE role='student'");
$stats['total_students'] = $stmt->fetch()['total'];

// Total courses
$stmt = $pdo->query("SELECT COUNT(*) as total FROM courses WHERE status='active'");
$stats['total_courses'] = $stmt->fetch()['total'] ?? 0;

// Total videos
$stmt = $pdo->query("SELECT COUNT(*) as total FROM videos");
$stats['total_videos'] = $stmt->fetch()['total'] ?? 0;

// Total tests
$stmt = $pdo->query("SELECT COUNT(*) as total FROM tests");
$stats['total_tests'] = $stmt->fetch()['total'] ?? 0;

// Recent users
$stmt = $pdo->query("SELECT id, name, email, created_at FROM users WHERE role='student' ORDER BY created_at DESC LIMIT 5");
$recent_users = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-gray-100">
    <div class="flex h-screen">
        <?php include 'sidebar.php'; ?>

        <div class="flex-1 overflow-auto">
            <div class="p-8">
                <?php if (!$isMainAdmin): ?>
                <!-- Warning for additional admins (should not exist, but just in case) -->
                <div class="bg-yellow-100 border-l-4 border-yellow-500 text-yellow-700 p-4 mb-6">
                    <div class="flex items-center">
                        <i class="fas fa-exclamation-triangle mr-2"></i>
                        <div>
                            <p class="font-bold">Limited Access Mode</p>
                            <p class="text-sm">You have limited privileges. Some settings can only be changed by the main administrator.</p>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <h1 class="text-3xl font-bold mb-8">Dashboard Overview</h1>
                
                <!-- Stats Cards -->
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                    <div class="bg-white rounded-lg shadow p-6">
                        <div class="flex items-center">
                            <div class="p-3 bg-blue-100 rounded-full">
                                <i class="fas fa-users text-blue-600 text-xl"></i>
                            </div>
                            <div class="ml-4">
                                <p class="text-sm text-gray-500">Total Students</p>
                                <p class="text-2xl font-bold"><?= $stats['total_students'] ?></p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="bg-white rounded-lg shadow p-6">
                        <div class="flex items-center">
                            <div class="p-3 bg-green-100 rounded-full">
                                <i class="fas fa-book text-green-600 text-xl"></i>
                            </div>
                            <div class="ml-4">
                                <p class="text-sm text-gray-500">Active Courses</p>
                                <p class="text-2xl font-bold"><?= $stats['total_courses'] ?></p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="bg-white rounded-lg shadow p-6">
                        <div class="flex items-center">
                            <div class="p-3 bg-red-100 rounded-full">
                                <i class="fas fa-video text-red-600 text-xl"></i>
                            </div>
                            <div class="ml-4">
                                <p class="text-sm text-gray-500">Total Videos</p>
                                <p class="text-2xl font-bold"><?= $stats['total_videos'] ?></p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="bg-white rounded-lg shadow p-6">
                        <div class="flex items-center">
                            <div class="p-3 bg-purple-100 rounded-full">
                                <i class="fas fa-file-alt text-purple-600 text-xl"></i>
                            </div>
                            <div class="ml-4">
                                <p class="text-sm text-gray-500">Total Tests</p>
                                <p class="text-2xl font-bold"><?= $stats['total_tests'] ?></p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Recent Users -->
                <div class="bg-white rounded-lg shadow p-6">
                    <h2 class="text-xl font-bold mb-4">Recent Students</h2>
                    <div class="space-y-3">
                        <?php foreach ($recent_users as $user): ?>
                        <div class="flex items-center justify-between border-b pb-2">
                            <div>
                                <p class="font-medium"><?= htmlspecialchars($user['name']) ?></p>
                                <p class="text-sm text-gray-500"><?= htmlspecialchars($user['email']) ?></p>
                            </div>
                            <span class="text-xs text-gray-400"><?= date('M d', strtotime($user['created_at'])) ?></span>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>