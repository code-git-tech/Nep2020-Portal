<?php
require_once '../includes/auth.php';
require_once '../includes/auto_publish.php';
requireAdmin();

// Run auto-publisher on every page load
autoPublishVideos($pdo);

// Handle Add/Edit/Delete operations
$message = '';
$error = '';

// Delete student
if (isset($_GET['delete'])) {
    $id = $_GET['delete'];
    try {
        $stmt = $pdo->prepare("DELETE FROM users WHERE id = ? AND role = 'student'");
        $stmt->execute([$id]);
        $message = 'Student deleted successfully';
    } catch (PDOException $e) {
        $error = 'Failed to delete student';
    }
}

// Add/Edit student
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        $name = trim($_POST['name']);
        $email = trim($_POST['email']);
        
        if ($_POST['action'] == 'add') {
            $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
            try {
                $stmt = $pdo->prepare("INSERT INTO users (name, email, password, role) VALUES (?, ?, ?, 'student')");
                $stmt->execute([$name, $email, $password]);
                
                // Add to General group
                $userId = $pdo->lastInsertId();
                addUserToGeneralGroup($userId);
                
                $message = 'Student added successfully';
            } catch (PDOException $e) {
                $error = 'Email already exists';
            }
        } elseif ($_POST['action'] == 'edit') {
            $id = $_POST['id'];
            if (!empty($_POST['password'])) {
                $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("UPDATE users SET name = ?, email = ?, password = ? WHERE id = ? AND role = 'student'");
                $stmt->execute([$name, $email, $password, $id]);
            } else {
                $stmt = $pdo->prepare("UPDATE users SET name = ?, email = ? WHERE id = ? AND role = 'student'");
                $stmt->execute([$name, $email, $id]);
            }
            $message = 'Student updated successfully';
        }
    }
}

// Get all students
$stmt = $pdo->query("SELECT id, name, email, created_at FROM users WHERE role = 'student' ORDER BY created_at DESC");
$students = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Management</title>
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
                    <h1 class="text-3xl font-bold">Student Management</h1>
                    <button onclick="openAddModal()" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700">
                        <i class="fas fa-plus mr-2"></i>Add New Student
                    </button>
                </div>

                <?php if ($message): ?>
                    <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4"><?= $message ?></div>
                <?php endif; ?>
                <?php if ($error): ?>
                    <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4"><?= $error ?></div>
                <?php endif; ?>

                <div class="bg-white rounded-lg shadow overflow-hidden">
                    <table class="min-w-full">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">ID</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Name</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Email</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Joined</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            <?php foreach ($students as $student): ?>
                            <tr>
                                <td class="px-6 py-4"><?= $student['id'] ?></td>
                                <td class="px-6 py-4"><?= htmlspecialchars($student['name']) ?></td>
                                <td class="px-6 py-4"><?= htmlspecialchars($student['email']) ?></td>
                                <td class="px-6 py-4"><?= date('M d, Y', strtotime($student['created_at'])) ?></td>
                                <td class="px-6 py-4">
                                    <button onclick="editStudent(<?= $student['id'] ?>, '<?= htmlspecialchars($student['name']) ?>', '<?= htmlspecialchars($student['email']) ?>')" class="text-blue-600 hover:text-blue-900 mr-3">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <a href="?delete=<?= $student['id'] ?>" onclick="return confirm('Are you sure?')" class="text-red-600 hover:text-red-900">
                                        <i class="fas fa-trash"></i>
                                    </a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Add/Edit Modal -->
    <div id="studentModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden items-center justify-center">
        <div class="bg-white rounded-lg p-8 max-w-md w-full">
            <h2 id="modalTitle" class="text-2xl font-bold mb-4">Add New Student</h2>
            <form method="POST" id="studentForm">
                <input type="hidden" name="action" id="action" value="add">
                <input type="hidden" name="id" id="studentId">
                
                <div class="mb-4">
                    <label class="block text-gray-700 mb-2">Name</label>
                    <input type="text" name="name" id="studentName" required class="w-full px-3 py-2 border rounded focus:outline-none focus:ring">
                </div>
                
                <div class="mb-4">
                    <label class="block text-gray-700 mb-2">Email</label>
                    <input type="email" name="email" id="studentEmail" required class="w-full px-3 py-2 border rounded focus:outline-none focus:ring">
                </div>
                
                <div class="mb-4">
                    <label class="block text-gray-700 mb-2">Password</label>
                    <input type="password" name="password" id="studentPassword" class="w-full px-3 py-2 border rounded focus:outline-none focus:ring">
                    <p class="text-xs text-gray-500 mt-1">Leave blank to keep current password when editing</p>
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
            document.getElementById('modalTitle').textContent = 'Add New Student';
            document.getElementById('action').value = 'add';
            document.getElementById('studentId').value = '';
            document.getElementById('studentName').value = '';
            document.getElementById('studentEmail').value = '';
            document.getElementById('studentPassword').value = '';
            document.getElementById('studentPassword').required = true;
            document.getElementById('studentModal').classList.remove('hidden');
            document.getElementById('studentModal').classList.add('flex');
        }

        function editStudent(id, name, email) {
            document.getElementById('modalTitle').textContent = 'Edit Student';
            document.getElementById('action').value = 'edit';
            document.getElementById('studentId').value = id;
            document.getElementById('studentName').value = name;
            document.getElementById('studentEmail').value = email;
            document.getElementById('studentPassword').value = '';
            document.getElementById('studentPassword').required = false;
            document.getElementById('studentModal').classList.remove('hidden');
            document.getElementById('studentModal').classList.add('flex');
        }

        function closeModal() {
            document.getElementById('studentModal').classList.add('hidden');
            document.getElementById('studentModal').classList.remove('flex');
        }
    </script>
</body>
</html>