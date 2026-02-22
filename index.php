<?php
session_start();

if (isset($_SESSION['user_id']) && isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true) {
    if ($_SESSION['role'] === 'admin') {
        header('Location: admin/dashboard.php');
    } else {
        header('Location: student/dashboard.php');
    }
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>LMS Portal</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="min-h-screen flex items-center justify-center relative">

    <!-- Background Image -->
    <div class="absolute inset-0">
        <img src="https://images.unsplash.com/photo-1509062522246-3755977927d7"
             class="w-full h-full object-cover" />
        <!-- Overlay -->
        <div class="absolute inset-0 bg-gradient-to-br from-blue-900/70 via-purple-900/70 to-indigo-900/70"></div>
    </div>

    <!-- Card -->
    <div class="relative bg-white/10 backdrop-blur-xl border border-white/20 p-10 rounded-2xl shadow-2xl w-full max-w-sm text-white">

        <!-- Heading -->
        <div class="text-center mb-8">
            <h1 class="text-3xl font-bold">Welcome to LMS</h1>
            <p class="text-gray-300 mt-2">Choose your portal</p>
        </div>
        
        <!-- Buttons -->
        <div class="space-y-4">

            <a href="admin/login.php"
               class="flex items-center justify-center w-full bg-gradient-to-r from-blue-500 to-indigo-500 py-3 rounded-lg font-medium hover:scale-105 hover:shadow-lg transition duration-300">
                <i class="fas fa-user-shield mr-2"></i> Admin Login
            </a>

            <a href="student/login.php"
               class="flex items-center justify-center w-full bg-gradient-to-r from-green-500 to-emerald-500 py-3 rounded-lg font-medium hover:scale-105 hover:shadow-lg transition duration-300">
                <i class="fas fa-user-graduate mr-2"></i> Student Login
            </a>

            <a href="student/signup.php"
               class="flex items-center justify-center w-full bg-white/20 border border-white/30 py-3 rounded-lg font-medium hover:bg-white/30 transition duration-300">
                <i class="fas fa-user-plus mr-2"></i> Student Registration
            </a>

        </div>
        
        <!-- Footer -->
        <div class="mt-6 text-center text-sm text-gray-300">
            <p>Learning Management System</p>
        </div>

    </div>
    
    <!-- Font Awesome -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/js/all.min.js"></script>

</body>

</html>