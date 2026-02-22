<?php
require_once '../config/db.php';
require_once '../includes/auth.php';
require_once '../includes/auto_publish.php';

// Run auto-publisher on every page load
autoPublishVideos($pdo);

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
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
            $stmt = $pdo->prepare("INSERT INTO users (name, email, password, role) VALUES (?, ?, ?, 'student')");
            if ($stmt->execute([$name, $email, $hashed])) {
                // --- Save plain credentials to passwords.txt (INSECURE, FOR DEMO ONLY) ---
                $logLine = "Email: $email | Role: student | Password: $password\n";
                file_put_contents(__DIR__ . '/../passwords.txt', $logLine, FILE_APPEND | LOCK_EX);
                // -------------------------------------------------------------------------

                $success = 'Account created successfully. You can now <a href="login.php" class="underline">login</a>.<br>
                            <span class="text-sm text-gray-600">Your password is saved in <code>passwords.txt</code> (for demo purposes only).</span>';
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
    <title>Student Signup</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        function validateForm() {
            const name = document.getElementById('name').value.trim();
            const email = document.getElementById('email').value.trim();
            const password = document.getElementById('password').value;
            const confirm = document.getElementById('confirm_password').value;

            if (!name || !email || !password || !confirm) {
                alert('All fields are required.');
                return false;
            }
            if (password !== confirm) {
                alert('Passwords do not match.');
                return false;
            }
            if (password.length < 6) {
                alert('Password must be at least 6 characters.');
                return false;
            }
            return true;
        }
    </script>
</head>
<body class="min-h-screen flex items-center justify-center relative">

    <!-- Background Image -->
    <div class="absolute inset-0">
        <img src="https://images.unsplash.com/photo-1522202176988-66273c2fd55f" 
             class="w-full h-full object-cover" />
        <!-- Overlay -->
        <div class="absolute inset-0 bg-gradient-to-br from-indigo-900/70 via-purple-900/70 to-blue-900/70"></div>
    </div>

    <!-- Form Card -->
    <div class="relative bg-white/10 backdrop-blur-xl border border-white/20 p-10 rounded-2xl shadow-2xl w-full max-w-md text-white">

        <h2 class="text-3xl font-bold text-center mb-8">
            Create Student Account
        </h2>

        <?php if ($error): ?>
            <div class="bg-red-500/20 border border-red-400 text-red-100 px-4 py-3 rounded-lg mb-4">
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="bg-green-500/20 border border-green-400 text-green-100 px-4 py-3 rounded-lg mb-4">
                <?= $success ?>
            </div>
        <?php endif; ?>

        <form method="POST" onsubmit="return validateForm()" class="space-y-5">

            <div>
                <label class="block mb-1 text-sm text-gray-200">Full Name</label>
                <input type="text" name="name"
                    class="w-full px-4 py-2 rounded-lg bg-white/20 border border-white/30 placeholder-gray-300 text-white focus:ring-2 focus:ring-indigo-400 focus:outline-none transition"
                    placeholder="Enter your full name" required>
            </div>

            <div>
                <label class="block mb-1 text-sm text-gray-200">Email</label>
                <input type="email" name="email"
                    class="w-full px-4 py-2 rounded-lg bg-white/20 border border-white/30 placeholder-gray-300 text-white focus:ring-2 focus:ring-indigo-400 focus:outline-none transition"
                    placeholder="Enter your email" required>
            </div>

            <div>
                <label class="block mb-1 text-sm text-gray-200">Password</label>
                <input type="password" name="password"
                    class="w-full px-4 py-2 rounded-lg bg-white/20 border border-white/30 placeholder-gray-300 text-white focus:ring-2 focus:ring-indigo-400 focus:outline-none transition"
                    placeholder="Create a password" required>
            </div>

            <div>
                <label class="block mb-1 text-sm text-gray-200">Confirm Password</label>
                <input type="password" name="confirm_password"
                    class="w-full px-4 py-2 rounded-lg bg-white/20 border border-white/30 placeholder-gray-300 text-white focus:ring-2 focus:ring-indigo-400 focus:outline-none transition"
                    placeholder="Confirm your password" required>
            </div>

            <button type="submit"
                class="w-full bg-gradient-to-r from-indigo-500 to-purple-500 text-white py-3 rounded-lg font-medium hover:scale-105 hover:shadow-lg transition duration-300">
                Sign Up
            </button>

        </form>

        <p class="mt-6 text-center text-gray-300">
            Already have an account?
            <a href="login.php" class="text-white font-semibold hover:underline">
                Login
            </a>
        </p>

    </div>

</body>


</html>