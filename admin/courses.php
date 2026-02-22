<?php
require_once '../includes/auth.php';
require_once '../includes/auto_publish.php';
requireAdmin();

// Run auto-publisher on every page load
if (function_exists('autoPublishVideos')) {
    autoPublishVideos($pdo);
}

// Check if required tables and columns exist
$tables = [
    'chapters' => $pdo->query("SHOW TABLES LIKE 'chapters'")->rowCount() > 0,
    'materials' => $pdo->query("SHOW TABLES LIKE 'materials'")->rowCount() > 0,
    'enrollments' => $pdo->query("SHOW TABLES LIKE 'enrollments'")->rowCount() > 0,
    'videos' => $pdo->query("SHOW TABLES LIKE 'videos'")->rowCount() > 0
];

// Check columns in courses table
$checkSchoolColumn = $pdo->query("SHOW COLUMNS FROM courses LIKE 'school_id'")->rowCount() > 0;
$checkCategoryColumn = $pdo->query("SHOW COLUMNS FROM courses LIKE 'category'")->rowCount() > 0;
$checkThumbnailColumn = $pdo->query("SHOW COLUMNS FROM courses LIKE 'thumbnail'")->rowCount() > 0;
$checkLevelColumn = $pdo->query("SHOW COLUMNS FROM courses LIKE 'level'")->rowCount() > 0;
$checkPriceColumn = $pdo->query("SHOW COLUMNS FROM courses LIKE 'price'")->rowCount() > 0;
$checkLanguageColumn = $pdo->query("SHOW COLUMNS FROM courses LIKE 'language'")->rowCount() > 0;
$checkPrereqColumn = $pdo->query("SHOW COLUMNS FROM courses LIKE 'prerequisites'")->rowCount() > 0;
$checkObjectivesColumn = $pdo->query("SHOW COLUMNS FROM courses LIKE 'learning_objectives'")->rowCount() > 0;

// Get all schools for filtering
$schools = [];
try {
    $schools = $pdo->query("SELECT id, name FROM schools ORDER BY name")->fetchAll();
} catch (PDOException $e) {
    $schools = [];
}

// Course categories
$categories = [
    'Web Development', 'Mobile Development', 'Data Science', 'AI & Machine Learning',
    'Cloud Computing', 'Cybersecurity', 'DevOps', 'Programming Languages',
    'Database Design', 'Software Testing', 'UI/UX Design', 'Business & Management'
];

// Course levels
$levels = ['Beginner', 'Intermediate', 'Advanced', 'All Levels'];

// Handle CRUD operations
$message = '';
$error = '';

// Delete course
if (isset($_GET['delete'])) {
    $id = $_GET['delete'];
    try {
        // Start transaction
        $pdo->beginTransaction();
        
        // Delete related records only if tables exist
        if ($tables['chapters']) {
            $pdo->prepare("DELETE FROM chapters WHERE course_id = ?")->execute([$id]);
        }
        if ($tables['videos']) {
            $pdo->prepare("DELETE FROM videos WHERE course_id = ?")->execute([$id]);
        }
        if ($tables['materials']) {
            $pdo->prepare("DELETE FROM materials WHERE course_id = ?")->execute([$id]);
        }
        if ($tables['enrollments']) {
            $pdo->prepare("DELETE FROM enrollments WHERE course_id = ?")->execute([$id]);
        }
        
        // Delete course
        $stmt = $pdo->prepare("DELETE FROM courses WHERE id = ?");
        $stmt->execute([$id]);
        
        $pdo->commit();
        $message = 'Course deleted successfully';
    } catch (PDOException $e) {
        $pdo->rollBack();
        $error = 'Failed to delete course: ' . $e->getMessage();
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
        $category = $_POST['category'] ?? '';
        $level = $_POST['level'] ?? 'Beginner';
        $school_id = !empty($_POST['school_id']) ? $_POST['school_id'] : null;
        $price = !empty($_POST['price']) ? floatval($_POST['price']) : 0;
        $language = trim($_POST['language'] ?? 'English');
        $prerequisites = trim($_POST['prerequisites'] ?? '');
        $learning_objectives = trim($_POST['learning_objectives'] ?? '');
        
        // Handle thumbnail upload
        $thumbnail = '';
        if (isset($_FILES['thumbnail']) && $_FILES['thumbnail']['error'] == 0) {
            $allowed = ['image/jpeg', 'image/png', 'image/jpg', 'image/webp'];
            if (in_array($_FILES['thumbnail']['type'], $allowed)) {
                $upload_dir = '../uploads/courses/thumbnails/';
                if (!file_exists($upload_dir)) {
                    mkdir($upload_dir, 0777, true);
                }
                
                $extension = pathinfo($_FILES['thumbnail']['name'], PATHINFO_EXTENSION);
                $filename = 'course_' . time() . '_' . uniqid() . '.' . $extension;
                $destination = $upload_dir . $filename;
                
                if (move_uploaded_file($_FILES['thumbnail']['tmp_name'], $destination)) {
                    $thumbnail = 'uploads/courses/thumbnails/' . $filename;
                }
            }
        }
        
        if ($_POST['action'] == 'add') {
            // Build dynamic insert query based on existing columns
            $fields = ['title', 'description', 'instructor', 'duration', 'status', 'created_by'];
            $placeholders = ['?', '?', '?', '?', '?', '?'];
            $values = [$title, $description, $instructor, $duration, $status, $_SESSION['user_id']];
            
            if ($checkCategoryColumn && $category) {
                $fields[] = 'category';
                $placeholders[] = '?';
                $values[] = $category;
            }
            if ($checkLevelColumn && $level) {
                $fields[] = 'level';
                $placeholders[] = '?';
                $values[] = $level;
            }
            if ($checkSchoolColumn && $school_id) {
                $fields[] = 'school_id';
                $placeholders[] = '?';
                $values[] = $school_id;
            }
            if ($checkThumbnailColumn && $thumbnail) {
                $fields[] = 'thumbnail';
                $placeholders[] = '?';
                $values[] = $thumbnail;
            }
            if ($checkPriceColumn) {
                $fields[] = 'price';
                $placeholders[] = '?';
                $values[] = $price;
            }
            if ($checkLanguageColumn) {
                $fields[] = 'language';
                $placeholders[] = '?';
                $values[] = $language;
            }
            if ($checkPrereqColumn) {
                $fields[] = 'prerequisites';
                $placeholders[] = '?';
                $values[] = $prerequisites;
            }
            if ($checkObjectivesColumn) {
                $fields[] = 'learning_objectives';
                $placeholders[] = '?';
                $values[] = $learning_objectives;
            }
            
            $sql = "INSERT INTO courses (" . implode(', ', $fields) . ") VALUES (" . implode(', ', $placeholders) . ")";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($values);
            $message = 'Course added successfully';
            
        } elseif ($_POST['action'] == 'edit') {
            $id = $_POST['id'];
            
            // Build dynamic update query
            $sets = ["title = ?", "description = ?", "instructor = ?", "duration = ?", "status = ?"];
            $values = [$title, $description, $instructor, $duration, $status];
            
            if ($checkCategoryColumn) {
                $sets[] = "category = ?";
                $values[] = $category;
            }
            if ($checkLevelColumn) {
                $sets[] = "level = ?";
                $values[] = $level;
            }
            if ($checkSchoolColumn) {
                $sets[] = "school_id = ?";
                $values[] = $school_id;
            }
            if ($checkThumbnailColumn && $thumbnail) {
                $sets[] = "thumbnail = ?";
                $values[] = $thumbnail;
            }
            if ($checkPriceColumn) {
                $sets[] = "price = ?";
                $values[] = $price;
            }
            if ($checkLanguageColumn) {
                $sets[] = "language = ?";
                $values[] = $language;
            }
            if ($checkPrereqColumn) {
                $sets[] = "prerequisites = ?";
                $values[] = $prerequisites;
            }
            if ($checkObjectivesColumn) {
                $sets[] = "learning_objectives = ?";
                $values[] = $learning_objectives;
            }
            
            $sets[] = "updated_at = NOW()";
            $sql = "UPDATE courses SET " . implode(', ', $sets) . " WHERE id = ?";
            $values[] = $id;
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute($values);
            $message = 'Course updated successfully';
        }
    }
}

// Get filter parameters
$search = $_GET['search'] ?? '';
$category_filter = $_GET['category'] ?? '';
$level_filter = $_GET['level'] ?? '';
$school_filter = $_GET['school'] ?? '';
$status_filter = $_GET['status'] ?? '';

// Build dynamic query with table checks
$sql = "SELECT c.*";
$params = [];

// Add school name if column exists
if ($checkSchoolColumn) {
    $sql .= ", s.name as school_name";
}

// Add counts only if tables exist
if ($tables['enrollments']) {
    $sql .= ", (SELECT COUNT(*) FROM enrollments WHERE course_id = c.id AND status = 'active') as students_count";
} else {
    $sql .= ", 0 as students_count";
}

if ($tables['videos']) {
    $sql .= ", (SELECT COUNT(*) FROM videos WHERE course_id = c.id) as videos_count";
} else {
    $sql .= ", 0 as videos_count";
}

if ($tables['chapters']) {
    $sql .= ", (SELECT COUNT(*) FROM chapters WHERE course_id = c.id) as chapters_count";
} else {
    $sql .= ", 0 as chapters_count";
}

$sql .= " FROM courses c";

if ($checkSchoolColumn) {
    $sql .= " LEFT JOIN schools s ON c.school_id = s.id";
}

$sql .= " WHERE 1=1";

if ($search) {
    $sql .= " AND (c.title LIKE ? OR c.description LIKE ? OR c.instructor LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}
if ($category_filter && $checkCategoryColumn) {
    $sql .= " AND c.category = ?";
    $params[] = $category_filter;
}
if ($level_filter && $checkLevelColumn) {
    $sql .= " AND c.level = ?";
    $params[] = $level_filter;
}
if ($school_filter && $checkSchoolColumn) {
    $sql .= " AND c.school_id = ?";
    $params[] = $school_filter;
}
if ($status_filter) {
    $sql .= " AND c.status = ?";
    $params[] = $status_filter;
}

$sql .= " ORDER BY c.created_at DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$courses = $stmt->fetchAll();

// Get statistics with table checks
$stats = [
    'total' => $pdo->query("SELECT COUNT(*) FROM courses")->fetchColumn(),
    'active' => $pdo->query("SELECT COUNT(*) FROM courses WHERE status = 'active'")->fetchColumn(),
    'inactive' => $pdo->query("SELECT COUNT(*) FROM courses WHERE status = 'inactive'")->fetchColumn(),
    'total_students' => $tables['enrollments'] ? ($pdo->query("SELECT COUNT(*) FROM enrollments WHERE status = 'active'")->fetchColumn() ?: 0) : 0,
];
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
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * { font-family: 'Inter', sans-serif; }
        body { background-color: #0a1929; }
        .gradient-bg { 
            background: linear-gradient(135deg, #1e3c72 0%, #0a1929 100%);
        }
        .course-card {
            transition: all 0.3s ease;
            background: rgba(255, 255, 255, 0.05);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.1);
        }
        .course-card:hover {
            transform: translateY(-5px);
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
    <div class="flex-1 flex flex-col">
        <!-- Header -->
        <div class="bg-[#0f2744] shadow-lg px-6 py-4 sticky top-0 z-10 border-b border-gray-800">
            <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                <div>
                    <h1 class="text-2xl font-bold text-white flex items-center">
                        <i class="fas fa-book-open text-blue-400 mr-3"></i>
                        Course Management
                    </h1>
                    <p class="text-sm text-gray-400 mt-1">Manage all courses, content, and enrollments</p>
                </div>
                <div class="flex items-center space-x-3">
                    <button onclick="openAddModal()" class="gradient-bg text-white px-4 py-2 rounded-lg hover:opacity-90 transition flex items-center text-sm">
                        <i class="fas fa-plus mr-2"></i>
                        Add New Course
                    </button>
                </div>
            </div>
        </div>

        <!-- Content -->
        <div class="p-6 space-y-6">

            <!-- Stats Cards -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
                <div class="compact-card rounded-lg p-4">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-xs text-gray-400 uppercase tracking-wider">Total Courses</p>
                            <p class="text-2xl font-bold text-white"><?= $stats['total'] ?></p>
                        </div>
                        <div class="w-10 h-10 bg-blue-900 bg-opacity-30 rounded-lg flex items-center justify-center">
                            <i class="fas fa-book text-blue-400 text-lg"></i>
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
                            <p class="text-xs text-gray-400 uppercase tracking-wider">Inactive</p>
                            <p class="text-2xl font-bold text-yellow-400"><?= $stats['inactive'] ?></p>
                        </div>
                        <div class="w-10 h-10 bg-yellow-900 bg-opacity-30 rounded-lg flex items-center justify-center">
                            <i class="fas fa-pause-circle text-yellow-400 text-lg"></i>
                        </div>
                    </div>
                </div>
                
                <div class="compact-card rounded-lg p-4">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-xs text-gray-400 uppercase tracking-wider">Enrolled Students</p>
                            <p class="text-2xl font-bold text-purple-400"><?= number_format($stats['total_students']) ?></p>
                        </div>
                        <div class="w-10 h-10 bg-purple-900 bg-opacity-30 rounded-lg flex items-center justify-center">
                            <i class="fas fa-users text-purple-400 text-lg"></i>
                        </div>
                    </div>
                </div>
            </div>

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

            <!-- Filters -->
            <div class="compact-card rounded-lg p-3">
                <form method="GET" class="flex flex-wrap gap-2 items-center">
                    <div class="flex-1 min-w-[200px]">
                        <div class="relative">
                            <i class="fas fa-search absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-500 text-xs"></i>
                            <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" 
                                   placeholder="Search by title, instructor..." 
                                   class="compact-input w-full pl-8">
                        </div>
                    </div>
                    
                    <?php if ($checkCategoryColumn): ?>
                    <select name="category" class="compact-input w-40">
                        <option value="" class="bg-gray-900">All Categories</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?= $cat ?>" <?= $category_filter == $cat ? 'selected' : '' ?>>
                                <?= $cat ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <?php endif; ?>
                    
                    <?php if ($checkLevelColumn): ?>
                    <select name="level" class="compact-input w-32">
                        <option value="" class="bg-gray-900">All Levels</option>
                        <?php foreach ($levels as $lvl): ?>
                            <option value="<?= $lvl ?>" <?= $level_filter == $lvl ? 'selected' : '' ?>>
                                <?= $lvl ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <?php endif; ?>
                    
                    <?php if ($checkSchoolColumn && !empty($schools)): ?>
                    <select name="school" class="compact-input w-40">
                        <option value="" class="bg-gray-900">All Schools</option>
                        <?php foreach ($schools as $school): ?>
                            <option value="<?= $school['id'] ?>" <?= $school_filter == $school['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($school['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <?php endif; ?>
                    
                    <select name="status" class="compact-input w-32">
                        <option value="" class="bg-gray-900">All Status</option>
                        <option value="active" <?= $status_filter == 'active' ? 'selected' : '' ?>>Active</option>
                        <option value="inactive" <?= $status_filter == 'inactive' ? 'selected' : '' ?>>Inactive</option>
                    </select>
                    
                    <button type="submit" class="px-4 py-1.5 bg-gray-700 text-white rounded text-xs hover:bg-gray-600 transition">
                        <i class="fas fa-filter mr-1"></i>
                        Filter
                    </button>
                    
                    <a href="courses.php" class="px-4 py-1.5 border border-gray-700 rounded text-xs text-gray-300 hover:bg-gray-800 transition">
                        <i class="fas fa-times mr-1"></i>
                        Clear
                    </a>
                </form>
            </div>

            <!-- Courses Grid -->
            <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-6">
                <?php foreach ($courses as $course): ?>
                <div class="course-card rounded-xl overflow-hidden">
                    <!-- Thumbnail -->
                    <div class="h-36 bg-gradient-to-r from-blue-900 to-purple-900 relative">
                        <?php if ($checkThumbnailColumn && !empty($course['thumbnail'])): ?>
                            <img src="../<?= htmlspecialchars($course['thumbnail']) ?>" alt="Thumbnail" class="w-full h-full object-cover opacity-80">
                        <?php else: ?>
                            <div class="w-full h-full flex items-center justify-center">
                                <i class="fas fa-graduation-cap text-white text-4xl opacity-30"></i>
                            </div>
                        <?php endif; ?>
                        
                        <!-- Status Badge -->
                        <div class="absolute top-2 right-2">
                            <span class="status-badge text-xs
                                <?= $course['status'] == 'active' ? 'bg-green-900 text-green-300' : 'bg-yellow-900 text-yellow-300' ?>">
                                <?= ucfirst($course['status']) ?>
                            </span>
                        </div>
                        
                        <!-- Level Badge -->
                        <?php if ($checkLevelColumn && !empty($course['level'])): ?>
                        <div class="absolute bottom-2 left-2">
                            <span class="bg-blue-900 bg-opacity-80 text-blue-300 text-xs px-2 py-1 rounded-full">
                                <?= $course['level'] ?>
                            </span>
                        </div>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Content -->
                    <div class="p-5">
                        <div class="flex items-start justify-between mb-2">
                            <h3 class="font-bold text-white text-lg"><?= htmlspecialchars($course['title']) ?></h3>
                            <?php if ($checkCategoryColumn && !empty($course['category'])): ?>
                            <span class="text-xs text-blue-400 bg-blue-900 bg-opacity-30 px-2 py-1 rounded-full">
                                <?= htmlspecialchars($course['category']) ?>
                            </span>
                            <?php endif; ?>
                        </div>
                        
                        <!-- School -->
                        <?php if ($checkSchoolColumn && !empty($course['school_name'])): ?>
                        <div class="flex items-center mb-2">
                            <i class="fas fa-school text-gray-500 text-xs mr-2"></i>
                            <span class="text-xs text-gray-400"><?= htmlspecialchars($course['school_name']) ?></span>
                        </div>
                        <?php endif; ?>
                        
                        <!-- Instructor & Duration -->
                        <div class="flex items-center space-x-4 text-xs text-gray-400 mb-3">
                            <span><i class="fas fa-user mr-1 text-blue-400"></i><?= htmlspecialchars($course['instructor'] ?? 'N/A') ?></span>
                            <span><i class="far fa-clock mr-1 text-green-400"></i><?= htmlspecialchars($course['duration'] ?? 'N/A') ?></span>
                        </div>
                        
                        <!-- Description -->
                        <p class="text-gray-400 text-xs leading-relaxed mb-4 line-clamp-2">
                            <?= htmlspecialchars(substr($course['description'] ?? '', 0, 120)) ?>...
                        </p>
                        
                        <!-- Stats -->
                        <div class="flex items-center justify-between text-xs text-gray-500 mb-4">
                            <?php if ($tables['videos']): ?>
                            <span><i class="fas fa-video mr-1"></i> <?= $course['videos_count'] ?? 0 ?> Videos</span>
                            <?php endif; ?>
                            <?php if ($tables['chapters']): ?>
                            <span><i class="fas fa-layer-group mr-1"></i> <?= $course['chapters_count'] ?? 0 ?> Chapters</span>
                            <?php endif; ?>
                            <?php if ($tables['enrollments']): ?>
                            <span><i class="fas fa-users mr-1"></i> <?= $course['students_count'] ?? 0 ?> Students</span>
                            <?php endif; ?>
                        </div>
                        
                        <!-- Price -->
                        <?php if ($checkPriceColumn && !empty($course['price']) && $course['price'] > 0): ?>
                        <div class="mb-3">
                            <span class="text-lg font-bold text-white">$<?= number_format($course['price'], 2) ?></span>
                        </div>
                        <?php endif; ?>
                        
                        <!-- Actions -->
                        <div class="flex items-center justify-between pt-3 border-t border-gray-800">
                            <div class="flex space-x-3">
                                <?php if ($tables['chapters']): ?>
                                <a href="chapters.php?course_id=<?= $course['id'] ?>" 
                                   class="text-blue-400 hover:text-blue-300 text-xs font-medium">
                                    <i class="fas fa-video mr-1"></i> Chapters
                                </a>
                                <?php endif; ?>
                                <?php if ($tables['materials']): ?>
                                <a href="materials.php?course_id=<?= $course['id'] ?>" 
                                   class="text-green-400 hover:text-green-300 text-xs font-medium">
                                    <i class="fas fa-file-pdf mr-1"></i> Materials
                                </a>
                                <?php endif; ?>
                                <?php if ($tables['enrollments']): ?>
                                <a href="enrollments.php?course_id=<?= $course['id'] ?>" 
                                   class="text-purple-400 hover:text-purple-300 text-xs font-medium">
                                    <i class="fas fa-users mr-1"></i> Enrollments
                                </a>
                                <?php endif; ?>
                            </div>
                            <div class="flex space-x-2">
                                <button onclick="editCourse(<?= $course['id'] ?>, 
                                    '<?= htmlspecialchars(addslashes($course['title'] ?? '')) ?>', 
                                    '<?= htmlspecialchars(addslashes($course['description'] ?? '')) ?>', 
                                    '<?= htmlspecialchars(addslashes($course['instructor'] ?? '')) ?>', 
                                    '<?= htmlspecialchars($course['duration'] ?? '') ?>', 
                                    '<?= $course['status'] ?? '' ?>',
                                    '<?= htmlspecialchars(addslashes($course['category'] ?? '')) ?>',
                                    '<?= htmlspecialchars(addslashes($course['level'] ?? '')) ?>',
                                    '<?= $course['school_id'] ?? '' ?>',
                                    '<?= $course['price'] ?? '' ?>',
                                    '<?= htmlspecialchars(addslashes($course['language'] ?? '')) ?>',
                                    '<?= htmlspecialchars(addslashes($course['prerequisites'] ?? '')) ?>',
                                    '<?= htmlspecialchars(addslashes($course['learning_objectives'] ?? '')) ?>')" 
                                    class="text-gray-400 hover:text-blue-400 transition" title="Edit">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <a href="?delete=<?= $course['id'] ?>" onclick="return confirm('Delete this course and all its content?')" 
                                   class="text-gray-400 hover:text-red-400 transition" title="Delete">
                                    <i class="fas fa-trash"></i>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
                
                <?php if (empty($courses)): ?>
                <div class="col-span-3 text-center py-12">
                    <div class="w-16 h-16 bg-gray-800 rounded-full mx-auto mb-3 flex items-center justify-center">
                        <i class="fas fa-book-open text-gray-600 text-2xl"></i>
                    </div>
                    <h3 class="text-base font-medium text-gray-300 mb-1">No courses found</h3>
                    <p class="text-xs text-gray-500 mb-4">Add your first course to get started</p>
                    <button onclick="openAddModal()" class="inline-flex items-center px-4 py-2 bg-blue-600 text-white rounded text-xs hover:bg-blue-700 transition">
                        <i class="fas fa-plus mr-2"></i>
                        Add New Course
                    </button>
                </div>
                <?php endif; ?>
            </div>

        </div>
    </div>
</div>

<!-- Add/Edit Modal -->
<div id="courseModal" class="fixed inset-0 bg-black bg-opacity-75 hidden items-center justify-center z-50 overflow-y-auto">
    <div class="bg-[#0f2744] rounded-xl p-6 max-w-3xl w-full m-4 border border-gray-700">
        <div class="flex justify-between items-center mb-4">
            <h2 id="modalTitle" class="text-xl font-bold text-white">Add New Course</h2>
            <button onclick="closeModal()" class="text-gray-400 hover:text-white">
                <i class="fas fa-times text-xl"></i>
            </button>
        </div>
        
        <form method="POST" enctype="multipart/form-data" id="courseForm" class="space-y-4 max-h-[70vh] overflow-y-auto pr-2">
            <input type="hidden" name="action" id="action" value="add">
            <input type="hidden" name="id" id="courseId">
            
            <!-- Basic Information -->
            <div class="border-b border-gray-700 pb-3">
                <h3 class="text-sm font-semibold text-blue-400 mb-3">Basic Information</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                    <div class="md:col-span-2">
                        <label class="compact-label">Course Title *</label>
                        <input type="text" name="title" id="courseTitle" required class="compact-input">
                    </div>
                    
                    <div class="md:col-span-2">
                        <label class="compact-label">Description</label>
                        <textarea name="description" id="courseDescription" rows="3" class="compact-input"></textarea>
                    </div>
                    
                    <div>
                        <label class="compact-label">Instructor *</label>
                        <input type="text" name="instructor" id="courseInstructor" required class="compact-input">
                    </div>
                    
                    <div>
                        <label class="compact-label">Duration</label>
                        <input type="text" name="duration" id="courseDuration" placeholder="e.g., 12 weeks" class="compact-input">
                    </div>
                    
                    <?php if ($checkCategoryColumn): ?>
                    <div>
                        <label class="compact-label">Category</label>
                        <select name="category" id="courseCategory" class="compact-input">
                            <option value="" class="bg-gray-900">Select Category</option>
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?= $cat ?>" class="bg-gray-900"><?= $cat ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <?php endif; ?>
                    
                    <?php if ($checkLevelColumn): ?>
                    <div>
                        <label class="compact-label">Level</label>
                        <select name="level" id="courseLevel" class="compact-input">
                            <?php foreach ($levels as $lvl): ?>
                                <option value="<?= $lvl ?>" class="bg-gray-900"><?= $lvl ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <?php endif; ?>
                    
                    <?php if ($checkLanguageColumn): ?>
                    <div>
                        <label class="compact-label">Language</label>
                        <input type="text" name="language" id="courseLanguage" value="English" class="compact-input">
                    </div>
                    <?php endif; ?>
                    
                    <?php if ($checkPriceColumn): ?>
                    <div>
                        <label class="compact-label">Price ($)</label>
                        <input type="number" name="price" id="coursePrice" step="0.01" min="0" value="0" class="compact-input">
                    </div>
                    <?php endif; ?>
                    
                    <div>
                        <label class="compact-label">Status</label>
                        <select name="status" id="courseStatus" class="compact-input">
                            <option value="active" class="bg-gray-900">Active</option>
                            <option value="inactive" class="bg-gray-900">Inactive</option>
                        </select>
                    </div>
                    
                    <?php if ($checkSchoolColumn && !empty($schools)): ?>
                    <div>
                        <label class="compact-label">School</label>
                        <select name="school_id" id="courseSchool" class="compact-input">
                            <option value="" class="bg-gray-900">All Schools</option>
                            <?php foreach ($schools as $school): ?>
                                <option value="<?= $school['id'] ?>" class="bg-gray-900"><?= htmlspecialchars($school['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <?php endif; ?>
                    
                    <?php if ($checkThumbnailColumn): ?>
                    <div>
                        <label class="compact-label">Thumbnail Image</label>
                        <input type="file" name="thumbnail" accept="image/*" class="compact-input">
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Additional Information -->
            <?php if ($checkPrereqColumn || $checkObjectivesColumn): ?>
            <div class="border-b border-gray-700 pb-3">
                <h3 class="text-sm font-semibold text-blue-400 mb-3">Additional Information</h3>
                <div class="grid grid-cols-1 gap-3">
                    <?php if ($checkPrereqColumn): ?>
                    <div>
                        <label class="compact-label">Prerequisites</label>
                        <textarea name="prerequisites" id="coursePrerequisites" rows="2" class="compact-input" 
                                  placeholder="What students should know before taking this course"></textarea>
                    </div>
                    <?php endif; ?>
                    
                    <?php if ($checkObjectivesColumn): ?>
                    <div>
                        <label class="compact-label">Learning Objectives</label>
                        <textarea name="learning_objectives" id="courseObjectives" rows="2" class="compact-input" 
                                  placeholder="What students will learn in this course"></textarea>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>
            
            <div class="flex justify-end space-x-3 pt-3">
                <button type="button" onclick="closeModal()" class="px-4 py-2 border border-gray-600 rounded text-gray-300 hover:bg-gray-800 transition text-sm">
                    Cancel
                </button>
                <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700 transition text-sm">
                    <i class="fas fa-save mr-2"></i>
                    Save Course
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function openAddModal() {
    document.getElementById('modalTitle').innerText = 'Add New Course';
    document.getElementById('action').value = 'add';
    document.getElementById('courseId').value = '';
    document.getElementById('courseTitle').value = '';
    document.getElementById('courseDescription').value = '';
    document.getElementById('courseInstructor').value = '';
    document.getElementById('courseDuration').value = '';
    document.getElementById('courseStatus').value = 'active';
    
    <?php if ($checkLanguageColumn): ?>
    document.getElementById('courseLanguage').value = 'English';
    <?php endif; ?>
    <?php if ($checkPriceColumn): ?>
    document.getElementById('coursePrice').value = '0';
    <?php endif; ?>
    <?php if ($checkCategoryColumn): ?>
    document.getElementById('courseCategory').value = '';
    <?php endif; ?>
    <?php if ($checkLevelColumn): ?>
    document.getElementById('courseLevel').value = 'Beginner';
    <?php endif; ?>
    <?php if ($checkSchoolColumn): ?>
    document.getElementById('courseSchool').value = '';
    <?php endif; ?>
    <?php if ($checkPrereqColumn): ?>
    document.getElementById('coursePrerequisites').value = '';
    <?php endif; ?>
    <?php if ($checkObjectivesColumn): ?>
    document.getElementById('courseObjectives').value = '';
    <?php endif; ?>
    
    document.getElementById('courseModal').classList.remove('hidden');
    document.getElementById('courseModal').classList.add('flex');
}

function editCourse(id, title, description, instructor, duration, status, category = '', level = '', school_id = '', price = '', language = 'English', prerequisites = '', objectives = '') {
    document.getElementById('modalTitle').innerText = 'Edit Course';
    document.getElementById('action').value = 'edit';
    document.getElementById('courseId').value = id;
    document.getElementById('courseTitle').value = title;
    document.getElementById('courseDescription').value = description;
    document.getElementById('courseInstructor').value = instructor;
    document.getElementById('courseDuration').value = duration;
    document.getElementById('courseStatus').value = status;
    
    <?php if ($checkLanguageColumn): ?>
    document.getElementById('courseLanguage').value = language || 'English';
    <?php endif; ?>
    <?php if ($checkPriceColumn): ?>
    document.getElementById('coursePrice').value = price || '0';
    <?php endif; ?>
    <?php if ($checkCategoryColumn): ?>
    document.getElementById('courseCategory').value = category;
    <?php endif; ?>
    <?php if ($checkLevelColumn): ?>
    document.getElementById('courseLevel').value = level || 'Beginner';
    <?php endif; ?>
    <?php if ($checkSchoolColumn): ?>
    document.getElementById('courseSchool').value = school_id || '';
    <?php endif; ?>
    <?php if ($checkPrereqColumn): ?>
    document.getElementById('coursePrerequisites').value = prerequisites || '';
    <?php endif; ?>
    <?php if ($checkObjectivesColumn): ?>
    document.getElementById('courseObjectives').value = objectives || '';
    <?php endif; ?>
    
    document.getElementById('courseModal').classList.remove('hidden');
    document.getElementById('courseModal').classList.add('flex');
}

function closeModal() {
    document.getElementById('courseModal').classList.add('hidden');
}

// Close modal when clicking outside
window.onclick = function(event) {
    const modal = document.getElementById('courseModal');
    if (event.target === modal) {
        closeModal();
    }
}
</script>

</body>
</html>