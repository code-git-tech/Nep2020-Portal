<?php
require_once '../config/db.php';
require_once '../includes/auth.php';
require_once '../includes/auto_publish.php';

// Run auto-publisher on every page load
autoPublishVideos($pdo);

// Only allow if no system owner exists
$stmt = $pdo->query("SELECT id FROM users WHERE is_system_owner = 1");
if ($stmt->fetch()) {
    die("System owner already exists. This script should be deleted for security.");
}

// Create system owner account
$name = "System Administrator";
$email = "admin@system.com"; // Change this
$password = "Admin@123456"; // Change this - use strong password
$hashed = password_hash($password, PASSWORD_DEFAULT);

try {
    $pdo->beginTransaction();
    
    $stmt = $pdo->prepare("
        INSERT INTO users (name, email, password, role, is_system_owner, email_verified) 
        VALUES (?, ?, ?, 'admin', 1, 1)
    ");
    $stmt->execute([$name, $email, $hashed]);
    
    $pdo->commit();
    
    echo "✅ System owner created successfully!\n";
    echo "Email: $email\n";
    echo "Password: $password\n";
    echo "\n⚠️ IMPORTANT: Delete this file immediately for security!\n";
    
} catch (Exception $e) {
    $pdo->rollBack();
    echo "❌ Failed to create system owner: " . $e->getMessage() . "\n";
}
?>