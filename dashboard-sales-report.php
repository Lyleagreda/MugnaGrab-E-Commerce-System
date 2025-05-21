<?php
session_start();
include '../db.php';
include '../data/products.php';
include 'product-sales-tracker.php';

// Check if admin is logged in
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

// Get date range parameters
$period = isset($_GET['period']) ? $_GET['period'] : 'all';
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : '';
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : '';

// Initialize response array
$response = [
    'success' => true,
    'data' => [
        'summary' => [
            'total_sales' => 0,
            'total_orders' => 0,
            'average_order_value' => 0,
            'total_products_sold' => 0
        ],
        'top_products' => [],
        'sales_by_category' => [],
        'sales_over_time' => []
    ]
];

try {
    // Build date condition based on period
    $date_condition = "";
    $params = [];
    $types = "";
    
    if ($period === 'today') {
        $date_condition = "DATE(created_at) = CURDATE()";
    } elseif ($period === 'yesterday') {
        $date_condition = "DATE(created_at) = DATE_SUB(CURDATE(), INTERVAL 1 DAY)";
    } elseif ($period === 'last7days') {
        $date_condition = "DATE(created_at) >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)";
    } elseif ($period === 'last30days') {
        $date_condition = "DATE(created_at) >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)";
    } elseif ($period === 'thisMonth') {
        $date_condition = "YEAR(created_at) = YEAR(CURDATE()) AND MONTH(created_at) = MONTH(CURDATE())";
    } elseif ($period === 'lastMonth') {
        $date_condition = "YEAR(created_at) = YEAR(DATE_SUB(CURDATE(), INTERVAL 1 MONTH)) AND MONTH(created_at) = MONTH(DATE_SUB(CURDATE(), INTERVAL 1 MONTH))";
    } elseif ($period === 'custom' && !empty($start_date) && !empty($end_date)) {
        $date_condition = "DATE(created_at) BETWEEN ? AND ?";
        $params[] = $start_date;
        $params[] = $end_date;
        $types .= "ss";
    }
    
    // Add status condition to only include completed orders
    $status_condition = "status IN ('shipped', 'delivered', 'received')";
    
    // Combine conditions
    $where_clause = "";
    if (!empty($date_condition)) {
        $where_clause = "WHERE $date_condition AND $status_condition";
    } else {
        $where_clause = "WHERE $status_condition";
    }
    
    // Get sales summary
    $summary_query = "
        SELECT 
            COUNT(*) as total_orders,
            SUM(total) as total_sales,
            AVG(total) as average_order_value
        FROM orders
        $where_clause
    ";
    
    $stmt = $conn->prepare($summary_query);
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $summary_result = $stmt->get_result();
    $summary = $summary_result->fetch_assoc();
    
    $response['data']['summary']['total_orders'] = intval($summary['total_orders']);
    $response['data']['summary']['total_sales'] = floatval($summary['total_sales']);
    $response['data']['summary']['average_order_value'] = floatval($summary['average_order_value']);
    
    // Get total products sold
    $products_query = "
        SELECT 
            SUM(quantity) as total_products_sold
        FROM order_items oi
        JOIN orders o ON oi.order_id = o.id
        $where_clause
    ";
    
    $stmt = $conn->prepare($products_query);
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $products_result = $stmt->get_result();
    $products_summary = $products_result->fetch_assoc();
    
    $response['data']['summary']['total_products_sold'] = intval($products_summary['total_products_sold']);
    
    // Get top products
    $top_products_query = "
        SELECT 
            oi.product_id,
            oi.product_name,
            SUM(oi.quantity) as quantity_sold,
            SUM(oi.total) as revenue
        FROM order_items oi
        JOIN orders o ON oi.order_id = o.id
        $where_clause
        GROUP BY oi.product_id, oi.product_name
        ORDER BY revenue DESC
        LIMIT 10
    ";
    
    $stmt = $conn->prepare($top_products_query);
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $top_products_result = $stmt->get_result();
    
    $top_products = [];
    while ($product = $top_products_result->fetch_assoc()) {
        // Find product in products array to get category
        $category = 'Uncategorized';
        foreach ($products as $p) {
            if ($p['id'] == $product['product_id']) {
                $category = $p['category'];
                break;
            }
        }
        
        $top_products[] = [
            'product_id' => $product['product_id'],
            'product_name' => $product['product_name'],
            'category' => $category,
            'quantity_sold' => intval($product['quantity_sold']),
            'revenue' => floatval($product['revenue'])
        ];
    }
    
    $response['data']['top_products'] = $top_products;
    
    // Get sales by category
    $category_sales = [];
    
    // Use the top products to calculate category sales
    foreach ($top_products as $product) {
        $category = $product['category'];
        
        if (!isset($category_sales[$category])) {
            $category_sales[$category] = [
                'category' => $category,
                'revenue' => 0,
                'quantity_sold' => 0
            ];
        }
        
        $category_sales[$category]['revenue'] += $product['revenue'];
        $category_sales[$category]['quantity_sold'] += $product['quantity_sold'];
    }
    
    // Convert to indexed array and sort by revenue
    $category_sales = array_values($category_sales);
    usort($category_sales, function($a, $b) {
        return $b['revenue'] <=> $a['revenue'];
    });
    
    $response['data']['sales_by_category'] = $category_sales;
    
    // Get sales over time (daily for the last 30 days)
    $time_query = "
        SELECT 
            DATE(created_at) as date,
            COUNT(*) as orders,
            SUM(total) as revenue
        FROM orders
        WHERE status IN ('shipped', 'delivered', 'received')
        AND created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
        GROUP BY DATE(created_at)
        ORDER BY date
    ";
    
    $time_result = $conn->query($time_query);
    
    $sales_over_time = [];
    while ($day = $time_result->fetch_assoc()) {
        $sales_over_time[] = [
            'date' => $day['date'],
            'orders' => intval($day['orders']),
            'revenue' => floatval($day['revenue'])
        ];
    }
    
    $response['data']['sales_over_time'] = $sales_over_time;
    
} catch (Exception $e) {
    $response['success'] = false;
    $response['message'] = 'Error generating sales report: ' . $e->getMessage();
}

// Return JSON response
header('Content-Type: application/json');
echo json_encode($response);
