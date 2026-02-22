<?php
// file: includes/auto_publish.php
// This script can be called from any page or via cron job

function autoPublishVideos($pdo) {
    try {
        $pdo->beginTransaction();
        
        // Get videos that will be published (for logging)
        $videos_to_publish = $pdo->prepare("
            SELECT id, title, course_id 
            FROM videos 
            WHERE status = 'scheduled' 
            AND scheduled_at IS NOT NULL 
            AND scheduled_at <= NOW()
        ");
        $videos_to_publish->execute();
        $published_videos = $videos_to_publish->fetchAll();
        
        // Update videos that are scheduled and past their time
        $update_stmt = $pdo->prepare("
            UPDATE videos 
            SET status = 'published', 
                scheduled_at = NULL 
            WHERE status = 'scheduled' 
            AND scheduled_at IS NOT NULL 
            AND scheduled_at <= NOW()
        ");
        $update_stmt->execute();
        $published_count = $update_stmt->rowCount();
        
        // Log the auto-publish action if there's a user session
        if ($published_count > 0 && isset($_SESSION['user_id'])) {
            foreach ($published_videos as $video) {
                $log_stmt = $pdo->prepare("
                    INSERT INTO activity_logs (user_id, action, details, ip_address, user_agent) 
                    VALUES (?, 'auto_publish', ?, ?, ?)
                ");
                $log_stmt->execute([
                    $_SESSION['user_id'],
                    "Video '{$video['title']}' (ID: {$video['id']}) automatically published",
                    $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1',
                    $_SERVER['HTTP_USER_AGENT'] ?? 'System'
                ]);
            }
        } elseif ($published_count > 0) {
            // Log without user session
            error_log("Auto-published $published_count videos (system)");
        }
        
        $pdo->commit();
        return $published_count;
    } catch (Exception $e) {
        $pdo->rollBack();
        error_log("Auto-publish error: " . $e->getMessage());
        return 0;
    }
}
?>