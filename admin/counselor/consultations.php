<?php
session_start();
require_once '../../config/db.php';
require_once '../../includes/functions.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../login.php');
    exit();
}

// Handle status updates
if (isset($_POST['update_status'])) {
    $request_id = $_POST['request_id'];
    $new_status = $_POST['new_status'];
    updateConsultationStatus($request_id, $new_status);
    header('Location: consultations.php?msg=status_updated');
    exit();
}

// Handle counselor assignment
if (isset($_POST['assign_counselor'])) {
    $request_id = $_POST['request_id'];
    $counselor_id = $_POST['counselor_id'];
    if (assignCounselorToRequest($request_id, $counselor_id)) {
        header('Location: consultations.php?msg=counselor_assigned');
    } else {
        header('Location: consultations.php?msg=assignment_failed');
    }
    exit();
}

// Get filter parameters
$status_filter = isset($_GET['status']) ? $_GET['status'] : 'all';
$risk_filter = isset($_GET['risk']) ? $_GET['risk'] : 'all';
$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : '';
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : '';

// Fetch consultation requests
$requests = getConsultationRequests($status_filter, $risk_filter, $date_from, $date_to);

// Fetch all counselors for assignment dropdown
$counselors = getAllCounselors(true);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Consultation Management | Admin</title>
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
            display: flex;
        }

        .sidebar {
            width: 260px;
            background: #2c3e50;
            color: white;
            height: 100vh;
            position: fixed;
            left: 0;
            top: 0;
            overflow-y: auto;
        }

        .sidebar-header {
            padding: 20px;
            text-align: center;
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }

        .sidebar-header h2 {
            font-size: 1.5rem;
            margin-bottom: 5px;
        }

        .sidebar-header p {
            font-size: 0.9rem;
            opacity: 0.8;
        }

        .sidebar-menu {
            padding: 20px 0;
        }

        .sidebar-menu a {
            display: block;
            padding: 12px 20px;
            color: white;
            text-decoration: none;
            transition: all 0.3s;
            border-left: 3px solid transparent;
        }

        .sidebar-menu a:hover,
        .sidebar-menu a.active {
            background: rgba(255,255,255,0.1);
            border-left-color: #f39c12;
        }

        .sidebar-menu a i {
            margin-right: 10px;
            width: 20px;
            text-align: center;
        }

        .main-content {
            flex: 1;
            margin-left: 260px;
            padding: 20px;
        }

        .header {
            background: white;
            padding: 15px 25px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 25px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .header h1 {
            font-size: 1.8rem;
            color: #2c3e50;
        }

        .header h1 i {
            margin-right: 10px;
            color: #f39c12;
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .user-info span {
            color: #2c3e50;
            font-weight: 500;
        }

        .user-info a {
            color: #e74c3c;
            text-decoration: none;
        }

        .filter-section {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 25px;
        }

        .filter-section h3 {
            margin-bottom: 15px;
            color: #2c3e50;
            font-size: 1.2rem;
        }

        .filter-form {
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
            align-items: flex-end;
        }

        .filter-group {
            flex: 1;
            min-width: 150px;
        }

        .filter-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 500;
            color: #555;
            font-size: 0.9rem;
        }

        .filter-group select,
        .filter-group input {
            width: 100%;
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 0.95rem;
        }

        .filter-actions {
            display: flex;
            gap: 10px;
        }

        .btn {
            padding: 8px 16px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 0.95rem;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-block;
        }

        .btn-primary {
            background: #3498db;
            color: white;
        }

        .btn-primary:hover {
            background: #2980b9;
        }

        .btn-secondary {
            background: #95a5a6;
            color: white;
        }

        .btn-secondary:hover {
            background: #7f8c8d;
        }

        .btn-success {
            background: #27ae60;
            color: white;
        }

        .btn-success:hover {
            background: #219a52;
        }

        .btn-danger {
            background: #e74c3c;
            color: white;
        }

        .btn-info {
            background: #3498db;
            color: white;
        }

        .table-container {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th {
            background: #ecf0f1;
            padding: 12px;
            text-align: left;
            font-weight: 600;
            color: #2c3e50;
        }

        td {
            padding: 12px;
            border-bottom: 1px solid #eee;
        }

        tr:hover {
            background: #f8f9fa;
        }

        .badge {
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
            display: inline-block;
        }

        .badge-pending {
            background: #fff3cd;
            color: #856404;
        }

        .badge-approved {
            background: #d4edda;
            color: #155724;
        }

        .badge-completed {
            background: #cce5ff;
            color: #004085;
        }

        .badge-rejected {
            background: #f8d7da;
            color: #721c24;
        }

        .badge-critical {
            background: #dc3545;
            color: white;
        }

        .badge-high {
            background: #fd7e14;
            color: white;
        }

        .badge-medium {
            background: #ffc107;
            color: #212529;
        }

        .badge-low {
            background: #28a745;
            color: white;
        }

        .action-dropdown {
            position: relative;
            display: inline-block;
        }

        .action-btn {
            background: #3498db;
            color: white;
            padding: 6px 12px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 0.9rem;
        }

        .dropdown-content {
            display: none;
            position: absolute;
            background: white;
            min-width: 180px;
            box-shadow: 0 8px 16px rgba(0,0,0,0.1);
            z-index: 1;
            border-radius: 4px;
            right: 0;
        }

        .dropdown-content a,
        .dropdown-content button {
            color: #2c3e50;
            padding: 10px 12px;
            text-decoration: none;
            display: block;
            width: 100%;
            text-align: left;
            border: none;
            background: none;
            cursor: pointer;
            font-size: 0.9rem;
        }

        .dropdown-content a:hover,
        .dropdown-content button:hover {
            background: #f8f9fa;
        }

        .action-dropdown:hover .dropdown-content {
            display: block;
        }

        .assign-form {
            display: flex;
            gap: 5px;
        }

        .assign-form select {
            padding: 5px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 0.9rem;
        }

        .assign-form button {
            padding: 5px 10px;
            background: #27ae60;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
        }

        .alert {
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
        }

        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .alert-danger {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .kpi-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 25px;
        }

        .kpi-card {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .kpi-icon {
            width: 50px;
            height: 50px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
        }

        .kpi-icon.pending {
            background: #fff3cd;
            color: #856404;
        }

        .kpi-icon.approved {
            background: #d4edda;
            color: #155724;
        }

        .kpi-icon.high {
            background: #f8d7da;
            color: #721c24;
        }

        .kpi-info h4 {
            font-size: 0.9rem;
            color: #666;
            margin-bottom: 5px;
        }

        .kpi-info p {
            font-size: 1.5rem;
            font-weight: 600;
            color: #2c3e50;
        }
    </style>
</head>
<body>
    <div class="sidebar">
        <div class="sidebar-header">
            <h2>Admin Panel</h2>
            <p>Consultation Management</p>
        </div>
        <div class="sidebar-menu">
            <a href="dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
            <a href="consultations.php" class="active"><i class="fas fa-calendar-check"></i> Consultations</a>
            <a href="counselors.php"><i class="fas fa-users"></i> Counselors</a>
            <a href="risk-monitor.php"><i class="fas fa-exclamation-triangle"></i> Risk Monitor</a>
            <a href="../admin/courses.php"><i class="fas fa-book"></i> Courses</a>
            <a href="users.php"><i class="fas fa-user-graduate"></i> Users</a>
            <a href="reports.php"><i class="fas fa-chart-bar"></i> Reports</a>
        </div>
    </div>

    <div class="main-content">
        <div class="header">
            <h1><i class="fas fa-calendar-check"></i> Consultation Management</h1>
            <div class="user-info">
                <span><i class="fas fa-user"></i> <?php echo htmlspecialchars($_SESSION['name']); ?></span>
                <a href="../logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
            </div>
        </div>

        <?php if (isset($_GET['msg'])): ?>
            <?php if ($_GET['msg'] == 'status_updated'): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i> Consultation status updated successfully!
                </div>
            <?php elseif ($_GET['msg'] == 'counselor_assigned'): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i> Counselor assigned successfully!
                </div>
            <?php elseif ($_GET['msg'] == 'assignment_failed'): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle"></i> Failed to assign counselor. Please try again.
                </div>
            <?php endif; ?>
        <?php endif; ?>

        <!-- KPI Cards -->
        <?php
        $pending_count = count(array_filter($requests, function($r) { return $r['status'] == 'pending'; }));
        $approved_count = count(array_filter($requests, function($r) { return $r['status'] == 'approved'; }));
        $high_risk_count = count(array_filter($requests, function($r) { 
            return in_array($r['risk_level'], ['high', 'critical']); 
        }));
        ?>
        <div class="kpi-cards">
            <div class="kpi-card">
                <div class="kpi-icon pending">
                    <i class="fas fa-clock"></i>
                </div>
                <div class="kpi-info">
                    <h4>Pending Requests</h4>
                    <p><?php echo $pending_count; ?></p>
                </div>
            </div>
            <div class="kpi-card">
                <div class="kpi-icon approved">
                    <i class="fas fa-check-circle"></i>
                </div>
                <div class="kpi-info">
                    <h4>Approved Sessions</h4>
                    <p><?php echo $approved_count; ?></p>
                </div>
            </div>
            <div class="kpi-card">
                <div class="kpi-icon high">
                    <i class="fas fa-exclamation-triangle"></i>
                </div>
                <div class="kpi-info">
                    <h4>High Risk Cases</h4>
                    <p><?php echo $high_risk_count; ?></p>
                </div>
            </div>
            <div class="kpi-card">
                <div class="kpi-icon" style="background: #e8f4fd; color: #3498db;">
                    <i class="fas fa-users"></i>
                </div>
                <div class="kpi-info">
                    <h4>Active Counselors</h4>
                    <p><?php echo count($counselors); ?></p>
                </div>
            </div>
        </div>

        <!-- Filter Section -->
        <div class="filter-section">
            <h3><i class="fas fa-filter"></i> Filter Requests</h3>
            <form method="GET" class="filter-form">
                <div class="filter-group">
                    <label>Status</label>
                    <select name="status">
                        <option value="all" <?php echo $status_filter == 'all' ? 'selected' : ''; ?>>All Status</option>
                        <option value="pending" <?php echo $status_filter == 'pending' ? 'selected' : ''; ?>>Pending</option>
                        <option value="approved" <?php echo $status_filter == 'approved' ? 'selected' : ''; ?>>Approved</option>
                        <option value="completed" <?php echo $status_filter == 'completed' ? 'selected' : ''; ?>>Completed</option>
                        <option value="rejected" <?php echo $status_filter == 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                        <option value="cancelled" <?php echo $status_filter == 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                    </select>
                </div>
                <div class="filter-group">
                    <label>Risk Level</label>
                    <select name="risk">
                        <option value="all" <?php echo $risk_filter == 'all' ? 'selected' : ''; ?>>All Risks</option>
                        <option value="low" <?php echo $risk_filter == 'low' ? 'selected' : ''; ?>>Low</option>
                        <option value="medium" <?php echo $risk_filter == 'medium' ? 'selected' : ''; ?>>Medium</option>
                        <option value="high" <?php echo $risk_filter == 'high' ? 'selected' : ''; ?>>High</option>
                        <option value="critical" <?php echo $risk_filter == 'critical' ? 'selected' : ''; ?>>Critical</option>
                    </select>
                </div>
                <div class="filter-group">
                    <label>From Date</label>
                    <input type="date" name="date_from" value="<?php echo $date_from; ?>">
                </div>
                <div class="filter-group">
                    <label>To Date</label>
                    <input type="date" name="date_to" value="<?php echo $date_to; ?>">
                </div>
                <div class="filter-actions">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-search"></i> Apply Filters
                    </button>
                    <a href="consultations.php" class="btn btn-secondary">
                        <i class="fas fa-times"></i> Clear
                    </a>
                </div>
            </form>
        </div>

        <!-- Consultation Requests Table -->
        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Student</th>
                        <th>Parent</th>
                        <th>Issue Type</th>
                        <th>Risk Level</th>
                        <th>Status</th>
                        <th>Assigned Counselor</th>
                        <th>Requested Date</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($requests)): ?>
                        <tr>
                            <td colspan="9" style="text-align: center; padding: 30px;">
                                <i class="fas fa-inbox" style="font-size: 3rem; color: #ccc; margin-bottom: 10px;"></i>
                                <p>No consultation requests found.</p>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($requests as $request): ?>
                        <tr>
                            <td>#<?php echo $request['id']; ?></td>
                            <td>
                                <strong><?php echo htmlspecialchars($request['student_name']); ?></strong><br>
                                <small><?php echo htmlspecialchars($request['student_email']); ?></small>
                            </td>
                            <td>
                                <?php if ($request['parent_name']): ?>
                                    <strong><?php echo htmlspecialchars($request['parent_name']); ?></strong><br>
                                    <small><?php echo htmlspecialchars($request['parent_email']); ?></small>
                                <?php else: ?>
                                    <span style="color: #999;">Not provided</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="badge" style="background: #e8f4fd; color: #3498db;">
                                    <?php echo ucfirst($request['issue_type']); ?>
                                </span>
                            </td>
                            <td>
                                <span class="badge badge-<?php echo $request['risk_level']; ?>">
                                    <?php echo ucfirst($request['risk_level']); ?>
                                </span>
                            </td>
                            <td>
                                <span class="badge badge-<?php echo $request['status']; ?>">
                                    <?php echo ucfirst($request['status']); ?>
                                </span>
                            </td>
                            <td>
                                <?php if ($request['assigned_counselor_id']): ?>
                                    <strong><?php echo htmlspecialchars($request['counselor_name']); ?></strong><br>
                                    <small><?php echo htmlspecialchars($request['counselor_specialization']); ?></small>
                                <?php else: ?>
                                    <span style="color: #999;">Not assigned</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php echo date('d M Y', strtotime($request['requested_date'])); ?><br>
                                <small><?php echo date('h:i A', strtotime($request['requested_date'])); ?></small>
                            </td>
                            <td>
                                <div class="action-dropdown">
                                    <button class="action-btn">
                                        Actions <i class="fas fa-chevron-down"></i>
                                    </button>
                                    <div class="dropdown-content">
                                        <a href="session-notes.php?consultation_id=<?php echo $request['id']; ?>">
                                            <i class="fas fa-notes-medical"></i> View/Add Notes
                                        </a>
                                        
                                        <?php if ($request['status'] == 'pending'): ?>
                                            <form method="POST" style="display: inline;">
                                                <input type="hidden" name="request_id" value="<?php echo $request['id']; ?>">
                                                <input type="hidden" name="new_status" value="approved">
                                                <button type="submit" name="update_status">
                                                    <i class="fas fa-check-circle"></i> Approve
                                                </button>
                                            </form>
                                            <form method="POST" style="display: inline;">
                                                <input type="hidden" name="request_id" value="<?php echo $request['id']; ?>">
                                                <input type="hidden" name="new_status" value="rejected">
                                                <button type="submit" name="update_status" style="color: #e74c3c;">
                                                    <i class="fas fa-times-circle"></i> Reject
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                        
                                        <?php if ($request['status'] == 'approved'): ?>
                                            <form method="POST" style="display: inline;">
                                                <input type="hidden" name="request_id" value="<?php echo $request['id']; ?>">
                                                <input type="hidden" name="new_status" value="completed">
                                                <button type="submit" name="update_status">
                                                    <i class="fas fa-check-double"></i> Mark Completed
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                        
                                        <?php if (!$request['assigned_counselor_id'] && !empty($counselors)): ?>
                                            <div style="padding: 10px;">
                                                <form method="POST" class="assign-form">
                                                    <input type="hidden" name="request_id" value="<?php echo $request['id']; ?>">
                                                    <select name="counselor_id" required>
                                                        <option value="">Assign Counselor</option>
                                                        <?php foreach ($counselors as $counselor): ?>
                                                            <option value="<?php echo $counselor['id']; ?>">
                                                                <?php echo htmlspecialchars($counselor['name']); ?>
                                                            </option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                    <button type="submit" name="assign_counselor">
                                                        <i class="fas fa-user-plus"></i>
                                                    </button>
                                                </form>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>