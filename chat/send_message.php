<!-- send_message.php -->

<?php
require_once 'functions.php';
requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit;
}

$conversationId = $_POST['conversation_id'] ?? 0;
$content = trim($_POST['content'] ?? '');
$userId = $_SESSION['user_id'];

if (empty($content)) {
    http_response_code(400);
    echo json_encode(['error' => 'Message cannot be empty']);
    exit;
}

// Verify user is part of this conversation
$pdo = $GLOBALS['pdo'];
$stmt = $pdo->prepare("SELECT 1 FROM conversation_participants WHERE conversation_id = ? AND user_id = ?");
$stmt->execute([$conversationId, $userId]);
if (!$stmt->fetch()) {
    http_response_code(403);
    echo json_encode(['error' => 'Access denied']);
    exit;
}

$success = sendMessage($conversationId, $userId, $content);
echo json_encode(['success' => $success]);
?>