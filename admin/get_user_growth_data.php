<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

try {
    // Get user growth data with cumulative totals
    $stmt = $pdo->query("SELECT 
        DATE(created_at) as date,
        SUM(COUNT(*)) OVER (ORDER BY DATE(created_at)) as cumulative_total,
        SUM(COUNT(CASE WHEN deleted_at IS NOT NULL THEN 1 END)) OVER (ORDER BY DATE(created_at)) as cumulative_deleted
        FROM users 
        GROUP BY DATE(created_at) 
        ORDER BY date DESC LIMIT 30");
    $user_growth = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get current user statistics
    $stmt = $pdo->query("SELECT 
        COUNT(*) as total_users,
        COUNT(CASE WHEN deleted_at IS NULL THEN 1 END) as active_users,
        COUNT(CASE WHEN deleted_at IS NOT NULL THEN 1 END) as deleted_users
        FROM users");
    $userStats = $stmt->fetch(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'user_growth' => array_reverse($user_growth), // Reverse to show oldest to newest
        'userStats' => $userStats
    ]);

} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}
?> 