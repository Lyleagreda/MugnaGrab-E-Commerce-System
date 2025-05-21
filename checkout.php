<?php
session_start();
require 'db.php'; // Include your database connection
include 'data/products.php'; // Include product data to get images

// Check if the user is logged in
if (!isset($_SESSION['logged_in']) || !isset($_SESSION['user_email'])) {
    // Display JavaScript alert if not logged in
    echo "<script>alert('You are not logged in. Please log in to access checkout.');</script>";
    // Redirect to login page
    echo "<script>window.location.href = 'index.php';</script>";
    exit;
}

// Check if there are selected cart items in session
if (!isset($_SESSION['selected_cart_items']) || empty($_SESSION['selected_cart_items'])) {
    // Redirect back to cart page if no items are selected
    header('Location: view_cart.php');
    exit;
}

// Get user ID from session
$user_email = $_SESSION['user_email'];
$stmt_user = $conn->prepare("SELECT id, first_name, last_name, email, phone FROM users WHERE email = :email");
$stmt_user->bindParam(':email', $user_email, PDO::PARAM_STR);
$stmt_user->execute();

if ($stmt_user->rowCount() > 0) {
    $user = $stmt_user->fetch(PDO::FETCH_ASSOC);
    $user_id = $user['id'];
} else {
    // If no matching user is found, terminate the process
    echo "<script>alert('User session invalid. Please log in again.');</script>";
    echo "<script>window.location.href = 'index.php';</script>";
    exit;
}

// Create placeholders for the selected cart items
$placeholders = implode(',', array_fill(0, count($_SESSION['selected_cart_items']), '?'));
$cart_ids = $_SESSION['selected_cart_items'];

// Fetch user's addresses
// Check if is_default column exists in the user_addresses table
$stmt_check_column = $conn->prepare("SHOW COLUMNS FROM user_addresses LIKE 'is_default'");
$stmt_check_column->execute();
if ($stmt_check_column->rowCount() > 0) {
    $stmt_addresses = $conn->prepare("SELECT * FROM user_addresses WHERE user_id = :user_id ORDER BY is_default DESC");
} else {
    $stmt_addresses = $conn->prepare("SELECT * FROM user_addresses WHERE user_id = :user_id");
}
$stmt_addresses->bindParam(':user_id', $user_id, PDO::PARAM_INT);
$stmt_addresses->execute();
$addresses = $stmt_addresses->fetchAll(PDO::FETCH_ASSOC);

// Fetch selected items from the user's cart
$params = array_merge([$user_id], $cart_ids);
$stmt_cart = $conn->prepare("SELECT c.cart_id, c.product_id, c.product_name, c.price, c.quantity, (c.price * c.quantity) AS total_price 
                             FROM cart c 
                             WHERE c.user_id = ? AND c.cart_id IN (" . $placeholders . ")");

// Execute with all parameters in an array
$stmt_cart->execute($params);

// Check if there are any items in the cart
if ($stmt_cart->rowCount() > 0) {
    $cart_items = $stmt_cart->fetchAll(PDO::FETCH_ASSOC);
} else {
    // Redirect to cart page if cart is empty
    header('Location: view_cart.php');
    exit;
}

// Create a lookup array for product images
$product_images = [];
foreach ($products as $product) {
    $product_images[$product['id']] = $product['image'];
}

// Calculate cart totals
$subtotal = 0;
foreach ($cart_items as $item) {
    $subtotal += $item['total_price'];
}

$total = $subtotal; // No shipping or tax, just the subtotal

// Process checkout form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['place_order'])) {
    // Get form data
    $shipping_address_id = $_POST['shipping_address'];
    $payment_method = $_POST['payment_method'];
    $shipping_fee = isset($_POST['shipping_fee']) ? floatval($_POST['shipping_fee']) : 0;
    
    // Fetch the selected shipping address
    $stmt_address = $conn->prepare("SELECT * FROM user_addresses WHERE id = :shipping_address_id");
    $stmt_address->bindParam(':shipping_address_id', $shipping_address_id, PDO::PARAM_INT);
    $stmt_address->execute();
    $shipping_address = $stmt_address->fetch(PDO::FETCH_ASSOC);
    
    if ($shipping_address) {
        // If shipping fee wasn't provided in the form, calculate it now as a fallback
        if ($shipping_fee <= 0) {
            // Get the city and state of the selected address
            $city = $shipping_address['city'];
            $state = $shipping_address['state'];
            
            // Fetch shipping fee based on city and state
            $stmt_shipping_fee = $conn->prepare("SELECT fee_amount FROM delivery_fees WHERE city = :city AND state = :state AND is_available = 1");
            $stmt_shipping_fee->bindParam(':city', $city, PDO::PARAM_STR);
            $stmt_shipping_fee->bindParam(':state', $state, PDO::PARAM_STR);
            $stmt_shipping_fee->execute();
            
            if ($stmt_shipping_fee->rowCount() > 0) {
                $shipping_fee = $stmt_shipping_fee->fetchColumn();
            } else {
                // Try to get a default fee for the state
                $stmt_default = $conn->prepare("SELECT fee_amount FROM delivery_fees WHERE state = :state AND city = 'Default' AND is_available = 1");
                $stmt_default->bindParam(':state', $state, PDO::PARAM_STR);
                $stmt_default->execute();
                
                if ($stmt_default->rowCount() > 0) {
                    $shipping_fee = $stmt_default->fetchColumn();
                }
            }
        }
        
        // Add shipping fee to the total
        $total = $subtotal + $shipping_fee;
        
        // Handle payment screenshot upload for GCash and PayPal
        $payment_screenshot = null;
        if (($payment_method == 'gcash' || $payment_method == 'paypal') && isset($_FILES['payment_screenshot']) && $_FILES['payment_screenshot']['error'] == 0) {
            $upload_dir = 'uploads/payment_screenshots/';
            
            // Create directory if it doesn't exist
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            
            $file_name = time() . '_' . basename($_FILES['payment_screenshot']['name']);
            $target_file = $upload_dir . $file_name;
            
            // Check if image file is a actual image
            $check = getimagesize($_FILES['payment_screenshot']['tmp_name']);
            if ($check !== false) {
                // Upload file
                if (move_uploaded_file($_FILES['payment_screenshot']['tmp_name'], $target_file)) {
                    $payment_screenshot = $target_file;
                } else {
                    $error_message = "Sorry, there was an error uploading your file.";
                }
            } else {
                $error_message = "File is not an image.";
            }
        } else if (($payment_method == 'gcash' || $payment_method == 'paypal') && (!isset($_FILES['payment_screenshot']) || $_FILES['payment_screenshot']['error'] != 0)) {
            $error_message = "Please upload a payment screenshot for " . ($payment_method == 'gcash' ? 'GCash' : 'PayPal') . " payment.";
        }
        
        // If there's no error, proceed with order creation
        if (!isset($error_message)) {
            try {
                // Start transaction
                $conn->beginTransaction();
                
                // Insert order
                $stmt_order = $conn->prepare("INSERT INTO orders (user_id, shipping_address_id, payment_method, payment_screenshot, subtotal, total, status, created_at)
                                             VALUES (:user_id, :shipping_address_id, :payment_method, :payment_screenshot, :subtotal, :total, 'pending', NOW())");
                $stmt_order->bindParam(':user_id', $user_id, PDO::PARAM_INT);
                $stmt_order->bindParam(':shipping_address_id', $shipping_address_id, PDO::PARAM_INT);
                $stmt_order->bindParam(':payment_method', $payment_method, PDO::PARAM_STR);
                $stmt_order->bindParam(':payment_screenshot', $payment_screenshot, PDO::PARAM_STR);
                $stmt_order->bindParam(':subtotal', $subtotal, PDO::PARAM_STR);
                $stmt_order->bindParam(':total', $total, PDO::PARAM_STR);
                $stmt_order->execute();
                
                $order_id = $conn->lastInsertId();
                
                // Insert order items
                foreach ($cart_items as $item) {
                    $stmt_item = $conn->prepare("INSERT INTO order_items (order_id, product_id, product_name, price, quantity, total)
                                                VALUES (:order_id, :product_id, :product_name, :price, :quantity, :total)");
                    $stmt_item->bindParam(':order_id', $order_id, PDO::PARAM_INT);
                    $stmt_item->bindParam(':product_id', $item['product_id'], PDO::PARAM_INT);
                    $stmt_item->bindParam(':product_name', $item['product_name'], PDO::PARAM_STR);
                    $stmt_item->bindParam(':price', $item['price'], PDO::PARAM_STR);
                    $stmt_item->bindParam(':quantity', $item['quantity'], PDO::PARAM_INT);
                    $stmt_item->bindParam(':total', $item['total_price'], PDO::PARAM_STR);
                    $stmt_item->execute();
                }
                
                // Remove selected items from cart
                foreach ($cart_ids as $cart_id) {
                    $stmt_remove = $conn->prepare("DELETE FROM cart WHERE cart_id = :cart_id AND user_id = :user_id");
                    $stmt_remove->bindParam(':cart_id', $cart_id, PDO::PARAM_INT);
                    $stmt_remove->bindParam(':user_id', $user_id, PDO::PARAM_INT);
                    $stmt_remove->execute();
                }
                
                // Clear selected items from session
                unset($_SESSION['selected_cart_items']);
                
                // Commit transaction
                $conn->commit();
                
                // Redirect to order confirmation page
                header("Location: order_confirmation.php?order_id=$order_id&shipping_fee=$shipping_fee");
                exit;
            } catch (PDOException $e) {
                // Rollback transaction on error
                $conn->rollBack();
                $error_message = "Order processing failed: " . $e->getMessage();
            }
        }
    } else {
        // If no address is found, handle the error
        echo "<script>alert('Invalid shipping address. Please select a valid address.');</script>";
    }
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Checkout - Mugna</title>
    <link rel="stylesheet" href="css/styles.css">
    <link rel="shortcut icon" href="./images/mugna-icon.png" type="image/x-icon">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary: #4f46e5;
            --primary-hover: #4338ca;
            --primary-light: #eef2ff;
            --secondary: #64748b;
            --success: #10b981;
            --danger: #ef4444;
            --warning: #f59e0b;
            --dark: #1e293b;
            --light: #f8fafc;
            --border: #e2e8f0;
            --border-dark: #cbd5e1;
            --shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
            --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
            --radius: 0.5rem;
            --radius-lg: 1rem;
            --transition: all 0.3s ease;
        }

        /* Modern Reset */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, 'Open Sans', 'Helvetica Neue', sans-serif;
            color: var(--dark);
            background-color: #f9fafb;
            line-height: 1.6;
        }

        /* Checkout Page Styles */
        .checkout-container {
            max-width: 1200px;
            margin: 2rem auto;
            padding: 0 1rem;
            display: grid;
            grid-template-columns: 1fr 400px;
            gap: 2rem;
        }

        .checkout-header {
            margin-bottom: 2rem;
            text-align: center;
        }

        .checkout-title {
            font-size: 2rem;
            font-weight: 700;
            color: var(--dark);
            margin-bottom: 0.5rem;
        }

        .checkout-subtitle {
            color: var(--secondary);
            font-size: 1rem;
        }

        .checkout-progress {
            display: flex;
            justify-content: center;
            margin: 2rem 0;
        }

        .progress-step {
            display: flex;
            align-items: center;
            margin: 0 1rem;
        }

        .step-number {
            width: 30px;
            height: 30px;
            border-radius: 50%;
            background-color: var(--primary-light);
            color: var(--primary);
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            margin-right: 0.5rem;
        }

        .step-number.active {
            background-color: var(--primary);
            color: white;
        }

        .step-text {
            font-weight: 500;
            color: var(--secondary);
        }

        .step-text.active {
            color: var(--primary);
            font-weight: 600;
        }

        .checkout-form {
            background-color: white;
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow);
            overflow: hidden;
        }

        .checkout-section {
            padding: 2rem;
            border-bottom: 1px solid var(--border);
        }

        .checkout-section:last-child {
            border-bottom: none;
        }

        .section-header {
            display: flex;
            align-items: center;
            margin-bottom: 1.5rem;
        }

        .section-icon {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background-color: var(--primary-light);
            color: var(--primary);
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 1rem;
            font-size: 1.25rem;
        }

        .section-title {
            font-size: 1.25rem;
            font-weight: 600;
            color: var(--dark);
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
            color: var(--dark);
            font-size: 0.875rem;
        }

        .form-input {
            width: 100%;
            padding: 0.75rem 1rem;
            border: 1px solid var(--border);
            border-radius: var(--radius);
            font-size: 1rem;
            transition: var(--transition);
            background-color: white;
        }

        .form-input:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.1);
        }

        .form-input:disabled,
        .form-input[readonly] {
            background-color: #f9fafb;
            cursor: not-allowed;
        }

        .form-row {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 1rem;
        }

        /* Address Cards */
        .address-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 1rem;
            margin-bottom: 1.5rem;
        }

        .address-card {
            border: 2px solid var(--border);
            border-radius: var(--radius);
            padding: 1.25rem;
            cursor: pointer;
            transition: var(--transition);
            position: relative;
            height: 100%;
        }

        .address-card:hover {
            border-color: var(--primary);
            transform: translateY(-2px);
        }

        .address-card.selected {
            border-color: var(--primary);
            background-color: var(--primary-light);
        }

        .address-card-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 0.75rem;
        }

        .address-card-title {
            font-weight: 600;
            color: var(--dark);
            font-size: 1rem;
        }

        .address-card-badge {
            font-size: 0.75rem;
            padding: 0.25rem 0.5rem;
            border-radius: 20px;
            background-color: var(--primary);
            color: white;
        }

        .address-card-content {
            color: var(--secondary);
            font-size: 0.875rem;
            line-height: 1.5;
        }

        .address-card-radio {
            position: absolute;
            opacity: 0;
            cursor: pointer;
        }

        .address-card-check {
            position: absolute;
            top: 1rem;
            right: 1rem;
            width: 20px;
            height: 20px;
            border-radius: 50%;
            border: 2px solid var(--border-dark);
            display: flex;
            align-items: center;
            justify-content: center;
            transition: var(--transition);
        }

        .address-card.selected .address-card-check {
            border-color: var(--primary);
            background-color: var(--primary);
            color: white;
        }

        .address-card.selected .address-card-check i {
            display: block;
        }

        .address-card-check i {
            display: none;
            font-size: 0.75rem;
        }

        .add-address-btn {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.75rem 1.25rem;
            background-color: white;
            border: 1px dashed var(--border-dark);
            border-radius: var(--radius);
            color: var(--primary);
            font-weight: 500;
            cursor: pointer;
            transition: var(--transition);
        }

        .add-address-btn:hover {
            background-color: var(--primary-light);
            border-color: var(--primary);
        }

        /* Payment Methods */
        .payment-methods {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 1rem;
            margin-bottom: 1.5rem;
        }

        .payment-method {
            border: 2px solid var(--border);
            border-radius: var(--radius);
            padding: 1.25rem;
            cursor: pointer;
            transition: var(--transition);
            position: relative;
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .payment-method:hover {
            border-color: var(--primary);
            transform: translateY(-2px);
        }

        .payment-method.selected {
            border-color: var(--primary);
            background-color: var(--primary-light);
        }

        .payment-method-radio {
            position: absolute;
            opacity: 0;
            cursor: pointer;
        }

        .payment-method-check {
            width: 20px;
            height: 20px;
            border-radius: 50%;
            border: 2px solid var(--border-dark);
            display: flex;
            align-items: center;
            justify-content: center;
            transition: var(--transition);
        }

        .payment-method.selected .payment-method-check {
            border-color: var(--primary);
            background-color: var(--primary);
            color: white;
        }

        .payment-method.selected .payment-method-check i {
            display: block;
        }

        .payment-method-check i {
            display: none;
            font-size: 0.75rem;
        }

        .payment-method-icon {
            width: 48px;
            height: 48px;
            border-radius: var(--radius);
            background-color: var(--primary-light);
            color: var(--primary);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
        }

        .payment-method-info {
            flex: 1;
        }

        .payment-method-title {
            font-weight: 600;
            color: var(--dark);
            margin-bottom: 0.25rem;
        }

        .payment-method-description {
            font-size: 0.875rem;
            color: var(--secondary);
        }

        /* Payment Instructions */
        .payment-instructions {
            margin-top: 1.5rem;
            padding: 1.25rem;
            border-radius: var(--radius);
            background-color: var(--primary-light);
            border-left: 4px solid var(--primary);
            display: none;
        }

        .payment-instructions.active {
            display: block;
            animation: fadeIn 0.3s ease;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .payment-instructions-title {
            font-weight: 600;
            color: var(--dark);
            margin-bottom: 0.75rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .payment-instructions-title i {
            color: var(--primary);
        }

        .payment-instructions-list {
            list-style-type: none;
            padding: 0;
            margin: 0;
        }

        .payment-instructions-list li {
            margin-bottom: 0.75rem;
            font-size: 0.875rem;
            color: var(--secondary);
            display: flex;
            align-items: flex-start;
            gap: 0.75rem;
        }

        .payment-instructions-list li:last-child {
            margin-bottom: 0;
        }

        .payment-instructions-list li i {
            color: var(--primary);
            margin-top: 0.25rem;
        }

        .payment-account {
            font-weight: 600;
            color: var(--dark);
            background-color: rgba(79, 70, 229, 0.1);
            padding: 0.25rem 0.5rem;
            border-radius: 4px;
        }

        /* Modern Upload Area */
        .upload-container {
            margin-top: 1.5rem;
            display: none;
        }

        .upload-container.active {
            display: block;
            animation: fadeIn 0.3s ease;
        }

        .upload-title {
            font-weight: 600;
            color: var(--dark);
            margin-bottom: 0.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .upload-title i {
            color: var(--primary);
        }

        .upload-description {
            font-size: 0.875rem;
            color: var(--secondary);
            margin-bottom: 1rem;
        }

        .upload-area {
            border: 2px dashed var(--border-dark);
            border-radius: var(--radius);
            padding: 2rem;
            text-align: center;
            background-color: #f9fafb;
            transition: var(--transition);
            cursor: pointer;
            position: relative;
        }

        .upload-area:hover {
            border-color: var(--primary);
            background-color: var(--primary-light);
        }

        .upload-area.active {
            border-color: var(--primary);
            background-color: var(--primary-light);
        }

        .upload-area.has-image {
            border-style: solid;
            padding: 1rem;
            background-color: white;
        }

        .upload-icon {
            font-size: 3rem;
            color: var(--primary);
            margin-bottom: 1rem;
        }

        .upload-text {
            color: var(--dark);
            font-weight: 500;
            margin-bottom: 0.5rem;
        }

        .upload-hint {
            font-size: 0.75rem;
            color: var(--secondary);
        }

        .upload-input {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            opacity: 0;
            cursor: pointer;
        }

        .image-preview-container {
            position: relative;
            margin-top: 1rem;
            display: none;
        }

        .image-preview-container.active {
            display: block;
        }

        .image-preview {
            width: 100%;
            height: 200px;
            object-fit: contain;
            border-radius: var(--radius);
            border: 1px solid var(--border);
            background-color: white;
        }

        .preview-actions {
            display: flex;
            justify-content: center;
            gap: 1rem;
            margin-top: 1rem;
        }

        .preview-action {
            padding: 0.5rem 1rem;
            border-radius: var(--radius);
            font-size: 0.875rem;
            font-weight: 500;
            cursor: pointer;
            transition: var(--transition);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            border: none;
        }

        .preview-action.view {
            background-color: var(--primary-light);
            color: var(--primary);
        }

        .preview-action.view:hover {
            background-color: rgba(79, 70, 229, 0.2);
        }

        .preview-action.remove {
            background-color: #fee2e2;
            color: var(--danger);
        }

        .preview-action.remove:hover {
            background-color: #fecaca;
        }

        /* Order Summary */
        .order-summary {
            background-color: white;
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow);
            position: sticky;
            top: 2rem;
            height: fit-content;
        }

        .order-summary-header {
            padding: 1.5rem;
            border-bottom: 1px solid var(--border);
        }

        .order-summary-title {
            font-size: 1.25rem;
            font-weight: 600;
            color: var(--dark);
            margin-bottom: 0.5rem;
        }

        .order-summary-subtitle {
            font-size: 0.875rem;
            color: var(--secondary);
        }

        .order-items {
            padding: 1.5rem;
            max-height: 300px;
            overflow-y: auto;
            border-bottom: 1px solid var(--border);
        }

        .order-item {
            display: flex;
            gap: 1rem;
            margin-bottom: 1.25rem;
            padding-bottom: 1.25rem;
            border-bottom: 1px solid var(--border);
        }

        .order-item:last-child {
            margin-bottom: 0;
            padding-bottom: 0;
            border-bottom: none;
        }

        .order-item-image {
            width: 64px;
            height: 64px;
            border-radius: var(--radius);
            overflow: hidden;
            background-color: #f9fafb;
            display: flex;
            align-items: center;
            justify-content: center;
            border: 1px solid var(--border);
        }

        .order-item-image img {
            max-width: 90%;
            max-height: 90%;
            object-fit: contain;
        }

        .order-item-info {
            flex: 1;
        }

        .order-item-name {
            font-weight: 600;
            color: var(--dark);
            margin-bottom: 0.25rem;
            font-size: 0.875rem;
        }

        .order-item-meta {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .order-item-price {
            font-size: 0.875rem;
            color: var(--secondary);
        }

        .order-item-quantity {
            font-size: 0.75rem;
            color: var(--secondary);
            background-color: #f1f5f9;
            padding: 0.25rem 0.5rem;
            border-radius: 20px;
        }

        .order-summary-details {
            padding: 1.5rem;
        }

        .summary-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 0.75rem;
            font-size: 0.875rem;
        }

        .summary-row-label {
            color: var(--secondary);
        }

        .summary-row-value {
            font-weight: 500;
            color: var(--dark);
        }

        .summary-total {
            display: flex;
            justify-content: space-between;
            padding-top: 1rem;
            margin-top: 1rem;
            border-top: 1px solid var(--border);
        }

        .summary-total-label {
            font-size: 1rem;
            font-weight: 600;
            color: var(--dark);
        }

        .summary-total-value {
            font-size: 1.25rem;
            font-weight: 700;
            color: var(--primary);
        }

        .place-order-btn {
            width: 100%;
            padding: 1rem;
            background-color: var(--primary);
            color: white;
            border: none;
            border-radius: var(--radius);
            font-weight: 600;
            cursor: pointer;
            transition: var(--transition);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            margin-top: 1.5rem;
        }

        .place-order-btn:hover {
            background-color: var(--primary-hover);
        }

        .back-to-cart {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            margin-top: 1rem;
            color: var(--primary);
            font-weight: 500;
            transition: var(--transition);
            font-size: 0.875rem;
        }

        .back-to-cart:hover {
            color: var(--primary-hover);
        }

        /* Image Modal */
        .image-modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            overflow: auto;
            background-color: rgba(15, 23, 42, 0.9);
            opacity: 0;
            transition: opacity 0.3s ease;
            backdrop-filter: blur(5px);
        }

        .image-modal.active {
            display: flex;
            align-items: center;
            justify-content: center;
            opacity: 1;
        }

        .image-modal-content {
            max-width: 90%;
            max-height: 90%;
            margin: auto;
            position: relative;
            animation: zoomIn 0.3s ease;
        }

        @keyframes zoomIn {
            from { transform: scale(0.9); opacity: 0; }
            to { transform: scale(1); opacity: 1; }
        }

        .image-modal-img {
            width: 100%;
            max-height: 90vh;
            object-fit: contain;
            border-radius: var(--radius);
            box-shadow: var(--shadow-lg);
        }

        .image-modal-close {
            position: absolute;
            top: -40px;
            right: 0;
            color: white;
            font-size: 1.5rem;
            cursor: pointer;
            transition: var(--transition);
            width: 36px;
            height: 36px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            background-color: rgba(255, 255, 255, 0.2);
        }

        .image-modal-close:hover {
            background-color: rgba(255, 255, 255, 0.3);
            transform: rotate(90deg);
        }

        .alert {
            padding: 1rem 1.5rem;
            border-radius: var(--radius);
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .alert-danger {
            background-color: #fee2e2;
            color: #b91c1c;
            border-left: 4px solid #ef4444;
        }

        .alert-icon {
            font-size: 1.25rem;
        }

        /* Responsive Adjustments */
        @media (max-width: 992px) {
            .checkout-container {
                grid-template-columns: 1fr;
            }
            
            .order-summary {
                position: static;
                margin-top: 2rem;
            }
        }

        @media (max-width: 768px) {
            .payment-methods,
            .address-grid {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 576px) {
            .form-row {
                grid-template-columns: 1fr;
            }
            
            .preview-actions {
                flex-direction: column;
                gap: 0.5rem;
            }

            .checkout-section {
                padding: 1.5rem;
            }
        }

        /* Utility Classes */
        .hidden {
            display: none;
        }

        .sr-only {
            position: absolute;
            width: 1px;
            height: 1px;
            padding: 0;
            margin: -1px;
            overflow: hidden;
            clip: rect(0, 0, 0, 0);
            white-space: nowrap;
            border-width: 0;
        }
    </style>
</head>
<body>
    <div class="site-container">
        <?php include 'includes/header.php'; ?>
        
        <main class="main-content">
            <div class="container">
                <div class="checkout-header">
                    <h1 class="checkout-title">Checkout</h1>
                    <p class="checkout-subtitle">Complete your purchase by providing the details below</p>
                    
                    <div class="checkout-progress">
                        <div class="progress-step">
                            <div class="step-number active">1</div>
                            <span class="step-text active">Cart</span>
                        </div>
                        <div class="progress-step">
                            <div class="step-number active">2</div>
                            <span class="step-text active">Checkout</span>
                        </div>
                        <div class="progress-step">
                            <div class="step-number">3</div>
                            <span class="step-text">Confirmation</span>
                        </div>
                    </div>
                </div>
                
                <?php if (isset($error_message)): ?>
                    <div class="alert alert-danger">
                        <span class="alert-icon"><i class="fas fa-exclamation-circle"></i></span>
                        <span><?php echo $error_message; ?></span>
                    </div>
                <?php endif; ?>
                
                <div class="checkout-container">
                    <div class="checkout-form">
                        <form method="post" action="checkout.php" id="checkout-form" enctype="multipart/form-data">
                            <!-- Customer Information -->
                            <div class="checkout-section">
                                <div class="section-header">
                                    <div class="section-icon">
                                        <i class="fas fa-user"></i>
                                    </div>
                                    <h2 class="section-title">Customer Information</h2>
                                </div>
                                
                                <div class="form-row">
                                    <div class="form-group">
                                        <label class="form-label" for="first_name">First Name</label>
                                        <input type="text" id="first_name" name="first_name" class="form-input" value="<?php echo htmlspecialchars($user['first_name'] ?? ''); ?>" readonly>
                                    </div>
                                    
                                    <div class="form-group">
                                        <label class="form-label" for="last_name">Last Name</label>
                                        <input type="text" id="last_name" name="last_name" class="form-input" value="<?php echo htmlspecialchars($user['last_name'] ?? ''); ?>" readonly>
                                    </div>
                                </div>
                                
                                <div class="form-row">
                                    <div class="form-group">
                                        <label class="form-label" for="email">Email</label>
                                        <input type="email" id="email" name="email" class="form-input" value="<?php echo htmlspecialchars($user['email'] ?? ''); ?>" readonly>
                                    </div>
                                    
                                    <div class="form-group">
                                        <label class="form-label" for="phone">Phone</label>
                                        <input type="tel" id="phone" name="phone" class="form-input" value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>" readonly>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Shipping Address -->
                            <div class="checkout-section">
                                <div class="section-header">
                                    <div class="section-icon">
                                        <i class="fas fa-map-marker-alt"></i>
                                    </div>
                                    <h2 class="section-title">Shipping Address</h2>
                                </div>
                                
                                <?php if (empty($addresses)): ?>
                                    <div class="form-group">
                                        <p>You don't have any saved addresses. Please add one.</p>
                                        <a href="account.php?tab=addresses" class="add-address-btn">
                                            <i class="fas fa-plus"></i> Add New Address
                                        </a>
                                    </div>
                                <?php else: ?>
                                    <div class="address-grid">
                                        <?php foreach ($addresses as $address): ?>
                                            <div class="address-card <?php echo (isset($address['is_default']) && $address['is_default']) ? 'selected' : ''; ?>">
                                                <div class="address-card-check">
                                                    <i class="fas fa-check"></i>
                                                </div>
                                                <div class="address-card-header">
                                                    <h4 class="address-card-title"><?php echo htmlspecialchars($address['address_name'] ?? 'My Address'); ?></h4>
                                                    <?php if ((isset($address['is_default']) && $address['is_default'])): ?>
                                                        <span class="address-card-badge">Default</span>
                                                    <?php endif; ?>
                                                </div>
                                                <div class="address-card-content">
                                                    <p><?php echo htmlspecialchars($address['address_line1']); ?></p>
                                                    <?php if (!empty($address['address_line2'])): ?>
                                                        <p><?php echo htmlspecialchars($address['address_line2']); ?></p>
                                                    <?php endif; ?>
                                                    <p>
                                                        <?php echo htmlspecialchars($address['city']); ?>,
                                                        <?php echo htmlspecialchars($address['state']); ?>,
                                                        <?php echo htmlspecialchars($address['postal_code']); ?>
                                                    </p>
                                                </div>
                                                <input type="radio" name="shipping_address" value="<?php echo $address['id']; ?>" <?php echo (isset($address['is_default']) && $address['is_default']) ? 'checked' : ''; ?> class="address-card-radio">
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                    
                                    <div class="form-group">
                                        <a href="account.php?tab=addresses" class="add-address-btn">
                                            <i class="fas fa-plus"></i> Add New Address
                                        </a>
                                    </div>
                                <?php endif; ?>
                            </div>
                            
                            <!-- Payment Method -->
                            <div class="checkout-section">
                                <div class="section-header">
                                    <div class="section-icon">
                                        <i class="fas fa-credit-card"></i>
                                    </div>
                                    <h2 class="section-title">Payment Method</h2>
                                </div>
                                
                                <div class="payment-methods">
                                    <div class="payment-method selected">
                                        <div class="payment-method-check">
                                            <i class="fas fa-check"></i>
                                        </div>
                                        <input type="radio" name="payment_method" value="cash_on_delivery" class="payment-method-radio" checked>
                                        <div class="payment-method-icon">
                                            <i class="fas fa-money-bill-wave"></i>
                                        </div>
                                        <div class="payment-method-info">
                                            <h4 class="payment-method-title">Cash on Delivery</h4>
                                            <p class="payment-method-description">Pay when you receive your order</p>
                                        </div>
                                    </div>
                                    
                                    <div class="payment-method">
                                        <div class="payment-method-check">
                                            <i class="fas fa-check"></i>
                                        </div>
                                        <input type="radio" name="payment_method" value="gcash" class="payment-method-radio">
                                        <div class="payment-method-icon">
                                            <i class="fas fa-mobile-alt"></i>
                                        </div>
                                        <div class="payment-method-info">
                                            <h4 class="payment-method-title">GCash</h4>
                                            <p class="payment-method-description">Pay with your GCash account</p>
                                        </div>
                                    </div>
                                    
                                    <div class="payment-method">
                                        <div class="payment-method-check">
                                            <i class="fas fa-check"></i>
                                        </div>
                                        <input type="radio" name="payment_method" value="paypal" class="payment-method-radio">
                                        <div class="payment-method-icon">
                                            <i class="fab fa-paypal"></i>
                                        </div>
                                        <div class="payment-method-info">
                                            <h4 class="payment-method-title">PayPal</h4>
                                            <p class="payment-method-description">Pay with your PayPal account</p>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Payment Instructions -->
                                <div id="gcash-instructions" class="payment-instructions">
                                    <h4 class="payment-instructions-title">
                                        <i class="fas fa-info-circle"></i> GCash Payment Instructions
                                    </h4>
                                    <ul class="payment-instructions-list">
                                        <li><i class="fas fa-circle-check"></i> Send payment to GCash number: <span class="payment-account">0936-800-5521</span></li>
                                        <li><i class="fas fa-circle-check"></i> Account name: <span class="payment-account">Mugna Leather Arts</span></li>
                                        <li><i class="fas fa-circle-check"></i> Include your name and email in the payment reference</li>
                                        <li><i class="fas fa-circle-check"></i> Take a screenshot of your payment receipt</li>
                                        <li><i class="fas fa-circle-check"></i> Upload the screenshot below</li>
                                    </ul>
                                </div>
                                
                                <div id="paypal-instructions" class="payment-instructions">
                                    <h4 class="payment-instructions-title">
                                        <i class="fas fa-info-circle"></i> PayPal Payment Instructions
                                    </h4>
                                    <ul class="payment-instructions-list">
                                        <li><i class="fas fa-circle-check"></i> Send payment to PayPal email: <span class="payment-account">payments@mugnaleatherarts.com</span></li>
                                        <li><i class="fas fa-circle-check"></i> Include your name and email in the payment notes</li>
                                        <li><i class="fas fa-circle-check"></i> Take a screenshot of your payment receipt</li>
                                        <li><i class="fas fa-circle-check"></i> Upload the screenshot below</li>
                                    </ul>
                                </div>
                                
                                <!-- Modern Payment Screenshot Upload -->
                                <div id="upload-container" class="upload-container">
                                    <h4 class="upload-title">
                                        <i class="fas fa-upload"></i> Upload Payment Screenshot
                                    </h4>
                                    <p class="upload-description">Please upload a screenshot of your payment receipt for verification.</p>
                                    
                                    <div id="upload-area" class="upload-area">
                                        <input type="file" name="payment_screenshot" id="payment-screenshot" class="upload-input" accept="image/*">
                                        <div id="upload-placeholder">
                                            <div class="upload-icon">
                                                <i class="fas fa-cloud-upload-alt"></i>
                                            </div>
                                            <p class="upload-text">Drag and drop your receipt here or click to browse</p>
                                            <p class="upload-hint">Supported formats: JPG, PNG, GIF (Max size: 5MB)</p>
                                        </div>
                                    </div>
                                    
                                    <div id="image-preview-container" class="image-preview-container">
                                        <img id="image-preview" class="image-preview" src="/placeholder.svg" alt="Receipt Preview">
                                        
                                        <div class="preview-actions">
                                            <button type="button" id="view-image" class="preview-action view">
                                                <i class="fas fa-eye"></i> View Full Image
                                            </button>
                                            <button type="button" id="remove-image" class="preview-action remove">
                                                <i class="fas fa-trash"></i> Remove Image
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <button type="submit" name="place_order" class="place-order-btn">
                                <i class="fas fa-lock"></i>
                                Place Order
                            </button>
                        </form>
                    </div>
                    
                    <div class="order-summary">
                        <div class="order-summary-header">
                            <h3 class="order-summary-title">Order Summary</h3>
                            <p class="order-summary-subtitle"><?php echo count($cart_items); ?> items in your cart</p>
                        </div>
                        
                        <div class="order-items">
                            <?php foreach ($cart_items as $item): ?>
                                <div class="order-item">
                                    <div class="order-item-image">
                                        <?php 
                                        // Get product image from the products array
                                        $image_src = isset($product_images[$item['product_id']]) ? $product_images[$item['product_id']] : 'images/placeholder.jpg';
                                        ?>
                                        <img src="<?php echo htmlspecialchars($image_src); ?>" alt="<?php echo htmlspecialchars($item['product_name']); ?>">
                                    </div>
                                    <div class="order-item-info">
                                        <h4 class="order-item-name"><?php echo htmlspecialchars($item['product_name']); ?></h4>
                                        <div class="order-item-meta">
                                            <span class="order-item-price">₱<?php echo number_format($item['price'], 2); ?></span>
                                            <span class="order-item-quantity">x<?php echo $item['quantity']; ?></span>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        
                        <div class="order-summary-details">
    <div class="summary-row">
        <span class="summary-row-label">Subtotal</span>
        <span id="subtotal-value" class="summary-row-value" data-value="<?php echo $subtotal; ?>">₱<?php echo number_format($subtotal, 2); ?></span>
    </div>
    
    <div class="summary-row">
        <span class="summary-row-label">Shipping <span id="shipping-details" style="display: none; font-size: 0.75rem; color: #64748b;"></span></span>
        <span id="shipping-fee-value" class="summary-row-value">Select an address</span>
    </div>
    
    <div class="summary-total">
        <span class="summary-total-label">Total</span>
        <span id="total-value" class="summary-total-value">₱<?php echo number_format($total, 2); ?></span>
    </div>
    
    <a href="view_cart.php" class="back-to-cart">
        <i class="fas fa-arrow-left"></i>
        Back to Cart
    </a>
</div>
                    </div>
                </div>
            </div>
        </main>
        
        <!-- Image Modal -->
        <div id="image-modal" class="image-modal">
            <div class="image-modal-content">
                <span id="image-modal-close" class="image-modal-close">&times;</span>
                <img id="image-modal-img" class="image-modal-img" src="/placeholder.svg" alt="Payment Receipt">
            </div>
        </div>
        
        <?php include 'includes/footer.php'; ?>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Address card selection
            const addressCards = document.querySelectorAll('.address-card');
            addressCards.forEach(card => {
                card.addEventListener('click', function() {
                    // Remove selected class from all cards
                    addressCards.forEach(c => c.classList.remove('selected'));
                    
                    // Add selected class to clicked card
                    this.classList.add('selected');
                    
                    // Check the radio button
                    const radio = this.querySelector('input[type="radio"]');
                    radio.checked = true;
                    
                    // Get the address ID
                    const addressId = radio.value;
                    
                    // Fetch shipping fee for this address
                    fetchShippingFee(addressId);
                });
            });

            // Function to fetch shipping fee based on address
            function fetchShippingFee(addressId) {
                // Show loading indicator in the shipping row
                document.getElementById('shipping-fee-value').innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
                
                // Create and send AJAX request
                const xhr = new XMLHttpRequest();
                xhr.open('GET', 'get_shipping_fee.php?address_id=' + addressId, true);
                
                xhr.onload = function() {
                    if (this.status === 200) {
                        try {
                            const response = JSON.parse(this.responseText);
                            
                            if (response.success) {
                                // Update shipping fee in the order summary
                                const shippingFeeValue = document.getElementById('shipping-fee-value');
                                shippingFeeValue.textContent = '₱' + parseFloat(response.fee).toLocaleString(undefined, {
                                    minimumFractionDigits: 2,
                                    maximumFractionDigits: 2
                                });
                                
                                // Update total
                                const subtotal = parseFloat(document.getElementById('subtotal-value').getAttribute('data-value'));
                                const shippingFee = parseFloat(response.fee);
                                const total = subtotal + shippingFee;
                                
                                // Update total display
                                const totalValue = document.getElementById('total-value');
                                totalValue.textContent = '₱' + total.toLocaleString(undefined, {
                                    minimumFractionDigits: 2,
                                    maximumFractionDigits: 2
                                });
                                
                                // Store shipping fee in a hidden input for form submission
                                let shippingFeeInput = document.getElementById('shipping-fee-input');
                                if (!shippingFeeInput) {
                                    shippingFeeInput = document.createElement('input');
                                    shippingFeeInput.type = 'hidden';
                                    shippingFeeInput.id = 'shipping-fee-input';
                                    shippingFeeInput.name = 'shipping_fee';
                                    document.getElementById('checkout-form').appendChild(shippingFeeInput);
                                }
                                shippingFeeInput.value = response.fee;
                                
                                // Show shipping details if available
                                if (response.city && response.state) {
                                    const shippingDetails = document.getElementById('shipping-details');
                                    if (shippingDetails) {
                                        shippingDetails.textContent = `(${response.city}, ${response.state})`;
                                        shippingDetails.style.display = 'inline';
                                    }
                                }
                            } else {
                                // Show error message
                                document.getElementById('shipping-fee-value').textContent = 'Error calculating';
                            }
                        } catch (e) {
                            console.error('Error parsing shipping fee response:', e);
                            document.getElementById('shipping-fee-value').textContent = 'Error calculating';
                        }
                    } else {
                        document.getElementById('shipping-fee-value').textContent = 'Error calculating';
                    }
                };
                
                xhr.onerror = function() {
                    document.getElementById('shipping-fee-value').textContent = 'Error calculating';
                };
                
                xhr.send();
            }
            
            // Payment method selection
            const paymentMethods = document.querySelectorAll('.payment-method');
            const uploadContainer = document.getElementById('upload-container');
            const gcashInstructions = document.getElementById('gcash-instructions');
            const paypalInstructions = document.getElementById('paypal-instructions');
            
            paymentMethods.forEach(method => {
                method.addEventListener('click', function() {
                    // Remove selected class from all methods
                    paymentMethods.forEach(m => m.classList.remove('selected'));
                    
                    // Add selected class to clicked method
                    this.classList.add('selected');
                    
                    // Check the radio button
                    const radio = this.querySelector('input[type="radio"]');
                    radio.checked = true;
                    
                    // Show/hide payment screenshot upload based on payment method
                    if (radio.value === 'gcash') {
                        uploadContainer.classList.add('active');
                        gcashInstructions.classList.add('active');
                        paypalInstructions.classList.remove('active');
                    } else if (radio.value === 'paypal') {
                        uploadContainer.classList.add('active');
                        paypalInstructions.classList.add('active');
                        gcashInstructions.classList.remove('active');
                    } else {
                        uploadContainer.classList.remove('active');
                        gcashInstructions.classList.remove('active');
                        paypalInstructions.classList.remove('active');
                    }
                });
            });
            
            // Modern file upload handling
            const uploadArea = document.getElementById('upload-area');
            const uploadInput = document.getElementById('payment-screenshot');
            const imagePreview = document.getElementById('image-preview');
            const uploadPlaceholder = document.getElementById('upload-placeholder');
            const imagePreviewContainer = document.getElementById('image-preview-container');
            const viewImageBtn = document.getElementById('view-image');
            const removeImageBtn = document.getElementById('remove-image');
            const imageModal = document.getElementById('image-modal');
            const imageModalImg = document.getElementById('image-modal-img');
            const imageModalClose = document.getElementById('image-modal-close');
            
            // Drag and drop functionality
            ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
                uploadArea.addEventListener(eventName, preventDefaults, false);
            });
            
            function preventDefaults(e) {
                e.preventDefault();
                e.stopPropagation();
            }
            
            ['dragenter', 'dragover'].forEach(eventName => {
                uploadArea.addEventListener(eventName, highlight, false);
            });
            
            ['dragleave', 'drop'].forEach(eventName => {
                uploadArea.addEventListener(eventName, unhighlight, false);
            });
            
            function highlight() {
                uploadArea.classList.add('active');
            }
            
            function unhighlight() {
                uploadArea.classList.remove('active');
            }
            
            uploadArea.addEventListener('drop', handleDrop, false);
            
            function handleDrop(e) {
                const dt = e.dataTransfer;
                const files = dt.files;
                
                if (files.length) {
                    uploadInput.files = files;
                    handleFiles(files);
                }
            }
            
            uploadInput.addEventListener('change', function() {
                if (this.files.length) {
                    handleFiles(this.files);
                }
            });
            
            function handleFiles(files) {
                const file = files[0];
                
                // Check if file is an image
                if (!file.type.match('image.*')) {
                    alert('Please select an image file (JPG, PNG, GIF)');
                    return;
                }
                
                // Check file size (max 5MB)
                if (file.size > 5 * 1024 * 1024) {
                    alert('File size exceeds 5MB limit');
                    return;
                }
                
                const reader = new FileReader();
                
                reader.onload = function(e) {
                    // Show image preview
                    imagePreview.src = e.target.result;
                    imagePreviewContainer.classList.add('active');
                    uploadPlaceholder.style.display = 'none';
                    uploadArea.classList.add('has-image');
                };
                
                reader.readAsDataURL(file);
            }
            
            // View image in modal
            viewImageBtn.addEventListener('click', function() {
                imageModalImg.src = imagePreview.src;
                imageModal.classList.add('active');
            });
            
            // Close modal
            imageModalClose.addEventListener('click', function() {
                imageModal.classList.remove('active');
            });
            
            // Close modal when clicking outside
            imageModal.addEventListener('click', function(e) {
                if (e.target === imageModal) {
                    imageModal.classList.remove('active');
                }
            });
            
            // Remove image
            removeImageBtn.addEventListener('click', function() {
                imagePreview.src = '';
                imagePreviewContainer.classList.remove('active');
                uploadPlaceholder.style.display = 'block';
                uploadArea.classList.remove('has-image');
                uploadInput.value = '';
            });
            
            // Form validation
            const checkoutForm = document.getElementById('checkout-form');
            if (checkoutForm) {
                checkoutForm.addEventListener('submit', function(e) {
                    // Check if an address is selected
                    const addressSelected = document.querySelector('input[name="shipping_address"]:checked');
                    if (!addressSelected && addressCards.length > 0) {
                        e.preventDefault();
                        alert('Please select a shipping address');
                        return false;
                    }
                    
                    // Check if a payment method is selected
                    const paymentSelected = document.querySelector('input[name="payment_method"]:checked');
                    if (!paymentSelected) {
                        e.preventDefault();
                        alert('Please select a payment method');
                        return false;
                    }
                    
                    // Check if payment screenshot is uploaded for GCash or PayPal
                    if ((paymentSelected.value === 'gcash' || paymentSelected.value === 'paypal')) {
                        if (!uploadInput.files || uploadInput.files.length === 0) {
                            e.preventDefault();
                            alert('Please upload a payment screenshot for ' + 
                                  (paymentSelected.value === 'gcash' ? 'GCash' : 'PayPal') + 
                                  ' payment');
                            return false;
                        }
                    }
                    
                    return true;
                });
            }
        });

// Initialize shipping fee for pre-selected address (if any)
document.addEventListener('DOMContentLoaded', function() {
    // Check if there's a pre-selected address
    const selectedAddress = document.querySelector('.address-card.selected input[type="radio"]');
    if (selectedAddress) {
        // Fetch shipping fee for the pre-selected address
        fetchShippingFee(selectedAddress.value);
    }
});
    </script>
</body>
</html>
