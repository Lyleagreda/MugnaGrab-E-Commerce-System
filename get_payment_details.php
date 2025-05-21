<?php
session_start();
include '../config.php';

// Check if admin is logged in
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
   header('Content-Type: application/json');
   echo json_encode(['error' => 'Unauthorized access']);
   exit;
}

// Check if order_id is provided
if (!isset($_GET['order_id'])) {
   header('Content-Type: application/json');
   echo json_encode(['error' => 'Order ID is required']);
   exit;
}

$order_id = $_GET['order_id'];

try {
   // Get order details
   $order_query = "SELECT o.* FROM orders o WHERE o.id = ?";
   $stmt = $conn->prepare($order_query);
   $stmt->bind_param("i", $order_id);
   $stmt->execute();
   $order_result = $stmt->get_result();
   
   if ($order_result->num_rows === 0) {
       header('Content-Type: application/json');
       echo json_encode(['error' => 'Order not found']);
       exit;
   }
   
   $order = $order_result->fetch_assoc();
   
   // Get customer details
   $customer_query = "SELECT u.* FROM users u WHERE u.id = ?";
   $stmt = $conn->prepare($customer_query);
   $stmt->bind_param("i", $order['user_id']);
   $stmt->execute();
   $customer_result = $stmt->get_result();
   $customer = $customer_result->fetch_assoc();
   
   // Get order items
   $items_query = "SELECT oi.* FROM order_items oi WHERE oi.order_id = ?";
   $stmt = $conn->prepare($items_query);
   $stmt->bind_param("i", $order_id);
   $stmt->execute();
   $items_result = $stmt->get_result();
   $items = [];
   
   while ($item = $items_result->fetch_assoc()) {
       $items[] = $item;
   }
   
   // Get shipping address
   $address_query = "SELECT * FROM user_addresses WHERE id = ?";
   $stmt = $conn->prepare($address_query);
   $stmt->bind_param("i", $order['shipping_address_id']);
   $stmt->execute();
   $address_result = $stmt->get_result();
   $shipping_address = $address_result->fetch_assoc();
   
   // Prepare response
   $response = [
       'order' => $order,
       'customer' => $customer,
       'items' => $items,
       'shipping_address' => $shipping_address
   ];
   
   header('Content-Type: application/json');
   echo json_encode($response);
   
} catch (Exception $e) {
   header('Content-Type: application/json');
   echo json_encode(['error' => 'Error fetching order details: ' . $e->getMessage()]);
}
