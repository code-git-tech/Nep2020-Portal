<?php
require_once '../../includes/auth.php';
require_once '../../includes/student-functions.php';
requireStudent();

header('Content-Type: application/json');

$student_id = $_SESSION['user_id'];

// Update streak
updateStudentStreak($student_id);

// Get updated streak
$stmt = $pdo->prepare("SELECT current_streak, longest_streak FROM student_streaks WHERE student_id = ?");
$stmt->execute([$student_id]);
$streak = $stmt->fetch(PDO::FETCH_ASSOC);

echo json_encode([
    'success' => true,
    'current_streak' => $streak['current_streak'] ?? 0,
    'longest_streak' => $streak['longest_streak'] ?? 0
]);
?>