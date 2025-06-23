<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../login.php');
    exit();
}

// Get user ID from URL
$userId = isset($_GET['id']) ? $_GET['id'] : null;

if (!$userId) {
    header('Location: add_user.php');
    exit();
}

// Fetch user details
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$userId]);
$user = $stmt->fetch();

if (!$user) {
    header('Location: add_user.php');
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View User - PGOM Facilities</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
</head>
<body>
    <div class="d-flex">
        <?php include '../components/sidebar.php'; ?>

        <!-- Main Content -->
        <div class="main-content flex-grow-1">
            <?php include '../components/header.php'; ?>

            <!-- View User Content -->
            <div class="container-fluid py-4 px-4">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h2>View User Details</h2>
                    <a href="add_user.php" class="btn btn-secondary">Back to Users</a>
                </div>

                <div class="card">
                    <div class="card-body">
                        <div class="row mb-3">
                            <div class="col-md-4">
                                <strong>Username:</strong>
                                <p><?php echo htmlspecialchars($user['username']); ?></p>
                            </div>
                            <div class="col-md-4">
                                <strong>Full Name:</strong>
                                <p><?php echo htmlspecialchars($user['name']); ?></p>
                            </div>
                            <div class="col-md-4">
                                <strong>Suffix:</strong>
                                <p><?php echo htmlspecialchars($user['suffix'] ?: 'N/A'); ?></p>
                            </div>
                        </div>

                        <div class="row mb-3">
                            <div class="col-md-4">
                                <strong>Birthday:</strong>
                                <p><?php echo htmlspecialchars($user['birthday']); ?></p>
                            </div>
                            <div class="col-md-8">
                                <strong>Address:</strong>
                                <p><?php echo htmlspecialchars($user['address']); ?></p>
                            </div>
                        </div>

                        <div class="row mb-3">
                            <div class="col-md-4">
                                <strong>Gender:</strong>
                                <p><?php echo htmlspecialchars($user['gender']); ?></p>
                            </div>
                            <div class="col-md-4">
                                <strong>Email:</strong>
                                <p><?php echo htmlspecialchars($user['email']); ?></p>
                            </div>
                            <div class="col-md-4">
                                <strong>Phone Number:</strong>
                                <p><?php echo htmlspecialchars($user['phone_number']); ?></p>
                            </div>
                        </div>

                        <div class="row mb-3">
                            <div class="col-md-6">
                                <strong>Valid ID Type:</strong>
                                <p><?php echo htmlspecialchars($user['valid_id_type']); ?></p>
                            </div>
                            <div class="col-md-6">
                                <strong>Valid ID Number:</strong>
                                <p><?php echo htmlspecialchars($user['valid_id_number']); ?></p>
                            </div>
                        </div>

                        <div class="row mb-3">
                            <div class="col-md-4">
                                <strong>Position:</strong>
                                <p><?php echo htmlspecialchars($user['position'] ?: 'N/A'); ?></p>
                            </div>
                            <div class="col-md-4">
                                <strong>Role:</strong>
                                <p><?php echo htmlspecialchars(ucfirst($user['role'])); ?></p>
                            </div>
                        </div>

                        <div class="text-end">
                            <a href="edit_user.php?id=<?php echo $user['id']; ?>" class="btn btn-primary">Edit User</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>