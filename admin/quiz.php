<?php
require_once '../includes/auth.php';
require_once '../includes/auto_publish.php';
requireStudent();

$message = '';
$error = '';

// Get quiz ID from URL
$quiz_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Fetch quiz details
$stmt = $pdo->prepare("
    SELECT q.*, v.title as video_title, v.id as video_id,
           c.id as course_id, c.title as course_title
    FROM quizzes q
    JOIN videos v ON q.video_id = v.id
    JOIN courses c ON v.course_id = c.id
    WHERE q.id = ?
");
$stmt->execute([$quiz_id]);
$quiz = $stmt->fetch();

if (!$quiz) {
    header('Location: dashboard.php');
    exit;
}

// Check if student has already passed this quiz
$stmt = $pdo->prepare("
    SELECT id, passed, attempt_number 
    FROM quiz_attempts 
    WHERE quiz_id = ? AND student_id = ? AND passed = 1
    ORDER BY attempt_number DESC
    LIMIT 1
");
$stmt->execute([$quiz_id, $_SESSION['user_id']]);
$passed_attempt = $stmt->fetch();

if ($passed_attempt && $quiz['max_attempts'] == 1) {
    $_SESSION['error'] = 'You have already passed this quiz and cannot retake it.';
    header('Location: watch.php?video_id=' . $quiz['video_id']);
    exit;
}

// Check number of attempts
$stmt = $pdo->prepare("
    SELECT COUNT(*) as attempt_count, MAX(attempt_number) as max_attempt 
    FROM quiz_attempts 
    WHERE quiz_id = ? AND student_id = ?
");
$stmt->execute([$quiz_id, $_SESSION['user_id']]);
$attempt_info = $stmt->fetch();

$current_attempt = $attempt_info['max_attempt'] + 1;

if ($quiz['max_attempts'] && $attempt_info['attempt_count'] >= $quiz['max_attempts']) {
    $_SESSION['error'] = 'You have reached the maximum number of attempts for this quiz.';
    header('Location: watch.php?video_id=' . $quiz['video_id']);
    exit;
}

// Check for existing incomplete attempt
$stmt = $pdo->prepare("
    SELECT * FROM quiz_attempts 
    WHERE quiz_id = ? AND student_id = ? AND status = 'in_progress'
    ORDER BY started_at DESC
    LIMIT 1
");
$stmt->execute([$quiz_id, $_SESSION['user_id']]);
$incomplete_attempt = $stmt->fetch();

if ($incomplete_attempt) {
    // Resume existing attempt
    $attempt_id = $incomplete_attempt['id'];
    $attempt_number = $incomplete_attempt['attempt_number'];
    
    // Get answers so far
    $stmt = $pdo->prepare("
        SELECT question_id, selected_option_id, answer_text 
        FROM quiz_answers 
        WHERE attempt_id = ?
    ");
    $stmt->execute([$attempt_id]);
    $saved_answers = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
} else {
    // Create new attempt
    $stmt = $pdo->prepare("
        INSERT INTO quiz_attempts (quiz_id, student_id, attempt_number, status, started_at)
        VALUES (?, ?, ?, 'in_progress', NOW())
    ");
    $stmt->execute([$quiz_id, $_SESSION['user_id'], $current_attempt]);
    $attempt_id = $pdo->lastInsertId();
    $attempt_number = $current_attempt;
    $saved_answers = [];
}

// Get quiz questions
$stmt = $pdo->prepare("
    SELECT q.*, 
           GROUP_CONCAT(
               JSON_OBJECT(
                   'id', o.id,
                   'option_text', o.option_text,
                   'order_num', o.order_num
               )
           ) as options_json
    FROM quiz_questions q
    LEFT JOIN quiz_options o ON q.id = o.question_id
    WHERE q.quiz_id = ?
    GROUP BY q.id
    ORDER BY q.order_num
");
$stmt->execute([$quiz_id]);
$questions = $stmt->fetchAll();

// Process each question's options
foreach ($questions as &$question) {
    if ($question['options_json']) {
        $options = [];
        $option_strings = explode(',', $question['options_json']);
        foreach ($option_strings as $opt_json) {
            $opt = json_decode($opt_json, true);
            if ($opt) {
                $options[] = $opt;
            }
        }
        $question['options'] = $options;
    } else {
        $question['options'] = [];
    }
}

// Handle quiz submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_quiz'])) {
    $answers = $_POST['answers'] ?? [];
    $time_taken = time() - strtotime($incomplete_attempt['started_at'] ?? 'now');
    
    try {
        $pdo->beginTransaction();
        
        $total_points = 0;
        $earned_points = 0;
        
        // Save answers and calculate score
        foreach ($questions as $question) {
            $answer = $answers[$question['id']] ?? null;
            $is_correct = false;
            $points_earned = 0;
            
            if ($answer) {
                if ($question['question_type'] == 'multiple_choice' || $question['question_type'] == 'true_false') {
                    // Check if selected option is correct
                    $stmt = $pdo->prepare("SELECT is_correct FROM quiz_options WHERE id = ?");
                    $stmt->execute([$answer]);
                    $is_correct = $stmt->fetchColumn();
                    
                    if ($is_correct) {
                        $points_earned = $question['points'];
                    }
                    
                    // Save answer
                    $stmt = $pdo->prepare("
                        INSERT INTO quiz_answers (attempt_id, question_id, selected_option_id, is_correct, points_earned)
                        VALUES (?, ?, ?, ?, ?)
                    ");
                    $stmt->execute([$attempt_id, $question['id'], $answer, $is_correct, $points_earned]);
                    
                } elseif ($question['question_type'] == 'short_answer') {
                    // For short answer, we'd need more sophisticated checking
                    // This is a simplified version
                    $points_earned = $question['points']; // Assume correct for demo
                    
                    $stmt = $pdo->prepare("
                        INSERT INTO quiz_answers (attempt_id, question_id, answer_text, points_earned)
                        VALUES (?, ?, ?, ?)
                    ");
                    $stmt->execute([$attempt_id, $question['id'], $answer, $points_earned]);
                }
            }
            
            $total_points += $question['points'];
            $earned_points += $points_earned;
        }
        
        $percentage = $total_points > 0 ? ($earned_points / $total_points) * 100 : 0;
        $passed = $percentage >= $quiz['passing_score'];
        
        // Update attempt
        $stmt = $pdo->prepare("
            UPDATE quiz_attempts 
            SET completed_at = NOW(), 
                score = ?, 
                percentage = ?, 
                passed = ?, 
                time_taken = ?,
                status = 'completed'
            WHERE id = ?
        ");
        $stmt->execute([$earned_points, $percentage, $passed, $time_taken, $attempt_id]);
        
        // If passed and there's a next video, mark video progress
        if ($passed && $quiz['requires_quiz']) {
            // Mark current video as completed
            $stmt = $pdo->prepare("
                INSERT INTO video_progress (student_id, video_id, completed, completed_at)
                VALUES (?, ?, 1, NOW())
                ON DUPLICATE KEY UPDATE completed = 1, completed_at = NOW()
            ");
            $stmt->execute([$_SESSION['user_id'], $quiz['video_id']]);
        }
        
        $pdo->commit();
        
        // Redirect to results page
        header("Location: quiz_result.php?attempt_id=$attempt_id");
        exit;
        
    } catch (PDOException $e) {
        $pdo->rollBack();
        $error = 'Failed to submit quiz: ' . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($quiz['title']) ?> - Quiz</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .timer-warning {
            animation: pulse 1s infinite;
        }
        @keyframes pulse {
            0% { background-color: #fef3c7; }
            50% { background-color: #fde68a; }
            100% { background-color: #fef3c7; }
        }
        .timer-danger {
            animation: blink 1s infinite;
        }
        @keyframes blink {
            0% { background-color: #fee2e2; color: #991b1b; }
            50% { background-color: #fecaca; color: #7f1d1d; }
            100% { background-color: #fee2e2; color: #991b1b; }
        }
    </style>
</head>
<body class="bg-gray-50">
    <div class="min-h-screen">
        <!-- Header -->
        <div class="bg-white shadow-sm">
            <div class="max-w-7xl mx-auto px-4 py-4 flex justify-between items-center">
                <div>
                    <h1 class="text-xl font-bold text-gray-800"><?= htmlspecialchars($quiz['title']) ?></h1>
                    <p class="text-sm text-gray-600">
                        Course: <?= htmlspecialchars($quiz['course_title']) ?> | 
                        Video: <?= htmlspecialchars($quiz['video_title']) ?>
                    </p>
                </div>
                <div class="flex items-center space-x-4">
                    <div id="timer" class="text-lg font-mono bg-gray-100 px-4 py-2 rounded-lg">
                        <?= $quiz['time_limit'] ?>:00
                    </div>
                    <a href="watch.php?video_id=<?= $quiz['video_id'] ?>" class="text-gray-600 hover:text-gray-800">
                        <i class="fas fa-times text-2xl"></i>
                    </a>
                </div>
            </div>
        </div>
        
        <!-- Quiz Content -->
        <div class="max-w-4xl mx-auto px-4 py-8">
            <?php if ($error): ?>
                <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
                    <?= $error ?>
                </div>
            <?php endif; ?>
            
            <div class="bg-white rounded-lg shadow-sm p-6 mb-4">
                <div class="flex justify-between items-center mb-4">
                    <div>
                        <span class="bg-blue-100 text-blue-800 text-sm font-medium px-3 py-1 rounded">
                            Attempt <?= $attempt_number ?>
                            <?php if ($quiz['max_attempts']): ?>
                                of <?= $quiz['max_attempts'] ?>
                            <?php endif; ?>
                        </span>
                        <span class="ml-2 text-sm text-gray-500">
                            Passing score: <?= $quiz['passing_score'] ?>%
                        </span>
                    </div>
                    <div class="text-sm text-gray-500">
                        <i class="far fa-clock mr-1"></i>
                        Time remaining: <span id="timeRemaining"><?= $quiz['time_limit'] ?>:00</span>
                    </div>
                </div>
                
                <form method="POST" id="quizForm">
                    <div class="space-y-6">
                        <?php foreach ($questions as $index => $question): ?>
                            <div class="border rounded-lg p-4">
                                <div class="flex items-start mb-3">
                                    <span class="bg-gray-200 text-gray-700 text-sm font-medium px-3 py-1 rounded mr-3">
                                        Q<?= $index + 1 ?>
                                    </span>
                                    <div class="flex-1">
                                        <p class="text-gray-800 font-medium"><?= htmlspecialchars($question['question_text']) ?></p>
                                        <p class="text-sm text-gray-500 mt-1"><?= $question['points'] ?> point(s)</p>
                                    </div>
                                </div>
                                
                                <div class="ml-8 space-y-2">
                                    <?php if ($question['question_type'] == 'multiple_choice'): ?>
                                        <?php foreach ($question['options'] as $option): ?>
                                            <label class="flex items-center p-2 hover:bg-gray-50 rounded">
                                                <input type="radio" 
                                                       name="answers[<?= $question['id'] ?>]" 
                                                       value="<?= $option['id'] ?>"
                                                       <?= isset($saved_answers[$question['id']]) && $saved_answers[$question['id']] == $option['id'] ? 'checked' : '' ?>
                                                       class="mr-3">
                                                <span><?= htmlspecialchars($option['option_text']) ?></span>
                                            </label>
                                        <?php endforeach; ?>
                                        
                                    <?php elseif ($question['question_type'] == 'true_false'): ?>
                                        <label class="flex items-center p-2 hover:bg-gray-50 rounded">
                                            <input type="radio" 
                                                   name="answers[<?= $question['id'] ?>]" 
                                                   value="<?= $question['options'][0]['id'] ?? '' ?>"
                                                   <?= isset($saved_answers[$question['id']]) && $saved_answers[$question['id']] == ($question['options'][0]['id'] ?? '') ? 'checked' : '' ?>
                                                   class="mr-3">
                                            <span>True</span>
                                        </label>
                                        <label class="flex items-center p-2 hover:bg-gray-50 rounded">
                                            <input type="radio" 
                                                   name="answers[<?= $question['id'] ?>]" 
                                                   value="<?= $question['options'][1]['id'] ?? '' ?>"
                                                   <?= isset($saved_answers[$question['id']]) && $saved_answers[$question['id']] == ($question['options'][1]['id'] ?? '') ? 'checked' : '' ?>
                                                   class="mr-3">
                                            <span>False</span>
                                        </label>
                                        
                                    <?php elseif ($question['question_type'] == 'short_answer'): ?>
                                        <textarea name="answers[<?= $question['id'] ?>]" 
                                                  rows="3"
                                                  placeholder="Type your answer here..."
                                                  class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"><?= htmlspecialchars($saved_answers[$question['id']] ?? '') ?></textarea>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <div class="mt-6 flex justify-end">
                        <button type="submit" 
                                name="submit_quiz"
                                onclick="return confirm('Are you sure you want to submit the quiz?')"
                                class="px-6 py-3 bg-green-600 text-white rounded-lg hover:bg-green-700">
                            <i class="fas fa-check-circle mr-2"></i>Submit Quiz
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <script>
        // Timer functionality
        let timeLimit = <?= $quiz['time_limit'] ?> * 60; // convert to seconds
        const timerElement = document.getElementById('timer');
        const timeRemainingElement = document.getElementById('timeRemaining');
        
        function updateTimer() {
            const minutes = Math.floor(timeLimit / 60);
            const seconds = timeLimit % 60;
            
            const timeString = `${minutes.toString().padStart(2, '0')}:${seconds.toString().padStart(2, '0')}`;
            timerElement.textContent = timeString;
            timeRemainingElement.textContent = timeString;
            
            // Visual warnings
            if (timeLimit <= 300) { // 5 minutes remaining
                timerElement.classList.add('timer-danger');
                timerElement.classList.remove('timer-warning');
            } else if (timeLimit <= 600) { // 10 minutes remaining
                timerElement.classList.add('timer-warning');
                timerElement.classList.remove('timer-danger');
            }
            
            if (timeLimit <= 0) {
                // Time's up - auto submit
                document.getElementById('quizForm').submit();
            } else {
                timeLimit--;
                setTimeout(updateTimer, 1000);
            }
        }
        
        // Start timer
        updateTimer();
        
        // Prevent accidental navigation
        window.addEventListener('beforeunload', function(e) {
            e.preventDefault();
            e.returnValue = 'You have an ongoing quiz. Are you sure you want to leave?';
        });
        
        // Remove warning on form submit
        document.getElementById('quizForm').addEventListener('submit', function() {
            window.removeEventListener('beforeunload', function() {});
        });
    </script>
</body>
</html>