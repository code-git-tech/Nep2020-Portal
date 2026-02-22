<?php
require_once '../config/db.php';
require_once '../includes/auth.php';
require_once '../includes/auto_publish.php';

// Run auto-publisher on every page load
autoPublishVideos($pdo);

$error = '';
$success = isset($_GET['registered']) ? 'Registration successful! Please login.' : '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $remember = isset($_POST['remember']);

    if (empty($email) || empty($password)) {
        $error = 'All fields are required.';
    } else {
        if (loginUser($email, $password, $remember)) {
            // Check role and redirect
            if ($_SESSION['role'] === 'student') {
                header('Location: dashboard.php');
            } else {
                // If admin tries to login to student portal, logout and show error
                logoutUser();
                $error = 'Invalid student credentials.';
            }
            exit;
        } else {
            $error = 'Invalid email or password.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Login | EduPortal</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap');
        
        * {
            font-family: 'Inter', sans-serif;
        }
        
        .gradient-bg {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
        
        .hero-gradient {
            background: radial-gradient(circle at 10% 20%, rgba(102, 126, 234, 0.1) 0%, rgba(118, 75, 162, 0.1) 90%);
        }
        
        .floating {
            animation: floating 3s ease-in-out infinite;
        }
        
        @keyframes floating {
            0% { transform: translateY(0px); }
            50% { transform: translateY(-10px); }
            100% { transform: translateY(0px); }
        }
        
        .pulse {
            animation: pulse 2s infinite;
        }
        
        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.05); }
            100% { transform: scale(1); }
        }
    </style>
</head>
<body class="min-h-screen relative text-white">

    <!-- 🌄 Background -->
    <div class="absolute inset-0 -z-10">
        <img src="https://images.unsplash.com/photo-1516321318423-f06f85e504b3"
             class="w-full h-full object-cover" />
        <div class="absolute inset-0 bg-gradient-to-br from-blue-900/80 via-purple-900/80 to-indigo-900/80"></div>
    </div>

    <!-- 🔝 Navbar (Glass Effect) -->
    <nav class="fixed top-0 w-full z-50 bg-white/10 backdrop-blur-xl border-b border-white/20 shadow-lg">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between items-center h-16">

                <div class="flex items-center">
                    <div class="w-8 h-8 bg-gradient-to-r from-blue-500 to-indigo-500 rounded-lg flex items-center justify-center">
                        <i class="fas fa-graduation-cap text-white text-sm"></i>
                    </div>
                    <span class="ml-2 text-xl font-bold">EduPortal</span>
                </div>

                <div class="flex items-center space-x-4">
                    <a href="signup.php" class="text-gray-200 hover:text-white font-medium">Sign Up</a>
                    <a href="login.php" class="bg-gradient-to-r from-blue-500 to-indigo-500 px-4 py-2 rounded-lg hover:scale-105 transition font-medium shadow-lg">
                        Login
                    </a>
                </div>

            </div>
        </div>
    </nav>

    <!-- 🚀 Main -->
    <div class="pt-20">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12 lg:py-20">

            <div class="grid lg:grid-cols-2 gap-12 items-center">

                <!-- LEFT -->
                <div class="space-y-8">

                    <span class="inline-block px-4 py-2 bg-white/20 backdrop-blur-md rounded-full text-sm font-semibold">
                        🎓 Online Learning Platform
                    </span>

                    <h1 class="text-4xl lg:text-5xl font-extrabold leading-tight">
                        Your Smart Learning
                        <span class="block bg-gradient-to-r from-blue-400 to-indigo-400 bg-clip-text text-transparent">
                            Companion
                        </span>
                    </h1>

                    <p class="text-lg text-gray-200">
                        Practice quizzes, mock tests, flashcards, and coaching — all in one platform
                    </p>

                    <!-- CTA -->
                    <div class="flex gap-4">
                        <a href="signup.php"
                           class="px-8 py-4 bg-gradient-to-r from-blue-500 to-indigo-500 rounded-xl font-semibold hover:scale-105 transition shadow-xl">
                            Start Free Trial
                        </a>

                        <a href="#"
                           class="px-8 py-4 bg-white/10 border border-white/30 rounded-xl font-semibold hover:bg-white/20 transition">
                            Subscribe Now
                        </a>
                    </div>

                </div>

                <!-- RIGHT (Glass Login Card) -->
                <div class="relative">

                    <!-- blobs -->
                    <div class="absolute -top-10 -right-10 w-64 h-64 bg-blue-500 rounded-full blur-3xl opacity-30 animate-blob"></div>
                    <div class="absolute -bottom-10 -left-10 w-64 h-64 bg-purple-500 rounded-full blur-3xl opacity-30 animate-blob animation-delay-2000"></div>

                    <div class="relative bg-white/10 backdrop-blur-xl border border-white/20 rounded-2xl shadow-2xl p-8 h-200 flex flex-col justify-center w-full max-w-md mx-auto">

                        <div class="text-center mb-8">
                            <h2 class="text-3xl font-bold">Welcome Back</h2>
                            <p class="text-gray-300">Login to continue</p> 
                        </div>

                        <?php if ($error): ?>
                            <div class="bg-red-500/20 border border-red-400 text-red-100 px-4 py-3 rounded-lg mb-6">
                                <?= htmlspecialchars($error) ?>
                            </div>
                        <?php endif; ?>

                        <?php if ($success): ?>
                            <div class="bg-green-500/20 border border-green-400 text-green-100 px-4 py-3 rounded-lg mb-6">
                                <?= htmlspecialchars($success) ?>
                            </div>
                        <?php endif; ?>

                        <form method="POST" class="space-y-5">

                            <input type="email" name="email" required
                                   placeholder="Email"
                                   class="w-full px-4 py-3 rounded-lg bg-white/20 border border-white/30 placeholder-gray-300 text-white focus:ring-2 focus:ring-blue-400 outline-none">

                            <input type="password" name="password" required
                                   placeholder="Password"
                                   class="w-full px-4 py-3 rounded-lg bg-white/20 border border-white/30 placeholder-gray-300 text-white focus:ring-2 focus:ring-blue-400 outline-none">

                            <button type="submit"
                                    class="w-full bg-gradient-to-r from-blue-500 to-indigo-500 py-3 rounded-lg font-semibold hover:scale-105 transition shadow-lg">
                                Login
                            </button>

                        </form>

                        <p class="mt-6 text-center text-gray-300">
                            Don’t have an account?
                            <a href="signup.php" class="text-white font-semibold hover:underline">Sign up</a>
                        </p>

                    </div>
                </div>

            </div>
        </div>
    </div>

    <!-- ✨ Animation -->
    <style>
        @keyframes blob {
            0%,100% { transform: scale(1); }
            33% { transform: scale(1.1); }
            66% { transform: scale(0.9); }
        }
        .animate-blob { animation: blob 8s infinite; }
        .animation-delay-2000 { animation-delay: 2s; }
    </style>

</body>
</html