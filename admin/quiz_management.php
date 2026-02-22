<?php
require_once '../includes/auth.php';
require_once '../includes/auto_publish.php';
requireAdmin();

$message = '';
$error = '';

// Get video ID from URL
$video_id = isset($_GET['video_id']) ? (int)$_GET['video_id'] : 0;

// Fetch video details
$stmt = $pdo->prepare("
    SELECT v.*, c.title as course_title 
    FROM videos v 
    JOIN courses c ON v.course_id = c.id 
    WHERE v.id = ?
");
$stmt->execute([$video_id]);
$video = $stmt->fetch();

if (!$video) {
    header('Location: content.php');
    exit;
}

// Check if quiz exists for this video
$stmt = $pdo->prepare("SELECT * FROM quizzes WHERE video_id = ?");
$stmt->execute([$video_id]);
$quiz = $stmt->fetch();

// Handle quiz creation/update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['save_quiz'])) {
        $title = trim($_POST['title']);
        $description = trim($_POST['description']);
        $time_limit = intval($_POST['time_limit']);
        $passing_score = intval($_POST['passing_score']);
        $max_attempts = !empty($_POST['max_attempts']) ? intval($_POST['max_attempts']) : null;
        $shuffle_questions = isset($_POST['shuffle_questions']) ? 1 : 0;
        $show_results = isset($_POST['show_results']) ? 1 : 0;
        
        try {
            $pdo->beginTransaction();
            
            if ($quiz) {
                // Update existing quiz
                $stmt = $pdo->prepare("
                    UPDATE quizzes 
                    SET title = ?, description = ?, time_limit = ?, passing_score = ?, 
                        max_attempts = ?, shuffle_questions = ?, show_results = ?
                    WHERE id = ?
                ");
                $stmt->execute([$title, $description, $time_limit, $passing_score, 
                               $max_attempts, $shuffle_questions, $show_results, $quiz['id']]);
                $quiz_id = $quiz['id'];
                $message = 'Quiz updated successfully';
            } else {
                // Create new quiz
                $stmt = $pdo->prepare("
                    INSERT INTO quizzes (video_id, title, description, time_limit, passing_score, 
                                        max_attempts, shuffle_questions, show_results)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([$video_id, $title, $description, $time_limit, $passing_score, 
                               $max_attempts, $shuffle_questions, $show_results]);
                $quiz_id = $pdo->lastInsertId();
                $message = 'Quiz created successfully';
            }
            
            // Update video constraint
            $requires_quiz = isset($_POST['requires_quiz']) ? 1 : 0;
            $next_video_id = !empty($_POST['next_video_id']) ? intval($_POST['next_video_id']) : null;
            
            $stmt = $pdo->prepare("UPDATE videos SET requires_quiz = ?, next_video_id = ? WHERE id = ?");
            $stmt->execute([$requires_quiz, $next_video_id, $video_id]);
            
            $pdo->commit();
            
        } catch (PDOException $e) {
            $pdo->rollBack();
            $error = 'Failed to save quiz: ' . $e->getMessage();
        }
    }
    
    // Handle question addition
    if (isset($_POST['add_question'])) {
        $quiz_id = $_POST['quiz_id'];
        $question_text = trim($_POST['question_text']);
        $question_type = $_POST['question_type'];
        $points = intval($_POST['points']);
        
        try {
            $pdo->beginTransaction();
            
            // Get max order
            $stmt = $pdo->prepare("SELECT MAX(order_num) FROM quiz_questions WHERE quiz_id = ?");
            $stmt->execute([$quiz_id]);
            $max_order = $stmt->fetchColumn();
            $order_num = $max_order + 1;
            
            // Insert question
            $stmt = $pdo->prepare("
                INSERT INTO quiz_questions (quiz_id, question_text, question_type, points, order_num)
                VALUES (?, ?, ?, ?, ?)
            ");
            $stmt->execute([$quiz_id, $question_text, $question_type, $points, $order_num]);
            $question_id = $pdo->lastInsertId();
            
            // Handle options based on question type
            if ($question_type == 'multiple_choice' || $question_type == 'true_false') {
                $options = $_POST['options'] ?? [];
                $correct_option = $_POST['correct_option'] ?? null;
                
                foreach ($options as $index => $option_text) {
                    if (!empty(trim($option_text))) {
                        $is_correct = ($correct_option == $index) ? 1 : 0;
                        $stmt = $pdo->prepare("
                            INSERT INTO quiz_options (question_id, option_text, is_correct, order_num)
                            VALUES (?, ?, ?, ?)
                        ");
                        $stmt->execute([$question_id, $option_text, $is_correct, $index]);
                    }
                }
            }
            
            $pdo->commit();
            $message = 'Question added successfully';
            
        } catch (PDOException $e) {
            $pdo->rollBack();
            $error = 'Failed to add question: ' . $e->getMessage();
        }
    }
    
    // Handle question deletion
    if (isset($_GET['delete_question'])) {
        $question_id = $_GET['delete_question'];
        $stmt = $pdo->prepare("DELETE FROM quiz_questions WHERE id = ?");
        if ($stmt->execute([$question_id])) {
            $message = 'Question deleted successfully';
        }
    }
}

// Get all questions for this quiz
$questions = [];
if ($quiz) {
    $stmt = $pdo->prepare("
        SELECT q.*, 
               (SELECT COUNT(*) FROM quiz_options WHERE question_id = q.id) as option_count
        FROM quiz_questions q
        WHERE q.quiz_id = ?
        ORDER BY q.order_num
    ");
    $stmt->execute([$quiz['id']]);
    $questions = $stmt->fetchAll();
}

// Get other videos in same course for next video dropdown
$stmt = $pdo->prepare("
    SELECT id, title, order_num 
    FROM videos 
    WHERE course_id = ? AND id != ?
    ORDER BY order_num
");
$stmt->execute([$video['course_id'], $video_id]);
$other_videos = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quiz Management - <?= htmlspecialchars($video['title']) ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-gray-50">
    <div class="flex h-screen">
        <?php include 'sidebar.php'; ?>

        <div class="flex-1 overflow-auto">
            <div class="p-8">
                <div class="flex justify-between items-center mb-6">
                    <div>
                        <h1 class="text-3xl font-bold text-gray-800">Quiz Management</h1>
                        <p class="text-gray-600 mt-1">
                            Course: <?= htmlspecialchars($video['course_title']) ?> | 
                            Video: <?= htmlspecialchars($video['title']) ?>
                        </p>
                    </div>
                    <a href="content.php" class="bg-gray-500 text-white px-4 py-2 rounded-lg hover:bg-gray-600">
                        <i class="fas fa-arrow-left mr-2"></i>Back to Content
                    </a>
                </div>

                <?php if ($message): ?>
                    <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
                        <i class="fas fa-check-circle mr-2"></i><?= $message ?>
                    </div>
                <?php endif; ?>
                
                <?php if ($error): ?>
                    <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
                        <i class="fas fa-exclamation-circle mr-2"></i><?= $error ?>
                    </div>
                <?php endif; ?>

                <!-- Quiz Settings -->
                <div class="bg-white rounded-lg shadow-sm p-6 mb-6">
                    <h2 class="text-xl font-bold mb-4">Quiz Settings</h2>
                    <form method="POST">
                        <div class="grid grid-cols-2 gap-6">
                            <div>
                                <div class="mb-4">
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Quiz Title *</label>
                                    <input type="text" name="title" required 
                                           value="<?= htmlspecialchars($quiz['title'] ?? '') ?>"
                                           class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                                </div>
                                
                                <div class="mb-4">
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Description</label>
                                    <textarea name="description" rows="3" 
                                              class="w-full px-4 py-2 border border-gray-300 rounded-lg"><?= htmlspecialchars($quiz['description'] ?? '') ?></textarea>
                                </div>
                                
                                <div class="grid grid-cols-2 gap-4 mb-4">
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-2">Time Limit (minutes)</label>
                                        <input type="number" name="time_limit" min="1" value="<?= $quiz['time_limit'] ?? 10 ?>" required
                                               class="w-full px-4 py-2 border border-gray-300 rounded-lg">
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-2">Passing Score (%)</label>
                                        <input type="number" name="passing_score" min="1" max="100" value="<?= $quiz['passing_score'] ?? 70 ?>" required
                                               class="w-full px-4 py-2 border border-gray-300 rounded-lg">
                                    </div>
                                </div>
                            </div>
                            
                            <div>
                                <div class="mb-4">
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Max Attempts</label>
                                    <input type="number" name="max_attempts" min="1" 
                                           value="<?= $quiz['max_attempts'] ?? '' ?>"
                                           placeholder="Leave empty for unlimited"
                                           class="w-full px-4 py-2 border border-gray-300 rounded-lg">
                                </div>
                                
                                <div class="mb-4 space-y-2">
                                    <label class="flex items-center">
                                        <input type="checkbox" name="shuffle_questions" value="1" 
                                               <?= isset($quiz['shuffle_questions']) && $quiz['shuffle_questions'] ? 'checked' : '' ?>
                                               class="mr-2">
                                        <span class="text-sm text-gray-700">Shuffle questions order</span>
                                    </label>
                                    
                                    <label class="flex items-center">
                                        <input type="checkbox" name="show_results" value="1" 
                                               <?= !isset($quiz['show_results']) || $quiz['show_results'] ? 'checked' : '' ?>
                                               class="mr-2">
                                        <span class="text-sm text-gray-700">Show results after completion</span>
                                    </label>
                                    
                                    <label class="flex items-center">
                                        <input type="checkbox" name="requires_quiz" value="1" 
                                               <?= $video['requires_quiz'] ? 'checked' : '' ?>
                                               class="mr-2">
                                        <span class="text-sm text-gray-700">Require quiz completion to proceed</span>
                                    </label>
                                </div>
                                
                                <div class="mb-4">
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Next Video (after quiz)</label>
                                    <select name="next_video_id" class="w-full px-4 py-2 border border-gray-300 rounded-lg">
                                        <option value="">-- No next video --</option>
                                        <?php foreach ($other_videos as $vid): ?>
                                            <option value="<?= $vid['id'] ?>" <?= $vid['id'] == $video['next_video_id'] ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($vid['title']) ?> (Order: <?= $vid['order_num'] ?>)
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <p class="text-xs text-gray-500 mt-1">Student will be redirected to this video after passing the quiz</p>
                                </div>
                            </div>
                        </div>
                        
                        <div class="flex justify-end mt-4">
                            <button type="submit" name="save_quiz" class="px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">
                                <i class="fas fa-save mr-2"></i><?= $quiz ? 'Update Quiz' : 'Create Quiz' ?>
                            </button>
                        </div>
                    </form>
                </div>

                <?php if ($quiz): ?>
                <!-- Questions List -->
                <div class="bg-white rounded-lg shadow-sm p-6 mb-6">
                    <div class="flex justify-between items-center mb-4">
                        <h2 class="text-xl font-bold">Quiz Questions</h2>
                        <button onclick="openAddQuestionModal()" class="bg-green-600 text-white px-4 py-2 rounded-lg hover:bg-green-700">
                            <i class="fas fa-plus mr-2"></i>Add Question
                        </button>
                    </div>
                    
                    <?php if (empty($questions)): ?>
                        <div class="text-center py-8 text-gray-500">
                            <i class="fas fa-question-circle text-4xl mb-3"></i>
                            <p>No questions added yet. Click "Add Question" to create your first question.</p>
                        </div>
                    <?php else: ?>
                        <div class="space-y-4">
                            <?php foreach ($questions as $index => $q): ?>
                                <div class="border rounded-lg p-4">
                                    <div class="flex justify-between items-start">
                                        <div class="flex-1">
                                            <div class="flex items-center mb-2">
                                                <span class="bg-blue-100 text-blue-800 text-xs font-medium px-2.5 py-0.5 rounded mr-2">
                                                    Question <?= $index + 1 ?>
                                                </span>
                                                <span class="text-sm text-gray-500 mr-2">
                                                    <?= ucfirst(str_replace('_', ' ', $q['question_type'])) ?>
                                                </span>
                                                <span class="text-sm text-gray-500">
                                                    <?= $q['points'] ?> point(s)
                                                </span>
                                            </div>
                                            <p class="text-gray-800"><?= htmlspecialchars($q['question_text']) ?></p>
                                            <?php if ($q['option_count'] > 0): ?>
                                                <p class="text-sm text-gray-500 mt-1"><?= $q['option_count'] ?> option(s)</p>
                                            <?php endif; ?>
                                        </div>
                                        <div class="flex space-x-2">
                                            <button onclick="editQuestion(<?= $q['id'] ?>)" class="text-blue-600 hover:text-blue-800">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <a href="?video_id=<?= $video_id ?>&delete_question=<?= $q['id'] ?>" 
                                               onclick="return confirm('Delete this question?')"
                                               class="text-red-600 hover:text-red-800">
                                                <i class="fas fa-trash"></i>
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Student Attempts Summary -->
                <div class="bg-white rounded-lg shadow-sm p-6">
                    <h2 class="text-xl font-bold mb-4">Student Attempts</h2>
                    <?php
                    $stmt = $pdo->prepare("
                        SELECT 
                            u.name,
                            u.email,
                            COUNT(DISTINCT qa.id) as attempts,
                            MAX(qa.score) as best_score,
                            MAX(CASE WHEN qa.passed = 1 THEN 1 ELSE 0 END) as passed
                        FROM quiz_attempts qa
                        JOIN users u ON qa.student_id = u.id
                        WHERE qa.quiz_id = ?
                        GROUP BY u.id, u.name, u.email
                        ORDER BY attempts DESC
                    ");
                    $stmt->execute([$quiz['id']]);
                    $attempts = $stmt->fetchAll();
                    ?>
                    
                    <?php if (empty($attempts)): ?>
                        <p class="text-gray-500 text-center py-4">No student attempts yet.</p>
                    <?php else: ?>
                        <div class="overflow-x-auto">
                            <table class="min-w-full">
                                <thead>
                                    <tr class="bg-gray-50">
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Student</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Attempts</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Best Score</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-200">
                                    <?php foreach ($attempts as $attempt): ?>
                                    <tr>
                                        <td class="px-6 py-4">
                                            <div class="font-medium"><?= htmlspecialchars($attempt['name']) ?></div>
                                            <div class="text-sm text-gray-500"><?= htmlspecialchars($attempt['email']) ?></div>
                                        </td>
                                        <td class="px-6 py-4"><?= $attempt['attempts'] ?></td>
                                        <td class="px-6 py-4"><?= $attempt['best_score'] ?>%</td>
                                        <td class="px-6 py-4">
                                            <?php if ($attempt['passed']): ?>
                                                <span class="px-2 py-1 bg-green-100 text-green-800 rounded-full text-xs">Passed</span>
                                            <?php else: ?>
                                                <span class="px-2 py-1 bg-yellow-100 text-yellow-800 rounded-full text-xs">In Progress</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Add Question Modal -->
    <div id="questionModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden items-center justify-center z-50">
        <div class="bg-white rounded-xl p-8 max-w-2xl w-full max-h-[90vh] overflow-y-auto">
            <div class="flex justify-between items-center mb-6">
                <h2 class="text-2xl font-bold text-gray-800">Add Question</h2>
                <button onclick="closeQuestionModal()" class="text-gray-400 hover:text-gray-600">
                    <i class="fas fa-times text-2xl"></i>
                </button>
            </div>
            
            <form method="POST" id="questionForm">
                <input type="hidden" name="quiz_id" value="<?= $quiz['id'] ?? '' ?>">
                <input type="hidden" name="add_question" value="1">
                
                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Question Type</label>
                        <select name="question_type" id="questionType" required 
                                onchange="toggleOptionsField()"
                                class="w-full px-4 py-2 border border-gray-300 rounded-lg">
                            <option value="multiple_choice">Multiple Choice</option>
                            <option value="true_false">True/False</option>
                            <option value="short_answer">Short Answer</option>
                        </select>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Question Text *</label>
                        <textarea name="question_text" rows="3" required 
                                  class="w-full px-4 py-2 border border-gray-300 rounded-lg"></textarea>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Points</label>
                        <input type="number" name="points" min="1" value="1" 
                               class="w-full px-4 py-2 border border-gray-300 rounded-lg">
                    </div>
                    
                    <div id="optionsContainer">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Options</label>
                        <div id="optionsList">
                            <!-- Options will be added here via JavaScript -->
                        </div>
                        <button type="button" onclick="addOption()" class="mt-2 text-blue-600 hover:text-blue-800">
                            <i class="fas fa-plus mr-1"></i>Add Option
                        </button>
                    </div>
                    
                    <div class="flex justify-end space-x-3 mt-6">
                        <button type="button" onclick="closeQuestionModal()" 
                                class="px-6 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50">
                            Cancel
                        </button>
                        <button type="submit" 
                                class="px-6 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700">
                            Add Question
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <script>
        let optionCount = 0;
        
        function openAddQuestionModal() {
            document.getElementById('questionModal').classList.remove('hidden');
            document.getElementById('questionModal').classList.add('flex');
            resetOptions();
        }
        
        function closeQuestionModal() {
            document.getElementById('questionModal').classList.add('hidden');
            document.getElementById('questionModal').classList.remove('flex');
            document.getElementById('questionForm').reset();
            resetOptions();
        }
        
        function toggleOptionsField() {
            const type = document.getElementById('questionType').value;
            const optionsContainer = document.getElementById('optionsContainer');
            
            if (type === 'multiple_choice' || type === 'true_false') {
                optionsContainer.style.display = 'block';
                if (type === 'true_false') {
                    // Clear and set true/false options
                    document.getElementById('optionsList').innerHTML = `
                        <div class="mb-2 flex items-center">
                            <input type="radio" name="correct_option" value="0" class="mr-2" checked>
                            <input type="text" name="options[0]" value="True" readonly 
                                   class="flex-1 px-3 py-2 bg-gray-100 border border-gray-300 rounded">
                        </div>
                        <div class="mb-2 flex items-center">
                            <input type="radio" name="correct_option" value="1" class="mr-2">
                            <input type="text" name="options[1]" value="False" readonly 
                                   class="flex-1 px-3 py-2 bg-gray-100 border border-gray-300 rounded">
                        </div>
                    `;
                } else {
                    resetOptions();
                    addOption(); // Add first option
                }
            } else {
                optionsContainer.style.display = 'none';
            }
        }
        
        function addOption() {
            const optionsList = document.getElementById('optionsList');
            const div = document.createElement('div');
            div.className = 'mb-2 flex items-center';
            div.innerHTML = `
                <input type="radio" name="correct_option" value="${optionCount}" class="mr-2">
                <input type="text" name="options[${optionCount}]" placeholder="Option text" required 
                       class="flex-1 px-3 py-2 border border-gray-300 rounded mr-2">
                <button type="button" onclick="this.parentElement.remove()" class="text-red-600 hover:text-red-800">
                    <i class="fas fa-times"></i>
                </button>
            `;
            optionsList.appendChild(div);
            optionCount++;
        }
        
        function resetOptions() {
            document.getElementById('optionsList').innerHTML = '';
            optionCount = 0;
        }
        
        // Initialize on page load
        document.addEventListener('DOMContentLoaded', function() {
            if (document.getElementById('questionType')) {
                toggleOptionsField();
            }
        });
        
        // Close modal when clicking outside
        window.onclick = function(event) {
            const modal = document.getElementById('questionModal');
            if (event.target === modal) {
                closeQuestionModal();
            }
        }
    </script>
</body>
</html>