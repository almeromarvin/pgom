<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../login.php');
    exit();
}

// Get filter parameters
$status_filter = isset($_GET['status']) ? $_GET['status'] : 'all';
$date_filter = isset($_GET['date']) ? $_GET['date'] : '';
$search_query = isset($_GET['search']) ? $_GET['search'] : '';

// Build the SQL query with filters
$sql = "SELECT b.*, u.name as user_name, f.name as facility_name 
        FROM bookings b 
        JOIN users u ON b.user_id = u.id 
        JOIN facilities f ON b.facility_id = f.id 
        WHERE 1=1";
$params = [];

if ($status_filter !== 'all') {
    $sql .= " AND b.status = ?";
    $params[] = $status_filter;
}

if ($date_filter) {
    $sql .= " AND DATE(b.start_time) = ?";
    $params[] = $date_filter;
}

if ($search_query) {
    $sql .= " AND (u.name LIKE ? OR f.name LIKE ?)";
    $params[] = "%$search_query%";
    $params[] = "%$search_query%";
}

// Set headers for Excel download
header('Content-Type: application/vnd.ms-excel');
header('Content-Disposition: attachment;filename="reservations_export.xls"');
header('Cache-Control: max-age=0');

// Create Excel content
echo "<table border='1'>";
echo "<tr>
        <th>Name</th>
        <th>Venue</th>
        <th>Equipment</th>
        <th>Time</th>
        <th>Event Date</th>
        <th>Status</th>
        <th>Purpose</th>
    </tr>";

try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $equipment = json_decode($row['equipment'], true) ?: [];
        $equipment_list = [];
        foreach ($equipment as $item) {
            if ($item['quantity'] > 0) {
                $equipment_list[] = $item['quantity'] . ' ' . ucfirst($item['item']);
            }
        }
        
        echo "<tr>";
        echo "<td>" . htmlspecialchars($row['user_name']) . "</td>";
        echo "<td>" . htmlspecialchars($row['facility_name']) . "</td>";
        echo "<td>" . (!empty($equipment_list) ? htmlspecialchars(implode(', ', $equipment_list)) : 'None') . "</td>";
        echo "<td>" . date('g:i a', strtotime($row['start_time'])) . ' - ' . 
                     date('g:i a', strtotime($row['end_time'])) . "</td>";
        echo "<td>" . date('M d, Y', strtotime($row['start_time'])) . "</td>";
        echo "<td>" . htmlspecialchars(ucfirst($row['status'])) . "</td>";
        echo "<td>" . htmlspecialchars($row['purpose']) . "</td>";
        echo "</tr>";
    }
} catch (PDOException $e) {
    // Handle error silently
}

echo "</table>";
?>