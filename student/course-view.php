<?php
require_once '../includes/auth.php';
require_once '../includes/student-functions.php';
require_once '../includes/auto_publish.php';

requireStudent();
autoPublishVideos($pdo);

$course_id = $_GET['id'] ?? 0;
$user_id = $_SESSION['user_id'] ?? 0;

// ================== CHECK ENROLLMENT ==================
$stmt = $pdo->prepare("
    SELECT * FROM enrollments 
    WHERE student_id = ? AND course_id = ? AND status = 'active'
");
$stmt->execute([$user_id, $course_id]);

if (!$stmt->fetch()) {
    header('Location: courses.php');
    exit;
}

// ================== GET COURSE DATA ==================
$data = getCourseWithVideos($course_id, $user_id);

// 🔥 FIX: Always safe array
$course = $data['course'] ?? [];
$videos = (isset($data['videos']) && is_array($data['videos'])) ? $data['videos'] : [];

// ================== MATERIALS ==================
$stmt = $pdo->prepare("
    SELECT * FROM materials 
    WHERE course_id = ? 
    ORDER BY created_at DESC
");
$stmt->execute([$course_id]);
$materials = $stmt->fetchAll(PDO::FETCH_ASSOC) ?? [];

// ================== PROGRESS ==================
$progress = getCourseProgress($course_id, $user_id);
$progress = is_numeric($progress) ? $progress : 0;

// ================== SAFE COUNTS ==================
$totalVideos = count($videos);
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title><?= htmlspecialchars($course['title'] ?? 'Course View') ?></title>

<script src="https://cdn.tailwindcss.com"></script>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

<style>
.video-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
    gap: 1.5rem;
}

.video-card {
    transition: all 0.3s ease;
}

.video-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 10px 25px rgba(0,0,0,0.1);
}
</style>
</head>

<body class="bg-gray-50">

<div class="flex h-screen">

<?php include 'sidebar.php'; ?>

<div class="flex-1 overflow-auto">

<?php include 'header.php'; ?>

<div class="p-6">

<!-- ================= HEADER ================= -->
<div class="bg-gradient-to-r from-blue-600 to-indigo-700 rounded-2xl p-8 text-white mb-8">

<div class="flex justify-between items-start">

<div>
<h1 class="text-3xl font-bold mb-2">
<?= htmlspecialchars($course['title'] ?? 'Course Title') ?>
</h1>

<p class="text-blue-100 mb-4">
<?= htmlspecialchars($course['description'] ?? 'No description available') ?>
</p>

<div class="flex space-x-6 text-sm">

<span>
<i class="fas fa-user mr-2"></i>
<?= htmlspecialchars($course['instructor'] ?? 'Instructor') ?>
</span>

<span>
<i class="far fa-clock mr-2"></i>
<?= htmlspecialchars($course['duration'] ?? 'N/A') ?>
</span>

<span>
<i class="fas fa-video mr-2"></i>
<?= $totalVideos ?> Lessons
</span>

</div>
</div>

<!-- PROGRESS -->
<div class="text-center">
<div class="text-3xl font-bold"><?= round($progress) ?>%</div>
<p class="text-sm">Completed</p>
</div>

</div>

<!-- Progress Bar -->
<div class="mt-6 bg-white/20 rounded-full h-2">
<div class="bg-yellow-400 h-2 rounded-full" style="width: <?= $progress ?>%"></div>
</div>

</div>

<!-- ================= TABS ================= -->
<div class="border-b border-gray-200 mb-6">

<nav class="flex space-x-8">

<button onclick="showTab('content')" 
class="tab-btn border-b-2 border-blue-500 text-blue-600 px-2 py-3">
Content
</button>

<button onclick="showTab('materials')" 
class="tab-btn text-gray-500 px-2 py-3">
Materials
</button>

<button onclick="showTab('discussion')" 
class="tab-btn text-gray-500 px-2 py-3">
Discussion
</button>

</nav>

</div>

<!-- ================= CONTENT TAB ================= -->
<div id="content-tab">

<div class="video-grid">

<?php if (!empty($videos)): ?>
<?php $index = 0; ?>

<?php foreach ($videos as $video): ?>

<a href="video.php?id=<?= $video['id'] ?>&course=<?= $course_id ?>" 
class="video-card bg-white rounded-xl shadow-sm border overflow-hidden">

<!-- THUMBNAIL -->
<div class="h-40 bg-gray-200">

<?php if (!empty($video['thumbnail'])): ?>
<img src="../<?= htmlspecialchars($video['thumbnail']) ?>" 
class="w-full h-full object-cover">
<?php else: ?>
<div class="flex items-center justify-center h-full">
<i class="fas fa-play text-3xl text-gray-400"></i>
</div>
<?php endif; ?>

</div>

<!-- CONTENT -->
<div class="p-4">

<span class="text-xs text-blue-600">
Lesson <?= ++$index ?>
</span>

<h3 class="font-semibold text-gray-800">
<?= htmlspecialchars($video['title'] ?? 'Untitled') ?>
</h3>

<p class="text-sm text-gray-500 mt-1">
<?= htmlspecialchars($video['description'] ?? '') ?>
</p>

<div class="mt-3 text-xs text-gray-400">
<?= htmlspecialchars($video['duration'] ?? '10:00') ?>
</div>

</div>

</a>

<?php endforeach; ?>

<?php else: ?>

<div class="col-span-full text-center py-10">
<p class="text-gray-500">No videos available</p>
</div>

<?php endif; ?>

</div>

</div>

<!-- ================= MATERIALS TAB ================= -->
<div id="materials-tab" class="hidden">

<?php if (empty($materials)): ?>

<div class="text-center py-10 text-gray-500">
No materials available
</div>

<?php else: ?>

<?php foreach ($materials as $m): ?>

<div class="bg-white p-4 mb-3 rounded-lg shadow flex justify-between items-center">

<div>
<h4 class="font-medium">
<?= htmlspecialchars($m['title']) ?>
</h4>

<p class="text-sm text-gray-500">
<?= htmlspecialchars($m['description'] ?? '') ?>
</p>
</div>

<a href="../<?= htmlspecialchars($m['file_path']) ?>" 
download 
class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700">
Download
</a>

</div>

<?php endforeach; ?>

<?php endif; ?>

</div>

<!-- ================= DISCUSSION ================= -->
<div id="discussion-tab" class="hidden">

<div class="bg-white p-6 rounded-lg shadow text-center text-gray-500">
Discussion feature coming soon...
</div>

</div>

</div>
</div>
</div>

<!-- ================= JS ================= -->
<script>
function showTab(tab) {

document.getElementById('content-tab').classList.add('hidden');
document.getElementById('materials-tab').classList.add('hidden');
document.getElementById('discussion-tab').classList.add('hidden');

document.querySelectorAll('.tab-btn').forEach(btn => {
btn.classList.remove('border-blue-500','text-blue-600');
btn.classList.add('text-gray-500');
});

document.getElementById(tab + '-tab').classList.remove('hidden');

event.target.classList.add('border-blue-500','text-blue-600');
}
</script>

</body>
</html>
