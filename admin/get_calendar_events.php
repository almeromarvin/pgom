<?php
session_start();
require_once '../config/database.php';

// Set proper headers
header('Content-Type: application/json');

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['error' => 'Access denied']);
    exit;
}

// Get date from query parameter
$date = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');

try {
    // Get bookings for the specific date
    $stmt = $pdo->prepare("
        SELECT 
    b.id,
            b.start_time,
            b.end_time,
    b.status,
            u.name as user_name,
    f.name as facility_name,
            GROUP_CONCAT(CONCAT(i.name, ' (', be.quantity, ')') SEPARATOR ', ') as equipment
FROM bookings b
        JOIN users u ON b.user_id = u.id
        JOIN facilities f ON b.facility_id = f.id
LEFT JOIN booking_equipment be ON b.id = be.booking_id
        LEFT JOIN inventory i ON be.equipment_id = i.id
        WHERE DATE(b.start_time) = ?
        GROUP BY b.id
        ORDER BY b.start_time
    ");
    $stmt->execute([$date]);
    $bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Format bookings for response
    $formatted_bookings = [];
    foreach ($bookings as $booking) {
        $formatted_bookings[] = [
            'id' => $booking['id'],
            'user_name' => $booking['user_name'],
            'facility_name' => $booking['facility_name'],
            'status' => $booking['status'],
            'time' => date('g:i A', strtotime($booking['start_time'])) . ' - ' . 
                     date('g:i A', strtotime($booking['end_time'])),
            'equipment' => $booking['equipment'] ?: null
        ];
    }
    
    echo json_encode([
        'success' => true,
        'bookings' => $formatted_bookings
    ]);
    
} catch (Exception $e) {
    error_log('Error in get_calendar_events.php: ' . $e->getMessage());
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Failed to load bookings'
    ]);
}
?> 