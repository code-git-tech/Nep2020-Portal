<?php
/**
 * Notification Functions - Handle system notifications
 * 
 * This file manages all notification operations including creation,
 * retrieval, and marking as read for users, parents, and admins.
 */

require_once __DIR__ . '/../config/db.php';

/**
 * Create a new notification
 * 
 * @param mysqli $conn Database connection
 * @param int $user_id User ID to notify
 * @param string $message Notification message
 * @param string $type Notification type (info, success, warning, danger)
 * @param string $link Optional link to redirect when clicked
 * @return bool Success or failure
 */
function createNotification($conn, $user_id, $message, $type = 'info', $link = '') {
    // Check if notifications table exists, if not create it
    $checkTable = "SHOW TABLES LIKE 'notifications'";
    $result = $conn->query($checkTable);
    
    if ($result->num_rows == 0) {
        createNotificationsTable($conn);
    }
    
    $query = "INSERT INTO notifications (user_id, message, type, link, created_at) 
              VALUES (?, ?, ?, ?, NOW())";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("isss", $user_id, $message, $type, $link);
    
    return $stmt->execute();
}

/**
 * Create notifications table if it doesn't exist
 * 
 * @param mysqli $conn Database connection
 */
function createNotificationsTable($conn) {
    $sql = "CREATE TABLE IF NOT EXISTS notifications (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        message TEXT NOT NULL,
        type ENUM('info', 'success', 'warning', 'danger') DEFAULT 'info',
        link VARCHAR(255),
        is_read TINYINT DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_user (user_id),
        INDEX idx_read (is_read),
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    
    return $conn->query($sql);
}

/**
 * Get unread notifications for a user
 * 
 * @param mysqli $conn Database connection
 * @param int $user_id User ID
 * @param int $limit Maximum number of notifications to return
 * @return array Array of notifications
 */
function getUnreadNotifications($conn, $user_id, $limit = 10) {
    $query = "SELECT * FROM notifications 
              WHERE user_id = ? AND is_read = 0 
              ORDER BY created_at DESC 
              LIMIT ?";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("ii", $user_id, $limit);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $notifications = [];
    while ($row = $result->fetch_assoc()) {
        $notifications[] = $row;
    }
    
    return $notifications;
}

/**
 * Get all notifications for a user (paginated)
 * 
 * @param mysqli $conn Database connection
 * @param int $user_id User ID
 * @param int $page Page number
 * @param int $per_page Notifications per page
 * @return array Array with notifications and pagination info
 */
function getAllNotifications($conn, $user_id, $page = 1, $per_page = 20) {
    $offset = ($page - 1) * $per_page;
    
    // Get total count
    $countQuery = "SELECT COUNT(*) as total FROM notifications WHERE user_id = ?";
    $stmt = $conn->prepare($countQuery);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $countResult = $stmt->get_result();
    $total = $countResult->fetch_assoc()['total'];
    
    // Get notifications
    $query = "SELECT * FROM notifications 
              WHERE user_id = ? 
              ORDER BY created_at DESC 
              LIMIT ? OFFSET ?";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("iii", $user_id, $per_page, $offset);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $notifications = [];
    while ($row = $result->fetch_assoc()) {
        $notifications[] = $row;
    }
    
    return [
        'notifications' => $notifications,
        'total' => $total,
        'pages' => ceil($total / $per_page),
        'current_page' => $page
    ];
}

/**
 * Get unread notification count for a user
 * 
 * @param mysqli $conn Database connection
 * @param int $user_id User ID
 * @return int Count of unread notifications
 */
function getUnreadNotificationCount($conn, $user_id) {
    $query = "SELECT COUNT(*) as count FROM notifications 
              WHERE user_id = ? AND is_read = 0";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    
    return $row['count'] ?? 0;
}

/**
 * Mark a notification as read
 * 
 * @param mysqli $conn Database connection
 * @param int $notification_id Notification ID
 * @param int $user_id User ID (for verification)
 * @return bool Success or failure
 */
function markNotificationRead($conn, $notification_id, $user_id) {
    $query = "UPDATE notifications 
              SET is_read = 1 
              WHERE id = ? AND user_id = ?";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("ii", $notification_id, $user_id);
    
    return $stmt->execute();
}

/**
 * Mark all notifications as read for a user
 * 
 * @param mysqli $conn Database connection
 * @param int $user_id User ID
 * @return bool Success or failure
 */
function markAllNotificationsRead($conn, $user_id) {
    $query = "UPDATE notifications SET is_read = 1 WHERE user_id = ?";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $user_id);
    
    return $stmt->execute();
}

/**
 * Delete a notification
 * 
 * @param mysqli $conn Database connection
 * @param int $notification_id Notification ID
 * @param int $user_id User ID (for verification)
 * @return bool Success or failure
 */
function deleteNotification($conn, $notification_id, $user_id) {
    $query = "DELETE FROM notifications WHERE id = ? AND user_id = ?";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("ii", $notification_id, $user_id);
    
    return $stmt->execute();
}

/**
 * Send risk alert to parent
 * 
 * @param mysqli $conn Database connection
 * @param int $parent_id Parent user ID
 * @param int $student_id Student user ID
 * @param string $risk_level Risk level
 * @param string $message Alert message
 * @return bool Success or failure
 */
function sendParentRiskAlert($conn, $parent_id, $student_id, $risk_level, $message = '') {
    // Get student name
    $query = "SELECT name FROM users WHERE id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $student_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $student = $result->fetch_assoc();
    
    $student_name = $student['name'] ?? 'Your child';
    
    if (empty($message)) {
        $message = "⚠️ {$risk_level} risk level detected for {$student_name}. Please check in with them.";
    }
    
    // Create notification for parent
    $success = createNotification(
        $conn,
        $parent_id,
        $message,
        'warning',
        "parent/student-details.php?id={$student_id}"
    );
    
    // Also log in activity_logs
    $logQuery = "INSERT INTO activity_logs (user_id, action, details, created_at) 
                 VALUES (?, 'risk_alert', ?, NOW())";
    $logStmt = $conn->prepare($logQuery);
    $logStmt->bind_param("is", $student_id, $message);
    @$logStmt->execute(); // Suppress errors if table doesn't exist
    
    return $success;
}
?>