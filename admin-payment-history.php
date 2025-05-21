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
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';
$payment_method = isset($_GET['payment_method']) ? $_GET['payment_method'] : '';
$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : '';
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : '';
$search = isset($_GET['search']) ? $_GET['search'] : '';
$sort = isset($_GET['sort']) ? $_GET['sort'] : 'date_desc';

// Build the query with filters
$query = "SELECT o.*, 
         CONCAT(u.first_name, ' ', u.last_name) as customer_name, 
         u.email as customer_email
         FROM orders o 
         LEFT JOIN users u ON o.user_id = u.id 
         WHERE 1=1";

$params = [];
$types = "";

if (!empty($status_filter)) {
   $query .= " AND o.status = ?";
   $params[] = $status_filter;
   $types .= "s";
}

if (!empty($payment_method)) {
   $query .= " AND o.payment_method = ?";
   $params[] = $payment_method;
   $types .= "s";
}

if (!empty($date_from)) {
   $query .= " AND DATE(o.created_at) >= ?";
   $params[] = $date_from;
   $types .= "s";
}

if (!empty($date_to)) {
   $query .= " AND DATE(o.created_at) <= ?";
   $params[] = $date_to;
   $types .= "s";
}

if (!empty($search)) {
   $search_term = "%$search%";
   $query .= " AND (o.id LIKE ? OR CONCAT(u.first_name, ' ', u.last_name) LIKE ? OR u.email LIKE ?)";
   $params[] = $search_term;
   $params[] = $search_term;
   $params[] = $search_term;
   $types .= "sss";
}

// Add sorting
switch ($sort) {
   case 'date_asc':
       $query .= " ORDER BY o.created_at ASC";
       break;
   case 'amount_desc':
       $query .= " ORDER BY o.total DESC";
       break;
   case 'amount_asc':
       $query .= " ORDER BY o.total ASC";
       break;
   case 'date_desc':
   default:
       $query .= " ORDER BY o.created_at DESC";
       break;
}

// Pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$records_per_page = 10;
$offset = ($page - 1) * $records_per_page;

// Get total records for pagination
$count_query = str_replace("SELECT o.*, 
         CONCAT(u.first_name, ' ', u.last_name) as customer_name, 
         u.email as customer_email", 
         "SELECT COUNT(*) as total", $query);

$stmt = $conn->prepare($count_query);
if (!empty($params)) {
   $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$total_result = $stmt->get_result();
$total_row = $total_result->fetch_assoc();
$total_records = $total_row['total'];
$total_pages = ceil($total_records / $records_per_page);

// Add pagination to the main query
$query .= " LIMIT ?, ?";
$params[] = $offset;
$params[] = $records_per_page;
$types .= "ii";

// Prepare and execute the query
$stmt = $conn->prepare($query);
if (!empty($params)) {
   $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();
$payments = $result->fetch_all(MYSQLI_ASSOC);

// Get order statuses for filter dropdown
$status_query = "SELECT DISTINCT status FROM orders ORDER BY status";
$status_result = $conn->query($status_query);
$statuses = [];
while ($row = $status_result->fetch_assoc()) {
   $statuses[] = $row['status'];
}

// Get payment methods for filter dropdown
$method_query = "SELECT DISTINCT payment_method FROM orders ORDER BY payment_method";
$method_result = $conn->query($method_query);
$methods = [];
while ($row = $method_result->fetch_assoc()) {
   $methods[] = $row['payment_method'];
}

// Get payment summary statistics
$summary_query = "SELECT 
    COUNT(*) as total_payments,
    SUM(total) as total_amount,
    SUM(CASE WHEN status = 'shipped' OR status = 'delivered' OR status = 'received' THEN 1 ELSE 0 END) as completed_count,
    SUM(CASE WHEN status = 'shipped' OR status = 'delivered' OR status = 'received' THEN total ELSE 0 END) as completed_amount,
    SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_count,
    SUM(CASE WHEN status = 'pending' THEN total ELSE 0 END) as pending_amount,
    SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as failed_count,
    SUM(CASE WHEN status = 'cancelled' THEN total ELSE 0 END) as failed_amount
FROM orders";

$summary_result = $conn->query($summary_query);
$summary = $summary_result->fetch_assoc();

// Page title
$pageTitle = 'Payments';
?>

<!DOCTYPE html>
<html lang="en">
<head>
   <meta charset="UTF-8">
   <meta name="viewport" content="width=device-width, initial-scale=1.0">
   <title>Payment History - Mugna Admin</title>
   <link rel="stylesheet" href="css/admin-styles.css">
   <link rel="shortcut icon" href="../images/mugna-icon.png" type="image/x-icon">
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

       /* Payments Table */
       .payments-table-wrapper {
           background-color: var(--card-bg);
           border-radius: var(--border-radius);
           box-shadow: var(--shadow);
           overflow: hidden;
           border: 1px solid var(--border-color);
       }

       .payments-table {
           width: 100%;
           border-collapse: collapse;
       }

       .payments-table th,
       .payments-table td {
           padding: 1rem;
           text-align: left;
           border-bottom: 1px solid var(--border-color);
       }

       .payments-table th {
           background-color: var(--light-bg);
           font-weight: 600;
           color: var(--text-secondary);
           font-size: 0.875rem;
           text-transform: uppercase;
           letter-spacing: 0.05em;
       }

       .payments-table tbody tr {
           transition: var(--transition-fast);
       }

       .payments-table tbody tr:hover {
           background-color: var(--primary-bg);
       }

       .payments-table tbody tr:last-child td {
           border-bottom: none;
       }

       /* Status Badge */
       .status-badge {
           display: inline-flex;
           align-items: center;
           padding: 0.375rem 0.75rem;
           border-radius: 20px;
           font-size: 0.75rem;
           font-weight: 600;
           text-transform: capitalize;
       }

       .status-completed, .status-shipped, .status-delivered, .status-received {
           background-color: var(--success-bg);
           color: var(--success-color);
       }

       .status-pending {
           background-color: var(--warning-bg);
           color: var(--warning-color);
       }

       .status-failed, .status-cancelled {
           background-color: var(--danger-bg);
           color: var(--danger-color);
       }

       .status-processing {
           background-color: var(--info-bg);
           color: var(--info-color);
       }

       /* Payment Method Badge */
       .payment-method-badge {
           display: inline-flex;
           align-items: center;
           gap: 0.5rem;
           padding: 0.375rem 0.75rem;
           border-radius: 20px;
           font-size: 0.75rem;
           font-weight: 600;
           background-color: var(--light-bg);
           color: var(--text-secondary);
       }

       /* Action Buttons */
       .action-btn {
           width: 36px;
           height: 36px;
           border-radius: 50%;
           display: inline-flex;
           align-items: center;
           justify-content: center;
           background-color: var(--light-bg);
           color: var(--text-secondary);
           border: 1px solid var(--border-color);
           cursor: pointer;
           transition: var(--transition-fast);
           margin-right: 0.5rem;
       }

       .action-btn:hover {
           background-color: var(--primary-color);
           color: white;
           border-color: var(--primary-color);
       }

       .action-btn.view-btn:hover {
           background-color: var(--primary-color);
           border-color: var(--primary-color);
       }

       .action-btn.receipt-btn:hover {
           background-color: var(--success-color);
           border-color: var(--success-color);
       }

       /* Payment Modal */
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
           opacity: 0;
           transition: opacity 0.3s ease;
       }

       .modal.active {
           display: block;
           opacity: 1;
       }

       .modal-content {
           background-color: var(--card-bg);
           margin: 2% auto;
           width: 90%;
           max-width: 800px;
           border-radius: var(--border-radius);
           box-shadow: var(--shadow-lg);
           position: relative;
           transform: translateY(-20px);
           transition: transform 0.3s ease;
           overflow: hidden;
           max-height: 90vh;
           display: flex;
           flex-direction: column;
       }

       .modal.active .modal-content {
           transform: translateY(0);
       }

       .modal-header {
           padding: 1.5rem;
           border-bottom: 1px solid var(--border-color);
           display: flex;
           align-items: center;
           justify-content: space-between;
           background: linear-gradient(135deg, var(--primary-color), var(--primary-light));
           color: white;
       }

       .modal-title {
           font-size: 1.25rem;
           font-weight: 600;
           margin: 0;
       }

       .modal-close {
           font-size: 1.5rem;
           font-weight: bold;
           color: white;
           cursor: pointer;
           width: 36px;
           height: 36px;
           display: flex;
           align-items: center;
           justify-content: center;
           border-radius: 50%;
           background-color: rgba(255, 255, 255, 0.2);
           transition: var(--transition-fast);
       }

       .modal-close:hover {
           background-color: rgba(255, 255, 255, 0.3);
       }

       .modal-body {
           padding: 1.5rem;
           overflow-y: auto;
       }

       .modal-footer {
           padding: 1.5rem;
           border-top: 1px solid var(--border-color);
           display: flex;
           justify-content: flex-end;
           gap: 1rem;
       }

       /* Payment Details */
       .payment-details {
           display: grid;
           grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
           gap: 1.5rem;
           margin-bottom: 1.5rem;
       }

       .payment-info-card {
           background-color: var(--light-bg);
           border-radius: var(--border-radius);
           padding: 1.5rem;
           border: 1px solid var(--border-color);
       }

       .payment-info-card h3 {
           font-size: 1rem;
           font-weight: 600;
           margin-top: 0;
           margin-bottom: 1rem;
           color: var(--text-primary);
           display: flex;
           align-items: center;
           gap: 0.5rem;
       }

       .payment-info-card h3 i {
           color: var(--primary-color);
       }

       .info-list {
           list-style: none;
           padding: 0;
           margin: 0;
       }

       .info-list li {
           display: flex;
           margin-bottom: 0.75rem;
           font-size: 0.875rem;
       }

       .info-list li:last-child {
           margin-bottom: 0;
       }

       .info-label {
           font-weight: 500;
           color: var(--text-secondary);
           width: 40%;
           flex-shrink: 0;
       }

       .info-value {
           color: var(--text-primary);
           font-weight: 500;
       }

       /* Payment Screenshot */
       .payment-screenshot {
           background-color: var(--light-bg);
           border-radius: var(--border-radius);
           padding: 1.5rem;
           border: 1px solid var(--border-color);
           margin-bottom: 1.5rem;
       }

       .payment-screenshot h3 {
           font-size: 1rem;
           font-weight: 600;
           margin-top: 0;
           margin-bottom: 1rem;
           color: var(--text-primary);
           display: flex;
           align-items: center;
           gap: 0.5rem;
       }

       .payment-screenshot h3 i {
           color: var(--primary-color);
       }

       .screenshot-container {
           text-align: center;
       }

       .screenshot-image {
           max-width: 100%;
           max-height: 400px;
           border-radius: var(--input-radius);
           border: 1px solid var(--border-color);
           cursor: pointer;
           transition: var(--transition);
       }

       .screenshot-image:hover {
           transform: scale(1.02);
           box-shadow: var(--shadow-md);
       }

       /* Pagination */
       .pagination {
           display: flex;
           justify-content: center;
           margin-top: 2rem;
           gap: 0.5rem;
       }

       .pagination-btn {
           width: 40px;
           height: 40px;
           display: flex;
           align-items: center;
           justify-content: center;
           border-radius: var(--input-radius);
           background-color: var(--card-bg);
           border: 1px solid var(--border-color);
           color: var(--text-secondary);
           font-weight: 500;
           transition: var(--transition-fast);
           cursor: pointer;
       }

       .pagination-btn:hover {
           background-color: var(--light-bg);
           color: var(--primary-color);
           border-color: var(--primary-color);
       }

       .pagination-btn.active {
           background-color: var(--primary-color);
           color: white;
           border-color: var(--primary-color);
       }

       .pagination-btn.disabled {
           opacity: 0.5;
           cursor: not-allowed;
       }

       /* Summary Cards */
       .summary-cards {
           display: grid;
           grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
           gap: 1.5rem;
           margin-bottom: 2rem;
       }

       .summary-card {
           background-color: var(--card-bg);
           border-radius: var(--border-radius);
           padding: 1.5rem;
           box-shadow: var(--shadow);
           border: 1px solid var(--border-color);
           display: flex;
           align-items: center;
           gap: 1rem;
       }

       .summary-icon {
           width: 48px;
           height: 48px;
           border-radius: 12px;
           display: flex;
           align-items: center;
           justify-content: center;
           font-size: 1.5rem;
           flex-shrink: 0;
       }

       .summary-icon.total {
           background-color: var(--primary-bg);
           color: var(--primary-color);
       }

       .summary-icon.completed {
           background-color: var(--success-bg);
           color: var(--success-color);
       }

       .summary-icon.pending {
           background-color: var(--warning-bg);
           color: var(--warning-color);
       }

       .summary-icon.failed {
           background-color: var(--danger-bg);
           color: var(--danger-color);
       }

       .summary-details {
           flex: 1;
       }

       .summary-title {
           font-size: 0.875rem;
           color: var(--text-secondary);
           margin: 0 0 0.25rem 0;
       }

       .summary-value {
           font-size: 1.5rem;
           font-weight: 700;
           color: var(--text-primary);
           margin: 0;
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

       /* Lightbox for Payment Screenshot */
       .lightbox {
           display: none;
           position: fixed;
           z-index: 1100;
           left: 0;
           top: 0;
           width: 100%;
           height: 100%;
           overflow: auto;
           background-color: rgba(0, 0, 0, 0.9);
           opacity: 0;
           transition: opacity 0.3s ease;
       }

       .lightbox.active {
           display: flex;
           opacity: 1;
           align-items: center;
           justify-content: center;
       }

       .lightbox-content {
           max-width: 90%;
           max-height: 90%;
       }

       .lightbox-image {
           display: block;
           max-width: 100%;
           max-height: 90vh;
           margin: auto;
           border-radius: 4px;
       }

       .lightbox-close {
           position: absolute;
           top: 20px;
           right: 20px;
           font-size: 30px;
           color: white;
           cursor: pointer;
           width: 40px;
           height: 40px;
           display: flex;
           align-items: center;
           justify-content: center;
           border-radius: 50%;
           background-color: rgba(0, 0, 0, 0.5);
           transition: var(--transition-fast);
       }

       .lightbox-close:hover {
           background-color: rgba(0, 0, 0, 0.8);
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
           
           .payments-table {
               display: block;
               overflow-x: auto;
           }
           
           .modal-content {
               width: 95%;
               margin: 5% auto;
           }
           
           .payment-details {
               grid-template-columns: 1fr;
           }
           
           .summary-cards {
               grid-template-columns: 1fr;
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

           <!-- Payment History Content -->
           <div class="content-wrapper">
               <div class="content-header">
                   <h1>Payment History</h1>
               </div>

               <!-- Summary Cards -->
               <div class="summary-cards">
                   <div class="summary-card">
                       <div class="summary-icon total">
                           <i class="fas fa-money-bill-wave"></i>
                       </div>
                       <div class="summary-details">
                           <p class="summary-title">Total Payments</p>
                           <p class="summary-value">₱<?php echo number_format($summary['total_amount'] ?? 0, 2); ?></p>
                           <small><?php echo number_format($summary['total_payments'] ?? 0); ?> transactions</small>
                       </div>
                   </div>
                   
                   <div class="summary-card">
                       <div class="summary-icon completed">
                           <i class="fas fa-check-circle"></i>
                       </div>
                       <div class="summary-details">
                           <p class="summary-title">Completed Payments</p>
                           <p class="summary-value">₱<?php echo number_format($summary['completed_amount'] ?? 0, 2); ?></p>
                           <small><?php echo number_format($summary['completed_count'] ?? 0); ?> transactions</small>
                       </div>
                   </div>
                   
                   <div class="summary-card">
                       <div class="summary-icon pending">
                           <i class="fas fa-clock"></i>
                       </div>
                       <div class="summary-details">
                           <p class="summary-title">Pending Payments</p>
                           <p class="summary-value">₱<?php echo number_format($summary['pending_amount'] ?? 0, 2); ?></p>
                           <small><?php echo number_format($summary['pending_count'] ?? 0); ?> transactions</small>
                       </div>
                   </div>
                   
                   <div class="summary-card">
                       <div class="summary-icon failed">
                           <i class="fas fa-times-circle"></i>
                       </div>
                       <div class="summary-details">
                           <p class="summary-title">Cancelled Orders</p>
                           <p class="summary-value">₱<?php echo number_format($summary['failed_amount'] ?? 0, 2); ?></p>
                           <small><?php echo number_format($summary['failed_count'] ?? 0); ?> transactions</small>
                       </div>
                   </div>
               </div>

               <!-- Filter Section -->
               <div class="filter-section">
                   <form action="" method="GET" class="filter-form">
                       <div class="form-group">
                           <label for="status">Order Status</label>
                           <select name="status" id="status" class="form-control">
                               <option value="">All Statuses</option>
                               <?php foreach ($statuses as $status): ?>
                                   <option value="<?php echo $status; ?>" <?php echo $status_filter === $status ? 'selected' : ''; ?>>
                                       <?php echo ucfirst($status); ?>
                                   </option>
                               <?php endforeach; ?>
                           </select>
                       </div>
                       <div class="form-group">
                           <label for="payment_method">Payment Method</label>
                           <select name="payment_method" id="payment_method" class="form-control">
                               <option value="">All Methods</option>
                               <?php foreach ($methods as $method): ?>
                                   <option value="<?php echo $method; ?>" <?php echo $payment_method === $method ? 'selected' : ''; ?>>
                                       <?php echo ucfirst(str_replace('_', ' ', $method)); ?>
                                   </option>
                               <?php endforeach; ?>
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
                           <label for="search">Search</label>
                           <input type="text" name="search" id="search" class="form-control" placeholder="Order ID, Customer..." value="<?php echo $search; ?>">
                       </div>
                       <div class="form-group">
                           <label for="sort">Sort By</label>
                           <select name="sort" id="sort" class="form-control">
                               <option value="date_desc" <?php echo $sort === 'date_desc' ? 'selected' : ''; ?>>Date (Newest First)</option>
                               <option value="date_asc" <?php echo $sort === 'date_asc' ? 'selected' : ''; ?>>Date (Oldest First)</option>
                               <option value="amount_desc" <?php echo $sort === 'amount_desc' ? 'selected' : ''; ?>>Amount (High to Low)</option>
                               <option value="amount_asc" <?php echo $sort === 'amount_asc' ? 'selected' : ''; ?>>Amount (Low to High)</option>
                           </select>
                       </div>
                       <div class="filter-buttons">
                           <button type="submit" class="btn btn-primary">
                               <i class="fas fa-filter"></i> Filter
                           </button>
                           <a href="admin-payment-history.php" class="btn btn-outline">
                               <i class="fas fa-redo"></i> Reset
                           </a>
                       </div>
                   </form>
               </div>

               <!-- Payments Table -->
               <div class="payments-table-wrapper">
                   <?php if (count($payments) > 0): ?>
                   <table class="payments-table">
                       <thead>
                           <tr>
                               <th>Order ID</th>
                               <th>Date</th>
                               <th>Customer</th>
                               <th>Amount</th>
                               <th>Method</th>
                               <th>Status</th>
                               <th>Actions</th>
                           </tr>
                       </thead>
                       <tbody>
                           <?php foreach ($payments as $payment): ?>
                           <tr>
                               <td>#<?php echo $payment['id']; ?></td>
                               <td><?php echo date('M d, Y H:i', strtotime($payment['created_at'])); ?></td>
                               <td>
                                   <?php echo $payment['customer_name']; ?><br>
                                   <small class="text-muted"><?php echo $payment['customer_email']; ?></small>
                               </td>
                               <td>₱<?php echo number_format($payment['total'], 2); ?></td>
                               <td>
                                   <span class="payment-method-badge">
                                       <?php 
                                       $method = $payment['payment_method'];
                                       $icon = 'credit-card';
                                       
                                       if ($method === 'credit_card') {
                                           $icon = 'credit-card';
                                       } elseif ($method === 'gcash') {
                                           $icon = 'mobile-alt';
                                       } elseif ($method === 'paypal') {
                                           $icon = 'paypal';
                                       } elseif ($method === 'bank_transfer') {
                                           $icon = 'university';
                                       } elseif ($method === 'cash_on_delivery') {
                                           $icon = 'money-bill';
                                       }
                                       ?>
                                       <i class="fas fa-<?php echo $icon; ?>"></i>
                                       <?php echo ucfirst(str_replace('_', ' ', $method)); ?>
                                   </span>
                               </td>
                               <td>
                                   <span class="status-badge status-<?php echo strtolower($payment['status']); ?>">
                                       <?php echo ucfirst($payment['status']); ?>
                                   </span>
                               </td>
                               <td>
                                   <button class="action-btn view-btn" onclick="viewPayment('<?php echo $payment['id']; ?>')" title="View Payment Details">
                                       <i class="fas fa-eye"></i>
                                   </button>
                                   <?php if (in_array($payment['status'], ['shipped', 'delivered', 'received'])): ?>
                                   <button class="action-btn receipt-btn" onclick="viewReceipt('<?php echo $payment['id']; ?>')" title="View Receipt">
                                       <i class="fas fa-file-invoice"></i>
                                   </button>
                                   <?php endif; ?>
                               </td>
                           </tr>
                           <?php endforeach; ?>
                       </tbody>
                   </table>
                   <?php else: ?>
                   <div class="empty-state">
                       <i class="fas fa-money-check-alt"></i>
                       <h3>No Payments Found</h3>
                       <p>There are no payments matching your filter criteria. Try adjusting your filters or check back later.</p>
                       <a href="admin-payment-history.php" class="btn btn-primary">
                           <i class="fas fa-redo"></i> Reset Filters
                       </a>
                   </div>
                   <?php endif; ?>
               </div>

               <!-- Pagination -->
               <?php if ($total_pages > 1): ?>
               <div class="pagination">
                   <?php if ($page > 1): ?>
                   <a href="?page=1<?php echo !empty($status_filter) ? '&status=' . $status_filter : ''; ?><?php echo !empty($payment_method) ? '&payment_method=' . $payment_method : ''; ?><?php echo !empty($date_from) ? '&date_from=' . $date_from : ''; ?><?php echo !empty($date_to) ? '&date_to=' . $date_to : ''; ?><?php echo !empty($search) ? '&search=' . $search : ''; ?><?php echo !empty($sort) ? '&sort=' . $sort : ''; ?>" class="pagination-btn" title="First Page">
                       <i class="fas fa-angle-double-left"></i>
                   </a>
                   <a href="?page=<?php echo $page - 1; ?><?php echo !empty($status_filter) ? '&status=' . $status_filter : ''; ?><?php echo !empty($payment_method) ? '&payment_method=' . $payment_method : ''; ?><?php echo !empty($date_from) ? '&date_from=' . $date_from : ''; ?><?php echo !empty($date_to) ? '&date_to=' . $date_to : ''; ?><?php echo !empty($search) ? '&search=' . $search : ''; ?><?php echo !empty($sort) ? '&sort=' . $sort : ''; ?>" class="pagination-btn" title="Previous Page">
                       <i class="fas fa-angle-left"></i>
                   </a>
                   <?php endif; ?>

                   <?php
                   $start_page = max(1, $page - 2);
                   $end_page = min($total_pages, $start_page + 4);
                   if ($end_page - $start_page < 4) {
                       $start_page = max(1, $end_page - 4);
                   }
                   ?>

                   <?php for ($i = $start_page; $i <= $end_page; $i++): ?>
                   <a href="?page=<?php echo $i; ?><?php echo !empty($status_filter) ? '&status=' . $status_filter : ''; ?><?php echo !empty($payment_method) ? '&payment_method=' . $payment_method : ''; ?><?php echo !empty($date_from) ? '&date_from=' . $date_from : ''; ?><?php echo !empty($date_to) ? '&date_to=' . $date_to : ''; ?><?php echo !empty($search) ? '&search=' . $search : ''; ?><?php echo !empty($sort) ? '&sort=' . $sort : ''; ?>" class="pagination-btn <?php echo $i === $page ? 'active' : ''; ?>">
                       <?php echo $i; ?>
                   </a>
                   <?php endfor; ?>

                   <?php if ($page < $total_pages): ?>
                   <a href="?page=<?php echo $page + 1; ?><?php echo !empty($status_filter) ? '&status=' . $status_filter : ''; ?><?php echo !empty($payment_method) ? '&payment_method=' . $payment_method : ''; ?><?php echo !empty($date_from) ? '&date_from=' . $date_from : ''; ?><?php echo !empty($date_to) ? '&date_to=' . $date_to : ''; ?><?php echo !empty($search) ? '&search=' . $search : ''; ?><?php echo !empty($sort) ? '&sort=' . $sort : ''; ?>" class="pagination-btn" title="Next Page">
                       <i class="fas fa-angle-right"></i>
                   </a>
                   <a href="?page=<?php echo $total_pages; ?><?php echo !empty($status_filter) ? '&status=' . $status_filter : ''; ?><?php echo !empty($payment_method) ? '&payment_method=' . $payment_method : ''; ?><?php echo !empty($date_from) ? '&date_from=' . $date_from : ''; ?><?php echo !empty($date_to) ? '&date_to=' . $date_to : ''; ?><?php echo !empty($search) ? '&search=' . $search : ''; ?><?php echo !empty($sort) ? '&sort=' . $sort : ''; ?>" class="pagination-btn" title="Last Page">
                       <i class="fas fa-angle-double-right"></i>
                   </a>
                   <?php endif; ?>
               </div>
               <?php endif; ?>
           </div>
       </main>
   </div>

   <!-- Payment Details Modal -->
   <div id="paymentModal" class="modal">
       <div class="modal-content">
           <div class="modal-header">
               <h2 class="modal-title">Payment Details</h2>
               <span class="modal-close">&times;</span>
           </div>
           <div class="modal-body">
               <div id="paymentDetails"></div>
           </div>
           <div class="modal-footer">
               <button class="btn btn-outline" onclick="closeModal()">Close</button>
               <button class="btn btn-primary" id="printReceiptBtn" style="display: none;">
                   <i class="fas fa-print"></i> Print Receipt
               </button>
           </div>
       </div>
   </div>

   <!-- Lightbox for Payment Screenshot -->
   <div id="screenshotLightbox" class="lightbox">
       <span class="lightbox-close">&times;</span>
       <div class="lightbox-content">
           <img id="lightboxImage" class="lightbox-image" src="/placeholder.svg" alt="Payment Screenshot">
       </div>
   </div>

   <script>
       document.addEventListener('DOMContentLoaded', function() {
           // Modal functionality
           const modal = document.getElementById('paymentModal');
           const modalClose = document.querySelector('.modal-close');
           
           modalClose.addEventListener('click', closeModal);
           
           window.addEventListener('click', function(event) {
               if (event.target === modal) {
                   closeModal();
               }
           });
           
           // Lightbox functionality
           const lightbox = document.getElementById('screenshotLightbox');
           const lightboxClose = document.querySelector('.lightbox-close');
           
           lightboxClose.addEventListener('click', function() {
               lightbox.classList.remove('active');
           });
           
           window.addEventListener('click', function(event) {
               if (event.target === lightbox) {
                   lightbox.classList.remove('active');
               }
           });

           // Live filter functionality
           const filterForm = document.querySelector('.filter-form');
           const filterInputs = filterForm.querySelectorAll('select, input[type="date"]');
           
           filterInputs.forEach(input => {
               input.addEventListener('change', function() {
                   filterForm.submit();
               });
           });
           
           // For text input, add debounce for typing
           const searchInput = document.getElementById('search');
           if (searchInput) {
               let debounceTimer;
               searchInput.addEventListener('input', function() {
                   clearTimeout(debounceTimer);
                   debounceTimer = setTimeout(() => {
                       filterForm.submit();
                   }, 500); // Wait 500ms after user stops typing
               });
           }
       });
       
       // Close modal function
       function closeModal() {
           const modal = document.getElementById('paymentModal');
           modal.classList.remove('active');
           document.body.style.overflow = '';
       }
       
       // View payment function
       function viewPayment(orderId) {
           const modal = document.getElementById('paymentModal');
           const paymentDetails = document.getElementById('paymentDetails');
           const printReceiptBtn = document.getElementById('printReceiptBtn');
           
           // Show loading state
           paymentDetails.innerHTML = '<div style="text-align: center; padding: 2rem;"><i class="fas fa-spinner fa-spin" style="font-size: 2rem; color: var(--primary-color);"></i><p>Loading payment details...</p></div>';
           
           // Show modal
           modal.classList.add('active');
           document.body.style.overflow = 'hidden';
           
           // Fetch payment details
           fetch('get_payment_details.php?order_id=' + orderId)
               .then(response => response.json())
               .then(data => {
                   if (data.error) {
                       paymentDetails.innerHTML = `<div class="alert alert-error"><div class="alert-icon"><i class="fas fa-exclamation"></i></div><div class="alert-content">${data.error}</div></div>`;
                       return;
                   }
                   
                   // Show/hide print receipt button based on order status
                   if (data.order.status === 'shipped' || data.order.status === 'delivered' || data.order.status === 'received') {
                       printReceiptBtn.style.display = 'inline-flex';
                       printReceiptBtn.onclick = function() {
                           printReceipt(orderId);
                       };
                   } else {
                       printReceiptBtn.style.display = 'none';
                   }
                   
                   // Format payment details HTML
                   let html = `
                       <div class="payment-details">
                           <div class="payment-info-card">
                               <h3><i class="fas fa-info-circle"></i> Payment Information</h3>
                               <ul class="info-list">
                                   <li>
                                       <span class="info-label">Order ID:</span>
                                       <span class="info-value">#${data.order.id}</span>
                                   </li>
                                   <li>
                                       <span class="info-label">Date:</span>
                                       <span class="info-value">${formatDate(data.order.created_at)}</span>
                                   </li>
                                   <li>
                                       <span class="info-label">Amount:</span>
                                       <span class="info-value">₱${formatPrice(data.order.total)}</span>
                                   </li>
                                   <li>
                                       <span class="info-label">Payment Method:</span>
                                       <span class="info-value">${formatPaymentMethod(data.order.payment_method)}</span>
                                   </li>
                                   <li>
                                       <span class="info-label">Status:</span>
                                       <span class="info-value">
                                           <span class="status-badge status-${data.order.status.toLowerCase()}">${data.order.status}</span>
                                       </span>
                                   </li>
                               </ul>
                           </div>
                           
                           <div class="payment-info-card">
                               <h3><i class="fas fa-user"></i> Customer Information</h3>
                               <ul class="info-list">
                                   <li>
                                       <span class="info-label">Name:</span>
                                       <span class="info-value">${data.customer.first_name} ${data.customer.last_name}</span>
                                   </li>
                                   <li>
                                       <span class="info-label">Email:</span>
                                       <span class="info-value">${data.customer.email}</span>
                                   </li>
                                   <li>
                                       <span class="info-label">Phone:</span>
                                       <span class="info-value">${data.customer.phone || 'Not provided'}</span>
                                   </li>
                               </ul>
                           </div>
                           
                           <div class="payment-info-card">
                               <h3><i class="fas fa-shopping-cart"></i> Order Summary</h3>
                               <ul class="info-list">
                                   <li>
                                       <span class="info-label">Subtotal:</span>
                                       <span class="info-value">₱${formatPrice(data.order.subtotal)}</span>
                                   </li>
                                   <li>
                                       <span class="info-label">Shipping Fee:</span>
                                       <span class="info-value">₱${formatPrice(data.order.total - data.order.subtotal)}</span>
                                   </li>
                                   <li>
                                       <span class="info-label">Total:</span>
                                       <span class="info-value">₱${formatPrice(data.order.total)}</span>
                                   </li>
                               </ul>
                           </div>
                       </div>
                   `;
                   
                   // Add payment screenshot if available
                   if (data.order.payment_screenshot) {
                       html += `
                           <div class="payment-screenshot">
                               <h3><i class="fas fa-image"></i> Payment Screenshot</h3>
                               <div class="screenshot-container">
                                   <img src="../${data.order.payment_screenshot}" alt="Payment Screenshot" class="screenshot-image" onclick="openLightbox('../${data.order.payment_screenshot}')">
                               </div>
                           </div>
                       `;
                   }
                   
                   // Add order items
                   if (data.items && data.items.length > 0) {
                       html += `
                           <div class="payment-info-card">
                               <h3><i class="fas fa-box"></i> Order Items</h3>
                               <table class="payments-table" style="width: 100%;">
                                   <thead>
                                       <tr>
                                           <th>Product</th>
                                           <th>Price</th>
                                           <th>Quantity</th>
                                           <th>Total</th>
                                       </tr>
                                   </thead>
                                   <tbody>
                       `;
                       
                       data.items.forEach(item => {
                           html += `
                               <tr>
                                   <td>${item.product_name}</td>
                                   <td>₱${formatPrice(item.price)}</td>
                                   <td>${item.quantity}</td>
                                   <td>₱${formatPrice(item.total)}</td>
                               </tr>
                           `;
                       });
                       
                       html += `
                                   </tbody>
                               </table>
                           </div>
                       `;
                   }
                   
                   // Update payment details in modal
                   paymentDetails.innerHTML = html;
               })
               .catch(error => {
                   console.error('Error fetching payment details:', error);
                   paymentDetails.innerHTML = `<div class="alert alert-error"><div class="alert-icon"><i class="fas fa-exclamation"></i></div><div class="alert-content">Error loading payment details. Please try again.</div></div>`;
               });
       }
       
       // View receipt function
       function viewReceipt(orderId) {
           window.open('generate_payment_receipt.php?order_id=' + orderId, '_blank');
       }
       
       // Print receipt function
       function printReceipt(orderId) {
           window.open('generate_payment_receipt.php?order_id=' + orderId, '_blank');
       }
       
       // Open lightbox for payment screenshot
       function openLightbox(imageSrc) {
           const lightbox = document.getElementById('screenshotLightbox');
           const lightboxImage = document.getElementById('lightboxImage');
           
           lightboxImage.src = imageSrc;
           lightbox.classList.add('active');
       }
       
       // Format date
       function formatDate(dateString) {
           const options = { year: 'numeric', month: 'long', day: 'numeric', hour: '2-digit', minute: '2-digit' };
           return new Date(dateString).toLocaleDateString('en-US', options);
       }
       
       // Format price
       function formatPrice(price) {
           return parseFloat(price).toLocaleString(undefined, {
               minimumFractionDigits: 2,
               maximumFractionDigits: 2
           });
       }
       
       // Format payment method
       function formatPaymentMethod(method) {
           return method.split('_').map(word => word.charAt(0).toUpperCase() + word.slice(1)).join(' ');
       }
   </script>
</body>
</html>
