<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Unauthorized access']);
    exit();
}

// Check if ID is provided
if (!isset($_GET['id'])) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'No ID provided']);
    exit();
}

try {
    // Get reservation details
    $stmt = $pdo->prepare("SELECT b.*, u.name as user_name, f.name as facility_name,
        GROUP_CONCAT(
            CONCAT(i.name, ' (', be.quantity, ')')
            ORDER BY i.name
            SEPARATOR ', '
        ) as equipment_details
        FROM bookings b 
        JOIN users u ON b.user_id = u.id 
        JOIN facilities f ON b.facility_id = f.id 
        LEFT JOIN booking_equipment be ON b.id = be.booking_id
        LEFT JOIN inventory i ON be.equipment_id = i.id
        WHERE b.id = ?
        GROUP BY b.id");
    $stmt->execute([$_GET['id']]);
    $reservation = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$reservation) {
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Reservation not found']);
        exit();
    }

    // Return the data as JSON
    header('Content-Type: application/json');
    echo json_encode($reservation);

} catch (PDOException $e) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
    exit();
}
?> 