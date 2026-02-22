<?php
if (session_status() === PHP_SESSION_NONE) {
    // Secure session configuration
    ini_set('session.cookie_httponly', 1);
    ini_set('session.use_only_cookies', 1);
    ini_set('session.cookie_secure', 0); // Set to 1 if using HTTPS
    ini_set('session.cookie_samesite', 'Strict');
    
    session_start();
}

require_once __DIR__ . '/../config/db.php';

/**
 * Check if user is logged in
 */
function isLoggedIn() {
    return isset($_SESSION['user_id']) && isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true;
}

/**
 * Check if current user is admin
 */
function isAdmin() {
    return isLoggedIn() && isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
}

/**
 * Check if current user is student
 */
function isStudent() {
    return isLoggedIn() && isset($_SESSION['role']) && $_SESSION['role'] === 'student';
}

/**
 * Check if current user is system owner (master admin)
 */
function isSystemOwner() {
    global $pdo;
    if (!isAdmin()) return false;
    
    try {
        // Check if is_system_owner column exists
        $stmt = $pdo->prepare("SHOW COLUMNS FROM users LIKE 'is_system_owner'");
        $stmt->execute();
        $columnExists = $stmt->fetch();
        
        if (!$columnExists) {
            return false; // Column doesn't exist, so no system owner
        }
        
        $stmt = $pdo->prepare("SELECT is_system_owner FROM users WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $result = $stmt->fetch();
        
        return $result && isset($result['is_system_owner']) && $result['is_system_owner'] == 1;
    } catch (Exception $e) {
        return false;
    }
}

/**
 * Require login - redirect if not logged in
 */
function requireLogin() {
    if (!isLoggedIn()) {
        $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'];
        header('Location: /New/index.php');
        exit;
    }
    
    // Update last activity
    $_SESSION['last_activity'] = time();
}

/**
 * Require admin role
 */
function requireAdmin() {
    requireLogin();
    if (!isAdmin()) {
        header('Location: /New/index.php?error=unauthorized');
        exit;
    }
}

/**
 * Require student role
 */
function requireStudent() {
    requireLogin();
    if (!isStudent()) {
        header('Location: /New/index.php?error=unauthorized');
        exit;
    }
}

/**
 * Require system owner (master admin)
 */
function requireSystemOwner() {
    requireAdmin();
    if (!isSystemOwner()) {
        header('Location: /New/admin/dashboard.php?error=not_owner');
        exit;
    }
}

/**
 * Secure login function - UPDATED to handle missing columns
 */
function loginUser($email, $password) {
    global $pdo;
    
    try {
        // First, check if status column exists
        $stmt = $pdo->prepare("SHOW COLUMNS FROM users LIKE 'status'");
        $stmt->execute();
        $statusColumnExists = $stmt->fetch();
        
        // Build query based on existing columns
        if ($statusColumnExists) {
            $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? AND status = 'active'");
        } else {
            $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
        }
        
        $stmt->execute([$email]);
        $user = $stmt->fetch();
        
        // Verify password
        if ($user && password_verify($password, $user['password'])) {
            // Check if password needs rehash
            if (password_needs_rehash($user['password'], PASSWORD_DEFAULT)) {
                $newHash = password_hash($password, PASSWORD_DEFAULT);
                $updateStmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
                $updateStmt->execute([$newHash, $user['id']]);
            }
            
            // Regenerate session ID for security
            session_regenerate_id(true);
            
            // Set session variables
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['name'] = $user['name'];
            $_SESSION['email'] = $user['email'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['logged_in'] = true;
            $_SESSION['login_time'] = time();
            $_SESSION['last_activity'] = time();
            $_SESSION['ip_address'] = $_SERVER['REMOTE_ADDR'];
            $_SESSION['user_agent'] = $_SERVER['HTTP_USER_AGENT'];
            
            // Try to update last login info if columns exist
            try {
                // Check if last_login column exists
                $checkStmt = $pdo->prepare("SHOW COLUMNS FROM users LIKE 'last_login'");
                $checkStmt->execute();
                if ($checkStmt->fetch()) {
                    $updateStmt = $pdo->prepare("UPDATE users SET last_login = NOW(), last_ip = ? WHERE id = ?");
                    $updateStmt->execute([$_SERVER['REMOTE_ADDR'], $user['id']]);
                }
            } catch (Exception $e) {
                // Ignore errors - columns might not exist
            }
            
            // Log activity if function exists
            if (function_exists('logActivity')) {
                logActivity($user['id'], 'login', 'User logged in');
            }
            
            return true;
        }
        
        return false;
        
    } catch (PDOException $e) {
        // Log error and return false
        error_log("Login error: " . $e->getMessage());
        return false;
    }
}

/**
 * Secure logout function
 */
function logoutUser() {
    if (isLoggedIn() && function_exists('logActivity')) {
        try {
            logActivity($_SESSION['user_id'], 'logout', 'User logged out');
        } catch (Exception $e) {
            // Ignore errors
        }
    }
    
    $_SESSION = array();
    
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    
    session_destroy();
}

/**
 * Check session timeout (30 minutes)
 */
function checkSessionTimeout($timeout = 1800) {
    if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > $timeout)) {
        logoutUser();
        header('Location: /New/index.php?timeout=1');
        exit;
    }
    $_SESSION['last_activity'] = time();
}

/**
 * Verify session integrity
 */
function verifySessionIntegrity() {
    // Skip check for localhost development
    if ($_SERVER['REMOTE_ADDR'] === '127.0.0.1' || $_SERVER['REMOTE_ADDR'] === '::1') {
        return true;
    }
    
    // If these aren't set, we can't verify
    if (!isset($_SESSION['ip_address']) || !isset($_SESSION['user_agent'])) {
        return true;
    }
    
    // Check user agent (more reliable)
    return $_SESSION['user_agent'] === $_SERVER['HTTP_USER_AGENT'];
}

/**
 * Log user activity
 */
function logActivity($user_id, $action, $details = null) {
    global $pdo;
    
    // Check if activity_logs table exists
    try {
        $stmt = $pdo->query("SHOW TABLES LIKE 'activity_logs'");
        if (!$stmt->fetch()) {
            return false; // Table doesn't exist
        }
        
        $stmt = $pdo->prepare("
            INSERT INTO activity_logs (user_id, action, details, ip_address, user_agent)
            VALUES (?, ?, ?, ?, ?)
        ");
        
        return $stmt->execute([
            $user_id,
            $action,
            $details,
            $_SERVER['REMOTE_ADDR'],
            $_SERVER['HTTP_USER_AGENT']
        ]);
    } catch (Exception $e) {
        return false;
    }
}

/**
 * Get current user data
 */
function getCurrentUser() {
    global $pdo;
    
    if (!isLoggedIn()) {
        return null;
    }
    
    try {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        return null;
    }
}

/**
 * Check if email exists
 */
function emailExists($email, $exclude_user_id = null) {
    global $pdo;
    
    $sql = "SELECT id FROM users WHERE email = ?";
    $params = [$email];
    
    if ($exclude_user_id) {
        $sql .= " AND id != ?";
        $params[] = $exclude_user_id;
    }
    
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetch() !== false;
    } catch (Exception $e) {
        return false;
    }
}

/**
 * Register new student
 */
function registerStudent($name, $email, $password) {
    global $pdo;
    
    if (emailExists($email)) {
        return ['success' => false, 'error' => 'Email already exists'];
    }
    
    $hashed = password_hash($password, PASSWORD_DEFAULT);
    
    try {
        $pdo->beginTransaction();
        
        $stmt = $pdo->prepare("
            INSERT INTO users (name, email, password, role) 
            VALUES (?, ?, ?, 'student')
        ");
        $stmt->execute([$name, $email, $hashed]);
        $userId = $pdo->lastInsertId();
        
        // Add to General group if function exists
        if (function_exists('addUserToGeneralGroup')) {
            addUserToGeneralGroup($userId);
        }
        
        $pdo->commit();
        
        return ['success' => true, 'user_id' => $userId];
        
    } catch (Exception $e) {
        $pdo->rollBack();
        return ['success' => false, 'error' => 'Registration failed'];
    }
}

// Chat system integration functions
function ensureGeneralGroup() {
    global $pdo;
    
    // Check if conversations table exists
    try {
        $stmt = $pdo->query("SHOW TABLES LIKE 'conversations'");
        if (!$stmt->fetch()) {
            return null;
        }
        
        $stmt = $pdo->query("SELECT id FROM conversations WHERE type='group' AND name='General'");
        $group = $stmt->fetch();
        if (!$group) {
            $pdo->exec("INSERT INTO conversations (type, name) VALUES ('group', 'General')");
            $groupId = $pdo->lastInsertId();
        } else {
            $groupId = $group['id'];
        }
        return $groupId;
    } catch (Exception $e) {
        return null;
    }
}

function addUserToGeneralGroup($userId) {
    global $pdo;
    $groupId = ensureGeneralGroup();
    if (!$groupId) return;
    
    try {
        // Check if conversation_participants table exists
        $stmt = $pdo->query("SHOW TABLES LIKE 'conversation_participants'");
        if (!$stmt->fetch()) {
            return;
        }
        
        $stmt = $pdo->prepare("SELECT id FROM conversation_participants WHERE conversation_id = ? AND user_id = ?");
        $stmt->execute([$groupId, $userId]);
        if (!$stmt->fetch()) {
            $pdo->prepare("INSERT INTO conversation_participants (conversation_id, user_id) VALUES (?, ?)")
                ->execute([$groupId, $userId]);
        }
    } catch (Exception $e) {
        // Ignore errors
    }
}

// Check session timeout on every page load
checkSessionTimeout();

// Only verify integrity for logged in users - disabled for now
if (isLoggedIn()) {
    // Skip integrity check to avoid issues
    // You can enable this later if needed
}
?>