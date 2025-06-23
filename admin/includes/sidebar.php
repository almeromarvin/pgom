<?php
// Get current page for active menu highlighting
$current_page = basename($_SERVER['PHP_SELF']);
?>
<div class="col-md-3 col-lg-2 d-md-block bg-light sidebar collapse">
    <div class="position-sticky pt-3">
        <div class="sidebar-heading d-flex align-items-center justify-content-center mb-3">
            <img src="../images/logo.png" alt="PGOM Logo" class="me-2" style="height: 40px;">
            <span class="fw-bold">PGOM FACILITIES</span>
        </div>
        <ul class="nav flex-column">
            <li class="nav-item">
                <a class="nav-link <?php echo $current_page == 'dashboard.php' ? 'active' : ''; ?>" href="dashboard.php">
                    <i class="bi bi-speedometer2 me-2"></i>
                    Dashboard
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo $current_page == 'reservation.php' ? 'active' : ''; ?>" href="reservation.php">
                    <i class="bi bi-calendar-check me-2"></i>
                    Reservation
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo $current_page == 'inventory.php' ? 'active' : ''; ?>" href="inventory.php">
                    <i class="bi bi-box-seam me-2"></i>
                    Inventory
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo $current_page == 'report.php' ? 'active' : ''; ?>" href="report.php">
                    <i class="bi bi-file-text me-2"></i>
                    Report
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo $current_page == 'add_user.php' ? 'active' : ''; ?>" href="add_user.php">
                    <i class="bi bi-person-plus me-2"></i>
                    Add User
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo $current_page == 'history.php' ? 'active' : ''; ?>" href="history.php">
                    <i class="bi bi-clock-history me-2"></i>
                    History
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo $current_page == 'calendar.php' ? 'active' : ''; ?>" href="calendar.php">
                    <i class="bi bi-calendar3 me-2"></i>
                    Calendar View
                </a>
            </li>
        </ul>
    </div>
</div> 