<?php
// Add this after loading the video details
$video_id = $_GET['video_id'];

// Check if video requires quiz
$stmt = $pdo->prepare("
    SELECT v.*, q.id as quiz_id, q.title as quiz_title,
           (SELECT COUNT(*) FROM quiz_attempts 
            WHERE quiz_id = q.id AND student_id = ? AND passed = 1) as has_passed_quiz
    FROM videos v
    LEFT JOIN quizzes q ON v.id = q.video_id
    WHERE v.id = ?
");
$stmt->execute([$_SESSION['user_id'], $video_id]);
$video = $stmt->fetch();

// Check if user has access to this video (quiz constraint)
if ($video['requires_quiz'] && $video['quiz_id']) {
    if (!$video['has_passed_quiz']) {
        // Redirect to quiz
        header("Location: quiz.php?id=" . $video['quiz_id']);
        exit;
    }
}

// For next video constraint
if ($video['next_video_id']) {
    $next_video_id = $video['next_video_id'];
    // Store in session or pass to template
}