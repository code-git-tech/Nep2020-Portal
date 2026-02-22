<?php
require_once '../config/db.php';
require_once '../includes/auth.php';
require_once '../includes/auto_publish.php';

// Run auto-publisher on every page load
autoPublishVideos($pdo);

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($email) || empty($password)) {
        $error = 'All fields are required.';
    } else {
        if (loginUser($email, $password)) {
            // Check role and redirect
            if ($_SESSION['role'] === 'admin') {
                header('Location: dashboard.php');
            } else {
                // If student tries to login to admin, logout and show error
                logoutUser();
                $error = 'Invalid admin credentials.';
            }
            exit;
        } else {
            $error = 'Invalid email or password.';
        }
    }
}
?>
<!-- Rest of HTML remains same -->
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        function validateForm() {
            const email = document.getElementById('email').value.trim();
            const password = document.getElementById('password').value.trim();
            if (!email || !password) {
                alert('Both fields are required.');
                return false;
            }
            return true;
        }
    </script>
</head>
<body class="min-h-screen bg-gradient-to-r from-indigo-700 via-purple-700 to-indigo-800 flex items-center justify-center">
    <div class="bg-white p-8 rounded shadow-md w-96">
        <h2 class="text-2xl font-bold mb-6 text-center">Admin Login</h2>
        <?php if ($error): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        <form method="POST" onsubmit="return validateForm()">
            <div class="mb-4">
                <label for="email" class="block text-gray-700">Email</label>
                <input type="email" id="email" name="email" class="w-full px-3 py-2 border rounded focus:outline-none focus:ring focus:border-blue-300" required>
            </div>
            <div class="mb-6">
                <label for="password" class="block text-gray-700">Password</label>
                <input type="password" id="password" name="password" class="w-full px-3 py-2 border rounded focus:outline-none focus:ring focus:border-blue-300" required>
            </div>
            <button type="submit" class="w-full bg-blue-500 text-white py-2 rounded hover:bg-blue-600">Login</button>
        </form>
        <p class="mt-4 text-center">Don't have an account? <a href="signup.php" class="text-blue-500">Sign up</a></p>
    </div>
</body>
</html>