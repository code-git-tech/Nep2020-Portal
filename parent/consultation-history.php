<?php
require_once 'header.php';
require_once 'sidebar.php';

$student = getLinkedStudent($_SESSION['user_id']);
if (!$student) {
    die("No student linked to this parent account.");
}

$consultations = getConsultationHistory($_SESSION['user_id'], $student['id']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Consultation History - EduTrack</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .history-card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.05);
            margin-bottom: 30px;
        }
        .consultation-item {
            border: 1px solid #eee;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 15px;
            transition: all 0.3s;
        }
        .consultation-item:hover {
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            border-color: transparent;
        }
        .status-badge {
            padding: 5px 12px;
            border-radius: 20px;
            font-weight: 500;
            font-size: 0.85rem;
        }
        .status-pending { background: #fff3cd; color: #856404; }
        .status-approved { background: #d4edda; color: #155724; }
        .status-completed { background: #cce5ff; color: #004085; }
        .status-cancelled { background: #f8d7da; color: #721c24; }
        
        .session-notes {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 15px;
            margin-top: 15px;
        }
        .btn-view-notes {
            color: #667eea;
            cursor: pointer;
        }
        .filter-tabs {
            background: white;
            border-radius: 10px;
            padding: 10px;
            margin-bottom: 20px;
        }
        .filter-tab {
            padding: 8px 20px;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s;
            display: inline-block;
            margin-right: 10px;
        }
        .filter-tab.active {
            background: #667eea;
            color: white;
        }
    </style>
</head>
<body>
    <div class="main-content">
        <div class="container-fluid">
            <!-- Page Header -->
            <div class="row mb-4">
                <div class="col-12">
                    <h2 class="mb-1">Consultation History</h2>
                    <p class="text-muted">Track past and upcoming counseling sessions</p>
                </div>
            </div>
            
            <!-- Filter Tabs -->
            <div class="row mb-4">
                <div class="col-12">
                    <div class="filter-tabs">
                        <span class="filter-tab active" onclick="filterConsultations('all')">All</span>
                        <span class="filter-tab" onclick="filterConsultations('upcoming')">Upcoming</span>
                        <span class="filter-tab" onclick="filterConsultations('completed')">Completed</span>
                        <span class="filter-tab" onclick="filterConsultations('pending')">Pending</span>
                    </div>
                </div>
            </div>
            
            <!-- Consultations List -->
            <div class="row">
                <div class="col-12">
                    <?php if (empty($consultations)): ?>
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle"></i> No consultation history found.
                        </div>
                    <?php else: ?>
                        <?php foreach ($consultations as $consult): ?>
                        <div class="consultation-item" data-status="<?php echo strtolower($consult['status']); ?>">
                            <div class="row align-items-center">
                                <div class="col-lg-3">
                                    <div class="d-flex align-items-center">
                                        <i class="fas fa-calendar-alt fa-2x me-3" style="color: #667eea;"></i>
                                        <div>
                                            <div class="fw-bold"><?php echo date('M d, Y', strtotime($consult['preferred_date'])); ?></div>
                                            <div class="text-muted"><?php echo date('h:i A', strtotime($consult['preferred_time'])); ?></div>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="col-lg-3">
                                    <div class="d-flex align-items-center">
                                        <i class="fas fa-user-md me-2" style="color: #26de81;"></i>
                                        <div>
                                            <div class="fw-bold"><?php echo htmlspecialchars($consult['counselor_name'] ?? 'To be assigned'); ?></div>
                                            <div class="text-muted">Counselor</div>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="col-lg-2">
                                    <span class="badge bg-<?php 
                                        echo $consult['issue_type'] == 'Emotional Stress' ? 'danger' : 
                                            ($consult['issue_type'] == 'Academic Pressure' ? 'warning' : 
                                            ($consult['issue_type'] == 'Behavioral Concern' ? 'info' : 
                                            ($consult['issue_type'] == 'Screen Addiction' ? 'secondary' : 'primary'))); 
                                    ?>">
                                        <?php echo $consult['issue_type']; ?>
                                    </span>
                                </div>
                                
                                <div class="col-lg-2">
                                    <span class="status-badge status-<?php echo strtolower($consult['status']); ?>">
                                        <?php echo $consult['status']; ?>
                                    </span>
                                </div>
                                
                                <div class="col-lg-2 text-lg-end">
                                    <?php if ($consult['status'] == 'Completed' && !empty($consult['notes'])): ?>
                                        <a href="#" class="btn-view-notes" onclick="viewNotes(<?php echo $consult['id']; ?>); return false;">
                                            <i class="fas fa-file-alt me-1"></i> View Notes
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <?php if (!empty($consult['notes'])): ?>
                            <div class="session-notes" id="notes-<?php echo $consult['id']; ?>" style="display: none;">
                                <h6 class="mb-3">Session Notes</h6>
                                <p><?php echo nl2br(htmlspecialchars($consult['notes'])); ?></p>
                                <?php if (!empty($consult['recommendations'])): ?>
                                    <h6 class="mb-2 mt-3">Recommendations</h6>
                                    <p><?php echo nl2br(htmlspecialchars($consult['recommendations'])); ?></p>
                                <?php endif; ?>
                            </div>
                            <?php endif; ?>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        function filterConsultations(filter) {
            document.querySelectorAll('.filter-tab').forEach(tab => tab.classList.remove('active'));
            event.target.classList.add('active');
            
            const items = document.querySelectorAll('.consultation-item');
            items.forEach(item => {
                if (filter == 'all') {
                    item.style.display = 'block';
                } else if (filter == 'upcoming') {
                    item.style.display = (item.dataset.status == 'approved' || item.dataset.status == 'pending') ? 'block' : 'none';
                } else {
                    item.style.display = item.dataset.status.toLowerCase() == filter ? 'block' : 'none';
                }
            });
        }
        
        function viewNotes(id) {
            event.preventDefault();
            const notes = document.getElementById('notes-' + id);
            if (notes.style.display == 'none') {
                notes.style.display = 'block';
            } else {
                notes.style.display = 'none';
            }
        }
    </script>
</body>
</html>