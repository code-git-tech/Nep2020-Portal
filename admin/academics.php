<?php
require_once '../config/db.php';
require_once '../includes/auth.php';
requireAdmin();

$error = '';
$success = '';

// Handle different actions
$action = $_GET['action'] ?? 'list';
$course_id = $_GET['id'] ?? 0;

// Predefined AI/Tech courses
$ai_courses = [
    'Python Development with AI',
    'Website Development with AI',
    'AI & Machine Learning',
    'Data Science with AI',
    'Cloud Computing'
];

// All subjects for classes 6-10
$all_subjects = [
    'Mathematics', 'Science', 'English', 'Hindi', 'Sanskrit',
    'Social Studies', 'Computer Science', 'General Knowledge',
    'Physics', 'Chemistry', 'Biology', 'History', 'Geography',
    'Civics', 'Economics', 'Art', 'Physical Education'
];

// Get all schools
$schools = $pdo->query("SELECT * FROM schools ORDER BY name")->fetchAll();

// ADD/EDIT COURSE
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_course'])) {
    $title = trim($_POST['title']);
    $school_id = trim($_POST['school_id']);
    $class = trim($_POST['class']);
    $subject = trim($_POST['subject']);
    $description = trim($_POST['description']);
    $duration = trim($_POST['duration']);
    $status = $_POST['status'] ?? 'draft';
    $featured = isset($_POST['featured']) ? 1 : 0;
    
    // Handle thumbnail upload
    $thumbnail = '';
    if (isset($_FILES['thumbnail']) && $_FILES['thumbnail']['error'] == 0) {
        $allowed = ['image/jpeg', 'image/png', 'image/jpg', 'image/webp'];
        if (in_array($_FILES['thumbnail']['type'], $allowed)) {
            $upload_dir = '../uploads/academic/thumbnails/';
            if (!file_exists($upload_dir)) mkdir($upload_dir, 0777, true);
            
            $extension = pathinfo($_FILES['thumbnail']['name'], PATHINFO_EXTENSION);
            $filename = 'course_' . time() . '_' . uniqid() . '.' . $extension;
            $destination = $upload_dir . $filename;
            
            if (move_uploaded_file($_FILES['thumbnail']['tmp_name'], $destination)) {
                $thumbnail = 'uploads/academic/thumbnails/' . $filename;
            }
        }
    }
    
    if ($title && $school_id && $class && $subject) {
        if ($course_id) {
            // Update existing course
            $sql = "UPDATE academic_courses SET 
                    title = ?, school_id = ?, class = ?, subject = ?, description = ?, 
                    duration = ?, status = ?, featured = ?";
            $params = [$title, $school_id, $class, $subject, $description, $duration, $status, $featured];
            
            if ($thumbnail) {
                $sql .= ", thumbnail = ?";
                $params[] = $thumbnail;
            }
            
            $sql .= " WHERE id = ?";
            $params[] = $course_id;
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $success = "Course updated successfully!";
        } else {
            // Insert new course
            $stmt = $pdo->prepare("
                INSERT INTO academic_courses (title, school_id, class, subject, description, duration, thumbnail, status, featured, created_by) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$title, $school_id, $class, $subject, $description, $duration, $thumbnail, $status, $featured, $_SESSION['user_id']]);
            $success = "Course added successfully!";
        }
    } else {
        $error = "Title, School, Class, and Subject are required!";
    }
}

// DELETE COURSE
if (isset($_GET['delete'])) {
    $id = $_GET['delete'];
    
    // Get thumbnail to delete file
    $stmt = $pdo->prepare("SELECT thumbnail FROM academic_courses WHERE id = ?");
    $stmt->execute([$id]);
    $course = $stmt->fetch();
    
    if ($course && $course['thumbnail'] && file_exists('../' . $course['thumbnail'])) {
        unlink('../' . $course['thumbnail']);
    }
    
    $stmt = $pdo->prepare("DELETE FROM academic_courses WHERE id = ?");
    $stmt->execute([$id]);
    
    $_SESSION['success'] = "Course deleted successfully!";
    header("Location: academics.php");
    exit;
}

// TOGGLE FEATURED
if (isset($_GET['toggle_featured'])) {
    $id = $_GET['toggle_featured'];
    $stmt = $pdo->prepare("UPDATE academic_courses SET featured = NOT featured WHERE id = ?");
    $stmt->execute([$id]);
    header("Location: academics.php");
    exit;
}

// FETCH COURSES
$search = $_GET['search'] ?? '';
$school_filter = $_GET['school'] ?? '';
$class_filter = $_GET['class'] ?? '';
$subject_filter = $_GET['subject'] ?? '';

$sql = "SELECT ac.*, 
        s.name as school_name,
        (SELECT COUNT(*) FROM academic_chapters WHERE course_id = ac.id) as chapters_count,
        (SELECT COUNT(*) FROM academic_enrollments WHERE course_id = ac.id) as students_count
        FROM academic_courses ac
        LEFT JOIN schools s ON ac.school_id = s.id
        WHERE 1=1";
$params = [];

if ($search) {
    $sql .= " AND (ac.title LIKE ? OR ac.description LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}
if ($school_filter) {
    $sql .= " AND ac.school_id = ?";
    $params[] = $school_filter;
}
if ($class_filter) {
    $sql .= " AND ac.class = ?";
    $params[] = $class_filter;
}
if ($subject_filter) {
    $sql .= " AND ac.subject = ?";
    $params[] = $subject_filter;
}

$sql .= " ORDER BY ac.featured DESC, ac.created_at DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$courses = $stmt->fetchAll();

// Get unique values for filters
$schools_filter = $pdo->query("SELECT id, name FROM schools ORDER BY name")->fetchAll();
$classes_filter = ['6th', '7th', '8th', '9th', '10th'];
$subjects_filter = $all_subjects;

// If editing, fetch course details
$edit_course = null;
if ($action == 'edit' && $course_id) {
    $stmt = $pdo->prepare("SELECT * FROM academic_courses WHERE id = ?");
    $stmt->execute([$course_id]);
    $edit_course = $stmt->fetch();
}

// Get statistics
$stats = [
    'total' => $pdo->query("SELECT COUNT(*) FROM academic_courses")->fetchColumn(),
    'published' => $pdo->query("SELECT COUNT(*) FROM academic_courses WHERE status = 'published'")->fetchColumn(),
    'draft' => $pdo->query("SELECT COUNT(*) FROM academic_courses WHERE status = 'draft'")->fetchColumn(),
    'featured' => $pdo->query("SELECT COUNT(*) FROM academic_courses WHERE featured = 1")->fetchColumn(),
];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Academic Courses Management</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { font-family: 'Inter', sans-serif; }
        .gradient-bg { 
            background: linear-gradient(135deg, #1e3c72 0%, #0a1929 100%);
        }
        body {
            background-color: #0a1929;
        }
        .course-card {
            transition: all 0.3s ease;
            background: rgba(255, 255, 255, 0.05);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.1);
        }
        .course-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 20px 25px -5px rgba(0,0,0,0.5), 0 10px 10px -5px rgba(0,0,0,0.4);
            background: rgba(255, 255, 255, 0.08);
        }
        .status-badge {
            transition: all 0.2s ease;
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
        }
        .compact-input:focus {
            border-color: #3b82f6;
            ring-color: #3b82f6;
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
        }
        .form-section {
            transition: all 0.3s ease;
        }
    </style>
</head>
<body class="bg-[#0a1929]">

<div class="flex min-h-screen">

    <!-- SIDEBAR -->
    <?php include 'sidebar.php'; ?>

    <!-- MAIN CONTENT -->
    <div class="flex-1 flex flex-col ">

        <!-- HEADER -->
        <div class="bg-[#0f2744] shadow-lg px-6 py-4 sticky top-0 z-10 border-b border-gray-800">
            <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                <div>
                    <h1 class="text-2xl font-bold text-white flex items-center">
                        <i class="fas fa-robot text-blue-400 mr-3"></i>
                        AI & Tech Courses Management
                    </h1>
                    <p class="text-sm text-gray-400 mt-1">Classes 6-10 • All Subjects • School-wise</p>
                </div>
                <div class="flex items-center space-x-3">
                    <a href="?action=add#course-form" class="gradient-bg text-white px-4 py-2 rounded-lg hover:opacity-90 transition flex items-center text-sm">
                        <i class="fas fa-plus mr-2"></i>
                        Add New Course
                    </a>
                </div>
            </div>
        </div>

        <!-- CONTENT -->
        <div class="p-6 space-y-6" id="top">

            <!-- STATS CARDS - Compact -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
                <div class="compact-card rounded-lg p-4">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-xs text-gray-400 uppercase tracking-wider">Total Courses</p>
                            <p class="text-xl font-bold text-white"><?= $stats['total'] ?></p>
                        </div>
                        <div class="w-10 h-10 bg-blue-900 bg-opacity-30 rounded-lg flex items-center justify-center">
                            <i class="fas fa-book text-blue-400 text-lg"></i>
                        </div>
                    </div>
                </div>
                
                <div class="compact-card rounded-lg p-4">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-xs text-gray-400 uppercase tracking-wider">Published</p>
                            <p class="text-xl font-bold text-green-400"><?= $stats['published'] ?></p>
                        </div>
                        <div class="w-10 h-10 bg-green-900 bg-opacity-30 rounded-lg flex items-center justify-center">
                            <i class="fas fa-check-circle text-green-400 text-lg"></i>
                        </div>
                    </div>
                </div>
                
                <div class="compact-card rounded-lg p-4">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-xs text-gray-400 uppercase tracking-wider">Draft</p>
                            <p class="text-xl font-bold text-yellow-400"><?= $stats['draft'] ?></p>
                        </div>
                        <div class="w-10 h-10 bg-yellow-900 bg-opacity-30 rounded-lg flex items-center justify-center">
                            <i class="fas fa-pen text-yellow-400 text-lg"></i>
                        </div>
                    </div>
                </div>
                
                <div class="compact-card rounded-lg p-4">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-xs text-gray-400 uppercase tracking-wider">Featured</p>
                            <p class="text-xl font-bold text-purple-400"><?= $stats['featured'] ?></p>
                        </div>
                        <div class="w-10 h-10 bg-purple-900 bg-opacity-30 rounded-lg flex items-center justify-center">
                            <i class="fas fa-star text-purple-400 text-lg"></i>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ALERTS -->
            <?php if ($error): ?>
                <div class="bg-red-900 bg-opacity-20 border border-red-800 text-red-400 px-4 py-2 rounded-lg flex items-center text-sm">
                    <i class="fas fa-exclamation-circle mr-2"></i>
                    <?= $error ?>
                </div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="bg-green-900 bg-opacity-20 border border-green-800 text-green-400 px-4 py-2 rounded-lg flex items-center text-sm">
                    <i class="fas fa-check-circle mr-2"></i>
                    <?= $success ?>
                </div>
            <?php endif; ?>

            <?php if (isset($_SESSION['success'])): ?>
                <div class="bg-green-900 bg-opacity-20 border border-green-800 text-green-400 px-4 py-2 rounded-lg flex items-center text-sm">
                    <i class="fas fa-check-circle mr-2"></i>
                    <?= $_SESSION['success'] ?>
                </div>
                <?php unset($_SESSION['success']); ?>
            <?php endif; ?>

            <!-- ADD/EDIT COURSE FORM - Always at top -->
            <div id="course-form" class="scroll-mt-20">
                <?php if ($action == 'add' || $action == 'edit'): ?>
                <div class="compact-card rounded-lg overflow-hidden mb-6">
                    <div class="p-4 border-b border-gray-800 bg-[#0f2744]">
                        <h2 class="text-base font-semibold text-white flex items-center">
                            <i class="fas fa-<?= $action == 'add' ? 'plus-circle' : 'edit' ?> text-blue-400 mr-2"></i>
                            <?= $action == 'add' ? 'Add New AI/Tech Course' : 'Edit Course' ?>
                        </h2>
                    </div>
                    
                    <form method="POST" enctype="multipart/form-data" class="p-4">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                            <div class="md:col-span-2">
                                <label class="compact-label block mb-1">Course Title *</label>
                                <input type="text" name="title" list="ai-courses" value="<?= htmlspecialchars($edit_course['title'] ?? '') ?>" 
                                       class="compact-input w-full rounded"
                                       placeholder="e.g., Python Development with AI" required>
                                <datalist id="ai-courses">
                                    <?php foreach ($ai_courses as $course_name): ?>
                                        <option value="<?= $course_name ?>">
                                    <?php endforeach; ?>
                                </datalist>
                            </div>
                            
                            <div>
                                <label class="compact-label block mb-1">School *</label>
                                <select name="school_id" class="compact-input w-full rounded" required>
                                    <option value="" class="bg-gray-900">Select School</option>
                                    <?php foreach ($schools as $school): ?>
                                        <option value="<?= $school['id'] ?>" <?= ($edit_course['school_id'] ?? '') == $school['id'] ? 'selected' : '' ?> class="bg-gray-900">
                                            <?= htmlspecialchars($school['name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div>
                                <label class="compact-label block mb-1">Class *</label>
                                <select name="class" class="compact-input w-full rounded" required>
                                    <option value="" class="bg-gray-900">Select Class</option>
                                    <?php for($i = 6; $i <= 10; $i++): ?>
                                        <option value="<?= $i ?>th" <?= ($edit_course['class'] ?? '') == $i.'th' ? 'selected' : '' ?> class="bg-gray-900">
                                            <?= $i ?>th Class
                                        </option>
                                    <?php endfor; ?>
                                </select>
                            </div>
                            
                            <div>
                                <label class="compact-label block mb-1">Subject *</label>
                                <select name="subject" class="compact-input w-full rounded" required>
                                    <option value="" class="bg-gray-900">Select Subject</option>
                                    <?php foreach ($all_subjects as $subject): ?>
                                        <option value="<?= $subject ?>" <?= ($edit_course['subject'] ?? '') == $subject ? 'selected' : '' ?> class="bg-gray-900">
                                            <?= $subject ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div>
                                <label class="compact-label block mb-1">Duration</label>
                                <input type="text" name="duration" value="<?= htmlspecialchars($edit_course['duration'] ?? '12 weeks') ?>" 
                                       placeholder="e.g., 12 weeks"
                                       class="compact-input w-full rounded">
                            </div>
                            
                            <div>
                                <label class="compact-label block mb-1">Status</label>
                                <select name="status" class="compact-input w-full rounded">
                                    <option value="draft" <?= ($edit_course['status'] ?? '') == 'draft' ? 'selected' : '' ?> class="bg-gray-900">Draft</option>
                                    <option value="published" <?= ($edit_course['status'] ?? '') == 'published' ? 'selected' : '' ?> class="bg-gray-900">Published</option>
                                </select>
                            </div>
                            
                            <div class="md:col-span-2">
                                <label class="compact-label block mb-1">Description</label>
                                <textarea name="description" rows="2" 
                                          class="compact-input w-full rounded" 
                                          placeholder="Brief course description..."><?= htmlspecialchars($edit_course['description'] ?? '') ?></textarea>
                            </div>
                            
                            <div>
                                <label class="compact-label block mb-1">Thumbnail</label>
                                <input type="file" name="thumbnail" accept="image/*" class="compact-input w-full rounded text-sm">
                                <?php if ($edit_course && $edit_course['thumbnail']): ?>
                                    <div class="mt-2">
                                        <img src="../<?= $edit_course['thumbnail'] ?>" alt="Thumbnail" class="h-12 rounded">
                                    </div>
                                <?php endif; ?>
                            </div>
                            
                            <div class="flex items-center">
                                <label class="flex items-center space-x-2">
                                    <input type="checkbox" name="featured" value="1" <?= ($edit_course['featured'] ?? 0) ? 'checked' : '' ?> 
                                           class="w-3.5 h-3.5 text-blue-600 bg-gray-800 border-gray-600 rounded focus:ring-blue-500">
                                    <span class="text-xs text-gray-300">Feature this course</span>
                                </label>
                            </div>
                        </div>
                        
                        <div class="flex justify-end space-x-2 mt-4 pt-3 border-t border-gray-800">
                            <a href="academics.php" class="px-4 py-1.5 border border-gray-700 rounded text-xs text-gray-300 hover:bg-gray-800 transition">
                                Cancel
                            </a>
                            <button type="submit" name="save_course" class="px-4 py-1.5 bg-blue-600 text-white rounded text-xs hover:bg-blue-700 transition">
                                <i class="fas fa-save mr-1"></i>
                                <?= $action == 'add' ? 'Save Course' : 'Update Course' ?>
                            </button>
                        </div>
                    </form>
                </div>
                <?php endif; ?>
            </div>

            <!-- FILTERS AND SEARCH - Compact -->
            <div class="compact-card rounded-lg p-3">
                <form method="GET" class="flex flex-wrap gap-2 items-center">
                    <div class="flex-1 min-w-[150px]">
                        <div class="relative">
                            <i class="fas fa-search absolute left-2 top-1/2 transform -translate-y-1/2 text-gray-500 text-xs"></i>
                            <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" 
                                   placeholder="Search courses..." 
                                   class="compact-input w-full pl-7 pr-3 py-1.5 rounded text-sm">
                        </div>
                    </div>
                    
                    <select name="school" class="compact-input px-3 py-1.5 rounded text-sm">
                        <option value="" class="bg-gray-900">All Schools</option>
                        <?php foreach ($schools_filter as $school): ?>
                            <option value="<?= $school['id'] ?>" <?= $school_filter == $school['id'] ? 'selected' : '' ?> class="bg-gray-900">
                                <?= htmlspecialchars($school['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    
                    <select name="class" class="compact-input px-3 py-1.5 rounded text-sm">
                        <option value="" class="bg-gray-900">All Classes</option>
                        <?php foreach ($classes_filter as $class): ?>
                            <option value="<?= $class ?>" <?= $class_filter == $class ? 'selected' : '' ?> class="bg-gray-900">
                                Class <?= $class ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    
                    <select name="subject" class="compact-input px-3 py-1.5 rounded text-sm">
                        <option value="" class="bg-gray-900">All Subjects</option>
                        <?php foreach ($subjects_filter as $subject): ?>
                            <option value="<?= $subject ?>" <?= $subject_filter == $subject ? 'selected' : '' ?> class="bg-gray-900">
                                <?= $subject ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    
                    <button type="submit" class="px-4 py-1.5 bg-gray-700 text-white rounded text-xs hover:bg-gray-600 transition">
                        <i class="fas fa-filter mr-1"></i>
                        Filter
                    </button>
                    
                    <a href="academics.php" class="px-4 py-1.5 border border-gray-700 rounded text-xs text-gray-300 hover:bg-gray-800 transition">
                        <i class="fas fa-times mr-1"></i>
                        Clear
                    </a>
                </form>
            </div>

            <!-- COURSES GRID - Always below form -->
            <div class="compact-card rounded-lg overflow-hidden">
                <div class="p-4 border-b border-gray-800 bg-[#0f2744]">
                    <h2 class="text-base font-semibold text-white flex items-center">
                        <i class="fas fa-robot text-blue-400 mr-2"></i>
                        AI & Technology Courses (<?= count($courses) ?>)
                    </h2>
                </div>
                
                <div class="p-4">
                    <?php if (count($courses) > 0): ?>
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                            <?php foreach ($courses as $course): ?>
                                <div class="course-card rounded-lg overflow-hidden">
                                    <!-- Thumbnail -->
                                    <div class="h-28 bg-gradient-to-r from-blue-900 to-purple-900 relative">
                                        <?php if ($course['thumbnail']): ?>
                                            <img src="../<?= htmlspecialchars($course['thumbnail']) ?>" alt="Thumbnail" class="w-full h-full object-cover opacity-80">
                                        <?php else: ?>
                                            <div class="w-full h-full flex items-center justify-center">
                                                <i class="fas fa-robot text-white text-3xl opacity-30"></i>
                                            </div>
                                        <?php endif; ?>
                                        
                                        <!-- Status Badge -->
                                        <div class="absolute top-2 right-2">
                                            <span class="status-badge px-1.5 py-0.5 text-2xs font-medium rounded-full 
                                                <?= $course['status'] == 'published' ? 'bg-green-900 bg-opacity-80 text-green-300' : 
                                                   'bg-yellow-900 bg-opacity-80 text-yellow-300' ?>">
                                                <?= ucfirst($course['status']) ?>
                                            </span>
                                        </div>
                                        
                                        <!-- Featured Badge -->
                                        <?php if ($course['featured']): ?>
                                            <div class="absolute top-2 left-2">
                                                <span class="bg-yellow-600 bg-opacity-80 text-yellow-100 text-2xs font-medium px-1.5 py-0.5 rounded-full flex items-center">
                                                    <i class="fas fa-star mr-1 text-2xs"></i>
                                                    Featured
                                                </span>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <!-- Content -->
                                    <div class="p-3">
                                        <div class="flex items-start justify-between">
                                            <h3 class="font-semibold text-white text-sm mb-1"><?= htmlspecialchars($course['title']) ?></h3>
                                        </div>
                                        
                                        <div class="flex items-center space-x-2 text-2xs text-gray-400 mb-1">
                                            <span class="flex items-center bg-blue-900 bg-opacity-30 px-1.5 py-0.5 rounded">
                                                <i class="fas fa-school mr-1 text-blue-400"></i>
                                                <?= htmlspecialchars($course['school_name'] ?? 'No School') ?>
                                            </span>
                                        </div>
                                        
                                        <div class="flex items-center space-x-2 text-2xs text-gray-400 mb-2">
                                            <span class="flex items-center">
                                                <i class="fas fa-layer-group mr-1 text-blue-400"></i>
                                                Class <?= htmlspecialchars($course['class']) ?>
                                            </span>
                                            <span>•</span>
                                            <span class="flex items-center">
                                                <i class="fas fa-book mr-1 text-green-400"></i>
                                                <?= htmlspecialchars($course['subject']) ?>
                                            </span>
                                        </div>
                                        
                                        <p class="text-gray-400 text-2xs line-clamp-2 mb-2">
                                            <?= htmlspecialchars(substr($course['description'] ?? '', 0, 80)) ?>...
                                        </p>
                                        
                                        <!-- Stats -->
                                        <div class="flex items-center justify-between text-2xs text-gray-500 mb-2">
                                            <span><i class="far fa-clock mr-1"></i> <?= $course['duration'] ?? 'N/A' ?></span>
                                            <span><i class="fas fa-video mr-1"></i> <?= $course['chapters_count'] ?> Ch</span>
                                            <span><i class="fas fa-users mr-1"></i> <?= $course['students_count'] ?> Std</span>
                                        </div>
                                        
                                        <!-- Actions -->
<!-- DEBUG: Check the generated URL -->
<?php 
$debug_url = "academics-quizzes.php?course_id=" . $course['id'];
echo "<!-- DEBUG URL: " . $debug_url . " -->";
echo "<!-- DEBUG Full Path: " . __DIR__ . "/" . $debug_url . " -->";
?>

<!-- Actions -->
<div class="flex items-center justify-between pt-3 border-t border-gray-800">
    <div class="flex space-x-3">
        <?php
        $chapters_url = "academics-chapters.php?course_id=" . $course['id'];
        $materials_url = "academics-materials.php?course_id=" . $course['id'];
        $quizzes_url = "academics-quizzes.php?course_id=" . $course['id'];
        ?>
        <a href="<?php echo $chapters_url; ?>" 
           class="text-blue-400 hover:text-blue-300 text-2xs font-medium">
            <i class="fas fa-video mr-1"></i> Chapters
        </a>
        <a href="<?php echo $materials_url; ?>" 
           class="text-green-400 hover:text-green-300 text-2xs font-medium">
            <i class="fas fa-file-pdf mr-1"></i> Materials
        </a>
        <a href="<?php echo $quizzes_url; ?>" 
           class="text-yellow-200 hover:text-pink-300 text-2xs font-medium" 
           onclick="console.log('Quiz link clicked: ' + this.href); return true;">
            <i class="fas fa-puzzle-piece mr-1"></i> Quizzes
        </a>
    </div>
    <!-- rest of your code -->
</div>                           </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-8">
                            <div class="w-16 h-16 bg-gray-800 rounded-full mx-auto mb-3 flex items-center justify-center">
                                <i class="fas fa-robot text-gray-600 text-2xl"></i>
                            </div>
                            <h3 class="text-base font-medium text-gray-300 mb-1">No AI/Tech courses found</h3>
                            <p class="text-xs text-gray-500 mb-4">Add your first cutting-edge technology course</p>
                            <a href="?action=add#course-form" class="inline-flex items-center px-4 py-2 bg-blue-600 text-white rounded text-xs hover:bg-blue-700 transition">
                                <i class="fas fa-plus mr-2"></i>
                                Add New Course
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

        </div>

    </div>

</div>

<script>
    // Smooth scroll to form when adding/editing
    if (window.location.hash === '#course-form') {
        document.getElementById('course-form').scrollIntoView({ behavior: 'smooth' });
    }
</script>

<!-- Font Awesome -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/js/all.min.js"></script>
</body>
</html>