<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'user') {
    header('Location: ../login.php');
    exit();
}

// Get user's booking counts with error handling
$user_id = $_SESSION['user_id'];
$counts = [
    'completed' => 0,
    'pending' => 0,
    'approved' => 0,
    'rejected' => 0,
    'cancelled' => 0,
    'total' => 0
];
$recent_bookings = [];

try {
    $stmt = $pdo->prepare("SELECT 
        COUNT(CASE WHEN (status = 'completed' OR (status = 'approved' AND end_time < NOW())) THEN 1 END) as completed,
        COUNT(CASE WHEN status = 'pending' THEN 1 END) as pending,
        COUNT(CASE WHEN status = 'approved' AND end_time >= NOW() THEN 1 END) as approved,
        COUNT(CASE WHEN status = 'rejected' THEN 1 END) as rejected,
        COUNT(CASE WHEN status = 'cancelled' THEN 1 END) as cancelled,
        COUNT(*) as total
        FROM bookings WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $counts = $stmt->fetch(PDO::FETCH_ASSOC);

    // Get recent bookings
    $stmt = $pdo->prepare("SELECT b.*, f.name as facility_name 
        FROM bookings b 
        JOIN facilities f ON b.facility_id = f.id 
        WHERE b.user_id = ? 
        ORDER BY b.created_at DESC LIMIT 5");
    $stmt->execute([$user_id]);
    $recent_bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // Table doesn't exist - keep default empty values
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Dashboard - PGOM Facilities</title>
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
            
            .stat-card {
                margin-bottom: 1rem;
            }
            
            .stat-card .stat-value {
                font-size: 1.25rem;
            }
            
            .quick-action-card {
                margin-bottom: 1rem;
                padding: 1rem;
            }
            
            .quick-action-card i {
                font-size: 1.5rem;
            }
            
            .quick-action-card h5 {
                font-size: 1rem;
            }
            
            .container-fluid {
                padding-left: 1rem !important;
                padding-right: 1rem !important;
            }
        }
        
        @media (max-width: 576px) {
            .stat-card {
                padding: 0.75rem;
            }
            
            .stat-card .card-title {
                font-size: 0.7rem;
            }
            
            .stat-card .stat-value {
                font-size: 1.1rem;
            }
            
            .stat-card .stat-icon {
                font-size: 1rem;
            }
            
            .quick-action-card {
                padding: 0.75rem;
            }
            
            .quick-action-card i {
                font-size: 1.25rem;
                margin-bottom: 0.5rem;
            }
            
            .quick-action-card h5 {
                font-size: 0.9rem;
                margin-bottom: 0.25rem;
            }
            
            .quick-action-card p {
                font-size: 0.8rem;
                margin-bottom: 1rem;
            }
            
            .btn {
                font-size: 0.875rem;
                padding: 0.375rem 0.75rem;
            }
        }
        
        @media (max-width: 480px) {
            .header-title {
                font-size: 1rem !important;
            }
            
            .stat-card .d-flex {
                flex-direction: column;
                text-align: center;
            }
            
            .stat-card .stat-icon {
                margin-top: 0.5rem;
            }
            
            .quick-action-card {
                text-align: center;
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
        
        /* Card styles */
        .stat-card {
            background: white;
            border-radius: 8px;
            padding: 1rem;
            height: 100%;
            border: 1px solid #e9ecef;
            transition: all 0.2s ease;
            margin-bottom: 0;
        }

        .stat-card:hover {
            border-color: #43a047;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.04);
        }

        .stat-card .card-title {
            color: #6c757d;
            font-size: 0.75rem;
            font-weight: 600;
            margin-bottom: 0;
            text-transform: uppercase;
        }

        .stat-card .stat-value {
            font-size: 1.5rem;
            font-weight: 600;
            color: #2c3e50;
            margin: 0.25rem 0;
            line-height: 1;
        }

        .stat-card .text-muted {
            font-size: 0.75rem;
            color: #6c757d !important;
        }

        .stat-card .stat-icon {
            font-size: 1.25rem;
            opacity: 0.9;
        }

        /* Quick action cards */
        .quick-action-card {
            background: white;
            border-radius: 10px;
            padding: 1.5rem;
            text-align: center;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            height: 100%;
        }

        .quick-action-card i {
            font-size: 2rem;
            color: #43a047;
            margin-bottom: 1rem;
        }

        .quick-action-card h5 {
            color: #2c3e50;
            margin-bottom: 0.5rem;
        }

        .quick-action-card p {
            color: #6c757d;
            font-size: 0.875rem;
            margin-bottom: 1.5rem;
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
        
        /* Improved spacing for mobile */
        .row.g-3 {
            --bs-gutter-x: 0.75rem;
            --bs-gutter-y: 0.75rem;
        }
        
        @media (max-width: 576px) {
            .row.g-3 {
                --bs-gutter-x: 0.5rem;
                --bs-gutter-y: 0.5rem;
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
                <a href="dashboard.php" class="list-group-item list-group-item-action active">
                    <i class="bi bi-speedometer2"></i> Dashboard
                </a>
                <a href="make_booking.php" class="list-group-item list-group-item-action">
                    <i class="bi bi-calendar-plus"></i> Make a Booking
                </a>
                <a href="my_bookings.php" class="list-group-item list-group-item-action">
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
                        <i class="bi bi-speedometer2 me-2"></i>
                        Dashboard Overview
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

            <!-- Quick Actions -->
            <!-- Use the same header code as admin dashboard -->

            <!-- Dashboard Cards -->
            <div class="container-fluid py-4 px-4">
                <div class="row g-3 mb-4">
                    <div class="col">
                        <div class="stat-card completed">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="card-title text-uppercase">Completed</h6>
                                    <h3 class="stat-value"><?php echo $counts['completed']; ?></h3>
                                    <p class="text-muted mb-0">Bookings</p>
                                </div>
                                <div class="stat-icon">
                                    <i class="bi bi-check-circle text-primary"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col">
                        <div class="stat-card pending">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="card-title text-uppercase">Pending</h6>
                                    <h3 class="stat-value"><?php echo $counts['pending']; ?></h3>
                                    <p class="text-muted mb-0">Bookings</p>
                                </div>
                                <div class="stat-icon">
                                    <i class="bi bi-clock text-warning"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col">
                        <div class="stat-card approved">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="card-title text-uppercase">Approved</h6>
                                    <h3 class="stat-value"><?php echo $counts['approved']; ?></h3>
                                    <p class="text-muted mb-0">Bookings</p>
                                </div>
                                <div class="stat-icon">
                                    <i class="bi bi-check-circle text-success"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col">
                        <div class="stat-card rejected">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="card-title text-uppercase">Rejected</h6>
                                    <h3 class="stat-value"><?php echo $counts['rejected']; ?></h3>
                                    <p class="text-muted mb-0">Bookings</p>
                                </div>
                                <div class="stat-icon">
                                    <i class="bi bi-x-circle text-danger"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col">
                        <div class="stat-card cancelled">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="card-title text-uppercase">Cancelled</h6>
                                    <h3 class="stat-value"><?php echo $counts['cancelled']; ?></h3>
                                    <p class="text-muted mb-0">Bookings</p>
                                </div>
                                <div class="stat-icon">
                                    <i class="bi bi-x-circle text-secondary"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col">
                        <div class="stat-card total">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="card-title text-uppercase">Total</h6>
                                    <h3 class="stat-value"><?php echo $counts['total']; ?></h3>
                                    <p class="text-muted mb-0">Bookings</p>
                                </div>
                                <div class="stat-icon">
                                    <i class="bi bi-calendar-check text-info"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Quick Actions -->
            <div class="container-fluid px-4">
                <div class="row g-4">
                    <div class="col-md-4">
                        <div class="quick-action-card">
                            <i class="bi bi-calendar-plus"></i>
                            <h5>New Booking</h5>
                            <p>Schedule a new facility</p>
                            <a href="make_booking.php" class="btn btn-success px-4">Book Now →</a>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="quick-action-card">
                            <i class="bi bi-calendar-check"></i>
                            <h5>My Bookings</h5>
                            <p>View your Existing Booking</p>
                            <a href="my_bookings.php" class="btn btn-success px-4">View All →</a>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="quick-action-card">
                            <i class="bi bi-clock-history"></i>
                            <h5>My History</h5>
                            <p>View your History</p>
                            <a href="history.php" class="btn btn-success px-4">View →</a>
                        </div>
                    </div>
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