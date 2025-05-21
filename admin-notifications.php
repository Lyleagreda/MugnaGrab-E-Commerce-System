<?php
session_start();
include '../config.php';

// Check if the user is logged in
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Unauthorized access']);
    exit;
}

// Function to get notifications
function getNotifications($conn) {
    $notifications = [];
    $seen_notifications = [];
    
    // Get seen notification IDs from session if exists
    if (isset($_SESSION['seen_notifications'])) {
        $seen_notifications = $_SESSION['seen_notifications'];
    }
    
    // Get new orders (last 24 hours)
    $new_orders_query = "SELECT o.id, o.created_at, u.first_name, u.last_name, o.total 
                         FROM orders o 
                         JOIN users u ON o.user_id = u.id 
                         WHERE o.created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR) 
                         ORDER BY o.created_at DESC 
                         LIMIT 10";
    
    $result = $conn->query($new_orders_query);
    
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $notification_id = 'order_' . $row['id'];
            $is_read = in_array($notification_id, $seen_notifications);
            
            $notifications[] = [
                'id' => $notification_id,
                'type' => 'new_order',
                'title' => 'New Order Placed',
                'message' => 'Order #' . $row['id'] . ' for ₱' . number_format($row['total'], 2) . ' by ' . $row['first_name'] . ' ' . $row['last_name'],
                'time' => $row['created_at'],
                'link' => 'admin-payment-history.php?search=' . $row['id'],
                'is_read' => $is_read
            ];
        }
    }
    
    // Get orders with status changes (last 24 hours)
    $status_changes_query = "SELECT o.id, o.updated_at, o.status, u.first_name, u.last_name 
                             FROM orders o 
                             JOIN users u ON o.user_id = u.id 
                             WHERE o.updated_at IS NOT NULL 
                             AND o.updated_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR) 
                             AND o.status IN ('shipped', 'delivered', 'received') 
                             ORDER BY o.updated_at DESC 
                             LIMIT 10";
    
    $result = $conn->query($status_changes_query);
    
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $notification_id = 'status_' . $row['id'] . '_' . $row['status'];
            $is_read = in_array($notification_id, $seen_notifications);
            
            $status_text = ucfirst($row['status']);
            
            $notifications[] = [
                'id' => $notification_id,
                'type' => 'status_change',
                'title' => 'Order Status Updated',
                'message' => 'Order #' . $row['id'] . ' is now ' . $status_text . ' for ' . $row['first_name'] . ' ' . $row['last_name'],
                'time' => $row['updated_at'],
                'link' => 'admin-payment-history.php?search=' . $row['id'],
                'is_read' => $is_read
            ];
        }
    }
    
    // Get new users (last 24 hours)
    $new_users_query = "SELECT id, first_name, last_name, email, created_at 
                        FROM users 
                        WHERE created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR) 
                        ORDER BY created_at DESC 
                        LIMIT 10";
    
    $result = $conn->query($new_users_query);
    
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $notification_id = 'user_' . $row['id'];
            $is_read = in_array($notification_id, $seen_notifications);
            
            $notifications[] = [
                'id' => $notification_id,
                'type' => 'new_user',
                'title' => 'New User Registration',
                'message' => $row['first_name'] . ' ' . $row['last_name'] . ' (' . $row['email'] . ') has registered',
                'time' => $row['created_at'],
                'link' => 'admin-users.php?search=' . $row['email'],
                'is_read' => $is_read
            ];
        }
    }
    
    // Sort notifications by time (newest first)
    usort($notifications, function($a, $b) {
        return strtotime($b['time']) - strtotime($a['time']);
    });
    
    return $notifications;
}

// Mark notification as read
if (isset($_POST['mark_read']) && !empty($_POST['notification_id'])) {
    $notification_id = $_POST['notification_id'];
    
    if (!isset($_SESSION['seen_notifications'])) {
        $_SESSION['seen_notifications'] = [];
    }
    
    if (!in_array($notification_id, $_SESSION['seen_notifications'])) {
        $_SESSION['seen_notifications'][] = $notification_id;
    }
    
    header('Content-Type: application/json');
    echo json_encode(['success' => true]);
    exit;
}

// Mark all notifications as read
if (isset($_POST['mark_all_read'])) {
    $notifications = getNotifications($conn);
    
    if (!isset($_SESSION['seen_notifications'])) {
        $_SESSION['seen_notifications'] = [];
    }
    
    foreach ($notifications as $notification) {
        if (!in_array($notification['id'], $_SESSION['seen_notifications'])) {
            $_SESSION['seen_notifications'][] = $notification['id'];
        }
    }
    
    header('Content-Type: application/json');
    echo json_encode(['success' => true]);
    exit;
}

// Get notifications
$notifications = getNotifications($conn);
$unread_count = 0;

foreach ($notifications as $notification) {
    if (!$notification['is_read']) {
        $unread_count++;
    }
}

// Return notifications as JSON
header('Content-Type: application/json');
echo json_encode([
    'notifications' => $notifications,
    'unread_count' => $unread_count
]);
