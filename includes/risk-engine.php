<?php
/**
 * Risk Engine - Central Risk Scoring Calculator
 * 
 * This file processes mood and behavior data to calculate risk scores
 * and determine risk levels for students.
 */

require_once __DIR__ . '/../config/db.php';

class RiskEngine {
    private $conn;
    private $pdo;
    
    /**
     * Constructor - initialize database connection
     */
    public function __construct($pdo = null) {
        global $pdo;
        $this->pdo = $pdo;
        
        // Also maintain mysqli connection for backward compatibility
        $this->conn = $this->getMysqliConnection();
    }
    
    /**
     * Get MySQLi connection from config
     */
    private function getMysqliConnection() {
        // Include db.php which creates $conn variable
        include __DIR__ . '/../config/db.php';
        
        // Check if $conn exists and return it
        if (isset($conn) && $conn instanceof mysqli) {
            return $conn;
        }
        
        // If $conn doesn't exist, create a new MySQLi connection
        $host = 'localhost';
        $username = 'root';
        $password = '';
        $database = 'auth_system';
        
        $new_conn = new mysqli($host, $username, $password, $database);
        
        if ($new_conn->connect_error) {
            die("MySQLi Connection failed: " . $new_conn->connect_error);
        }
        
        return $new_conn;
    }
    
    /**
     * Calculate risk score based on student data
     * 
     * @param array $data Student data including mood entries
     * @return array Risk assessment result
     */
    public function calculateRiskScore($data) {
        $score = 0;
        $factors = [];
        
        // Factor 1: Current mood stress level
        if (isset($data['stress_level']) && $data['stress_level'] >= 4) {
            $score += 3;
            $factors[] = 'High stress level (+3)';
        }
        
        // Factor 2: Mood type risk
        if (isset($data['mood'])) {
            switch ($data['mood']) {
                case 'stressed':
                    $score += 2;
                    $factors[] = 'Stressed mood (+2)';
                    break;
                case 'sad':
                    $score += 2;
                    $factors[] = 'Sad mood (+2)';
                    break;
                case 'neutral':
                    $score += 1;
                    $factors[] = 'Neutral mood (+1)';
                    break;
            }
        }
        
        // Factor 3: Energy level (low energy is risk)
        if (isset($data['energy_level']) && $data['energy_level'] <= 2) {
            $score += 2;
            $factors[] = 'Low energy level (+2)';
        }
        
        // Factor 4: Consecutive sad detections
        if (isset($data['student_id'])) {
            $consecutiveSad = $this->detectConsecutiveSad($data['student_id']);
            if ($consecutiveSad >= 3) {
                $score += 3;
                $factors[] = '3+ consecutive sad entries (+3)';
            } elseif ($consecutiveSad >= 2) {
                $score += 2;
                $factors[] = '2 consecutive sad entries (+2)';
            }
        }
        
        // Cap the score at 15
        $score = min($score, 15);
        
        // Determine risk level
        $riskLevel = $this->getRiskLevel($score);
        
        // Generate explanation
        $explanation = $this->generateExplanation($score, $riskLevel, $factors);
        
        return [
            'score' => $score,
            'level' => $riskLevel,
            'factors' => $factors,
            'explanation' => $explanation,
            'requires_intervention' => ($score >= 8),
            'requires_immediate_action' => ($score >= 12)
        ];
    }
    
    /**
     * Detect consecutive sad mood entries
     * 
     * @param int $student_id Student ID
     * @return int Count of consecutive sad entries
     */
    public function detectConsecutiveSad($student_id) {
        $query = "SELECT mood FROM mood_entries 
                  WHERE user_id = ? 
                  ORDER BY created_at DESC 
                  LIMIT 5";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("i", $student_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $consecutive = 0;
        while ($row = $result->fetch_assoc()) {
            if ($row['mood'] == 'sad' || $row['mood'] == 'stressed') {
                $consecutive++;
            } else {
                break;
            }
        }
        
        return $consecutive;
    }
    
    /**
     * Get risk level based on score
     * 
     * @param int $score Risk score
     * @return string Risk level
     */
    public function getRiskLevel($score) {
        if ($score >= 12) {
            return 'Critical';
        } elseif ($score >= 8) {
            return 'High';
        } elseif ($score >= 5) {
            return 'Medium';
        } elseif ($score >= 3) {
            return 'Low';
        } else {
            return 'Minimal';
        }
    }
    
    /**
     * Generate explanation for risk assessment
     * 
     * @param int $score Risk score
     * @param string $level Risk level
     * @param array $factors Contributing factors
     * @return string Explanation
     */
    private function generateExplanation($score, $level, $factors) {
        $explanation = "Risk Level: {$level} (Score: {$score}/15). ";
        
        if (empty($factors)) {
            $explanation .= "No significant risk factors detected.";
        } else {
            $explanation .= "Contributing factors: " . implode(", ", $factors) . ".";
        }
        
        if ($score >= 12) {
            $explanation .= " Immediate intervention recommended.";
        } elseif ($score >= 8) {
            $explanation .= " Schedule a counseling session soon.";
        } elseif ($score >= 5) {
            $explanation .= " Monitor closely and check in with student.";
        }
        
        return $explanation;
    }
    
    /**
     * Calculate overall risk for a student over time
     * 
     * @param int $student_id Student ID
     * @param int $days Number of days to analyze
     * @return array Risk trend data
     */
    public function calculateStudentRiskTrend($student_id, $days = 7) {
        $query = "SELECT me.*, 
                  DATE(me.created_at) as entry_date
                  FROM mood_entries me
                  WHERE me.user_id = ?
                  AND me.created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
                  ORDER BY me.created_at DESC";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("ii", $student_id, $days);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $daily_risks = [];
        
        while ($row = $result->fetch_assoc()) {
            $riskData = $this->calculateRiskScore([
                'student_id' => $student_id,
                'mood' => $row['mood'],
                'stress_level' => $row['stress_level'],
                'energy_level' => $row['energy_level']
            ]);
            
            $date = $row['entry_date'];
            
            if (!isset($daily_risks[$date])) {
                $daily_risks[$date] = [
                    'total_score' => 0,
                    'count' => 0,
                    'max_level' => 'Minimal'
                ];
            }
            
            $daily_risks[$date]['total_score'] += $riskData['score'];
            $daily_risks[$date]['count']++;
            
            // Track highest risk level for the day
            $levelPriority = ['Critical' => 4, 'High' => 3, 'Medium' => 2, 'Low' => 1, 'Minimal' => 0];
            $currentPriority = $levelPriority[$riskData['level']];
            $existingPriority = $levelPriority[$daily_risks[$date]['max_level']];
            
            if ($currentPriority > $existingPriority) {
                $daily_risks[$date]['max_level'] = $riskData['level'];
            }
        }
        
        // Calculate averages for each day
        $trend = [];
        foreach ($daily_risks as $date => $data) {
            $trend[] = [
                'date' => $date,
                'avg_score' => round($data['total_score'] / $data['count'], 1),
                'max_level' => $data['max_level'],
                'entries' => $data['count']
            ];
        }
        
        return $trend;
    }
    
    /**
     * Get students at risk (for admin dashboard)
     * 
     * @param string $min_level Minimum risk level to include
     * @return array List of students at risk
     */
    public function getStudentsAtRisk($min_level = 'Medium') {
        $level_priority = ['Critical' => 4, 'High' => 3, 'Medium' => 2, 'Low' => 1, 'Minimal' => 0];
        $min_priority = $level_priority[$min_level];
        
        $query = "SELECT u.id, u.name, u.email, 
                  COUNT(me.id) as recent_entries,
                  MAX(me.created_at) as last_entry
                  FROM users u
                  LEFT JOIN mood_entries me ON u.id = me.user_id
                  WHERE u.role = 'student' AND u.status = 'active'
                  GROUP BY u.id
                  HAVING last_entry >= DATE_SUB(NOW(), INTERVAL 3 DAY)";
        
        $result = $this->conn->query($query);
        
        if (!$result) {
            return [];
        }
        
        $students = $result->fetch_all(MYSQLI_ASSOC);
        
        $at_risk = [];
        
        foreach ($students as $student) {
            // Get latest mood entry
            $mood_query = "SELECT * FROM mood_entries 
                           WHERE user_id = ? 
                           ORDER BY created_at DESC LIMIT 1";
            $mood_stmt = $this->conn->prepare($mood_query);
            $mood_stmt->bind_param("i", $student['id']);
            $mood_stmt->execute();
            $mood_result = $mood_stmt->get_result();
            $latest = $mood_result->fetch_assoc();
            
            if ($latest) {
                $riskData = $this->calculateRiskScore([
                    'student_id' => $student['id'],
                    'mood' => $latest['mood'],
                    'stress_level' => $latest['stress_level'],
                    'energy_level' => $latest['energy_level']
                ]);
                
                if ($level_priority[$riskData['level']] >= $min_priority) {
                    $student['risk_score'] = $riskData['score'];
                    $student['risk_level'] = $riskData['level'];
                    $student['risk_explanation'] = $riskData['explanation'];
                    $student['latest_mood'] = $latest['mood'];
                    $at_risk[] = $student;
                }
            }
        }
        
        // Sort by risk score (highest first)
        usort($at_risk, function($a, $b) {
            return $b['risk_score'] - $a['risk_score'];
        });
        
        return $at_risk;
    }
}
?>