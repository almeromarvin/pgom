<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../login.php');
    exit();
}

// Fetch admin user info
$user_id = $_SESSION['user_id'];
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

// Helper for safe output
function safe(
    $value, $default = '-') {
    return htmlspecialchars($value ?: $default);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profile - PGOM Facilities</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
    <link href="../assets/css/notifications.css" rel="stylesheet">
    <style>
        .main-header {
            background-color: #004225;
            padding: 0.75rem 1.5rem;
            color: white;
        }
        .main-header h1 {
            font-size: 1.25rem;
            margin: 0;
        }
        .header-icon {
            color: #ffffff;
            font-size: 1.25rem;
            padding: 0.5rem;
            border-radius: 50rem;
            background-color: rgba(255,255,255,0.08);
            transition: all 0.2s ease;
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .header-icon:hover, .header-icon:focus {
            background-color: rgba(255,255,255,0.18);
            color: #fff;
        }
        .profile-banner {
            background: #237a13;
            height: 120px;
            border-radius: 16px 16px 0 0;
        }
        .profile-card {
            background: #fff;
            border-radius: 16px;
            box-shadow: 0 4px 24px rgba(0,0,0,0.08);
            padding: 2.5rem 2.5rem 2rem 2.5rem;
            margin: -60px auto 2rem auto;
            max-width: 1100px;
            position: relative;
        }
        .profile-avatar {
            width: 110px;
            height: 110px;
            border-radius: 50%;
            background: #17612a;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto;
            font-size: 3.5rem;
            color: #fff;
            border: 5px solid #fff;
            box-shadow: 0 2px 8px rgba(0,0,0,0.07);
            position: absolute;
            left: 50%;
            top: 0;
            transform: translate(-50%, -50%);
        }
        .profile-header {
            margin-top: 70px;
            text-align: center;
        }
        .profile-name {
            font-size: 1.5rem;
            font-weight: 700;
            color: #222;
        }
        .profile-section {
            margin-top: 2.5rem;
        }
        .profile-section-title {
            font-size: 1.1rem;
            font-weight: 600;
            color: #237a13;
            margin-bottom: 1rem;
            border-left: 5px solid #237a13;
            padding-left: 0.75rem;
        }
        .profile-info-list {
            list-style: none;
            padding: 0;
            margin: 0;
        }
        .profile-info-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.75rem 0;
            border-bottom: 1px solid #f0f0f0;
        }
        .profile-info-label {
            color: #6c757d;
            font-size: 1rem;
            font-weight: 600;
        }
        .profile-info-value {
            font-size: 1.08rem;
            font-weight: 400;
            color: #2c3e50;
            text-align: right;
        }
        .profile-section:last-child .profile-info-item:last-child {
            border-bottom: none;
        }
        .change-password-btn {
            margin-top: 0.5rem;
            font-size: 0.95rem;
            padding: 0.4rem 1.2rem;
            border-radius: 8px;
            font-weight: 500;
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            border: none;
            transition: all 0.3s ease;
        }
        
        .change-password-btn:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 15px rgba(40, 167, 69, 0.4);
        }
        
        /* Change Password Modal Styles */
        .modal-content {
            border-radius: 15px;
            overflow: hidden;
        }
        
        .modal-header {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            padding: 1.5rem;
        }
        
        .password-icon {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }
        
        .password-icon i {
            font-size: 2.5rem;
            color: white;
        }
        
        .form-control:focus {
            border-color: #28a745;
            box-shadow: 0 0 0 0.2rem rgba(40, 167, 69, 0.25);
        }
        
        .input-group .btn {
            border-color: #dee2e6;
        }
        
        .input-group .btn:hover {
            background-color: #f8f9fa;
            border-color: #adb5bd;
        }
        
        .progress {
            border-radius: 10px;
            background-color: #f8f9fa;
        }
        
        .progress-bar {
            border-radius: 10px;
            transition: width 0.3s ease;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            border: none;
            border-radius: 8px;
            font-weight: 500;
            transition: all 0.3s ease;
        }
        
        .btn-primary:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 15px rgba(40, 167, 69, 0.4);
        }
        
        .btn-light {
            border-radius: 8px;
            font-weight: 500;
            transition: all 0.3s ease;
        }
        
        .btn-light:hover {
            background-color: #e9ecef;
            transform: translateY(-1px);
        }
        
        .alert {
            border-radius: 10px;
            border: none;
            padding: 1rem;
        }
        
        .alert-success {
            background-color: #d4edda;
            color: #155724;
        }
        
        .alert-warning {
            background-color: #fff3cd;
            color: #856404;
        }
        
        .alert-danger {
            background-color: #f8d7da;
            color: #721c24;
        }
        
        .form-label {
            color: #495057;
            font-size: 0.9rem;
        }
        
        .text-muted {
            font-size: 0.85rem;
        }
        
        .password-match {
            animation: fadeIn 0.3s ease;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .modal-footer {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
        }
        @media (max-width: 991px) {
            .profile-card {
                padding: 2rem 1rem 1.5rem 1rem;
            }
            .profile-section {
                margin-top: 2rem;
            }
        }
        @media (max-width: 767px) {
            .profile-card {
                padding: 1.5rem 0.5rem 1rem 0.5rem;
            }
            .profile-header {
                margin-top: 60px;
            }
        }

        /* Mobile Responsive Styles */
        @media (max-width: 768px) {
            .main-header {
                padding: 0.75rem 1rem;
            }
            
            .main-header h1 {
                font-size: 1.1rem;
            }
            
            .header-icon {
                width: 36px;
                height: 36px;
                font-size: 1.1rem;
            }
            
            .profile-card {
                padding: 1.5rem 1rem 1rem 1rem;
                margin: -60px 1rem 2rem 1rem;
                border-radius: 12px;
            }
            
            .profile-avatar {
                width: 90px;
                height: 90px;
                font-size: 2.8rem;
                border-width: 4px;
            }
            
            .profile-header {
                margin-top: 55px;
            }
            
            .profile-name {
                font-size: 1.3rem;
            }
            
            .profile-section {
                margin-top: 2rem;
            }
            
            .profile-section-title {
                font-size: 1rem;
                margin-bottom: 0.8rem;
            }
            
            .profile-info-item {
                padding: 0.6rem 0;
                flex-direction: column;
                align-items: flex-start;
                gap: 0.3rem;
            }
            
            .profile-info-label {
                font-size: 0.9rem;
                color: #6c757d;
            }
            
            .profile-info-value {
                font-size: 1rem;
                text-align: left;
                font-weight: 500;
            }
            
            .change-password-btn {
                font-size: 0.9rem;
                padding: 0.35rem 1rem;
            }
            
            /* Container adjustments */
            .container-fluid {
                padding-left: 0.5rem !important;
                padding-right: 0.5rem !important;
            }
            
            /* Modal adjustments for mobile */
            .modal-dialog {
                margin: 1rem;
                max-width: calc(100% - 2rem);
            }
            
            .modal-body {
                padding: 1.5rem;
            }
            
            .modal-footer {
                padding: 1rem 1.5rem;
            }
            
            /* Mobile menu toggle */
            .mobile-menu-toggle {
                display: block !important;
                background: none;
                border: none;
                color: white;
                font-size: 1.5rem;
                padding: 0.5rem;
            }
            
            /* Sidebar mobile behavior */
            .sidebar {
                position: fixed;
                top: 0;
                left: -100%;
                width: 280px;
                height: 100vh;
                z-index: 1050;
                transition: left 0.3s ease;
                box-shadow: 2px 0 10px rgba(0,0,0,0.1);
            }
            
            .sidebar.show {
                left: 0;
            }
            
            .main-content {
                margin-left: 0 !important;
                width: 100%;
                overflow-x: hidden;
            }
            
            /* Sidebar overlay for mobile */
            .sidebar-overlay {
                display: none;
                position: fixed;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                background: rgba(0,0,0,0.5);
                z-index: 1040;
            }
            
            .sidebar-overlay.show {
                display: block;
            }
        }

        @media (max-width: 576px) {
            .main-header {
                padding: 0.6rem 0.8rem;
            }
            
            .main-header h1 {
                font-size: 1rem;
            }
            
            .header-icon {
                width: 32px;
                height: 32px;
                font-size: 1rem;
            }
            
            .profile-card {
                padding: 1.2rem 0.8rem 0.8rem 0.8rem;
                margin: -60px 0.5rem 1.5rem 0.5rem;
                border-radius: 10px;
            }
            
            .profile-avatar {
                width: 80px;
                height: 80px;
                font-size: 2.5rem;
                border-width: 3px;
            }
            
            .profile-header {
                margin-top: 50px;
            }
            
            .profile-name {
                font-size: 1.2rem;
            }
            
            .profile-section {
                margin-top: 1.5rem;
            }
            
            .profile-section-title {
                font-size: 0.95rem;
                margin-bottom: 0.6rem;
            }
            
            .profile-info-item {
                padding: 0.5rem 0;
                gap: 0.2rem;
            }
            
            .profile-info-label {
                font-size: 0.85rem;
            }
            
            .profile-info-value {
                font-size: 0.95rem;
            }
            
            .change-password-btn {
                font-size: 0.85rem;
                padding: 0.3rem 0.8rem;
            }
            
            /* Container adjustments for small screens */
            .container-fluid {
                padding-left: 0.25rem !important;
                padding-right: 0.25rem !important;
            }
            
            /* Modal adjustments for small screens */
            .modal-dialog {
                margin: 0.5rem;
                max-width: calc(100% - 1rem);
            }
            
            .modal-body {
                padding: 1rem;
            }
            
            .modal-footer {
                padding: 0.8rem 1rem;
            }
        }

        @media (max-width: 480px) {
            .main-header {
                padding: 0.5rem 0.6rem;
            }
            
            .main-header h1 {
                font-size: 0.9rem;
            }
            
            .header-icon {
                width: 30px;
                height: 30px;
                font-size: 0.9rem;
            }
            
            .profile-card {
                padding: 1rem 0.6rem 0.6rem 0.6rem;
                margin: -60px 0.25rem 1rem 0.25rem;
                border-radius: 8px;
            }
            
            .profile-avatar {
                width: 70px;
                height: 70px;
                font-size: 2.2rem;
                border-width: 3px;
            }
            
            .profile-header {
                margin-top: 45px;
            }
            
            .profile-name {
                font-size: 1.1rem;
            }
            
            .profile-section {
                margin-top: 1.2rem;
            }
            
            .profile-section-title {
                font-size: 0.9rem;
                margin-bottom: 0.5rem;
            }
            
            .profile-info-item {
                padding: 0.4rem 0;
                gap: 0.15rem;
            }
            
            .profile-info-label {
                font-size: 0.8rem;
            }
            
            .profile-info-value {
                font-size: 0.9rem;
            }
            
            .change-password-btn {
                font-size: 0.8rem;
                padding: 0.25rem 0.7rem;
            }
            
            /* Container adjustments for very small screens */
            .container-fluid {
                padding-left: 0.125rem !important;
                padding-right: 0.125rem !important;
            }
            
            /* Modal adjustments for very small screens */
            .modal-dialog {
                margin: 0.25rem;
                max-width: calc(100% - 0.5rem);
            }
            
            .modal-body {
                padding: 0.8rem;
            }
            
            .modal-footer {
                padding: 0.6rem 0.8rem;
            }
        }

        /* Touch-friendly improvements */
        .btn {
            min-height: 44px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }
        
        .list-group-item {
            min-height: 48px;
            display: flex;
            align-items: center;
        }
        
        .dropdown-item {
            min-height: 44px;
            display: flex;
            align-items: center;
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
        <!-- Sidebar -->
        <div class="sidebar bg-light border-end">
            <div class="sidebar-heading">
                <img src="../images/logo.png" alt="PGOM Logo">
                <span>PGOM FACILITIES</span>
            </div>
            <div class="list-group list-group-flush">
                <a href="dashboard.php" class="list-group-item list-group-item-action">
                    <i class="bi bi-speedometer2"></i> Dashboard
                </a>
                <a href="reservation.php" class="list-group-item list-group-item-action">
                    <i class="bi bi-calendar-check"></i> Booking
                </a>
                <a href="inventory.php" class="list-group-item list-group-item-action">
                    <i class="bi bi-box-seam"></i> Inventory
                </a>
                <a href="report.php" class="list-group-item list-group-item-action">
                    <i class="bi bi-file-text"></i> Report
                </a>
                <a href="add_user.php" class="list-group-item list-group-item-action">
                    <i class="bi bi-person-plus"></i> Add User
                </a>
                <a href="history.php" class="list-group-item list-group-item-action">
                    <i class="bi bi-clock-history"></i> History
                </a>
                <a href="calendar.php" class="list-group-item list-group-item-action">
                    <i class="bi bi-calendar3"></i> Calendar View
                </a>
            </div>
        </div>
        <!-- Main Content -->
        <div class="main-content flex-grow-1">
            <!-- Header -->
            <header class="main-header text-white d-flex justify-content-between align-items-center">
                <div class="d-flex align-items-center">
                    <button class="mobile-menu-toggle me-3" id="mobileMenuToggle">
                        <i class="bi bi-list"></i>
                    </button>
                    <h1 class="h3 mb-0 d-flex align-items-center">
                        <i class="bi bi-person-circle me-2"></i>
                        Profile
                    </h1>
                </div>
                <div class="d-flex align-items-center gap-3">
                    <!-- Notification Dropdown -->
                    <div class="header-icon dropdown">
                        <button class="btn p-0 border-0 position-relative" type="button" data-bs-toggle="dropdown" aria-expanded="false" id="notificationDropdown">
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
                    <!-- End Notification Dropdown -->
                    <div class="dropdown">
                        <button class="header-icon dropdown-toggle border-0 bg-transparent" type="button" data-bs-toggle="dropdown" style="width:40px;height:40px;display:flex;align-items:center;justify-content:center;font-size:1.25rem;padding:0.5rem;border-radius:50rem;background-color:rgba(255,255,255,0.08);">
                            <i class="bi bi-person-circle"></i>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><a class="dropdown-item" href="profile.php"><i class="bi bi-person me-2"></i>Profile</a></li>
                            <li><a class="dropdown-item" href="../logout.php"><i class="bi bi-box-arrow-right me-2"></i>Logout</a></li>
                        </ul>
                    </div>
                </div>
            </header>
            
            <!-- Sidebar Overlay for Mobile -->
            <div class="sidebar-overlay" id="sidebarOverlay"></div>
            
            <!-- Profile Content -->
            <div class="container-fluid" style="overflow-x:hidden;">
                <div class="profile-banner position-relative mb-4"></div>
                <div class="profile-card">
                    <div class="profile-avatar">
                        <i class="bi bi-person-circle"></i>
                    </div>
                    <div class="profile-header">
                        <div class="profile-name mb-1"><?php echo safe($user['name']); ?></div>
                        <button class="btn btn-primary change-password-btn" data-bs-toggle="modal" data-bs-target="#changePasswordModal">
                            <i class="bi bi-key me-1"></i> Change Password
                        </button>
                    </div>
                    <div class="row g-4 profile-section">
                        <div class="col-12 col-md-4">
                            <div class="profile-section-title">Personal & Contact</div>
                            <ul class="profile-info-list">
                                <li class="profile-info-item"><span class="profile-info-label">Full Name</span><span class="profile-info-value"><?php echo safe($user['name']); ?></span></li>
                                <li class="profile-info-item"><span class="profile-info-label">Gender</span><span class="profile-info-value"><?php echo safe($user['gender'] ?? '-'); ?></span></li>
                                <li class="profile-info-item"><span class="profile-info-label">Birthday</span><span class="profile-info-value"><?php echo safe(date('F d, Y', strtotime($user['birthday'] ?? ''))); ?></span></li>
                                <li class="profile-info-item"><span class="profile-info-label">Email</span><span class="profile-info-value"><?php echo safe($user['email']); ?></span></li>
                                <li class="profile-info-item"><span class="profile-info-label">Phone</span><span class="profile-info-value"><?php echo safe($user['phone_number'] ?? '-'); ?></span></li>
                                <li class="profile-info-item"><span class="profile-info-label">Address</span><span class="profile-info-value"><?php echo safe($user['address'] ?? '-'); ?></span></li>
                            </ul>
                        </div>
                        <div class="col-12 col-md-4">
                            <div class="profile-section-title">Account Information</div>
                            <ul class="profile-info-list">
                                <li class="profile-info-item"><span class="profile-info-label">Username</span><span class="profile-info-value"><?php echo safe($user['username']); ?></span></li>
                                <li class="profile-info-item"><span class="profile-info-label">Role</span><span class="profile-info-value text-capitalize"><?php echo safe($user['role']); ?></span></li>
                                <li class="profile-info-item"><span class="profile-info-label">Position</span><span class="profile-info-value"><?php echo safe($user['position'] ?? '-'); ?></span></li>
                                <li class="profile-info-item"><span class="profile-info-label">Registration Date</span><span class="profile-info-value"><?php echo safe(date('F d, Y', strtotime($user['created_at'] ?? ''))); ?></span></li>
                            </ul>
                        </div>
                        <div class="col-12 col-md-4">
                            <div class="profile-section-title">ID Information</div>
                            <ul class="profile-info-list">
                                <li class="profile-info-item"><span class="profile-info-label">ID Type</span><span class="profile-info-value"><?php echo safe($user['valid_id_type'] ?? '-'); ?></span></li>
                                <li class="profile-info-item"><span class="profile-info-label">ID Number</span><span class="profile-info-value"><?php echo safe($user['valid_id_number'] ?? '-'); ?></span></li>
                                <li class="profile-info-item"><span class="profile-info-label">Suffix</span><span class="profile-info-value"><?php echo safe($user['suffix'] ?? '-'); ?></span></li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Change Password Modal -->
    <div class="modal fade" id="changePasswordModal" tabindex="-1" aria-labelledby="changePasswordModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow-lg">
                <div class="modal-header bg-primary text-white border-0">
                    <h5 class="modal-title" id="changePasswordModalLabel">
                        <i class="bi bi-shield-lock me-2"></i>Change Password
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form id="changePasswordForm">
                    <div class="modal-body p-4">
                        <div class="text-center mb-4">
                            <div class="password-icon mb-3">
                                <i class="bi bi-key-fill text-primary" style="font-size: 2.5rem;"></i>
                            </div>
                            <p class="text-muted mb-0">Enter your current password and choose a new secure password</p>
                        </div>
                        
                        <div class="mb-4">
                            <label for="currentPassword" class="form-label fw-semibold">
                                <i class="bi bi-lock me-1"></i>Current Password
                            </label>
                            <div class="input-group">
                                <input type="password" class="form-control border-end-0" id="currentPassword" name="current_password" required>
                                <button class="btn btn-outline-secondary border-start-0" type="button" onclick="togglePassword('currentPassword')">
                                    <i class="bi bi-eye" id="currentPasswordIcon"></i>
                                </button>
                            </div>
                        </div>
                        
                        <div class="mb-4">
                            <label for="newPassword" class="form-label fw-semibold">
                                <i class="bi bi-lock-fill me-1"></i>New Password
                            </label>
                            <div class="input-group">
                                <input type="password" class="form-control border-end-0" id="newPassword" name="new_password" required>
                                <button class="btn btn-outline-secondary border-start-0" type="button" onclick="togglePassword('newPassword')">
                                    <i class="bi bi-eye" id="newPasswordIcon"></i>
                                </button>
                            </div>
                            <div class="password-strength mt-2" id="passwordStrength">
                                <div class="progress" style="height: 4px;">
                                    <div class="progress-bar" role="progressbar" style="width: 0%"></div>
                                </div>
                                <small class="text-muted mt-1 d-block">Password strength: <span id="strengthText">Weak</span></small>
                            </div>
                        </div>
                        
                        <div class="mb-4">
                            <label for="confirmPassword" class="form-label fw-semibold">
                                <i class="bi bi-check-circle me-1"></i>Confirm New Password
                            </label>
                            <div class="input-group">
                                <input type="password" class="form-control border-end-0" id="confirmPassword" name="confirm_password" required>
                                <button class="btn btn-outline-secondary border-start-0" type="button" onclick="togglePassword('confirmPassword')">
                                    <i class="bi bi-eye" id="confirmPasswordIcon"></i>
                                </button>
                            </div>
                            <div class="password-match mt-2" id="passwordMatch" style="display: none;">
                                <small class="text-success"><i class="bi bi-check-circle me-1"></i>Passwords match</small>
                            </div>
                        </div>
                        
                        <div id="passwordMessage" class="alert" style="display: none;"></div>
                    </div>
                    <div class="modal-footer border-0 bg-light">
                        <button type="button" class="btn btn-light px-4" data-bs-dismiss="modal">
                            <i class="bi bi-x-circle me-1"></i>Cancel
                        </button>
                        <button type="submit" class="btn btn-primary px-4">
                            <span class="spinner-border spinner-border-sm me-2" style="display: none;" id="passwordSpinner"></span>
                            <i class="bi bi-check-circle me-1"></i>Update Password
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/notifications.js"></script>
    
    <script>
        // Mobile menu functionality
        document.addEventListener('DOMContentLoaded', function() {
            const mobileMenuToggle = document.getElementById('mobileMenuToggle');
            const sidebar = document.querySelector('.sidebar');
            const sidebarOverlay = document.getElementById('sidebarOverlay');
            
            // Toggle mobile menu
            mobileMenuToggle.addEventListener('click', function() {
                sidebar.classList.toggle('show');
                sidebarOverlay.classList.toggle('show');
                document.body.style.overflow = sidebar.classList.contains('show') ? 'hidden' : '';
            });
            
            // Close menu when clicking overlay
            sidebarOverlay.addEventListener('click', function() {
                sidebar.classList.remove('show');
                sidebarOverlay.classList.remove('show');
                document.body.style.overflow = '';
            });
            
            // Close menu when clicking on a link (mobile)
            const sidebarLinks = sidebar.querySelectorAll('.list-group-item');
            sidebarLinks.forEach(link => {
                link.addEventListener('click', function() {
                    if (window.innerWidth <= 768) {
                        sidebar.classList.remove('show');
                        sidebarOverlay.classList.remove('show');
                        document.body.style.overflow = '';
                    }
                });
            });
            
            // Handle window resize
            window.addEventListener('resize', function() {
                if (window.innerWidth > 768) {
                    sidebar.classList.remove('show');
                    sidebarOverlay.classList.remove('show');
                    document.body.style.overflow = '';
                }
            });

            // Touch-friendly improvements
            const buttons = document.querySelectorAll('.btn');
            buttons.forEach(button => {
                button.addEventListener('touchstart', function() {
                    this.style.transform = 'scale(0.98)';
                });
                
                button.addEventListener('touchend', function() {
                    this.style.transform = 'scale(1)';
                });
            });
            
            // Prevent zoom on double tap (iOS)
            let lastTouchEnd = 0;
            document.addEventListener('touchend', function(event) {
                const now = (new Date()).getTime();
                if (now - lastTouchEnd <= 300) {
                    event.preventDefault();
                }
                lastTouchEnd = now;
            }, false);
        });

        // Password visibility toggle
        function togglePassword(fieldId) {
            const field = document.getElementById(fieldId);
            const icon = document.getElementById(fieldId + 'Icon');
            
            if (field.type === 'password') {
                field.type = 'text';
                icon.className = 'bi bi-eye-slash';
            } else {
                field.type = 'password';
                icon.className = 'bi bi-eye';
            }
        }
        
        // Password strength checker
        function checkPasswordStrength(password) {
            let strength = 0;
            let feedback = [];
            
            if (password.length >= 6) strength += 1;
            if (password.length >= 8) strength += 1;
            if (/[a-z]/.test(password)) strength += 1;
            if (/[A-Z]/.test(password)) strength += 1;
            if (/[0-9]/.test(password)) strength += 1;
            if (/[^A-Za-z0-9]/.test(password)) strength += 1;
            
            const progressBar = document.querySelector('#passwordStrength .progress-bar');
            const strengthText = document.getElementById('strengthText');
            
            let strengthLabel = 'Weak';
            let strengthColor = 'danger';
            
            if (strength >= 5) {
                strengthLabel = 'Strong';
                strengthColor = 'success';
            } else if (strength >= 3) {
                strengthLabel = 'Good';
                strengthColor = 'warning';
            } else if (strength >= 2) {
                strengthLabel = 'Fair';
                strengthColor = 'info';
            }
            
            progressBar.style.width = (strength / 6 * 100) + '%';
            progressBar.className = `progress-bar bg-${strengthColor}`;
            strengthText.textContent = strengthLabel;
            strengthText.className = `text-${strengthColor}`;
        }
        
        // Password match checker
        function checkPasswordMatch() {
            const newPassword = document.getElementById('newPassword').value;
            const confirmPassword = document.getElementById('confirmPassword').value;
            const matchDiv = document.getElementById('passwordMatch');
            
            if (confirmPassword && newPassword === confirmPassword) {
                matchDiv.style.display = 'block';
            } else {
                matchDiv.style.display = 'none';
            }
        }
        
        // Event listeners for password fields
        document.getElementById('newPassword').addEventListener('input', function() {
            checkPasswordStrength(this.value);
            checkPasswordMatch();
        });
        
        document.getElementById('confirmPassword').addEventListener('input', function() {
            checkPasswordMatch();
        });
        
        // Change Password Functionality
        document.getElementById('changePasswordForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const currentPassword = document.getElementById('currentPassword').value;
            const newPassword = document.getElementById('newPassword').value;
            const confirmPassword = document.getElementById('confirmPassword').value;
            const messageDiv = document.getElementById('passwordMessage');
            const spinner = document.getElementById('passwordSpinner');
            const submitBtn = document.querySelector('#changePasswordForm button[type="submit"]');
            
            // Reset message
            messageDiv.style.display = 'none';
            messageDiv.className = 'alert';
            
            // Enhanced validation
            if (!currentPassword) {
                showMessage('Please enter your current password', 'warning');
                return;
            }
            
            if (newPassword.length < 6) {
                showMessage('New password must be at least 6 characters long', 'warning');
                return;
            }
            
            if (newPassword !== confirmPassword) {
                showMessage('New passwords do not match', 'warning');
                return;
            }
            
            if (currentPassword === newPassword) {
                showMessage('New password must be different from current password', 'warning');
                return;
            }
            
            // Show loading
            spinner.style.display = 'inline-block';
            submitBtn.disabled = true;
            
            // Send request
            fetch('../user/change_password.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    current_password: currentPassword,
                    new_password: newPassword
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showMessage('Password updated successfully!', 'success');
                    document.getElementById('changePasswordForm').reset();
                    // Reset password strength indicator
                    document.querySelector('#passwordStrength .progress-bar').style.width = '0%';
                    document.getElementById('strengthText').textContent = 'Weak';
                    document.getElementById('strengthText').className = 'text-muted';
                    document.getElementById('passwordMatch').style.display = 'none';
                    
                    setTimeout(() => {
                        const modal = bootstrap.Modal.getInstance(document.getElementById('changePasswordModal'));
                        modal.hide();
                    }, 2000);
                } else {
                    showMessage(data.message || 'Failed to update password', 'danger');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showMessage('An error occurred while updating password', 'danger');
            })
            .finally(() => {
                spinner.style.display = 'none';
                submitBtn.disabled = false;
            });
        });
        
        function showMessage(message, type) {
            const messageDiv = document.getElementById('passwordMessage');
            messageDiv.innerHTML = `
                <div class="d-flex align-items-center">
                    <i class="bi bi-${type === 'success' ? 'check-circle' : type === 'warning' ? 'exclamation-triangle' : 'x-circle'} me-2"></i>
                    ${message}
                </div>
            `;
            messageDiv.className = `alert alert-${type} border-0`;
            messageDiv.style.display = 'block';
        }
        
        // Reset form when modal is closed
        document.getElementById('changePasswordModal').addEventListener('hidden.bs.modal', function () {
            document.getElementById('changePasswordForm').reset();
            document.getElementById('passwordMessage').style.display = 'none';
            // Reset password strength indicator
            document.querySelector('#passwordStrength .progress-bar').style.width = '0%';
            document.getElementById('strengthText').textContent = 'Weak';
            document.getElementById('strengthText').className = 'text-muted';
            document.getElementById('passwordMatch').style.display = 'none';
        });
    </script>
</body>
</html> 