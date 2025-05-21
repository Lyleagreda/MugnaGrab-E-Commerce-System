<?php
// Include product data
include 'data/products.php';

// Get product ID from request
$product_id = isset($_GET['product_id']) ? intval($_GET['product_id']) : 0;

// Find the product in the products array
$product = null;
foreach ($products as $p) {
    if ($p['id'] == $product_id) {
        $product = $p;
        break;
    }
}

// If product found, return as JSON
if ($product) {
    // Format the product data for the modal
    $formatted_product = [
        'id' => $product['id'],
        'name' => $product['name'],
        'price' => $product['price'],
        'oldPrice' => $product['oldPrice'],
        'image' => $product['image'],
        'category' => $product['category'],
        'rating' => $product['rating'],
        'reviews' => $product['reviews'],
        'description' => $product['description'],
        'stock' => $product['stock'],
        'sale' => $product['sale'],
        'new' => $product['new']
    ];
    
    // Return as JSON
    header('Content-Type: application/json');
    echo json_encode($formatted_product);
} else {
    // Return error
    header('HTTP/1.1 404 Not Found');
    echo json_encode(['error' => 'Product not found']);
}
?>
