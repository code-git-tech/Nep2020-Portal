<?php
require_once 'header.php';
require_once 'sidebar.php';

$student = getLinkedStudent($_SESSION['user_id']);
if (!$student) {
    die("No student linked to this parent account.");
}

$counselors = getAvailableCounselors();

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $result = bookConsultation($_SESSION['user_id'], $student['id'], $_POST);
    if ($result['success']) {
        $success = "Consultation booked successfully!";
    } else {
        $error = $result['message'];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Book Consultation - EduTrack</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .booking-card {
            background: white;
            border-radius: 20px;
            padding: 30px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.1);
        }
        .step-indicator {
            display: flex;
            justify-content: space-between;
            margin-bottom: 40px;
            position: relative;
        }
        .step-indicator::before {
            content: '';
            position: absolute;
            top: 25px;
            left: 0;
            right: 0;
            height: 2px;
            background: #e0e0e0;
            z-index: 1;
        }
        .step {
            position: relative;
            z-index: 2;
            background: white;
            padding: 0 20px;
            text-align: center;
        }
        .step-number {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            background: #f8f9fa;
            color: #666;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            margin: 0 auto 10px;
            border: 2px solid #e0e0e0;
        }
        .step.active .step-number {
            background: #667eea;
            color: white;
            border-color: #667eea;
        }
        .step.completed .step-number {
            background: #26de81;
            color: white;
            border-color: #26de81;
        }
        .counselor-card {
            border: 2px solid #eee;
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 15px;
            cursor: pointer;
            transition: all 0.3s;
        }
        .counselor-card:hover {
            border-color: #667eea;
            transform: translateY(-2px);
            box-shadow: 0 5px 20px rgba(102,126,234,0.1);
        }
        .counselor-card.selected {
            border-color: #667eea;
            background: #f0f3ff;
        }
        .counselor-avatar {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background: #667eea20;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            color: #667eea;
        }
        .issue-badge {
            padding: 8px 15px;
            border-radius: 25px;
            background: #f8f9fa;
            color: #666;
            cursor: pointer;
            transition: all 0.3s;
            margin: 5px;
            display: inline-block;
        }
        .issue-badge:hover {
            background: #667eea20;
            color: #667eea;
        }
        .issue-badge.selected {
            background: #667eea;
            color: white;
        }
        .btn-next {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 10px;
            padding: 12px 30px;
            font-weight: 600;
            transition: transform 0.3s;
        }
        .btn-next:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 20px rgba(102,126,234,0.4);
            color: white;
        }
    </style>
</head>
<body>
    <div class="main-content">
        <div class="container-fluid">
            <div class="row justify-content-center">
                <div class="col-lg-10">
                    <!-- Page Header -->
                    <div class="row mb-4">
                        <div class="col-12">
                            <h2 class="mb-1">Book Consultation</h2>
                            <p class="text-muted">Schedule a counseling session for <?php echo htmlspecialchars($student['name']); ?></p>
                        </div>
                    </div>
                    
                    <!-- Booking Form -->
                    <div class="booking-card">
                        <?php if (isset($success)): ?>
                            <div class="alert alert-success"><?php echo $success; ?></div>
                        <?php endif; ?>
                        
                        <?php if (isset($error)): ?>
                            <div class="alert alert-danger"><?php echo $error; ?></div>
                        <?php endif; ?>
                        
                        <form method="POST" id="bookingForm">
                            <!-- Step 1: Issue Type -->
                            <div class="step-indicator">
                                <div class="step active" data-step="1">
                                    <div class="step-number">1</div>
                                    <div>Issue Type</div>
                                </div>
                                <div class="step" data-step="2">
                                    <div class="step-number">2</div>
                                    <div>Select Counselor</div>
                                </div>
                                <div class="step" data-step="3">
                                    <div class="step-number">3</div>
                                    <div>Date & Time</div>
                                </div>
                            </div>
                            
                            <!-- Step 1 Content -->
                            <div class="step-content" id="step1">
                                <h5 class="mb-4">What type of issue would you like to discuss?</h5>
                                <div class="row">
                                    <?php 
                                    $issues = ['Emotional Stress', 'Academic Pressure', 'Behavioral Concern', 'Screen Addiction', 'Other'];
                                    foreach ($issues as $issue): 
                                    ?>
                                    <div class="col-md-6">
                                        <div class="issue-badge" onclick="selectIssue('<?php echo $issue; ?>')">
                                            <i class="fas 
                                                <?php 
                                                echo $issue == 'Emotional Stress' ? 'fa-heart' : 
                                                    ($issue == 'Academic Pressure' ? 'fa-book' : 
                                                    ($issue == 'Behavioral Concern' ? 'fa-users' : 
                                                    ($issue == 'Screen Addiction' ? 'fa-mobile-alt' : 'fa-ellipsis-h'))); 
                                                ?> me-2">
                                            </i>
                                            <?php echo $issue; ?>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                                <input type="hidden" name="issue_type" id="issue_type" required>
                                
                                <div class="mt-4">
                                    <label class="form-label">Description (Optional)</label>
                                    <textarea name="description" class="form-control" rows="4" placeholder="Please provide more details about your concern..."></textarea>
                                </div>
                                
                                <div class="text-end mt-4">
                                    <button type="button" class="btn-next" onclick="nextStep(2)">Next Step <i class="fas fa-arrow-right ms-2"></i></button>
                                </div>
                            </div>
                            
                            <!-- Step 2 Content -->
                            <div class="step-content" id="step2" style="display: none;">
                                <h5 class="mb-4">Select a counselor</h5>
                                <div class="row">
                                    <?php foreach ($counselors as $counselor): ?>
                                    <div class="col-md-6">
                                        <div class="counselor-card" onclick="selectCounselor(<?php echo $counselor['id']; ?>)">
                                            <div class="d-flex align-items-center">
                                                <div class="counselor-avatar me-3">
                                                    <i class="fas fa-user-md"></i>
                                                </div>
                                                <div>
                                                    <h6 class="mb-1"><?php echo htmlspecialchars($counselor['name']); ?></h6>
                                                    <p class="text-muted mb-1"><?php echo htmlspecialchars($counselor['specialization'] ?? 'General Counselor'); ?></p>
                                                    <small><i class="fas fa-star text-warning"></i> <?php echo $counselor['rating'] ?? '4.8'; ?> (<?php echo $counselor['sessions'] ?? '50'; ?> sessions)</small>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                                <input type="hidden" name="counselor_id" id="counselor_id" required>
                                
                                <div class="d-flex justify-content-between mt-4">
                                    <button type="button" class="btn btn-outline-secondary" onclick="prevStep(1)"><i class="fas fa-arrow-left me-2"></i>Back</button>
                                    <button type="button" class="btn-next" onclick="nextStep(3)">Next Step <i class="fas fa-arrow-right ms-2"></i></button>
                                </div>
                            </div>
                            
                            <!-- Step 3 Content -->
                            <div class="step-content" id="step3" style="display: none;">
                                <h5 class="mb-4">Select preferred date and time</h5>
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Preferred Date</label>
                                        <input type="date" name="preferred_date" class="form-control" id="preferred_date" required min="<?php echo date('Y-m-d'); ?>">
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Preferred Time</label>
                                        <input type="time" name="preferred_time" class="form-control" id="preferred_time" required>
                                    </div>
                                </div>
                                
                                <div class="alert alert-info">
                                    <i class="fas fa-info-circle"></i> The counselor will confirm the appointment time. You'll receive a notification once confirmed.
                                </div>
                                
                                <div class="d-flex justify-content-between mt-4">
                                    <button type="button" class="btn btn-outline-secondary" onclick="prevStep(2)"><i class="fas fa-arrow-left me-2"></i>Back</button>
                                    <button type="submit" class="btn-next">Book Consultation <i class="fas fa-check ms-2"></i></button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        let currentStep = 1;
        
        function selectIssue(issue) {
            document.querySelectorAll('.issue-badge').forEach(b => b.classList.remove('selected'));
            event.target.classList.add('selected');
            document.getElementById('issue_type').value = issue;
        }
        
        function selectCounselor(id) {
            document.querySelectorAll('.counselor-card').forEach(c => c.classList.remove('selected'));
            event.currentTarget.classList.add('selected');
            document.getElementById('counselor_id').value = id;
        }
        
        function nextStep(step) {
            if (step == 2 && !document.getElementById('issue_type').value) {
                alert('Please select an issue type');
                return;
            }
            if (step == 3 && !document.getElementById('counselor_id').value) {
                alert('Please select a counselor');
                return;
            }
            
            document.getElementById('step' + currentStep).style.display = 'none';
            document.getElementById('step' + step).style.display = 'block';
            
            document.querySelectorAll('.step').forEach(s => s.classList.remove('active', 'completed'));
            for (let i = 1; i < step; i++) {
                document.querySelector(`.step[data-step="${i}"]`).classList.add('completed');
            }
            document.querySelector(`.step[data-step="${step}"]`).classList.add('active');
            
            currentStep = step;
        }
        
        function prevStep(step) {
            document.getElementById('step' + currentStep).style.display = 'none';
            document.getElementById('step' + step).style.display = 'block';
            
            document.querySelectorAll('.step').forEach(s => s.classList.remove('active', 'completed'));
            for (let i = 1; i < step; i++) {
                document.querySelector(`.step[data-step="${i}"]`).classList.add('completed');
            }
            document.querySelector(`.step[data-step="${step}"]`).classList.add('active');
            
            currentStep = step;
        }
    </script>
</body>
</html>