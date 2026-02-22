<?php
// file: student/mood-history.php
require_once '../includes/auth.php';
require_once '../includes/student-functions.php';
require_once '../includes/ai-mood-engine.php';

// Ensure user is logged in and is a student
if (!isLoggedIn() || $_SESSION['role'] !== 'student') {
    header('Location: login.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$filter = $_GET['filter'] ?? '7'; // Default to last 7 days
$focus = $_GET['focus'] ?? ''; // For special focus modes

// Validate filter
$valid_filters = ['7', '30', 'all'];
if (!in_array($filter, $valid_filters)) {
    $filter = '7';
}

// Build query based on filter
$date_condition = "";
if ($filter === '7') {
    $date_condition = "AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
} elseif ($filter === '30') {
    $date_condition = "AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
}

// Get mood entries with AI analysis
$stmt = $pdo->prepare("
    SELECT 
        id,
        mood,
        stress_level,
        energy_level,
        notes,
        ai_analysis,
        ai_suggestions,
        created_at
    FROM mood_entries 
    WHERE user_id = ? 
    $date_condition
    ORDER BY created_at DESC
");
$stmt->execute([$user_id]);
$mood_entries = $stmt->fetchAll();

// Calculate statistics
$total_entries = count($mood_entries);
$avg_stress = 0;
$avg_energy = 0;
$mood_counts = [
    'happy' => 0,
    'neutral' => 0,
    'sad' => 0,
    'stressed' => 0,
    'motivated' => 0
];

$stress_trend = [];
$energy_trend = [];
$dates = [];
$mood_trend = [];

foreach ($mood_entries as $entry) {
    $avg_stress += $entry['stress_level'];
    $avg_energy += $entry['energy_level'];
    $mood_counts[$entry['mood']] = ($mood_counts[$entry['mood']] ?? 0) + 1;
    
    // For trend data (reverse for chronological order)
    array_unshift($stress_trend, $entry['stress_level']);
    array_unshift($energy_trend, $entry['energy_level']);
    array_unshift($dates, date('M d', strtotime($entry['created_at'])));
    
    // Convert mood to numeric for chart
    $mood_value = 0;
    switch($entry['mood']) {
        case 'sad': $mood_value = 1; break;
        case 'stressed': $mood_value = 2; break;
        case 'neutral': $mood_value = 3; break;
        case 'happy': $mood_value = 4; break;
        case 'motivated': $mood_value = 5; break;
        default: $mood_value = 3;
    }
    array_unshift($mood_trend, $mood_value);
}

if ($total_entries > 0) {
    $avg_stress = round($avg_stress / $total_entries, 1);
    $avg_energy = round($avg_energy / $total_entries, 1);
}

// Find most common mood
$most_common_mood = array_search(max($mood_counts), $mood_counts) ?: 'neutral';
$most_common_mood_label = ucfirst($most_common_mood);

// Calculate trend (compare first half vs second half)
$trend_direction = 'stable';
$trend_percentage = 0;

if ($total_entries >= 4) {
    $half = floor($total_entries / 2);
    $first_half = array_slice($stress_trend, 0, $half);
    $second_half = array_slice($stress_trend, $half);
    
    if (count($first_half) > 0 && count($second_half) > 0) {
        $first_avg = array_sum($first_half) / count($first_half);
        $second_avg = array_sum($second_half) / count($second_half);
        
        $trend_percentage = round((($second_avg - $first_avg) / $first_avg) * 100, 1);
        
        if ($trend_percentage > 5) {
            $trend_direction = 'increasing';
        } elseif ($trend_percentage < -5) {
            $trend_direction = 'decreasing';
        }
    }
}

// Mood colors and icons
$mood_config = [
    'happy' => [
        'color' => 'yellow',
        'bg' => 'bg-yellow-100',
        'text' => 'text-yellow-800',
        'icon' => '😊',
        'label' => 'Happy',
        'gradient' => 'from-yellow-400 to-yellow-500',
        'chart_color' => '#FBBF24'
    ],
    'neutral' => [
        'color' => 'gray',
        'bg' => 'bg-gray-100',
        'text' => 'text-gray-800',
        'icon' => '😐',
        'label' => 'Neutral',
        'gradient' => 'from-gray-400 to-gray-500',
        'chart_color' => '#9CA3AF'
    ],
    'sad' => [
        'color' => 'blue',
        'bg' => 'bg-blue-100',
        'text' => 'text-blue-800',
        'icon' => '😔',
        'label' => 'Sad',
        'gradient' => 'from-blue-400 to-blue-500',
        'chart_color' => '#60A5FA'
    ],
    'stressed' => [
        'color' => 'red',
        'bg' => 'bg-red-100',
        'text' => 'text-red-800',
        'icon' => '😰',
        'label' => 'Stressed',
        'gradient' => 'from-red-400 to-red-500',
        'chart_color' => '#F87171'
    ],
    'motivated' => [
        'color' => 'green',
        'bg' => 'bg-green-100',
        'text' => 'text-green-800',
        'icon' => '🔥',
        'label' => 'Motivated',
        'gradient' => 'from-green-400 to-green-500',
        'chart_color' => '#34D399'
    ]
];

// Get wellness tips based on mood patterns
$wellness_tips = [];
if ($total_entries > 0) {
    if ($most_common_mood === 'stressed' || $most_common_mood === 'sad') {
        $wellness_tips = [
            "Take short breaks between study sessions",
            "Practice deep breathing for 5 minutes",
            "Reach out to friends or family",
            "Consider speaking with a counselor"
        ];
    } elseif ($avg_stress > 3.5) {
        $wellness_tips = [
            "Try meditation or mindfulness exercises",
            "Get some fresh air and sunlight",
            "Stay hydrated and eat well",
            "Get at least 7-8 hours of sleep"
        ];
    } elseif ($avg_energy < 2.5) {
        $wellness_tips = [
            "Light exercise can boost energy",
            "Take power naps (15-20 minutes)",
            "Eat energy-boosting foods",
            "Stay hydrated throughout the day"
        ];
    } else {
        $wellness_tips = [
            "Keep up the great work!",
            "Share your positive energy with others",
            "Maintain your healthy habits",
            "Set new goals to stay motivated"
        ];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Your Mood Insights - Wellness Analytics Dashboard</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-annotation"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap');
        
        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
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
            background: rgba(255, 255, 255, 0.1);
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
            background: rgba(255, 255, 255, 0.25);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.18);
            border-radius: 1rem;
            transition: all 0.3s ease;
        }
        
        .glass-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 20px 30px -10px rgba(0, 0, 0, 0.3);
            background: rgba(255, 255, 255, 0.3);
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
            background: rgba(255, 255, 255, 0.25);
            backdrop-filter: blur(5px);
            color: white;
            border: 1px solid rgba(255, 255, 255, 0.3);
        }
        
        .filter-btn:not(.active):hover {
            background: rgba(255, 255, 255, 0.35);
            transform: translateY(-2px);
        }
        
        .filter-btn::before {
            content: '';
            position: absolute;
            top: 50%;
            left: 50%;
            width: 0;
            height: 0;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.3);
            transform: translate(-50%, -50%);
            transition: width 0.6s, height 0.6s;
        }
        
        .filter-btn:hover::before {
            width: 300px;
            height: 300px;
        }
        
        .chart-container {
            position: relative;
            height: 300px;
            width: 100%;
        }
        
        .trend-up {
            color: #ef4444;
            background: rgba(239, 68, 68, 0.2);
            backdrop-filter: blur(5px);
        }
        
        .trend-down {
            color: #10b981;
            background: rgba(16, 185, 129, 0.2);
            backdrop-filter: blur(5px);
        }
        
        .trend-stable {
            color: #6b7280;
            background: rgba(107, 114, 128, 0.2);
            backdrop-filter: blur(5px);
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
        
        .mood-badge:hover {
            transform: scale(1.05);
        }
        
        .insight-box {
            background: linear-gradient(135deg, rgba(243, 232, 255, 0.3), rgba(224, 242, 254, 0.3));
            backdrop-filter: blur(10px);
            border-left: 4px solid #667eea;
            border-radius: 0.5rem;
            padding: 1rem;
            animation: pulse 2s infinite;
        }
        
        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.9; }
        }
        
        .table-row {
            transition: all 0.3s ease;
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(5px);
        }
        
        .table-row:hover {
            background: rgba(255, 255, 255, 0.2);
            transform: scale(1.01);
        }
        
        .floating {
            animation: float 6s ease-in-out infinite;
        }
        
        @keyframes float {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-10px); }
        }
        
        .progress-ring {
            transition: stroke-dashoffset 0.35s;
            transform: rotate(-90deg);
            transform-origin: 50% 50%;
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
            <?php include 'header.php'; ?>
            
            <!-- Mood Insights Content -->
            <div class="p-8">
                <!-- Page Title with Filter and Animation -->
                <div class="flex flex-col md:flex-row md:items-center md:justify-between mb-8 floating">
                    <div>
                        <h1 class="text-4xl font-bold text-white flex items-center">
                            <i class="fas fa-chart-line text-purple-300 mr-3 animate-pulse"></i>
                            Your Mood Insights
                        </h1>
                        <p class="text-white/80 mt-2 text-lg">Track your emotional wellness journey over time</p>
                    </div>
                    
                    <!-- Filter Buttons with Animation -->
                    <div class="flex space-x-2 mt-4 md:mt-0">
                        <a href="?filter=7" class="filter-btn <?= $filter === '7' ? 'active' : '' ?> ripple">
                            <i class="fas fa-calendar-week mr-2"></i>Last 7 Days
                        </a>
                        <a href="?filter=30" class="filter-btn <?= $filter === '30' ? 'active' : '' ?> ripple">
                            <i class="fas fa-calendar-alt mr-2"></i>Last 30 Days
                        </a>
                        <a href="?filter=all" class="filter-btn <?= $filter === 'all' ? 'active' : '' ?> ripple">
                            <i class="fas fa-infinity mr-2"></i>All Time
                        </a>
                    </div>
                </div>
                
                <?php if ($total_entries === 0): ?>
                    <!-- No Data State with Animation -->
                    <div class="glass-card text-center py-16 fade-in floating">
                        <div class="text-9xl mb-4 animate-bounce">📊</div>
                        <h2 class="text-3xl font-semibold text-white mb-3">No Mood Data Yet</h2>
                        <p class="text-white/80 mb-8 text-lg">Start tracking your mood to see insights and patterns</p>
                        <a href="mood.php" class="inline-flex items-center px-8 py-4 bg-gradient-to-r from-purple-600 to-indigo-600 text-white rounded-xl hover:shadow-xl transform hover:scale-105 transition-all duration-300 ripple">
                            <i class="fas fa-smile mr-2"></i>
                            Check In Now
                        </a>
                    </div>
                <?php else: ?>
                    <!-- Stats Cards with Animation -->
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                        <!-- Total Check-ins -->
                        <div class="glass-card p-6 stat-card">
                            <div class="flex items-center justify-between">
                                <div>
                                    <p class="text-sm text-white/70">Total Check-ins</p>
                                    <p class="text-4xl font-bold text-white"><?= $total_entries ?></p>
                                </div>
                                <div class="w-14 h-14 bg-purple-500/30 rounded-full flex items-center justify-center backdrop-blur-sm">
                                    <i class="fas fa-calendar-check text-purple-300 text-2xl"></i>
                                </div>
                            </div>
                            <div class="mt-3 w-full bg-white/20 rounded-full h-2">
                                <div class="bg-purple-400 rounded-full h-2" style="width: <?= min(100, $total_entries * 10) ?>%"></div>
                            </div>
                        </div>
                        
                        <!-- Average Stress -->
                        <div class="glass-card p-6 stat-card">
                            <div class="flex items-center justify-between">
                                <div>
                                    <p class="text-sm text-white/70">Average Stress</p>
                                    <p class="text-4xl font-bold text-white"><?= $avg_stress ?><span class="text-lg text-white/50">/5</span></p>
                                </div>
                                <div class="w-14 h-14 <?= $avg_stress > 3 ? 'bg-red-500/30' : 'bg-green-500/30' ?> rounded-full flex items-center justify-center backdrop-blur-sm">
                                    <i class="fas fa-heartbeat <?= $avg_stress > 3 ? 'text-red-300' : 'text-green-300' ?> text-2xl"></i>
                                </div>
                            </div>
                            <div class="mt-3 w-full bg-white/20 rounded-full h-2">
                                <div class="bg-<?= $avg_stress > 3 ? 'red' : ($avg_stress > 2 ? 'yellow' : 'green') ?>-400 rounded-full h-2" style="width: <?= ($avg_stress/5)*100 ?>%"></div>
                            </div>
                            <p class="text-xs <?= $avg_stress > 3 ? 'text-red-300' : 'text-green-300' ?> mt-2">
                                <i class="fas <?= $avg_stress > 3 ? 'fa-exclamation-triangle' : 'fa-check-circle' ?> mr-1"></i>
                                <?= $avg_stress > 3 ? 'Above recommended level' : 'Within healthy range' ?>
                            </p>
                        </div>
                        
                        <!-- Average Energy -->
                        <div class="glass-card p-6 stat-card">
                            <div class="flex items-center justify-between">
                                <div>
                                    <p class="text-sm text-white/70">Average Energy</p>
                                    <p class="text-4xl font-bold text-white"><?= $avg_energy ?><span class="text-lg text-white/50">/5</span></p>
                                </div>
                                <div class="w-14 h-14 <?= $avg_energy > 3 ? 'bg-green-500/30' : 'bg-yellow-500/30' ?> rounded-full flex items-center justify-center backdrop-blur-sm">
                                    <i class="fas fa-bolt <?= $avg_energy > 3 ? 'text-green-300' : 'text-yellow-300' ?> text-2xl"></i>
                                </div>
                            </div>
                            <div class="mt-3 w-full bg-white/20 rounded-full h-2">
                                <div class="bg-<?= $avg_energy > 3 ? 'green' : ($avg_energy > 2 ? 'yellow' : 'gray') ?>-400 rounded-full h-2" style="width: <?= ($avg_energy/5)*100 ?>%"></div>
                            </div>
                            <p class="text-xs text-white/70 mt-2">
                                <?php if ($avg_energy > 3): ?>
                                    Great energy levels!
                                <?php elseif ($avg_energy > 2): ?>
                                    Moderate energy levels
                                <?php else: ?>
                                    Could use an energy boost
                                <?php endif; ?>
                            </p>
                        </div>
                        
                        <!-- Most Common Mood -->
                        <div class="glass-card p-6 stat-card">
                            <div class="flex items-center justify-between">
                                <div>
                                    <p class="text-sm text-white/70">Most Common</p>
                                    <p class="text-3xl font-bold text-white"><?= $most_common_mood_label ?></p>
                                </div>
                                <div class="w-14 h-14 bg-indigo-500/30 rounded-full flex items-center justify-center text-3xl backdrop-blur-sm">
                                    <?= $mood_config[$most_common_mood]['icon'] ?>
                                </div>
                            </div>
                            <p class="text-xs text-white/70 mt-2">
                                <?= $mood_counts[$most_common_mood] ?> out of <?= $total_entries ?> entries
                            </p>
                        </div>
                    </div>
                    
                    <!-- Perfect Line Chart -->
                    <div class="glass-card p-6 mb-8 floating">
                        <div class="flex items-center justify-between mb-4">
                            <h2 class="text-xl font-semibold text-white">Mood Trends Over Time</h2>
                            <div class="flex space-x-4">
                                <div class="flex items-center">
                                    <span class="w-3 h-3 bg-purple-400 rounded-full mr-2"></span>
                                    <span class="text-sm text-white/70">Stress</span>
                                </div>
                                <div class="flex items-center">
                                    <span class="w-3 h-3 bg-green-400 rounded-full mr-2"></span>
                                    <span class="text-sm text-white/70">Energy</span>
                                </div>
                                <div class="flex items-center">
                                    <span class="w-3 h-3 bg-yellow-400 rounded-full mr-2"></span>
                                    <span class="text-sm text-white/70">Mood</span>
                                </div>
                            </div>
                        </div>
                        <div class="chart-container">
                            <canvas id="moodChart"></canvas>
                        </div>
                    </div>
                    
                    <!-- Trend Graph and Insights -->
                    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-8">
                        <!-- Mood Distribution -->
                        <div class="glass-card p-6 fade-in">
                            <h2 class="text-xl font-semibold text-white mb-4">Mood Distribution</h2>
                            <div class="space-y-4">
                                <?php foreach ($mood_counts as $mood => $count): ?>
                                    <?php if ($count > 0): ?>
                                        <div>
                                            <div class="flex items-center justify-between text-sm mb-1">
                                                <span class="flex items-center text-white">
                                                    <span class="mr-2 text-2xl"><?= $mood_config[$mood]['icon'] ?></span>
                                                    <?= $mood_config[$mood]['label'] ?>
                                                </span>
                                                <span class="text-white/70"><?= $count ?> (<?= round(($count/$total_entries)*100) ?>%)</span>
                                            </div>
                                            <div class="w-full bg-white/20 rounded-full h-3">
                                                <div class="bg-gradient-to-r <?= $mood_config[$mood]['gradient'] ?> rounded-full h-3" style="width: <?= ($count/$total_entries)*100 ?>%"></div>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        
                        <!-- Trend Indicator and Quick Tips -->
                        <div class="glass-card p-6 fade-in">
                            <h2 class="text-xl font-semibold text-white mb-4">Trend Analysis</h2>
                            
                            <!-- Stress Trend -->
                            <div class="mb-6">
                                <div class="flex items-center justify-between mb-2">
                                    <span class="text-sm text-white/70">Stress Trend</span>
                                    <div class="flex items-center">
                                        <?php if ($trend_direction === 'increasing'): ?>
                                            <span class="trend-up text-xs px-3 py-1 rounded-full">
                                                <i class="fas fa-arrow-up mr-1"></i><?= abs($trend_percentage) ?>% increase
                                            </span>
                                        <?php elseif ($trend_direction === 'decreasing'): ?>
                                            <span class="trend-down text-xs px-3 py-1 rounded-full">
                                                <i class="fas fa-arrow-down mr-1"></i><?= abs($trend_percentage) ?>% decrease
                                            </span>
                                        <?php else: ?>
                                            <span class="trend-stable text-xs px-3 py-1 rounded-full">
                                                <i class="fas fa-minus mr-1"></i>Stable
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div class="relative pt-1">
                                    <div class="flex mb-2 items-center justify-between">
                                        <div>
                                            <span class="text-xs font-semibold inline-block py-1 px-3 uppercase rounded-full <?= $avg_stress > 3 ? 'bg-red-500/30 text-red-300' : 'bg-green-500/30 text-green-300' ?>">
                                                <?= $trend_direction === 'decreasing' ? 'Improving' : ($trend_direction === 'increasing' ? 'Needs Attention' : 'Stable') ?>
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Wellness Tips with Animation -->
                            <?php if (!empty($wellness_tips)): ?>
                                <div class="insight-box mt-4">
                                    <h4 class="font-semibold text-white mb-2 flex items-center">
                                        <i class="fas fa-lightbulb text-yellow-300 mr-2 animate-pulse"></i>
                                        Wellness Tips
                                    </h4>
                                    <ul class="text-sm text-white/80 space-y-2">
                                        <?php foreach (array_slice($wellness_tips, 0, 3) as $tip): ?>
                                            <li class="flex items-start">
                                                <i class="fas fa-check-circle text-green-400 mr-2 mt-0.5 text-xs"></i>
                                                <span><?= $tip ?></span>
                                            </li>
                                        <?php endforeach; ?>
                                    </ul>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <!-- Mood Entries Table -->
                    <div class="glass-card p-6 fade-in">
                        <div class="flex items-center justify-between mb-4">
                            <h2 class="text-xl font-semibold text-white">Mood History</h2>
                            <span class="text-sm text-white/70"><?= $total_entries ?> entries found</span>
                        </div>
                        
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-white/10">
                                <thead class="bg-white/5">
                                    <tr>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-white/70 uppercase tracking-wider">Date & Time</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-white/70 uppercase tracking-wider">Mood</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-white/70 uppercase tracking-wider">Stress</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-white/70 uppercase tracking-wider">Energy</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-white/70 uppercase tracking-wider">AI Analysis</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-white/70 uppercase tracking-wider">Notes</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-white/10">
                                    <?php foreach ($mood_entries as $entry): 
                                        $mood = $entry['mood'];
                                        $config = $mood_config[$mood];
                                        
                                        // Decode AI analysis if it exists
                                        $ai_analysis = $entry['ai_analysis'] ?? '';
                                        $ai_suggestions = $entry['ai_suggestions'] ?? '';
                                        
                                        // Truncate analysis for display
                                        $analysis_preview = strlen($ai_analysis) > 50 ? substr($ai_analysis, 0, 50) . '...' : $ai_analysis;
                                    ?>
                                        <tr class="table-row">
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-white">
                                                <?= date('M d, Y g:i A', strtotime($entry['created_at'])) ?>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <span class="mood-badge <?= $config['bg'] ?> <?= $config['text'] ?>">
                                                    <span class="mr-1 text-lg"><?= $config['icon'] ?></span>
                                                    <?= $config['label'] ?>
                                                </span>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <div class="flex items-center">
                                                    <span class="text-sm font-medium text-white mr-2"><?= $entry['stress_level'] ?>/5</span>
                                                    <div class="w-16 bg-white/20 rounded-full h-2">
                                                        <div class="bg-<?= $entry['stress_level'] > 3 ? 'red' : ($entry['stress_level'] > 2 ? 'yellow' : 'green') ?>-400 rounded-full h-2" style="width: <?= ($entry['stress_level']/5)*100 ?>%"></div>
                                                    </div>
                                                </div>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <div class="flex items-center">
                                                    <span class="text-sm font-medium text-white mr-2"><?= $entry['energy_level'] ?>/5</span>
                                                    <div class="w-16 bg-white/20 rounded-full h-2">
                                                        <div class="bg-<?= $entry['energy_level'] > 3 ? 'green' : ($entry['energy_level'] > 2 ? 'yellow' : 'gray') ?>-400 rounded-full h-2" style="width: <?= ($entry['energy_level']/5)*100 ?>%"></div>
                                                    </div>
                                                </div>
                                            </td>
                                            <td class="px-6 py-4">
                                                <?php if (!empty($ai_analysis)): ?>
                                                    <div class="group relative">
                                                        <p class="text-sm text-white/80 cursor-help"><?= htmlspecialchars($analysis_preview) ?></p>
                                                        <?php if (strlen($ai_analysis) > 50): ?>
                                                            <div class="absolute bottom-full left-0 mb-2 w-64 p-3 bg-gray-900/95 backdrop-blur-sm text-white text-xs rounded-lg opacity-0 group-hover:opacity-100 transition pointer-events-none z-10">
                                                                <?= htmlspecialchars($ai_analysis) ?>
                                                                <?php if (!empty($ai_suggestions)): ?>
                                                                    <hr class="my-1 border-white/20">
                                                                    <span class="text-green-400">💡 <?= htmlspecialchars($ai_suggestions) ?></span>
                                                                <?php endif; ?>
                                                            </div>
                                                        <?php endif; ?>
                                                    </div>
                                                <?php else: ?>
                                                    <span class="text-sm text-white/40">-</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="px-6 py-4">
                                                <?php if (!empty($entry['notes'])): ?>
                                                    <div class="group relative">
                                                        <p class="text-sm text-white/80 cursor-help max-w-xs truncate"><?= htmlspecialchars($entry['notes']) ?></p>
                                                        <?php if (strlen($entry['notes']) > 30): ?>
                                                            <div class="absolute bottom-full left-0 mb-2 w-64 p-3 bg-gray-900/95 backdrop-blur-sm text-white text-xs rounded-lg opacity-0 group-hover:opacity-100 transition pointer-events-none z-10">
                                                                <?= htmlspecialchars($entry['notes']) ?>
                                                            </div>
                                                        <?php endif; ?>
                                                    </div>
                                                <?php else: ?>
                                                    <span class="text-sm text-white/40">-</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        
                        <?php if ($total_entries === 0): ?>
                            <div class="text-center py-8">
                                <p class="text-white/70">No mood entries found for the selected period.</p>
                                <a href="mood.php" class="inline-flex items-center mt-4 px-6 py-3 bg-gradient-to-r from-purple-600 to-indigo-600 text-white rounded-xl hover:shadow-xl transform hover:scale-105 transition-all duration-300 ripple">
                                    <i class="fas fa-plus mr-2"></i>Add Your First Entry
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Export/Additional Actions -->
                    <?php if ($total_entries > 0): ?>
                        <div class="mt-6 flex justify-end space-x-3">
                            
                            <a href="mood.php" class="px-6 py-3 bg-gradient-to-r from-purple-600 to-indigo-600 text-white rounded-xl hover:shadow-xl transform hover:scale-105 transition-all duration-300 flex items-center ripple">
                                <i class="fas fa-smile mr-2"></i>New Check-in
                            </a>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <script>
        // Initialize chart with perfect styling
        const ctx = document.getElementById('moodChart').getContext('2d');
        
        <?php if ($total_entries > 0): ?>
            new Chart(ctx, {
                type: 'line',
                data: {
                    labels: <?= json_encode($dates) ?>,
                    datasets: [
                        {
                            label: 'Stress Level',
                            data: <?= json_encode($stress_trend) ?>,
                            borderColor: '#8b5cf6',
                            backgroundColor: 'rgba(139, 92, 246, 0.1)',
                            borderWidth: 4,
                            tension: 0.4,
                            fill: true,
                            pointBackgroundColor: '#8b5cf6',
                            pointBorderColor: '#fff',
                            pointBorderWidth: 2,
                            pointRadius: 5,
                            pointHoverRadius: 8,
                            pointHoverBackgroundColor: '#fff',
                            pointHoverBorderColor: '#8b5cf6',
                            pointHoverBorderWidth: 3,
                            borderDash: [],
                            spanGaps: true
                        },
                        {
                            label: 'Energy Level',
                            data: <?= json_encode($energy_trend) ?>,
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
                            data: <?= json_encode($mood_trend) ?>,
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
        <?php else: ?>
            // Show empty state message on chart
            ctx.font = '14px Inter';
            ctx.fillStyle = '#9ca3af';
            ctx.textAlign = 'center';
            ctx.fillText('No data to display', ctx.canvas.width/2, ctx.canvas.height/2);
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
        
        // Handle focus mode (for support)
        <?php if ($focus === 'support'): ?>
            setTimeout(() => {
                document.querySelector('.insight-box')?.scrollIntoView({ behavior: 'smooth' });
            }, 500);
        <?php endif; ?>
        
        // Add ripple animation style
        const style = document.createElement('style');
        style.textContent = `
            @keyframes ripple {
                to {
                    transform: scale(4);
                    opacity: 0;
                }
            }
        `;
        document.head.appendChild(style);
    </script>
</body>
</html>