<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../login.php');
    exit();
}

// Check if item ID is provided
if (!isset($_GET['id'])) {
    header('Location: inventory.php');
    exit();
}

$item_id = $_GET['id'];

try {
    // Get item details
    $stmt = $pdo->prepare("SELECT * FROM inventory WHERE id = ?");
    $stmt->execute([$item_id]);
    $item = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$item) {
        header('Location: inventory.php');
        exit();
    }

    // Get borrowing history
    $stmt = $pdo->prepare("
        SELECT b.*, u.name as user_name 
        FROM borrowings b 
        JOIN users u ON b.user_id = u.id 
        WHERE b.item_id = ? 
        ORDER BY b.borrow_date DESC 
        LIMIT 5
    ");
    $stmt->execute([$item_id]);
    $borrowing_history = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    // Handle error
    header('Location: inventory.php');
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Item - PGOM Facilities</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
    <style>
        .form-card {
            background: white;
            border-radius: 15px;
            padding: 2rem;
            box-shadow: 0 0 20px rgba(0,0,0,0.05);
        }
        .form-label {
            color: #6c757d;
            font-size: 0.9rem;
            margin-bottom: 0.5rem;
        }
        .form-value {
            font-size: 1.1rem;
            font-weight: 500;
            padding: 0.8rem;
            background: #f8f9fa;
            border-radius: 8px;
            margin-bottom: 1rem;
        }
        .status-badge {
            padding: 0.5rem 1rem;
            border-radius: 50px;
            font-weight: 500;
            font-size: 0.85rem;
            display: inline-block;
        }
        .history-card {
            background: white;
            border-radius: 15px;
            padding: 1.5rem;
            box-shadow: 0 0 20px rgba(0,0,0,0.05);
            height: 100%;
        }
        .history-item {
            padding: 1rem;
            border-bottom: 1px solid #e9ecef;
        }
        .history-item:last-child {
            border-bottom: none;
        }
        .stock-indicator {
            height: 8px;
            border-radius: 4px;
            margin-top: 0.5rem;
        }
        .current-value {
            font-size: 0.9rem;
            color: #6c757d;
            margin-top: 0.5rem;
        }
        .header-icon {
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.1);
            color: white;
            cursor: pointer;
            transition: all 0.2s;
        }
        .header-icon:hover {
            background: rgba(255, 255, 255, 0.2);
        }
        .header-icon i {
            font-size: 1.2rem;
        }
        .dropdown-menu {
            margin-top: 10px;
            border-radius: 10px;
        }
        .dropdown-item {
            padding: 8px 20px;
            color: #495057;
            transition: all 0.2s;
        }
        .dropdown-item:hover {
            background: #f8f9fa;
            color: #000;
        }
        .dropdown-item i {
            width: 20px;
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
                <h1 class="h3 mb-0 d-flex align-items-center">
                    <i class="bi bi-box-seam me-2"></i>
                    View Item Details
                </h1>
                <div class="d-flex align-items-center gap-3">
                    <div class="header-icon">
                        <i class="bi bi-bell"></i>
                    </div>
                    <div class="header-icon">
                        <i class="bi bi-moon"></i>
                    </div>
                    <div class="dropdown">
                        <button class="header-icon dropdown-toggle border-0 bg-transparent" type="button" data-bs-toggle="dropdown">
                            <i class="bi bi-person-circle"></i>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end shadow-sm border-0">
                            <li><a class="dropdown-item py-2" href="profile.php"><i class="bi bi-person me-2"></i>Profile</a></li>
                            <li><a class="dropdown-item py-2" href="../logout.php"><i class="bi bi-box-arrow-right me-2"></i>Logout</a></li>
                        </ul>
                    </div>
                </div>
            </header>

            <!-- Content -->
            <div class="container-fluid py-4 px-4">
                <!-- Breadcrumb -->
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                        <li class="breadcrumb-item"><a href="inventory.php">Inventory</a></li>
                        <li class="breadcrumb-item active" aria-current="page"><?php echo htmlspecialchars($item['name']); ?></li>
                    </ol>
                </nav>

                <div class="row g-4">
                    <!-- Item Details -->
                    <div class="col-md-8">
                        <div class="form-card">
                            <div class="row g-4">
                                <div class="col-12">
                                    <label class="form-label">Item Name</label>
                                    <div class="form-value"><?php echo htmlspecialchars($item['name']); ?></div>
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label">Total Quantity</label>
                                    <div class="form-value"><?php echo $item['total_quantity']; ?></div>
                                    <div class="current-value">
                                        Currently borrowed: <strong><?php echo $item['borrowed']; ?></strong> items
                                    </div>
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label">Available Quantity</label>
                                    <div class="form-value"><?php echo $item['total_quantity'] - $item['borrowed']; ?></div>
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label">Minimum Stock Level</label>
                                    <div class="form-value"><?php echo $item['minimum_stock']; ?></div>
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label">Status</label>
                                    <div class="form-value">
                                        <span class="status-badge <?php 
                                            echo $item['status'] === 'Available' ? 'bg-success bg-opacity-10 text-success' : 'bg-secondary bg-opacity-10 text-secondary';
                                        ?>">
                                            <?php echo $item['status']; ?>
                                        </span>
                                    </div>
                                </div>

                                <div class="col-12">
                                    <label class="form-label">Stock Level</label>
                                    <div class="progress stock-indicator">
                                        <?php
                                        $stock_percentage = ($item['total_quantity'] - $item['borrowed']) / $item['total_quantity'] * 100;
                                        $indicator_class = $stock_percentage <= 30 ? 'bg-danger' : ($stock_percentage <= 70 ? 'bg-warning' : 'bg-success');
                                        ?>
                                        <div class="progress-bar <?php echo $indicator_class; ?>" 
                                             style="width: <?php echo $stock_percentage; ?>%">
                                        </div>
                                    </div>
                                </div>

                                <div class="col-12">
                                    <label class="form-label">Last Updated</label>
                                    <div class="form-value">
                                        <?php echo date('F d, Y h:i A', strtotime($item['last_updated'])); ?>
                                    </div>
                                </div>

                                <div class="col-12 d-flex gap-3 justify-content-end mt-4">
                                    <a href="inventory.php" class="btn btn-primary">
                                        <i class="bi bi-arrow-left me-2"></i>Back
                                    </a>
                                    <button type="button" class="btn btn-danger" 
                                            onclick="deleteItem(<?php echo $item['id']; ?>, '<?php echo htmlspecialchars($item['name'], ENT_QUOTES); ?>')">
                                        <i class="bi bi-trash me-2"></i>Delete Item
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Recent Borrowing History -->
                    <div class="col-md-4">
                        <div class="history-card">
                            <h4 class="mb-4">Recent Borrowing History</h4>
                            <?php if (empty($borrowing_history)): ?>
                                <p class="text-muted">No borrowing history available.</p>
                            <?php else: ?>
                                <?php foreach ($borrowing_history as $history): ?>
                                    <div class="history-item">
                                        <div class="d-flex justify-content-between align-items-start">
                                            <div>
                                                <h6 class="mb-1"><?php echo htmlspecialchars($history['user_name']); ?></h6>
                                                <small class="text-muted">
                                                    Borrowed: <?php echo date('M d, Y', strtotime($history['borrow_date'])); ?>
                                                </small>
                                                <?php if ($history['return_date']): ?>
                                                    <br>
                                                    <small class="text-muted">
                                                        Returned: <?php echo date('M d, Y', strtotime($history['return_date'])); ?>
                                                    </small>
                                                <?php endif; ?>
                                            </div>
                                            <span class="badge <?php echo $history['return_date'] ? 'bg-success' : 'bg-warning'; ?>">
                                                <?php echo $history['return_date'] ? 'Returned' : 'Borrowed'; ?>
                                            </span>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div class="modal fade" id="deleteModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Delete Item</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to delete <strong id="deleteItemName"></strong>?</p>
                    <p class="text-danger mb-0">This action cannot be undone.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                    <a href="#" id="confirmDelete" class="btn btn-danger">Delete Item</a>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    function deleteItem(id, name) {
        document.getElementById('deleteItemName').textContent = name;
        document.getElementById('confirmDelete').href = 'delete_item.php?id=' + id;
        var modal = new bootstrap.Modal(document.getElementById('deleteModal'));
        modal.show();
    }
    </script>
</body>
</html>