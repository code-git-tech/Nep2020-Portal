<?php
require_once '../config/db.php';
require_once '../includes/auth.php';
requireLogin();

$userId = $_SESSION['user_id'];

try {
    $stmt = $pdo->prepare("SELECT id, name, email, role FROM users WHERE id != ? ORDER BY name");
    $stmt->execute([$userId]);
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    header('Content-Type: application/json');
    echo json_encode($users);
    
} catch (Exception $e) {
    header('Content-Type: application/json');
    echo json_encode([]);
}
?>