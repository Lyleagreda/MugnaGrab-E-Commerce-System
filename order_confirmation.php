<?php
session_start();
require 'db.php'; // Include your database connection

// Check if the user is logged in
if (!isset($_SESSION['logged_in']) || !isset($_SESSION['user_email'])) {
    // Redirect to login page
    header('Location: index.php');
    exit;
}

// Check if order ID is provided
if (!isset($_GET['order_id']) || empty($_GET['order_id'])) {
    // Redirect to home page
    header('Location: home.php');
    exit;
}

$order_id = intval($_GET['order_id']);
$user_email = $_SESSION['user_email'];

// Get user ID from session
$stmt_user = $conn->prepare("SELECT id FROM users WHERE email = :email");
$stmt_user->bindParam(':email', $user_email, PDO::PARAM_STR);
$stmt_user->execute();

if ($stmt_user->rowCount() > 0) {
    $user = $stmt_user->fetch(PDO::FETCH_ASSOC);
    $user_id = $user['id'];
} else {
    // Redirect to login page
    header('Location: index.php');
    exit;
}

// Fetch order details
$stmt_order = $conn->prepare("SELECT o.*, a.address_line1, a.address_line2, a.city, a.state, a.postal_code
                             FROM orders o
                             JOIN user_addresses a ON o.shipping_address_id = a.id
                             WHERE o.id = :order_id AND o.user_id = :user_id");
$stmt_order->bindParam(':order_id', $order_id, PDO::PARAM_INT);
$stmt_order->bindParam(':user_id', $user_id, PDO::PARAM_INT);
$stmt_order->execute();

if ($stmt_order->rowCount() === 0) {
    // Order not found or doesn't belong to user
    header('Location: home.php');
    exit;
}

$order = $stmt_order->fetch(PDO::FETCH_ASSOC);

// Fetch the shipping fee based on the city and state
$stmt_shipping_fee = $conn->prepare("SELECT fee_amount FROM delivery_fees WHERE city = :city AND state = :state");
$stmt_shipping_fee->bindParam(':city', $order['city'], PDO::PARAM_STR);
$stmt_shipping_fee->bindParam(':state', $order['state'], PDO::PARAM_STR);
$stmt_shipping_fee->execute();

$shipping_fee = 0; // Default shipping fee if not found
if ($stmt_shipping_fee->rowCount() > 0) {
    $shipping_fee_data = $stmt_shipping_fee->fetch(PDO::FETCH_ASSOC);
    $shipping_fee = $shipping_fee_data['fee_amount'];
}

// Fetch order items
$stmt_items = $conn->prepare("SELECT * FROM order_items WHERE order_id = :order_id");
$stmt_items->bindParam(':order_id', $order_id, PDO::PARAM_INT);
$stmt_items->execute();
$order_items = $stmt_items->fetchAll(PDO::FETCH_ASSOC);

// Calculate the total price (including shipping fee)
$total_price_with_shipping = $order['total'] + $shipping_fee;

include 'data/products.php'; // make sure path is correct
$product_images = [];

foreach ($products as $product) {
    $product_images[$product['id']] = $product['image'];
}

?>


<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order Confirmation - Mugna</title>
    <link rel="stylesheet" href="css/styles.css">
    <link rel="shortcut icon" href="./images/mugna-icon.png" type="image/x-icon">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* Order Confirmation Styles */
        .page-header {
            background-color: #f1f5f9;
            padding: 3rem 0;
            margin-bottom: 2rem;
            border-radius: 16px;
        }

        .page-title {
            font-size: 2.5rem;
            font-weight: 700;
            color: #1e3a8a;
            margin-bottom: 0.5rem;
        }

        .breadcrumb {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            color: #64748b;
        }

        .breadcrumb a {
            color: #2563eb;
            transition: all 0.3s ease;
        }

        .breadcrumb a:hover {
            color: #1d4ed8;
        }

        .breadcrumb-separator {
            color: #94a3b8;
        }

        .confirmation-container {
            background-color: white;
            border-radius: 12px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
            padding: 2rem;
            margin-bottom: 2rem;
            text-align: center;
        }

        .confirmation-icon {
            width: 80px;
            height: 80px;
            background-color: #dcfce7;
            color: #16a34a;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1.5rem;
            font-size: 2rem;
        }

        .confirmation-title {
            font-size: 1.5rem;
            font-weight: 700;
            color: #1e3a8a;
            margin-bottom: 0.5rem;
        }

        .confirmation-message {
            color: #64748b;
            margin-bottom: 1.5rem;
        }

        .order-number {
            font-weight: 600;
            color: #2563eb;
        }

        .order-details {
            background-color: white;
            border-radius: 12px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
            padding: 2rem;
            margin-bottom: 2rem;
        }

        .order-details-title {
            font-size: 1.25rem;
            font-weight: 600;
            color: #1e3a8a;
            margin-bottom: 1.5rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid #e2e8f0;
        }

        .order-info-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 2rem;
            margin-bottom: 2rem;
        }

        .order-info-section {
            margin-bottom: 1.5rem;
        }

        .order-info-section-title {
            font-weight: 600;
            color: #1e3a8a;
            margin-bottom: 0.5rem;
        }

        .order-info-content {
            color: #64748b;
            font-size: 0.875rem;
        }

        .order-items-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 1.5rem;
        }

        .order-items-table th {
            text-align: left;
            padding: 1rem;
            border-bottom: 1px solid #e2e8f0;
            color: #1e3a8a;
            font-weight: 600;
        }

        .order-items-table td {
            padding: 1rem;
            border-bottom: 1px solid #e2e8f0;
            color: #64748b;
        }

        .order-items-table tr:last-child td {
            border-bottom: none;
        }

        .order-item-product {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .order-item-image {
            width: 60px;
            height: 60px;
            border-radius: 8px;
            overflow: hidden;
            background-color: #f8fafc;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .order-item-image img {
            max-width: 90%;
            max-height: 90%;
            object-fit: contain;
        }

        .order-item-name {
            font-weight: 600;
            color: #1e3a8a;
        }

        .order-summary {
            background-color: #f8fafc;
            border-radius: 8px;
            padding: 1.5rem;
        }

        .order-summary-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 0.75rem;
        }

        .order-summary-label {
            color: #64748b;
        }

        .order-summary-value {
            font-weight: 600;
            color: #1e3a8a;
        }

        .order-summary-total {
            display: flex;
            justify-content: space-between;
            margin-top: 0.75rem;
            padding-top: 0.75rem;
            border-top: 1px solid #e2e8f0;
        }

        .order-summary-total-label {
            font-weight: 600;
            color: #1e3a8a;
        }

        .order-summary-total-value {
            font-weight: 700;
            color: #2563eb;
        }

        .action-buttons {
            display: flex;
            gap: 1rem;
            justify-content: center;
            margin-top: 2rem;
        }

        .action-button {
            padding: 0.75rem 1.5rem;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .action-button.primary {
            background-color: #2563eb;
            color: white;
        }

        .action-button.primary:hover {
            background-color: #1d4ed8;
        }

        .action-button.secondary {
            background-color: white;
            color: #1e3a8a;
            border: 1px solid #e2e8f0;
        }

        .action-button.secondary:hover {
            background-color: #f8fafc;
        }

        /* Responsive Adjustments */
        @media (max-width: 768px) {
            .order-info-grid {
                grid-template-columns: 1fr;
                gap: 1rem;
            }

            .action-buttons {
                flex-direction: column;
            }
        }
    </style>
</head>

<body>
    <div class="site-container">
        <?php include 'includes/header.php'; ?>

        <main class="main-content">
            <div class="container">


                <!-- Confirmation Message -->
                <div class="confirmation-container">
                    <div class="confirmation-icon">
                        <i class="fas fa-check"></i>
                    </div>
                    <h2 class="confirmation-title">Thank You for Your Order!</h2>
                    <p class="confirmation-message">
                        Your order has been placed successfully. We've sent a confirmation email to your registered
                        email address.
                        <br>
                        Your order number is <span class="order-number">#<?php echo $order_id; ?></span>
                    </p>
                </div>

                <!-- Order Details -->
                <div class="order-details">
                    <h3 class="order-details-title">Order Details</h3>

                    <div class="order-info-grid">
                        <div>
                            <div class="order-info-section">
                                <h4 class="order-info-section-title">Order Information</h4>
                                <div class="order-info-content">
                                    <p><strong>Order Number:</strong> #<?php echo $order_id; ?></p>
                                    <p><strong>Date:</strong>
                                        <?php echo date('F j, Y', strtotime($order['created_at'])); ?></p>
                                    <p><strong>Status:</strong> <?php echo ucfirst($order['status']); ?></p>
                                    <p><strong>Payment Method:</strong>
                                        <?php echo ucwords(str_replace('_', ' ', $order['payment_method'])); ?></p>
                                </div>
                            </div>
                        </div>

                        <div>
                            <div class="order-info-section">
                                <h4 class="order-info-section-title">Shipping Address</h4>
                                <div class="order-info-content">
                                    <p><?php echo htmlspecialchars($order['address_line1']); ?></p>
                                    <?php if (!empty($order['address_line2'])): ?>
                                        <p><?php echo htmlspecialchars($order['address_line2']); ?></p>
                                    <?php endif; ?>
                                    <p>
                                        <?php echo htmlspecialchars($order['city']); ?>,
                                        <?php echo htmlspecialchars($order['state']); ?>,
                                        <?php echo htmlspecialchars($order['postal_code']); ?>
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <h4 class="order-info-section-title">Order Items</h4>
                    <table class="order-items-table">
                        <thead>
                            <tr>
                                <th>Product</th>
                                <th>Price</th>
                                <th>Quantity</th>
                                <th>Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($order_items as $item): ?>
                                <tr>
                                    <td>
                                        <div class="order-item-product">
                                            <div class="order-item-image">
                                                <?php
                                                $productId = isset($item['product_id']) ? $item['product_id'] : null;
                                                $image_src = ($productId && isset($product_images[$productId]))
                                                    ? $product_images[$productId]
                                                    : 'images/placeholder.jpg';
                                                ?>
                                                <img src="<?php echo htmlspecialchars($image_src); ?>"
                                                    alt="Product Image" />

                                            </div>
                                            <span
                                                class="order-item-name"><?php echo htmlspecialchars($item['product_name']); ?></span>
                                        </div>
                                    </td>
                                    <td>₱<?php echo number_format($item['price'], 2); ?></td>
                                    <td><?php echo $item['quantity']; ?></td>
                                    <td>₱<?php echo number_format($item['total'], 2); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>

                    <div class="order-summary">
                        <div class="order-summary-row">
                            <span class="order-summary-label">Subtotal</span>
                            <span
                                class="order-summary-value">₱<?php echo number_format($order['subtotal'], 2); ?></span>
                        </div>
                        <div class="order-summary-row">
                            <span class="order-summary-label">Shipping</span>
                            <span class="order-summary-value">₱<?php echo number_format($shipping_fee, 2); ?></span>
                        </div>

                        <div class="order-summary-total">
                            <span class="order-summary-total-label">Total</span>
                            <span
                                class="order-summary-total-value">₱<?php echo number_format($order['total'], 2); ?></span>
                        </div>
                    </div>
                </div>

                <div class="action-buttons">
                    <a href="home.php" class="action-button primary">
                        <i class="fas fa-shopping-bag"></i>
                        Continue Shopping
                    </a>
                    <a href="account.php?tab=orders" class="action-button secondary">
                        <i class="fas fa-list"></i>
                        View My Orders
                    </a>
                </div>
            </div>
        </main>

        <?php include 'includes/footer.php'; ?>
    </div>
</body>

</html>