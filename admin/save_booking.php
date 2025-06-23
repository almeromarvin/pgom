<?php
session_start();
require_once '../config/database.php';
require_once '../includes/notification_helper.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

// Validate input
if (empty($_POST['facility_id']) || empty($_POST['start_time']) || empty($_POST['end_time']) || empty($_POST['purpose'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'All fields are required']);
    exit();
}

try {
    // Check for booking conflicts
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM bookings 
        WHERE facility_id = ? 
        AND ((start_time BETWEEN ? AND ?) 
        OR (end_time BETWEEN ? AND ?))
        AND status != 'rejected'");
    
    $stmt->execute([
        $_POST['facility_id'],
        $_POST['start_time'],
        $_POST['end_time'],
        $_POST['start_time'],
        $_POST['end_time']
    ]);

    if ($stmt->fetchColumn() > 0) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'This facility is already booked for the selected time period']);
        exit();
    }

    // Insert new booking
    $stmt = $pdo->prepare("INSERT INTO bookings (facility_id, user_id, start_time, end_time, purpose, status, created_at) 
        VALUES (?, ?, ?, ?, ?, 'pending', NOW())");
    
    $stmt->execute([
        $_POST['facility_id'],
        $_SESSION['user_id'],
        $_POST['start_time'],
        $_POST['end_time'],
        $_POST['purpose']
    ]);

    // Get facility and user details for response
    $stmt = $pdo->prepare("SELECT 
        b.id,
        f.name as facility_name,
        u.name as user_name
        FROM bookings b
        JOIN facilities f ON b.facility_id = f.id
        JOIN users u ON b.user_id = u.id
        WHERE b.id = ?");
    
    $stmt->execute([$pdo->lastInsertId()]);
    $booking = $stmt->fetch(PDO::FETCH_ASSOC);

    // Add notification for new reservation
    $notificationSent = addReservationNotification(
        'new',
        $booking['facility_name'],
        $booking['user_name'],
        $booking['id']
    );

    if (!$notificationSent) {
        error_log("Failed to send notification for new booking ID: {$booking['id']}");
    }

    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'id' => $booking['id'],
        'facility_name' => $booking['facility_name'],
        'user_name' => $booking['user_name'],
        'start_time' => $_POST['start_time'],
        'end_time' => $_POST['end_time']
    ]);

} catch (PDOException $e) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?> 