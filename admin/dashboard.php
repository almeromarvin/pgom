<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../login.php');
    exit();
}

// Get reservation counts with error handling
$counts = [
    'completed' => 0,
    'pending' => 0,
    'approved' => 0,
    'rejected' => 0,
    'total' => 0
];
try {
    $stmt = $pdo->query("SELECT 
        COUNT(CASE WHEN (status = 'completed' OR (status = 'approved' AND end_time < NOW())) THEN 1 END) as completed,
        COUNT(CASE WHEN status = 'pending' THEN 1 END) as pending,
        COUNT(CASE WHEN status = 'approved' AND end_time >= NOW() THEN 1 END) as approved,
        COUNT(CASE WHEN status = 'rejected' THEN 1 END) as rejected,
        COUNT(*) as total
        FROM bookings");
    $counts = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // Table might not exist
}

// Get user statistics with error handling
$userStats = ['total_users' => 0, 'active_users' => 0, 'deleted_users' => 0];
$user_growth = [];
try {
    // Get total, active, and deleted user counts
    $stmt = $pdo->query("SELECT 
        COUNT(*) as total_users,
        COUNT(CASE WHEN deleted_at IS NULL THEN 1 END) as active_users,
        COUNT(CASE WHEN deleted_at IS NOT NULL THEN 1 END) as deleted_users
        FROM users");
    $userStats = $stmt->fetch(PDO::FETCH_ASSOC);

    // Get user growth data with cumulative totals
    $stmt = $pdo->query("SELECT 
        DATE(created_at) as date,
        SUM(COUNT(*)) OVER (ORDER BY DATE(created_at)) as cumulative_total,
        SUM(COUNT(CASE WHEN deleted_at IS NOT NULL THEN 1 END)) OVER (ORDER BY DATE(created_at)) as cumulative_deleted
        FROM users 
        GROUP BY DATE(created_at) 
        ORDER BY date DESC LIMIT 30");
    $user_growth = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // Handle error
}

// Get inventory status with error handling
$inventory_status = [];
try {
    $stmt = $pdo->query("SELECT 
        COUNT(CASE WHEN status = 'Available' THEN 1 END) as available,
        COUNT(CASE WHEN status = 'Borrowed' THEN 1 END) as in_use,
        COUNT(CASE WHEN status = 'In Maintenance' THEN 1 END) as maintenance
        FROM inventory");
    $inventory_status = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $inventory_status = [
        'available' => 0,
        'in_use' => 0,
        'maintenance' => 0
    ];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - PGOM Facilities</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
    <link href="../assets/css/notifications.css" rel="stylesheet">
    <link href="../assets/css/darkmode.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
            }
            
            .stat-value {
                font-size: 1.5rem;
            }
            
            .stat-label {
                font-size: 0.875rem;
            }
            
            .chart-container {
                margin-bottom: 1.5rem;
            }
            
            .chart-title {
                font-size: 1.1rem;
            }
            
            .container-fluid {
                padding-left: 1rem !important;
                padding-right: 1rem !important;
            }
            
            .notification-dropdown {
                width: 300px;
                max-height: 400px;
            }
        }
        
        @media (max-width: 576px) {
            .stat-card {
                padding: 1rem;
            }
            
            .stat-value {
                font-size: 1.25rem;
            }
            
            .stat-label {
                font-size: 0.8rem;
            }
            
            .chart-container {
                padding: 1rem;
                margin-bottom: 1rem;
            }
            
            .chart-title {
                font-size: 1rem;
            }
            
            .notification-dropdown {
                width: 280px;
                max-height: 350px;
            }
            
            .notification-item {
                padding: 0.75rem;
            }
            
            .notification-text {
                font-size: 0.875rem;
            }
            
            .notification-time {
                font-size: 0.75rem;
            }
        }
        
        @media (max-width: 480px) {
            .header-title {
                font-size: 1rem !important;
            }
            
            .stat-card {
                padding: 0.75rem;
            }
            
            .stat-value {
                font-size: 1.1rem;
            }
            
            .stat-label {
                font-size: 0.75rem;
            }
            
            .chart-container {
                padding: 0.75rem;
            }
            
            .chart-title {
                font-size: 0.9rem;
            }
            
            .notification-dropdown {
                width: 260px;
                max-height: 300px;
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
        
        /* Spinning animation for refresh button */
        .spin {
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }
        
        /* Overlay for mobile sidebar */
        .sidebar-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
        /* Base styles */
        :root {
            --card-bg: #ffffff;
            --card-border: #e9ecef;
            --text-primary: #2c3e50;
            --text-secondary: #6c757d;
            --hover-bg: #f8f9fa;
            --chart-grid: rgba(0, 0, 0, 0.1);
        }

        .stat-card {
            color: var(--text-primary);
        }

        .stat-value {
            color: var(--text-primary);
        }

        .stat-label {
            color: var(--text-secondary);
        }

        .chart-container {
            background-color: var(--card-bg);
            border-color: var(--card-border);
        }

        .chart-title {
            color: var(--text-primary);
        }

        .text-muted {
            color: var(--text-secondary) !important;
        }

        .notification-item {
            color: var(--text-primary);
            background-color: var(--card-bg);
            border-color: var(--card-border);
        }

        .notification-item:hover {
            background-color: var(--hover-bg);
        }

        .notification-time {
            color: var(--text-secondary);
        }

        .spinner-border {
            border-color: var(--text-primary);
            border-right-color: transparent;
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
                <a href="dashboard.php" class="list-group-item list-group-item-action active">
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
                    <h1 class="h3 mb-0 d-flex align-items-center header-title">
                        <i class="bi bi-speedometer2 me-2"></i>
                        Dashboard Overview
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

            <!-- Dashboard Content -->
            <div class="container-fluid py-4 px-4">
                <div class="row g-4 mb-4">
                    <div class="col-12 col-md-6 col-lg-4">
                        <div class="stat-card completed h-100">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="card-title text-uppercase mb-2">Completed</h6>
                                    <h3 class="stat-value mb-1"><?php echo $counts['completed']; ?></h3>
                                    <p class="text-muted mb-0">Bookings</p>
                                </div>
                                <div class="stat-icon">
                                    <i class="bi bi-check-circle"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-12 col-md-6 col-lg-4">
                        <div class="stat-card pending h-100">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="card-title text-uppercase mb-2">Pending</h6>
                                    <h3 class="stat-value mb-1"><?php echo $counts['pending']; ?></h3>
                                    <p class="text-muted mb-0">Bookings</p>
                                </div>
                                <div class="stat-icon">
                                    <i class="bi bi-clock"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-12 col-md-6 col-lg-4">
                        <div class="stat-card approved h-100">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="card-title text-uppercase mb-2">Approved</h6>
                                    <h3 class="stat-value mb-1"><?php echo $counts['approved']; ?></h3>
                                    <p class="text-muted mb-0">Bookings</p>
                                </div>
                                <div class="stat-icon">
                                    <i class="bi bi-check-circle"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-12 col-md-6 col-lg-4">
                        <div class="stat-card rejected h-100">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="card-title text-uppercase mb-2">Rejected</h6>
                                    <h3 class="stat-value mb-1"><?php echo $counts['rejected']; ?></h3>
                                    <p class="text-muted mb-0">Bookings</p>
                                </div>
                                <div class="stat-icon">
                                    <i class="bi bi-x-circle"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-12 col-md-6 col-lg-4">
                        <div class="stat-card total h-100">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="card-title text-uppercase mb-2">Total</h6>
                                    <h3 class="stat-value mb-1"><?php echo $counts['total']; ?></h3>
                                    <p class="text-muted mb-0">Bookings</p>
                                </div>
                                <div class="stat-icon">
                                    <i class="bi bi-calendar-check"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-12 col-md-6 col-lg-4">
                        <div class="stat-card users h-100">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="card-title text-uppercase mb-2">Total Users</h6>
                                    <h3 class="stat-value mb-1"><?php echo $userStats['total_users']; ?></h3>
                                    <p class="text-muted mb-0">
                                        <span class="text-success"><?php echo $userStats['active_users']; ?> Active</span> • 
                                        <span class="text-danger"><?php echo $userStats['deleted_users']; ?> Deleted</span>
                                    </p>
                                </div>
                                <div class="stat-icon">
                                    <i class="bi bi-people"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <style>
                    .stat-card {
                        background: #fff;
                        border-radius: 14px;
                        padding: 2rem 1.5rem;
                        box-shadow: 0 2px 8px rgba(0,0,0,0.07);
                        transition: transform 0.2s ease, box-shadow 0.2s ease;
                        min-width: 220px;
                    }

                    .stat-card:hover {
                        transform: translateY(-2px) scale(1.03);
                        box-shadow: 0 6px 16px rgba(0,0,0,0.13);
                    }

                    .stat-card .card-title {
                        font-size: 1rem;
                        font-weight: 700;
                        color: #004225;
                        letter-spacing: 0.5px;
                    }

                    .stat-card .stat-value {
                        font-size: 2.25rem;
                        font-weight: 700;
                        color: #2c3e50;
                        line-height: 1.2;
                    }

                    .stat-card .stat-icon {
                        width: 56px;
                        height: 56px;
                        border-radius: 14px;
                        display: flex;
                        align-items: center;
                        justify-content: center;
                        font-size: 2rem;
                    }

                    .stat-card.completed .stat-icon {
                        background-color: #d4edda;
                        color: #155724;
                    }

                    .stat-card.pending .stat-icon {
                        background-color: #fff3cd;
                        color: #856404;
                    }

                    .stat-card.approved .stat-icon {
                        background-color: #cce5ff;
                        color: #004085;
                    }

                    .stat-card.rejected .stat-icon {
                        background-color: #f8d7da;
                        color: #721c24;
                    }

                    .stat-card.total .stat-icon {
                        background-color: #e2e3e5;
                        color: #383d41;
                    }

                    .stat-card.users .stat-icon {
                        background-color: #d1ecf1;
                        color: #0c5460;
                    }

                    .stat-card p.text-muted {
                        font-size: 1rem;
                    }

                    .stat-card .text-success,
                    .stat-card .text-danger {
                        font-weight: 600;
                    }
                </style>

                <!-- Tabs and Chart -->
                <div class="card bg-white mb-4">
                    <div class="card-header bg-white">
                        <ul class="nav nav-tabs card-header-tabs">
                            <li class="nav-item">
                                <a class="nav-link active" id="reservation-tab" data-bs-toggle="tab" href="#reservation-content">All Reservation</a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" id="users-tab" data-bs-toggle="tab" href="#users-content">Users</a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" id="inventory-tab" data-bs-toggle="tab" href="#inventory-content">Inventory</a>
                            </li>
                        </ul>
                    </div>
                    <div class="card-body">
                        <div class="tab-content">
                            <!-- Reservation Content -->
                            <div class="tab-pane fade show active" id="reservation-content">
                                <div class="chart-container">
                                    <canvas id="reservationChart"></canvas>
                                </div>
                            </div>
                            <!-- Users Content -->
                            <div class="tab-pane fade" id="users-content">
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <h6 class="mb-0">User Growth Analytics</h6>
                                    <button id="refreshUserGrowth" class="btn btn-sm btn-outline-primary">
                                        <i class="bi bi-arrow-clockwise"></i> Refresh
                                    </button>
                                </div>
                                <div class="chart-container">
                                    <canvas id="userChart"></canvas>
                                </div>
                            </div>
                            <!-- Inventory Content -->
                            <div class="tab-pane fade" id="inventory-content">
                                <div class="chart-container">
                                    <canvas id="inventoryChart"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>
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
        
        document.addEventListener('DOMContentLoaded', function() {
            // Reservation Chart
            const reservationCtx = document.getElementById('reservationChart').getContext('2d');
            new Chart(reservationCtx, {
                type: 'pie',
                data: {
                    labels: ['Completed', 'Pending', 'Approved', 'Rejected'],
                    datasets: [{
                        data: [<?php echo $counts['completed']; ?>, <?php echo $counts['pending']; ?>, <?php echo $counts['approved']; ?>, <?php echo $counts['rejected']; ?>],
                        backgroundColor: ['#0d6efd', '#ffc107', '#198754', '#dc3545']
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'bottom',
                            labels: {
                                padding: 20,
                                font: { size: 12 }
                            }
                        }
                    }
                }
            });

            // User Growth Chart
            const userCtx = document.getElementById('userChart').getContext('2d');
            new Chart(userCtx, {
                type: 'line',
                data: {
                    labels: <?php echo json_encode(array_column(array_reverse($user_growth), 'date')); ?>,
                    datasets: [{
                        label: 'All Users',
                        data: <?php echo json_encode(array_column(array_reverse($user_growth), 'cumulative_total')); ?>,
                        borderColor: '#198754',
                        backgroundColor: '#19875422',
                        tension: 0.1,
                        fill: true
                    }, {
                        label: 'Deleted Users',
                        data: <?php echo json_encode(array_column(array_reverse($user_growth), 'cumulative_deleted')); ?>,
                        borderColor: '#dc3545',
                        backgroundColor: '#dc354522',
                        tension: 0.1,
                        fill: true
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'bottom',
                            labels: {
                                padding: 20,
                                font: { size: 12 }
                            }
                        },
                        title: {
                            display: true,
                            text: 'User Growth (Last 30 Days)',
                            font: { size: 16, weight: 'bold' },
                            padding: { bottom: 20 }
                        }
                    },
                    scales: {
                        x: {
                            display: true,
                            title: {
                                display: true,
                                text: 'Date'
                            }
                        },
                        y: {
                            display: true,
                            title: {
                                display: true,
                                text: 'Number of Users'
                            },
                            beginAtZero: true,
                            ticks: {
                                stepSize: 1
                            }
                        }
                    }
                }
            });

            // Inventory Chart
            const inventoryCtx = document.getElementById('inventoryChart').getContext('2d');
            new Chart(inventoryCtx, {
                type: 'doughnut',
                data: {
                    labels: ['Available', 'In Use', 'Maintenance'],
                    datasets: [{
                        data: [
                            <?php 
                            echo $inventory_status['available'] . ", " . 
                                 $inventory_status['in_use'] . ", " . 
                                 $inventory_status['maintenance'];
                            ?>
                        ],
                        backgroundColor: ['#198754', '#0dcaf0', '#ffc107'],
                        borderWidth: 2,
                        borderColor: '#ffffff',
                        hoverOffset: 15,
                        hoverBorderWidth: 0
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    cutout: '65%',
                    plugins: {
                        legend: {
                            position: 'bottom',
                            labels: {
                                padding: 20,
                                font: { size: 14, weight: 'bold' },
                                usePointStyle: true,
                                pointStyle: 'circle'
                            }
                        },
                        title: {
                            display: true,
                            text: 'Inventory Status Overview',
                            font: { size: 18, weight: 'bold' },
                            padding: { bottom: 30 }
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    const label = context.label || '';
                                    const value = context.raw || 0;
                                    const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                    const percentage = ((value / total) * 100).toFixed(1);
                                    return `${label}: ${value} (${percentage}%)`;
                                }
                            },
                            padding: 12,
                            titleFont: { size: 14 },
                            bodyFont: { size: 14 }
                        }
                    },
                    animation: {
                        animateScale: true,
                        animateRotate: true,
                        duration: 2000,
                        easing: 'easeOutQuart'
                    }
                }
            });

            // Add tab switching functionality
            document.querySelectorAll('[data-bs-toggle="tab"]').forEach(tab => {
                tab.addEventListener('shown.bs.tab', function(e) {
                    const targetId = e.target.getAttribute('href');
                    const charts = {
                        '#reservation-content': reservationChart,
                        '#users-content': userChart,
                        '#inventory-content': inventoryChart
                    };
                    if (charts[targetId]) {
                        charts[targetId].resize();
                    }
                });
            });

            // Function to refresh user growth data
            function refreshUserGrowthData() {
                fetch('get_user_growth_data.php')
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            // Update the user chart with new data
                            userChart.data.labels = data.user_growth.map(item => item.date);
                            userChart.data.datasets[0].data = data.user_growth.map(item => item.cumulative_total);
                            userChart.data.datasets[1].data = data.user_growth.map(item => item.cumulative_deleted);
                            userChart.update();
                        }
                    })
                    .catch(error => console.error('Error refreshing user growth data:', error));
            }

            // Add refresh button functionality
            const refreshBtn = document.getElementById('refreshUserGrowth');
            if (refreshBtn) {
                refreshBtn.addEventListener('click', function() {
                    this.innerHTML = '<i class="bi bi-arrow-clockwise spin"></i> Refreshing...';
                    refreshUserGrowthData();
                    setTimeout(() => {
                        this.innerHTML = '<i class="bi bi-arrow-clockwise"></i> Refresh';
                    }, 2000);
                });
            }

            // Auto-refresh user growth data every 5 minutes
            setInterval(refreshUserGrowthData, 300000);
        });
    </script>
</body>
</html>