<?php
require_once __DIR__ . '/../config/db.php';

function getLinkedStudent($parent_id) {
    global $pdo;
    
    try {
        // Check if parent has linked students
        $stmt = $pdo->prepare("
            SELECT u.* FROM users u 
            JOIN parent_student_relationship psr ON u.id = psr.student_id 
            WHERE psr.parent_id = ?
        ");
        $stmt->execute([$parent_id]);
        $student = $stmt->fetch();
        
        if (!$student) {
            // Get first student for demo
            $stmt = $pdo->prepare("SELECT * FROM users WHERE role = 'student' LIMIT 1");
            $stmt->execute();
            $student = $stmt->fetch();
            
            if ($student) {
                // Create relationship
                $stmt = $pdo->prepare("INSERT IGNORE INTO parent_student_relationship (parent_id, student_id, relationship) VALUES (?, ?, 'Parent')");
                $stmt->execute([$parent_id, $student['id']]);
            }
        }
        
        return $student;
    } catch(PDOException $e) {
        error_log("Error in getLinkedStudent: " . $e->getMessage());
        return null;
    }
}

function getLatestStudentReport($student_id) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("
            SELECT * FROM student_daily_report 
            WHERE student_id = ? 
            ORDER BY report_date DESC 
            LIMIT 1
        ");
        $stmt->execute([$student_id]);
        return $stmt->fetch();
    } catch(PDOException $e) {
        error_log("Error in getLatestStudentReport: " . $e->getMessage());
        return null;
    }
}

function getLatestRiskAlert($student_id) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("
            SELECT * FROM mood_alerts 
            WHERE student_id = ? 
            ORDER BY created_at DESC 
            LIMIT 1
        ");
        $stmt->execute([$student_id]);
        return $stmt->fetch();
    } catch(PDOException $e) {
        error_log("Error in getLatestRiskAlert: " . $e->getMessage());
        return null;
    }
}

function getWeeklyStats($student_id) {
    global $pdo;
    
    $stats = [
        'avg_sleep' => 0,
        'avg_screen' => 0,
        'wellness_score' => 75,
        'stress_data' => [],
        'energy_data' => [],
        'chart_labels' => [],
        'ai_insight' => 'Stress increased during exam week. Encourage structured routine and breaks.',
        'ai_recommendation' => 'Consider establishing a consistent sleep schedule and daily outdoor time.'
    ];
    
    try {
        // Get last 7 days reports
        $stmt = $pdo->prepare("
            SELECT * FROM student_daily_report 
            WHERE student_id = ? 
            AND report_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
            ORDER BY report_date ASC
        ");
        $stmt->execute([$student_id]);
        $reports = $stmt->fetchAll();
        
        if (count($reports) > 0) {
            $total_sleep = 0;
            $total_screen = 0;
            $count = count($reports);
            
            foreach ($reports as $report) {
                $stats['chart_labels'][] = date('D', strtotime($report['report_date']));
                $stats['stress_data'][] = (int)$report['stress_level'];
                $stats['energy_data'][] = (int)$report['energy_level'];
                $total_sleep += (float)$report['sleep_hours'];
                $total_screen += (float)$report['screen_hours'];
            }
            
            $stats['avg_sleep'] = $count > 0 ? round($total_sleep / $count, 1) : 0;
            $stats['avg_screen'] = $count > 0 ? round($total_screen / $count, 1) : 0;
            
            // Calculate wellness score
            if (count($stats['stress_data']) > 0 && count($stats['energy_data']) > 0) {
                $avg_stress = array_sum($stats['stress_data']) / $count;
                $avg_energy = array_sum($stats['energy_data']) / $count;
                $stats['wellness_score'] = round((($avg_energy * 20) + (6 - $avg_stress) * 20) / 2);
            }
        } else {
            // Use demo data if no reports
            $stats['chart_labels'] = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
            $stats['stress_data'] = [3, 4, 4, 5, 3, 2, 2];
            $stats['energy_data'] = [3, 2, 3, 2, 4, 4, 5];
            $stats['avg_sleep'] = 7.5;
            $stats['avg_screen'] = 4.5;
        }
    } catch(PDOException $e) {
        error_log("Error in getWeeklyStats: " . $e->getMessage());
        // Use demo data
        $stats['chart_labels'] = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
        $stats['stress_data'] = [3, 4, 4, 5, 3, 2, 2];
        $stats['energy_data'] = [3, 2, 3, 2, 4, 4, 5];
        $stats['avg_sleep'] = 7.5;
        $stats['avg_screen'] = 4.5;
    }
    
    return $stats;
}

function getStudentAlerts($student_id, $filters = []) {
    global $pdo;
    
    try {
        $sql = "SELECT * FROM mood_alerts WHERE student_id = ?";
        $params = [$student_id];
        
        if (!empty($filters['risk_level'])) {
            $sql .= " AND risk_level = ?";
            $params[] = $filters['risk_level'];
        }
        
        if (!empty($filters['date_from'])) {
            $sql .= " AND alert_date >= ?";
            $params[] = $filters['date_from'];
        }
        
        if (!empty($filters['date_to'])) {
            $sql .= " AND alert_date <= ?";
            $params[] = $filters['date_to'];
        }
        
        $sql .= " ORDER BY alert_date DESC, created_at DESC";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    } catch(PDOException $e) {
        error_log("Error in getStudentAlerts: " . $e->getMessage());
        // Return demo data
        return [
            ['id' => 1, 'alert_date' => date('Y-m-d'), 'risk_level' => 'High', 'ai_summary' => 'High stress levels detected with low sleep pattern', 'status' => 'unread', 'created_at' => date('Y-m-d H:i:s')],
            ['id' => 2, 'alert_date' => date('Y-m-d', strtotime('-1 day')), 'risk_level' => 'Medium', 'ai_summary' => 'Increased screen time affecting mood', 'status' => 'read', 'created_at' => date('Y-m-d H:i:s', strtotime('-1 day'))],
        ];
    }
}

function getBehaviorData($student_id) {
    global $pdo;
    
    $data = [
        'avg_screen' => 4.5,
        'avg_screen_last' => 3.8,
        'avg_study' => 3.2,
        'avg_sleep' => 7.5,
        'sleep_quality' => 75,
        'outdoor_days' => 3,
        'social_days' => 4,
        'week_days' => ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'],
        'screen_data' => [4.5, 5.2, 4.8, 5.5, 6.1, 5.8, 4.2],
        'study_data' => [3.2, 2.8, 3.5, 2.5, 3.8, 2.0, 4.0],
        'sleep_data' => [7.5, 7.2, 8.1, 6.8, 7.3, 8.5, 8.0]
    ];
    
    try {
        // Get last 30 days reports for analysis
        $stmt = $pdo->prepare("
            SELECT * FROM student_daily_report 
            WHERE student_id = ? 
            AND report_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
            ORDER BY report_date DESC
        ");
        $stmt->execute([$student_id]);
        $reports = $stmt->fetchAll();
        
        if (count($reports) >= 14) {
            // Calculate averages
            $recent = array_slice($reports, 0, 7);
            $previous = array_slice($reports, 7, 7);
            
            $data['avg_screen'] = round(array_sum(array_column($recent, 'screen_hours')) / 7, 1);
            $data['avg_screen_last'] = round(array_sum(array_column($previous, 'screen_hours')) / 7, 1);
            $data['avg_study'] = round(array_sum(array_column($recent, 'study_hours')) / 7, 1);
            $data['avg_sleep'] = round(array_sum(array_column($recent, 'sleep_hours')) / 7, 1);
            $data['outdoor_days'] = count(array_filter($recent, function($r) { return $r['outdoor_activity']; }));
            $data['social_days'] = count(array_filter($recent, function($r) { return $r['social_interaction']; }));
            
            // Prepare chart data
            $recent = array_reverse($recent);
            $data['week_days'] = array_map(function($r) { return date('D', strtotime($r['report_date'])); }, $recent);
            $data['screen_data'] = array_column($recent, 'screen_hours');
            $data['study_data'] = array_column($recent, 'study_hours');
            $data['sleep_data'] = array_column($recent, 'sleep_hours');
        }
    } catch(PDOException $e) {
        error_log("Error in getBehaviorData: " . $e->getMessage());
    }
    
    return $data;
}

function getBehaviorInsights($student_id) {
    return [
        'behavior_insight' => 'High screen usage correlated with reduced sleep and mood decline. Consider setting screen time limits and encouraging outdoor activities.'
    ];
}

function getMoodHistoryData($student_id) {
    global $pdo;
    
    $data = [
        'mood_counts' => ['happy' => 12, 'neutral' => 8, 'sad' => 4, 'stressed' => 5, 'motivated' => 7],
        'dates' => [],
        'stress_data' => [],
        'energy_data' => []
    ];
    
    try {
        // Get last 30 days mood entries
        $stmt = $pdo->prepare("
            SELECT * FROM mood_entries 
            WHERE user_id = ? 
            ORDER BY created_at DESC 
            LIMIT 30
        ");
        $stmt->execute([$student_id]);
        $entries = $stmt->fetchAll();
        
        if ($entries) {
            $entries = array_reverse($entries);
            $mood_counts = ['happy' => 0, 'neutral' => 0, 'sad' => 0, 'stressed' => 0, 'motivated' => 0];
            $stress_data = [];
            $energy_data = [];
            $dates = [];
            
            foreach ($entries as $entry) {
                $mood_counts[$entry['mood']]++;
                $stress_data[] = (int)$entry['stress_level'];
                $energy_data[] = (int)$entry['energy_level'];
                $dates[] = date('M d', strtotime($entry['created_at']));
            }
            
            $data['mood_counts'] = $mood_counts;
            $data['stress_data'] = $stress_data;
            $data['energy_data'] = $energy_data;
            $data['dates'] = $dates;
        }
    } catch(PDOException $e) {
        error_log("Error in getMoodHistoryData: " . $e->getMessage());
    }
    
    return $data;
}

function getMoodCalendarData($student_id) {
    global $pdo;
    
    $calendar = [];
    
    try {
        $stmt = $pdo->prepare("
            SELECT * FROM mood_entries 
            WHERE user_id = ? 
            AND created_at >= DATE_SUB(NOW(), INTERVAL 1 MONTH)
            ORDER BY created_at
        ");
        $stmt->execute([$student_id]);
        $entries = $stmt->fetchAll();
        
        foreach ($entries as $entry) {
            $date = date('Y-m-d', strtotime($entry['created_at']));
            $calendar[$date] = $entry;
        }
    } catch(PDOException $e) {
        error_log("Error in getMoodCalendarData: " . $e->getMessage());
    }
    
    return $calendar;
}

function getAvailableCounselors() {
    global $pdo;
    
    try {
        // Get users with admin role as counselors for demo
        $stmt = $pdo->prepare("SELECT id, name FROM users WHERE role = 'admin' LIMIT 5");
        $stmt->execute();
        $counselors = $stmt->fetchAll();
        
        if (empty($counselors)) {
            // Demo counselors
            $counselors = [
                ['id' => 1, 'name' => 'Dr. Sarah Johnson', 'specialization' => 'Child Psychologist', 'rating' => 4.9, 'sessions' => 120],
                ['id' => 2, 'name' => 'Dr. Michael Chen', 'specialization' => 'Educational Counselor', 'rating' => 4.8, 'sessions' => 95],
                ['id' => 3, 'name' => 'Dr. Priya Sharma', 'specialization' => 'Behavioral Therapist', 'rating' => 4.9, 'sessions' => 150],
            ];
        } else {
            // Add default specialization and rating
            foreach ($counselors as &$c) {
                $c['specialization'] = 'General Counselor';
                $c['rating'] = 4.8;
                $c['sessions'] = 50;
            }
        }
        
        return $counselors;
    } catch(PDOException $e) {
        error_log("Error in getAvailableCounselors: " . $e->getMessage());
        return [];
    }
}

function bookConsultation($parent_id, $student_id, $data) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("
            INSERT INTO consultation_requests 
            (student_id, parent_id, issue_type, description, counselor_id, preferred_date, preferred_time, status) 
            VALUES (?, ?, ?, ?, ?, ?, ?, 'Pending')
        ");
        
        $stmt->execute([
            $student_id,
            $parent_id,
            $data['issue_type'],
            $data['description'] ?? null,
            !empty($data['counselor_id']) ? $data['counselor_id'] : null,
            $data['preferred_date'],
            $data['preferred_time']
        ]);
        
        return ['success' => true, 'message' => 'Consultation booked successfully'];
    } catch(PDOException $e) {
        error_log("Error in bookConsultation: " . $e->getMessage());
        return ['success' => false, 'message' => 'Failed to book consultation: ' . $e->getMessage()];
    }
}

function getConsultationHistory($parent_id, $student_id) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("
            SELECT cr.*, u.name as counselor_name, sn.notes, sn.recommendations 
            FROM consultation_requests cr
            LEFT JOIN users u ON cr.counselor_id = u.id
            LEFT JOIN session_notes sn ON cr.id = sn.consultation_id
            WHERE cr.parent_id = ? AND cr.student_id = ?
            ORDER BY cr.preferred_date DESC, cr.preferred_time DESC
        ");
        $stmt->execute([$parent_id, $student_id]);
        return $stmt->fetchAll();
    } catch(PDOException $e) {
        error_log("Error in getConsultationHistory: " . $e->getMessage());
        // Demo data
        return [
            [
                'id' => 1,
                'preferred_date' => date('Y-m-d'),
                'preferred_time' => '10:00:00',
                'counselor_name' => 'Dr. Sarah Johnson',
                'issue_type' => 'Emotional Stress',
                'status' => 'Completed',
                'notes' => 'Student showed signs of academic stress. Recommended regular breaks and mindfulness exercises.',
                'recommendations' => 'Practice deep breathing for 5 minutes daily.'
            ],
            [
                'id' => 2,
                'preferred_date' => date('Y-m-d', strtotime('+2 days')),
                'preferred_time' => '14:30:00',
                'counselor_name' => 'Dr. Michael Chen',
                'issue_type' => 'Academic Pressure',
                'status' => 'Approved',
                'notes' => null,
                'recommendations' => null
            ]
        ];
    }
}

function getParentNotifications($user_id) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("
            SELECT n.*, 
                   CASE WHEN un.id IS NOT NULL THEN 1 ELSE 0 END as is_read 
            FROM notifications n
            LEFT JOIN user_notifications un ON n.id = un.notification_id AND un.user_id = ?
            WHERE n.target_role IN ('all', 'parent')
            ORDER BY n.created_at DESC
            LIMIT 20
        ");
        $stmt->execute([$user_id]);
        return $stmt->fetchAll();
    } catch(PDOException $e) {
        error_log("Error in getParentNotifications: " . $e->getMessage());
        // Demo notifications
        return [
            [
                'id' => 1,
                'title' => 'Risk Alert: High Stress Detected',
                'message' => 'Your child showed high stress levels today. Consider checking in.',
                'type' => 'warning',
                'created_at' => date('Y-m-d H:i:s'),
                'is_read' => 0
            ],
            [
                'id' => 2,
                'title' => 'Consultation Approved',
                'message' => 'Your consultation request has been approved for tomorrow at 2:30 PM',
                'type' => 'success',
                'created_at' => date('Y-m-d H:i:s', strtotime('-1 day')),
                'is_read' => 1
            ],
            [
                'id' => 3,
                'title' => 'Weekly Report Available',
                'message' => 'Your child\'s weekly wellness report is now available',
                'type' => 'info',
                'created_at' => date('Y-m-d H:i:s', strtotime('-2 days')),
                'is_read' => 0
            ]
        ];
    }
}

function markNotificationRead($notification_id) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("INSERT IGNORE INTO user_notifications (user_id, notification_id, is_read, read_at) VALUES (?, ?, 1, NOW())");
        $stmt->execute([$_SESSION['user_id'], $notification_id]);
    } catch(PDOException $e) {
        error_log("Error in markNotificationRead: " . $e->getMessage());
    }
}

function markAllNotificationsRead($user_id) {
    global $pdo;
    
    try {
        // Get all unread notifications
        $stmt = $pdo->prepare("
            INSERT IGNORE INTO user_notifications (user_id, notification_id, is_read, read_at)
            SELECT ?, id, 1, NOW() FROM notifications 
            WHERE target_role IN ('all', 'parent')
            AND id NOT IN (SELECT notification_id FROM user_notifications WHERE user_id = ?)
        ");
        $stmt->execute([$user_id, $user_id]);
    } catch(PDOException $e) {
        error_log("Error in markAllNotificationsRead: " . $e->getMessage());
    }
}

function getStudentProfileData($student_id) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$student_id]);
        $user = $stmt->fetch();
        
        if ($user) {
            return array_merge($user, [
                'class' => '10th',
                'age' => '16 years',
                'dob' => 'Jan 15, 2010',
                'blood_group' => 'O+',
                'parent_phone' => '+91 98765 43210',
                'address' => 'Mumbai, India',
                'emergency_contact' => '+91 98765 43210'
            ]);
        }
        
        return [
            'class' => '10th',
            'age' => '16 years',
            'dob' => 'Jan 15, 2010',
            'blood_group' => 'O+',
            'parent_phone' => '+91 98765 43210',
            'address' => 'Mumbai, India',
            'emergency_contact' => '+91 98765 43210'
        ];
    } catch(PDOException $e) {
        error_log("Error in getStudentProfileData: " . $e->getMessage());
        return [
            'class' => '10th',
            'age' => '16 years',
            'dob' => 'Jan 15, 2010',
            'blood_group' => 'O+',
            'parent_phone' => '+91 98765 43210',
            'address' => 'Mumbai, India',
            'emergency_contact' => '+91 98765 43210'
        ];
    }
}

function getStudentRiskSummary($student_id) {
    global $pdo;
    
    $summary = [
        'high_count' => 0,
        'medium_count' => 0,
        'low_count' => 0,
        'last_assessment' => 'Today, 10:30 AM',
        'wellness_score' => 75
    ];
    
    try {
        $stmt = $pdo->prepare("
            SELECT risk_level, COUNT(*) as count 
            FROM mood_alerts 
            WHERE student_id = ? 
            GROUP BY risk_level
        ");
        $stmt->execute([$student_id]);
        $results = $stmt->fetchAll();
        
        foreach ($results as $row) {
            $summary[strtolower($row['risk_level']) . '_count'] = $row['count'];
        }
        
        // Get last assessment
        $stmt = $pdo->prepare("
            SELECT created_at FROM mood_alerts 
            WHERE student_id = ? 
            ORDER BY created_at DESC 
            LIMIT 1
        ");
        $stmt->execute([$student_id]);
        $last = $stmt->fetch();
        
        if ($last) {
            $summary['last_assessment'] = date('M d, h:i A', strtotime($last['created_at']));
        }
        
        // Get wellness score from latest report
        $report = getLatestStudentReport($student_id);
        if ($report) {
            $summary['wellness_score'] = round((($report['energy_level'] * 20) + (6 - $report['stress_level']) * 20) / 2);
        }
    } catch(PDOException $e) {
        error_log("Error in getStudentRiskSummary: " . $e->getMessage());
        // Use demo data
        $summary['high_count'] = 2;
        $summary['medium_count'] = 5;
        $summary['low_count'] = 8;
    }
    
    return $summary;
}
?>