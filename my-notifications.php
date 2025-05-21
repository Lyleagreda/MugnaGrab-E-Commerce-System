<?php
session_start();
include 'config.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$user_id = $_SESSION['user_id'];

// Function to get user notifications
function getUserNotifications($conn, $user_id) {
    $notifications = [];
    $seen_notifications = [];
    
    // Get seen notification IDs from session if exists
    if (isset($_SESSION['user_seen_notifications'])) {
        $seen_notifications = $_SESSION['user_seen_notifications'];
    }
    
    // Get orders with status changes in the last 30 days
    $orders_query = "SELECT o.id, o.status, o.updated_at, o.created_at 
                     FROM orders o 
                     WHERE o.user_id = ? 
                     AND (o.updated_at IS NOT NULL OR o.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY))
                     ORDER BY COALESCE(o.updated_at, o.created_at) DESC";
    
    $stmt = $conn->prepare($orders_query);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            // For orders with status updates
            if ($row['status'] == 'processing' || $row['status'] == 'shipped' || $row['status'] == 'delivered') {
                $notification_id = 'order_' . $row['id'] . '_' . $row['status'];
                $is_read = in_array($notification_id, $seen_notifications);
                
                $title = '';
                $message = '';
                $icon = '';
                
                if ($row['status'] == 'processing') {
                    $title = 'Order Processing';
                    $message = 'Your order #' . $row['id'] . ' is now being processed.';
                    $icon = 'cog';
                } elseif ($row['status'] == 'shipped') {
                    $title = 'Order Shipped';
                    $message = 'Your order #' . $row['id'] . ' has been shipped and is on its way!';
                    $icon = 'truck';
                } elseif ($row['status'] == 'delivered') {
                    $title = 'Order Delivered';
                    $message = 'Your order #' . $row['id'] . ' has been delivered. Please mark it as received once you verify all items.';
                    $icon = 'box';
                }
                
                $notifications[] = [
                    'id' => $notification_id,
                    'type' => 'order_status',
                    'title' => $title,
                    'message' => $message,
                    'icon' => $icon,
                    'time' => $row['updated_at'] ? $row['updated_at'] : $row['created_at'],
                    'link' => 'order-details.php?id=' . $row['id'],
                    'is_read' => $is_read
                ];
            }
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
    
    if (!isset($_SESSION['user_seen_notifications'])) {
        $_SESSION['user_seen_notifications'] = [];
    }
    
    if (!in_array($notification_id, $_SESSION['user_seen_notifications'])) {
        $_SESSION['user_seen_notifications'][] = $notification_id;
    }
    
    // Redirect back to the page
    header('Location: my-notifications.php');
    exit;
}

// Mark all notifications as read
if (isset($_POST['mark_all_read'])) {
    $notifications = getUserNotifications($conn, $user_id);
    
    if (!isset($_SESSION['user_seen_notifications'])) {
        $_SESSION['user_seen_notifications'] = [];
    }
    
    foreach ($notifications as $notification) {
        if (!in_array($notification['id'], $_SESSION['user_seen_notifications'])) {
            $_SESSION['user_seen_notifications'][] = $notification['id'];
        }
    }
    
    // Redirect back to the page
    header('Location: my-notifications.php');
    exit;
}

// Get notifications
$notifications = getUserNotifications($conn, $user_id);
$unread_count = 0;

foreach ($notifications as $notification) {
    if (!$notification['is_read']) {
        $unread_count++;
    }
}

// Page title
$pageTitle = "My Notifications";
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Notifications - CyberZone</title>
    <link rel="stylesheet" href="css/styles.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .notifications-container {
            max-width: 800px;
            margin: 0 auto;
            padding: 2rem;
        }
        
        .notifications-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 2rem;
        }
        
        .notifications-header h1 {
            font-size: 1.75rem;
            font-weight: 700;
            color: #1e293b;
            margin: 0;
        }
        
        .notifications-header .badge {
            background-color: #ef4444;
            color: white;
            font-size: 0.75rem;
            font-weight: 600;
            padding: 0.25rem 0.5rem;
            border-radius: 20px;
            margin-left: 0.5rem;
        }
        
        .notifications-list {
            background-color: #ffffff;
            border-radius: 0.75rem;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
            overflow: hidden;
            border: 1px solid #e2e8f0;
        }
        
        .notification-item {
            padding: 1.5rem;
            border-bottom: 1px solid #e2e8f0;
            display: flex;
            gap: 1rem;
            transition: all 0.15s ease;
        }
        
        .notification-item:hover {
            background-color: #f8fafc;
        }
        
        .notification-item.unread {
            background-color: rgba(37, 99, 235, 0.05);
        }
        
        .notification-item.unread:hover {
            background-color: rgba(37, 99, 235, 0.1);
        }
        
        .notification-icon {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.25rem;
            flex-shrink: 0;
        }
        
        .notification-icon.order-status {
            background-color: rgba(37, 99, 235, 0.05);
            color: #2563eb;
        }
        
        .notification-content {
            flex: 1;
        }
        
        .notification-title {
            font-weight: 600;
            font-size: 1rem;
            margin-bottom: 0.5rem;
            color: #1e293b;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        
        .notification-message {
            font-size: 0.9rem;
            color: #64748b;
            margin-bottom: 0.75rem;
        }
        
        .notification-time {
            font-size: 0.8rem;
            color: #94a3b8;
        }
        
        .notification-actions {
            display: flex;
            gap: 0.5rem;
            margin-top: 1rem;
        }
        
        .notification-badge {
            display: inline-flex;
            align-items: center;
            padding: 0.25rem 0.5rem;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
            margin-left: 0.5rem;
        }
        
        .notification-badge.unread {
            background-color: rgba(37, 99, 235, 0.05);
            color: #2563eb;
        }
        
        .notification-action-btn {
            padding: 0.5rem 0.75rem;
            border-radius: 0.5rem;
            font-size: 0.8rem;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            cursor: pointer;
            transition: all 0.15s ease;
        }
        
        .notification-action-btn.view {
            background-color: rgba(37, 99, 235, 0.05);
            color: #2563eb;
            border: 1px solid #2563eb;
        }
        
        .notification-action-btn.view:hover {
            background-color: #2563eb;
            color: white;
        }
        
        .notification-action-btn.mark-read {
            background-color: #f8fafc;
            color: #64748b;
            border: 1px solid #e2e8f0;
        }
        
        .notification-action-btn.mark-read:hover {
            background-color: #64748b;
            color: white;
            border-color: #64748b;
        }
        
        .empty-state {
            text-align: center;
            padding: 3rem 1.5rem;
            color: #64748b;
        }
        
        .empty-state i {
            font-size: 3rem;
            margin-bottom: 1rem;
            color: #e2e8f0;
        }
        
        .empty-state h3 {
            font-size: 1.25rem;
            font-weight: 600;
            margin-bottom: 0.5rem;
            color: #1e293b;
        }
        
        .empty-state p {
            margin-bottom: 1.5rem;
            max-width: 500px;
            margin-left: auto;
            margin-right: auto;
        }
        
        .mark-all-read-btn {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.75rem 1.5rem;
            background-color: #ffffff;
            color: #2563eb;
            border: 1px solid #2563eb;
            border-radius: 0.5rem;
            font-weight: 600;
            font-size: 0.875rem;
            cursor: pointer;
            transition: all 0.15s ease;
        }
        
        .mark-all-read-btn:hover {
            background-color: rgba(37, 99, 235, 0.05);
        }
        
        @media (max-width: 768px) {
            .notifications-container {
                padding: 1.5rem;
            }
            
            .notifications-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 1rem;
            }
            
            .notification-item {
                flex-direction: column;
            }
            
            .notification-actions {
                flex-direction: column;
            }
            
            .notification-action-btn {
                width: 100%;
                justify-content: center;
            }
        }
    </style>
</head>
<body>
    <!-- Header -->
    <?php include 'includes/header.php'; ?>
    
    <!-- Main Content -->
    <main class="main-content">
        <div class="notifications-container">
            <div class="notifications-header">
                <h1>
                    My Notifications
                    <?php if ($unread_count > 0): ?>
                    <span class="badge"><?php echo $unread_count; ?> unread</span>
                    <?php endif; ?>
                </h1>
                
                <?php if ($unread_count > 0): ?>
                <form action="" method="POST">
                    <input type="hidden" name="mark_all_read" value="1">
                    <button type="submit" class="mark-all-read-btn">
                        <i class="fas fa-check-double"></i> Mark All as Read
                    </button>
                </form>
                <?php endif; ?>
            </div>
            
            <div class="notifications-list">
                <?php if (count($notifications) > 0): ?>
                    <?php foreach ($notifications as $notification): ?>
                        <?php
                        $iconClass = '';
                        $icon = '';
                        
                        if ($notification['type'] === 'order_status') {
                            $iconClass = 'order-status';
                            $icon = $notification['icon'] ?? 'shopping-bag';
                        }
                        
                        $timeAgo = getTimeAgo(strtotime($notification['time']));
                        ?>
                        <div class="notification-item <?php echo $notification['is_read'] ? '' : 'unread'; ?>">
                            <div class="notification-icon <?php echo $iconClass; ?>">
                                <i class="fas fa-<?php echo $icon; ?>"></i>
                            </div>
                            <div class="notification-content">
                                <div class="notification-title">
                                    <?php echo $notification['title']; ?>
                                    <?php if (!$notification['is_read']): ?>
                                    <span class="notification-badge unread">New</span>
                                    <?php endif; ?>
                                </div>
                                <div class="notification-message"><?php echo $notification['message']; ?></div>
                                <div class="notification-time"><?php echo $timeAgo; ?></div>
                                
                                <div class="notification-actions">
                                    <a href="<?php echo $notification['link']; ?>" class="notification-action-btn view">
                                        <i class="fas fa-eye"></i> View Details
                                    </a>
                                    
                                    <?php if (!$notification['is_read']): ?>
                                    <form action="" method="POST" style="display: inline;">
                                        <input type="hidden" name="mark_read" value="1">
                                        <input type="hidden" name="notification_id" value="<?php echo $notification['id']; ?>">
                                        <button type="submit" class="notification-action-btn mark-read">
                                            <i class="fas fa-check"></i> Mark as Read
                                        </button>
                                    </form>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-bell-slash"></i>
                        <h3>No Notifications</h3>
                        <p>You don't have any notifications at the moment. We'll notify you when there are updates to your orders.</p>
                        <a href="index.php" class="btn-primary">
                            <i class="fas fa-home"></i> Back to Home
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </main>
    
    <!-- Footer -->
    <?php include 'includes/footer.php'; ?>
    
    <script>
        // Get time ago function
        function getTimeAgo(timestamp) {
            const seconds = Math.floor((new Date() - timestamp * 1000) / 1000);
            
            let interval = Math.floor(seconds / 31536000);
            if (interval >= 1) {
                return interval + " year" + (interval === 1 ? "" : "s") + " ago";
            }
            
            interval = Math.floor(seconds / 2592000);
            if (interval >= 1) {
                return interval + " month" + (interval === 1 ? "" : "s") + " ago";
            }
            
            interval = Math.floor(seconds / 86400);
            if (interval >= 1) {
                return interval + " day" + (interval === 1 ? "" : "s") + " ago";
            }
            
            interval = Math.floor(seconds / 3600);
            if (interval >= 1) {
                return interval + " hour" + (interval === 1 ? "" : "s") + " ago";
            }
            
            interval = Math.floor(seconds / 60);
            if (interval >= 1) {
                return interval + " minute" + (interval === 1 ? "" : "s") + " ago";
            }
            
            return "Just now";
        }
    </script>
</body>
</html>

<?php
// Helper function to get time ago string
function getTimeAgo($timestamp) {
    $current_time = time();
    $time_difference = $current_time - $timestamp;
    
    $seconds = $time_difference;
    $minutes = round($seconds / 60);
    $hours = round($seconds / 3600);
    $days = round($seconds / 86400);
    $weeks = round($seconds / 604800);
    $months = round($seconds / 2629440);
    $years = round($seconds / 31553280);
    
    if ($seconds <= 60) {
        return "Just now";
    } elseif ($minutes <= 60) {
        return $minutes == 1 ? "1 minute ago" : "$minutes minutes ago";
    } elseif ($hours <= 24) {
        return $hours == 1 ? "1 hour ago" : "$hours hours ago";
    } elseif ($days <= 7) {
        return $days == 1 ? "1 day ago" : "$days days ago";
    } elseif ($weeks <= 4.3) {
        return $weeks == 1 ? "1 week ago" : "$weeks weeks ago";
    } elseif ($months <= 12) {
        return $months == 1 ? "1 month ago" : "$months months ago";
    } else {
        return $years == 1 ? "1 year ago" : "$years years ago";
    }
}
?>
