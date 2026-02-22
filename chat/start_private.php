<?php
require_once '../config/db.php';
require_once '../includes/auth.php';
requireLogin();

$otherUserId = $_POST['user_id'] ?? 0;
$userId = $_SESSION['user_id'];

if (!$otherUserId) {
    http_response_code(400);
    echo json_encode(['error' => 'User ID required']);
    exit;
}

// Check if private conversation already exists
$stmt = $pdo->prepare("
    SELECT c.id
    FROM conversations c
    JOIN conversation_participants cp1 ON c.id = cp1.conversation_id
    JOIN conversation_participants cp2 ON c.id = cp2.conversation_id
    WHERE c.type = 'private'
      AND cp1.user_id = ? 
      AND cp2.user_id = ?
");
$stmt->execute([$userId, $otherUserId]);
$conv = $stmt->fetch();

if ($conv) {
    // Return existing conversation
    echo json_encode(['conversation_id' => $conv['id']]);
} else {
    // Create new private conversation
    $pdo->beginTransaction();
    try {
        // Create conversation
        $stmt = $pdo->prepare("INSERT INTO conversations (type) VALUES ('private')");
        $stmt->execute();
        $convId = $pdo->lastInsertId();
        
        // Add both participants
        $stmt = $pdo->prepare("INSERT INTO conversation_participants (conversation_id, user_id) VALUES (?, ?), (?, ?)");
        $stmt->execute([$convId, $userId, $convId, $otherUserId]);
        
        $pdo->commit();
        echo json_encode(['conversation_id' => $convId]);
    } catch (Exception $e) {
        $pdo->rollBack();
        http_response_code(500);
        echo json_encode(['error' => 'Failed to create conversation']);
    }
}
?>