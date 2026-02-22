<?php
require_once '../config/db.php';
require_once '../includes/auth.php';
requireLogin();

$conversationId = $_GET['conversation_id'] ?? 0;
$userId = $_SESSION['user_id'];

if (!$conversationId) {
    header('Content-Type: application/json');
    echo json_encode([]);
    exit;
}

try {
    // Verify user is part of this conversation
    $stmt = $pdo->prepare("SELECT 1 FROM conversation_participants WHERE conversation_id = ? AND user_id = ?");
    $stmt->execute([$conversationId, $userId]);
    
    if (!$stmt->fetch()) {
        header('Content-Type: application/json');
        echo json_encode([]);
        exit;
    }
    
    $stmt = $pdo->prepare("
        SELECT m.*, u.name as sender_name, u.role as sender_role
        FROM messages m
        JOIN users u ON m.sender_id = u.id
        WHERE m.conversation_id = ?
        ORDER BY m.created_at ASC
    ");
    $stmt->execute([$conversationId]);
    $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    header('Content-Type: application/json');
    echo json_encode($messages);
    
} catch (Exception $e) {
    header('Content-Type: application/json');
    echo json_encode([]);
}
?>