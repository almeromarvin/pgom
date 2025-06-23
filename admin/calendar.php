<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../login.php');
    exit();
}

// Get current month and year
$current_month = isset($_GET['month']) ? (int)$_GET['month'] : (int)date('n');
$current_year = isset($_GET['year']) ? (int)$_GET['year'] : (int)date('Y');

// Get first day of month and number of days
$first_day = mktime(0, 0, 0, $current_month, 1, $current_year);
$days_in_month = date('t', $first_day);
$first_day_of_week = date('w', $first_day);

// Get bookings for the current month
$bookings = [];
try {
    $start_date = date('Y-m-01', $first_day);
    $end_date = date('Y-m-t', $first_day);
    
    $stmt = $pdo->prepare("
        SELECT 
    b.id,
    b.start_time,
    b.end_time,
    b.status,
            u.name as user_name,
    f.name as facility_name,
            GROUP_CONCAT(CONCAT(i.name, ' (', be.quantity, ')') SEPARATOR ', ') as equipment
    FROM bookings b
    JOIN users u ON b.user_id = u.id
        JOIN facilities f ON b.facility_id = f.id
        LEFT JOIN booking_equipment be ON b.id = be.booking_id
        LEFT JOIN inventory i ON be.equipment_id = i.id
        WHERE DATE(b.start_time) BETWEEN ? AND ?
        GROUP BY b.id
        ORDER BY b.start_time
    ");
    $stmt->execute([$start_date, $end_date]);
    $bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // Handle error silently
}

// Organize bookings by date
$bookings_by_date = [];
foreach ($bookings as $booking) {
    $date = date('j', strtotime($booking['start_time']));
    if (!isset($bookings_by_date[$date])) {
        $bookings_by_date[$date] = [];
    }
    $bookings_by_date[$date][] = $booking;
}

// Get month name
$month_name = date('F Y', $first_day);

// Navigation URLs
$prev_month = $current_month - 1;
$prev_year = $current_year;
if ($prev_month < 1) {
    $prev_month = 12;
    $prev_year--;
}

$next_month = $current_month + 1;
$next_year = $current_year;
if ($next_month > 12) {
    $next_month = 1;
    $next_year++;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Calendar View - PGOM Facilities</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
    <link href="../assets/css/notifications.css" rel="stylesheet">
    <style>
        /* Calendar Styles */
            .calendar-container {
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 12px rgba(0,0,0,0.08);
            overflow: hidden;
                border: 1px solid #e9ecef;
        }

        .calendar-header {
                background: #004225;
                color: white;
            padding: 1.5rem;
            text-align: center;
        }

        .calendar-nav {
                display: flex;
                justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
        }

        .calendar-nav .btn {
            background: rgba(255,255,255,0.2);
            border: none;
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 6px;
            transition: all 0.2s ease;
                font-weight: 500;
            }
            
        .calendar-nav .btn:hover {
            background: rgba(255,255,255,0.3);
            color: white;
            }
            
            .calendar-title {
            font-size: 1.5rem;
            font-weight: 600;
            margin: 0;
        }

        .calendar-grid {
            display: grid;
            grid-template-columns: repeat(7, 1fr);
            gap: 0;
            background: #f8f9fa;
        }

        .calendar-day-header {
            background: #f8f9fa;
            padding: 1rem 0.5rem;
            text-align: center;
            font-weight: 600;
            color: #495057;
            font-size: 0.875rem;
            border-bottom: 1px solid #dee2e6;
        }

        .calendar-day {
            background: white;
            min-height: 100px;
            padding: 0.5rem;
            position: relative;
            cursor: pointer;
            transition: all 0.2s ease;
            user-select: none;
            border-right: 1px solid #f1f3f4;
            border-bottom: 1px solid #f1f3f4;
        }

        .calendar-day:hover {
            background: #f8f9fa;
        }

        .calendar-day:active {
            background: #e9ecef;
        }

        .calendar-day.today {
            background: #e3f2fd;
            border: 2px solid #2196f3;
        }

        .calendar-day.other-month {
            background: #f8f9fa;
            color: #adb5bd;
            cursor: default;
        }

        .calendar-day.other-month:hover {
            background: #f8f9fa;
        }

        .calendar-day.clickable {
            cursor: pointer;
        }

        .calendar-day.clickable:hover {
            background: #f8f9fa;
        }

        .day-number {
            font-weight: 600;
                font-size: 0.9rem;
            margin-bottom: 0.25rem;
            color: #2c3e50;
            text-align: center;
            padding: 0.25rem;
            border-radius: 4px;
            transition: all 0.2s ease;
        }

        .calendar-day:hover .day-number {
            background: rgba(0, 66, 37, 0.1);
            color: #004225;
        }

        .calendar-day.today .day-number {
            background: rgba(33, 150, 243, 0.2);
            color: #1976d2;
            font-weight: 700;
        }

        .booking-indicator {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            display: inline-block;
            margin-right: 3px;
        }

        .booking-indicator.pending {
            background: #ffc107;
        }

        .booking-indicator.approved {
            background: #28a745;
        }

        .booking-indicator.rejected {
            background: #dc3545;
        }

        .booking-indicator.completed {
            background: #17a2b8;
        }

        .booking-count {
            font-size: 0.75rem;
            color: #6c757d;
            margin-top: 0.25rem;
            font-weight: 500;
            text-align: center;
        }

        .booking-preview {
            font-size: 0.7rem;
            color: #495057;
            margin-top: 0.25rem;
            line-height: 1.2;
            padding: 0.25rem;
            background: rgba(255, 255, 255, 0.8);
            border-radius: 4px;
        }

        .booking-preview .user-name {
            font-weight: 600;
            color: #004225;
            display: block;
            margin-bottom: 0.1rem;
        }

        /* Modal Styles */
        .booking-modal .modal-content {
            border: none;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.15);
            overflow: hidden;
        }

        .booking-modal .modal-header {
            background: #004225;
            color: white;
            padding: 1.5rem 2rem;
        }

        .booking-modal .modal-title {
            font-weight: 600;
            font-size: 1.25rem;
        }

        .booking-modal .btn-close {
            filter: invert(1);
            opacity: 0.8;
        }

        .booking-modal .btn-close:hover {
            opacity: 1;
        }

        .booking-modal .modal-body {
            padding: 2rem;
            background: white;
        }

        .booking-modal .modal-footer {
            padding: 1.5rem 2rem;
            background: #f8f9fa;
            border-top: 1px solid #e9ecef;
        }

        .status-badge {
            padding: 0.375rem 0.75rem;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
        }

        .status-badge.pending {
            background-color: #fff3cd;
            color: #856404;
        }

        .status-badge.approved {
            background-color: #d4edda;
            color: #155724;
        }

        .status-badge.rejected {
            background-color: #f8d7da;
            color: #721c24;
        }

        .status-badge.completed {
            background-color: #cce5ff;
            color: #004085;
        }

        /* Card styles in modal */
        .booking-modal .card {
            border: 1px solid #e9ecef;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
            transition: all 0.2s ease;
            background: white;
        }

        .booking-modal .card:hover {
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }

        .booking-modal .card-body {
            padding: 1.5rem;
        }

        .booking-modal .card-title {
            color: #2c3e50;
            font-weight: 600;
            margin-bottom: 1rem;
        }

        .booking-modal .card-text {
            color: #6c757d;
            font-weight: 500;
        }

        .booking-modal .btn {
            border-radius: 6px;
            font-weight: 500;
            padding: 0.5rem 1rem;
            transition: all 0.2s ease;
        }

        .booking-modal .btn-outline-primary {
            border-color: #004225;
            color: #004225;
        }

        .booking-modal .btn-outline-primary:hover {
            background: #004225;
            border-color: #004225;
        }

        .booking-modal .btn-outline-danger {
            border-color: #dc3545;
            color: #dc3545;
        }

        .booking-modal .btn-outline-danger:hover {
            background: #dc3545;
            border-color: #dc3545;
        }

        /* Mobile Responsive */
        @media (max-width: 768px) {
            .calendar-container {
                margin: 0.5rem;
                border-radius: 8px;
            }

            .calendar-day {
                min-height: 80px;
                padding: 0.4rem;
            }

            .day-number {
                font-size: 0.8rem;
                padding: 0.2rem;
            }

            .booking-preview {
                font-size: 0.65rem;
                padding: 0.2rem;
            }

            .calendar-title {
                font-size: 1.25rem;
            }

            .calendar-header {
                padding: 1rem;
            }

            .calendar-nav .btn {
                padding: 0.4rem 0.8rem;
                font-size: 0.9rem;
            }

            /* Modal adjustments for mobile */
            .booking-modal .modal-dialog {
                margin: 1rem;
                max-width: calc(100% - 2rem);
            }

            .booking-modal .modal-body {
                padding: 1.5rem;
            }

            .booking-modal .card-body {
                padding: 1rem;
            }

            .booking-modal .btn {
                padding: 0.5rem 0.8rem;
                font-size: 0.9rem;
            }

            /* Container adjustments */
            .container-fluid {
                padding-left: 0.5rem !important;
                padding-right: 0.5rem !important;
            }

            /* Calendar grid adjustments */
            .calendar-grid {
                grid-template-columns: repeat(7, 1fr);
                gap: 0;
            }

            .calendar-day-header {
                padding: 0.75rem 0.25rem;
                font-size: 0.8rem;
            }

            /* Ensure calendar fits properly */
            .main-content {
                overflow-x: hidden;
            }

            .calendar-container {
                overflow-x: auto;
                max-width: 100%;
            }
        }

        @media (max-width: 576px) {
            .calendar-day {
                min-height: 70px;
                padding: 0.3rem;
            }

            .day-number {
                font-size: 0.75rem;
                padding: 0.15rem;
            }

            .booking-count {
                font-size: 0.7rem;
            }

            .booking-indicator {
                width: 6px;
                height: 6px;
            }

            .calendar-title {
                font-size: 1.1rem;
            }

            .header-title {
                font-size: 1rem !important;
            }

            .calendar-header {
                padding: 0.75rem;
            }

            .calendar-nav .btn {
                padding: 0.3rem 0.6rem;
                font-size: 0.8rem;
            }

            .calendar-day-header {
                padding: 0.5rem 0.2rem;
                font-size: 0.75rem;
            }

            .booking-preview {
                font-size: 0.6rem;
                padding: 0.15rem;
            }

            /* Modal adjustments for small screens */
            .booking-modal .modal-dialog {
                margin: 0.5rem;
                max-width: calc(100% - 1rem);
            }

            .booking-modal .modal-body {
                padding: 1rem;
            }

            .booking-modal .card-body {
                padding: 0.75rem;
            }

            .booking-modal .btn {
                padding: 0.4rem 0.6rem;
                font-size: 0.8rem;
            }

            /* Container adjustments for small screens */
            .container-fluid {
                padding-left: 0.25rem !important;
                padding-right: 0.25rem !important;
            }
        }

        @media (max-width: 480px) {
            .calendar-day {
                min-height: 60px;
                padding: 0.25rem;
            }

            .day-number {
                font-size: 0.7rem;
                padding: 0.1rem;
            }

            .booking-count {
                font-size: 0.65rem;
            }

            .booking-indicator {
                width: 5px;
                height: 5px;
            }

            .calendar-title {
                font-size: 1rem;
            }

            .header-title {
                font-size: 0.9rem !important;
            }

            .calendar-header {
                padding: 0.5rem;
            }

            .calendar-nav .btn {
                padding: 0.25rem 0.5rem;
                font-size: 0.75rem;
            }

            .calendar-day-header {
                padding: 0.4rem 0.15rem;
                font-size: 0.7rem;
            }

            .booking-preview {
                font-size: 0.55rem;
                padding: 0.1rem;
            }

            /* Modal adjustments for very small screens */
            .booking-modal .modal-dialog {
                margin: 0.25rem;
                max-width: calc(100% - 0.5rem);
            }

            .booking-modal .modal-body {
                padding: 0.75rem;
            }

            .booking-modal .card-body {
                padding: 0.5rem;
            }

            .booking-modal .btn {
                padding: 0.3rem 0.5rem;
                font-size: 0.75rem;
            }

            /* Container adjustments for very small screens */
            .container-fluid {
                padding-left: 0.125rem !important;
                padding-right: 0.125rem !important;
            }
        }

        /* Mobile menu toggle */
        .mobile-menu-toggle {
            display: none;
            background: none;
            border: none;
            color: white;
            font-size: 1.5rem;
            padding: 0.5rem;
        }

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
                overflow-x: hidden;
            }
            
            .mobile-menu-toggle {
                display: block !important;
            }
            
            .header-title {
                font-size: 1.1rem !important;
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

        @media (max-width: 480px) {
            .header-title {
                font-size: 1rem !important;
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
            
            .calendar-container {
                max-width: 100vw;
                overflow-x: auto;
            }
            
            .calendar-grid {
                min-width: 100%;
            }
        }
    </style>
</head>
<body>
    <div class="d-flex">
        <!-- Sidebar -->
        <?php include '../components/sidebar.php'; ?>

        <!-- Main Content -->
        <div class="main-content flex-grow-1">
            <!-- Header -->
            <header class="main-header text-white d-flex justify-content-between align-items-center">
                <div class="d-flex align-items-center">
                    <button class="mobile-menu-toggle me-3" id="mobileMenuToggle">
                        <i class="bi bi-list"></i>
                    </button>
                    <h1 class="h3 mb-0 d-flex align-items-center header-title">
                        <i class="bi bi-calendar3 me-2"></i>
                        Calendar View
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

            <!-- Sidebar Overlay for Mobile -->
            <div class="sidebar-overlay" id="sidebarOverlay"></div>

            <!-- Calendar Content -->
            <div class="container-fluid py-4 px-4">
                <div class="calendar-container">
                    <!-- Calendar Header -->
                    <div class="calendar-header">
                        <div class="calendar-nav">
                            <a href="?month=<?php echo $prev_month; ?>&year=<?php echo $prev_year; ?>" class="btn">
                                <i class="bi bi-chevron-left"></i> Previous
                            </a>
                            <h2 class="calendar-title"><?php echo $month_name; ?></h2>
                            <a href="?month=<?php echo $next_month; ?>&year=<?php echo $next_year; ?>" class="btn">
                                Next <i class="bi bi-chevron-right"></i>
                            </a>
        </div>
    </div>
    
                    <!-- Calendar Grid -->
                    <div class="calendar-grid">
                        <!-- Day Headers -->
                        <div class="calendar-day-header">Sun</div>
                        <div class="calendar-day-header">Mon</div>
                        <div class="calendar-day-header">Tue</div>
                        <div class="calendar-day-header">Wed</div>
                        <div class="calendar-day-header">Thu</div>
                        <div class="calendar-day-header">Fri</div>
                        <div class="calendar-day-header">Sat</div>

                        <?php
                        // Previous month days
                        for ($i = 0; $i < $first_day_of_week; $i++) {
                            echo '<div class="calendar-day other-month"></div>';
                        }

                        // Current month days
                        $today = date('j');
                        $current_month_num = date('n');
                        $current_year_num = date('Y');

                        for ($day = 1; $day <= $days_in_month; $day++) {
                            $is_today = ($day == $today && $current_month == $current_month_num && $current_year == $current_year_num);
                            $day_class = $is_today ? 'calendar-day today clickable' : 'calendar-day clickable';
                            $date_attr = date('Y-m-d', mktime(0, 0, 0, $current_month, $day, $current_year));
                            
                            echo '<div class="' . $day_class . '" data-date="' . $date_attr . '" onclick="loadBookingsForDate(\'' . $date_attr . '\')">';
                            echo '<div class="day-number">' . $day . '</div>';
                            
                            if (isset($bookings_by_date[$day])) {
                                $day_bookings = $bookings_by_date[$day];
                                $status_counts = [
                                    'pending' => 0,
                                    'approved' => 0,
                                    'rejected' => 0,
                                    'completed' => 0
                                ];
                                
                                foreach ($day_bookings as $booking) {
                                    $status_counts[$booking['status']]++;
                                }
                                
                                // Show status indicators
                                foreach ($status_counts as $status => $count) {
                                    if ($count > 0) {
                                        echo '<span class="booking-indicator ' . $status . '"></span>';
                                    }
                                }
                                
                                // Show total count
                                $total_bookings = count($day_bookings);
                                if ($total_bookings > 0) {
                                    echo '<div class="booking-count">' . $total_bookings . ' booking' . ($total_bookings > 1 ? 's' : '') . '</div>';
                                }
                                
                                // Show first booking preview
                                if (!empty($day_bookings)) {
                                    $first_booking = $day_bookings[0];
                                    echo '<div class="booking-preview">';
                                    echo '<span class="user-name">' . htmlspecialchars($first_booking['user_name']) . '</span><br>';
                                    echo htmlspecialchars($first_booking['facility_name']);
                                    if (count($day_bookings) > 1) {
                                        echo ' <small>(+' . (count($day_bookings) - 1) . ' more)</small>';
                                    }
                                    echo '</div>';
                                }
                            }
                            
                            echo '</div>';
                        }

                        // Next month days
                        $total_days_shown = $first_day_of_week + $days_in_month;
                        $remaining_days = 42 - $total_days_shown; // 6 rows * 7 days
                        for ($i = 0; $i < $remaining_days; $i++) {
                            echo '<div class="calendar-day other-month"></div>';
                        }
                        ?>
        </div>
    </div>
                </div>
        </div>
    </div>

    <!-- Booking Details Modal -->
    <div class="modal fade booking-modal" id="bookingModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="bi bi-calendar-check me-2"></i>
                        Bookings for <span id="modalDate"></span>
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="modalBody">
                    <!-- Booking details will be loaded here -->
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
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

            // Debug: Log that calendar is ready
            console.log('Calendar initialized - days are clickable');
        });

        function loadBookingsForDate(date) {
            console.log('Loading bookings for date:', date); // Debug log
            
            const modal = new bootstrap.Modal(document.getElementById('bookingModal'));
            const modalDate = document.getElementById('modalDate');
            const modalBody = document.getElementById('modalBody');
            
            // Format date for display
            const dateObj = new Date(date);
            const formattedDate = dateObj.toLocaleDateString('en-US', { 
                weekday: 'long', 
                year: 'numeric', 
                month: 'long', 
                day: 'numeric' 
            });
            modalDate.textContent = formattedDate;
            
            // Show loading
            modalBody.innerHTML = `
                <div class="text-center py-4">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Loading...</span>
                                            </div>
                    <p class="mt-2">Loading bookings...</p>
                                        </div>
            `;
            
            modal.show();
            
            // Fetch bookings for the date
            fetch(`get_calendar_events.php?date=${date}`)
                    .then(response => response.json())
                .then(data => {
                    console.log('Received booking data:', data); // Debug log
                    if (data.bookings && data.bookings.length > 0) {
                        let html = '<div class="row">';
                        data.bookings.forEach(booking => {
                            html += `
                                <div class="col-md-6 mb-3">
                                    <div class="card border-0 shadow-sm">
                                        <div class="card-body">
                                            <div class="d-flex justify-content-between align-items-start mb-2">
                                                <h6 class="card-title mb-0">${booking.user_name}</h6>
                                                <span class="status-badge ${booking.status}">${booking.status}</span>
                            </div>
                                            <p class="card-text text-muted mb-2">
                                                <i class="bi bi-building me-1"></i>${booking.facility_name}
                                            </p>
                                            <p class="card-text text-muted mb-2">
                                                <i class="bi bi-clock me-1"></i>${booking.time}
                                            </p>
                                            ${booking.equipment ? `<p class="card-text text-muted mb-2">
                                                <i class="bi bi-box-seam me-1"></i>${booking.equipment}
                                            </p>` : ''}
                                            <div class="d-flex gap-2">
                                                <a href="view_reservation.php?id=${booking.id}" class="btn btn-sm btn-outline-primary">
                                                    <i class="bi bi-eye me-1"></i>View Details
                                                </a>
                                                <button class="btn btn-sm btn-outline-danger" onclick="deleteBooking(${booking.id}, '${booking.user_name}')">
                                                    <i class="bi bi-trash me-1"></i>Delete
                                                </button>
                        </div>
                                    </div>
                                    </div>
                                </div>
                            `;
                        });
                        html += '</div>';
                        modalBody.innerHTML = html;
                    } else {
                        modalBody.innerHTML = `
                            <div class="text-center py-4">
                                <i class="bi bi-calendar-x text-muted" style="font-size: 3rem;"></i>
                                <h5 class="mt-3 text-muted">No Bookings</h5>
                                <p class="text-muted">No bookings scheduled for this date.</p>
                                </div>
                        `;
                    }
                })
                .catch(error => {
                    console.error('Error loading bookings:', error);
                    modalBody.innerHTML = `
                        <div class="text-center py-4 text-danger">
                            <i class="bi bi-exclamation-triangle" style="font-size: 3rem;"></i>
                            <h5 class="mt-3">Error</h5>
                            <p>Failed to load bookings. Please try again.</p>
                        </div>
                    `;
                });
        }

        function deleteBooking(bookingId, bookingName) {
            if (confirm(`Are you sure you want to delete the booking for "${bookingName}"? This action cannot be undone.`)) {
                console.log('Attempting to delete booking with ID:', bookingId);
                
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
                        alert('Booking deleted successfully!');
                        location.reload(); // Refresh the calendar
                    } else {
                        const errorMessage = data.error || data.message || 'Unknown error occurred';
                        console.log('Error message extracted:', errorMessage);
                        alert('Error deleting booking: ' + errorMessage);
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
                });
            }
        }
    </script>
</body>
</html>