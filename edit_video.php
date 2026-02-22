<?php
require_once '../includes/auth.php';
require_once '../includes/auto_publish.php';
requireAdmin();

// Run auto-publisher on every page load
autoPublishVideos($pdo);

$message = '';
$error = '';

// Get video ID from URL
$video_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Fetch video details
$stmt = $pdo->prepare("
    SELECT v.*, c.title as course_title 
    FROM videos v 
    JOIN courses c ON v.course_id = c.id 
    WHERE v.id = ?
");
$stmt->execute([$video_id]);
$video = $stmt->fetch();

if (!$video) {
    header('Location: content.php');
    exit;
}

// Get all courses for dropdown
$courses = $pdo->query("SELECT id, title FROM courses WHERE status = 'active' ORDER BY title")->fetchAll();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $course_id = $_POST['course_id'];
    $title = trim($_POST['title']);
    $description = trim($_POST['description']);
    $duration = trim($_POST['duration']);
    $order_num = intval($_POST['order_num'] ?? 0);
    $status = $_POST['status'];
    $access = $_POST['access'];
    $scheduled_at = !empty($_POST['scheduled_at']) ? $_POST['scheduled_at'] : null;
    
    // Handle thumbnail upload if new one provided
    $thumbnail = $video['thumbnail'];
    if (isset($_FILES['thumbnail']) && $_FILES['thumbnail']['error'] == 0) {
        $thumb_allowed = ['image/jpeg', 'image/png', 'image/jpg', 'image/gif'];
        if (in_array($_FILES['thumbnail']['type'], $thumb_allowed)) {
            // Delete old thumbnail if exists
            if ($thumbnail && file_exists(__DIR__ . '/../' . $thumbnail)) {
                unlink(__DIR__ . '/../' . $thumbnail);
            }
            
            $thumb_ext = pathinfo($_FILES['thumbnail']['name'], PATHINFO_EXTENSION);
            $thumb_name = 'thumb_' . time() . '_' . uniqid() . '.' . $thumb_ext;
            $thumb_dest = __DIR__ . '/../uploads/thumbnails/' . $thumb_name;
            
            if (move_uploaded_file($_FILES['thumbnail']['tmp_name'], $thumb_dest)) {
                $thumbnail = 'uploads/thumbnails/' . $thumb_name;
            }
        }
    }
    
    // Update video
    $stmt = $pdo->prepare("
        UPDATE videos 
        SET course_id = ?, title = ?, description = ?, duration = ?, 
            order_num = ?, status = ?, access = ?, scheduled_at = ?, thumbnail = ?
        WHERE id = ?
    ");
    
    if ($stmt->execute([$course_id, $title, $description, $duration, $order_num, 
                       $status, $access, $scheduled_at, $thumbnail, $video_id])) {
        $message = 'Video updated successfully!';
        
        // Refresh video data
        $stmt = $pdo->prepare("SELECT v.*, c.title as course_title FROM videos v JOIN courses c ON v.course_id = c.id WHERE v.id = ?");
        $stmt->execute([$video_id]);
        $video = $stmt->fetch();
    } else {
        $error = 'Failed to update video';
    }
}

// Get all courses for dropdown
$courses = $pdo->query("SELECT id, title FROM courses WHERE status = 'active' ORDER BY title")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Video</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-gray-50">
    <div class="flex h-screen">
        <?php include 'sidebar.php'; ?>

        <div class="flex-1 overflow-auto">
            <div class="p-8">
                <div class="flex justify-between items-center mb-6">
                    <h1 class="text-3xl font-bold text-gray-800">Edit Video</h1>
                    <a href="content.php" class="bg-gray-500 text-white px-4 py-2 rounded-lg hover:bg-gray-600">
                        <i class="fas fa-arrow-left mr-2"></i>Back to Content
                    </a>
                </div>

                <?php if ($message): ?>
                    <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
                        <i class="fas fa-check-circle mr-2"></i><?= $message ?>
                    </div>
                <?php endif; ?>
                
                <?php if ($error): ?>
                    <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
                        <i class="fas fa-exclamation-circle mr-2"></i><?= $error ?>
                    </div>
                <?php endif; ?>

                <div class="bg-white rounded-lg shadow-sm p-6">
                    <form method="POST" enctype="multipart/form-data">
                        <div class="grid grid-cols-2 gap-6">
                            <!-- Left Column -->
                            <div class="space-y-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Course *</label>
                                    <select name="course_id" required class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                                        <?php foreach ($courses as $course): ?>
                                            <option value="<?= $course['id'] ?>" <?= $course['id'] == $video['course_id'] ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($course['title']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Video Title *</label>
                                    <input type="text" name="title" value="<?= htmlspecialchars($video['title']) ?>" required 
                                           class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                                </div>
                                
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Description</label>
                                    <textarea name="description" rows="4" 
                                              class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"><?= htmlspecialchars($video['description']) ?></textarea>
                                </div>
                                
                                <div class="grid grid-cols-2 gap-4">
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-2">Duration</label>
                                        <input type="text" name="duration" value="<?= htmlspecialchars($video['duration']) ?>" 
                                               placeholder="e.g., 10:30" class="w-full px-4 py-2 border border-gray-300 rounded-lg">
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-2">Order Number</label>
                                        <input type="number" name="order_num" value="<?= $video['order_num'] ?>" 
                                               class="w-full px-4 py-2 border border-gray-300 rounded-lg">
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Right Column -->
                            <div class="space-y-4">
                                <div class="grid grid-cols-2 gap-4">
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-2">Status</label>
                                        <select name="status" id="videoStatus" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                                            <option value="draft" <?= $video['status'] == 'draft' ? 'selected' : '' ?>>Draft</option>
                                            <option value="published" <?= $video['status'] == 'published' ? 'selected' : '' ?>>Published</option>
                                            <option value="scheduled" <?= $video['status'] == 'scheduled' ? 'selected' : '' ?>>Scheduled</option>
                                            <option value="disabled" <?= $video['status'] == 'disabled' ? 'selected' : '' ?>>Disabled</option>
                                        </select>
                                    </div>
                                    
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-2">Access</label>
                                        <select name="access" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                                            <option value="all" <?= $video['access'] == 'all' ? 'selected' : '' ?>>All Users</option>
                                            <option value="enrolled_only" <?= $video['access'] == 'enrolled_only' ? 'selected' : '' ?>>Enrolled Students Only</option>
                                            <option value="none" <?= $video['access'] == 'none' ? 'selected' : '' ?>>No Access</option>
                                        </select>
                                    </div>
                                </div>
                                
                                <div id="scheduleField" style="<?= $video['status'] == 'scheduled' ? 'display:block' : 'display:none' ?>">
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Schedule Date & Time</label>
                                    <input type="datetime-local" name="scheduled_at" 
                                           value="<?= $video['scheduled_at'] ? date('Y-m-d\TH:i', strtotime($video['scheduled_at'])) : '' ?>"
                                           class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                                </div>
                                
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Current Thumbnail</label>
                                    <?php if ($video['thumbnail']): ?>
                                        <div class="mb-3">
                                            <img src="../<?= htmlspecialchars($video['thumbnail']) ?>" alt="Current thumbnail" class="w-48 h-32 object-cover rounded-lg border">
                                        </div>
                                    <?php endif; ?>
                                    <input type="file" name="thumbnail" accept="image/*" 
                                           class="w-full px-4 py-2 border border-gray-300 rounded-lg">
                                    <p class="text-xs text-gray-500 mt-1">Leave empty to keep current thumbnail</p>
                                </div>
                                
                                <div class="bg-gray-50 p-4 rounded-lg">
                                    <h3 class="font-medium text-gray-700 mb-2">Video Information</h3>
                                    <p class="text-sm text-gray-600">
                                        <i class="fas fa-video mr-2"></i>Video File: <?= basename($video['video_url']) ?>
                                    </p>
                                    <p class="text-sm text-gray-600 mt-1">
                                        <i class="fas fa-calendar mr-2"></i>Uploaded: <?= date('M d, Y', strtotime($video['created_at'])) ?>
                                    </p>
                                    <p class="text-sm text-gray-600 mt-1">
                                        <i class="fas fa-eye mr-2"></i>Views: <?= $video['views'] ?>
                                    </p>
                                </div>
                            </div>
                        </div>
                        
                        <div class="flex justify-end space-x-3 mt-8 pt-4 border-t">
                            <a href="content.php" class="px-6 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50">
                                Cancel
                            </a>
                            <button type="submit" class="px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">
                                <i class="fas fa-save mr-2"></i>Save Changes
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Show/hide schedule field based on status selection
        document.getElementById('videoStatus').addEventListener('change', function() {
            const scheduleField = document.getElementById('scheduleField');
            if (this.value === 'scheduled') {
                scheduleField.style.display = 'block';
            } else {
                scheduleField.style.display = 'none';
            }
        });
    </script>
</body>
</html>