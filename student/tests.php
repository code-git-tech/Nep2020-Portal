<?php
require_once '../includes/auth.php';
require_once '../includes/student-functions.php';
require_once '../includes/auto_publish.php';
requireStudent();

// Run auto-publisher on every page load
if (function_exists('autoPublishVideos')) {
    autoPublishVideos($pdo);
}

$user_id = $_SESSION['user_id'];
$course_filter = $_GET['course'] ?? null;
$class_filter = $_GET['class'] ?? null;
$subject_filter = $_GET['subject'] ?? null;

// Get student details with class and school
$stmt = $pdo->prepare("SELECT u.*, s.name as school_name FROM users u LEFT JOIN schools s ON u.school_id = s.id WHERE u.id = ?");
$stmt->execute([$user_id]);
$student = $stmt->fetch();

$student_class = $student['class'] ?? null; // e.g., "10th"
$student_school_id = $student['school_id'] ?? null;

// Get all available tests with detailed information based on student's class
$tests = [];

// Check if tables exist
$tables_exist = [
    'academic_tests' => $pdo->query("SHOW TABLES LIKE 'academic_tests'")->rowCount() > 0,
    'academic_questions' => $pdo->query("SHOW TABLES LIKE 'academic_questions'")->rowCount() > 0,
    'test_attempts' => $pdo->query("SHOW TABLES LIKE 'test_attempts'")->rowCount() > 0,
    'academic_courses' => $pdo->query("SHOW TABLES LIKE 'academic_courses'")->rowCount() > 0,
    'courses' => $pdo->query("SHOW TABLES LIKE 'courses'")->rowCount() > 0
];

if ($tables_exist['academic_tests']) {
    // Get tests from academic courses based on student's class
    $sql = "SELECT 
                t.*, 
                c.title as course_title,
                c.id as course_id,
                c.class,
                c.subject,
                c.school_id,
                'academic' as course_type,
                (SELECT COUNT(*) FROM academic_questions WHERE test_id = t.id) as total_questions,
                (SELECT COUNT(*) FROM academic_questions WHERE test_id = t.id AND question_type = 'multiple_choice') as mcq_count,
                (SELECT COUNT(*) FROM academic_questions WHERE test_id = t.id AND question_type = 'true_false') as tf_count,
                (SELECT COUNT(*) FROM academic_questions WHERE test_id = t.id AND question_type = 'fill_blank') as fb_count,
                (SELECT COUNT(*) FROM test_attempts WHERE test_id = t.id AND student_id = ? AND passed = 1) as already_passed,
                (SELECT COUNT(*) FROM test_attempts WHERE test_id = t.id AND student_id = ?) as attempts_count,
                (SELECT MAX(score) FROM test_attempts WHERE test_id = t.id AND student_id = ?) as best_score
            FROM academic_tests t
            JOIN academic_courses c ON t.course_id = c.id
            WHERE t.status = 'published'";
    
    $params = [$user_id, $user_id, $user_id];
    
    // Filter by student's class
    if ($student_class) {
        $sql .= " AND c.class = ?";
        $params[] = $student_class;
    }
    
    // Filter by student's school if applicable
    if ($student_school_id) {
        $sql .= " AND (c.school_id = ? OR c.school_id IS NULL)";
        $params[] = $student_school_id;
    }
    
    // Apply additional filters if provided
    if ($class_filter) {
        $sql .= " AND c.class = ?";
        $params[] = $class_filter;
    }
    
    if ($subject_filter) {
        $sql .= " AND c.subject = ?";
        $params[] = $subject_filter;
    }
    
    if ($course_filter) {
        $sql .= " AND t.course_id = ?";
        $params[] = $course_filter;
    }
    
    $sql .= " ORDER BY c.class, c.subject, t.created_at DESC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $academic_tests = $stmt->fetchAll();
    $tests = array_merge($tests, $academic_tests);
}

// Also get tests from regular courses if they exist
// Also get tests from regular courses if they exist
$tests_table_exists = $pdo->query("SHOW TABLES LIKE 'tests'")->rowCount() > 0;

if ($tests_table_exists) {
    $sql2 = "SELECT 
                t.*, 
                c.title as course_title,
                c.id as course_id,
                NULL as class,
                NULL as subject,
                NULL as school_id,
                'regular' as course_type,
                (SELECT COUNT(*) FROM questions WHERE test_id = t.id) as total_questions,
                (SELECT COUNT(*) FROM test_attempts WHERE test_id = t.id AND student_id = ? AND passed = 1) as already_passed,
                (SELECT COUNT(*) FROM test_attempts WHERE test_id = t.id AND student_id = ?) as attempts_count,
                (SELECT MAX(score) FROM test_attempts WHERE test_id = t.id AND student_id = ?) as best_score
            FROM tests t
            JOIN courses c ON t.course_id = c.id
            JOIN enrollments e ON c.id = e.course_id
            WHERE e.student_id = ? 
                AND e.status = 'active'
                AND t.status = 'published'";
    
    $params2 = [$user_id, $user_id, $user_id, $user_id];
    
    if ($course_filter) {
        $sql2 .= " AND t.course_id = ?";
        $params2[] = $course_filter;
    }
    
    $sql2 .= " ORDER BY t.created_at DESC";
    
    $stmt2 = $pdo->prepare($sql2);
    $stmt2->execute($params2);
    $regular_tests = $stmt2->fetchAll();
    $tests = array_merge($tests, $regular_tests);
}
// Get unique classes and subjects for filters
$classes = [];
$subjects = [];

if ($tables_exist['academic_courses']) {
    // Get classes based on student's level
    $class_sql = "SELECT DISTINCT class FROM academic_courses WHERE status = 'published'";
    if ($student_class) {
        // Show classes around student's level (e.g., if student is in 10th, show 9th, 10th, 11th)
        $class_num = (int)filter_var($student_class, FILTER_SANITIZE_NUMBER_INT);
        $class_sql .= " AND CAST(REPLACE(class, 'th', '') AS UNSIGNED) BETWEEN " . ($class_num - 1) . " AND " . ($class_num + 1);
    }
    $class_sql .= " ORDER BY class";
    $stmt = $pdo->query($class_sql);
    $classes = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    // Get subjects based on student's class
    $subject_sql = "SELECT DISTINCT subject FROM academic_courses WHERE status = 'published'";
    if ($student_class) {
        $subject_sql .= " AND class = ?";
        $stmt = $pdo->prepare($subject_sql);
        $stmt->execute([$student_class]);
    } else {
        $stmt = $pdo->query($subject_sql);
    }
    $subjects = $stmt->fetchAll(PDO::FETCH_COLUMN);
}

// Get enrolled courses for filter dropdown
$enrolled_courses = [];
$stmt = $pdo->prepare("
    SELECT c.id, c.title 
    FROM courses c
    JOIN enrollments e ON c.id = e.course_id
    WHERE e.student_id = ? AND e.status = 'active'
    ORDER BY c.title
");
$stmt->execute([$user_id]);
$enrolled_courses = $stmt->fetchAll();

// Get statistics
$stats = [
    'total_tests' => count($tests),
    'completed' => count(array_filter($tests, fn($t) => $t['already_passed'] > 0)),
    'pending' => count(array_filter($tests, fn($t) => $t['already_passed'] == 0)),
    'avg_score' => 0,
    'by_class' => [],
    'by_subject' => []
];

$scores = array_filter(array_column($tests, 'best_score'));
if (!empty($scores)) {
    $stats['avg_score'] = round(array_sum($scores) / count($scores));
}

// Group tests by class and subject for stats
foreach ($tests as $test) {
    if ($test['course_type'] == 'academic' && isset($test['class'])) {
        $class = $test['class'];
        $subject = $test['subject'];
        
        if (!isset($stats['by_class'][$class])) {
            $stats['by_class'][$class] = 0;
        }
        $stats['by_class'][$class]++;
        
        if (!isset($stats['by_subject'][$subject])) {
            $stats['by_subject'][$subject] = 0;
        }
        $stats['by_subject'][$subject]++;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tests & Quizzes</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { font-family: 'Inter', sans-serif; }
        body { background-color: #f8fafc; }
        .gradient-bg { 
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
        .test-card {
            transition: all 0.3s ease;
            border: 1px solid #e2e8f0;
        }
        .test-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 20px 25px -5px rgba(0,0,0,0.1), 0 10px 10px -5px rgba(0,0,0,0.04);
        }
        .stat-card {
            background: white;
            border: 1px solid #e2e8f0;
        }
        .type-badge {
            padding: 0.25rem 0.75rem;
            border-radius: 9999px;
            font-size: 0.7rem;
            font-weight: 500;
        }
        .class-badge {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
    </style>
</head>
<body class="bg-gray-50">
    <div class="flex h-screen">
        <?php include 'sidebar.php'; ?>

        <div class="flex-1 overflow-auto">
            <?php include 'header.php'; ?>

            <div class="p-8">
                <!-- Welcome Section with Class Info -->
                <div class="mb-8">
                    <h1 class="text-3xl font-bold text-gray-800 mb-2">Tests & Quizzes</h1>
                    <div class="flex items-center space-x-4">
                        <p class="text-gray-500">Assess your knowledge and track your progress</p>
                        <?php if ($student_class): ?>
                            <span class="class-badge text-white px-4 py-1 rounded-full text-sm">
                                <i class="fas fa-graduation-cap mr-2"></i>Class <?= $student_class ?>
                            </span>
                        <?php endif; ?>
                        <?php if ($student['school_name']): ?>
                            <span class="bg-blue-100 text-blue-700 px-4 py-1 rounded-full text-sm">
                                <i class="fas fa-school mr-2"></i><?= htmlspecialchars($student['school_name']) ?>
                            </span>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Statistics Cards -->
                <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-8">
                    <div class="stat-card rounded-xl p-4">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm text-gray-500">Total Tests</p>
                                <p class="text-2xl font-bold text-gray-800"><?= $stats['total_tests'] ?></p>
                            </div>
                            <div class="w-10 h-10 bg-purple-100 rounded-lg flex items-center justify-center">
                                <i class="fas fa-file-alt text-purple-600"></i>
                            </div>
                        </div>
                    </div>
                    
                    <div class="stat-card rounded-xl p-4">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm text-gray-500">Completed</p>
                                <p class="text-2xl font-bold text-green-600"><?= $stats['completed'] ?></p>
                            </div>
                            <div class="w-10 h-10 bg-green-100 rounded-lg flex items-center justify-center">
                                <i class="fas fa-check-circle text-green-600"></i>
                            </div>
                        </div>
                    </div>
                    
                    <div class="stat-card rounded-xl p-4">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm text-gray-500">Pending</p>
                                <p class="text-2xl font-bold text-yellow-600"><?= $stats['pending'] ?></p>
                            </div>
                            <div class="w-10 h-10 bg-yellow-100 rounded-lg flex items-center justify-center">
                                <i class="fas fa-clock text-yellow-600"></i>
                            </div>
                        </div>
                    </div>
                    
                    <div class="stat-card rounded-xl p-4">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm text-gray-500">Avg. Score</p>
                                <p class="text-2xl font-bold text-blue-600"><?= $stats['avg_score'] ?>%</p>
                            </div>
                            <div class="w-10 h-10 bg-blue-100 rounded-lg flex items-center justify-center">
                                <i class="fas fa-star text-blue-600"></i>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Class-wise Stats (if available) -->
                <?php if (!empty($stats['by_class'])): ?>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
                    <div class="bg-white rounded-xl shadow-sm p-4">
                        <h3 class="text-sm font-semibold text-gray-700 mb-3">Tests by Class</h3>
                        <div class="space-y-2">
                            <?php foreach ($stats['by_class'] as $class => $count): ?>
                                <div class="flex items-center justify-between">
                                    <span class="text-sm text-gray-600">Class <?= $class ?></span>
                                    <span class="text-sm font-semibold text-purple-600"><?= $count ?> tests</span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    
                    <div class="bg-white rounded-xl shadow-sm p-4">
                        <h3 class="text-sm font-semibold text-gray-700 mb-3">Tests by Subject</h3>
                        <div class="space-y-2">
                            <?php foreach (array_slice($stats['by_subject'], 0, 5) as $subject => $count): ?>
                                <div class="flex items-center justify-between">
                                    <span class="text-sm text-gray-600"><?= $subject ?></span>
                                    <span class="text-sm font-semibold text-blue-600"><?= $count ?> tests</span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Filters -->
                <div class="bg-white rounded-xl shadow-sm p-4 mb-6">
                    <form method="GET" class="flex flex-wrap gap-4 items-center">
                        <div class="flex-1 min-w-[200px]">
                            <div class="relative">
                                <i class="fas fa-search absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400"></i>
                                <input type="text" name="search" id="searchInput" placeholder="Search tests..." 
                                       value="<?= htmlspecialchars($_GET['search'] ?? '') ?>"
                                       class="w-full pl-10 pr-4 py-2 border border-gray-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500">
                            </div>
                        </div>
                        
                        <?php if (!empty($classes)): ?>
                        <select name="class" class="px-4 py-2 border border-gray-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500">
                            <option value="">All Classes</option>
                            <?php foreach ($classes as $class): ?>
                                <option value="<?= $class ?>" <?= $class_filter == $class ? 'selected' : '' ?>>
                                    Class <?= $class ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <?php endif; ?>
                        
                        <?php if (!empty($subjects)): ?>
                        <select name="subject" class="px-4 py-2 border border-gray-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500">
                            <option value="">All Subjects</option>
                            <?php foreach ($subjects as $subject): ?>
                                <option value="<?= $subject ?>" <?= $subject_filter == $subject ? 'selected' : '' ?>>
                                    <?= $subject ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <?php endif; ?>
                        
                        <?php if (!empty($enrolled_courses)): ?>
                        <select name="course" class="px-4 py-2 border border-gray-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500">
                            <option value="">All Courses</option>
                            <?php foreach ($enrolled_courses as $course): ?>
                                <option value="<?= $course['id'] ?>" <?= $course_filter == $course['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($course['title']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <?php endif; ?>
                        
                        <select name="status" class="px-4 py-2 border border-gray-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500">
                            <option value="">All Status</option>
                            <option value="pending" <?= ($_GET['status'] ?? '') == 'pending' ? 'selected' : '' ?>>Pending</option>
                            <option value="completed" <?= ($_GET['status'] ?? '') == 'completed' ? 'selected' : '' ?>>Completed</option>
                        </select>
                        
                        <button type="submit" class="px-6 py-2 bg-purple-600 text-white rounded-lg hover:bg-purple-700 transition">
                            <i class="fas fa-filter mr-2"></i>Apply Filters
                        </button>
                        
                        <a href="tests.php" class="px-6 py-2 border border-gray-300 rounded-lg text-gray-600 hover:bg-gray-50 transition">
                            <i class="fas fa-times mr-2"></i>Clear
                        </a>
                    </form>
                </div>

                <!-- Tests Grid -->
                <?php if (empty($tests)): ?>
                    <div class="bg-white rounded-xl shadow-sm p-16 text-center">
                        <div class="w-24 h-24 bg-purple-100 rounded-full mx-auto mb-4 flex items-center justify-center">
                            <i class="fas fa-file-alt text-purple-400 text-3xl"></i>
                        </div>
                        <h3 class="text-xl font-semibold text-gray-800 mb-2">No Tests Available</h3>
                        <p class="text-gray-500 max-w-md mx-auto">
                            There are no tests available for your class (<?= $student_class ?>) yet. 
                            Check back later or contact your instructor.
                        </p>
                        <?php if (!$student_class): ?>
                            <p class="text-sm text-yellow-600 mt-2">
                                <i class="fas fa-info-circle mr-1"></i>
                                Please update your profile with your class to see relevant tests.
                            </p>
                            <a href="profile.php" class="inline-block mt-4 px-6 py-2 bg-purple-600 text-white rounded-lg hover:bg-purple-700 transition">
                                Update Profile
                            </a>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <div id="testsGrid" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                      <?php foreach ($tests as $testItem): ?>
    <div class="test-card bg-white rounded-xl shadow-sm overflow-hidden" 
         data-course="<?= $testItem['course_id'] ?>"
         data-class="<?= $testItem['class'] ?? '' ?>"
         data-subject="<?= $testItem['subject'] ?? '' ?>"
         data-status="<?= $testItem['already_passed'] > 0 ? 'completed' : 'pending' ?>"
         data-title="<?= strtolower($testItem['title']) ?>">
        
        <!-- Card Header -->
        <div class="h-2 <?= $testItem['course_type'] == 'academic' ? 'bg-gradient-to-r from-purple-500 to-pink-500' : 'bg-gradient-to-r from-blue-500 to-green-500' ?>"></div>
        
        <div class="p-6">
            <!-- Title and Status -->
            <div class="flex items-start justify-between mb-3">
                <h3 class="text-lg font-bold text-gray-800 flex-1"><?= htmlspecialchars($testItem['title']) ?></h3>
                <?php if ($testItem['already_passed'] > 0): ?>
                    <span class="px-3 py-1 bg-green-100 text-green-700 text-xs font-medium rounded-full flex items-center">
                        <i class="fas fa-check-circle mr-1"></i> Passed
                    </span>
                <?php else: ?>
                    <span class="px-3 py-1 bg-yellow-100 text-yellow-700 text-xs font-medium rounded-full flex items-center">
                        <i class="fas fa-clock mr-1"></i> Pending
                    </span>
                <?php endif; ?>
            </div>
            
            <!-- Course Info -->
            <div class="flex items-center text-sm text-gray-500 mb-2">
                <i class="fas fa-book-open mr-2 text-purple-400"></i>
                <span><?= htmlspecialchars($testItem['course_title']) ?></span>
            </div>
            
            <!-- Class and Subject Badges -->
            <?php if ($testItem['course_type'] == 'academic' && isset($testItem['class'])): ?>
            <div class="flex items-center space-x-2 mb-3">
                <span class="px-2 py-1 bg-purple-100 text-purple-700 text-xs rounded-full">
                    <i class="fas fa-layer-group mr-1"></i>Class <?= $testItem['class'] ?>
                </span>
                <span class="px-2 py-1 bg-blue-100 text-blue-700 text-xs rounded-full">
                    <i class="fas fa-book mr-1"></i><?= $testItem['subject'] ?>
                </span>
            </div>
            <?php endif; ?>
            
            <!-- Question Types -->
            <div class="flex flex-wrap gap-2 mb-4">
                <?php if (isset($testItem['mcq_count']) && $testItem['mcq_count'] > 0): ?>
                    <span class="type-badge bg-blue-100 text-blue-700">
                        <i class="fas fa-list-ul mr-1"></i> MCQ: <?= $testItem['mcq_count'] ?>
                    </span>
                <?php endif; ?>
                <?php if (isset($testItem['tf_count']) && $testItem['tf_count'] > 0): ?>
                    <span class="type-badge bg-green-100 text-green-700">
                        <i class="fas fa-check-circle mr-1"></i> T/F: <?= $testItem['tf_count'] ?>
                    </span>
                <?php endif; ?>
                <?php if (isset($testItem['fb_count']) && $testItem['fb_count'] > 0): ?>
                    <span class="type-badge bg-yellow-100 text-yellow-700">
                        <i class="fas fa-edit mr-1"></i> Fill: <?= $testItem['fb_count'] ?>
                    </span>
                <?php endif; ?>
            </div>
            
            <!-- Test Details -->
            <div class="grid grid-cols-3 gap-2 mb-4 p-3 bg-gray-50 rounded-lg">
                <div class="text-center">
                    <div class="text-sm font-semibold text-gray-800"><?= $testItem['duration'] ?></div>
                    <div class="text-xs text-gray-500">Minutes</div>
                </div>
                <div class="text-center">
                    <div class="text-sm font-semibold text-gray-800"><?= $testItem['total_marks'] ?></div>
                    <div class="text-xs text-gray-500">Marks</div>
                </div>
                <div class="text-center">
                    <div class="text-sm font-semibold text-gray-800"><?= $testItem['total_questions'] ?></div>
                    <div class="text-xs text-gray-500">Questions</div>
                </div>
            </div>
            
            <!-- Previous Attempt Info -->
            <?php if ($testItem['attempts_count'] > 0): ?>
                <div class="mb-4 p-2 bg-purple-50 rounded-lg">
                    <div class="flex items-center justify-between text-sm">
                        <span class="text-gray-600">Previous attempts:</span>
                        <span class="font-semibold text-purple-600"><?= $testItem['attempts_count'] ?></span>
                    </div>
                    <?php if ($testItem['best_score'] > 0): ?>
                        <div class="flex items-center justify-between text-sm mt-1">
                            <span class="text-gray-600">Best score:</span>
                            <span class="font-semibold text-green-600"><?= $testItem['best_score'] ?>/<?= $testItem['total_marks'] ?></span>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
            
            <!-- Action Button - THIS IS THE IMPORTANT PART -->
            <?php if ($testItem['already_passed'] > 0): ?>
                <div class="space-y-2">
                    <button disabled class="w-full px-4 py-3 bg-gray-100 text-gray-400 rounded-lg cursor-not-allowed font-medium">
                        <i class="fas fa-check-circle mr-2"></i>Already Passed
                    </button>
                    <a href="results.php?test_id=<?= $testItem['id'] ?>" 
                       class="block w-full text-center px-4 py-2 border border-purple-600 text-purple-600 rounded-lg hover:bg-purple-50 transition text-sm">
                        View Results
                    </a>
                </div>
            <?php else: ?>
                <!-- FIXED: Using $testItem['id'] instead of $test['id'] -->
                <a href="take-test.php?id=<?= $testItem['id'] ?>"
   class="block w-full text-center px-4 py-3 bg-gradient-to-r from-purple-600 to-pink-600 text-white rounded-lg hover:opacity-90 transition font-medium">
    <i class="fas fa-play-circle mr-2"></i>Start Test
</a>
            <?php endif; ?>
        </div>
    </div>
<?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
        // Client-side filtering (works with the server-side filters)
        document.addEventListener('DOMContentLoaded', function() {
            const searchInput = document.getElementById('searchInput');
            if (searchInput) {
                searchInput.addEventListener('keyup', filterTests);
            }
        });
        
        function filterTests() {
            const searchTerm = document.getElementById('searchInput').value.toLowerCase();
            const cards = document.querySelectorAll('.test-card');
            
            cards.forEach(card => {
                const title = card.dataset.title;
                const show = !searchTerm || title.includes(searchTerm);
                card.style.display = show ? 'block' : 'none';
            });
        }
    </script>
</body>
</html>