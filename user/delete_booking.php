<?php
session_start();
require_once '../config/database.php';

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
    $stmt = $pdo->prepare("SELECT b.*, f.name as facility_name, f.description as facility_description
        FROM bookings b
        JOIN facilities f ON b.facility_id = f.id
        WHERE b.id = ? AND b.user_id = ? AND b.status IN ('completed', 'cancelled')");
    $stmt->execute([$data['booking_id'], $_SESSION['user_id']]);
    $booking = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$booking) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Booking not found or cannot be deleted']);
        exit();
    }

    // Start transaction
    $pdo->beginTransaction();

    // Record the deletion in user_history
    $stmt = $pdo->prepare("INSERT INTO user_history (user_id, action_type, admin_id) 
        VALUES (?, 'booking_deleted', ?)");
    
    $stmt->execute([$_SESSION['user_id'], $_SESSION['user_id']]);

    // Delete booking
    $stmt = $pdo->prepare("DELETE FROM bookings WHERE id = ?");
    $stmt->execute([$data['booking_id']]);

    // Commit transaction
    $pdo->commit();

    header('Content-Type: application/json');
    echo json_encode(['success' => true, 'message' => 'Booking deleted successfully']);

} catch (PDOException $e) {
    // Rollback transaction on error
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?> 