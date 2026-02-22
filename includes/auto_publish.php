<?php
// file: includes/auto_publish.php
// This script can be called from any page or via cron job

function autoPublishVideos($pdo) {
    if (!$pdo) {
        error_log("Auto-publish error: PDO connection not available");
        return 0;
    }

    try {
        // Get videos that will be published (for logging)
        $videos_to_publish = $pdo->prepare("
            SELECT id, title, course_id 
            FROM videos 
            WHERE status = 'scheduled' 
            AND scheduled_at IS NOT NULL 
            AND scheduled_at <= NOW()
        ");
        $videos_to_publish->execute();
        $published_videos = $videos_to_publish->fetchAll(PDO::FETCH_ASSOC);
        
        if (empty($published_videos)) {
            return 0; // No videos to publish
        }
        
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
        
        if ($published_count > 0) {
            // Log the auto-publish action for each video
            foreach ($published_videos as $video) {
                try {
                    $log_stmt = $pdo->prepare("
                        INSERT INTO activity_logs (user_id, action, details, ip_address, user_agent) 
                        VALUES (?, 'auto_publish', ?, ?, ?)
                    ");
                    $log_stmt->execute([
                        isset($_SESSION['user_id']) ? $_SESSION['user_id'] : NULL,
                        "Video '{$video['title']}' (ID: {$video['id']}) automatically published",
                        $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1',
                        $_SERVER['HTTP_USER_AGENT'] ?? 'System'
                    ]);
                } catch (Exception $log_e) {
                    error_log("Failed to log auto-publish: " . $log_e->getMessage());
                }
            }
            error_log("Auto-published $published_count videos");
        }
        
        return $published_count;
    } catch (Exception $e) {
        error_log("Auto-publish error: " . $e->getMessage());
        return 0;
    }
}
?>
