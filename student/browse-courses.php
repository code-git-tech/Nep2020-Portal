<?php
require_once '../includes/auth.php';
requireStudent();

// Get all available courses not enrolled
$stmt = $pdo->prepare("
    SELECT c.* 
    FROM courses c
    WHERE c.status = 'active' 
    AND c.id NOT IN (
        SELECT course_id FROM enrollments WHERE student_id = ?
    )
");
$stmt->execute([$_SESSION['user_id']]);
$available_courses = $stmt->fetchAll();
?>
<!-- Add HTML similar to courses.php but with enroll button -->