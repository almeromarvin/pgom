<?php
session_start();
require_once '../config/database.php';
require_once '../includes/notification_helper.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

// Check if required parameters are present
if (!isset($_POST['booking_id']) || !isset($_POST['status'])) {
    echo json_encode(['success' => false, 'message' => 'Missing required parameters']);
    exit();
}

$booking_id = $_POST['booking_id'];
$status = $_POST['status'];
$admin_id = $_SESSION['user_id'];

try {
    // Begin transaction
    $pdo->beginTransaction();

    // Check if there's any overlapping booking for the same facility
    if ($status === 'approved') {
        $stmt = $pdo->prepare("
            SELECT b1.* FROM bookings b1
            JOIN bookings b2 ON b1.facility_id = b2.facility_id
            WHERE b2.id = ?
            AND b1.id != b2.id
            AND b1.status = 'approved'
            AND (
                (b1.start_time BETWEEN b2.start_time AND b2.end_time)
                OR (b1.end_time BETWEEN b2.start_time AND b2.end_time)
                OR (b2.start_time BETWEEN b1.start_time AND b1.end_time)
            )
        ");
        $stmt->execute([$booking_id]);
        
        if ($stmt->rowCount() > 0) {
            $pdo->rollBack();
            echo json_encode(['success' => false, 'message' => 'There is already an approved booking for this facility during the selected time period.']);
            exit();
        }
    }

    // Update booking status
    $stmt = $pdo->prepare("UPDATE bookings SET status = ?, updated_at = NOW() WHERE id = ?");
    $stmt->execute([$status, $booking_id]);

    // Get booking details for notification
    $stmt = $pdo->prepare("
        SELECT b.*, f.name as facility_name, u.name as user_name 
        FROM bookings b
        JOIN facilities f ON b.facility_id = f.id
        JOIN users u ON b.user_id = u.id
        WHERE b.id = ?
    ");
    $stmt->execute([$booking_id]);
    $booking = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($booking) {
        // Add notification based on status
        $notificationSent = false;
        switch ($status) {
            case 'approved':
                $notificationSent = addBookingNotification('approved', $booking['facility_name'], $booking['user_name'], $booking_id);
                break;
            case 'rejected':
                $notificationSent = addBookingNotification('rejected', $booking['facility_name'], $booking['user_name'], $booking_id);
                break;
            case 'cancelled':
                $notificationSent = addBookingNotification('cancelled', $booking['facility_name'], $booking['user_name'], $booking_id);
                break;
            case 'completed':
                $notificationSent = addBookingNotification('completed', $booking['facility_name'], $booking['user_name'], $booking_id);
                break;
        }

        if (!$notificationSent) {
            error_log("Failed to send notification for booking ID: $booking_id with status: $status");
        }
    } else {
        error_log("Could not find booking details for ID: $booking_id");
    }

    // For approved bookings, check if they're past and mark as completed
    if ($status === 'approved') {
        $stmt = $pdo->prepare("
            UPDATE bookings 
            SET status = 'completed' 
            WHERE id = ? 
            AND end_time < NOW()
        ");
        $stmt->execute([$booking_id]);
    }

    // Log the action in user_history
    $stmt = $pdo->prepare("INSERT INTO user_history (user_id, action_type, admin_id) 
                          SELECT user_id, ?, ? FROM bookings WHERE id = ?");
    if ($status === 'approved') {
        $action_type = 'booking_approved';
    } else if ($status === 'rejected') {
        $action_type = 'booking_rejected';
    } else if ($status === 'completed') {
        $action_type = 'booking_completed';
    } else {
        $action_type = 'booking_' . $status;
    }
    $stmt->execute([$action_type, $admin_id, $booking_id]);

    // Commit transaction
    $pdo->commit();

    echo json_encode(['success' => true, 'message' => 'Booking status updated successfully']);
} catch (PDOException $e) {
    // Rollback transaction on error
    $pdo->rollBack();
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?> 