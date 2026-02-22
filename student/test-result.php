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
$attempt_id = isset($_GET['attempt']) ? (int)$_GET['attempt'] : 0;
$test_id = isset($_GET['test_id']) ? (int)$_GET['test_id'] : 0;

$result = null;
$test = null;
$questions = [];
$answers = [];

// If we have an attempt ID, get from database
if ($attempt_id > 0) {
    try {
        // Check if test_attempts table exists
        $table_check = $pdo->query("SHOW TABLES LIKE 'test_attempts'");
        if ($table_check->rowCount() > 0) {
            $stmt = $pdo->prepare("
                SELECT ta.*, t.title as test_title, t.total_marks, t.passing_marks,
                       c.title as course_title
                FROM test_attempts ta
                JOIN academic_tests t ON ta.test_id = t.id
                JOIN academic_courses c ON t.course_id = c.id
                WHERE ta.id = ? AND ta.student_id = ?
            ");
            $stmt->execute([$attempt_id, $user_id]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($result) {
                $test_id = $result['test_id'];
                $answers = json_decode($result['answers'], true) ?? [];
            }
        }
    } catch (PDOException $e) {
        // Table might not exist, continue to session fallback
    }
}

// If no result from database, check session
if (!$result && isset($_SESSION['result'])) {
    $result = $_SESSION['result'];
    $test_id = $result['test_id'] ?? 0;
    unset($_SESSION['result']); // Clear after use
}

// If still no result, redirect
if (!$result) {
    $_SESSION['error'] = "No test result found";
    header('Location: tests.php');
    exit;
}

// Get test details if we have test_id
if ($test_id > 0) {
    try {
        $stmt = $pdo->prepare("
            SELECT t.*, c.title as course_title, c.class, c.subject
            FROM academic_tests t
            JOIN academic_courses c ON t.course_id = c.id
            WHERE t.id = ?
        ");
        $stmt->execute([$test_id]);
        $test = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $test = null;
    }
}

// Get questions and correct answers for review
if ($test_id > 0) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM academic_questions WHERE test_id = ? ORDER BY id");
        $stmt->execute([$test_id]);
        $questions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $questions = [];
    }
}

// Calculate percentage
$percentage = $result['total_marks'] > 0 
    ? round(($result['score'] / $result['total_marks']) * 100, 1) 
    : 0;

$passed = $result['passed'] ?? ($percentage >= 40); // Default passing at 40%
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Test Results</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { font-family: 'Inter', sans-serif; }
        body { background-color: #f8fafc; }
        .result-card {
            transition: all 0.3s ease;
        }
        .progress-ring {
            transition: stroke-dashoffset 0.35s;
            transform: rotate(-90deg);
            transform-origin: 50% 50%;
        }
        .correct-answer {
            background-color: #10b981;
            color: white;
        }
        .wrong-answer {
            background-color: #ef4444;
            color: white;
        }
    </style>
</head>
<body class="bg-gray-50">
    <div class="flex h-screen">
        <?php include 'sidebar.php'; ?>

        <div class="flex-1 overflow-auto">
            <?php include 'header.php'; ?>

            <div class="p-8">
                <!-- Header -->
                <div class="mb-8">
                    <h1 class="text-3xl font-bold text-gray-800 mb-2">Test Results</h1>
                    <p class="text-gray-500">Review your performance and answers</p>
                </div>

                <!-- Result Card -->
                <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-8 mb-8 result-card">
                    <div class="flex flex-col lg:flex-row items-center gap-8">
                        <!-- Score Circle -->
                        <div class="relative w-48 h-48">
                            <svg class="w-48 h-48 transform -rotate-90">
                                <circle cx="96" cy="96" r="88" stroke="currentColor" stroke-width="12" fill="transparent" class="text-gray-200"></circle>
                                <circle cx="96" cy="96" r="88" stroke="currentColor" stroke-width="12" fill="transparent" 
                                        stroke-dasharray="<?= 2 * pi() * 88 ?>" 
                                        stroke-dashoffset="<?= 2 * pi() * 88 * (1 - $percentage / 100) ?>"
                                        class="<?= $passed ? 'text-green-500' : 'text-red-500' ?> progress-ring"></circle>
                            </svg>
                            <div class="absolute inset-0 flex flex-col items-center justify-center text-center">
                                <span class="text-4xl font-bold <?= $passed ? 'text-green-600' : 'text-red-600' ?>"><?= $percentage ?>%</span>
                                <span class="text-sm text-gray-500 mt-1">Score</span>
                            </div>
                        </div>

                        <!-- Result Details -->
                        <div class="flex-1 text-center lg:text-left">
                            <h2 class="text-2xl font-bold text-gray-800 mb-2">
                                <?= htmlspecialchars($test['title'] ?? $result['test_title'] ?? 'Test') ?>
                            </h2>
                            <p class="text-gray-500 mb-4"><?= htmlspecialchars($test['course_title'] ?? '') ?></p>
                            
                            <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mt-6">
                                <div class="bg-gray-50 rounded-xl p-4">
                                    <div class="text-2xl font-bold text-gray-800"><?= $result['score'] ?></div>
                                    <div class="text-xs text-gray-500">Your Score</div>
                                </div>
                                <div class="bg-gray-50 rounded-xl p-4">
                                    <div class="text-2xl font-bold text-gray-800"><?= $result['total_marks'] ?></div>
                                    <div class="text-xs text-gray-500">Total Marks</div>
                                </div>
                                <div class="bg-gray-50 rounded-xl p-4">
                                    <div class="text-2xl font-bold <?= $passed ? 'text-green-600' : 'text-red-600' ?>">
                                        <?= $test['passing_marks'] ?? 40 ?>
                                    </div>
                                    <div class="text-xs text-gray-500">Passing Marks</div>
                                </div>
                                <div class="bg-gray-50 rounded-xl p-4">
                                    <div class="text-2xl font-bold text-gray-800"><?= count($questions) ?></div>
                                    <div class="text-xs text-gray-500">Questions</div>
                                </div>
                            </div>

                            <!-- Status Badge -->
                            <div class="mt-6">
                                <?php if ($passed): ?>
                                    <span class="inline-flex items-center px-4 py-2 bg-green-100 text-green-700 rounded-full text-sm font-medium">
                                        <i class="fas fa-check-circle mr-2"></i>
                                        Congratulations! You Passed
                                    </span>
                                <?php else: ?>
                                    <span class="inline-flex items-center px-4 py-2 bg-red-100 text-red-700 rounded-full text-sm font-medium">
                                        <i class="fas fa-times-circle mr-2"></i>
                                        Keep Practicing! Try Again
                                    </span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Answer Review Section -->
                <?php if (!empty($questions)): ?>
                <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
                    <div class="p-6 border-b border-gray-100">
                        <h2 class="text-xl font-bold text-gray-800">Answer Review</h2>
                        <p class="text-sm text-gray-500 mt-1">Review your answers and see the correct ones</p>
                    </div>
                    
                    <div class="divide-y divide-gray-100">
                        <?php foreach ($questions as $index => $q): 
                            $user_answer = $answers[$q['id']] ?? '';
                            $is_correct = $user_answer == $q['correct_answer'];
                        ?>
                        <div class="p-6 hover:bg-gray-50 transition">
                            <div class="flex items-start gap-4">
                                <!-- Question Number -->
                                <div class="flex-shrink-0 w-8 h-8 rounded-lg bg-gradient-to-r from-purple-600 to-pink-600 text-white flex items-center justify-center font-bold">
                                    <?= $index + 1 ?>
                                </div>
                                
                                <div class="flex-1">
                                    <h3 class="font-semibold text-gray-800 mb-3"><?= htmlspecialchars($q['question']) ?></h3>
                                    
                                    <!-- Options -->
                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                                        <?php foreach (['A', 'B', 'C', 'D'] as $option): 
                                            $option_field = 'option_' . strtolower($option);
                                            $option_value = $q[$option_field] ?? '';
                                            if (empty($option_value)) continue;
                                            
                                            $is_selected = ($user_answer == $option);
                                            $is_correct_option = ($q['correct_answer'] == $option);
                                            
                                            $bg_color = 'bg-gray-50';
                                            $text_color = 'text-gray-700';
                                            
                                            if ($is_correct_option && $is_selected) {
                                                $bg_color = 'bg-green-100';
                                                $text_color = 'text-green-700';
                                            } elseif ($is_correct_option) {
                                                $bg_color = 'bg-green-50';
                                                $text_color = 'text-green-600';
                                            } elseif ($is_selected && !$is_correct_option) {
                                                $bg_color = 'bg-red-100';
                                                $text_color = 'text-red-700';
                                            }
                                        ?>
                                        <div class="<?= $bg_color ?> rounded-lg p-3 border <?= $is_selected ? 'border-2 border-' . ($is_correct ? 'green' : 'red') . '-500' : 'border-gray-200' ?>">
                                            <div class="flex items-center justify-between">
                                                <span class="<?= $text_color ?>">
                                                    <span class="font-medium mr-2"><?= $option ?>.</span>
                                                    <?= htmlspecialchars($option_value) ?>
                                                </span>
                                                <?php if ($is_correct_option): ?>
                                                    <i class="fas fa-check-circle text-green-500"></i>
                                                <?php endif; ?>
                                                <?php if ($is_selected && !$is_correct_option): ?>
                                                    <i class="fas fa-times-circle text-red-500"></i>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        <?php endforeach; ?>
                                    </div>
                                    
                                    <!-- Marks -->
                                    <div class="mt-3 flex items-center gap-4 text-sm">
                                        <span class="text-gray-500">
                                            <i class="fas fa-star mr-1 text-yellow-500"></i>
                                            Marks: <?= $q['marks'] ?>
                                        </span>
                                        <?php if ($is_correct): ?>
                                            <span class="text-green-600">
                                                <i class="fas fa-check mr-1"></i>
                                                Correct
                                            </span>
                                        <?php else: ?>
                                            <span class="text-red-600">
                                                <i class="fas fa-times mr-1"></i>
                                                Incorrect
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Action Buttons -->
                <div class="flex justify-center gap-4 mt-8">
                    <a href="tests.php" class="px-6 py-3 bg-purple-600 text-white rounded-xl hover:bg-purple-700 transition font-medium">
                        <i class="fas fa-list mr-2"></i>
                        Back to Tests
                    </a>
                    <?php if (!$passed): ?>
                    <a href="take-test.php?id=<?= $test_id ?>" class="px-6 py-3 border border-purple-600 text-purple-600 rounded-xl hover:bg-purple-50 transition font-medium">
                        <i class="fas fa-redo mr-2"></i>
                        Try Again
                    </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Add any animations or interactions here
        document.addEventListener('DOMContentLoaded', function() {
            // Animate the progress ring on load
            const ring = document.querySelector('.progress-ring');
            if (ring) {
                setTimeout(() => {
                    ring.style.strokeDashoffset = ring.style.strokeDashoffset;
                }, 100);
            }
        });
    </script>
</body>
</html>