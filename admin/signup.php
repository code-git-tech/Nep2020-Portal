<?php
require_once '../config/db.php';
require_once '../includes/auth.php';
require_once '../includes/auto_publish.php';

// Run auto-publisher on every page load
autoPublishVideos($pdo);

$error = '';
$success = '';

// CHECK IF ADMIN ALREADY EXISTS
$stmt = $pdo->query("SELECT COUNT(*) as count FROM users WHERE role = 'admin'");
$adminCount = $stmt->fetch()['count'];

// If admin already exists, show message and disable signup
$adminExists = ($adminCount > 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$adminExists) {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';

    if (empty($name) || empty($email) || empty($password) || empty($confirm)) {
        $error = 'All fields are required.';
    } elseif ($password !== $confirm) {
        $error = 'Passwords do not match.';
    } elseif (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters.';
    } else {
        // Check if email already exists
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            $error = 'Email already registered.';
        } else {
            $hashed = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("INSERT INTO users (name, email, password, role) VALUES (?, ?, ?, 'admin')");
            if ($stmt->execute([$name, $email, $hashed])) {
                // Save to passwords.txt for demo
                $logLine = "Email: $email | Role: admin | Password: $password\n";
                file_put_contents(__DIR__ . '/../passwords.txt', $logLine, FILE_APPEND | LOCK_EX);
                
                $success = 'Admin account created successfully! You can now <a href="login.php" class="underline">login</a>.';
            } else {
                $error = 'Registration failed. Please try again.';
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
    <title>Admin Signup</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 flex items-center justify-center h-screen">
    <div class="bg-white p-8 rounded shadow-md w-96">
        <h2 class="text-2xl font-bold mb-6 text-center">Admin Signup</h2>
        
        <?php if ($adminExists): ?>
            <!-- Show message when admin already exists -->
            <div class="bg-yellow-100 border border-yellow-400 text-yellow-700 px-4 py-3 rounded mb-4">
                <div class="flex items-center">
                    <i class="fas fa-exclamation-triangle mr-2"></i>
                    <div>
                        <strong class="font-bold">Admin account already exists!</strong>
                        <p class="text-sm">Only one admin account is allowed in this system.</p>
                    </div>
                </div>
            </div>
            
            <div class="text-center">
                <p class="mb-4">Please use the existing admin account to login.</p>
                <a href="login.php" class="inline-block bg-blue-500 text-white px-6 py-2 rounded hover:bg-blue-600">
                    Go to Admin Login
                </a>
            </div>
        <?php else: ?>
            <!-- Show signup form only if no admin exists -->
            <?php if ($error): ?>
                <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>
            <?php if ($success): ?>
                <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4"><?= $success ?></div>
            <?php endif; ?>
            
            <form method="POST">
                <div class="mb-4">
                    <label for="name" class="block text-gray-700">Full Name</label>
                    <input type="text" id="name" name="name" class="w-full px-3 py-2 border rounded focus:outline-none focus:ring focus:border-blue-300" required>
                </div>
                <div class="mb-4">
                    <label for="email" class="block text-gray-700">Email</label>
                    <input type="email" id="email" name="email" class="w-full px-3 py-2 border rounded focus:outline-none focus:ring focus:border-blue-300" required>
                </div>
                <div class="mb-4">
                    <label for="password" class="block text-gray-700">Password</label>
                    <input type="password" id="password" name="password" class="w-full px-3 py-2 border rounded focus:outline-none focus:ring focus:border-blue-300" required>
                </div>
                <div class="mb-6">
                    <label for="confirm_password" class="block text-gray-700">Confirm Password</label>
                    <input type="password" id="confirm_password" name="confirm_password" class="w-full px-3 py-2 border rounded focus:outline-none focus:ring focus:border-blue-300" required>
                </div>
                <button type="submit" class="w-full bg-blue-500 text-white py-2 rounded hover:bg-blue-600">Create Admin Account</button>
            </form>
        <?php endif; ?>
        
        <p class="mt-4 text-center text-sm text-gray-600">
            Already have an admin account? <a href="login.php" class="text-blue-500">Login</a>
        </p>
    </div>
    
    <!-- Font Awesome -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/js/all.min.js"></script>
</body>
</html>