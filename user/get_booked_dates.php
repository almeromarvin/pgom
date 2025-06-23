<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'user') {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

$user_id = $_SESSION['user_id'];
$exclude_booking_id = isset($_GET['exclude_booking_id']) ? (int)$_GET['exclude_booking_id'] : null;

try {
    // Get all booked dates for this user
    if ($exclude_booking_id) {
        // Exclude the current booking being edited
        $sql = "SELECT DISTINCT DATE(start_time) as booked_date 
                FROM bookings 
                WHERE user_id = ? 
                AND status IN ('pending', 'approved')
                AND id != ?
                ORDER BY booked_date";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$user_id, $exclude_booking_id]);
    } else {
        // Get all booked dates
        $sql = "SELECT DISTINCT DATE(start_time) as booked_date 
                FROM bookings 
                WHERE user_id = ? 
                AND status IN ('pending', 'approved') 
                ORDER BY booked_date";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$user_id]);
    }
    
    $booked_dates = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    // Format dates for JavaScript (YYYY-MM-DD)
    $formatted_dates = array_map(function($date) {
        return date('Y-m-d', strtotime($date));
    }, $booked_dates);
    
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'booked_dates' => $formatted_dates
    ]);
    
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Database error: ' . $e->getMessage()
    ]);
}
?> 