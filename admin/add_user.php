<?php
session_start();
require_once '../config/database.php';
require_once '../includes/notification_helper.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../login.php');
    exit();
}

// Self-healing session: If username is not in session, fetch it from the DB.
if (!isset($_SESSION['username'])) {
    try {
        $stmt = $pdo->prepare("SELECT username FROM users WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($user) {
            $_SESSION['username'] = $user['username'];
        } else {
            $_SESSION['username'] = 'Admin';
        }
    } catch (PDOException $e) {
        $_SESSION['username'] = 'Admin';
    }
}

$message = '';
$error = '';

// Handle Add User submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_user'])) {
    $username = trim($_POST['username']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $role = $_POST['role'];
    $email = trim($_POST['email']);
    $name = trim($_POST['name']);
    $suffix = trim($_POST['suffix']);
    $birthday = $_POST['birthday'];
    $gender = $_POST['gender'];
    $phone_number = trim($_POST['phone_number']);
    $position = trim($_POST['position']);
    $address = trim($_POST['address']);
    $valid_id_type = $_POST['valid_id_type'];
    $valid_id_number = trim($_POST['valid_id_number']);

    // Validate required fields
    $required_fields = ['username', 'password', 'role', 'email', 'name', 'birthday', 'gender', 'phone_number', 'position', 'address', 'valid_id_type', 'valid_id_number'];
    $missing_fields = [];
    
    foreach ($required_fields as $field) {
        if (empty($_POST[$field])) {
            $missing_fields[] = ucfirst(str_replace('_', ' ', $field));
        }
    }
    
    if (!empty($missing_fields)) {
        $error = "Please fill in all required fields: " . implode(', ', $missing_fields);
    } elseif ($password !== $confirm_password) {
        $error = "Passwords do not match.";
    } elseif (strlen($password) < 6) {
        $error = "Password must be at least 6 characters long.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Please enter a valid email address.";
    } elseif (!in_array($gender, ['Male', 'Female', 'Other'])) {
        $error = "Please select a valid gender.";
    } elseif (!in_array($valid_id_type, ['Driver\'s License', 'Passport', 'SSS ID', 'GSIS ID', 'UMID', 'PhilHealth ID', 'TIN ID', 'Voter\'s ID', 'Postal ID', 'School ID', 'Company ID', 'Other'])) {
        $error = "Please select a valid ID type.";
    } else {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
        $stmt->execute([$username, $email]);
        if ($stmt->fetch()) {
            $error = "Username or email already exists.";
        } else {
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            try {
                $stmt = $pdo->prepare("INSERT INTO users (username, password, role, email, name, suffix, birthday, gender, phone_number, position, address, valid_id_type, valid_id_number) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$username, $hashed_password, $role, $email, $name, $suffix, $birthday, $gender, $phone_number, $position, $address, $valid_id_type, $valid_id_number]);
                
                // Create notification for new user addition
                $notification_title = "New User Account Created";
                $notification_message = "A new user account has been created: {$name} ({$username}) - {$role}";
                createNotification($pdo, $notification_title, $notification_message, 'user_added', 'add_user.php');
                
                $message = "User created successfully!";
            } catch (PDOException $e) {
                $error = "Database error: " . $e->getMessage();
            }
        }
    }
}

// Fetch active and deleted users
try {
    $active_users_stmt = $pdo->prepare("SELECT * FROM users WHERE deleted_at IS NULL ORDER BY created_at DESC");
    $active_users_stmt->execute();
    $active_users = $active_users_stmt->fetchAll(PDO::FETCH_ASSOC);

    $deleted_users_stmt = $pdo->prepare("SELECT * FROM users WHERE deleted_at IS NOT NULL ORDER BY deleted_at DESC");
    $deleted_users_stmt->execute();
    $deleted_users = $deleted_users_stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $active_users = [];
    $deleted_users = [];
    $error = "Failed to fetch users: " . $e->getMessage();
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Management - PGOM Facilities</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/notifications.css">
    <style>
        /* Minimal and Professional Add User Form Styles */
        .card {
            border: none;
            box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
            border-radius: 0.75rem;
        }
        
        .card-header {
            border-radius: 0.75rem 0.75rem 0 0 !important;
            border-bottom: none;
            padding: 1.5rem;
        }
        
        .card-body {
            padding: 2rem;
        }
        
        .form-control, .form-select {
            border: 1px solid #e9ecef;
            border-radius: 0.5rem;
            transition: all 0.2s ease-in-out;
        }
        
        .form-control:focus, .form-select:focus {
            border-color: #198754;
            box-shadow: 0 0 0 0.2rem rgba(25, 135, 84, 0.25);
        }
        
        .form-control-lg, .form-select-lg {
            padding: 0.75rem 1rem;
            font-size: 1rem;
        }
        
        .form-label {
            color: #495057;
            margin-bottom: 0.5rem;
        }
        
        .form-label.fw-semibold {
            font-weight: 600;
        }
        
        .text-primary {
            color: #0d6efd !important;
        }
        
        .text-success {
            color: #198754 !important;
        }
        
        .border-bottom {
            border-bottom: 2px solid #e9ecef !important;
        }
        
        .btn-success {
            background: linear-gradient(135deg, #198754 0%, #157347 100%);
            border: none;
            border-radius: 0.5rem;
            padding: 0.75rem 2rem;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        
        .btn-success:hover {
            background: linear-gradient(135deg, #157347 0%, #146c43 100%);
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(25, 135, 84, 0.3);
        }
        
        .btn-lg {
            padding: 1rem 3rem;
            font-size: 1.1rem;
        }
        
        .invalid-feedback {
            font-size: 0.875rem;
            color: #dc3545;
        }
        
        .form-control.is-invalid, .form-select.is-invalid {
            border-color: #dc3545;
        }
        
        .form-control.is-invalid:focus, .form-select.is-invalid:focus {
            border-color: #dc3545;
            box-shadow: 0 0 0 0.2rem rgba(220, 53, 69, 0.25);
        }
        
        /* Section headers */
        h6.text-success {
            font-weight: 600;
            font-size: 1.1rem;
            margin-bottom: 1.5rem;
        }
        
        /* Mobile menu toggle - hidden by default on desktop */
        .mobile-menu-toggle {
            display: none;
            background: rgba(255, 255, 255, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
            color: white;
            font-size: 1.5rem;
            padding: 0.75rem;
            cursor: pointer;
            min-width: 48px;
            min-height: 48px;
            align-items: center;
            justify-content: center;
            border-radius: 6px;
            transition: all 0.2s ease;
            z-index: 1060;
            position: relative;
            -webkit-tap-highlight-color: transparent;
            touch-action: manipulation;
            user-select: none;
        }
        
        .mobile-menu-toggle:hover {
            background-color: rgba(255, 255, 255, 0.2);
            transform: scale(1.05);
        }
        
        .mobile-menu-toggle:active {
            background-color: rgba(255, 255, 255, 0.2);
        }
        
        .mobile-menu-toggle:focus {
            outline: 2px solid rgba(255, 255, 255, 0.5);
            outline-offset: 2px;
        }

        /* Screen reader only class for accessibility */
        .sr-only {
            position: absolute;
            width: 1px;
            height: 1px;
            padding: 0;
            margin: -1px;
            overflow: hidden;
            clip: rect(0, 0, 0, 0);
            white-space: nowrap;
            border: 0;
        }
        
        /* Responsive adjustments */
        @media (max-width: 768px) {
            .card-body {
                padding: 1.5rem;
            }
            
            .btn-lg {
                padding: 0.875rem 2rem;
                font-size: 1rem;
            }

            /* Form responsive improvements */
            .form-control-lg, .form-select-lg {
                padding: 0.6rem 0.8rem;
                font-size: 0.9rem;
            }

            /* Form layout improvements for mobile */
            .row .col-md-4,
            .row .col-md-3,
            .row .col-md-6,
            .row .col-md-2 {
                margin-bottom: 1rem;
            }

            /* Label improvements */
            .form-label {
                font-size: 0.9rem;
                margin-bottom: 0.4rem;
                font-weight: 600;
            }

            /* Section headers */
            h6.text-success {
                font-size: 1rem;
                margin-bottom: 1rem;
            }

            /* Modal responsive */
            .modal-dialog {
                margin: 1rem;
                max-width: calc(100% - 2rem);
            }

            .modal-body {
                padding: 1.5rem;
            }

            /* Table responsive */
            .table-responsive {
                font-size: 0.875rem;
            }

            .table td, .table th {
                padding: 0.5rem 0.25rem;
            }

            /* Action buttons responsive */
            .action-buttons .btn {
                padding: 0.25rem 0.5rem;
                font-size: 0.75rem;
            }

            /* Tab responsive */
            .nav-tabs .nav-link {
                padding: 0.5rem 1rem;
                font-size: 0.875rem;
            }

            /* Mobile menu toggle */
            .mobile-menu-toggle {
                display: block !important;
                background: none;
                border: none;
                color: white;
                font-size: 1.5rem;
                padding: 0.5rem;
                cursor: pointer;
                min-width: 44px;
                min-height: 44px;
                align-items: center;
                justify-content: center;
                border-radius: 4px;
                transition: background-color 0.2s ease;
                z-index: 1060;
                position: relative;
            }
            
            .mobile-menu-toggle:hover {
                background-color: rgba(255, 255, 255, 0.1);
            }
            
            .mobile-menu-toggle:active {
                background-color: rgba(255, 255, 255, 0.2);
            }
            
            .mobile-menu-toggle:focus {
                outline: 2px solid rgba(255, 255, 255, 0.5);
                outline-offset: 2px;
            }

            /* Sidebar mobile behavior */
            .sidebar {
                position: fixed;
                top: 0;
                left: -280px !important; /* Start off-screen */
                width: 280px;
                height: 100vh;
                z-index: 9999; /* Force on top */
                background-color: #ffffff !important; /* Override all other styles */
                box-shadow: 2px 0 10px rgba(0,0,0,0.2);
                transition: left 0.3s ease-in-out;
            }
            
            .sidebar.show {
                left: 0 !important; /* Slide into view */
            }
            
            .main-content {
                margin-left: 0 !important;
                width: 100%;
            }
            
            .header-title {
                font-size: 1.1rem !important;
            }

            /* Container adjustments */
            .container-fluid {
                padding-left: 1rem !important;
                padding-right: 1rem !important;
            }

            /* Form control spacing */
            .form-control, .form-select {
                margin-bottom: 0.75rem;
            }

            .form-label {
                margin-bottom: 0.4rem;
                font-size: 0.9rem;
            }

            .row.mb-4 {
                margin-bottom: 1.5rem !important;
            }

            /* Ensure proper stacking */
            .col-md-4, .col-md-3, .col-md-6, .col-md-2 {
                margin-bottom: 1rem;
            }

            /* Sidebar overlay for mobile */
            .sidebar-overlay {
                position: fixed;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                background-color: rgba(0, 0, 0, 0.5);
                z-index: 9998; /* Directly below sidebar */
                opacity: 0;
                visibility: hidden;
                transition: opacity 0.3s ease-in-out, visibility 0s 0.3s;
            }
            
            .sidebar-overlay.show {
                opacity: 1;
                visibility: visible;
                transition: opacity 0.3s ease-in-out;
            }
        }

        @media (max-width: 576px) {
            .card-body {
                padding: 1rem;
            }

            .header-title {
                font-size: 1rem !important;
            }

            /* Form controls for small screens */
            .form-control-lg, .form-select-lg {
                padding: 0.5rem 0.7rem;
                font-size: 0.85rem;
            }

            /* Form layout for very small screens */
            .row .col-md-4,
            .row .col-md-3,
            .row .col-md-6,
            .row .col-md-2 {
                margin-bottom: 1.5rem;
            }

            /* Labels for very small screens */
            .form-label {
                font-size: 0.85rem;
                margin-bottom: 0.3rem;
                line-height: 1.2;
            }

            /* Section headers for small screens */
            h6.text-success {
                font-size: 0.9rem;
                margin-bottom: 0.8rem;
            }

            /* Modal for small screens */
            .modal-dialog {
                margin: 0.5rem;
                max-width: calc(100% - 1rem);
            }

            .modal-body {
                padding: 0.75rem;
            }

            /* Table for small screens */
            .table-responsive {
                font-size: 0.8rem;
            }

            .table td, .table th {
                padding: 0.4rem 0.2rem;
            }

            /* Action buttons for small screens */
            .action-buttons .btn {
                padding: 0.2rem 0.4rem;
                font-size: 0.7rem;
            }

            /* Tab for small screens */
            .nav-tabs .nav-link {
                padding: 0.4rem 0.8rem;
                font-size: 0.8rem;
            }

            /* Hide some table columns on very small screens */
            .table-responsive .table th:nth-child(4),
            .table-responsive .table td:nth-child(4),
            .table-responsive .table th:nth-child(5),
            .table-responsive .table td:nth-child(5) {
                display: none;
            }

            /* Container adjustments for small screens */
            .container-fluid {
                padding-left: 0.5rem !important;
                padding-right: 0.5rem !important;
            }

            /* Form control spacing for small screens */
            .form-control, .form-select {
                margin-bottom: 1rem;
            }

            .form-label {
                margin-bottom: 0.3rem;
                font-size: 0.85rem;
            }

            .row.mb-4 {
                margin-bottom: 1rem !important;
            }

            /* More spacing for very small screens */
            .col-md-4, .col-md-3, .col-md-6, .col-md-2 {
                margin-bottom: 1.5rem;
            }
        }

        @media (max-width: 480px) {
            .card-body {
                padding: 0.75rem;
            }

            .header-title {
                font-size: 0.9rem !important;
            }

            /* Form controls for very small screens */
            .form-control-lg, .form-select-lg {
                padding: 0.4rem 0.6rem;
                font-size: 0.8rem;
            }

            /* Form layout for very small screens */
            .row .col-md-4,
            .row .col-md-3,
            .row .col-md-6,
            .row .col-md-2 {
                margin-bottom: 2rem;
            }

            /* Labels for very small screens */
            .form-label {
                font-size: 0.8rem;
                margin-bottom: 0.25rem;
                line-height: 1.1;
            }

            /* Section headers for very small screens */
            h6.text-success {
                font-size: 0.85rem;
                margin-bottom: 0.6rem;
            }

            /* Modal for very small screens */
            .modal-dialog {
                margin: 0.25rem;
                max-width: calc(100% - 0.5rem);
            }

            .modal-body {
                padding: 0.5rem;
            }

            /* Table for very small screens */
            .table-responsive {
                font-size: 0.75rem;
            }

            .table td, .table th {
                padding: 0.3rem 0.15rem;
            }

            /* Action buttons for very small screens */
            .action-buttons .btn {
                padding: 0.15rem 0.3rem;
                font-size: 0.65rem;
            }

            /* Tab for very small screens */
            .nav-tabs .nav-link {
                padding: 0.3rem 0.6rem;
                font-size: 0.75rem;
            }

            /* Hide more columns on very small screens */
            .table-responsive .table th:nth-child(6),
            .table-responsive .table td:nth-child(6) {
                display: none;
            }

            /* Container adjustments for very small screens */
            .container-fluid {
                padding-left: 0.25rem !important;
                padding-right: 0.25rem !important;
            }

            /* Form control spacing for very small screens */
            .form-control, .form-select {
                margin-bottom: 1.5rem;
            }

            .form-label {
                margin-bottom: 0.25rem;
                font-size: 0.8rem;
            }

            .row.mb-4 {
                margin-bottom: 0.75rem !important;
            }

            /* More spacing for very small screens */
            .col-md-4, .col-md-3, .col-md-6, .col-md-2 {
                margin-bottom: 2rem;
            }
        }

        /* Touch-friendly improvements */
        @media (hover: none) and (pointer: coarse) {
            .btn {
                min-height: 44px;
                min-width: 44px;
            }

            .form-control, .form-select {
                min-height: 44px;
            }

            .nav-tabs .nav-link {
                min-height: 44px;
            }
        }
        
        /* Tab styling */
        .nav-tabs .nav-link {
            border: none;
            color: #6c757d;
            font-weight: 500;
            padding: 0.75rem 1.5rem;
            border-radius: 0.5rem 0.5rem 0 0;
            transition: all 0.2s ease;
        }
        
        .nav-tabs .nav-link:hover {
            color: #198754;
            background-color: #f8f9fa;
        }
        
        .nav-tabs .nav-link.active {
            color: #198754;
            background-color: #fff;
            border-bottom: 3px solid #198754;
        }

        /* Responsive Tables */
        .table-responsive {
            border-radius: 0.5rem;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }

        .table-responsive .table {
            margin-bottom: 0;
        }

        .table-responsive .table th {
            background: #f8f9fa;
            border-bottom: 2px solid #dee2e6;
            font-weight: 600;
            color: #495057;
            white-space: nowrap;
            padding: 0.75rem 0.5rem;
        }

        .table-responsive .table td {
            vertical-align: middle;
            padding: 0.75rem 0.5rem;
        }

        /* Mobile table improvements */
        @media (max-width: 768px) {
            .table-responsive .table {
                font-size: 0.8rem;
            }

            .table-responsive .table th,
            .table-responsive .table td {
                padding: 0.5rem 0.25rem;
                white-space: normal;
                word-wrap: break-word;
            }

            /* Stack table content on mobile */
            .table-responsive .table td {
                display: block;
                text-align: left;
                border: none;
                padding: 0.25rem 0;
                position: relative;
            }

            .table-responsive .table td:before {
                content: attr(data-label) ": ";
                font-weight: 600;
                color: #495057;
                display: inline-block;
                width: 80px;
                margin-right: 0.5rem;
            }

            .table-responsive .table th {
                display: none;
            }

            .table-responsive .table tr {
                display: block;
                margin-bottom: 1rem;
                border: 1px solid #dee2e6;
                border-radius: 0.5rem;
                padding: 0.75rem;
                background: white;
            }

            /* Action buttons on mobile */
            .action-buttons {
                display: flex;
                gap: 0.5rem;
                justify-content: flex-start;
                margin-top: 0.5rem;
            }

            .action-buttons .btn {
                padding: 0.4rem 0.6rem;
                font-size: 0.75rem;
            }

            /* Hide less important columns on mobile */
            .table-responsive .table th:nth-child(4),
            .table-responsive .table td:nth-child(4),
            .table-responsive .table th:nth-child(5),
            .table-responsive .table td:nth-child(5) {
                display: none;
            }
        }

        @media (max-width: 480px) {
            .table-responsive .table {
                font-size: 0.75rem;
            }

            .table-responsive .table td {
                padding: 0.2rem 0;
            }

            .table-responsive .table td:before {
                width: 70px;
                font-size: 0.7rem;
            }

            .table-responsive .table tr {
                padding: 0.5rem;
                margin-bottom: 0.75rem;
            }

            .action-buttons {
                gap: 0.3rem;
            }

            .action-buttons .btn {
                padding: 0.3rem 0.5rem;
                font-size: 0.7rem;
            }

            /* Hide more columns on very small screens */
            .table-responsive .table th:nth-child(6),
            .table-responsive .table td:nth-child(6) {
                display: none;
            }
        }

        /* Action buttons styling */
        .action-buttons {
            white-space: nowrap;
            display: flex;
            gap: 0.25rem;
            justify-content: center;
        }

        .action-buttons .btn {
            flex-shrink: 0;
        }

        /* Sidebar Toggle Button */
        .sidebar-toggle {
            position: fixed;
            top: 50%;
            left: 0;
            transform: translateY(-50%);
            z-index: 1040;
            background: #004225;
            color: white;
            border: none;
            border-radius: 0 0.5rem 0.5rem 0;
            padding: 0.75rem 0.5rem;
            font-size: 1.2rem;
            box-shadow: 2px 0 8px rgba(0,0,0,0.1);
            transition: all 0.3s ease;
        }

        .sidebar-toggle:hover {
            background: #006633;
            color: white;
        }

        .sidebar-toggle.sidebar-hidden {
            left: 0;
        }

        .sidebar-toggle.sidebar-visible {
            left: 280px;
        }

        /* Sidebar States */
        .sidebar {
            transition: left 0.3s ease;
        }

        .sidebar.hidden {
            left: -280px;
        }

        .sidebar.visible {
            left: 0;
        }

        .main-content {
            transition: margin-left 0.3s ease;
        }

        .main-content.sidebar-hidden {
            margin-left: 0;
        }

        .main-content.sidebar-visible {
            margin-left: 280px;
        }

        /* Mobile sidebar behavior */
        @media (max-width: 768px) {
            .sidebar-toggle {
                display: none;
            }
            
            .sidebar {
                position: fixed;
                top: 0;
                left: -280px !important; /* Start off-screen */
                width: 280px;
                height: 100vh;
                z-index: 9999; /* Force on top */
                background-color: #ffffff !important; /* Override all other styles */
                box-shadow: 2px 0 10px rgba(0,0,0,0.2);
                transition: left 0.3s ease-in-out;
            }
            
            .sidebar.show {
                left: 0 !important; /* Slide into view */
            }
            
            .main-content {
                margin-left: 0 !important;
                width: 100%;
            }
        }

        /* Form layout improvements */
        .form-control, .form-select {
            margin-bottom: 0.5rem;
        }

        .form-label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 600;
            color: #495057;
        }

        /* Prevent label overlapping */
        .form-label.fw-semibold {
            font-weight: 600 !important;
            line-height: 1.3;
            word-wrap: break-word;
        }

        /* Section spacing */
        .row.mb-4 {
            margin-bottom: 2rem !important;
        }

        /* Responsive form improvements */
        @media (max-width: 768px) {
            .form-control, .form-select {
                margin-bottom: 0.75rem;
            }

            .form-label {
                margin-bottom: 0.4rem;
                font-size: 0.9rem;
            }

            .row.mb-4 {
                margin-bottom: 1.5rem !important;
            }

            /* Ensure proper stacking */
            .col-md-4, .col-md-3, .col-md-6, .col-md-2 {
                margin-bottom: 1rem;
            }
        }

        @media (max-width: 480px) {
            .form-control, .form-select {
                margin-bottom: 1rem;
            }

            .form-label {
                margin-bottom: 0.3rem;
                font-size: 0.85rem;
            }

            .row.mb-4 {
                margin-bottom: 1rem !important;
            }

            /* More spacing for very small screens */
            .col-md-4, .col-md-3, .col-md-6, .col-md-2 {
                margin-bottom: 1.5rem;
            }
        }

        /* Prevent horizontal scrolling on mobile */
        @media (max-width: 768px) {
            body {
                overflow-x: hidden;
            }
            
            .main-content {
                overflow-x: hidden;
            }
            
            .container-fluid {
                overflow-x: hidden;
            }
        }
    </style>
</head>
<body>
    <div class="d-flex">
        <?php include '../components/sidebar.php'; ?>

        <!-- Sidebar Overlay for Mobile -->
        <div class="sidebar-overlay" id="sidebarOverlay"></div>

        <div class="main-content flex-grow-1">
            <header class="main-header text-white d-flex justify-content-between align-items-center">
                <div class="d-flex align-items-center">
                    <button class="mobile-menu-toggle me-3" id="mobileMenuToggle" type="button">
                        <i class="bi bi-list"></i>
                    </button>
                    <h1 class="h3 mb-0 d-flex align-items-center header-title">
                        <i class="bi bi-people-fill me-2"></i>
                        User Management
                    </h1>
                </div>
                <div class="d-flex align-items-center gap-3">
                    <div class="header-icon dropdown">
                        <button class="btn p-0 border-0 position-relative bg-transparent" type="button" data-bs-toggle="dropdown" aria-expanded="false" id="notificationDropdown">
                            <i class="bi bi-bell"></i>
                            <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger notification-badge" style="display: none;">
                                0
                            </span>
                        </button>
                        <div class="dropdown-menu dropdown-menu-end notification-dropdown">
                            <div class="notification-header border-bottom p-3">
                                <div class="d-flex justify-content-between align-items-center">
                                    <h6 class="mb-0">Notifications</h6>
                                    <button class="btn btn-link text-decoration-none p-0" id="markAllRead">Mark all as read</button>
                                </div>
                            </div>
                            <div class="notification-body">
                                <div class="notifications-list p-3">
                                    <!-- Notifications will be loaded here -->
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="dropdown">
                        <button class="header-icon dropdown-toggle border-0 bg-transparent" type="button" data-bs-toggle="dropdown">
                            <i class="bi bi-person-circle"></i>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <!-- <li><span class="dropdown-item-text">Welcome, <?php echo htmlspecialchars($_SESSION['username']); ?></span></li> -->
                            <!-- <li><hr class="dropdown-divider"></li> -->
                            <li><a class="dropdown-item" href="profile.php"><i class="bi bi-person me-2"></i>Profile</a></li>
                            <li><a class="dropdown-item" href="../logout.php"><i class="bi bi-box-arrow-right me-2"></i>Logout</a></li>
                        </ul>
                    </div>
                </div>
            </header>

            <div class="container-fluid mt-4">
                <?php if ($message): ?>
                    <div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div>
                <?php endif; ?>
                <?php if ($error): ?>
                    <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
                <?php endif; ?>

                <ul class="nav nav-tabs" id="userTabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="users-tab" data-bs-toggle="tab" data-bs-target="#users" type="button" role="tab" aria-controls="users" aria-selected="true"><i class="bi bi-people-fill"></i> Users</button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="add-user-tab" data-bs-toggle="tab" data-bs-target="#add-user" type="button" role="tab" aria-controls="add-user" aria-selected="false"><i class="bi bi-person-plus-fill"></i> Add User</button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="deleted-users-tab" data-bs-toggle="tab" data-bs-target="#deleted-users" type="button" role="tab" aria-controls="deleted-users" aria-selected="false"><i class="bi bi-trash-fill"></i> Deleted Users</button>
                    </li>
                </ul>

                <div class="tab-content" id="userTabsContent">
                    <!-- Users Tab -->
                    <div class="tab-pane fade show active" id="users" role="tabpanel" aria-labelledby="users-tab">
                        <div class="card mt-3">
                            <div class="card-header">
                                All Active Users
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-striped table-hover">
                                        <thead>
                                            <tr>
                                                <th>Name</th>
                                                <th>Username</th>
                                                <th>Email</th>
                                                <th>Position</th>
                                                <th>Phone</th>
                                                <th>Role</th>
                                                <th>Created At</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($active_users as $user): ?>
                                            <tr>
                                                <td data-label="Name"><?php echo htmlspecialchars($user['name'] . ($user['suffix'] ? ' ' . $user['suffix'] : '')); ?></td>
                                                <td data-label="Username"><?php echo htmlspecialchars($user['username']); ?></td>
                                                <td data-label="Email"><?php echo htmlspecialchars($user['email']); ?></td>
                                                <td data-label="Position"><?php echo htmlspecialchars($user['position'] ?? 'N/A'); ?></td>
                                                <td data-label="Phone"><?php echo htmlspecialchars($user['phone_number'] ?? 'N/A'); ?></td>
                                                <td data-label="Role"><span class="badge bg-<?php echo $user['role'] === 'admin' ? 'primary' : 'secondary'; ?>"><?php echo ucfirst($user['role']); ?></span></td>
                                                <td data-label="Created At"><?php echo date('M d, Y', strtotime($user['created_at'])); ?></td>
                                                <td data-label="Actions" class="action-buttons">
                                                    <button class="btn btn-sm btn-outline-info view-user" data-user-id="<?php echo $user['id']; ?>" title="View"><i class="bi bi-eye"></i></button>
                                                    <button class="btn btn-sm btn-outline-primary edit-user" data-user-id="<?php echo $user['id']; ?>" title="Edit"><i class="bi bi-pencil-square"></i></button>
                                                    <button class="btn btn-sm btn-outline-danger delete-user" data-user-id="<?php echo $user['id']; ?>" data-username="<?php echo htmlspecialchars($user['username']); ?>" title="Delete"><i class="bi bi-trash"></i></button>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                            <?php if (empty($active_users)): ?>
                                            <tr><td colspan="8" class="text-center">No active users found.</td></tr>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Add User Tab -->
                    <div class="tab-pane fade" id="add-user" role="tabpanel" aria-labelledby="add-user-tab">
                        <div class="card mt-3">
                            <div class="card-header bg-success text-white">
                                <h5 class="mb-0"><i class="bi bi-person-plus me-2"></i>Create New User Account</h5>
                            </div>
                            <div class="card-body p-4">
                                <form method="POST" action="add_user.php" class="needs-validation" novalidate>
                                    <!-- Personal Information Section -->
                                    <div class="row mb-4">
                                        <div class="col-12">
                                            <h6 class="text-success border-bottom pb-2 mb-3">
                                                <i class="bi bi-person me-2"></i>Personal Information
                                            </h6>
                                        </div>
                                        <div class="col-md-4 mb-3">
                                            <label for="name" class="form-label fw-semibold">Full Name <span class="text-danger">*</span></label>
                                            <input type="text" class="form-control form-control-lg" id="name" name="name" autocomplete="name" required>
                                            <div class="invalid-feedback">Please enter the full name.</div>
                                        </div>
                                        <div class="col-md-2 mb-3">
                                            <label for="suffix" class="form-label fw-semibold">Suffix</label>
                                            <select class="form-select form-select-lg" id="suffix" name="suffix" autocomplete="off">
                                                <option value="">None</option>
                                                <option value="Jr.">Jr.</option>
                                                <option value="Sr.">Sr.</option>
                                                <option value="I">I</option>
                                                <option value="II">II</option>
                                                <option value="III">III</option>
                                                <option value="IV">IV</option>
                                                <option value="V">V</option>
                                            </select>
                                        </div>
                                        <div class="col-md-3 mb-3">
                                            <label for="birthday" class="form-label fw-semibold">Birthday <span class="text-danger">*</span></label>
                                            <input type="date" class="form-control form-control-lg" id="birthday" name="birthday" autocomplete="bday" required>
                                            <div class="invalid-feedback">Please select a birthday.</div>
                                        </div>
                                        <div class="col-md-3 mb-3">
                                            <label for="gender" class="form-label fw-semibold">Gender <span class="text-danger">*</span></label>
                                            <select class="form-select form-select-lg" id="gender" name="gender" autocomplete="sex" required>
                                                <option value="">Select Gender</option>
                                                <option value="Male">Male</option>
                                                <option value="Female">Female</option>
                                                <option value="Other">Other</option>
                                            </select>
                                            <div class="invalid-feedback">Please select a gender.</div>
                                        </div>
                                    </div>

                                    <!-- Account Information Section -->
                                    <div class="row mb-4">
                                        <div class="col-12">
                                            <h6 class="text-success border-bottom pb-2 mb-3">
                                                <i class="bi bi-shield-lock me-2"></i>Account Information
                                            </h6>
                                        </div>
                                        <div class="col-md-4 mb-3">
                                            <label for="username" class="form-label fw-semibold">Username <span class="text-danger">*</span></label>
                                            <input type="text" class="form-control form-control-lg" id="username" name="username" autocomplete="username" required>
                                            <div class="invalid-feedback">Please enter a username.</div>
                                        </div>
                                        <div class="col-md-4 mb-3">
                                            <label for="email" class="form-label fw-semibold">Email Address <span class="text-danger">*</span></label>
                                            <input type="email" class="form-control form-control-lg" id="email" name="email" autocomplete="email" required>
                                            <div class="invalid-feedback">Please enter a valid email address.</div>
                                        </div>
                                        <div class="col-md-4 mb-3">
                                            <label for="role" class="form-label fw-semibold">Role <span class="text-danger">*</span></label>
                                            <select class="form-select form-select-lg" id="role" name="role" autocomplete="off" required>
                                                <option value="user" selected>User</option>
                                                <option value="admin">Admin</option>
                                            </select>
                                            <div class="invalid-feedback">Please select a role.</div>
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <label for="password" class="form-label fw-semibold">Password <span class="text-danger">*</span></label>
                                            <input type="password" class="form-control form-control-lg" id="password" name="password" autocomplete="new-password" required>
                                            <div class="invalid-feedback">Please enter a password (minimum 6 characters).</div>
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <label for="confirm_password" class="form-label fw-semibold">Confirm Password <span class="text-danger">*</span></label>
                                            <input type="password" class="form-control form-control-lg" id="confirm_password" name="confirm_password" autocomplete="new-password" required>
                                            <div class="invalid-feedback">Please confirm your password.</div>
                                        </div>
                                    </div>

                                    <!-- Contact & Professional Information Section -->
                                    <div class="row mb-4">
                                        <div class="col-12">
                                            <h6 class="text-success border-bottom pb-2 mb-3">
                                                <i class="bi bi-telephone me-2"></i>Contact & Professional Information
                                            </h6>
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <label for="phone_number" class="form-label fw-semibold">Phone Number <span class="text-danger">*</span></label>
                                            <input type="tel" class="form-control form-control-lg" id="phone_number" name="phone_number" autocomplete="tel" required>
                                            <div class="invalid-feedback">Please enter a phone number.</div>
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <label for="position" class="form-label fw-semibold">Position/Job Title <span class="text-danger">*</span></label>
                                            <input type="text" class="form-control form-control-lg" id="position" name="position" autocomplete="off" required>
                                            <div class="invalid-feedback">Please enter a position or job title.</div>
                                        </div>
                                        <div class="col-12 mb-3">
                                            <label for="address" class="form-label fw-semibold">Complete Address <span class="text-danger">*</span></label>
                                            <textarea class="form-control" id="address" name="address" rows="3" autocomplete="street-address" required></textarea>
                                            <div class="invalid-feedback">Please enter a complete address.</div>
                                        </div>
                                    </div>

                                    <!-- Identification Section -->
                                    <div class="row mb-4">
                                        <div class="col-12">
                                            <h6 class="text-success border-bottom pb-2 mb-3">
                                                <i class="bi bi-card-text me-2"></i>Identification
                                            </h6>
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <label for="valid_id_type" class="form-label fw-semibold">Valid ID Type <span class="text-danger">*</span></label>
                                            <select class="form-select form-select-lg" id="valid_id_type" name="valid_id_type" autocomplete="off" required>
                                                <option value="">Select ID Type</option>
                                                <option value="Driver's License">Driver's License</option>
                                                <option value="Passport">Passport</option>
                                                <option value="SSS ID">SSS ID</option>
                                                <option value="GSIS ID">GSIS ID</option>
                                                <option value="UMID">UMID</option>
                                                <option value="PhilHealth ID">PhilHealth ID</option>
                                                <option value="TIN ID">TIN ID</option>
                                                <option value="Voter's ID">Voter's ID</option>
                                                <option value="Postal ID">Postal ID</option>
                                                <option value="School ID">School ID</option>
                                                <option value="Company ID">Company ID</option>
                                                <option value="Other">Other</option>
                                            </select>
                                            <div class="invalid-feedback">Please select a valid ID type.</div>
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <label for="valid_id_number" class="form-label fw-semibold">Valid ID Number <span class="text-danger">*</span></label>
                                            <input type="text" class="form-control form-control-lg" id="valid_id_number" name="valid_id_number" autocomplete="off" required>
                                            <div class="invalid-feedback">Please enter a valid ID number.</div>
                                        </div>
                                    </div>

                                    <!-- Submit Button -->
                                    <div class="row">
                                        <div class="col-12 text-center">
                                            <button type="submit" name="add_user" class="btn btn-success btn-lg px-5">
                                                <i class="bi bi-person-plus me-2"></i>Create User Account
                                            </button>
                                        </div>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>

                    <!-- Deleted Users Tab -->
                    <div class="tab-pane fade" id="deleted-users" role="tabpanel" aria-labelledby="deleted-users-tab">
                        <div class="card mt-3">
                            <div class="card-header">
                                Deleted User Accounts
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-striped table-hover">
                                        <thead>
                                            <tr>
                                                <th>Name</th>
                                                <th>Username</th>
                                                <th>Email</th>
                                                <th>Position</th>
                                                <th>Phone</th>
                                                <th>Role</th>
                                                <th>Date Deleted</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($deleted_users as $user): ?>
                                            <tr>
                                                <td data-label="Name"><?php echo htmlspecialchars($user['name'] . ($user['suffix'] ? ' ' . $user['suffix'] : '')); ?></td>
                                                <td data-label="Username"><?php echo htmlspecialchars($user['username']); ?></td>
                                                <td data-label="Email"><?php echo htmlspecialchars($user['email']); ?></td>
                                                <td data-label="Position"><?php echo htmlspecialchars($user['position'] ?? 'N/A'); ?></td>
                                                <td data-label="Phone"><?php echo htmlspecialchars($user['phone_number'] ?? 'N/A'); ?></td>
                                                <td data-label="Role"><span class="badge bg-secondary"><?php echo ucfirst($user['role']); ?></span></td>
                                                <td data-label="Date Deleted"><?php echo date('M d, Y', strtotime($user['deleted_at'])); ?></td>
                                                <td data-label="Actions" class="action-buttons">
                                                    <button class="btn btn-sm btn-outline-success restore-user" data-user-id="<?php echo $user['id']; ?>" title="Restore"><i class="bi bi-arrow-counterclockwise"></i></button>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                            <?php if (empty($deleted_users)): ?>
                                            <tr><td colspan="8" class="text-center">No deleted users found.</td></tr>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- View User Modal -->
    <div class="modal fade" id="viewUserModal" tabindex="-1" aria-labelledby="viewUserModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="viewUserModalLabel">User Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="view-name" class="form-label fw-bold">Full Name:</label>
                            <p id="view-name" class="form-control-plaintext"></p>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="view-username" class="form-label fw-bold">Username:</label>
                            <p id="view-username" class="form-control-plaintext"></p>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="view-email" class="form-label fw-bold">Email:</label>
                            <p id="view-email" class="form-control-plaintext"></p>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="view-role" class="form-label fw-bold">Role:</label>
                            <p id="view-role" class="form-control-plaintext"></p>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="view-birthday" class="form-label fw-bold">Birthday:</label>
                            <p id="view-birthday" class="form-control-plaintext"></p>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="view-gender" class="form-label fw-bold">Gender:</label>
                            <p id="view-gender" class="form-control-plaintext"></p>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="view-phone" class="form-label fw-bold">Phone Number:</label>
                            <p id="view-phone" class="form-control-plaintext"></p>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="view-position" class="form-label fw-bold">Position:</label>
                            <p id="view-position" class="form-control-plaintext"></p>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="view-id-type" class="form-label fw-bold">Valid ID Type:</label>
                            <p id="view-id-type" class="form-control-plaintext"></p>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="view-id-number" class="form-label fw-bold">Valid ID Number:</label>
                            <p id="view-id-number" class="form-control-plaintext"></p>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-12 mb-3">
                            <label for="view-address" class="form-label fw-bold">Complete Address:</label>
                            <p id="view-address" class="form-control-plaintext"></p>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="view-created" class="form-label fw-bold">Created At:</label>
                            <p id="view-created" class="form-control-plaintext"></p>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="view-updated" class="form-label fw-bold">Last Updated:</label>
                            <p id="view-updated" class="form-control-plaintext"></p>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Edit User Modal -->
    <div class="modal fade" id="editUserModal" tabindex="-1" aria-labelledby="editUserModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="editUserModalLabel">Edit User</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form id="editUserForm">
                    <div class="modal-body">
                        <label for="edit-user-id" class="sr-only">User ID</label>
                        <input type="hidden" id="edit-user-id" name="user_id">
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label for="edit-name" class="form-label">Full Name *</label>
                                <input type="text" class="form-control" id="edit-name" name="name" autocomplete="name" required>
                            </div>
                            <div class="col-md-2 mb-3">
                                <label for="edit-suffix" class="form-label">Suffix</label>
                                <select class="form-select" id="edit-suffix" name="suffix" autocomplete="off">
                                    <option value="">None</option>
                                    <option value="Jr.">Jr.</option>
                                    <option value="Sr.">Sr.</option>
                                    <option value="I">I</option>
                                    <option value="II">II</option>
                                    <option value="III">III</option>
                                    <option value="IV">IV</option>
                                    <option value="V">V</option>
                                </select>
                            </div>
                            <div class="col-md-3 mb-3">
                                <label for="edit-username" class="form-label">Username *</label>
                                <input type="text" class="form-control" id="edit-username" name="username" autocomplete="username" required>
                            </div>
                            <div class="col-md-3 mb-3">
                                <label for="edit-email" class="form-label">Email Address *</label>
                                <input type="email" class="form-control" id="edit-email" name="email" autocomplete="email" required>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-3 mb-3">
                                <label for="edit-birthday" class="form-label">Birthday *</label>
                                <input type="date" class="form-control" id="edit-birthday" name="birthday" autocomplete="bday" required>
                            </div>
                            <div class="col-md-3 mb-3">
                                <label for="edit-gender" class="form-label">Gender *</label>
                                <select class="form-select" id="edit-gender" name="gender" autocomplete="sex" required>
                                    <option value="">Select Gender</option>
                                    <option value="Male">Male</option>
                                    <option value="Female">Female</option>
                                    <option value="Other">Other</option>
                                </select>
                            </div>
                            <div class="col-md-3 mb-3">
                                <label for="edit-phone" class="form-label">Phone Number *</label>
                                <input type="tel" class="form-control" id="edit-phone" name="phone_number" autocomplete="tel" required>
                            </div>
                            <div class="col-md-3 mb-3">
                                <label for="edit-role" class="form-label">Role *</label>
                                <select class="form-select" id="edit-role" name="role" autocomplete="off" required>
                                    <option value="user">User</option>
                                    <option value="admin">Admin</option>
                                </select>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="edit-position" class="form-label">Position/Job Title *</label>
                                <input type="text" class="form-control" id="edit-position" name="position" autocomplete="off" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="edit-id-type" class="form-label">Valid ID Type *</label>
                                <select class="form-select" id="edit-id-type" name="valid_id_type" autocomplete="off" required>
                                    <option value="">Select ID Type</option>
                                    <option value="Driver's License">Driver's License</option>
                                    <option value="Passport">Passport</option>
                                    <option value="SSS ID">SSS ID</option>
                                    <option value="GSIS ID">GSIS ID</option>
                                    <option value="UMID">UMID</option>
                                    <option value="PhilHealth ID">PhilHealth ID</option>
                                    <option value="TIN ID">TIN ID</option>
                                    <option value="Voter's ID">Voter's ID</option>
                                    <option value="Postal ID">Postal ID</option>
                                    <option value="School ID">School ID</option>
                                    <option value="Company ID">Company ID</option>
                                    <option value="Other">Other</option>
                                </select>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="edit-id-number" class="form-label">Valid ID Number *</label>
                                <input type="text" class="form-control" id="edit-id-number" name="valid_id_number" autocomplete="off" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="edit-password" class="form-label">New Password (leave blank to keep current)</label>
                                <input type="password" class="form-control" id="edit-password" name="password" autocomplete="new-password">
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="edit-confirm-password" class="form-label">Confirm New Password</label>
                                <input type="password" class="form-control" id="edit-confirm-password" name="confirm_password" autocomplete="new-password">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="edit-address" class="form-label">Complete Address *</label>
                                <textarea class="form-control" id="edit-address" name="address" rows="3" autocomplete="street-address" required></textarea>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Update User</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div class="modal fade" id="deleteUserModal" tabindex="-1" aria-labelledby="deleteUserModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="deleteUserModalLabel">Confirm Delete</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to delete user <strong id="delete-username"></strong>?</p>
                    <p class="text-danger">This action cannot be undone. The user will be moved to the deleted users list.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-danger" id="confirmDelete">Delete User</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/notifications.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const mobileMenuToggle = document.getElementById('mobileMenuToggle');
            const sidebar = document.getElementById('sidebar');
            const sidebarOverlay = document.getElementById('sidebarOverlay');

            const openSidebar = () => {
                if (sidebar) sidebar.classList.add('show');
                if (sidebarOverlay) sidebarOverlay.classList.add('show');
                document.body.style.overflow = 'hidden';
            };

            const closeSidebar = () => {
                if (sidebar) sidebar.classList.remove('show');
                if (sidebarOverlay) sidebarOverlay.classList.remove('show');
                document.body.style.overflow = '';
            };

            if (mobileMenuToggle) {
                mobileMenuToggle.addEventListener('click', (e) => {
                    e.stopPropagation();
                    const isSidebarVisible = sidebar && sidebar.classList.contains('show');
                    if (isSidebarVisible) {
                        closeSidebar();
                    } else {
                        openSidebar();
                    }
                });
            }

            if (sidebarOverlay) {
                sidebarOverlay.addEventListener('click', closeSidebar);
            }
            
            if (sidebar) {
                sidebar.addEventListener('click', (e) => {
                    if (e.target.classList.contains('list-group-item')) {
                        if (window.innerWidth <= 768) {
                            closeSidebar();
                        }
                    }
                });
            }

            window.addEventListener('resize', () => {
                if (window.innerWidth > 768) {
                    closeSidebar();
                }
            });

            // --- All other jQuery-dependent code below ---

            // View User functionality
            $(document).on('click', '.view-user', function() {
                const userId = $(this).data('user-id');
                $.get('get_user.php', { user_id: userId }, function(response) {
                    const data = JSON.parse(response);
                    if (data.success) {
                        const user = data.user;
                        $('#view-name').text(user.name);
                        $('#view-username').text(user.username);
                        $('#view-email').text(user.email);
                        $('#view-role').text(user.role.charAt(0).toUpperCase() + user.role.slice(1));
                        $('#view-birthday').text(user.birthday ? new Date(user.birthday).toLocaleDateString() : 'N/A');
                        $('#view-gender').text(user.gender ? user.gender.charAt(0).toUpperCase() + user.gender.slice(1) : 'N/A');
                        $('#view-phone').text(user.phone_number ?? 'N/A');
                        $('#view-position').text(user.position ?? 'N/A');
                        $('#view-id-type').text(user.valid_id_type ?? 'N/A');
                        $('#view-id-number').text(user.valid_id_number ?? 'N/A');
                        $('#view-address').text(user.address ?? 'N/A');
                        $('#view-created').text(new Date(user.created_at).toLocaleDateString());
                        $('#view-updated').text('N/A');
                        $('#viewUserModal').modal('show');
                    } else {
                        alert('Error loading user details: ' + data.message);
                    }
                });
            });

            // Edit User functionality
            $(document).on('click', '.edit-user', function() {
                const userId = $(this).data('user-id');
                $.get('get_user.php', { user_id: userId }, function(response) {
                    const data = JSON.parse(response);
                    if (data.success) {
                        const user = data.user;
                        $('#edit-user-id').val(user.id);
                        $('#edit-name').val(user.name);
                        $('#edit-username').val(user.username);
                        $('#edit-email').val(user.email);
                        $('#edit-role').val(user.role);
                        $('#edit-password').val('');
                        $('#edit-confirm-password').val('');
                        $('#edit-birthday').val(user.birthday ? new Date(user.birthday).toISOString().split('T')[0] : '');
                        $('#edit-gender').val(user.gender || '');
                        $('#edit-phone').val(user.phone_number || '');
                        $('#edit-position').val(user.position || '');
                        $('#edit-suffix').val(user.suffix || '');
                        $('#edit-id-type').val(user.valid_id_type || '');
                        $('#edit-id-number').val(user.valid_id_number || '');
                        $('#edit-address').val(user.address || '');
                        $('#editUserModal').modal('show');
                    } else {
                        alert('Error loading user details: ' + data.message);
                    }
                });
            });

            // Handle edit form submission
            $('#editUserForm').on('submit', function(e) {
                e.preventDefault();
                const formData = $(this).serialize();
                
                $.post('update_user.php', formData, function(response) {
                    const data = JSON.parse(response);
                    if (data.success) {
                        $('#editUserModal').modal('hide');
                        location.reload(); // Refresh the page to show updated data
                    } else {
                        alert('Error updating user: ' + data.message);
                    }
                });
            });

            // Delete User functionality
            $(document).on('click', '.delete-user', function() {
                const userId = $(this).data('user-id');
                const username = $(this).data('username');
                $('#delete-username').text(username);
                $('#confirmDelete').data('user-id', userId);
                $('#deleteUserModal').modal('show');
            });

            // Handle delete confirmation
            $('#confirmDelete').on('click', function() {
                const userId = $(this).data('user-id');
                $.post('delete_user.php', { user_id: userId }, function(response) {
                    const data = JSON.parse(response);
                    if (data.success) {
                        $('#deleteUserModal').modal('hide');
                        location.reload(); // Refresh the page to show updated data
                    } else {
                        alert('Error deleting user: ' + data.message);
                    }
                });
            });

            // Restore user functionality
            $(document).on('click', '.restore-user', function() {
                const userId = $(this).data('user-id');
                if (confirm('Are you sure you want to restore this user?')) {
                    $.post('restore_user.php', { user_id: userId }, function(response) {
                        const data = JSON.parse(response);
                        if (data.success) {
                            location.reload(); // Refresh the page to show updated data
                        } else {
                            alert('Error restoring user: ' + data.message);
                        }
                    });
                }
            });

            // Modal focus management
            $('.modal').on('hidden.bs.modal', function() {
                // Remove focus from any focused element inside the modal
                $(this).find(':focus').blur();
                // Return focus to the element that opened the modal
                if (window.lastFocusedElement) {
                    window.lastFocusedElement.focus();
                }
            });

            // Store the element that opened the modal
            $('.modal').on('show.bs.modal', function() {
                window.lastFocusedElement = document.activeElement;
            });

            // Ensure modals are properly initialized
            $('.modal').each(function() {
                const modal = $(this);
                modal.on('shown.bs.modal', function() {
                    // Focus the first focusable element in the modal
                    const firstFocusable = modal.find('input, select, textarea, button, [tabindex]:not([tabindex="-1"])').first();
                    if (firstFocusable.length) {
                        firstFocusable.focus();
                    }
                });
            });

            // Form validation
            const forms = document.querySelectorAll('.needs-validation');
            Array.from(forms).forEach(form => {
                form.addEventListener('submit', event => {
                    if (!form.checkValidity()) {
                        event.preventDefault();
                        event.stopPropagation();
                    }
                    form.classList.add('was-validated');
                }, false);
            });

            // Password confirmation validation
            $('#confirm_password').on('input', function() {
                const password = $('#password').val();
                const confirmPassword = $(this).val();
                
                if (password !== confirmPassword) {
                    $(this).get(0).setCustomValidity('Passwords do not match');
                } else {
                    $(this).get(0).setCustomValidity('');
                }
            });

            // Password strength validation
            $('#password').on('input', function() {
                const password = $(this).val();
                if (password.length < 6) {
                    $(this).get(0).setCustomValidity('Password must be at least 6 characters long');
                } else {
                    $(this).get(0).setCustomValidity('');
                }
                // Trigger confirm password validation
                $('#confirm_password').trigger('input');
            });
        });
    </script>
</body>
</html>
