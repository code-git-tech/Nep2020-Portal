<?php
require_once '../includes/auth.php';
require_once '../includes/auto_publish.php';
requireAdmin();

$quiz_id = isset($_GET['quiz_id']) ? (int)$_GET['quiz_id'] : 0;
$course_id = isset($_GET['course_id']) ? (int)$_GET['course_id'] : 0;

// Validate parameters
if ($quiz_id <= 0 || $course_id <= 0) {
    $_SESSION['error'] = "Invalid quiz or course ID";
    header("Location: academics.php");
    exit;
}

// Get quiz details
$stmt = $pdo->prepare("
    SELECT t.*, c.title as course_title, c.class, c.subject 
    FROM academic_tests t
    JOIN academic_courses c ON t.course_id = c.id
    WHERE t.id = ? AND t.course_id = ?
");
$stmt->execute([$quiz_id, $course_id]);
$quiz = $stmt->fetch();

if (!$quiz) {
    $_SESSION['error'] = "Quiz not found";
    header("Location: academics.php?course_id=$course_id");
    exit;
}

$message = '';
$error = '';

// Handle question operations
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['save_question'])) {
        $question_type = $_POST['question_type'];
        $question = trim($_POST['question']);
        $marks = intval($_POST['marks']);
        
        if ($question_type == 'multiple_choice') {
            $option_a = trim($_POST['option_a']);
            $option_b = trim($_POST['option_b']);
            $option_c = trim($_POST['option_c'] ?? '');
            $option_d = trim($_POST['option_d'] ?? '');
            $correct_answer = $_POST['correct_answer'];
            
            $stmt = $pdo->prepare("
                INSERT INTO academic_questions (test_id, question_type, question, option_a, option_b, option_c, option_d, correct_answer, marks) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$quiz_id, $question_type, $question, $option_a, $option_b, $option_c, $option_d, $correct_answer, $marks]);
            
        } elseif ($question_type == 'true_false') {
            $correct_answer = $_POST['tf_answer'];
            
            $stmt = $pdo->prepare("
                INSERT INTO academic_questions (test_id, question_type, question, correct_answer, marks) 
                VALUES (?, ?, ?, ?, ?)
            ");
            $stmt->execute([$quiz_id, $question_type, $question, $correct_answer, $marks]);
            
        } elseif ($question_type == 'fill_blank') {
            $correct_answer = trim($_POST['blank_answer']);
            
            $stmt = $pdo->prepare("
                INSERT INTO academic_questions (test_id, question_type, question, correct_answer, marks) 
                VALUES (?, ?, ?, ?, ?)
            ");
            $stmt->execute([$quiz_id, $question_type, $question, $correct_answer, $marks]);
        }
        
        $message = "Question added successfully!";
    }
    
    if (isset($_POST['delete_question'])) {
        $qid = $_POST['question_id'];
        $stmt = $pdo->prepare("DELETE FROM academic_questions WHERE id = ? AND test_id = ?");
        $stmt->execute([$qid, $quiz_id]);
        $message = "Question deleted successfully!";
    }
}

// Get all questions
$stmt = $pdo->prepare("SELECT * FROM academic_questions WHERE test_id = ? ORDER BY id");
$stmt->execute([$quiz_id]);
$questions = $stmt->fetchAll();

// Group questions by type
$mcq_questions = array_filter($questions, fn($q) => $q['question_type'] == 'multiple_choice');
$tf_questions = array_filter($questions, fn($q) => $q['question_type'] == 'true_false');
$fb_questions = array_filter($questions, fn($q) => $q['question_type'] == 'fill_blank');
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Questions - <?= htmlspecialchars($quiz['title']) ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { font-family: 'Inter', sans-serif; }
        body { background-color: #0a1929; }
        .gradient-bg { 
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
        .question-card {
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.1);
        }
        .ml-64 {
            margin-left: 16rem;
        }
        .type-badge {
            padding: 0.25rem 0.75rem;
            border-radius: 9999px;
            font-size: 0.7rem;
            font-weight: 500;
        }
    </style>
</head>
<body class="bg-[#0a1929]">
    <div class="flex min-h-screen">
        <?php include 'sidebar.php'; ?>
        
        <div class="flex-1 p-8">
            <!-- Breadcrumb -->
            <div class="flex items-center text-sm text-gray-400 mb-4">
                <a href="academics.php" class="hover:text-white">Courses</a>
                <i class="fas fa-chevron-right mx-2 text-xs"></i>
                <a href="academics-quizzes.php?course_id=<?= $course_id ?>" class="hover:text-white"><?= htmlspecialchars($quiz['course_title']) ?></a>
                <i class="fas fa-chevron-right mx-2 text-xs"></i>
                <span class="text-purple-400">Questions</span>
            </div>
            
            <!-- Header -->
            <div class="mb-8">
                <h1 class="text-3xl font-bold text-white"><?= htmlspecialchars($quiz['title']) ?></h1>
                <p class="text-gray-400 mt-2"><?= htmlspecialchars($quiz['description'] ?? 'No description') ?></p>
                
                <div class="flex items-center gap-4 mt-4">
                    <span class="px-3 py-1 bg-purple-900/30 text-purple-300 rounded-full text-sm">
                        <i class="far fa-clock mr-1"></i> <?= $quiz['duration'] ?> minutes
                    </span>
                    <span class="px-3 py-1 bg-blue-900/30 text-blue-300 rounded-full text-sm">
                        <i class="fas fa-star mr-1"></i> Total: <?= $quiz['total_marks'] ?> marks
                    </span>
                    <span class="px-3 py-1 bg-green-900/30 text-green-300 rounded-full text-sm">
                        <i class="fas fa-check-circle mr-1"></i> Pass: <?= $quiz['passing_marks'] ?> marks
                    </span>
                </div>
            </div>
            
            <!-- Messages -->
            <?php if ($message): ?>
                <div class="bg-green-900/30 border border-green-700 text-green-300 px-6 py-4 rounded-xl mb-6">
                    <i class="fas fa-check-circle mr-2"></i> <?= $message ?>
                </div>
            <?php endif; ?>
            
            <?php if ($error): ?>
                <div class="bg-red-900/30 border border-red-700 text-red-300 px-6 py-4 rounded-xl mb-6">
                    <i class="fas fa-exclamation-circle mr-2"></i> <?= $error ?>
                </div>
            <?php endif; ?>
            
            <!-- Question Type Tabs -->
            <div class="flex space-x-2 mb-6 border-b border-gray-800 pb-2">
                <button onclick="showQuestionType('mcq')" id="tab-mcq" class="px-4 py-2 text-sm font-medium rounded-t-lg transition tab-active bg-purple-600 text-white">
                    <i class="fas fa-list-ul mr-2"></i>Multiple Choice (<?= count($mcq_questions) ?>)
                </button>
                <button onclick="showQuestionType('tf')" id="tab-tf" class="px-4 py-2 text-sm font-medium rounded-t-lg transition text-gray-400 hover:text-white">
                    <i class="fas fa-check-circle mr-2"></i>True/False (<?= count($tf_questions) ?>)
                </button>
                <button onclick="showQuestionType('fb')" id="tab-fb" class="px-4 py-2 text-sm font-medium rounded-t-lg transition text-gray-400 hover:text-white">
                    <i class="fas fa-edit mr-2"></i>Fill Blanks (<?= count($fb_questions) ?>)
                </button>
            </div>
            
            <!-- MCQ Form -->
            <div id="mcq-form" class="bg-white/5 backdrop-blur-lg rounded-2xl p-6 mb-8 border border-white/10">
                <h3 class="text-lg font-semibold text-white mb-4 flex items-center">
                    <i class="fas fa-plus-circle text-purple-400 mr-2"></i>
                    Add Multiple Choice Question
                </h3>
                <form method="POST">
                    <input type="hidden" name="question_type" value="multiple_choice">
                    
                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm text-gray-400 mb-2">Question</label>
                            <textarea name="question" rows="2" required 
                                      class="w-full bg-white/5 border border-gray-700 rounded-xl px-4 py-3 text-white focus:outline-none focus:border-purple-500"
                                      placeholder="Enter your question here..."></textarea>
                        </div>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm text-gray-400 mb-2">Option A</label>
                                <input type="text" name="option_a" required 
                                       class="w-full bg-white/5 border border-gray-700 rounded-xl px-4 py-2 text-white focus:outline-none focus:border-purple-500"
                                       placeholder="Option A">
                            </div>
                            <div>
                                <label class="block text-sm text-gray-400 mb-2">Option B</label>
                                <input type="text" name="option_b" required 
                                       class="w-full bg-white/5 border border-gray-700 rounded-xl px-4 py-2 text-white focus:outline-none focus:border-purple-500"
                                       placeholder="Option B">
                            </div>
                            <div>
                                <label class="block text-sm text-gray-400 mb-2">Option C (Optional)</label>
                                <input type="text" name="option_c" 
                                       class="w-full bg-white/5 border border-gray-700 rounded-xl px-4 py-2 text-white focus:outline-none focus:border-purple-500"
                                       placeholder="Option C">
                            </div>
                            <div>
                                <label class="block text-sm text-gray-400 mb-2">Option D (Optional)</label>
                                <input type="text" name="option_d" 
                                       class="w-full bg-white/5 border border-gray-700 rounded-xl px-4 py-2 text-white focus:outline-none focus:border-purple-500"
                                       placeholder="Option D">
                            </div>
                        </div>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm text-gray-400 mb-2">Correct Answer</label>
                                <select name="correct_answer" required 
                                        class="w-full bg-white/5 border border-gray-700 rounded-xl px-4 py-2 text-white focus:outline-none focus:border-purple-500">
                                    <option value="A" class="bg-gray-900">A</option>
                                    <option value="B" class="bg-gray-900">B</option>
                                    <option value="C" class="bg-gray-900">C</option>
                                    <option value="D" class="bg-gray-900">D</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm text-gray-400 mb-2">Marks</label>
                                <input type="number" name="marks" value="1" min="1" 
                                       class="w-full bg-white/5 border border-gray-700 rounded-xl px-4 py-2 text-white focus:outline-none focus:border-purple-500">
                            </div>
                        </div>
                        
                        <button type="submit" name="save_question" 
                                class="px-6 py-3 bg-gradient-to-r from-purple-600 to-pink-600 text-white rounded-xl hover:opacity-90 transition">
                            <i class="fas fa-plus mr-2"></i>Add MCQ Question
                        </button>
                    </div>
                </form>
            </div>
            
            <!-- True/False Form -->
            <div id="tf-form" class="bg-white/5 backdrop-blur-lg rounded-2xl p-6 mb-8 border border-white/10 hidden">
                <h3 class="text-lg font-semibold text-white mb-4 flex items-center">
                    <i class="fas fa-plus-circle text-green-400 mr-2"></i>
                    Add True/False Question
                </h3>
                <form method="POST">
                    <input type="hidden" name="question_type" value="true_false">
                    
                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm text-gray-400                        <div>
                            <label class="block text-sm text-gray-400 mb-2">Question</label>
                            <textarea name="question" rows="2" required 
                                      class="w-full bg-white/5 border border-gray-700 rounded-xl px-4 py-3 text-white focus:outline-none focus:border-green-500"
                                      placeholder="Enter your true/false question here..."></textarea>
                        </div>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm text-gray-400 mb-2">Correct Answer</label>
                                <select name="tf_answer" required 
                                        class="w-full bg-white/5 border border-gray-700 rounded-xl px-4 py-2 text-white focus:outline-none focus:border-green-500">
                                    <option value="true" class="bg-gray-900">True</option>
                                    <option value="false" class="bg-gray-900">False</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm text-gray-400 mb-2">Marks</label>
                                <input type="number" name="marks" value="1" min="1" 
                                       class="w-full bg-white/5 border border-gray-700 rounded-xl px-4 py-2 text-white focus:outline-none focus:border-green-500">
                            </div>
                        </div>
                        
                        <button type="submit" name="save_question" 
                                class="px-6 py-3 bg-gradient-to-r from-green-600 to-emerald-600 text-white rounded-xl hover:opacity-90 transition">
                            <i class="fas fa-plus mr-2"></i>Add True/False Question
                        </button>
                    </div>
                </form>
            </div>
            
            <!-- Fill Blanks Form -->
            <div id="fb-form" class="bg-white/5 backdrop-blur-lg rounded-2xl p-6 mb-8 border border-white/10 hidden">
                <h3 class="text-lg font-semibold text-white mb-4 flex items-center">
                    <i class="fas fa-plus-circle text-yellow-400 mr-2"></i>
                    Add Fill in the Blank Question
                </h3>
                <form method="POST">
                    <input type="hidden" name="question_type" value="fill_blank">
                    
                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm text-gray-400 mb-2">Question (use _____ for blank)</label>
                            <textarea name="question" rows="2" required 
                                      class="w-full bg-white/5 border border-gray-700 rounded-xl px-4 py-3 text-white focus:outline-none focus:border-yellow-500"
                                      placeholder="e.g., The capital of France is _____"></textarea>
                        </div>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm text-gray-400 mb-2">Correct Answer</label>
                                <input type="text" name="blank_answer" required 
                                       class="w-full bg-white/5 border border-gray-700 rounded-xl px-4 py-2 text-white focus:outline-none focus:border-yellow-500"
                                       placeholder="e.g., Paris">
                            </div>
                            <div>
                                <label class="block text-sm text-gray-400 mb-2">Marks</label>
                                <input type="number" name="marks" value="1" min="1" 
                                       class="w-full bg-white/5 border border-gray-700 rounded-xl px-4 py-2 text-white focus:outline-none focus:border-yellow-500">
                            </div>
                        </div>
                        
                        <button type="submit" name="save_question" 
                                class="px-6 py-3 bg-gradient-to-r from-yellow-600 to-orange-600 text-white rounded-xl hover:opacity-90 transition">
                            <i class="fas fa-plus mr-2"></i>Add Fill Blank Question
                        </button>
                    </div>
                </form>
            </div>
            
            <!-- Questions List -->
            <div class="bg-white/5 backdrop-blur-lg rounded-2xl p-6 border border-white/10">
                <h2 class="text-xl font-bold text-white mb-6">All Questions</h2>
                
                <?php if (empty($questions)): ?>
                    <div class="text-center py-12">
                        <i class="fas fa-question-circle text-5xl text-gray-600 mb-4"></i>
                        <p class="text-gray-400">No questions added yet. Use the forms above to add questions.</p>
                    </div>
                <?php else: ?>
                    <!-- MCQ Questions -->
                    <?php if (!empty($mcq_questions)): ?>
                        <div class="mb-8">
                            <h3 class="text-lg font-semibold text-purple-400 mb-4 flex items-center">
                                <i class="fas fa-list-ul mr-2"></i>
                                Multiple Choice Questions (<?= count($mcq_questions) ?>)
                            </h3>
                            <div class="space-y-4">
                                <?php foreach ($mcq_questions as $index => $q): ?>
                                    <div class="question-card rounded-xl p-4">
                                        <div class="flex justify-between items-start mb-3">
                                            <h4 class="font-medium text-white">Q<?= $index + 1 ?>. <?= htmlspecialchars($q['question']) ?></h4>
                                            <span class="px-2 py-1 bg-purple-900/30 text-purple-300 rounded-full text-xs"><?= $q['marks'] ?> marks</span>
                                        </div>
                                        <div class="grid grid-cols-1 md:grid-cols-2 gap-2 text-sm ml-4">
                                            <div class="<?= $q['correct_answer'] == 'A' ? 'text-green-400 font-medium' : 'text-gray-400' ?>">
                                                A. <?= htmlspecialchars($q['option_a']) ?>
                                                <?php if ($q['correct_answer'] == 'A'): ?>
                                                    <i class="fas fa-check-circle text-green-400 ml-2"></i>
                                                <?php endif; ?>
                                            </div>
                                            <div class="<?= $q['correct_answer'] == 'B' ? 'text-green-400 font-medium' : 'text-gray-400' ?>">
                                                B. <?= htmlspecialchars($q['option_b']) ?>
                                                <?php if ($q['correct_answer'] == 'B'): ?>
                                                    <i class="fas fa-check-circle text-green-400 ml-2"></i>
                                                <?php endif; ?>
                                            </div>
                                            <?php if ($q['option_c']): ?>
                                                <div class="<?= $q['correct_answer'] == 'C' ? 'text-green-400 font-medium' : 'text-gray-400' ?>">
                                                    C. <?= htmlspecialchars($q['option_c']) ?>
                                                    <?php if ($q['correct_answer'] == 'C'): ?>
                                                        <i class="fas fa-check-circle text-green-400 ml-2"></i>
                                                    <?php endif; ?>
                                                </div>
                                            <?php endif; ?>
                                            <?php if ($q['option_d']): ?>
                                                <div class="<?= $q['correct_answer'] == 'D' ? 'text-green-400 font-medium' : 'text-gray-400' ?>">
                                                    D. <?= htmlspecialchars($q['option_d']) ?>
                                                    <?php if ($q['correct_answer'] == 'D'): ?>
                                                        <i class="fas fa-check-circle text-green-400 ml-2"></i>
                                                    <?php endif; ?>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                        <div class="flex justify-end mt-3 pt-2 border-t border-gray-800">
                                            <form method="POST" onsubmit="return confirm('Delete this question?')">
                                                <input type="hidden" name="question_id" value="<?= $q['id'] ?>">
                                                <button type="submit" name="delete_question" class="text-red-400 hover:text-red-300 text-sm">
                                                    <i class="fas fa-trash mr-1"></i> Delete
                                                </button>
                                            </form>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                    
                    <!-- True/False Questions -->
                    <?php if (!empty($tf_questions)): ?>
                        <div class="mb-8">
                            <h3 class="text-lg font-semibold text-green-400 mb-4 flex items-center">
                                <i class="fas fa-check-circle mr-2"></i>
                                True/False Questions (<?= count($tf_questions) ?>)
                            </h3>
                            <div class="space-y-4">
                                <?php foreach ($tf_questions as $index => $q): ?>
                                    <div class="question-card rounded-xl p-4">
                                        <div class="flex justify-between items-start mb-3">
                                            <h4 class="font-medium text-white"><?= htmlspecialchars($q['question']) ?></h4>
                                            <span class="px-2 py-1 bg-green-900/30 text-green-300 rounded-full text-xs"><?= $q['marks'] ?> marks</span>
                                        </div>
                                        <div class="ml-4">
                                            <span class="text-gray-400">Answer: </span>
                                            <span class="text-green-400 font-medium">
                                                <?= ucfirst($q['correct_answer']) ?>
                                                <i class="fas fa-check-circle ml-1"></i>
                                            </span>
                                        </div>
                                        <div class="flex justify-end mt-3 pt-2 border-t border-gray-800">
                                            <form method="POST" onsubmit="return confirm('Delete this question?')">
                                                <input type="hidden" name="question_id" value="<?= $q['id'] ?>">
                                                <button type="submit" name="delete_question" class="text-red-400 hover:text-red-300 text-sm">
                                                    <i class="fas fa-trash mr-1"></i> Delete
                                                </button>
                                            </form>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                    
                    <!-- Fill Blank Questions -->
                    <?php if (!empty($fb_questions)): ?>
                        <div class="mb-8">
                            <h3 class="text-lg font-semibold text-yellow-400 mb-4 flex items-center">
                                <i class="fas fa-edit mr-2"></i>
                                Fill in the Blanks (<?= count($fb_questions) ?>)
                            </h3>
                            <div class="space-y-4">
                                <?php foreach ($fb_questions as $index => $q): ?>
                                    <div class="question-card rounded-xl p-4">
                                        <div class="flex justify-between items-start mb-3">
                                            <h4 class="font-medium text-white"><?= htmlspecialchars($q['question']) ?></h4>
                                            <span class="px-2 py-1 bg-yellow-900/30 text-yellow-300 rounded-full text-xs"><?= $q['marks'] ?> marks</span>
                                        </div>
                                        <div class="ml-4">
                                            <span class="text-gray-400">Correct Answer: </span>
                                            <span class="text-yellow-400 font-medium">"<?= htmlspecialchars($q['correct_answer']) ?>"</span>
                                        </div>
                                        <div class="flex justify-end mt-3 pt-2 border-t border-gray-800">
                                            <form method="POST" onsubmit="return confirm('Delete this question?')">
                                                <input type="hidden" name="question_id" value="<?= $q['id'] ?>">
                                                <button type="submit" name="delete_question" class="text-red-400 hover:text-red-300 text-sm">
                                                    <i class="fas fa-trash mr-1"></i> Delete
                                                </button>
                                            </form>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <script>
        function showQuestionType(type) {
            // Hide all forms
            document.getElementById('mcq-form').classList.add('hidden');
            document.getElementById('tf-form').classList.add('hidden');
            document.getElementById('fb-form').classList.add('hidden');
            
            // Remove active class from all tabs
            document.getElementById('tab-mcq').classList.remove('bg-purple-600', 'text-white');
            document.getElementById('tab-mcq').classList.add('text-gray-400');
            document.getElementById('tab-tf').classList.remove('bg-green-600', 'text-white');
            document.getElementById('tab-tf').classList.add('text-gray-400');
            document.getElementById('tab-fb').classList.remove('bg-yellow-600', 'text-white');
            document.getElementById('tab-fb').classList.add('text-gray-400');
            
            // Show selected form and activate tab
            if (type === 'mcq') {
                document.getElementById('mcq-form').classList.remove('hidden');
                document.getElementById('tab-mcq').classList.remove('text-gray-400');
                document.getElementById('tab-mcq').classList.add('bg-purple-600', 'text-white');
            } else if (type === 'tf') {
                document.getElementById('tf-form').classList.remove('hidden');
                document.getElementById('tab-tf').classList.remove('text-gray-400');
                document.getElementById('tab-tf').classList.add('bg-green-600', 'text-white');
            } else if (type === 'fb') {
                document.getElementById('fb-form').classList.remove('hidden');
                document.getElementById('tab-fb').classList.remove('text-gray-400');
                document.getElementById('tab-fb').classList.add('bg-yellow-600', 'text-white');
            }
        }
    </script>
</body>
</html>