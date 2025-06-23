<?php
session_start();
require_once '../config/database.php';

// Set proper headers
header('Content-Type: application/json');

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Access denied']);
    exit;
}

// Check if it's a POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

// Check if JSON parsing failed
if (json_last_error() !== JSON_ERROR_NONE) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid JSON data']);
    exit;
}

$booking_id = $input['booking_id'] ?? null;

if (!$booking_id) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Booking ID is required']);
    exit;
}

// Validate booking ID is numeric
if (!is_numeric($booking_id)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid booking ID format']);
    exit;
}

try {
    // Start transaction
    $pdo->beginTransaction();

    // First, check if the booking exists
    $stmt = $pdo->prepare("SELECT id, user_id, facility_id, start_time, end_time, status FROM bookings WHERE id = ?");
    $stmt->execute([$booking_id]);
    $booking = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$booking) {
        throw new Exception('Booking not found');
    }

    // Check if booking can be deleted (only pending or rejected bookings can be deleted)
    if (!in_array($booking['status'], ['pending', 'rejected'])) {
        throw new Exception('Only pending or rejected bookings can be deleted');
    }

    // Delete booking equipment first (due to foreign key constraints)
    $stmt = $pdo->prepare("DELETE FROM booking_equipment WHERE booking_id = ?");
    $stmt->execute([$booking_id]);

    // Delete the booking
    $stmt = $pdo->prepare("DELETE FROM bookings WHERE id = ?");
    $stmt->execute([$booking_id]);

    // Check if the booking was actually deleted
    if ($stmt->rowCount() === 0) {
        throw new Exception('Failed to delete booking');
    }

    // Commit transaction
    $pdo->commit();

    // Log the deletion
    error_log("Admin {$_SESSION['user_id']} deleted booking {$booking_id}");

    echo json_encode([
        'success' => true,
        'message' => 'Booking deleted successfully'
    ]);

} catch (PDOException $e) {
    // Rollback transaction on error
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log('Database error deleting booking: ' . $e->getMessage());
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Database error occurred while deleting booking'
    ]);
} catch (Exception $e) {
    // Rollback transaction on error
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log('Error deleting booking: ' . $e->getMessage());
    
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?> 