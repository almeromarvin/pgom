<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'user') {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized access']);
    exit();
}

if (!isset($_GET['id'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Booking ID is required']);
    exit();
}

try {
    // Get booking details
    $stmt = $pdo->prepare("SELECT 
        b.*,
        f.name as facility_name,
        f.description as facility_description,
        GROUP_CONCAT(
            CONCAT(i.name, ' (', be.quantity, ')')
            ORDER BY i.name
            SEPARATOR ', '
        ) as equipment_details
        FROM bookings b
        JOIN facilities f ON b.facility_id = f.id
        LEFT JOIN booking_equipment be ON b.id = be.booking_id
        LEFT JOIN inventory i ON be.equipment_id = i.id
        WHERE b.id = ? AND b.user_id = ?
        GROUP BY b.id, b.facility_id, b.user_id, b.start_time, b.end_time, b.status, b.created_at, b.updated_at, b.request_letter, f.name, f.description");
    
    $stmt->execute([$_GET['id'], $_SESSION['user_id']]);
    $booking = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$booking) {
        http_response_code(404);
        echo json_encode(['error' => 'Booking not found']);
        exit();
    }

    // Ensure all required fields are present
    $booking['facility_name'] = $booking['facility_name'] ?? 'Unknown Facility';
    $booking['equipment_details'] = $booking['equipment_details'] ?? '';
    $booking['request_letter'] = $booking['request_letter'] ?? '';
    $booking['status'] = $booking['status'] ?? 'unknown';
    $booking['updated_at'] = $booking['updated_at'] ?? $booking['created_at'];

    header('Content-Type: application/json');
    echo json_encode($booking);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}
?> 