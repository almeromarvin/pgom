<?php
session_start();
require_once '../config/database.php';
require_once '../includes/notification_helper.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

// Check if it's a POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit();
}

// Check if all required fields are present
$required_fields = ['id', 'name', 'total_quantity', 'minimum_stock', 'status'];
foreach ($required_fields as $field) {
    if (!isset($_POST[$field])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => "Missing required field: $field"]);
        exit();
    }
}

try {
    // Start transaction
    $pdo->beginTransaction();

    // Get current item data
    $stmt = $pdo->prepare("SELECT * FROM inventory WHERE id = ?");
    $stmt->execute([$_POST['id']]);
    $current_item = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$current_item) {
        throw new Exception('Item not found');
    }

    // Update item
    $stmt = $pdo->prepare("
        UPDATE inventory 
        SET name = ?, total_quantity = ?, minimum_stock = ?, status = ?, description = ?, last_updated = NOW()
        WHERE id = ?
    ");
    $stmt->execute([
        $_POST['name'],
        $_POST['total_quantity'],
        $_POST['minimum_stock'],
        $_POST['status'],
        $_POST['description'] ?? null,
        $_POST['id']
    ]);

    // Log changes in history
    $changes = [];
    if ($current_item['name'] !== $_POST['name']) {
        $changes[] = "Name changed from '{$current_item['name']}' to '{$_POST['name']}'";
    }
    if ($current_item['total_quantity'] != $_POST['total_quantity']) {
        $quantity_diff = $_POST['total_quantity'] - $current_item['total_quantity'];
        $action = $quantity_diff > 0 ? "Added" : "Removed";
        $changes[] = "$action " . abs($quantity_diff) . " items";
    }
    if ($current_item['minimum_stock'] != $_POST['minimum_stock']) {
        $changes[] = "Minimum stock updated to {$_POST['minimum_stock']}";
    }
    if ($current_item['status'] !== $_POST['status']) {
        $changes[] = "Status changed to {$_POST['status']}";
    }
    if (($current_item['description'] ?? '') !== ($_POST['description'] ?? '')) {
        $changes[] = "Description updated";
    }

    if (!empty($changes)) {
        // Add to history
        $stmt = $pdo->prepare("
            INSERT INTO inventory_history (item_id, action, quantity, modified_by, date_modified)
            VALUES (?, ?, ?, ?, NOW())
        ");
        $stmt->execute([
            $_POST['id'],
            implode(", ", $changes),
            $_POST['total_quantity'],
            $_SESSION['user_id']
        ]);

        // Add notifications for different actions
        if ($current_item['total_quantity'] != $_POST['total_quantity']) {
            if ($_POST['total_quantity'] <= $_POST['minimum_stock']) {
                addInventoryNotification('low_stock', $_POST['name']);
            }
            if ($_POST['total_quantity'] == 0) {
                addInventoryNotification('out_of_stock', $_POST['name']);
            }
        }

        if ($current_item['status'] !== $_POST['status']) {
            if ($_POST['status'] === 'In Maintenance') {
                addMaintenanceNotification('started', $_POST['name']);
            } else if ($current_item['status'] === 'In Maintenance' && $_POST['status'] === 'Available') {
                addMaintenanceNotification('completed', $_POST['name']);
            }
        }
    }

    // Commit transaction
    $pdo->commit();
    
    echo json_encode([
        'success' => true, 
        'message' => 'Item updated successfully',
        'item' => [
            'id' => $_POST['id'],
            'name' => $_POST['name'],
            'total_quantity' => $_POST['total_quantity'],
            'minimum_stock' => $_POST['minimum_stock'],
            'status' => $_POST['status']
        ]
    ]);

} catch (Exception $e) {
    $pdo->rollBack();
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error updating item: ' . $e->getMessage()]);
}
?> 