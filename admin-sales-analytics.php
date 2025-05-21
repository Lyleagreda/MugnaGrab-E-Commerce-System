<?php
date_default_timezone_set('Asia/Manila'); // or your local timezone

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
$date_range = isset($_GET['date_range']) ? $_GET['date_range'] : 'last30days';
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-d', strtotime('-30 days'));
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');

// Set date range based on selection
if ($date_range == 'today') {
    $start_date = date('Y-m-d');
    $end_date = date('Y-m-d');
} elseif ($date_range == 'yesterday') {
    $start_date = date('Y-m-d', strtotime('-1 day'));
    $end_date = date('Y-m-d', strtotime('-1 day'));
} elseif ($date_range == 'last7days') {
    $start_date = date('Y-m-d', strtotime('-7 days'));
    $end_date = date('Y-m-d');
} elseif ($date_range == 'last30days') {
    $start_date = date('Y-m-d', strtotime('-30 days'));
    $end_date = date('Y-m-d');
} elseif ($date_range == 'thismonth') {
    $start_date = date('Y-m-01');
    $end_date = date('Y-m-d');
} elseif ($date_range == 'lastmonth') {
    $start_date = date('Y-m-01', strtotime('first day of last month'));
    $end_date = date('Y-m-t', strtotime('last day of last month'));
} elseif ($date_range == 'thisyear') {
    $start_date = date('Y-01-01');
    $end_date = date('Y-m-d');
} elseif ($date_range == 'lastyear') {
    $start_date = date('Y-01-01', strtotime('-1 year'));
    $end_date = date('Y-12-31', strtotime('-1 year'));
}

// Get sales summary
$summary_query = "SELECT 
    COUNT(*) as total_orders,
    SUM(total) as total_revenue,
    SUM(subtotal) as total_subtotal,
    SUM(total - subtotal) as total_shipping,
    AVG(total) as average_order_value,
    COUNT(DISTINCT user_id) as unique_customers
FROM orders 
WHERE created_at BETWEEN ? AND DATE_ADD(?, INTERVAL 1 DAY)
AND status NOT IN ('cancelled')";


$stmt = $conn->prepare($summary_query);
$stmt->bind_param("ss", $start_date, $end_date);
$stmt->execute();
$summary_result = $stmt->get_result();
$summary = $summary_result->fetch_assoc();

// Get total sales excluding cancelled orders
$total_sales_query = "SELECT 
    SUM(total) as total_sales
FROM orders 
WHERE created_at BETWEEN ? AND DATE_ADD(?, INTERVAL 1 DAY)
AND status NOT IN ('cancelled', 'pending')";

$stmt = $conn->prepare($total_sales_query);
$stmt->bind_param("ss", $start_date, $end_date);
$stmt->execute();
$total_sales_result = $stmt->get_result();
$total_sales = $total_sales_result->fetch_assoc();

// Get sales by date for chart
$sales_by_date_query = "SELECT 
    DATE(created_at) as order_date,
    COUNT(*) as order_count,
    SUM(total) as daily_revenue
FROM orders 
WHERE created_at BETWEEN ? AND DATE_ADD(?, INTERVAL 1 DAY)
GROUP BY DATE(created_at)
ORDER BY order_date";

$stmt = $conn->prepare($sales_by_date_query);
$stmt->bind_param("ss", $start_date, $end_date);
$stmt->execute();
$sales_by_date_result = $stmt->get_result();
$sales_by_date = [];
$dates = [];
$revenues = [];

while ($row = $sales_by_date_result->fetch_assoc()) {
    $sales_by_date[] = $row;
    $dates[] = date('M d', strtotime($row['order_date']));
    $revenues[] = $row['daily_revenue'];
}

// Get sales by payment method
$payment_method_query = "SELECT 
    payment_method,
    COUNT(*) as order_count,
    SUM(total) as total_revenue
FROM orders 
WHERE created_at BETWEEN ? AND DATE_ADD(?, INTERVAL 1 DAY)
GROUP BY payment_method
ORDER BY total_revenue DESC";

$stmt = $conn->prepare($payment_method_query);
$stmt->bind_param("ss", $start_date, $end_date);
$stmt->execute();
$payment_method_result = $stmt->get_result();
$payment_methods = [];
$payment_method_labels = [];
$payment_method_data = [];

while ($row = $payment_method_result->fetch_assoc()) {
    $payment_methods[] = $row;
    $payment_method_labels[] = ucwords(str_replace('_', ' ', $row['payment_method']));
    $payment_method_data[] = $row['total_revenue'];
}

// Get top selling products
$top_products_query = "SELECT 
    oi.product_name,
    SUM(oi.quantity) as total_quantity,
    SUM(oi.total) as total_revenue
FROM order_items oi
JOIN orders o ON oi.order_id = o.id
WHERE o.created_at BETWEEN ? AND DATE_ADD(?, INTERVAL 1 DAY)
GROUP BY oi.product_name
ORDER BY total_revenue DESC
LIMIT 10";

$stmt = $conn->prepare($top_products_query);
$stmt->bind_param("ss", $start_date, $end_date);
$stmt->execute();
$top_products_result = $stmt->get_result();
$top_products = [];

while ($row = $top_products_result->fetch_assoc()) {
    $top_products[] = $row;
}

// Get sales by status
$status_query = "SELECT 
    status,
    COUNT(*) as order_count,
    SUM(total) as total_revenue
FROM orders 
WHERE created_at BETWEEN ? AND DATE_ADD(?, INTERVAL 1 DAY)
GROUP BY status
ORDER BY total_revenue DESC";

$stmt = $conn->prepare($status_query);
$stmt->bind_param("ss", $start_date, $end_date);
$stmt->execute();
$status_result = $stmt->get_result();
$statuses = [];
$status_labels = [];
$status_data = [];

while ($row = $status_result->fetch_assoc()) {
    $statuses[] = $row;
    $status_labels[] = ucfirst($row['status']);
    $status_data[] = $row['total_revenue'];
}

// Get customer purchase frequency
$customer_frequency_query = "SELECT 
    purchase_count,
    COUNT(*) as customer_count
FROM (
    SELECT 
        user_id,
        COUNT(*) as purchase_count
    FROM orders 
    WHERE created_at BETWEEN ? AND DATE_ADD(?, INTERVAL 1 DAY)
    GROUP BY user_id
) as customer_purchases
GROUP BY purchase_count
ORDER BY purchase_count";

$stmt = $conn->prepare($customer_frequency_query);
$stmt->bind_param("ss", $start_date, $end_date);
$stmt->execute();
$customer_frequency_result = $stmt->get_result();
$customer_frequency = [];
$frequency_labels = [];
$frequency_data = [];

while ($row = $customer_frequency_result->fetch_assoc()) {
    $customer_frequency[] = $row;
    $frequency_labels[] = $row['purchase_count'] . ' order(s)';
    $frequency_data[] = $row['customer_count'];
}

// Page title
$pageTitle = 'Analytics';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sales Analytics - Mugna Admin</title>
    <link rel="stylesheet" href="css/admin-styles.css">
    <link rel="shortcut icon" href="../images/mugna-icon.png" type="image/x-icon">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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

        .summary-icon.revenue {
            background-color: var(--primary-bg);
            color: var(--primary-color);
        }

        .summary-icon.orders {
            background-color: var(--success-bg);
            color: var(--success-color);
        }

        .summary-icon.aov {
            background-color: var(--warning-bg);
            color: var(--warning-color);
        }

        .summary-icon.customers {
            background-color: var(--info-bg);
            color: var(--info-color);
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

        /* Chart Cards */
        .chart-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(500px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        @media (max-width: 768px) {
            .chart-grid {
                grid-template-columns: 1fr;
            }
        }

        .chart-card {
            background-color: var(--card-bg);
            border-radius: var(--border-radius);
            padding: 1.5rem;
            box-shadow: var(--shadow);
            border: 1px solid var(--border-color);
        }

        .chart-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 1.5rem;
        }

        .chart-title {
            font-size: 1.125rem;
            font-weight: 600;
            color: var(--text-primary);
            margin: 0;
        }

        .chart-container {
            position: relative;
            height: 300px;
            width: 100%;
        }

        /* Table Styles */
        .data-table {
            width: 100%;
            border-collapse: collapse;
        }

        .data-table th,
        .data-table td {
            padding: 1rem;
            text-align: left;
            border-bottom: 1px solid var(--border-color);
        }

        .data-table th {
            background-color: var(--light-bg);
            font-weight: 600;
            color: var(--text-secondary);
            font-size: 0.875rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .data-table tbody tr {
            transition: var(--transition-fast);
        }

        .data-table tbody tr:hover {
            background-color: var(--primary-bg);
        }

        .data-table tbody tr:last-child td {
            border-bottom: none;
        }

        /* Progress Bar */
        .progress-bar {
            height: 8px;
            background-color: var(--light-bg);
            border-radius: 4px;
            overflow: hidden;
            margin-top: 0.5rem;
        }

        .progress-fill {
            height: 100%;
            background: linear-gradient(90deg, var(--primary-color), var(--primary-light));
            border-radius: 4px;
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

            <!-- Sales Analytics Content -->
            <div class="content-wrapper">
                <div class="content-header">
                    <h1>Sales Analytics</h1>
                </div>

                <!-- Filter Section -->
                <div class="filter-section">
                    <form action="" method="GET" class="filter-form">
                        <div class="form-group">
                            <label for="date_range">Date Range</label>
                            <select name="date_range" id="date_range" class="form-control">
                                <option value="today" <?php echo $date_range === 'today' ? 'selected' : ''; ?>>Today</option>
                                <option value="yesterday" <?php echo $date_range === 'yesterday' ? 'selected' : ''; ?>>Yesterday</option>
                                <option value="last7days" <?php echo $date_range === 'last7days' ? 'selected' : ''; ?>>Last 7 Days</option>
                                <option value="last30days" <?php echo $date_range === 'last30days' ? 'selected' : ''; ?>>Last 30 Days</option>
                                <option value="thismonth" <?php echo $date_range === 'thismonth' ? 'selected' : ''; ?>>This Month</option>
                                <option value="lastmonth" <?php echo $date_range === 'lastmonth' ? 'selected' : ''; ?>>Last Month</option>
                                <option value="thisyear" <?php echo $date_range === 'thisyear' ? 'selected' : ''; ?>>This Year</option>
                                <option value="lastyear" <?php echo $date_range === 'lastyear' ? 'selected' : ''; ?>>Last Year</option>
                                <option value="custom" <?php echo $date_range === 'custom' ? 'selected' : ''; ?>>Custom Range</option>
                            </select>
                        </div>
                        <div class="form-group" id="start_date_group" style="<?php echo $date_range === 'custom' ? 'display: block;' : 'display: none;'; ?>">
                            <label for="start_date">Start Date</label>
                            <input type="date" name="start_date" id="start_date" class="form-control" value="<?php echo $start_date; ?>">
                        </div>
                        <div class="form-group" id="end_date_group" style="<?php echo $date_range === 'custom' ? 'display: block;' : 'display: none;'; ?>">
                            <label for="end_date">End Date</label>
                            <input type="date" name="end_date" id="end_date" class="form-control" value="<?php echo $end_date; ?>">
                        </div>
                        <div class="filter-buttons">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-filter"></i> Apply Filter
                            </button>
                            <a href="admin-sales-analytics.php" class="btn btn-outline">
                                <i class="fas fa-redo"></i> Reset
                            </a>
                        </div>
                    </form>
                </div>

                <!-- Summary Cards -->
                <div class="summary-cards">
                    <div class="summary-card">
                        <div class="summary-icon revenue">
                            <i class="fas fa-money-bill-wave"></i>
                        </div>
                        <div class="summary-details">
                            <p class="summary-title">Total Revenue</p>
                            <p class="summary-value">₱<?php echo number_format($summary['total_revenue'] ?? 0, 2); ?></p>
                            <small><?php echo date('M d, Y', strtotime($start_date)); ?> - <?php echo date('M d, Y', strtotime($end_date)); ?></small>
                        </div>
                    </div>
                    
                    <div class="summary-card">
                        <div class="summary-icon revenue" style="background-color: var(--success-bg); color: var(--success-color);">
                            <i class="fas fa-chart-line"></i>
                        </div>
                        <div class="summary-details">
                            <p class="summary-title">Total Sales (Excl. Cancelled)</p>
                            <p class="summary-value">₱<?php echo number_format($total_sales['total_sales'] ?? 0, 2); ?></p>
                            <small>Active orders only</small>
                        </div>
                    </div>
                    
                    <div class="summary-card">
                        <div class="summary-icon orders">
                            <i class="fas fa-shopping-cart"></i>
                        </div>
                        <div class="summary-details">
                            <p class="summary-title">Total Orders</p>
                            <p class="summary-value"><?php echo number_format($summary['total_orders'] ?? 0); ?></p>
                            <small><?php echo date('M d, Y', strtotime($start_date)); ?> - <?php echo date('M d, Y', strtotime($end_date)); ?></small>
                        </div>
                    </div>
                    
                    <div class="summary-card">
                        <div class="summary-icon aov">
                            <i class="fas fa-chart-line"></i>
                        </div>
                        <div class="summary-details">
                            <p class="summary-title">Average Order Value</p>
                            <p class="summary-value">₱<?php echo number_format($summary['average_order_value'] ?? 0, 2); ?></p>
                            <small><?php echo date('M d, Y', strtotime($start_date)); ?> - <?php echo date('M d, Y', strtotime($end_date)); ?></small>
                        </div>
                    </div>
                    
                    <div class="summary-card">
                        <div class="summary-icon customers">
                            <i class="fas fa-users"></i>
                        </div>
                        <div class="summary-details">
                            <p class="summary-title">Unique Customers</p>
                            <p class="summary-value"><?php echo number_format($summary['unique_customers'] ?? 0); ?></p>
                            <small><?php echo date('M d, Y', strtotime($start_date)); ?> - <?php echo date('M d, Y', strtotime($end_date)); ?></small>
                        </div>
                    </div>
                </div>

                <!-- Charts -->
                <div class="chart-grid">
                    <!-- Revenue Over Time Chart -->
                    <div class="chart-card">
                        <div class="chart-header">
                            <h3 class="chart-title">Revenue Over Time</h3>
                        </div>
                        <div class="chart-container">
                            <canvas id="revenueChart"></canvas>
                        </div>
                    </div>

                    <!-- Payment Methods Chart -->
                    <div class="chart-card">
                        <div class="chart-header">
                            <h3 class="chart-title">Revenue by Payment Method</h3>
                        </div>
                        <div class="chart-container">
                            <canvas id="paymentMethodChart"></canvas>
                        </div>
                    </div>

                    <!-- Order Status Chart -->
                    <div class="chart-card">
                        <div class="chart-header">
                            <h3 class="chart-title">Revenue by Order Status</h3>
                        </div>
                        <div class="chart-container">
                            <canvas id="orderStatusChart"></canvas>
                        </div>
                    </div>

                    <!-- Customer Purchase Frequency Chart -->
                    <div class="chart-card">
                        <div class="chart-header">
                            <h3 class="chart-title">Customer Purchase Frequency</h3>
                        </div>
                        <div class="chart-container">
                            <canvas id="customerFrequencyChart"></canvas>
                        </div>
                    </div>
                </div>

                <!-- Top Products Table -->
                <div class="chart-card">
                    <div class="chart-header">
                        <h3 class="chart-title">Top Selling Products</h3>
                    </div>
                    <?php if (count($top_products) > 0): ?>
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Product</th>
                                <th>Quantity Sold</th>
                                <th>Revenue</th>
                                <th>Performance</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $max_revenue = $top_products[0]['total_revenue'];
                            foreach ($top_products as $product): 
                                $percentage = ($product['total_revenue'] / $max_revenue) * 100;
                            ?>
                            <tr>
                                <td><?php echo $product['product_name']; ?></td>
                                <td><?php echo number_format($product['total_quantity']); ?></td>
                                <td>₱<?php echo number_format($product['total_revenue'], 2); ?></td>
                                <td>
                                    <div class="progress-bar">
                                        <div class="progress-fill" style="width: <?php echo $percentage; ?>%;"></div>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php else: ?>
                    <div style="text-align: center; padding: 2rem;">
                        <i class="fas fa-chart-bar" style="font-size: 3rem; color: var(--border-color); margin-bottom: 1rem;"></i>
                        <h3 style="font-size: 1.25rem; margin-bottom: 0.5rem;">No Sales Data Available</h3>
                        <p style="color: var(--text-secondary);">There are no sales in the selected date range. Try adjusting your filters or check back later.</p>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Date range selector
            const dateRangeSelect = document.getElementById('date_range');
            const startDateGroup = document.getElementById('start_date_group');
            const endDateGroup = document.getElementById('end_date_group');
            
            dateRangeSelect.addEventListener('change', function() {
                if (this.value === 'custom') {
                    startDateGroup.style.display = 'block';
                    endDateGroup.style.display = 'block';
                } else {
                    startDateGroup.style.display = 'none';
                    endDateGroup.style.display = 'none';
                }
            });
            
            // Chart.js Configuration
            Chart.defaults.font.family = "'Segoe UI', 'Helvetica Neue', 'Arial', sans-serif";
            Chart.defaults.font.size = 12;
            Chart.defaults.color = '#64748b';
            
            // Revenue Over Time Chart
            const revenueChartCtx = document.getElementById('revenueChart').getContext('2d');
            const revenueChart = new Chart(revenueChartCtx, {
                type: 'line',
                data: {
                    labels: <?php echo json_encode($dates); ?>,
                    datasets: [{
                        label: 'Daily Revenue',
                        data: <?php echo json_encode($revenues); ?>,
                        backgroundColor: 'rgba(37, 99, 235, 0.1)',
                        borderColor: '#2563eb',
                        borderWidth: 2,
                        pointBackgroundColor: '#ffffff',
                        pointBorderColor: '#2563eb',
                        pointBorderWidth: 2,
                        pointRadius: 4,
                        pointHoverRadius: 6,
                        tension: 0.3,
                        fill: true
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: false
                        },
                        tooltip: {
                            backgroundColor: '#ffffff',
                            titleColor: '#1e293b',
                            bodyColor: '#1e293b',
                            borderColor: '#e2e8f0',
                            borderWidth: 1,
                            padding: 12,
                            boxPadding: 6,
                            usePointStyle: true,
                            callbacks: {
                                label: function(context) {
                                    return '₱' + parseFloat(context.raw).toLocaleString(undefined, {
                                        minimumFractionDigits: 2,
                                        maximumFractionDigits: 2
                                    });
                                }
                            }
                        }
                    },
                    scales: {
                        x: {
                            grid: {
                                display: false
                            }
                        },
                        y: {
                            beginAtZero: true,
                            ticks: {
                                callback: function(value) {
                                    return '₱' + value.toLocaleString();
                                }
                            }
                        }
                    }
                }
            });
            
            // Payment Method Chart
            const paymentMethodChartCtx = document.getElementById('paymentMethodChart').getContext('2d');
            const paymentMethodChart = new Chart(paymentMethodChartCtx, {
                type: 'doughnut',
                data: {
                    labels: <?php echo json_encode($payment_method_labels); ?>,
                    datasets: [{
                        data: <?php echo json_encode($payment_method_data); ?>,
                        backgroundColor: [
                            '#2563eb', // Primary
                            '#10b981', // Success
                            '#f59e0b', // Warning
                            '#0ea5e9', // Info
                            '#8b5cf6', // Purple
                            '#ec4899', // Pink
                            '#6b7280', // Gray
                        ],
                        borderWidth: 2,
                        borderColor: '#ffffff'
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'right',
                            labels: {
                                padding: 20,
                                usePointStyle: true,
                                pointStyle: 'circle'
                            }
                        },
                        tooltip: {
                            backgroundColor: '#ffffff',
                            titleColor: '#1e293b',
                            bodyColor: '#1e293b',
                            borderColor: '#e2e8f0',
                            borderWidth: 1,
                            padding: 12,
                            boxPadding: 6,
                            usePointStyle: true,
                            callbacks: {
                                label: function(context) {
                                    const value = parseFloat(context.raw).toLocaleString(undefined, {
                                        minimumFractionDigits: 2,
                                        maximumFractionDigits: 2
                                    });
                                    const total = context.chart.data.datasets[0].data.reduce((a, b) => a + b, 0);
                                    const percentage = Math.round((context.raw / total) * 100);
                                    return `₱${value} (${percentage}%)`;
                                }
                            }
                        }
                    },
                    cutout: '65%',
                    radius: '90%'
                }
            });
            
            // Order Status Chart
            const orderStatusChartCtx = document.getElementById('orderStatusChart').getContext('2d');
            const orderStatusChart = new Chart(orderStatusChartCtx, {
                type: 'bar',
                data: {
                    labels: <?php echo json_encode($status_labels); ?>,
                    datasets: [{
                        label: 'Revenue by Status',
                        data: <?php echo json_encode($status_data); ?>,
                        backgroundColor: [
                            '#f59e0b', // Pending
                            '#0ea5e9', // Processing
                            '#10b981', // Shipped
                            '#2563eb', // Delivered
                            '#ef4444', // Cancelled
                        ],
                        borderWidth: 0,
                        borderRadius: 6,
                        maxBarThickness: 40
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: false
                        },
                        tooltip: {
                            backgroundColor: '#ffffff',
                            titleColor: '#1e293b',
                            bodyColor: '#1e293b',
                            borderColor: '#e2e8f0',
                            borderWidth: 1,
                            padding: 12,
                            boxPadding: 6,
                            usePointStyle: true,
                            callbacks: {
                                label: function(context) {
                                    return '₱' + parseFloat(context.raw).toLocaleString(undefined, {
                                        minimumFractionDigits: 2,
                                        maximumFractionDigits: 2
                                    });
                                }
                            }
                        }
                    },
                    scales: {
                        x: {
                            grid: {
                                display: false
                            }
                        },
                        y: {
                            beginAtZero: true,
                            ticks: {
                                callback: function(value) {
                                    return '₱' + value.toLocaleString();
                                }
                            }
                        }
                    }
                }
            });
            
            // Customer Frequency Chart
            const customerFrequencyChartCtx = document.getElementById('customerFrequencyChart').getContext('2d');
            const customerFrequencyChart = new Chart(customerFrequencyChartCtx, {
                type: 'pie',
                data: {
                    labels: <?php echo json_encode($frequency_labels); ?>,
                    datasets: [{
                        data: <?php echo json_encode($frequency_data); ?>,
                        backgroundColor: [
                            '#2563eb', // Primary
                            '#10b981', // Success
                            '#f59e0b', // Warning
                            '#0ea5e9', // Info
                            '#8b5cf6', // Purple
                            '#ec4899', // Pink
                        ],
                        borderWidth: 2,
                        borderColor: '#ffffff'
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'right',
                            labels: {
                                padding: 20,
                                usePointStyle: true,
                                pointStyle: 'circle'
                            }
                        },
                        tooltip: {
                            backgroundColor: '#ffffff',
                            titleColor: '#1e293b',
                            bodyColor: '#1e293b',
                            borderColor: '#e2e8f0',
                            borderWidth: 1,
                            padding: 12,
                            boxPadding: 6,
                            usePointStyle: true,
                            callbacks: {
                                label: function(context) {
                                    const value = context.raw;
                                    const total = context.chart.data.datasets[0].data.reduce((a, b) => a + b, 0);
                                    const percentage = Math.round((value / total) * 100);
                                    return `${value} customers (${percentage}%)`;
                                }
                            }
                        }
                    },
                    radius: '90%'
                }
            });
        });
    </script>
</body>
</html>
