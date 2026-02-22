<?php
require_once '../includes/auth.php';
require_once '../includes/student-functions.php';
requireStudent();

$quiz_id = $_GET['quiz_id'] ?? 0;
$user_id = $_SESSION['user_id'];

header('Content-Type: application/json');

try {
    if (!$quiz_id) {
        echo json_encode(['success' => false, 'error' => 'Quiz ID is required']);
        exit;
    }

    // Get quiz details
    $stmt = $pdo->prepare("SELECT * FROM quizzes WHERE id = ?");
    $stmt->execute([$quiz_id]);
    $quiz = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$quiz) {
        echo json_encode(['success' => false, 'error' => 'Quiz not found']);
        exit;
    }
    
    // Check if user is enrolled in the course (through video)
    $stmt = $pdo->prepare("
        SELECT v.course_id 
        FROM videos v 
        WHERE v.id = ?
    ");
    $stmt->execute([$quiz['video_id']]);
    $video = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($video) {
        $stmt = $pdo->prepare("SELECT * FROM enrollments WHERE student_id = ? AND course_id = ? AND status = 'active'");
        $stmt->execute([$user_id, $video['course_id']]);
        if (!$stmt->fetch()) {
            echo json_encode(['success' => false, 'error' => 'Not enrolled in this course']);
            exit;
        }
    }
    
    // Check if user has exceeded max attempts
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as attempts,
               SUM(CASE WHEN passed = 1 THEN 1 ELSE 0 END) as passed_count
        FROM quiz_attempts 
        WHERE quiz_id = ? AND student_id = ?
    ");
    $stmt->execute([$quiz_id, $user_id]);
    $attempts = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($attempts['passed_count'] > 0) {
        echo json_encode(['success' => false, 'error' => 'You have already passed this quiz']);
        exit;
    }
    
    if ($quiz['max_attempts'] > 0 && $attempts['attempts'] >= $quiz['max_attempts']) {
        echo json_encode(['success' => false, 'error' => 'Maximum attempts reached']);
        exit;
    }
    
    // Get quiz questions
    $stmt = $pdo->prepare("
        SELECT q.* 
        FROM quiz_questions q
        WHERE q.quiz_id = ?
        ORDER BY q.order_num
    ");
    $stmt->execute([$quiz_id]);
    $questions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // For each question, get its options
    foreach ($questions as &$question) {
        $stmt = $pdo->prepare("
            SELECT id, option_text, order_num 
            FROM quiz_options 
            WHERE question_id = ? 
            ORDER BY order_num
        ");
        $stmt->execute([$question['id']]);
        $question['options'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    echo json_encode([
        'success' => true,
        'quiz' => $quiz,
        'questions' => $questions
    ]);
    
} catch (PDOException $e) {
    error_log("Quiz error: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Database error occurred']);
} catch (Exception $e) {
    error_log("Quiz error: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'An error occurred']);
}
?>