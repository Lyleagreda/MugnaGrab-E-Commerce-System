<?php
session_start();

// Check if the user is logged in
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
   // Display JavaScript alert if not logged in
   echo "<script>alert('You are not logged in. Please log in to access your account.');</script>";
   
   // Redirect after alert
   echo "<script>window.location.href = 'index.php';</script>";
   exit;  // Stop further execution of the script
}

// Check if user is admin
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
   echo "<script>alert('You do not have permission to access this page.');</script>";
   echo "<script>window.location.href = '../index.php';</script>";
   exit;
}

// Include database connection
include '../db.php';

// Set page title for sidebar highlighting
$pageTitle = "Users";

// Get admin email
$adminEmail = $_SESSION['email'] ?? 'Admin';

// Pagination settings
$usersPerPage = 10;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $usersPerPage;

// Search and filter
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$sortBy = isset($_GET['sort']) ? trim($_GET['sort']) : 'id';
$sortOrder = isset($_GET['order']) ? trim($_GET['order']) : 'ASC';

// Build query conditions
$conditions = [];
$params = [];

if (!empty($search)) {
    $conditions[] = "(email LIKE :search OR first_name LIKE :search OR last_name LIKE :search OR phone LIKE :search)";
    $params[':search'] = "%$search%";
}

$whereClause = !empty($conditions) ? "WHERE " . implode(' AND ', $conditions) : "";

// Validate sort parameters to prevent SQL injection
$allowedSortColumns = ['id', 'email', 'first_name', 'last_name', 'created_at'];
$sortBy = in_array($sortBy, $allowedSortColumns) ? $sortBy : 'id';

$allowedSortOrders = ['ASC', 'DESC'];
$sortOrder = in_array(strtoupper($sortOrder), $allowedSortOrders) ? strtoupper($sortOrder) : 'ASC';

// Get total users count
$countQuery = "SELECT COUNT(*) FROM users $whereClause";
$stmt = $conn->prepare($countQuery);
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value);
}
$stmt->execute();
$totalUsers = $stmt->fetchColumn();
$totalPages = ceil($totalUsers / $usersPerPage);

// Get users data
$query = "SELECT *, DATE_FORMAT(last_login, '%Y-%m-%d %H:%i:%s') as formatted_last_login FROM users $whereClause ORDER BY $sortBy $sortOrder LIMIT :offset, :limit";
$stmt = $conn->prepare($query);
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value);
}
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->bindValue(':limit', $usersPerPage, PDO::PARAM_INT);
$stmt->execute();
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get user statistics
$statsQuery = "SELECT 
    COUNT(*) as total_users,
    COUNT(CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN 1 END) as new_users_30days,
    COUNT(CASE WHEN profile_picture IS NOT NULL THEN 1 END) as users_with_profile_pic
FROM users";
$stmt = $conn->prepare($statsQuery);
$stmt->execute();
$stats = $stmt->fetch(PDO::FETCH_ASSOC);

// Handle user deletion
if (isset($_POST['delete_user']) && isset($_POST['user_id'])) {
    $userId = (int)$_POST['user_id'];
    
    try {
        // Begin transaction
        $conn->beginTransaction();
        
        // Delete user addresses first (assuming foreign key constraint)
        $stmt = $conn->prepare("DELETE FROM user_addresses WHERE user_id = :user_id");
        $stmt->bindParam(':user_id', $userId);
        $stmt->execute();
        
        // Delete the user
        $stmt = $conn->prepare("DELETE FROM users WHERE id = :user_id");
        $stmt->bindParam(':user_id', $userId);
        $stmt->execute();
        
        // Commit transaction
        $conn->commit();
        
        // Set success message
        $successMessage = "User deleted successfully!";
        
        // Redirect to refresh the page
        header("Location: users_accounts.php?success=deleted");
        exit;
    } catch (PDOException $e) {
        // Rollback transaction on error
        $conn->rollBack();
        $errorMessage = "Error deleting user: " . $e->getMessage();
    }
}

// Get success message from URL parameter
$successMessage = isset($_GET['success']) ? ($_GET['success'] === 'deleted' ? 'User deleted successfully!' : '') : '';

// Add this AJAX search functionality to the PHP section (add before the closing PHP tag)
// Add a route for AJAX search if needed
if (isset($_GET['ajax_search']) && $_GET['ajax_search'] === 'true') {
    // This would be used for a full AJAX implementation
    // For now, we're using the simpler approach with page reloads
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'users' => $users,
        'total' => $totalUsers
    ]);
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Accounts - Mugna Admin</title>
    <link rel="stylesheet" href="css/admin-styles.css">
    <link rel="shortcut icon" href="../images/mugna-icon.png" type="image/x-icon">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* Modern User Accounts Page Styles */
        .users-container {
            margin-bottom: 2rem;
        }
        
        .stats-cards {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(240px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }
        
        .stat-card {
            background-color: white;
            border-radius: 12px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
            padding: 1.5rem;
            display: flex;
            align-items: center;
            transition: all 0.3s ease;
            border-left: 4px solid transparent;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 15px rgba(0, 0, 0, 0.1);
        }
        
        .stat-card.primary {
            border-left-color: var(--primary-color);
        }
        
        .stat-card.success {
            border-left-color: var(--success-color);
        }
        
        .stat-card.warning {
            border-left-color: var(--warning-color);
        }
        
        .stat-icon {
            width: 60px;
            height: 60px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            margin-right: 1rem;
        }
        
        .stat-icon.primary {
            background-color: rgba(37, 99, 235, 0.1);
            color: var(--primary-color);
        }
        
        .stat-icon.success {
            background-color: rgba(16, 185, 129, 0.1);
            color: var(--success-color);
        }
        
        .stat-icon.warning {
            background-color: rgba(245, 158, 11, 0.1);
            color: var(--warning-color);
        }
        
        .stat-details {
            flex: 1;
        }
        
        .stat-value {
            font-size: 1.75rem;
            font-weight: 700;
            color: var(--gray-800);
            margin-bottom: 0.25rem;
        }
        
        .stat-label {
            color: var(--gray-500);
            font-size: 0.875rem;
        }
        
        /* Search and Filter Bar */
        .filter-bar {
            background-color: white;
            border-radius: 12px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            display: flex;
            flex-wrap: wrap;
            gap: 1rem;
            align-items: center;
        }
        
        .search-box {
            flex: 1;
            min-width: 250px;
            position: relative;
            margin-top: 2rem;
        }
        
        .search-box input {
            width: 100%;
            padding: 0.75rem 1rem 0.75rem 2.5rem;
            border: 1px solid var(--gray-200);
            border-radius: 8px;
            font-size: 0.875rem;
            transition: all 0.3s ease;
        }
        
        .search-box input:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
            outline: none;
        }
        
        .search-box i {
            position: absolute;
            left: 1rem;
            top: 50%;
            transform: translateY(-50%);
            color: var(--gray-400);
        }
        
        .filter-group {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .filter-group label {
            color: var(--gray-500);
            font-size: 0.875rem;
        }
        
        .filter-group select {
            padding: 0.75rem 1rem;
            border: 1px solid var(--gray-200);
            border-radius: 8px;
            font-size: 0.875rem;
            color: var(--gray-700);
            background-color: white;
            transition: all 0.3s ease;
        }
        
        .filter-group select:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
            outline: none;
        }
        
        /* Users Table */
        .users-table-container {
            background-color: white;
            border-radius: 12px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
            overflow: hidden;
            margin-bottom: 1.5rem;
        }
        
        .users-table-header {
            padding: 1.5rem;
            border-bottom: 1px solid var(--gray-200);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .users-table-title {
            font-size: 1.25rem;
            font-weight: 600;
            color: var(--gray-800);
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .users-table-title i {
            color: var(--primary-color);
        }
        
        .table-actions {
            display: flex;
            gap: 0.5rem;
        }
        
        .table-responsive {
            overflow-x: auto;
        }
        
        .users-table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .users-table th {
            background-color: var(--gray-50);
            color: var(--gray-600);
            font-weight: 600;
            text-align: left;
            padding: 1rem 1.5rem;
            border-bottom: 1px solid var(--gray-200);
            white-space: nowrap;
        }
        
        .users-table th.sortable {
            cursor: pointer;
            user-select: none;
        }
        
        .users-table th.sortable:hover {
            background-color: var(--gray-100);
        }
        
        .users-table th i {
            margin-left: 0.25rem;
            font-size: 0.75rem;
        }
        
        .users-table td {
            padding: 1rem 1.5rem;
            border-bottom: 1px solid var(--gray-200);
            vertical-align: middle;
        }
        
        .users-table tr:last-child td {
            border-bottom: none;
        }
        
        .users-table tr:hover td {
            background-color: var(--gray-50);
        }
        
        /* User Profile */
        .user-profile {
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        
        .profile-image {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            overflow: hidden;
            background-color: var(--gray-100);
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            color: var(--primary-color);
            font-size: 1rem;
        }
        
        .profile-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .user-info {
            display: flex;
            flex-direction: column;
        }
        
        .user-name {
            font-weight: 600;
            color: var(--gray-800);
        }
        
        .user-email {
            font-size: 0.875rem;
            color: var(--gray-500);
        }
        
        /* Action Buttons */
        .row-actions {
            display: flex;
            gap: 0.5rem;
        }
        
        .btn-icon {
            width: 32px;
            height: 32px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            background-color: var(--gray-100);
            border: none;
            color: var(--gray-600);
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .btn-icon:hover {
            background-color: var(--gray-200);
        }
        
        .btn-icon.view:hover {
            background-color: rgba(59, 130, 246, 0.1);
            color: var(--info-color);
        }
        
        .btn-icon.delete:hover {
            background-color: rgba(239, 68, 68, 0.1);
            color: #ef4444;
        }
        
        /* Pagination */
        .pagination-container {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1rem 1.5rem;
            border-top: 1px solid var(--gray-200);
        }
        
        .pagination-info {
            color: var(--gray-500);
            font-size: 0.875rem;
        }
        
        .pagination {
            display: flex;
            gap: 0.25rem;
        }
        
        .page-item {
            display: inline-block;
        }
        
        .page-link {
            display: flex;
            align-items: center;
            justify-content: center;
            min-width: 32px;
            height: 32px;
            padding: 0 0.5rem;
            border-radius: 8px;
            background-color: var(--gray-100);
            color: var(--gray-700);
            font-size: 0.875rem;
            transition: all 0.3s ease;
        }
        
        .page-link:hover {
            background-color: var(--gray-200);
        }
        
        .page-item.active .page-link {
            background-color: var(--primary-color);
            color: white;
        }
        
        .page-item.disabled .page-link {
            opacity: 0.5;
            cursor: not-allowed;
        }
        
        /* Alert Messages */
        .alert {
            padding: 1rem 1.5rem;
            border-radius: 8px;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }
        
        .alert-success {
            background-color: rgba(16, 185, 129, 0.1);
            color: var(--success-color);
            border-left: 4px solid var(--success-color);
        }
        
        .alert-danger {
            background-color: rgba(239, 68, 68, 0.1);
            color: #ef4444;
            border-left: 4px solid #ef4444;
        }
        
        .alert i {
            font-size: 1.25rem;
        }
        
        /* User View Modal */
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            overflow: auto;
            background-color: rgba(0, 0, 0, 0.5);
        }
        
        .modal-content {
            background-color: white;
            margin: 5% auto;
            padding: 0;
            border-radius: 12px;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
            width: 90%;
            max-width: 600px;
            animation: modalFadeIn 0.3s;
        }
        
        @keyframes modalFadeIn {
            from {opacity: 0; transform: translateY(-50px);}
            to {opacity: 1; transform: translateY(0);}
        }
        
        .modal-header {
            padding: 1.5rem;
            border-bottom: 1px solid var(--gray-200);
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        
        .modal-title {
            font-size: 1.25rem;
            font-weight: 600;
            color: var(--gray-800);
        }
        
        .modal-close {
            background: none;
            border: none;
            font-size: 1.5rem;
            color: var(--gray-500);
            cursor: pointer;
            transition: color 0.3s ease;
        }
        
        .modal-close:hover {
            color: var(--gray-700);
        }
        
        .modal-body {
            padding: 1.5rem;
        }
        
        .user-detail-header {
            display: flex;
            align-items: center;
            gap: 1.5rem;
            margin-bottom: 2rem;
        }
        
        .user-detail-avatar {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            overflow: hidden;
            background-color: var(--gray-100);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2.5rem;
            font-weight: 600;
            color: var(--primary-color);
        }
        
        .user-detail-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .user-detail-info h3 {
            font-size: 1.5rem;
            font-weight: 600;
            color: var(--gray-800);
            margin-bottom: 0.25rem;
        }
        
        .user-detail-info p {
            color: var(--gray-500);
            margin-bottom: 0.5rem;
        }
        
        .user-details-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 1.5rem;
        }
        
        .detail-group {
            margin-bottom: 1.5rem;
        }
        
        .detail-label {
            font-size: 0.875rem;
            color: var(--gray-500);
            margin-bottom: 0.25rem;
        }
        
        .detail-value {
            font-weight: 500;
            color: var(--gray-800);
        }
        
        .modal-footer {
            padding: 1rem 1.5rem;
            border-top: 1px solid var(--gray-200);
            display: flex;
            justify-content: flex-end;
            gap: 1rem;
        }
        
        /* Responsive Adjustments */
        @media (max-width: 768px) {
            .filter-bar {
                flex-direction: column;
                align-items: stretch;
            }
            
            .search-box {
                width: 100%;
            }
            
            .user-details-grid {
                grid-template-columns: 1fr;
            }
            
            .stats-cards {
                grid-template-columns: 1fr;
            }
        }
        
        /* Empty State */
        .empty-state {
            padding: 3rem 1.5rem;
            text-align: center;
        }
        
        .empty-icon {
            font-size: 3rem;
            color: var(--gray-300);
            margin-bottom: 1rem;
        }
        
        .empty-text {
            color: var(--gray-500);
            margin-bottom: 1.5rem;
        }

    /* Enhanced Modal Styles */
    .btn-danger {
        background-color: #ef4444;
        height: 2.5rem;
        width: 8rem;
        color: white;
        border: none;
        border-radius: 8px;
    }
    
    .btn-danger:hover {
        background-color: #dc2626;
    }
    
    .btn-danger:disabled {
        opacity: 0.7;
        cursor: not-allowed;
    }
    
    .user-to-delete-info {
        border-left: 4px solid #ef4444;
    }
    
    /* Loading Spinner */
    .spinner {
        display: inline-block;
        width: 1rem;
        height: 1rem;
        border: 2px solid rgba(255, 255, 255, 0.3);
        border-radius: 50%;
        border-top-color: white;
        animation: spin 1s ease-in-out infinite;
        margin-right: 0.5rem;
    }
    
    @keyframes spin {
        to {
            transform: rotate(360deg);
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

            <!-- Users Content -->
            <div class="content-wrapper">
                <div class="content-header">
                    <h1>User Accounts</h1>
                    <a href="#" class="btn btn-primary" id="exportUsersBtn">
                        <i class="fas fa-download"></i> Export Users
                    </a>
                </div>

                <?php if (!empty($successMessage)): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i>
                    <span><?php echo $successMessage; ?></span>
                </div>
                <?php endif; ?>

                <?php if (isset($errorMessage)): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle"></i>
                    <span><?php echo $errorMessage; ?></span>
                </div>
                <?php endif; ?>

                <div class="users-container">
                    <!-- Stats Cards -->
                    <div class="stats-cards">
                        <div class="stat-card primary">
                            <div class="stat-icon primary">
                                <i class="fas fa-users"></i>
                            </div>
                            <div class="stat-details">
                                <div class="stat-value"><?php echo number_format($stats['total_users']); ?></div>
                                <div class="stat-label">Total Users</div>
                            </div>
                        </div>
                        
                        <div class="stat-card success">
                            <div class="stat-icon success">
                                <i class="fas fa-user-plus"></i>
                            </div>
                            <div class="stat-details">
                                <div class="stat-value"><?php echo number_format($stats['new_users_30days']); ?></div>
                                <div class="stat-label">New Users (30 days)</div>
                            </div>
                        </div>
                        
                        <div class="stat-card warning">
                            <div class="stat-icon warning">
                                <i class="fas fa-image"></i>
                            </div>
                            <div class="stat-details">
                                <div class="stat-value"><?php echo number_format($stats['users_with_profile_pic']); ?></div>
                                <div class="stat-label">Users with Profile Picture</div>
                            </div>
                        </div>
                    </div>

                    <!-- Search and Filter Bar -->
                    <form action="users_accounts.php" method="get" class="filter-bar" id="filterForm">
    <div class="search-box">
        <i class="fas fa-search"></i>
        <input type="text" name="search" id="searchInput" placeholder="Search users..." value="<?php echo htmlspecialchars($search); ?>">
    </div>
    
    <div class="filter-group">
        <label for="sort-by">Sort By:</label>
        <select id="sort-by" name="sort">
            <option value="id" <?php echo $sortBy === 'id' ? 'selected' : ''; ?>>ID</option>
            <option value="first_name" <?php echo $sortBy === 'first_name' ? 'selected' : ''; ?>>Name</option>
            <option value="email" <?php echo $sortBy === 'email' ? 'selected' : ''; ?>>Email</option>
            <option value="created_at" <?php echo $sortBy === 'created_at' ? 'selected' : ''; ?>>Date Joined</option>
        </select>
    </div>
    
    <div class="filter-group">
        <label for="sort-order">Order:</label>
        <select id="sort-order" name="order">
            <option value="ASC" <?php echo $sortOrder === 'ASC' ? 'selected' : ''; ?>>Ascending</option>
            <option value="DESC" <?php echo $sortOrder === 'DESC' ? 'selected' : ''; ?>>Descending</option>
        </select>
    </div>
</form>

                    <!-- Users Table -->
                    <div class="users-table-container">
                        <div class="users-table-header">
                            <h2 class="users-table-title">
                                <i class="fas fa-user-circle"></i> User Accounts
                            </h2>
                            <div class="table-actions">
                                <button class="btn btn-outline" id="refreshTableBtn">
                                    <i class="fas fa-sync-alt"></i> Refresh
                                </button>
                            </div>
                        </div>
                        
                        <div class="table-responsive">
                            <?php if (count($users) > 0): ?>
                            <table class="users-table">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>User</th>
                                        <th>Phone</th>
                                        <th>Date Joined</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($users as $user): ?>
                                    <tr>
                                        <td><?php echo $user['id']; ?></td>
                                        <td>
                                            <div class="user-profile">
                                                <div class="profile-image">
                                                    <?php if (!empty($user['profile_picture']) && file_exists('../' . $user['profile_picture'])): ?>
                                                        <img src="../<?php echo $user['profile_picture']; ?>" alt="<?php echo $user['first_name']; ?>">
                                                    <?php else: ?>
                                                        <?php echo substr($user['first_name'] ?? '', 0, 1) . substr($user['last_name'] ?? '', 0, 1); ?>
                                                    <?php endif; ?>
                                                </div>
                                                <div class="user-info">
                                                    <div class="user-name"><?php echo $user['first_name'] . ' ' . $user['last_name']; ?></div>
                                                    <div class="user-email"><?php echo $user['email']; ?></div>
                                                </div>
                                            </div>
                                        </td>
                                        <td><?php echo $user['phone'] ?? 'N/A'; ?></td>
                                        <td><?php echo date('M d, Y', strtotime($user['created_at'])); ?></td>
                                        <td>
                                            <div class="row-actions">
                                                <button class="btn-icon view view-user-btn" data-id="<?php echo $user['id']; ?>" data-lastlogin="<?php echo !empty($user['formatted_last_login']) ? date('M d, Y H:i:s', strtotime($user['formatted_last_login'])) : 'Never'; ?>" title="View Details">
                                                    <i class="fas fa-eye"></i>
                                                </button>
                                                <button class="btn-icon delete delete-user-btn" data-id="<?php echo $user['id']; ?>" title="Delete User">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                            <?php else: ?>
                            <div class="empty-state">
                                <div class="empty-icon">
                                    <i class="fas fa-users-slash"></i>
                                </div>
                                <h3>No Users Found</h3>
                                <p class="empty-text">No users match your search criteria. Try adjusting your filters or search terms.</p>
                                <a href="users_accounts.php" class="btn btn-outline">
                                    <i class="fas fa-redo"></i> Reset Filters
                                </a>
                            </div>
                            <?php endif; ?>
                        </div>
                        
                        <?php if ($totalPages > 1): ?>
                        <div class="pagination-container">
                            <div class="pagination-info">
                                Showing <?php echo $offset + 1; ?> to <?php echo min($offset + $usersPerPage, $totalUsers); ?> of <?php echo $totalUsers; ?> users
                            </div>
                            <ul class="pagination">
                                <li class="page-item <?php echo ($page <= 1) ? 'disabled' : ''; ?>">
                                    <a class="page-link" href="?page=1<?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?><?php echo !empty($sortBy) ? '&sort=' . urlencode($sortBy) : ''; ?><?php echo !empty($sortOrder) ? '&order=' . urlencode($sortOrder) : ''; ?>" aria-label="First">
                                        <i class="fas fa-angle-double-left"></i>
                                    </a>
                                </li>
                                <li class="page-item <?php echo ($page <= 1) ? 'disabled' : ''; ?>">
                                    <a class="page-link" href="?page=<?php echo $page - 1; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?><?php echo !empty($sortBy) ? '&sort=' . urlencode($sortBy) : ''; ?><?php echo !empty($sortOrder) ? '&order=' . urlencode($sortOrder) : ''; ?>" aria-label="Previous">
                                        <i class="fas fa-angle-left"></i>
                                    </a>
                                </li>
                                
                                <?php
                                $startPage = max(1, $page - 2);
                                $endPage = min($totalPages, $page + 2);
                                
                                if ($startPage > 1) {
                                    echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                                }
                                
                                for ($i = $startPage; $i <= $endPage; $i++) {
                                    echo '<li class="page-item ' . ($page == $i ? 'active' : '') . '">';
                                    echo '<a class="page-link" href="?page=' . $i . (!empty($search) ? '&search=' . urlencode($search) : '') . (!empty($sortBy) ? '&sort=' . urlencode($sortBy) : '') . (!empty($sortOrder) ? '&order=' . urlencode($sortOrder) : '') . '">' . $i . '</a>';
                                    echo '</li>';
                                }
                                
                                if ($endPage < $totalPages) {
                                    echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                                }
                                ?>
                                
                                <li class="page-item <?php echo ($page >= $totalPages) ? 'disabled' : ''; ?>">
                                    <a class="page-link" href="?page=<?php echo $page + 1; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?><?php echo !empty($sortBy) ? '&sort=' . urlencode($sortBy) : ''; ?><?php echo !empty($sortOrder) ? '&order=' . urlencode($sortOrder) : ''; ?>" aria-label="Next">
                                        <i class="fas fa-angle-right"></i>
                                    </a>
                                </li>
                                <li class="page-item <?php echo ($page >= $totalPages) ? 'disabled' : ''; ?>">
                                    <a class="page-link" href="?page=<?php echo $totalPages; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?><?php echo !empty($sortBy) ? '&sort=' . urlencode($sortBy) : ''; ?><?php echo !empty($sortOrder) ? '&order=' . urlencode($sortOrder) : ''; ?>" aria-label="Last">
                                        <i class="fas fa-angle-double-right"></i>
                                    </a>
                                </li>
                            </ul>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <!-- User View Modal -->
    <div id="userViewModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">User Details</h3>
                <button class="modal-close">&times;</button>
            </div>
            <div class="modal-body" id="userDetailsContent">
                <!-- User details will be loaded here via AJAX -->
                <div class="text-center">
                    <i class="fas fa-spinner fa-spin fa-2x"></i>
                    <p>Loading user details...</p>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-outline modal-close-btn">Close</button>
            </div>
        </div>
    </div>

    <!-- Delete User Confirmation Modal -->
    
<div id="deleteUserModal" class="modal">
    <div class="modal-content" style="max-width: 400px;">
        <div class="modal-header">
            <h3 class="modal-title">Confirm Deletion</h3>
            <button class="modal-close">&times;</button>
        </div>
        <div class="modal-body">
            <p>Are you sure you want to delete this user? This action cannot be undone.</p>
            <p><strong>Warning:</strong> All associated data will also be deleted.</p>
            <div class="user-to-delete-info" style="margin-top: 15px; padding: 10px; background-color: #f8fafc; border-radius: 8px;">
                <div style="display: flex; align-items: center; gap: 10px;">
                    <div class="profile-image" id="delete-user-avatar" style="width: 40px; height: 40px;">
                        <!-- User avatar will be inserted here -->
                    </div>
                    <div>
                        <div id="delete-user-name" style="font-weight: 600; color: var(--gray-800);"></div>
                        <div id="delete-user-email" style="font-size: 0.875rem; color: var(--gray-500);"></div>
                    </div>
                </div>
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn btn-outline modal-close-btn">Cancel</button>
            <form action="users_accounts.php" method="post" id="deleteUserForm">
                <input type="hidden" name="delete_user" value="1">
                <input type="hidden" name="user_id" id="deleteUserId" value="">
                <button type="submit" class="btn btn-danger" id="confirmDeleteBtn">
                    <i class="fas fa-trash"></i> Delete User
                </button>
            </form>
        </div>
    </div>
</div>

    <script src="js/admin.js"></script>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // View User Modal
        const userViewModal = document.getElementById('userViewModal');
        const userDetailsContent = document.getElementById('userDetailsContent');
        const viewUserBtns = document.querySelectorAll('.view-user-btn');
        const modalCloseBtns = document.querySelectorAll('.modal-close, .modal-close-btn');
        
        // Delete User Modal
        const deleteUserModal = document.getElementById('deleteUserModal');
        const deleteUserBtns = document.querySelectorAll('.delete-user-btn');
        const deleteUserId = document.getElementById('deleteUserId');
        const deleteUserName = document.getElementById('delete-user-name');
        const deleteUserEmail = document.getElementById('delete-user-email');
        const deleteUserAvatar = document.getElementById('delete-user-avatar');
        const confirmDeleteBtn = document.getElementById('confirmDeleteBtn');
        
        // Real-time search and filter
        const searchInput = document.getElementById('searchInput');
        const sortBySelect = document.getElementById('sort-by');
        const sortOrderSelect = document.getElementById('sort-order');
        let searchTimeout;
        
        // Add event listeners for real-time search and filtering
        if (searchInput) {
            searchInput.addEventListener('input', function() {
                // Clear previous timeout
                clearTimeout(searchTimeout);
                
                // Set a timeout to prevent too many requests while typing
                searchTimeout = setTimeout(function() {
                    applyFilters();
                }, 300); // 300ms delay
            });
        }
        
        if (sortBySelect) {
            sortBySelect.addEventListener('change', applyFilters);
        }
        
        if (sortOrderSelect) {
            sortOrderSelect.addEventListener('change', applyFilters);
        }
        
        // Function to apply filters
        function applyFilters() {
            const searchValue = searchInput.value;
            const sortBy = sortBySelect.value;
            const sortOrder = sortOrderSelect.value;
            
            // Build URL with parameters
            let url = 'users_accounts.php?';
            if (searchValue) {
                url += 'search=' + encodeURIComponent(searchValue) + '&';
            }
            url += 'sort=' + encodeURIComponent(sortBy) + '&';
            url += 'order=' + encodeURIComponent(sortOrder);
            
            // Navigate to the URL
            window.location.href = url;
        }
        
        // Open View User Modal
        viewUserBtns.forEach(btn => {
            btn.addEventListener('click', function() {
                const userId = this.getAttribute('data-id');
                
                // Show modal
                userViewModal.style.display = 'block';
                
                // Load user details
                loadUserDetails(userId);
            });
        });
        
        // Open Delete User Modal with user details
        deleteUserBtns.forEach(btn => {
            btn.addEventListener('click', function() {
                const userId = this.getAttribute('data-id');
                const userRow = this.closest('tr');
                const userName = userRow.querySelector('.user-name').textContent;
                const userEmail = userRow.querySelector('.user-email').textContent;
                const profileImage = userRow.querySelector('.profile-image').innerHTML;
                
                // Set values in the modal
                deleteUserId.value = userId;
                deleteUserName.textContent = userName;
                deleteUserEmail.textContent = userEmail;
                deleteUserAvatar.innerHTML = profileImage;
                
                // Show the modal
                deleteUserModal.style.display = 'block';
                
                // Add loading state to delete button when clicked
                confirmDeleteBtn.addEventListener('click', function() {
                    this.disabled = true;
                    this.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Deleting...';
                    document.getElementById('deleteUserForm').submit();
                });
            });
        });
        
        // Close Modals
        modalCloseBtns.forEach(btn => {
            btn.addEventListener('click', function() {
                userViewModal.style.display = 'none';
                deleteUserModal.style.display = 'none';
            });
        });
        
        // Close modal when clicking outside
        window.addEventListener('click', function(event) {
            if (event.target === userViewModal) {
                userViewModal.style.display = 'none';
            }
            if (event.target === deleteUserModal) {
                deleteUserModal.style.display = 'none';
            }
        });
        
        // Refresh table button
        const refreshTableBtn = document.getElementById('refreshTableBtn');
        if (refreshTableBtn) {
            refreshTableBtn.addEventListener('click', function() {
                window.location.reload();
            });
        }
        
        // Export users button
        const exportUsersBtn = document.getElementById('exportUsersBtn');
        if (exportUsersBtn) {
            exportUsersBtn.addEventListener('click', function(e) {
                e.preventDefault();
                window.open('generate_user_report.php', '_blank');
            });
        }
        
        // Function to load user details
        function loadUserDetails(userId) {
            // In a real application, this would be an AJAX call to fetch user details
            // For this demo, we'll simulate it with a timeout
            
            userDetailsContent.innerHTML = `
                <div style="text-align: center; padding: 2rem;">
                    <i class="fas fa-spinner fa-spin fa-2x"></i>
                    <p>Loading user details...</p>
                </div>
            `;
            
            setTimeout(() => {
                // Find the user in the table
                const userRow = document.querySelector(`tr .view-user-btn[data-id="${userId}"]`).closest('tr');
                const userName = userRow.querySelector('.user-name').textContent;
                const userEmail = userRow.querySelector('.user-email').textContent;
                const userPhone = userRow.querySelector('td:nth-child(3)').textContent;
                const userJoined = userRow.querySelector('td:nth-child(4)').textContent;
                const userLastLogin = document.querySelector(`.view-user-btn[data-id="${userId}"]`).getAttribute('data-lastlogin');
                
                // Get profile image
                const profileImage = userRow.querySelector('.profile-image').innerHTML;
                
                userDetailsContent.innerHTML = `
                    <div class="user-detail-header">
                        <div class="user-detail-avatar">
                            ${profileImage}
                        </div>
                        <div class="user-detail-info">
                            <h3>${userName}</h3>
                            <p>${userEmail}</p>
                        </div>
                    </div>
                    
                    <div class="user-details-grid">
                        <div class="detail-group">
                            <div class="detail-label">User ID</div>
                            <div class="detail-value">${userId}</div>
                        </div>
                        
                        <div class="detail-group">
                            <div class="detail-label">Phone Number</div>
                            <div class="detail-value">${userPhone}</div>
                        </div>
                        
                        <div class="detail-group">
                            <div class="detail-label">Date Joined</div>
                            <div class="detail-value">${userJoined}</div>
                        </div>
                        
                        <div class="detail-group">
                            <div class="detail-label">Account Status</div>
                            <div class="detail-value">
                                <span class="role-badge success">Active</span>
                            </div>
                        </div>
                    </div>
                    
                        <div class="detail-group">
                            <div class="detail-label">Last Login</div>
                            <div class="detail-value">${userLastLogin}</div>
                        </div>
                    </div>
                    
                `;
            }, 500);
        }
    });
</script>
</body>
</html>
