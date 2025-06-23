<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

// Check if it's a POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit();
}

// Validate required fields
$required_fields = ['user_id', 'name', 'username', 'email', 'role', 'birthday', 'gender', 'phone_number', 'position', 'address', 'valid_id_type', 'valid_id_number'];
foreach ($required_fields as $field) {
    if (!isset($_POST[$field]) || empty($_POST[$field])) {
        echo json_encode(['success' => false, 'message' => ucfirst(str_replace('_', ' ', $field)) . ' is required']);
        exit();
    }
}

$user_id = $_POST['user_id'];
$name = trim($_POST['name']);
$username = trim($_POST['username']);
$email = trim($_POST['email']);
$role = $_POST['role'];
$suffix = trim($_POST['suffix'] ?? '');
$birthday = $_POST['birthday'];
$gender = $_POST['gender'];
$phone_number = trim($_POST['phone_number']);
$position = trim($_POST['position']);
$address = trim($_POST['address']);
$valid_id_type = $_POST['valid_id_type'];
$valid_id_number = trim($_POST['valid_id_number']);
$password = isset($_POST['password']) ? $_POST['password'] : '';
$confirm_password = isset($_POST['confirm_password']) ? $_POST['confirm_password'] : '';

// Validate role
if (!in_array($role, ['user', 'admin'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid role']);
    exit();
}

// Validate email format
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['success' => false, 'message' => 'Invalid email format']);
    exit();
}

// Validate gender
if (!in_array($gender, ['Male', 'Female', 'Other'])) {
    echo json_encode(['success' => false, 'message' => 'Please select a valid gender']);
    exit();
}

// Validate valid ID type
$valid_id_types = ['Driver\'s License', 'Passport', 'SSS ID', 'GSIS ID', 'UMID', 'PhilHealth ID', 'TIN ID', 'Voter\'s ID', 'Postal ID', 'School ID', 'Company ID', 'Other'];
if (!in_array($valid_id_type, $valid_id_types)) {
    echo json_encode(['success' => false, 'message' => 'Please select a valid ID type']);
    exit();
}

// Check if password is provided, validate it
if (!empty($password)) {
    if ($password !== $confirm_password) {
        echo json_encode(['success' => false, 'message' => 'Passwords do not match']);
        exit();
    }
    if (strlen($password) < 6) {
        echo json_encode(['success' => false, 'message' => 'Password must be at least 6 characters long']);
        exit();
    }
}

try {
    // Check if username or email already exists for other users
    $stmt = $pdo->prepare("SELECT id FROM users WHERE (username = ? OR email = ?) AND id != ?");
    $stmt->execute([$username, $email, $user_id]);
    if ($stmt->fetch()) {
        echo json_encode(['success' => false, 'message' => 'Username or email already exists']);
        exit();
    }

    // Update user
    if (!empty($password)) {
        // Update with new password
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("UPDATE users SET name = ?, username = ?, email = ?, role = ?, suffix = ?, birthday = ?, gender = ?, phone_number = ?, position = ?, address = ?, valid_id_type = ?, valid_id_number = ?, password = ? WHERE id = ?");
        $stmt->execute([$name, $username, $email, $role, $suffix, $birthday, $gender, $phone_number, $position, $address, $valid_id_type, $valid_id_number, $hashed_password, $user_id]);
    } else {
        // Update without changing password
        $stmt = $pdo->prepare("UPDATE users SET name = ?, username = ?, email = ?, role = ?, suffix = ?, birthday = ?, gender = ?, phone_number = ?, position = ?, address = ?, valid_id_type = ?, valid_id_number = ? WHERE id = ?");
        $stmt->execute([$name, $username, $email, $role, $suffix, $birthday, $gender, $phone_number, $position, $address, $valid_id_type, $valid_id_number, $user_id]);
    }

    if ($stmt->rowCount() > 0) {
        echo json_encode(['success' => true, 'message' => 'User updated successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'No changes made or user not found']);
    }
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?> 