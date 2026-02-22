<?php
session_start();
require_once '../config/db.php';
require_once '../includes/functions.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: login.php');
    exit();
}

// Get risk statistics
$risk_stats = getRiskStatistics();

// Get unresolved risk alerts
$risk_alerts = getRiskAlerts(false);

// Get high risk students from mood entries
$high_risk_students = getHighRiskStudents();

// Handle resolve alert
if (isset($_POST['resolve_alert'])) {
    $alert_id = $_POST['alert_id'];
    resolveRiskAlert($alert_id, $_SESSION['user_id']);
    header('Location: risk-monitor.php?msg=alert_resolved');
    exit();
}

// Handle create consultation from risk
if (isset($_POST['create_consultation'])) {
    $student_id = $_POST['student_id'];
    $risk_level = $_POST['risk_level'];
    
    // Create consultation request
    $stmt = $pdo->prepare("
        INSERT INTO consultation_requests (student_id, issue_type, description, risk_level, status)
        VALUES (?, 'emotional', ?, ?, 'pending')
    ");
    
    $description = "Auto-generated from risk monitoring system. Risk level: $risk_level. Student showing signs of distress in mood entries.";
    $stmt->execute([$student_id, $description, $risk_level]);
    
    header('Location: risk-monitor.php?msg=consultation_created');
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Risk Monitor | Admin Dashboard</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            color: #2d3748;
        }

        .container {
            max-width: 1440px;
            margin: 0 auto;
            padding: 24px;
        }

        /* Glassmorphism Effects */
        .glass-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }

        /* Header Styles */
        .header {
            background: rgba(255, 255, 255, 0.98);
            backdrop-filter: blur(10px);
            padding: 24px 32px;
            border-radius: 24px;
            margin-bottom: 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.3);
        }

        .header h1 {
            font-size: 2rem;
            font-weight: 700;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .header h1 i {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            font-size: 2rem;
        }

        .header a {
            color: #667eea;
            text-decoration: none;
            padding: 12px 24px;
            background: rgba(102, 126, 234, 0.1);
            border-radius: 12px;
            font-weight: 600;
            transition: all 0.3s ease;
            border: 1px solid rgba(102, 126, 234, 0.2);
        }

        .header a:hover {
            background: #667eea;
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(102, 126, 234, 0.3);
        }

        /* Alert Messages */
        .alert {
            padding: 16px 24px;
            border-radius: 16px;
            margin-bottom: 24px;
            display: flex;
            align-items: center;
            gap: 12px;
            animation: slideIn 0.3s ease;
        }

        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .alert-success {
            background: linear-gradient(135deg, #84fab0 0%, #8fd3f4 100%);
            color: #1a4731;
            border: 1px solid rgba(26, 71, 49, 0.1);
        }

        /* KPI Cards Grid */
        .kpi-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 24px;
            margin-bottom: 30px;
        }

        .kpi-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            padding: 24px;
            border-radius: 20px;
            transition: all 0.3s ease;
            border: 1px solid rgba(255, 255, 255, 0.3);
            position: relative;
            overflow: hidden;
        }

        .kpi-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, #667eea, #764ba2);
        }

        .kpi-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 30px 60px rgba(0, 0, 0, 0.15);
        }

        .kpi-card.critical::before { background: linear-gradient(90deg, #f43f5e, #e11d48); }
        .kpi-card.high::before { background: linear-gradient(90deg, #fb923c, #ea580c); }
        .kpi-card.medium::before { background: linear-gradient(90deg, #fbbf24, #d97706); }
        .kpi-card.total::before { background: linear-gradient(90deg, #3b82f6, #1d4ed8); }

        .kpi-card h3 {
            font-size: 0.9rem;
            color: #64748b;
            margin-bottom: 12px;
            text-transform: uppercase;
            letter-spacing: 1px;
            font-weight: 600;
        }

        .kpi-card .number {
            font-size: 3rem;
            font-weight: 700;
            color: #1e293b;
            line-height: 1;
            margin-bottom: 8px;
        }

        .kpi-card .trend {
            font-size: 0.9rem;
            color: #10b981;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .kpi-card .trend i {
            font-size: 0.8rem;
        }

        /* Charts Row */
        .charts-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 24px;
            margin-bottom: 30px;
        }

        .chart-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            padding: 24px;
            border-radius: 20px;
            border: 1px solid rgba(255, 255, 255, 0.3);
        }

        .chart-card h2 {
            color: #1e293b;
            margin-bottom: 20px;
            font-size: 1.2rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 10px;
            padding-bottom: 15px;
            border-bottom: 2px solid rgba(102, 126, 234, 0.1);
        }

        .chart-card h2 i {
            color: #667eea;
        }

        .chart-container {
            height: 300px;
            position: relative;
        }

        /* Alerts Section */
        .alerts-section {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            padding: 24px;
            border-radius: 20px;
            margin-bottom: 30px;
            border: 1px solid rgba(255, 255, 255, 0.3);
        }

        .alerts-section h2 {
            color: #1e293b;
            margin-bottom: 24px;
            font-size: 1.2rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 10px;
            padding-bottom: 15px;
            border-bottom: 2px solid rgba(102, 126, 234, 0.1);
        }

        .alerts-section h2 i {
            color: #667eea;
        }

        .alert-item {
            background: rgba(255, 255, 255, 0.8);
            backdrop-filter: blur(5px);
            padding: 20px;
            border-radius: 16px;
            margin-bottom: 15px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border: 1px solid rgba(0, 0, 0, 0.05);
            transition: all 0.3s ease;
            animation: fadeIn 0.5s ease;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateX(-10px);
            }
            to {
                opacity: 1;
                transform: translateX(0);
            }
        }

        .alert-item:hover {
            transform: translateX(5px);
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
        }

        .alert-item.critical { border-left: 6px solid #f43f5e; }
        .alert-item.high { border-left: 6px solid #fb923c; }
        .alert-item.medium { border-left: 6px solid #fbbf24; }
        .alert-item.low { border-left: 6px solid #10b981; }

        .alert-info h4 {
            margin-bottom: 8px;
            color: #1e293b;
            font-size: 1.1rem;
            display: flex;
            align-items: center;
            gap: 12px;
            flex-wrap: wrap;
        }

        .alert-info p {
            color: #64748b;
            font-size: 0.95rem;
            margin-bottom: 10px;
        }

        .alert-meta {
            display: flex;
            gap: 20px;
            font-size: 0.85rem;
            color: #94a3b8;
        }

        .alert-meta span {
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .alert-meta i {
            color: #667eea;
        }

        .alert-actions {
            display: flex;
            gap: 12px;
        }

        /* Button Styles */
        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 12px;
            cursor: pointer;
            font-size: 0.9rem;
            font-weight: 600;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            border: 1px solid transparent;
        }

        .btn-primary {
            background: linear-gradient(135deg, #3b82f6, #1d4ed8);
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(59, 130, 246, 0.3);
        }

        .btn-success {
            background: linear-gradient(135deg, #10b981, #059669);
            color: white;
        }

        .btn-success:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(16, 185, 129, 0.3);
        }

        .btn-warning {
            background: linear-gradient(135deg, #f59e0b, #d97706);
            color: white;
        }

        .btn-warning:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(245, 158, 11, 0.3);
        }

        .btn-danger {
            background: linear-gradient(135deg, #ef4444, #dc2626);
            color: white;
        }

        .btn-danger:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(239, 68, 68, 0.3);
        }

        .btn-outline {
            background: transparent;
            border: 2px solid #e2e8f0;
            color: #64748b;
        }

        .btn-outline:hover {
            background: #f8fafc;
            border-color: #94a3b8;
        }

        /* Students Table */
        .students-table {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            padding: 24px;
            border-radius: 20px;
            overflow-x: auto;
            border: 1px solid rgba(255, 255, 255, 0.3);
        }

        .students-table h2 {
            color: #1e293b;
            margin-bottom: 24px;
            font-size: 1.2rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 10px;
            padding-bottom: 15px;
            border-bottom: 2px solid rgba(102, 126, 234, 0.1);
        }

        .students-table h2 i {
            color: #667eea;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th {
            text-align: left;
            padding: 16px;
            background: rgba(102, 126, 234, 0.05);
            color: #1e293b;
            font-weight: 600;
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        td {
            padding: 16px;
            border-bottom: 1px solid rgba(0, 0, 0, 0.05);
            color: #475569;
        }

        tr:hover {
            background: rgba(102, 126, 234, 0.02);
        }

        tr:last-child td {
            border-bottom: none;
        }

        /* Risk Badges */
        .risk-badge {
            padding: 6px 12px;
            border-radius: 30px;
            font-size: 0.8rem;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }

        .risk-critical {
            background: linear-gradient(135deg, #f43f5e, #e11d48);
            color: white;
        }

        .risk-high {
            background: linear-gradient(135deg, #fb923c, #ea580c);
            color: white;
        }

        .risk-medium {
            background: linear-gradient(135deg, #fbbf24, #d97706);
            color: #1e293b;
        }

        .risk-low {
            background: linear-gradient(135deg, #10b981, #059669);
            color: white;
        }

        .recommendation-badge {
            background: linear-gradient(135deg, #8b5cf6, #7c3aed);
            color: white;
            padding: 4px 12px;
            border-radius: 30px;
            font-size: 0.75rem;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 4px;
            animation: pulse 2s infinite;
        }

        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.8; }
        }

        .badge {
            padding: 4px 8px;
            border-radius: 20px;
            font-size: 0.7rem;
            background: #e2e8f0;
            color: #475569;
            font-weight: 500;
        }

        /* Progress Bars */
        .progress-bar {
            width: 100%;
            height: 6px;
            background: #e2e8f0;
            border-radius: 3px;
            overflow: hidden;
            margin-top: 8px;
        }

        .progress-fill {
            height: 100%;
            background: linear-gradient(90deg, #667eea, #764ba2);
            border-radius: 3px;
            transition: width 0.3s ease;
        }

        /* Empty States */
        .empty-state {
            text-align: center;
            padding: 40px;
            color: #94a3b8;
        }

        .empty-state i {
            font-size: 3rem;
            margin-bottom: 16px;
            color: #cbd5e1;
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .charts-row {
                grid-template-columns: 1fr;
            }

            .alert-item {
                flex-direction: column;
                gap: 15px;
            }

            .alert-actions {
                width: 100%;
                justify-content: flex-end;
            }

            .header {
                flex-direction: column;
                gap: 15px;
                text-align: center;
            }
        }

        /* Animations */
        @keyframes float {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-5px); }
        }

        .float-animation {
            animation: float 3s ease-in-out infinite;
        }

        /* Custom Scrollbar */
        ::-webkit-scrollbar {
            width: 10px;
            height: 10px;
        }

        ::-webkit-scrollbar-track {
            background: rgba(255, 255, 255, 0.1);
            border-radius: 10px;
        }

        ::-webkit-scrollbar-thumb {
            background: linear-gradient(135deg, #667eea, #764ba2);
            border-radius: 10px;
        }

        ::-webkit-scrollbar-thumb:hover {
            background: linear-gradient(135deg, #764ba2, #667eea);
        }

        /* Tooltip Styles */
        [data-tooltip] {
            position: relative;
            cursor: help;
        }

        [data-tooltip]:before {
            content: attr(data-tooltip);
            position: absolute;
            bottom: 100%;
            left: 50%;
            transform: translateX(-50%);
            padding: 8px 12px;
            background: #1e293b;
            color: white;
            font-size: 0.8rem;
            border-radius: 8px;
            white-space: nowrap;
            opacity: 0;
            visibility: hidden;
            transition: all 0.3s ease;
            pointer-events: none;
            z-index: 10;
        }

        [data-tooltip]:hover:before {
            opacity: 1;
            visibility: visible;
            bottom: calc(100% + 10px);
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Header -->
        <div class="header">
            <h1>
                <i class="fas fa-shield-alt"></i>
                Risk Monitor Dashboard
            </h1>
            <a href="consultations.php">
                <i class="fas fa-calendar-check"></i> View Consultations
            </a>
        </div>

        <!-- Alert Messages -->
        <?php if (isset($_GET['msg'])): ?>
            <?php if ($_GET['msg'] == 'alert_resolved'): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i>
                    Risk alert resolved successfully!
                </div>
            <?php elseif ($_GET['msg'] == 'consultation_created'): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i>
                    Consultation request created successfully!
                </div>
            <?php endif; ?>
        <?php endif; ?>

        <!-- KPI Cards -->
        <div class="kpi-grid">
            <?php
            $critical_count = 0;
            $high_count = 0;
            $medium_count = 0;
            
            foreach ($risk_alerts as $alert) {
                if ($alert['risk_level'] == 'critical') $critical_count++;
                elseif ($alert['risk_level'] == 'high') $high_count++;
                elseif ($alert['risk_level'] == 'medium') $medium_count++;
            }
            ?>
            
            <div class="kpi-card high">
                <h3><i class="fas fa-exclamation-triangle"></i> High Risk</h3>
                <div class="number"><?php echo $high_count; ?></div>
                <div class="trend">
                    <i class="fas fa-clock"></i> Requires intervention
                </div>
            </div>
            <div class="kpi-card medium">
                <h3><i class="fas fa-chart-line"></i> Medium Risk</h3>
                <div class="number"><?php echo $medium_count; ?></div>
                <div class="trend">
                    <i class="fas fa-eye"></i> Monitor closely
                </div>
            </div>
            <div class="kpi-card total">
                <h3><i class="fas fa-bell"></i> Total Alerts</h3>
                <div class="number"><?php echo count($risk_alerts); ?></div>
                <div class="trend">
                    <i class="fas fa-hourglass-half"></i> Unresolved cases
                </div>
            </div>
        </div>

        <!-- Charts Row -->
        <div class="charts-row">
            <div class="chart-card">
                <h2>
                    <i class="fas fa-chart-pie"></i>
                    Risk Distribution
                </h2>
                <div class="chart-container">
                    <canvas id="riskPieChart"></canvas>
                </div>
            </div>
            <div class="chart-card">
                <h2>
                    <i class="fas fa-chart-line"></i>
                    Risk Trend (Last 7 Days)
                </h2>
                <div class="chart-container">
                    <canvas id="riskTrendChart"></canvas>
                </div>
            </div>
        </div>

        <!-- Active Risk Alerts -->
        <div class="alerts-section">
            <h2>
                <i class="fas fa-bell"></i>
                Active Risk Alerts
                <?php if (!empty($risk_alerts)): ?>
                    <span class="risk-badge risk-critical" style="margin-left: auto;">
                        <?php echo count($risk_alerts); ?> Active
                    </span>
                <?php endif; ?>
            </h2>
            
            <?php if (empty($risk_alerts)): ?>
                <div class="empty-state">
                    <i class="fas fa-check-circle"></i>
                    <h3>All Clear!</h3>
                    <p>No active risk alerts. All systems normal.</p>
                </div>
            <?php else: ?>
                <?php foreach ($risk_alerts as $alert): ?>
                <div class="alert-item <?php echo $alert['risk_level']; ?>">
                    <div class="alert-info">
                        <h4>
                            <i class="fas fa-user-circle"></i>
                            <?php echo htmlspecialchars($alert['student_name']); ?>
                            <span class="risk-badge risk-<?php echo $alert['risk_level']; ?>">
                                <i class="fas fa-<?php echo $alert['risk_level'] == 'critical' ? 'skull' : ($alert['risk_level'] == 'high' ? 'exclamation' : 'chart-line'); ?>"></i>
                                <?php echo ucfirst($alert['risk_level']); ?> Risk
                            </span>
                            <?php if ($alert['risk_level'] == 'critical' || $alert['risk_level'] == 'high'): ?>
                                <span class="recommendation-badge">
                                    <i class="fas fa-stethoscope"></i> Consultation Recommended
                                </span>
                            <?php endif; ?>
                        </h4>
                        <p>
                            <i class="fas fa-quote-right" style="opacity: 0.5;"></i>
                            <?php echo htmlspecialchars($alert['description']); ?>
                        </p>
                        <div class="alert-meta">
                            <span>
                                <i class="fas fa-tag"></i> 
                                Source: <?php echo ucfirst($alert['trigger_source']); ?>
                            </span>
                            <span>
                                <i class="fas fa-clock"></i> 
                                <?php echo date('d M Y, h:i A', strtotime($alert['created_at'])); ?>
                            </span>
                        </div>
                    </div>
                    <div class="alert-actions">
                        <?php if ($alert['risk_level'] == 'critical' || $alert['risk_level'] == 'high'): ?>
                            <form method="POST" style="display: inline;">
                                <input type="hidden" name="student_id" value="<?php echo $alert['student_id']; ?>">
                                <input type="hidden" name="risk_level" value="<?php echo $alert['risk_level']; ?>">
                                <button type="submit" name="create_consultation" class="btn btn-warning" data-tooltip="Schedule consultation">
                                    <i class="fas fa-calendar-plus"></i> Schedule
                                </button>
                            </form>
                        <?php endif; ?>
                        <form method="POST" style="display: inline;">
                            <input type="hidden" name="alert_id" value="<?php echo $alert['id']; ?>">
                            <button type="submit" name="resolve_alert" class="btn btn-success" data-tooltip="Mark as resolved">
                                <i class="fas fa-check"></i> Resolve
                            </button>
                        </form>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <!-- High Risk Students from Mood Data -->
        <div class="students-table">
            <h2>
                <i class="fas fa-users"></i>
                High Risk Students (Mood Analysis)
                <?php if (!empty($high_risk_students)): ?>
                    <span class="risk-badge risk-high" style="margin-left: auto;">
                        <?php echo count($high_risk_students); ?> Students
                    </span>
                <?php endif; ?>
            </h2>
            
            <table>
                <thead>
                    <tr>
                        <th>Student</th>
                        <th>Stress Level</th>
                        <th>Energy Level</th>
                        <th>Risk Level</th>
                        <th>Last Activity</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($high_risk_students)): ?>
                        <tr>
                            <td colspan="6" class="empty-state">
                                <i class="fas fa-smile"></i>
                                <p>No high risk students detected. All students seem to be doing well!</p>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($high_risk_students as $student): ?>
                        <tr>
                            <td>
                                <div style="display: flex; align-items: center; gap: 10px;">
                                    <div style="width: 35px; height: 35px; background: linear-gradient(135deg, #667eea, #764ba2); border-radius: 10px; display: flex; align-items: center; justify-content: center; color: white; font-weight: 600;">
                                        <?php echo strtoupper(substr($student['name'], 0, 2)); ?>
                                    </div>
                                    <div>
                                        <strong><?php echo htmlspecialchars($student['name']); ?></strong><br>
                                        <span class="badge"><?php echo htmlspecialchars($student['email']); ?></span>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <div style="display: flex; align-items: center; gap: 10px;">
                                    <span style="font-weight: 600;"><?php echo number_format($student['avg_stress'], 1); ?>/5</span>
                                    <div class="progress-bar" style="width: 80px;">
                                        <div class="progress-fill" style="width: <?php echo ($student['avg_stress'] / 5) * 100; ?>%; background: #f43f5e;"></div>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <div style="display: flex; align-items: center; gap: 10px;">
                                    <span style="font-weight: 600;"><?php echo number_format($student['avg_energy'], 1); ?>/5</span>
                                    <div class="progress-bar" style="width: 80px;">
                                        <div class="progress-fill" style="width: <?php echo ($student['avg_energy'] / 5) * 100; ?>%; background: #3b82f6;"></div>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <span class="risk-badge risk-<?php echo $student['risk_level']; ?>">
                                    <i class="fas fa-<?php echo $student['risk_level'] == 'critical' ? 'skull' : 'exclamation'; ?>"></i>
                                    <?php echo ucfirst($student['risk_level']); ?>
                                </span>
                            </td>
                            <td>
                                <span class="badge">
                                    <i class="far fa-calendar"></i>
                                    <?php echo date('d M Y', strtotime($student['last_mood_date'])); ?>
                                </span>
                            </td>
                            <td>
                                <form method="POST" style="display: inline;">
                                    <input type="hidden" name="student_id" value="<?php echo $student['id']; ?>">
                                    <input type="hidden" name="risk_level" value="<?php echo $student['risk_level']; ?>">
                                    <button type="submit" name="create_consultation" class="btn btn-primary">
                                        <i class="fas fa-calendar-plus"></i> Schedule
                                    </button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <script>
        // Risk Distribution Pie Chart
        const riskDistData = {
            labels: ['Critical', 'High', 'Medium', 'Low'],
            datasets: [{
                data: [
                    <?php 
                    $dist = array_column($risk_stats['risk_distribution'] ?? [], 'count', 'risk_level');
                    echo ($dist['critical'] ?? 0) . ',';
                    echo ($dist['high'] ?? 0) . ',';
                    echo ($dist['medium'] ?? 0) . ',';
                    echo ($dist['low'] ?? 0);
                    ?>
                ],
                backgroundColor: [
                    '#f43f5e',
                    '#fb923c',
                    '#fbbf24',
                    '#10b981'
                ],
                borderWidth: 0,
                hoverOffset: 10
            }]
        };

        const riskPieChart = new Chart(document.getElementById('riskPieChart'), {
            type: 'doughnut',
            data: riskDistData,
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '70%',
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            color: '#1e293b',
                            font: {
                                size: 12,
                                weight: '500'
                            },
                            padding: 15
                        }
                    },
                    tooltip: {
                        backgroundColor: '#1e293b',
                        titleColor: '#fff',
                        bodyColor: '#94a3b8',
                        padding: 12,
                        cornerRadius: 8
                    }
                }
            }
        });

        // Risk Trend Line Chart
        const trendData = <?php echo json_encode($risk_stats['risk_trend'] ?? []); ?>;
        const trendLabels = trendData.map(d => {
            const date = new Date(d.date);
            return date.toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
        });
        const totalAlerts = trendData.map(d => d.total_alerts);
        const highRiskAlerts = trendData.map(d => d.high_risk);

        const riskTrendChart = new Chart(document.getElementById('riskTrendChart'), {
            type: 'line',
            data: {
                labels: trendLabels,
                datasets: [
                    {
                        label: 'Total Alerts',
                        data: totalAlerts,
                        borderColor: '#3b82f6',
                        backgroundColor: 'rgba(59, 130, 246, 0.1)',
                        borderWidth: 3,
                        tension: 0.4,
                        fill: true,
                        pointBackgroundColor: '#3b82f6',
                        pointBorderColor: '#fff',
                        pointBorderWidth: 2,
                        pointRadius: 4,
                        pointHoverRadius: 6
                    },
                    {
                        label: 'High Risk',
                        data: highRiskAlerts,
                        borderColor: '#f43f5e',
                        backgroundColor: 'rgba(244, 63, 94, 0.1)',
                        borderWidth: 3,
                        tension: 0.4,
                        fill: true,
                        pointBackgroundColor: '#f43f5e',
                        pointBorderColor: '#fff',
                        pointBorderWidth: 2,
                        pointRadius: 4,
                        pointHoverRadius: 6
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        labels: {
                            color: '#1e293b',
                            font: {
                                size: 12,
                                weight: '500'
                            },
                            usePointStyle: true,
                            pointStyle: 'circle'
                        }
                    },
                    tooltip: {
                        backgroundColor: '#1e293b',
                        titleColor: '#fff',
                        bodyColor: '#94a3b8',
                        padding: 12,
                        cornerRadius: 8
                    }
                },
                scales: {
                    x: {
                        grid: {
                            display: false
                        },
                        ticks: {
                            color: '#64748b'
                        }
                    },
                    y: {
                        grid: {
                            color: 'rgba(0, 0, 0, 0.05)'
                        },
                        ticks: {
                            color: '#64748b',
                            stepSize: 1
                        },
                        beginAtZero: true
                    }
                }
            }
        });
    </script>
</body>
</html>