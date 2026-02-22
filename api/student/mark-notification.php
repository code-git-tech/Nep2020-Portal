<?php
require_once '../../includes/auth.php';
require_once '../../includes/student-functions.php';
requireStudent();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$student_id = $_SESSION['user_id'];
$data = json_decode(file_get_contents('php://input'), true);
$notification_id = $data['id'] ?? 0;

if (!$notification_id) {
    http_response_code(400);
    echo json_encode(['error' => 'Notification ID required']);
    exit;
}

$success = markNotificationRead($notification_id, $student_id);

echo json_encode(['success' => $success]);
?>