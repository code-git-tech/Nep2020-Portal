<?php
/**
 * Behavior Engine - Analyze lifestyle patterns and correlate with mood
 */

require_once __DIR__ . '/../config/db.php';

class BehaviorEngine {
    private $conn;
    
    /**
     * Constructor - initialize database connection
     */
    public function __construct() {
        global $conn;
        $this->conn = $conn;
    }
    
    /**
     * Analyze screen time patterns
     */
    public function analyzeScreenTime($mobile_time = 0, $tv_time = 0, $gaming_time = 0, $study_time = 0) {
        $total_screen = $mobile_time + $tv_time + $gaming_time;
        $recreational_screen = $total_screen - $study_time;
        
        $analysis = [
            'total_screen' => round($total_screen, 1),
            'recreational_screen' => round($recreational_screen, 1),
            'study_screen' => round($study_time, 1),
            'screen_risk' => false,
            'screen_risk_level' => 'Low',
            'recommendations' => []
        ];
        
        // Assess risk based on recreational screen time
        if ($recreational_screen > 6) {
            $analysis['screen_risk'] = true;
            $analysis['screen_risk_level'] = 'High';
            $analysis['recommendations'][] = 'Reduce recreational screen time to less than 4 hours';
            $analysis['recommendations'][] = 'Try outdoor activities or hobbies';
        } elseif ($recreational_screen > 4) {
            $analysis['screen_risk'] = true;
            $analysis['screen_risk_level'] = 'Medium';
            $analysis['recommendations'][] = 'Consider reducing recreational screen time';
            $analysis['recommendations'][] = 'Take regular breaks every 30 minutes';
        }
        
        // Check screen-to-study ratio
        if ($study_time > 0 && $recreational_screen > ($study_time * 2)) {
            $analysis['recommendations'][] = 'Recreational screen time is double your study time - try to balance';
        }
        
        return $analysis;
    }
    
    /**
     * Analyze sleep patterns
     */
    public function analyzeSleepPattern($sleep_hours, $sleep_quality = 'fair') {
        $analysis = [
            'sleep_hours' => $sleep_hours,
            'sleep_quality' => $sleep_quality,
            'sleep_risk' => false,
            'sleep_risk_level' => 'Low',
            'recommendations' => []
        ];
        
        // Check sleep duration
        if ($sleep_hours < 5) {
            $analysis['sleep_risk'] = true;
            $analysis['sleep_risk_level'] = 'Critical';
            $analysis['recommendations'][] = 'Severe sleep deprivation - aim for 7-9 hours';
            $analysis['recommendations'][] = 'Consider consulting a doctor if persistent';
        } elseif ($sleep_hours < 6) {
            $analysis['sleep_risk'] = true;
            $analysis['sleep_risk_level'] = 'High';
            $analysis['recommendations'][] = 'Insufficient sleep - try to get at least 7 hours';
            $analysis['recommendations'][] = 'Establish a consistent sleep schedule';
        } elseif ($sleep_hours < 7) {
            $analysis['sleep_risk'] = true;
            $analysis['sleep_risk_level'] = 'Medium';
            $analysis['recommendations'][] = 'Borderline sleep - aim for 7-8 hours';
        }
        
        // Check sleep quality
        if ($sleep_quality == 'poor') {
            $analysis['recommendations'][] = 'Improve sleep quality: avoid screens before bed, keep room dark';
        }
        
        return $analysis;
    }
    
    /**
     * Analyze study focus patterns
     */
    public function analyzeStudyFocus($study_hours, $target_hours = 4, $completion_rate = 70) {
        $analysis = [
            'study_hours' => $study_hours,
            'target_hours' => $target_hours,
            'completion_rate' => $completion_rate,
            'study_risk' => false,
            'productivity_level' => 'Average',
            'recommendations' => []
        ];
        
        // Check study hours
        if ($study_hours < ($target_hours * 0.5)) {
            $analysis['study_risk'] = true;
            $analysis['productivity_level'] = 'Low';
            $analysis['recommendations'][] = 'Study time is significantly below target';
            $analysis['recommendations'][] = 'Try breaking study sessions into smaller chunks';
        } elseif ($study_hours < $target_hours) {
            $analysis['study_risk'] = true;
            $analysis['productivity_level'] = 'Below Average';
            $analysis['recommendations'][] = 'Try to increase study time gradually';
        } elseif ($study_hours > $target_hours * 1.5) {
            $analysis['productivity_level'] = 'High';
            $analysis['recommendations'][] = 'Great effort! Remember to take breaks to avoid burnout';
        }
        
        // Check completion rate
        if ($completion_rate < 50) {
            $analysis['recommendations'][] = 'Assignment completion is low - consider seeking help';
        } elseif ($completion_rate < 70) {
            $analysis['recommendations'][] = 'Try to improve assignment completion rate';
        }
        
        return $analysis;
    }
    
    /**
     * Analyze social isolation patterns
     */
    public function analyzeSocialIsolation($social_data) {
        $analysis = [
            'social_score' => 0,
            'isolation_risk' => false,
            'isolation_level' => 'Low',
            'recommendations' => []
        ];
        
        $score = 0;
        
        // Check social interaction frequency
        if (isset($social_data['interactions_per_week'])) {
            $interactions = $social_data['interactions_per_week'];
            if ($interactions < 2) {
                $analysis['isolation_risk'] = true;
                $analysis['isolation_level'] = 'High';
                $score += 3;
                $analysis['recommendations'][] = 'Very low social interaction - try to connect with friends';
            } elseif ($interactions < 4) {
                $analysis['isolation_risk'] = true;
                $analysis['isolation_level'] = 'Medium';
                $score += 2;
                $analysis['recommendations'][] = 'Consider increasing social activities';
            }
        }
        
        // Check if they feel lonely
        if (isset($social_data['feels_lonely']) && $social_data['feels_lonely'] === true) {
            $analysis['isolation_risk'] = true;
            $score += 2;
            $analysis['recommendations'][] = 'Feelings of loneliness detected - reach out to friends or counselor';
        }
        
        // Check group participation
        if (isset($social_data['group_participation']) && $social_data['group_participation'] === false) {
            $score += 1;
            $analysis['recommendations'][] = 'Join study groups or clubs to connect with peers';
        }
        
        $analysis['social_score'] = $score;
        
        return $analysis;
    }
    
    /**
     * Generate comprehensive behavior summary
     */
    public function generateBehaviorSummary($data) {
        $summary = [
            'screen_analysis' => $this->analyzeScreenTime(
                $data['mobile_time'] ?? 0,
                $data['tv_time'] ?? 0,
                $data['gaming_time'] ?? 0,
                $data['study_time'] ?? 0
            ),
            'sleep_analysis' => $this->analyzeSleepPattern(
                $data['sleep_hours'] ?? 7,
                $data['sleep_quality'] ?? 'fair'
            ),
            'study_analysis' => $this->analyzeStudyFocus(
                $data['study_hours'] ?? 0,
                $data['target_hours'] ?? 4,
                $data['completion_rate'] ?? 70
            ),
            'social_analysis' => $this->analyzeSocialIsolation(
                $data['social_data'] ?? []
            ),
            'overall_risk' => false,
            'overall_risk_level' => 'Low',
            'behavior_summary' => '',
            'key_recommendations' => []
        ];
        
        // Determine overall risk
        $risk_factors = 0;
        $risk_details = [];
        
        if ($summary['screen_analysis']['screen_risk']) {
            $risk_factors++;
            $risk_details[] = 'high screen time';
        }
        
        if ($summary['sleep_analysis']['sleep_risk']) {
            $risk_factors++;
            $risk_details[] = 'sleep issues';
        }
        
        if ($summary['study_analysis']['study_risk']) {
            $risk_factors++;
            $risk_details[] = 'low study focus';
        }
        
        if ($summary['social_analysis']['isolation_risk']) {
            $risk_factors++;
            $risk_details[] = 'social isolation';
        }
        
        // Set overall risk level
        if ($risk_factors >= 3) {
            $summary['overall_risk'] = true;
            $summary['overall_risk_level'] = 'High';
        } elseif ($risk_factors >= 2) {
            $summary['overall_risk'] = true;
            $summary['overall_risk_level'] = 'Medium';
        } elseif ($risk_factors >= 1) {
            $summary['overall_risk'] = true;
            $summary['overall_risk_level'] = 'Low';
        }
        
        // Generate behavior summary text
        if (!empty($risk_details)) {
            $summary['behavior_summary'] = "Risk factors detected: " . implode(', ', $risk_details) . 
                                           ". These patterns may affect emotional well-being.";
        } else {
            $summary['behavior_summary'] = "No significant behavioral risk factors detected. Maintaining healthy habits.";
        }
        
        // Collect key recommendations
        $all_recommendations = array_merge(
            $summary['screen_analysis']['recommendations'],
            $summary['sleep_analysis']['recommendations'],
            $summary['study_analysis']['recommendations'],
            $summary['social_analysis']['recommendations']
        );
        
        $summary['key_recommendations'] = array_slice($all_recommendations, 0, 5);
        
        return $summary;
    }
    
    /**
     * Get behavior trends for a student over time
     */
    public function getBehaviorTrends($student_id, $days = 30) {
        $query = "SELECT DATE(created_at) as date,
                  AVG(stress_level) as avg_stress,
                  AVG(energy_level) as avg_energy,
                  COUNT(*) as entries
                  FROM mood_entries
                  WHERE user_id = ?
                  AND created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
                  GROUP BY DATE(created_at)
                  ORDER BY date DESC";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("ii", $student_id, $days);
        $stmt->execute();
        $result = $stmt->get_result();
        
        return $result->fetch_all(MYSQLI_ASSOC);
    }
}
?>