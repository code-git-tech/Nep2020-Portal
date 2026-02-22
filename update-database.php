<?php
require_once 'config/db.php';

echo "<h2>Updating Database Structure</h2>";

try {
    // Check if status column exists
    $stmt = $pdo->query("SHOW COLUMNS FROM users LIKE 'status'");
    if (!$stmt->fetch()) {
        $pdo->exec("ALTER TABLE users ADD COLUMN status ENUM('active', 'suspended', 'inactive') DEFAULT 'active' AFTER role");
        echo "<p style='color:green'>✅ Added 'status' column</p>";
    } else {
        echo "<p style='color:blue'>⏺ 'status' column already exists</p>";
    }
    
    // Check if is_system_owner column exists
    $stmt = $pdo->query("SHOW COLUMNS FROM users LIKE 'is_system_owner'");
    if (!$stmt->fetch()) {
        $pdo->exec("ALTER TABLE users ADD COLUMN is_system_owner BOOLEAN DEFAULT FALSE AFTER status");
        echo "<p style='color:green'>✅ Added 'is_system_owner' column</p>";
    } else {
        echo "<p style='color:blue'>⏺ 'is_system_owner' column already exists</p>";
    }
    
    // Check if last_login column exists
    $stmt = $pdo->query("SHOW COLUMNS FROM users LIKE 'last_login'");
    if (!$stmt->fetch()) {
        $pdo->exec("ALTER TABLE users ADD COLUMN last_login TIMESTAMP NULL AFTER is_system_owner");
        echo "<p style='color:green'>✅ Added 'last_login' column</p>";
    } else {
        echo "<p style='color:blue'>⏺ 'last_login' column already exists</p>";
    }
    
    // Check if last_ip column exists
    $stmt = $pdo->query("SHOW COLUMNS FROM users LIKE 'last_ip'");
    if (!$stmt->fetch()) {
        $pdo->exec("ALTER TABLE users ADD COLUMN last_ip VARCHAR(45) NULL AFTER last_login");
        echo "<p style='color:green'>✅ Added 'last_ip' column</p>";
    } else {
        echo "<p style='color:blue'>⏺ 'last_ip' column already exists</p>";
    }
    
    // Check if email_verified column exists
    $stmt = $pdo->query("SHOW COLUMNS FROM users LIKE 'email_verified'");
    if (!$stmt->fetch()) {
        $pdo->exec("ALTER TABLE users ADD COLUMN email_verified BOOLEAN DEFAULT FALSE AFTER last_ip");
        echo "<p style='color:green'>✅ Added 'email_verified' column</p>";
    } else {
        echo "<p style='color:blue'>⏺ 'email_verified' column already exists</p>";
    }
    
    // Check if avatar column exists
    $stmt = $pdo->query("SHOW COLUMNS FROM users LIKE 'avatar'");
    if (!$stmt->fetch()) {
        $pdo->exec("ALTER TABLE users ADD COLUMN avatar VARCHAR(500) NULL AFTER email_verified");
        echo "<p style='color:green'>✅ Added 'avatar' column</p>";
    } else {
        echo "<p style='color:blue'>⏺ 'avatar' column already exists</p>";
    }
    
    // Update existing users to have active status
    $pdo->exec("UPDATE users SET status = 'active' WHERE status IS NULL OR status = ''");
    echo "<p style='color:green'>✅ Updated existing users with active status</p>";
    
    echo "<h3 style='color:green'>Database update completed successfully!</h3>";
    echo "<p><a href='index.php'>Go to Homepage</a></p>";
    
} catch (PDOException $e) {
    echo "<p style='color:red'>Error: " . $e->getMessage() . "</p>";
}
?>