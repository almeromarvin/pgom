<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

try {
    if (isset($_POST['notification_id'])) {
        // Mark specific notification as read
        $stmt = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE id = ?");
        $stmt->execute([$_POST['notification_id']]);
    } else {
        // Mark all notifications as read
        $stmt = $pdo->prepare("UPDATE notifications SET is_read = 1");
        $stmt->execute();
    }
    
    echo json_encode(['success' => true]);
} catch (PDOException $e) {
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
} 