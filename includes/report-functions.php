<?php
/**
 * Report Functions - Generate summary reports and analytics
 * 
 * This file handles all reporting functionality including monthly stats,
 * risk trends, behavior averages, and export capabilities.
 */

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/risk-engine.php';

/**
 * Get monthly mood statistics for a student
 * 
 * @param mysqli $conn Database connection
 * @param int $student_id Student ID
 * @param string $month Month (YYYY-MM)
 * @return array Monthly mood statistics
 */
function getMonthlyMoodStats($conn, $student_id, $month = null) {
    if (!$month) {
        $month = date('Y-m');
    }
    
    $start_date = $month . '-01';
    $end_date = date('Y-m-t', strtotime($start_date));
    
    $query = "SELECT 
              DATE(created_at) as date,
              mood,
              stress_level,
              energy_level,
              notes
              FROM mood_entries 
              WHERE user_id = ? 
              AND DATE(created_at) BETWEEN ? AND ?
              ORDER BY created_at ASC";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("iss", $student_id, $start_date, $end_date);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $daily_entries = [];
    $mood_counts = ['happy' => 0, 'neutral' => 0, 'sad' => 0, 'stressed' => 0, 'motivated' => 0];
    $stress_sum = 0;
    $energy_sum = 0;
    $entry_count = 0;
    
    while ($row = $result->fetch_assoc()) {
        $daily_entries[$row['date']][] = $row;
        $mood_counts[$row['mood']] = ($mood_counts[$row['mood']] ?? 0) + 1;
        $stress_sum += $row['stress_level'];
        $energy_sum += $row['energy_level'];
        $entry_count++;
    }
    
    $avg_stress = $entry_count > 0 ? round($stress_sum / $entry_count, 1) : 0;
    $avg_energy = $entry_count > 0 ? round($energy_sum / $entry_count, 1) : 0;
    
    // Find most common mood
    $most_common_mood = array_search(max($mood_counts), $mood_counts);
    
    // Calculate mood trend
    $trend = [];
    foreach ($daily_entries as $date => $entries) {
        $day_moods = array_column($entries, 'mood');
        $day_stress = array_sum(array_column($entries, 'stress_level')) / count($entries);
        
        $trend[] = [
            'date' => $date,
            'primary_mood' => most_frequent($day_moods),
            'avg_stress' => round($day_stress, 1),
            'entries' => count($entries)
        ];
    }
    
    return [
        'month' => $month,
        'total_entries' => $entry_count,
        'days_with_entries' => count($daily_entries),
        'mood_distribution' => $mood_counts,
        'most_common_mood' => $most_common_mood,
        'avg_stress' => $avg_stress,
        'avg_energy' => $avg_energy,
        'daily_trend' => $trend,
        'raw_data' => $daily_entries
    ];
}

/**
 * Helper function to find most frequent item in array
 */
function most_frequent($arr) {
    $values = array_count_values($arr);
    return array_search(max($values), $values);
}

/**
 * Helper function to get maximum risk level from array
 */
function getMaxRiskLevel($levels) {
    $priority = ['Critical' => 4, 'High' => 3, 'Medium' => 2, 'Low' => 1, 'Minimal' => 0];
    $max_level = 'Minimal';
    $max_priority = 0;
    
    foreach ($levels as $level) {
        if ($priority[$level] > $max_priority) {
            $max_priority = $priority[$level];
            $max_level = $level;
        }
    }
    
    return $max_level;
}

/**
 * Get risk trend over a date range
 * 
 * @param mysqli $conn Database connection
 * @param int $student_id Student ID
 * @param string $start_date Start date (YYYY-MM-DD)
 * @param string $end_date End date (YYYY-MM-DD)
 * @return array Risk trend data
 */
function getRiskTrend($conn, $student_id, $start_date, $end_date) {
    $riskEngine = new RiskEngine();
    
    $query = "SELECT * FROM mood_entries 
              WHERE user_id = ? 
              AND DATE(created_at) BETWEEN ? AND ?
              ORDER BY created_at ASC";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("iss", $student_id, $start_date, $end_date);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $daily_risks = [];
    
    while ($row = $result->fetch_assoc()) {
        $riskData = $riskEngine->calculateRiskScore([
            'student_id' => $student_id,
            'mood' => $row['mood'],
            'stress_level' => $row['stress_level'],
            'energy_level' => $row['energy_level']
        ]);
        
        $date = date('Y-m-d', strtotime($row['created_at']));
        
        if (!isset($daily_risks[$date])) {
            $daily_risks[$date] = [
                'total_score' => 0,
                'count' => 0,
                'levels' => []
            ];
        }
        
        $daily_risks[$date]['total_score'] += $riskData['score'];
        $daily_risks[$date]['count']++;
        $daily_risks[$date]['levels'][] = $riskData['level'];
    }
    
    $trend = [];
    foreach ($daily_risks as $date => $data) {
        $trend[] = [
            'date' => $date,
            'avg_risk_score' => round($data['total_score'] / $data['count'], 1),
            'max_risk_level' => getMaxRiskLevel($data['levels']),
            'entries' => $data['count']
        ];
    }
    
    return $trend;
}

/**
 * Generate CSV report from data
 * 
 * @param array $data Report data
 * @param string $filename Filename for download
 */
function generateCSVReport($data, $filename = 'report.csv') {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    
    $output = fopen('php://output', 'w');
    
    // Add headers
    if (!empty($data)) {
        fputcsv($output, array_keys($data[0]));
        
        // Add data rows
        foreach ($data as $row) {
            fputcsv($output, $row);
        }
    }
    
    fclose($output);
    exit;
}

/**
 * Generate PDF report (simplified HTML version)
 * 
 * @param array $data Report data
 * @param string $title Report title
 * @return string HTML content for PDF
 */
function generatePDFReport($data, $title = 'Wellness Report') {
    $html = '<!DOCTYPE html>
    <html>
    <head>
        <title>' . $title . '</title>
        <style>
            body { font-family: Arial, sans-serif; }
            h1 { color: #333; }
            table { width: 100%; border-collapse: collapse; margin-top: 20px; }
            th { background-color: #4CAF50; color: white; padding: 10px; }
            td { border: 1px solid #ddd; padding: 8px; text-align: left; }
            tr:nth-child(even) { background-color: #f2f2f2; }
        </style>
    </head>
    <body>
        <h1>' . $title . '</h1>
        <p>Generated on: ' . date('Y-m-d H:i:s') . '</p>';
    
    if (!empty($data)) {
        $html .= '<table>
            <thead><tr>';
        
        // Add headers
        foreach (array_keys($data[0]) as $header) {
            $html .= '<th>' . ucwords(str_replace('_', ' ', $header)) . '</th>';
        }
        $html .= '</tr></thead><tbody>';
        
        // Add data rows
        foreach ($data as $row) {
            $html .= '<tr>';
            foreach ($row as $value) {
                $html .= '<td>' . htmlspecialchars($value) . '</td>';
            }
            $html .= '</tr>';
        }
        
        $html .= '</tbody></table>';
    }
    
    $html .= '</body></html>';
    
    return $html;
}

/**
 * Get mood distribution chart data
 * 
 * @param array $mood_counts Mood counts
 * @return array Chart data
 */
function getMoodChartData($mood_counts) {
    $colors = [
        'happy' => '#4CAF50',
        'neutral' => '#FFC107',
        'sad' => '#2196F3',
        'stressed' => '#F44336',
        'motivated' => '#9C27B0'
    ];
    
    $chart_data = [
        'labels' => [],
        'data' => [],
        'backgroundColor' => []
    ];
    
    foreach ($mood_counts as $mood => $count) {
        if ($count > 0) {
            $chart_data['labels'][] = ucfirst($mood);
            $chart_data['data'][] = $count;
            $chart_data['backgroundColor'][] = $colors[$mood] ?? '#999999';
        }
    }
    
    return $chart_data;
}

/**
 * Get weekly summary report
 * 
 * @param mysqli $conn Database connection
 * @param int $student_id Student ID
 * @return array Weekly summary
 */
function getWeeklySummary($conn, $student_id) {
    $start_date = date('Y-m-d', strtotime('-7 days'));
    $end_date = date('Y-m-d');
    
    $query = "SELECT 
              AVG(stress_level) as avg_stress,
              AVG(energy_level) as avg_energy,
              COUNT(*) as total_entries,
              SUM(CASE WHEN mood IN ('sad', 'stressed') THEN 1 ELSE 0 END) as negative_moods,
              SUM(CASE WHEN mood IN ('happy', 'motivated') THEN 1 ELSE 0 END) as positive_moods
              FROM mood_entries 
              WHERE user_id = ? 
              AND DATE(created_at) BETWEEN ? AND ?";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("iss", $student_id, $start_date, $end_date);
    $stmt->execute();
    $result = $stmt->get_result();
    $stats = $result->fetch_assoc();
    
    // Calculate mood ratio
    $total_moods = ($stats['negative_moods'] ?? 0) + ($stats['positive_moods'] ?? 0);
    $mood_ratio = $total_moods > 0 ? round(($stats['positive_moods'] / $total_moods) * 100) : 0;
    
    return [
        'period' => 'Last 7 days',
        'start_date' => $start_date,
        'end_date' => $end_date,
        'total_entries' => $stats['total_entries'] ?? 0,
        'avg_stress' => round($stats['avg_stress'] ?? 0, 1),
        'avg_energy' => round($stats['avg_energy'] ?? 0, 1),
        'positive_mood_ratio' => $mood_ratio,
        'negative_moods' => $stats['negative_moods'] ?? 0,
        'positive_moods' => $stats['positive_moods'] ?? 0
    ];
}
?>