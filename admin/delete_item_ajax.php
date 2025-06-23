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

// Check if ID is provided
if (!isset($_POST['id'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Item ID is required']);
    exit();
}

try {
    // Start transaction
    $pdo->beginTransaction();

    // Get item details before deletion
    $stmt = $pdo->prepare("SELECT * FROM inventory WHERE id = ?");
    $stmt->execute([$_POST['id']]);
    $item = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$item) {
        throw new Exception('Item not found');
    }

    // Add to history
    $stmt = $pdo->prepare("
        INSERT INTO inventory_history (item_id, action, quantity, modified_by, date_modified)
        VALUES (?, ?, ?, ?, NOW())
    ");
    $stmt->execute([
        $_POST['id'],
        "Item deleted",
        0,
        $_SESSION['user_id']
    ]);

    // Delete the item
    $stmt = $pdo->prepare("DELETE FROM inventory WHERE id = ?");
    $result = $stmt->execute([$_POST['id']]);
    
    if ($result) {
        // Add notification for item deletion
        addInventoryNotification('deleted', $item['name']);
        
        // Commit transaction
        $pdo->commit();
        
        echo json_encode(['success' => true, 'message' => 'Item deleted successfully']);
    } else {
        throw new Exception('Failed to delete item');
    }
} catch (Exception $e) {
    // Rollback transaction on error
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?> 