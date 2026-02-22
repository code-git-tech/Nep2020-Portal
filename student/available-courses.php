<?php
require_once '../includes/auth.php';
require_once '../includes/student-functions.php';
require_once '../includes/auto_publish.php';
requireStudent();

// Run auto-publisher on every page load
autoPublishVideos($pdo);

$user_id = $_SESSION['user_id'];

// Get available courses
$available_courses = getAvailableCourses($user_id);

// Handle enrollment
if (isset($_POST['enroll'])) {
    $course_id = $_POST['course_id'];
    if (enrollInCourse($user_id, $course_id)) {
        $_SESSION['success_message'] = 'Successfully enrolled in course!';
        header('Location: courses.php');
        exit;
    } else {
        $error = 'Failed to enroll in course. Please try again.';
    }
}

// Check for success message
$success_message = $_SESSION['success_message'] ?? null;
unset($_SESSION['success_message']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Available Courses - Student Portal</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap');
        
        * {
            font-family: 'Inter', sans-serif;
        }
        
        /* Gradient Animations */
        @keyframes gradient {
            0% { background-position: 0% 50%; }
            50% { background-position: 100% 50%; }
            100% { background-position: 0% 50%; }
        }
        
        .animate-gradient {
            background-size: 200% 200%;
            animation: gradient 15s ease infinite;
        }
        
        /* Floating Animation */
        @keyframes float {
            0% { transform: translateY(0px); }
            50% { transform: translateY(-10px); }
            100% { transform: translateY(0px); }
        }
        
        .animate-float {
            animation: float 3s ease-in-out infinite;
        }
        
        /* Glow Effect */
        .glow-effect {
            transition: all 0.3s ease;
        }
        
        .glow-effect:hover {
            filter: drop-shadow(0 0 15px rgba(59, 130, 246, 0.5));
        }
        
        /* Card Hover Effects */
        .course-card {
            transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
        }
        
        .course-card:hover {
            transform: translateY(-10px) scale(1.02);
        }
        
        /* Shimmer Effect */
        .shimmer {
            background: linear-gradient(
                90deg,
                transparent,
                rgba(255, 255, 255, 0.2),
                transparent
            );
            background-size: 200% 100%;
            animation: shimmer 2s infinite;
        }
        
        @keyframes shimmer {
            0% { background-position: -200% 0; }
            100% { background-position: 200% 0; }
        }
        
        /* Particle Background */
        .particle-bg {
            position: relative;
            overflow: hidden;
        }
        
        .particle-bg::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(59,130,246,0.1) 0%, transparent 50%);
            animation: rotate 20s linear infinite;
        }
        
        @keyframes rotate {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }
    </style>
</head>
<body class="bg-gradient-to-br from-gray-50 via-blue-50 to-indigo-50">
    <div class="flex h-screen">
        <?php include 'sidebar.php'; ?>

        <div class="flex-1 overflow-auto relative">
            <!-- Animated Background -->
            <div class="fixed inset-0 pointer-events-none">
                <div class="absolute top-0 left-0 w-full h-full">
                    <div class="absolute top-20 left-20 w-72 h-72 bg-blue-300 rounded-full mix-blend-multiply filter blur-3xl opacity-20 animate-float"></div>
                    <div class="absolute bottom-20 right-20 w-96 h-96 bg-purple-300 rounded-full mix-blend-multiply filter blur-3xl opacity-20 animate-float" style="animation-delay: 2s;"></div>
                    <div class="absolute top-40 right-40 w-80 h-80 bg-indigo-300 rounded-full mix-blend-multiply filter blur-3xl opacity-20 animate-float" style="animation-delay: 4s;"></div>
                </div>
            </div>

            <?php include 'header.php'; ?>

            <div class="p-8 relative z-10">
                <!-- Modern Breadcrumb with Icons -->
                <div class="mb-8">
                    <div class="flex items-center space-x-2 text-sm">
                        <a href="dashboard.php" class="flex items-center text-gray-500 hover:text-blue-600 transition bg-white/50 backdrop-blur px-3 py-1.5 rounded-full">
                            <i class="fas fa-home mr-1"></i>
                            Dashboard
                        </a>
                        <i class="fas fa-chevron-right text-gray-400 text-xs"></i>
                        <a href="courses.php" class="flex items-center text-gray-500 hover:text-blue-600 transition bg-white/50 backdrop-blur px-3 py-1.5 rounded-full">
                            <i class="fas fa-book-open mr-1"></i>
                            My Courses
                        </a>
                        <i class="fas fa-chevron-right text-gray-400 text-xs"></i>
                        <span class="flex items-center text-blue-600 bg-blue-50 px-3 py-1.5 rounded-full">
                            <i class="fas fa-compass mr-1"></i>
                            Available Courses
                        </span>
                    </div>
                </div>

                <!-- Hero Section with Stats -->
              <!-- Hero Section - Compact Version -->
<div class="relative mb-8">
    <div class="bg-gradient-to-r from-blue-600 via-indigo-600 to-purple-600 rounded-2xl shadow-xl overflow-hidden">
        <div class="absolute inset-0 bg-black opacity-5 shimmer"></div>
        <div class="relative p-6">
            <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between">
                <div>
                    <div class="flex items-center space-x-2 mb-3">
                        <span class="px-2 py-0.5 bg-white/20 backdrop-blur rounded-full text-white text-xs">
                            <i class="fas fa-rocket mr-1 text-xs"></i> Explore
                        </span>
                        <span class="px-2 py-0.5 bg-white/20 backdrop-blur rounded-full text-white text-xs">
                            <i class="fas fa-infinity mr-1 text-xs"></i> Self-Paced
                        </span>
                    </div>
                    <h1 class="text-2xl font-bold text-white mb-1">Available Courses</h1>
                    <p class="text-blue-100 text-sm max-w-xl">Discover new skills with our curated courses</p>
                </div>
                
                <!-- Live Stats - Compact -->
                <div class="mt-4 lg:mt-0 flex items-center space-x-4">
                    <div class="text-center">
                        <div class="text-xl font-bold text-white"><?= count($available_courses) ?></div>
                        <div class="text-blue-200 text-xs">New Courses</div>
                    </div>
                    <div class="w-px h-8 bg-white/20"></div>
                    <div class="text-center">
                        <div class="text-xl font-bold text-white">24/7</div>
                        <div class="text-blue-200 text-xs">Support</div>
                    </div>
                    <div class="w-px h-8 bg-white/20"></div>
                    <div class="text-center">
                        <div class="text-xl font-bold text-white">Free</div>
                        <div class="text-blue-200 text-xs">Access</div>
                    </div>
                </div>
            </div>
            
            <!-- Search Bar - Compact -->
            <div class="mt-4 max-w-xl">
                <div class="flex items-center bg-white/10 backdrop-blur-lg rounded-xl border border-white/20 p-0.5">
                    <div class="flex-1 flex items-center px-3">
                        <i class="fas fa-search text-white/60 mr-2 text-sm"></i>
                        <input type="text" placeholder="Search courses..." class="w-full bg-transparent text-white placeholder-white/60 text-sm focus:outline-none py-2">
                    </div>
                    <button class="px-4 py-2 bg-white text-blue-600 rounded-lg text-sm font-semibold hover:shadow-md transition transform hover:scale-105">
                        <i class="fas fa-filter mr-1 text-xs"></i>Filter
                    </button>
                </div>
            </div>
        </div>
        
        <!-- Decorative Elements - Smaller -->
        <div class="absolute top-0 right-0 -mt-6 -mr-6 w-24 h-24 bg-white/10 rounded-full blur-xl"></div>
        <div class="absolute bottom-0 left-0 -mb-6 -ml-6 w-24 h-24 bg-purple-400/20 rounded-full blur-xl"></div>
    </div>
</div>

                <!-- Success/Error Messages with Animation -->
                <?php if ($success_message): ?>
                <div class="mb-6 transform transition-all duration-500 animate-slideDown">
                    <div class="bg-gradient-to-r from-green-500 to-emerald-500 text-white px-6 py-4 rounded-2xl shadow-xl flex items-center">
                        <div class="w-10 h-10 bg-white/20 rounded-full flex items-center justify-center mr-4">
                            <i class="fas fa-check-circle text-xl"></i>
                        </div>
                        <div class="flex-1">
                            <p class="font-semibold">Success!</p>
                            <p class="text-green-100 text-sm"><?= htmlspecialchars($success_message) ?></p>
                        </div>
                        <button class="text-white/80 hover:text-white transition">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                </div>
                <?php endif; ?>

                <?php if (isset($error)): ?>
                <div class="mb-6 transform transition-all duration-500 animate-slideDown">
                    <div class="bg-gradient-to-r from-red-500 to-pink-500 text-white px-6 py-4 rounded-2xl shadow-xl flex items-center">
                        <div class="w-10 h-10 bg-white/20 rounded-full flex items-center justify-center mr-4">
                            <i class="fas fa-exclamation-circle text-xl"></i>
                        </div>
                        <div class="flex-1">
                            <p class="font-semibold">Error!</p>
                            <p class="text-red-100 text-sm"><?= htmlspecialchars($error) ?></p>
                        </div>
                        <button class="text-white/80 hover:text-white transition">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                </div>
                <?php endif; ?>

                <?php if (empty($available_courses)): ?>
                    <!-- Empty State with Animation -->
                    <div class="bg-gradient-to-r from-yellow-400 to-emerald-500 text-white/90
                    backdrop-blur-xl rounded-3xl shadow-2xl p-12 text-center border border-white/50">
                        <div class="relative inline-block">
                            <div class="w-32 h-32 bg-gradient-to-r from-green-400 to-emerald-500 rounded-full mx-auto mb-6 flex items-center justify-center animate-float">
                                <i class="fas fa-check-circle text-white text-5xl"></i>
                            </div>
                            <div class="absolute -top-2 -right-2 w-12 h-12 bg-yellow-400 rounded-full flex items-center justify-center text-white font-bold animate-pulse">
                                <i class="fas fa-star"></i>
                            </div>
                        </div>
                        
                        <h3 class="text-3xl font-bold text-gray-800 mb-3">All Caught Up! 🎉</h3>
                        <p class="text-gray-600 text-lg mb-8 max-w-md mx-auto">You're enrolled in all available courses. Check back later for exciting new courses!</p>
                        
                        <?php
                        $enrolled = getEnrolledCourses($user_id);
                        if (!empty($enrolled)):
                        ?>
                        <div class="max-w-2xl mx-auto bg-gradient-to-r from-blue-50 to-indigo-50 p-6 rounded-2xl mb-8">
                            <p class="font-semibold text-gray-700 mb-4 flex items-center">
                                <i class="fas fa-graduation-cap text-blue-600 mr-2"></i>
                                Your Learning Journey:
                            </p>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <?php foreach ($enrolled as $course): ?>
                                <div class="bg-white rounded-xl p-4 shadow-md hover:shadow-xl transition group">
                                    <div class="flex items-center">
                                        <div class="w-10 h-10 bg-gradient-to-r from-blue-500 to-indigo-500 rounded-lg flex items-center justify-center text-white mr-3 group-hover:scale-110 transition">
                                            <i class="fas fa-book-open"></i>
                                        </div>
                                        <div>
                                            <p class="font-medium text-gray-800"><?= htmlspecialchars($course['title']) ?></p>
                                            <p class="text-xs text-gray-500">In Progress</p>
                                        </div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <div class="flex justify-center space-x-4">
                            <a href="courses.php" class="px-8 py-4 bg-gradient-to-r from-blue-600 to-indigo-700 text-white rounded-xl hover:shadow-xl transition transform hover:scale-105 font-semibold">
                                <i class="fas fa-book-open mr-2"></i>
                                Continue Learning
                            </a>
                            <a href="dashboard.php" class="px-8 py-4 bg-white text-gray-700 rounded-xl hover:shadow-xl transition transform hover:scale-105 font-semibold border border-gray-200">
                                <i class="fas fa-home mr-2"></i>
                                Dashboard
                            </a>
                        </div>
                    </div>
                <?php else: ?>
                    <!-- Quick Stats Cards -->
                    <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
                        <div class="bg-white/80 backdrop-blur rounded-2xl p-6 shadow-xl border border-white/50 hover:scale-105 transition">
                            <div class="flex items-center justify-between">
                                <div>
                                    <p class="text-gray-500 text-sm">Total Courses</p>
                                    <p class="text-3xl font-bold text-gray-800"><?= count($available_courses) ?></p>
                                </div>
                                <div class="w-12 h-12 bg-gradient-to-r from-blue-500 to-blue-600 rounded-xl flex items-center justify-center text-white text-xl">
                                    <i class="fas fa-book"></i>
                                </div>
                            </div>
                        </div>
                        
                        <div class="bg-white/80 backdrop-blur rounded-2xl p-6 shadow-xl border border-white/50 hover:scale-105 transition">
                            <div class="flex items-center justify-between">
                                <div>
                                    <p class="text-gray-500 text-sm">Learning Hours</p>
                                    <p class="text-3xl font-bold text-gray-800">120+</p>
                                </div>
                                <div class="w-12 h-12 bg-gradient-to-r from-green-500 to-green-600 rounded-xl flex items-center justify-center text-white text-xl">
                                    <i class="fas fa-clock"></i>
                                </div>
                            </div>
                        </div>
                        
                        <div class="bg-white/80 backdrop-blur rounded-2xl p-6 shadow-xl border border-white/50 hover:scale-105 transition">
                            <div class="flex items-center justify-between">
                                <div>
                                    <p class="text-gray-500 text-sm">Active Students</p>
                                    <p class="text-3xl font-bold text-gray-800">2.5k+</p>
                                </div>
                                <div class="w-12 h-12 bg-gradient-to-r from-purple-500 to-purple-600 rounded-xl flex items-center justify-center text-white text-xl">
                                    <i class="fas fa-users"></i>
                                </div>
                            </div>
                        </div>
                        
                        <div class="bg-white/80 backdrop-blur rounded-2xl p-6 shadow-xl border border-white/50 hover:scale-105 transition">
                            <div class="flex items-center justify-between">
                                <div>
                                    <p class="text-gray-500 text-sm">Certificates</p>
                                    <p class="text-3xl font-bold text-gray-800">50+</p>
                                </div>
                                <div class="w-12 h-12 bg-gradient-to-r from-orange-500 to-orange-600 rounded-xl flex items-center justify-center text-white text-xl">
                                    <i class="fas fa-certificate"></i>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Category Filters -->
                    <div class="mb-8 flex flex-wrap gap-3">
                        <button class="px-6 py-3 bg-blue-600 text-white rounded-xl font-semibold hover:shadow-lg transition transform hover:scale-105">
                            <i class="fas fa-th-large mr-2"></i>All Courses
                        </button>
                        <button class="px-6 py-3 bg-white text-gray-700 rounded-xl font-semibold hover:shadow-lg transition transform hover:scale-105 border border-gray-200">
                            <i class="fab fa-python mr-2"></i>Programming
                        </button>
                        <button class="px-6 py-3 bg-white text-gray-700 rounded-xl font-semibold hover:shadow-lg transition transform hover:scale-105 border border-gray-200">
                            <i class="fas fa-chart-line mr-2"></i>Data Science
                        </button>
                        <button class="px-6 py-3 bg-white text-gray-700 rounded-xl font-semibold hover:shadow-lg transition transform hover:scale-105 border border-gray-200">
                            <i class="fas fa-robot mr-2"></i>AI & ML
                        </button>
                        <button class="px-6 py-3 bg-white text-gray-700 rounded-xl font-semibold hover:shadow-lg transition transform hover:scale-105 border border-gray-200">
                            <i class="fas fa-lock mr-2"></i>Cybersecurity
                        </button>
                    </div>

                    <!-- Courses Grid with Enhanced Cards -->
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
                        <?php foreach ($available_courses as $index => $course): ?>
                        <div class="course-card group relative">
                            <!-- Card Glow Effect -->
                            <div class="absolute -inset-0.5 bg-gradient-to-r from-blue-600 to-purple-600 rounded-3xl opacity-0 group-hover:opacity-100 transition duration-500 blur-xl"></div>
                            
                            <!-- Main Card -->
                            <div class="relative bg-white rounded-2xl shadow-xl overflow-hidden border border-gray-100">
                                <!-- Card Header with Gradient -->
                                <div class="h-48 bg-gradient-to-br from-blue-600 via-indigo-600 to-purple-600 relative overflow-hidden">
                                    <div class="absolute inset-0 bg-black opacity-10"></div>
                                    
                                    <!-- Animated Pattern -->
                                    <div class="absolute inset-0 opacity-30">
                                        <svg class="absolute inset-0 w-full h-full" xmlns="http://www.w3.org/2000/svg">
                                            <defs>
                                                <pattern id="grid-<?= $index ?>" width="40" height="40" patternUnits="userSpaceOnUse">
                                                    <path d="M 40 0 L 0 0 0 40" fill="none" stroke="rgba(255,255,255,0.1)" stroke-width="1"/>
                                                </pattern>
                                            </defs>
                                            <rect width="100%" height="100%" fill="url(#grid-<?= $index ?>)"/>
                                        </svg>
                                    </div>
                                    
                                    <!-- Badges -->
                                    <div class="absolute top-4 right-4 flex space-x-2">
                                        <span class="bg-gradient-to-r from-yellow-400 to-yellow-500 text-yellow-900 text-xs font-bold px-3 py-1.5 rounded-full shadow-lg">
                                            <i class="fas fa-star mr-1"></i>NEW
                                        </span>
                                        <?php if ($index % 3 == 0): ?>
                                        <span class="bg-gradient-to-r from-green-400 to-green-500 text-white text-xs font-bold px-3 py-1.5 rounded-full shadow-lg">
                                            <i class="fas fa-fire mr-1"></i>HOT
                                        </span>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <!-- Course Icon -->
                                    <div class="absolute bottom-4 left-4">
                                        <div class="flex items-center space-x-2">
                                            <?php
                                            $icons = [
                                                '<i class="fab fa-python text-2xl"></i>',
                                                '<i class="fas fa-chart-line text-2xl"></i>',
                                                '<i class="fas fa-robot text-2xl"></i>',
                                                '<i class="fas fa-database text-2xl"></i>',
                                                '<i class="fas fa-cloud text-2xl"></i>'
                                            ];
                                            ?>
                                            <div class="w-10 h-10 bg-white/20 backdrop-blur rounded-xl flex items-center justify-center text-white">
                                                <?= $icons[$index % count($icons)] ?>
                                            </div>
                                            <span class="text-white text-sm font-medium">Level <?= $index + 1 ?></span>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Card Body -->
                                <div class="p-6">
                                    <div class="flex items-center justify-between mb-3">
                                        <span class="text-xs font-semibold px-3 py-1 bg-blue-100 text-blue-600 rounded-full">
                                            <i class="far fa-clock mr-1"></i><?= rand(4, 12) ?> weeks
                                        </span>
                                        <span class="text-xs font-semibold px-3 py-1 bg-green-100 text-green-600 rounded-full">
                                            <i class="fas fa-signal mr-1"></i>Beginner
                                        </span>
                                    </div>
                                    
                                    <h3 class="font-bold text-xl text-gray-800 mb-2 group-hover:text-blue-600 transition line-clamp-1">
                                        <?= htmlspecialchars($course['title']) ?>
                                    </h3>
                                    
                                    <p class="text-gray-600 text-sm mb-4 line-clamp-2">
                                        <?= htmlspecialchars(substr($course['description'] ?? 'Master the fundamentals and advance your skills with this comprehensive course designed for beginners.', 0, 100)) ?>...
                                    </p>
                                    
                                    <!-- Instructor & Rating -->
                                    <div class="flex items-center justify-between mb-4">
                                        <div class="flex items-center">
                                            <div class="w-8 h-8 rounded-full bg-gradient-to-r from-gray-400 to-gray-500 flex items-center justify-center text-white text-xs font-bold">
                                                <?= substr($course['instructor'] ?? 'EX', 0, 2) ?>
                                            </div>
                                            <div class="ml-2">
                                                <p class="text-xs text-gray-500">Instructor</p>
                                                <p class="text-sm font-medium text-gray-700"><?= htmlspecialchars($course['instructor'] ?? 'Expert') ?></p>
                                            </div>
                                        </div>
                                        <div class="flex items-center">
                                            <i class="fas fa-star text-yellow-400 mr-1"></i>
                                            <span class="text-sm font-medium text-gray-700">4.<?= rand(5, 9) ?></span>
                                        </div>
                                    </div>
                                    
                                    <!-- Stats -->
                                    <div class="flex items-center justify-between text-sm text-gray-500 mb-4 pb-4 border-b border-gray-100">
                                        <div class="flex items-center">
                                            <i class="fas fa-video text-blue-500 mr-2"></i>
                                            <span><?= $course['total_videos'] ?? rand(20, 50) ?> Lessons</span>
                                        </div>
                                        <div class="flex items-center">
                                            <i class="fas fa-users text-green-500 mr-2"></i>
                                            <span><?= number_format($course['total_students'] ?? rand(500, 5000)) ?>+</span>
                                        </div>
                                    </div>
                                    
                                    <!-- Progress Bar (Optional) -->
                                    <div class="mb-4">
                                        <div class="flex items-center justify-between text-xs mb-1">
                                            <span class="text-gray-500">Popularity</span>
                                            <span class="font-semibold text-blue-600"><?= rand(70, 98) ?>%</span>
                                        </div>
                                        <div class="w-full bg-gray-200 rounded-full h-1.5">
                                            <div class="bg-gradient-to-r from-blue-500 to-blue-600 h-1.5 rounded-full" style="width: <?= rand(70, 98) ?>%"></div>
                                        </div>
                                    </div>
                                    
                                    <!-- Enroll Button with Animation -->
                                    <form method="POST">
                                        <input type="hidden" name="course_id" value="<?= $course['id'] ?>">
                                        <button type="submit" name="enroll" 
                                                class="w-full relative overflow-hidden group/btn">
                                            <div class="absolute inset-0 bg-gradient-to-r from-blue-600 to-indigo-700 opacity-0 group-hover/btn:opacity-100 transition duration-300"></div>
                                            <div class="relative px-6 py-4 bg-gradient-to-r from-blue-600 to-indigo-700 text-white rounded-xl font-semibold hover:shadow-xl transition transform group-hover/btn:scale-105 flex items-center justify-center">
                                                <span class="mr-2">Enroll Now</span>
                                                <i class="fas fa-arrow-right group-hover/btn:translate-x-1 transition"></i>
                                                <div class="absolute inset-0 flex items-center justify-center opacity-0 group-hover/btn:opacity-100">
                                                    <div class="w-full h-full shimmer"></div>
                                                </div>
                                            </div>
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <!-- Load More Section -->
                    <div class="mt-12 text-center">
                        <button class="px-8 py-4 bg-white text-gray-700 rounded-xl font-semibold hover:shadow-xl transition transform hover:scale-105 border border-gray-200 inline-flex items-center">
                            <i class="fas fa-spinner mr-2"></i>
                            Load More Courses
                        </button>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Custom Scrollbar -->
    <style>
        ::-webkit-scrollbar {
            width: 8px;
            height: 8px;
        }
        
        ::-webkit-scrollbar-track {
            background: rgba(0,0,0,0.05);
            border-radius: 10px;
        }
        
        ::-webkit-scrollbar-thumb {
            background: linear-gradient(135deg, #3b82f6, #8b5cf6);
            border-radius: 10px;
        }
        
        ::-webkit-scrollbar-thumb:hover {
            background: linear-gradient(135deg, #2563eb, #7c3aed);
        }
    </style>

    <script>
        // Smooth scroll animations
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                e.preventDefault();
                document.querySelector(this.getAttribute('href')).scrollIntoView({
                    behavior: 'smooth'
                });
            });
        });

        // Auto-hide alerts after 5 seconds
        setTimeout(() => {
            document.querySelectorAll('.animate-slideDown').forEach(el => {
                el.style.opacity = '0';
                setTimeout(() => el.remove(), 500);
            });
        }, 5000);
    </script>
</body>
</html>