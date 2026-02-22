<?php
require_once '../config/db.php';
require_once '../includes/auth.php';
requireLogin();

$userId = $_SESSION['user_id'];

try {
    $stmt = $pdo->prepare("
        SELECT c.*, 
               (SELECT content FROM messages WHERE conversation_id = c.id ORDER BY created_at DESC LIMIT 1) as last_message
        FROM conversations c
        JOIN conversation_participants cp ON c.id = cp.conversation_id
        WHERE cp.user_id = ?
        ORDER BY c.id DESC
    ");
    $stmt->execute([$userId]);
    $conversations = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Always return an array (even if empty)
    header('Content-Type: application/json');
    echo json_encode($conversations);
    
} catch (Exception $e) {
    header('Content-Type: application/json');
    echo json_encode([]); // Return empty array on error
}
?>