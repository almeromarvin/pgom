<?php
require_once __DIR__ . '/../config/database.php';

function addNotification($title, $message, $type = 'info', $link = null) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("
            INSERT INTO notifications (title, message, type, link, created_at) 
            VALUES (?, ?, ?, ?, NOW())
        ");
        return $stmt->execute([$title, $message, $type, $link]);
    } catch (PDOException $e) {
        error_log("Error adding notification: " . $e->getMessage());
        return false;
    }
}

function addBookingNotification($action, $facilityName, $userName, $bookingId = null) {
    global $pdo;
    
    $message = '';
    $title = '';
    $type = 'booking';
    $link = "admin/reservation.php?id=$bookingId";
    
    switch($action) {
        case 'new':
            $title = 'New Booking Request';
            $message = "$userName has requested to book $facilityName";
            break;
        case 'cancelled':
            $title = 'Booking Cancelled';
            $message = "$userName has cancelled their booking for $facilityName";
            break;
        case 'approved':
            $title = 'Booking Approved';
            $message = "The booking for $facilityName by $userName has been approved";
            break;
        case 'rejected':
            $title = 'Booking Rejected';
            $message = "The booking for $facilityName by $userName has been rejected";
            break;
    }
    
    return addNotification($title, $message, $type, $link);
}

function addInventoryNotification($action, $itemName, $quantity = null, $itemId = null) {
    $title = '';
    $message = '';
    $type = '';
    $link = $itemId ? "admin/view_item.php?id=" . $itemId : null;
    
    switch ($action) {
        case 'added':
            $title = 'New Item Added';
            $message = "New item '$itemName' has been added to inventory";
            $type = 'success';
            break;
            
        case 'low_stock':
            $title = 'Low Stock Alert';
            $message = "$itemName is running low on stock";
            $type = 'warning';
            break;
            
        case 'out_of_stock':
            $title = 'Out of Stock Alert';
            $message = "$itemName is out of stock";
            $type = 'danger';
            break;
            
        case 'borrowed':
            $title = 'Item Borrowed';
            $message = "$quantity $itemName has been borrowed";
            $type = 'info';
            break;
            
        case 'returned':
            $title = 'Item Returned';
            $message = "$quantity $itemName has been returned";
            $type = 'success';
            break;
    }
    
    return addNotification($title, $message, $type, $link);
}

function addMaintenanceNotification($action, $itemName) {
    $title = '';
    $message = '';
    $type = '';
    
    switch ($action) {
        case 'started':
            $title = 'Maintenance Started';
            $message = "$itemName has been placed under maintenance";
            $type = 'maintenance';
            break;
            
        case 'completed':
            $title = 'Maintenance Completed';
            $message = "Maintenance for $itemName has been completed";
            $type = 'success';
            break;
    }
    
    return addNotification($title, $message, $type);
}

function addNotificationForBooking($booking_id, $user_id, $status) {
    $title = '';
    $message = '';
    $type = 'booking';
    
    switch ($status) {
        case 'approved':
            $title = 'Booking Approved';
            $message = 'Your facility booking request has been approved.';
            break;
        case 'rejected':
            $title = 'Booking Rejected';
            $message = 'Your facility booking request has been rejected.';
            break;
        case 'completed':
            $title = 'Booking Completed';
            $message = 'Your facility booking has been marked as completed.';
            break;
        default:
            return false;
    }
    
    $link = "booking_details.php?id=" . $booking_id;
    return addNotification($title, $message, $type, $link);
}

function addNotificationForInventory($user_id, $item_name, $action) {
    $title = 'Inventory Update';
    $message = "The item '$item_name' has been $action.";
    return addNotification($title, $message, 'inventory', 'inventory.php');
}

function addNotificationForUser($user_id, $action) {
    $title = 'Account Update';
    $message = "Your account has been $action.";
    return addNotification($title, $message, 'user', 'profile.php');
}

function addUserNotification($action, $userName) {
    $title = '';
    $message = '';
    $type = 'user';
    
    switch ($action) {
        case 'created':
            $title = 'New User Created';
            $message = "New user account created for $userName";
            break;
            
        case 'updated':
            $title = 'User Updated';
            $message = "User account updated for $userName";
            break;
            
        case 'deleted':
            $title = 'User Deleted';
            $message = "User account deleted for $userName";
            break;
            
        case 'restored':
            $title = 'User Restored';
            $message = "User account restored for $userName";
            break;
    }
    
    return addNotification($title, $message, $type);
}

// Function to notify admins
function notifyAdmins($title, $message, $type = 'system', $link = null) {
    global $pdo;
    
    try {
        // Get all admin users
        $stmt = $pdo->query("SELECT id FROM users WHERE role = 'admin'");
        $admins = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        // Add notification for each admin
        foreach ($admins as $admin_id) {
            addNotification($title, $message, $type, $link);
        }
        
        return true;
    } catch (PDOException $e) {
        error_log("Error notifying admins: " . $e->getMessage());
        return false;
    }
} 