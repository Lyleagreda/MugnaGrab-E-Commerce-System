<?php
session_start();
include '../db.php';
include '../data/products.php';

// Check if admin is logged in
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

// Initialize response array
$response = [
    'success' => true,
    'data' => [
        'labels' => [],
        'datasets' => [
            [
                'data' => [],
                'backgroundColor' => ['#2563eb', '#10b981', '#f59e0b', '#ef4444', '#8b5cf6'],
                'borderWidth' => 0
            ]
        ]
    ]
];

try {
    // Get sales by category from order_items joined with products
    $stmt = $conn->prepare("
        SELECT 
            oi.product_id,
            oi.product_name,
            SUM(oi.total) as category_sales
        FROM 
            order_items oi
        JOIN 
            orders o ON oi.order_id = o.id
        WHERE 
            o.status IN ('shipped', 'delivered', 'received')
        GROUP BY 
            oi.product_id, oi.product_name
    ");
    $stmt->execute();
    $productSales = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Group by category using products array
    $categorySales = [];
    
    if (count($productSales) > 0) {
        foreach ($productSales as $product) {
            // Find product in products array to get category
            $category = 'Uncategorized';
            foreach ($products as $p) {
                if ($p['id'] == $product['product_id']) {
                    $category = $p['category'];
                    break;
                }
            }
            
            if (!isset($categorySales[$category])) {
                $categorySales[$category] = 0;
            }
            
            $categorySales[$category] += floatval($product['category_sales']);
        }
        
        // Sort by sales amount
        arsort($categorySales);
        
        // Get top 5 categories
        $categorySales = array_slice($categorySales, 0, 5, true);
    } else {
        // If no order data, use products array to estimate category distribution
        $categoryData = [];
        foreach ($products as $product) {
            $category = $product['category'] ?? 'Uncategorized';
            $price = isset($product['price']) ? $product['price'] : 0;
            $estimatedSales = $price * (isset($product['reviews']) ? ($product['reviews'] / 10) : 5);
            
            if (!isset($categoryData[$category])) {
                $categoryData[$category] = 0;
            }
            $categoryData[$category] += $estimatedSales;
        }
        
        // Sort by sales and get top 5
        arsort($categoryData);
        $categorySales = array_slice($categoryData, 0, 5, true);
    }
    
    // If still no data, use sample data
    if (empty($categorySales)) {
        $categorySales = [
            'Laptops' => 35,
            'Desktops' => 25,
            'Components' => 20,
            'Peripherals' => 15,
            'Monitors' => 5
        ];
    }
    
    // Prepare data for chart
    foreach ($categorySales as $category => $sales) {
        $response['data']['labels'][] = $category;
        $response['data']['datasets'][0]['data'][] = $sales;
    }
} catch (PDOException $e) {
    $response['success'] = false;
    $response['message'] = 'Database error: ' . $e->getMessage();
}

// Return JSON response
header('Content-Type: application/json');
echo json_encode($response);
