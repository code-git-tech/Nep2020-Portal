<?php
/**
 * Consultation Functions - Manage counseling workflow
 * 
 * This file handles all consultation-related operations including
 * requests, counselor assignment, session tracking, and notes.
 */

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/notification-functions.php';

/**
 * Create consultation tables if they don't exist
 * 
 * @param mysqli $conn Database connection
 */
function createConsultationTables($conn) {
    // Counselors table
    $counselorsSQL = "CREATE TABLE IF NOT EXISTS counselors (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT UNIQUE,
        name VARCHAR(100) NOT NULL,
        email VARCHAR(100) NOT NULL,
        specialization VARCHAR(255),
        bio TEXT,
        available TINYINT DEFAULT 1,
        max_sessions_per_day INT DEFAULT 5,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        INDEX idx_available (available)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    
    if (!$conn->query($counselorsSQL)) {
        error_log("Error creating counselors table: " . $conn->error);
    }
    
    // Consultation requests table
    $requestsSQL = "CREATE TABLE IF NOT EXISTS consultation_requests (
        id INT AUTO_INCREMENT PRIMARY KEY,
        student_id INT NOT NULL,
        parent_id INT,
        counselor_id INT,
        title VARCHAR(200) NOT NULL,
        description TEXT,
        preferred_date DATE,
        preferred_time TIME,
        urgency ENUM('low', 'medium', 'high', 'emergency') DEFAULT 'medium',
        status ENUM('pending', 'assigned', 'scheduled', 'completed', 'cancelled', 'rescheduled') DEFAULT 'pending',
        scheduled_date DATE,
        scheduled_time TIME,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY (parent_id) REFERENCES users(id) ON DELETE SET NULL,
        FOREIGN KEY (counselor_id) REFERENCES counselors(id) ON DELETE SET NULL,
        INDEX idx_status (status),
        INDEX idx_urgency (urgency),
        INDEX idx_student (student_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    
    if (!$conn->query($requestsSQL)) {
        error_log("Error creating consultation_requests table: " . $conn->error);
    }
    
    // Session notes table
    $notesSQL = "CREATE TABLE IF NOT EXISTS consultation_notes (
        id INT AUTO_INCREMENT PRIMARY KEY,
        request_id INT NOT NULL,
        counselor_id INT NOT NULL,
        notes TEXT,
        mood_before VARCHAR(50),
        mood_after VARCHAR(50),
        follow_up_needed TINYINT DEFAULT 0,
        follow_up_date DATE,
        private_notes TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (request_id) REFERENCES consultation_requests(id) ON DELETE CASCADE,
        FOREIGN KEY (counselor_id) REFERENCES counselors(id) ON DELETE CASCADE,
        INDEX idx_request (request_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    
    if (!$conn->query($notesSQL)) {
        error_log("Error creating consultation_notes table: " . $conn->error);
    }
}

/**
 * Create a new consultation request
 * 
 * @param mysqli $conn Database connection
 * @param array $data Request data
 * @return int|bool Request ID on success, false on failure
 */
function createConsultationRequest($conn, $data) {
    createConsultationTables($conn);
    
    $query = "INSERT INTO consultation_requests 
              (student_id, parent_id, title, description, preferred_date, preferred_time, urgency) 
              VALUES (?, ?, ?, ?, ?, ?, ?)";
    
    $stmt = $conn->prepare($query);
    if (!$stmt) {
        error_log("Prepare failed: " . $conn->error);
        return false;
    }
    
    $stmt->bind_param(
        "iisssss",
        $data['student_id'],
        $data['parent_id'],
        $data['title'],
        $data['description'],
        $data['preferred_date'],
        $data['preferred_time'],
        $data['urgency']
    );
    
    if ($stmt->execute()) {
        $request_id = $conn->insert_id;
        
        // Create notification for admins
        $adminQuery = "SELECT id FROM users WHERE role = 'admin'";
        $adminResult = $conn->query($adminQuery);
        if ($adminResult) {
            while ($admin = $adminResult->fetch_assoc()) {
                createNotification(
                    $conn,
                    $admin['id'],
                    "New consultation request #{$request_id}",
                    'info',
                    "admin/consultation-details.php?id={$request_id}"
                );
            }
        }
        
        return $request_id;
    }
    
    error_log("Execute failed: " . $stmt->error);
    return false;
}

/**
 * Assign a counselor to a consultation request
 * 
 * @param mysqli $conn Database connection
 * @param int $request_id Request ID
 * @param int $counselor_id Counselor ID
 * @return bool Success or failure
 */
function assignCounselor($conn, $request_id, $counselor_id) {
    $query = "UPDATE consultation_requests 
              SET counselor_id = ?, status = 'assigned' 
              WHERE id = ?";
    
    $stmt = $conn->prepare($query);
    if (!$stmt) {
        error_log("Prepare failed: " . $conn->error);
        return false;
    }
    
    $stmt->bind_param("ii", $counselor_id, $request_id);
    $success = $stmt->execute();
    
    if ($success && $stmt->affected_rows > 0) {
        // Get request details for notification
        $request = getConsultationById($conn, $request_id);
        
        if ($request) {
            // Notify counselor
            $counselorQuery = "SELECT user_id FROM counselors WHERE id = ?";
            $counselorStmt = $conn->prepare($counselorQuery);
            if ($counselorStmt) {
                $counselorStmt->bind_param("i", $counselor_id);
                $counselorStmt->execute();
                $counselorResult = $counselorStmt->get_result();
                $counselor = $counselorResult->fetch_assoc();
                
                if ($counselor) {
                    createNotification(
                        $conn,
                        $counselor['user_id'],
                        "You have been assigned to consultation request #{$request_id} for {$request['student_name']}",
                        'success',
                        "counselor/consultation-details.php?id={$request_id}"
                    );
                }
            }
            
            // Notify parent
            if ($request['parent_id']) {
                createNotification(
                    $conn,
                    $request['parent_id'],
                    "Counselor assigned to your consultation request for {$request['student_name']}",
                    'info',
                    "parent/consultation-details.php?id={$request_id}"
                );
            }
            
            // Notify student
            createNotification(
                $conn,
                $request['student_id'],
                "A counselor has been assigned to your consultation request",
                'info',
                "student/consultation-details.php?id={$request_id}"
            );
        }
        
        return true;
    }
    
    return false;
}

/**
 * Update consultation status - SIMPLIFIED VERSION without dynamic binding issues
 * 
 * @param mysqli $conn Database connection
 * @param int $request_id Request ID
 * @param string $status New status
 * @param string $scheduled_date Optional scheduled date
 * @param string $scheduled_time Optional scheduled time
 * @return bool Success or failure
 */
function updateConsultationStatus($conn, $request_id, $status, $scheduled_date = null, $scheduled_time = null) {
    // Simple approach - use separate queries based on what's provided
    
    if ($scheduled_date !== null && $scheduled_time !== null) {
        // Both date and time provided
        $query = "UPDATE consultation_requests SET status = ?, scheduled_date = ?, scheduled_time = ? WHERE id = ?";
        $stmt = $conn->prepare($query);
        if (!$stmt) return false;
        $stmt->bind_param("sssi", $status, $scheduled_date, $scheduled_time, $request_id);
    } 
    elseif ($scheduled_date !== null) {
        // Only date provided
        $query = "UPDATE consultation_requests SET status = ?, scheduled_date = ? WHERE id = ?";
        $stmt = $conn->prepare($query);
        if (!$stmt) return false;
        $stmt->bind_param("ssi", $status, $scheduled_date, $request_id);
    } 
    elseif ($scheduled_time !== null) {
        // Only time provided
        $query = "UPDATE consultation_requests SET status = ?, scheduled_time = ? WHERE id = ?";
        $stmt = $conn->prepare($query);
        if (!$stmt) return false;
        $stmt->bind_param("ssi", $status, $scheduled_time, $request_id);
    } 
    else {
        // Just status
        $query = "UPDATE consultation_requests SET status = ? WHERE id = ?";
        $stmt = $conn->prepare($query);
        if (!$stmt) return false;
        $stmt->bind_param("si", $status, $request_id);
    }
    
    return $stmt->execute();
}

/**
 * Add session notes
 * 
 * @param mysqli $conn Database connection
 * @param array $data Notes data
 * @return bool Success or failure
 */
function addSessionNotes($conn, $data) {
    $query = "INSERT INTO consultation_notes 
              (request_id, counselor_id, notes, mood_before, mood_after, follow_up_needed, follow_up_date, private_notes) 
              VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
    
    $stmt = $conn->prepare($query);
    if (!$stmt) {
        error_log("Prepare failed: " . $conn->error);
        return false;
    }
    
    // Set defaults for optional fields
    $mood_before = $data['mood_before'] ?? null;
    $mood_after = $data['mood_after'] ?? null;
    $follow_up_needed = $data['follow_up_needed'] ?? 0;
    $follow_up_date = $data['follow_up_date'] ?? null;
    $private_notes = $data['private_notes'] ?? null;
    
    $stmt->bind_param(
        "iisssiss",
        $data['request_id'],
        $data['counselor_id'],
        $data['notes'],
        $mood_before,
        $mood_after,
        $follow_up_needed,
        $follow_up_date,
        $private_notes
    );
    
    $success = $stmt->execute();
    
    if ($success) {
        // Update consultation status to completed
        updateConsultationStatus($conn, $data['request_id'], 'completed');
    }
    
    return $success;
}

/**
 * Get consultation history for a student
 * 
 * @param mysqli $conn Database connection
 * @param int $student_id Student ID
 * @return array Consultation history
 */
function getConsultationHistory($conn, $student_id) {
    $query = "SELECT cr.*, 
              c.name as counselor_name,
              (SELECT notes FROM consultation_notes WHERE request_id = cr.id ORDER BY created_at DESC LIMIT 1) as latest_notes
              FROM consultation_requests cr
              LEFT JOIN counselors c ON cr.counselor_id = c.id
              WHERE cr.student_id = ?
              ORDER BY cr.created_at DESC";
    
    $stmt = $conn->prepare($query);
    if (!$stmt) {
        error_log("Prepare failed: " . $conn->error);
        return [];
    }
    
    $stmt->bind_param("i", $student_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $history = [];
    while ($row = $result->fetch_assoc()) {
        $history[] = $row;
    }
    
    return $history;
}

/**
 * Get pending consultation requests
 * 
 * @param mysqli $conn Database connection
 * @param string $status Filter by status
 * @return array Pending requests
 */
function getPendingConsultations($conn, $status = 'pending') {
    $query = "SELECT cr.*, 
              u.name as student_name, 
              u.email as student_email,
              p.name as parent_name
              FROM consultation_requests cr
              JOIN users u ON cr.student_id = u.id
              LEFT JOIN users p ON cr.parent_id = p.id
              WHERE cr.status = ?
              ORDER BY cr.urgency DESC, cr.created_at ASC";
    
    $stmt = $conn->prepare($query);
    if (!$stmt) {
        error_log("Prepare failed: " . $conn->error);
        return [];
    }
    
    $stmt->bind_param("s", $status);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $requests = [];
    while ($row = $result->fetch_assoc()) {
        $requests[] = $row;
    }
    
    return $requests;
}

/**
 * Get counselor availability
 * 
 * @param mysqli $conn Database connection
 * @param int $counselor_id Counselor ID
 * @param string $date Date to check (Y-m-d)
 * @return array Availability info
 */
function getCounselorAvailability($conn, $counselor_id, $date) {
    // Get counselor's max sessions
    $query = "SELECT max_sessions_per_day FROM counselors WHERE id = ?";
    $stmt = $conn->prepare($query);
    if (!$stmt) {
        return [
            'max_sessions' => 5,
            'scheduled' => 0,
            'available' => false,
            'slots_left' => 0
        ];
    }
    
    $stmt->bind_param("i", $counselor_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $counselor = $result->fetch_assoc();
    
    $max_sessions = $counselor['max_sessions_per_day'] ?? 5;
    
    // Count scheduled sessions for the date
    $countQuery = "SELECT COUNT(*) as scheduled 
                   FROM consultation_requests 
                   WHERE counselor_id = ? 
                   AND scheduled_date = ? 
                   AND status IN ('scheduled', 'assigned')";
    $countStmt = $conn->prepare($countQuery);
    if (!$countStmt) {
        return [
            'max_sessions' => $max_sessions,
            'scheduled' => 0,
            'available' => true,
            'slots_left' => $max_sessions
        ];
    }
    
    $countStmt->bind_param("is", $counselor_id, $date);
    $countStmt->execute();
    $countResult = $countStmt->get_result();
    $count = $countResult->fetch_assoc()['scheduled'];
    
    return [
        'max_sessions' => $max_sessions,
        'scheduled' => $count,
        'available' => ($count < $max_sessions),
        'slots_left' => $max_sessions - $count
    ];
}

/**
 * Get all counselors
 * 
 * @param mysqli $conn Database connection
 * @return array List of counselors
 */
function getAllCounselors($conn) {
    $query = "SELECT c.*, u.name, u.email, u.avatar
              FROM counselors c
              JOIN users u ON c.user_id = u.id
              WHERE c.available = 1
              ORDER BY c.name";
    
    $result = $conn->query($query);
    if (!$result) {
        return [];
    }
    
    $counselors = [];
    while ($row = $result->fetch_assoc()) {
        $counselors[] = $row;
    }
    
    return $counselors;
}

/**
 * Get consultation details by ID
 * 
 * @param mysqli $conn Database connection
 * @param int $request_id Request ID
 * @return array|null Consultation details
 */
function getConsultationById($conn, $request_id) {
    $query = "SELECT cr.*, 
              u.name as student_name,
              u.email as student_email,
              p.name as parent_name,
              p.email as parent_email,
              c.name as counselor_name,
              c.specialization
              FROM consultation_requests cr
              JOIN users u ON cr.student_id = u.id
              LEFT JOIN users p ON cr.parent_id = p.id
              LEFT JOIN counselors c ON cr.counselor_id = c.id
              WHERE cr.id = ?";
    
    $stmt = $conn->prepare($query);
    if (!$stmt) {
        return null;
    }
    
    $stmt->bind_param("i", $request_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    return $result->fetch_assoc();
}

/**
 * Get session notes for a consultation
 * 
 * @param mysqli $conn Database connection
 * @param int $request_id Request ID
 * @return array Session notes
 */
function getConsultationNotes($conn, $request_id) {
    $query = "SELECT cn.*, c.name as counselor_name
              FROM consultation_notes cn
              JOIN counselors c ON cn.counselor_id = c.id
              WHERE cn.request_id = ?
              ORDER BY cn.created_at DESC";
    
    $stmt = $conn->prepare($query);
    if (!$stmt) {
        return [];
    }
    
    $stmt->bind_param("i", $request_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $notes = [];
    while ($row = $result->fetch_assoc()) {
        $notes[] = $row;
    }
    
    return $notes;
}

/**
 * Cancel consultation request
 * 
 * @param mysqli $conn Database connection
 * @param int $request_id Request ID
 * @param int $user_id User ID requesting cancellation
 * @return bool Success or failure
 */
function cancelConsultation($conn, $request_id, $user_id) {
    // Get request details for notification
    $request = getConsultationById($conn, $request_id);
    
    if (!$request) {
        return false;
    }
    
    $query = "UPDATE consultation_requests SET status = 'cancelled' WHERE id = ?";
    $stmt = $conn->prepare($query);
    if (!$stmt) {
        return false;
    }
    
    $stmt->bind_param("i", $request_id);
    $success = $stmt->execute();
    
    if ($success && $stmt->affected_rows > 0) {
        // Notify relevant parties
        $message = "Consultation #{$request_id} has been cancelled.";
        
        // Notify student
        createNotification($conn, $request['student_id'], $message, 'warning', "student/consultations.php");
        
        // Notify parent if exists
        if ($request['parent_id']) {
            createNotification($conn, $request['parent_id'], $message, 'warning', "parent/consultations.php");
        }
        
        // Notify counselor if assigned
        if ($request['counselor_id']) {
            $counselorQuery = "SELECT user_id FROM counselors WHERE id = ?";
            $counselorStmt = $conn->prepare($counselorQuery);
            if ($counselorStmt) {
                $counselorStmt->bind_param("i", $request['counselor_id']);
                $counselorStmt->execute();
                $counselorResult = $counselorStmt->get_result();
                $counselor = $counselorResult->fetch_assoc();
                
                if ($counselor) {
                    createNotification($conn, $counselor['user_id'], $message, 'warning', "counselor/consultations.php");
                }
            }
        }
        
        return true;
    }
    
    return false;
}

/**
 * Add a new counselor
 * 
 * @param mysqli $conn Database connection
 * @param array $data Counselor data
 * @return int|bool Counselor ID on success, false on failure
 */
function addCounselor($conn, $data) {
    $query = "INSERT INTO counselors (user_id, name, email, specialization, bio, max_sessions_per_day) 
              VALUES (?, ?, ?, ?, ?, ?)";
    
    $stmt = $conn->prepare($query);
    if (!$stmt) {
        return false;
    }
    
    $stmt->bind_param(
        "issssi",
        $data['user_id'],
        $data['name'],
        $data['email'],
        $data['specialization'],
        $data['bio'],
        $data['max_sessions_per_day']
    );
    
    if ($stmt->execute()) {
        return $conn->insert_id;
    }
    
    return false;
}

/**
 * Update counselor availability
 * 
 * @param mysqli $conn Database connection
 * @param int $counselor_id Counselor ID
 * @param int $available Availability status (1 or 0)
 * @return bool Success or failure
 */
function updateCounselorAvailability($conn, $counselor_id, $available) {
    $query = "UPDATE counselors SET available = ? WHERE id = ?";
    $stmt = $conn->prepare($query);
    if (!$stmt) {
        return false;
    }
    
    $stmt->bind_param("ii", $available, $counselor_id);
    return $stmt->execute();
}
?>