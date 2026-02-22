<?php
/**
 * submit-mood.php
 * Processes mood form data, runs AI analysis, calculates risk, and stores results
 */

require_once '../includes/auth.php';
require_once '../includes/ai-mood-engine.php';

// Ensure user is logged in and is a student
if (!isLoggedIn() || $_SESSION['role'] !== 'student') {
    header('Location: login.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$today = date('Y-m-d');

// Check for duplicate submission
$stmt = $pdo->prepare("SELECT id FROM mood_entries WHERE user_id = ? AND DATE(created_at) = ?");
$stmt->execute([$user_id, $today]);
if ($stmt->fetch()) {
    $_SESSION['mood_message'] = "You've already submitted your check-in for today!";
    $_SESSION['mood_message_type'] = "info";
    header('Location: mood.php');
    exit();
}

// Validate required fields
if (empty($_POST['mood']) || !isset($_POST['stress_level']) || !isset($_POST['energy_level'])) {
    $_SESSION['mood_message'] = "Please fill in all required fields.";
    $_SESSION['mood_message_type'] = "error";
    header('Location: mood.php');
    exit();
}

// Sanitize and prepare data
$mood = $_POST['mood'];
$stress_level = (int)$_POST['stress_level'];
$energy_level = (int)$_POST['energy_level'];
$notes = trim($_POST['notes'] ?? '');

// Study section
$study_hours = (float)($_POST['study_hours'] ?? 0);
$homework_completed = isset($_POST['homework_completed']) ? 1 : 0;
$subjects_studied = trim($_POST['subjects_studied'] ?? '');

// TV section
$watched_tv = isset($_POST['watched_tv']) ? 1 : 0;
$tv_hours = (float)($_POST['tv_hours'] ?? 0);
$tv_show_name = trim($_POST['tv_show_name'] ?? '');
$tv_content_type = $_POST['tv_content_type'] ?? '';

// Mobile section
$mobile_hours = (float)($_POST['mobile_hours'] ?? 0);
$mobile_purpose = isset($_POST['mobile_purpose']) ? implode(',', $_POST['mobile_purpose']) : '';

// Gaming section
$played_games = isset($_POST['played_games']) ? 1 : 0;
$game_name = trim($_POST['game_name'] ?? '');
$game_duration = (float)($_POST['game_duration'] ?? 0);
$game_type = trim($_POST['game_type'] ?? '');

// Sleep section
$sleep_hours = (float)($_POST['sleep_hours'] ?? 8);
$sleep_quality = $_POST['sleep_quality'] ?? 'good';

// Social section
$talked_family = isset($_POST['talked_family']) ? 1 : 0;
$met_friends = isset($_POST['met_friends']) ? 1 : 0;
$felt_lonely = isset($_POST['felt_lonely']) ? 1 : 0;

// Calculate total screen time
$total_screen_time = $tv_hours + $mobile_hours + $game_duration;

// Initialize AI Engine
$aiEngine = new AIMoodEngine($pdo, $user_id);

// Prepare data for AI analysis
$moodData = [
    'mood' => $mood,
    'stress_level' => $stress_level,
    'energy_level' => $energy_level,
    'notes' => $notes,
    'study_hours' => $study_hours,
    'homework_completed' => $homework_completed,
    'sleep_hours' => $sleep_hours,
    'sleep_quality' => $sleep_quality,
    'total_screen_time' => $total_screen_time,
    'felt_lonely' => $felt_lonely,
    'talked_family' => $talked_family,
    'met_friends' => $met_friends,
    'tv_content_type' => $tv_content_type
];

// Run AI analysis
$aiResult = $aiEngine->analyzeMood($moodData);

// Calculate risk score (can be overridden by AI or calculate here)
$risk_score = 0;
$risk_level = 'low';

// Risk scoring based on factors
if ($stress_level >= 4) $risk_score += 20;
if ($sleep_hours < 6) $risk_score += 25;
if ($total_screen_time > 4) $risk_score += 15;
if ($felt_lonely) $risk_score += 20;
if ($sleep_quality == 'poor') $risk_score += 10;
if ($sleep_quality == 'disturbed') $risk_score += 5;
if ($mood == 'sad' || $mood == 'stressed') $risk_score += 10;
if ($study_hours < 1 && $homework_completed == 0) $risk_score += 10;
if ($tv_content_type == 'violent' || $tv_content_type == 'horror') $risk_score += 10;

// Determine risk level
if ($risk_score >= 50) {
    $risk_level = 'high';
} elseif ($risk_score >= 25) {
    $risk_level = 'medium';
}

// Use AI's risk level if available (it might be more sophisticated)
if (isset($aiResult['risk_level'])) {
    // Convert AI risk level (which might have emojis) to our enum
    $aiRisk = $aiResult['risk_level'];
    if (strpos($aiRisk, 'High') !== false) {
        $risk_level = 'high';
    } elseif (strpos($aiRisk, 'Medium') !== false) {
        $risk_level = 'medium';
    } elseif (strpos($aiRisk, 'Low') !== false) {
        $risk_level = 'low';
    }
}

// Insert into database
try {
    $pdo->beginTransaction();
    
    $stmt = $pdo->prepare("
        INSERT INTO mood_entries (
            user_id, mood, stress_level, energy_level, notes,
            study_hours, homework_completed, subjects_studied,
            watched_tv, tv_hours, tv_show_name, tv_content_type,
            mobile_hours, mobile_purpose,
            played_games, game_name, game_duration, game_type,
            sleep_hours, sleep_quality,
            talked_family, met_friends, felt_lonely,
            risk_score, risk_level,
            ai_analysis, ai_suggestions,
            created_at
        ) VALUES (
            ?, ?, ?, ?, ?,
            ?, ?, ?,
            ?, ?, ?, ?,
            ?, ?,
            ?, ?, ?, ?,
            ?, ?,
            ?, ?, ?,
            ?, ?,
            ?, ?,
            NOW()
        )
    ");
    
    $ai_analysis = $aiResult['ai_analysis'] ?? $aiResult['emotional_summary'] ?? '';
    $ai_suggestions = $aiResult['ai_suggestions'] ?? $aiResult['suggestion']['description'] ?? '';
    
    $stmt->execute([
        $user_id, $mood, $stress_level, $energy_level, $notes,
        $study_hours, $homework_completed, $subjects_studied,
        $watched_tv, $tv_hours, $tv_show_name, $tv_content_type,
        $mobile_hours, $mobile_purpose,
        $played_games, $game_name, $game_duration, $game_type,
        $sleep_hours, $sleep_quality,
        $talked_family, $met_friends, $felt_lonely,
        $risk_score, $risk_level,
        $ai_analysis, $ai_suggestions
    ]);
    
    $entry_id = $pdo->lastInsertId();
    
    // If risk is HIGH, create alert
    if ($risk_level === 'high') {
        // Insert into mood_alerts
        $alert_stmt = $pdo->prepare("
            INSERT INTO mood_alerts (user_id, alert_type, risk_score, message, created_at)
            VALUES (?, 'high_risk', ?, ?, NOW())
        ");
        
        $alert_message = "High emotional risk detected. Risk score: $risk_score. " . ($ai_analysis ?: 'Please check on student.');
        $alert_stmt->execute([$user_id, $risk_score, $alert_message]);
        
        // If parent email exists in users table (you might need to add this field)
        // For now, we'll create a parent notification placeholder
        $parent_stmt = $pdo->prepare("
            INSERT INTO parent_notifications (student_id, alert_type, message, created_at)
            VALUES (?, 'high_risk', ?, NOW())
        ");
        $parent_message = "Your child's wellness check-in indicates high stress levels. Please check in with them.";
        $parent_stmt->execute([$user_id, $parent_message]);
    }
    
    // Check for 3 consecutive sad/stressed entries
    $check_stmt = $pdo->prepare("
        SELECT mood FROM mood_entries 
        WHERE user_id = ? 
        ORDER BY created_at DESC 
        LIMIT 3
    ");
    $check_stmt->execute([$user_id]);
    $last_three = $check_stmt->fetchAll();
    
    if (count($last_three) == 3) {
        $consecutive_sad = true;
        foreach ($last_three as $entry) {
            if (!in_array($entry['mood'], ['sad', 'stressed'])) {
                $consecutive_sad = false;
                break;
            }
        }
        
        if ($consecutive_sad && $risk_level !== 'high') {
            // Create alert for consecutive sad entries
            $alert_stmt = $pdo->prepare("
                INSERT INTO mood_alerts (user_id, alert_type, risk_score, message, created_at)
                VALUES (?, 'consecutive_sad', 0, ?, NOW())
            ");
            $alert_stmt->execute([$user_id, "Student has reported feeling sad or stressed for 3 consecutive days."]);
        }
    }
    
    $pdo->commit();
    
    // Store AI message in session to display on dashboard
    $_SESSION['ai_insight'] = $aiResult['ai_message'] ?? "Thank you for sharing how you feel today!";
    $_SESSION['risk_level'] = $risk_level;
    $_SESSION['show_ai_popup'] = true;
    
    // Redirect to dashboard with success message
    $_SESSION['mood_message'] = "Your wellness check-in has been recorded! 🎉";
    $_SESSION['mood_message_type'] = "success";
    
    header('Location: dashboard.php');
    exit();
    
} catch (Exception $e) {
    $pdo->rollBack();
    error_log("Error saving mood: " . $e->getMessage());
    $_SESSION['mood_message'] = "Something went wrong. Please try again.";
    $_SESSION['mood_message_type'] = "error";
    header('Location: mood.php');
    exit();
}
?>