<?php
// Include necessary files
require_once '../config.php';
require_once '../includes/functions.php';

// Set page title
$pageTitle = 'Delivery Fees';

// Check if admin is logged in
session_start();
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    // Display JavaScript alert if not logged in
    echo "<script>alert('You are not logged in. Please log in to access your account.');</script>";
    header('Location: ../index.php');
    exit;  // Stop further execution of the script
}

// Admin is logged in
$adminEmail = $_SESSION['email'] ?? 'Admin'; // Using email from your session

// Process form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Add or update delivery fee
    if (isset($_POST['action']) && $_POST['action'] === 'save_fee') {
        $feeId = isset($_POST['fee_id']) ? intval($_POST['fee_id']) : 0;
        $city = trim($_POST['city']);
        $state = trim($_POST['state']);
        $feeAmount = floatval($_POST['fee_amount']);
        $minOrder = floatval($_POST['min_order']);
        $estimatedDays = trim($_POST['estimated_days']);
        $isAvailable = ($_POST['status'] === 'Active') ? 1 : 0;
        
        // Validate inputs
        if (empty($city) || empty($state) || $feeAmount < 0 || $minOrder < 0 || empty($estimatedDays)) {
            echo json_encode(['success' => false, 'message' => 'Please fill all required fields with valid values.', 'alert' => '<Alert message="Please fill all required fields with valid values." type="error" />']);
            exit;
        }
        
        if ($feeId > 0) {
            // Update existing fee
            $stmt = $conn->prepare("UPDATE delivery_fees SET city = ?, state = ?, fee_amount = ?, 
                                    min_order_free_delivery = ?, estimated_days = ?, is_available = ? 
                                    WHERE id = ?");
            $stmt->bind_param("ssddsii", $city, $state, $feeAmount, $minOrder, $estimatedDays, $isAvailable, $feeId);
            
            if ($stmt->execute()) {
                echo json_encode(['success' => true, 'message' => 'Delivery fee updated successfully.', 'alert' => '<Alert message="Delivery fee updated successfully." type="success" />']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Error updating delivery fee: ' . $conn->error, 'alert' => '<Alert message="Error updating delivery fee: ' . $conn->error . '" type="error" />']);
            }
        } else {
            // Check if city/state combination already exists
            $checkStmt = $conn->prepare("SELECT id FROM delivery_fees WHERE city = ? AND state = ?");
            $checkStmt->bind_param("ss", $city, $state);
            $checkStmt->execute();
            $checkResult = $checkStmt->get_result();
            
            if ($checkResult->num_rows > 0) {
                echo json_encode(['success' => false, 'message' => 'A delivery fee for this city and state already exists.', 'alert' => '<Alert message="A delivery fee for this city and state already exists." type="error" />']);
                exit;
            }
            
            // Add new fee
            $stmt = $conn->prepare("INSERT INTO delivery_fees (city, state, fee_amount, min_order_free_delivery, 
                                   estimated_days, is_available) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("ssddsi", $city, $state, $feeAmount, $minOrder, $estimatedDays, $isAvailable);
            
            if ($stmt->execute()) {
                echo json_encode(['success' => true, 'message' => 'Delivery fee added successfully.', 'alert' => '<Alert message="Delivery fee added successfully." type="success" />']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Error adding delivery fee: ' . $conn->error, 'alert' => '<Alert message="Error adding delivery fee: ' . $conn->error . '" type="error" />']);
            }
        }
        exit;
    }
    
    // Delete delivery fee
    if (isset($_POST['action']) && $_POST['action'] === 'delete_fee') {
        $feeId = intval($_POST['fee_id']);
        
        $stmt = $conn->prepare("DELETE FROM delivery_fees WHERE id = ?");
        $stmt->bind_param("i", $feeId);
        
        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'Delivery fee deleted successfully.', 'alert' => '<Alert message="Delivery fee deleted successfully." type="success" />']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Error deleting delivery fee: ' . $conn->error, 'alert' => '<Alert message="Error deleting delivery fee: ' . $conn->error . '" type="error" />']);
        }
        exit;
    }
    
    // Toggle status
    if (isset($_POST['action']) && $_POST['action'] === 'toggle_status') {
        $feeId = intval($_POST['fee_id']);
        $newStatus = intval($_POST['status']);
        
        $stmt = $conn->prepare("UPDATE delivery_fees SET is_available = ? WHERE id = ?");
        $stmt->bind_param("ii", $newStatus, $feeId);
        
        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'Status updated successfully.', 'alert' => '<Alert message="Status updated successfully." type="success" />']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Error updating status: ' . $conn->error, 'alert' => '<Alert message="Error updating status: ' . $conn->error . '" type="error" />']);
        }
        exit;
    }
    
    // Bulk actions
    if (isset($_POST['action']) && $_POST['action'] === 'bulk_action') {
        $ids = $_POST['ids'];
        $bulkAction = $_POST['bulk_action'];
        
        if (empty($ids)) {
            echo json_encode(['success' => false, 'message' => 'No items selected.', 'alert' => '<Alert message="No items selected." type="error" />']);
            exit;
        }
        
        $idArray = explode(',', $ids);
        $idList = implode(',', array_map('intval', $idArray));
        
        if ($bulkAction === 'delete') {
            $query = "DELETE FROM delivery_fees WHERE id IN ($idList)";
            if ($conn->query($query)) {
                echo json_encode(['success' => true, 'message' => count($idArray) . ' items deleted successfully.', 'alert' => '<Alert message="' . count($idArray) . ' items deleted successfully." type="success" />']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Error deleting items: ' . $conn->error, 'alert' => '<Alert message="Error deleting items: ' . $conn->error . '" type="error" />']);
            }
        } else if ($bulkAction === 'activate' || $bulkAction === 'deactivate') {
            $status = ($bulkAction === 'activate') ? 1 : 0;
            $query = "UPDATE delivery_fees SET is_available = $status WHERE id IN ($idList)";
            
            if ($conn->query($query)) {
                echo json_encode(['success' => true, 'message' => count($idArray) . ' items updated successfully.', 'alert' => '<Alert message="' . count($idArray) . ' items updated successfully." type="success" />']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Error updating items: ' . $conn->error, 'alert' => '<Alert message="Error updating items: ' . $conn->error . '" type="error" />']);
            }
        }
        exit;
    }
}

// Get statistics
$totalQuery = "SELECT COUNT(*) as total FROM delivery_fees";
$totalResult = $conn->query($totalQuery);
$totalLocations = ($totalResult) ? $totalResult->fetch_assoc()['total'] : 0;

$activeQuery = "SELECT COUNT(*) as active FROM delivery_fees WHERE is_available = 1";
$activeResult = $conn->query($activeQuery);
$activeLocations = ($activeResult) ? $activeResult->fetch_assoc()['active'] : 0;

$avgFeeQuery = "SELECT AVG(fee_amount) as avg_fee FROM delivery_fees";
$avgFeeResult = $conn->query($avgFeeQuery);
$avgFee = ($avgFeeResult) ? $avgFeeResult->fetch_assoc()['avg_fee'] : 0;

$freeDeliveryQuery = "SELECT COUNT(*) as free_count FROM delivery_fees WHERE min_order_free_delivery > 0";
$freeDeliveryResult = $conn->query($freeDeliveryQuery);
$freeDeliveryLocations = ($freeDeliveryResult) ? $freeDeliveryResult->fetch_assoc()['free_count'] : 0;

// Get states for filter
$statesQuery = "SELECT DISTINCT state FROM delivery_fees ORDER BY state";
$statesResult = $conn->query($statesQuery);
$states = [];
if ($statesResult && $statesResult->num_rows > 0) {
    while ($row = $statesResult->fetch_assoc()) {
        $states[] = $row['state'];
    }
}

// Pagination
$page = isset($_GET['page']) ? intval($_GET['page']) : 1;
$limit = 10;
$offset = ($page - 1) * $limit;

// Get total number of records for pagination
$countQuery = "SELECT COUNT(*) as total FROM delivery_fees";
$countResult = $conn->query($countQuery);
$totalRecords = ($countResult) ? $countResult->fetch_assoc()['total'] : 0;
$totalPages = ceil($totalRecords / $limit);

// Get delivery fees with pagination
$query = "SELECT * FROM delivery_fees ORDER BY id DESC LIMIT $offset, $limit";
$result = $conn->query($query);
$deliveryFees = [];
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $deliveryFees[] = $row;
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Delivery Fees - Mugna Admin</title>
    <link rel="stylesheet" href="css/admin-styles.css">
    <link rel="shortcut icon" href="../images/mugna-icon.png" type="image/x-icon">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .fee-amount, .min-order {
            font-family: 'Courier New', monospace;
            font-weight: 600;
        }
        
        .status-toggle {
            cursor: pointer;
        }
        
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
            background-color: #fefefe;
            margin: 5% auto;
            padding: 20px;
            border-radius: var(--border-radius);
            box-shadow: var(--box-shadow);
            width: 90%;
            max-width: 600px;
            animation: modalFadeIn 0.3s;
        }
        
        @keyframes modalFadeIn {
            from {opacity: 0; transform: translateY(-20px);}
            to {opacity: 1; transform: translateY(0);}
        }
        
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 1px solid var(--gray-200);
        }
        
        .modal-header h2 {
            font-size: 1.25rem;
            font-weight: 600;
            color: var(--gray-800);
        }
        
        .close-modal {
            color: var(--gray-500);
            font-size: 1.5rem;
            cursor: pointer;
        }
        
        .close-modal:hover {
            color: var(--danger-color);
        }
        
        .modal-body {
            margin-bottom: 20px;
        }
        
        .modal-footer {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            padding-top: 10px;
            border-top: 1px solid var(--gray-200);
        }
        
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
            margin-bottom: 15px;
        }
        
        @media (max-width: 576px) {
            .form-row {
                grid-template-columns: 1fr;
            }
        }
        
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 500;
            color: var(--gray-700);
        }
        
        .form-group input, .form-group select {
            width: 100%;
            padding: 8px 12px;
            border: 1px solid var(--gray-300);
            border-radius: var(--border-radius);
            transition: var(--transition);
        }
        
        .form-group input:focus, .form-group select:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
        }
        
        .days-badge {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 0.75rem;
            font-weight: 500;
            background-color: rgba(59, 130, 246, 0.1);
            color: var(--info-color);
        }
        
        .fee-card {
            background-color: white;
            border-radius: var(--border-radius);
            box-shadow: var(--box-shadow);
            padding: 1.5rem;
            margin-bottom: 1.5rem;
        }
        
        .fee-card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
        }
        
        .fee-card-title {
            font-size: 1.25rem;
            font-weight: 600;
            color: var(--gray-800);
        }
        
        .fee-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 1.5rem;
        }
        
        .stat-item {
            background-color: var(--gray-50);
            border-radius: var(--border-radius);
            padding: 1rem;
            display: flex;
            flex-direction: column;
        }
        
        .stat-label {
            font-size: 0.875rem;
            color: var(--gray-500);
            margin-bottom: 0.5rem;
        }
        
        .stat-value {
            font-size: 1.5rem;
            font-weight: 600;
            color: var(--gray-800);
        }
        
        .alert {
            padding: 10px 15px;
            margin-bottom: 15px;
            border-radius: var(--border-radius);
            display: none;
        }
        
        .alert-success {
            background-color: rgba(16, 185, 129, 0.1);
            border: 1px solid rgba(16, 185, 129, 0.2);
            color: #065f46;
        }
        
        .alert-error {
            background-color: rgba(239, 68, 68, 0.1);
            border: 1px solid rgba(239, 68, 68, 0.2);
            color: #991b1b;
        }
        
        .form-error {
            color: #991b1b;
            font-size: 0.75rem;
            margin-top: 4px;
            display: none;
        }
        .btn-search{
            padding-bottom: 2rem;
        }
    </style>
</head>
<body>
    <div class="admin-container">
        <!-- Sidebar -->
        <?php include 'includes/sidebar.php'; ?>
        
        <!-- Main Content -->
        <div class="main-content">
            <!-- Header -->
            <?php include 'includes/header.php'; ?>
            
            <!-- Content Wrapper -->
            <div class="content-wrapper">
                <div class="content-header">
                    <h1>Manage Delivery Fees</h1>
                    <button class="btn-primary" id="addFeeBtn">
                        <i class="fas fa-plus"></i> Add New Fee
                    </button>
                </div>
                
                <!-- Alert Messages -->
                <div id="alertSuccess" class="alert alert-success"></div>
                <div id="alertError" class="alert alert-error"></div>
                
                <!-- Stats Cards -->
                <div class="fee-card">
                    <div class="fee-card-header">
                        <h2 class="fee-card-title">Delivery Fee Statistics</h2>
                    </div>
                    <div class="fee-stats">
                        <div class="stat-item">
                            <span class="stat-label">Total Locations</span>
                            <span class="stat-value"><?php echo $totalLocations; ?></span>
                        </div>
                        <div class="stat-item">
                            <span class="stat-label">Active Locations</span>
                            <span class="stat-value"><?php echo $activeLocations; ?></span>
                        </div>
                        <div class="stat-item">
                            <span class="stat-label">Average Fee</span>
                            <span class="stat-value">₱<?php echo number_format($avgFee, 2, '.', ','); ?></span>
                        </div>
                        <div class="stat-item">
                            <span class="stat-label">Free Delivery Locations</span>
                            <span class="stat-value"><?php echo $freeDeliveryLocations; ?></span>
                        </div>
                    </div>
                </div>
                
                <!-- Filters -->
                <div class="fee-card">
                    <div class="filters-container">
                        <div class="filters">
                            <div class="filter-group">
                                <label for="stateFilter">State</label>
                                <select id="stateFilter">
                                    <option value="">All States</option>
                                    <?php foreach ($states as $state): ?>
                                    <option value="<?php echo htmlspecialchars($state); ?>"><?php echo htmlspecialchars($state); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="filter-group">
                                <label for="statusFilter">Status</label>
                                <select id="statusFilter">
                                    <option value="">All Status</option>
                                    <option value="Active">Active</option>
                                    <option value="Inactive">Inactive</option>
                                </select>
                            </div>
                            <div class="filter-group">
                                <label for="feeRangeFilter">Fee Range</label>
                                <select id="feeRangeFilter">
                                    <option value="">All Fees</option>
                                    <option value="0-5">₱0 - ₱5</option>
                                    <option value="5-10">₱5 - ₱10</option>
                                    <option value="10-15">₱10 - ₱15</option>
                                    <option value="15+">₱15+</option>
                                </select>
                            </div>
                        </div>
                        <div class="search-container">
                            <input type="text" id="searchInput" placeholder="Search by city or state...">
                            <button class="btn-search">
                                <i class="fas fa-search"></i>
                            </button>
                        </div>
                    </div>
                </div>
                
                <!-- Table Card -->
                <div class="table-card">
                    <div class="table-actions">
                        <div class="bulk-actions">
                            <select id="bulkActionSelect">
                                <option value="">Bulk Actions</option>
                                <option value="activate">Activate Selected</option>
                                <option value="deactivate">Deactivate Selected</option>
                                <option value="delete">Delete Selected</option>
                            </select>
                            <button class="btn-outline" id="applyBulkAction">Apply</button>
                        </div>
                        <div class="table-view-options">
                            <button class="btn-outline active" id="gridView">
                                <i class="fas fa-table"></i>
                            </button>
                            <button class="btn-outline" id="listView">
                                <i class="fas fa-list"></i>
                            </button>
                        </div>
                    </div>
                    <div class="table-responsive">
                        <table>
                            <thead>
                                <tr>
                                    <th>
                                        <input type="checkbox" id="select-all">
                                    </th>
                                    <th>ID</th>
                                    <th>City</th>
                                    <th>State</th>
                                    <th>Fee Amount</th>
                                    <th>Min Order for Free</th>
                                    <th>Est. Days</th>
                                    <th>Status</th>
                                    <th>Last Updated</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($deliveryFees)): ?>
                                <tr>
                                    <td colspan="10" class="text-center">No delivery fees found.</td>
                                </tr>
                                <?php else: ?>
                                <?php foreach ($deliveryFees as $fee): ?>
                                <tr>
                                    <td>
                                        <input type="checkbox" class="fee-select" value="<?php echo $fee['id']; ?>">
                                    </td>
                                    <td><?php echo $fee['id']; ?></td>
                                    <td><?php echo htmlspecialchars($fee['city']); ?></td>
                                    <td><?php echo htmlspecialchars($fee['state']); ?></td>
                                    <td class="fee-amount">₱<?php echo number_format($fee['fee_amount'], 2, '.', ','); ?></td>
                                    <td class="min-order">₱<?php echo number_format($fee['min_order_free_delivery'], 2, '.', ','); ?></td>
                                    <td><span class="days-badge"><?php echo htmlspecialchars($fee['estimated_days']); ?></span></td>
                                    <td>
                                        <span class="status-badge <?php echo $fee['is_available'] ? 'active' : 'pending'; ?> status-toggle" data-id="<?php echo $fee['id']; ?>">
                                            <?php echo $fee['is_available'] ? 'Active' : 'Inactive'; ?>
                                        </span>
                                    </td>
                                    <td><?php echo date('Y-m-d', strtotime($fee['updated_at'])); ?></td>
                                    <td>
                                        <div class="table-actions">
                                            <button class="btn-icon edit-fee" data-id="<?php echo $fee['id']; ?>">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <button class="btn-icon delete-fee" data-id="<?php echo $fee['id']; ?>">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                    <div class="pagination">
                        <div class="pagination-info">
                            Showing <?php echo $offset + 1; ?> to <?php echo min($offset + $limit, $totalRecords); ?> of <?php echo $totalRecords; ?> entries
                        </div>
                        <div class="pagination-controls">
                            <a href="?page=<?php echo max(1, $page - 1); ?>" class="btn-pagination <?php echo ($page <= 1) ? 'disabled' : ''; ?>">
                                <i class="fas fa-chevron-left"></i>
                            </a>
                            
                            <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                            <a href="?page=<?php echo $i; ?>" class="btn-pagination <?php echo ($page == $i) ? 'active' : ''; ?>">
                                <?php echo $i; ?>
                            </a>
                            <?php endfor; ?>
                            
                            <a href="?page=<?php echo min($totalPages, $page + 1); ?>" class="btn-pagination <?php echo ($page >= $totalPages) ? 'disabled' : ''; ?>">
                                <i class="fas fa-chevron-right"></i>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Add/Edit Fee Modal -->
    <div id="feeModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 id="modalTitle">Add New Delivery Fee</h2>
                <span class="close-modal">&times;</span>
            </div>
            <div class="modal-body">
                <form id="feeForm">
                    <input type="hidden" id="feeId" name="fee_id" value="">
                    <input type="hidden" name="action" value="save_fee">
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="city">City</label>
                            <input type="text" id="city" name="city" required>
                            <div id="cityError" class="form-error">Please enter a city name.</div>
                        </div>
                        <div class="form-group">
                            <label for="state">State</label>
                            <input type="text" id="state" name="state" required>
                            <div id="stateError" class="form-error">Please enter a state name.</div>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="feeAmount">Fee Amount (₱)</label>
                            <input type="number" id="feeAmount" name="fee_amount" step="0.01" min="0" required>
                            <div id="feeAmountError" class="form-error">Please enter a valid fee amount.</div>
                        </div>
                        <div class="form-group">
                            <label for="minOrder">Min Order for Free Delivery (₱)</label>
                            <input type="number" id="minOrder" name="min_order" step="0.01" min="0" required>
                            <div id="minOrderError" class="form-error">Please enter a valid minimum order amount.</div>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="estimatedDays">Estimated Delivery Days</label>
                            <input type="text" id="estimatedDays" name="estimated_days" placeholder="e.g. 1-3" required>
                            <div id="estimatedDaysError" class="form-error">Please enter estimated delivery days.</div>
                        </div>
                        <div class="form-group">
                            <label for="status">Status</label>
                            <select id="status" name="status" required>
                                <option value="Active">Active</option>
                                <option value="Inactive">Inactive</option>
                            </select>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-outline" id="cancelBtn">Cancel</button>
                <button type="button" class="btn-primary" id="saveBtn">Save Changes</button>
            </div>
        </div>
    </div>
    
    <!-- Delete Confirmation Modal -->
    <div id="deleteModal" class="modal">
        <div class="modal-content" style="max-width: 400px;">
            <div class="modal-header">
                <h2>Confirm Deletion</h2>
                <span class="close-modal">&times;</span>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to delete this delivery fee? This action cannot be undone.</p>
                <form id="deleteForm">
                    <input type="hidden" id="deleteId" name="fee_id" value="">
                    <input type="hidden" name="action" value="delete_fee">
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-outline" id="cancelDeleteBtn">Cancel</button>
                <button type="button" class="btn-primary" style="background-color: var(--danger-color);" id="confirmDeleteBtn">Delete</button>
            </div>
        </div>
    </div>
    
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="js/admin.js"></script>
    <script>
        $(document).ready(function() {
            // Show alert message
            function showAlert(message, type) {
                const alertId = type === 'success' ? '#alertSuccess' : '#alertError';
                $(alertId).text(message).fadeIn();
                
                setTimeout(function() {
                    $(alertId).fadeOut();
                }, 3000);
            }
            
            // Validate form
            function validateForm() {
                let isValid = true;
                
                // Reset error messages
                $('.form-error').hide();
                
                // Validate city
                if ($('#city').val().trim() === '') {
                    $('#cityError').show();
                    isValid = false;
                }
                
                // Validate state
                if ($('#state').val().trim() === '') {
                    $('#stateError').show();
                    isValid = false;
                }
                
                // Validate fee amount
                const feeAmount = parseFloat($('#feeAmount').val());
                if (isNaN(feeAmount) || feeAmount < 0) {
                    $('#feeAmountError').show();
                    isValid = false;
                }
                
                // Validate min order
                const minOrder = parseFloat($('#minOrder').val());
                if (isNaN(minOrder) || minOrder < 0) {
                    $('#minOrderError').show();
                    isValid = false;
                }
                
                // Validate estimated days
                if ($('#estimatedDays').val().trim() === '') {
                    $('#estimatedDaysError').show();
                    isValid = false;
                }
                
                return isValid;
            }
            
            // Modal functionality
            const feeModal = document.getElementById('feeModal');
            const deleteModal = document.getElementById('deleteModal');
            const addFeeBtn = document.getElementById('addFeeBtn');
            const closeModalBtns = document.querySelectorAll('.close-modal');
            const cancelBtn = document.getElementById('cancelBtn');
            const cancelDeleteBtn = document.getElementById('cancelDeleteBtn');
            const saveBtn = document.getElementById('saveBtn');
            const confirmDeleteBtn = document.getElementById('confirmDeleteBtn');
            const modalTitle = document.getElementById('modalTitle');
            const feeForm = document.getElementById('feeForm');
            
            // Select all checkbox
            const selectAll = document.getElementById('select-all');
            const checkboxes = document.querySelectorAll('.fee-select');
            
            // Edit and delete buttons
            const editBtns = document.querySelectorAll('.edit-fee');
            const deleteBtns = document.querySelectorAll('.delete-fee');
            
            // Status toggle
            const statusToggles = document.querySelectorAll('.status-toggle');
            
            // Open add fee modal
            addFeeBtn.addEventListener('click', function() {
                modalTitle.textContent = 'Add New Delivery Fee';
                feeForm.reset();
                document.getElementById('feeId').value = '';
                $('.form-error').hide();
                feeModal.style.display = 'block';
            });
            
            // Close modals
            closeModalBtns.forEach(btn => {
                btn.addEventListener('click', function() {
                    feeModal.style.display = 'none';
                    deleteModal.style.display = 'none';
                });
            });
            
            cancelBtn.addEventListener('click', function() {
                feeModal.style.display = 'none';
            });
            
            cancelDeleteBtn.addEventListener('click', function() {
                deleteModal.style.display = 'none';
            });
            
            // Close modal when clicking outside
            window.addEventListener('click', function(event) {
                if (event.target === feeModal) {
                    feeModal.style.display = 'none';
                }
                if (event.target === deleteModal) {
                    deleteModal.style.display = 'none';
                }
            });
            
            // Select all checkboxes
            selectAll.addEventListener('change', function() {
                checkboxes.forEach(checkbox => {
                    checkbox.checked = selectAll.checked;
                });
            });
            
            // Edit fee
            editBtns.forEach(btn => {
                btn.addEventListener('click', function() {
                    const feeId = this.getAttribute('data-id');
                    modalTitle.textContent = 'Edit Delivery Fee';
                    document.getElementById('feeId').value = feeId;
                    $('.form-error').hide();
                    
                    // Fetch fee data via AJAX
                    $.ajax({
                        url: 'get_delivery_fee.php',
                        type: 'GET',
                        data: { id: feeId },
                        dataType: 'json',
                        success: function(response) {
                            if (response.success) {
                                const fee = response.data;
                                $('#city').val(fee.city);
                                $('#state').val(fee.state);
                                $('#feeAmount').val(fee.fee_amount);
                                $('#minOrder').val(fee.min_order_free_delivery);
                                $('#estimatedDays').val(fee.estimated_days);
                                $('#status').val(fee.is_available == 1 ? 'Active' : 'Inactive');
                                
                                feeModal.style.display = 'block';
                            } else {
                                showAlert(response.message, 'error');
                            }
                        },
                        error: function() {
                            showAlert('Error fetching delivery fee data.', 'error');
                        }
                    });
                });
            });
            
            // Delete fee
            deleteBtns.forEach(btn => {
                btn.addEventListener('click', function() {
                    const feeId = this.getAttribute('data-id');
                    document.getElementById('deleteId').value = feeId;
                    deleteModal.style.display = 'block';
                });
            });
            
            // Toggle status
            statusToggles.forEach(toggle => {
                toggle.addEventListener('click', function() {
                    const feeId = this.getAttribute('data-id');
                    const currentStatus = this.textContent.trim();
                    const newStatus = currentStatus === 'Active' ? 0 : 1;
                    
                    // Update status via AJAX
                    $.ajax({
                        url: 'delivery_fees.php',
                        type: 'POST',
                        data: {
                            action: 'toggle_status',
                            fee_id: feeId,
                            status: newStatus
                        },
                        dataType: 'json',
                        success: function(response) {
                            if (response.success) {
                                // Update UI
                                const statusText = newStatus === 1 ? 'Active' : 'Inactive';
                                const statusClass = newStatus === 1 ? 'active' : 'pending';
                                
                                toggle.textContent = statusText;
                                toggle.className = `status-badge ${statusClass} status-toggle`;
                                
                                showAlert(response.message, 'success');
                            } else {
                                showAlert(response.message, 'error');
                            }
                        },
                        error: function() {
                            showAlert('Error updating status.', 'error');
                        }
                    });
                });
            });
            
            // Save changes
            saveBtn.addEventListener('click', function() {
                if (validateForm()) {
                    // Submit form via AJAX
                    $.ajax({
                        url: 'delivery_fees.php',
                        type: 'POST',
                        data: $('#feeForm').serialize(),
                        dataType: 'json',
                        success: function(response) {
                            if (response.success) {
                                feeModal.style.display = 'none';
                                showAlert(response.message, 'success');
                                
                                // Reload page to show updated data
                                setTimeout(function() {
                                    location.reload();
                                }, 1500);
                            } else {
                                showAlert(response.message, 'error');
                            }
                        },
                        error: function() {
                            showAlert('Error saving delivery fee.', 'error');
                        }
                    });
                }
            });
            
            // Confirm delete
            confirmDeleteBtn.addEventListener('click', function() {
                // Submit delete form via AJAX
                $.ajax({
                    url: 'delivery_fees.php',
                    type: 'POST',
                    data: $('#deleteForm').serialize(),
                    dataType: 'json',
                    success: function(response) {
                        if (response.success) {
                            deleteModal.style.display = 'none';
                            showAlert(response.message, 'success');
                            
                            // Reload page to show updated data
                            setTimeout(function() {
                                location.reload();
                            }, 1500);
                        } else {
                            showAlert(response.message, 'error');
                        }
                    },
                    error: function() {
                        showAlert('Error deleting delivery fee.', 'error');
                    }
                });
            });
            
            // Search functionality
            const searchInput = document.getElementById('searchInput');
            searchInput.addEventListener('keyup', function() {
                const searchTerm = this.value.toLowerCase();
                const rows = document.querySelectorAll('tbody tr');
                
                rows.forEach(row => {
                    const city = row.cells[2].textContent.toLowerCase();
                    const state = row.cells[3].textContent.toLowerCase();
                    
                    if (city.includes(searchTerm) || state.includes(searchTerm)) {
                        row.style.display = '';
                    } else {
                        row.style.display = 'none';
                    }
                });
            });
            
            // Filter functionality
            const stateFilter = document.getElementById('stateFilter');
            const statusFilter = document.getElementById('statusFilter');
            const feeRangeFilter = document.getElementById('feeRangeFilter');
            
            function applyFilters() {
                const stateValue = stateFilter.value.toLowerCase();
                const statusValue = statusFilter.value;
                const feeRangeValue = feeRangeFilter.value;
                
                const rows = document.querySelectorAll('tbody tr');
                
                rows.forEach(row => {
                    if (row.cells.length < 4) return; // Skip "No delivery fees found" row
                    
                    const state = row.cells[3].textContent.toLowerCase();
                    const status = row.cells[7].querySelector('.status-badge').textContent.trim();
                    const feeAmount = parseFloat(row.cells[4].textContent.replace('₱', '').replace(',', ''));
                    
                    let showRow = true;
                    
                    if (stateValue && state !== stateValue) {
                        showRow = false;
                    }
                    
                    if (statusValue && status !== statusValue) {
                        showRow = false;
                    }
                    
                    if (feeRangeValue) {
                        let [min, max] = feeRangeValue.split('-');
                        min = parseFloat(min);
                        max = (max === '+') ? Infinity : parseFloat(max);
                        
                        if (feeAmount < min || feeAmount > max) {
                            showRow = false;
                        }
                    }
                    
                    row.style.display = showRow ? '' : 'none';
                });
            }
            
            stateFilter.addEventListener('change', applyFilters);
            statusFilter.addEventListener('change', applyFilters);
            feeRangeFilter.addEventListener('change', applyFilters);
            
            // Pagination
            const paginationBtns = document.querySelectorAll('.btn-pagination');
            paginationBtns.forEach(btn => {
                if (!btn.classList.contains('disabled')) {
                    btn.addEventListener('click', function() {
                        paginationBtns.forEach(b => b.classList.remove('active'));
                        if (!this.querySelector('i')) {
                            this.classList.add('active');
                        }
                    });
                }
            });

            // View Toggle
            const gridViewBtn = document.getElementById('gridView');
            const listViewBtn = document.getElementById('listView');

            gridViewBtn.addEventListener('click', function() {
                gridViewBtn.classList.add('active');
                listViewBtn.classList.remove('active');
                // Add logic to show grid view and hide list view if needed
            });

            listViewBtn.addEventListener('click', function() {
                listViewBtn.classList.add('active');
                gridViewBtn.classList.remove('active');
                // Add logic to show list view and hide grid view if needed
            });
        });
    </script>
</body>
</html>
