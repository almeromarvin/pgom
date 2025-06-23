<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'user') {
    header('Location: ../login.php');
    exit();
}

// Initialize filter variables
$status_filter = isset($_GET['status']) ? $_GET['status'] : 'all';
$date_filter = isset($_GET['date']) ? $_GET['date'] : '';

// Build the SQL query with filters
$sql = "SELECT b.*, f.name as facility_name,
        DATE(b.start_time) as event_date,
        TIME(b.start_time) as start_time,
        TIME(b.end_time) as end_time,
        LOWER(b.status) as status,
        GROUP_CONCAT(
            CONCAT(i.name, ' (', be.quantity, ')')
            ORDER BY i.name
            SEPARATOR ', '
        ) as equipment_details
        FROM bookings b 
        JOIN facilities f ON b.facility_id = f.id 
        LEFT JOIN booking_equipment be ON b.id = be.booking_id
        LEFT JOIN inventory i ON be.equipment_id = i.id
        WHERE b.user_id = ?";

$params = [$_SESSION['user_id']];

if ($status_filter !== 'all') {
    $sql .= " AND LOWER(b.status) = LOWER(?)";
    $params[] = $status_filter;
}

if ($date_filter) {
    $sql .= " AND DATE(b.start_time) = ?";
    $params[] = $date_filter;
}

$sql .= " GROUP BY b.id ORDER BY b.start_time DESC";

try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $bookings = [];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Bookings - PGOM Facilities</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css">
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
        
        /* Card styling to match admin */
        .card {
            border-radius: 16px;
            box-shadow: 0 4px 24px rgba(0,0,0,0.08);
            border: none;
            overflow: hidden;
        }
        
        .card-body {
            padding: 2rem;
        }
        
        /* Table styling */
        .table {
            margin-bottom: 0;
        }
        
        .table th {
            background-color: #f8f9fa;
            border-bottom: 2px solid #dee2e6;
            font-weight: 600;
            color: #495057;
            padding: 1rem;
        }
        
        .table td {
            padding: 1rem;
            vertical-align: middle;
            border-bottom: 1px solid #f0f0f0;
        }
        
        .table tbody tr:hover {
            background-color: #f8f9fa;
        }
        
        /* Status badges */
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
        
        .status-badge.cancelled {
            background-color: #e2e3e5;
            color: #383d41;
        }
        
        .status-badge.completed {
            background-color: #cce5ff;
            color: #004085;
        }
        
        /* Button styling */
        .btn {
            border-radius: 8px;
            font-weight: 500;
            transition: all 0.3s ease;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            border: none;
        }
        
        .btn-primary:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 15px rgba(40, 167, 69, 0.4);
        }
        
        .btn-sm {
            padding: 0.375rem 0.75rem;
            font-size: 0.875rem;
        }
        
        .btn-icon {
            width: 36px;
            height: 36px;
            padding: 0;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 8px;
        }
        
        /* Form controls */
        .form-control, .form-select {
            border-radius: 8px;
            border: 1px solid #dee2e6;
        }
        
        .form-control:focus, .form-select:focus {
            border-color: #28a745;
            box-shadow: 0 0 0 0.2rem rgba(40, 167, 69, 0.25);
        }
        
        /* Progress steps */
        .booking-progress {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .progress-steps {
            display: flex;
            align-items: center;
            gap: 0.25rem;
        }
        
        .step {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 0.25rem;
        }
        
        .step-icon {
            width: 24px;
            height: 24px;
            border-radius: 50%;
            background-color: #e9ecef;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.75rem;
            color: #6c757d;
        }
        
        .step.active .step-icon {
            background-color: #28a745;
            color: white;
        }
        
        .step-label {
            font-size: 0.625rem;
            color: #6c757d;
            text-align: center;
        }
        
        .step-connector {
            width: 20px;
            height: 2px;
            background-color: #e9ecef;
        }
        
        /* Action buttons container */
        .action-buttons {
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
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
            
            .table-responsive {
                display: none;
            }
            
            .mobile-bookings {
                display: block;
            }
            
            .booking-progress {
                display: none;
            }
            
            .status-badge {
                font-size: 0.75rem;
                padding: 0.25rem 0.5rem;
            }
            
            .btn {
                font-size: 0.75rem;
                padding: 0.25rem 0.5rem;
            }
            
            .container-fluid {
                padding-left: 1rem !important;
                padding-right: 1rem !important;
            }
            
            /* Mobile card layout */
            .mobile-booking-card {
                display: block;
                background: white;
                border: 1px solid #dee2e6;
                border-radius: 16px;
                padding: 1.5rem;
                margin-bottom: 1rem;
                box-shadow: 0 4px 24px rgba(0,0,0,0.08);
            }
            
            .mobile-booking-card .booking-header {
                display: flex;
                justify-content: space-between;
                align-items: flex-start;
                margin-bottom: 1rem;
                padding-bottom: 1rem;
                border-bottom: 1px solid #f0f0f0;
            }
            
            .mobile-booking-card .facility-name {
                font-weight: 600;
                color: #2c3e50;
                font-size: 1rem;
            }
            
            .mobile-booking-card .booking-date {
                color: #6c757d;
                font-size: 0.875rem;
            }
            
            .mobile-booking-card .booking-details {
                margin-bottom: 1rem;
            }
            
            .mobile-booking-card .detail-item {
                display: flex;
                justify-content: space-between;
                margin-bottom: 0.5rem;
                font-size: 0.875rem;
            }
            
            .mobile-booking-card .detail-label {
                color: #6c757d;
                font-weight: 500;
            }
            
            .mobile-booking-card .detail-value {
                color: #2c3e50;
                text-align: right;
            }
            
            .mobile-booking-card .booking-actions {
                display: flex;
                gap: 0.5rem;
                flex-wrap: wrap;
            }
            
            .mobile-booking-card .btn {
                flex: 1;
                min-width: 80px;
                font-size: 0.75rem;
                padding: 0.375rem 0.5rem;
            }
        }
        
        @media (min-width: 769px) {
            .mobile-bookings {
                display: none;
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
        
        .form-control,
        .form-select {
            min-height: 44px;
        }
        
        /* Empty state styling */
        .empty-state {
            text-align: center;
            padding: 3rem 1rem;
        }
        
        .empty-state i {
            font-size: 4rem;
            color: #dee2e6;
            margin-bottom: 1rem;
        }
        
        .empty-state h4 {
            color: #6c757d;
            margin-bottom: 0.5rem;
        }
        
        .empty-state p {
            color: #adb5bd;
            margin-bottom: 1.5rem;
        }
        
        /* Date picker styling for double booking prevention */
        .date-input-wrapper {
            position: relative;
        }
        
        .date-input-wrapper input[type="date"] {
            padding-right: 60px;
        }
        
        .booked-date-indicator {
            position: absolute;
            right: 35px;
            top: 50%;
            transform: translateY(-50%);
            color: #dc3545;
            font-weight: bold;
            font-size: 14px;
            pointer-events: none;
            background: rgba(220, 53, 69, 0.1);
            padding: 2px 6px;
            border-radius: 4px;
        }
        
        .form-text {
            font-size: 0.875rem;
            color: #6c757d;
            margin-top: 0.25rem;
        }
        
        .form-text .text-danger {
            color: #dc3545 !important;
        }
        
        /* Simple Professional Modal Styles */
        .modal-content {
            border-radius: 8px;
            border: 1px solid #dee2e6;
        }
        
        .modal-header {
            background-color: #f8f9fa;
            border-bottom: 1px solid #dee2e6;
            padding: 1rem 1.5rem;
        }
        
        .modal-title {
            font-weight: 600;
            color: #495057;
        }
        
        .modal-body {
            padding: 1.5rem;
        }
        
        .modal-footer {
            background-color: #f8f9fa;
            border-top: 1px solid #dee2e6;
            padding: 1rem 1.5rem;
        }
        
        .status-section {
            padding: 1rem;
            background-color: #f8f9fa;
            border-radius: 6px;
            border: 1px solid #e9ecef;
        }
        
        .status-badge {
            display: inline-block;
            padding: 0.5rem 1rem;
            border-radius: 4px;
            font-weight: 600;
            font-size: 0.875rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 0.5rem;
        }
        
        .status-badge.pending {
            background-color: #fff3cd;
            color: #856404;
            border: 1px solid #ffeaa7;
        }
        
        .status-badge.approved {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .status-badge.rejected {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .status-badge.completed {
            background-color: #d1ecf1;
            color: #0c5460;
            border: 1px solid #bee5eb;
        }
        
        .status-badge.cancelled {
            background-color: #e2e3e5;
            color: #383d41;
            border: 1px solid #d6d8db;
        }
        
        .status-description {
            color: #6c757d;
            font-size: 0.9rem;
            margin: 0;
        }
        
        .booking-info {
            margin-top: 1.5rem;
        }
        
        .info-row {
            display: flex;
            padding: 0.75rem 0;
            border-bottom: 1px solid #f1f3f4;
        }
        
        .info-row:last-child {
            border-bottom: none;
        }
        
        .info-label {
            font-weight: 600;
            color: #495057;
            min-width: 140px;
            flex-shrink: 0;
        }
        
        .info-value {
            color: #212529;
            flex: 1;
        }
        
        .equipment-list {
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
        }
        
        .equipment-item {
            background-color: #f8f9fa;
            border: 1px solid #e9ecef;
            border-radius: 4px;
            padding: 0.25rem 0.5rem;
            font-size: 0.875rem;
            color: #495057;
        }
        
        .request-letter-link {
            color: #007bff;
            text-decoration: none;
            font-weight: 500;
        }
        
        .request-letter-link:hover {
            text-decoration: underline;
        }
        
        /* Responsive adjustments */
        @media (max-width: 768px) {
            .info-row {
                flex-direction: column;
                gap: 0.25rem;
            }
            
            .info-label {
                min-width: auto;
            }
            
            .equipment-list {
                flex-direction: column;
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
                <a href="make_booking.php" class="list-group-item list-group-item-action">
                    <i class="bi bi-calendar-plus"></i> Make a Booking
                </a>
                <a href="my_bookings.php" class="list-group-item list-group-item-action active">
                    <i class="bi bi-calendar-check"></i> My Bookings
                </a>
                <a href="history.php" class="list-group-item list-group-item-action">
                    <i class="bi bi-clock-history"></i> History
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
                        My Bookings
                    </h1>
                </div>
                <div class="d-flex align-items-center gap-3">
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

            <!-- Content -->
            <div class="container-fluid py-4 px-4">
                <!-- Filters -->
                <div class="card mb-4">
                    <div class="card-body">
                        <form method="GET" class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label fw-semibold">Status</label>
                                <select name="status" class="form-select" onchange="this.form.submit()">
                                    <option value="all" <?php echo $status_filter === 'all' ? 'selected' : ''; ?>>All</option>
                                    <option value="pending" <?php echo $status_filter === 'pending' ? 'selected' : ''; ?>>Pending</option>
                                    <option value="approved" <?php echo $status_filter === 'approved' ? 'selected' : ''; ?>>Approved</option>
                                    <option value="rejected" <?php echo $status_filter === 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                                    <option value="cancelled" <?php echo $status_filter === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                                    <option value="completed" <?php echo $status_filter === 'completed' ? 'selected' : ''; ?>>Completed</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label fw-semibold">Date</label>
                                <input type="date" name="date" class="form-control" value="<?php echo $date_filter; ?>" onchange="this.form.submit()">
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Empty State (shown when no bookings) -->
                <?php if (empty($bookings)): ?>
                    <div class="empty-state">
                        <i class="bi bi-calendar-x"></i>
                        <h4>No Bookings Found</h4>
                        <p>You haven't made any bookings yet.</p>
                        <a href="make_booking.php" class="btn btn-primary">
                            <i class="bi bi-plus-circle me-2"></i>Make a Booking
                        </a>
                    </div>
                <?php else: ?>
                    <!-- Bookings Table -->
                    <div class="card">
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-hover mb-0">
                                    <thead>
                                        <tr>
                                            <th>Facility</th>
                                            <th>Date</th>
                                            <th>Time</th>
                                            <th>Equipment</th>
                                            <th>Progress</th>
                                            <th>Status</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($bookings as $booking): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($booking['facility_name']); ?></td>
                                                <td><?php echo date('M d, Y', strtotime($booking['event_date'])); ?></td>
                                                <td>
                                                    <?php 
                                                    echo date('h:i A', strtotime($booking['start_time'])) . ' - ' . 
                                                         date('h:i A', strtotime($booking['end_time'])); 
                                                    ?>
                                                </td>
                                                <td><?php echo htmlspecialchars($booking['equipment_details'] ?? 'None'); ?></td>
                                                <td>
                                                    <div class="booking-progress">
                                                        <div class="progress-steps">
                                                            <div class="step <?php echo in_array($booking['status'], ['pending', 'approved', 'completed']) ? 'active' : ''; ?>">
                                                                <div class="step-icon">
                                                                    <i class="bi bi-hourglass-split"></i>
                                                                </div>
                                                                <div class="step-label">Pending</div>
                                                            </div>
                                                            <div class="step-connector"></div>
                                                            <div class="step <?php echo in_array($booking['status'], ['approved', 'completed']) ? 'active' : ''; ?>">
                                                                <div class="step-icon">
                                                                    <i class="bi bi-check-circle"></i>
                                                                </div>
                                                                <div class="step-label">Approved</div>
                                                            </div>
                                                            <div class="step-connector"></div>
                                                            <div class="step <?php echo $booking['status'] === 'completed' ? 'active' : ''; ?>">
                                                                <div class="step-icon">
                                                                    <i class="bi bi-flag-checkered"></i>
                                                                </div>
                                                                <div class="step-label">Completed</div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td>
                                                    <span class="status-badge <?php echo strtolower($booking['status']); ?>">
                                                        <?php echo ucfirst($booking['status']); ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <div class="action-buttons">
                                                        <button class="btn btn-sm btn-outline-primary btn-icon view-booking" 
                                                                onclick="viewBooking(<?php echo $booking['id']; ?>)"
                                                                title="View Details">
                                                            <i class="bi bi-eye"></i>
                                                        </button>
                                                        <?php if ($booking['status'] === 'approved'): ?>
                                                            <button class="btn btn-sm btn-outline-success btn-icon download-booking"
                                                                    onclick="downloadBooking(<?php echo $booking['id']; ?>)"
                                                                    title="Download Booking Evidence">
                                                                <i class="bi bi-download"></i>
                                                            </button>
                                                        <?php endif; ?>
                                                        <?php if ($booking['status'] === 'pending'): ?>
                                                            <button class="btn btn-sm btn-outline-warning btn-icon edit-booking"
                                                                    data-booking-id="<?php echo $booking['id']; ?>"
                                                                    title="Edit Booking">
                                                                <i class="bi bi-pencil"></i>
                                                            </button>
                                                            <button class="btn btn-sm btn-outline-danger btn-icon cancel-booking"
                                                                    data-booking-id="<?php echo $booking['id']; ?>"
                                                                    title="Cancel Booking">
                                                                <i class="bi bi-x-circle"></i>
                                                            </button>
                                                        <?php endif; ?>
                                                        <?php if (in_array($booking['status'], ['completed', 'cancelled'])): ?>
                                                            <button class="btn btn-sm btn-outline-danger btn-icon delete-booking"
                                                                    data-booking-id="<?php echo $booking['id']; ?>"
                                                                    title="Delete Booking">
                                                                <i class="bi bi-trash"></i>
                                                            </button>
                                                        <?php endif; ?>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Mobile Booking Cards -->
                    <div class="mobile-bookings">
                        <?php foreach ($bookings as $booking): ?>
                            <div class="mobile-booking-card">
                                <div class="booking-header">
                                    <div>
                                        <div class="facility-name"><?php echo htmlspecialchars($booking['facility_name']); ?></div>
                                        <div class="booking-date"><?php echo date('M d, Y', strtotime($booking['event_date'])); ?></div>
                                    </div>
                                    <span class="status-badge <?php echo strtolower($booking['status']); ?>">
                                        <?php echo ucfirst($booking['status']); ?>
                                    </span>
                                </div>
                                
                                <div class="booking-details">
                                    <div class="detail-item">
                                        <span class="detail-label">Time:</span>
                                        <span class="detail-value">
                                            <?php 
                                            echo date('h:i A', strtotime($booking['start_time'])) . ' - ' . 
                                                 date('h:i A', strtotime($booking['end_time'])); 
                                            ?>
                                        </span>
                                    </div>
                                    <div class="detail-item">
                                        <span class="detail-label">Equipment:</span>
                                        <span class="detail-value"><?php echo htmlspecialchars($booking['equipment_details'] ?? 'None'); ?></span>
                                    </div>
                                </div>
                                
                                <div class="booking-actions">
                                    <button class="btn btn-sm btn-outline-primary view-booking" 
                                            onclick="viewBooking(<?php echo $booking['id']; ?>)">
                                        <i class="bi bi-eye me-1"></i>View
                                    </button>
                                    
                                    <?php if ($booking['status'] === 'approved'): ?>
                                        <button class="btn btn-sm btn-outline-success download-booking"
                                                onclick="downloadBooking(<?php echo $booking['id']; ?>)">
                                            <i class="bi bi-download me-1"></i>Download
                                        </button>
                                    <?php endif; ?>
                                    
                                    <?php if ($booking['status'] === 'pending'): ?>
                                        <button class="btn btn-sm btn-outline-warning edit-booking"
                                                data-booking-id="<?php echo $booking['id']; ?>">
                                            <i class="bi bi-pencil me-1"></i>Edit
                                        </button>
                                        <button class="btn btn-sm btn-outline-danger cancel-booking"
                                                data-booking-id="<?php echo $booking['id']; ?>">
                                            <i class="bi bi-x-circle me-1"></i>Cancel
                                        </button>
                                    <?php endif; ?>
                                    
                                    <?php if (in_array($booking['status'], ['completed', 'cancelled'])): ?>
                                        <button class="btn btn-sm btn-outline-danger delete-booking"
                                                data-booking-id="<?php echo $booking['id']; ?>">
                                            <i class="bi bi-trash me-1"></i>Delete
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Booking Details Modal -->
    <div class="modal fade" id="bookingDetailsModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="bi bi-calendar-check me-2"></i>Booking Details
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <!-- Status Section -->
                    <div class="status-section mb-4">
                        <div class="status-badge" id="modalStatus">Loading...</div>
                        <div class="status-description" id="statusDescription">Loading status information...</div>
                    </div>
                    
                    <!-- Booking Information -->
                    <div class="booking-info">
                        <div class="info-row">
                            <div class="info-label">Facility:</div>
                            <div class="info-value" id="modalFacility">Loading...</div>
                        </div>
                        
                        <div class="info-row">
                            <div class="info-label">Event Date:</div>
                            <div class="info-value" id="modalDate">Loading...</div>
                        </div>
                        
                        <div class="info-row">
                            <div class="info-label">Time:</div>
                            <div class="info-value" id="modalTime">Loading...</div>
                        </div>
                        
                        <div class="info-row">
                            <div class="info-label">Duration:</div>
                            <div class="info-value" id="modalDuration">Loading...</div>
                        </div>
                        
                        <div class="info-row">
                            <div class="info-label">Equipment:</div>
                            <div class="info-value" id="modalEquipment">Loading...</div>
                        </div>
                        
                        <div class="info-row">
                            <div class="info-label">Request Letter:</div>
                            <div class="info-value" id="modalRequestLetter">Loading...</div>
                        </div>
                        
                        <div class="info-row">
                            <div class="info-label">Created:</div>
                            <div class="info-value" id="modalCreatedAt">Loading...</div>
                        </div>
                        
                        <div class="info-row">
                            <div class="info-label">Last Updated:</div>
                            <div class="info-value" id="modalUpdatedAt">Loading...</div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Cancel Confirmation Modal -->
    <div class="modal fade" id="cancelModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header border-0">
                    <h5 class="modal-title">Cancel Booking</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body text-center py-4">
                    <i class="bi bi-exclamation-circle text-warning display-4 mb-3"></i>
                    <h5 class="mb-3">Cancel this booking?</h5>
                    <p class="text-muted mb-0">This action cannot be undone. Are you sure you want to cancel this booking?</p>
                </div>
                <div class="modal-footer border-0 justify-content-center">
                    <button type="button" class="btn btn-light px-4" data-bs-dismiss="modal">No, Keep it</button>
                    <button type="button" class="btn btn-warning px-4" id="confirmCancel">Yes, Cancel it</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div class="modal fade" id="deleteModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header border-0">
                    <h5 class="modal-title">Delete Booking</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body text-center py-4">
                    <i class="bi bi-exclamation-triangle text-danger display-4 mb-3"></i>
                    <h5 class="mb-3">Delete this booking?</h5>
                    <p class="text-muted mb-0">This action cannot be undone. Are you sure you want to delete this booking?</p>
                </div>
                <div class="modal-footer border-0 justify-content-center">
                    <button type="button" class="btn btn-light px-4" data-bs-dismiss="modal">No, Keep it</button>
                    <button type="button" class="btn btn-danger px-4" id="confirmDelete">Yes, Delete it</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Edit Booking Modal -->
    <div class="modal fade" id="editBookingModal" tabindex="-1">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Edit Booking</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="editBookingForm">
                        <input type="hidden" id="editBookingId" name="booking_id">
                        
                        <!-- Facility Selection -->
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="editFacility" class="form-label">Facility *</label>
                                <select class="form-select" id="editFacility" name="facility_id" required>
                                    <option value="">Select a facility</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label for="editEventDate" class="form-label">Event Date *</label>
                                <div class="custom-date-picker">
                                    <input type="text" class="form-control" id="editEventDateDisplay" readonly placeholder="Select a date">
                                    <input type="hidden" id="editEventDate" name="event_date" required>
                                    <div class="date-picker-calendar" id="editDatePickerCalendar" style="display: none;">
                                        <div class="calendar-header">
                                            <button type="button" class="btn btn-sm btn-link" id="editPrevMonth">
                                                <i class="bi bi-chevron-left"></i>
                                            </button>
                                            <span id="editCurrentMonthYear"></span>
                                            <button type="button" class="btn btn-sm btn-link" id="editNextMonth">
                                                <i class="bi bi-chevron-right"></i>
                                            </button>
                                        </div>
                                        <div class="calendar-weekdays">
                                            <div>Sun</div>
                                            <div>Mon</div>
                                            <div>Tue</div>
                                            <div>Wed</div>
                                            <div>Thu</div>
                                            <div>Fri</div>
                                            <div>Sat</div>
                                        </div>
                                        <div class="calendar-days" id="editCalendarDays"></div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Time Selection -->
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="editStartTime" class="form-label">Start Time *</label>
                                <input type="time" class="form-control" id="editStartTime" name="start_time" required>
                            </div>
                            <div class="col-md-6">
                                <label for="editEndTime" class="form-label">End Time *</label>
                                <input type="time" class="form-control" id="editEndTime" name="end_time" required>
                            </div>
                        </div>

                        <!-- Equipment Selection -->
                        <div class="mb-3">
                            <label class="form-label">Equipment</label>
                            <div id="editEquipmentList" class="row">
                                <!-- Equipment items will be loaded here -->
                            </div>
                        </div>

                        <!-- Request Letter -->
                        <div class="mb-3">
                            <label for="editRequestLetter" class="form-label">Request Letter (PDF)</label>
                            <input type="file" class="form-control" id="editRequestLetter" name="request_letter" accept=".pdf">
                            <div class="form-text">Upload a new request letter or leave empty to keep the current one</div>
                            <div id="currentRequestLetter" class="mt-2"></div>
                        </div>

                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-primary">Update Booking</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // View booking details
        function viewBooking(id) {
            console.log('Fetching booking details for ID:', id);
            
            // First, let's check if all modal elements exist
            const elements = {
                facility: document.getElementById('modalFacility'),
                date: document.getElementById('modalDate'),
                time: document.getElementById('modalTime'),
                duration: document.getElementById('modalDuration'),
                equipment: document.getElementById('modalEquipment'),
                requestLetter: document.getElementById('modalRequestLetter'),
                createdAt: document.getElementById('modalCreatedAt'),
                updatedAt: document.getElementById('modalUpdatedAt'),
                status: document.getElementById('modalStatus'),
                statusDescription: document.getElementById('statusDescription')
            };
            
            console.log('Modal elements found:', elements);
            
            // Set loading state
            Object.values(elements).forEach(el => {
                if (el) el.textContent = 'Loading...';
            });
            
            fetch(`get_booking_details.php?id=${id}`)
                .then(response => {
                    console.log('Response status:', response.status);
                    if (!response.ok) {
                        throw new Error(`HTTP error! status: ${response.status}`);
                    }
                    return response.json();
                })
                .then(data => {
                    console.log('Booking data received:', data);
                    
                    // Format date and time
                    const startDate = new Date(data.start_time);
                    const endDate = new Date(data.end_time);
                    const createdDate = new Date(data.created_at);
                    const updatedDate = data.updated_at ? new Date(data.updated_at) : createdDate;

                    // Format date as "Month Day, Year"
                    const formatDate = (date) => {
                        return date.toLocaleDateString('en-US', {
                            year: 'numeric',
                            month: 'long',
                            day: 'numeric'
                        });
                    };

                    // Format time as "HH:MM AM/PM"
                    const formatTime = (date) => {
                        return date.toLocaleTimeString('en-US', {
                            hour: '2-digit',
                            minute: '2-digit',
                            hour12: true
                        });
                    };

                    // Calculate duration
                    const durationMs = endDate - startDate;
                    const durationHours = Math.floor(durationMs / (1000 * 60 * 60));
                    const durationMinutes = Math.floor((durationMs % (1000 * 60 * 60)) / (1000 * 60));
                    const durationText = `${durationHours}h ${durationMinutes}m`;

                    // Update status
                    if (elements.status && elements.statusDescription) {
                        const status = data.status ? data.status.toLowerCase() : 'unknown';
                        const statusText = data.status ? data.status.charAt(0).toUpperCase() + data.status.slice(1) : 'Unknown';
                        
                        elements.status.textContent = statusText;
                        elements.status.className = `status-badge ${status}`;
                        
                        const statusDescriptions = {
                            pending: 'Your booking is waiting for admin approval',
                            approved: 'Your booking has been approved and confirmed',
                            rejected: 'Your booking request has been rejected',
                            completed: 'Your booking has been completed successfully',
                            cancelled: 'This booking has been cancelled'
                        };
                        
                        elements.statusDescription.textContent = statusDescriptions[status] || 'Status information unavailable';
                    }

                    // Update modal content
                    if (elements.facility) {
                        elements.facility.textContent = data.facility_name || 'N/A';
                        console.log('Set facility:', data.facility_name);
                    }

                    if (elements.date) {
                        elements.date.textContent = formatDate(startDate);
                        console.log('Set date:', formatDate(startDate));
                    }

                    if (elements.time) {
                        elements.time.textContent = `${formatTime(startDate)} - ${formatTime(endDate)}`;
                        console.log('Set time:', `${formatTime(startDate)} - ${formatTime(endDate)}`);
                    }

                    if (elements.duration) {
                        elements.duration.textContent = durationText;
                        console.log('Set duration:', durationText);
                    }
                    
                    if (elements.equipment) {
                        if (data.equipment_details && data.equipment_details.trim() !== '') {
                            const equipmentItems = data.equipment_details.split(', ');
                            elements.equipment.innerHTML = `
                                <div class="equipment-list">
                                    ${equipmentItems.map(item => {
                                        const [name, quantity] = item.split(' (');
                                        const qty = quantity ? quantity.replace(')', '') : '1';
                                        return `<div class="equipment-item">${name} (${qty})</div>`;
                                    }).join('')}
                                </div>
                            `;
                            console.log('Set equipment:', data.equipment_details);
                        } else {
                            elements.equipment.textContent = 'No equipment selected';
                            console.log('Set equipment: No equipment selected');
                        }
                    }

                    if (elements.requestLetter) {
                        if (data.request_letter && data.request_letter.trim() !== '') {
                            elements.requestLetter.innerHTML = `
                                <a href="../uploads/${data.request_letter}" target="_blank" class="request-letter-link">
                                    View Request Letter
                                </a>
                            `;
                            console.log('Set request letter: Link to', data.request_letter);
                        } else {
                            elements.requestLetter.textContent = 'No request letter uploaded';
                            console.log('Set request letter: No request letter uploaded');
                        }
                    }

                    if (elements.createdAt) {
                        elements.createdAt.textContent = `${formatDate(createdDate)} ${formatTime(createdDate)}`;
                        console.log('Set created at:', `${formatDate(createdDate)} ${formatTime(createdDate)}`);
                    }

                    if (elements.updatedAt) {
                        elements.updatedAt.textContent = `${formatDate(updatedDate)} ${formatTime(updatedDate)}`;
                        console.log('Set updated at:', `${formatDate(updatedDate)} ${formatTime(updatedDate)}`);
                    }

                    // Show modal
                    const modal = new bootstrap.Modal(document.getElementById('bookingDetailsModal'));
                    modal.show();
                })
                .catch(error => {
                    console.error('Error loading booking details:', error);
                    
                    // Set error state
                    Object.values(elements).forEach(el => {
                        if (el) el.textContent = 'Error loading data';
                    });
                    
                    alert('Error loading booking details: ' + error.message);
                });
        }

        // Download booking certificate
        function downloadBooking(id) {
            console.log('Generating PNG certificate for ID:', id);
            
            // Show loading indicator
            const downloadBtn = event.target.closest('.download-booking');
            const originalContent = downloadBtn.innerHTML;
            downloadBtn.innerHTML = '<i class="bi bi-hourglass-split"></i>';
            downloadBtn.disabled = true;
            
            // Open certificate generation in new window
            const certificateWindow = window.open(`download_booking.php?booking_id=${id}`, '_blank', 'width=900,height=700,scrollbars=yes,resizable=yes');
            
            // Reset button after a delay
            setTimeout(() => {
                downloadBtn.innerHTML = originalContent;
                downloadBtn.disabled = false;
            }, 3000);
            
            // Focus on the new window
            if (certificateWindow) {
                certificateWindow.focus();
            }
        }

        // Cancel Booking
        let bookingToCancel = null;
        const cancelModal = new bootstrap.Modal(document.getElementById('cancelModal'));
        
        document.querySelectorAll('.cancel-booking').forEach(button => {
            button.addEventListener('click', function() {
                bookingToCancel = this.dataset.bookingId;
                cancelModal.show();
            });
        });

        document.getElementById('confirmCancel').addEventListener('click', function() {
            if (bookingToCancel) {
                fetch('cancel_booking.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({ booking_id: bookingToCancel })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        location.reload();
                    } else {
                        alert(data.message || 'Failed to cancel booking');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('An error occurred while cancelling the booking');
                });
            }
            cancelModal.hide();
        });

        // Delete Booking
        let bookingToDelete = null;
        const deleteModal = new bootstrap.Modal(document.getElementById('deleteModal'));
        
        document.querySelectorAll('.delete-booking').forEach(button => {
            button.addEventListener('click', function() {
                bookingToDelete = this.dataset.bookingId;
                deleteModal.show();
            });
        });

        document.getElementById('confirmDelete').addEventListener('click', function() {
            if (bookingToDelete) {
                fetch('delete_booking.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({ booking_id: bookingToDelete })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        location.reload();
                    } else {
                        alert(data.message || 'Failed to delete booking');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('An error occurred while deleting the booking');
                });
            }
            deleteModal.hide();
        });

        // Edit Booking
        document.querySelectorAll('.edit-booking').forEach(button => {
            button.addEventListener('click', function() {
                const bookingId = this.dataset.bookingId;
                editBooking(bookingId);
            });
        });

        // Edit booking function
        function editBooking(id) {
            console.log('Editing booking ID:', id);
            
            // Load booking details
            fetch(`get_booking_details.php?id=${id}`)
                .then(response => response.json())
                .then(data => {
                    console.log('Booking data for editing:', data);
                    
                    // Set booking ID
                    document.getElementById('editBookingId').value = id;
                    
                    // Load facilities
                    loadFacilities(data.facility_id);
                    
                    // Set date and time
                    const startDate = new Date(data.start_time);
                    const displayString = startDate.toLocaleDateString('en-US', {
                        year: 'numeric',
                        month: 'long',
                        day: 'numeric'
                    });
                    
                    document.getElementById('editEventDateDisplay').value = displayString;
                    document.getElementById('editEventDate').value = startDate.toISOString().split('T')[0];
                    document.getElementById('editStartTime').value = startDate.toTimeString().slice(0, 5);
                    
                    const endDate = new Date(data.end_time);
                    document.getElementById('editEndTime').value = endDate.toTimeString().slice(0, 5);
                    
                    // Load equipment
                    loadEquipment(data.equipment_details);
                    
                    // Show current request letter
                    const currentRequestLetter = document.getElementById('currentRequestLetter');
                    if (data.request_letter && data.request_letter.trim() !== '') {
                        currentRequestLetter.innerHTML = `
                            <small class="text-muted">
                                <i class="bi bi-file-earmark-text"></i> 
                                Current: <a href="../uploads/${data.request_letter}" target="_blank">${data.request_letter}</a>
                            </small>
                        `;
                    } else {
                        currentRequestLetter.innerHTML = '<small class="text-muted">No request letter uploaded</small>';
                    }
                    
                    // Setup double booking prevention for edit form
                    setupEditDatePicker(id, startDate);
                    
                    // Show modal
                    const modal = new bootstrap.Modal(document.getElementById('editBookingModal'));
                    modal.show();
                })
                .catch(error => {
                    console.error('Error loading booking for editing:', error);
                    alert('Error loading booking details for editing');
                });
        }
        
        // Setup double booking prevention for edit form
        function setupEditDatePicker(currentBookingId, currentBookingDate) {
            let editCurrentDate = new Date(currentBookingDate);
            let editSelectedDate = new Date(currentBookingDate);
            let editBookedDates = [];
            
            // Load booked dates excluding current booking
            fetch(`get_booked_dates.php?exclude_booking_id=${currentBookingId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        editBookedDates = data.booked_dates;
                        
                        // Setup edit calendar
                        setupEditCalendar();
                        
                        // Store data for form validation
                        window.editBookedDates = editBookedDates;
                        window.currentEditBookingId = currentBookingId;
                        window.editCurrentDate = editCurrentDate;
                        window.editSelectedDate = editSelectedDate;
                    }
                })
                .catch(error => {
                    console.error('Error loading booked dates for edit:', error);
                });
            
            // Setup edit calendar functionality
            function setupEditCalendar() {
                const displayInput = document.getElementById('editEventDateDisplay');
                const hiddenInput = document.getElementById('editEventDate');
                const calendar = document.getElementById('editDatePickerCalendar');
                
                // Toggle calendar on input click
                displayInput.addEventListener('click', function() {
                    calendar.style.display = calendar.style.display === 'none' ? 'block' : 'none';
                    renderEditCalendar();
                });
                
                // Close calendar when clicking outside
                document.addEventListener('click', function(e) {
                    if (!e.target.closest('.custom-date-picker')) {
                        calendar.style.display = 'none';
                    }
                });
                
                // Navigation buttons
                document.getElementById('editPrevMonth').addEventListener('click', function() {
                    editCurrentDate.setMonth(editCurrentDate.getMonth() - 1);
                    renderEditCalendar();
                });
                
                document.getElementById('editNextMonth').addEventListener('click', function() {
                    editCurrentDate.setMonth(editCurrentDate.getMonth() + 1);
                    renderEditCalendar();
                });
            }
            
            // Render edit calendar
            function renderEditCalendar() {
                const year = editCurrentDate.getFullYear();
                const month = editCurrentDate.getMonth();
                
                // Update header
                const monthNames = ['January', 'February', 'March', 'April', 'May', 'June',
                                   'July', 'August', 'September', 'October', 'November', 'December'];
                document.getElementById('editCurrentMonthYear').textContent = `${monthNames[month]} ${year}`;
                
                // Get first day of month and number of days
                const firstDay = new Date(year, month, 1).getDay();
                const daysInMonth = new Date(year, month + 1, 0).getDate();
                const today = new Date();
                
                // Clear previous calendar
                const calendarDays = document.getElementById('editCalendarDays');
                calendarDays.innerHTML = '';
                
                // Add empty cells for days before first day of month
                for (let i = 0; i < firstDay; i++) {
                    const dayElement = document.createElement('div');
                    dayElement.className = 'calendar-day other-month';
                    dayElement.textContent = '';
                    calendarDays.appendChild(dayElement);
                }
                
                // Add days of month
                for (let day = 1; day <= daysInMonth; day++) {
                    const dayElement = document.createElement('div');
                    dayElement.className = 'calendar-day';
                    dayElement.textContent = day;
                    
                    const currentDayDate = new Date(year, month, day);
                    const dateString = currentDayDate.toISOString().split('T')[0];
                    
                    // Check if it's today
                    if (currentDayDate.toDateString() === today.toDateString()) {
                        dayElement.classList.add('today');
                    }
                    
                    // Check if it's selected
                    if (editSelectedDate && editSelectedDate.toDateString() === currentDayDate.toDateString()) {
                        dayElement.classList.add('selected');
                    }
                    
                    // Check if it's booked
                    if (editBookedDates.includes(dateString)) {
                        dayElement.classList.add('booked');
                    }
                    
                    // Check if it's in the past
                    if (currentDayDate < new Date(today.getFullYear(), today.getMonth(), today.getDate())) {
                        dayElement.classList.add('disabled');
                    }
                    
                    // Add click event
                    if (!dayElement.classList.contains('disabled') && !dayElement.classList.contains('booked')) {
                        dayElement.addEventListener('click', function() {
                            selectEditDate(currentDayDate);
                        });
                    }
                    
                    calendarDays.appendChild(dayElement);
                }
            }
            
            // Select edit date
            function selectEditDate(date) {
                editSelectedDate = date;
                const dateString = date.toISOString().split('T')[0];
                const displayString = date.toLocaleDateString('en-US', {
                    year: 'numeric',
                    month: 'long',
                    day: 'numeric'
                });
                
                document.getElementById('editEventDateDisplay').value = displayString;
                document.getElementById('editEventDate').value = dateString;
                document.getElementById('editDatePickerCalendar').style.display = 'none';
                
                renderEditCalendar();
            }
            
            // Initial render
            renderEditCalendar();
        }

        // Load facilities
        function loadFacilities(selectedFacilityId = null) {
            fetch('get_facilities.php')
                .then(response => response.json())
                .then(facilities => {
                    const select = document.getElementById('editFacility');
                    select.innerHTML = '<option value="">Select a facility</option>';
                    
                    facilities.forEach(facility => {
                        const option = document.createElement('option');
                        option.value = facility.id;
                        option.textContent = facility.name;
                        if (selectedFacilityId && facility.id == selectedFacilityId) {
                            option.selected = true;
                        }
                        select.appendChild(option);
                    });
                })
                .catch(error => {
                    console.error('Error loading facilities:', error);
                });
        }

        // Load equipment
        function loadEquipment(currentEquipment = '') {
            fetch('get_equipment.php')
                .then(response => response.json())
                .then(equipment => {
                    const container = document.getElementById('editEquipmentList');
                    container.innerHTML = '';
                    
                    // Parse current equipment
                    const currentItems = {};
                    if (currentEquipment) {
                        currentEquipment.split(', ').forEach(item => {
                            const [name, quantity] = item.split(' (');
                            const qty = quantity ? quantity.replace(')', '') : '1';
                            currentItems[name] = parseInt(qty);
                        });
                    }
                    
                    equipment.forEach(item => {
                        const col = document.createElement('div');
                        col.className = 'col-md-6 col-lg-4 mb-3';
                        
                        if (item.is_group && item.name === 'Sound System') {
                            // Handle sound system group with Yes/No choice
                            const hasSoundSystem = currentItems[item.name] > 0;
                            
                            col.innerHTML = `
                                <div class="card h-100">
                                    <div class="card-body">
                                        <h6 class="card-title">${item.name}</h6>
                                        <p class="card-text small text-muted">${item.description}</p>
                                        <div class="form-check">
                                            <input class="form-check-input" 
                                                   type="checkbox" 
                                                   name="equipment_sound_system" 
                                                   id="soundSystemCheck"
                                                   value="1"
                                                   ${hasSoundSystem ? 'checked' : ''}>
                                            <label class="form-check-label" for="soundSystemCheck">
                                                Yes, I need Sound System
                                            </label>
                                        </div>
                                        <small class="text-muted">Includes: Speaker, Mixer, Amplifier, Cables, Microphone</small>
                                    </div>
                                </div>
                            `;
                        } else {
                            // Handle regular equipment
                            const currentQty = currentItems[item.name] || 0;
                            const maxQty = item.available + currentQty;
                            
                            col.innerHTML = `
                                <div class="card h-100">
                                    <div class="card-body">
                                        <h6 class="card-title">${item.name}</h6>
                                        <p class="card-text small text-muted">Available: ${item.available}</p>
                                        <div class="input-group">
                                            <input type="number" 
                                                   class="form-control" 
                                                   name="equipment_${item.id}" 
                                                   value="${currentQty}"
                                                   min="0" 
                                                   max="${maxQty}"
                                                   ${item.available === 0 && currentQty === 0 ? 'disabled' : ''}>
                                            <span class="input-group-text">units</span>
                                        </div>
                                    </div>
                                </div>
                            `;
                        }
                        container.appendChild(col);
                    });
                })
                .catch(error => {
                    console.error('Error loading equipment:', error);
                });
        }

        // Handle edit form submission
        document.getElementById('editBookingForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            const selectedDate = formData.get('event_date');
            
            // Validate that selected date is not in the past
            const today = new Date().toISOString().split('T')[0];
            if (selectedDate < today) {
                alert('Please select a future date.');
                return;
            }
            
            // Submit the form
            submitEditForm(formData);
        });
        
        // Helper function to submit edit form
        function submitEditForm(formData) {
            fetch('update_booking.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Booking updated successfully!');
                    location.reload(); // Refresh the page to show updated data
                } else {
                    alert(data.message || 'Error updating booking');
                }
            })
            .catch(error => {
                console.error('Error updating booking:', error);
                alert('Error updating booking');
            });
        }
        
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
    </script>
</body>
</html> 