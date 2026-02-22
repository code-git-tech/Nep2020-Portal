<?php
require_once '../includes/auth.php';
require_once '../includes/auto_publish.php';
requireSystemOwner(); // Only system owner can access this page

// Run auto-publisher on every page load
autoPublishVideos($pdo);

$message = '';
$error = '';

// Get all admins (only system owner can see this)
$stmt = $pdo->query("SELECT id, name, email, is_system_owner, status, last_login FROM users WHERE role = 'admin' ORDER BY is_system_owner DESC, id ASC");
$admins = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>System Settings</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-gray-100">
    <div class="flex h-screen">
        <?php include 'sidebar.php'; ?>

        <div class="flex-1 overflow-auto">
            <div class="p-8">
                <h1 class="text-3xl font-bold mb-8">System Settings</h1>
                
                <div class="bg-yellow-100 border-l-4 border-yellow-500 text-yellow-700 p-4 mb-6">
                    <p class="font-bold">System Owner Access Only</p>
                    <p>You are viewing this page as the system owner with full privileges.</p>
                </div>

                <div class="bg-white rounded-lg shadow p-6">
                    <h2 class="text-xl font-bold mb-4">Admin Accounts</h2>
                    <table class="min-w-full">
                        <thead>
                            <tr class="bg-gray-50">
                                <th class="px-6 py-3 text-left">ID</th>
                                <th class="px-6 py-3 text-left">Name</th>
                                <th class="px-6 py-3 text-left">Email</th>
                                <th class="px-6 py-3 text-left">Type</th>
                                <th class="px-6 py-3 text-left">Status</th>
                                <th class="px-6 py-3 text-left">Last Login</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($admins as $admin): ?>
                            <tr class="border-t">
                                <td class="px-6 py-4"><?= $admin['id'] ?></td>
                                <td class="px-6 py-4"><?= htmlspecialchars($admin['name']) ?></td>
                                <td class="px-6 py-4"><?= htmlspecialchars($admin['email']) ?></td>
                                <td class="px-6 py-4">
                                    <?php if ($admin['is_system_owner']): ?>
                                        <span class="px-2 py-1 bg-purple-100 text-purple-800 rounded-full text-xs">System Owner</span>
                                    <?php else: ?>
                                        <span class="px-2 py-1 bg-blue-100 text-blue-800 rounded-full text-xs">Admin</span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-6 py-4">
                                    <span class="px-2 py-1 bg-green-100 text-green-800 rounded-full text-xs">
                                        <?= ucfirst($admin['status']) ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4"><?= $admin['last_login'] ? date('Y-m-d H:i', strtotime($admin['last_login'])) : 'Never' ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</body>
</html>