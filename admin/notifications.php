<?php
require_once '../includes/auth.php';
require_once '../includes/auto_publish.php';
requireAdmin();

// Run auto-publisher on every page load
autoPublishVideos($pdo);

$message = '';
$error = '';

// Handle notification creation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_notification'])) {
    $title = trim($_POST['title']);
    $message_text = trim($_POST['message']);
    $type = $_POST['type'];
    $target_role = $_POST['target_role'];
    $expires_at = !empty($_POST['expires_at']) ? $_POST['expires_at'] : null;
    
    try {
        $pdo->beginTransaction();
        
        // Insert notification
        $stmt = $pdo->prepare("INSERT INTO notifications (title, message, type, target_role, created_by, expires_at) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([$title, $message_text, $type, $target_role, $_SESSION['user_id'], $expires_at]);
        $notification_id = $pdo->lastInsertId();
        
        // Get target users
        if ($target_role == 'all') {
            $users = $pdo->query("SELECT id FROM users")->fetchAll();
        } else {
            $users = $pdo->prepare("SELECT id FROM users WHERE role = ?")->execute([$target_role]);
            $users = $stmt->fetchAll();
        }
        
        // Create user notifications
        $stmt = $pdo->prepare("INSERT INTO user_notifications (user_id, notification_id) VALUES (?, ?)");
        foreach ($users as $user) {
            $stmt->execute([$user['id'], $notification_id]);
        }
        
        $pdo->commit();
        $message = 'Notification sent successfully';
    } catch (Exception $e) {
        $pdo->rollBack();
        $error = 'Failed to send notification';
    }
}

// Delete notification
if (isset($_GET['delete'])) {
    $id = $_GET['delete'];
    try {
        $stmt = $pdo->prepare("DELETE FROM notifications WHERE id = ?");
        $stmt->execute([$id]);
        $message = 'Notification deleted successfully';
    } catch (PDOException $e) {
        $error = 'Failed to delete notification';
    }
}

// Get all notifications
$stmt = $pdo->query("SELECT n.*, u.name as creator_name, 
                     (SELECT COUNT(*) FROM user_notifications WHERE notification_id = n.id AND is_read = 1) as read_count,
                     (SELECT COUNT(*) FROM user_notifications WHERE notification_id = n.id) as total_count
                     FROM notifications n 
                     LEFT JOIN users u ON n.created_by = u.id 
                     ORDER BY n.created_at DESC");
$notifications = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notification Management</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-gray-100">
    <div class="flex h-screen">
        <?php include 'sidebar.php'; ?>

        <div class="flex-1 overflow-auto">
            <div class="p-8">
                <h1 class="text-3xl font-bold mb-8">Notification Management</h1>

                <?php if ($message): ?>
                    <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4"><?= $message ?></div>
                <?php endif; ?>
                <?php if ($error): ?>
                    <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4"><?= $error ?></div>
                <?php endif; ?>

                <!-- Create Notification Form -->
                <div class="bg-white rounded-lg shadow p-6 mb-8">
                    <h2 class="text-xl font-bold mb-4">Create New Notification</h2>
                    <form method="POST">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label class="block text-gray-700 mb-2">Title</label>
                                <input type="text" name="title" required class="w-full px-3 py-2 border rounded focus:outline-none focus:ring">
                            </div>
                            
                            <div>
                                <label class="block text-gray-700 mb-2">Type</label>
                                <select name="type" class="w-full px-3 py-2 border rounded focus:outline-none focus:ring">
                                    <option value="info">Info (Blue)</option>
                                    <option value="success">Success (Green)</option>
                                    <option value="warning">Warning (Yellow)</option>
                                    <option value="danger">Danger (Red)</option>
                                </select>
                            </div>
                            
                            <div class="md:col-span-2">
                                <label class="block text-gray-700 mb-2">Message</label>
                                <textarea name="message" rows="4" required class="w-full px-3 py-2 border rounded focus:outline-none focus:ring"></textarea>
                            </div>
                            
                            <div>
                                <label class="block text-gray-700 mb-2">Target Audience</label>
                                <select name="target_role" class="w-full px-3 py-2 border rounded focus:outline-none focus:ring">
                                    <option value="all">All Users</option>
                                    <option value="admin">Admins Only</option>
                                    <option value="student">Students Only</option>
                                </select>
                            </div>
                            
                            <div>
                                <label class="block text-gray-700 mb-2">Expires At (Optional)</label>
                                <input type="datetime-local" name="expires_at" class="w-full px-3 py-2 border rounded focus:outline-none focus:ring">
                            </div>
                        </div>
                        
                        <button type="submit" name="create_notification" class="mt-4 bg-blue-600 text-white px-6 py-2 rounded hover:bg-blue-700">
                            <i class="fas fa-paper-plane mr-2"></i>Send Notification
                        </button>
                    </form>
                </div>

                <!-- Notifications List -->
                <div class="bg-white rounded-lg shadow overflow-hidden">
                    <h2 class="text-xl font-bold p-6 border-b">Notification History</h2>
                    <div class="divide-y divide-gray-200">
                        <?php foreach ($notifications as $note): ?>
                        <div class="p-6">
                            <div class="flex items-start justify-between">
                                <div class="flex-1">
                                    <div class="flex items-center space-x-3">
                                        <span class="px-2 py-1 text-xs rounded 
                                            <?= $note['type'] == 'info' ? 'bg-blue-100 text-blue-800' : '' ?>
                                            <?= $note['type'] == 'success' ? 'bg-green-100 text-green-800' : '' ?>
                                            <?= $note['type'] == 'warning' ? 'bg-yellow-100 text-yellow-800' : '' ?>
                                            <?= $note['type'] == 'danger' ? 'bg-red-100 text-red-800' : '' ?>">
                                            <?= ucfirst($note['type']) ?>
                                        </span>
                                        <span class="text-sm text-gray-500">
                                            <i class="fas fa-users mr-1"></i><?= ucfirst($note['target_role']) ?>
                                        </span>
                                        <span class="text-sm text-gray-500">
                                            <i class="fas fa-eye mr-1"></i><?= $note['read_count'] ?>/<?= $note['total_count'] ?> read
                                        </span>
                                    </div>
                                    <h3 class="text-lg font-bold mt-2"><?= htmlspecialchars($note['title']) ?></h3>
                                    <p class="text-gray-600 mt-1"><?= nl2br(htmlspecialchars($note['message'])) ?></p>
                                    <div class="flex items-center text-sm text-gray-500 mt-2">
                                        <span>Sent by <?= htmlspecialchars($note['creator_name'] ?? 'System') ?></span>
                                        <span class="mx-2">•</span>
                                        <span><?= date('M d, Y H:i', strtotime($note['created_at'])) ?></span>
                                        <?php if ($note['expires_at']): ?>
                                        <span class="mx-2">•</span>
                                        <span>Expires: <?= date('M d, Y', strtotime($note['expires_at'])) ?></span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <a href="?delete=<?= $note['id'] ?>" onclick="return confirm('Delete this notification?')" class="text-red-600 hover:text-red-900 ml-4">
                                    <i class="fas fa-trash"></i>
                                </a>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>