<?php
session_start();
include '../db.php';
include '../data/products.php';
include 'product-sales-tracker.php'; // Include our sales tracker

// Check if admin is logged in
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

// Initialize response array
$response = [
    'success' => true,
    'data' => []
];

try {
    // First try to get top selling products from our sales tracker
    $topProducts = getTopSellingProducts('revenue', 5);
    
    // If we have sales data
    if (!empty($topProducts)) {
        $formattedProducts = [];
        
        foreach ($topProducts as $product) {
            $product_id = $product['id'];
            $product_details = null;
            
            // Find product details in products array
            foreach ($products as $p) {
                if ($p['id'] == $product_id) {
                    $product_details = $p;
                    break;
                }
            }
            
            if ($product_details) {
                $formattedProducts[] = [
                    'product_id' => $product_id,
                    'product_name' => $product_details['name'],
                    'category' => $product_details['category'] ?? 'Uncategorized',
                    'quantity_sold' => $product['quantity_sold'],
                    'revenue' => $product['revenue'],
                    'image' => $product_details['image'] ?? 'images/products/placeholder.jpg'
                ];
            }
        }
        
        $response['data'] = $formattedProducts;
    } else {
        // If no sales data, fall back to database query
        $stmt = $conn->prepare("
            SELECT 
                oi.product_id,
                oi.product_name,
                SUM(oi.quantity) as quantity_sold,
                SUM(oi.total) as revenue
            FROM 
                order_items oi
            JOIN 
                orders o ON oi.order_id = o.id
            WHERE 
                o.status IN ('shipped', 'delivered', 'received')
            GROUP BY 
                oi.product_id, oi.product_name
            ORDER BY 
                revenue DESC
            LIMIT 5
        ");
        $stmt->execute();
        $topProducts = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // If we have results from the database
        if (!empty($topProducts)) {
            // Enhance with product details from products array
            foreach ($topProducts as &$product) {
                // Find product in products array to get category and image
                $productDetails = null;
                foreach ($products as $p) {
                    if ($p['id'] == $product['product_id']) {
                        $productDetails = $p;
                        break;
                    }
                }
                
                // Add category and image if found
                if ($productDetails) {
                    $product['category'] = $productDetails['category'] ?? 'Uncategorized';
                    $product['image'] = $productDetails['image'] ?? 'images/products/placeholder.jpg';
                } else {
                    $product['category'] = 'Uncategorized';
                    $product['image'] = 'images/products/placeholder.jpg';
                }
            }
            
            $response['data'] = $topProducts;
        } else {
            // If no order data, use top products from products array based on price and reviews
            usort($products, function($a, $b) {
                $aScore = isset($a['price']) && isset($a['reviews']) ? $a['price'] * $a['reviews'] : 0;
                $bScore = isset($b['price']) && isset($b['reviews']) ? $b['price'] * $b['reviews'] : 0;
                return $bScore <=> $aScore;
            });
            
            // $sampleSold = [124, 98, 76, 65, 210]; // Sample sold quantities
            // $topProducts = [];
            
            for ($i = 0; $i < min(5, count($products)); $i++) {
                $product = $products[$i];
                $revenue = isset($product['price']) ? $product['price'] * ($sampleSold[$i] ?? 50) : 0;
                
                $topProducts[] = [
                    'product_id' => $product['id'],
                    'product_name' => $product['name'],
                    'category' => $product['category'] ?? 'Uncategorized',
                    'quantity_sold' => $sampleSold[$i] ?? 50,
                    'revenue' => $revenue,
                    'image' => $product['image'] ?? 'images/products/placeholder.jpg'
                ];
            }
            
            $response['data'] = $topProducts;
        }
    }
} catch (PDOException $e) {
    $response['success'] = false;
    $response['message'] = 'Database error: ' . $e->getMessage();
}

// Return JSON response
header('Content-Type: application/json');
echo json_encode($response);
