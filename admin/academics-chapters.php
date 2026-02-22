<?php
require_once '../config/db.php';
require_once '../includes/auth.php';
requireAdmin();

$course_id = $_GET['course_id'] ?? 0;

// Get course details
$stmt = $pdo->prepare("SELECT * FROM academic_courses WHERE id = ?");
$stmt->execute([$course_id]);
$course = $stmt->fetch();

if (!$course) {
    header("Location: academics.php");
    exit;
}

// Handle chapter actions
$action = $_GET['action'] ?? 'list';
$chapter_id = $_GET['chapter_id'] ?? 0;

// Add/Edit chapter
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_chapter'])) {
    $title = trim($_POST['title']);
    $description = trim($_POST['description']);
    $chapter_number = $_POST['chapter_number'];
    $duration = $_POST['duration'];
    
    if ($chapter_id) {
        // Update
        $stmt = $pdo->prepare("UPDATE academic_chapters SET title=?, description=?, chapter_number=?, duration=? WHERE id=? AND course_id=?");
        $stmt->execute([$title, $description, $chapter_number, $duration, $chapter_id, $course_id]);
        $_SESSION['success'] = "Chapter updated successfully!";
    } else {
        // Insert
        $stmt = $pdo->prepare("INSERT INTO academic_chapters (course_id, title, description, chapter_number, duration) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$course_id, $title, $description, $chapter_number, $duration]);
        $_SESSION['success'] = "Chapter added successfully!";
    }
    header("Location: academics-chapters.php?course_id=$course_id");
    exit;
}

// Delete chapter
if (isset($_GET['delete_chapter'])) {
    $id = $_GET['delete_chapter'];
    $stmt = $pdo->prepare("DELETE FROM academic_chapters WHERE id = ? AND course_id = ?");
    $stmt->execute([$id, $course_id]);
    $_SESSION['success'] = "Chapter deleted successfully!";
    header("Location: academics-chapters.php?course_id=$course_id");
    exit;
}

// Get chapters
$stmt = $pdo->prepare("SELECT * FROM academic_chapters WHERE course_id = ? ORDER BY chapter_number");
$stmt->execute([$course_id]);
$chapters = $stmt->fetchAll();

// Get chapter for editing
$edit_chapter = null;
if ($action == 'edit' && $chapter_id) {
    $stmt = $pdo->prepare("SELECT * FROM academic_chapters WHERE id = ? AND course_id = ?");
    $stmt->execute([$chapter_id, $course_id]);
    $edit_chapter = $stmt->fetch();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Manage Chapters - <?= htmlspecialchars($course['title']) ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-gray-50">

<div class="flex min-h-screen">
    <?php include 'sidebar.php'; ?>

    <div class="flex-1 flex flex-col lg:ml-64">
        <div class="bg-white shadow-sm px-6 py-4">
            <h1 class="text-xl font-bold text-gray-800">
                <a href="academics.php" class="text-blue-600 hover:text-blue-800">
                    <i class="fas fa-arrow-left mr-2"></i>
                </a>
                Chapters: <?= htmlspecialchars($course['title']) ?>
            </h1>
        </div>

        <div class="p-6">
            <?php if (isset($_SESSION['success'])): ?>
                <div class="bg-green-100 border border-green-200 text-green-600 px-4 py-3 rounded-lg mb-4">
                    <?= $_SESSION['success'] ?>
                </div>
                <?php unset($_SESSION['success']); ?>
            <?php endif; ?>

            <!-- Add Chapter Button -->
            <div class="mb-6">
                <a href="?course_id=<?= $course_id ?>&action=add" 
                   class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 transition">
                    <i class="fas fa-plus mr-2"></i>Add New Chapter
                </a>
            </div>

            <?php if ($action == 'add' || $action == 'edit'): ?>
                <!-- Chapter Form -->
                <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 mb-6">
                    <h2 class="text-lg font-bold mb-4"><?= $action == 'add' ? 'Add New' : 'Edit' ?> Chapter</h2>
                    <form method="POST">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div class="md:col-span-2">
                                <label class="block text-sm font-medium text-gray-700 mb-2">Chapter Title</label>
                                <input type="text" name="title" value="<?= htmlspecialchars($edit_chapter['title'] ?? '') ?>" required
                                       class="w-full px-4 py-2 border border-gray-200 rounded-lg focus:ring-2 focus:ring-blue-500">
                            </div>
                            <div class="md:col-span-2">
                                <label class="block text-sm font-medium text-gray-700 mb-2">Description</label>
                                <textarea name="description" rows="3" 
                                          class="w-full px-4 py-2 border border-gray-200 rounded-lg focus:ring-2 focus:ring-blue-500"><?= htmlspecialchars($edit_chapter['description'] ?? '') ?></textarea>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Chapter Number</label>
                                <input type="number" name="chapter_number" value="<?= $edit_chapter['chapter_number'] ?? count($chapters)+1 ?>" required
                                       class="w-full px-4 py-2 border border-gray-200 rounded-lg focus:ring-2 focus:ring-blue-500">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Duration</label>
                                <input type="text" name="duration" value="<?= htmlspecialchars($edit_chapter['duration'] ?? '') ?>" 
                                       placeholder="e.g., 2 hours"
                                       class="w-full px-4 py-2 border border-gray-200 rounded-lg focus:ring-2 focus:ring-blue-500">
                            </div>
                        </div>
                        <div class="flex justify-end space-x-3 mt-4">
                            <a href="?course_id=<?= $course_id ?>" class="px-4 py-2 border border-gray-300 rounded-lg">Cancel</a>
                            <button type="submit" name="save_chapter" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">
                                Save Chapter
                            </button>
                        </div>
                    </form>
                </div>
            <?php endif; ?>

            <!-- Chapters List -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
                <div class="p-4 border-b border-gray-100">
                    <h2 class="font-semibold">Chapters (<?= count($chapters) ?>)</h2>
                </div>
                
                <?php if (empty($chapters)): ?>
                    <div class="p-8 text-center text-gray-500">
                        No chapters added yet
                    </div>
                <?php else: ?>
                    <div class="divide-y divide-gray-100">
                        <?php foreach ($chapters as $index => $chapter): ?>
                            <div class="p-4 hover:bg-gray-50 transition">
                                <div class="flex items-start justify-between">
                                    <div class="flex-1">
                                        <div class="flex items-center space-x-3">
                                            <span class="w-6 h-6 bg-blue-100 text-blue-600 rounded-full flex items-center justify-center text-sm font-medium">
                                                <?= $chapter['chapter_number'] ?>
                                            </span>
                                            <h3 class="font-medium text-gray-800"><?= htmlspecialchars($chapter['title']) ?></h3>
                                            <?php if ($chapter['duration']): ?>
                                                <span class="text-xs text-gray-500">
                                                    <i class="far fa-clock"></i> <?= $chapter['duration'] ?>
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                        <?php if ($chapter['description']): ?>
                                            <p class="text-sm text-gray-500 mt-2 ml-9"><?= htmlspecialchars($chapter['description']) ?></p>
                                        <?php endif; ?>
                                    </div>
                                    <div class="flex space-x-2 ml-4">
                                        <a href="?course_id=<?= $course_id ?>&action=edit&chapter_id=<?= $chapter['id'] ?>" 
                                           class="text-gray-400 hover:text-blue-600">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <a href="?course_id=<?= $course_id ?>&delete_chapter=<?= $chapter['id'] ?>" 
                                           onclick="return confirm('Delete this chapter?')"
                                           class="text-gray-400 hover:text-red-600">
                                            <i class="fas fa-trash"></i>
                                        </a>
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

</body>
</html>