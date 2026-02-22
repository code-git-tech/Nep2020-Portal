<?php
require_once __DIR__ . '/../config/db.php'; // Add this line
require_once __DIR__ . '/../includes/auth.php';
/**
 * Get all conversations for a user with last message and unread count (simplified).
 */
function getUserConversations($userId) {
    global $pdo;
    $stmt = $pdo->prepare("
        SELECT c.*, 
               (SELECT content FROM messages WHERE conversation_id = c.id ORDER BY created_at DESC LIMIT 1) as last_message,
               (SELECT COUNT(*) FROM messages WHERE conversation_id = c.id AND sender_id != ? AND created_at > IFNULL((SELECT last_read FROM user_conversation WHERE user_id = ? AND conversation_id = c.id), '1970-01-01')) as unread
        FROM conversations c
        JOIN conversation_participants cp ON c.id = cp.conversation_id
        WHERE cp.user_id = ?
        ORDER BY last_message_date DESC
    ");
    $stmt->execute([$userId, $userId, $userId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Get messages for a conversation.
 */
function getConversationMessages($conversationId, $userId) {
    global $pdo;
    // Mark messages as read (simplified – you may want a proper read receipt table)
    $stmt = $pdo->prepare("
        SELECT m.*, u.name as sender_name, u.role as sender_role
        FROM messages m
        JOIN users u ON m.sender_id = u.id
        WHERE m.conversation_id = ?
        ORDER BY m.created_at ASC
    ");
    $stmt->execute([$conversationId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Send a message.
 */
function sendMessage($conversationId, $senderId, $content) {
    global $pdo;
    $stmt = $pdo->prepare("INSERT INTO messages (conversation_id, sender_id, content) VALUES (?, ?, ?)");
    return $stmt->execute([$conversationId, $senderId, $content]);
}

/**
 * Get all users except the current one (for starting private chats).
 */
function getAllUsersExcept($userId) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT id, name, email, role FROM users WHERE id != ? ORDER BY name");
    $stmt->execute([$userId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Find or create a private conversation between two users.
 */
function getOrCreatePrivateConversation($user1, $user2) {
    global $pdo;
    
    // Find a private conversation that contains exactly these two users
    $stmt = $pdo->prepare("
        SELECT c.id
        FROM conversations c
        INNER JOIN conversation_participants cp1 ON c.id = cp1.conversation_id AND cp1.user_id = ?
        INNER JOIN conversation_participants cp2 ON c.id = cp2.conversation_id AND cp2.user_id = ?
        WHERE c.type = 'private'
          AND (SELECT COUNT(*) FROM conversation_participants WHERE conversation_id = c.id) = 2
    ");
    $stmt->execute([$user1, $user2]);
    $conv = $stmt->fetch();
    
    if ($conv) {
        return $conv['id']; // existing conversation
    }
    
    // Create a new private conversation
    $pdo->prepare("INSERT INTO conversations (type) VALUES ('private')")->execute();
    $convId = $pdo->lastInsertId();
    
    // Add both participants
    $pdo->prepare("INSERT INTO conversation_participants (conversation_id, user_id) VALUES (?, ?), (?, ?)")
         ->execute([$convId, $user1, $convId, $user2]);
    
    return $convId;
}
?>