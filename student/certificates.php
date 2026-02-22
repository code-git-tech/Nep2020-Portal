<?php
require_once '../includes/auth.php';
require_once '../includes/student-functions.php';
require_once '../includes/auto_publish.php';
requireStudent();

// Run auto-publisher on every page load
autoPublishVideos($pdo);

$user_id = $_SESSION['user_id'];

// Get user's certificates
$stmt = $pdo->prepare("
    SELECT c.*, crs.title as course_title, crs.instructor,
           u.name as student_name
    FROM certificates c
    JOIN courses crs ON c.course_id = crs.id
    JOIN users u ON c.student_id = u.id
    WHERE c.student_id = ?
    ORDER BY c.issued_date DESC
");
$stmt->execute([$user_id]);
$certificates = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Certificates</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-gray-50">
    <div class="flex h-screen">
        <?php include 'sidebar.php'; ?>

        <div class="flex-1 overflow-auto">
            <?php include 'header.php'; ?>

            <div class="p-6">
                <h1 class="text-2xl font-bold text-gray-800 mb-6">My Certificates</h1>

                <?php if (empty($certificates)): ?>
                    <div class="bg-white rounded-xl shadow-sm p-12 text-center">
                        <div class="w-24 h-24 bg-gray-100 rounded-full mx-auto mb-4 flex items-center justify-center">
                            <i class="fas fa-award text-gray-400 text-3xl"></i>
                        </div>
                        <h3 class="text-xl font-semibold text-gray-700 mb-2">No Certificates Yet</h3>
                        <p class="text-gray-500 mb-6">Complete courses and pass tests to earn certificates.</p>
                        <a href="courses.php" class="inline-block px-6 py-3 bg-blue-600 text-white rounded-lg hover:bg-blue-700">
                            Browse Courses
                        </a>
                    </div>
                <?php else: ?>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <?php foreach ($certificates as $cert): ?>
                        <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden hover:shadow-md transition">
                            <div class="h-40 bg-gradient-to-r from-yellow-400 to-yellow-600 relative">
                                <div class="absolute inset-0 bg-black opacity-10"></div>
                                <div class="absolute top-4 right-4">
                                    <i class="fas fa-award text-white text-4xl opacity-50"></i>
                                </div>
                                <div class="absolute bottom-4 left-4 text-white">
                                    <h3 class="text-lg font-bold">Certificate of Completion</h3>
                                </div>
                            </div>
                            
                            <div class="p-6">
                                <h4 class="font-bold text-gray-800 mb-2"><?= htmlspecialchars($cert['course_title']) ?></h4>
                                <p class="text-sm text-gray-500 mb-1">Issued to: <?= htmlspecialchars($cert['student_name']) ?></p>
                                <p class="text-sm text-gray-500 mb-1">Certificate #: <?= htmlspecialchars($cert['certificate_number']) ?></p>
                                <p class="text-sm text-gray-500 mb-4">Issued: <?= date('F d, Y', strtotime($cert['issued_date'])) ?></p>
                                
                                <div class="flex space-x-3">
                                    <a href="#" class="flex-1 text-center px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">
                                        <i class="fas fa-download mr-2"></i>Download
                                    </a>
                                    <a href="#" class="px-4 py-2 border border-gray-300 rounded-lg text-gray-600 hover:bg-gray-50">
                                        <i class="fas fa-share-alt"></i>
                                    </a>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>