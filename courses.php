<?php
require_once '../includes/auth.php';
require_once '../includes/auto_publish.php';
requireAdmin();

// Run auto-publisher on every page load
autoPublishVideos($pdo);

// Handle CRUD operations
$message = '';
$error = '';

// Delete course
if (isset($_GET['delete'])) {
    $id = $_GET['delete'];
    try {
        $stmt = $pdo->prepare("DELETE FROM courses WHERE id = ?");
        $stmt->execute([$id]);
        $message = 'Course deleted successfully';
    } catch (PDOException $e) {
        $error = 'Failed to delete course';
    }
}

// Add/Edit course
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        $title = trim($_POST['title']);
        $description = trim($_POST['description']);
        $instructor = trim($_POST['instructor']);
        $duration = trim($_POST['duration']);
        $status = $_POST['status'];
        
        if ($_POST['action'] == 'add') {
            $stmt = $pdo->prepare("INSERT INTO courses (title, description, instructor, duration, status) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$title, $description, $instructor, $duration, $status]);
            $message = 'Course added successfully';
        } elseif ($_POST['action'] == 'edit') {
            $id = $_POST['id'];
            $stmt = $pdo->prepare("UPDATE courses SET title = ?, description = ?, instructor = ?, duration = ?, status = ? WHERE id = ?");
            $stmt->execute([$title, $description, $instructor, $duration, $status, $id]);
            $message = 'Course updated successfully';
        }
    }
}

// Get all courses
$stmt = $pdo->query("SELECT * FROM courses ORDER BY created_at DESC");
$courses = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Course Management</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-gray-100">
    <div class="flex h-screen">
        <?php include 'sidebar.php'; ?>

        <div class="flex-1 overflow-auto">
            <div class="p-8">
                <div class="flex justify-between items-center mb-6">
                    <h1 class="text-3xl font-bold">Course Management</h1>
                    <button onclick="openAddModal()" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700">
                        <i class="fas fa-plus mr-2"></i>Add New Course
                    </button>
                </div>

                <?php if ($message): ?>
                    <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4"><?= $message ?></div>
                <?php endif; ?>
                <?php if ($error): ?>
                    <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4"><?= $error ?></div>
                <?php endif; ?>

                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                    <?php foreach ($courses as $course): ?>
                    <div class="bg-white rounded-lg shadow overflow-hidden">
                        <div class="p-6">
                            <div class="flex justify-between items-start">
                                <h3 class="text-xl font-bold mb-2"><?= htmlspecialchars($course['title']) ?></h3>
                                <span class="px-2 py-1 text-xs rounded <?= $course['status'] == 'active' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' ?>">
                                    <?= ucfirst($course['status']) ?>
                                </span>
                            </div>
                            <p class="text-gray-600 mb-4"><?= substr(htmlspecialchars($course['description']), 0, 100) ?>...</p>
                            <p class="text-sm text-gray-500"><i class="fas fa-user mr-2"></i><?= htmlspecialchars($course['instructor']) ?></p>
                            <p class="text-sm text-gray-500"><i class="fas fa-clock mr-2"></i><?= htmlspecialchars($course['duration']) ?></p>
                            
                            <div class="mt-4 flex space-x-3">
                                <button onclick="editCourse(<?= $course['id'] ?>, '<?= htmlspecialchars(addslashes($course['title'])) ?>', '<?= htmlspecialchars(addslashes($course['description'])) ?>', '<?= htmlspecialchars(addslashes($course['instructor'])) ?>', '<?= htmlspecialchars($course['duration']) ?>', '<?= $course['status'] ?>')" class="text-blue-600 hover:text-blue-900">
                                    <i class="fas fa-edit"></i> Edit
                                </button>
                                <a href="?delete=<?= $course['id'] ?>" onclick="return confirm('Are you sure?')" class="text-red-600 hover:text-red-900">
                                    <i class="fas fa-trash"></i> Delete
                                </a>
                                <a href="content.php?course_id=<?= $course['id'] ?>" class="text-green-600 hover:text-green-900">
                                    <i class="fas fa-video"></i> Content
                                </a>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Add/Edit Modal -->
    <div id="courseModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden items-center justify-center">
        <div class="bg-white rounded-lg p-8 max-w-md w-full">
            <h2 id="modalTitle" class="text-2xl font-bold mb-4">Add New Course</h2>
            <form method="POST" id="courseForm">
                <input type="hidden" name="action" id="action" value="add">
                <input type="hidden" name="id" id="courseId">
                
                <div class="mb-4">
                    <label class="block text-gray-700 mb-2">Title</label>
                    <input type="text" name="title" id="courseTitle" required class="w-full px-3 py-2 border rounded focus:outline-none focus:ring">
                </div>
                
                <div class="mb-4">
                    <label class="block text-gray-700 mb-2">Description</label>
                    <textarea name="description" id="courseDescription" rows="3" required class="w-full px-3 py-2 border rounded focus:outline-none focus:ring"></textarea>
                </div>
                
                <div class="mb-4">
                    <label class="block text-gray-700 mb-2">Instructor</label>
                    <input type="text" name="instructor" id="courseInstructor" required class="w-full px-3 py-2 border rounded focus:outline-none focus:ring">
                </div>
                
                <div class="mb-4">
                    <label class="block text-gray-700 mb-2">Duration (e.g., "10 hours")</label>
                    <input type="text" name="duration" id="courseDuration" required class="w-full px-3 py-2 border rounded focus:outline-none focus:ring">
                </div>
                
                <div class="mb-4">
                    <label class="block text-gray-700 mb-2">Status</label>
                    <select name="status" id="courseStatus" class="w-full px-3 py-2 border rounded focus:outline-none focus:ring">
                        <option value="active">Active</option>
                        <option value="inactive">Inactive</option>
                    </select>
                </div>
                
                <div class="flex justify-end space-x-3">
                    <button type="button" onclick="closeModal()" class="px-4 py-2 bg-gray-300 rounded hover:bg-gray-400">Cancel</button>
                    <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700">Save</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openAddModal() {
            document.getElementById('modalTitle').textContent = 'Add New Course';
            document.getElementById('action').value = 'add';
            document.getElementById('courseId').value = '';
            document.getElementById('courseTitle').value = '';
            document.getElementById('courseDescription').value = '';
            document.getElementById('courseInstructor').value = '';
            document.getElementById('courseDuration').value = '';
            document.getElementById('courseStatus').value = 'active';
            document.getElementById('courseModal').classList.remove('hidden');
            document.getElementById('courseModal').classList.add('flex');
        }

        function editCourse(id, title, description, instructor, duration, status) {
            document.getElementById('modalTitle').textContent = 'Edit Course';
            document.getElementById('action').value = 'edit';
            document.getElementById('courseId').value = id;
            document.getElementById('courseTitle').value = title;
            document.getElementById('courseDescription').value = description;
            document.getElementById('courseInstructor').value = instructor;
            document.getElementById('courseDuration').value = duration;
            document.getElementById('courseStatus').value = status;
            document.getElementById('courseModal').classList.remove('hidden');
            document.getElementById('courseModal').classList.add('flex');
        }

        function closeModal() {
            document.getElementById('courseModal').classList.add('hidden');
            document.getElementById('courseModal').classList.remove('flex');
        }
    </script>
</body>
</html>