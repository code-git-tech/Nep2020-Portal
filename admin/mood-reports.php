<?php
/**
 * Admin Mood Reports Dashboard
 * File: admin/mood-reports.php
 * 
 * Displays comprehensive mood analytics for all students
 * Based on actual database structure from auth_system.sql
 */

require_once '../includes/auth.php';
require_once '../includes/ai-mood-engine.php';

// Ensure user is logged in and is admin
if (!isLoggedIn() || $_SESSION['role'] !== 'admin') {
    header('Location: login.php');
    exit();
}

// Get filter parameters
$filter = $_GET['filter'] ?? 'week';
$student_id = isset($_GET['student_id']) ? (int)$_GET['student_id'] : null;
$date_from = $_GET['date_from'] ?? date('Y-m-d', strtotime('-30 days'));
$date_to = $_GET['date_to'] ?? date('Y-m-d');

// Validate filter
$valid_filters = ['day', 'week', 'month', 'custom'];
if (!in_array($filter, $valid_filters)) {
    $filter = 'week';
}

// Build date condition
$date_condition = "";
switch($filter) {
    case 'day':
        $date_condition = "AND me.created_at >= DATE_SUB(NOW(), INTERVAL 1 DAY)";
        break;
    case 'week':
        $date_condition = "AND me.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
        break;
    case 'month':
        $date_condition = "AND me.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
        break;
    case 'custom':
        $date_condition = "AND DATE(me.created_at) BETWEEN :date_from AND :date_to";
        break;
}

// Build student filter
$student_condition = "";
if ($student_id) {
    $student_condition = "AND me.user_id = :student_id";
}

// Get all students for dropdown
$students_stmt = $pdo->query("
    SELECT id, name, email 
    FROM users 
    WHERE role = 'student' 
    ORDER BY name
");
$students = $students_stmt->fetchAll();

// Get overall mood statistics
$stats_query = "
    SELECT 
        COUNT(DISTINCT me.user_id) as active_students,
        COUNT(me.id) as total_entries,
        ROUND(AVG(me.stress_level), 1) as avg_stress,
        ROUND(AVG(me.energy_level), 1) as avg_energy,
        ROUND(AVG(
            CASE 
                WHEN me.mood = 'happy' THEN 5
                WHEN me.mood = 'motivated' THEN 4
                WHEN me.mood = 'neutral' THEN 3
                WHEN me.mood = 'sad' THEN 2
                WHEN me.mood = 'stressed' THEN 1
                ELSE 3
            END
        ), 1) as avg_mood_score,
        MAX(me.created_at) as latest_entry,
        MIN(me.created_at) as first_entry
    FROM mood_entries me
    WHERE 1=1
    $date_condition
    $student_condition
";

$stats_stmt = $pdo->prepare($stats_query);

if ($filter === 'custom') {
    $stats_stmt->bindParam(':date_from', $date_from);
    $stats_stmt->bindParam(':date_to', $date_to);
}
if ($student_id) {
    $stats_stmt->bindParam(':student_id', $student_id, PDO::PARAM_INT);
}

$stats_stmt->execute();
$stats = $stats_stmt->fetch();

// Get mood distribution
$distribution_query = "
    SELECT 
        me.mood,
        COUNT(*) as count,
        ROUND(COUNT(*) * 100.0 / SUM(COUNT(*)) OVER(), 1) as percentage,
        ROUND(AVG(me.stress_level), 1) as avg_stress,
        ROUND(AVG(me.energy_level), 1) as avg_energy
    FROM mood_entries me
    WHERE 1=1
    $date_condition
    $student_condition
    GROUP BY me.mood
    ORDER BY 
        CASE me.mood
            WHEN 'happy' THEN 1
            WHEN 'motivated' THEN 2
            WHEN 'neutral' THEN 3
            WHEN 'sad' THEN 4
            WHEN 'stressed' THEN 5
        END
";

$dist_stmt = $pdo->prepare($distribution_query);

if ($filter === 'custom') {
    $dist_stmt->bindParam(':date_from', $date_from);
    $dist_stmt->bindParam(':date_to', $date_to);
}
if ($student_id) {
    $dist_stmt->bindParam(':student_id', $student_id, PDO::PARAM_INT);
}

$dist_stmt->execute();
$mood_distribution = $dist_stmt->fetchAll();

// Get trend data for chart
$trend_query = "
    SELECT 
        DATE(me.created_at) as date,
        ROUND(AVG(me.stress_level), 1) as avg_stress,
        ROUND(AVG(me.energy_level), 1) as avg_energy,
        COUNT(*) as entry_count,
        GROUP_CONCAT(DISTINCT me.mood) as moods
    FROM mood_entries me
    WHERE 1=1
    $date_condition
    $student_condition
    GROUP BY DATE(me.created_at)
    ORDER BY date ASC
";

$trend_stmt = $pdo->prepare($trend_query);

if ($filter === 'custom') {
    $trend_stmt->bindParam(':date_from', $date_from);
    $trend_stmt->bindParam(':date_to', $date_to);
}
if ($student_id) {
    $trend_stmt->bindParam(':student_id', $student_id, PDO::PARAM_INT);
}

$trend_stmt->execute();
$trend_data = $trend_stmt->fetchAll();

// Prepare chart data
$chart_dates = [];
$chart_stress = [];
$chart_energy = [];
$chart_mood_scores = [];

foreach ($trend_data as $row) {
    $chart_dates[] = date('M d', strtotime($row['date']));
    $chart_stress[] = $row['avg_stress'];
    $chart_energy[] = $row['avg_energy'];
    
    // Calculate average mood score from moods string
    $moods = explode(',', $row['moods']);
    $mood_score = 0;
    $mood_count = 0;
    foreach ($moods as $m) {
        switch($m) {
            case 'sad': $mood_score += 1; break;
            case 'stressed': $mood_score += 2; break;
            case 'neutral': $mood_score += 3; break;
            case 'happy': $mood_score += 4; break;
            case 'motivated': $mood_score += 5; break;
            default: $mood_score += 3;
        }
        $mood_count++;
    }
    $chart_mood_scores[] = $mood_count > 0 ? round($mood_score / $mood_count, 1) : 3;
}

// Get student list with their latest mood
$students_mood_query = "
    SELECT 
        u.id,
        u.name,
        u.email,
        u.last_login,
        u.status,
        COUNT(me.id) as total_entries,
        MAX(me.created_at) as last_mood_date,
        (
            SELECT me2.mood 
            FROM mood_entries me2 
            WHERE me2.user_id = u.id 
            ORDER BY me2.created_at DESC 
            LIMIT 1
        ) as current_mood,
        (
            SELECT me2.stress_level 
            FROM mood_entries me2 
            WHERE me2.user_id = u.id 
            ORDER BY me2.created_at DESC 
            LIMIT 1
        ) as current_stress,
        (
            SELECT ROUND(AVG(me3.stress_level), 1)
            FROM mood_entries me3
            WHERE me3.user_id = u.id
            AND me3.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
        ) as weekly_avg_stress
    FROM users u
    LEFT JOIN mood_entries me ON u.id = me.user_id
    WHERE u.role = 'student'
    GROUP BY u.id
    ORDER BY last_mood_date DESC
";

$students_mood = $pdo->query($students_mood_query)->fetchAll();

// Calculate risk indicators
$high_risk_students = 0;
$moderate_risk_students = 0;
foreach ($students_mood as $student) {
    if ($student['weekly_avg_stress'] >= 4) {
        $high_risk_students++;
    } elseif ($student['weekly_avg_stress'] >= 3) {
        $moderate_risk_students++;
    }
}

// Mood configuration for UI
$mood_config = [
    'happy' => [
        'color' => 'yellow',
        'bg' => 'bg-yellow-100',
        'text' => 'text-yellow-800',
        'badge' => 'bg-yellow-500',
        'icon' => '😊',
        'label' => 'Happy',
        'gradient' => 'from-yellow-400 to-yellow-500'
    ],
    'neutral' => [
        'color' => 'gray',
        'bg' => 'bg-gray-100',
        'text' => 'text-gray-800',
        'badge' => 'bg-gray-500',
        'icon' => '😐',
        'label' => 'Neutral',
        'gradient' => 'from-gray-400 to-gray-500'
    ],
    'sad' => [
        'color' => 'blue',
        'bg' => 'bg-blue-100',
        'text' => 'text-blue-800',
        'icon' => '😔',
        'label' => 'Sad',
        'gradient' => 'from-blue-400 to-blue-500'
    ],
    'stressed' => [
        'color' => 'red',
        'bg' => 'bg-red-100',
        'text' => 'text-red-800',
        'icon' => '😰',
        'label' => 'Stressed',
        'gradient' => 'from-red-400 to-red-500'
    ],
    'motivated' => [
        'color' => 'green',
        'bg' => 'bg-green-100',
        'text' => 'text-green-800',
        'icon' => '🔥',
        'label' => 'Motivated',
        'gradient' => 'from-green-400 to-green-500'
    ]
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mood Analytics Dashboard - Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap');
        
        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, #1e1b4b 0%, #312e81 100%);
            position: relative;
            overflow-x: hidden;
        }
        
        /* Animated Background */
        .bg-animation {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: -1;
            overflow: hidden;
        }
        
        .bg-animation span {
            position: absolute;
            display: block;
            width: 20px;
            height: 20px;
            background: rgba(255, 255, 255, 0.05);
            border-radius: 50%;
            animation: animate 25s linear infinite;
            bottom: -150px;
        }
        
        .bg-animation span:nth-child(1) { left: 25%; width: 80px; height: 80px; animation-delay: 0s; }
        .bg-animation span:nth-child(2) { left: 10%; width: 20px; height: 20px; animation-delay: 2s; animation-duration: 12s; }
        .bg-animation span:nth-child(3) { left: 70%; width: 20px; height: 20px; animation-delay: 4s; }
        .bg-animation span:nth-child(4) { left: 40%; width: 60px; height: 60px; animation-delay: 0s; animation-duration: 18s; }
        .bg-animation span:nth-child(5) { left: 65%; width: 20px; height: 20px; animation-delay: 0s; }
        .bg-animation span:nth-child(6) { left: 75%; width: 110px; height: 110px; animation-delay: 3s; }
        .bg-animation span:nth-child(7) { left: 35%; width: 150px; height: 150px; animation-delay: 7s; }
        .bg-animation span:nth-child(8) { left: 50%; width: 25px; height: 25px; animation-delay: 15s; animation-duration: 45s; }
        .bg-animation span:nth-child(9) { left: 20%; width: 15px; height: 15px; animation-delay: 2s; animation-duration: 35s; }
        .bg-animation span:nth-child(10) { left: 85%; width: 150px; height: 150px; animation-delay: 0s; animation-duration: 11s; }
        
        @keyframes animate {
            0% { transform: translateY(0) rotate(0deg); opacity: 1; }
            100% { transform: translateY(-1000px) rotate(720deg); opacity: 0; }
        }
        
        .glass-card {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 1rem;
            transition: all 0.3s ease;
        }
        
        .glass-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 20px 30px -10px rgba(0, 0, 0, 0.3);
            background: rgba(255, 255, 255, 0.15);
        }
        
        .stat-card {
            animation: slideIn 0.5s ease-out forwards;
            opacity: 0;
        }
        
        .stat-card:nth-child(1) { animation-delay: 0.1s; }
        .stat-card:nth-child(2) { animation-delay: 0.2s; }
        .stat-card:nth-child(3) { animation-delay: 0.3s; }
        .stat-card:nth-child(4) { animation-delay: 0.4s; }
        
        @keyframes slideIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .filter-btn {
            padding: 0.5rem 1.5rem;
            border-radius: 0.5rem;
            font-size: 0.875rem;
            font-weight: 500;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }
        
        .filter-btn.active {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.4);
        }
        
        .filter-btn:not(.active) {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(5px);
            color: white;
            border: 1px solid rgba(255, 255, 255, 0.2);
        }
        
        .filter-btn:not(.active):hover {
            background: rgba(255, 255, 255, 0.2);
            transform: translateY(-2px);
        }
        
        .chart-container {
            position: relative;
            height: 400px;
            width: 100%;
        }
        
        .risk-high {
            background: rgba(239, 68, 68, 0.2);
            color: #fecaca;
        }
        
        .risk-medium {
            background: rgba(245, 158, 11, 0.2);
            color: #fed7aa;
        }
        
        .risk-low {
            background: rgba(16, 185, 129, 0.2);
            color: #a7f3d0;
        }
        
        .mood-badge {
            display: inline-flex;
            align-items: center;
            padding: 0.5rem 1rem;
            border-radius: 9999px;
            font-size: 0.875rem;
            font-weight: 500;
            transition: all 0.3s ease;
        }
        
        .floating {
            animation: float 6s ease-in-out infinite;
        }
        
        @keyframes float {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-10px); }
        }
        
        .ripple {
            position: relative;
            overflow: hidden;
        }
        
        .ripple:after {
            content: "";
            display: block;
            position: absolute;
            width: 100%;
            height: 100%;
            top: 0;
            left: 0;
            pointer-events: none;
            background-image: radial-gradient(circle, #fff 10%, transparent 10.01%);
            background-repeat: no-repeat;
            background-position: 50%;
            transform: scale(10, 10);
            opacity: 0;
            transition: transform .3s, opacity .5s;
        }
        
        .ripple:active:after {
            transform: scale(0, 0);
            opacity: .2;
            transition: 0s;
        }
        
        .gradient-text {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        
        .table-row {
            transition: all 0.3s ease;
            background: rgba(255, 255, 255, 0.05);
        }
        
        .table-row:hover {
            background: rgba(255, 255, 255, 0.1);
            transform: scale(1.01);
        }
    </style>
</head>
<body class="h-full">
    <!-- Animated Background -->
    <div class="bg-animation">
        <span></span><span></span><span></span><span></span><span></span>
        <span></span><span></span><span></span><span></span><span></span>
    </div>
    
    <div class="flex h-screen">
        <!-- Include Sidebar -->
        <?php include 'sidebar.php'; ?>
        
        <!-- Main Content -->
        <div class="flex-1 overflow-auto">
            <!-- Header -->
            <div class="glass-card m-4 p-4 flex items-center justify-between">
                <h1 class="text-2xl font-bold text-white flex items-center">
                    <i class="fas fa-chart-pie text-purple-300 mr-3 animate-pulse"></i>
                    Mood Analytics Dashboard
                </h1>
                <div class="flex items-center space-x-4">
                    <span class="text-sm text-white/70">
                        <i class="far fa-calendar-alt mr-2"></i>
                        <?= date('F j, Y') ?>
                    </span>
                    <div class="w-10 h-10 bg-gradient-to-r from-purple-600 to-indigo-600 rounded-full flex items-center justify-center text-white font-semibold shadow-lg">
                        <?= strtoupper(substr($_SESSION['name'] ?? 'A', 0, 1)) ?>
                    </div>
                </div>
            </div>
            
            <!-- Main Content Area -->
            <div class="p-8">
                <!-- Filters -->
                <div class="mb-8 flex flex-wrap items-center justify-between gap-4 floating">
                    <div class="flex space-x-2">
                        <a href="?filter=day<?= $student_id ? '&student_id='.$student_id : '' ?>" 
                           class="filter-btn <?= $filter === 'day' ? 'active' : '' ?> ripple">
                            <i class="fas fa-sun mr-2"></i>Today
                        </a>
                        <a href="?filter=week<?= $student_id ? '&student_id='.$student_id : '' ?>" 
                           class="filter-btn <?= $filter === 'week' ? 'active' : '' ?> ripple">
                            <i class="fas fa-calendar-week mr-2"></i>Last 7 Days
                        </a>
                        <a href="?filter=month<?= $student_id ? '&student_id='.$student_id : '' ?>" 
                           class="filter-btn <?= $filter === 'month' ? 'active' : '' ?> ripple">
                            <i class="fas fa-calendar-alt mr-2"></i>Last 30 Days
                        </a>
                        <a href="?filter=custom<?= $student_id ? '&student_id='.$student_id : '' ?>" 
                           class="filter-btn <?= $filter === 'custom' ? 'active' : '' ?> ripple">
                            <i class="fas fa-sliders-h mr-2"></i>Custom
                        </a>
                    </div>
                    
                    <div class="flex items-center space-x-4">
                        <!-- Student Filter -->
                        <select onchange="window.location.href='?filter=<?= $filter ?>&student_id='+this.value" 
                                class="bg-white/10 backdrop-blur-sm border border-white/20 rounded-lg px-4 py-2 text-sm text-white focus:outline-none focus:ring-2 focus:ring-purple-600">
                            <option value="" class="bg-gray-800">All Students</option>
                            <?php foreach ($students as $student): ?>
                                <option value="<?= $student['id'] ?>" <?= $student_id == $student['id'] ? 'selected' : '' ?> class="bg-gray-800">
                                    <?= htmlspecialchars($student['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        
                        <?php if ($filter === 'custom'): ?>
                            <form method="GET" class="flex space-x-2">
                                <input type="hidden" name="filter" value="custom">
                                <?php if ($student_id): ?>
                                    <input type="hidden" name="student_id" value="<?= $student_id ?>">
                                <?php endif; ?>
                                <input type="date" name="date_from" value="<?= $date_from ?>" 
                                       class="bg-white/10 backdrop-blur-sm border border-white/20 rounded-lg px-3 py-2 text-sm text-white">
                                <input type="date" name="date_to" value="<?= $date_to ?>" 
                                       class="bg-white/10 backdrop-blur-sm border border-white/20 rounded-lg px-3 py-2 text-sm text-white">
                                <button type="submit" class="bg-gradient-to-r from-purple-600 to-indigo-600 text-white px-4 py-2 rounded-lg text-sm hover:shadow-xl transform hover:scale-105 transition-all duration-300">
                                    Apply
                                </button>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
                
                <?php if ($stats && $stats['total_entries'] > 0): ?>
                    <!-- Key Statistics -->
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                        <div class="glass-card p-6 stat-card">
                            <div class="flex items-center justify-between mb-2">
                                <span class="text-sm text-white/70">Active Students</span>
                                <span class="text-xs px-2 py-1 bg-green-500/30 text-green-300 rounded-full">
                                    <i class="fas fa-circle text-[8px] mr-1"></i> Active
                                </span>
                            </div>
                            <div class="text-4xl font-bold text-white"><?= $stats['active_students'] ?></div>
                            <div class="text-sm text-white/50 mt-2">
                                <i class="far fa-clock mr-1"></i>
                                Last: <?= $stats['latest_entry'] ? date('M d, H:i', strtotime($stats['latest_entry'])) : 'N/A' ?>
                            </div>
                        </div>
                        
                        <div class="glass-card p-6 stat-card">
                            <div class="flex items-center justify-between mb-2">
                                <span class="text-sm text-white/70">Total Check-ins</span>
                                <span class="text-xs px-2 py-1 bg-blue-500/30 text-blue-300 rounded-full">
                                    <i class="fas fa-chart-line mr-1"></i> <?= $filter ?>
                                </span>
                            </div>
                            <div class="text-4xl font-bold text-white"><?= $stats['total_entries'] ?></div>
                            <div class="text-sm text-white/50 mt-2">
                                <i class="fas fa-calendar-alt mr-1"></i>
                                Since: <?= $stats['first_entry'] ? date('M d', strtotime($stats['first_entry'])) : 'N/A' ?>
                            </div>
                        </div>
                        
                        <div class="glass-card p-6 stat-card">
                            <div class="flex items-center justify-between mb-2">
                                <span class="text-sm text-white/70">Avg Stress Level</span>
                                <span class="text-xs px-2 py-1 rounded-full <?= $stats['avg_stress'] > 3 ? 'risk-high' : 'risk-low' ?>">
                                    <i class="fas fa-heartbeat mr-1"></i> <?= $stats['avg_stress'] > 3 ? 'High' : 'Normal' ?>
                                </span>
                            </div>
                            <div class="flex items-end">
                                <div class="text-4xl font-bold text-white"><?= $stats['avg_stress'] ?></div>
                                <span class="text-sm text-white/50 mb-1 ml-1">/5</span>
                            </div>
                            <div class="mt-3 w-full bg-white/20 rounded-full h-2.5">
                                <div class="bg-<?= $stats['avg_stress'] > 3 ? 'red' : ($stats['avg_stress'] > 2 ? 'yellow' : 'green') ?>-400 rounded-full h-2.5" 
                                     style="width: <?= ($stats['avg_stress']/5)*100 ?>%"></div>
                            </div>
                        </div>
                        
                        <div class="glass-card p-6 stat-card">
                            <div class="flex items-center justify-between mb-2">
                                <span class="text-sm text-white/70">Avg Energy Level</span>
                                <span class="text-xs px-2 py-1 rounded-full <?= $stats['avg_energy'] > 3 ? 'risk-low' : 'risk-medium' ?>">
                                    <i class="fas fa-bolt mr-1"></i> <?= $stats['avg_energy'] > 3 ? 'High' : 'Moderate' ?>
                                </span>
                            </div>
                            <div class="flex items-end">
                                <div class="text-4xl font-bold text-white"><?= $stats['avg_energy'] ?></div>
                                <span class="text-sm text-white/50 mb-1 ml-1">/5</span>
                            </div>
                            <div class="mt-3 w-full bg-white/20 rounded-full h-2.5">
                                <div class="bg-<?= $stats['avg_energy'] > 3 ? 'green' : ($stats['avg_energy'] > 2 ? 'yellow' : 'gray') ?>-400 rounded-full h-2.5" 
                                     style="width: <?= ($stats['avg_energy']/5)*100 ?>%"></div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Perfect Main Trend Chart -->
                    <div class="glass-card p-6 mb-8 floating">
                        <div class="flex items-center justify-between mb-4">
                            <h2 class="text-xl font-semibold text-white">Main Mood Trends</h2>
                            <div class="flex space-x-4">
                                <div class="flex items-center">
                                    <span class="w-3 h-3 bg-red-400 rounded-full mr-2 animate-pulse"></span>
                                    <span class="text-sm text-white/70">Stress</span>
                                </div>
                                <div class="flex items-center">
                                    <span class="w-3 h-3 bg-green-400 rounded-full mr-2 animate-pulse"></span>
                                    <span class="text-sm text-white/70">Energy</span>
                                </div>
                                <div class="flex items-center">
                                    <span class="w-3 h-3 bg-yellow-400 rounded-full mr-2 animate-pulse"></span>
                                    <span class="text-sm text-white/70">Mood Score</span>
                                </div>
                            </div>
                        </div>
                        <div class="chart-container">
                            <canvas id="moodTrendChart"></canvas>
                        </div>
                    </div>
                    
                    <!-- Charts Row -->
                    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-8">
                        <!-- Mood Distribution -->
                        <div class="glass-card p-6 fade-in">
                            <h2 class="text-xl font-semibold text-white mb-4">Mood Distribution</h2>
                            <div class="space-y-4">
                                <?php 
                                $total_mood_count = array_sum(array_column($mood_distribution, 'count'));
                                foreach ($mood_distribution as $mood): 
                                    $config = $mood_config[$mood['mood']];
                                ?>
                                    <div>
                                        <div class="flex items-center justify-between text-sm mb-1">
                                            <span class="flex items-center text-white">
                                                <span class="mr-2 text-2xl"><?= $config['icon'] ?></span>
                                                <span class="font-medium"><?= $config['label'] ?></span>
                                            </span>
                                            <span class="text-white/70"><?= $mood['count'] ?> (<?= $mood['percentage'] ?>%)</span>
                                        </div>
                                        <div class="w-full bg-white/20 rounded-full h-3">
                                            <div class="bg-gradient-to-r <?= $config['gradient'] ?> rounded-full h-3" 
                                                 style="width: <?= $mood['percentage'] ?>%"></div>
                                        </div>
                                        <div class="flex justify-between text-xs text-white/50 mt-1">
                                            <span>Stress: <?= $mood['avg_stress'] ?>/5</span>
                                            <span>Energy: <?= $mood['avg_energy'] ?>/5</span>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                                
                                <?php if (empty($mood_distribution)): ?>
                                    <div class="text-center py-6 text-white/50">
                                        <i class="far fa-smile text-4xl mb-2 opacity-50"></i>
                                        <p>No mood data available</p>
                                    </div>
                                <?php endif; ?>
                            </div>
                            
                            <!-- Quick Stats -->
                            <div class="mt-6 pt-4 border-t border-white/10">
                                <div class="grid grid-cols-2 gap-4">
                                    <div>
                                        <span class="text-xs text-white/50">At Risk Students</span>
                                        <div class="flex items-center mt-1">
                                            <span class="text-xl font-bold text-white mr-2"><?= $high_risk_students ?></span>
                                            <span class="text-xs px-2 py-1 risk-high rounded-full">
                                                High Stress
                                            </span>
                                        </div>
                                    </div>
                                    <div>
                                        <span class="text-xs text-white/50">Moderate Risk</span>
                                        <div class="flex items-center mt-1">
                                            <span class="text-xl font-bold text-white mr-2"><?= $moderate_risk_students ?></span>
                                            <span class="text-xs px-2 py-1 risk-medium rounded-full">
                                                Medium
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Trend Summary -->
                        <div class="glass-card p-6 fade-in">
                            <h2 class="text-xl font-semibold text-white mb-4">Trend Summary</h2>
                            
                            <?php if (!empty($trend_data)): ?>
                                <?php 
                                $last_entry = end($trend_data);
                                $first_entry = reset($trend_data);
                                $stress_change = $last_entry['avg_stress'] - $first_entry['avg_stress'];
                                $energy_change = $last_entry['avg_energy'] - $first_entry['avg_energy'];
                                ?>
                                
                                <div class="space-y-4">
                                    <div class="p-4 bg-white/5 rounded-lg">
                                        <p class="text-sm text-white/70 mb-2">Stress Trend</p>
                                        <div class="flex items-center justify-between">
                                            <span class="text-2xl font-bold text-white"><?= $stress_change > 0 ? '+' : '' ?><?= $stress_change ?></span>
                                            <span class="text-sm px-3 py-1 rounded-full <?= $stress_change > 0 ? 'risk-high' : 'risk-low' ?>">
                                                <i class="fas <?= $stress_change > 0 ? 'fa-arrow-up' : 'fa-arrow-down' ?> mr-1"></i>
                                                <?= abs($stress_change) ?> points
                                            </span>
                                        </div>
                                    </div>
                                    
                                    <div class="p-4 bg-white/5 rounded-lg">
                                        <p class="text-sm text-white/70 mb-2">Energy Trend</p>
                                        <div class="flex items-center justify-between">
                                            <span class="text-2xl font-bold text-white"><?= $energy_change > 0 ? '+' : '' ?><?= $energy_change ?></span>
                                            <span class="text-sm px-3 py-1 rounded-full <?= $energy_change > 0 ? 'risk-low' : 'risk-medium' ?>">
                                                <i class="fas <?= $energy_change > 0 ? 'fa-arrow-up' : 'fa-arrow-down' ?> mr-1"></i>
                                                <?= abs($energy_change) ?> points
                                            </span>
                                        </div>
                                    </div>
                                    
                                    <div class="p-4 bg-gradient-to-r from-purple-600/20 to-indigo-600/20 rounded-lg">
                                        <p class="text-sm text-white/70 mb-2">Most Active Day</p>
                                        <?php 
                                        $max_entries_day = array_reduce($trend_data, function($carry, $item) {
                                            return ($carry['entry_count'] > $item['entry_count']) ? $carry : $item;
                                        }, $trend_data[0]);
                                        ?>
                                        <p class="text-white font-semibold"><?= date('M d, Y', strtotime($max_entries_day['date'])) ?></p>
                                        <p class="text-xs text-white/50"><?= $max_entries_day['entry_count'] ?> entries</p>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <!-- Students Mood Table -->
                    <div class="glass-card p-6 fade-in">
                        <div class="flex items-center justify-between mb-4">
                            <h2 class="text-xl font-semibold text-white">Student Mood Status</h2>
                            <span class="text-sm text-white/50"><?= count($students_mood) ?> students</span>
                        </div>
                        
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-white/10">
                                <thead class="bg-white/5">
                                    <tr>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-white/70 uppercase tracking-wider">Student</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-white/70 uppercase tracking-wider">Current Mood</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-white/70 uppercase tracking-wider">Stress Level</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-white/70 uppercase tracking-wider">Weekly Avg</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-white/70 uppercase tracking-wider">Total Entries</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-white/70 uppercase tracking-wider">Last Active</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-white/70 uppercase tracking-wider">Status</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-white/10">
                                    <?php foreach ($students_mood as $student): 
                                        $current_mood = $student['current_mood'] ?? 'neutral';
                                        $mood_config_item = $mood_config[$current_mood] ?? $mood_config['neutral'];
                                        
                                        // Determine risk level
                                        $risk_level = 'low';
                                        $risk_class = 'risk-low';
                                        $risk_text = 'Low';
                                        
                                        if ($student['weekly_avg_stress'] >= 4) {
                                            $risk_level = 'high';
                                            $risk_class = 'risk-high';
                                            $risk_text = 'High';
                                        } elseif ($student['weekly_avg_stress'] >= 3) {
                                            $risk_level = 'medium';
                                            $risk_class = 'risk-medium';
                                            $risk_text = 'Medium';
                                        }
                                    ?>
                                        <tr class="table-row">
                                            <td class="px-6 py-4">
                                                <div class="flex items-center">
                                                    <div class="w-8 h-8 bg-gradient-to-r from-purple-600 to-indigo-600 rounded-full flex items-center justify-center text-white text-sm font-semibold mr-3 shadow-lg">
                                                        <?= strtoupper(substr($student['name'], 0, 1)) ?>
                                                    </div>
                                                    <div>
                                                        <div class="text-sm font-medium text-white"><?= htmlspecialchars($student['name']) ?></div>
                                                        <div class="text-xs text-white/50"><?= htmlspecialchars($student['email']) ?></div>
                                                    </div>
                                                </div>
                                            </td>
                                            <td class="px-6 py-4">
                                                <?php if ($student['current_mood']): ?>
                                                    <span class="mood-badge <?= $mood_config_item['bg'] ?> <?= $mood_config_item['text'] ?>">
                                                        <span class="mr-1 text-lg"><?= $mood_config_item['icon'] ?></span>
                                                        <?= $mood_config_item['label'] ?>
                                                    </span>
                                                <?php else: ?>
                                                    <span class="text-white/40">No data</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="px-6 py-4">
                                                <?php if ($student['current_stress']): ?>
                                                    <div class="flex items-center">
                                                        <span class="text-sm font-medium text-white mr-2"><?= $student['current_stress'] ?>/5</span>
                                                        <div class="w-12 bg-white/20 rounded-full h-1.5">
                                                            <div class="bg-<?= $student['current_stress'] > 3 ? 'red' : ($student['current_stress'] > 2 ? 'yellow' : 'green') ?>-400 rounded-full h-1.5" 
                                                                 style="width: <?= ($student['current_stress']/5)*100 ?>%"></div>
                                                        </div>
                                                    </div>
                                                <?php else: ?>
                                                    <span class="text-white/40">-</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="px-6 py-4">
                                                <?php if ($student['weekly_avg_stress']): ?>
                                                    <span class="px-2 py-1 text-xs rounded-full <?= $risk_class ?>">
                                                        <?= $student['weekly_avg_stress'] ?>/5 - <?= $risk_text ?> Risk
                                                    </span>
                                                <?php else: ?>
                                                    <span class="text-white/40">Insufficient</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="px-6 py-4 text-sm text-white">
                                                <?= $student['total_entries'] ?: 0 ?>
                                            </td>
                                            <td class="px-6 py-4 text-sm text-white/50">
                                                <?= $student['last_mood_date'] ? date('M d, H:i', strtotime($student['last_mood_date'])) : 'Never' ?>
                                            </td>
                                            <td class="px-6 py-4">
                                                <?php if ($student['status'] === 'active'): ?>
                                                    <span class="px-2 py-1 text-xs bg-green-500/20 text-green-300 rounded-full">
                                                        <i class="fas fa-circle text-[6px] mr-1"></i> Active
                                                    </span>
                                                <?php else: ?>
                                                    <span class="px-2 py-1 text-xs bg-white/10 text-white/50 rounded-full">
                                                        <?= ucfirst($student['status']) ?>
                                                    </span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        
                        <?php if (empty($students_mood)): ?>
                            <div class="text-center py-8">
                                <p class="text-white/50">No student data available</p>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                <?php else: ?>
                    <!-- No Data State -->
                    <div class="glass-card text-center py-16 fade-in floating">
                        <div class="text-9xl mb-4 animate-bounce">📊</div>
                        <h2 class="text-3xl font-semibold text-white mb-3">No Mood Data Available</h2>
                        <p class="text-white/70 mb-8 text-lg">There are no mood entries for the selected period.</p>
                        <div class="flex justify-center space-x-4">
                            <a href="?filter=week" class="px-8 py-4 bg-gradient-to-r from-purple-600 to-indigo-600 text-white rounded-xl hover:shadow-xl transform hover:scale-105 transition-all duration-300 ripple">
                                <i class="fas fa-calendar-week mr-2"></i>View Last 7 Days
                            </a>
                            <a href="students.php" class="px-8 py-4 bg-white/10 backdrop-blur-sm text-white rounded-xl hover:bg-white/20 transform hover:scale-105 transition-all duration-300 ripple">
                                <i class="fas fa-users mr-2"></i>Manage Students
                            </a>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <script>
        // Initialize chart with perfect styling
        const ctx = document.getElementById('moodTrendChart').getContext('2d');
        
        <?php if (!empty($chart_dates)): ?>
            new Chart(ctx, {
                type: 'line',
                data: {
                    labels: <?= json_encode($chart_dates) ?>,
                    datasets: [
                        {
                            label: 'Stress Level',
                            data: <?= json_encode($chart_stress) ?>,
                            borderColor: '#ef4444',
                            backgroundColor: 'rgba(239, 68, 68, 0.1)',
                            borderWidth: 4,
                            tension: 0.4,
                            fill: true,
                            pointBackgroundColor: '#ef4444',
                            pointBorderColor: '#fff',
                            pointBorderWidth: 2,
                            pointRadius: 5,
                            pointHoverRadius: 8,
                            pointHoverBackgroundColor: '#fff',
                            pointHoverBorderColor: '#ef4444',
                            pointHoverBorderWidth: 3
                        },
                        {
                            label: 'Energy Level',
                            data: <?= json_encode($chart_energy) ?>,
                            borderColor: '#10b981',
                            backgroundColor: 'rgba(16, 185, 129, 0.1)',
                            borderWidth: 4,
                            tension: 0.4,
                            fill: true,
                            pointBackgroundColor: '#10b981',
                            pointBorderColor: '#fff',
                            pointBorderWidth: 2,
                            pointRadius: 5,
                            pointHoverRadius: 8,
                            pointHoverBackgroundColor: '#fff',
                            pointHoverBorderColor: '#10b981',
                            pointHoverBorderWidth: 3
                        },
                        {
                            label: 'Mood Score',
                            data: <?= json_encode($chart_mood_scores) ?>,
                            borderColor: '#fbbf24',
                            backgroundColor: 'rgba(251, 191, 36, 0.1)',
                            borderWidth: 4,
                            tension: 0.4,
                            fill: true,
                            pointBackgroundColor: '#fbbf24',
                            pointBorderColor: '#fff',
                            pointBorderWidth: 2,
                            pointRadius: 5,
                            pointHoverRadius: 8,
                            pointHoverBackgroundColor: '#fff',
                            pointHoverBorderColor: '#fbbf24',
                            pointHoverBorderWidth: 3,
                            borderDash: [5, 5]
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: false
                        },
                        tooltip: {
                            mode: 'index',
                            intersect: false,
                            backgroundColor: 'rgba(17, 24, 39, 0.9)',
                            titleColor: '#f3f4f6',
                            bodyColor: '#d1d5db',
                            borderColor: '#374151',
                            borderWidth: 1,
                            padding: 10,
                            cornerRadius: 8,
                            displayColors: true,
                            callbacks: {
                                label: function(context) {
                                    let label = context.dataset.label || '';
                                    if (label) {
                                        label += ': ';
                                    }
                                    if (context.parsed.y !== null) {
                                        label += context.parsed.y + '/5';
                                    }
                                    return label;
                                }
                            }
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            max: 5,
                            grid: {
                                color: 'rgba(255, 255, 255, 0.1)',
                                drawBorder: false
                            },
                            ticks: {
                                stepSize: 1,
                                callback: function(value) {
                                    return value + '/5';
                                },
                                color: 'rgba(255, 255, 255, 0.7)'
                            }
                        },
                        x: {
                            grid: {
                                display: false
                            },
                            ticks: {
                                color: 'rgba(255, 255, 255, 0.7)',
                                maxRotation: 45,
                                minRotation: 45
                            }
                        }
                    },
                    elements: {
                        line: {
                            borderJoinStyle: 'round'
                        }
                    },
                    animation: {
                        duration: 2000,
                        easing: 'easeInOutQuart'
                    },
                    interaction: {
                        intersect: false,
                        mode: 'index'
                    }
                }
            });
        <?php endif; ?>
        
        // Intersection Observer for animations
        const observerOptions = {
            threshold: 0.1,
            rootMargin: '0px 0px -50px 0px'
        };
        
        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.style.opacity = '1';
                    entry.target.style.transform = 'translateY(0)';
                }
            });
        }, observerOptions);
        
        document.querySelectorAll('.glass-card').forEach(card => {
            card.style.opacity = '0';
            card.style.transform = 'translateY(20px)';
            card.style.transition = 'all 0.5s ease-out';
            observer.observe(card);
        });
        
        // Auto-refresh data every 5 minutes
        setTimeout(() => {
            location.reload();
        }, 300000);
    </script>
</body>
</html>