<?php
session_start();
require_once '../../config/db.php';
require_once '../../includes/functions.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../login.php');
    exit();
}

// Handle status toggle
if (isset($_GET['toggle_status'])) {
    $counselor_id = $_GET['toggle_status'];
    $counselor = getCounselorById($counselor_id);
    if ($counselor) {
        $new_status = $counselor['status'] == 'active' ? 'inactive' : 'active';
        $pdo->prepare("UPDATE counselors SET status = ? WHERE id = ?")
            ->execute([$new_status, $counselor_id]);
        header('Location: counselors.php?msg=status_updated');
        exit();
    }
}

// Handle deletion
if (isset($_GET['delete'])) {
    $counselor_id = $_GET['delete'];
    if (deleteCounselor($counselor_id)) {
        header('Location: counselors.php?msg=deleted');
    } else {
        header('Location: counselors.php?msg=delete_failed');
    }
    exit();
}

// Get search parameter
$search = isset($_GET['search']) ? $_GET['search'] : '';
$specialization_filter = isset($_GET['specialization']) ? $_GET['specialization'] : '';

// Fetch counselors
$sql = "SELECT * FROM counselors WHERE 1=1";
$params = [];

if (!empty($search)) {
    $sql .= " AND (name LIKE ? OR email LIKE ? OR specialization LIKE ?)";
    $search_term = "%$search%";
    $params = array_merge($params, [$search_term, $search_term, $search_term]);
}

if (!empty($specialization_filter)) {
    $sql .= " AND specialization LIKE ?";
    $params[] = "%$specialization_filter%";
}

$sql .= " ORDER BY name";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$counselors = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get unique specializations for filter
$specializations = $pdo->query("SELECT DISTINCT specialization FROM counselors WHERE specialization IS NOT NULL")
                      ->fetchAll(PDO::FETCH_COLUMN);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Counselor Management | Admin</title>
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

        .action-bar {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 25px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
        }

        .search-form {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        .search-form input,
        .search-form select {
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 0.95rem;
        }

        .search-form input {
            width: 250px;
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

        .counselor-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 20px;
        }

        .counselor-card {
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            overflow: hidden;
            transition: transform 0.3s, box-shadow 0.3s;
        }

        .counselor-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 20px rgba(0,0,0,0.15);
        }

        .card-header {
            padding: 20px;
            background: linear-gradient(135deg, #3498db, #2c3e50);
            color: white;
            position: relative;
        }

        .card-header h3 {
            font-size: 1.3rem;
            margin-bottom: 5px;
        }

        .card-header p {
            font-size: 0.9rem;
            opacity: 0.9;
        }

        .status-badge {
            position: absolute;
            top: 15px;
            right: 15px;
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 0.7rem;
            font-weight: 600;
            text-transform: uppercase;
        }

        .status-badge.active {
            background: #27ae60;
            color: white;
        }

        .status-badge.inactive {
            background: #95a5a6;
            color: white;
        }

        .card-body {
            padding: 20px;
        }

        .info-row {
            display: flex;
            margin-bottom: 10px;
            font-size: 0.95rem;
        }

        .info-label {
            width: 100px;
            color: #666;
            font-weight: 500;
        }

        .info-value {
            flex: 1;
            color: #2c3e50;
        }

        .specialization-tag {
            display: inline-block;
            background: #e8f4fd;
            color: #3498db;
            padding: 4px 8px;
            border-radius: 15px;
            font-size: 0.8rem;
            margin-right: 5px;
            margin-bottom: 5px;
        }

        .workload-badge {
            display: inline-block;
            background: #f39c12;
            color: white;
            padding: 4px 8px;
            border-radius: 15px;
            font-size: 0.8rem;
            font-weight: 600;
        }

        .card-footer {
            padding: 15px 20px;
            background: #f8f9fa;
            border-top: 1px solid #eee;
            display: flex;
            justify-content: flex-end;
            gap: 10px;
        }

        .card-footer a {
            text-decoration: none;
            color: #3498db;
            font-size: 0.9rem;
        }

        .card-footer a:hover {
            text-decoration: underline;
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

        .empty-state {
            text-align: center;
            padding: 50px;
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        .empty-state i {
            font-size: 4rem;
            color: #ccc;
            margin-bottom: 15px;
        }

        .empty-state h3 {
            color: #666;
            margin-bottom: 10px;
        }

        .empty-state p {
            color: #999;
        }
    </style>
</head>
<body>
    

    <div class="main-content">
        <div class="header">
            <h1><i class="fas fa-users"></i> Counselor Management</h1>
            <div class="user-info">
                <span><i class="fas fa-user"></i> <?php echo htmlspecialchars($_SESSION['name']); ?></span>
                <a href="../logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
            </div>
        </div>

        <?php if (isset($_GET['msg'])): ?>
            <?php if ($_GET['msg'] == 'status_updated'): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i> Counselor status updated successfully!
                </div>
            <?php elseif ($_GET['msg'] == 'deleted'): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i> Counselor deleted successfully!
                </div>
            <?php elseif ($_GET['msg'] == 'delete_failed'): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle"></i> Cannot delete counselor with active sessions!
                </div>
            <?php elseif ($_GET['msg'] == 'added'): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i> New counselor added successfully!
                </div>
            <?php elseif ($_GET['msg'] == 'updated'): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i> Counselor updated successfully!
                </div>
            <?php endif; ?>
        <?php endif; ?>

        <!-- Action Bar -->
        <div class="action-bar">
            <form method="GET" class="search-form">
                <input type="text" name="search" placeholder="Search by name, email, specialization..." 
                       value="<?php echo htmlspecialchars($search); ?>">
                <select name="specialization">
                    <option value="">All Specializations</option>
                    <?php foreach ($specializations as $spec): ?>
                        <?php if (!empty($spec)): ?>
                        <option value="<?php echo htmlspecialchars($spec); ?>" 
                                <?php echo $specialization_filter == $spec ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($spec); ?>
                        </option>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </select>
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-search"></i> Search
                </button>
                <a href="counselors.php" class="btn btn-secondary">Clear</a>
            </form>
            <a href="add-counselor.php" class="btn btn-success">
                <i class="fas fa-plus"></i> Add New Counselor
            </a>
        </div>

        <!-- Counselors Grid -->
        <?php if (empty($counselors)): ?>
            <div class="empty-state">
                <i class="fas fa-users-slash"></i>
                <h3>No Counselors Found</h3>
                <p>Try adjusting your search criteria or add a new counselor.</p>
                <a href="add-counselor.php" class="btn btn-success" style="margin-top: 15px;">
                    <i class="fas fa-plus"></i> Add New Counselor
                </a>
            </div>
        <?php else: ?>
            <div class="counselor-grid">
                <?php foreach ($counselors as $counselor): ?>
                <div class="counselor-card">
                    <div class="card-header">
                        <h3><?php echo htmlspecialchars($counselor['name']); ?></h3>
                        <p><?php echo htmlspecialchars($counselor['email']); ?></p>
                        <span class="status-badge <?php echo $counselor['status']; ?>">
                            <?php echo $counselor['status']; ?>
                        </span>
                    </div>
                    <div class="card-body">
                        <div class="info-row">
                            <span class="info-label">Phone:</span>
                            <span class="info-value"><?php echo htmlspecialchars($counselor['phone'] ?? 'Not provided'); ?></span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Specialization:</span>
                            <span class="info-value">
                                <?php 
                                $specializations = explode(',', $counselor['specialization'] ?? '');
                                foreach ($specializations as $spec) {
                                    echo '<span class="specialization-tag">' . htmlspecialchars(trim($spec)) . '</span>';
                                }
                                ?>
                            </span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Experience:</span>
                            <span class="info-value"><?php echo $counselor['experience']; ?> years</span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Availability:</span>
                            <span class="info-value"><?php echo htmlspecialchars($counselor['availability'] ?? 'Not set'); ?></span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Workload:</span>
                            <span class="info-value">
                                <span class="workload-badge">
                                    <?php echo $counselor['assigned_sessions']; ?> active sessions
                                </span>
                            </span>
                        </div>
                    </div>
                    <div class="card-footer">
                        <a href="edit-counselor.php?id=<?php echo $counselor['id']; ?>">
                            <i class="fas fa-edit"></i> Edit
                        </a>
                        <a href="?toggle_status=<?php echo $counselor['id']; ?>" 
                           onclick="return confirm('Toggle status for this counselor?')">
                            <i class="fas fa-toggle-on"></i> Toggle Status
                        </a>
                        <a href="?delete=<?php echo $counselor['id']; ?>" 
                           onclick="return confirm('Are you sure you want to delete this counselor? This action cannot be undone.')"
                           style="color: #e74c3c;">
                            <i class="fas fa-trash"></i> Delete
                        </a>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>