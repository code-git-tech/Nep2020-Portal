<?php
require_once '../includes/auth.php';
require_once '../includes/student-functions.php';
require_once '../includes/auto_publish.php';
requireStudent();

// Run auto-publisher on every page load
autoPublishVideos($pdo);

$user_id = $_SESSION['user_id'];
$message = '';
$error = '';

// Get current user data
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_profile'])) {
        $name = trim($_POST['name']);
        $email = trim($_POST['email']);
        
        // Check if email already exists for other users
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
        $stmt->execute([$email, $user_id]);
        
        if ($stmt->fetch()) {
            $error = 'Email already exists';
        } else {
            $stmt = $pdo->prepare("UPDATE users SET name = ?, email = ? WHERE id = ?");
            if ($stmt->execute([$name, $email, $user_id])) {
                $_SESSION['name'] = $name;
                $message = 'Profile updated successfully';
                // Refresh user data
                $user['name'] = $name;
                $user['email'] = $email;
            } else {
                $error = 'Failed to update profile';
            }
        }
    }
    
    // Handle password change
    if (isset($_POST['change_password'])) {
        $current = $_POST['current_password'];
        $new = $_POST['new_password'];
        $confirm = $_POST['confirm_password'];
        
        if (empty($current) || empty($new) || empty($confirm)) {
            $error = 'All password fields are required';
        } elseif ($new !== $confirm) {
            $error = 'New passwords do not match';
        } elseif (strlen($new) < 6) {
            $error = 'Password must be at least 6 characters';
        } elseif (!password_verify($current, $user['password'])) {
            $error = 'Current password is incorrect';
        } else {
            $hashed = password_hash($new, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
            if ($stmt->execute([$hashed, $user_id])) {
                $message = 'Password changed successfully';
            } else {
                $error = 'Failed to change password';
            }
        }
    }
    
    // Handle profile picture upload
    if (isset($_POST['upload_avatar'])) {
        if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] == 0) {
            $allowed = ['image/jpeg', 'image/png', 'image/gif'];
            if (in_array($_FILES['avatar']['type'], $allowed)) {
                $upload_dir = '../uploads/avatars/';
                if (!file_exists($upload_dir)) {
                    mkdir($upload_dir, 0777, true);
                }
                
                $extension = pathinfo($_FILES['avatar']['name'], PATHINFO_EXTENSION);
                $filename = 'user_' . $user_id . '_' . time() . '.' . $extension;
                $destination = $upload_dir . $filename;
                
                if (move_uploaded_file($_FILES['avatar']['tmp_name'], $destination)) {
                    // Delete old avatar if exists
                    if (!empty($user['avatar']) && file_exists('../' . $user['avatar'])) {
                        unlink('../' . $user['avatar']);
                    }
                    
                    $avatar_path = 'uploads/avatars/' . $filename;
                    $stmt = $pdo->prepare("UPDATE users SET avatar = ? WHERE id = ?");
                    if ($stmt->execute([$avatar_path, $user_id])) {
                        $user['avatar'] = $avatar_path;
                        $message = 'Profile picture updated';
                    }
                }
            } else {
                $error = 'Invalid file type. Only JPG, PNG and GIF are allowed';
            }
        }
    }
}

// Get user statistics
$stats = [
    'courses' => 0,
    'videos' => 0,
    'tests' => 0,
    'certificates' => 0
];

$stmt = $pdo->prepare("SELECT COUNT(*) FROM enrollments WHERE student_id = ? AND status = 'active'");
$stmt->execute([$user_id]);
$stats['courses'] = $stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COUNT(*) FROM video_progress WHERE student_id = ? AND completed = 1");
$stmt->execute([$user_id]);
$stats['videos'] = $stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COUNT(*) FROM test_attempts WHERE student_id = ?");
$stmt->execute([$user_id]);
$stats['tests'] = $stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COUNT(*) FROM certificates WHERE student_id = ?");
$stmt->execute([$user_id]);
$stats['certificates'] = $stmt->fetchColumn();

// Get recent activity
$stmt = $pdo->prepare("
    (SELECT 'video' as type, v.title, vp.last_watched as date
     FROM video_progress vp
     JOIN videos v ON vp.video_id = v.id
     WHERE vp.student_id = ?
     ORDER BY vp.last_watched DESC
     LIMIT 3)
    UNION ALL
    (SELECT 'test' as type, t.title, ta.completed_at as date
     FROM test_attempts ta
     JOIN tests t ON ta.test_id = t.id
     WHERE ta.student_id = ? AND ta.completed_at IS NOT NULL
     ORDER BY ta.completed_at DESC
     LIMIT 3)
    ORDER BY date DESC
    LIMIT 5
");
$stmt->execute([$user_id, $user_id]);
$recent_activity = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-gray-50">
    <div class="flex h-screen">
        <!-- Sidebar -->
        <?php include 'sidebar.php'; ?>

        <!-- Main Content -->
        <div class="flex-1 overflow-auto">
            <!-- Header -->
            <?php include 'header.php'; ?>

            <div class="p-6">
                <!-- Messages -->
                <?php if ($message): ?>
                    <div class="mb-4 bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative">
                        <?= htmlspecialchars($message) ?>
                    </div>
                <?php endif; ?>
                
                <?php if ($error): ?>
                    <div class="mb-4 bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative">
                        <?= htmlspecialchars($error) ?>
                    </div>
                <?php endif; ?>

                <!-- Profile Header -->
                <div class="bg-white rounded-xl shadow-sm p-6 mb-6">
                    <div class="flex items-start space-x-6">
                        <!-- Avatar -->
                        <div class="relative group">
                            <div class="w-24 h-24 bg-gradient-to-r from-blue-500 to-purple-600 rounded-full flex items-center justify-center text-white text-3xl font-bold overflow-hidden">
                                <?php if (!empty($user['avatar'])): ?>
                                    <img src="../<?= htmlspecialchars($user['avatar']) ?>" alt="Avatar" class="w-full h-full object-cover">
                                <?php else: ?>
                                    <?= strtoupper(substr($user['name'], 0, 1)) ?>
                                <?php endif; ?>
                            </div>
                            <form method="POST" enctype="multipart/form-data" class="absolute inset-0 opacity-0 group-hover:opacity-100 transition">
                                <input type="file" name="avatar" accept="image/*" class="absolute inset-0 w-full h-full opacity-0 cursor-pointer" onchange="this.form.submit()">
                                <input type="hidden" name="upload_avatar" value="1">
                                <div class="absolute inset-0 bg-black bg-opacity-50 rounded-full flex items-center justify-center">
                                    <i class="fas fa-camera text-white"></i>
                                </div>
                            </form>
                        </div>
                        
                        <!-- User Info -->
                        <div class="flex-1">
                            <h1 class="text-2xl font-bold text-gray-800"><?= htmlspecialchars($user['name']) ?></h1>
                            <p class="text-gray-500"><?= htmlspecialchars($user['email']) ?></p>
                            <p class="text-sm text-gray-400 mt-1">Member since <?= date('F Y', strtotime($user['created_at'])) ?></p>
                            
                            <!-- Stats Row -->
                            <div class="flex space-x-6 mt-4">
                                <div class="text-center">
                                    <div class="text-xl font-bold text-blue-600"><?= $stats['courses'] ?></div>
                                    <div class="text-xs text-gray-500">Courses</div>
                                </div>
                                <div class="text-center">
                                    <div class="text-xl font-bold text-green-600"><?= $stats['videos'] ?></div>
                                    <div class="text-xs text-gray-500">Videos</div>
                                </div>
                                <div class="text-center">
                                    <div class="text-xl font-bold text-purple-600"><?= $stats['tests'] ?></div>
                                    <div class="text-xs text-gray-500">Tests</div>
                                </div>
                                <div class="text-center">
                                    <div class="text-xl font-bold text-yellow-600"><?= $stats['certificates'] ?></div>
                                    <div class="text-xs text-gray-500">Certificates</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                    <!-- Left Column - Profile Forms -->
                    <div class="lg:col-span-2 space-y-6">
                        <!-- Edit Profile Form -->
                        <div class="bg-white rounded-xl shadow-sm p-6">
                            <h2 class="text-lg font-semibold text-gray-800 mb-4">Edit Profile</h2>
                            <form method="POST">
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-2">Full Name</label>
                                        <input type="text" name="name" value="<?= htmlspecialchars($user['name']) ?>" required
                                               class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-2">Email Address</label>
                                        <input type="email" name="email" value="<?= htmlspecialchars($user['email']) ?>" required
                                               class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                                    </div>
                                </div>
                                <button type="submit" name="update_profile" 
                                        class="mt-4 px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition">
                                    Save Changes
                                </button>
                            </form>
                        </div>

                        <!-- Change Password Form -->
                        <div class="bg-white rounded-xl shadow-sm p-6">
                            <h2 class="text-lg font-semibold text-gray-800 mb-4">Change Password</h2>
                            <form method="POST">
                                <div class="space-y-4">
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-2">Current Password</label>
                                        <input type="password" name="current_password" required
                                               class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-2">New Password</label>
                                        <input type="password" name="new_password" required
                                               class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-2">Confirm New Password</label>
                                        <input type="password" name="confirm_password" required
                                               class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                                    </div>
                                </div>
                                <button type="submit" name="change_password" 
                                        class="mt-4 px-6 py-2 bg-purple-600 text-white rounded-lg hover:bg-purple-700 transition">
                                    Change Password
                                </button>
                            </form>
                        </div>
                    </div>

                    <!-- Right Column - Recent Activity & Stats -->
                    <div class="lg:col-span-1 space-y-6">
                        <!-- Account Summary -->
                        <div class="bg-white rounded-xl shadow-sm p-6">
                            <h2 class="text-lg font-semibold text-gray-800 mb-4">Account Summary</h2>
                            <div class="space-y-3">
                                <div class="flex justify-between py-2 border-b">
                                    <span class="text-gray-600">Account Type</span>
                                    <span class="font-medium text-gray-800">Student</span>
                                </div>
                                <div class="flex justify-between py-2 border-b">
                                    <span class="text-gray-600">Member Since</span>
                                    <span class="font-medium text-gray-800"><?= date('M d, Y', strtotime($user['created_at'])) ?></span>
                                </div>
                                <div class="flex justify-between py-2 border-b">
                                    <span class="text-gray-600">Last Login</span>
                                    <span class="font-medium text-gray-800">Today</span>
                                </div>
                                <div class="flex justify-between py-2">
                                    <span class="text-gray-600">Account Status</span>
                                    <span class="px-2 py-1 bg-green-100 text-green-700 text-xs rounded-full">Active</span>
                                </div>
                            </div>
                        </div>

                        <!-- Recent Activity -->
                        <div class="bg-white rounded-xl shadow-sm p-6">
                            <h2 class="text-lg font-semibold text-gray-800 mb-4">Recent Activity</h2>
                            <?php if (empty($recent_activity)): ?>
                                <p class="text-gray-500 text-sm">No recent activity</p>
                            <?php else: ?>
                                <div class="space-y-3">
                                    <?php foreach ($recent_activity as $activity): ?>
                                    <div class="flex items-start space-x-3">
                                        <div class="w-8 h-8 rounded-full flex items-center justify-center flex-shrink-0
                                            <?= $activity['type'] == 'video' ? 'bg-blue-100' : 'bg-purple-100' ?>">
                                            <i class="fas fa-<?= $activity['type'] == 'video' ? 'play' : 'file-alt' ?> 
                                                <?= $activity['type'] == 'video' ? 'text-blue-600' : 'text-purple-600' ?> text-xs">
                                            </i>
                                        </div>
                                        <div class="flex-1">
                                            <p class="text-sm font-medium text-gray-800"><?= htmlspecialchars($activity['title']) ?></p>
                                            <p class="text-xs text-gray-500">
                                                <?= date('M d, H:i', strtotime($activity['date'])) ?>
                                            </p>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>

                        <!-- Quick Links -->
                        <div class="bg-white rounded-xl shadow-sm p-6">
                            <h2 class="text-lg font-semibold text-gray-800 mb-4">Quick Links</h2>
                            <div class="space-y-2">
                                <a href="courses.php" class="flex items-center p-3 bg-gray-50 rounded-lg hover:bg-gray-100 transition">
                                    <i class="fas fa-book-open text-blue-600 w-6"></i>
                                    <span class="text-gray-700">My Courses</span>
                                </a>
                                <a href="certificates.php" class="flex items-center p-3 bg-gray-50 rounded-lg hover:bg-gray-100 transition">
                                    <i class="fas fa-award text-yellow-600 w-6"></i>
                                    <span class="text-gray-700">My Certificates</span>
                                </a>
                                <a href="tests.php" class="flex items-center p-3 bg-gray-50 rounded-lg hover:bg-gray-100 transition">
                                    <i class="fas fa-file-alt text-purple-600 w-6"></i>
                                    <span class="text-gray-700">Tests & Quizzes</span>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>