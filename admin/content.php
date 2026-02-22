<?php
require_once '../includes/auth.php';
require_once '../includes/auto_publish.php';
requireAdmin();

// Run auto-publisher on every page load
$auto_published_count = autoPublishVideos($pdo);
if ($auto_published_count > 0) {
    $_SESSION['auto_published_count'] = $auto_published_count;
}

// Create upload directories with proper permissions
$upload_base = __DIR__ . '/../uploads/';
$video_dir = $upload_base . 'videos/';
$material_dir = $upload_base . 'materials/';
$thumb_dir = $upload_base . 'thumbnails/';

// Create directories if they don't exist with proper permissions
if (!file_exists($video_dir)) {
    mkdir($video_dir, 0755, true);
    chmod($video_dir, 0755);
}
if (!file_exists($material_dir)) {
    mkdir($material_dir, 0755, true);
    chmod($material_dir, 0755);
}
if (!file_exists($thumb_dir)) {
    mkdir($thumb_dir, 0755, true);
    chmod($thumb_dir, 0755);
}

// Check if directories are writable
$upload_errors = [];
if (!is_writable($video_dir)) $upload_errors[] = 'Video directory is not writable';
if (!is_writable($thumb_dir)) $upload_errors[] = 'Thumbnail directory is not writable';

$message = '';
$error = '';

// Get all courses
$courses = $pdo->query("SELECT id, title FROM courses WHERE status = 'active' ORDER BY title")->fetchAll();

// Handle video upload
if (isset($_POST['upload_video'])) {
    $course_id = $_POST['course_id'];
    $title = trim($_POST['title']);
    $description = trim($_POST['description']);
    $duration = trim($_POST['duration']);
    $order_num = intval($_POST['order_num'] ?? 0);
    $status = $_POST['status'] ?? 'draft';
    $access = $_POST['access'] ?? 'all';
    
    // FIX: Properly handle scheduled_at for database
    $scheduled_at = null;
    if ($status == 'scheduled' && !empty($_POST['scheduled_at'])) {
        $scheduled_at = date('Y-m-d H:i:s', strtotime($_POST['scheduled_at']));
        
        // Validate that scheduled time is in the future
        if (strtotime($scheduled_at) <= time()) {
            $error = 'Scheduled time must be in the future';
            $scheduled_at = null;
            $status = 'draft';
        }
    }
    
    if ($_FILES['video_file']['error'] == 0) {
        $allowed = ['video/mp4', 'video/webm', 'video/ogg', 'video/quicktime'];
        $max_size = 500 * 1024 * 1024; // 500MB
        
        if ($_FILES['video_file']['size'] > $max_size) {
            $error = 'Video file too large. Maximum size is 500MB.';
        } elseif (in_array($_FILES['video_file']['type'], $allowed)) {
            // Generate filename
            $extension = pathinfo($_FILES['video_file']['name'], PATHINFO_EXTENSION);
            $filename = 'video_' . time() . '_' . uniqid() . '.' . $extension;
            $destination = $video_dir . $filename;
            
            if (move_uploaded_file($_FILES['video_file']['tmp_name'], $destination)) {
                // Set proper file permissions
                chmod($destination, 0644);
                
                // Handle thumbnail upload
                $thumbnail = '';
                if (isset($_FILES['thumbnail']) && $_FILES['thumbnail']['error'] == 0) {
                    $thumb_allowed = ['image/jpeg', 'image/png', 'image/jpg', 'image/gif', 'image/webp'];
                    $thumb_max_size = 5 * 1024 * 1024; // 5MB
                    
                    if ($_FILES['thumbnail']['size'] > $thumb_max_size) {
                        $error = 'Thumbnail too large. Maximum size is 5MB.';
                    } elseif (in_array($_FILES['thumbnail']['type'], $thumb_allowed)) {
                        // Get image dimensions
                        $image_info = getimagesize($_FILES['thumbnail']['tmp_name']);
                        if ($image_info) {
                            $thumb_ext = pathinfo($_FILES['thumbnail']['name'], PATHINFO_EXTENSION);
                            $thumb_name = 'thumb_' . time() . '_' . uniqid() . '.' . $thumb_ext;
                            $thumb_dest = $thumb_dir . $thumb_name;
                            
                            if (move_uploaded_file($_FILES['thumbnail']['tmp_name'], $thumb_dest)) {
                                chmod($thumb_dest, 0644);
                                $thumbnail = 'uploads/thumbnails/' . $thumb_name;
                                
                                // Verify file was saved
                                if (!file_exists($thumb_dest)) {
                                    $error = 'Thumbnail file was not saved properly';
                                }
                            } else {
                                $error = 'Failed to upload thumbnail file. Error code: ' . $_FILES['thumbnail']['error'];
                            }
                        } else {
                            $error = 'Invalid image file';
                        }
                    } else {
                        $error = 'Invalid thumbnail file type. Allowed: JPG, PNG, GIF, WebP';
                    }
                }
                
                $video_url = 'uploads/videos/' . $filename;
                
                // Insert into database
                $stmt = $pdo->prepare("
                    INSERT INTO videos (course_id, title, description, video_url, thumbnail, duration, order_num, status, access, scheduled_at) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                
                if ($stmt->execute([$course_id, $title, $description, $video_url, $thumbnail, $duration, $order_num, $status, $access, $scheduled_at])) {
                    $message = 'Video uploaded successfully';
                    if ($thumbnail) {
                        $message .= ' with thumbnail';
                    }
                    if ($status == 'scheduled') {
                        $message .= ' and scheduled for ' . date('M d, Y H:i', strtotime($scheduled_at));
                    }
                } else {
                    $error = 'Failed to save video information to database';
                }
            } else {
                $error = 'Failed to upload video file. Check directory permissions.';
            }
        } else {
            $error = 'Invalid file type. Allowed: MP4, WebM, OGG, MOV';
        }
    } else {
        $error = 'Please select a video file';
    }
}

// Handle quick status update via GET
if (isset($_GET['quick_status'])) {
    $id = $_GET['id'];
    $status = $_GET['status'];
    
    try {
        $pdo->beginTransaction();
        
        // Get video details for logging
        $video_stmt = $pdo->prepare("SELECT title FROM videos WHERE id = ?");
        $video_stmt->execute([$id]);
        $video = $video_stmt->fetch();
        
        if ($status == 'scheduled' && isset($_GET['scheduled_at']) && !empty($_GET['scheduled_at'])) {
            // Format the datetime properly for database
            $scheduled_at = date('Y-m-d H:i:s', strtotime($_GET['scheduled_at']));
            
            // Validate that scheduled time is in the future
            if (strtotime($scheduled_at) <= time()) {
                $error = 'Scheduled time must be in the future';
            } else {
                $stmt = $pdo->prepare("UPDATE videos SET status = ?, scheduled_at = ? WHERE id = ?");
                $stmt->execute([$status, $scheduled_at, $id]);
                $message = 'Video scheduled successfully for ' . date('M d, Y H:i', strtotime($scheduled_at));
                
                // Log the action
                $log_stmt = $pdo->prepare("
                    INSERT INTO activity_logs (user_id, action, details, ip_address, user_agent) 
                    VALUES (?, 'schedule_video', ?, ?, ?)
                ");
                $log_stmt->execute([
                    $_SESSION['user_id'],
                    "Video '{$video['title']}' (ID: $id) scheduled for " . $scheduled_at,
                    $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1',
                    $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown'
                ]);
            }
        } else {
            $stmt = $pdo->prepare("UPDATE videos SET status = ?, scheduled_at = NULL WHERE id = ?");
            $stmt->execute([$status, $id]);
            $message = 'Video status updated to ' . ucfirst($status);
            
            // Log the action
            $log_stmt = $pdo->prepare("
                INSERT INTO activity_logs (user_id, action, details, ip_address, user_agent) 
                VALUES (?, 'update_status', ?, ?, ?)
            ");
            $log_stmt->execute([
                $_SESSION['user_id'],
                "Video '{$video['title']}' (ID: $id) status changed to $status",
                $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1',
                $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown'
            ]);
        }
        
        $pdo->commit();
    } catch (PDOException $e) {
        $pdo->rollBack();
        error_log("Status update error: " . $e->getMessage());
        $error = 'Failed to update video status';
    }
}

// Handle delete
if (isset($_GET['delete_video'])) {
    $id = $_GET['delete_video'];
    // Get file path first
    $stmt = $pdo->prepare("SELECT video_url, thumbnail, title FROM videos WHERE id = ?");
    $stmt->execute([$id]);
    $video = $stmt->fetch();
    
    if ($video) {
        // Delete video file
        $video_path = __DIR__ . '/../' . $video['video_url'];
        if (file_exists($video_path)) {
            unlink($video_path);
        }
        
        // Delete thumbnail file
        if ($video['thumbnail']) {
            $thumb_path = __DIR__ . '/../' . $video['thumbnail'];
            if (file_exists($thumb_path)) {
                unlink($thumb_path);
            }
        }
        
        $stmt = $pdo->prepare("DELETE FROM videos WHERE id = ?");
        if ($stmt->execute([$id])) {
            $message = 'Video deleted successfully';
            
            // Log the deletion
            $log_stmt = $pdo->prepare("
                INSERT INTO activity_logs (user_id, action, details, ip_address, user_agent) 
                VALUES (?, 'delete_video', ?, ?, ?)
            ");
            $log_stmt->execute([
                $_SESSION['user_id'],
                "Deleted video '{$video['title']}' (ID: $id)",
                $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1',
                $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown'
            ]);
        }
    }
}

// Handle material upload
if (isset($_POST['upload_material'])) {
    $course_id = $_POST['course_id'];
    $title = trim($_POST['title']);
    $description = trim($_POST['description']);
    $type = $_POST['material_type'];
    
    if ($_FILES['material_file']['error'] == 0) {
        $allowed = [
            'application/pdf' => 'pdf',
            'application/msword' => 'doc',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
            'application/vnd.ms-excel' => 'xls',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'xlsx',
            'application/vnd.ms-powerpoint' => 'ppt',
            'application/vnd.openxmlformats-officedocument.presentationml.presentation' => 'pptx',
            'text/plain' => 'txt',
            'application/zip' => 'zip',
            'application/x-zip-compressed' => 'zip'
        ];
        
        $max_size = 100 * 1024 * 1024; // 100MB
        
        if ($_FILES['material_file']['size'] > $max_size) {
            $error = 'File too large. Maximum size is 100MB.';
        } elseif (array_key_exists($_FILES['material_file']['type'], $allowed)) {
            $extension = $allowed[$_FILES['material_file']['type']];
            $filename = 'material_' . time() . '_' . uniqid() . '.' . $extension;
            $destination = $material_dir . $filename;
            
            if (move_uploaded_file($_FILES['material_file']['tmp_name'], $destination)) {
                chmod($destination, 0644);
                $file_size = $_FILES['material_file']['size'];
                $file_path = 'uploads/materials/' . $filename;
                $file_type = $_FILES['material_file']['type'];
                
                $stmt = $pdo->prepare("
                    INSERT INTO materials (course_id, title, description, file_path, file_type, file_size, material_type) 
                    VALUES (?, ?, ?, ?, ?, ?, ?)
                ");
                if ($stmt->execute([$course_id, $title, $description, $file_path, $file_type, $file_size, $type])) {
                    $message = 'Material uploaded successfully';
                } else {
                    $error = 'Failed to save material information';
                }
            } else {
                $error = 'Failed to upload file';
            }
        } else {
            $error = 'Invalid file type. Allowed: PDF, DOC, DOCX, XLS, XLSX, PPT, PPTX, TXT, ZIP';
        }
    } else {
        $error = 'Please select a file';
    }
}

if (isset($_GET['delete_material'])) {
    $id = $_GET['delete_material'];
    // Get file path first
    $stmt = $pdo->prepare("SELECT file_path, title FROM materials WHERE id = ?");
    $stmt->execute([$id]);
    $material = $stmt->fetch();
    
    if ($material) {
        $file_path = __DIR__ . '/../' . $material['file_path'];
        if (file_exists($file_path)) {
            unlink($file_path);
        }
        
        $stmt = $pdo->prepare("DELETE FROM materials WHERE id = ?");
        if ($stmt->execute([$id])) {
            $message = 'Material deleted successfully';
            
            // Log the deletion
            $log_stmt = $pdo->prepare("
                INSERT INTO activity_logs (user_id, action, details, ip_address, user_agent) 
                VALUES (?, 'delete_material', ?, ?, ?)
            ");
            $log_stmt->execute([
                $_SESSION['user_id'],
                "Deleted material '{$material['title']}' (ID: $id)",
                $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1',
                $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown'
            ]);
        }
    }
}

// Get all videos with course info - Only show published videos to students, but admin sees all
$videos = $pdo->query("
    SELECT v.*, c.title as course_title 
    FROM videos v 
    JOIN courses c ON v.course_id = c.id 
    ORDER BY c.title, v.order_num
")->fetchAll();

// Debug: Check if thumbnails exist
foreach ($videos as &$video) {
    if ($video['thumbnail']) {
        $full_thumb_path = __DIR__ . '/../' . $video['thumbnail'];
        if (!file_exists($full_thumb_path)) {
            // Thumbnail file missing, update database
            $video['thumbnail'] = null;
            $pdo->prepare("UPDATE videos SET thumbnail = NULL WHERE id = ?")->execute([$video['id']]);
        }
    }
}

// Get all materials with course info
$materials = $pdo->query("
    SELECT m.*, c.title as course_title 
    FROM materials m 
    JOIN courses c ON m.course_id = c.id 
    ORDER BY c.title, m.created_at DESC
")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Content Management</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .video-grid, .material-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
            gap: 1.5rem;
        }
        .video-card {
            transition: all 0.3s ease;
        }
        .video-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px -5px rgba(0,0,0,0.1);
        }
        .status-badge {
            padding: 0.25rem 0.75rem;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 600;
            display: inline-block;
        }
        .quick-status-menu {
            position: absolute;
            background: white;
            border: 1px solid #e2e8f0;
            border-radius: 0.5rem;
            box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1);
            z-index: 50;
            display: none;
        }
        .quick-status-menu.show {
            display: block;
        }
        .quick-status-item {
            padding: 0.5rem 1rem;
            cursor: pointer;
            transition: background 0.2s;
        }
        .quick-status-item:hover {
            background: #f7fafc;
        }
        .thumbnail-preview {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        .thumbnail-error {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
        .auto-publish-badge {
            animation: pulse 2s infinite;
        }
        @keyframes pulse {
            0% {
                opacity: 1;
            }
            50% {
                opacity: 0.7;
            }
            100% {
                opacity: 1;
            }
        }
    </style>
</head>
<body class="bg-gray-50">
    <div class="flex h-screen">
        <?php include 'sidebar.php'; ?>

        <div class="flex-1 overflow-auto">
            <div class="p-8">
                <div class="flex justify-between items-center mb-8">
                    <h1 class="text-3xl font-bold text-gray-800">Content Management</h1>
                    <div class="flex space-x-3">
                        <!-- APPROACH 2: Manual Publish Button -->
                        <button onclick="publishNow()" class="bg-yellow-500 text-white px-4 py-2 rounded-lg hover:bg-yellow-600">
                            <i class="fas fa-clock mr-2"></i>Publish Scheduled
                        </button>
                        <button onclick="openUploadModal('video')" class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700">
                            <i class="fas fa-video mr-2"></i>Upload Video
                        </button>
                        <button onclick="openUploadModal('material')" class="bg-green-600 text-white px-4 py-2 rounded-lg hover:bg-green-700">
                            <i class="fas fa-file-pdf mr-2"></i>Upload Material
                        </button>
                    </div>
                </div>

                <!-- Display upload directory errors -->
                <?php if (!empty($upload_errors)): ?>
                    <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
                        <strong>System Configuration Errors:</strong>
                        <ul class="list-disc ml-5 mt-2">
                            <?php foreach ($upload_errors as $upload_error): ?>
                                <li><?= htmlspecialchars($upload_error) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <?php if ($message): ?>
                    <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4 flex justify-between items-center">
                        <span><i class="fas fa-check-circle mr-2"></i><?= htmlspecialchars($message) ?></span>
                        <button onclick="this.parentElement.remove()" class="text-green-700">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                <?php endif; ?>
                
                <?php if ($error): ?>
                    <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4 flex justify-between items-center">
                        <span><i class="fas fa-exclamation-circle mr-2"></i><?= htmlspecialchars($error) ?></span>
                        <button onclick="this.parentElement.remove()" class="text-red-700">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                <?php endif; ?>

                <!-- Display auto-publish message -->
                <?php if (isset($auto_published_count) && $auto_published_count > 0 && !isset($_GET['publish_now'])): ?>
                    <div class="bg-blue-100 border border-blue-400 text-blue-700 px-4 py-3 rounded mb-4 flex justify-between items-center auto-publish-badge">
                        <span>
                            <i class="fas fa-clock mr-2"></i>
                            <strong><?= $auto_published_count ?> scheduled video(s) have been automatically published</strong> and are now visible to students.
                        </span>
                        <button onclick="this.parentElement.remove()" class="text-blue-700">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                <?php endif; ?>

                <!-- Tabs -->
                <div class="border-b border-gray-200 mb-6">
                    <nav class="flex space-x-8">
                        <button onclick="showTab('videos')" id="tab-videos-btn" class="tab-btn active py-2 px-1 border-b-2 border-blue-500 font-medium text-blue-600">
                            <i class="fas fa-video mr-2"></i>Videos (<?= count($videos) ?>)
                        </button>
                        <button onclick="showTab('materials')" id="tab-materials-btn" class="tab-btn py-2 px-1 border-b-2 border-transparent font-medium text-gray-500 hover:text-gray-700">
                            <i class="fas fa-file-pdf mr-2"></i>Study Materials (<?= count($materials) ?>)
                        </button>
                    </nav>
                </div>

                <!-- Videos Tab -->
                <div id="videos-tab" class="tab-content">
                    <?php if (empty($videos)): ?>
                        <div class="text-center py-12">
                            <div class="w-24 h-24 bg-gray-100 rounded-full mx-auto mb-4 flex items-center justify-center">
                                <i class="fas fa-video text-gray-400 text-3xl"></i>
                            </div>
                            <h3 class="text-lg font-medium text-gray-900 mb-2">No videos yet</h3>
                            <p class="text-gray-500 mb-4">Upload your first video to get started</p>
                            <button onclick="openUploadModal('video')" class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700">
                                <i class="fas fa-upload mr-2"></i>Upload Video
                            </button>
                        </div>
                    <?php else: ?>
                        <div class="video-grid">
                            <?php foreach ($videos as $video): 
                                // Status badge color
                                $status_colors = [
                                    'draft' => 'bg-gray-100 text-gray-800',
                                    'published' => 'bg-green-100 text-green-800',
                                    'scheduled' => 'bg-yellow-100 text-yellow-800',
                                    'disabled' => 'bg-red-100 text-red-800'
                                ];
                                $status_color = $status_colors[$video['status']] ?? 'bg-gray-100 text-gray-800';
                                
                                // Access badge color
                                $access_colors = [
                                    'all' => 'bg-blue-100 text-blue-800',
                                    'enrolled_only' => 'bg-purple-100 text-purple-800',
                                    'none' => 'bg-gray-100 text-gray-800'
                                ];
                                $access_color = $access_colors[$video['access']] ?? 'bg-blue-100 text-blue-800';
                            ?>
                            <div class="video-card bg-white rounded-lg shadow-sm border border-gray-100 overflow-hidden relative" id="video-<?= $video['id'] ?>">
                                <div class="relative h-40 bg-gray-800">
                                    <?php if ($video['thumbnail']): ?>
                                        <img src="../<?= htmlspecialchars($video['thumbnail']) ?>?t=<?= time() ?>" 
                                             alt="Thumbnail" 
                                             class="w-full h-full object-cover"
                                             onerror="this.onerror=null; this.parentElement.classList.add('thumbnail-error'); this.parentElement.innerHTML='<div class=\'w-full h-full flex items-center justify-center\'><i class=\'fas fa-play text-white text-4xl opacity-50\'></i><div class=\'absolute bottom-0 left-0 right-0 bg-red-500 text-white text-xs p-1 text-center\'>Thumbnail not found</div></div>';">
                                    <?php else: ?>
                                        <div class="w-full h-full flex items-center justify-center bg-gradient-to-br from-blue-500 to-purple-600">
                                            <i class="fas fa-play text-white text-4xl opacity-50"></i>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <!-- Status Badge -->
                                    <div class="absolute top-2 left-2">
                                        <span class="status-badge <?= $status_color ?>">
                                            <i class="fas fa-<?= $video['status'] == 'published' ? 'check-circle' : ($video['status'] == 'draft' ? 'pen' : ($video['status'] == 'scheduled' ? 'clock' : 'ban')) ?> mr-1"></i>
                                            <?= ucfirst($video['status']) ?>
                                        </span>
                                    </div>
                                    
                                    <!-- Quick Actions Menu -->
                                    <div class="absolute top-2 right-2">
                                        <button onclick="toggleQuickMenu(<?= $video['id'] ?>)" class="bg-black bg-opacity-50 text-white p-2 rounded-full hover:bg-opacity-70 transition">
                                            <i class="fas fa-ellipsis-v"></i>
                                        </button>
                                        
                                        <!-- Quick Status Menu -->
                                        <div id="quick-menu-<?= $video['id'] ?>" class="quick-status-menu absolute right-0 mt-2 w-48">
                                            <div class="py-1">
                                                <div onclick="quickStatusChange(<?= $video['id'] ?>, 'draft')" class="quick-status-item flex items-center text-gray-700">
                                                    <i class="fas fa-pen w-5 text-gray-500"></i>
                                                    <span class="ml-2">Set as Draft</span>
                                                </div>
                                                <div onclick="quickStatusChange(<?= $video['id'] ?>, 'published')" class="quick-status-item flex items-center text-gray-700">
                                                    <i class="fas fa-check-circle w-5 text-green-500"></i>
                                                    <span class="ml-2">Publish Now</span>
                                                </div>
                                                <div onclick="openScheduleModal(<?= $video['id'] ?>)" class="quick-status-item flex items-center text-gray-700">
                                                    <i class="fas fa-calendar-alt w-5 text-yellow-500"></i>
                                                    <span class="ml-2">Schedule</span>
                                                </div>
                                                <div onclick="quickStatusChange(<?= $video['id'] ?>, 'disabled')" class="quick-status-item flex items-center text-gray-700">
                                                    <i class="fas fa-ban w-5 text-red-500"></i>
                                                    <span class="ml-2">Disable</span>
                                                </div>
                                                <div class="border-t my-1"></div>
                                                <div onclick="openEditModal(<?= $video['id'] ?>)" class="quick-status-item flex items-center text-gray-700">
                                                    <i class="fas fa-edit w-5 text-blue-500"></i>
                                                    <span class="ml-2">Full Edit</span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <span class="absolute bottom-2 right-2 bg-black bg-opacity-75 text-white text-xs px-2 py-1 rounded">
                                        <i class="far fa-clock mr-1"></i><?= htmlspecialchars($video['duration'] ?? '00:00') ?>
                                    </span>
                                </div>
                                
                                <div class="p-4">
                                    <div class="flex items-start justify-between">
                                        <div class="flex-1">
                                            <span class="text-xs text-blue-600 font-medium"><?= htmlspecialchars($video['course_title']) ?></span>
                                            <h3 class="font-semibold text-gray-800 mt-1"><?= htmlspecialchars($video['title']) ?></h3>
                                            <p class="text-sm text-gray-500 mt-1 line-clamp-2"><?= htmlspecialchars($video['description']) ?></p>
                                            
                                            <!-- Access and Views -->
                                            <div class="flex items-center mt-2 space-x-2">
                                                <span class="text-xs px-2 py-1 rounded-full <?= $access_color ?>">
                                                    <i class="fas fa-<?= $video['access'] == 'all' ? 'globe' : ($video['access'] == 'enrolled_only' ? 'lock' : 'ban') ?> mr-1"></i>
                                                    <?= ucfirst(str_replace('_', ' ', $video['access'])) ?>
                                                </span>
                                                <span class="text-xs text-gray-500">
                                                    <i class="fas fa-eye mr-1"></i> <?= number_format($video['views'] ?? 0) ?> views
                                                </span>
                                            </div>
                                            
                                            <!-- Scheduled date if applicable -->
                                            <?php if ($video['status'] == 'scheduled' && $video['scheduled_at']): ?>
                                                <p class="text-xs text-yellow-600 mt-2">
                                                    <i class="fas fa-calendar-alt mr-1"></i>
                                                    Scheduled: <?= date('M d, Y H:i', strtotime($video['scheduled_at'])) ?>
                                                    <?php if (strtotime($video['scheduled_at']) <= time()): ?>
                                                        <span class="text-red-600 ml-2">(Past due - will publish on next page load)</span>
                                                    <?php endif; ?>
                                                </p>
                                            <?php endif; ?>

                                            <!-- Debug info (remove in production) -->
                                            <?php if ($video['thumbnail']): ?>
                                                <p class="text-xs text-gray-400 mt-1">
                                                    <i class="fas fa-image mr-1"></i>
                                                    Thumbnail: <?= htmlspecialchars(basename($video['thumbnail'])) ?>
                                                </p>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    
                                    <div class="flex items-center justify-between mt-4 pt-3 border-t border-gray-100">
                                        <span class="text-xs text-gray-400">
                                            <i class="far fa-calendar mr-1"></i><?= date('M d, Y', strtotime($video['created_at'])) ?>
                                        </span>
                                        <div class="flex space-x-3">
                                            <a href="../<?= htmlspecialchars($video['video_url']) ?>" target="_blank" class="text-blue-600 hover:text-blue-800" title="View">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            <a href="edit_video.php?id=<?= $video['id'] ?>" class="text-indigo-600 hover:text-indigo-800" title="Full Edit">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <a href="?delete_video=<?= $video['id'] ?>" onclick="return confirm('Are you sure you want to delete this video? This action cannot be undone.')" class="text-red-600 hover:text-red-800" title="Delete">
                                                <i class="fas fa-trash"></i>
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Materials Tab -->
                <div id="materials-tab" class="tab-content hidden">
                    <?php if (empty($materials)): ?>
                        <div class="text-center py-12">
                            <div class="w-24 h-24 bg-gray-100 rounded-full mx-auto mb-4 flex items-center justify-center">
                                <i class="fas fa-file-pdf text-gray-400 text-3xl"></i>
                            </div>
                            <h3 class="text-lg font-medium text-gray-900 mb-2">No materials yet</h3>
                            <p class="text-gray-500 mb-4">Upload study materials for your courses</p>
                            <button onclick="openUploadModal('material')" class="bg-green-600 text-white px-4 py-2 rounded-lg hover:bg-green-700">
                                <i class="fas fa-upload mr-2"></i>Upload Material
                            </button>
                        </div>
                    <?php else: ?>
                        <div class="material-grid">
                            <?php foreach ($materials as $material): ?>
                            <div class="bg-white rounded-lg shadow-sm border border-gray-100 p-4 hover:shadow-md transition">
                                <div class="flex items-start">
                                    <div class="flex-shrink-0">
                                        <?php
                                        $icon = 'fa-file-pdf';
                                        $color = 'text-red-500';
                                        if (strpos($material['file_type'], 'word') !== false) {
                                            $icon = 'fa-file-word';
                                            $color = 'text-blue-500';
                                        } elseif (strpos($material['file_type'], 'excel') !== false || strpos($material['file_type'], 'sheet') !== false) {
                                            $icon = 'fa-file-excel';
                                            $color = 'text-green-500';
                                        } elseif (strpos($material['file_type'], 'presentation') !== false || strpos($material['file_type'], 'powerpoint') !== false) {
                                            $icon = 'fa-file-powerpoint';
                                            $color = 'text-orange-500';
                                        } elseif (strpos($material['file_type'], 'text') !== false) {
                                            $icon = 'fa-file-alt';
                                            $color = 'text-gray-500';
                                        } elseif (strpos($material['file_type'], 'zip') !== false) {
                                            $icon = 'fa-file-archive';
                                            $color = 'text-yellow-600';
                                        }
                                        ?>
                                        <i class="fas <?= $icon ?> <?= $color ?> text-3xl"></i>
                                    </div>
                                    <div class="ml-3 flex-1">
                                        <span class="text-xs text-blue-600 font-medium"><?= htmlspecialchars($material['course_title']) ?></span>
                                        <h3 class="font-semibold text-gray-800"><?= htmlspecialchars($material['title']) ?></h3>
                                        <p class="text-sm text-gray-500 mt-1"><?= htmlspecialchars($material['description']) ?></p>
                                        <div class="flex items-center justify-between mt-3">
                                            <span class="text-xs text-gray-400">
                                                <i class="far fa-file mr-1"></i><?= round($material['file_size'] / 1024 / 1024, 2) ?> MB
                                            </span>
                                            <div class="flex space-x-2">
                                                <a href="../<?= htmlspecialchars($material['file_path']) ?>" target="_blank" class="text-blue-600 hover:text-blue-800">
                                                    <i class="fas fa-download"></i>
                                                </a>
                                                <a href="?delete_material=<?= $material['id'] ?>" onclick="return confirm('Delete this material?')" class="text-red-600 hover:text-red-800">
                                                    <i class="fas fa-trash"></i>
                                                </a>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Upload Modal -->
    <div id="uploadModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden items-center justify-center z-50">
        <div class="bg-white rounded-xl p-8 max-w-2xl w-full max-h-[90vh] overflow-y-auto">
            <div class="flex justify-between items-center mb-6">
                <h2 id="modalTitle" class="text-2xl font-bold text-gray-800">Upload Video</h2>
                <button onclick="closeModal()" class="text-gray-400 hover:text-gray-600">
                    <i class="fas fa-times text-2xl"></i>
                </button>
            </div>

            <!-- Video Upload Form -->
            <form id="videoForm" method="POST" enctype="multipart/form-data" class="hidden">
                <input type="hidden" name="upload_video" value="1">
                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Course *</label>
                        <select name="course_id" required class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                            <option value="">Select Course</option>
                            <?php foreach ($courses as $course): ?>
                                <option value="<?= $course['id'] ?>"><?= htmlspecialchars($course['title']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Video Title *</label>
                        <input type="text" name="title" required class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Description</label>
                        <textarea name="description" rows="3" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"></textarea>
                    </div>
                    
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Duration</label>
                            <input type="text" name="duration" placeholder="e.g., 10:30" class="w-full px-4 py-2 border border-gray-300 rounded-lg">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Order Number</label>
                            <input type="number" name="order_num" value="0" class="w-full px-4 py-2 border border-gray-300 rounded-lg">
                        </div>
                    </div>
                    
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Status</label>
                            <select name="status" id="videoStatus" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                                <option value="draft">Draft</option>
                                <option value="published">Published</option>
                                <option value="scheduled">Scheduled</option>
                                <option value="disabled">Disabled</option>
                            </select>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Access</label>
                            <select name="access" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                                <option value="all">All Users</option>
                                <option value="enrolled_only">Enrolled Students Only</option>
                                <option value="none">No Access</option>
                            </select>
                        </div>
                    </div>
                    
                    <div id="scheduleField" style="display: none;">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Schedule Date & Time</label>
                        <input type="datetime-local" name="scheduled_at" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Video File * (MP4, WebM, MOV - max 500MB)</label>
                        <input type="file" name="video_file" accept="video/*" required class="w-full border border-gray-300 rounded-lg p-2">
                        <p class="text-xs text-gray-500 mt-1">Supported formats: MP4, WebM, OGG, MOV</p>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Thumbnail (Optional - JPG, PNG, GIF, WebP - max 5MB)</label>
                        <input type="file" name="thumbnail" accept="image/*" class="w-full border border-gray-300 rounded-lg p-2">
                        <p class="text-xs text-gray-500 mt-1">Recommended size: 1280x720 pixels</p>
                    </div>
                    
                    <div class="flex justify-end space-x-3 mt-6">
                        <button type="button" onclick="closeModal()" class="px-6 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50">
                            Cancel
                        </button>
                        <button type="submit" class="px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">
                            <i class="fas fa-upload mr-2"></i>Upload Video
                        </button>
                    </div>
                </div>
            </form>

            <!-- Material Upload Form -->
            <form id="materialForm" method="POST" enctype="multipart/form-data" class="hidden">
                <input type="hidden" name="upload_material" value="1">
                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Course *</label>
                        <select name="course_id" required class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500">
                            <option value="">Select Course</option>
                            <?php foreach ($courses as $course): ?>
                                <option value="<?= $course['id'] ?>"><?= htmlspecialchars($course['title']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Material Title *</label>
                        <input type="text" name="title" required class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500">
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Description</label>
                        <textarea name="description" rows="3" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500"></textarea>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Material Type</label>
                        <select name="material_type" class="w-full px-4 py-2 border border-gray-300 rounded-lg">
                            <option value="lecture_notes">Lecture Notes</option>
                            <option value="assignment">Assignment</option>
                            <option value="reference">Reference Material</option>
                            <option value="exercise">Exercise</option>
                            <option value="other">Other</option>
                        </select>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">File * (PDF, DOC, DOCX, XLS, XLSX, PPT, PPTX, TXT, ZIP - max 100MB)</label>
                        <input type="file" name="material_file" required class="w-full border border-gray-300 rounded-lg p-2">
                    </div>
                    
                    <div class="flex justify-end space-x-3 mt-6">
                        <button type="button" onclick="closeModal()" class="px-6 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50">
                            Cancel
                        </button>
                        <button type="submit" class="px-6 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700">
                            <i class="fas fa-upload mr-2"></i>Upload Material
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Schedule Modal -->
    <div id="scheduleModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden items-center justify-center z-50">
        <div class="bg-white rounded-xl p-8 max-w-md w-full">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-xl font-bold text-gray-800">Schedule Video</h3>
                <button onclick="closeScheduleModal()" class="text-gray-400 hover:text-gray-600">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <input type="hidden" id="scheduleVideoId">
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-2">Select Date & Time</label>
                <input type="datetime-local" id="scheduleDateTime" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                <p class="text-xs text-gray-500 mt-1">Video will be automatically published at this time and visible to students</p>
            </div>
            <div class="flex justify-end space-x-3">
                <button onclick="closeScheduleModal()" class="px-4 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50">
                    Cancel
                </button>
                <button onclick="saveSchedule()" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">
                    Save Schedule
                </button>
            </div>
        </div>
    </div>

    <script>
        let activeMenu = null;

        // APPROACH 2: Manual Publish Button Function
        function publishNow() {
            if (confirm('Publish all scheduled videos that are past their scheduled time? They will immediately become visible to students.')) {
                window.location.href = '?publish_now=1';
            }
        }

        // Tabs
        function showTab(tab) {
            document.getElementById('videos-tab').classList.add('hidden');
            document.getElementById('materials-tab').classList.add('hidden');
            
            document.getElementById('tab-videos-btn').classList.remove('border-blue-500', 'text-blue-600');
            document.getElementById('tab-materials-btn').classList.remove('border-blue-500', 'text-blue-600');
            
            if (tab === 'videos') {
                document.getElementById('videos-tab').classList.remove('hidden');
                document.getElementById('tab-videos-btn').classList.add('border-blue-500', 'text-blue-600');
            } else {
                document.getElementById('materials-tab').classList.remove('hidden');
                document.getElementById('tab-materials-btn').classList.add('border-blue-500', 'text-blue-600');
            }
        }

        // Upload Modal
        function openUploadModal(type) {
            document.getElementById('uploadModal').classList.remove('hidden');
            document.getElementById('uploadModal').classList.add('flex');
            
            if (type === 'video') {
                document.getElementById('modalTitle').textContent = 'Upload Video';
                document.getElementById('videoForm').classList.remove('hidden');
                document.getElementById('materialForm').classList.add('hidden');
            } else {
                document.getElementById('modalTitle').textContent = 'Upload Study Material';
                document.getElementById('materialForm').classList.remove('hidden');
                document.getElementById('videoForm').classList.add('hidden');
            }
        }

        function closeModal() {
            document.getElementById('uploadModal').classList.add('hidden');
            document.getElementById('uploadModal').classList.remove('flex');
            
            // Reset forms
            document.getElementById('videoForm').reset();
            document.getElementById('materialForm').reset();
            document.getElementById('scheduleField').style.display = 'none';
        }

        // Quick Menu
        function toggleQuickMenu(videoId) {
            const menu = document.getElementById(`quick-menu-${videoId}`);
            
            // Close any open menu
            if (activeMenu && activeMenu !== menu) {
                activeMenu.classList.remove('show');
            }
            
            menu.classList.toggle('show');
            activeMenu = menu.classList.contains('show') ? menu : null;
        }

        // Quick Status Change
        function quickStatusChange(videoId, status) {
            if (confirm(`Change video status to ${status}?`)) {
                window.location.href = `?quick_status=1&id=${videoId}&status=${status}`;
            }
        }

        // Schedule Modal
        function openScheduleModal(videoId) {
            document.getElementById('scheduleVideoId').value = videoId;
            document.getElementById('scheduleModal').classList.remove('hidden');
            document.getElementById('scheduleModal').classList.add('flex');
            
            // Set minimum date to now
            const now = new Date();
            now.setMinutes(now.getMinutes() - now.getTimezoneOffset());
            document.getElementById('scheduleDateTime').min = now.toISOString().slice(0,16);
            
            // Close quick menu if open
            const menu = document.getElementById(`quick-menu-${videoId}`);
            if (menu) menu.classList.remove('show');
        }

        function closeScheduleModal() {
            document.getElementById('scheduleModal').classList.add('hidden');
            document.getElementById('scheduleModal').classList.remove('flex');
        }

        function saveSchedule() {
            const videoId = document.getElementById('scheduleVideoId').value;
            const scheduledAt = document.getElementById('scheduleDateTime').value;
            
            if (!scheduledAt) {
                alert('Please select a date and time');
                return;
            }
            
            // Check if scheduled time is in the future
            const scheduledTime = new Date(scheduledAt).getTime();
            const now = new Date().getTime();
            
            if (scheduledTime <= now) {
                alert('Scheduled time must be in the future');
                return;
            }
            
            if (confirm('Schedule this video for ' + new Date(scheduledAt).toLocaleString() + '? It will automatically become visible to students at that time.')) {
                window.location.href = `?quick_status=1&id=${videoId}&status=scheduled&scheduled_at=${encodeURIComponent(scheduledAt)}`;
            }
        }

        // Edit Modal (redirect to full edit page)
        function openEditModal(videoId) {
            window.location.href = `edit_video.php?id=${videoId}`;
        }

        // Show/hide schedule field in upload form
        document.addEventListener('DOMContentLoaded', function() {
            const statusSelect = document.getElementById('videoStatus');
            if (statusSelect) {
                statusSelect.addEventListener('change', function() {
                    const scheduleField = document.getElementById('scheduleField');
                    scheduleField.style.display = this.value === 'scheduled' ? 'block' : 'none';
                });
            }

            // Check for any broken thumbnails on page load
            document.querySelectorAll('img[alt="Thumbnail"]').forEach(img => {
                img.addEventListener('error', function() {
                    console.log('Thumbnail failed to load:', this.src);
                });
            });
        });

        // Close menus when clicking outside
        document.addEventListener('click', function(event) {
            if (!event.target.closest('.quick-status-menu') && !event.target.closest('button[onclick^="toggleQuickMenu"]')) {
                if (activeMenu) {
                    activeMenu.classList.remove('show');
                    activeMenu = null;
                }
            }
        });

        // Close modals when clicking outside
        window.onclick = function(event) {
            const uploadModal = document.getElementById('uploadModal');
            const scheduleModal = document.getElementById('scheduleModal');
            
            if (event.target === uploadModal) {
                closeModal();
            }
            if (event.target === scheduleModal) {
                closeScheduleModal();
            }
        }
    </script>
</body>
</html>