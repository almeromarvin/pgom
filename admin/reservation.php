<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../login.php');
    exit();
}

// Initialize filter variables
$status_filter = isset($_GET['status']) ? $_GET['status'] : 'all';
$date_filter = isset($_GET['date']) ? $_GET['date'] : '';
$search_query = isset($_GET['search']) ? $_GET['search'] : '';

// Build the SQL query with filters
$sql = "SELECT b.*, u.name as user_name, 
        DATE(b.start_time) as event_date,
        TIME(b.start_time) as start_time,
        TIME(b.end_time) as end_time,
        f.name as facility_name,
        LOWER(b.status) as status,
        b.request_letter,
        GROUP_CONCAT(
            CONCAT(i.name, ' (', be.quantity, ')')
            ORDER BY i.name
            SEPARATOR ', '
        ) as equipment_details
        FROM bookings b 
        JOIN users u ON b.user_id = u.id 
        JOIN facilities f ON b.facility_id = f.id 
        LEFT JOIN booking_equipment be ON b.id = be.booking_id
        LEFT JOIN inventory i ON be.equipment_id = i.id
        WHERE 1=1";
$params = [];

if ($status_filter !== 'all') {
    $sql .= " AND LOWER(b.status) = LOWER(?)";
    $params[] = $status_filter;
}

if ($date_filter) {
    $sql .= " AND DATE(b.start_time) = ?";
    $params[] = $date_filter;
}

if ($search_query) {
    $sql .= " AND (u.name LIKE ? OR f.name LIKE ?)";
    $params[] = "%$search_query%";
    $params[] = "%$search_query%";
}

$sql .= " GROUP BY b.id ORDER BY b.start_time DESC";

// Get venue-specific reservation counts
$venue_counts = [];
try {
    $venues = ['Training Center', 'Evacuation Center', 'Grand Plaza', 'Event Center', 'Back Door'];
    foreach ($venues as $venue) {
        $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM bookings b JOIN facilities f ON b.facility_id = f.id WHERE f.name = ?" . ($status_filter !== 'all' ? " AND b.status = ?" : ''));
        $stmt_params = [$venue];
        if ($status_filter !== 'all') {
            $stmt_params[] = $status_filter;
        }
        $stmt->execute($stmt_params);
        $venue_counts[$venue] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    }
} catch (PDOException $e) {
    foreach ($venues as $venue) {
        $venue_counts[$venue] = 0;
    }
}

// Get filtered reservations
try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $reservations = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $reservations = [];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Booking Management - PGOM Facilities</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
    <link href="../assets/css/notifications.css" rel="stylesheet">
    <style>
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
            
            .stat-card {
                margin-bottom: 1rem;
                padding: 1rem;
            }
            
            .stat-value {
                font-size: 1.5rem;
            }
            
            .filters-section {
                padding: 1rem;
                margin-bottom: 1rem;
            }
            
            .filter-group {
                margin-bottom: 1rem;
            }
            
            .table-responsive {
                font-size: 0.875rem;
            }
            
            .table th,
            .table td {
                padding: 0.5rem 0.25rem;
            }
            
            .btn-sm {
                font-size: 0.75rem;
                padding: 0.25rem 0.5rem;
            }
            
            .container-fluid {
                padding-left: 1rem !important;
                padding-right: 1rem !important;
            }
            
            /* Mobile card layout for reservations */
            .mobile-reservation-card {
                display: block;
                background: white;
                border: 1px solid #dee2e6;
                border-radius: 8px;
                padding: 1rem;
                margin-bottom: 1rem;
                box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            }
            
            .mobile-reservation-card .reservation-header {
                display: flex;
                justify-content: space-between;
                align-items: flex-start;
                margin-bottom: 0.75rem;
                padding-bottom: 0.75rem;
                border-bottom: 1px solid #f0f0f0;
            }
            
            .mobile-reservation-card .user-info {
                display: flex;
                align-items: center;
                gap: 0.5rem;
            }
            
            .mobile-reservation-card .user-avatar {
                width: 32px;
                height: 32px;
                background: #43a047;
                color: white;
                border-radius: 50%;
                display: flex;
                align-items: center;
                justify-content: center;
                font-weight: 600;
                font-size: 0.9rem;
            }
            
            .mobile-reservation-card .user-name {
                font-weight: 600;
                color: #2c3e50;
                font-size: 0.9rem;
            }
            
            .mobile-reservation-card .facility-name {
                color: #6c757d;
                font-size: 0.8rem;
            }
            
            .mobile-reservation-card .reservation-details {
                margin-bottom: 0.75rem;
            }
            
            .mobile-reservation-card .detail-item {
                display: flex;
                justify-content: space-between;
                margin-bottom: 0.5rem;
                font-size: 0.8rem;
            }
            
            .mobile-reservation-card .detail-label {
                color: #6c757d;
                font-weight: 500;
            }
            
            .mobile-reservation-card .detail-value {
                color: #2c3e50;
                text-align: right;
            }
            
            .mobile-reservation-card .action-buttons {
                display: flex;
                gap: 0.5rem;
                flex-wrap: wrap;
            }
            
            .mobile-reservation-card .btn {
                flex: 1;
                min-width: 80px;
                font-size: 0.75rem;
                padding: 0.4rem 0.5rem;
            }
        }
        
        @media (max-width: 576px) {
            .stat-card {
                padding: 0.75rem;
            }
            
            .stat-value {
                font-size: 1.25rem;
            }
            
            .filters-section {
                padding: 0.75rem;
            }
            
            .mobile-reservation-card {
                padding: 0.75rem;
            }
            
            .mobile-reservation-card .user-name {
                font-size: 0.85rem;
            }
            
            .mobile-reservation-card .facility-name {
                font-size: 0.75rem;
            }
            
            .mobile-reservation-card .detail-item {
                font-size: 0.75rem;
            }
            
            .mobile-reservation-card .btn {
                font-size: 0.7rem;
                padding: 0.3rem 0.4rem;
            }
        }
        
        @media (max-width: 480px) {
            .header-title {
                font-size: 1rem !important;
            }
            
            .mobile-reservation-card .reservation-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 0.5rem;
            }
            
            .mobile-reservation-card .user-info {
                width: 100%;
            }
            
            .mobile-reservation-card .detail-item {
                flex-direction: column;
                align-items: flex-start;
                gap: 0.25rem;
            }
            
            .mobile-reservation-card .detail-value {
                text-align: left;
            }
            
            .mobile-reservation-card .action-buttons {
                flex-direction: column;
            }
            
            .mobile-reservation-card .btn {
                width: 100%;
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
        
        /* Hide table on mobile, show cards */
        @media (max-width: 768px) {
            .table-container {
                display: none;
            }
            
            .mobile-reservations {
                display: block;
            }
        }
        
        @media (min-width: 769px) {
            .mobile-reservations {
                display: none;
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
        
        /* Card styles */
        .stat-card {
            background: #ffffff;
            border-radius: 8px;
            padding: 1.25rem;
            height: 100%;
            border: 1px solid #e9ecef;
            transition: all 0.2s ease;
        }

        .stat-card:hover {
            border-color: #43a047;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.04);
        }

        .stat-card .card-title {
            color: #6c757d;
            font-size: 0.875rem;
            font-weight: 500;
            margin-bottom: 0;
            text-transform: uppercase;
        }

        .stat-card .stat-icon {
            font-size: 1.5rem;
            opacity: 0.9;
            color: #43a047;
        }

        .stat-card .stat-value {
            font-size: 1.75rem;
            font-weight: 600;
            color: #2c3e50;
            margin: 0.5rem 0;
            line-height: 1;
        }

        .stat-card .text-muted {
            font-size: 0.875rem;
            color: #6c757d !important;
        }

        /* Filter section */
        .filters-section {
            background: #ffffff;
            border: 1px solid #e9ecef;
            border-radius: 8px;
            padding: 1.25rem;
            margin-bottom: 1.5rem;
        }

        .filters-section h6 {
            color: #2c3e50;
            font-size: 0.875rem;
            font-weight: 600;
            text-transform: uppercase;
        }

        .filter-group label {
            font-size: 0.75rem;
            color: #6c757d;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 0.5rem;
        }

        .form-select, .form-control {
            font-size: 0.875rem;
            border: 1px solid #e9ecef;
            border-radius: 6px;
            padding: 0.5rem 0.75rem;
        }

        .form-select:focus, .form-control:focus {
            border-color: #43a047;
            box-shadow: 0 0 0 0.2rem rgba(67, 160, 71, 0.25);
        }

        .btn-export {
            color: #43a047;
            background: transparent;
            border: 1px solid #43a047;
            border-radius: 6px;
            font-size: 0.875rem;
            padding: 0.5rem 1rem;
            transition: all 0.2s ease;
        }

        .btn-export:hover {
            color: #ffffff;
            background: #43a047;
        }

        /* Table styles */
        .table-container {
            background: #ffffff;
            border: 1px solid #e9ecef;
            border-radius: 8px;
            overflow: hidden;
        }

        .table {
            margin-bottom: 0;
        }

        .table th {
            background: #f8f9fa;
            font-weight: 600;
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #495057;
            padding: 0.75rem 1rem;
            border-bottom: 1px solid #e9ecef;
        }

        .table td {
            padding: 0.75rem 1rem;
            vertical-align: middle;
            color: #495057;
            font-size: 0.875rem;
            border-bottom: 1px solid #e9ecef;
        }

        .table tbody tr:hover {
            background-color: #f8f9fa;
        }

        /* Status badges */
        .status-badge {
            display: inline-block;
            padding: 0.25rem 0.75rem;
            font-size: 0.75rem;
            font-weight: 500;
            border-radius: 4px;
            text-transform: capitalize;
        }

        .status-badge.pending {
            background: #fff3e0;
            color: #ef6c00;
        }

        .status-badge.approved {
            background: #e8f5e9;
            color: #2e7d32;
        }

        .status-badge.completed {
            background: #e3f2fd;
            color: #0d6efd;
        }

        .status-badge.rejected {
            background: #ffebee;
            color: #c62828;
        }

        /* Action buttons */
        .action-btn {
            padding: 0.5rem;
            font-size: 1rem;
            color: #6c757d;
            background: #f8f9fa;
            border: 1px solid #e9ecef;
            border-radius: 8px;
            transition: all 0.3s ease;
            margin: 0 0.2rem;
            position: relative;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 36px;
            height: 36px;
        }

        .action-btn:hover {
            color: #ffffff;
            background: #43a047;
            border-color: #43a047;
            transform: translateY(-2px);
            box-shadow: 0 3px 5px rgba(0,0,0,0.1);
        }

        .action-btn.text-danger {
            color: #dc3545;
            background: rgba(220, 53, 69, 0.1);
            border: 1px solid transparent;
        }

        .action-btn.text-danger:hover {
            background: rgba(220, 53, 69, 0.15);
            color: rgb(163, 26, 39);
            border-color: rgba(220, 53, 69, 0.2);
            box-shadow: 0 3px 5px rgba(220, 53, 69, 0.1);
        }

        .action-btn.text-primary {
            color: #0d6efd;
            background: rgba(13, 110, 253, 0.1);
            border: 1px solid transparent;
        }

        .action-btn.text-primary:hover {
            background: rgba(13, 110, 253, 0.15);
            color: #0d6efd;
            border-color: rgba(13, 110, 253, 0.2);
            box-shadow: 0 3px 5px rgba(13, 110, 253, 0.1);
        }

        .action-btn.text-success {
            color: #198754;
            background: rgba(25, 135, 84, 0.1);
            border: 1px solid transparent;
        }

        .action-btn.text-success:hover {
            background: rgba(25, 135, 84, 0.15);
            color: #198754;
            border-color: rgba(25, 135, 84, 0.2);
            box-shadow: 0 3px 5px rgba(25, 135, 84, 0.1);
        }

        .action-btn i {
            font-size: 1.1rem;
            transition: transform 0.3s ease;
        }

        .action-btn:hover i {
            transform: scale(1.1);
        }

        /* Tooltip styles */
        .action-btn::after {
            content: attr(title);
            position: absolute;
            bottom: -30px;
            left: 50%;
            transform: translateX(-50%);
            background: rgba(0, 0, 0, 0.8);
            color: white;
            padding: 0.25rem 0.5rem;
            border-radius: 4px;
            font-size: 0.75rem;
            white-space: nowrap;
            opacity: 0;
            visibility: hidden;
            transition: all 0.2s ease;
            z-index: 1000;
        }

        .action-btn:hover::after {
            opacity: 1;
            visibility: visible;
            bottom: -35px;
        }

        /* View Reservation Modal Styles */
        .view-item-modal .modal-content {
            border: none;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        .view-item-modal .modal-header {
            background: white;
            border-bottom: 2px solid #43a047;
            border-radius: 8px 8px 0 0;
            padding: 1rem 1.5rem;
        }

        .view-item-modal .modal-title {
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 10px;
            color: #43a047;
            font-size: 1.1rem;
        }

        .view-item-modal .modal-body {
            padding: 1.5rem;
        }

        .item-info-section {
            margin-bottom: 1.5rem;
            padding-bottom: 0.5rem;
            border-bottom: 1px solid #e0e0e0;
        }

        .item-info-section:last-child {
            margin-bottom: 0;
            border-bottom: none;
            padding-bottom: 0;
        }

        .section-title {
            font-size: 0.9rem;
            font-weight: 600;
            color: #43a047;
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .info-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 1.5rem;
        }

        .info-item {
            background: transparent;
            padding: 0;
        }

        .info-label {
            font-size: 0.8rem;
            color: #666;
            margin-bottom: 0.25rem;
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }

        .info-value {
            font-size: 0.95rem;
            color: #333;
            font-weight: normal;
            line-height: 1.4;
        }

        .info-value:empty::before {
            content: "-";
            color: #999;
        }

        .loading-state {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 1rem;
            padding: 2rem;
            color: #666;
        }

        .loading-state .spinner-border {
            width: 3rem;
            height: 3rem;
        }

        .equipment-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 1rem;
            padding: 1rem;
        }

        .equipment-item {
            display: flex;
            align-items: center;
            gap: 1rem;
            padding: 1rem;
            background: #f8f9fa;
            border-radius: 8px;
            transition: all 0.2s ease;
        }

        .equipment-item:hover {
            background: #e9ecef;
            transform: translateY(-2px);
        }

        .equipment-icon {
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #e8f5e9;
            border-radius: 8px;
            color: #43a047;
        }

        .equipment-icon i {
            font-size: 1.2rem;
        }

        .equipment-info {
            flex: 1;
        }

        .equipment-name {
            font-weight: 500;
            color: #2c3e50;
            margin-bottom: 0.25rem;
        }

        .equipment-quantity .badge {
            font-size: 0.85rem;
            padding: 0.35rem 0.65rem;
            border-radius: 6px;
            background: #43a047;
        }

        .equipment-list {
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
            padding: 0.5rem;
        }

        .equipment-item {
            display: inline-flex;
            align-items: center;
            padding: 0.25rem 0.75rem;
            background: #f8f9fa;
            border-radius: 4px;
            font-size: 0.9rem;
            white-space: nowrap;
        }

        .equipment-name {
            color: #2c3e50;
            font-weight: 500;
        }

        .equipment-quantity {
            color: #43a047;
            margin-left: 0.25rem;
        }
    </style>
</head>
<body>
    <!-- Mobile Sidebar Overlay -->
    <div class="sidebar-overlay" id="sidebarOverlay"></div>
    
    <div class="d-flex">
        <!-- Sidebar -->
        <div class="sidebar bg-light border-end" id="sidebar">
            <div class="sidebar-heading">
                <img src="../images/logo.png" alt="PGOM Logo">
                <span>PGOM FACILITIES</span>
            </div>
            <div class="list-group list-group-flush">
                <a href="dashboard.php" class="list-group-item list-group-item-action">
                    <i class="bi bi-speedometer2"></i> Dashboard
                </a>
                <a href="reservation.php" class="list-group-item list-group-item-action active">
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
                    <h1 class="h3 mb-0 d-flex align-items-center header-title">
                        <i class="bi bi-calendar-check me-2"></i>
                        Booking
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
                        <button class="header-icon dropdown-toggle border-0 bg-transparent" type="button" data-bs-toggle="dropdown">
                            <i class="bi bi-person-circle"></i>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><a class="dropdown-item" href="profile.php"><i class="bi bi-person me-2"></i>Profile</a></li>
                            <li><a class="dropdown-item" href="../logout.php"><i class="bi bi-box-arrow-right me-2"></i>Logout</a></li>
                        </ul>
                    </div>
                </div>
            </header>

            <!-- Reservation Content -->
            <div class="container-fluid py-4">
                <!-- Venue Cards -->
                <div class="row g-3 mb-4">
                    <?php
                    $venues = [
                        ['name' => 'Training Center', 'icon' => 'bi-building', 'count' => $venue_counts['Training Center']],
                        ['name' => 'Evacuation Center', 'icon' => 'bi-house-door', 'count' => $venue_counts['Evacuation Center']],
                        ['name' => 'Grand Plaza', 'icon' => 'bi-shop', 'count' => $venue_counts['Grand Plaza']],
                        ['name' => 'Event Center', 'icon' => 'bi-calendar-event', 'count' => $venue_counts['Event Center']],
                        ['name' => 'Back Door', 'icon' => 'bi-door-closed', 'count' => $venue_counts['Back Door']]
                    ];

                    foreach ($venues as $venue) :
                    ?>
                    <div class="col">
                        <div class="stat-card">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="card-title"><?php echo $venue['name']; ?></h6>
                                    <h3 class="stat-value"><?php echo $venue['count']; ?></h3>
                                    <p class="text-muted mb-0">Reservations</p>
                                </div>
                                <div class="stat-icon">
                                    <i class="bi <?php echo $venue['icon']; ?>"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>

                <!-- Filters and Table -->
                <div class="filters-section">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h6 class="mb-0">Filters</h6>
                        <button class="btn btn-export" id="exportBtn">
                            <i class="bi bi-file-earmark-excel me-2"></i>Export to Excel
                        </button>
                    </div>
                    <form id="filterForm" class="row g-3">
                        <div class="col-md-4">
                            <div class="filter-group">
                                <label>Status</label>
                                <select class="form-select" name="status" onchange="this.form.submit()">
                                    <option value="all" <?php echo $status_filter === 'all' ? 'selected' : ''; ?>>All Status</option>
                                    <option value="pending" <?php echo $status_filter === 'pending' ? 'selected' : ''; ?>>Pending</option>
                                    <option value="approved" <?php echo $status_filter === 'approved' ? 'selected' : ''; ?>>Approved</option>
                                    <option value="completed" <?php echo $status_filter === 'completed' ? 'selected' : ''; ?>>Completed</option>
                                    <option value="rejected" <?php echo $status_filter === 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="filter-group">
                                <label>Date Range</label>
                                <input type="date" class="form-control" name="date" value="<?php echo $date_filter; ?>" onchange="this.form.submit()">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="filter-group">
                                <label>Search</label>
                                <div class="input-group">
                                    <input type="text" class="form-control" name="search" value="<?php echo htmlspecialchars($search_query); ?>" placeholder="Search...">
                                    <button class="btn btn-success" type="submit">
                                        <i class="bi bi-search"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>

                <div class="table-container">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Venue</th>
                                <th>Equipment</th>
                                <th>Time</th>
                                <th>Event Date</th>
                                <th>Status</th>
                                <th>Request Letter</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($reservations as $reservation): 
                            ?>
                            <tr>
                                <td><?php echo htmlspecialchars($reservation['user_name']); ?></td>
                                <td><?php echo htmlspecialchars($reservation['facility_name']); ?></td>
                                <td><?php 
                                    if (!empty($reservation['equipment_details'])) {
                                        $equipment_items = explode(', ', $reservation['equipment_details']);
                                        $sound_system_components = ['Speaker', 'Mixer', 'Amplifier', 'Cables', 'Microphone'];
                                        $has_sound_system = false;
                                        $other_equipment = [];
                                        
                                        foreach ($equipment_items as $item) {
                                            if (preg_match('/(.+) \((\d+)\)/', $item, $matches)) {
                                                $name = trim($matches[1]);
                                                if (in_array($name, $sound_system_components)) {
                                                    $has_sound_system = true;
                                                } else {
                                                    $other_equipment[] = $item;
                                                }
                                            }
                                        }
                                        
                                        $display_items = [];
                                        if ($has_sound_system) {
                                            $display_items[] = 'Sound System (1)';
                                        }
                                        $display_items = array_merge($display_items, $other_equipment);
                                        
                                        echo htmlspecialchars(implode(', ', $display_items));
                                    } else {
                                        echo '<span class="text-muted">No equipment selected</span>';
                                    }
                                ?></td>
                                <td><?php echo date('g:i a', strtotime($reservation['start_time'])) . ' - ' . 
                                         date('g:i a', strtotime($reservation['end_time'])); ?></td>
                                <td><?php echo date('M d, Y', strtotime($reservation['event_date'])); ?></td>
                                <td>
                                    <span class="status-badge <?php echo strtolower($reservation['status']); ?>">
                                        <?php echo ucfirst($reservation['status']); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if (!empty($reservation['request_letter'])): ?>
                                        <a href="../uploads/<?php echo htmlspecialchars($reservation['request_letter']); ?>" target="_blank" class="btn btn-sm btn-outline-primary">View PDF</a>
                                    <?php else: ?>
                                        <span class="text-muted">No file</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <button class="action-btn" onclick="viewReservation(<?php echo $reservation['id']; ?>)" title="View Details">
                                            <i class="bi bi-eye-fill"></i>
                                        </button>
                                        <?php if (strtolower($reservation['status']) == 'pending'): ?>
                                            <button class="action-btn text-success" onclick="updateStatus(<?php echo $reservation['id']; ?>, 'approved')" title="Approve">
                                                <i class="bi bi-check-circle-fill"></i>
                                            </button>
                                            <button class="action-btn text-danger" onclick="updateStatus(<?php echo $reservation['id']; ?>, 'rejected')" title="Reject">
                                                <i class="bi bi-x-circle-fill"></i>
                                            </button>
                                        <?php endif; ?>
                                        <?php if (strtolower($reservation['status']) == 'approved'): ?>
                                            <button class="action-btn text-primary" onclick="updateStatus(<?php echo $reservation['id']; ?>, 'completed')" title="Mark as Completed">
                                                <i class="bi bi-check-square-fill"></i>
                                            </button>
                                        <?php endif; ?>
                                        <button class="action-btn text-danger" onclick="deleteReservation(<?php echo $reservation['id']; ?>)" title="Delete">
                                            <i class="bi bi-trash-fill"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Mobile Reservations -->
                <div class="mobile-reservations">
                    <?php foreach ($reservations as $reservation): ?>
                        <div class="mobile-reservation-card">
                            <div class="reservation-header">
                                <div class="user-info">
                                    <div class="user-avatar">
                                        <?php echo strtoupper(substr($reservation['user_name'], 0, 1)); ?>
                                    </div>
                                    <div>
                                        <div class="user-name"><?php echo htmlspecialchars($reservation['user_name']); ?></div>
                                        <div class="facility-name"><?php echo htmlspecialchars($reservation['facility_name']); ?></div>
                                    </div>
                                </div>
                                <div>
                                    <?php
                                    $status_class = '';
                                    switch ($reservation['status']) {
                                        case 'pending':
                                            $status_class = 'warning';
                                            break;
                                        case 'approved':
                                            $status_class = 'success';
                                            break;
                                        case 'rejected':
                                            $status_class = 'danger';
                                            break;
                                        case 'completed':
                                            $status_class = 'info';
                                            break;
                                        default:
                                            $status_class = 'secondary';
                                    }
                                    ?>
                                    <span class="badge bg-<?php echo $status_class; ?>">
                                        <?php echo ucfirst($reservation['status']); ?>
                                    </span>
                                </div>
                            </div>
                            
                            <div class="reservation-details">
                                <div class="detail-item">
                                    <span class="detail-label">Event Date:</span>
                                    <span class="detail-value">
                                        <?php echo date('M d, Y', strtotime($reservation['start_time'])); ?>
                                    </span>
                                </div>
                                <div class="detail-item">
                                    <span class="detail-label">Time:</span>
                                    <span class="detail-value">
                                        <?php echo date('h:i A', strtotime($reservation['start_time'])); ?> - 
                                        <?php echo date('h:i A', strtotime($reservation['end_time'])); ?>
                                    </span>
                                </div>
                                <div class="detail-item">
                                    <span class="detail-label">Equipment:</span>
                                    <span class="detail-value">
                                        <?php 
                                        if (!empty($reservation['equipment_details'])) {
                                            $equipment_items = explode(', ', $reservation['equipment_details']);
                                            $sound_system_components = ['Speaker', 'Mixer', 'Amplifier', 'Cables', 'Microphone'];
                                            $has_sound_system = false;
                                            $other_equipment = [];
                                            
                                            foreach ($equipment_items as $item) {
                                                if (preg_match('/(.+) \((\d+)\)/', $item, $matches)) {
                                                    $name = trim($matches[1]);
                                                    if (in_array($name, $sound_system_components)) {
                                                        $has_sound_system = true;
                                                    } else {
                                                        $other_equipment[] = $item;
                                                    }
                                                }
                                            }
                                            
                                            $display_items = [];
                                            if ($has_sound_system) {
                                                $display_items[] = 'Sound System (1)';
                                            }
                                            $display_items = array_merge($display_items, $other_equipment);
                                            
                                            echo htmlspecialchars(implode(', ', $display_items));
                                        } else {
                                            echo '<span class="text-muted">No equipment</span>';
                                        }
                                        ?>
                                    </span>
                                </div>
                            </div>
                            
                            <div class="action-buttons">
                                <button class="btn btn-outline-primary btn-sm" onclick="viewReservation(<?php echo $reservation['id']; ?>)" title="View">
                                    <i class="bi bi-eye"></i> View
                                </button>
                                <?php if ($reservation['status'] === 'pending'): ?>
                                    <button class="btn btn-success btn-sm" onclick="updateStatus(<?php echo $reservation['id']; ?>, 'approved')" title="Approve">
                                        <i class="bi bi-check"></i> Approve
                                    </button>
                                    <button class="btn btn-danger btn-sm" onclick="updateStatus(<?php echo $reservation['id']; ?>, 'rejected')" title="Reject">
                                        <i class="bi bi-x"></i> Reject
                                    </button>
                                <?php elseif ($reservation['status'] === 'approved'): ?>
                                    <button class="btn btn-info btn-sm" onclick="updateStatus(<?php echo $reservation['id']; ?>, 'completed')" title="Mark Complete">
                                        <i class="bi bi-check-square-fill"></i> Complete
                                    </button>
                                <?php endif; ?>
                                <button class="btn btn-outline-danger btn-sm" onclick="deleteReservation(<?php echo $reservation['id']; ?>)" title="Delete">
                                    <i class="bi bi-trash"></i> Delete
                                </button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <!-- Debug Info -->
                <?php if (isset($_GET['debug'])): ?>
                <div class="mt-4 p-3 bg-light">
                    <h6>Debug Information:</h6>
                    <?php foreach ($reservations as $reservation): ?>
                        <div>ID: <?php echo $reservation['id']; ?>, Status: "<?php echo $reservation['status']; ?>"</div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>

            </div>
        </div>
    </div>

    <!-- View Reservation Modal -->
    <div class="modal fade view-item-modal" id="viewReservationModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="bi bi-calendar-check"></i>
                        Reservation Details
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div id="loadingState" class="loading-state">
                        <div class="spinner-border text-success"></div>
                        <div>Loading reservation information...</div>
                    </div>
                    
                    <div id="reservationInfo" style="display: none;">
                        <div class="item-info-section">
                            <div class="section-title">
                                <i class="bi bi-info-circle"></i>
                                Reservation Information
                            </div>
                            <div class="info-grid">
                                <div class="info-item">
                                    <div class="info-label">Name</div>
                                    <div class="info-value" id="viewName"></div>
                                </div>
                                <div class="info-item">
                                    <div class="info-label">Venue</div>
                                    <div class="info-value" id="viewFacility"></div>
                                </div>
                                <div class="info-item">
                                    <div class="info-label">Status</div>
                                    <div class="info-value" id="viewStatus"></div>
                                </div>
                            </div>
                        </div>

                        <div class="item-info-section">
                            <div class="section-title">
                                <i class="bi bi-clock"></i>
                                Event Details
                            </div>
                            <div class="info-grid">
                                <div class="info-item">
                                    <div class="info-label">Event Date</div>
                                    <div class="info-value" id="viewDate"></div>
                                </div>
                                <div class="info-item">
                                    <div class="info-label">Time</div>
                                    <div class="info-value" id="viewTime"></div>
                                </div>
                                <div class="info-item">
                                    <div class="info-label">Purpose</div>
                                    <div class="info-value" id="viewPurpose"></div>
                                </div>
                            </div>
                        </div>

                        <div class="item-info-section">
                            <div class="section-title">
                                <i class="bi bi-box-seam"></i>
                                Equipment Details
                            </div>
                            <div class="info-grid">
                                <div class="info-item">
                                    <div class="info-label">Equipment</div>
                                    <div class="info-value" id="viewEquipment"></div>
                                </div>
                            </div>
                        </div>

                        <div id="pendingActions" class="mt-4" style="display: none;">
                            <!-- Approve and Reject buttons removed -->
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-success" data-bs-dismiss="modal">Close</button>
                </div>
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
            const sidebar = document.getElementById('sidebar');
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
        
        document.getElementById('exportBtn').addEventListener('click', function() {
            window.location.href = 'export_reservations.php' + window.location.search;
        });

        let currentReservationId = null;

        function viewReservation(id) {
            currentReservationId = id;
            const modal = new bootstrap.Modal(document.getElementById('viewReservationModal'));
            const loadingState = document.getElementById('loadingState');
            const reservationInfo = document.getElementById('reservationInfo');
            const pendingActions = document.getElementById('pendingActions');
            
            modal.show();
            loadingState.style.display = 'flex';
            reservationInfo.style.display = 'none';
            
            fetch(`get_reservation.php?id=${id}`)
                .then(response => response.json())
                .then(data => {
                    // Update modal content
                    document.getElementById('viewName').textContent = data.user_name;
                    document.getElementById('viewFacility').textContent = data.facility_name;
                    document.getElementById('viewDate').textContent = data.date;
                    document.getElementById('viewTime').textContent = data.start_time + ' - ' + data.end_time;
                    document.getElementById('viewPurpose').textContent = data.purpose;
                    
                    // Format and display equipment details
                    const equipmentContainer = document.getElementById('viewEquipment');
                    if (data.equipment_details) {
                        const equipmentItems = data.equipment_details.split(', ');
                        const soundSystemComponents = ['Speaker', 'Mixer', 'Amplifier', 'Cables', 'Microphone'];
                        const hasSoundSystem = equipmentItems.some(item => {
                            const name = item.split(' (')[0].trim();
                            return soundSystemComponents.includes(name);
                        });
                        
                        const otherEquipment = equipmentItems.filter(item => {
                            const name = item.split(' (')[0].trim();
                            return !soundSystemComponents.includes(name);
                        });
                        
                        let equipmentHTML = '<div class="equipment-list">';
                        
                        // Add Sound System if present
                        if (hasSoundSystem) {
                            equipmentHTML += `
                                <div class="equipment-item">
                                    <span class="equipment-name">Sound System</span>
                                    <span class="equipment-quantity">(1)</span>
                                    <div class="sound-system-components mt-2">
                                        <small class="text-muted">Includes:</small>
                                        <ul class="list-unstyled ms-3 mb-0">
                                            <li><i class="bi bi-check-circle-fill text-success me-1"></i>Speaker</li>
                                            <li><i class="bi bi-check-circle-fill text-success me-1"></i>Mixer</li>
                                            <li><i class="bi bi-check-circle-fill text-success me-1"></i>Amplifier</li>
                                            <li><i class="bi bi-check-circle-fill text-success me-1"></i>Cables</li>
                                            <li><i class="bi bi-check-circle-fill text-success me-1"></i>Microphone</li>
                                        </ul>
                                    </div>
                                </div>
                            `;
                        }
                        
                        // Add other equipment
                        otherEquipment.forEach(item => {
                            const [name, quantity] = item.split(' (');
                            const qty = quantity.replace(')', '');
                            equipmentHTML += `
                                <div class="equipment-item">
                                    <span class="equipment-name">${name}</span>
                                    <span class="equipment-quantity">(${qty})</span>
                                </div>
                            `;
                        });
                        
                        equipmentHTML += '</div>';
                        equipmentContainer.innerHTML = equipmentHTML;
                    } else {
                        equipmentContainer.innerHTML = `
                            <div class="text-muted text-center py-2">
                                No equipment selected
                            </div>
                        `;
                    }

                    // Show/hide pending actions
                    pendingActions.style.display = data.status.toLowerCase() === 'pending' ? 'block' : 'none';
                    
                    loadingState.style.display = 'none';
                    reservationInfo.style.display = 'block';
                })
                .catch(error => {
                    console.error('Error:', error);
                    loadingState.innerHTML = `
                        <div class="text-danger">
                            <i class="bi bi-exclamation-circle"></i>
                            <div>Error loading reservation data</div>
                        </div>
                    `;
                });
        }

        function updateStatus(bookingId, status) {
            if (!confirm(`Are you sure you want to ${status} this booking?`)) {
                return;
            }

            const formData = new FormData();
            formData.append('booking_id', bookingId);
            formData.append('status', status);

            fetch('update_booking_status.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert(`Booking has been ${status} successfully`);
                    // Reload the page to show updated status
                    window.location.reload();
                } else {
                    alert('Error updating booking status: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error updating booking status');
            });
        }

        function deleteReservation(id) {
            if (confirm('Are you sure you want to delete this reservation?')) {
                console.log('Attempting to delete reservation with ID:', id);
                
                fetch('delete_reservation.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        booking_id: id
                    })
                })
                .then(response => {
                    console.log('Response status:', response.status);
                    console.log('Response headers:', response.headers);
                    
                    if (!response.ok) {
                        throw new Error(`HTTP error! status: ${response.status}`);
                    }
                    return response.json();
                })
                .then(data => {
                    console.log('Response data:', data);
                    
                    if (data.success) {
                        alert('Reservation deleted successfully');
                        // Reload the page to show updated list
                        window.location.reload();
                    } else {
                        const errorMessage = data.error || data.message || 'Unknown error occurred';
                        console.log('Error message extracted:', errorMessage);
                        alert('Error deleting reservation: ' + errorMessage);
                    }
                })
                .catch(error => {
                    console.error('Error in deleteReservation:', error);
                    console.error('Error message:', error.message);
                    console.error('Error stack:', error.stack);
                    
                    let errorMessage = 'Error deleting reservation. Please try again.';
                    
                    // Try to parse error response if it's JSON
                    if (error.message && error.message.includes('HTTP error')) {
                        errorMessage = 'Server error occurred. Please try again.';
                    }
                    
                    alert(errorMessage);
                });
            }
        }
    </script>
</body>
</html>