<?php
require_once 'header.php';
require_once 'sidebar.php';

$student = getLinkedStudent($_SESSION['user_id']);
if (!$student) {
    die("No student linked to this parent account.");
}

$behaviorData = getBehaviorData($student['id']);
$insights = getBehaviorInsights($student['id']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Behavior Report - EduTrack</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        .report-card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.05);
            margin-bottom: 30px;
            height: 100%;
        }
        .insight-box {
            background: linear-gradient(135deg, #f6f9fc 0%, #e9f2f9 100%);
            border-radius: 15px;
            padding: 20px;
            margin-top: 20px;
            border-left: 4px solid #667eea;
        }
        .metric-value {
            font-size: 2rem;
            font-weight: 600;
            color: #333;
            line-height: 1.2;
        }
        .metric-label {
            color: #666;
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .trend-up { color: #26de81; }
        .trend-down { color: #ff4757; }
        .isolation-indicator {
            padding: 20px;
            border-radius: 12px;
            background: #fff3cd;
            color: #856404;
            text-align: center;
        }
        .chart-container {
            position: relative;
            height: 250px;
            width: 100%;
        }
    </style>
</head>
<body>
    <div class="main-content">
        <div class="container-fluid">
            <!-- Page Header -->
            <div class="row mb-4">
                <div class="col-12">
                    <h2 class="mb-1">Behavior Report</h2>
                    <p class="text-muted">Detailed lifestyle analytics for <?php echo htmlspecialchars($student['name']); ?></p>
                </div>
            </div>
            
            <!-- Screen Time vs Study Hours Chart and Weekly Averages -->
            <div class="row">
                <!-- Screen Time vs Study Hours Chart -->
                <div class="col-xl-8 mb-4">
                    <div class="report-card">
                        <h5 class="mb-4">Screen Time vs Study Hours</h5>
                        <div class="chart-container">
                            <canvas id="screenStudyChart"></canvas>
                        </div>
                        <div class="row mt-3 text-center">
                            <div class="col-6">
                                <small class="text-muted">
                                    <i class="fas fa-circle" style="color: #ffa502;"></i> Total Screen: 
                                    <?php echo array_sum($behaviorData['screen_data'] ?? [4.5, 5.2, 4.8, 5.5, 6.1, 5.8, 4.2]); ?>h
                                </small>
                            </div>
                            <div class="col-6">
                                <small class="text-muted">
                                    <i class="fas fa-circle" style="color: #667eea;"></i> Total Study: 
                                    <?php echo array_sum($behaviorData['study_data'] ?? [3.2, 2.8, 3.5, 2.5, 3.8, 2.0, 4.0]); ?>h
                                </small>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Weekly Averages -->
                <div class="col-xl-4">
                    <div class="report-card">
                        <h5 class="mb-4">Weekly Averages</h5>
                        <div class="text-center mb-4">
                            <div class="metric-value"><?php echo $behaviorData['avg_screen'] ?? 4.5; ?>h</div>
                            <div class="metric-label">Daily Screen Time</div>
                            <small class="text-muted">vs <?php echo $behaviorData['avg_screen_last'] ?? 3.8; ?>h last week</small>
                            <?php if (($behaviorData['avg_screen'] ?? 0) > ($behaviorData['avg_screen_last'] ?? 0)): ?>
                                <div class="trend-up"><i class="fas fa-arrow-up"></i> +<?php echo round(($behaviorData['avg_screen'] - $behaviorData['avg_screen_last']), 1); ?>h</div>
                            <?php else: ?>
                                <div class="trend-down"><i class="fas fa-arrow-down"></i> <?php echo round(($behaviorData['avg_screen'] - $behaviorData['avg_screen_last']), 1); ?>h</div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="text-center">
                            <div class="metric-value"><?php echo $behaviorData['avg_study'] ?? 3.2; ?>h</div>
                            <div class="metric-label">Daily Study Time</div>
                            <small class="text-muted">target: 4h</small>
                            <div class="progress mt-2" style="height: 8px;">
                                <div class="progress-bar" style="width: <?php echo (($behaviorData['avg_study'] ?? 3.2) / 4) * 100; ?>%; background: #667eea;"></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Sleep Analysis & Social Interaction -->
            <div class="row">
                <div class="col-xl-6 mb-4">
                    <div class="report-card">
                        <h5 class="mb-4">Sleep Analysis</h5>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="text-center mb-3">
                                    <div class="metric-value"><?php echo $behaviorData['avg_sleep'] ?? 7.5; ?>h</div>
                                    <div class="metric-label">Average Sleep</div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="text-center mb-3">
                                    <div class="metric-value"><?php echo $behaviorData['sleep_quality'] ?? 75; ?>%</div>
                                    <div class="metric-label">Sleep Quality</div>
                                </div>
                            </div>
                        </div>
                        <div class="chart-container" style="height: 200px;">
                            <canvas id="sleepChart"></canvas>
                        </div>
                        <div class="mt-3">
                            <?php if (($behaviorData['avg_sleep'] ?? 7.5) < 8): ?>
                                <div class="alert alert-warning">
                                    <i class="fas fa-exclamation-triangle"></i> Sleep below recommended 8-10 hours for optimal development.
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <div class="col-xl-6 mb-4">
                    <div class="report-card">
                        <h5 class="mb-4">Social & Lifestyle Summary</h5>
                        
                        <?php 
                        $outdoorDays = $behaviorData['outdoor_days'] ?? 3;
                        $socialDays = $behaviorData['social_days'] ?? 4;
                        $isolationRisk = $outdoorDays < 2 || $socialDays < 3;
                        ?>
                        
                        <?php if ($isolationRisk): ?>
                            <div class="isolation-indicator mb-4">
                                <i class="fas fa-exclamation-triangle fa-2x mb-2"></i>
                                <h6>Isolation Risk Detected</h6>
                                <p class="mb-0">Limited outdoor and social activities this week.</p>
                            </div>
                        <?php endif; ?>
                        
                        <div class="row text-center">
                            <div class="col-6">
                                <div class="metric-value"><?php echo $outdoorDays; ?>/7</div>
                                <div class="metric-label">Outdoor Days</div>
                            </div>
                            <div class="col-6">
                                <div class="metric-value"><?php echo $socialDays; ?>/7</div>
                                <div class="metric-label">Social Days</div>
                            </div>
                        </div>
                        
                        <div class="chart-container" style="height: 150px; margin-top: 20px;">
                            <canvas id="socialChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- AI Behavior Insight -->
            <div class="row">
                <div class="col-12">
                    <div class="insight-box">
                        <div class="d-flex align-items-start">
                            <i class="fas fa-robot fa-2x me-3" style="color: #667eea;"></i>
                            <div>
                                <h5 class="mb-2">AI Behavior Insight</h5>
                                <p class="mb-0"><?php echo $insights['behavior_insight'] ?? 'High screen usage correlated with reduced sleep and mood decline. Consider setting screen time limits and encouraging outdoor activities.'; ?></p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        // Screen vs Study Chart
        const screenCtx = document.getElementById('screenStudyChart').getContext('2d');
        new Chart(screenCtx, {
            type: 'bar',
            data: {
                labels: <?php echo json_encode($behaviorData['week_days'] ?? ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun']); ?>,
                datasets: [{
                    label: 'Screen Time (hours)',
                    data: <?php echo json_encode($behaviorData['screen_data'] ?? [4.5, 5.2, 4.8, 5.5, 6.1, 5.8, 4.2]); ?>,
                    backgroundColor: '#ffa502',
                    borderRadius: 5
                }, {
                    label: 'Study Hours',
                    data: <?php echo json_encode($behaviorData['study_data'] ?? [3.2, 2.8, 3.5, 2.5, 3.8, 2.0, 4.0]); ?>,
                    backgroundColor: '#667eea',
                    borderRadius: 5
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true,
                        title: {
                            display: true,
                            text: 'Hours'
                        }
                    }
                }
            }
        });
        
        // Sleep Chart
        const sleepCtx = document.getElementById('sleepChart').getContext('2d');
        new Chart(sleepCtx, {
            type: 'line',
            data: {
                labels: <?php echo json_encode($behaviorData['week_days'] ?? ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun']); ?>,
                datasets: [{
                    label: 'Sleep Hours',
                    data: <?php echo json_encode($behaviorData['sleep_data'] ?? [7.5, 7.2, 8.1, 6.8, 7.3, 8.5, 8.0]); ?>,
                    borderColor: '#26de81',
                    backgroundColor: '#26de8120',
                    fill: true,
                    tension: 0.4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true,
                        max: 12
                    }
                }
            }
        });
        
        // Social Chart
        const socialCtx = document.getElementById('socialChart').getContext('2d');
        new Chart(socialCtx, {
            type: 'doughnut',
            data: {
                labels: ['Outdoor Days', 'Social Days', 'Other Days'],
                datasets: [{
                    data: [
                        <?php echo $outdoorDays; ?>,
                        <?php echo $socialDays; ?>,
                        <?php echo 7 - max($outdoorDays, $socialDays); ?>
                    ],
                    backgroundColor: ['#26de81', '#667eea', '#f1f2f6']
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                }
            }
        });
    </script>
</body>
</html>