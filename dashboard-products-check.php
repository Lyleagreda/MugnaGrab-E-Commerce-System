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
        'totalProducts' => count($products),
        'lowStockItems' => 0,
        'lowStockThreshold' => 20,
        'lowStockProducts' => []
    ]
];

// Count low stock items and collect their details
foreach ($products as $product) {
    if (isset($product['stock']) && $product['stock'] <= 20) {
        $response['data']['lowStockItems']++;
        $response['data']['lowStockProducts'][] = [
            'id' => $product['id'],
            'name' => $product['name'],
            'category' => $product['category'],
            'stock' => $product['stock'],
            'image' => $product['image'] ?? 'images/products/placeholder.jpg'
        ];
    }
}

// Return JSON response
header('Content-Type: application/json');
echo json_encode($response);
