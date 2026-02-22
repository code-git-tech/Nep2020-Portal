<?php
require_once '../includes/auth.php';
require_once '../includes/student-functions.php';
require_once '../includes/auto_publish.php';
requireStudent();

// Run auto-publisher on every page load
autoPublishVideos($pdo);

$attempt_id = $_GET['attempt'] ?? 0;
$user_id = $_SESSION['user_id'];

// Get attempt details with answers
$stmt = $pdo->prepare("
    SELECT ta.*, t.title as test_title, t.course_id, c.title as course_title,
           t.passing_marks, t.total_marks as test_total_marks,
           t.description as test_description
    FROM test_attempts ta
    JOIN tests t ON ta.test_id = t.id
    JOIN courses c ON t.course_id = c.id
    WHERE ta.id = ? AND ta.student_id = ?
");
$stmt->execute([$attempt_id, $user_id]);
$attempt = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$attempt) {
    header('Location: tests.php');
    exit;
}

// Get questions and correct answers for review
$stmt = $pdo->prepare("
    SELECT q.* FROM questions q
    WHERE q.test_id = ?
    ORDER BY q.id
");
$stmt->execute([$attempt['test_id']]);
$questions = $stmt->fetchAll(PDO::FETCH_ASSOC);

$user_answers = json_decode($attempt['answers'], true) ?? [];

// Calculate percentage
$percentage = ($attempt['score'] / $attempt['total_marks']) * 100;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Test Results - <?= htmlspecialchars($attempt['test_title']) ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .result-circle {
            transition: stroke-dashoffset 0.35s;
            transform: rotate(-90deg);
            transform-origin: 50% 50%;
        }
        .question-review {
            transition: all 0.3s ease;
        }
        .question-review:hover {
            transform: translateX(5px);
        }
        .correct-answer {
            border-left-color: #10b981;
        }
        .wrong-answer {
            border-left-color: #ef4444;
        }
    </style>
</head>
<body class="bg-gray-50">
    <div class="flex h-screen">
        <?php include 'sidebar.php'; ?>

        <div class="flex-1 overflow-auto">
            <?php include 'header.php'; ?>

            <div class="p-6">
                <div class="max-w-4xl mx-auto">
                    <!-- Result Header -->
                    <div class="bg-white rounded-xl shadow-sm p-8 mb-6">
                        <div class="flex items-center justify-between">
                            <div>
                                <h1 class="text-2xl font-bold text-gray-800"><?= htmlspecialchars($attempt['test_title']) ?></h1>
                                <p class="text-gray-600 mt-1"><?= htmlspecialchars($attempt['course_title']) ?></p>
                            </div>
                            <div class="text-right">
                                <p class="text-sm text-gray-500">Completed on</p>
                                <p class="font-semibold text-gray-800"><?= date('F d, Y H:i', strtotime($attempt['completed_at'])) ?></p>
                            </div>
                        </div>
                    </div>

                    <!-- Result Card -->
                    <div class="bg-white rounded-xl shadow-sm p-8 text-center mb-6">
                        <!-- Result Icon -->
                        <?php if ($attempt['passed']): ?>
                        <div class="w-24 h-24 bg-green-100 rounded-full mx-auto mb-4 flex items-center justify-center animate-bounce">
                            <i class="fas fa-trophy text-green-600 text-4xl"></i>
                        </div>
                        <h1 class="text-3xl font-bold text-gray-800 mb-2">Congratulations!</h1>
                        <p class="text-gray-600 mb-6">You passed the test</p>
                        <?php else: ?>
                        <div class="w-24 h-24 bg-red-100 rounded-full mx-auto mb-4 flex items-center justify-center">
                            <i class="fas fa-times-circle text-red-600 text-4xl"></i>
                        </div>
                        <h1 class="text-3xl font-bold text-gray-800 mb-2">Not this time</h1>
                        <p class="text-gray-600 mb-6">Keep practicing and try again</p>
                        <?php endif; ?>

                        <!-- Score Circle -->
                        <div class="relative w-48 h-48 mx-auto mb-6">
                            <svg class="w-48 h-48 transform -rotate-90">
                                <circle cx="96" cy="96" r="88" stroke="#e5e7eb" stroke-width="12" fill="transparent"></circle>
                                <circle cx="96" cy="96" r="88" 
                                        stroke="<?= $attempt['passed'] ? '#10b981' : '#ef4444' ?>" 
                                        stroke-width="12" 
                                        fill="transparent"
                                        stroke-dasharray="<?= 2 * pi() * 88 ?>" 
                                        stroke-dashoffset="<?= 2 * pi() * 88 * (1 - $percentage / 100) ?>"
                                        class="result-circle"></circle>
                            </svg>
                            <div class="absolute inset-0 flex items-center justify-center">
                                <div class="text-center">
                                    <span class="text-4xl font-bold <?= $attempt['passed'] ? 'text-green-600' : 'text-red-600' ?>">
                                        <?= round($percentage, 1) ?>%
                                    </span>
                                    <p class="text-sm text-gray-500 mt-1">Your Score</p>
                                </div>
                            </div>
                        </div>

                        <!-- Stats Grid -->
                        <div class="grid grid-cols-3 gap-4 mb-8 max-w-md mx-auto">
                            <div class="p-4 bg-gray-50 rounded-lg">
                                <p class="text-2xl font-bold text-gray-800"><?= $attempt['score'] ?></p>
                                <p class="text-xs text-gray-500">Marks Obtained</p>
                            </div>
                            <div class="p-4 bg-gray-50 rounded-lg">
                                <p class="text-2xl font-bold text-gray-800"><?= $attempt['total_marks'] ?></p>
                                <p class="text-xs text-gray-500">Total Marks</p>
                            </div>
                            <div class="p-4 bg-gray-50 rounded-lg">
                                <p class="text-2xl font-bold text-gray-800"><?= $attempt['passing_marks'] ?></p>
                                <p class="text-xs text-gray-500">Passing Marks</p>
                            </div>
                        </div>

                        <!-- Action Buttons -->
                        <div class="flex justify-center space-x-4">
                            <a href="course-view.php?id=<?= $attempt['course_id'] ?>" 
                               class="px-6 py-3 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition">
                                <i class="fas fa-book-open mr-2"></i>Back to Course
                            </a>
                            <?php if (!$attempt['passed']): ?>
                            <a href="take-test.php?id=<?= $attempt['test_id'] ?>" 
                               class="px-6 py-3 border border-purple-600 text-purple-600 rounded-lg hover:bg-purple-50 transition">
                                <i class="fas fa-redo mr-2"></i>Try Again
                            </a>
                            <?php endif; ?>
                            <button onclick="window.print()" 
                                    class="px-6 py-3 border border-gray-300 text-gray-600 rounded-lg hover:bg-gray-50 transition">
                                <i class="fas fa-print mr-2"></i>Print
                            </button>
                        </div>
                    </div>

                    <!-- Detailed Review -->
                    <div class="bg-white rounded-xl shadow-sm p-6">
                        <h2 class="text-xl font-bold text-gray-800 mb-6">Detailed Review</h2>
                        
                        <div class="space-y-4">
                            <?php foreach ($questions as $index => $q): 
                                $user_answer = $user_answers[$q['id']] ?? '';
                                $is_correct = !empty($user_answer) && $user_answer == $q['correct_answer'];
                                $option_text = 'option_' . strtolower($q['correct_answer']);
                            ?>
                            <div class="question-review p-4 border-l-4 <?= $is_correct ? 'border-green-500 bg-green-50' : 'border-red-500 bg-red-50' ?> rounded-lg">
                                <div class="flex items-start">
                                    <span class="flex items-center justify-center w-8 h-8 bg-white rounded-lg shadow-sm font-bold mr-4">
                                        <?= $index + 1 ?>
                                    </span>
                                    <div class="flex-1">
                                        <p class="font-medium text-gray-800 mb-2"><?= htmlspecialchars($q['question']) ?></p>
                                        
                                        <div class="grid grid-cols-2 gap-4 text-sm">
                                            <div>
                                                <p class="text-gray-600 mb-1">Your Answer:</p>
                                                <?php if (!empty($user_answer)): ?>
                                                    <p class="<?= $is_correct ? 'text-green-600' : 'text-red-600' ?> font-medium">
                                                        <?= $user_answer ?>. <?= htmlspecialchars($q['option_' . strtolower($user_answer)] ?? '') ?>
                                                    </p>
                                                <?php else: ?>
                                                    <p class="text-red-600 font-medium">Not answered</p>
                                                <?php endif; ?>
                                            </div>
                                            <div>
                                                <p class="text-gray-600 mb-1">Correct Answer:</p>
                                                <p class="text-green-600 font-medium">
                                                    <?= $q['correct_answer'] ?>. <?= htmlspecialchars($q[$option_text]) ?>
                                                </p>
                                            </div>
                                        </div>
                                        
                                        <div class="mt-2 text-sm">
                                            <span class="text-gray-500">Marks: </span>
                                            <span class="font-medium <?= $is_correct ? 'text-green-600' : 'text-red-600' ?>">
                                                <?= $is_correct ? $q['marks'] : 0 ?>/<?= $q['marks'] ?>
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>