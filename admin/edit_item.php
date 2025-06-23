<?php
session_start();
require_once '../config/database.php';
require_once '../includes/notification_helper.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../login.php');
    exit();
}

// Check for valid item_id in URL
if (!isset($_GET['id']) || !is_numeric($_GET['id']) || $_GET['id'] <= 0) {
    $_SESSION['error'] = 'Invalid or missing item ID.';
    header('Location: inventory.php');
    exit();
}

$item_id = (int)$_GET['id'];

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Start transaction
        $pdo->beginTransaction();

        // Get current item data
        $stmt = $pdo->prepare("SELECT * FROM inventory WHERE id = ?");
        $stmt->execute([$item_id]);
        $current_item = $stmt->fetch(PDO::FETCH_ASSOC);

        // Update item
        $stmt = $pdo->prepare("
            UPDATE inventory 
            SET name = ?, total_quantity = ?, minimum_stock = ?, status = ?, description = ?, last_updated = NOW()
            WHERE id = ?
        ");
        $stmt->execute([
            $_POST['name'],
            $_POST['total_quantity'],
            $_POST['minimum_stock'],
            $_POST['status'],
            $_POST['description'] ?? null,
            $item_id
        ]);

        // Log changes in history
        if ($current_item) {
            $changes = [];
            if ($current_item['name'] !== $_POST['name']) {
                $changes[] = "Name changed from '{$current_item['name']}' to '{$_POST['name']}'";
            }
            if ($current_item['total_quantity'] != $_POST['total_quantity']) {
                $quantity_diff = $_POST['total_quantity'] - $current_item['total_quantity'];
                $action = $quantity_diff > 0 ? "Added" : "Removed";
                $changes[] = "$action " . abs($quantity_diff) . " items";
            }
            if ($current_item['minimum_stock'] != $_POST['minimum_stock']) {
                $changes[] = "Minimum stock updated to {$_POST['minimum_stock']}";
            }
            if ($current_item['status'] !== $_POST['status']) {
                $changes[] = "Status changed to {$_POST['status']}";
            }

            if (!empty($changes)) {
                $stmt = $pdo->prepare("
                    INSERT INTO inventory_history (item_id, action, quantity, modified_by, date_modified)
                    VALUES (?, ?, ?, ?, NOW())
                ");
                $stmt->execute([
                    $item_id,
                    implode(", ", $changes),
                    $_POST['total_quantity'],
                    $_SESSION['user_id']
                ]);

                // Add notifications for different actions
                if ($current_item['total_quantity'] != $_POST['total_quantity']) {
                    if ($_POST['total_quantity'] <= $_POST['minimum_stock']) {
                        addInventoryNotification('low_stock', $_POST['name'], null, $item_id);
                    }
                    if ($_POST['total_quantity'] == 0) {
                        addInventoryNotification('out_of_stock', $_POST['name'], null, $item_id);
                    }
                }

                if ($current_item['status'] !== $_POST['status']) {
                    if ($_POST['status'] === 'In Maintenance') {
                        addMaintenanceNotification('started', $_POST['name']);
                    } else if ($current_item['status'] === 'In Maintenance' && $_POST['status'] === 'Available') {
                        addMaintenanceNotification('completed', $_POST['name']);
                    }
                }
            }
        }

        // Commit transaction
        $pdo->commit();
        
        $_SESSION['success_message'] = "Item updated successfully!";
        header("Location: inventory.php");
        exit();
    } catch (PDOException $e) {
        $pdo->rollBack();
        $error_message = "Error updating item: " . $e->getMessage();
    }
}

// Get item details
try {
    $stmt = $pdo->prepare("SELECT * FROM inventory WHERE id = ?");
    $stmt->execute([$item_id]);
    $item = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$item) {
        $_SESSION['error'] = "Item not found";
        header("Location: inventory.php");
        exit();
    }
} catch (PDOException $e) {
    $_SESSION['error'] = "Error fetching item details";
    header("Location: inventory.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Item - PGOM Facilities</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
    <link href="../assets/css/notifications.css" rel="stylesheet">
    <style>
        /* Light mode specific styles */
        :root {
            --input-border: #ced4da;
            --input-focus-border: #198754;
            --input-shadow: rgba(0, 0, 0, 0.1);
            --card-bg: #ffffff;
            --card-border: #e9ecef;
            --text-primary: #2c3e50;
            --text-secondary: #6c757d;
            --hover-bg: #f8f9fa;
            --input-bg: #ffffff;
        }

        .form-label {
            color: var(--text-primary);
        }

        .form-control {
            background-color: var(--input-bg);
            border-color: var(--input-border);
            color: var(--text-primary);
        }

        .form-control:focus {
            background-color: var(--input-bg);
            border-color: #4ade80;
            color: var(--text-primary);
        }

        .form-text {
            color: var(--text-secondary);
        }

        .card {
            background-color: var(--card-bg);
            border-color: var(--card-border);
        }

        .card-title {
            color: var(--text-primary);
        }

        .text-muted {
            color: var(--text-secondary) !important;
        }

        .btn-primary {
            background-color: #4ade80;
            border-color: #4ade80;
            color: #000000;
        }

        .btn-primary:hover {
            background-color: #22c55e;
            border-color: #22c55e;
        }

        .form-card {
            background: var(--bg-secondary);
            border-radius: 15px;
            padding: 2rem;
            box-shadow: 0 0 20px rgba(0,0,0,0.05);
            color: var(--text-primary);
            border: 2px solid var(--input-border);
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

        .current-value {
            font-size: 0.85rem;
            color: var(--text-secondary);
            margin-top: 0.5rem;
        }

        .current-value strong {
            color: var(--text-primary);
        }

        /* Dropdown menu styling */
        .dropdown-menu {
            border: 2px solid var(--input-border);
            border-radius: 10px;
            overflow: hidden;
            background-color: var(--bg-secondary);
        }

        .dropdown-item {
            color: var(--text-primary);
            padding: 0.75rem 1rem;
        }

        .dropdown-item:hover {
            background-color: var(--hover-bg);
            color: var(--text-primary);
        }

        textarea.form-control {
            min-height: 100px;
            resize: vertical;
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
                        <i class="bi bi-pencil-square me-2"></i>
                        Edit Item
                    </h1>
                </div>
                <div class="d-flex align-items-center gap-3">
                    <div class="header-actions">
                        <button class="header-icon notifications-toggle border-0 bg-transparent">
                            <i class="bi bi-bell"></i>
                        </button>
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
                        <li class="breadcrumb-item"><a href="view_item.php?id=<?php echo $item['id']; ?>"><?php echo htmlspecialchars($item['name']); ?></a></li>
                        <li class="breadcrumb-item active" aria-current="page">Edit</li>
                    </ol>
                </nav>

                <?php if (isset($error_message)): ?>
                <div class="alert alert-danger" role="alert">
                    <i class="bi bi-exclamation-triangle me-2"></i>
                    <?php echo $error_message; ?>
                </div>
                <?php endif; ?>

                <div class="form-card">
                    <form id="editItemForm" method="POST" class="needs-validation" novalidate>
                        <div class="row g-4">
                            <div class="col-12">
                                <label for="name" class="form-label">Item Name</label>
                                <select class="form-select" id="name" name="name" required>
                                    <option value="">Select item name</option>
                                    <option value="Chairs" <?php echo $item['name'] === 'Chairs' ? 'selected' : ''; ?>>Chairs</option>
                                    <option value="Tables" <?php echo $item['name'] === 'Tables' ? 'selected' : ''; ?>>Tables</option>
                                    <option value="Industrial Fan" <?php echo $item['name'] === 'Industrial Fan' ? 'selected' : ''; ?>>Industrial Fan</option>
                                    <option value="Extension Wires" <?php echo $item['name'] === 'Extension Wires' ? 'selected' : ''; ?>>Extension Wires</option>
                                    <option value="Sound System" <?php echo $item['name'] === 'Sound System' ? 'selected' : ''; ?>>Sound System</option>
                                    <option value="Red Carpet" <?php echo $item['name'] === 'Red Carpet' ? 'selected' : ''; ?>>Red Carpet</option>
                                    <option value="Podium" <?php echo $item['name'] === 'Podium' ? 'selected' : ''; ?>>Podium</option>
                                </select>
                                <div class="invalid-feedback">Please select an item name.</div>
                            </div>

                            <div class="col-md-6">
                                <label for="total_quantity" class="form-label">Total Quantity</label>
                                <input type="number" class="form-control" id="total_quantity" name="total_quantity" 
                                       value="<?php echo $item['total_quantity']; ?>" min="0" required>
                                <div class="invalid-feedback">Please enter a valid quantity.</div>
                                <div class="current-value">
                                    Currently borrowed: <strong><?php echo $item['borrowed']; ?></strong> items
                                </div>
                            </div>

                            <div class="col-md-6">
                                <label for="minimum_stock" class="form-label">Minimum Stock Level</label>
                                <input type="number" class="form-control" id="minimum_stock" name="minimum_stock" 
                                       value="<?php echo $item['minimum_stock']; ?>" min="0" required>
                                <div class="invalid-feedback">Please enter a valid minimum stock level.</div>
                                <div class="form-hint">Set the threshold for low stock alerts</div>
                            </div>

                            <div class="col-12">
                                <label for="status" class="form-label">Status</label>
                                <select class="form-select" id="status" name="status" required>
                                    <option value="">Select status</option>
                                    <option value="Available" <?php echo $item['status'] === 'Available' ? 'selected' : ''; ?>>Available</option>
                                    <option value="In Maintenance" <?php echo $item['status'] === 'In Maintenance' ? 'selected' : ''; ?>>In Maintenance</option>
                                </select>
                                <div class="invalid-feedback">Please select a status.</div>
                                <div class="form-hint">Note: Low Stock status is automatically set based on quantity</div>
                            </div>

                            <div class="col-12">
                                <label for="description" class="form-label">Description</label>
                                <textarea class="form-control" id="description" name="description" 
                                          style="height: 100px"><?php echo htmlspecialchars($item['description'] ?? ''); ?></textarea>
                                <div class="form-hint">Optional: Add any additional details about the item</div>
                            </div>

                            <div class="col-12 d-flex gap-3 justify-content-end mt-4">
                                <a href="inventory.php" class="btn btn-light btn-submit px-4">
                                    <i class="bi bi-x-lg me-2"></i>Cancel
                                </a>
                                <button type="submit" class="btn btn-success btn-submit">
                                    <i class="bi bi-check-lg me-2"></i>Save Changes
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
    });
    </script>
</body>
</html>