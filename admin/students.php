<?php
require_once '../includes/auth.php';
require_once '../includes/auto_publish.php';
requireAdmin();

// Run auto-publisher on every page load
autoPublishVideos($pdo);

// Handle Add/Edit/Delete operations
$message = '';
$error = '';

// Get all schools for filtering
$schools = $pdo->query("SELECT id, name FROM schools ORDER BY name")->fetchAll();

// Get all classes
$classes = ['6th', '7th', '8th', '9th', '10th', '11th', '12th'];

// Delete student
if (isset($_GET['delete'])) {
    $id = $_GET['delete'];
    try {
        // Start transaction
        $pdo->beginTransaction();
        
        // Delete student progress
        $pdo->prepare("DELETE FROM video_progress WHERE student_id = ?")->execute([$id]);
        $pdo->prepare("DELETE FROM academic_progress WHERE student_id = ?")->execute([$id]);
        $pdo->prepare("DELETE FROM test_attempts WHERE student_id = ?")->execute([$id]);
        $pdo->prepare("DELETE FROM enrollments WHERE student_id = ?")->execute([$id]);
        $pdo->prepare("DELETE FROM certificates WHERE student_id = ?")->execute([$id]);
        
        // Delete student
        $stmt = $pdo->prepare("DELETE FROM users WHERE id = ? AND role = 'student'");
        $stmt->execute([$id]);
        
        $pdo->commit();
        $message = 'Student deleted successfully';
    } catch (PDOException $e) {
        $pdo->rollBack();
        $error = 'Failed to delete student';
    }
}

// Add/Edit student
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        $name = trim($_POST['name']);
        $email = trim($_POST['email']);
        $phone = trim($_POST['phone'] ?? '');
        $address = trim($_POST['address'] ?? '');
        $city = trim($_POST['city'] ?? '');
        $state = trim($_POST['state'] ?? '');
        $pincode = trim($_POST['pincode'] ?? '');
        $school_id = !empty($_POST['school_id']) ? $_POST['school_id'] : null;
        $class = trim($_POST['class'] ?? '');
        $roll_number = trim($_POST['roll_number'] ?? '');
        $father_name = trim($_POST['father_name'] ?? '');
        $mother_name = trim($_POST['mother_name'] ?? '');
        $dob = !empty($_POST['dob']) ? $_POST['dob'] : null;
        $gender = trim($_POST['gender'] ?? '');
        $blood_group = trim($_POST['blood_group'] ?? '');
        
        if ($_POST['action'] == 'add') {
            $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
            try {
                $pdo->beginTransaction();
                
                $stmt = $pdo->prepare("
                    INSERT INTO users (
                        name, email, password, role, phone, address, city, state, pincode,
                        school_id, class, roll_number, father_name, mother_name, dob, gender, blood_group,
                        email_verified, status
                    ) VALUES (
                        ?, ?, ?, 'student', ?, ?, ?, ?, ?,
                        ?, ?, ?, ?, ?, ?, ?, ?,
                        1, 'active'
                    )
                ");
                $stmt->execute([
                    $name, $email, $password, $phone, $address, $city, $state, $pincode,
                    $school_id, $class, $roll_number, $father_name, $mother_name, $dob, $gender, $blood_group
                ]);
                
                // Add to General group
                $userId = $pdo->lastInsertId();
                if (function_exists('addUserToGeneralGroup')) {
                    addUserToGeneralGroup($userId);
                }
                
                // Initialize XP and streak
                $pdo->prepare("INSERT INTO student_xp (student_id, xp_points, level) VALUES (?, 0, 1)")->execute([$userId]);
                $pdo->prepare("INSERT INTO student_streaks (student_id, current_streak, longest_streak) VALUES (?, 0, 0)")->execute([$userId]);
                
                $pdo->commit();
                $message = 'Student added successfully';
            } catch (PDOException $e) {
                $pdo->rollBack();
                $error = 'Email already exists or invalid data';
            }
        } elseif ($_POST['action'] == 'edit') {
            $id = $_POST['id'];
            try {
                $pdo->beginTransaction();
                
                if (!empty($_POST['password'])) {
                    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
                    $stmt = $pdo->prepare("
                        UPDATE users SET 
                            name = ?, email = ?, password = ?,
                            phone = ?, address = ?, city = ?, state = ?, pincode = ?,
                            school_id = ?, class = ?, roll_number = ?,
                            father_name = ?, mother_name = ?, dob = ?, gender = ?, blood_group = ?
                        WHERE id = ? AND role = 'student'
                    ");
                    $stmt->execute([
                        $name, $email, $password,
                        $phone, $address, $city, $state, $pincode,
                        $school_id, $class, $roll_number,
                        $father_name, $mother_name, $dob, $gender, $blood_group,
                        $id
                    ]);
                } else {
                    $stmt = $pdo->prepare("
                        UPDATE users SET 
                            name = ?, email = ?,
                            phone = ?, address = ?, city = ?, state = ?, pincode = ?,
                            school_id = ?, class = ?, roll_number = ?,
                            father_name = ?, mother_name = ?, dob = ?, gender = ?, blood_group = ?
                        WHERE id = ? AND role = 'student'
                    ");
                    $stmt->execute([
                        $name, $email,
                        $phone, $address, $city, $state, $pincode,
                        $school_id, $class, $roll_number,
                        $father_name, $mother_name, $dob, $gender, $blood_group,
                        $id
                    ]);
                }
                
                $pdo->commit();
                $message = 'Student updated successfully';
            } catch (PDOException $e) {
                $pdo->rollBack();
                $error = 'Failed to update student';
            }
        }
    }
}

// Get filter parameters
$search = $_GET['search'] ?? '';
$school_filter = $_GET['school'] ?? '';
$class_filter = $_GET['class'] ?? '';
$status_filter = $_GET['status'] ?? '';

// Get all students with additional info
$sql = "SELECT u.*, s.name as school_name,
        (SELECT COUNT(*) FROM enrollments WHERE student_id = u.id AND status = 'active') as enrolled_courses,
        (SELECT COUNT(*) FROM certificates WHERE student_id = u.id) as certificates_count,
        COALESCE(sx.xp_points, 0) as xp_points,
        COALESCE(sx.level, 1) as level,
        COALESCE(ss.current_streak, 0) as streak
        FROM users u
        LEFT JOIN schools s ON u.school_id = s.id
        LEFT JOIN student_xp sx ON u.id = sx.student_id
        LEFT JOIN student_streaks ss ON u.id = ss.student_id
        WHERE u.role = 'student'";
$params = [];

if ($search) {
    $sql .= " AND (u.name LIKE ? OR u.email LIKE ? OR u.roll_number LIKE ? OR u.phone LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}
if ($school_filter) {
    $sql .= " AND u.school_id = ?";
    $params[] = $school_filter;
}
if ($class_filter) {
    $sql .= " AND u.class = ?";
    $params[] = $class_filter;
}
if ($status_filter) {
    $sql .= " AND u.status = ?";
    $params[] = $status_filter;
}

$sql .= " ORDER BY u.created_at DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$students = $stmt->fetchAll();

// Get statistics
$stats = [
    'total' => $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'student'")->fetchColumn(),
    'active' => $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'student' AND status = 'active'")->fetchColumn(),
    'new_today' => $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'student' AND DATE(created_at) = CURDATE()")->fetchColumn(),
    'total_xp' => $pdo->query("SELECT SUM(xp_points) FROM student_xp")->fetchColumn() ?: 0,
];
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
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * { font-family: 'Inter', sans-serif; }
        body { background-color: #0a1929; }
        .gradient-bg { 
            background: linear-gradient(135deg, #1e3c72 0%, #0a1929 100%);
        }
        .student-card {
            transition: all 0.3s ease;
            background: rgba(255, 255, 255, 0.05);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.1);
        }
        .student-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 20px 25px -5px rgba(0,0,0,0.5);
            background: rgba(255, 255, 255, 0.08);
        }
        .compact-card {
            background: rgba(255, 255, 255, 0.03);
            border: 1px solid rgba(255, 255, 255, 0.1);
        }
        .compact-input {
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.1);
            color: white;
            padding: 0.5rem 0.75rem;
            font-size: 0.875rem;
            width: 100%;
            border-radius: 0.5rem;
        }
        .compact-input:focus {
            border-color: #3b82f6;
            outline: none;
            ring: 2px solid #3b82f6;
            background: rgba(255, 255, 255, 0.08);
        }
        .compact-input option {
            background-color: #0a1929;
            color: white;
        }
        .compact-label {
            color: #94a3b8;
            font-size: 0.75rem;
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            margin-bottom: 0.25rem;
            display: block;
        }
        .status-badge {
            padding: 0.25rem 0.75rem;
            border-radius: 9999px;
            font-size: 0.7rem;
            font-weight: 500;
        }
    </style>
</head>
<body class="bg-[#0a1929]">
    <div class="flex min-h-screen">
        <!-- Sidebar -->
        <?php include 'sidebar.php'; ?>

        <!-- Main Content -->
        <div class="flex-1 flex flex-col ">
            <!-- Header -->
            <div class="bg-[#0f2744] shadow-lg px-6 py-4 sticky top-0 z-10 border-b border-gray-800">
                <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                    <div>
                        <h1 class="text-2xl font-bold text-white flex items-center">
                            <i class="fas fa-users text-blue-400 mr-3"></i>
                            Student Management
                        </h1>
                        <p class="text-sm text-gray-400 mt-1">Manage all student accounts and profiles</p>
                    </div>
                    <div class="flex items-center space-x-3">
                        <button onclick="openAddModal()" class="gradient-bg text-white px-4 py-2 rounded-lg hover:opacity-90 transition flex items-center text-sm">
                            <i class="fas fa-plus mr-2"></i>
                            Add New Student
                        </button>
                    </div>
                </div>
            </div>

            <!-- Content -->
            <div class="p-6 space-y-6">
                <!-- Messages -->
                <?php if ($message): ?>
                    <div class="bg-green-900 bg-opacity-20 border border-green-800 text-green-400 px-4 py-3 rounded-lg flex items-center text-sm">
                        <i class="fas fa-check-circle mr-2"></i>
                        <?= $message ?>
                    </div>
                <?php endif; ?>
                <?php if ($error): ?>
                    <div class="bg-red-900 bg-opacity-20 border border-red-800 text-red-400 px-4 py-3 rounded-lg flex items-center text-sm">
                        <i class="fas fa-exclamation-circle mr-2"></i>
                        <?= $error ?>
                    </div>
                <?php endif; ?>

                <!-- Stats Cards -->
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
                    <div class="compact-card rounded-lg p-4">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-xs text-gray-400 uppercase tracking-wider">Total Students</p>
                                <p class="text-2xl font-bold text-white"><?= $stats['total'] ?></p>
                            </div>
                            <div class="w-10 h-10 bg-blue-900 bg-opacity-30 rounded-lg flex items-center justify-center">
                                <i class="fas fa-users text-blue-400 text-lg"></i>
                            </div>
                        </div>
                    </div>
                    
                    <div class="compact-card rounded-lg p-4">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-xs text-gray-400 uppercase tracking-wider">Active</p>
                                <p class="text-2xl font-bold text-green-400"><?= $stats['active'] ?></p>
                            </div>
                            <div class="w-10 h-10 bg-green-900 bg-opacity-30 rounded-lg flex items-center justify-center">
                                <i class="fas fa-check-circle text-green-400 text-lg"></i>
                            </div>
                        </div>
                    </div>
                    
                    <div class="compact-card rounded-lg p-4">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-xs text-gray-400 uppercase tracking-wider">Joined Today</p>
                                <p class="text-2xl font-bold text-yellow-400"><?= $stats['new_today'] ?></p>
                            </div>
                            <div class="w-10 h-10 bg-yellow-900 bg-opacity-30 rounded-lg flex items-center justify-center">
                                <i class="fas fa-calendar-plus text-yellow-400 text-lg"></i>
                            </div>
                        </div>
                    </div>
                    
                    <div class="compact-card rounded-lg p-4">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-xs text-gray-400 uppercase tracking-wider">Total XP</p>
                                <p class="text-2xl font-bold text-purple-400"><?= number_format($stats['total_xp']) ?></p>
                            </div>
                            <div class="w-10 h-10 bg-purple-900 bg-opacity-30 rounded-lg flex items-center justify-center">
                                <i class="fas fa-star text-purple-400 text-lg"></i>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Filters -->
                <div class="compact-card rounded-lg p-3">
                    <form method="GET" class="flex flex-wrap gap-2 items-center">
                        <div class="flex-1 min-w-[200px]">
                            <div class="relative">
                                <i class="fas fa-search absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-500 text-xs"></i>
                                <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" 
                                       placeholder="Search by name, email, roll number..." 
                                       class="compact-input w-full pl-8">
                            </div>
                        </div>
                        
                        <select name="school" class="compact-input w-40">
                            <option value="" class="bg-gray-900">All Schools</option>
                            <?php foreach ($schools as $school): ?>
                                <option value="<?= $school['id'] ?>" <?= $school_filter == $school['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($school['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        
                        <select name="class" class="compact-input w-32">
                            <option value="" class="bg-gray-900">All Classes</option>
                            <?php foreach ($classes as $class): ?>
                                <option value="<?= $class ?>" <?= $class_filter == $class ? 'selected' : '' ?>>
                                    <?= $class ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        
                        <select name="status" class="compact-input w-32">
                            <option value="" class="bg-gray-900">All Status</option>
                            <option value="active" <?= $status_filter == 'active' ? 'selected' : '' ?>>Active</option>
                            <option value="inactive" <?= $status_filter == 'inactive' ? 'selected' : '' ?>>Inactive</option>
                            <option value="suspended" <?= $status_filter == 'suspended' ? 'selected' : '' ?>>Suspended</option>
                        </select>
                        
                        <button type="submit" class="px-4 py-1.5 bg-gray-700 text-white rounded text-xs hover:bg-gray-600 transition">
                            <i class="fas fa-filter mr-1"></i>
                            Filter
                        </button>
                        
                        <a href="students.php" class="px-4 py-1.5 border border-gray-700 rounded text-xs text-gray-300 hover:bg-gray-800 transition">
                            <i class="fas fa-times mr-1"></i>
                            Clear
                        </a>
                    </form>
                </div>

                <!-- Students Grid/Table -->
                <div class="compact-card rounded-lg overflow-hidden">
                    <div class="p-4 border-b border-gray-800 bg-[#0f2744]">
                        <h2 class="text-base font-semibold text-white flex items-center">
                            <i class="fas fa-user-graduate text-blue-400 mr-2"></i>
                            Students (<?= count($students) ?>)
                        </h2>
                    </div>
                    
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-800">
                            <thead class="bg-[#0f2744]">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">Student</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">School & Class</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">Contact</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">Progress</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">Status</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-800">
                                <?php foreach ($students as $student): ?>
                                <tr class="hover:bg-white hover:bg-opacity-5 transition">
                                    <td class="px-6 py-4">
                                        <div class="flex items-center">
                                            <div class="flex-shrink-0 h-10 w-10 bg-gradient-to-br from-blue-500 to-purple-600 rounded-lg flex items-center justify-center text-white font-bold text-lg">
                                                <?= strtoupper(substr($student['name'], 0, 1)) ?>
                                            </div>
                                            <div class="ml-4">
                                                <div class="text-sm font-medium text-white"><?= htmlspecialchars($student['name']) ?></div>
                                                <div class="text-xs text-gray-400">ID: <?= $student['id'] ?> • Roll: <?= $student['roll_number'] ?? 'N/A' ?></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4">
                                        <div class="text-sm text-white"><?= htmlspecialchars($student['school_name'] ?? 'Not Assigned') ?></div>
                                        <div class="text-xs text-gray-400">Class <?= $student['class'] ?? 'N/A' ?></div>
                                    </td>
                                    <td class="px-6 py-4">
                                        <div class="text-sm text-white"><?= htmlspecialchars($student['email']) ?></div>
                                        <div class="text-xs text-gray-400"><?= $student['phone'] ?? 'No phone' ?></div>
                                    </td>
                                    <td class="px-6 py-4">
                                        <div class="flex items-center space-x-2">
                                            <div class="text-sm text-white"><?= number_format($student['xp_points']) ?> XP</div>
                                            <span class="text-xs bg-blue-900 text-blue-300 px-2 py-0.5 rounded-full">Lvl <?= $student['level'] ?></span>
                                        </div>
                                        <div class="flex items-center mt-1">
                                            <i class="fas fa-fire text-orange-400 text-xs mr-1"></i>
                                            <span class="text-xs text-gray-400"><?= $student['streak'] ?> day streak</span>
                                        </div>
                                        <div class="text-xs text-gray-400 mt-1">
                                            📚 <?= $student['enrolled_courses'] ?> courses • 🏆 <?= $student['certificates_count'] ?> certs
                                        </div>
                                    </td>
                                    <td class="px-6 py-4">
                                        <span class="status-badge 
                                            <?= $student['status'] == 'active' ? 'bg-green-900 text-green-300' : '' ?>
                                            <?= $student['status'] == 'inactive' ? 'bg-gray-900 text-gray-300' : '' ?>
                                            <?= $student['status'] == 'suspended' ? 'bg-red-900 text-red-300' : '' ?>">
                                            <?= ucfirst($student['status'] ?? 'active') ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4">
                                        <div class="flex space-x-2">
                                            <button onclick="editStudent(<?= $student['id'] ?>, '<?= htmlspecialchars(addslashes($student['name'])) ?>', '<?= $student['email'] ?>', '<?= $student['phone'] ?? '' ?>', '<?= $student['address'] ?? '' ?>', '<?= $student['city'] ?? '' ?>', '<?= $student['state'] ?? '' ?>', '<?= $student['pincode'] ?? '' ?>', '<?= $student['school_id'] ?? '' ?>', '<?= $student['class'] ?? '' ?>', '<?= $student['roll_number'] ?? '' ?>', '<?= $student['father_name'] ?? '' ?>', '<?= $student['mother_name'] ?? '' ?>', '<?= $student['dob'] ?? '' ?>', '<?= $student['gender'] ?? '' ?>', '<?= $student['blood_group'] ?? '' ?>')" 
                                                    class="text-blue-400 hover:text-blue-300 transition" title="Edit">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <a href="student-profile.php?id=<?= $student['id'] ?>" class="text-green-400 hover:text-green-300 transition" title="View Profile">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            <a href="?delete=<?= $student['id'] ?>" onclick="return confirm('Are you sure? This will delete all student data including progress and certificates!')" 
                                               class="text-red-400 hover:text-red-300 transition" title="Delete">
                                                <i class="fas fa-trash"></i>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <?php if (empty($students)): ?>
                    <div class="text-center py-12">
                        <div class="w-16 h-16 bg-gray-800 rounded-full mx-auto mb-3 flex items-center justify-center">
                            <i class="fas fa-user-graduate text-gray-600 text-2xl"></i>
                        </div>
                        <h3 class="text-base font-medium text-gray-300 mb-1">No students found</h3>
                        <p class="text-xs text-gray-500 mb-4">Add your first student to get started</p>
                        <button onclick="openAddModal()" class="inline-flex items-center px-4 py-2 bg-blue-600 text-white rounded text-xs hover:bg-blue-700 transition">
                            <i class="fas fa-plus mr-2"></i>
                            Add New Student
                        </button>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Add/Edit Modal -->
    <div id="studentModal" class="fixed inset-0 bg-black bg-opacity-75 hidden items-center justify-center z-50 overflow-y-auto">
        <div class="bg-[#0f2744] rounded-xl p-6 max-w-3xl w-full m-4 border border-gray-700">
            <div class="flex justify-between items-center mb-4">
                <h2 id="modalTitle" class="text-xl font-bold text-white">Add New Student</h2>
                <button onclick="closeModal()" class="text-gray-400 hover:text-white">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>
            
            <form method="POST" id="studentForm" class="space-y-4 max-h-[70vh] overflow-y-auto pr-2">
                <input type="hidden" name="action" id="action" value="add">
                <input type="hidden" name="id" id="studentId">
                
                <!-- Basic Information -->
                <div class="border-b border-gray-700 pb-3">
                    <h3 class="text-sm font-semibold text-blue-400 mb-3">Basic Information</h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                        <div>
                            <label class="compact-label">Full Name *</label>
                            <input type="text" name="name" id="studentName" required class="compact-input">
                        </div>
                        
                        <div>
                            <label class="compact-label">Email *</label>
                            <input type="email" name="email" id="studentEmail" required class="compact-input">
                        </div>
                        
                        <div>
                            <label class="compact-label">Password</label>
                            <input type="password" name="password" id="studentPassword" class="compact-input">
                            <p class="text-xs text-gray-500 mt-1">Leave blank to keep current password when editing</p>
                        </div>
                        
                        <div>
                            <label class="compact-label">Phone</label>
                            <input type="text" name="phone" id="studentPhone" class="compact-input">
                        </div>
                        
                        <div>
                            <label class="compact-label">Date of Birth</label>
                            <input type="date" name="dob" id="studentDob" class="compact-input">
                        </div>
                        
                        <div>
                            <label class="compact-label">Gender</label>
                            <select name="gender" id="studentGender" class="compact-input">
                                <option value="" class="bg-gray-900">Select Gender</option>
                                <option value="male" class="bg-gray-900">Male</option>
                                <option value="female" class="bg-gray-900">Female</option>
                                <option value="other" class="bg-gray-900">Other</option>
                            </select>
                        </div>
                        
                        <div>
                            <label class="compact-label">Blood Group</label>
                            <input type="text" name="blood_group" id="studentBloodGroup" placeholder="e.g., O+" class="compact-input">
                        </div>
                    </div>
                </div>
                
                <!-- Academic Information -->
                <div class="border-b border-gray-700 pb-3">
                    <h3 class="text-sm font-semibold text-blue-400 mb-3">Academic Information</h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                        <div>
                            <label class="compact-label">School</label>
                            <select name="school_id" id="studentSchool" class="compact-input">
                                <option value="" class="bg-gray-900">Select School</option>
                                <?php foreach ($schools as $school): ?>
                                    <option value="<?= $school['id'] ?>" class="bg-gray-900"><?= htmlspecialchars($school['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div>
                            <label class="compact-label">Class</label>
                            <select name="class" id="studentClass" class="compact-input">
                                <option value="" class="bg-gray-900">Select Class</option>
                                <?php foreach ($classes as $class): ?>
                                    <option value="<?= $class ?>" class="bg-gray-900"><?= $class ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div>
                            <label class="compact-label">Roll Number</label>
                            <input type="text" name="roll_number" id="studentRollNumber" class="compact-input">
                        </div>
                        
                        <div>
                            <label class="compact-label">Father's Name</label>
                            <input type="text" name="father_name" id="studentFatherName" class="compact-input">
                        </div>
                        
                        <div>
                            <label class="compact-label">Mother's Name</label>
                            <input type="text" name="mother_name" id="studentMotherName" class="compact-input">
                        </div>
                    </div>
                </div>
                
                <!-- Address Information -->
                <div class="border-b border-gray-700 pb-3">
                    <h3 class="text-sm font-semibold text-blue-400 mb-3">Address Information</h3>
                    <div class="grid grid-cols-1 gap-3">
                        <div>
                            <label class="compact-label">Address</label>
                            <textarea name="address" id="studentAddress" rows="2" class="compact-input"></textarea>
                        </div>
                        
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                            <div>
                                <label class="compact-label">City</label>
                                <input type="text" name="city" id="studentCity" class="compact-input">
                            </div>
                            
                            <div>
                                <label class="compact-label">State</label>
                                <input type="text" name="state" id="studentState" class="compact-input">
                            </div>
                            
                            <div>
                                <label class="compact-label">Pincode</label>
                                <input type="text" name="pincode" id="studentPincode" class="compact-input">
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="flex justify-end space-x-3 pt-3">
                    <button type="button" onclick="closeModal()" class="px-4 py-2 border border-gray-600 rounded text-gray-300 hover:bg-gray-800 transition text-sm">
                        Cancel
                    </button>
                    <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700 transition text-sm">
                        <i class="fas fa-save mr-2"></i>
                        Save Student
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openAddModal() {
            document.getElementById('modalTitle').textContent = 'Add New Student';
            document.getElementById('action').value = 'add';
            document.getElementById('studentId').value = '';
            
            // Clear all fields
            document.getElementById('studentName').value = '';
            document.getElementById('studentEmail').value = '';
            document.getElementById('studentPassword').value = '';
            document.getElementById('studentPhone').value = '';
            document.getElementById('studentDob').value = '';
            document.getElementById('studentGender').value = '';
            document.getElementById('studentBloodGroup').value = '';
            document.getElementById('studentSchool').value = '';
            document.getElementById('studentClass').value = '';
            document.getElementById('studentRollNumber').value = '';
            document.getElementById('studentFatherName').value = '';
            document.getElementById('studentMotherName').value = '';
            document.getElementById('studentAddress').value = '';
            document.getElementById('studentCity').value = '';
            document.getElementById('studentState').value = '';
            document.getElementById('studentPincode').value = '';
            
            document.getElementById('studentPassword').required = true;
            document.getElementById('studentModal').classList.remove('hidden');
            document.getElementById('studentModal').classList.add('flex');
        }

        function editStudent(id, name, email, phone, address, city, state, pincode, school_id, class_name, roll_number, father_name, mother_name, dob, gender, blood_group) {
            document.getElementById('modalTitle').textContent = 'Edit Student';
            document.getElementById('action').value = 'edit';
            document.getElementById('studentId').value = id;
            document.getElementById('studentName').value = name;
            document.getElementById('studentEmail').value = email;
            document.getElementById('studentPhone').value = phone || '';
            document.getElementById('studentAddress').value = address || '';
            document.getElementById('studentCity').value = city || '';
            document.getElementById('studentState').value = state || '';
            document.getElementById('studentPincode').value = pincode || '';
            document.getElementById('studentSchool').value = school_id || '';
            document.getElementById('studentClass').value = class_name || '';
            document.getElementById('studentRollNumber').value = roll_number || '';
            document.getElementById('studentFatherName').value = father_name || '';
            document.getElementById('studentMotherName').value = mother_name || '';
            document.getElementById('studentDob').value = dob || '';
            document.getElementById('studentGender').value = gender || '';
            document.getElementById('studentBloodGroup').value = blood_group || '';
            
            document.getElementById('studentPassword').value = '';
            document.getElementById('studentPassword').required = false;
            
            document.getElementById('studentModal').classList.remove('hidden');
            document.getElementById('studentModal').classList.add('flex');
        }

        function closeModal() {
            document.getElementById('studentModal').classList.add('hidden');
            document.getElementById('studentModal').classList.remove('flex');
        }

        // Close modal when clicking outside
        window.onclick = function(event) {
            const modal = document.getElementById('studentModal');
            if (event.target === modal) {
                closeModal();
            }
        }
    </script>
</body>
</html>