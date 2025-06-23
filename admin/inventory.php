<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../login.php');
    exit();
}

// Get filter parameters
$filter = isset($_GET['filter']) ? $_GET['filter'] : '';
$search = isset($_GET['search']) ? $_GET['search'] : '';
$status = isset($_GET['status']) ? $_GET['status'] : '';
$stock = isset($_GET['stock']) ? $_GET['stock'] : '';
$sort = isset($_GET['sort']) ? $_GET['sort'] : 'last_updated';

// Build the SQL query based on filters
$where_conditions = [];
$params = [];

// Basic filter
switch ($filter) {
    case 'low_stock':
        $where_conditions[] = 'total_quantity <= minimum_stock';
        break;
    case 'available':
        $where_conditions[] = "status = 'Available'";
        break;
    case 'maintenance':
        $where_conditions[] = "status = 'In Maintenance'";
        break;
}

// Advanced filters
if (!empty($search)) {
    $where_conditions[] = "name LIKE ?";
    $params[] = "%$search%";
}

if (!empty($status)) {
    $where_conditions[] = "status = ?";
    $params[] = $status;
}

if (!empty($stock)) {
    switch ($stock) {
        case 'critical':
            $where_conditions[] = "total_quantity BETWEEN 0 AND 2";
            break;
        case 'low':
            $where_conditions[] = "total_quantity BETWEEN 3 AND 5";
            break;
        case 'medium':
            $where_conditions[] = "total_quantity BETWEEN 6 AND 10";
            break;
        case 'high':
            $where_conditions[] = "total_quantity > 10";
            break;
    }
}

// Build WHERE clause
$where_clause = '';
if (!empty($where_conditions)) {
    $where_clause = 'WHERE ' . implode(' AND ', $where_conditions);
}

// Build ORDER BY clause
$order_clause = 'ORDER BY ';
switch ($sort) {
    case 'name':
        $order_clause .= 'name ASC';
        break;
    case 'total_quantity':
        $order_clause .= 'total_quantity DESC';
        break;
    case 'available':
        $order_clause .= '(total_quantity - borrowed) DESC';
        break;
    case 'last_updated':
    default:
        $order_clause .= 'last_updated DESC';
        break;
}

// Get inventory statistics
try {
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM inventory");
    $total_items = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

    $stmt = $pdo->query("SELECT COUNT(*) as low_stock FROM inventory WHERE total_quantity <= minimum_stock");
    $low_stock = $stmt->fetch(PDO::FETCH_ASSOC)['low_stock'];

    $stmt = $pdo->query("SELECT COUNT(*) as available FROM inventory WHERE status = 'Available'");
    $available = $stmt->fetch(PDO::FETCH_ASSOC)['available'];

    $stmt = $pdo->query("SELECT COUNT(*) as maintenance FROM inventory WHERE status = 'In Maintenance'");
    $maintenance = $stmt->fetch(PDO::FETCH_ASSOC)['maintenance'];

    // Get filtered inventory items
    $sql = "SELECT * FROM inventory " . $where_clause . " " . $order_clause;
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $inventory_items = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $total_items = $low_stock = $available = $maintenance = 0;
    $inventory_items = [];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inventory Management - PGOM Facilities</title>
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
            
            .filter-buttons {
                flex-wrap: wrap;
                gap: 0.5rem;
            }
            
            .filter-btn {
                flex: 1;
                min-width: 120px;
                text-align: center;
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
            
            /* Mobile card layout for inventory */
            .mobile-inventory-card {
                display: block;
                background: var(--bg-secondary);
                border: 1px solid var(--border-color);
                border-radius: 8px;
                padding: 1rem;
                margin-bottom: 1rem;
                box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            }
            
            .mobile-inventory-card .inventory-header {
                display: flex;
                justify-content: space-between;
                align-items: flex-start;
                margin-bottom: 0.75rem;
                padding-bottom: 0.75rem;
                border-bottom: 1px solid var(--border-color);
            }
            
            .mobile-inventory-card .item-info {
                display: flex;
                align-items: center;
                gap: 0.5rem;
            }
            
            .mobile-inventory-card .item-icon {
                width: 40px;
                height: 40px;
                background: #43a047;
                color: white;
                border-radius: 8px;
                display: flex;
                align-items: center;
                justify-content: center;
                font-weight: 600;
                font-size: 1rem;
            }
            
            .mobile-inventory-card .item-name {
                font-weight: 600;
                color: var(--text-primary);
                font-size: 0.9rem;
            }
            
            .mobile-inventory-card .item-description {
                color: var(--text-secondary);
                font-size: 0.8rem;
            }
            
            .mobile-inventory-card .inventory-details {
                margin-bottom: 0.75rem;
            }
            
            .mobile-inventory-card .detail-item {
                display: flex;
                justify-content: space-between;
                margin-bottom: 0.5rem;
                font-size: 0.8rem;
            }
            
            .mobile-inventory-card .detail-label {
                color: var(--text-secondary);
                font-weight: 500;
            }
            
            .mobile-inventory-card .detail-value {
                color: var(--text-primary);
                text-align: right;
            }
            
            .mobile-inventory-card .action-buttons {
                display: flex;
                gap: 0.5rem;
                flex-wrap: wrap;
            }
            
            .mobile-inventory-card .btn {
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
            
            /* Filter buttons - Extra small screens */
            .d-flex.gap-2.flex-wrap {
                flex-direction: column;
                gap: 0.5rem !important;
            }
            
            .filter-btn {
                width: 100%;
                max-width: none;
                min-width: auto;
                font-size: 0.85rem;
                padding: 0.6rem 1rem;
            }
            
            .btn.btn-success {
                font-size: 0.9rem;
                padding: 0.8rem 1rem;
            }
            
            .mobile-inventory-card {
                padding: 0.75rem;
            }
            
            .mobile-inventory-card .item-name {
                font-size: 0.85rem;
            }
            
            .mobile-inventory-card .item-description {
                font-size: 0.75rem;
            }
            
            .mobile-inventory-card .detail-item {
                font-size: 0.75rem;
            }
            
            .mobile-inventory-card .btn {
                font-size: 0.7rem;
                padding: 0.3rem 0.4rem;
            }
        }
        
        @media (max-width: 480px) {
            .header-title {
                font-size: 1rem !important;
            }
            
            /* Filter buttons - Very small screens */
            .filter-btn {
                font-size: 0.8rem;
                padding: 0.5rem 0.75rem;
            }
            
            .filter-btn i {
                font-size: 0.8rem;
            }
            
            .btn.btn-success {
                font-size: 0.85rem;
                padding: 0.7rem 0.9rem;
            }
            
            .mobile-inventory-card .inventory-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 0.5rem;
            }
            
            .mobile-inventory-card .item-info {
                width: 100%;
            }
            
            .mobile-inventory-card .detail-item {
                flex-direction: column;
                align-items: flex-start;
                gap: 0.25rem;
            }
            
            .mobile-inventory-card .detail-value {
                text-align: left;
            }
            
            .mobile-inventory-card .action-buttons {
                flex-direction: column;
            }
            
            .mobile-inventory-card .btn {
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
            .table-responsive {
                display: none;
            }
            
            .mobile-inventory {
                display: block;
            }
        }
        
        @media (min-width: 769px) {
            .mobile-inventory {
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
            background: var(--bg-secondary);
            border-radius: 8px;
            padding: 1.25rem;
            height: 100%;
            border: 1px solid var(--border-color);
            transition: all 0.2s ease;
            color: var(--text-primary);
        }

        .stat-card:hover {
            border-color: #43a047;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.04);
            background-color: var(--hover-bg);
        }

        .stat-card .card-title {
            color: var(--text-secondary);
            font-size: 0.875rem;
            font-weight: 500;
            margin-bottom: 0;
            text-transform: uppercase;
        }

        .stat-card .stat-icon {
            font-size: 1.5rem;
            opacity: 0.9;
        }

        .stat-card .stat-value {
            font-size: 1.75rem;
            font-weight: 600;
            color: var(--text-primary);
            margin: 0.5rem 0;
            line-height: 1;
        }

        .stat-card .text-muted {
            font-size: 0.875rem;
            color: var(--text-secondary) !important;
        }

        /* Status colors */
        .stat-card.total .stat-icon { color: #0d6efd; }
        .stat-card.low-stock .stat-icon { color: #dc3545; }
        .stat-card.available .stat-icon { color: #198754; }
        .stat-card.maintenance .stat-icon { color: #ffc107; }

        /* Filter buttons */
        .filter-btn {
            border: 1px solid var(--border-color);
            padding: 0.6rem 1.2rem;
            font-size: 0.875rem;
            font-weight: 500;
            color: var(--text-secondary);
            background: var(--bg-secondary);
            border-radius: 8px;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            text-decoration: none;
            cursor: pointer;
        }

        .filter-btn:hover {
            color: #43a047;
            background: var(--hover-bg);
            border-color: #43a047;
            transform: translateY(-1px);
            box-shadow: 0 2px 4px rgba(67, 160, 71, 0.1);
        }

        .filter-btn.active {
            background: #43a047;
            color: #ffffff;
            border-color: #43a047;
            box-shadow: 0 2px 8px rgba(67, 160, 71, 0.3);
        }

        .filter-btn i {
            font-size: 1rem;
        }

        /* Advanced Filters Card */
        #advancedFilters {
            border: 1px solid var(--border-color);
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        }

        #advancedFilters .card-header {
            background: var(--bg-unified);
            border-bottom: 1px solid var(--border-color);
            border-radius: 8px 8px 0 0;
        }

        #advancedFilters .card-header h6 {
            color: var(--text-primary);
            font-weight: 600;
        }

        #advancedFilters .form-label {
            font-weight: 500;
            color: var(--text-primary);
            font-size: 0.875rem;
        }

        #advancedFilters .form-control,
        #advancedFilters .form-select {
            border: 1px solid var(--border-color);
            border-radius: 6px;
            background: var(--bg-secondary);
            color: var(--text-primary);
        }

        #advancedFilters .form-control:focus,
        #advancedFilters .form-select:focus {
            border-color: #43a047;
            box-shadow: 0 0 0 0.2rem rgba(67, 160, 71, 0.25);
        }

        /* Table styles */
        .inventory-table {
            background: var(--bg-secondary);
            border: 1px solid var(--border-color);
            border-radius: 8px;
            overflow: hidden;
        }

        .inventory-table th {
            background: var(--bg-unified);
            font-weight: 600;
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: var(--text-primary);
            padding: 0.75rem 1rem;
            border-bottom: 1px solid var(--border-color);
        }

        .inventory-table td {
            padding: 0.75rem 1rem;
            vertical-align: middle;
            color: var(--text-primary);
            font-size: 0.875rem;
            border-bottom: 1px solid var(--border-color);
            background-color: transparent !important;
        }

        .inventory-table tbody tr:hover {
            background-color: var(--hover-bg) !important;
        }

        .inventory-table tbody tr:hover td {
            background-color: transparent !important;
            color: var(--text-primary);
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

        .status-badge.available {
            background: #1b3326;
            color: #66bb6a;
        }

        .status-badge.maintenance {
            background: #2c1810;
            color: #ffa726;
        }

        .status-badge.low-stock {
            background: #2d1215;
            color: #ef5350;
        }

        /* Action buttons */
        .action-btn {
            padding: 0.5rem;
            font-size: 1rem;
            color: var(--text-secondary);
            background: var(--hover-bg);
            border: 1px solid var(--border-color);
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
            transform: translateY(-2px);
            background: var(--bg-unified);
        }

        .action-btn.edit-btn {
            color: #0d6efd;
            background: rgba(13, 110, 253, 0.1);
            border: 1px solid transparent;
        }

        .action-btn.edit-btn:hover {
            background: rgba(13, 110, 253, 0.15);
            color: #0d6efd;
            border-color: rgba(13, 110, 253, 0.2);
            box-shadow: 0 3px 5px rgba(13, 110, 253, 0.1);
        }

        .action-btn.delete-btn {
            color: #dc3545;
            background: rgba(220, 53, 69, 0.1);
            border: 1px solid transparent;
        }

        .action-btn.delete-btn:hover {
            background: rgba(220, 53, 69, 0.15);
            color: rgb(163, 26, 39);
            border-color: rgba(220, 53, 69, 0.2);
            box-shadow: 0 3px 5px rgba(220, 53, 69, 0.1);
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
            bottom: 100%;
            left: 50%;
            transform: translateX(-50%);
            padding: 0.25rem 0.5rem;
            background: var(--bg-unified);
            color: var(--text-primary);
            font-size: 0.75rem;
            border-radius: 4px;
            white-space: nowrap;
            opacity: 0;
            visibility: hidden;
            transition: all 0.2s ease;
            border: 1px solid var(--border-color);
        }

        .action-btn:hover::after {
            opacity: 1;
            visibility: visible;
            bottom: calc(100% + 5px);
        }

        /* View Item Modal Styles */
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

        /* Delete Modal Styles */
        .delete-modal .modal-content {
            border: none;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        .delete-modal .modal-header {
            background: white;
            border-bottom: 2px solid #dc3545;
            border-radius: 8px 8px 0 0;
            padding: 1rem 1.5rem;
        }

        .delete-modal .modal-title {
            color: #dc3545;
            font-weight: 600;
            font-size: 1.1rem;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .delete-modal .modal-body {
            padding: 1.5rem;
        }

        .delete-modal .modal-footer {
            background: white;
            border-top: 1px solid #e0e0e0;
            padding: 1rem 1.5rem;
            border-radius: 0 0 8px 8px;
        }

        .delete-modal .btn-close {
            background: none;
            font-size: 1.5rem;
            color: #666;
        }

        .delete-modal .btn-close:hover {
            color: #333;
        }

        /* Fixed column widths */
        .inventory-table th:nth-child(1),
        .inventory-table td:nth-child(1) { 
            width: 250px;
            max-width: 250px;
        }
        .inventory-table th:nth-child(2),
        .inventory-table td:nth-child(2),
        .inventory-table th:nth-child(3),
        .inventory-table td:nth-child(3),
        .inventory-table th:nth-child(4),
        .inventory-table td:nth-child(4),
        .inventory-table th:nth-child(5),
        .inventory-table td:nth-child(5) { 
            width: 100px;
            max-width: 100px;
        }
        .inventory-table th:nth-child(6),
        .inventory-table td:nth-child(6) { 
            width: 120px;
            max-width: 120px;
        }
        .inventory-table th:nth-child(7),
        .inventory-table td:nth-child(7) { 
            width: 150px;
            max-width: 150px;
        }
        .inventory-table th:nth-child(8),
        .inventory-table td:nth-child(8) { 
            width: 140px;
            max-width: 140px;
        }

        /* Add these styles */
        .alert-container {
            position: relative;
            z-index: 1000;
        }

        .alert {
            border: none;
            border-radius: 8px;
            padding: 1rem;
            margin-bottom: 0;
            animation: slideDown 0.3s ease-out;
        }

        .alert.alert-success {
            background-color: #d4edda;
            color: #155724;
            border-left: 4px solid #28a745;
        }

        .alert.alert-danger {
            background-color: #f8d7da;
            color: #721c24;
            border-left: 4px solid #dc3545;
        }

        @keyframes slideDown {
            from {
                transform: translateY(-100%);
                opacity: 0;
            }
            to {
                transform: translateY(0);
                opacity: 1;
            }
        }

        /* Remove the toast container styles */
        .toast-container {
            display: none;
        }

        /* Add these status badge styles */
        .status-badge.bg-warning {
            background-color: #fff3cd !important;
            color: #856404 !important;
        }
        
        .status-badge.text-warning {
            color: #856404 !important;
        }
        
        .status-badge.bg-success {
            background-color: #d4edda !important;
            color: #155724 !important;
        }
        
        .status-badge.text-success {
            color: #155724 !important;
        }
        
        .status-badge.bg-secondary {
            background-color: #e2e3e5 !important;
            color: #383d41 !important;
        }
        
        .status-badge.text-secondary {
            color: #383d41 !important;
        }

        /* Filter and Actions Section - Mobile Responsive */
        .d-flex.justify-content-between.align-items-center.mb-4 {
            flex-direction: column;
            gap: 1rem;
            align-items: stretch !important;
        }
        
        .d-flex.gap-2.flex-wrap {
            justify-content: center;
            gap: 0.5rem !important;
        }
        
        .filter-btn {
            flex: 1;
            min-width: 120px;
            max-width: 150px;
            font-size: 0.8rem;
            padding: 0.5rem 0.75rem;
            white-space: nowrap;
            text-align: center;
        }
        
        .filter-btn i {
            font-size: 0.9rem;
        }
        
        .btn.btn-success {
            width: 100%;
            justify-content: center;
            padding: 0.75rem 1rem;
            font-size: 0.9rem;
        }
        
        .filter-buttons {
            flex-wrap: wrap;
            gap: 0.5rem;
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
                        <i class="bi bi-box-seam me-2"></i>
                        Inventory Management
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

            <!-- Alert Container -->
            <div id="alertContainer" class="alert-container mb-4" style="display: none;">
                <div class="alert d-flex align-items-center justify-content-between" role="alert">
                    <div class="d-flex align-items-center">
                        <i class="bi me-2"></i>
                        <span class="alert-message"></span>
                    </div>
                    <button type="button" class="btn-close" onclick="hideAlert()"></button>
                </div>
            </div>

            <!-- Inventory Content -->
            <div class="container-fluid py-4 px-4">
                <!-- Statistics Cards -->
                <div class="row g-3 mb-4">
                    <div class="col-md-3">
                        <div class="stat-card total">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="card-title">Total Items</h6>
                                    <h3 class="stat-value"><?php echo $total_items; ?></h3>
                                    <p class="text-muted mb-0">In Inventory</p>
                                </div>
                                <div class="stat-icon">
                                    <i class="bi bi-box-seam"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stat-card low-stock">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="card-title">Low Stock</h6>
                                    <h3 class="stat-value"><?php echo $low_stock; ?></h3>
                                    <p class="text-muted mb-0">Items</p>
                                </div>
                                <div class="stat-icon">
                                    <i class="bi bi-exclamation-triangle"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stat-card available">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="card-title">Available</h6>
                                    <h3 class="stat-value"><?php echo $available; ?></h3>
                                    <p class="text-muted mb-0">Items</p>
                                </div>
                                <div class="stat-icon">
                                    <i class="bi bi-check-circle"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stat-card maintenance">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="card-title">Maintenance</h6>
                                    <h3 class="stat-value"><?php echo $maintenance; ?></h3>
                                    <p class="text-muted mb-0">Items</p>
                                </div>
                                <div class="stat-icon">
                                    <i class="bi bi-tools"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Filter and Actions -->
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <div class="d-flex gap-2 flex-wrap">
                        <button class="filter-btn <?php echo $filter === '' ? 'active' : ''; ?>" onclick="window.location.href='inventory.php'">
                            <i class="bi bi-grid-3x3-gap me-1"></i>All Items
                        </button>
                        <button class="filter-btn <?php echo $filter === 'low_stock' ? 'active' : ''; ?>" onclick="window.location.href='inventory.php?filter=low_stock'">
                            <i class="bi bi-exclamation-triangle me-1"></i>Low Stock
                        </button>
                        <button class="filter-btn <?php echo $filter === 'available' ? 'active' : ''; ?>" onclick="window.location.href='inventory.php?filter=available'">
                            <i class="bi bi-check-circle me-1"></i>Available
                        </button>
                        <button class="filter-btn <?php echo $filter === 'maintenance' ? 'active' : ''; ?>" onclick="window.location.href='inventory.php?filter=maintenance'">
                            <i class="bi bi-tools me-1"></i>In Maintenance
                        </button>
                        <button class="filter-btn" onclick="showAdvancedFilters()">
                            <i class="bi bi-funnel me-1"></i>Advanced
                        </button>
                    </div>
                    <a href="add_item.php" class="btn btn-success">
                        <i class="bi bi-plus-lg me-2"></i>Add New Item
                    </a>
                </div>

                <!-- Advanced Filters (Hidden by default) -->
                <div id="advancedFilters" class="card mb-4" style="display: none;">
                    <div class="card-header bg-light">
                        <h6 class="mb-0">
                            <i class="bi bi-funnel me-2"></i>Advanced Filters
                        </h6>
                    </div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-md-3">
                                <label for="searchItem" class="form-label">Search Item</label>
                                <input type="text" class="form-control" id="searchItem" placeholder="Search by name...">
                            </div>
                            <div class="col-md-3">
                                <label for="statusFilter" class="form-label">Status</label>
                                <select class="form-select" id="statusFilter">
                                    <option value="">All Status</option>
                                    <option value="Available">Available</option>
                                    <option value="Low Stock">Low Stock</option>
                                    <option value="In Maintenance">In Maintenance</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label for="stockFilter" class="form-label">Stock Level</label>
                                <select class="form-select" id="stockFilter">
                                    <option value="">All Levels</option>
                                    <option value="critical">Critical (0-2)</option>
                                    <option value="low">Low (3-5)</option>
                                    <option value="medium">Medium (6-10)</option>
                                    <option value="high">High (10+)</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label for="sortBy" class="form-label">Sort By</label>
                                <select class="form-select" id="sortBy">
                                    <option value="name">Name</option>
                                    <option value="total_quantity">Total Quantity</option>
                                    <option value="available">Available</option>
                                    <option value="last_updated">Last Updated</option>
                                </select>
                            </div>
                        </div>
                        <div class="row mt-3">
                            <div class="col-12">
                                <button type="button" class="btn btn-primary me-2" onclick="applyAdvancedFilters()">
                                    <i class="bi bi-search me-1"></i>Apply Filters
                                </button>
                                <button type="button" class="btn btn-outline-secondary" onclick="clearAdvancedFilters()">
                                    <i class="bi bi-x-circle me-1"></i>Clear All
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Inventory Table -->
                <div class="inventory-table">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead>
                                <tr>
                                    <th>Item Name</th>
                                    <th class="text-center">Total</th>
                                    <th class="text-center">Borrowed</th>
                                    <th class="text-center">Available</th>
                                    <th class="text-center">Min Stock</th>
                                    <th class="text-center">Status</th>
                                    <th>Last Updated</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($inventory_items as $item): ?>
                                <tr data-item-id="<?php echo $item['id']; ?>">
                                    <td class="fw-medium text-truncate" title="<?php echo htmlspecialchars($item['name']); ?>">
                                        <?php echo htmlspecialchars($item['name']); ?>
                                    </td>
                                    <td class="text-center"><?php echo $item['total_quantity']; ?></td>
                                    <td class="text-center"><?php echo $item['borrowed']; ?></td>
                                    <td class="text-center"><?php echo $item['total_quantity'] - $item['borrowed']; ?></td>
                                    <td class="text-center"><?php echo $item['minimum_stock']; ?></td>
                                    <td class="text-center">
                                        <span class="status-badge <?php 
                                            if ($item['total_quantity'] <= $item['minimum_stock']) {
                                                echo 'bg-warning bg-opacity-10 text-warning';
                                                // Update status to Low Stock if not in maintenance
                                                if ($item['status'] !== 'In Maintenance') {
                                                    $update_stmt = $pdo->prepare("UPDATE inventory SET status = 'Low Stock' WHERE id = ? AND status != 'In Maintenance'");
                                                    $update_stmt->execute([$item['id']]);
                                                    $item['status'] = 'Low Stock';
                                                }
                                            } elseif ($item['status'] === 'In Maintenance') {
                                                echo 'bg-secondary bg-opacity-10 text-secondary';
                                            } else {
                                                echo 'bg-success bg-opacity-10 text-success';
                                                // Update status back to Available if stock is good
                                                if ($item['status'] === 'Low Stock') {
                                                    $update_stmt = $pdo->prepare("UPDATE inventory SET status = 'Available' WHERE id = ?");
                                                    $update_stmt->execute([$item['id']]);
                                                    $item['status'] = 'Available';
                                                }
                                            }
                                        ?>">
                                            <?php echo $item['status']; ?>
                                        </span>
                                    </td>
                                    <td><?php echo date('M d, Y H:i', strtotime($item['last_updated'])); ?></td>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <button class="action-btn" onclick="viewItem(<?php echo $item['id']; ?>)" title="View Details">
                                                <i class="bi bi-eye-fill"></i>
                                            </button>
                                            <button class="action-btn text-primary" onclick="location.href='edit_item.php?id=<?php echo $item['id']; ?>'" title="Edit Item">
                                                <i class="bi bi-pencil-fill"></i>
                                            </button>
                                            <button class="action-btn text-danger" onclick="deleteItem(<?php echo $item['id']; ?>, '<?php echo htmlspecialchars($item['name'], ENT_QUOTES); ?>')" title="Delete Item">
                                                <i class="bi bi-trash-fill"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Mobile Inventory -->
                <div class="mobile-inventory">
                    <?php foreach ($inventory_items as $item): ?>
                        <div class="mobile-inventory-card">
                            <div class="inventory-header">
                                <div class="item-info">
                                    <div class="item-icon">
                                        <i class="bi bi-box-seam"></i>
                                    </div>
                                    <div>
                                        <div class="item-name"><?php echo htmlspecialchars($item['name']); ?></div>
                                        <div class="item-description"><?php echo htmlspecialchars($item['description']); ?></div>
                                    </div>
                                </div>
                                <div>
                                    <?php
                                    $status_class = '';
                                    $status_text = '';
                                    if ($item['total_quantity'] <= $item['minimum_stock']) {
                                        $status_class = 'low-stock';
                                        $status_text = 'Low Stock';
                                    } elseif ($item['status'] === 'Available') {
                                        $status_class = 'available';
                                        $status_text = 'Available';
                                    } elseif ($item['status'] === 'In Maintenance') {
                                        $status_class = 'maintenance';
                                        $status_text = 'Maintenance';
                                    }
                                    ?>
                                    <span class="status-badge <?php echo $status_class; ?>">
                                        <?php echo $status_text; ?>
                                    </span>
                                </div>
                            </div>
                            
                            <div class="inventory-details">
                                <div class="detail-item">
                                    <span class="detail-label">Total Stock:</span>
                                    <span class="detail-value"><?php echo $item['total_quantity']; ?></span>
                                </div>
                                <div class="detail-item">
                                    <span class="detail-label">Borrowed:</span>
                                    <span class="detail-value"><?php echo $item['borrowed']; ?></span>
                                </div>
                                <div class="detail-item">
                                    <span class="detail-label">Available:</span>
                                    <span class="detail-value"><?php echo $item['total_quantity'] - $item['borrowed']; ?></span>
                                </div>
                                <div class="detail-item">
                                    <span class="detail-label">Min Stock:</span>
                                    <span class="detail-value"><?php echo $item['minimum_stock']; ?></span>
                                </div>
                                <div class="detail-item">
                                    <span class="detail-label">Last Updated:</span>
                                    <span class="detail-value">
                                        <?php echo date('M d, Y h:i A', strtotime($item['last_updated'])); ?>
                                    </span>
                                </div>
                            </div>
                            
                            <div class="action-buttons">
                                <button class="btn btn-outline-primary btn-sm" onclick="viewItem(<?php echo $item['id']; ?>)" title="View">
                                    <i class="bi bi-eye"></i> View
                                </button>
                                <button class="btn btn-outline-primary btn-sm" onclick="location.href='edit_item.php?id=<?php echo $item['id']; ?>'" title="Edit">
                                    <i class="bi bi-pencil"></i> Edit
                                </button>
                                <button class="btn btn-outline-danger btn-sm" onclick="deleteItem(<?php echo $item['id']; ?>, '<?php echo htmlspecialchars($item['name'], ENT_QUOTES); ?>')" title="Delete">
                                    <i class="bi bi-trash"></i> Delete
                                </button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Add View Item Modal -->
    <div class="modal fade view-item-modal" id="viewItemModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="bi bi-box-seam"></i>
                        Item Details
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div id="loadingState" class="loading-state">
                        <div class="spinner-border text-success"></div>
                        <div>Loading item information...</div>
                    </div>
                    
                    <div id="itemInfo" style="display: none;">
                        <div class="item-info-section">
                            <div class="section-title">
                                <i class="bi bi-info-circle"></i>
                                Item Details
                            </div>
                            <div class="info-grid">
                                <div class="info-item">
                                    <div class="info-label">Item Name</div>
                                    <div class="info-value" id="viewName"></div>
                                </div>
                                <div class="info-item">
                                    <div class="info-label">Description</div>
                                    <div class="info-value" id="viewDescription"></div>
                                </div>
                                <div class="info-item">
                                    <div class="info-label">Status</div>
                                    <div class="info-value" id="viewStatus"></div>
                                </div>
                            </div>
                        </div>

                        <div class="item-info-section">
                            <div class="section-title">
                                <i class="bi bi-graph-up"></i>
                                Quantity Information
                            </div>
                            <div class="info-grid">
                                <div class="info-item">
                                    <div class="info-label">Total Stock</div>
                                    <div class="info-value" id="viewTotalQty"></div>
                                </div>
                                <div class="info-item">
                                    <div class="info-label">Currently Available</div>
                                    <div class="info-value" id="viewAvailable"></div>
                                </div>
                                <div class="info-item">
                                    <div class="info-label">Currently Borrowed</div>
                                    <div class="info-value" id="viewBorrowed"></div>
                                </div>
                            </div>
                        </div>

                        <div class="item-info-section">
                            <div class="section-title">
                                <i class="bi bi-gear"></i>
                                Stock Management
                            </div>
                            <div class="info-grid">
                                <div class="info-item">
                                    <div class="info-label">Minimum Stock Level</div>
                                    <div class="info-value" id="viewMinStock"></div>
                                </div>
                                <div class="info-item">
                                    <div class="info-label">Last Updated</div>
                                    <div class="info-value" id="viewLastUpdated"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-success" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Update Delete Modal -->
    <div class="modal fade delete-modal" id="deleteModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="bi bi-exclamation-triangle"></i>
                        Delete Item
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to delete <strong id="deleteItemName"></strong>?</p>
                    <p class="text-danger mb-0"><i class="bi bi-exclamation-circle me-2"></i>This action cannot be undone.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                    <a href="#" id="confirmDelete" class="btn btn-danger">
                        <i class="bi bi-trash me-2"></i>Delete Item
                    </a>
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
        
        // Check for message in session storage
        window.addEventListener('load', function() {
            const message = sessionStorage.getItem('inventoryMessage');
            if (message) {
                showAlert(message, true);
                sessionStorage.removeItem('inventoryMessage');
            }
        });

        function showAlert(message, isSuccess = true) {
            const alertContainer = document.getElementById('alertContainer');
            const alert = alertContainer.querySelector('.alert');
            const alertMessage = alertContainer.querySelector('.alert-message');
            const alertIcon = alertContainer.querySelector('.bi');
            
            // Set the message
            alertMessage.textContent = message;
            
            // Set the appropriate classes and icon
            if (isSuccess) {
                alert.className = 'alert alert-success d-flex align-items-center justify-content-between';
                alertIcon.className = 'bi bi-check-circle-fill me-2';
            } else {
                alert.className = 'alert alert-danger d-flex align-items-center justify-content-between';
                alertIcon.className = 'bi bi-exclamation-circle-fill me-2';
            }
            
            // Show the alert
            alertContainer.style.display = 'block';
            
            // Auto hide after 5 seconds
            setTimeout(hideAlert, 5000);
        }

        function hideAlert() {
            const alertContainer = document.getElementById('alertContainer');
            alertContainer.style.display = 'none';
        }

        function deleteItem(id, name) {
            document.getElementById('deleteItemName').textContent = name;
            document.getElementById('confirmDelete').onclick = function(e) {
                e.preventDefault();
                
                // Send delete request
                fetch('delete_item_ajax.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: 'id=' + id
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Close modal
                        bootstrap.Modal.getInstance(document.getElementById('deleteModal')).hide();
                        
                        // Show success message
                        showAlert('Item deleted successfully');
                        
                        // Remove the row from table
                        const row = document.querySelector(`tr[data-item-id="${id}"]`);
                        if (row) {
                            row.remove();
                        } else {
                            // Reload the page if row not found
                            window.location.reload();
                        }
                    } else {
                        throw new Error(data.message || 'Failed to delete item');
                    }
                })
                .catch(error => {
                    showAlert(error.message, false);
                });
            };
            
            var modal = new bootstrap.Modal(document.getElementById('deleteModal'));
            modal.show();
        }

        function viewItem(id) {
            const modal = new bootstrap.Modal(document.getElementById('viewItemModal'));
            const loadingState = document.getElementById('loadingState');
            const itemInfo = document.getElementById('itemInfo');
            
            modal.show();
            loadingState.style.display = 'block';
            itemInfo.style.display = 'none';
            
            fetch(`get_item.php?id=${id}`)
                .then(response => response.json())
                .then(item => {
                    document.getElementById('viewName').textContent = item.name || '';
                    document.getElementById('viewStatus').textContent = item.status || '';
                    document.getElementById('viewTotalQty').textContent = item.total_quantity || '0';
                    document.getElementById('viewAvailable').textContent = (item.total_quantity - item.borrowed) || '0';
                    document.getElementById('viewBorrowed').textContent = item.borrowed || '0';
                    document.getElementById('viewMinStock').textContent = item.minimum_stock || '0';
                    document.getElementById('viewLastUpdated').textContent = new Date(item.last_updated).toLocaleString();
                    document.getElementById('viewDescription').textContent = item.description || '';
                    
                    loadingState.style.display = 'none';
                    itemInfo.style.display = 'block';
                })
                .catch(error => {
                    console.error('Error:', error);
                    loadingState.innerHTML = `
                        <div class="text-danger">
                            <i class="bi bi-exclamation-circle"></i>
                            <div>Error loading item data</div>
                        </div>
                    `;
                });
        }

        function editItem(id) {
            // Redirect to edit_item.php with the item ID
            window.location.href = `edit_item.php?id=${id}`;
        }

        // Advanced Filters Functions
        function showAdvancedFilters() {
            const advancedFilters = document.getElementById('advancedFilters');
            if (advancedFilters.style.display === 'none') {
                advancedFilters.style.display = 'block';
                // Smooth scroll to filters
                advancedFilters.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
            } else {
                advancedFilters.style.display = 'none';
            }
        }

        function applyAdvancedFilters() {
            const searchTerm = document.getElementById('searchItem').value;
            const statusFilter = document.getElementById('statusFilter').value;
            const stockFilter = document.getElementById('stockFilter').value;
            const sortBy = document.getElementById('sortBy').value;

            // Build query parameters
            const params = new URLSearchParams();
            if (searchTerm) params.append('search', searchTerm);
            if (statusFilter) params.append('status', statusFilter);
            if (stockFilter) params.append('stock', stockFilter);
            if (sortBy) params.append('sort', sortBy);

            // Redirect with filters
            window.location.href = 'inventory.php?' + params.toString();
        }

        function clearAdvancedFilters() {
            document.getElementById('searchItem').value = '';
            document.getElementById('statusFilter').value = '';
            document.getElementById('stockFilter').value = '';
            document.getElementById('sortBy').value = 'name';
            
            // Hide advanced filters
            document.getElementById('advancedFilters').style.display = 'none';
            
            // Redirect to base inventory page
            window.location.href = 'inventory.php';
        }

        // Real-time search functionality
        document.addEventListener('DOMContentLoaded', function() {
            const searchInput = document.getElementById('searchItem');
            if (searchInput) {
                searchInput.addEventListener('input', function() {
                    const searchTerm = this.value.toLowerCase();
                    const tableRows = document.querySelectorAll('.inventory-table tbody tr');
                    const mobileCards = document.querySelectorAll('.mobile-inventory-card');
                    
                    // Filter table rows
                    tableRows.forEach(row => {
                        const itemName = row.querySelector('td:first-child').textContent.toLowerCase();
                        if (itemName.includes(searchTerm)) {
                            row.style.display = '';
                        } else {
                            row.style.display = 'none';
                        }
                    });
                    
                    // Filter mobile cards
                    mobileCards.forEach(card => {
                        const itemName = card.querySelector('.item-name').textContent.toLowerCase();
                        if (itemName.includes(searchTerm)) {
                            card.style.display = '';
                        } else {
                            card.style.display = 'none';
                        }
                    });
                });
            }
        });
    </script>
</body>
</html>