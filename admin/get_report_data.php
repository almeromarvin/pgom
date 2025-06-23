<?php
session_start();
require_once '../config/database.php';

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Set proper headers
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');

// Log session and request information
error_log('Session data: ' . print_r($_SESSION, true));
error_log('Request data: ' . print_r($_GET, true));

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    error_log('Access denied - user not logged in or not admin');
    header('HTTP/1.1 403 Forbidden');
    echo json_encode(['error' => 'Access denied - user not logged in or not admin']);
    exit;
}

// Get period from query parameter
$period = isset($_GET['period']) ? $_GET['period'] : 'weekly';

try {
    // Verify database connection
    if (!isset($pdo)) {
        throw new Exception('Database connection not established');
    }

    // Test database connection
    $pdo->query('SELECT 1');
    error_log('Database connection successful');

    // Base query
    $query = "SELECT 
        b.id,
        b.start_time,
        b.end_time,
        b.status,
        f.name as facility_name,
        u.name as user_name,
        GROUP_CONCAT(
            CASE 
                WHEN i.name = 'Sound System' THEN CONCAT(i.name, ' (', be.quantity, ') - Includes: Microphone, Speaker, Mixer, Amplifier')
                ELSE CONCAT(i.name, ' (', be.quantity, ')')
            END
            SEPARATOR ', '
        ) as equipment
        FROM bookings b
        JOIN facilities f ON b.facility_id = f.id
        JOIN users u ON b.user_id = u.id
        LEFT JOIN booking_equipment be ON b.id = be.booking_id
        LEFT JOIN inventory i ON be.equipment_id = i.id";

    // Add period filter
    switch ($period) {
        case 'weekly':
            $query .= " WHERE b.start_time >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)";
            break;
        case 'monthly':
            $query .= " WHERE b.start_time >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)";
            break;
        case 'yearly':
            $query .= " WHERE b.start_time >= DATE_SUB(CURDATE(), INTERVAL 1 YEAR)";
            break;
        default:
            $query .= " WHERE 1=1"; // Show all bookings if no period specified
            break;
    }

    $query .= " GROUP BY b.id ORDER BY b.start_time DESC";

    error_log('Executing query: ' . $query);
    $stmt = $pdo->prepare($query);
    $stmt->execute();
    $bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);
    error_log('Found ' . count($bookings) . ' bookings');

    // Format data for response
    $formattedBookings = array_map(function($booking) {
        return [
            'id' => $booking['id'],
            'name' => $booking['user_name'],
            'venue' => $booking['facility_name'],
            'purpose' => 'Facility Booking', // Default purpose since it's not in the schema
            'equipment' => $booking['equipment'] ?: 'No equipment requested',
            'date_time' => date('M d, Y h:i A', strtotime($booking['start_time'])) . ' - ' . 
                          date('M d, Y h:i A', strtotime($booking['end_time'])),
            'status' => $booking['status']
        ];
    }, $bookings);

    // Get statistics
    $stats = [
        'Booking' => count($bookings),
        'Chairs' => 0,
        'Tables' => 0,
        'Users' => 0,
        'Sound System' => 0,
        'Industrial Fan' => 0,
        'Extension Wires' => 0,
        'Red Carpets' => 0,
        'Podium' => 0
    ];

    // Get inventory counts
    $items = ['Chairs', 'Tables', 'Sound System', 'Industrial Fan', 'Extension Wires', 'Red Carpets', 'Podium'];
    foreach ($items as $item) {
        $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM inventory WHERE name = ?");
        $stmt->execute([$item]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($result) {
            $stats[$item] = $result['count'];
        }
    }

    // Get user count
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM users WHERE deleted_at IS NULL");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($result) {
        $stats['Users'] = $result['count'];
    }

    $response = [
        'bookings' => $formattedBookings,
        'stats' => $stats
    ];

    error_log('Sending response: ' . json_encode($response));
    echo json_encode($response);

} catch (PDOException $e) {
    error_log('Database error in get_report_data.php: ' . $e->getMessage());
    error_log('SQL State: ' . $e->getCode());
    error_log('Error Info: ' . print_r($e->errorInfo, true));
    
    header('HTTP/1.1 500 Internal Server Error');
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
} catch (Exception $e) {
    error_log('General error in get_report_data.php: ' . $e->getMessage());
    error_log('Stack trace: ' . $e->getTraceAsString());
    
    header('HTTP/1.1 500 Internal Server Error');
    echo json_encode([
        'error' => 'An error occurred',
        'details' => $e->getMessage()
    ]);
}