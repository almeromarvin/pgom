<?php
session_start();
require_once '../config/database.php';
require_once '../includes/notification_helper.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

// Check if required parameters are present
if (!isset($_POST['item_id']) || !isset($_POST['quantity']) || !isset($_POST['user_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Missing required parameters']);
    exit();
}

$item_id = $_POST['item_id'];
$quantity = (int)$_POST['quantity'];
$user_id = $_POST['user_id'];
$action = $_POST['action'] ?? 'borrow'; // 'borrow' or 'return'

try {
    // Start transaction
    $pdo->beginTransaction();

    // Get item details
    $stmt = $pdo->prepare("SELECT * FROM inventory WHERE id = ?");
    $stmt->execute([$item_id]);
    $item = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$item) {
        throw new Exception('Item not found');
    }

    // Check if there's enough quantity for borrowing
    if ($action === 'borrow' && ($item['total_quantity'] - $item['borrowed'] - $quantity) < 0) {
        throw new Exception('Not enough items available for borrowing');
    }

    // Update inventory
    if ($action === 'borrow') {
        $stmt = $pdo->prepare("UPDATE inventory SET borrowed = borrowed + ? WHERE id = ?");
    } else {
        $stmt = $pdo->prepare("UPDATE inventory SET borrowed = borrowed - ? WHERE id = ?");
    }
    $stmt->execute([$quantity, $item_id]);

    // Add borrowing record
    $stmt = $pdo->prepare("
        INSERT INTO borrowings (item_id, user_id, quantity, borrow_date, status)
        VALUES (?, ?, ?, NOW(), ?)
    ");
    $stmt->execute([$item_id, $user_id, $quantity, $action === 'borrow' ? 'Borrowed' : 'Returned']);

    // Get user name for notification
    $stmt = $pdo->prepare("SELECT name FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    // Add notification
    if ($action === 'borrow') {
        addInventoryNotification('borrowed', $item['name'], $quantity);
        
        // Check if stock is low after borrowing
        $remaining = $item['total_quantity'] - ($item['borrowed'] + $quantity);
        if ($remaining <= $item['minimum_stock']) {
            addInventoryNotification('low_stock', $item['name']);
        }
        if ($remaining === 0) {
            addInventoryNotification('out_of_stock', $item['name']);
        }
    } else {
        addInventoryNotification('returned', $item['name'], $quantity);
    }

    // Commit transaction
    $pdo->commit();

    echo json_encode([
        'success' => true,
        'message' => 'Item ' . ($action === 'borrow' ? 'borrowed' : 'returned') . ' successfully'
    ]);

} catch (Exception $e) {
    $pdo->rollBack();
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?> 