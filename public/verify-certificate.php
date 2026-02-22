<?php
require_once '../includes/db.php';
require_once '../includes/certificate-functions.php';

$certificate = null;
$error = '';

if (isset($_GET['certificate_number'])) {
    $certificate_number = trim($_GET['certificate_number']);
    $certificate = verifyCertificate($pdo, $certificate_number);
    
    if (!$certificate) {
        $error = 'Invalid or expired certificate number.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verify Certificate</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-gray-50">
    <div class="min-h-screen flex items-center justify-center p-6">
        <div class="max-w-3xl w-full">
            <!-- Header -->
            <div class="text-center mb-8">
                <h1 class="text-4xl font-bold text-gray-800 mb-2">Certificate Verification</h1>
                <p class="text-gray-600">Verify the authenticity of a certificate</p>
            </div>

            <!-- Search Form -->
            <div class="bg-white rounded-xl shadow-sm p-8 mb-6">
                <form method="GET" class="flex space-x-3">
                    <input type="text" 
                           name="certificate_number" 
                           placeholder="Enter certificate number (e.g., CERT-1-2-1234567890-ABCDEF)"
                           class="flex-1 px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                           value="<?= htmlspecialchars($_GET['certificate_number'] ?? '') ?>">
                    <button type="submit" 
                            class="px-6 py-3 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition flex items-center">
                        <i class="fas fa-search mr-2"></i>
                        Verify
                    </button>
                </form>
            </div>

            <?php if ($error): ?>
                <div class="bg-red-100 border border-red-400 text-red-700 px-6 py-4 rounded-lg flex items-center">
                    <i class="fas fa-exclamation-circle text-2xl mr-3"></i>
                    <div>
                        <h3 class="font-bold">Invalid Certificate</h3>
                        <p class="text-sm"><?= htmlspecialchars($error) ?></p>
                    </div>
                </div>
            <?php endif; ?>

            <?php if ($certificate): ?>
                <div class="bg-white rounded-xl shadow-sm p-8 border-2 border-green-500">
                    <div class="text-center mb-6">
                        <div class="inline-block p-4 bg-green-100 rounded-full mb-4">
                            <i class="fas fa-check-circle text-green-600 text-5xl"></i>
                        </div>
                        <h2 class="text-2xl font-bold text-gray-800 mb-2">Valid Certificate</h2>
                        <p class="text-gray-600">This certificate has been verified and is authentic.</p>
                    </div>

                    <div class="border-t border-gray-200 pt-6">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div class="bg-gray-50 p-4 rounded-lg">
                                <p class="text-sm text-gray-500 mb-1">Certificate Number</p>
                                <p class="font-mono font-medium"><?= htmlspecialchars($certificate['certificate_number']) ?></p>
                            </div>
                            <div class="bg-gray-50 p-4 rounded-lg">
                                <p class="text-sm text-gray-500 mb-1">Issued To</p>
                                <p class="font-medium"><?= htmlspecialchars($certificate['student_name']) ?></p>
                            </div>
                            <div class="bg-gray-50 p-4 rounded-lg">
                                <p class="text-sm text-gray-500 mb-1">Course</p>
                                <p class="font-medium"><?= htmlspecialchars($certificate['course_title']) ?></p>
                            </div>
                            <div class="bg-gray-50 p-4 rounded-lg">
                                <p class="text-sm text-gray-500 mb-1">Instructor</p>
                                <p class="font-medium"><?= htmlspecialchars($certificate['instructor']) ?></p>
                            </div>
                            <div class="bg-gray-50 p-4 rounded-lg">
                                <p class="text-sm text-gray-500 mb-1">Issue Date</p>
                                <p class="font-medium"><?= date('F d, Y', strtotime($certificate['issued_date'])) ?></p>
                            </div>
                            <div class="bg-gray-50 p-4 rounded-lg">
                                <p class="text-sm text-gray-500 mb-1">Status</p>
                                <p class="font-medium text-green-600">Active & Verified</p>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Share/Print Options -->
                    <div class="mt-6 pt-6 border-t border-gray-200 flex justify-center space-x-4">
                        <button onclick="window.print()" class="px-4 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50">
                            <i class="fas fa-print mr-2"></i>Print
                        </button>
                        <button onclick="shareCertificate()" class="px-4 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50">
                            <i class="fas fa-share-alt mr-2"></i>Share
                        </button>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
    function shareCertificate() {
        if (navigator.share) {
            navigator.share({
                title: 'Certificate Verification',
                text: 'Verified Certificate from Your Platform',
                url: window.location.href
            });
        } else {
            // Fallback - copy to clipboard
            navigator.clipboard.writeText(window.location.href);
            alert('Link copied to clipboard!');
        }
    }
    </script>
</body>
</html>