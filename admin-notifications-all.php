<?php
session_start();
include '../config.php';

// Check if the user is logged in
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    // Display JavaScript alert if not logged in
    echo "<script>alert('You are not logged in. Please log in to access your account.');</script>";
    header('Location: ../index.php');
    exit;  // Stop further execution of the script
}

// Admin is logged in
$adminEmail = $_SESSION['email'] ?? 'Admin'; // Using email from your session

// Get filter parameters
$type_filter = isset($_GET['type']) ? $_GET['type'] : '';
$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : date('Y-m-d', strtotime('-7 days'));
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : date('Y-m-d');
$read_status = isset($_GET['read_status']) ? $_GET['read_status'] : '';

// Function to get notifications
function getNotifications($conn, $type_filter, $date_from, $date_to, $read_status) {
    $notifications = [];
    $seen_notifications = [];
    
    // Get seen notification IDs from session if exists
    if (isset($_SESSION['seen_notifications'])) {
        $seen_notifications = $_SESSION['seen_notifications'];
    }
    
    // Build queries based on filters
    $queries = [];
    $params = [];
    $types = "";
    
    // New orders query
    if (empty($type_filter) || $type_filter === 'new_order') {
        $new_orders_query = "SELECT o.id, o.created_at, u.first_name, u.last_name, o.total 
                             FROM orders o 
                             JOIN users u ON o.user_id = u.id 
                             WHERE DATE(o.created_at) BETWEEN ? AND ?";
        
        $params[] = $date_from;
        $params[] = $date_to;
        $types .= "ss";
        
        $queries['new_order'] = $new_orders_query;
    }
    
    // Status changes query
    if (empty($type_filter) || $type_filter === 'status_change') {
        $status_changes_query = "SELECT o.id, o.updated_at, o.status, u.first_name, u.last_name 
                                 FROM orders o 
                                 JOIN users u ON o.user_id = u.id 
                                 WHERE o.updated_at IS NOT NULL 
                                 AND DATE(o.updated_at) BETWEEN ? AND ? 
                                 AND o.status IN ('shipped', 'delivered', 'received')";
        
        $params[] = $date_from;
        $params[] = $date_to;
        $types .= "ss";
        
        $queries['status_change'] = $status_changes_query;
    }
    
    // New users query
    if (empty($type_filter) || $type_filter === 'new_user') {
        $new_users_query = "SELECT id, first_name, last_name, email, created_at 
                            FROM users 
                            WHERE DATE(created_at) BETWEEN ? AND ?";
        
        $params[] = $date_from;
        $params[] = $date_to;
        $types .= "ss";
        
        $queries['new_user'] = $new_users_query;
    }
    
    // Execute queries and collect notifications
    foreach ($queries as $type => $query) {
        $stmt = $conn->prepare($query);
        
        // Create specific parameters for this query
        $queryParams = [];
        $queryTypes = "";
        
        if ($type === 'new_order' || $type === 'status_change' || $type === 'new_user') {
            $queryParams[] = $date_from;
            $queryParams[] = $date_to;
            $queryTypes = "ss";
        }
        
        if (!empty($queryParams)) {
            $stmt->bind_param($queryTypes, ...$queryParams);
        }
        
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result && $result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                if ($type === 'new_order') {
                    $notification_id = 'order_' . $row['id'];
                    $is_read = in_array($notification_id, $seen_notifications);
                    
                    // Skip based on read status filter
                    if ($read_status === 'read' && !$is_read) continue;
                    if ($read_status === 'unread' && $is_read) continue;
                    
                    $notifications[] = [
                        'id' => $notification_id,
                        'type' => 'new_order',
                        'title' => 'New Order Placed',
                        'message' => 'Order #' . $row['id'] . ' for ₱' . number_format($row['total'], 2) . ' by ' . $row['first_name'] . ' ' . $row['last_name'],
                        'time' => $row['created_at'],
                        'link' => 'admin-payment-history.php?search=' . $row['id'],
                        'is_read' => $is_read
                    ];
                } elseif ($type === 'status_change') {
                    $notification_id = 'status_' . $row['id'] . '_' . $row['status'];
                    $is_read = in_array($notification_id, $seen_notifications);
                    
                    // Skip based on read status filter
                    if ($read_status === 'read' && !$is_read) continue;
                    if ($read_status === 'unread' && $is_read) continue;
                    
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
                } elseif ($type === 'new_user') {
                    $notification_id = 'user_' . $row['id'];
                    $is_read = in_array($notification_id, $seen_notifications);
                    
                    // Skip based on read status filter
                    if ($read_status === 'read' && !$is_read) continue;
                    if ($read_status === 'unread' && $is_read) continue;
                    
                    $notifications[] = [
                        'id' => $notification_id,
                        'type' => 'new_user',
                        'title' => 'New User Registration',
                        'message' => $row['first_name'] . ' ' . $row['last_name'] . ' (' . $row['email'] . ') has registered',
                        'time' => $row['created_at'],
                        'link' => 'users_accounts.php?search=' . $row['email'],
                        'is_read' => $is_read
                    ];
                }
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
    
    if (!isset($_SESSION['seen_notifications'])) {
        $_SESSION['seen_notifications'] = [];
    }
    
    if (!in_array($notification_id, $_SESSION['seen_notifications'])) {
        $_SESSION['seen_notifications'][] = $notification_id;
    }
    
    // Redirect back to the page
    header('Location: admin-notifications-all.php');
    exit;
}

// Mark all notifications as read
if (isset($_POST['mark_all_read'])) {
    $notifications = getNotifications($conn, $type_filter, $date_from, $date_to, $read_status);
    
    if (!isset($_SESSION['seen_notifications'])) {
        $_SESSION['seen_notifications'] = [];
    }
    
    foreach ($notifications as $notification) {
        if (!in_array($notification['id'], $_SESSION['seen_notifications'])) {
            $_SESSION['seen_notifications'][] = $notification['id'];
        }
    }
    
    // Redirect back to the page
    header('Location: admin-notifications-all.php');
    exit;
}

// Get notifications
$notifications = getNotifications($conn, $type_filter, $date_from, $date_to, $read_status);
$unread_count = 0;

foreach ($notifications as $notification) {
    if (!$notification['is_read']) {
        $unread_count++;
    }
}

// Page title
$pageTitle = "All Notifications";
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>All Notifications - CyberZone Admin</title>
    <link rel="stylesheet" href="css/admin-styles.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* Modern UI Styles */
        :root {
            --primary-color: #2563eb;
            --primary-light: #3b82f6;
            --primary-dark: #1d4ed8;
            --primary-bg: rgba(37, 99, 235, 0.05);
            --success-color: #10b981;
            --success-bg: rgba(16, 185, 129, 0.05);
            --warning-color: #f59e0b;
            --warning-bg: rgba(245, 158, 11, 0.05);
            --danger-color: #ef4444;
            --danger-bg: rgba(239, 68, 68, 0.05);
            --info-color: #0ea5e9;
            --info-bg: rgba(14, 165, 233, 0.05);
            --light-bg: #f8fafc;
            --card-bg: #ffffff;
            --border-color: #e2e8f0;
            --text-primary: #1e293b;
            --text-secondary: #64748b;
            --text-muted: #94a3b8;
            --border-radius: 0.75rem;
            --input-radius: 0.5rem;
            --shadow-sm: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
            --shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
            --shadow-md: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
            --shadow-lg: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
            --transition: all 0.3s ease;
            --transition-fast: all 0.15s ease;
        }

        body {
            background-color: var(--light-bg);
            color: var(--text-primary);
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        .content-wrapper {
            padding: 2rem;
        }

        .content-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 2rem;
        }

        .content-header h1 {
            font-size: 1.75rem;
            font-weight: 700;
            color: var(--text-primary);
            margin: 0;
            letter-spacing: -0.025em;
        }

        /* Filter Section */
        .filter-section {
            background-color: var(--card-bg);
            border-radius: var(--border-radius);
            padding: 1.5rem;
            margin-bottom: 2rem;
            box-shadow: var(--shadow);
            border: 1px solid var(--border-color);
        }

        .filter-form {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
        }

        .form-group {
            margin-bottom: 0;
        }

        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
            color: var(--text-secondary);
            font-size: 0.875rem;
        }

        .form-control {
            width: 100%;
            padding: 0.75rem 1rem;
            border: 1px solid var(--border-color);
            border-radius: var(--input-radius);
            background-color: var(--light-bg);
            color: var(--text-primary);
            font-size: 0.875rem;
            transition: var(--transition-fast);
        }

        .form-control:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
        }

        .filter-buttons {
            display: flex;
            gap: 0.75rem;
            align-items: flex-end;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 0.75rem 1.5rem;
            border-radius: var(--input-radius);
            font-weight: 600;
            font-size: 0.875rem;
            transition: var(--transition);
            cursor: pointer;
            border: none;
            gap: 0.5rem;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--primary-color), var(--primary-light));
            color: white;
            box-shadow: 0 4px 6px rgba(37, 99, 235, 0.2);
        }

        .btn-primary:hover {
            background: linear-gradient(135deg, var(--primary-dark), var(--primary-color));
            transform: translateY(-2px);
            box-shadow: 0 6px 8px rgba(37, 99, 235, 0.25);
        }

        .btn-outline {
            background-color: transparent;
            border: 1px solid var(--border-color);
            color: var(--text-secondary);
        }

        .btn-outline:hover {
            background-color: var(--light-bg);
            border-color: var(--text-secondary);
            color: var(--text-primary);
        }

        /* Notification List */
        .notification-list-wrapper {
            background-color: var(--card-bg);
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
            border: 1px solid var(--border-color);
            overflow: hidden;
        }

        .notification-list-header {
            padding: 1.5rem;
            border-bottom: 1px solid var(--border-color);
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .notification-list-title {
            font-size: 1.125rem;
            font-weight: 600;
            margin: 0;
        }

        .notification-list-actions {
            display: flex;
            gap: 1rem;
        }

        .notification-list {
            max-height: 600px;
            overflow-y: auto;
        }

        .notification-item {
            padding: 1.5rem;
            border-bottom: 1px solid var(--border-color);
            display: flex;
            gap: 1rem;
            transition: var(--transition-fast);
        }

        .notification-item:hover {
            background-color: var(--primary-bg);
        }

        .notification-item.unread {
            background-color: var(--primary-bg);
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

        .notification-icon.new-order {
            background-color: var(--primary-bg);
            color: var(--primary-color);
        }

        .notification-icon.status-change {
            background-color: var(--success-bg);
            color: var(--success-color);
        }

        .notification-icon.new-user {
            background-color: var(--info-bg);
            color: var(--info-color);
        }

        .notification-content {
            flex: 1;
        }

        .notification-title {
            font-weight: 600;
            font-size: 1rem;
            margin-bottom: 0.5rem;
            color: var(--text-primary);
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .notification-message {
            font-size: 0.9rem;
            color: var(--text-secondary);
            margin-bottom: 0.75rem;
        }

        .notification-time {
            font-size: 0.8rem;
            color: var(--text-muted);
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
            background-color: var(--primary-bg);
            color: var(--primary-color);
        }

        .notification-badge.read {
            background-color: var(--light-bg);
            color: var(--text-secondary);
        }

        .notification-action-btn {
            padding: 0.5rem 0.75rem;
            border-radius: var(--input-radius);
            font-size: 0.8rem;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            cursor: pointer;
            transition: var(--transition-fast);
        }

        .notification-action-btn.view {
            background-color: var(--primary-bg);
            color: var(--primary-color);
            border: 1px solid var(--primary-color);
        }

        .notification-action-btn.view:hover {
            background-color: var(--primary-color);
            color: white;
        }

        .notification-action-btn.mark-read {
            background-color: var(--light-bg);
            color: var(--text-secondary);
            border: 1px solid var(--border-color);
        }

        .notification-action-btn.mark-read:hover {
            background-color: var(--text-secondary);
            color: white;
            border-color: var(--text-secondary);
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 3rem 1.5rem;
            color: var(--text-secondary);
        }

        .empty-state i {
            font-size: 3rem;
            margin-bottom: 1rem;
            color: var(--border-color);
        }

        .empty-state h3 {
            font-size: 1.25rem;
            font-weight: 600;
            margin-bottom: 0.5rem;
            color: var(--text-primary);
        }

        .empty-state p {
            margin-bottom: 1.5rem;
            max-width: 500px;
            margin-left: auto;
            margin-right: auto;
        }

        /* Responsive Adjustments */
        @media (max-width: 768px) {
            .content-wrapper {
                padding: 1.5rem;
            }
            
            .content-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 1rem;
            }
            
            .filter-form {
                grid-template-columns: 1fr;
            }
            
            .filter-buttons {
                flex-direction: column;
                width: 100%;
            }
            
            .filter-buttons .btn {
                width: 100%;
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
    <div class="admin-container">
        <!-- Sidebar -->
        <?php include 'includes/sidebar.php'; ?>

        <!-- Main Content -->
        <main class="main-content">
            <!-- Header -->
            <?php include 'includes/header.php'; ?>

            <!-- All Notifications Content -->
            <div class="content-wrapper">
                <div class="content-header">
                    <h1>All Notifications</h1>
                </div>

                <!-- Filter Section -->
                <div class="filter-section">
                    <form action="" method="GET" class="filter-form">
                        <div class="form-group">
                            <label for="type">Notification Type</label>
                            <select name="type" id="type" class="form-control">
                                <option value="" <?php echo $type_filter === '' ? 'selected' : ''; ?>>All Types</option>
                                <option value="new_order" <?php echo $type_filter === 'new_order' ? 'selected' : ''; ?>>New Orders</option>
                                <option value="status_change" <?php echo $type_filter === 'status_change' ? 'selected' : ''; ?>>Status Changes</option>
                                <option value="new_user" <?php echo $type_filter === 'new_user' ? 'selected' : ''; ?>>New Users</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="date_from">Date From</label>
                            <input type="date" name="date_from" id="date_from" class="form-control" value="<?php echo $date_from; ?>">
                        </div>
                        <div class="form-group">
                            <label for="date_to">Date To</label>
                            <input type="date" name="date_to" id="date_to" class="form-control" value="<?php echo $date_to; ?>">
                        </div>
                        <div class="form-group">
                            <label for="read_status">Read Status</label>
                            <select name="read_status" id="read_status" class="form-control">
                                <option value="" <?php echo $read_status === '' ? 'selected' : ''; ?>>All</option>
                                <option value="read" <?php echo $read_status === 'read' ? 'selected' : ''; ?>>Read</option>
                                <option value="unread" <?php echo $read_status === 'unread' ? 'selected' : ''; ?>>Unread</option>
                            </select>
                        </div>
                        <div class="filter-buttons">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-filter"></i> Apply Filter
                            </button>
                            <a href="admin-notifications-all.php" class="btn btn-outline">
                                <i class="fas fa-redo"></i> Reset
                            </a>
                        </div>
                    </form>
                </div>

                <!-- Notifications List -->
                <div class="notification-list-wrapper">
                    <div class="notification-list-header">
                        <h3 class="notification-list-title">
                            Notifications
                            <?php if ($unread_count > 0): ?>
                            <span class="notification-badge unread"><?php echo $unread_count; ?> unread</span>
                            <?php endif; ?>
                        </h3>
                        <div class="notification-list-actions">
                            <?php if ($unread_count > 0): ?>
                            <form action="" method="POST">
                                <input type="hidden" name="mark_all_read" value="1">
                                <button type="submit" class="btn btn-outline">
                                    <i class="fas fa-check-double"></i> Mark All as Read
                                </button>
                            </form>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="notification-list">
                        <?php if (count($notifications) > 0): ?>
                            <?php foreach ($notifications as $notification): ?>
                                <?php
                                $iconClass = '';
                                $icon = '';
                                
                                if ($notification['type'] === 'new_order') {
                                    $iconClass = 'new-order';
                                    $icon = 'shopping-cart';
                                } elseif ($notification['type'] === 'status_change') {
                                    $iconClass = 'status-change';
                                    $icon = 'truck';
                                } elseif ($notification['type'] === 'new_user') {
                                    $iconClass = 'new-user';
                                    $icon = 'user-plus';
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
                                <h3>No Notifications Found</h3>
                                <p>There are no notifications matching your filter criteria. Try adjusting your filters or check back later.</p>
                                <a href="admin-notifications-all.php" class="btn btn-primary">
                                    <i class="fas fa-redo"></i> Reset Filters
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </main>
    </div>

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
