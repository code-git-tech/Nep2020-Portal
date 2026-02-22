<?php
require_once '../config/db.php';

/**
 * Get student dashboard statistics
 */
function getStudentDashboardStats($student_id) {
    global $pdo;
    
    $stats = [];
    
    // Get enrolled courses count and progress
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(DISTINCT c.id) as enrolled_courses,
            COALESCE(AVG(cp.progress), 0) as avg_progress
        FROM courses c
        JOIN enrollments e ON c.id = e.course_id
        LEFT JOIN (
            SELECT 
                v.course_id,
                COUNT(DISTINCT v.id) as total_videos,
                SUM(CASE WHEN vp.completed THEN 1 ELSE 0 END) as completed_videos,
                (SUM(CASE WHEN vp.completed THEN 1 ELSE 0 END) / COUNT(DISTINCT v.id)) * 100 as progress
            FROM videos v
            LEFT JOIN video_progress vp ON v.id = vp.video_id AND vp.student_id = ?
            GROUP BY v.course_id
        ) cp ON c.id = cp.course_id
        WHERE e.student_id = ? AND e.status = 'active'
    ");
    $stmt->execute([$student_id, $student_id]);
    $result = $stmt->fetch();
    
    $stats['enrolled_courses'] = $result['enrolled_courses'] ?? 0;
    $stats['course_progress'] = round($result['avg_progress'] ?? 0);
    
    // Get XP and Level
    $stmt = $pdo->prepare("SELECT xp_points, level FROM student_xp WHERE student_id = ?");
    $stmt->execute([$student_id]);
    $xpData = $stmt->fetch();
    $stats['xp_points'] = $xpData['xp_points'] ?? 0;
    $stats['level'] = $xpData['level'] ?? 1;
    
    // Get streak
    $stmt = $pdo->prepare("SELECT current_streak, longest_streak FROM student_streaks WHERE student_id = ?");
    $stmt->execute([$student_id]);
    $streakData = $stmt->fetch();
    $stats['current_streak'] = $streakData['current_streak'] ?? 0;
    $stats['longest_streak'] = $streakData['longest_streak'] ?? 0;
    
    // Get rankings
    $stmt = $pdo->prepare("
        SELECT global_rank, weekly_rank, total_students
        FROM (
            SELECT 
                student_id,
                @rank := @rank + 1 as global_rank,
                (SELECT COUNT(*) FROM users WHERE role = 'student') as total_students
            FROM student_xp sx
            CROSS JOIN (SELECT @rank := 0) r
            ORDER BY sx.xp_points DESC
        ) rankings
        WHERE student_id = ?
    ");
    $stmt->execute([$student_id]);
    $rankData = $stmt->fetch();
    
    $stats['global_rank'] = $rankData['global_rank'] ?? 0;
    $stats['total_students'] = $rankData['total_students'] ?? 1;
    $stats['rank_percentile'] = $stats['global_rank'] > 0 
        ? round((($stats['total_students'] - $stats['global_rank']) / $stats['total_students']) * 100, 1)
        : 0;
    
    // Get weekly XP percentile
    $stats['weekly_xp_percentile'] = 0.3; // Placeholder - calculate based on weekly XP
    
    return $stats;
}

/**
 * Get today's class
 */
function getTodaysClass($student_id) {
    global $pdo;
    
    $stmt = $pdo->prepare("
        SELECT 
            cs.*,
            c.title as course_title,
            c.id as course_id,
            c.instructor,
            COALESCE(ca.completed_video, FALSE) as video_completed,
            COALESCE(ca.completed_mcq, FALSE) as mcq_completed,
            COALESCE(ca.completed_lab, FALSE) as lab_completed
        FROM class_schedule cs
        JOIN courses c ON cs.course_id = c.id
        LEFT JOIN class_attendance ca ON cs.id = ca.class_id AND ca.student_id = ?
        WHERE cs.scheduled_date = CURDATE()
            AND cs.status = 'upcoming'
        LIMIT 1
    ");
    $stmt->execute([$student_id]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

/**
 * Get upcoming classes for the week
 */
function getUpcomingClasses($student_id, $limit = 5) {
    global $pdo;
    
    $stmt = $pdo->prepare("
        SELECT 
            cs.*,
            c.title as course_title,
            DATEDIFF(cs.scheduled_date, CURDATE()) as days_remaining
        FROM class_schedule cs
        JOIN courses c ON cs.course_id = c.id
        JOIN enrollments e ON c.id = e.course_id
        WHERE e.student_id = ? 
            AND cs.scheduled_date >= CURDATE()
            AND cs.status = 'upcoming'
        ORDER BY cs.scheduled_date ASC
        LIMIT ?
    ");
    $stmt->execute([$student_id, $limit]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Get recent notifications
 */
function getStudentNotifications($student_id, $limit = 5) {
    global $pdo;
    
    $stmt = $pdo->prepare("
        SELECT * FROM student_notifications 
        WHERE student_id = ? 
        ORDER BY created_at DESC 
        LIMIT ?
    ");
    $stmt->execute([$student_id, $limit]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Get unread notifications count
 */
function getUnreadNotificationsCount($student_id) {
    global $pdo;
    
    $stmt = $pdo->prepare("
        SELECT COUNT(*) FROM student_notifications 
        WHERE student_id = ? AND is_read = FALSE
    ");
    $stmt->execute([$student_id]);
    return $stmt->fetchColumn();
}

/**
 * Get course preview for dashboard
 */
function getCoursePreviews($student_id, $limit = 3) {
    global $pdo;
    
    $stmt = $pdo->prepare("
        SELECT 
            c.*,
            COUNT(DISTINCT v.id) as total_videos,
            SUM(CASE WHEN vp.completed THEN 1 ELSE 0 END) as completed_videos,
            (SUM(CASE WHEN vp.completed THEN 1 ELSE 0 END) / COUNT(DISTINCT v.id)) * 100 as progress
        FROM courses c
        JOIN enrollments e ON c.id = e.course_id
        LEFT JOIN videos v ON c.id = v.course_id
        LEFT JOIN video_progress vp ON v.id = vp.video_id AND vp.student_id = ?
        WHERE e.student_id = ? AND e.status = 'active'
        GROUP BY c.id
        ORDER BY e.enrolled_at DESC
        LIMIT ?
    ");
    $stmt->execute([$student_id, $student_id, $limit]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Update streak based on daily activity
 */
function updateStudentStreak($student_id) {
    global $pdo;
    
    $today = date('Y-m-d');
    
    // Check if already logged today
    $stmt = $pdo->prepare("
        SELECT id FROM daily_activity 
        WHERE student_id = ? AND activity_date = ?
    ");
    $stmt->execute([$student_id, $today]);
    
    if (!$stmt->fetch()) {
        // Add today's activity
        $pdo->prepare("
            INSERT INTO daily_activity (student_id, activity_date) VALUES (?, ?)
        ")->execute([$student_id, $today]);
        
        // Update streak
        $stmt = $pdo->prepare("
            SELECT 
                CASE 
                    WHEN EXISTS (
                        SELECT 1 FROM daily_activity 
                        WHERE student_id = ? AND activity_date = DATE_SUB(?, INTERVAL 1 DAY)
                    )
                    THEN 1
                    ELSE 0
                END as is_consecutive
        ");
        $stmt->execute([$student_id, $today]);
        $is_consecutive = $stmt->fetchColumn();
        
        if ($is_consecutive) {
            $pdo->prepare("
                UPDATE student_streaks 
                SET current_streak = current_streak + 1,
                    longest_streak = GREATEST(longest_streak, current_streak + 1),
                    last_activity_date = ?
                WHERE student_id = ?
            ")->execute([$today, $student_id]);
        } else {
            $pdo->prepare("
                UPDATE student_streaks 
                SET current_streak = 1,
                    last_activity_date = ?
                WHERE student_id = ?
            ")->execute([$today, $student_id]);
        }
        
        // Award XP for daily login
        awardXP($student_id, 10, 'login', 'Daily Login Bonus');
    }
}

/**
 * Award XP to student
 */
function awardXP($student_id, $amount, $source, $description) {
    global $pdo;
    
    try {
        $pdo->beginTransaction();
        
        // Add XP transaction
        $pdo->prepare("
            INSERT INTO xp_transactions (student_id, xp_amount, source, description)
            VALUES (?, ?, ?, ?)
        ")->execute([$student_id, $amount, $source, $description]);
        
        // Update total XP
        $pdo->prepare("
            UPDATE student_xp 
            SET xp_points = xp_points + ?,
                level = FLOOR(1 + SQRT(xp_points + ?) / 10)
            WHERE student_id = ?
        ")->execute([$amount, $amount, $student_id]);
        
        $pdo->commit();
        return true;
    } catch (Exception $e) {
        $pdo->rollBack();
        return false;
    }
}

/**
 * Mark notification as read
 */
function markNotificationRead($notification_id, $student_id) {
    global $pdo;
    
    $stmt = $pdo->prepare("
        UPDATE student_notifications 
        SET is_read = TRUE 
        WHERE id = ? AND student_id = ?
    ");
    return $stmt->execute([$notification_id, $student_id]);
}

/**
 * Get weekly XP ranking
 */
function getWeeklyRanking($student_id) {
    global $pdo;
    
    $stmt = $pdo->prepare("
        SELECT 
            student_id,
            weekly_xp,
            FIND_IN_SET(weekly_xp, (
                SELECT GROUP_CONCAT(weekly_xp ORDER BY weekly_xp DESC)
                FROM student_rankings
            )) as weekly_rank
        FROM student_rankings
        WHERE student_id = ?
    ");
    $stmt->execute([$student_id]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}



function getEnrolledCourses($student_id) {
    global $pdo;

    $stmt = $pdo->prepare("
        SELECT 
            c.id,
            c.title,
            c.description,
            c.instructor,
            COUNT(DISTINCT v.id) as total_videos,
            SUM(CASE WHEN vp.completed = 1 THEN 1 ELSE 0 END) as completed_videos,
            COALESCE(
                (SUM(CASE WHEN vp.completed = 1 THEN 1 ELSE 0 END) / COUNT(DISTINCT v.id)) * 100,
                0
            ) as progress
        FROM courses c
        JOIN enrollments e ON c.id = e.course_id
        LEFT JOIN videos v ON c.id = v.course_id
        LEFT JOIN video_progress vp 
            ON v.id = vp.video_id AND vp.student_id = ?
        WHERE e.student_id = ? AND e.status = 'active'
        GROUP BY c.id
        ORDER BY e.enrolled_at DESC
    ");

    $stmt->execute([$student_id, $student_id]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}


/**
 * Get all available courses (not yet enrolled by student)
 */
function getAvailableCourses($student_id) {
    global $pdo;

    $stmt = $pdo->prepare("
        SELECT 
            c.id,
            c.title,
            c.description,
            c.instructor,
            COUNT(DISTINCT v.id) as total_videos,
            0 as progress
        FROM courses c
        LEFT JOIN videos v ON c.id = v.course_id
        WHERE c.id NOT IN (
            SELECT course_id 
            FROM enrollments 
            WHERE student_id = ?
        )
        GROUP BY c.id
        ORDER BY c.created_at DESC
    ");

    $stmt->execute([$student_id]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}
/**
 * Enroll student in a course
 */
function enrollInCourse($student_id, $course_id) {
    global $pdo;

    // Check if already enrolled (avoid duplicate entry)
    $check = $pdo->prepare("
        SELECT id FROM enrollments 
        WHERE student_id = ? AND course_id = ?
    ");
    $check->execute([$student_id, $course_id]);

    if ($check->fetch()) {
        return "already_enrolled";
    }

    // Insert enrollment
    $stmt = $pdo->prepare("
        INSERT INTO enrollments (student_id, course_id, status, enrolled_at)
        VALUES (?, ?, 'active', NOW())
    ");

    if ($stmt->execute([$student_id, $course_id])) {
        return "success";
    } else {
        return "error";
    }
}
/**
 * Get course with all videos + progress
 */
// function getCourseWithVideos($course_id, $student_id) {
//     global $pdo;

//     // Course details
//     $stmt = $pdo->prepare("
//         SELECT * FROM courses WHERE id = ?
//     ");
//     $stmt->execute([$course_id]);
//     $course = $stmt->fetch(PDO::FETCH_ASSOC);

//     // Videos + progress
//     $stmt = $pdo->prepare("
//         SELECT 
//             v.*,
//             COALESCE(vp.completed, 0) as completed
//         FROM videos v
//         LEFT JOIN video_progress vp 
//             ON v.id = vp.video_id AND vp.student_id = ?
//         WHERE v.course_id = ?
//         ORDER BY v.id ASC
//     ");
//     $stmt->execute([$student_id, $course_id]);
//     $videos = $stmt->fetchAll(PDO::FETCH_ASSOC);

//     return [
//         'course' => $course,
//         'videos' => $videos
//     ];
// }
/**
 * Get course progress percentage for a student
 */
function getCourseProgress($course_id, $student_id) {
    global $pdo;

    $stmt = $pdo->prepare("
        SELECT 
            COUNT(DISTINCT v.id) as total_videos,
            SUM(CASE WHEN vp.completed = 1 THEN 1 ELSE 0 END) as completed_videos
        FROM videos v
        LEFT JOIN video_progress vp 
            ON v.id = vp.video_id AND vp.student_id = ?
        WHERE v.course_id = ?
    ");

    $stmt->execute([$student_id, $course_id]);
    $data = $stmt->fetch(PDO::FETCH_ASSOC);

    $total = $data['total_videos'] ?? 0;
    $completed = $data['completed_videos'] ?? 0;

    if ($total == 0) return 0;

    return round(($completed / $total) * 100);
}
function getCourseWithVideos($course_id, $user_id) {
    global $pdo;

    // Course
    $stmt = $pdo->prepare("SELECT * FROM courses WHERE id = ?");
    $stmt->execute([$course_id]);
    $course = $stmt->fetch();

    // Videos
    $stmt = $pdo->prepare("
        SELECT * FROM videos 
        WHERE course_id = ? 
        ORDER BY id ASC
    ");
    $stmt->execute([$course_id]);
    $videos = $stmt->fetchAll();

    return [
        'course' => $course,
        'videos' => $videos
    ];
}


function getAvailableTests($conn, $user_id) {
    $query = "SELECT * FROM tests WHERE user_id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    return $stmt->get_result();
}