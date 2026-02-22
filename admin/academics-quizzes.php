<?php
require_once '../includes/auth.php';
require_once '../includes/auto_publish.php';
requireAdmin();

$course_id = isset($_GET['course_id']) ? (int)$_GET['course_id'] : 0;

// Validate course_id
if ($course_id <= 0) {
    $_SESSION['error'] = "Invalid course ID";
    header("Location: academics.php");
    exit;
}

// Get course details
$stmt = $pdo->prepare("SELECT * FROM academic_courses WHERE id = ?");
$stmt->execute([$course_id]);
$course = $stmt->fetch();

if (!$course) {
    $_SESSION['error'] = "Course not found";
    header("Location: academics.php");
    exit;
}

$message = '';
$error = '';

// Handle quiz operations
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_quiz'])) {
    $title = trim($_POST['title']);
    $description = trim($_POST['description']);
    $duration = intval($_POST['duration']);
    $total_marks = intval($_POST['total_marks']);
    $passing_marks = intval($_POST['passing_marks']);
    $status = $_POST['status'] ?? 'draft';
    
    $stmt = $pdo->prepare("
        INSERT INTO academic_tests (course_id, title, description, duration, total_marks, passing_marks, status) 
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ");
    
    if ($stmt->execute([$course_id, $title, $description, $duration, $total_marks, $passing_marks, $status])) {
        $message = "Quiz added successfully!";
    } else {
        $error = "Failed to add quiz";
    }
}

// Delete Quiz
if (isset($_GET['delete_quiz'])) {
    $id = (int)$_GET['delete_quiz'];
    try {
        // Delete related questions first
        $pdo->prepare("DELETE FROM academic_questions WHERE test_id = ?")->execute([$id]);
        // Delete quiz
        $stmt = $pdo->prepare("DELETE FROM academic_tests WHERE id = ? AND course_id = ?");
        if ($stmt->execute([$id, $course_id])) {
            $message = "Quiz deleted successfully!";
        }
    } catch (PDOException $e) {
        $error = "Failed to delete quiz";
    }
}

// Get all quizzes for this course
$stmt = $pdo->prepare("
    SELECT t.*, 
           (SELECT COUNT(*) FROM academic_questions WHERE test_id = t.id) as question_count,
           (SELECT COUNT(*) FROM academic_questions WHERE test_id = t.id AND question_type = 'multiple_choice') as mcq_count,
           (SELECT COUNT(*) FROM academic_questions WHERE test_id = t.id AND question_type = 'true_false') as tf_count,
           (SELECT COUNT(*) FROM academic_questions WHERE test_id = t.id AND question_type = 'fill_blank') as fb_count
    FROM academic_tests t 
    WHERE t.course_id = ? 
    ORDER BY t.created_at DESC
");
$stmt->execute([$course_id]);
$quizzes = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quizzes - <?= htmlspecialchars($course['title']) ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { font-family: 'Inter', sans-serif; }
        body { background-color: #0a1929; }
        .gradient-bg { 
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
        .quiz-card {
            transition: all 0.3s ease;
            background: rgba(255, 255, 255, 0.05);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.1);
        }
        .quiz-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 20px 25px -5px rgba(0,0,0,0.5);
        }
        .stat-badge {
            padding: 0.25rem 0.75rem;
            border-radius: 9999px;
            font-size: 0.7rem;
            font-weight: 500;
        }
        .ml-64 {
            margin-left: 16rem;
        }
    </style>
</head>
<body class="bg-[#0a1929]">
    <div class="flex min-h-screen">
        <?php include 'sidebar.php'; ?>
        
        <div class="flex-1  p-8">
            <!-- Header with Breadcrumb -->
            <div class="mb-8">
                <div class="flex items-center text-sm text-gray-400 mb-2">
                    <a href="academics.php" class="hover:text-white transition">Courses</a>
                    <i class="fas fa-chevron-right mx-2 text-xs"></i>
                    <span class="text-white"><?= htmlspecialchars($course['title']) ?></span>
                    <i class="fas fa-chevron-right mx-2 text-xs"></i>
                    <span class="text-yellow-400">Quizzes</span>
                </div>
                
                <div class="flex items-center justify-between">
                    <div>
                        <h1 class="text-3xl font-bold text-white">Quiz Management</h1>
                        <p class="text-gray-400 mt-1">Class <?= $course['class'] ?> • <?= $course['subject'] ?> • <?= $course['school_name'] ?? 'All Schools' ?></p>
                    </div>
                    <button onclick="openQuizModal()" class="gradient-bg text-white px-6 py-3 rounded-xl hover:opacity-90 transition flex items-center shadow-lg">
                        <i class="fas fa-plus mr-2"></i>
                        Create New Quiz
                    </button>
                </div>
            </div>
            
            <!-- Messages -->
            <?php if ($message): ?>
                <div class="bg-green-900/30 border border-green-700 text-green-300 px-6 py-4 rounded-xl mb-6 flex items-center">
                    <i class="fas fa-check-circle mr-3 text-xl"></i>
                    <?= $message ?>
                </div>
            <?php endif; ?>
            
            <?php if ($error): ?>
                <div class="bg-red-900/30 border border-red-700 text-red-300 px-6 py-4 rounded-xl mb-6 flex items-center">
                    <i class="fas fa-exclamation-circle mr-3 text-xl"></i>
                    <?= $error ?>
                </div>
            <?php endif; ?>
            
            <!-- Stats Overview -->
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-8">
                <div class="bg-gradient-to-br from-purple-900/30 to-pink-900/30 rounded-xl p-4 border border-purple-500/30">
                    <div class="text-purple-400 text-2xl font-bold"><?= count($quizzes) ?></div>
                    <div class="text-gray-400 text-sm">Total Quizzes</div>
                </div>
                <div class="bg-gradient-to-br from-blue-900/30 to-indigo-900/30 rounded-xl p-4 border border-blue-500/30">
                    <div class="text-blue-400 text-2xl font-bold">
                        <?= array_sum(array_column($quizzes, 'mcq_count')) ?>
                    </div>
                    <div class="text-gray-400 text-sm">MCQ Questions</div>
                </div>
                <div class="bg-gradient-to-br from-green-900/30 to-emerald-900/30 rounded-xl p-4 border border-green-500/30">
                    <div class="text-green-400 text-2xl font-bold">
                        <?= array_sum(array_column($quizzes, 'tf_count')) ?>
                    </div>
                    <div class="text-gray-400 text-sm">True/False</div>
                </div>
                <div class="bg-gradient-to-br from-yellow-900/30 to-orange-900/30 rounded-xl p-4 border border-yellow-500/30">
                    <div class="text-yellow-400 text-2xl font-bold">
                        <?= array_sum(array_column($quizzes, 'fb_count')) ?>
                    </div>
                    <div class="text-gray-400 text-sm">Fill Blanks</div>
                </div>
            </div>
            
            <!-- Quizzes Grid -->
            <?php if (empty($quizzes)): ?>
                <div class="bg-white/5 backdrop-blur-lg rounded-2xl p-16 text-center border border-white/10">
                    <div class="w-24 h-24 bg-gradient-to-br from-purple-500 to-pink-500 rounded-full mx-auto mb-6 flex items-center justify-center">
                        <i class="fas fa-puzzle-piece text-white text-4xl"></i>
                    </div>
                    <h3 class="text-2xl font-semibold text-white mb-3">No Quizzes Yet</h3>
                    <p class="text-gray-400 mb-8 max-w-md mx-auto">Create your first quiz for this course. You can add multiple choice, true/false, and fill-in-the-blank questions.</p>
                    <button onclick="openQuizModal()" class="gradient-bg text-white px-8 py-3 rounded-xl hover:opacity-90 transition inline-flex items-center">
                        <i class="fas fa-plus mr-2"></i>
                        Create Your First Quiz
                    </button>
                </div>
            <?php else: ?>
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    <?php foreach ($quizzes as $quiz): ?>
                        <div class="quiz-card rounded-2xl overflow-hidden">
                            <!-- Quiz Header -->
                            <div class="p-6 border-b border-white/10 bg-gradient-to-r from-purple-900/20 to-pink-900/20">
                                <div class="flex justify-between items-start">
                                    <div>
                                        <h3 class="text-xl font-bold text-white mb-2"><?= htmlspecialchars($quiz['title']) ?></h3>
                                        <p class="text-gray-400 text-sm"><?= htmlspecialchars($quiz['description'] ?? 'No description provided') ?></p>
                                    </div>
                                    <span class="stat-badge <?= $quiz['status'] == 'published' ? 'bg-green-900/50 text-green-300 border border-green-500/50' : 'bg-yellow-900/50 text-yellow-300 border border-yellow-500/50' ?>">
                                        <i class="fas fa-<?= $quiz['status'] == 'published' ? 'eye' : 'eye-slash' ?> mr-1"></i>
                                        <?= ucfirst($quiz['status']) ?>
                                    </span>
                                </div>
                            </div>
                            
                            <!-- Quiz Stats -->
                            <div class="p-6">
                                <div class="grid grid-cols-3 gap-4 mb-4">
                                    <div class="text-center">
                                        <div class="text-2xl font-bold text-purple-400"><?= $quiz['duration'] ?></div>
                                        <div class="text-xs text-gray-500">Minutes</div>
                                    </div>
                                    <div class="text-center">
                                        <div class="text-2xl font-bold text-blue-400"><?= $quiz['total_marks'] ?></div>
                                        <div class="text-xs text-gray-500">Total Marks</div>
                                    </div>
                                    <div class="text-center">
                                        <div class="text-2xl font-bold text-green-400"><?= $quiz['passing_marks'] ?></div>
                                        <div class="text-xs text-gray-500">Pass Marks</div>
                                    </div>
                                </div>
                                
                                <!-- Question Types Breakdown -->
                                <div class="flex flex-wrap gap-2 mb-4">
                                    <?php if ($quiz['mcq_count'] > 0): ?>
                                        <span class="px-3 py-1 bg-blue-900/30 text-blue-300 rounded-full text-xs border border-blue-500/30">
                                            <i class="fas fa-list-ul mr-1"></i> MCQ: <?= $quiz['mcq_count'] ?>
                                        </span>
                                    <?php endif; ?>
                                    <?php if ($quiz['tf_count'] > 0): ?>
                                        <span class="px-3 py-1 bg-green-900/30 text-green-300 rounded-full text-xs border border-green-500/30">
                                            <i class="fas fa-check-circle mr-1"></i> T/F: <?= $quiz['tf_count'] ?>
                                        </span>
                                    <?php endif; ?>
                                    <?php if ($quiz['fb_count'] > 0): ?>
                                        <span class="px-3 py-1 bg-yellow-900/30 text-yellow-300 rounded-full text-xs border border-yellow-500/30">
                                            <i class="fas fa-edit mr-1"></i> Fill: <?= $quiz['fb_count'] ?>
                                        </span>
                                    <?php endif; ?>
                                    <span class="px-3 py-1 bg-purple-900/30 text-purple-300 rounded-full text-xs border border-purple-500/30">
                                        <i class="fas fa-question-circle mr-1"></i> Total: <?= $quiz['question_count'] ?>
                                    </span>
                                </div>
                                
                                <!-- Action Buttons -->
                                <div class="flex items-center justify-between pt-4 border-t border-white/10">
                                    <a href="academics-questions.php?quiz_id=<?= $quiz['id'] ?>&course_id=<?= $course_id ?>" 
                                       class="flex-1 bg-gradient-to-r from-purple-600 to-pink-600 text-white py-2 rounded-lg hover:opacity-90 transition text-sm font-medium text-center mr-2">
                                        <i class="fas fa-edit mr-1"></i> Manage Questions
                                    </a>
                                    <div class="flex space-x-2">
                                        <button onclick="editQuiz(<?= $quiz['id'] ?>, '<?= htmlspecialchars(addslashes($quiz['title'])) ?>', '<?= htmlspecialchars(addslashes($quiz['description'] ?? '')) ?>', <?= $quiz['duration'] ?>, <?= $quiz['total_marks'] ?>, <?= $quiz['passing_marks'] ?>, '<?= $quiz['status'] ?>')" 
                                                class="p-2 bg-blue-900/30 text-blue-400 rounded-lg hover:bg-blue-900/50 transition" title="Edit Quiz">
                                            <i class="fas fa-pen"></i>
                                        </button>
                                        <a href="?course_id=<?= $course_id ?>&delete_quiz=<?= $quiz['id'] ?>" 
                                           onclick="return confirm('Delete this quiz? All questions will also be deleted.')" 
                                           class="p-2 bg-red-900/30 text-red-400 rounded-lg hover:bg-red-900/50 transition" title="Delete Quiz">
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
    </div>
    
    <!-- Create/Edit Quiz Modal -->
    <div id="quizModal" class="fixed inset-0 bg-black bg-opacity-75 hidden items-center justify-center z-50">
        <div class="bg-[#0f2744] rounded-2xl p-8 max-w-lg w-full mx-4 border border-gray-700 shadow-2xl">
            <div class="flex justify-between items-center mb-6">
                <h2 id="modalTitle" class="text-2xl font-bold text-white">Create New Quiz</h2>
                <button onclick="closeQuizModal()" class="text-gray-400 hover:text-white transition">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>
            
            <form method="POST" id="quizForm">
                <input type="hidden" name="quiz_id" id="quizId" value="">
                
                <div class="space-y-5">
                    <div>
                        <label class="block text-sm font-medium text-gray-300 mb-2">Quiz Title <span class="text-red-400">*</span></label>
                        <input type="text" name="title" id="quizTitle" required 
                               class="w-full bg-white/5 border border-gray-600 rounded-xl px-4 py-3 text-white focus:outline-none focus:border-purple-500 transition"
                               placeholder="e.g., Chapter 1: Python Basics">
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-300 mb-2">Description</label>
                        <textarea name="description" id="quizDescription" rows="3" 
                                  class="w-full bg-white/5 border border-gray-600 rounded-xl px-4 py-3 text-white focus:outline-none focus:border-purple-500 transition"
                                  placeholder="Brief description of the quiz"></textarea>
                    </div>
                    
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-300 mb-2">Duration (mins)</label>
                            <input type="number" name="duration" id="quizDuration" value="30" min="1" required 
                                   class="w-full bg-white/5 border border-gray-600 rounded-xl px-4 py-3 text-white focus:outline-none focus:border-purple-500 transition">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-300 mb-2">Total Marks</label>
                            <input type="number" name="total_marks" id="quizTotalMarks" value="100" min="1" required 
                                   class="w-full bg-white/5 border border-gray-600 rounded-xl px-4 py-3 text-white focus:outline-none focus:border-purple-500 transition">
                        </div>
                    </div>
                    
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-300 mb-2">Passing Marks</label>
                            <input type="number" name="passing_marks" id="quizPassingMarks" value="40" min="1" required 
                                   class="w-full bg-white/5 border border-gray-600 rounded-xl px-4 py-3 text-white focus:outline-none focus:border-purple-500 transition">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-300 mb-2">Status</label>
                            <select name="status" id="quizStatus" 
                                    class="w-full bg-white/5 border border-gray-600 rounded-xl px-4 py-3 text-white focus:outline-none focus:border-purple-500 transition">
                                <option value="draft" class="bg-gray-900">Draft</option>
                                <option value="published" class="bg-gray-900">Published</option>
                            </select>
                        </div>
                    </div>
                </div>
                
                <div class="flex justify-end space-x-3 mt-8 pt-4 border-t border-gray-700">
                    <button type="button" onclick="closeQuizModal()" 
                            class="px-6 py-2.5 border border-gray-600 rounded-xl text-gray-300 hover:bg-gray-800 transition">
                        Cancel
                    </button>
                    <button type="submit" name="save_quiz" 
                            class="px-6 py-2.5 gradient-bg text-white rounded-xl hover:opacity-90 transition">
                        <i class="fas fa-save mr-2"></i>
                        Save Quiz
                    </button>
                </div>
            </form>
        </div>
    </div>
    
    <script>
        function openQuizModal() {
            document.getElementById('modalTitle').textContent = 'Create New Quiz';
            document.getElementById('quizId').value = '';
            document.getElementById('quizTitle').value = '';
            document.getElementById('quizDescription').value = '';
            document.getElementById('quizDuration').value = '30';
            document.getElementById('quizTotalMarks').value = '100';
            document.getElementById('quizPassingMarks').value = '40';
            document.getElementById('quizStatus').value = 'draft';
            document.getElementById('quizModal').classList.remove('hidden');
            document.getElementById('quizModal').classList.add('flex');
        }
        
        function editQuiz(id, title, description, duration, totalMarks, passingMarks, status) {
            document.getElementById('modalTitle').textContent = 'Edit Quiz';
            document.getElementById('quizId').value = id;
            document.getElementById('quizTitle').value = title;
            document.getElementById('quizDescription').value = description || '';
            document.getElementById('quizDuration').value = duration;
            document.getElementById('quizTotalMarks').value = totalMarks;
            document.getElementById('quizPassingMarks').value = passingMarks;
            document.getElementById('quizStatus').value = status;
            document.getElementById('quizModal').classList.remove('hidden');
            document.getElementById('quizModal').classList.add('flex');
        }
        
        function closeQuizModal() {
            document.getElementById('quizModal').classList.add('hidden');
            document.getElementById('quizModal').classList.remove('flex');
        }
    </script>
</body>
</html>