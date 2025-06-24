<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'user') {
    header('Location: ../login.php');
    exit();
}

// List of facilities (can be fetched from DB if needed)
$facilities = [
    'Training Center',
    'Evacuation Center',
    'Event Center',
    'Grand Plaza',
    'Back Door'
];

// 1. Map facility name to ID
$facility_map = [];
try {
    $stmt = $pdo->query("SELECT id, name FROM facilities");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $facility_map[$row['name']] = $row['id'];
    }
} catch (PDOException $e) {}

// Get selected facility ID
$selected_facility_id = null;
if (isset($_GET['facility'])) {
    $selected_facility = $_GET['facility'];
    $selected_facility_id = $facility_map[$selected_facility] ?? null;
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['facility_id'])) {
    $selected_facility_id = $_POST['facility_id'];
}

// Fetch available equipment counts
$equipment = [];
$sound_system_components = [];
try {
    // Get regular equipment
    $stmt = $pdo->query("SELECT i.*, g.name as group_name 
                         FROM inventory i 
                         LEFT JOIN equipment_groups g ON i.group_id = g.id 
                         WHERE i.status = 'Available'");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        if ($row['is_group']) {
            $equipment[$row['name']] = $row['total_quantity'] - $row['borrowed'];
        } else if ($row['group_id']) {
            $sound_system_components[$row['name']] = [
                'available' => $row['total_quantity'] - $row['borrowed'],
                'description' => $row['description']
            ];
        } else {
            $equipment[$row['name']] = $row['total_quantity'] - $row['borrowed'];
        }
    }
} catch (PDOException $e) {
    // Keep defaults if error
}

// Handle form submission
$success = false;
$error = '';
$show_step = 0;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['facility_id'])) {
    $selected_facility_id = $_POST['facility_id'];
    // Validate and process form
    $equipment_selections = [];
    foreach ($equipment as $item_name => $available) {
        if ($item_name === 'Sound System') {
            if (isset($_POST['sound_system'])) {
                $equipment_selections['Sound System'] = true;
            }
        } else {
            $quantity = (int)($_POST[strtolower(str_replace(' ', '_', $item_name))] ?? 0);
            if ($quantity > 0) {
                $equipment_selections[$item_name] = $quantity;
            }
        }
    }

    $event_date = $_POST['event_date'] ?? '';
    $start_time = $_POST['start_time'] ?? '';
    $end_time = $_POST['end_time'] ?? '';
    $start_datetime = $event_date . ' ' . $start_time . ':00';
    $end_datetime = $event_date . ' ' . $end_time . ':00';
    $user_id = $_SESSION['user_id'];
    $request_letter = null;

    // Handle file upload
    if (isset($_FILES['request_letter']) && $_FILES['request_letter']['error'] === UPLOAD_ERR_OK) {
        $ext = strtolower(pathinfo($_FILES['request_letter']['name'], PATHINFO_EXTENSION));
        if ($ext === 'pdf') {
            $upload_dir = '../uploads/';
            if (!is_dir($upload_dir)) mkdir($upload_dir);
            $filename = uniqid('req_') . '.pdf';
            $target = $upload_dir . $filename;
            if (move_uploaded_file($_FILES['request_letter']['tmp_name'], $target)) {
                $request_letter = $filename;
            }
        } else {
            $error = 'Request letter must be a PDF file.';
            $show_step = 2;
        }
    } else if (empty($_FILES['request_letter']['name']) || $_FILES['request_letter']['error'] === UPLOAD_ERR_NO_FILE) {
        $error = 'Request letter is required and must be a PDF file.';
        $show_step = 2;
    }

    // Simple validation
    if (!$start_time || !$end_time || !$event_date) {
        $error = 'Please fill in all required fields.';
        $show_step = 0;
    } else if (!$request_letter) {
        $error = 'Request letter is required and must be a PDF file.';
        $show_step = 2;
    } else {
        try {
            $pdo->beginTransaction();

            // Insert booking
            $stmt = $pdo->prepare("INSERT INTO bookings (user_id, facility_id, start_time, end_time, request_letter, status, created_at) VALUES (?, ?, ?, ?, ?, 'pending', NOW())");
            $stmt->execute([
                $user_id,
                $selected_facility_id,
                $start_datetime,
                $end_datetime,
                $request_letter
            ]);
            $booking_id = $pdo->lastInsertId();

            // Insert equipment selections
            if (!empty($equipment_selections)) {
                $stmt = $pdo->prepare("INSERT INTO booking_equipment (booking_id, equipment_id, quantity) VALUES (?, ?, ?)");
                
                // Get equipment IDs
                $equipment_ids = [];
                $stmt_ids = $pdo->query("SELECT id, name FROM inventory");
                while ($row = $stmt_ids->fetch(PDO::FETCH_ASSOC)) {
                    $equipment_ids[$row['name']] = $row['id'];
                }

                // Insert regular equipment
                foreach ($equipment_selections as $item_name => $quantity) {
                    if ($item_name !== 'Sound System' && isset($equipment_ids[$item_name])) {
                        $stmt->execute([$booking_id, $equipment_ids[$item_name], $quantity]);
                    }
                }

                // Insert sound system components
                if (isset($equipment_selections['Sound System'])) {
                    $stmt->execute([$booking_id, $equipment_ids['Speaker'], 1]);
                    $stmt->execute([$booking_id, $equipment_ids['Mixer'], 1]);
                    $stmt->execute([$booking_id, $equipment_ids['Amplifier'], 1]);
                    $stmt->execute([$booking_id, $equipment_ids['Cables'], 1]);
                    $stmt->execute([$booking_id, $equipment_ids['Microphone'], 1]);
                }
            }

            $pdo->commit();
            $success = true;
        } catch (PDOException $e) {
            $pdo->rollBack();
            $error = 'Error saving booking: ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Make a Booking - PGOM Facilities</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css">
    <style>
        body {
            background: #f6f8fa;
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
        
        /* Custom Date Picker Styles */
        .custom-date-picker {
            position: relative;
        }
        
        .date-picker-calendar {
            position: absolute;
            top: 100%;
            left: 0;
            right: 0;
            background: white;
            border: 1px solid #dee2e6;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            z-index: 1000;
            margin-top: 2px;
            padding: 1rem;
            min-width: 280px;
        }
        
        .calendar-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
            padding-bottom: 0.5rem;
            border-bottom: 1px solid #e9ecef;
        }
        
        .calendar-header span {
            font-weight: 600;
            color: #495057;
        }
        
        .calendar-header button {
            color: #6c757d;
            border: none;
            background: none;
            padding: 0.25rem;
            border-radius: 4px;
            transition: all 0.2s;
        }
        
        .calendar-header button:hover {
            background-color: #f8f9fa;
            color: #495057;
        }
        
        .calendar-weekdays {
            display: grid;
            grid-template-columns: repeat(7, 1fr);
            gap: 2px;
            margin-bottom: 0.5rem;
        }
        
        .calendar-weekdays div {
            text-align: center;
            font-size: 0.75rem;
            font-weight: 600;
            color: #6c757d;
            padding: 0.5rem 0;
        }
        
        .calendar-days {
            display: grid;
            grid-template-columns: repeat(7, 1fr);
            gap: 2px;
        }
        
        .calendar-day {
            aspect-ratio: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 6px;
            cursor: pointer;
            font-size: 0.875rem;
            font-weight: 500;
            transition: all 0.2s;
            position: relative;
            border: 1px solid transparent;
        }
        
        .calendar-day:hover:not(.disabled):not(.booked) {
            background-color: #e9ecef;
            border-color: #adb5bd;
        }
        
        .calendar-day.selected {
            background-color: #28a745;
            color: white;
            border-color: #28a745;
        }
        
        .calendar-day.today {
            background-color: #fff3cd;
            border-color: #ffc107;
            color: #856404;
        }
        
        .calendar-day.disabled {
            color: #adb5bd;
            cursor: not-allowed;
            background-color: #f8f9fa;
        }
        
        .calendar-day.booked {
            background-color: #f8d7da;
            color: #721c24;
            cursor: not-allowed;
            border-color: #f5c6cb;
        }
        
        .calendar-day.booked::after {
            content: '✕';
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            font-size: 1.2rem;
            font-weight: bold;
            color: #dc3545;
        }
        
        .calendar-day.booked:hover {
            background-color: #f8d7da;
        }
        
        .calendar-day.other-month {
            color: #adb5bd;
            background-color: #f8f9fa;
        }
        
        .calendar-day.other-month:hover {
            background-color: #e9ecef;
        }
        
        /* Responsive adjustments */
        @media (max-width: 768px) {
            .date-picker-calendar {
                position: fixed;
                top: 50%;
                left: 50%;
                transform: translate(-50%, -50%);
                width: 90vw;
                max-width: 320px;
                z-index: 1050;
            }
            
            .calendar-day {
                font-size: 0.8rem;
            }
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
            
        .facility-grid-row {
                grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
                gap: 1rem;
                padding: 0 0.5rem;
            }
            
            .facility-card {
                min-height: 160px;
                padding: 1.2rem 1rem 1rem 1rem;
            }
            
            .facility-icon {
                font-size: 1.8rem;
                margin-bottom: 0.5rem;
            }
            
            .facility-card .h4 {
                font-size: 0.95rem;
            }
            
            .reserve-btn {
                font-size: 0.85rem;
                padding: 0.6rem 0;
            }
            
            .form-card {
                margin: 1.5rem 0.5rem 1rem 0.5rem;
                padding: 1.5rem 1rem 1rem 1rem;
            }
            
            .stepper {
                gap: 1.5rem;
                margin-bottom: 1.5rem;
            }
            
            .step {
                font-size: 0.9rem;
            }
            
            .step-circle {
                width: 28px;
                height: 28px;
                font-size: 1rem;
            }
            
            .step-line {
                width: 32px;
            }
            
            .form-section-title {
                font-size: 1rem;
                margin-bottom: 0.75rem;
                margin-top: 1rem;
            }
            
            .container-fluid {
                padding-left: 1rem !important;
                padding-right: 1rem !important;
            }
        }
        
        @media (max-width: 576px) {
            .facility-grid-row {
                grid-template-columns: repeat(2, 1fr);
                gap: 0.75rem;
                padding: 0 0.25rem;
            }
            
            .facility-card {
                min-height: 140px;
                padding: 1rem 0.75rem 0.75rem 0.75rem;
            }
            
            .facility-icon {
                font-size: 1.5rem;
                margin-bottom: 0.4rem;
            }
            
            .facility-card .h4 {
                font-size: 0.85rem;
            }
            
            .reserve-btn {
                font-size: 0.8rem;
                padding: 0.5rem 0;
            }
            
            .form-card {
                margin: 1rem 0.25rem 0.75rem 0.25rem;
                padding: 1rem 0.75rem 0.75rem 0.75rem;
            }
            
            .stepper {
                gap: 1rem;
                margin-bottom: 1rem;
            }
            
            .step {
                font-size: 0.8rem;
            }
            
            .step-circle {
                width: 24px;
                height: 24px;
                font-size: 0.9rem;
            }
            
            .step-line {
                width: 24px;
            }
            
            .form-section-title {
                font-size: 0.9rem;
                margin-bottom: 0.5rem;
            }
            
            .form-label {
                font-size: 0.875rem;
                margin-bottom: 0.25rem;
            }
            
            .form-control, .form-select {
                font-size: 0.875rem;
                padding: 0.6rem 0.75rem;
            }
            
            .btn {
                font-size: 0.875rem;
                padding: 0.6rem 1.5rem;
            }
        }
        
        @media (max-width: 480px) {
            .header-title {
                font-size: 1rem !important;
            }
            
            .facility-grid-row {
                grid-template-columns: repeat(2, 1fr);
                gap: 0.5rem;
                padding: 0 0.125rem;
            }
            
            .facility-card {
                min-height: 120px;
                padding: 0.75rem 0.5rem 0.5rem 0.5rem;
            }
            
            .facility-icon {
                font-size: 1.25rem;
                margin-bottom: 0.25rem;
            }
            
            .facility-card .h4 {
                font-size: 0.75rem;
            }
            
            .reserve-btn {
                font-size: 0.7rem;
                padding: 0.4rem 0;
            }
            
            .stepper {
                flex-direction: column;
                gap: 0.5rem;
            }
            
            .step-line {
                width: 3px;
                height: 20px;
            }
            
            .form-card {
                margin: 0.75rem 0.125rem 0.5rem 0.125rem;
                padding: 0.75rem 0.5rem 0.5rem 0.5rem;
            }
        }
        
        @media (min-width: 769px) {
            .facility-grid-row {
                grid-template-columns: repeat(3, 1fr);
                max-width: 900px;
            }
        }
        
        @media (min-width: 1200px) {
            .facility-grid-row {
                grid-template-columns: repeat(5, 1fr);
                max-width: 1200px;
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
        
        .facility-grid-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 1.5rem;
            max-width: 1000px;
            margin: 0 auto 2rem auto;
            padding: 0 1rem;
        }
        .facility-card {
            background: #fff;
            border-radius: 16px;
            box-shadow: 0 2px 12px rgba(0,0,0,0.08);
            border: none;
            padding: 1.5rem 1.2rem 1.2rem 1.2rem;
            min-height: 180px;
            margin: 0;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: space-between;
            transition: box-shadow 0.18s, transform 0.18s;
            text-align: center;
        }
        .facility-card:hover {
            box-shadow: 0 6px 18px rgba(0,0,0,0.13);
            transform: translateY(-3px) scale(1.03);
        }
        .facility-icon {
            font-size: 2.1rem;
            margin-bottom: 0.7rem;
        }
        .facility-card .h4 {
            font-size: 1.08rem;
            font-weight: 700;
            margin-bottom: 0.3rem;
            text-align: center;
        }
        .reserve-btn {
            background: #4a7c3a;
            color: #fff;
            border-radius: 8px;
            font-weight: 700;
            margin-top: auto;
            padding: 0.7rem 0;
            width: 100%;
            font-size: 0.98rem;
            box-shadow: 0 2px 8px rgba(76, 175, 80, 0.08);
            letter-spacing: 0.01em;
        }
        .reserve-btn:hover {
            background: #35602a;
            color: #fff;
        }
        .form-card {
            background: #fff;
            border-radius: 18px;
            box-shadow: 0 4px 24px rgba(0,0,0,0.10);
            border: none;
            max-width: 800px;
            margin: 2.5rem auto 2rem auto;
            padding: 2.2rem 2.2rem 2rem 2.2rem;
        }
        .form-section-title {
            font-size: 1.15rem;
            font-weight: 700;
            color: #004225;
            margin-bottom: 1.1rem;
            margin-top: 1.5rem;
            letter-spacing: 0.01em;
        }
        .form-label {
            font-weight: 600;
            color: #2c3e50;
            margin-bottom: 0.3rem;
        }
        .form-control, .form-select {
            border-radius: 8px;
            font-size: 1rem;
            padding: 0.7rem 1rem;
        }
        .equipment-label {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        .equipment-available {
            font-size: 0.95rem;
            color: #198754;
            font-weight: 500;
        }
        .form-divider {
            border-top: 1.5px solid #e9ecef;
            margin: 2rem 0 1.5rem 0;
        }
        .btn-cancel {
            background: #dc3545;
            color: #fff;
            font-weight: 600;
            border-radius: 8px;
            padding: 0.7rem 2.2rem;
            font-size: 1rem;
        }
        .btn-cancel:hover {
            background: #b52a37;
        }
        .btn-submit {
            background: #004225;
            color: #fff;
            font-weight: 600;
            border-radius: 8px;
            padding: 0.7rem 2.2rem;
            font-size: 1rem;
        }
        .btn-submit:hover {
            background: #198754;
            color: #fff;
        }
        @media (max-width: 900px) {
            .form-card {
                max-width: 98vw;
                padding: 1.2rem 0.7rem 1.2rem 0.7rem;
            }
        }
        .stepper {
            display: flex;
            justify-content: center;
            align-items: center;
            margin-bottom: 2.2rem;
            gap: 2.5rem;
        }
        .step {
            display: flex;
            flex-direction: column;
            align-items: center;
            font-weight: 600;
            color: #b0b0b0;
            font-size: 1.05rem;
        }
        .step.active {
            color: #004225;
        }
        .step-circle {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            background: #e9ecef;
            color: #004225;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 1.1rem;
            margin-bottom: 0.3rem;
            border: 2px solid #e9ecef;
            transition: background 0.2s, border 0.2s;
        }
        .step.active .step-circle {
            background: #004225;
            color: #fff;
            border: 2px solid #004225;
        }
        .step-line {
            width: 48px;
            height: 3px;
            background: #e9ecef;
            margin: 0 0.5rem;
        }
        .step.active ~ .step-line {
            background: #004225;
        }
        .review-label {
            font-weight: 600;
            color: #004225;
        }
        .review-value {
            color: #2c3e50;
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
        
        /* Equipment grid responsive */
        @media (max-width: 768px) {
            .equipment-grid .col-md-6 {
                margin-bottom: 1rem;
            }
            
            .equipment-card {
                padding: 1rem;
            }
            
            .equipment-card .card-title {
                font-size: 0.9rem;
            }
            
            .equipment-card .card-text {
                font-size: 0.8rem;
            }
        }
        
        @media (max-width: 576px) {
            .equipment-grid .col-md-6 {
                margin-bottom: 0.75rem;
            }
            
            .equipment-card {
                padding: 0.75rem;
            }
            
            .equipment-card .card-title {
                font-size: 0.85rem;
            }
            
            .equipment-card .card-text {
                font-size: 0.75rem;
            }
            
            .input-group {
                flex-direction: column;
            }
            
            .input-group .form-control {
                border-radius: 8px 8px 0 0;
                border-bottom: none;
            }
            
            .input-group .input-group-text {
                border-radius: 0 0 8px 8px;
                border-top: none;
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
                <a href="make_booking.php" class="list-group-item list-group-item-action active">
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
            <!-- Header (reuse admin style) -->
            <header class="main-header text-white d-flex justify-content-between align-items-center">
                <div class="d-flex align-items-center">
                    <button class="mobile-menu-toggle me-3" id="mobileMenuToggle">
                        <i class="bi bi-list"></i>
                    </button>
                    <h1 class="h3 mb-0 d-flex align-items-center header-title">
                    <i class="bi bi-calendar-plus me-2"></i>
                    Make a Reservation
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
                <?php if (!$selected_facility_id): ?>
                <!-- Facility Selection Grid -->
                <div class="facility-grid-row">
                    <?php
                    $facility_icons = [
                        'Training Center' => 'bi-mortarboard',
                        'Evacuation Center' => 'bi-people',
                        'Event Center' => 'bi-calendar-event',
                        'Grand Plaza' => 'bi-building',
                        'Back Door' => 'bi-door-closed',
                    ];
                    foreach ($facilities as $facility): ?>
                        <div class="facility-card">
                            <i class="bi <?php echo $facility_icons[$facility] ?? 'bi-buildings'; ?> facility-icon"></i>
                            <span class="h4"><?php echo htmlspecialchars($facility); ?></span>
                            <form method="get" action="make_booking.php" style="width:100%;">
                                <input type="hidden" name="facility" value="<?php echo htmlspecialchars($facility); ?>">
                                <button type="submit" class="btn reserve-btn">
                                    Reserve Now <i class="bi bi-arrow-right ms-2"></i>
                                </button>
                            </form>
                        </div>
                    <?php endforeach; ?>
                </div>
                <?php else: ?>
                <!-- Fill-up Form for Selected Facility -->
                <div class="form-card">
                    <h2 class="mb-4 text-center">Fill up Form</h2>
                    <h4 class="mb-4 text-center text-success"><?php echo htmlspecialchars($selected_facility); ?></h4>
                    <?php if ($success): ?>
                        <div class="alert alert-success">Booking submitted successfully!</div>
                    <?php elseif ($error): ?>
                        <div class="alert alert-danger" id="serverErrorMsg"><?php echo htmlspecialchars($error); ?></div>
                    <?php endif; ?>
                    <!-- Stepper -->
                    <div class="stepper mb-4">
                        <div class="step step1 active">
                            <div class="step-circle">1</div>
                            Event Info
                        </div>
                        <div class="step-line"></div>
                        <div class="step step2">
                            <div class="step-circle">2</div>
                            Equipment
                        </div>
                        <div class="step-line"></div>
                        <div class="step step3">
                            <div class="step-circle">3</div>
                            Request & Review
                        </div>
                    </div>
                    <form id="bookingMultiStepForm" method="POST" enctype="multipart/form-data" autocomplete="off">
                        <input type="hidden" name="facility_id" value="<?php echo htmlspecialchars($selected_facility_id); ?>">
                        <!-- Step 1: Event Info -->
                        <div class="form-step step1-content">
                            <div class="form-section-title">Event Information</div>
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label">Event Date <span class="text-danger">*</span></label>
                                    <div class="custom-date-picker">
                                        <input type="text" class="form-control" id="event_date_display" readonly placeholder="Select a date">
                                        <input type="hidden" name="event_date" id="event_date" required>
                                        <div class="date-picker-calendar" id="datePickerCalendar" style="display: none;">
                                            <div class="calendar-header">
                                                <button type="button" class="btn btn-sm btn-link" id="prevMonth">
                                                    <i class="bi bi-chevron-left"></i>
                                                </button>
                                                <span id="currentMonthYear"></span>
                                                <button type="button" class="btn btn-sm btn-link" id="nextMonth">
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
                                            <div class="calendar-days" id="calendarDays"></div>
                                        </div>
                                    </div>
                                    <div class="form-text">
                                        <i class="bi bi-info-circle me-1"></i>
                                        Dates marked with <span class="text-danger fw-bold">X</span> are already booked
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Start Time <span class="text-danger">*</span></label>
                                    <input type="time" class="form-control" name="start_time" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">End Time <span class="text-danger">*</span></label>
                                    <input type="time" class="form-control" name="end_time" required>
                                </div>
                            </div>
                            <div class="d-flex gap-3 justify-content-end mt-4">
                                <button type="button" class="btn btn-submit next-btn">Next <i class="bi bi-arrow-right ms-2"></i></button>
                            </div>
                        </div>
                        <!-- Step 2: Equipment -->
                        <div class="form-step step2-content" style="display:none;">
                            <div class="form-section-title">Equipment Needed</div>
                            <div class="row g-3">
                                <?php foreach ($equipment as $item_name => $available): ?>
                                <div class="col-md-4">
                                    <label class="form-label equipment-label">
                                        <?php echo htmlspecialchars($item_name); ?>
                                        <span class="equipment-available">(<?php echo $available; ?> available)</span>
                                    </label>
                                    <?php if ($item_name === 'Sound System'): ?>
                                    <div class="form-check mt-2">
                                        <input class="form-check-input" type="checkbox" 
                                               name="sound_system" 
                                               value="1" id="sound_system">
                                        <label class="form-check-label" for="sound_system">
                                            Yes, I need Sound System
                                        </label>
                                        <small class="text-muted d-block mt-1">
                                            Includes: Speaker, Mixer, Amplifier, Cables, Microphone
                                        </small>
                                    </div>
                                    <?php else: ?>
                                    <input type="number" class="form-control" 
                                           name="<?php echo strtolower(str_replace(' ', '_', $item_name)); ?>" 
                                           min="0" max="<?php echo $available; ?>">
                                    <?php endif; ?>
                                </div>
                                <?php endforeach; ?>
                            </div>
                            <div class="d-flex gap-3 justify-content-between mt-4">
                                <button type="button" class="btn btn-cancel prev-btn"><i class="bi bi-arrow-left me-2"></i>Back</button>
                                <button type="button" class="btn btn-submit next-btn">Next <i class="bi bi-arrow-right ms-2"></i></button>
                            </div>
                        </div>

                        <!-- Step 3: Request Letter & Review -->
                        <div class="form-step step3-content" style="display:none;">
                            <div class="form-section-title">Request Letter</div>
                            <div class="row g-3 mb-3">
                                <div class="col-md-8">
                                    <label class="form-label">Request letter (PDF)</label>
                                    <input type="file" class="form-control" name="request_letter" accept="application/pdf" required onchange="validatePDF(this)">
                                    <small id="pdfError" class="text-danger" style="display:none;">PDF only</small>
                                </div>
                            </div>
                            <div class="form-divider"></div>
                            <div class="form-section-title">Review Your Booking</div>
                            <div class="row g-3 mb-3">
                                <div class="col-md-6">
                                    <div class="review-label">Event Date:</div>
                                    <div class="review-value" id="review_event_date"></div>
                                </div>
                                <div class="col-md-6">
                                    <div class="review-label">Start Time:</div>
                                    <div class="review-value" id="review_start_time"></div>
                                </div>
                                <div class="col-md-6">
                                    <div class="review-label">End Time:</div>
                                    <div class="review-value" id="review_end_time"></div>
                                </div>
                                <div class="col-md-6">
                                    <div class="review-label">Chairs:</div>
                                    <div class="review-value" id="review_chairs"></div>
                                </div>
                                <div class="col-md-6">
                                    <div class="review-label">Tables:</div>
                                    <div class="review-value" id="review_tables"></div>
                                </div>
                                <div class="col-md-6">
                                    <div class="review-label">Industrial Fan:</div>
                                    <div class="review-value" id="review_industrial_fan"></div>
                                </div>
                                <div class="col-md-6">
                                    <div class="review-label">Extension Wires:</div>
                                    <div class="review-value" id="review_extension_wires"></div>
                                </div>
                                <div class="col-md-6">
                                    <div class="review-label">Red Carpet:</div>
                                    <div class="review-value" id="review_red_carpet"></div>
                                </div>
                                <div class="col-md-6">
                                    <div class="review-label">Podium:</div>
                                    <div class="review-value" id="review_podium"></div>
                                </div>
                                <div class="col-md-6">
                                    <div class="review-label">Sound System:</div>
                                    <div class="review-value" id="review_sound_system"></div>
                                </div>
                            </div>
                            <div class="d-flex gap-3 justify-content-between mt-4">
                                <button type="button" class="btn btn-cancel prev-btn"><i class="bi bi-arrow-left me-2"></i>Back</button>
                                <button type="submit" class="btn btn-submit"><i class="bi bi-check-lg me-2"></i>Submit</button>
                            </div>
                        </div>
                    </form>
                </div>
                <script>
                // Double booking prevention
                let bookedDates = [];
                let currentDate = new Date();
                let selectedDate = null;
                
                // Load booked dates when page loads
                document.addEventListener('DOMContentLoaded', function() {
                    loadBookedDates();
                    setupCustomDatePicker();
                });
                
                // Function to load booked dates from server
                function loadBookedDates() {
                    fetch('get_booked_dates.php')
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                bookedDates = data.booked_dates;
                                renderCalendar();
                            } else {
                                console.error('Failed to load booked dates:', data.error);
                            }
                        })
                        .catch(error => {
                            console.error('Error loading booked dates:', error);
                        });
                }
                
                // Setup custom date picker
                function setupCustomDatePicker() {
                    const displayInput = document.getElementById('event_date_display');
                    const hiddenInput = document.getElementById('event_date');
                    const calendar = document.getElementById('datePickerCalendar');
                    
                    // Toggle calendar on input click
                    displayInput.addEventListener('click', function() {
                        calendar.style.display = calendar.style.display === 'none' ? 'block' : 'none';
                        renderCalendar();
                    });
                    
                    // Close calendar when clicking outside
                    document.addEventListener('click', function(e) {
                        if (!e.target.closest('.custom-date-picker')) {
                            calendar.style.display = 'none';
                        }
                    });
                    
                    // Navigation buttons
                    document.getElementById('prevMonth').addEventListener('click', function() {
                        currentDate.setMonth(currentDate.getMonth() - 1);
                        renderCalendar();
                    });
                    
                    document.getElementById('nextMonth').addEventListener('click', function() {
                        currentDate.setMonth(currentDate.getMonth() + 1);
                        renderCalendar();
                    });
                }
                
                // Render calendar
                function renderCalendar() {
                    const year = currentDate.getFullYear();
                    const month = currentDate.getMonth();
                    
                    // Update header
                    const monthNames = ['January', 'February', 'March', 'April', 'May', 'June',
                                       'July', 'August', 'September', 'October', 'November', 'December'];
                    document.getElementById('currentMonthYear').textContent = `${monthNames[month]} ${year}`;
                    
                    // Get first day of month and number of days
                    const firstDay = new Date(year, month, 1).getDay();
                    const daysInMonth = new Date(year, month + 1, 0).getDate();
                    const today = new Date();
                    
                    // Clear previous calendar
                    const calendarDays = document.getElementById('calendarDays');
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
                        if (selectedDate && selectedDate.toDateString() === currentDayDate.toDateString()) {
                            dayElement.classList.add('selected');
                        }
                        
                        // Check if it's booked
                        if (bookedDates.includes(dateString)) {
                            dayElement.classList.add('booked');
                        }
                        
                        // Check if it's in the past
                        if (currentDayDate < new Date(today.getFullYear(), today.getMonth(), today.getDate())) {
                            dayElement.classList.add('disabled');
                        }
                        
                        // Add click event
                        if (!dayElement.classList.contains('disabled') && !dayElement.classList.contains('booked')) {
                            dayElement.addEventListener('click', function() {
                                selectDate(currentDayDate);
                            });
                        }
                        
                        calendarDays.appendChild(dayElement);
                    }
                }
                
                // Select date
                function selectDate(date) {
                    selectedDate = date;
                    const dateString = date.toISOString().split('T')[0];
                    const displayString = date.toLocaleDateString('en-US', {
                        year: 'numeric',
                        month: 'long',
                        day: 'numeric'
                    });
                    
                    document.getElementById('event_date_display').value = displayString;
                    document.getElementById('event_date').value = dateString;
                    document.getElementById('datePickerCalendar').style.display = 'none';
                    
                    renderCalendar();
                }

                // Multi-step form logic
                const steps = [
                    document.querySelector('.step1-content'),
                    document.querySelector('.step2-content'),
                    document.querySelector('.step3-content')
                ];
                const stepIndicators = [
                    document.querySelector('.step1'),
                    document.querySelector('.step2'),
                    document.querySelector('.step3')
                ];
                let currentStep = <?php echo isset($error) && $error ? (isset($show_step) ? $show_step : 0) : 0; ?>;
                function showStep(idx) {
                    steps.forEach((step, i) => step.style.display = i === idx ? '' : 'none');
                    stepIndicators.forEach((el, i) => el.classList.toggle('active', i === idx));
                }
                document.querySelectorAll('.next-btn').forEach(btn => btn.onclick = function() {
                    if (currentStep === 0) {
                        // Validate step 1
                        const form = document.getElementById('bookingMultiStepForm');
                        if (!form.event_date.value || !form.start_time.value || !form.end_time.value) {
                            alert('Please fill in all event information fields.');
                            return;
                        }
                        
                        // Additional validation for double booking
                        if (bookedDates.includes(form.event_date.value)) {
                            alert('This date is already booked. Please select a different date.');
                            return;
                        }
                        
                        // Validate that selected date is not in the past
                        const today = new Date().toISOString().split('T')[0];
                        if (form.event_date.value < today) {
                            alert('Please select a future date.');
                            return;
                        }
                    }
                    if (currentStep === 1) {
                        // No required fields, but you can add validation here if needed
                    }
                    currentStep = Math.min(currentStep + 1, 2);
                    showStep(currentStep);
                    if (currentStep === 2) fillReview();
                });
                document.querySelectorAll('.prev-btn').forEach(btn => btn.onclick = function() {
                    currentStep = Math.max(currentStep - 1, 0);
                    showStep(currentStep);
                });
                function fillReview() {
                    const form = document.getElementById('bookingMultiStepForm');
                    document.getElementById('review_event_date').textContent = form.event_date.value;
                    document.getElementById('review_start_time').textContent = form.start_time.value;
                    document.getElementById('review_end_time').textContent = form.end_time.value;
                    document.getElementById('review_chairs').textContent = form.chairs.value;
                    document.getElementById('review_tables').textContent = form.tables.value;
                    document.getElementById('review_industrial_fan').textContent = form.industrial_fan.value;
                    document.getElementById('review_extension_wires').textContent = form.extension_wires.value;
                    document.getElementById('review_red_carpet').textContent = form.red_carpet.value;
                    document.getElementById('review_podium').textContent = form.podium.value;
                    
                    // Handle sound system review
                    const soundSystemCheckbox = document.querySelector('input[name="sound_system"]');
                    const reviewSoundSystem = document.getElementById('review_sound_system');
                    if (soundSystemCheckbox && soundSystemCheckbox.checked) {
                        reviewSoundSystem.textContent = 'Yes';
                    } else {
                        reviewSoundSystem.textContent = 'No';
                    }
                }
                showStep(currentStep);

                // Add form submission validation
                const bookingForm = document.getElementById('bookingMultiStepForm');
                bookingForm.onsubmit = function(event) {
                    // Only allow submission if on the last step
                    if (currentStep !== 2) {
                        alert('Please complete all steps before submitting.');
                        event.preventDefault();
                        return false;
                    }
                    const form = this;
                    if (!form.event_date.value || !form.start_time.value || !form.end_time.value) {
                        alert('Please fill in all event information fields.');
                        event.preventDefault();
                        return false;
                    }
                    
                    // Final validation for double booking
                    if (bookedDates.includes(form.event_date.value)) {
                        alert('This date is already booked. Please select a different date.');
                        event.preventDefault();
                        return false;
                    }
                    
                    // Validate that selected date is not in the past
                    const today = new Date().toISOString().split('T')[0];
                    if (form.event_date.value < today) {
                        alert('Please select a future date.');
                        event.preventDefault();
                        return false;
                    }
                    
                    // Add more validation here if needed
                    console.log('Form submitted!');
                    return true;
                };

                function validatePDF(input) {
                    const file = input.files[0];
                    const errorMsg = document.getElementById('pdfError');
                    if (file && file.type !== 'application/pdf') {
                        errorMsg.style.display = 'block';
                        input.value = '';
                    } else {
                        errorMsg.style.display = 'none';
                    }
                }

                // Hide server error message after 3 seconds
                window.addEventListener('DOMContentLoaded', function() {
                    var serverError = document.getElementById('serverErrorMsg');
                    if (serverError) {
                        setTimeout(function() {
                            serverError.style.display = 'none';
                        }, 3000);
                    }
                });
                </script>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
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