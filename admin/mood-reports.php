<?php
/**
 * Admin Mood Reports Dashboard
 */

require_once '../includes/auth.php';
require_once '../includes/ai-mood-engine.php';

if (!isLoggedIn() || $_SESSION['role'] !== 'admin') {
    header('Location: login.php');
    exit();
}

// Get filter parameters
$filter = $_GET['filter'] ?? 'week';
$date_from = $_GET['date_from'] ?? date('Y-m-d', strtotime('-30 days'));
$date_to = $_GET['date_to'] ?? date('Y-m-d');

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

// Get overall statistics
$stats_query = "
    SELECT 
        COUNT(DISTINCT me.user_id) as active_students,
        COUNT(me.id) as total_entries,
        ROUND(AVG(me.stress_level), 1) as avg_stress,
        ROUND(AVG(me.energy_level), 1) as avg_energy,
        ROUND(AVG(me.sleep_hours), 1) as avg_sleep,
        ROUND(AVG(me.study_hours), 1) as avg_study,
        SUM(CASE WHEN me.risk_level = 'high' THEN 1 ELSE 0 END) as high_risk_count,
        SUM(CASE WHEN me.risk_level = 'medium' THEN 1 ELSE 0 END) as medium_risk_count,
        MAX(me.created_at) as latest_entry
    FROM mood_entries me
    WHERE 1=1
    $date_condition
";

$stats_stmt = $pdo->prepare($stats_query);
if ($filter === 'custom') {
    $stats_stmt->bindParam(':date_from', $date_from);
    $stats_stmt->bindParam(':date_to', $date_to);
}
$stats_stmt->execute();
$stats = $stats_stmt->fetch();

// Get mood distribution
$dist_query = "
    SELECT 
        me.mood,
        COUNT(*) as count,
        ROUND(COUNT(*) * 100.0 / (SELECT COUNT(*) FROM mood_entries me2 WHERE 1=1 $date_condition), 1) as percentage
    FROM mood_entries me
    WHERE 1=1
    $date_condition
    GROUP BY me.mood
    ORDER BY count DESC
";

$dist_stmt = $pdo->prepare($dist_query);
if ($filter === 'custom') {
    $dist_stmt->bindParam(':date_from', $date_from);
    $dist_stmt->bindParam(':date_to', $date_to);
}
$dist_stmt->execute();
$mood_distribution = $dist_stmt->fetchAll();

// Get trend data
$trend_query = "
    SELECT 
        DATE(me.created_at) as date,
        ROUND(AVG(me.stress_level), 1) as avg_stress,
        ROUND(AVG(me.energy_level), 1) as avg_energy,
        COUNT(*) as entry_count
    FROM mood_entries me
    WHERE 1=1
    $date_condition
    GROUP BY DATE(me.created_at)
    ORDER BY date ASC
    LIMIT 30
";

$trend_stmt = $pdo->prepare($trend_query);
if ($filter === 'custom') {
    $trend_stmt->bindParam(':date_from', $date_from);
    $trend_stmt->bindParam(':date_to', $date_to);
}
$trend_stmt->execute();
$trend_data = $trend_stmt->fetchAll();

// Get at-risk students
$risk_query = "
    SELECT 
        u.id,
        u.name,
        u.email,
        COUNT(me.id) as total_entries,
        MAX(me.created_at) as last_entry,
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
            SELECT me2.risk_level 
            FROM mood_entries me2 
            WHERE me2.user_id = u.id 
            ORDER BY me2.created_at DESC 
            LIMIT 1
        ) as current_risk,
        ROUND(AVG(me.stress_level), 1) as avg_stress,
        ROUND(AVG(me.sleep_hours), 1) as avg_sleep
    FROM users u
    LEFT JOIN mood_entries me ON u.id = me.user_id
    WHERE u.role = 'student'
    GROUP BY u.id
    HAVING avg_stress >= 3.5 OR current_risk = 'high'
    ORDER BY avg_stress DESC
    LIMIT 20
";

$risk_stmt = $pdo->query($risk_query);
$at_risk_students = $risk_stmt->fetchAll();

// Get alerts
$alerts_query = "
    SELECT 
        ma.*,
        u.name as student_name
    FROM mood_alerts ma
    JOIN users u ON ma.user_id = u.id
    WHERE ma.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
    ORDER BY ma.created_at DESC
";

$alerts = $pdo->query($alerts_query)->fetchAll();

// Prepare chart data
$chart_dates = [];
$chart_stress = [];
$chart_energy = [];
foreach ($trend_data as $row) {
    $chart_dates[] = date('M d', strtotime($row['date']));
    $chart_stress[] = $row['avg_stress'];
    $chart_energy[] = $row['avg_energy'];
}
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
        body {
            background: #f3f4f6;
        }
        .stat-card {
            transition: all 0.3s ease;
        }
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px -5px rgba(0,0,0,0.1);
        }
        .risk-high {
            background: #fee2e2;
            color: #dc2626;
        }
        .risk-medium {
            background: #fef3c7;
            color: #d97706;
        }
        .risk-low {
            background: #d1fae5;
            color: #059669;
        }
    </style>
</head>
<body class="min-h-screen">
    <div class="flex h-screen">
        <!-- Sidebar -->
        <?php include 'sidebar.php'; ?>
        
        <!-- Main Content -->
        <div class="flex-1 overflow-auto">
            <!-- Header -->
            <div class="bg-white shadow-sm p-4 flex justify-between items-center">
                <h1 class="text-2xl font-bold text-gray-800 flex items-center">
                    <i class="fas fa-chart-pie text-blue-600 mr-3"></i>
                    Mood Analytics Dashboard
                </h1>
                <div class="flex items-center space-x-4">
                    <span class="text-gray-600">
                        <i class="far fa-calendar mr-2"></i>
                        <?= date('F j, Y') ?>
                    </span>
                </div>
            </div>
            
            <!-- Filters -->
            <div class="p-6 bg-white border-b">
                <div class="flex flex-wrap items-center justify-between">
                    <div class="flex space-x-2">
                        <a href="?filter=day" class="px-4 py-2 rounded-lg <?= $filter === 'day' ? 'bg-blue-600 text-white' : 'bg-gray-200 text-gray-700' ?> hover:bg-blue-500 hover:text-white transition">
                            Today
                        </a>
                        <a href="?filter=week" class="px-4 py-2 rounded-lg <?= $filter === 'week' ? 'bg-blue-600 text-white' : 'bg-gray-200 text-gray-700' ?> hover:bg-blue-500 hover:text-white transition">
                            Last 7 Days
                        </a>
                        <a href="?filter=month" class="px-4 py-2 rounded-lg <?= $filter === 'month' ? 'bg-blue-600 text-white' : 'bg-gray-200 text-gray-700' ?> hover:bg-blue-500 hover:text-white transition">
                            Last 30 Days
                        </a>
                        <a href="?filter=custom" class="px-4 py-2 rounded-lg <?= $filter === 'custom' ? 'bg-blue-600 text-white' : 'bg-gray-200 text-gray-700' ?> hover:bg-blue-500 hover:text-white transition">
                            Custom Range
                        </a>
                    </div>
                    
                    <?php if ($filter === 'custom'): ?>
                    <form method="GET" class="flex space-x-2">
                        <input type="hidden" name="filter" value="custom">
                        <input type="date" name="date_from" value="<?= $date_from ?>" class="px-3 py-2 border rounded-lg">
                        <input type="date" name="date_to" value="<?= $date_to ?>" class="px-3 py-2 border rounded-lg">
                        <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">
                            Apply
                        </button>
                    </form>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Main Content Area -->
            <div class="p-6">
                <!-- KPI Cards -->
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                    <div class="bg-white rounded-xl shadow-lg p-6 stat-card">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm text-gray-500">Active Students</p>
                                <p class="text-3xl font-bold text-gray-800"><?= $stats['active_students'] ?? 0 ?></p>
                            </div>
                            <div class="w-12 h-12 bg-blue-100 rounded-full flex items-center justify-center">
                                <i class="fas fa-users text-blue-600 text-xl"></i>
                            </div>
                        </div>
                        <p class="text-sm text-gray-500 mt-2">
                            Total entries: <?= $stats['total_entries'] ?? 0 ?>
                        </p>
                    </div>
                    
                    <div class="bg-white rounded-xl shadow-lg p-6 stat-card">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm text-gray-500">Avg Stress Level</p>
                                <p class="text-3xl font-bold text-gray-800"><?= $stats['avg_stress'] ?? 0 ?>/5</p>
                            </div>
                            <div class="w-12 h-12 <?= ($stats['avg_stress'] ?? 0) > 3 ? 'bg-red-100' : 'bg-green-100' ?> rounded-full flex items-center justify-center">
                                <i class="fas fa-heartbeat <?= ($stats['avg_stress'] ?? 0) > 3 ? 'text-red-600' : 'text-green-600' ?> text-xl"></i>
                            </div>
                        </div>
                        <div class="mt-2 w-full bg-gray-200 rounded-full h-2">
                            <div class="bg-<?= ($stats['avg_stress'] ?? 0) > 3 ? 'red' : 'green' ?>-500 rounded-full h-2" style="width: <?= (($stats['avg_stress'] ?? 0)/5)*100 ?>%"></div>
                        </div>
                    </div>
                    
                    <div class="bg-white rounded-xl shadow-lg p-6 stat-card">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm text-gray-500">Avg Sleep</p>
                                <p class="text-3xl font-bold text-gray-800"><?= $stats['avg_sleep'] ?? 0 ?>h</p>
                            </div>
                            <div class="w-12 h-12 bg-purple-100 rounded-full flex items-center justify-center">
                                <i class="fas fa-bed text-purple-600 text-xl"></i>
                            </div>
                        </div>
                        <p class="text-sm <?= ($stats['avg_sleep'] ?? 0) < 7 ? 'text-red-500' : 'text-green-500' ?> mt-2">
                            <?= ($stats['avg_sleep'] ?? 0) < 7 ? 'Below recommended' : 'Healthy range' ?>
                        </p>
                    </div>
                    
                    <div class="bg-white rounded-xl shadow-lg p-6 stat-card">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm text-gray-500">High Risk Students</p>
                                <p class="text-3xl font-bold text-gray-800"><?= $stats['high_risk_count'] ?? 0 ?></p>
                            </div>
                            <div class="w-12 h-12 bg-red-100 rounded-full flex items-center justify-center">
                                <i class="fas fa-exclamation-triangle text-red-600 text-xl"></i>
                            </div>
                        </div>
                        <p class="text-sm text-gray-500 mt-2">
                            Medium: <?= $stats['medium_risk_count'] ?? 0 ?>
                        </p>
                    </div>
                </div>
                
                <!-- Charts Row -->
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-8">
                    <!-- Mood Distribution -->
                    <div class="bg-white rounded-xl shadow-lg p-6 lg:col-span-1">
                        <h2 class="text-xl font-semibold text-gray-800 mb-4">Mood Distribution</h2>
                        <div class="space-y-4">
                            <?php
                            $mood_icons = [
                                'happy' => '😊',
                                'neutral' => '😐',
                                'sad' => '😔',
                                'stressed' => '😰',
                                'motivated' => '🔥'
                            ];
                            $mood_colors = [
                                'happy' => 'bg-yellow-500',
                                'neutral' => 'bg-gray-500',
                                'sad' => 'bg-blue-500',
                                'stressed' => 'bg-red-500',
                                'motivated' => 'bg-green-500'
                            ];
                            
                            foreach ($mood_distribution as $mood):
                            ?>
                                <div>
                                    <div class="flex items-center justify-between text-sm mb-1">
                                        <span class="flex items-center">
                                            <span class="text-2xl mr-2"><?= $mood_icons[$mood['mood']] ?? '😊' ?></span>
                                            <span class="font-medium text-gray-700"><?= ucfirst($mood['mood']) ?></span>
                                        </span>
                                        <span class="text-gray-600"><?= $mood['count'] ?> (<?= $mood['percentage'] ?>%)</span>
                                    </div>
                                    <div class="w-full bg-gray-200 rounded-full h-3">
                                        <div class="<?= $mood_colors[$mood['mood']] ?? 'bg-gray-500' ?> rounded-full h-3" style="width: <?= $mood['percentage'] ?>%"></div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    
                    <!-- Trend Chart -->
                    <div class="bg-white rounded-xl shadow-lg p-6 lg:col-span-2">
                        <h2 class="text-xl font-semibold text-gray-800 mb-4">Stress & Energy Trends</h2>
                        <div class="h-64">
                            <canvas id="trendChart"></canvas>
                        </div>
                    </div>
                </div>
                
                <!-- At Risk Students Table -->
                <div class="bg-white rounded-xl shadow-lg p-6 mb-8">
                    <h2 class="text-xl font-semibold text-gray-800 mb-4 flex items-center">
                        <i class="fas fa-exclamation-triangle text-red-500 mr-2"></i>
                        At-Risk Students
                    </h2>
                    
                    <div class="overflow-x-auto">
                        <table class="min-w-full">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Student</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Current Mood</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Stress</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Avg Sleep</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Risk Level</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Last Entry</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200">
                                <?php foreach ($at_risk_students as $student): ?>
                                <tr class="hover:bg-gray-50">
                                    <td class="px-6 py-4">
                                        <div class="font-medium text-gray-900"><?= htmlspecialchars($student['name']) ?></div>
                                        <div class="text-sm text-gray-500"><?= htmlspecialchars($student['email']) ?></div>
                                    </td>
                                    <td class="px-6 py-4">
                                        <?php if ($student['current_mood']): ?>
                                            <span class="px-3 py-1 rounded-full text-sm
                                                <?= $student['current_mood'] == 'happy' ? 'bg-yellow-100 text-yellow-800' : '' ?>
                                                <?= $student['current_mood'] == 'neutral' ? 'bg-gray-100 text-gray-800' : '' ?>
                                                <?= $student['current_mood'] == 'sad' ? 'bg-blue-100 text-blue-800' : '' ?>
                                                <?= $student['current_mood'] == 'stressed' ? 'bg-red-100 text-red-800' : '' ?>
                                                <?= $student['current_mood'] == 'motivated' ? 'bg-green-100 text-green-800' : '' ?>
                                            ">
                                                <?= $mood_icons[$student['current_mood']] ?? '😊' ?>
                                                <?= ucfirst($student['current_mood']) ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="text-gray-400">No data</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-6 py-4">
                                        <?= $student['current_stress'] ?>/5
                                    </td>
                                    <td class="px-6 py-4">
                                        <?= $student['avg_sleep'] ?>h
                                    </td>
                                    <td class="px-6 py-4">
                                        <?php if ($student['current_risk'] == 'high'): ?>
                                            <span class="px-3 py-1 rounded-full text-sm risk-high">High</span>
                                        <?php elseif ($student['current_risk'] == 'medium'): ?>
                                            <span class="px-3 py-1 rounded-full text-sm risk-medium">Medium</span>
                                        <?php else: ?>
                                            <span class="px-3 py-1 rounded-full text-sm risk-low">Low</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-6 py-4 text-sm text-gray-500">
                                        <?= $student['last_entry'] ? date('M d, H:i', strtotime($student['last_entry'])) : 'Never' ?>
                                    </td>
                                    <td class="px-6 py-4">
                                        <a href="student-details.php?id=<?= $student['id'] ?>" class="text-blue-600 hover:text-blue-800">
                                            View Details
                                        </a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                                
                                <?php if (empty($at_risk_students)): ?>
                                <tr>
                                    <td colspan="7" class="px-6 py-8 text-center text-gray-500">
                                        No at-risk students found
                                    </td>
                                </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                
                <!-- Recent Alerts -->
                <?php if (!empty($alerts)): ?>
                <div class="bg-white rounded-xl shadow-lg p-6">
                    <h2 class="text-xl font-semibold text-gray-800 mb-4 flex items-center">
                        <i class="fas fa-bell text-yellow-500 mr-2"></i>
                        Recent Alerts (Last 7 Days)
                    </h2>
                    
                    <div class="space-y-3">
                        <?php foreach ($alerts as $alert): ?>
                        <div class="p-4 bg-<?= $alert['alert_type'] == 'high_risk' ? 'red' : 'yellow' ?>-50 rounded-lg border-l-4 border-<?= $alert['alert_type'] == 'high_risk' ? 'red' : 'yellow' ?>-500">
                            <div class="flex items-start">
                                <i class="fas fa-<?= $alert['alert_type'] == 'high_risk' ? 'exclamation-circle text-red-500' : 'clock text-yellow-500' ?> mr-3 mt-1"></i>
                                <div class="flex-1">
                                    <p class="font-semibold text-gray-800"><?= htmlspecialchars($alert['student_name']) ?></p>
                                    <p class="text-gray-600 text-sm"><?= htmlspecialchars($alert['message']) ?></p>
                                    <p class="text-xs text-gray-500 mt-1"><?= date('M d, Y H:i', strtotime($alert['created_at'])) ?></p>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <script>
        <?php if (!empty($chart_dates)): ?>
        const ctx = document.getElementById('trendChart').getContext('2d');
        
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
                        tension: 0.4,
                        fill: true
                    },
                    {
                        label: 'Energy Level',
                        data: <?= json_encode($chart_energy) ?>,
                        borderColor: '#10b981',
                        backgroundColor: 'rgba(16, 185, 129, 0.1)',
                        tension: 0.4,
                        fill: true
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true,
                        max: 5,
                        title: {
                            display: true,
                            text: 'Level (1-5)'
                        }
                    }
                }
            }
        });
        <?php endif; ?>
    </script>
</body>
</html> border border-white/20 rounded-lg px-4 py-2 text-sm text-white focus:outline-none focus:ring-2 focus:ring-purple-600">
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