<?php
require_once '../includes/auth.php';
require_once '../includes/student-functions.php';
require_once '../includes/certificate-functions.php';
requireStudent();

$certificate_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$user_id = $_SESSION['user_id'];

// Get certificate details
$stmt = $pdo->prepare("
    SELECT c.*, crs.title as course_title, crs.instructor,
           u.name as student_name, u.email as student_email
    FROM certificates c
    JOIN courses crs ON c.course_id = crs.id
    JOIN users u ON c.student_id = u.id
    WHERE c.id = ? AND c.student_id = ?
");
$stmt->execute([$certificate_id, $user_id]);
$certificate = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$certificate) {
    header('Location: certificates.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Certificate of Completion - <?= htmlspecialchars($certificate['course_title']) ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        @media print {
            .no-print {
                display: none !important;
            }
            body {
                background: white;
                padding: 0;
            }
            .certificate-container {
                box-shadow: none;
                border: none;
            }
        }
        .certificate-container {
            background: white;
            border-radius: 1rem;
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
        }
        .certificate-border {
            border: 2px solid #3b82f6;
            border-radius: 0.75rem;
        }
        .inner-border {
            border: 1px solid #e5e7eb;
            border-radius: 0.5rem;
        }
        .signature-line {
            border-bottom: 2px solid #9ca3af;
            width: 200px;
        }
    </style>
</head>
<body class="bg-gray-100 p-8">
    <div class="max-w-5xl mx-auto">
        <!-- Actions -->
        <div class="mb-6 flex justify-between items-center no-print">
            <a href="certificates.php" class="text-blue-600 hover:text-blue-800">
                <i class="fas fa-arrow-left mr-2"></i>Back to Certificates
            </a>
            <div class="space-x-3">
                <?php if ($certificate['pdf_path']): ?>
                <a href="../<?= htmlspecialchars($certificate['pdf_path']) ?>" download 
                   class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700">
                    <i class="fas fa-download mr-2"></i>Download PDF
                </a>
                <?php endif; ?>
                <button onclick="window.print()" 
                        class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">
                    <i class="fas fa-print mr-2"></i>Print Certificate
                </button>
            </div>
        </div>

        <!-- Certificate -->
        <div class="certificate-container p-8 relative">
            <div class="certificate-border p-6">
                <div class="inner-border p-12 text-center">
                    <!-- Logo/Brand -->
                    <div class="mb-8">
                        <span class="text-4xl font-bold text-blue-600">🏆</span>
                        <h1 class="text-4xl font-bold text-blue-600 mt-2">CERTIFICATE</h1>
                        <p class="text-gray-500 text-lg">OF COMPLETION</p>
                    </div>

                    <!-- Presented to -->
                    <p class="text-gray-600 text-lg mb-2">This certificate is proudly presented to</p>
                    <h2 class="text-5xl font-bold text-gray-800 mb-4 border-b-2 border-blue-200 inline-block pb-2">
                        <?= htmlspecialchars($certificate['student_name']) ?>
                    </h2>

                    <!-- Course completion -->
                    <p class="text-gray-600 text-lg mt-6 mb-2">for successfully completing the course</p>
                    <h3 class="text-3xl font-bold text-blue-600 mb-4">
                        <?= htmlspecialchars($certificate['course_title']) ?>
                    </h3>

                    <!-- Course details -->
                    <div class="flex justify-center items-center space-x-6 text-gray-600 mb-8">
                        <span><i class="fas fa-chalkboard-teacher mr-2"></i><?= htmlspecialchars($certificate['instructor']) ?></span>
                        <span><i class="fas fa-calendar mr-2"></i><?= date('F d, Y', strtotime($certificate['issued_date'])) ?></span>
                    </div>

                    <!-- Certificate ID -->
                    <div class="mb-8">
                        <p class="text-sm text-gray-500">Certificate ID: <?= htmlspecialchars($certificate['certificate_number']) ?></p>
                    </div>

                    <!-- Signatures -->
                    <div class="flex justify-around items-center mt-12">
                        <div class="text-center">
                            <div class="signature-line mb-2 mx-auto"></div>
                            <p class="text-gray-600 font-medium">Course Instructor</p>
                        </div>
                        <div class="text-center">
                            <div class="signature-line mb-2 mx-auto"></div>
                            <p class="text-gray-600 font-medium">Platform Director</p>
                        </div>
                    </div>

                    <!-- Seal -->
                    <div class="absolute top-20 right-20 opacity-10">
                        <i class="fas fa-certificate text-9xl text-blue-600"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Auto-trigger download if requested
        <?php if (isset($_GET['download']) && $certificate['pdf_path']): ?>
        window.location.href = '../<?= htmlspecialchars($certificate['pdf_path']) ?>';
        <?php endif; ?>
    </script>
</body>
</html>