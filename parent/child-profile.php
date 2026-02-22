<?php
require_once 'header.php';
require_once 'sidebar.php';

$student = getLinkedStudent($_SESSION['user_id']);
if (!$student) {
    die("No student linked to this parent account.");
}

$profileData = getStudentProfileData($student['id']);
$riskSummary = getStudentRiskSummary($student['id']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Child Profile - EduTrack</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .profile-card {
            background: white;
            border-radius: 20px;
            padding: 30px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.1);
            margin-bottom: 30px;
        }
        .profile-avatar {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 3rem;
            margin: 0 auto 20px;
        }
        .info-card {
            background: #f8f9fa;
            border-radius: 15px;
            padding: 20px;
            height: 100%;
        }
        .info-label {
            color: #666;
            font-size: 0.9rem;
            margin-bottom: 5px;
        }
        .info-value {
            font-size: 1.2rem;
            font-weight: 600;
            color: #333;
        }
        .risk-indicator {
            padding: 15px;
            border-radius: 12px;
            background: white;
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
            margin-bottom: 15px;
        }
        .risk-high { border-left: 4px solid #ff4757; }
        .risk-medium { border-left: 4px solid #ffa502; }
        .risk-low { border-left: 4px solid #26de81; }
        
        .wellness-score {
            text-align: center;
            padding: 20px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 15px;
            color: white;
        }
        .wellness-score .score {
            font-size: 3rem;
            font-weight: 700;
        }
        .emergency-contact {
            background: #fff3cd;
            border: 1px solid #ffeeba;
            border-radius: 12px;
            padding: 15px;
            color: #856404;
        }
    </style>
</head>
<body>
    <div class="main-content">
        <div class="container-fluid">
            <!-- Page Header -->
            <div class="row mb-4">
                <div class="col-12">
                    <h2 class="mb-1">Child Profile</h2>
                    <p class="text-muted">View and manage your child's information</p>
                </div>
            </div>
            
            <div class="row">
                <!-- Profile Card -->
                <div class="col-lg-4 mb-4">
                    <div class="profile-card text-center">
                        <div class="profile-avatar">
                            <i class="fas fa-child"></i>
                        </div>
                        <h3 class="mb-1"><?php echo htmlspecialchars($student['name']); ?></h3>
                        <p class="text-muted mb-3"><?php echo htmlspecialchars($student['email']); ?></p>
                        
                        <div class="wellness-score mb-4">
                            <div class="score"><?php echo $riskSummary['wellness_score'] ?? 75; ?></div>
                            <div>Wellness Score</div>
                        </div>
                        
                        <div class="emergency-contact">
                            <i class="fas fa-phone-alt me-2"></i>
                            <strong>Emergency Contact:</strong><br>
                            <?php echo $profileData['emergency_contact'] ?? '+91 98765 43210'; ?>
                        </div>
                    </div>
                </div>
                
                <!-- Information Cards -->
                <div class="col-lg-8 mb-4">
                    <div class="row">
                        <!-- Basic Info -->
                        <div class="col-md-6 mb-4">
                            <div class="info-card">
                                <h5 class="mb-3"><i class="fas fa-info-circle me-2" style="color: #667eea;"></i>Basic Information</h5>
                                <table class="table table-borderless">
                                    <tr>
                                        <td class="info-label">Class/Grade</td>
                                        <td class="info-value"><?php echo $profileData['class'] ?? '10th'; ?></td>
                                    </tr>
                                    <tr>
                                        <td class="info-label">Age</td>
                                        <td class="info-value"><?php echo $profileData['age'] ?? '16 years'; ?></td>
                                    </tr>
                                    <tr>
                                        <td class="info-label">Date of Birth</td>
                                        <td class="info-value"><?php echo $profileData['dob'] ?? 'Jan 15, 2010'; ?></td>
                                    </tr>
                                    <tr>
                                        <td class="info-label">Blood Group</td>
                                        <td class="info-value"><?php echo $profileData['blood_group'] ?? 'O+'; ?></td>
                                    </tr>
                                </table>
                            </div>
                        </div>
                        
                        <!-- Contact Info -->
                        <div class="col-md-6 mb-4">
                            <div class="info-card">
                                <h5 class="mb-3"><i class="fas fa-address-book me-2" style="color: #26de81;"></i>Contact Information</h5>
                                <table class="table table-borderless">
                                    <tr>
                                        <td class="info-label">Parent Name</td>
                                        <td class="info-value"><?php echo htmlspecialchars($_SESSION['user_name']); ?></td>
                                    </tr>
                                    <tr>
                                        <td class="info-label">Parent Email</td>
                                        <td class="info-value"><?php echo htmlspecialchars($_SESSION['user_email'] ?? 'parent@example.com'); ?></td>
                                    </tr>
                                    <tr>
                                        <td class="info-label">Parent Phone</td>
                                        <td class="info-value"><?php echo $profileData['parent_phone'] ?? '+91 98765 43210'; ?></td>
                                    </tr>
                                    <tr>
                                        <td class="info-label">Address</td>
                                        <td class="info-value"><?php echo $profileData['address'] ?? 'Mumbai, India'; ?></td>
                                    </tr>
                                </table>
                            </div>
                        </div>
                        
                        <!-- Risk History Summary -->
                        <div class="col-12">
                            <div class="info-card">
                                <h5 class="mb-3"><i class="fas fa-exclamation-triangle me-2" style="color: #ff4757;"></i>Risk History Summary</h5>
                                <div class="row">
                                    <div class="col-md-4">
                                        <div class="risk-indicator risk-high">
                                            <div class="d-flex justify-content-between">
                                                <span>High Risk Alerts</span>
                                                <strong><?php echo $riskSummary['high_count'] ?? 2; ?></strong>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="risk-indicator risk-medium">
                                            <div class="d-flex justify-content-between">
                                                <span>Medium Risk Alerts</span>
                                                <strong><?php echo $riskSummary['medium_count'] ?? 5; ?></strong>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="risk-indicator risk-low">
                                            <div class="d-flex justify-content-between">
                                                <span>Low Risk Alerts</span>
                                                <strong><?php echo $riskSummary['low_count'] ?? 8; ?></strong>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="mt-3">
                                    <p class="mb-0"><strong>Last Risk Assessment:</strong> <?php echo $riskSummary['last_assessment'] ?? 'Today, 10:30 AM'; ?></p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>