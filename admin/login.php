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
<body class="min-h-screen flex items-center justify-center relative overflow-hidden font-sans">

    <!-- 🌌 BACKGROUND IMAGE -->
    <div class="absolute inset-0">
        <img src="https://images.unsplash.com/photo-1522202176988-66273c2fd55f"
             class="w-full h-full object-cover scale-110 blur-sm">
    </div>

    <!-- 🎨 DARK GRADIENT OVERLAY -->
    <div class="absolute inset-0 bg-gradient-to-br from-indigo-900/90 via-purple-900/90 to-black/90"></div>

    <!-- ✨ GLOW EFFECT -->
    <div class="absolute w-[400px] h-[400px] bg-purple-500 rounded-full blur-3xl opacity-20 top-10 left-10"></div>
    <div class="absolute w-[300px] h-[300px] bg-indigo-500 rounded-full blur-3xl opacity-20 bottom-10 right-10"></div>

    <!-- 💎 LOGIN CARD -->
    <div class="relative z-10 w-full max-w-md p-8 rounded-3xl shadow-2xl border border-white/20 backdrop-blur-xl bg-white/10">

        <!-- Header -->
        <div class="text-center mb-6">
            <h2 class="text-3xl font-bold text-white tracking-wide">
                Admin Panel
            </h2>
            <p class="text-gray-300 mt-2 text-sm">
                Secure Login Access
            </p>
        </div>

        <!-- Error -->
        <?php if ($error): ?>
            <div class="bg-red-500/20 border border-red-400 text-red-200 px-4 py-2 rounded-lg mb-4 text-sm text-center">
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <!-- FORM -->
        <form method="POST" onsubmit="return validateForm()" class="space-y-5">

          <!-- EMAIL -->
<div>
    <label class="block text-sm text-gray-300 mb-1">Email</label>
    <div class="relative group">
        <i class="fas fa-envelope absolute top-3 left-3 text-gray-400 group-focus-within:text-indigo-400 transition"></i>
        <input type="email" id="email" name="email"
            class="w-full pl-4 pr-3 py-2.5 rounded-xl bg-white/10 border border-white/20 text-white placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-indigo-400 focus:border-transparent transition"
            placeholder="Enter your email" required>
    </div>
</div>

<!-- PASSWORD -->
<div>
    <label class="block text-sm text-gray-300 mb-1">Password</label>
    <div class="relative group">
        <i class="fas fa-lock absolute top-3 left-3 text-gray-400 group-focus-within:text-indigo-400 transition"></i>
        <input type="password" id="password" name="password"
            class="w-full pl-4 pr-3 py-2.5 rounded-xl bg-white/10 border border-white/20 text-white placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-indigo-400 focus:border-transparent transition"
            placeholder="Enter your password" required>
    </div>
</div>

            <!-- BUTTON -->
            <button type="submit"
                class="w-full py-2.5 rounded-xl bg-gradient-to-r from-indigo-500 via-purple-500 to-pink-500 hover:scale-[1.03] transition transform shadow-lg font-semibold tracking-wide">
                Login →
            </button>

        </form>

        <!-- FOOTER -->
        <p class="mt-6 text-center text-sm text-gray-400">
            Don't have an account?
            <a href="signup.php" class="text-indigo-400 hover:text-indigo-300 font-medium transition">
                Sign up
            </a>
        </p>

    </div>

</body>
</html>