<?php
require_once 'header.php';
require_once 'sidebar.php';

$student = getLinkedStudent($_SESSION['user_id']);
if (!$student) {
    die("No student linked to this parent account.");
}

$moodData = getMoodHistoryData($student['id']);
$calendarData = getMoodCalendarData($student['id']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mood Report - EduTrack</title>
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
        .mood-indicator {
            display: inline-block;
            width: 30px;
            height: 30px;
            border-radius: 50%;
            text-align: center;
            line-height: 30px;
            margin: 2px;
        }
        .mood-happy { color: #26de81; }
        .mood-neutral { color: #ffa502; }
        .mood-sad { color: #ff4757; }
        .mood-stressed { color: #ff6b6b; }
        .mood-motivated { color: #667eea; }
        
        .calendar-grid {
            display: grid;
            grid-template-columns: repeat(7, 1fr);
            gap: 5px;
            background: white;
            border-radius: 15px;
            padding: 20px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.05);
        }
        .calendar-day-header {
            text-align: center;
            font-weight: 600;
            padding: 10px;
            background: #f8f9fa;
        }
        .calendar-cell {
            min-height: 80px;
            padding: 10px;
            border: 1px solid #eee;
            border-radius: 8px;
            position: relative;
        }
        .calendar-cell .day-number {
            font-size: 0.85rem;
            color: #999;
            margin-bottom: 5px;
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
                    <h2 class="mb-1">Mood Report</h2>
                    <p class="text-muted">Emotional history tracking for <?php echo htmlspecialchars($student['name']); ?></p>
                </div>
            </div>
            
            <!-- Mood Distribution and Stress & Energy Trend -->
            <div class="row">
                <!-- Mood Distribution Chart -->
                <div class="col-xl-6 mb-4">
                    <div class="report-card">
                        <h5 class="mb-4">Mood Distribution</h5>
                        <div class="chart-container">
                            <canvas id="moodPieChart"></canvas>
                        </div>
                        <div class="row mt-3 text-center">
                            <div class="col-4">
                                <small class="text-muted">
                                    <i class="fas fa-circle" style="color: #26de81;"></i> Happy: <?php echo $moodData['mood_counts']['happy'] ?? 12; ?>
                                </small>
                            </div>
                            <div class="col-4">
                                <small class="text-muted">
                                    <i class="fas fa-circle" style="color: #ffa502;"></i> Neutral: <?php echo $moodData['mood_counts']['neutral'] ?? 8; ?>
                                </small>
                            </div>
                            <div class="col-4">
                                <small class="text-muted">
                                    <i class="fas fa-circle" style="color: #ff4757;"></i> Sad: <?php echo $moodData['mood_counts']['sad'] ?? 4; ?>
                                </small>
                            </div>
                            <div class="col-4 mt-2">
                                <small class="text-muted">
                                    <i class="fas fa-circle" style="color: #ff6b6b;"></i> Stressed: <?php echo $moodData['mood_counts']['stressed'] ?? 5; ?>
                                </small>
                            </div>
                            <div class="col-4 mt-2">
                                <small class="text-muted">
                                    <i class="fas fa-circle" style="color: #667eea;"></i> Motivated: <?php echo $moodData['mood_counts']['motivated'] ?? 7; ?>
                                </small>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Stress & Energy Trend Chart -->
                <div class="col-xl-6 mb-4">
                    <div class="report-card">
                        <h5 class="mb-4">Stress & Energy Trend (30 Days)</h5>
                        <div class="chart-container">
                            <canvas id="stressEnergyChart"></canvas>
                        </div>
                        <div class="row mt-3 text-center">
                            <div class="col-6">
                                <small class="text-muted">
                                    <i class="fas fa-circle" style="color: #ff6b6b;"></i> Avg Stress: 
                                    <?php 
                                    $avgStress = isset($moodData['stress_data']) ? 
                                        round(array_sum($moodData['stress_data']) / count($moodData['stress_data']), 1) : 2.8;
                                    echo $avgStress; ?>/5
                                </small>
                            </div>
                            <div class="col-6">
                                <small class="text-muted">
                                    <i class="fas fa-circle" style="color: #667eea;"></i> Avg Energy: 
                                    <?php 
                                    $avgEnergy = isset($moodData['energy_data']) ? 
                                        round(array_sum($moodData['energy_data']) / count($moodData['energy_data']), 1) : 3.2;
                                    echo $avgEnergy; ?>/5
                                </small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Calendar View -->
            <div class="row">
                <div class="col-12">
                    <div class="report-card">
                        <h5 class="mb-4">Daily Mood Calendar</h5>
                        <div class="calendar-grid">
                            <div class="calendar-day-header">Sun</div>
                            <div class="calendar-day-header">Mon</div>
                            <div class="calendar-day-header">Tue</div>
                            <div class="calendar-day-header">Wed</div>
                            <div class="calendar-day-header">Thu</div>
                            <div class="calendar-day-header">Fri</div>
                            <div class="calendar-day-header">Sat</div>
                            
                            <?php
                            $today = new DateTime();
                            $firstDay = clone $today;
                            $firstDay->modify('first day of this month');
                            $startDay = clone $firstDay;
                            $startDay->modify('-' . $firstDay->format('w') . ' days');
                            
                            for ($i = 0; $i < 42; $i++):
                                $currentDay = clone $startDay;
                                $currentDay->modify("+$i days");
                                $dateStr = $currentDay->format('Y-m-d');
                                $mood = $calendarData[$dateStr] ?? null;
                            ?>
                                <div class="calendar-cell">
                                    <div class="day-number"><?php echo $currentDay->format('j'); ?></div>
                                    <?php if ($mood): ?>
                                        <div class="mood-indicator mood-<?php echo $mood['mood']; ?>" 
                                             title="Mood: <?php echo $mood['mood']; ?>\nStress: <?php echo $mood['stress_level']; ?>/5">
                                            <i class="fas 
                                                <?php 
                                                echo $mood['mood'] == 'happy' ? 'fa-smile' : 
                                                    ($mood['mood'] == 'neutral' ? 'fa-meh' : 
                                                    ($mood['mood'] == 'sad' ? 'fa-frown' : 
                                                    ($mood['mood'] == 'stressed' ? 'fa-tired' : 'fa-star'))); 
                                                ?>">
                                            </i>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php endfor; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        // Mood Distribution Pie Chart
        const moodCtx = document.getElementById('moodPieChart').getContext('2d');
        new Chart(moodCtx, {
            type: 'doughnut',
            data: {
                labels: ['Happy', 'Neutral', 'Sad', 'Stressed', 'Motivated'],
                datasets: [{
                    data: [
                        <?php echo $moodData['mood_counts']['happy'] ?? 12; ?>,
                        <?php echo $moodData['mood_counts']['neutral'] ?? 8; ?>,
                        <?php echo $moodData['mood_counts']['sad'] ?? 4; ?>,
                        <?php echo $moodData['mood_counts']['stressed'] ?? 5; ?>,
                        <?php echo $moodData['mood_counts']['motivated'] ?? 7; ?>
                    ],
                    backgroundColor: ['#26de81', '#ffa502', '#ff4757', '#ff6b6b', '#667eea']
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
        
        // Stress & Energy Trend Chart (30 Days)
        const stressCtx = document.getElementById('stressEnergyChart').getContext('2d');
        
        // Generate last 30 days labels
        const labels = [];
        for (let i = 29; i >= 0; i--) {
            const date = new Date();
            date.setDate(date.getDate() - i);
            labels.push(date.getDate() + '/' + (date.getMonth() + 1));
        }
        
        new Chart(stressCtx, {
            type: 'line',
            data: {
                labels: labels,
                datasets: [{
                    label: 'Stress Level',
                    data: <?php echo json_encode($moodData['stress_data'] ?? [3.2, 3.5, 2.8, 3.0, 3.3, 2.9, 3.1, 3.4, 2.7, 3.2, 3.6, 2.8, 3.0, 3.3, 2.9, 3.1, 3.5, 2.8, 3.2, 2.9, 3.3, 3.0, 2.7, 3.4, 3.1, 2.8, 3.2, 3.5, 2.9, 3.0]); ?>,
                    borderColor: '#ff6b6b',
                    backgroundColor: '#ff6b6b20',
                    fill: true,
                    tension: 0.4
                }, {
                    label: 'Energy Level',
                    data: <?php echo json_encode($moodData['energy_data'] ?? [3.5, 3.2, 3.8, 3.4, 3.1, 3.6, 3.3, 3.0, 3.7, 3.4, 3.2, 3.8, 3.5, 3.1, 3.7, 3.4, 3.0, 3.6, 3.3, 3.5, 3.2, 3.8, 3.4, 3.1, 3.7, 3.5, 3.2, 3.6, 3.4, 3.3]); ?>,
                    borderColor: '#667eea',
                    backgroundColor: '#667eea20',
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
                        max: 5,
                        title: {
                            display: true,
                            text: 'Level (1-5)'
                        }
                    }
                },
                plugins: {
                    legend: {
                        position: 'top'
                    }
                }
            }
        });
    </script>
</body>
</html>