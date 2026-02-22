<?php
require_once '../includes/auth.php';
require_once '../includes/student-functions.php';
requireStudent();

$user_id = $_SESSION['user_id'];

// Handle mark as read
if (isset($_POST['mark_read'])) {
    $notification_id = $_POST['notification_id'];
    $stmt = $pdo->prepare("UPDATE student_notifications SET is_read = 1 WHERE id = ? AND student_id = ?");
    $stmt->execute([$notification_id, $user_id]);
    header('Location: notifications.php');
    exit();
}

// Handle mark all as read
if (isset($_POST['mark_all_read'])) {
    $stmt = $pdo->prepare("UPDATE student_notifications SET is_read = 1 WHERE student_id = ? AND is_read = 0");
    $stmt->execute([$user_id]);
    header('Location: notifications.php');
    exit();
}

// Handle delete notification
if (isset($_POST['delete'])) {
    $notification_id = $_POST['notification_id'];
    $stmt = $pdo->prepare("DELETE FROM student_notifications WHERE id = ? AND student_id = ?");
    $stmt->execute([$notification_id, $user_id]);
    header('Location: notifications.php');
    exit();
}

// Handle clear all
if (isset($_POST['clear_all'])) {
    $stmt = $pdo->prepare("DELETE FROM student_notifications WHERE student_id = ?");
    $stmt->execute([$user_id]);
    header('Location: notifications.php');
    exit();
}

// Pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 10;
$offset = ($page - 1) * $limit;

// Filter
$filter = isset($_GET['filter']) ? $_GET['filter'] : 'all';

// Build query based on filter
$query = "FROM student_notifications WHERE student_id = ?";
$params = [$user_id];

if ($filter === 'unread') {
    $query .= " AND is_read = 0";
} elseif ($filter === 'read') {
    $query .= " AND is_read = 1";
}

// Get total count for pagination
$count_stmt = $pdo->prepare("SELECT COUNT(*) " . $query);
$count_stmt->execute($params);
$total_notifications = $count_stmt->fetchColumn();
$total_pages = ceil($total_notifications / $limit);

// Get notifications for current page
$stmt = $pdo->prepare("
    SELECT * " . $query . " 
    ORDER BY created_at DESC 
    LIMIT ? OFFSET ?
");
$params[] = $limit;
$params[] = $offset;
$stmt->execute($params);
$notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get counts
$unread_count = $pdo->prepare("SELECT COUNT(*) FROM student_notifications WHERE student_id = ? AND is_read = 0");
$unread_count->execute([$user_id]);
$unread_total = $unread_count->fetchColumn();

// Get notification by type counts
$type_counts = [];
$type_stmt = $pdo->prepare("
    SELECT type, COUNT(*) as count 
    FROM student_notifications 
    WHERE student_id = ? 
    GROUP BY type
");
$type_stmt->execute([$user_id]);
while ($row = $type_stmt->fetch(PDO::FETCH_ASSOC)) {
    $type_counts[$row['type']] = $row['count'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notifications - Student Portal</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap');
        
        * {
            font-family: 'Inter', sans-serif;
        }
        
        .notification-item {
            transition: all 0.2s ease;
        }
        
        .notification-item:hover {
            background-color: #f9fafb;
        }
        
        .notification-item.unread {
            background-color: #f0f9ff;
            border-left: 4px solid #3b82f6;
        }
        
        .fade-in {
            animation: fadeIn 0.3s ease-in;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }
    </style>
</head>
<body class="bg-gray-50">
    <div class="flex h-screen">
        <!-- Sidebar -->
        <?php include 'sidebar.php'; ?>

        <!-- Main Content -->
        <div class="flex-1 ml-72 bg-gray-50 overflow-auto">
            <!-- Header -->
            <header class="bg-white shadow-sm sticky top-0 z-10">
                <div class="flex items-center justify-between px-8 py-4">
                    <div>
                        <h2 class="text-2xl font-bold text-gray-800">Notifications</h2>
                        <p class="text-gray-500 mt-1">Stay updated with your learning journey</p>
                    </div>
                    <div class="flex items-center space-x-4">
                        <!-- Date -->
                        <div class="text-sm text-gray-500">
                            <i class="far fa-calendar-alt mr-2"></i><?= date('l, F j, Y') ?>
                        </div>
                        
                        <!-- Quick Actions -->
                        <?php if (!empty($notifications)): ?>
                        <form method="POST" class="inline" onsubmit="return confirm('Mark all notifications as read?')">
                            <button type="submit" name="mark_all_read" 
                                    class="px-4 py-2 text-sm bg-blue-50 text-blue-600 rounded-lg hover:bg-blue-100 transition">
                                <i class="fas fa-check-double mr-2"></i>Mark All Read
                            </button>
                        </form>
                        
                        <form method="POST" class="inline" onsubmit="return confirm('Clear all notifications? This cannot be undone.')">
                            <button type="submit" name="clear_all" 
                                    class="px-4 py-2 text-sm bg-red-50 text-red-600 rounded-lg hover:bg-red-100 transition">
                                <i class="fas fa-trash mr-2"></i>Clear All
                            </button>
                        </form>
                        <?php endif; ?>
                    </div>
                </div>
            </header>

            <!-- Main Content Area -->
            <div class="p-8">
                <!-- Summary Cards -->
                <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
                    <div class="bg-white rounded-xl shadow-sm p-6 border border-gray-100">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm text-gray-500 mb-1">Total</p>
                                <p class="text-2xl font-bold text-gray-800"><?= $total_notifications ?></p>
                            </div>
                            <div class="w-12 h-12 bg-blue-100 rounded-xl flex items-center justify-center">
                                <i class="fas fa-bell text-blue-600 text-xl"></i>
                            </div>
                        </div>
                    </div>
                    
                    <div class="bg-white rounded-xl shadow-sm p-6 border border-gray-100">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm text-gray-500 mb-1">Unread</p>
                                <p class="text-2xl font-bold text-yellow-600"><?= $unread_total ?></p>
                            </div>
                            <div class="w-12 h-12 bg-yellow-100 rounded-xl flex items-center justify-center">
                                <i class="fas fa-envelope text-yellow-600 text-xl"></i>
                            </div>
                        </div>
                    </div>
                    
                    <div class="bg-white rounded-xl shadow-sm p-6 border border-gray-100">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm text-gray-500 mb-1">Read</p>
                                <p class="text-2xl font-bold text-green-600"><?= $total_notifications - $unread_total ?></p>
                            </div>
                            <div class="w-12 h-12 bg-green-100 rounded-xl flex items-center justify-center">
                                <i class="fas fa-check-circle text-green-600 text-xl"></i>
                            </div>
                        </div>
                    </div>
                    
                    <div class="bg-white rounded-xl shadow-sm p-6 border border-gray-100">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm text-gray-500 mb-1">Categories</p>
                                <p class="text-2xl font-bold text-purple-600"><?= count($type_counts) ?></p>
                            </div>
                            <div class="w-12 h-12 bg-purple-100 rounded-xl flex items-center justify-center">
                                <i class="fas fa-tags text-purple-600 text-xl"></i>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Filters -->
                <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-4 mb-6">
                    <div class="flex items-center justify-between flex-wrap gap-4">
                        <div class="flex items-center space-x-2">
                            <span class="text-sm font-medium text-gray-600">Filter:</span>
                            <a href="?filter=all" 
                               class="px-4 py-2 text-sm rounded-lg transition <?= $filter === 'all' ? 'bg-blue-600 text-white' : 'bg-gray-100 text-gray-600 hover:bg-gray-200' ?>">
                                All
                            </a>
                            <a href="?filter=unread" 
                               class="px-4 py-2 text-sm rounded-lg transition <?= $filter === 'unread' ? 'bg-blue-600 text-white' : 'bg-gray-100 text-gray-600 hover:bg-gray-200' ?>">
                                Unread <?= $unread_total > 0 ? "($unread_total)" : '' ?>
                            </a>
                            <a href="?filter=read" 
                               class="px-4 py-2 text-sm rounded-lg transition <?= $filter === 'read' ? 'bg-blue-600 text-white' : 'bg-gray-100 text-gray-600 hover:bg-gray-200' ?>">
                                Read
                            </a>
                        </div>
                        
                        <!-- Type Filter (optional) -->
                        <div class="flex items-center space-x-2">
                            <span class="text-sm font-medium text-gray-600">Type:</span>
                            <select onchange="window.location.href='?type='+this.value" 
                                    class="px-3 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:border-blue-500">
                                <option value="all">All Types</option>
                                <option value="info">Info</option>
                                <option value="success">Success</option>
                                <option value="warning">Warning</option>
                                <option value="reminder">Reminder</option>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- Notifications List -->
                <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
                    <?php if (empty($notifications)): ?>
                        <div class="text-center py-16">
                            <div class="w-24 h-24 bg-gray-100 rounded-full mx-auto mb-4 flex items-center justify-center">
                                <i class="far fa-bell text-gray-400 text-3xl"></i>
                            </div>
                            <h3 class="text-lg font-semibold text-gray-800 mb-2">No notifications</h3>
                            <p class="text-gray-500">You're all caught up! Check back later for updates.</p>
                            <?php if ($filter !== 'all'): ?>
                                <a href="?filter=all" class="inline-block mt-4 text-blue-600 hover:text-blue-700">
                                    <i class="fas fa-arrow-left mr-2"></i>View all notifications
                                </a>
                            <?php endif; ?>
                        </div>
                    <?php else: ?>
                        <div class="divide-y divide-gray-100">
                            <?php foreach ($notifications as $notification): ?>
                                <div class="notification-item <?= $notification['is_read'] ? '' : 'unread' ?> p-6 fade-in">
                                    <div class="flex items-start justify-between">
                                        <div class="flex items-start space-x-4 flex-1">
                                            <!-- Icon based on type -->
                                            <div class="flex-shrink-0">
                                                <?php
                                                $icon = match($notification['type'] ?? 'info') {
                                                    'success' => 'fa-check-circle text-green-500 bg-green-100',
                                                    'warning' => 'fa-exclamation-triangle text-yellow-500 bg-yellow-100',
                                                    'reminder' => 'fa-clock text-blue-500 bg-blue-100',
                                                    default => 'fa-info-circle text-gray-500 bg-gray-100'
                                                };
                                                ?>
                                                <div class="w-10 h-10 rounded-full <?= explode(' ', $icon)[2] ?> flex items-center justify-center">
                                                    <i class="fas <?= explode(' ', $icon)[0] ?> <?= explode(' ', $icon)[1] ?>"></i>
                                                </div>
                                            </div>
                                            
                                            <!-- Content -->
                                            <div class="flex-1">
                                                <div class="flex items-center space-x-3 mb-1">
                                                    <h4 class="font-semibold text-gray-800"><?= htmlspecialchars($notification['title']) ?></h4>
                                                    <?php if (!$notification['is_read']): ?>
                                                        <span class="px-2 py-0.5 bg-blue-100 text-blue-600 text-xs rounded-full">New</span>
                                                    <?php endif; ?>
                                                    <span class="text-xs text-gray-400">
                                                        <?= date('M d, Y • g:i A', strtotime($notification['created_at'])) ?>
                                                    </span>
                                                </div>
                                                <p class="text-gray-600"><?= htmlspecialchars($notification['message']) ?></p>
                                                
                                                <!-- Additional metadata if available -->
                                                <?php if (!empty($notification['metadata'])): ?>
                                                    <div class="mt-2 text-sm text-gray-500">
                                                        <i class="fas fa-link mr-1"></i>
                                                        <?= htmlspecialchars($notification['metadata']) ?>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        
                                        <!-- Actions -->
                                        <div class="flex items-center space-x-2 ml-4">
                                            <?php if (!$notification['is_read']): ?>
                                                <form method="POST" class="inline">
                                                    <input type="hidden" name="notification_id" value="<?= $notification['id'] ?>">
                                                    <button type="submit" name="mark_read" 
                                                            class="p-2 text-gray-400 hover:text-blue-600 transition" 
                                                            title="Mark as read">
                                                        <i class="fas fa-check"></i>
                                                    </button>
                                                </form>
                                            <?php endif; ?>
                                            
                                            <form method="POST" class="inline" onsubmit="return confirm('Delete this notification?')">
                                                <input type="hidden" name="notification_id" value="<?= $notification['id'] ?>">
                                                <button type="submit" name="delete" 
                                                        class="p-2 text-gray-400 hover:text-red-600 transition" 
                                                        title="Delete">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        
                        <!-- Pagination -->
                        <?php if ($total_pages > 1): ?>
                            <div class="px-6 py-4 bg-gray-50 border-t border-gray-100">
                                <div class="flex items-center justify-between">
                                    <p class="text-sm text-gray-600">
                                        Showing <?= $offset + 1 ?>-<?= min($offset + $limit, $total_notifications) ?> of <?= $total_notifications ?> notifications
                                    </p>
                                    <div class="flex items-center space-x-2">
                                        <?php if ($page > 1): ?>
                                            <a href="?page=<?= $page - 1 ?>&filter=<?= $filter ?>" 
                                               class="px-4 py-2 bg-white border border-gray-200 rounded-lg text-sm hover:bg-gray-50 transition">
                                                <i class="fas fa-chevron-left mr-2"></i>Previous
                                            </a>
                                        <?php endif; ?>
                                        
                                        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                            <?php if ($i >= $page - 2 && $i <= $page + 2): ?>
                                                <a href="?page=<?= $i ?>&filter=<?= $filter ?>" 
                                                   class="px-4 py-2 <?= $i === $page ? 'bg-blue-600 text-white' : 'bg-white border border-gray-200 text-gray-600 hover:bg-gray-50' ?> rounded-lg text-sm transition">
                                                    <?= $i ?>
                                                </a>
                                            <?php endif; ?>
                                        <?php endfor; ?>
                                        
                                        <?php if ($page < $total_pages): ?>
                                            <a href="?page=<?= $page + 1 ?>&filter=<?= $filter ?>" 
                                               class="px-4 py-2 bg-white border border-gray-200 rounded-lg text-sm hover:bg-gray-50 transition">
                                                Next<i class="fas fa-chevron-right ml-2"></i>
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Auto-refresh every 60 seconds (optional)
        setTimeout(function() {
            location.reload();
        }, 60000);
    </script>
</body>
</html>