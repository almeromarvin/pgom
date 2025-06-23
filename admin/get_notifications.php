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
    if (isset($_GET['count'])) {
        // Get unread notifications count
        $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM notifications WHERE is_read = 0");
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        echo json_encode(['count' => $result['count']]);
    } else {
        // Get all notifications, ordered by most recent first
        $stmt = $pdo->prepare("
            SELECT 
                n.*,
                CASE
                    WHEN n.created_at > NOW() - INTERVAL 24 HOUR THEN TIME_FORMAT(n.created_at, '%l:%i %p')
                    WHEN n.created_at > NOW() - INTERVAL 48 HOUR THEN 'Yesterday'
                    ELSE DATE_FORMAT(n.created_at, '%M %d, %Y')
                END as formatted_date
            FROM notifications n
            ORDER BY n.created_at DESC
            LIMIT 50
        ");
        $stmt->execute();
        $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode(['notifications' => $notifications]);
    }
} catch (PDOException $e) {
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
} 