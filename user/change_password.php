<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'user') {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

// Get JSON data
$data = json_decode(file_get_contents('php://input'), true);

if (!isset($data['current_password']) || !isset($data['new_password'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Current password and new password are required']);
    exit();
}

$current_password = $data['current_password'];
$new_password = $data['new_password'];
$user_id = $_SESSION['user_id'];

try {
    // Get current user password
    $stmt = $pdo->prepare("SELECT password FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'User not found']);
        exit();
    }

    // Verify current password
    if (!password_verify($current_password, $user['password'])) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Current password is incorrect']);
        exit();
    }

    // Validate new password
    if (strlen($new_password) < 6) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'New password must be at least 6 characters long']);
        exit();
    }

    // Hash new password
    $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);

    // Update password
    $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
    $stmt->execute([$hashed_password, $user_id]);

    header('Content-Type: application/json');
    echo json_encode(['success' => true, 'message' => 'Password changed successfully']);

} catch (PDOException $e) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?> 