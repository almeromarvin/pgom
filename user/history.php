<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'user') {
    header('Location: ../login.php');
    exit();
}

$user_id = $_SESSION['user_id'];

// Fetch user's booking history (active bookings)
$sql = "SELECT b.*, f.name as facility_name, f.description as facility_description
        FROM bookings b
        JOIN facilities f ON b.facility_id = f.id
        WHERE b.user_id = ?
        ORDER BY b.created_at DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute([$user_id]);
$active_bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch deleted bookings from user_history (only basic info since details column doesn't exist)
$sql = "SELECT h.*
        FROM user_history h
        WHERE h.user_id = ? AND h.action_type = 'booking_deleted'
        ORDER BY h.created_at DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute([$user_id]);
$deleted_bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Combine and sort all history
$all_history = [];

// Add active bookings
foreach ($active_bookings as $booking) {
    $all_history[] = [
        'type' => 'active',
        'data' => $booking,
        'timestamp' => strtotime($booking['created_at'])
    ];
}

// Add deleted bookings (with basic info only)
foreach ($deleted_bookings as $history) {
    $all_history[] = [
        'type' => 'deleted',
        'data' => [
            'id' => 'Deleted',
            'facility_name' => 'Deleted Booking',
            'start_time' => 'N/A',
            'end_time' => 'N/A',
            'status' => 'deleted',
            'created_at' => $history['created_at'],
            'deleted_at' => $history['created_at']
        ],
        'timestamp' => strtotime($history['created_at'])
    ];
}

// Sort by timestamp (newest first)
usort($all_history, function($a, $b) {
    return $b['timestamp'] - $a['timestamp'];
});
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Booking History - PGOM Facilities</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css">
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
            
            .history-table {
                padding: 1rem;
                margin-top: 1rem;
            }
            
            .history-table h2 {
                font-size: 1.25rem;
                margin-bottom: 1rem;
            }
            
            .table-responsive {
                font-size: 0.875rem;
            }
            
            .table th,
            .table td {
                padding: 0.5rem 0.25rem;
            }
            
            .facility-badge .badge {
                width: 28px;
                height: 28px;
                font-size: 1rem;
            }
            
            .facility-badge .type {
                font-size: 0.75rem;
            }
            
            .status-badge {
                font-size: 0.75rem;
                padding: 0.25rem 0.5rem;
            }
            
            .container-fluid {
                padding-left: 1rem !important;
                padding-right: 1rem !important;
            }
            
            /* Mobile card layout */
            .mobile-history-card {
                display: block;
                background: white;
                border: 1px solid #dee2e6;
                border-radius: 8px;
                padding: 1rem;
                margin-bottom: 1rem;
                box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            }
            
            .mobile-history-card .history-header {
                display: flex;
                justify-content: space-between;
                align-items: flex-start;
                margin-bottom: 0.75rem;
                padding-bottom: 0.75rem;
                border-bottom: 1px solid #f0f0f0;
            }
            
            .mobile-history-card .facility-info {
                display: flex;
                align-items: center;
                gap: 0.5rem;
            }
            
            .mobile-history-card .facility-badge {
                width: 24px;
                height: 24px;
                font-size: 0.9rem;
            }
            
            .mobile-history-card .facility-name {
                font-weight: 600;
                color: #2c3e50;
                font-size: 0.9rem;
            }
            
            .mobile-history-card .facility-type {
                color: #6c757d;
                font-size: 0.75rem;
            }
            
            .mobile-history-card .history-details {
                margin-bottom: 0.75rem;
            }
            
            .mobile-history-card .detail-item {
                display: flex;
                justify-content: space-between;
                margin-bottom: 0.5rem;
                font-size: 0.8rem;
            }
            
            .mobile-history-card .detail-label {
                color: #6c757d;
                font-weight: 500;
            }
            
            .mobile-history-card .detail-value {
                color: #2c3e50;
                text-align: right;
            }
            
            .mobile-history-card.deleted {
                opacity: 0.7;
                background-color: #f8f9fa;
            }
            
            .mobile-history-card.deleted .facility-badge {
                background: #dc3545;
                color: white;
            }
        }
        
        @media (max-width: 576px) {
            .history-table {
                padding: 0.75rem;
            }
            
            .history-table h2 {
                font-size: 1.1rem;
                margin-bottom: 0.75rem;
            }
            
            .mobile-history-card {
                padding: 0.75rem;
            }
            
            .mobile-history-card .facility-name {
                font-size: 0.85rem;
            }
            
            .mobile-history-card .facility-type {
                font-size: 0.7rem;
            }
            
            .mobile-history-card .detail-item {
                font-size: 0.75rem;
            }
            
            .status-badge {
                font-size: 0.7rem;
                padding: 0.2rem 0.4rem;
            }
        }
        
        @media (max-width: 480px) {
            .header-title {
                font-size: 1rem !important;
            }
            
            .mobile-history-card .history-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 0.5rem;
            }
            
            .mobile-history-card .facility-info {
                width: 100%;
            }
            
            .mobile-history-card .detail-item {
                flex-direction: column;
                align-items: flex-start;
                gap: 0.25rem;
            }
            
            .mobile-history-card .detail-value {
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
            
            .mobile-history {
                display: block;
            }
        }
        
        @media (min-width: 769px) {
            .mobile-history {
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
        
        .history-table {
            background: white;
            border-radius: 10px;
            box-shadow: 0 0 20px rgba(0,0,0,0.08);
            padding: 2rem;
            margin-top: 2rem;
        }
        .history-table h2 {
            color: #2c3e50;
            margin-bottom: 1.5rem;
            font-weight: 600;
        }
        .table th {
            background-color: #f8f9fa;
            border-bottom: 2px solid #dee2e6;
            color: #495057;
            font-weight: 600;
        }
        .table td {
            vertical-align: middle;
            color: #2c3e50;
        }
        .facility-badge {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        .facility-badge .badge {
            border-radius: 50%;
            width: 32px;
            height: 32px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.1rem;
            background: #e9ecef;
            color: #198754;
            font-weight: 600;
        }
        .facility-badge .type {
            font-size: 0.85rem;
            color: #6c757d;
            text-transform: lowercase;
        }
        .status-badge {
            padding: 0.375rem 0.75rem;
            border-radius: 0.375rem;
            font-size: 0.875rem;
            font-weight: 500;
        }
        .status-pending { background: #fff3cd; color: #856404; }
        .status-approved { background: #d1e7dd; color: #0f5132; }
        .status-rejected { background: #f8d7da; color: #721c24; }
        .status-completed { background: #cff4fc; color: #055160; }
        .status-cancelled { background: #e2e3e5; color: #383d41; }
        .status-deleted { background: #f8d7da; color: #721c24; }
        .no-history {
            text-align: center;
            padding: 3rem;
            color: #6c757d;
        }
        .no-history i {
            font-size: 3rem;
            margin-bottom: 1rem;
            color: #dee2e6;
        }
        .no-history h4 {
            color: #495057;
            margin-bottom: 0.5rem;
        }
        .deleted-row {
            opacity: 0.7;
            background-color: #f8f9fa;
        }
        .deleted-row .facility-badge .badge {
            background: #dc3545;
            color: white;
        }
        .action-badge {
            font-size: 0.75rem;
            padding: 0.25rem 0.5rem;
            margin-left: 0.5rem;
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
                <a href="my_bookings.php" class="list-group-item list-group-item-action">
                    <i class="bi bi-calendar-check"></i> My Bookings
                </a>
                <a href="history.php" class="list-group-item list-group-item-action active">
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
                        <i class="bi bi-clock-history me-2"></i>
                        My Booking History
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
            <div class="container-fluid py-4 px-4">
                <div class="history-table">
                    <h2 class="mb-4">My Booking History</h2>
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>DATE & TIME</th>
                                    <th>FACILITY</th>
                                    <th>BOOKING PERIOD</th>
                                    <th>STATUS</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php if (count($all_history) > 0): ?>
                                <?php foreach ($all_history as $item): ?>
                                    <?php 
                                    $row = $item['data'];
                                    $isDeleted = $item['type'] === 'deleted';
                                    $rowClass = $isDeleted ? 'deleted-row' : '';
                                    ?>
                                    <tr class="<?php echo $rowClass; ?>">
                                        <td>
                                            <?php echo date('M d, Y h:i A', strtotime($isDeleted ? $row['created_at'] : $row['created_at'])); ?>
                                            <?php if ($isDeleted): ?>
                                                <span class="badge bg-danger action-badge">DELETED</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div class="facility-badge">
                                                <span class="badge">
                                                    <?php echo strtoupper(substr($row['facility_name'], 0, 1)); ?>
                                                </span>
                                                <div>
                                                    <div><?php echo htmlspecialchars($row['facility_name']); ?></div>
                                                    <div class="type"><?php echo htmlspecialchars($row['facility_description']); ?></div>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <div>
                                                <div><strong><?php echo date('M d, Y', strtotime($row['start_time'])); ?></strong></div>
                                                <div class="text-muted">
                                                    <?php 
                                                    if ($row['start_time'] !== 'N/A' && $row['end_time'] !== 'N/A' && strtotime($row['start_time']) && strtotime($row['end_time'])) {
                                                        $start = strtotime($row['start_time']);
                                                        $end = strtotime($row['end_time']);
                                                        $diff = $end - $start;
                                                        $hours = floor($diff / 3600);
                                                        $minutes = floor(($diff % 3600) / 60);
                                                        $duration = ($hours > 0 ? $hours . ' hr' . ($hours > 1 ? 's' : '') : '') . ($hours > 0 && $minutes > 0 ? ' ' : '') . ($minutes > 0 ? $minutes . ' min' . ($minutes > 1 ? 's' : '') : '');
                                                        echo date('h:i A', $start) . ' - ' . date('h:i A', $end) . ' (' . ($duration ? $duration : '0 min') . ')';
                                                    } else {
                                                        echo 'N/A';
                                                    }
                                                    ?>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <?php if ($isDeleted): ?>
                                                <span class="status-badge status-deleted">
                                                    Deleted
                                                </span>
                                            <?php else: ?>
                                                <span class="status-badge status-<?php echo $row['status']; ?>">
                                                    <?php echo ucfirst($row['status']); ?>
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="4" class="no-history">
                                        <i class="bi bi-clock-history"></i>
                                        <h4>No Booking History</h4>
                                        <p>You have no booking history yet.</p>
                                    </td>
                                </tr>
                            <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                
                <!-- Mobile History Cards -->
                <div class="mobile-history">
                    <?php if (count($all_history) > 0): ?>
                        <?php foreach ($all_history as $item): ?>
                            <?php 
                            $row = $item['data'];
                            $isDeleted = $item['type'] === 'deleted';
                            $cardClass = $isDeleted ? 'mobile-history-card deleted' : 'mobile-history-card';
                            ?>
                            <div class="<?php echo $cardClass; ?>">
                                <div class="history-header">
                                    <div class="facility-info">
                                        <span class="badge facility-badge">
                                            <?php echo strtoupper(substr($row['facility_name'], 0, 1)); ?>
                                        </span>
                                        <div>
                                            <div class="facility-name"><?php echo htmlspecialchars($row['facility_name']); ?></div>
                                            <div class="facility-type"><?php echo htmlspecialchars($row['facility_description']); ?></div>
                                        </div>
                                    </div>
                                    <div>
                                        <?php if ($isDeleted): ?>
                                            <span class="status-badge status-deleted">Deleted</span>
                                        <?php else: ?>
                                            <span class="status-badge status-<?php echo $row['status']; ?>">
                                                <?php echo ucfirst($row['status']); ?>
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                
                                <div class="history-details">
                                    <div class="detail-item">
                                        <span class="detail-label">Created:</span>
                                        <span class="detail-value">
                                            <?php echo date('M d, Y h:i A', strtotime($isDeleted ? $row['created_at'] : $row['created_at'])); ?>
                                        </span>
                                    </div>
                                    <div class="detail-item">
                                        <span class="detail-label">Date:</span>
                                        <span class="detail-value">
                                            <?php echo date('M d, Y', strtotime($row['start_time'])); ?>
                                        </span>
                                    </div>
                                    <div class="detail-item">
                                        <span class="detail-label">Booking Period:</span>
                                        <span class="detail-value">
                                            <?php 
                                            if ($row['start_time'] !== 'N/A' && $row['end_time'] !== 'N/A' && strtotime($row['start_time']) && strtotime($row['end_time'])) {
                                                $start = strtotime($row['start_time']);
                                                $end = strtotime($row['end_time']);
                                                $diff = $end - $start;
                                                $hours = floor($diff / 3600);
                                                $minutes = floor(($diff % 3600) / 60);
                                                $duration = ($hours > 0 ? $hours . ' hr' . ($hours > 1 ? 's' : '') : '') . ($hours > 0 && $minutes > 0 ? ' ' : '') . ($minutes > 0 ? $minutes . ' min' . ($minutes > 1 ? 's' : '') : '');
                                                echo date('h:i A', $start) . ' - ' . date('h:i A', $end) . ' (' . ($duration ? $duration : '0 min') . ')';
                                            } else {
                                                echo 'N/A';
                                            }
                                            ?>
                                        </span>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="text-center py-5">
                            <i class="bi bi-clock-history display-1 text-muted"></i>
                            <h4 class="mt-3">No Booking History</h4>
                            <p class="text-muted">You have no booking history yet.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

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
</script>
</body>
</html> 