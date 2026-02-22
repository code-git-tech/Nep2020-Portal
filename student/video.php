<?php
require_once '../includes/auth.php';
require_once '../includes/student-functions.php';
require_once '../includes/auto_publish.php';
requireStudent();

// Run auto-publisher on every page load
autoPublishVideos($pdo);

$video_id = $_GET['id'] ?? 0;
$course_id = $_GET['course'] ?? 0;
$user_id = $_SESSION['user_id'];

// Get video details with access check
$stmt = $pdo->prepare("
    SELECT v.*, c.title as course_title, c.id as course_id
    FROM videos v
    JOIN courses c ON v.course_id = c.id
    WHERE v.id = ? AND v.status = 'published'
");
$stmt->execute([$video_id]);
$video = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$video) {
    header('Location: courses.php');
    exit;
}

// Check if user is enrolled
$stmt = $pdo->prepare("SELECT * FROM enrollments WHERE student_id = ? AND course_id = ? AND status = 'active'");
$stmt->execute([$user_id, $video['course_id']]);
$is_enrolled = $stmt->fetch();

if ($video['access'] == 'enrolled_only' && !$is_enrolled) {
    header('Location: course-view.php?id=' . $video['course_id']);
    exit;
}

// Get user's progress for this video
$stmt = $pdo->prepare("SELECT watched_seconds, completed FROM video_progress WHERE student_id = ? AND video_id = ?");
$stmt->execute([$user_id, $video_id]);
$progress = $stmt->fetch(PDO::FETCH_ASSOC);
$last_position = $progress ? $progress['watched_seconds'] : 0;
$is_completed = $progress ? $progress['completed'] : false;

// Get all videos in course for navigation
$stmt = $pdo->prepare("
    SELECT id, title, duration, order_num 
    FROM videos 
    WHERE course_id = ? AND status = 'published'
    ORDER BY order_num
");
$stmt->execute([$video['course_id']]);
$course_videos = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Find next and previous videos
$current_index = array_search($video_id, array_column($course_videos, 'id'));
$prev_video = $current_index > 0 ? $course_videos[$current_index - 1] : null;
$next_video = $current_index < count($course_videos) - 1 ? $course_videos[$current_index + 1] : null;

// Get course tests
$stmt = $pdo->prepare("
    SELECT t.*, 
           (SELECT COUNT(*) FROM test_attempts WHERE test_id = t.id AND student_id = ? AND passed = 1) as has_passed
    FROM tests t
    WHERE t.course_id = ?
");
$stmt->execute([$user_id, $video['course_id']]);
$course_tests = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Handle AJAX progress update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] == 'update_progress') {
        $watched_seconds = intval($_POST['watched_seconds']);
        $video_duration = convertDurationToSeconds($video['duration']);
        $completed = $watched_seconds >= ($video_duration * 0.9); // Mark as completed if watched 90%
        
        $stmt = $pdo->prepare("
            INSERT INTO video_progress (student_id, video_id, watched_seconds, completed, last_watched)
            VALUES (?, ?, ?, ?, NOW())
            ON DUPLICATE KEY UPDATE
            watched_seconds = VALUES(watched_seconds),
            completed = VALUES(completed),
            last_watched = NOW()
        ");
        $stmt->execute([$user_id, $video_id, $watched_seconds, $completed ? 1 : 0]);
        
        echo json_encode(['success' => true, 'completed' => $completed]);
        exit;
    }
}

// Helper function to convert duration string to seconds
function convertDurationToSeconds($duration) {
    if (empty($duration)) return 600; // Default 10 minutes
    $parts = explode(':', $duration);
    if (count($parts) == 2) {
        return intval($parts[0]) * 60 + intval($parts[1]);
    } elseif (count($parts) == 3) {
        return intval($parts[0]) * 3600 + intval($parts[1]) * 60 + intval($parts[2]);
    }
    return 600;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($video['title']) ?> - <?= htmlspecialchars($video['course_title']) ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .video-container {
            position: relative;
            width: 100%;
            background: #000;
        }
        .video-player {
            width: 100%;
            max-height: 70vh;
            background: #000;
        }
        .playback-speed-menu {
            position: absolute;
            bottom: 70px;
            right: 20px;
            background: rgba(0,0,0,0.9);
            border-radius: 8px;
            padding: 8px 0;
            min-width: 120px;
            display: none;
            z-index: 50;
        }
        .playback-speed-menu.show {
            display: block;
        }
        .playback-speed-item {
            padding: 8px 16px;
            color: white;
            cursor: pointer;
            transition: background 0.2s;
        }
        .playback-speed-item:hover {
            background: rgba(255,255,255,0.2);
        }
        .playback-speed-item.active {
            background: #3b82f6;
        }
        .video-progress-bar {
            position: absolute;
            bottom: 0;
            left: 0;
            height: 3px;
            background: #3b82f6;
            transition: width 0.3s;
        }
        .chapter-item {
            transition: all 0.3s ease;
        }
        .chapter-item:hover {
            transform: translateX(5px);
        }
        .chapter-item.completed {
            border-left-color: #10b981;
        }
    </style>
</head>
<body class="bg-gray-900">
    <div class="flex h-screen">
        <?php include 'sidebar.php'; ?>

        <div class="flex-1 overflow-auto bg-gray-900">
            <!-- Video Player Section -->
            <div class="video-container">
                <video id="videoPlayer" class="video-player" controls controlsList="nodownload" 
                       data-video-id="<?= $video_id ?>" data-course-id="<?= $course_id ?>">
                    <source src="../<?= htmlspecialchars($video['video_url']) ?>" type="video/mp4">
                    Your browser does not support the video tag.
                </video>
                
                <!-- Custom Controls -->
                <div class="absolute bottom-4 right-4 flex space-x-2">
                    <!-- Playback Speed Button -->
                    <div class="relative">
                        <button onclick="toggleSpeedMenu()" class="bg-black bg-opacity-70 text-white px-3 py-2 rounded-lg hover:bg-opacity-90 transition">
                            <i class="fas fa-tachometer-alt mr-2"></i>
                            <span id="speedDisplay">1x</span>
                        </button>
                        
                        <!-- Speed Menu -->
                        <div id="speedMenu" class="playback-speed-menu">
                            <div class="playback-speed-item" data-speed="0.5">0.5x</div>
                            <div class="playback-speed-item active" data-speed="1">1x</div>
                            <div class="playback-speed-item" data-speed="1.5">1.5x</div>
                            <div class="playback-speed-item" data-speed="2">2x</div>
                        </div>
                    </div>
                </div>
                
                <!-- Progress Bar -->
                <div class="absolute bottom-0 left-0 w-full h-1 bg-gray-700">
                    <div id="progressBar" class="video-progress-bar" style="width: 0%"></div>
                </div>
            </div>

            <!-- Content Area -->
            <div class="flex">
                <!-- Main Content -->
                <div class="flex-1 p-6">
                    <!-- Video Info -->
                    <div class="mb-6">
                        <div class="flex items-center justify-between">
                            <h1 class="text-2xl font-bold text-white"><?= htmlspecialchars($video['title']) ?></h1>
                            <?php if ($is_completed): ?>
                                <span class="px-3 py-1 bg-green-600 text-white text-sm rounded-full">
                                    <i class="fas fa-check-circle mr-2"></i>Completed
                                </span>
                            <?php endif; ?>
                        </div>
                        <p class="text-gray-400 mt-2"><?= htmlspecialchars($video['course_title']) ?></p>
                        
                        <!-- Video Stats -->
                        <div class="flex items-center space-x-4 mt-4 text-sm text-gray-400">
                            <span><i class="far fa-clock mr-2"></i><?= htmlspecialchars($video['duration'] ?? '10:00') ?></span>
                            <span><i class="fas fa-eye mr-2"></i><?= number_format($video['views'] ?? 0) ?> views</span>
                            <span><i class="fas fa-calendar mr-2"></i>Added <?= date('M d, Y', strtotime($video['created_at'])) ?></span>
                        </div>
                    </div>

                    <!-- Video Description -->
                    <div class="bg-gray-800 rounded-lg p-6 mb-6">
                        <h3 class="text-lg font-semibold text-white mb-3">Description</h3>
                        <p class="text-gray-300 whitespace-pre-line"><?= nl2br(htmlspecialchars($video['description'] ?? 'No description available.')) ?></p>
                    </div>

                    <!-- Course Tests Section -->
                    <?php if (!empty($course_tests)): ?>
                    <div class="bg-gray-800 rounded-lg p-6 mb-6">
                        <h3 class="text-lg font-semibold text-white mb-4">Course Tests</h3>
                        <div class="space-y-3">
                            <?php foreach ($course_tests as $test): ?>
                            <div class="flex items-center justify-between p-4 bg-gray-700 rounded-lg">
                                <div>
                                    <h4 class="font-medium text-white"><?= htmlspecialchars($test['title']) ?></h4>
                                    <p class="text-sm text-gray-400 mt-1">
                                        <i class="far fa-clock mr-2"></i><?= $test['duration'] ?> minutes
                                        <span class="mx-2">•</span>
                                        <i class="fas fa-star mr-2"></i>Pass: <?= $test['passing_marks'] ?>/<?= $test['total_marks'] ?>
                                    </p>
                                </div>
                                <?php if ($test['has_passed'] > 0): ?>
                                    <span class="px-4 py-2 bg-green-600 text-white rounded-lg">
                                        <i class="fas fa-check mr-2"></i>Passed
                                    </span>
                                <?php else: ?>
                                    <a href="take-test.php?id=<?= $test['id'] ?>" 
                                       class="px-4 py-2 bg-purple-600 text-white rounded-lg hover:bg-purple-700 transition">
                                        Take Test
                                    </a>
                                <?php endif; ?>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- Navigation -->
                    <div class="flex justify-between items-center">
                        <?php if ($prev_video): ?>
                            <a href="video.php?id=<?= $prev_video['id'] ?>&course=<?= $course_id ?>" 
                               class="px-4 py-2 bg-gray-700 text-white rounded-lg hover:bg-gray-600 transition">
                                <i class="fas fa-arrow-left mr-2"></i>Previous: <?= htmlspecialchars($prev_video['title']) ?>
                            </a>
                        <?php else: ?>
                            <div></div>
                        <?php endif; ?>
                        
                        <a href="course-view.php?id=<?= $video['course_id'] ?>" 
                           class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition">
                            <i class="fas fa-th-large mr-2"></i>Course Overview
                        </a>
                        
                        <?php if ($next_video): ?>
                            <a href="video.php?id=<?= $next_video['id'] ?>&course=<?= $course_id ?>" 
                               class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition">
                                Next: <?= htmlspecialchars($next_video['title']) ?><i class="fas fa-arrow-right ml-2"></i>
                            </a>
                        <?php else: ?>
                            <div></div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Sidebar - Course Chapters -->
                <div class="w-80 bg-gray-800 border-l border-gray-700 p-4 overflow-auto">
                    <h3 class="font-semibold text-white mb-4">Course Content</h3>
                    <div class="space-y-2">
                        <?php foreach ($course_videos as $index => $v): 
                            $is_current = $v['id'] == $video_id;
                        ?>
                        <a href="video.php?id=<?= $v['id'] ?>&course=<?= $video['course_id'] ?>" 
                           class="chapter-item block p-3 rounded-lg <?= $is_current ? 'bg-blue-600 bg-opacity-20 border-l-4 border-blue-600' : 'hover:bg-gray-700' ?>">
                            <div class="flex items-start">
                                <span class="text-sm font-medium text-gray-400 mr-3"><?= $index + 1 ?></span>
                                <div class="flex-1">
                                    <p class="text-sm font-medium <?= $is_current ? 'text-blue-400' : 'text-white' ?>">
                                        <?= htmlspecialchars($v['title']) ?>
                                    </p>
                                    <p class="text-xs text-gray-500 mt-1">
                                        <i class="far fa-clock mr-1"></i><?= htmlspecialchars($v['duration'] ?? '10:00') ?>
                                    </p>
                                </div>
                            </div>
                        </a>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        const video = document.getElementById('videoPlayer');
        const speedMenu = document.getElementById('speedMenu');
        const speedDisplay = document.getElementById('speedDisplay');
        const progressBar = document.getElementById('progressBar');
        let progressUpdateInterval;
        let lastUpdateTime = 0;

        // Set last watched position
        video.addEventListener('loadedmetadata', function() {
            <?php if ($last_position > 0): ?>
            video.currentTime = <?= $last_position ?>;
            <?php endif; ?>
        });

        // Playback tracking
        video.addEventListener('play', function() {
            progressUpdateInterval = setInterval(updateProgress, 5000); // Update every 5 seconds
        });

        video.addEventListener('pause', function() {
            clearInterval(progressUpdateInterval);
            updateProgress(); // Final update on pause
        });

        video.addEventListener('ended', function() {
            clearInterval(progressUpdateInterval);
            updateProgress(true); // Mark as completed
        });

        video.addEventListener('timeupdate', function() {
            // Update progress bar
            const progress = (video.currentTime / video.duration) * 100;
            progressBar.style.width = progress + '%';
        });

        function updateProgress(completed = false) {
            const watchedSeconds = Math.floor(video.currentTime);
            
            // Only update if enough time has passed or it's the final update
            if (completed || Math.abs(watchedSeconds - lastUpdateTime) >= 5) {
                lastUpdateTime = watchedSeconds;
                
                fetch(window.location.href, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: 'action=update_progress&watched_seconds=' + watchedSeconds
                })
                .then(response => response.json())
                .then(data => {
                    if (data.completed) {
                        // Show completion message
                        showNotification('Video completed!', 'success');
                    }
                });
            }
        }

        // Playback speed control
        function toggleSpeedMenu() {
            speedMenu.classList.toggle('show');
        }

        document.querySelectorAll('.playback-speed-item').forEach(item => {
            item.addEventListener('click', function() {
                const speed = parseFloat(this.dataset.speed);
                video.playbackRate = speed;
                speedDisplay.textContent = speed + 'x';
                
                // Update active state
                document.querySelectorAll('.playback-speed-item').forEach(i => i.classList.remove('active'));
                this.classList.add('active');
                
                // Hide menu
                speedMenu.classList.remove('show');
            });
        });

        // Keyboard shortcuts
        document.addEventListener('keydown', function(e) {
            if (e.target.tagName === 'INPUT' || e.target.tagName === 'TEXTAREA') return;
            
            switch(e.key) {
                case ' ':
                    e.preventDefault();
                    if (video.paused) video.play();
                    else video.pause();
                    break;
                case 'ArrowLeft':
                    video.currentTime -= 10;
                    break;
                case 'ArrowRight':
                    video.currentTime += 10;
                    break;
                case 'f':
                case 'F':
                    if (document.fullscreenElement) {
                        document.exitFullscreen();
                    } else {
                        video.requestFullscreen();
                    }
                    break;
            }
        });

        // Close speed menu when clicking outside
        document.addEventListener('click', function(event) {
            if (!event.target.closest('.relative')) {
                speedMenu.classList.remove('show');
            }
        });

        // Notification function
        function showNotification(message, type) {
            const notification = document.createElement('div');
            notification.className = `fixed top-4 right-4 px-4 py-2 rounded-lg text-white ${type === 'success' ? 'bg-green-600' : 'bg-blue-600'} z-50 animate-fade-in`;
            notification.innerHTML = `<i class="fas fa-${type === 'success' ? 'check-circle' : 'info-circle'} mr-2"></i>${message}`;
            document.body.appendChild(notification);
            
            setTimeout(() => {
                notification.remove();
            }, 3000);
        }

        // Save progress when leaving page
        window.addEventListener('beforeunload', function() {
            updateProgress();
        });
    </script>
</body>
</html>