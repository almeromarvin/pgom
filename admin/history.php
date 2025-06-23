<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../login.php');
    exit();
}

// Fetch user action history with admin info
$stmt = $pdo->query("SELECT 
    h.id,
    h.created_at,
    h.action_type,
    h.user_id,
    h.admin_id,
    u.name as affected_user,
    u.username as affected_username,
    a.name as admin_name,
    a.username as admin_username
    FROM user_history h
    JOIN users u ON h.user_id = u.id
    JOIN users a ON h.admin_id = a.id
    ORDER BY h.created_at DESC");
$history = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>History - PGOM Facilities</title>
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
            
            .history-card {
                margin-bottom: 1rem;
            }
            
            .history-header {
                padding: 0.75rem 1rem;
            }
            
            .history-title {
                font-size: 1.1rem;
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
            
            /* Mobile card layout for history */
            .mobile-history-card {
                display: block;
                background: #fff;
                border: 1px solid #e9ecef;
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
                border-bottom: 1px solid #e9ecef;
            }
            
            .mobile-history-card .action-info {
                display: flex;
                align-items: center;
                gap: 0.5rem;
            }
            
            .mobile-history-card .action-icon {
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
            
            .mobile-history-card .action-type {
                font-weight: 600;
                color: #2c3e50;
                font-size: 0.9rem;
            }
            
            .mobile-history-card .action-time {
                color: #6c757d;
                font-size: 0.8rem;
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
            
            .mobile-history-card .user-section {
                background: #f8f9fa;
                border-radius: 6px;
                padding: 0.75rem;
                margin-top: 0.75rem;
            }
            
            .mobile-history-card .user-section-title {
                font-size: 0.75rem;
                font-weight: 600;
                color: #6c757d;
                text-transform: uppercase;
                margin-bottom: 0.5rem;
            }
        }
        
        @media (max-width: 576px) {
            .history-card {
                margin-bottom: 0.75rem;
            }
            
            .history-header {
                padding: 0.5rem 0.75rem;
            }
            
            .history-title {
                font-size: 1rem;
            }
            
            .mobile-history-card {
                padding: 0.75rem;
            }
            
            .mobile-history-card .action-type {
                font-size: 0.85rem;
            }
            
            .mobile-history-card .action-time {
                font-size: 0.75rem;
            }
            
            .mobile-history-card .detail-item {
                font-size: 0.75rem;
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
            
            .mobile-history-card .action-info {
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
        
        .dropdown-item {
            min-height: 44px;
            display: flex;
            align-items: center;
        }
        
        .main-header {
            background-color: #004225;
            padding: 0.75rem 1.5rem;
            color: white;
        }

        .main-header h1 {
            font-size: 1.75rem;
            margin: 0;
        }

        .main-header .dropdown > .header-icon {
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
        .main-header .dropdown > .header-icon:hover, 
        .main-header .dropdown > .header-icon:focus {
            background-color: rgba(255,255,255,0.18);
            color: #fff;
        }

        .history-card {
            background: #fff;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            margin-bottom: 1.5rem;
        }

        .history-header {
            padding: 1rem 1.5rem;
            border-bottom: 1px solid #e9ecef;
            background: #f8f9fa;
            border-radius: 8px 8px 0 0;
        }

        .history-title {
            font-size: 1.25rem;
            font-weight: 600;
            color: #2c3e50;
            margin: 0;
        }

        .table th {
            background: #f8f9fa;
            font-weight: 600;
            font-size: 0.875rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #2c3e50;
            padding: 0.75rem 1rem;
            border-bottom: 1px solid #e9ecef;
        }

        .table td {
            padding: 0.75rem 1rem;
            vertical-align: middle;
            color: #2c3e50;
            font-size: 0.875rem;
            border-bottom: 1px solid #e9ecef;
        }

        .table tbody tr:hover {
            background-color: #f8f9fa;
        }

        .action-badge {
            display: inline-block;
            padding: 0.25rem 0.75rem;
            font-size: 0.75rem;
            font-weight: 500;
            border-radius: 0.25rem;
            text-transform: capitalize;
        }

        .action-badge.create {
            background: #e8f5e9;
            color: #2e7d32;
        }

        .action-badge.update {
            background: #e3f2fd;
            color: #0d6efd;
        }

        .action-badge.delete {
            background: #ffebee;
            color: #c62828;
        }

        .datetime {
            font-size: 0.75rem;
            color: #6c757d;
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .user-avatar {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            background: #f8f9fa;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.875rem;
            color: #2c3e50;
        }

        .user-details {
            display: flex;
            flex-direction: column;
        }

        .user-name {
            font-weight: 500;
            color: #2c3e50;
        }

        .user-role {
            font-size: 0.75rem;
            color: #6c757d;
        }
        
        .empty-state {
            text-align: center;
            padding: 2rem;
            color: #6c757d;
        }
        
        .empty-state i {
            font-size: 2rem;
            margin-bottom: 1rem;
            color: #6c757d;
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
                <a href="report.php" class="list-group-item list-group-item-action">
                    <i class="bi bi-file-text"></i> Report
                </a>
                <a href="add_user.php" class="list-group-item list-group-item-action">
                    <i class="bi bi-person-plus"></i> Add User
                </a>
                <a href="history.php" class="list-group-item list-group-item-action active">
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
                        <i class="bi bi-clock-history me-2"></i>
                        History
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

            <!-- History Content -->
            <div class="container-fluid py-4">
                <div class="history-card">
                    <div class="history-header">
                        <h2 class="history-title">User Action History</h2>
                    </div>
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Date & Time</th>
                                    <th>Action</th>
                                    <th>Affected User</th>
                                    <th>Admin</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($history)): ?>
                                <tr>
                                        <td colspan="4" class="text-center">
                                        <div class="empty-state">
                                            <i class="bi bi-clock-history"></i>
                                                <p>No history records found.</p>
                                        </div>
                                    </td>
                                </tr>
                                <?php else: ?>
                                    <?php foreach ($history as $record): ?>
                                        <tr>
                                            <td>
                                                <div class="datetime">
                                                    <?php echo date('M d, Y h:i A', strtotime($record['created_at'])); ?>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="action-badge <?php echo strtolower($record['action_type']); ?>">
                                                    <?php echo ucfirst($record['action_type']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div class="user-info">
                                                <div class="user-avatar">
                                                        <?php echo strtoupper(substr($record['affected_username'], 0, 1)); ?>
                                                </div>
                                                <div class="user-details">
                                                        <span class="user-name"><?php echo $record['affected_user']; ?></span>
                                                        <span class="user-role"><?php echo $record['affected_username']; ?></span>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                                <div class="user-info">
                                                    <div class="user-avatar">
                                                        <?php echo strtoupper(substr($record['admin_username'], 0, 1)); ?>
                                                    </div>
                                                <div class="user-details">
                                                        <span class="user-name"><?php echo $record['admin_name']; ?></span>
                                                        <span class="user-role">Admin</span>
                                                </div>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                
                <!-- Mobile History -->
                <div class="mobile-history">
                    <?php if (empty($history)): ?>
                        <div class="text-center py-5">
                            <div class="empty-state">
                                <i class="bi bi-clock-history"></i>
                                <p>No history records found.</p>
                            </div>
                        </div>
                    <?php else: ?>
                        <?php foreach ($history as $record): ?>
                            <div class="mobile-history-card">
                                <div class="history-header">
                                    <div class="action-info">
                                        <div class="action-icon">
                                            <?php
                                            $icon = '';
                                            switch(strtolower($record['action_type'])) {
                                                case 'create':
                                                    $icon = 'bi-plus-circle';
                                                    break;
                                                case 'update':
                                                    $icon = 'bi-pencil';
                                                    break;
                                                case 'delete':
                                                    $icon = 'bi-trash';
                                                    break;
                                                default:
                                                    $icon = 'bi-clock-history';
                                            }
                                            ?>
                                            <i class="bi <?php echo $icon; ?>"></i>
                                        </div>
                                        <div>
                                            <div class="action-type"><?php echo ucfirst($record['action_type']); ?></div>
                                            <div class="action-time"><?php echo date('M d, Y h:i A', strtotime($record['created_at'])); ?></div>
                                        </div>
                                    </div>
                                    <div>
                                        <span class="action-badge <?php echo strtolower($record['action_type']); ?>">
                                            <?php echo ucfirst($record['action_type']); ?>
                                        </span>
                                    </div>
                                </div>
                                
                                <div class="history-details">
                                    <div class="detail-item">
                                        <span class="detail-label">Action Type:</span>
                                        <span class="detail-value"><?php echo ucfirst($record['action_type']); ?></span>
                                    </div>
                                    <div class="detail-item">
                                        <span class="detail-label">Date & Time:</span>
                                        <span class="detail-value"><?php echo date('M d, Y h:i A', strtotime($record['created_at'])); ?></span>
                                    </div>
                                </div>
                                
                                <div class="user-section">
                                    <div class="user-section-title">Affected User</div>
                                    <div class="user-info">
                                        <div class="user-avatar">
                                            <?php echo strtoupper(substr($record['affected_username'], 0, 1)); ?>
                                        </div>
                                        <div class="user-details">
                                            <span class="user-name"><?php echo $record['affected_user']; ?></span>
                                            <span class="user-role"><?php echo $record['affected_username']; ?></span>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="user-section">
                                    <div class="user-section-title">Admin</div>
                                    <div class="user-info">
                                        <div class="user-avatar">
                                            <?php echo strtoupper(substr($record['admin_username'], 0, 1)); ?>
                                        </div>
                                        <div class="user-details">
                                            <span class="user-name"><?php echo $record['admin_name']; ?></span>
                                            <span class="user-role">Admin</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
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
    </script>
</body>
</html>