<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../login.php');
    exit();
}

// Check if ID is provided
if (!isset($_GET['id'])) {
    header('Location: reservation.php');
    exit();
}

// Get reservation details
try {
    $stmt = $pdo->prepare("SELECT b.*, u.name as user_name, f.name as facility_name,
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
        WHERE b.id = ?
        GROUP BY b.id");
    $stmt->execute([$_GET['id']]);
    $reservation = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$reservation) {
        header('Location: reservation.php');
        exit();
    }

    // Parse equipment details
    $equipment_details = $reservation['equipment_details'] ?? '';
    $has_sound_system = false;
    $sound_system_components = [];
    $other_equipment = [];
    
    if ($equipment_details) {
        $equipment_items = explode(', ', $equipment_details);
        foreach ($equipment_items as $item) {
            if (preg_match('/(.+) \((\d+)\)/', $item, $matches)) {
                $name = trim($matches[1]);
                $quantity = (int)$matches[2];
                
                // Check if it's a sound system component
                if (in_array($name, ['Speaker', 'Mixer', 'Amplifier', 'Cables', 'Microphone'])) {
                    $has_sound_system = true;
                    $sound_system_components[] = $name;
                } else {
                    $other_equipment[$name] = $quantity;
                }
            }
        }
    }
} catch (PDOException $e) {
    header('Location: reservation.php');
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Booking - PGOM Facilities</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
    <link href="../assets/css/notifications.css" rel="stylesheet">
    <style>
        /* Mobile menu toggle */
        .mobile-menu-toggle {
            display: none;
            background: none;
            border: none;
            color: white;
            font-size: 1.5rem;
            padding: 0.5rem;
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
        }

        @media (max-width: 480px) {
            .header-title {
                font-size: 1rem !important;
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
                        <i class="bi bi-file-earmark-text me-2"></i>
                        Report Entry Details
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

            <!-- Booking Details -->
            <div class="container-fluid py-4 px-4">
                <div class="content-card">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="d-flex align-items-center mb-4">
                                <i class="bi bi-info-circle text-success me-2"></i>
                                <h5 class="mb-0">Booking Information</h5>
                            </div>
                            <div class="table-container">
                                <table class="table table-borderless">
                                    <tr>
                                        <th width="150" class="text-muted">Name:</th>
                                        <td class="fw-medium"><?php echo htmlspecialchars($reservation['user_name']); ?></td>
                                    </tr>
                                    <tr>
                                        <th class="text-muted">Venue:</th>
                                        <td class="fw-medium"><?php echo htmlspecialchars($reservation['facility_name']); ?></td>
                                    </tr>
                                    <tr>
                                        <th class="text-muted">Event Date:</th>
                                        <td class="fw-medium"><?php echo date('M. d, Y', strtotime($reservation['start_time'])); ?></td>
                                    </tr>
                                    <tr>
                                        <th class="text-muted">Time:</th>
                                        <td class="fw-medium"><?php echo date('g:i a', strtotime($reservation['start_time'])) . ' - ' . 
                                                   date('g:i a', strtotime($reservation['end_time'])); ?></td>
                                    </tr>
                                    <tr>
                                        <th class="text-muted">Status:</th>
                                        <td>
                                            <span class="badge bg-<?php 
                                                switch(strtolower($reservation['status'])) {
                                                    case 'pending': echo 'warning'; break;
                                                    case 'approved': echo 'success'; break;
                                                    case 'rejected': echo 'danger'; break;
                                                    default: echo 'secondary';
                                                }
                                            ?>">
                                                <?php echo ucfirst($reservation['status']); ?>
                                            </span>
                                        </td>
                                    </tr>
                                </table>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="d-flex align-items-center mb-4">
                                <i class="bi bi-box-seam text-success me-2"></i>
                                <h5 class="mb-0">Equipment Details</h5>
                            </div>
                            <div class="table-container">
                                <?php if ($equipment_details): ?>
                                    <div class="alert alert-info">
                                        <strong>Requested Equipment:</strong><br>
                                        <?php echo htmlspecialchars($equipment_details); ?>
                                    </div>
                                    
                                    <?php if ($has_sound_system && !empty($sound_system_components)): ?>
                                    <div class="mt-3">
                                        <strong>Sound System Components:</strong>
                                        <ul class="list-unstyled mb-0 mt-2">
                                                <?php foreach ($sound_system_components as $component): ?>
                                                <li><i class="bi bi-check-circle-fill text-success me-2"></i><?php echo htmlspecialchars($component); ?></li>
                                                <?php endforeach; ?>
                                            </ul>
                                    </div>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <div class="alert alert-light">
                                        <i class="bi bi-info-circle me-2"></i>
                                        No equipment requested for this booking.
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Request Letter Section -->
                    <?php if ($reservation['request_letter']): ?>
                    <div class="row mt-4">
                        <div class="col-12">
                            <div class="d-flex align-items-center mb-3">
                                <i class="bi bi-file-earmark-text text-success me-2"></i>
                                <h5 class="mb-0">Request Letter</h5>
                            </div>
                            <div class="alert alert-light">
                                <div class="d-flex align-items-center">
                                    <i class="bi bi-file-pdf text-danger me-2"></i>
                                    <span class="me-3">Request letter uploaded</span>
                                    <a href="../uploads/<?php echo htmlspecialchars($reservation['request_letter']); ?>" 
                                       target="_blank" 
                                       class="btn btn-sm btn-outline-primary">
                                        <i class="bi bi-download me-1"></i>View PDF
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- Back Button -->
                    <div class="mt-4">
                        <a href="report.php" class="btn btn-secondary">
                            <i class="bi bi-arrow-left me-2"></i>Back to Report
                        </a>
                    </div>
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
            const sidebar = document.getElementById('sidebar');
            
            // Toggle mobile menu
            mobileMenuToggle.addEventListener('click', function() {
                sidebar.classList.toggle('show');
            });
            
            // Close menu when clicking on a link (mobile)
            const sidebarLinks = sidebar.querySelectorAll('.list-group-item');
            sidebarLinks.forEach(link => {
                link.addEventListener('click', function() {
                    if (window.innerWidth <= 768) {
                        sidebar.classList.remove('show');
                    }
                });
            });
            
            // Handle window resize
            window.addEventListener('resize', function() {
                if (window.innerWidth > 768) {
                    sidebar.classList.remove('show');
                }
            });
        });
    </script>
</body>
</html>