<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../login.php');
    exit();
}

// Get statistics for each item type
try {
    $stats = [
        'Booking' => 0,
        'Chairs' => 0,
        'Tables' => 0,
        'Users' => 0,
        'Industrial Fan' => 0,
        'Extension Wires' => 0,
        'Sound System' => 0,
        'Red Carpets' => 0,
        'Podium' => 0,
        'Microphone' => 0,
        'Speaker' => 0,
        'Mixer' => 0,
        'Amplifier' => 0,
        'Fan' => 0
    ];
    
    $items = ['Booking', 'Chairs', 'Tables', 'Industrial Fan', 'Extension Wires', 'Sound System', 'Red Carpets', 'Podium', 'Microphone', 'Speaker', 'Mixer', 'Amplifier', 'Fan'];
    
    foreach ($items as $item) {
        $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM inventory WHERE item_name = ?");
        $stmt->execute([$item]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($result) {
            $stats[$item] = $result['count'];
        }
    }

    // Get user count
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM users");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($result) {
        $stats['Users'] = $result['count'];
    }

} catch (PDOException $e) {
    // Handle error
    error_log($e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports - PGOM Facilities</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
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
            
            .period-selector {
                flex-wrap: wrap;
                gap: 0.25rem;
            }
            
            .period-btn {
                flex: 1;
                min-width: 80px;
                text-align: center;
                font-size: 0.8rem;
                padding: 0.4rem 0.5rem;
            }
            
            .table-responsive {
                font-size: 0.875rem;
            }
            
            .table th,
            .table td {
                padding: 0.5rem 0.25rem;
            }
            
            .container-fluid {
                padding-left: 1rem !important;
                padding-right: 1rem !important;
            }
            
            /* Mobile card layout for reports */
            .mobile-report-card {
                display: block;
                background: var(--card-bg);
                border: 1px solid var(--card-border);
                border-radius: 8px;
                padding: 1rem;
                margin-bottom: 1rem;
                box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            }
            
            .mobile-report-card .report-header {
                display: flex;
                justify-content: space-between;
                align-items: flex-start;
                margin-bottom: 0.75rem;
                padding-bottom: 0.75rem;
                border-bottom: 1px solid var(--card-border);
            }
            
            .mobile-report-card .item-info {
                display: flex;
                align-items: center;
                gap: 0.5rem;
            }
            
            .mobile-report-card .item-icon {
                width: 40px;
                height: 40px;
                background: #004225;
                color: white;
                border-radius: 8px;
                display: flex;
                align-items: center;
                justify-content: center;
                font-weight: 600;
                font-size: 1rem;
            }
            
            .mobile-report-card .item-name {
                font-weight: 600;
                color: var(--text-primary);
                font-size: 0.9rem;
            }
            
            .mobile-report-card .item-category {
                color: var(--text-secondary);
                font-size: 0.8rem;
            }
            
            .mobile-report-card .report-details {
                margin-bottom: 0.75rem;
            }
            
            .mobile-report-card .detail-item {
                display: flex;
                justify-content: space-between;
                margin-bottom: 0.5rem;
                font-size: 0.8rem;
            }
            
            .mobile-report-card .detail-label {
                color: var(--text-secondary);
                font-weight: 500;
            }
            
            .mobile-report-card .detail-value {
                color: var(--text-primary);
                text-align: right;
            }
        }
        
        @media (max-width: 576px) {
            .stat-card {
                padding: 0.75rem;
            }
            
            .stat-value {
                font-size: 1.25rem;
            }
            
            .period-selector {
                flex-direction: column;
            }
            
            .period-btn {
                width: 100%;
                min-width: auto;
            }
            
            .mobile-report-card {
                padding: 0.75rem;
            }
            
            .mobile-report-card .item-name {
                font-size: 0.85rem;
            }
            
            .mobile-report-card .item-category {
                font-size: 0.75rem;
            }
            
            .mobile-report-card .detail-item {
                font-size: 0.75rem;
            }
        }
        
        @media (max-width: 480px) {
            .header-title {
                font-size: 1rem !important;
            }
            
            .mobile-report-card .report-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 0.5rem;
            }
            
            .mobile-report-card .item-info {
                width: 100%;
            }
            
            .mobile-report-card .detail-item {
                flex-direction: column;
                align-items: flex-start;
                gap: 0.25rem;
            }
            
            .mobile-report-card .detail-value {
                text-align: left;
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
            .table-responsive {
                display: none;
            }
            
            .mobile-reports {
                display: block;
            }
        }
        
        @media (min-width: 769px) {
            .mobile-reports {
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
        
        /* Base styles */
        :root {
            --card-bg: #ffffff;
            --card-border: #e9ecef;
            --text-primary: #2c3e50;
            --text-secondary: #6c757d;
            --hover-bg: #f8f9fa;
            --table-header-bg: #f8f9fa;
            --table-border: #e9ecef;
            --stat-value-color: #2c3e50;
            --stat-label-color: #6c757d;
            --text-muted: #6c757d;
        }

        /* Period Selector */
        .period-selector {
            display: flex;
            gap: 0.5rem;
            background: #f8f9fa;
            padding: 0.25rem;
            border-radius: 0.5rem;
        }

        .period-btn {
            border: none;
            padding: 0.5rem 1rem;
            font-size: 0.875rem;
            font-weight: 500;
            color: var(--text-secondary);
            background: transparent;
            border-radius: 0.375rem;
            transition: all 0.2s ease;
        }

        .period-btn:hover {
            color: #004225;
            background: var(--hover-bg);
        }

        .period-btn.active {
            background: #004225;
            color: #ffffff;
        }

        /* Export Button */
        .export-btn {
            display: flex;
            align-items: center;
            padding: 0.5rem 1rem;
            font-size: 0.875rem;
            font-weight: 500;
            color: #ffffff;
            background: #004225;
            border: none;
            border-radius: 0.375rem;
            transition: all 0.2s ease;
        }

        .export-btn:hover {
            background: #003319;
            color: #ffffff;
        }

        /* Stat Cards */
        .stat-card {
            background: var(--card-bg);
            border: 1px solid var(--card-border);
            border-radius: 0.5rem;
            padding: 1.25rem;
            height: 100%;
            transition: all 0.2s ease;
        }

        .stat-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }

        .stat-icon {
            font-size: 1.5rem;
            margin-bottom: 0.75rem;
            color: #004225;
        }

        .stat-value {
            font-size: 1.75rem;
            font-weight: 600;
            color: var(--stat-value-color);
            margin-bottom: 0.25rem;
        }

        .stat-label {
            font-size: 0.875rem;
            color: var(--stat-label-color);
        }

        /* Report Table */
        .report-table {
            background: var(--card-bg);
            border: 1px solid var(--card-border);
            border-radius: 0.5rem;
            overflow: hidden;
        }

        .table {
            margin-bottom: 0;
        }

        .table th {
            background: var(--table-header-bg);
            font-weight: 600;
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: var(--text-primary);
            padding: 0.75rem 1rem;
            border-bottom: 1px solid var(--table-border);
        }

        .table td {
            padding: 0.75rem 1rem;
            vertical-align: middle;
            color: var(--text-primary);
            font-size: 0.875rem;
            border-bottom: 1px solid var(--table-border);
        }

        .table tbody tr:hover {
            background-color: var(--hover-bg);
        }

        /* Status Badge */
        .status-badge {
            display: inline-block;
            padding: 0.25rem 0.75rem;
            font-size: 0.75rem;
            font-weight: 500;
            border-radius: 0.25rem;
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

        /* Loading State */
        .loading-state {
            text-align: center;
            padding: 2rem;
            color: var(--text-secondary);
        }

        .loading-spinner {
            width: 2rem;
            height: 2rem;
            border: 3px solid var(--text-secondary);
            border-top-color: transparent;
            border-radius: 50%;
            animation: spin 1s linear infinite;
            margin: 0 auto 1rem;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 2rem;
            color: var(--text-secondary);
        }

        .empty-state i {
            font-size: 2rem;
            margin-bottom: 1rem;
            color: var(--text-secondary);
        }

        .stat-card.podium .stat-icon {
            color: #6f42c1;
        }

        .stat-card.sound-system .stat-icon {
            color: #fd7e14;
        }

        .stat-card.fan .stat-icon {
            color: #20c997;
        }

        .stat-card.wires .stat-icon {
            color: #ffc107;
        }

        /* Button group styling */
        .btn-group .btn {
            border-radius: 0;
        }

        .btn-group .btn:first-child {
            border-top-left-radius: 0.375rem;
            border-bottom-left-radius: 0.375rem;
        }

        .btn-group .btn:last-child {
            border-top-right-radius: 0.375rem;
            border-bottom-right-radius: 0.375rem;
        }

        .btn-group .btn:not(:last-child) {
            border-right: 1px solid rgba(0,0,0,0.1);
        }

        /* Mobile responsive button group */
        @media (max-width: 768px) {
            .btn-group {
                display: flex;
                flex-direction: column;
                gap: 0.25rem;
            }
            
            .btn-group .btn {
                border-radius: 0.375rem !important;
                border-right: 1px solid rgba(0,0,0,0.1) !important;
            }

            .mobile-report-card .action-buttons .btn-group {
                flex-direction: row;
                gap: 0.5rem;
            }

            .mobile-report-card .action-buttons .btn {
                flex: 1;
                border-radius: 0.375rem !important;
            }
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
                <a href="reservation.php" class="list-group-item list-group-item-action">
                    <i class="bi bi-calendar-check"></i> Booking
                </a>
                <a href="inventory.php" class="list-group-item list-group-item-action">
                    <i class="bi bi-box-seam"></i> Inventory
                </a>
                <a href="report.php" class="list-group-item list-group-item-action active">
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
                        <i class="bi bi-file-text me-2"></i>
                        Report
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

            <!-- Report Content -->
            <div class="container-fluid py-4">
                <!-- Header Actions -->
                <div class="d-flex flex-wrap flex-md-nowrap justify-content-between align-items-center mb-4 gap-2">
                    <div class="period-selector flex-grow-1 mb-2 mb-md-0">
                        <button class="period-btn active" onclick="updatePeriod('weekly')">Weekly</button>
                        <button class="period-btn" onclick="updatePeriod('monthly')">Monthly</button>
                        <button class="period-btn" onclick="updatePeriod('yearly')">Yearly</button>
                    </div>
                    <button class="export-btn flex-shrink-0 w-10 w-md-auto" style="min-height:48px;font-size:1rem;" onclick="exportToExcel()" aria-label="Export report to Excel">
                        <i class="bi bi-download me-2"></i>Export Report
                    </button>
                </div>

                <!-- Statistics Cards -->
                <div class="row g-3 mb-4">
                    <!-- Booking Card -->
                    <div class="col-md-3">
                        <div class="stat-card booking h-100">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="card-title text-uppercase mb-2">Bookings</h6>
                                    <h3 class="stat-value mb-1" data-stat="Booking"><?php echo $stats['Booking']; ?></h3>
                                </div>
                            <div class="stat-icon">
                                <i class="bi bi-calendar-check"></i>
                            </div>
                            </div>
                        </div>
                    </div>

                    <!-- Chairs Card -->
                    <div class="col-md-3">
                        <div class="stat-card chairs h-100">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="card-title text-uppercase mb-2">Chairs</h6>
                                    <h3 class="stat-value mb-1"><?php echo $stats['Chairs']; ?></h3>
                                </div>
                            <div class="stat-icon">
                                    <i class="bi bi-box"></i>
                            </div>
                            </div>
                        </div>
                    </div>

                    <!-- Tables Card -->
                    <div class="col-md-3">
                        <div class="stat-card tables h-100">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="card-title text-uppercase mb-2">Tables</h6>
                                    <h3 class="stat-value mb-1"><?php echo $stats['Tables']; ?></h3>
                                </div>
                            <div class="stat-icon">
                                <i class="bi bi-table"></i>
                            </div>
                            </div>
                        </div>
                    </div>

                    <!-- Users Card -->
                    <div class="col-md-3">
                        <div class="stat-card users h-100">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="card-title text-uppercase mb-2">Users</h6>
                                    <h3 class="stat-value mb-1"><?php echo $stats['Users']; ?></h3>
                                </div>
                            <div class="stat-icon">
                                <i class="bi bi-people"></i>
                            </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Additional Equipment Statistics -->
                <div class="row g-3 mb-4">
                    <!-- Sound System Card -->
                    <div class="col-md-3">
                        <div class="stat-card sound-system h-100">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="card-title text-uppercase mb-2">Sound System</h6>
                                    <h3 class="stat-value mb-1" data-stat="Sound System"><?php echo $stats['Sound System']; ?></h3>
                                </div>
                                <div class="stat-icon">
                                    <i class="bi bi-speaker"></i>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Industrial Fan Card -->
                    <div class="col-md-3">
                        <div class="stat-card fan h-100">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="card-title text-uppercase mb-2">Industrial Fan</h6>
                                    <h3 class="stat-value mb-1" data-stat="Industrial Fan"><?php echo $stats['Industrial Fan']; ?></h3>
                                </div>
                                <div class="stat-icon">
                                    <i class="bi bi-wind"></i>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Extension Wires Card -->
                    <div class="col-md-3">
                        <div class="stat-card wires h-100">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="card-title text-uppercase mb-2">Extension Wires</h6>
                                    <h3 class="stat-value mb-1" data-stat="Extension Wires"><?php echo $stats['Extension Wires']; ?></h3>
                                </div>
                                <div class="stat-icon">
                                    <i class="bi bi-lightning-charge"></i>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Podium Card -->
                    <div class="col-md-3">
                        <div class="stat-card podium h-100">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="card-title text-uppercase mb-2">Podium</h6>
                                    <h3 class="stat-value mb-1" data-stat="Podium"><?php echo $stats['Podium']; ?></h3>
                                </div>
                                <div class="stat-icon">
                                    <i class="bi bi-stand"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Report Table -->
                <div class="report-table">
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>Venue</th>
                                    <th>Purpose</th>
                                    <th>Equipment</th>
                                    <th>Date & Time</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody id="reportTableBody">
                                <tr>
                                    <td colspan="7" class="text-center">
                                        <div class="loading-state">
                                            <div class="loading-spinner"></div>
                                            <p>Loading data...</p>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Mobile Reports -->
                <div class="mobile-reports" id="mobileReportsBody">
                    <div class="text-center py-5">
                        <div class="loading-state">
                            <div class="loading-spinner"></div>
                            <p>Loading data...</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/xlsx@0.18.5/dist/xlsx.full.min.js"></script>
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
        
        let currentPeriod = 'weekly';

        function updatePeriod(period) {
            currentPeriod = period;
            $('.period-btn').removeClass('active');
            $(`.period-btn[onclick="updatePeriod('${period}')"]`).addClass('active');
            loadReportData();
        }

        function loadReportData() {
            // Show loading state
            $('#reportTableBody').html(`
                <tr>
                    <td colspan="7" class="text-center">
                        <div class="loading-state">
                            <div class="loading-spinner"></div>
                            <p>Loading data...</p>
                        </div>
                    </td>
                </tr>
            `);
            
            $('#mobileReportsBody').html(`
                <div class="text-center py-5">
                    <div class="loading-state">
                        <div class="loading-spinner"></div>
                        <p>Loading data...</p>
                    </div>
                </div>
            `);

            // Fetch report data
            fetch(`get_report_data.php?period=${currentPeriod}`)
                .then(response => {
                    if (!response.ok) {
                        throw new Error(`HTTP error! status: ${response.status}`);
                    }
                    return response.json();
                })
                .then(data => {
                    console.log('Report data received:', data);
                    
                    // Check if data has the expected structure
                    if (!data || typeof data !== 'object') {
                        throw new Error('Invalid data format received');
                    }
                    
                    // Update statistics
                    if (data.stats) {
                    Object.keys(data.stats).forEach(key => {
                            const element = $(`.stat-value[data-stat="${key}"]`);
                            if (element.length > 0) {
                                element.text(data.stats[key] || '0');
                            }
                    });
                    }

                    // Update desktop table
                    if (!data.bookings || data.bookings.length === 0) {
                        $('#reportTableBody').html(`
                            <tr>
                                <td colspan="7" class="text-center">
                                    <div class="empty-state">
                                        <i class="bi bi-inbox"></i>
                                        <p>No bookings found for this period</p>
                                    </div>
                                </td>
                            </tr>
                        `);
                    } else {
                        const tableRows = data.bookings.map(booking => `
                            <tr>
                                <td>${booking.name || 'N/A'}</td>
                                <td>${booking.venue || 'N/A'}</td>
                                <td>${booking.purpose || 'N/A'}</td>
                                <td>${booking.equipment || 'N/A'}</td>
                                <td>${booking.date_time || 'N/A'}</td>
                                <td>
                                    <span class="status-badge ${(booking.status || 'pending').toLowerCase()}">
                                        ${booking.status || 'Pending'}
                                    </span>
                                </td>
                                <td>
                                    <div class="btn-group" role="group">
                                    <a href="view_reservation.php?id=${booking.id}" class="btn btn-sm btn-outline-primary">
                                        <i class="bi bi-eye"></i> View
                                    </a>
                                        <button type="button" class="btn btn-sm btn-outline-danger" onclick="deleteBooking(${booking.id}, '${booking.name}')">
                                            <i class="bi bi-trash"></i> Delete
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        `).join('');
                        $('#reportTableBody').html(tableRows);
                    }
                    
                    // Update mobile cards
                    if (!data.bookings || data.bookings.length === 0) {
                        $('#mobileReportsBody').html(`
                            <div class="text-center py-5">
                                <div class="empty-state">
                                    <i class="bi bi-inbox"></i>
                                    <p>No bookings found for this period</p>
                                </div>
                            </div>
                        `);
                    } else {
                        const mobileCards = data.bookings.map(booking => `
                            <div class="mobile-report-card">
                                <div class="report-header">
                                    <div class="item-info">
                                        <div class="item-icon">
                                            <i class="bi bi-calendar-check"></i>
                                        </div>
                                        <div>
                                            <div class="item-name">${booking.name || 'N/A'}</div>
                                            <div class="item-category">${booking.venue || 'N/A'}</div>
                                        </div>
                                    </div>
                                    <div>
                                        <span class="status-badge ${(booking.status || 'pending').toLowerCase()}">
                                            ${booking.status || 'Pending'}
                                        </span>
                                    </div>
                                </div>
                                
                                <div class="report-details">
                                    <div class="detail-item">
                                        <span class="detail-label">Purpose:</span>
                                        <span class="detail-value">${booking.purpose || 'N/A'}</span>
                                    </div>
                                    <div class="detail-item">
                                        <span class="detail-label">Equipment:</span>
                                        <span class="detail-value">${booking.equipment || 'N/A'}</span>
                                    </div>
                                    <div class="detail-item">
                                        <span class="detail-label">Date & Time:</span>
                                        <span class="detail-value">${booking.date_time || 'N/A'}</span>
                                    </div>
                                </div>
                                
                                <div class="action-buttons">
                                    <div class="btn-group w-100" role="group">
                                        <a href="view_reservation.php?id=${booking.id}" class="btn btn-outline-primary btn-sm flex-fill">
                                        <i class="bi bi-eye"></i> View Details
                                    </a>
                                        <button type="button" class="btn btn-outline-danger btn-sm flex-fill" onclick="deleteBooking(${booking.id}, '${booking.name}')">
                                            <i class="bi bi-trash"></i> Delete
                                        </button>
                                    </div>
                                </div>
                            </div>
                        `).join('');
                        $('#mobileReportsBody').html(mobileCards);
                    }
                })
                .catch(error => {
                    console.error('Error loading report data:', error);
                    $('#reportTableBody').html(`
                        <tr>
                            <td colspan="7" class="text-center text-danger">
                                <i class="bi bi-exclamation-triangle me-2"></i>
                                Error loading data. Please try again.
                                <br><small class="text-muted">${error.message}</small>
                            </td>
                        </tr>
                    `);
                    $('#mobileReportsBody').html(`
                        <div class="text-center py-5 text-danger">
                            <i class="bi bi-exclamation-triangle me-2"></i>
                            Error loading data. Please try again.
                            <br><small class="text-muted">${error.message}</small>
                        </div>
                    `);
                });
        }

        function exportToExcel() {
            // Show loading state
            const exportBtn = $('.export-btn');
            const originalText = exportBtn.html();
            exportBtn.html('<span class="spinner-border spinner-border-sm me-2"></span>Exporting...').prop('disabled', true);

            // Fetch data for export
            fetch(`get_report_data.php?period=${currentPeriod}`)
                .then(response => response.json())
                .then(data => {
                    if (!data.bookings || data.bookings.length === 0) {
                        alert('No data available to export.');
                        exportBtn.html(originalText).prop('disabled', false);
                        return;
                    }

                    // Prepare worksheet data
                    const wsData = [
                        ['PGOM Facilities Booking Report'],
                        [`Period: ${currentPeriod.charAt(0).toUpperCase() + currentPeriod.slice(1)}`],
                        [''],
                        ['Name', 'Venue', 'Purpose', 'Equipment', 'Date & Time', 'Status']
                    ];

                    // Add booking data
                    data.bookings.forEach(booking => {
                        wsData.push([
                            booking.name || '',
                            booking.venue || '',
                            booking.purpose || '',
                            booking.equipment || '',
                            booking.date_time || '',
                            booking.status || ''
                        ]);
                    });

                    // Add statistics
                    wsData.push(['']);
                    wsData.push(['Statistics']);
                    wsData.push(['Bookings', data.stats.Booking || 0]);
                    wsData.push(['Chairs', data.stats.Chairs || 0]);
                    wsData.push(['Tables', data.stats.Tables || 0]);
                    wsData.push(['Users', data.stats.Users || 0]);
                    wsData.push(['Sound System', data.stats['Sound System'] || 0]);
                    wsData.push(['Industrial Fan', data.stats['Industrial Fan'] || 0]);
                    wsData.push(['Extension Wires', data.stats['Extension Wires'] || 0]);
                    wsData.push(['Podium', data.stats.Podium || 0]);

                    // Create worksheet
                    const ws = XLSX.utils.aoa_to_sheet(wsData);

                    // Set column widths
                    const colWidths = [
                        { wch: 30 }, // Name
                        { wch: 20 }, // Venue
                        { wch: 30 }, // Purpose
                        { wch: 30 }, // Equipment
                        { wch: 20 }, // Date & Time
                        { wch: 15 }  // Status
                    ];
                    ws['!cols'] = colWidths;

                    // Create workbook
                    const wb = XLSX.utils.book_new();
                    XLSX.utils.book_append_sheet(wb, ws, 'Bookings');

                    // Generate filename
                    const date = new Date().toISOString().split('T')[0];
                    const fileName = `PGOM_Booking_Report_${currentPeriod}_${date}.xlsx`;

                    // Use blob-based approach for better compatibility
                    try {
                        // Convert to blob
                        const wbout = XLSX.write(wb, { bookType: 'xlsx', type: 'array' });
                        const blob = new Blob([wbout], { 
                            type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' 
                        });
                        
                        // Create download link
                        const url = window.URL.createObjectURL(blob);
                        const link = document.createElement('a');
                        link.href = url;
                        link.download = fileName;
                        link.style.display = 'none';
                        
                        // For mobile devices, we need to make the link visible briefly
                        if (/Android|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent)) {
                            link.style.position = 'fixed';
                            link.style.top = '0';
                            link.style.left = '0';
                            link.style.width = '100%';
                            link.style.height = '100%';
                            link.style.opacity = '0';
                            link.style.zIndex = '9999';
                        }
                        
                        // Append to body and trigger download
                        document.body.appendChild(link);
                        
                        // Trigger click with a small delay to ensure DOM is ready
                        setTimeout(() => {
                            link.click();
                            
                            // Cleanup after a longer delay
                            setTimeout(() => {
                                if (document.body.contains(link)) {
                                    document.body.removeChild(link);
                                }
                                window.URL.revokeObjectURL(url);
                            }, 1000);
                        }, 100);
                        
                        // Restore button state
                        exportBtn.html(originalText).prop('disabled', false);
                        
                        // Show success message
                        setTimeout(() => {
                            alert('Report exported successfully! Check your downloads folder.');
                        }, 1000);
                        
                    } catch (error) {
                        console.error('Export error:', error);
                        
                        // Fallback: try to open in new window for mobile
                        try {
                            const wbout = XLSX.write(wb, { bookType: 'xlsx', type: 'base64' });
                            const dataUri = 'data:application/vnd.openxmlformats-officedocument.spreadsheetml.sheet;base64,' + wbout;
                            
                            const newWindow = window.open(dataUri, '_blank');
                            if (newWindow) {
                                setTimeout(() => {
                                    newWindow.close();
                                }, 1000);
                            }
                            
                            exportBtn.html(originalText).prop('disabled', false);
                            alert('Report opened in new window. Please save it manually.');
                            
                        } catch (fallbackError) {
                            console.error('Fallback export error:', fallbackError);
                            exportBtn.html(originalText).prop('disabled', false);
                            alert('Export failed. Please try again or contact support.');
                        }
                    }
                })
                .catch(error => {
                    console.error('Error fetching data for export:', error);
                    alert('Error loading data for export. Please try again.');
                    exportBtn.html(originalText).prop('disabled', false);
                });
        }

        function deleteBooking(bookingId, bookingName) {
            if (confirm(`Are you sure you want to delete the booking for "${bookingName}"? This action cannot be undone.`)) {
                console.log('Attempting to delete booking with ID:', bookingId);
                
                // Show loading state
                const deleteBtn = event.target.closest('.btn');
                const originalText = deleteBtn.innerHTML;
                deleteBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Deleting...';
                deleteBtn.disabled = true;

                // Send delete request
                fetch('delete_reservation.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        booking_id: bookingId
                    })
                })
                .then(response => {
                    console.log('Response status:', response.status);
                    
                    if (!response.ok) {
                        throw new Error(`HTTP error! status: ${response.status}`);
                    }
                    return response.json();
                })
                .then(data => {
                    console.log('Response data:', data);
                    
                    if (data.success) {
                        // Show success message
                        alert('Booking deleted successfully!');
                        // Reload the report data
                        loadReportData();
                    } else {
                        const errorMessage = data.error || data.message || 'Unknown error occurred';
                        console.log('Error message extracted:', errorMessage);
                        alert('Error deleting booking: ' + errorMessage);
                        // Restore button state
                        deleteBtn.innerHTML = originalText;
                        deleteBtn.disabled = false;
                    }
                })
                .catch(error => {
                    console.error('Error in deleteBooking:', error);
                    console.error('Error message:', error.message);
                    
                    let errorMessage = 'Error deleting booking. Please try again.';
                    
                    // Try to parse error response if it's JSON
                    if (error.message && error.message.includes('HTTP error')) {
                        errorMessage = 'Server error occurred. Please try again.';
                    }
                    
                    alert(errorMessage);
                    // Restore button state
                    deleteBtn.innerHTML = originalText;
                    deleteBtn.disabled = false;
                });
            }
        }

        // Initial load
        $(document).ready(function() {
            loadReportData();
        });
    </script>
</body>
</html>