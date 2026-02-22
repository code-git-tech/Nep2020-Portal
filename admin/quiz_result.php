<?php
require_once '../includes/auth.php';
require_once '../includes/auto_publish.php';
requireStudent();

$attempt_id = isset($_GET['attempt_id']) ? (int)$_GET['attempt_id'] : 0;

// Fetch attempt details with quiz info
$stmt = $pdo->prepare("
    SELECT 
        qa.*,
        q.title as quiz_title,
        q.description as quiz_description,
        q.passing_score,
        q.show_results,
        q.video_id,
        v.title as video_title,
        c.id as course_id,
        c.title as course_title
    FROM quiz_attempts qa
    JOIN quizzes q ON qa.quiz_id = q.id
    JOIN videos v ON q.video_id = v.id
    JOIN courses c ON v.course_id = c.id
    WHERE qa.id = ? AND qa.student_id = ?
");
$stmt->execute([$attempt_id, $_SESSION['user_id']]);
$attempt = $stmt->fetch();

if (!$attempt) {
    header('Location: dashboard.php');
    exit;
}

// Get detailed answers
$stmt = $pdo->prepare("
    SELECT 
        qq.question_text,
        qq.points as max_points,
        qa.selected_option_id,
        qa.answer_text,
        qa.is_correct,
        qa.points_earned,
        qo.option_text as selected_option_text,
        (SELECT option_text FROM quiz_options WHERE question_id = qq.id AND is_correct = 1 LIMIT 1) as correct_answer_text
    FROM quiz_answers qa
    JOIN quiz_questions qq ON qa.question_id = qq.id
    LEFT JOIN quiz_options qo ON qa.selected_option_id = qo.id
    WHERE qa.attempt_id = ?
");
$stmt->execute([$attempt_id]);
$answers = $stmt->fetchAll();

// Get next video if available
$next_video = null;
if ($attempt['passed']) {
    $stmt = $pdo->prepare("
        SELECT id, title, video_url 
        FROM videos 
        WHERE course_id = ? AND id > ? 
        ORDER BY order_num 
        LIMIT 1
    ");
    $stmt->execute([$attempt['course_id'], $attempt['video_id']]);
    $next_video = $stmt->fetch();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quiz Results - <?= htmlspecialchars($attempt['quiz_title']) ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-gray-50">
    <div class="min-h-screen">
        <!-- Header -->
        <div class="bg-white shadow-sm">
            <div class="max-w-4xl mx-auto px-4 py-4">
                <h1 class="text-xl font-bold text-gray-800">Quiz Results</h1>
                <p class="text-sm text-gray-600"><?= htmlspecialchars($attempt['quiz_title']) ?></p>
            </div>
        </div>
        
        <!-- Results Content -->
        <div class="max-w-4xl mx-auto px-4 py-8">
            <!-- Score Card -->
            <div class="bg-white rounded-lg shadow-sm p-6 mb-6 text-center">
                <?php if ($attempt['passed']): ?>
                    <div class="w-20 h-20 bg-green-100 rounded-full mx-auto mb-4 flex items-center justify-center">
                        <i class="fas fa-check-circle text-green-500 text-4xl"></i>
                    </div>
                    <h2 class="text-2xl font-bold text-green-600 mb-2">Congratulations!</h2>
                    <p class="text-gray-600 mb-4">You passed the quiz!</p>
                <?php else: ?>
                    <div class="w-20 h-20 bg-yellow-100 rounded-full mx-auto mb-4 flex items-center justify-center">
                        <i class="fas fa-clock text-yellow-500 text-4xl"></i>
                    </div>
                    <h2 class="text-2xl font-bold text-yellow-600 mb-2">Keep Practicing</h2>
                    <p class="text-gray-600 mb-4">You didn't pass this time. Try again!</p>
                <?php endif; ?>
                
                <div class="flex justify-center items-center space-x-8 mb-4">
                    <div class="text-center">
                        <div class="text-3xl font-bold text-gray-800"><?= round($attempt['percentage']) ?>%</div>
                        <div class="text-sm text-gray-500">Your Score</div>
                    </div>
                    <div class="text-center">
                        <div class="text-3xl font-bold text-gray-800"><?= $attempt['passing_score'] ?>%</div>
                        <div class="text-sm text-gray-500">Passing Score</div>
                    </div>
                </div>
                
                <div class="text-sm text-gray-500">
                    <i class="far fa-clock mr-1"></i>
                    Time taken: <?= floor($attempt['time_taken'] / 60) ?>:<?= str_pad($attempt['time_taken'] % 60, 2, '0', STR_PAD_LEFT) ?>
                </div>
            </div>
            
            <?php if ($attempt['show_results']): ?>
                <!-- Detailed Answers -->
                <div class="bg-white rounded-lg shadow-sm p-6 mb-6">
                    <h3 class="text-lg font-bold mb-4">Detailed Review</h3>
                    
                    <div class="space-y-4">
                        <?php foreach ($answers as $index => $answer): ?>
                            <div class="border rounded-lg p-4 <?= $answer['is_correct'] ? 'border-green-200 bg-green-50' : 'border-red-200 bg-red-50' ?>">
                                <div class="flex items-start">
                                    <span class="bg-gray-200 text-gray-700 text-sm font-medium px-2 py-1 rounded mr-3">
                                        Q<?= $index + 1 ?>
                                    </span>
                                    <div class="flex-1">
                                        <p class="font-medium"><?= htmlspecialchars($answer['question_text']) ?></p>
                                        
                                        <div class="mt-2 text-sm">
                                            <div class="flex items-center <?= $answer['is_correct'] ? 'text-green-600' : 'text-red-600' ?>">
                                                <i class="fas fa-<?= $answer['is_correct'] ? 'check' : 'times' ?> mr-2"></i>
                                                <span>
                                                    Your answer: 
                                                    <?= htmlspecialchars($answer['selected_option_text'] ?? $answer['answer_text'] ?? 'No answer') ?>
                                                </span>
                                            </div>
                                            
                                            <?php if (!$answer['is_correct'] && $answer['correct_answer_text']): ?>
                                                <div class="text-green-600 mt-1">
                                                    <i class="fas fa-check mr-2"></i>
                                                    Correct answer: <?= htmlspecialchars($answer['correct_answer_text']) ?>
                                                </div>
                                            <?php endif; ?>
                                            
                                            <div class="text-gray-500 mt-1">
                                                Points: <?= $answer['points_earned'] ?> / <?= $answer['max_points'] ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>
            
            <!-- Actions -->
            <div class="flex justify-center space-x-4">
                <?php if (!$attempt['passed']): ?>
                    <a href="quiz.php?id=<?= $attempt['quiz_id'] ?>" 
                       class="px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">
                        <i class="fas fa-redo mr-2"></i>Try Again
                    </a>
                <?php endif; ?>
                
                <?php if ($next_video): ?>
                    <a href="watch.php?video_id=<?= $next_video['id'] ?>" 
                       class="px-6 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700">
                        <i class="fas fa-play mr-2"></i>Next Video: <?= htmlspecialchars($next_video['title']) ?>
                    </a>
                <?php else: ?>
                    <a href="course.php?id=<?= $attempt['course_id'] ?>" 
                       class="px-6 py-2 bg-gray-600 text-white rounded-lg hover:bg-gray-700">
                        <i class="fas fa-book mr-2"></i>Back to Course
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>