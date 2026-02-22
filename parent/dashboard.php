<?php
require_once 'header.php';
require_once 'sidebar.php';

$student = getLinkedStudent($_SESSION['user_id']);
if (!$student) {
    // If no student linked, show error
    die("No student linked to this parent account. Please contact administrator.");
}

$latestReport = getLatestStudentReport($student['id']);
$riskAlert = getLatestRiskAlert($student['id']);
$weeklyStats = getWeeklyStats($student['id']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Parent Dashboard - EduTrack</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        :root {
            --primary: #4361ee;
            --secondary: #3f37c9;
            --success: #4cc9f0;
            --danger: #f72585;
            --warning: #f8961e;
            --info: #4895ef;
            --dark: #1b2635;
            --light: #f8f9fa;
        }

        body {
            background: #f4f7fc;
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
        }

        .main-content {
            background: #f4f7fc;
            min-height: 100vh;
        }

        /* Welcome Section */
        .welcome-section {
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            border-radius: 24px;
            padding: 32px;
            margin-bottom: 32px;
            color: white;
            box-shadow: 0 20px 40px rgba(67, 97, 238, 0.15);
        }

        .welcome-section h2 {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 8px;
        }

        .welcome-section p {
            opacity: 0.9;
            font-size: 1rem;
        }

        .student-badge {
            background: rgba(255, 255, 255, 0.2);
            backdrop-filter: blur(10px);
            padding: 12px 24px;
            border-radius: 50px;
            display: inline-flex;
            align-items: center;
            gap: 12px;
        }

        /* Stats Cards */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 24px;
            margin-bottom: 32px;
        }

        .stat-card {
            background: white;
            border-radius: 20px;
            padding: 24px;
            box-shadow: 0 8px 30px rgba(0, 0, 0, 0.05);
            transition: all 0.3s ease;
            border: 1px solid rgba(0, 0, 0, 0.05);
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 40px rgba(67, 97, 238, 0.1);
        }

        .stat-icon-wrapper {
            width: 56px;
            height: 56px;
            border-radius: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 20px;
            font-size: 24px;
        }

        .stat-icon-wrapper.mood { background: rgba(76, 201, 240, 0.1); color: var(--success); }
        .stat-icon-wrapper.risk { background: rgba(247, 37, 133, 0.1); color: var(--danger); }
        .stat-icon-wrapper.sleep { background: rgba(72, 149, 239, 0.1); color: var(--info); }
        .stat-icon-wrapper.screen { background: rgba(248, 150, 30, 0.1); color: var(--warning); }

        .stat-value {
            font-size: 2rem;
            font-weight: 700;
            color: var(--dark);
            margin-bottom: 4px;
        }

        .stat-label {
            color: #6c757d;
            font-size: 0.9rem;
            margin-bottom: 8px;
        }

        /* Alert Banner */
        .alert-banner {
            background: linear-gradient(135deg, #f72585, #b5179e);
            border-radius: 20px;
            padding: 28px;
            margin-bottom: 32px;
            color: white;
            box-shadow: 0 20px 40px rgba(247, 37, 133, 0.2);
            animation: slideIn 0.5s ease;
        }

        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .alert-banner.low {
            background: linear-gradient(135deg, #4cc9f0, #4895ef);
        }

        .alert-banner.medium {
            background: linear-gradient(135deg, #f8961e, #f3722c);
        }

        .alert-icon {
            width: 64px;
            height: 64px;
            background: rgba(255, 255, 255, 0.2);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 28px;
        }

        .alert-content h4 {
            font-size: 1.5rem;
            font-weight: 600;
            margin-bottom: 8px;
        }

        .alert-actions {
            display: flex;
            gap: 12px;
        }

        .alert-actions .btn {
            background: rgba(255, 255, 255, 0.2);
            border: none;
            color: white;
            padding: 10px 24px;
            border-radius: 50px;
            font-weight: 500;
            backdrop-filter: blur(10px);
            transition: all 0.3s;
        }

        .alert-actions .btn:hover {
            background: rgba(255, 255, 255, 0.3);
            transform: translateY(-2px);
        }

        /* Wellness Score Card */
        .wellness-card {
            background: white;
            border-radius: 20px;
            padding: 28px;
            margin-bottom: 32px;
            box-shadow: 0 8px 30px rgba(0, 0, 0, 0.05);
        }

        .wellness-score-circle {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2.5rem;
            font-weight: 700;
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            color: white;
            box-shadow: 0 10px 30px rgba(67, 97, 238, 0.3);
        }

        /* Chart Container */
        .chart-container {
            background: white;
            border-radius: 20px;
            padding: 24px;
            box-shadow: 0 8px 30px rgba(0, 0, 0, 0.05);
            height: 400px;
        }

        /* Risk Badge */
        .risk-badge {
            padding: 6px 16px;
            border-radius: 50px;
            font-size: 0.85rem;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        .risk-high { background: rgba(247, 37, 133, 0.1); color: #f72585; }
        .risk-medium { background: rgba(248, 150, 30, 0.1); color: #f8961e; }
        .risk-low { background: rgba(76, 201, 240, 0.1); color: #4cc9f0; }

        @media (max-width: 768px) {
            .welcome-section {
                padding: 24px;
            }
            
            .welcome-section h2 {
                font-size: 1.5rem;
            }
            
            .alert-actions {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>
    <div class="main-content">
        <div class="container-fluid px-4">
            <!-- Welcome Section -->
            <div class="welcome-section">
                <div class="row align-items-center">
                    <div class="col-lg-8">
                        <div class="student-badge mb-3">
                            <i class="fas fa-child"></i>
                            <span>Monitoring: <?php echo htmlspecialchars($student['name']); ?></span>
                        </div>
                        <h2>Welcome back, <?php echo htmlspecialchars($_SESSION['user_name']); ?>! 👋</h2>
                        <p class="mb-0">Here's your child's wellness overview for today</p>
                    </div>
                    <div class="col-lg-4 text-lg-end mt-4 mt-lg-0">
                        <img src="../assets/images/dashboard-illustration.png" alt="Dashboard" style="max-width: 200px; opacity: 0.9;">
                    </div>
                </div>
            </div>
            
            <!-- Risk Alert Banner -->
            <?php if ($riskAlert): ?>
            <div class="alert-banner <?php echo strtolower($riskAlert['risk_level']); ?>">
                <div class="row align-items-center">
                    <div class="col-lg-8">
                        <div class="d-flex align-items-center gap-4">
                            <div class="alert-icon">
                                <i class="fas fa-exclamation-triangle"></i>
                            </div>
                            <div class="alert-content">
                                <h4>Risk Alert: <?php echo $riskAlert['risk_level']; ?> Level</h4>
                                <p class="mb-0"><?php echo htmlspecialchars($riskAlert['ai_summary']); ?></p>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-4 text-lg-end mt-4 mt-lg-0">
                        <div class="alert-actions">
                            <a href="alerts.php?id=<?php echo $riskAlert['id']; ?>" class="btn">
                                <i class="fas fa-eye me-2"></i>View Details
                            </a>
                            <a href="book-consultation.php" class="btn">
                                <i class="fas fa-calendar-plus me-2"></i>Book Consultation
                            </a>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- Quick Stats Cards -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon-wrapper mood">
                        <i class="fas fa-smile"></i>
                    </div>
                    <div class="stat-value"><?php echo $latestReport ? ucfirst($latestReport['mood']) : 'N/A'; ?></div>
                    <div class="stat-label">Latest Mood</div>
                    <?php if ($latestReport): ?>
                        <div class="d-flex align-items-center text-muted">
                            <i class="fas fa-calendar-alt me-2"></i>
                            <small><?php echo date('M d, Y', strtotime($latestReport['report_date'])); ?></small>
                        </div>
                    <?php endif; ?>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon-wrapper risk">
                        <i class="fas fa-exclamation-circle"></i>
                    </div>
                    <div class="stat-value"><?php echo $riskAlert ? $riskAlert['risk_level'] : 'Low'; ?></div>
                    <div class="stat-label">Current Risk Level</div>
                    <?php if ($riskAlert): ?>
                        <span class="risk-badge risk-<?php echo strtolower($riskAlert['risk_level']); ?>">
                            <i class="fas fa-flag"></i>
                            <?php echo $riskAlert['risk_level']; ?>
                        </span>
                    <?php endif; ?>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon-wrapper sleep">
                        <i class="fas fa-moon"></i>
                    </div>
                    <div class="stat-value"><?php echo $weeklyStats['avg_sleep'] ?? 0; ?>h</div>
                    <div class="stat-label">Avg Sleep (7 days)</div>
                    <small class="text-muted">Recommended: 8-10h</small>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon-wrapper screen">
                        <i class="fas fa-mobile-alt"></i>
                    </div>
                    <div class="stat-value"><?php echo $weeklyStats['avg_screen'] ?? 0; ?>h</div>
                    <div class="stat-label">Avg Screen Time</div>
                    <small class="text-muted">Daily average</small>
                </div>
            </div>
            
            <!-- Wellness Score Card -->
            <div class="wellness-card">
                <div class="row align-items-center">
                    <div class="col-lg-6">
                        <h5 class="mb-2">Wellness Score</h5>
                        <p class="text-muted mb-0">Overall well-being indicator based on multiple factors</p>
                    </div>
                    <div class="col-lg-6">
                        <div class="d-flex align-items-center justify-content-lg-end gap-4">
                            <div class="wellness-score-circle">
                                <?php echo $weeklyStats['wellness_score'] ?? 0; ?>
                            </div>
                            <div style="flex: 1; max-width: 200px;">
                                <div class="d-flex justify-content-between mb-2">
                                    <span>Wellness Level</span>
                                    <span class="fw-bold">
                                        <?php 
                                        $score = $weeklyStats['wellness_score'] ?? 0;
                                        if ($score >= 70) echo 'Good';
                                        elseif ($score >= 40) echo 'Fair';
                                        else echo 'Needs Attention';
                                        ?>
                                    </span>
                                </div>
                                <div class="progress" style="height: 8px;">
                                    <div class="progress-bar" style="width: <?php echo $score; ?>%; background: <?php echo $score > 70 ? '#4cc9f0' : ($score > 40 ? '#f8961e' : '#f72585'); ?>"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Mood Chart -->
            <div class="row">
                <div class="col-12">
                    <div class="chart-container">
                        <div class="d-flex justify-content-between align-items-center mb-4">
                            <h5 class="mb-0">Weekly Mood & Stress Trends</h5>
                            <div class="d-flex gap-3">
                                <span><span style="color: #f72585;">●</span> Stress Level</span>
                                <span><span style="color: #4cc9f0;">●</span> Energy Level</span>
                            </div>
                        </div>
                        <canvas id="moodChart" style="height: calc(100% - 60px);"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        // Mood Chart
        const ctx = document.getElementById('moodChart').getContext('2d');
        new Chart(ctx, {
            type: 'line',
            data: {
                labels: <?php echo json_encode($weeklyStats['chart_labels'] ?? ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun']); ?>,
                datasets: [{
                    label: 'Stress Level',
                    data: <?php echo json_encode($weeklyStats['stress_data'] ?? [3, 4, 4, 5, 3, 2, 2]); ?>,
                    borderColor: '#f72585',
                    backgroundColor: 'rgba(247, 37, 133, 0.1)',
                    borderWidth: 3,
                    pointBackgroundColor: '#f72585',
                    pointBorderColor: 'white',
                    pointBorderWidth: 2,
                    pointRadius: 5,
                    pointHoverRadius: 7,
                    fill: true,
                    tension: 0.4
                }, {
                    label: 'Energy Level',
                    data: <?php echo json_encode($weeklyStats['energy_data'] ?? [3, 2, 3, 2, 4, 4, 5]); ?>,
                    borderColor: '#4cc9f0',
                    backgroundColor: 'rgba(76, 201, 240, 0.1)',
                    borderWidth: 3,
                    pointBackgroundColor: '#4cc9f0',
                    pointBorderColor: 'white',
                    pointBorderWidth: 2,
                    pointRadius: 5,
                    pointHoverRadius: 7,
                    fill: true,
                    tension: 0.4
                }]
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
                        backgroundColor: 'white',
                        titleColor: '#1b2635',
                        bodyColor: '#6c757d',
                        borderColor: 'rgba(0,0,0,0.1)',
                        borderWidth: 1
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        max: 5,
                        grid: {
                            color: 'rgba(0,0,0,0.05)'
                        }
                    },
                    x: {
                        grid: {
                            display: false
                        }
                    }
                }
            }
        });
    </script>
</body>
</html>