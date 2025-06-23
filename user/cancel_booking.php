<?php
session_start();
require_once '../config/database.php';
require_once '../includes/notification_helper.php';

// Check if user is logged in
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'user') {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

// Get JSON data
$data = json_decode(file_get_contents('php://input'), true);

if (!isset($data['booking_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Booking ID is required']);
    exit();
}

try {
    // Check if booking exists and belongs to user
    $stmt = $pdo->prepare("SELECT b.*, f.name as facility_name 
        FROM bookings b 
        JOIN facilities f ON b.facility_id = f.id 
        WHERE b.id = ? AND b.user_id = ?");
    $stmt->execute([$data['booking_id'], $_SESSION['user_id']]);
    $booking = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$booking) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Booking not found or does not belong to you']);
        exit();
    }

    // Check if booking can be cancelled
    if ($booking['status'] !== 'pending') {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Only pending bookings can be cancelled']);
        exit();
    }

    // Begin transaction
    $pdo->beginTransaction();

    try {
        // Update booking status to cancelled
        $stmt = $pdo->prepare("UPDATE bookings SET status = 'cancelled', updated_at = NOW() WHERE id = ?");
        $stmt->execute([$data['booking_id']]);

        // Add notification
        if (isset($_SESSION['user_name'])) {
            addBookingNotification('cancelled', $booking['facility_name'], $_SESSION['user_name'], $data['booking_id']);
        }

        // Commit transaction
        $pdo->commit();

        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'message' => 'Booking cancelled successfully']);
    } catch (Exception $e) {
        // Rollback transaction on error
        $pdo->rollBack();
        throw $e;
    }

} catch (PDOException $e) {
    // Log the error
    error_log("Database error in cancel_booking.php: " . $e->getMessage());
    
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false, 
        'message' => 'An error occurred while cancelling the booking. Please try again later.'
    ]);
} catch (Exception $e) {
    // Log the error
    error_log("General error in cancel_booking.php: " . $e->getMessage());
    
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false, 
        'message' => 'An unexpected error occurred. Please try again later.'
    ]);
}
?> 