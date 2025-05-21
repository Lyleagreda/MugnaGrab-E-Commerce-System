<?php
session_start();

// Check if admin is logged in
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true || !isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Unauthorized access']);
    exit;
}

include '../config.php'; // Make sure this file contains your DB connection ($conn)

// Include product data from parent directory
include '../data/products.php';

// Check if order ID is provided
if (!isset($_GET['order_id']) || empty($_GET['order_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Order ID is required']);
    exit;
}

$order_id = intval($_GET['order_id']);

// Get order details
$order_query = "SELECT o.*, u.id as user_id, u.first_name, u.last_name, u.email, u.phone 
                FROM orders o 
                LEFT JOIN users u ON o.user_id = u.id 
                WHERE o.id = ?";
$stmt = $conn->prepare($order_query);
$stmt->bind_param("i", $order_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Order not found']);
    exit;
}

$order = $result->fetch_assoc();

// Get order items
$items_query = "SELECT oi.* 
                FROM order_items oi 
                WHERE oi.order_id = ?";
$stmt = $conn->prepare($items_query);
$stmt->bind_param("i", $order_id);
$stmt->execute();
$items_result = $stmt->get_result();
$items = [];

while ($item = $items_result->fetch_assoc()) {
    // Try to find product image from products array
    $product_image = '../images/placeholder.jpg';
    foreach ($products as $product) {
        if ($product['id'] == $item['product_id']) {
            // Make sure image path is correctly referenced from admin folder
            $product_image = '../' . $product['image'];
            break;
        }
    }
    
    // Add product image to item data
    $item['product_image'] = $product_image;
    $items[] = $item;
}

// Get shipping address
$shipping_query = "SELECT * FROM user_addresses WHERE id = ?";
$stmt = $conn->prepare($shipping_query);
$stmt->bind_param("i", $order['shipping_address_id']);
$stmt->execute();
$shipping_result = $stmt->get_result();
$shipping = $shipping_result->fetch_assoc();

// If no shipping address found, use default placeholder
if (!$shipping) {
    $shipping = [
        'address_line1' => 'No shipping address provided',
        'address_line2' => '',
        'city' => '',
        'state' => '',
        'postal_code' => ''
    ];
}

// Prepare customer data
$customer = [
    'user_id' => $order['user_id'],
    'first_name' => $order['first_name'],
    'last_name' => $order['last_name'],
    'email' => $order['email'],
    'phone' => $order['phone']
];

// Prepare response data
$response = [
    'order' => $order,
    'items' => $items,
    'customer' => $customer,
    'shipping' => $shipping
];

// Return JSON response
header('Content-Type: application/json');
echo json_encode($response);
exit;
?>
