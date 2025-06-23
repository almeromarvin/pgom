<?php
session_start();
require_once '../config/database.php';
require_once '../includes/notification_helper.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../login.php');
    exit();
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $response = array('success' => false, 'message' => '');
    
    try {
        // Validate required fields
        $required_fields = ['name', 'total_quantity', 'minimum_stock', 'status'];
        foreach ($required_fields as $field) {
            if (empty($_POST[$field])) {
                throw new Exception("All fields are required.");
            }
        }

        // Validate numeric fields
        if (!is_numeric($_POST['total_quantity']) || $_POST['total_quantity'] < 0) {
            throw new Exception("Total quantity must be a positive number.");
        }
        if (!is_numeric($_POST['minimum_stock']) || $_POST['minimum_stock'] < 0) {
            throw new Exception("Minimum stock must be a positive number.");
        }
        if ($_POST['minimum_stock'] > $_POST['total_quantity']) {
            throw new Exception("Minimum stock cannot be greater than total quantity.");
        }

        // Start transaction
        $pdo->beginTransaction();

        // Insert the item
        $stmt = $pdo->prepare("INSERT INTO inventory (name, total_quantity, minimum_stock, status, description, last_updated) VALUES (?, ?, ?, ?, ?, NOW())");
        $stmt->execute([
            $_POST['name'],
            $_POST['total_quantity'],
            $_POST['minimum_stock'],
            $_POST['status'],
            $_POST['description'] ?? null
        ]);
        
        $item_id = $pdo->lastInsertId();

        // Add to inventory history
        $stmt = $pdo->prepare("
            INSERT INTO inventory_history (item_id, action, quantity, modified_by, date_modified)
            VALUES (?, ?, ?, ?, NOW())
        ");
        $stmt->execute([
            $item_id,
            "Item added",
            $_POST['total_quantity'],
            $_SESSION['user_id']
        ]);

        // Add notification for new item
        addInventoryNotification('added', $_POST['name'], null, $item_id);

        // Check if initial quantity is below minimum stock
        if ($_POST['total_quantity'] <= $_POST['minimum_stock']) {
            addInventoryNotification('low_stock', $_POST['name'], null, $item_id);
        }
        if ($_POST['total_quantity'] == 0) {
            addInventoryNotification('out_of_stock', $_POST['name'], null, $item_id);
        }

        // Commit transaction
        $pdo->commit();
        
        $response['success'] = true;
        $response['message'] = "Item added successfully!";
        
    } catch (Exception $e) {
        // Rollback transaction on error
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $response['success'] = false;
        $response['message'] = $e->getMessage();
    }
    
    // Send JSON response
    header('Content-Type: application/json');
    echo json_encode($response);
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add New Item - PGOM Facilities</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
    <link href="../assets/css/notifications.css" rel="stylesheet">
    <style>
        .form-card {
            background: var(--bg-secondary);
            border-radius: 15px;
            padding: 2rem;
            box-shadow: 0 0 20px rgba(0,0,0,0.05);
            color: var(--text-primary);
            border: 1px solid var(--border-color);
        }

        .form-label {
            font-weight: 500;
            color: var(--text-primary);
            margin-bottom: 0.5rem;
            font-size: 0.9rem;
        }

        /* Light mode specific styles */
        :root {
            --input-border: #ced4da;
            --input-focus-border: #198754;
            --input-shadow: rgba(0, 0, 0, 0.1);
        }

        /* Dark mode specific styles */
        [data-theme="dark"] {
            --input-border: var(--border-color);
            --input-focus-border: #198754;
            --input-shadow: rgba(0, 0, 0, 0.2);
        }

        .form-control, .form-select {
            background-color: var(--bg-unified);
            border: 2px solid var(--input-border);
            color: var(--text-primary);
            padding: 0.75rem 1rem;
            border-radius: 10px;
            transition: all 0.2s;
            font-size: 0.95rem;
            height: auto;
        }

        .form-control:hover, .form-select:hover {
            border-color: var(--input-focus-border);
        }

        .form-control:focus, .form-select:focus {
            border-color: var(--input-focus-border);
            box-shadow: 0 0 0 3px rgba(25, 135, 84, 0.15);
            background-color: var(--bg-unified);
            color: var(--text-primary);
        }

        .form-control::placeholder {
            color: var(--text-secondary);
            opacity: 0.7;
        }

        .form-select {
            padding-right: 2.5rem;
            background-position: right 1rem center;
        }

        .btn-submit {
            padding: 0.75rem 2rem;
            font-weight: 500;
            border-radius: 10px;
            transition: all 0.3s;
        }

        .btn-light {
            border: 2px solid var(--input-border);
            background-color: var(--bg-unified);
            color: var(--text-primary);
        }

        .btn-light:hover {
            background-color: var(--hover-bg);
            border-color: var(--input-focus-border);
            color: var(--text-primary);
        }

        .btn-submit:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(25, 135, 84, 0.2);
        }

        .form-hint {
            font-size: 0.85rem;
            color: var(--text-secondary);
            margin-top: 0.25rem;
        }

        .alert {
            border-radius: 10px;
            padding: 1rem;
            margin-bottom: 1.5rem;
            border: 1px solid var(--input-border);
        }

        .breadcrumb {
            margin-bottom: 1.5rem;
        }

        .breadcrumb-item a {
            color: var(--text-secondary);
            text-decoration: none;
            transition: color 0.2s;
        }

        .breadcrumb-item a:hover {
            color: #198754;
        }

        .breadcrumb-item.active {
            color: #198754;
        }

        /* Dark mode specific overrides */
        [data-theme="dark"] .form-select {
            background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16'%3e%3cpath fill='none' stroke='%23ffffff' stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='m2 5 6 6 6-6'/%3e%3c/svg%3e");
        }

        [data-theme="dark"] .form-select option {
            background-color: var(--bg-unified);
            color: var(--text-primary);
        }

        [data-theme="dark"] .form-control:disabled,
        [data-theme="dark"] .form-select:disabled {
            background-color: var(--hover-bg);
            color: var(--text-secondary);
            border-color: var(--border-color);
        }

        /* Invalid state styling */
        .form-control.is-invalid,
        .form-select.is-invalid {
            border-color: #dc3545;
            background-image: none;
        }

        .form-control.is-invalid:focus,
        .form-select.is-invalid:focus {
            border-color: #dc3545;
            box-shadow: 0 0 0 3px rgba(220, 53, 69, 0.1);
        }

        .invalid-feedback {
            color: #dc3545;
            font-size: 0.85rem;
            margin-top: 0.25rem;
        }

        /* Toast styling */
        .toast {
            background-color: var(--bg-secondary);
            border: 1px solid var(--border-color);
        }

        .toast-body {
            color: var(--text-primary);
        }

        /* Dropdown menu styling */
        .dropdown-menu {
            border: 2px solid var(--input-border);
            border-radius: 10px;
            overflow: hidden;
        }

        .dropdown-item {
            color: var(--text-primary);
            padding: 0.75rem 1rem;
        }

        .dropdown-item:hover {
            background-color: var(--hover-bg);
            color: var(--text-primary);
        }

        /* Header styles */
        .main-header {
            padding: 0.75rem 1.5rem;
            background-color: #004225;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }

        .main-header h1 {
            font-size: 1.25rem;
            margin: 0;
            color: #ffffff;
        }

        .header-actions {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .header-icon {
            color: #ffffff;
            font-size: 1.25rem;
            padding: 0.5rem;
            border-radius: 0.375rem;
            transition: all 0.2s ease;
        }

        .header-icon:hover {
            background-color: rgba(255, 255, 255, 0.1);
        }

        /* Responsive Design */
        @media (max-width: 768px) {
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
            }
            
            .mobile-menu-toggle {
                display: block !important;
            }
            
            .header-title {
                font-size: 1.1rem !important;
            }
            
            .form-card {
                padding: 1.5rem;
                margin: 1rem 0;
            }
            
            .container-fluid {
                padding-left: 1rem !important;
                padding-right: 1rem !important;
            }
            
            .breadcrumb {
                font-size: 0.875rem;
            }
            
            .form-label {
                font-size: 0.9rem;
                margin-bottom: 0.4rem;
            }
            
            .form-control, .form-select {
                padding: 0.6rem 0.8rem;
                font-size: 0.9rem;
            }
            
            .btn-submit {
                padding: 0.6rem 1.5rem;
                font-size: 0.9rem;
            }
            
            .col-md-6 {
                margin-bottom: 1rem;
            }
            
            .d-flex.gap-3.justify-content-end {
                flex-direction: column;
                gap: 0.75rem !important;
            }
            
            .d-flex.gap-3.justify-content-end .btn {
                width: 100%;
                justify-content: center;
            }
        }
        
        @media (max-width: 576px) {
            .form-card {
                padding: 1rem;
                margin: 0.5rem 0;
            }
            
            .header-title {
                font-size: 1rem !important;
            }
            
            .breadcrumb {
                font-size: 0.8rem;
            }
            
            .form-label {
                font-size: 0.85rem;
            }
            
            .form-control, .form-select {
                padding: 0.5rem 0.7rem;
                font-size: 0.85rem;
            }
            
            .btn-submit {
                padding: 0.5rem 1.2rem;
                font-size: 0.85rem;
            }
            
            .form-hint {
                font-size: 0.75rem;
            }
            
            .toast {
                font-size: 0.875rem;
            }
        }
        
        @media (max-width: 480px) {
            .form-card {
                padding: 0.75rem;
            }
            
            .header-title {
                font-size: 0.9rem !important;
            }
            
            .breadcrumb {
                font-size: 0.75rem;
            }
            
            .form-label {
                font-size: 0.8rem;
            }
            
            .form-control, .form-select {
                padding: 0.4rem 0.6rem;
                font-size: 0.8rem;
            }
            
            .btn-submit {
                padding: 0.4rem 1rem;
                font-size: 0.8rem;
            }
            
            .form-hint {
                font-size: 0.7rem;
            }
        }
        
        /* Mobile Menu Toggle */
        .mobile-menu-toggle {
            display: none;
            background: none;
            border: none;
            color: white;
            font-size: 1.5rem;
            padding: 0.5rem;
        }
        
        /* Overlay for mobile sidebar */
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

        /* Notification Styles */
        .notification-badge {
            position: absolute;
            top: -5px;
            right: -5px;
            background: #dc3545;
            color: white;
            border-radius: 50%;
            width: 18px;
            height: 18px;
            font-size: 0.7rem;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
        }

        .notifications-toggle {
            position: relative;
        }

        .notification-dropdown {
            border: 1px solid var(--border-color);
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        }

        .notification-dropdown .dropdown-header {
            background: var(--bg-unified);
            border-bottom: 1px solid var(--border-color);
            padding: 0.75rem 1rem;
        }

        .notification-dropdown .dropdown-header h6 {
            color: var(--text-primary);
            font-weight: 600;
            margin: 0;
        }

        .notification-item {
            padding: 0.75rem 1rem;
            border-bottom: 1px solid var(--border-color);
            transition: background-color 0.2s ease;
            cursor: pointer;
        }

        .notification-item:hover {
            background-color: var(--hover-bg);
        }

        .notification-item.unread {
            background-color: rgba(13, 110, 253, 0.05);
        }

        .notification-item.unread:hover {
            background-color: rgba(13, 110, 253, 0.1);
        }

        .notification-text {
            font-size: 0.875rem;
            color: var(--text-primary);
            margin-bottom: 0.25rem;
            line-height: 1.4;
        }

        .notification-time {
            font-size: 0.75rem;
            color: var(--text-secondary);
        }

        .notification-icon {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 0.75rem;
            font-size: 0.875rem;
        }

        .notification-icon.success {
            background: rgba(25, 135, 84, 0.1);
            color: #198754;
        }

        .notification-icon.warning {
            background: rgba(255, 193, 7, 0.1);
            color: #ffc107;
        }

        .notification-icon.danger {
            background: rgba(220, 53, 69, 0.1);
            color: #dc3545;
        }

        .notification-icon.info {
            background: rgba(13, 110, 253, 0.1);
            color: #0d6efd;
        }

        /* Responsive notification styles */
        @media (max-width: 768px) {
            .notification-dropdown {
                width: 300px !important;
                max-height: 350px !important;
            }
            
            .notification-item {
                padding: 0.6rem 0.8rem;
            }
            
            .notification-text {
                font-size: 0.8rem;
            }
            
            .notification-time {
                font-size: 0.7rem;
            }
        }

        @media (max-width: 576px) {
            .notification-dropdown {
                width: 280px !important;
                max-height: 300px !important;
            }
            
            .notification-item {
                padding: 0.5rem 0.7rem;
            }
            
            .notification-text {
                font-size: 0.75rem;
            }
            
            .notification-time {
                font-size: 0.65rem;
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
                    <i class="bi bi-calendar-check"></i> Reservation
                </a>
                <a href="inventory.php" class="list-group-item list-group-item-action active">
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
                    <h1 class="h3 mb-0 d-flex align-items-center header-title">
                    <i class="bi bi-plus-circle me-2"></i>
                    Add New Item
                </h1>
                </div>
                <div class="d-flex align-items-center gap-3">
                    <div class="header-actions">
                        <div class="dropdown">
                            <button class="header-icon notifications-toggle border-0 bg-transparent" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="bi bi-bell"></i>
                                <span class="notification-badge" id="notificationBadge" style="display: none;">0</span>
                        </button>
                            <div class="dropdown-menu dropdown-menu-end notification-dropdown" style="width: 350px; max-height: 400px; overflow-y: auto;">
                                <div class="dropdown-header d-flex justify-content-between align-items-center">
                                    <h6 class="mb-0">Notifications</h6>
                                    <button class="btn btn-sm btn-outline-secondary" onclick="markAllAsRead()">Mark all as read</button>
                                </div>
                                <div class="dropdown-divider"></div>
                                <div id="notificationsList">
                                    <div class="text-center text-muted py-3">
                                        <i class="bi bi-bell-slash"></i>
                                        <div>No notifications</div>
                                    </div>
                                </div>
                                <div class="dropdown-divider"></div>
                                <div class="dropdown-item text-center">
                                    <a href="#" class="text-decoration-none" onclick="viewAllNotifications()">View all notifications</a>
                                </div>
                            </div>
                        </div>
                        <div class="dropdown">
                            <button class="header-icon dropdown-toggle border-0 bg-transparent" type="button" data-bs-toggle="dropdown">
                                <i class="bi bi-person-circle"></i>
                            </button>
                            <ul class="dropdown-menu dropdown-menu-end">
                                <li><a class="dropdown-item" href="profile.php"><i class="bi bi-person me-2"></i>Profile</a></li>
                                <li><a class="dropdown-item" href="../logout.php"><i class="bi bi-box-arrow-right me-2"></i>Logout</a></li>
                            </ul>
                        </div>
                    </div>
                </div>
            </header>

            <!-- Sidebar Overlay for Mobile -->
            <div class="sidebar-overlay" id="sidebarOverlay"></div>

            <!-- Form Content -->
            <div class="container-fluid py-4 px-4">
                <!-- Breadcrumb -->
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                        <li class="breadcrumb-item"><a href="inventory.php">Inventory</a></li>
                        <li class="breadcrumb-item active" aria-current="page">Add New Item</li>
                    </ol>
                </nav>

                <!-- Toast container for notifications -->
                <div class="position-fixed top-0 end-0 p-3" style="z-index: 1100">
                    <div id="successToast" class="toast align-items-center text-white bg-success border-0" role="alert" aria-live="assertive" aria-atomic="true">
                        <div class="d-flex">
                            <div class="toast-body">
                                <i class="bi bi-check-circle me-2"></i>
                                <span id="successMessage"></span>
                            </div>
                            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
                        </div>
                    </div>
                    <div id="errorToast" class="toast align-items-center text-white bg-danger border-0" role="alert" aria-live="assertive" aria-atomic="true">
                        <div class="d-flex">
                            <div class="toast-body">
                                <i class="bi bi-exclamation-circle me-2"></i>
                                <span id="errorMessage"></span>
                            </div>
                            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
                        </div>
                    </div>
                </div>

                <div class="form-card">
                    <form id="addItemForm" method="POST" action="" class="needs-validation" novalidate>
                        <div class="row g-4">
                            <div class="col-12">
                                <label for="name" class="form-label">Item Name</label>
                                <select class="form-select" id="name" name="name" required>
                                    <option value="">Select item name</option>
                                    <option value="Chairs">Chairs</option>
                                    <option value="Tables">Tables</option>
                                    <option value="Industrial Fan">Industrial Fan</option>
                                    <option value="Extension Wires">Extension Wires</option>
                                    <option value="Sound System">Sound System</option>
                                    <option value="Red Carpet">Red Carpet</option>
                                    <option value="Podium">Podium</option>
                                </select>
                                <div class="invalid-feedback">Please select an item name.</div>
                            </div>

                            <div class="col-md-6">
                                <label for="total_quantity" class="form-label">Total Quantity</label>
                                <input type="number" class="form-control" id="total_quantity" name="total_quantity" placeholder="Enter total quantity" min="0" required>
                                <div class="invalid-feedback">Please enter a valid quantity.</div>
                            </div>

                            <div class="col-md-6">
                                <label for="minimum_stock" class="form-label">Minimum Stock Level</label>
                                <input type="number" class="form-control" id="minimum_stock" name="minimum_stock" placeholder="Enter minimum stock level" min="0" required>
                                <div class="invalid-feedback">Please enter a valid minimum stock level.</div>
                                <div class="form-hint">Set the threshold for low stock alerts</div>
                            </div>

                            <div class="col-12">
                                <label for="status" class="form-label">Status</label>
                                <select class="form-select" id="status" name="status" required>
                                    <option value="">Select status</option>
                                    <option value="Available">Available</option>
                                    <option value="In Maintenance">In Maintenance</option>
                                </select>
                                <div class="invalid-feedback">Please select a status.</div>
                            </div>

                            <div class="col-12 d-flex gap-3 justify-content-end mt-4">
                                <a href="inventory.php" class="btn btn-light btn-submit px-4">
                                    <i class="bi bi-x-lg me-2"></i>Cancel
                                </a>
                                <button type="submit" class="btn btn-success btn-submit" id="submitBtn">
                                    <i class="bi bi-plus-lg me-2"></i>Add Item
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/notifications.js"></script>
    <script>
    $(document).ready(function() {
        // Mobile menu functionality
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

        // Initialize toasts
        const successToast = new bootstrap.Toast(document.getElementById('successToast'));
        const errorToast = new bootstrap.Toast(document.getElementById('errorToast'));

        // Custom validation for minimum stock
        $('#minimum_stock, #total_quantity').on('input', function() {
            const minStock = parseInt($('#minimum_stock').val()) || 0;
            const totalQty = parseInt($('#total_quantity').val()) || 0;
            
            if (minStock > totalQty) {
                $('#minimum_stock')[0].setCustomValidity('Minimum stock cannot be greater than total quantity');
            } else {
                $('#minimum_stock')[0].setCustomValidity('');
            }
        });

        // Form submission handling
        $('#addItemForm').on('submit', function(e) {
            e.preventDefault();
            
            $.ajax({
                url: 'add_item.php',
                type: 'POST',
                data: $(this).serialize(),
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        // Store success message in session storage
                        sessionStorage.setItem('inventoryMessage', response.message);
                        // Redirect to inventory page
                        window.location.href = 'inventory.php';
                    } else {
                        showAlert(response.message, false);
                    }
                },
                error: function() {
                    showAlert('An error occurred while processing your request.', false);
                }
            });
        });

        function showAlert(message, isSuccess = true) {
            const alertContainer = document.getElementById('alertContainer');
            const alert = alertContainer.querySelector('.alert');
            const alertMessage = alertContainer.querySelector('.alert-message');
            const alertIcon = alertContainer.querySelector('.bi');
            
            alertMessage.textContent = message;
            
            if (isSuccess) {
                alert.className = 'alert alert-success d-flex align-items-center justify-content-between';
                alertIcon.className = 'bi bi-check-circle-fill me-2';
            } else {
                alert.className = 'alert alert-danger d-flex align-items-center justify-content-between';
                alertIcon.className = 'bi bi-exclamation-circle-fill me-2';
            }
            
            alertContainer.style.display = 'block';
            setTimeout(hideAlert, 5000);
        }

        function hideAlert() {
            const alertContainer = document.getElementById('alertContainer');
            alertContainer.style.display = 'none';
        }

        // Prevent negative numbers in numeric inputs
        $('input[type="number"]').on('input', function() {
            if (this.value < 0) {
                this.value = 0;
            }
        });

        // Notification functionality
        loadNotifications();
        
        // Load notifications every 30 seconds
        setInterval(loadNotifications, 30000);

        function loadNotifications() {
            fetch('get_notifications.php')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        updateNotificationBadge(data.unread_count);
                        updateNotificationsList(data.notifications);
                    }
                })
                .catch(error => console.error('Error loading notifications:', error));
        }

        function updateNotificationBadge(count) {
            const badge = document.getElementById('notificationBadge');
            if (count > 0) {
                badge.textContent = count > 99 ? '99+' : count;
                badge.style.display = 'flex';
            } else {
                badge.style.display = 'none';
            }
        }

        function updateNotificationsList(notifications) {
            const notificationsList = document.getElementById('notificationsList');
            
            if (notifications.length === 0) {
                notificationsList.innerHTML = `
                    <div class="text-center text-muted py-3">
                        <i class="bi bi-bell-slash"></i>
                        <div>No notifications</div>
                    </div>
                `;
                return;
            }

            let html = '';
            notifications.forEach(notification => {
                const iconClass = getNotificationIconClass(notification.type);
                const timeAgo = getTimeAgo(notification.created_at);
                const unreadClass = notification.is_read ? '' : 'unread';
                
                html += `
                    <div class="notification-item ${unreadClass}" onclick="markNotificationAsRead(${notification.id})">
                        <div class="d-flex align-items-start">
                            <div class="notification-icon ${iconClass}">
                                <i class="bi ${getNotificationIcon(notification.type)}"></i>
                            </div>
                            <div class="flex-grow-1">
                                <div class="notification-text">${notification.message}</div>
                                <div class="notification-time">${timeAgo}</div>
                            </div>
                        </div>
                    </div>
                `;
            });
            
            notificationsList.innerHTML = html;
        }

        function getNotificationIconClass(type) {
            switch (type) {
                case 'success': return 'success';
                case 'warning': return 'warning';
                case 'danger': return 'danger';
                case 'info': 
                default: return 'info';
            }
        }

        function getNotificationIcon(type) {
            switch (type) {
                case 'success': return 'bi-check-circle';
                case 'warning': return 'bi-exclamation-triangle';
                case 'danger': return 'bi-x-circle';
                case 'info': 
                default: return 'bi-info-circle';
            }
        }

        function getTimeAgo(dateString) {
            const date = new Date(dateString);
            const now = new Date();
            const diffInSeconds = Math.floor((now - date) / 1000);
            
            if (diffInSeconds < 60) return 'Just now';
            if (diffInSeconds < 3600) return `${Math.floor(diffInSeconds / 60)}m ago`;
            if (diffInSeconds < 86400) return `${Math.floor(diffInSeconds / 3600)}h ago`;
            return `${Math.floor(diffInSeconds / 86400)}d ago`;
        }

        function markNotificationAsRead(notificationId) {
            fetch('mark_notification_read.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'notification_id=' + notificationId
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    loadNotifications(); // Reload notifications
                }
            })
            .catch(error => console.error('Error marking notification as read:', error));
        }

        function markAllAsRead() {
            fetch('mark_notification_read.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'mark_all=1'
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    loadNotifications(); // Reload notifications
                }
            })
            .catch(error => console.error('Error marking all notifications as read:', error));
        }

        function viewAllNotifications() {
            // Redirect to a notifications page or show all notifications
            window.location.href = 'dashboard.php#notifications';
        }
    });
    </script>
</body>
</html>