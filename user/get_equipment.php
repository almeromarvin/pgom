<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'user') {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized access']);
    exit();
}

try {
    // Get all equipment items with quantities, including sound system components
    $stmt = $pdo->query("
        SELECT 
            id,
            name,
            (total_quantity - borrowed) as available,
            description,
            group_id,
            is_group
        FROM inventory 
        WHERE status = 'Available' 
        AND (
            name IN ('Chairs', 'Tables', 'Industrial Fan', 'Extension Wires', 'Red Carpet', 'Podium')
            OR (group_id = 1 AND name IN ('Speaker', 'Mixer', 'Amplifier', 'Cables', 'Microphone'))
        )
        ORDER BY 
            CASE 
                WHEN group_id = 1 THEN 1 
                ELSE 2 
            END,
            name
    ");
    $equipment = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Group sound system components together
    $organized_equipment = [];
    $sound_system_components = [];
    
    foreach ($equipment as $item) {
        if ($item['group_id'] == 1) {
            // Sound system components
            $sound_system_components[] = $item;
        } else {
            // Regular equipment
            $organized_equipment[] = $item;
        }
    }
    
    // Add sound system as a group if there are components
    if (!empty($sound_system_components)) {
        $organized_equipment[] = [
            'id' => 'sound_system_group',
            'name' => 'Sound System',
            'available' => 1,
            'description' => 'Complete sound system package including Speaker, Mixer, Amplifier, Cables, and Microphone',
            'group_id' => 1,
            'is_group' => 1,
            'components' => $sound_system_components
        ];
    }

    header('Content-Type: application/json');
    echo json_encode($organized_equipment);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}
?> 