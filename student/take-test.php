<?php
require_once '../includes/auth.php';
require_once '../includes/student-functions.php';
require_once '../includes/auto_publish.php';
requireStudent();

// Run auto-publisher on every page load
if (function_exists('autoPublishVideos')) {
    autoPublishVideos($pdo);
}

// Get test ID from URL
$test_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$user_id = $_SESSION['user_id'];

// Simple validation - just check if ID exists
if ($test_id <= 0) {
    $_SESSION['error'] = "Invalid test ID";
    header('Location: tests.php');
    exit;
}

// Get test details directly from academic_tests
try {
    $stmt = $pdo->prepare("
        SELECT t.*, c.title as course_title, c.id as course_id, c.class, c.subject
        FROM academic_tests t
        JOIN academic_courses c ON t.course_id = c.id
        WHERE t.id = ?
    ");
    $stmt->execute([$test_id]);
    $test = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Error fetching test: " . $e->getMessage());
    $_SESSION['error'] = "Error fetching test details";
    header('Location: tests.php');
    exit;
}

// If test not found
if (!$test) {
    $_SESSION['error'] = "Test not found";
    header('Location: tests.php');
    exit;
}

// Get questions
try {
    $stmt = $pdo->prepare("SELECT * FROM academic_questions WHERE test_id = ? ORDER BY id");
    $stmt->execute([$test_id]);
    $questions = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $questions = [];
}

// Handle test submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_test'])) {
    $answers = $_POST['answers'] ?? [];
    $score = 0;
    $total_marks = array_sum(array_column($questions, 'marks'));
    
    // Calculate score
    foreach ($questions as $q) {
        $user_answer = $answers[$q['id']] ?? '';
        if (!empty($user_answer) && $user_answer == $q['correct_answer']) {
            $score += $q['marks'];
        }
    }
    
    $passed = $score >= $test['passing_marks'] ? 1 : 0;
    
    // Save attempt and mark as completed
    try {
        // First, check if test_attempts table exists, if not create it
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS test_attempts (
                id INT AUTO_INCREMENT PRIMARY KEY,
                student_id INT NOT NULL,
                test_id INT NOT NULL,
                score INT DEFAULT 0,
                total_marks INT,
                passed BOOLEAN DEFAULT FALSE,
                completed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                answers JSON,
                test_type VARCHAR(20) DEFAULT 'academic',
                INDEX idx_student (student_id),
                INDEX idx_test (test_id)
            )
        ");
        
        // Insert the attempt
        $stmt = $pdo->prepare("
            INSERT INTO test_attempts (student_id, test_id, score, total_marks, passed, completed_at, answers, test_type) 
            VALUES (?, ?, ?, ?, ?, NOW(), ?, 'academic')
        ");
        $answers_json = json_encode($answers);
        $stmt->execute([$user_id, $test_id, $score, $total_marks, $passed, $answers_json]);
        $attempt_id = $pdo->lastInsertId();
        
        // Update the test status in the tests array (for session)
        $_SESSION['test_completed'][$test_id] = [
            'completed' => true,
            'passed' => $passed,
            'score' => $score,
            'attempt_id' => $attempt_id
        ];
        
        // Redirect to results page
        header('Location: test-result.php?attempt=' . $attempt_id);
        exit;
        
    } catch (PDOException $e) {
        error_log("Error saving attempt: " . $e->getMessage());
        
        // Fallback: store in session
        $_SESSION['result'] = [
            'score' => $score,
            'total_marks' => $total_marks,
            'passed' => $passed,
            'test_id' => $test_id,
            'test_title' => $test['title'],
            'course_title' => $test['course_title']
        ];
        header('Location: test-result.php');
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($test['title']) ?> - Test</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { font-family: 'Inter', sans-serif; }
        body { background-color: #f8fafc; }
        .question-card { transition: all 0.3s ease; border: 1px solid #e2e8f0; }
        .question-card:hover { transform: translateY(-2px); box-shadow: 0 20px 25px -5px rgba(0,0,0,0.1); }
        .option-label { transition: all 0.2s ease; border: 2px solid #e2e8f0; cursor: pointer; }
        .option-label:hover { background-color: #f8fafc; border-color: #94a3b8; }
        .option-label.selected { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; border-color: transparent; }
        .option-label.selected .option-letter { background: white; color: #667eea; }
        .timer-warning { animation: pulse 1s infinite; color: #ef4444; }
        @keyframes pulse { 0%, 100% { opacity: 1; } 50% { opacity: 0.5; } }
        .question-nav-item { width: 40px; height: 40px; display: flex; align-items: center; justify-content: center; border-radius: 8px; font-weight: 500; cursor: pointer; transition: all 0.2s ease; }
        .question-nav-item.answered { background: linear-gradient(135deg, #10b981 0%, #059669 100%); color: white; }
        .question-nav-item.current { border: 3px solid #667eea; transform: scale(1.1); font-weight: bold; }
        .question-nav-item:not(.answered):not(.current) { background-color: #e2e8f0; color: #1e293b; }
    </style>
</head>
<body class="bg-gray-50">
    <div class="flex h-screen">
        <?php include 'sidebar.php'; ?>

        <div class="flex-1 overflow-auto">
            <?php include 'header.php'; ?>

            <div class="p-6">
                <!-- Test Header -->
                <div class="bg-white rounded-xl shadow-sm p-6 mb-6 sticky top-0 z-10 border border-gray-200">
                    <div class="flex flex-wrap items-center justify-between gap-4">
                        <div>
                            <h1 class="text-2xl font-bold text-gray-800"><?= htmlspecialchars($test['title']) ?></h1>
                            <p class="text-gray-600 mt-1"><?= htmlspecialchars($test['course_title']) ?></p>
                        </div>
                        
                        <div class="flex items-center gap-4">
                            <div class="text-center px-4 py-2 bg-gray-50 rounded-lg">
                                <div class="text-sm text-gray-500">Questions</div>
                                <div class="text-xl font-bold text-gray-800"><?= count($questions) ?></div>
                            </div>
                            <div class="text-center px-4 py-2 bg-gray-50 rounded-lg">
                                <div class="text-sm text-gray-500">Total Marks</div>
                                <div class="text-xl font-bold text-gray-800"><?= $test['total_marks'] ?></div>
                            </div>
                            <div class="text-center px-4 py-2 bg-gradient-to-r from-purple-600 to-pink-600 text-white rounded-lg">
                                <div class="text-sm text-purple-100">Time Left</div>
                                <div id="timer" class="text-2xl font-bold"><?= $test['duration'] ?>:00</div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Progress Bar -->
                    <div class="mt-4">
                        <div class="flex justify-between text-sm mb-1">
                            <span class="text-gray-600">Progress</span>
                            <span class="font-medium text-purple-600" id="progressPercent">0%</span>
                        </div>
                        <div class="bg-gray-200 rounded-full h-2.5">
                            <div id="progressBar" class="bg-gradient-to-r from-purple-600 to-pink-600 h-2.5 rounded-full transition-all duration-300" style="width: 0%"></div>
                        </div>
                    </div>
                </div>

                <div class="flex flex-col lg:flex-row gap-6">
                    <!-- Main Test Area -->
                    <div class="flex-1">
                        <form method="POST" id="testForm" class="space-y-6">
                            <?php foreach ($questions as $index => $q): ?>
                                <div id="question-<?= $q['id'] ?>" class="question-card bg-white rounded-xl shadow-sm p-6 scroll-mt-32 border border-gray-200">
                                    <div class="flex items-start justify-between mb-4">
                                        <div class="flex items-center">
                                            <span class="flex items-center justify-center w-8 h-8 bg-gradient-to-r from-purple-600 to-pink-600 text-white rounded-lg font-bold mr-3 shadow-sm">
                                                <?= $index + 1 ?>
                                            </span>
                                            <h3 class="font-semibold text-gray-800"><?= htmlspecialchars($q['question']) ?></h3>
                                        </div>
                                        <span class="px-3 py-1 bg-purple-100 text-purple-700 text-sm rounded-full font-medium">
                                            <?= $q['marks'] ?> mark<?= $q['marks'] > 1 ? 's' : '' ?>
                                        </span>
                                    </div>
                                    
                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4">
                                        <?php foreach (['A', 'B', 'C', 'D'] as $option): ?>
                                            <?php 
                                            $option_field = 'option_' . strtolower($option);
                                            $option_value = $q[$option_field] ?? '';
                                            if (!empty($option_value)): 
                                            ?>
                                            <label class="option-label relative block p-4 rounded-lg" data-question="<?= $q['id'] ?>" data-option="<?= $option ?>">
                                                <input type="radio" name="answers[<?= $q['id'] ?>]" value="<?= $option ?>" class="absolute opacity-0" onchange="updateQuestionStatus(<?= $q['id'] ?>)">
                                                <div class="flex items-center">
                                                    <span class="option-letter flex items-center justify-center w-6 h-6 bg-gray-100 rounded-full text-sm font-medium mr-3">
                                                        <?= $option ?>
                                                    </span>
                                                    <span class="text-gray-700"><?= htmlspecialchars($option_value) ?></span>
                                                </div>
                                            </label>
                                            <?php endif; ?>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>

                            <!-- Submit Button -->
                            <div class="flex justify-end space-x-4 sticky bottom-0 bg-white p-4 rounded-lg shadow-lg border border-gray-200">
                                <a href="tests.php" class="px-6 py-3 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50 transition font-medium">
                                    Cancel
                                </a>
                                <button type="submit" name="submit_test" onclick="return confirmSubmit()" class="px-8 py-3 bg-gradient-to-r from-purple-600 to-pink-600 text-white rounded-lg hover:opacity-90 transition font-medium shadow-md">
                                    <i class="fas fa-check-circle mr-2"></i>Submit Test
                                </button>
                            </div>
                        </form>
                    </div>

                    <!-- Question Navigator -->
                    <div class="lg:w-80 sticky top-32 h-fit">
                        <div class="bg-white rounded-xl shadow-sm p-6 border border-gray-200">
                            <h4 class="font-semibold text-gray-800 mb-4 flex items-center">
                                <i class="fas fa-compass mr-2 text-purple-600"></i>
                                Question Navigator
                            </h4>
                            
                            <div class="grid grid-cols-5 gap-2 mb-4">
                                <?php foreach ($questions as $index => $q): ?>
                                    <div class="question-nav-item" data-question="<?= $q['id'] ?>" onclick="scrollToQuestion(<?= $q['id'] ?>)">
                                        <?= $index + 1 ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            
                            <div class="mt-6 pt-4 border-t border-gray-200">
                                <div class="space-y-2">
                                    <div class="flex items-center justify-between text-sm">
                                        <span class="text-gray-600">Answered</span>
                                        <span class="font-bold text-green-600" id="answeredCount">0</span>
                                    </div>
                                    <div class="flex items-center justify-between text-sm">
                                        <span class="text-gray-600">Remaining</span>
                                        <span class="font-bold text-gray-600" id="remainingCount"><?= count($questions) ?></span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Timer functionality
        let timeLeft = <?= $test['duration'] * 60 ?>;
        const timerDisplay = document.getElementById('timer');
        let timerInterval;

        function startTimer() {
            timerInterval = setInterval(() => {
                timeLeft--;
                const minutes = Math.floor(timeLeft / 60);
                const seconds = timeLeft % 60;
                timerDisplay.textContent = `${minutes}:${seconds.toString().padStart(2, '0')}`;
                
                if (timeLeft <= 300) {
                    timerDisplay.parentElement.classList.add('timer-warning');
                }
                
                if (timeLeft <= 0) {
                    clearInterval(timerInterval);
                    alert('Time is up! Your test will be submitted automatically.');
                    document.getElementById('testForm').submit();
                }
            }, 1000);
        }

        startTimer();

        // Question navigation
        const answeredQuestions = new Set();
        const totalQuestions = <?= count($questions) ?>;
        
        function updateQuestionStatus(questionId) {
            const radio = document.querySelector(`input[name="answers[${questionId}]"]:checked`);
            const navItem = document.querySelector(`.question-nav-item[data-question="${questionId}"]`);
            
            if (radio) {
                answeredQuestions.add(parseInt(questionId));
                if (navItem) navItem.classList.add('answered');
                
                document.querySelectorAll(`.option-label[data-question="${questionId}"]`).forEach(label => {
                    label.classList.remove('selected');
                });
                radio.closest('.option-label').classList.add('selected');
            } else {
                answeredQuestions.delete(parseInt(questionId));
                if (navItem) navItem.classList.remove('answered');
            }
            
            updateCounts();
            updateProgressBar();
        }

        function updateCounts() {
            document.getElementById('answeredCount').textContent = answeredQuestions.size;
            document.getElementById('remainingCount').textContent = totalQuestions - answeredQuestions.size;
        }

        function updateProgressBar() {
            const progress = (answeredQuestions.size / totalQuestions) * 100;
            document.getElementById('progressBar').style.width = progress + '%';
            document.getElementById('progressPercent').textContent = Math.round(progress) + '%';
        }

        function scrollToQuestion(questionId) {
            const element = document.getElementById(`question-${questionId}`);
            if (element) {
                element.scrollIntoView({ behavior: 'smooth', block: 'center' });
                
                document.querySelectorAll('.question-nav-item').forEach(item => {
                    item.classList.remove('current');
                });
                const navItem = document.querySelector(`.question-nav-item[data-question="${questionId}"]`);
                if (navItem) navItem.classList.add('current');
            }
        }

        function confirmSubmit() {
            const remaining = totalQuestions - answeredQuestions.size;
            if (remaining > 0) {
                return confirm(`You have ${remaining} unanswered question(s). Are you sure you want to submit?`);
            }
            return confirm('Are you sure you want to submit the test?');
        }

        // Initialize
        document.querySelectorAll('input[type="radio"]').forEach(radio => {
            if (radio.checked) {
                const questionId = radio.name.match(/\d+/)[0];
                updateQuestionStatus(parseInt(questionId));
            }
            
            radio.addEventListener('change', function() {
                const questionId = this.name.match(/\d+/)[0];
                updateQuestionStatus(parseInt(questionId));
            });
        });

        // Prevent accidental navigation
        window.addEventListener('beforeunload', function(e) {
            if (timeLeft > 0 && answeredQuestions.size < totalQuestions) {
                e.preventDefault();
                e.returnValue = 'Your test progress will be lost if you leave.';
            }
        });
    </script>
</body>
</html>