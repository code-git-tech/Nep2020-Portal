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

$source = $data['source'] ?? '';
$source_id = $data['source_id'] ?? null;
$amount = $data['amount'] ?? 0;

// Validate source
$valid_sources = ['video', 'test', 'assignment', 'login', 'bonus'];
if (!in_array($source, $valid_sources)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid source']);
    exit;
}

// Award XP
$success = awardXP($student_id, $amount, $source, $data['description'] ?? '');

if ($success) {
    // Get updated stats
    $stats = getStudentDashboardStats($student_id);
    echo json_encode([
        'success' => true,
        'new_xp' => $stats['xp_points'],
        'new_level' => $stats['level'],
        'message' => "Earned $amount XP!"
    ]);
} else {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to award XP']);
}
?>