<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../login.php');
    exit();
}

$item_id = $_GET['id'] ?? 0;

try {
    // Start transaction
    $pdo->beginTransaction();

    // Get item details before deletion
    $stmt = $pdo->prepare("SELECT * FROM inventory WHERE id = ?");
    $stmt->execute([$item_id]);
    $item = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$item) {
        throw new Exception("Item not found");
    }

    // Check if item has any borrowed quantities
    if ($item['borrowed'] > 0) {
        throw new Exception("Cannot delete item while {$item['borrowed']} units are borrowed");
    }

    // Log deletion in history
    $stmt = $pdo->prepare("
        INSERT INTO inventory_history (item_id, action, quantity, modified_by, date_modified)
        VALUES (?, ?, ?, ?, NOW())
    ");
    $stmt->execute([
        $item_id,
        "Item deleted",
        0,
        $_SESSION['user_id']
    ]);

    // Delete the item
    $stmt = $pdo->prepare("DELETE FROM inventory WHERE id = ?");
    $stmt->execute([$item_id]);

    // Commit transaction
    $pdo->commit();

    $_SESSION['success_message'] = "Item '{$item['name']}' has been deleted successfully.";
    header("Location: inventory.php");
    exit();

} catch (Exception $e) {
    // Rollback transaction on error
    $pdo->rollBack();
    
    $_SESSION['error_message'] = $e->getMessage();
    header("Location: view_item.php?id=$item_id");
    exit();
}
?>