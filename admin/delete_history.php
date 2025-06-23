    <?php
    session_start();
    require_once '../config/database.php';

    // Check if user is logged in and is admin
    if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
        $response = array('success' => false, 'message' => 'Unauthorized access');
        echo json_encode($response);
        exit();
    }

    // Check if history ID is provided
    if (!isset($_POST['history_id'])) {
        $response = array('success' => false, 'message' => 'History ID is required');
        echo json_encode($response);
        exit();
    }

    try {
        // Begin transaction
        $pdo->beginTransaction();

        // Get the history IDs (can be single ID or comma-separated IDs)
        $history_ids = explode(',', $_POST['history_id']);
        $history_ids = array_map('trim', $history_ids); // Clean up the IDs
        
        // Prepare placeholders for the query
        $placeholders = str_repeat('?,', count($history_ids) - 1) . '?';
        
        // Delete the history records
        $stmt = $pdo->prepare("DELETE FROM user_history WHERE id IN ($placeholders)");
        $result = $stmt->execute($history_ids);

        if ($result && $stmt->rowCount() > 0) {
            // Commit transaction
            $pdo->commit();
            $response = array(
                'success' => true, 
                'message' => 'History record(s) deleted successfully',
                'deleted_count' => $stmt->rowCount()
            );
        } else {
            // Rollback transaction
            $pdo->rollBack();
            $response = array('success' => false, 'message' => 'No records were deleted');
        }
    } catch (PDOException $e) {
        // Rollback transaction
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $response = array('success' => false, 'message' => 'Database error: ' . $e->getMessage());
    }

    // Return JSON response
    header('Content-Type: application/json');
    echo json_encode($response); 