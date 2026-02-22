<?php
session_start();
require_once '../../config/db.php';
require_once '../../includes/functions.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../login.php');
    exit();
}

// Get counselor ID
$counselor_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$counselor = getCounselorById($counselor_id);

if (!$counselor) {
    header('Location: counselors.php?msg=not_found');
    exit();
}

$error = '';
$success = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Validate input
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $specialization = trim($_POST['specialization'] ?? '');
    $experience = intval($_POST['experience'] ?? 0);
    $availability = trim($_POST['availability'] ?? '');
    $status = $_POST['status'] ?? 'active';

    // Basic validation
    if (empty($name) || empty($email)) {
        $error = 'Name and email are required fields.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } else {
        // Check if email already exists (excluding current counselor)
        $stmt = $pdo->prepare("SELECT id FROM counselors WHERE email = ? AND id != ?");
        $stmt->execute([$email, $counselor_id]);
        if ($stmt->fetch()) {
            $error = 'A counselor with this email already exists.';
        } else {
            // Update counselor
            $data = [
                'name' => $name,
                'email' => $email,
                'phone' => $phone,
                'specialization' => $specialization,
                'experience' => $experience,
                'availability' => $availability,
                'status' => $status
            ];
            
            if (updateCounselor($counselor_id, $data)) {
                header('Location: counselors.php?msg=updated');
                exit();
            } else {
                $error = 'Failed to update counselor. Please try again.';
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Counselor | Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f5f6fa;
        }

        .container {
            max-width: 800px;
            margin: 50px auto;
            padding: 0 20px;
        }

        .form-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            overflow: hidden;
        }

        .card-header {
            background: linear-gradient(135deg, #f39c12, #2c3e50);
            color: white;
            padding: 30px;
            text-align: center;
        }

        .card-header h1 {
            font-size: 2rem;
            margin-bottom: 10px;
        }

        .card-header h1 i {
            margin-right: 10px;
        }

        .card-header p {
            opacity: 0.9;
            font-size: 1rem;
        }

        .card-body {
            padding: 30px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: #2c3e50;
        }

        .form-group label i {
            margin-right: 8px;
            color: #f39c12;
        }

        .form-control {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 1rem;
            transition: border-color 0.3s;
        }

        .form-control:focus {
            outline: none;
            border-color: #f39c12;
        }

        textarea.form-control {
            resize: vertical;
            min-height: 100px;
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }

        .radio-group {
            display: flex;
            gap: 20px;
            padding: 10px 0;
        }

        .radio-option {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .radio-option input[type="radio"] {
            width: 18px;
            height: 18px;
        }

        .btn {
            padding: 12px 24px;
            border: none;
            border-radius: 8px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .btn-primary {
            background: #f39c12;
            color: white;
        }

        .btn-primary:hover {
            background: #e67e22;
            transform: translateY(-2px);
        }

        .btn-secondary {
            background: #95a5a6;
            color: white;
        }

        .btn-secondary:hover {
            background: #7f8c8d;
        }

        .form-actions {
            display: flex;
            gap: 15px;
            justify-content: flex-end;
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #eee;
        }

        .alert {
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }

        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .hint-text {
            font-size: 0.9rem;
            color: #666;
            margin-top: 5px;
        }

        .required::after {
            content: " *";
            color: #e74c3c;
            font-weight: bold;
        }

        .readonly-info {
            background: #f8f9fa;
            padding: 10px 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            border-left: 4px solid #f39c12;
        }

        .readonly-info p {
            margin: 5px 0;
            color: #666;
        }

        .readonly-info strong {
            color: #2c3e50;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="form-card">
            <div class="card-header">
                <h1><i class="fas fa-user-edit"></i> Edit Counselor</h1>
                <p>Update counselor information</p>
            </div>
            <div class="card-body">
                <?php if ($error): ?>
                    <div class="alert alert-error">
                        <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
                    </div>
                <?php endif; ?>

                <div class="readonly-info">
                    <p><i class="fas fa-id-card"></i> <strong>Counselor ID:</strong> #<?php echo $counselor['id']; ?></p>
                    <p><i class="fas fa-calendar-alt"></i> <strong>Joined:</strong> <?php echo date('d M Y', strtotime($counselor['created_at'])); ?></p>
                    <p><i class="fas fa-tasks"></i> <strong>Active Sessions:</strong> <?php echo $counselor['assigned_sessions']; ?></p>
                </div>

                <form method="POST" action="">
                    <div class="form-group">
                        <label class="required"><i class="fas fa-user"></i> Full Name</label>
                        <input type="text" name="name" class="form-control" 
                               value="<?php echo htmlspecialchars($counselor['name']); ?>" 
                               placeholder="Enter counselor's full name" required>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label class="required"><i class="fas fa-envelope"></i> Email</label>
                            <input type="email" name="email" class="form-control" 
                                   value="<?php echo htmlspecialchars($counselor['email']); ?>" 
                                   placeholder="counselor@example.com" required>
                        </div>
                        <div class="form-group">
                            <label><i class="fas fa-phone"></i> Phone Number</label>
                            <input type="tel" name="phone" class="form-control" 
                                   value="<?php echo htmlspecialchars($counselor['phone'] ?? ''); ?>" 
                                   placeholder="Enter phone number">
                        </div>
                    </div>

                    <div class="form-group">
                        <label><i class="fas fa-stethoscope"></i> Specialization</label>
                        <input type="text" name="specialization" class="form-control" 
                               value="<?php echo htmlspecialchars($counselor['specialization'] ?? ''); ?>" 
                               placeholder="e.g., Child Psychology, Academic Counseling, Behavioral Therapy">
                        <div class="hint-text">Separate multiple specializations with commas</div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label><i class="fas fa-briefcase"></i> Years of Experience</label>
                            <input type="number" name="experience" class="form-control" 
                                   value="<?php echo $counselor['experience']; ?>" 
                                   min="0" max="50">
                        </div>
                        <div class="form-group">
                            <label><i class="fas fa-clock"></i> Status</label>
                            <div class="radio-group">
                                <label class="radio-option">
                                    <input type="radio" name="status" value="active" 
                                           <?php echo $counselor['status'] == 'active' ? 'checked' : ''; ?>> Active
                                </label>
                                <label class="radio-option">
                                    <input type="radio" name="status" value="inactive" 
                                           <?php echo $counselor['status'] == 'inactive' ? 'checked' : ''; ?>> Inactive
                                </label>
                            </div>
                        </div>
                    </div>

                    <div class="form-group">
                        <label><i class="fas fa-calendar-alt"></i> Availability Schedule</label>
                        <textarea name="availability" class="form-control" 
                                  placeholder="e.g., Mon-Fri 9AM-5PM, Sat 10AM-2PM"><?php echo htmlspecialchars($counselor['availability'] ?? ''); ?></textarea>
                    </div>

                    <div class="form-actions">
                        <a href="counselors.php" class="btn btn-secondary">
                            <i class="fas fa-times"></i> Cancel
                        </a>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> Update Counselor
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</body>
</html>