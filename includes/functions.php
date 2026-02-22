<?php
// Include database connection
require_once __DIR__ . '/../config/db.php';

/**
 * Get all consultation requests with filters
 */
function getConsultationRequests($status = 'all', $risk = 'all', $date_from = '', $date_to = '') {
    global $pdo;
    
    $sql = "SELECT cr.*, 
            u.name as student_name, u.email as student_email,
            c.name as counselor_name, c.specialization as counselor_specialization
            FROM consultation_requests cr
            LEFT JOIN users u ON cr.student_id = u.id
            LEFT JOIN counselors c ON cr.assigned_counselor_id = c.id
            WHERE 1=1";
    
    $params = [];
    
    if ($status != 'all') {
        $sql .= " AND cr.status = ?";
        $params[] = $status;
    }
    
    if ($risk != 'all') {
        $sql .= " AND cr.risk_level = ?";
        $params[] = $risk;
    }
    
    if (!empty($date_from)) {
        $sql .= " AND DATE(cr.requested_date) >= ?";
        $params[] = $date_from;
    }
    
    if (!empty($date_to)) {
        $sql .= " AND DATE(cr.requested_date) <= ?";
        $params[] = $date_to;
    }
    
    $sql .= " ORDER BY 
                CASE cr.risk_level 
                    WHEN 'critical' THEN 1 
                    WHEN 'high' THEN 2 
                    WHEN 'medium' THEN 3 
                    WHEN 'low' THEN 4 
                END,
                cr.requested_date DESC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Update consultation status
 */
function updateConsultationStatus($request_id, $new_status) {
    global $pdo;
    
    $sql = "UPDATE consultation_requests SET status = ? WHERE id = ?";
    $stmt = $pdo->prepare($sql);
    return $stmt->execute([$new_status, $request_id]);
}

/**
 * Assign counselor to consultation request
 */
function assignCounselorToRequest($request_id, $counselor_id) {
    global $pdo;
    
    // Start transaction
    $pdo->beginTransaction();
    
    try {
        // Update the request
        $sql = "UPDATE consultation_requests 
                SET assigned_counselor_id = ?, status = 'approved' 
                WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$counselor_id, $request_id]);
        
        // Increment counselor's assigned sessions count
        $sql = "UPDATE counselors SET assigned_sessions = assigned_sessions + 1 WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$counselor_id]);
        
        $pdo->commit();
        return true;
    } catch (Exception $e) {
        $pdo->rollBack();
        return false;
    }
}

/**
 * Get all counselors
 */
function getAllCounselors($active_only = true) {
    global $pdo;
    
    $sql = "SELECT * FROM counselors";
    if ($active_only) {
        $sql .= " WHERE status = 'active'";
    }
    $sql .= " ORDER BY name";
    
    $stmt = $pdo->query($sql);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Get single counselor by ID
 */
function getCounselorById($id) {
    global $pdo;
    
    $stmt = $pdo->prepare("SELECT * FROM counselors WHERE id = ?");
    $stmt->execute([$id]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

/**
 * Add new counselor
 */
function addCounselor($data) {
    global $pdo;
    
    $sql = "INSERT INTO counselors (name, email, phone, specialization, experience, availability, status) 
            VALUES (?, ?, ?, ?, ?, ?, ?)";
    $stmt = $pdo->prepare($sql);
    return $stmt->execute([
        $data['name'],
        $data['email'],
        $data['phone'],
        $data['specialization'],
        $data['experience'],
        $data['availability'],
        $data['status']
    ]);
}

/**
 * Update counselor
 */
function updateCounselor($id, $data) {
    global $pdo;
    
    $sql = "UPDATE counselors 
            SET name = ?, email = ?, phone = ?, specialization = ?, 
                experience = ?, availability = ?, status = ? 
            WHERE id = ?";
    $stmt = $pdo->prepare($sql);
    return $stmt->execute([
        $data['name'],
        $data['email'],
        $data['phone'],
        $data['specialization'],
        $data['experience'],
        $data['availability'],
        $data['status'],
        $id
    ]);
}

/**
 * Delete counselor (only if no active sessions)
 */
function deleteCounselor($id) {
    global $pdo;
    
    // Check if counselor has active sessions
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM consultation_requests 
                           WHERE assigned_counselor_id = ? AND status IN ('approved', 'pending')");
    $stmt->execute([$id]);
    $active_sessions = $stmt->fetchColumn();
    
    if ($active_sessions > 0) {
        return false; // Cannot delete
    }
    
    $stmt = $pdo->prepare("DELETE FROM counselors WHERE id = ?");
    return $stmt->execute([$id]);
}

/**
 * Get session notes for a consultation
 */
function getSessionNotes($consultation_id) {
    global $pdo;
    
    $stmt = $pdo->prepare("SELECT sn.*, c.name as counselor_name 
                           FROM session_notes sn
                           LEFT JOIN counselors c ON sn.counselor_id = c.id
                           WHERE sn.consultation_id = ?
                           ORDER BY sn.created_at DESC");
    $stmt->execute([$consultation_id]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Add session notes
 */
function addSessionNotes($data) {
    global $pdo;
    
    $sql = "INSERT INTO session_notes (consultation_id, counselor_id, summary, observations, 
                                       recommendations, followup_required, followup_date, 
                                       mood_before, mood_after) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
    $stmt = $pdo->prepare($sql);
    $result = $stmt->execute([
        $data['consultation_id'],
        $data['counselor_id'],
        $data['summary'],
        $data['observations'],
        $data['recommendations'],
        $data['followup_required'],
        $data['followup_date'],
        $data['mood_before'],
        $data['mood_after']
    ]);
    
    if ($result && isset($data['followup_required']) && $data['followup_required'] == 1) {
        // Update consultation status to completed but with follow-up
        $stmt2 = $pdo->prepare("UPDATE consultation_requests SET status = 'completed' WHERE id = ?");
        $stmt2->execute([$data['consultation_id']]);
    }
    
    return $result;
}

/**
 * Get risk alerts
 */
function getRiskAlerts($resolved = null) {
    global $pdo;
    
    $sql = "SELECT ra.*, u.name as student_name, u.email as student_email,
            u2.name as resolved_by_name
            FROM risk_alerts ra
            LEFT JOIN users u ON ra.student_id = u.id
            LEFT JOIN users u2 ON ra.resolved_by = u2.id
            WHERE 1=1";
    
    if ($resolved !== null) {
        $sql .= " AND ra.is_resolved = " . ($resolved ? '1' : '0');
    }
    
    $sql .= " ORDER BY 
                CASE ra.risk_level 
                    WHEN 'critical' THEN 1 
                    WHEN 'high' THEN 2 
                    WHEN 'medium' THEN 3 
                    WHEN 'low' THEN 4 
                END,
                ra.created_at DESC";
    
    $stmt = $pdo->query($sql);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Get high risk students based on mood entries
 */
function getHighRiskStudents() {
    global $pdo;
    
    $sql = "SELECT u.id, u.name, u.email, 
            COUNT(me.id) as mood_entries,
            AVG(me.stress_level) as avg_stress,
            AVG(me.energy_level) as avg_energy,
            MAX(CASE 
                WHEN me.mood IN ('stressed', 'sad') AND me.stress_level >= 4 THEN 'high'
                WHEN me.mood IN ('stressed', 'sad') AND me.stress_level >= 3 THEN 'medium'
                ELSE 'low'
            END) as risk_level,
            MAX(me.created_at) as last_mood_date
            FROM users u
            LEFT JOIN mood_entries me ON u.id = me.user_id
            WHERE u.role = 'student'
            GROUP BY u.id
            HAVING avg_stress >= 3.5 OR avg_energy <= 2.5
            ORDER BY avg_stress DESC";
    
    $stmt = $pdo->query($sql);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Resolve risk alert
 */
function resolveRiskAlert($alert_id, $resolved_by) {
    global $pdo;
    
    $sql = "UPDATE risk_alerts 
            SET is_resolved = 1, resolved_by = ?, resolved_at = NOW() 
            WHERE id = ?";
    $stmt = $pdo->prepare($sql);
    return $stmt->execute([$resolved_by, $alert_id]);
}

/**
 * Get risk statistics for charts
 */
function getRiskStatistics() {
    global $pdo;
    
    $stats = [];
    
    // Risk level distribution
    $stmt = $pdo->query("SELECT risk_level, COUNT(*) as count 
                         FROM risk_alerts 
                         WHERE is_resolved = 0 
                         GROUP BY risk_level");
    $stats['risk_distribution'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Risk trend for last 7 days
    $stmt = $pdo->query("SELECT DATE(created_at) as date, 
                         COUNT(*) as total_alerts,
                         SUM(CASE WHEN risk_level IN ('high', 'critical') THEN 1 ELSE 0 END) as high_risk
                         FROM risk_alerts 
                         WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
                         GROUP BY DATE(created_at)
                         ORDER BY date");
    $stats['risk_trend'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    return $stats;
}