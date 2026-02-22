<?php
require_once 'header.php';
require_once 'sidebar.php';

$student = getLinkedStudent($_SESSION['user_id']);
if (!$student) {
    die("No student linked to this parent account.");
}

$alerts = getStudentAlerts($student['id'], $_GET);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Alerts - EduTrack</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
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
            font-family: 'Inter', sans-serif;
        }

        .page-header {
            background: white;
            border-radius: 20px;
            padding: 28px;
            margin-bottom: 28px;
            box-shadow: 0 8px 30px rgba(0, 0, 0, 0.05);
        }

        .page-header h2 {
            font-size: 2rem;
            font-weight: 700;
            color: var(--dark);
            margin-bottom: 8px;
        }

        .page-header p {
            color: #6c757d;
            margin-bottom: 0;
        }

        .filter-section {
            background: white;
            border-radius: 20px;
            padding: 24px;
            margin-bottom: 28px;
            box-shadow: 0 8px 30px rgba(0, 0, 0, 0.05);
        }

        .filter-section .form-label {
            font-weight: 600;
            color: var(--dark);
            margin-bottom: 8px;
        }

        .filter-section .form-control,
        .filter-section .form-select {
            border: 2px solid #e9ecef;
            border-radius: 12px;
            padding: 10px 16px;
            transition: all 0.3s;
        }

        .filter-section .form-control:focus,
        .filter-section .form-select:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(67, 97, 238, 0.1);
        }

        .filter-section .btn {
            padding: 10px 24px;
            border-radius: 12px;
            font-weight: 500;
            transition: all 0.3s;
        }

        .filter-section .btn-primary {
            background: var(--primary);
            border: none;
        }

        .filter-section .btn-primary:hover {
            background: var(--secondary);
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(67, 97, 238, 0.3);
        }

        .filter-section .btn-secondary {
            background: white;
            border: 2px solid #e9ecef;
            color: var(--dark);
        }

        .filter-section .btn-secondary:hover {
            background: #f8f9fa;
            border-color: #dee2e6;
        }

        .alert-card {
            background: white;
            border-radius: 16px;
            padding: 24px;
            margin-bottom: 16px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.03);
            transition: all 0.3s;
            border: 1px solid rgba(0, 0, 0, 0.05);
            position: relative;
            overflow: hidden;
        }

        .alert-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 4px;
            height: 100%;
        }

        .alert-card.high::before { background: var(--danger); }
        .alert-card.medium::before { background: var(--warning); }
        .alert-card.low::before { background: var(--success); }

        .alert-card:hover {
            transform: translateX(5px);
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
        }

        .risk-badge {
            padding: 6px 16px;
            border-radius: 50px;
            font-weight: 600;
            font-size: 0.85rem;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        .risk-high { background: rgba(247, 37, 133, 0.1); color: #f72585; }
        .risk-medium { background: rgba(248, 150, 30, 0.1); color: #f8961e; }
        .risk-low { background: rgba(76, 201, 240, 0.1); color: #4cc9f0; }

        .status-badge {
            padding: 6px 16px;
            border-radius: 50px;
            font-weight: 500;
            font-size: 0.85rem;
        }

        .status-unread { background: rgba(67, 97, 238, 0.1); color: var(--primary); }
        .status-read { background: #e9ecef; color: #6c757d; }
        .status-resolved { background: rgba(76, 201, 240, 0.1); color: #4cc9f0; }

        .alert-date {
            background: #f8f9fa;
            padding: 8px 16px;
            border-radius: 12px;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .alert-date i {
            color: var(--primary);
        }

        .btn-action {
            padding: 8px 16px;
            border-radius: 10px;
            font-size: 0.9rem;
            font-weight: 500;
            transition: all 0.3s;
            border: 2px solid transparent;
        }

        .btn-outline-primary {
            border: 2px solid rgba(67, 97, 238, 0.2);
            color: var(--primary);
        }

        .btn-outline-primary:hover {
            background: var(--primary);
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(67, 97, 238, 0.3);
        }

        .btn-outline-secondary {
            border: 2px solid #e9ecef;
            color: #6c757d;
        }

        .btn-outline-secondary:hover {
            background: #e9ecef;
            color: var(--dark);
        }

        .btn-outline-danger {
            border: 2px solid rgba(247, 37, 133, 0.2);
            color: var(--danger);
        }

        .btn-outline-danger:hover {
            background: var(--danger);
            color: white;
        }

        .empty-state {
            background: white;
            border-radius: 20px;
            padding: 60px 40px;
            text-align: center;
            box-shadow: 0 8px 30px rgba(0, 0, 0, 0.05);
        }

        .empty-state i {
            font-size: 64px;
            color: #dee2e6;
            margin-bottom: 20px;
        }

        .empty-state h4 {
            color: var(--dark);
            margin-bottom: 10px;
        }

        .empty-state p {
            color: #6c757d;
            margin-bottom: 0;
        }

        @media (max-width: 768px) {
            .alert-card {
                padding: 20px;
            }
            
            .alert-actions {
                margin-top: 16px;
                display: flex;
                gap: 8px;
            }
        }
    </style>
</head>
<body>
    <div class="main-content">
        <div class="container-fluid px-4">
            <!-- Page Header -->
            <div class="page-header">
                <div class="row align-items-center">
                    <div class="col-lg-8">
                        <h2>Risk Alerts</h2>
                        <p>Monitoring emotional alerts for <?php echo htmlspecialchars($student['name']); ?></p>
                    </div>
                    <div class="col-lg-4 text-lg-end">
                        <div class="d-flex justify-content-lg-end gap-2">
                            <span class="risk-badge risk-high"><i class="fas fa-circle"></i> High Priority</span>
                            <span class="risk-badge risk-medium"><i class="fas fa-circle"></i> Medium</span>
                            <span class="risk-badge risk-low"><i class="fas fa-circle"></i> Low</span>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Filter Section -->
            <div class="filter-section">
                <form method="GET" class="row g-4">
                    <div class="col-md-3">
                        <label class="form-label">Risk Level</label>
                        <select name="risk_level" class="form-select">
                            <option value="">All Levels</option>
                            <option value="High" <?php echo ($_GET['risk_level'] ?? '') == 'High' ? 'selected' : ''; ?>>High</option>
                            <option value="Medium" <?php echo ($_GET['risk_level'] ?? '') == 'Medium' ? 'selected' : ''; ?>>Medium</option>
                            <option value="Low" <?php echo ($_GET['risk_level'] ?? '') == 'Low' ? 'selected' : ''; ?>>Low</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Date From</label>
                        <input type="date" name="date_from" class="form-control" value="<?php echo $_GET['date_from'] ?? ''; ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Date To</label>
                        <input type="date" name="date_to" class="form-control" value="<?php echo $_GET['date_to'] ?? ''; ?>">
                    </div>
                    <div class="col-md-3 d-flex align-items-end">
                        <button type="submit" class="btn btn-primary me-2">
                            <i class="fas fa-filter me-2"></i>Apply Filter
                        </button>
                        <a href="alerts.php" class="btn btn-secondary">
                            <i class="fas fa-redo"></i>
                        </a>
                    </div>
                </form>
            </div>
            
            <!-- Alerts List -->
            <div class="row">
                <div class="col-12">
                    <?php if (empty($alerts)): ?>
                        <div class="empty-state">
                            <i class="fas fa-bell-slash"></i>
                            <h4>No alerts found</h4>
                            <p>There are no risk alerts matching your criteria</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($alerts as $alert): ?>
                            <div class="alert-card <?php echo strtolower($alert['risk_level']); ?>">
                                <div class="row align-items-center">
                                    <div class="col-lg-3">
                                        <div class="d-flex align-items-center">
                                            <div class="me-3">
                                                <span class="risk-badge risk-<?php echo strtolower($alert['risk_level']); ?>">
                                                    <i class="fas fa-flag"></i>
                                                    <?php echo $alert['risk_level']; ?>
                                                </span>
                                            </div>
                                            <div class="alert-date">
                                                <i class="fas fa-calendar"></i>
                                                <?php echo date('M d, Y', strtotime($alert['alert_date'])); ?>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="col-lg-4">
                                        <p class="mb-0 fw-medium"><?php echo htmlspecialchars($alert['ai_summary']); ?></p>
                                    </div>
                                    
                                    <div class="col-lg-2">
                                        <span class="status-badge status-<?php echo $alert['status']; ?>">
                                            <i class="fas <?php echo $alert['status'] == 'unread' ? 'fa-envelope' : ($alert['status'] == 'read' ? 'fa-check' : 'fa-check-circle'); ?> me-2"></i>
                                            <?php echo ucfirst($alert['status']); ?>
                                        </span>
                                    </div>
                                    
                                    <div class="col-lg-3">
                                        <div class="alert-actions text-lg-end">
                                            <a href="view-alert.php?id=<?php echo $alert['id']; ?>" class="btn btn-action btn-outline-primary me-2">
                                                <i class="fas fa-eye"></i>
                                                <span class="d-none d-xl-inline ms-2">View</span>
                                            </a>
                                            <?php if ($alert['status'] == 'unread'): ?>
                                                <a href="mark-alert-read.php?id=<?php echo $alert['id']; ?>" class="btn btn-action btn-outline-secondary me-2">
                                                    <i class="fas fa-check"></i>
                                                    <span class="d-none d-xl-inline ms-2">Mark Read</span>
                                                </a>
                                            <?php endif; ?>
                                            <a href="book-consultation.php?alert=<?php echo $alert['id']; ?>" class="btn btn-action btn-outline-danger">
                                                <i class="fas fa-calendar"></i>
                                                <span class="d-none d-xl-inline ms-2">Book</span>
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</body>
</html>