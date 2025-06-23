<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'user') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit();
}

// Validate required fields
if (empty($_POST['booking_id']) || empty($_POST['facility_id']) || 
    empty($_POST['event_date']) || empty($_POST['start_time']) || empty($_POST['end_time'])) {
    echo json_encode(['success' => false, 'message' => 'All required fields must be filled']);
    exit();
}

$booking_id = $_POST['booking_id'];
$facility_id = $_POST['facility_id'];
$event_date = $_POST['event_date'];
$start_time = $_POST['start_time'];
$end_time = $_POST['end_time'];
$user_id = $_SESSION['user_id'];

// Validate that the booking belongs to the user
try {
    $stmt = $pdo->prepare("SELECT status FROM bookings WHERE id = ? AND user_id = ?");
    $stmt->execute([$booking_id, $user_id]);
    $booking = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$booking) {
        echo json_encode(['success' => false, 'message' => 'Booking not found']);
        exit();
    }
    
    // Only allow editing pending bookings
    if ($booking['status'] !== 'pending') {
        echo json_encode(['success' => false, 'message' => 'Only pending bookings can be edited']);
        exit();
    }
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    exit();
}

// Validate date and time
$start_datetime = $event_date . ' ' . $start_time . ':00';
$end_datetime = $event_date . ' ' . $end_time . ':00';

if (strtotime($start_datetime) >= strtotime($end_datetime)) {
    echo json_encode(['success' => false, 'message' => 'End time must be after start time']);
    exit();
}

if (strtotime($start_datetime) < time()) {
    echo json_encode(['success' => false, 'message' => 'Cannot book in the past']);
    exit();
}

// Check for booking conflicts (excluding current booking)
try {
    $stmt = $pdo->prepare("SELECT id FROM bookings 
                          WHERE facility_id = ? 
                          AND id != ? 
                          AND status IN ('pending', 'approved')
                          AND (
                              (start_time <= ? AND end_time > ?) OR
                              (start_time < ? AND end_time >= ?) OR
                              (start_time >= ? AND end_time <= ?)
                          )");
    $stmt->execute([$facility_id, $booking_id, $start_datetime, $start_datetime, $end_datetime, $end_datetime, $start_datetime, $end_datetime]);
    
    if ($stmt->fetch()) {
        echo json_encode(['success' => false, 'message' => 'This facility is already booked for the selected time period']);
        exit();
    }
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    exit();
}

// Handle file upload
$request_letter = null;
if (isset($_FILES['request_letter']) && $_FILES['request_letter']['error'] === UPLOAD_ERR_OK) {
    $ext = strtolower(pathinfo($_FILES['request_letter']['name'], PATHINFO_EXTENSION));
    if ($ext === 'pdf') {
        $upload_dir = '../uploads/';
        if (!is_dir($upload_dir)) mkdir($upload_dir);
        $filename = uniqid('req_') . '.pdf';
        $target = $upload_dir . $filename;
        if (move_uploaded_file($_FILES['request_letter']['tmp_name'], $target)) {
            $request_letter = $filename;
        } else {
            echo json_encode(['success' => false, 'message' => 'Error uploading file']);
            exit();
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Request letter must be a PDF file']);
        exit();
    }
}

try {
    $pdo->beginTransaction();
    
    // Update booking
    if ($request_letter) {
        // Get current request letter to delete old file
        $stmt = $pdo->prepare("SELECT request_letter FROM bookings WHERE id = ?");
        $stmt->execute([$booking_id]);
        $current_letter = $stmt->fetchColumn();
        
        // Delete old file if exists
        if ($current_letter && file_exists('../uploads/' . $current_letter)) {
            unlink('../uploads/' . $current_letter);
        }
        
        $stmt = $pdo->prepare("UPDATE bookings SET 
                              facility_id = ?, start_time = ?, end_time = ?, 
                              request_letter = ?, updated_at = NOW() 
                              WHERE id = ? AND user_id = ?");
        $stmt->execute([$facility_id, $start_datetime, $end_datetime, $request_letter, $booking_id, $user_id]);
    } else {
        $stmt = $pdo->prepare("UPDATE bookings SET 
                              facility_id = ?, start_time = ?, end_time = ?, 
                              updated_at = NOW() 
                              WHERE id = ? AND user_id = ?");
        $stmt->execute([$facility_id, $start_datetime, $end_datetime, $booking_id, $user_id]);
    }
    
    // Update equipment
    // First, remove all existing equipment for this booking
    $stmt = $pdo->prepare("DELETE FROM booking_equipment WHERE booking_id = ?");
    $stmt->execute([$booking_id]);
    
    // Add new equipment selections
    $equipment_selections = [];
    foreach ($_POST as $key => $value) {
        if (strpos($key, 'equipment_') === 0) {
            $equipment_key = substr($key, 10); // Remove 'equipment_' prefix
            
            if ($equipment_key === 'sound_system') {
                // Handle sound system checkbox - if checked, add 1 set
                if ($value == '1') {
                    $equipment_selections['sound_system'] = 1;
                }
            } else if ($value > 0) {
                // Handle regular equipment quantities
                $equipment_selections[$equipment_key] = (int)$value;
            }
        }
    }
    
    if (!empty($equipment_selections)) {
        $stmt = $pdo->prepare("INSERT INTO booking_equipment (booking_id, equipment_id, quantity) VALUES (?, ?, ?)");
        
        // Get equipment IDs mapping
        $equipment_ids = [];
        $stmt_ids = $pdo->query("SELECT id, name FROM inventory");
        while ($row = $stmt_ids->fetch(PDO::FETCH_ASSOC)) {
            $equipment_ids[$row['name']] = $row['id'];
        }
        
        foreach ($equipment_selections as $equipment_key => $quantity) {
            if ($equipment_key === 'sound_system') {
                // Handle sound system group - add all components (1 set)
                $sound_system_components = ['Speaker', 'Mixer', 'Amplifier', 'Cables', 'Microphone'];
                foreach ($sound_system_components as $component) {
                    if (isset($equipment_ids[$component])) {
                        $stmt->execute([$booking_id, $equipment_ids[$component], $quantity]);
                    }
                }
            } else {
                // Handle regular equipment
                if (is_numeric($equipment_key)) {
                    $stmt->execute([$booking_id, $equipment_key, $quantity]);
                }
            }
        }
    }
    
    $pdo->commit();
    
    echo json_encode(['success' => true, 'message' => 'Booking updated successfully']);
    
} catch (PDOException $e) {
    $pdo->rollBack();
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?> 