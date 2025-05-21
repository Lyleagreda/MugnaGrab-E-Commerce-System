<?php
session_start();
require 'db.php'; // Include your database connection
include 'data/products.php'; // Include product data to get images

// Check if the user is logged in
if (!isset($_SESSION['logged_in']) || !isset($_SESSION['user_email'])) {
    // Display JavaScript alert if not logged in
    echo "<script>alert('You are not logged in. Please log in to access your cart.');</script>";
    // Redirect to login page
    echo "<script>window.location.href = 'index.php';</script>";
    exit;
}

// Get user ID from session
$user_email = $_SESSION['user_email'];
$stmt_user = $conn->prepare("SELECT id FROM users WHERE email = :email");
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

// AJAX handler for updating cart quantity
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_update_quantity'])) {
    $cart_id = intval($_POST['cart_id']);
    $new_quantity = intval($_POST['quantity']);
    $response = ['success' => false];
    
    if ($new_quantity > 0) {
        try {
            // Update quantity
            $stmt_update = $conn->prepare("UPDATE cart SET quantity = :quantity WHERE cart_id = :cart_id AND user_id = :user_id");
            $stmt_update->bindParam(':quantity', $new_quantity, PDO::PARAM_INT);
            $stmt_update->bindParam(':cart_id', $cart_id, PDO::PARAM_INT);
            $stmt_update->bindParam(':user_id', $user_id, PDO::PARAM_INT);
            $stmt_update->execute();
            
            // Get updated price
            $stmt_price = $conn->prepare("SELECT (price * quantity) AS total_price FROM cart WHERE cart_id = :cart_id");
            $stmt_price->bindParam(':cart_id', $cart_id, PDO::PARAM_INT);
            $stmt_price->execute();
            $item_data = $stmt_price->fetch(PDO::FETCH_ASSOC);
            
            $response = [
                'success' => true,
                'total_price' => $item_data['total_price'],
                'formatted_price' => number_format($item_data['total_price'], 2)
            ];
        } catch (PDOException $e) {
            $response['error'] = $e->getMessage();
        }
    }
    
    header('Content-Type: application/json');
    echo json_encode($response);
    exit;
}

// Fetch all items in the user's cart
$stmt_cart = $conn->prepare("SELECT c.cart_id, c.product_id, c.product_name, c.price, c.quantity, (c.price * c.quantity) AS total_price 
                             FROM cart c WHERE c.user_id = :user_id");
$stmt_cart->bindParam(':user_id', $user_id, PDO::PARAM_INT);
$stmt_cart->execute();

// Check if there are any items in the cart
if ($stmt_cart->rowCount() > 0) {
    $cart_items = $stmt_cart->fetchAll(PDO::FETCH_ASSOC);
} else {
    $cart_items = [];
}

// Create a lookup array for product images
$product_images = [];
foreach ($products as $product) {
    $product_images[$product['id']] = $product['image'];
}

// Store selected cart items in session if form is submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['proceed_to_checkout'])) {
    if (isset($_POST['selected_items']) && is_array($_POST['selected_items'])) {
        $_SESSION['selected_cart_items'] = $_POST['selected_items']; // Store selected items in session
        header('Location: checkout.php'); // Redirect to checkout page
        exit;
    } else {
        $checkout_error = "Please select at least one item to checkout.";
    }
}


// Handle cart updates (quantity change or item removal)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_quantity'])) {
        $cart_id = intval($_POST['cart_id']);
        $new_quantity = intval($_POST['quantity']);
        
        if ($new_quantity > 0) {
            // Update quantity
            $stmt_update = $conn->prepare("UPDATE cart SET quantity = :quantity WHERE cart_id = :cart_id AND user_id = :user_id");
            $stmt_update->bindParam(':quantity', $new_quantity, PDO::PARAM_INT);
            $stmt_update->bindParam(':cart_id', $cart_id, PDO::PARAM_INT);
            $stmt_update->bindParam(':user_id', $user_id, PDO::PARAM_INT);
            $stmt_update->execute();
            
            // Redirect back to the view cart page after update
            header('Location: view_cart.php');
            exit;
        }
    } elseif (isset($_POST['remove_item'])) {
        $cart_id = intval($_POST['cart_id']);
        
        // Remove item from cart
        $stmt_remove = $conn->prepare("DELETE FROM cart WHERE cart_id = :cart_id AND user_id = :user_id");
        $stmt_remove->bindParam(':cart_id', $cart_id, PDO::PARAM_INT);
        $stmt_remove->bindParam(':user_id', $user_id, PDO::PARAM_INT);
        $stmt_remove->execute();
        
        // Redirect back to the view cart page after remove
        header('Location: view_cart.php');
        exit;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Your Cart - Mugna</title>
    <link rel="stylesheet" href="css/styles.css">
    <link rel="shortcut icon" href="./images/mugna-icon.png" type="image/x-icon">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* Cart Page Styles */
        .page-header {
            background-color: #f1f5f9;
            padding: 2rem 0;
            margin-bottom: 2rem;
            border-radius: 12px;
            text-align: center;
        }
        
        .page-title {
            font-size: 2rem;
            font-weight: 700;
            color: #1e3a8a;
            margin-bottom: 0.5rem;
        }
        
        .breadcrumb {
            display: flex;
            align-items: center;
            justify-content: center;
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
        
        .cart-layout {
            display: flex;
            flex-direction: row;
            gap: 2rem;
            margin-bottom: 3rem;
        }
        
        .cart-items {
            flex: 2;
        }
        
        .cart-summary {
            flex: 1;
        }
        
        .cart-container {
            background-color: white;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
            padding: 1.5rem;
            margin-bottom: 2rem;
        }
        
        .cart-empty {
            text-align: center;
            padding: 3rem 0;
        }
        
        .cart-empty-icon {
            font-size: 4rem;
            color: #e2e8f0;
            margin-bottom: 1rem;
        }
        
        .cart-empty-text {
            font-size: 1.25rem;
            color: #64748b;
            margin-bottom: 1.5rem;
        }
        
        .cart-empty-button {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            padding: 0.75rem 1.5rem;
            background-color: #2563eb;
            color: white;
            border-radius: 8px;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        
        .cart-empty-button:hover {
            background-color: #1d4ed8;
        }
        
        .cart-item {
            display: flex;
            align-items: center;
            padding: 1.5rem 0;
            border-bottom: 1px solid #e2e8f0;
        }
        
        .cart-item:last-child {
            border-bottom: none;
        }
        
        .cart-item-checkbox {
            margin-right: 1rem;
        }
        
        .cart-checkbox {
            width: 20px;
            height: 20px;
            cursor: pointer;
            accent-color: #2563eb;
        }
        
        .cart-item-image {
            width: 100px;
            height: 100px;
            border-radius: 8px;
            overflow: hidden;
            background-color: #f8fafc;
            margin-right: 1.5rem;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .cart-item-image img {
            max-width: 90%;
            max-height: 90%;
            object-fit: contain;
        }
        
        .cart-item-details {
            flex: 1;
        }
        
        .cart-item-name {
            font-weight: 600;
            color: #1e3a8a;
            margin-bottom: 0.5rem;
            font-size: 1.1rem;
        }
        
        .cart-item-price {
            color: #64748b;
            margin-bottom: 0.5rem;
            font-size: 0.95rem;
        }
        
        .cart-item-actions {
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        
        .quantity-control {
            display: flex;
            align-items: center;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            overflow: hidden;
        }
        
        .quantity-btn {
            width: 32px;
            height: 32px;
            display: flex;
            align-items: center;
            justify-content: center;
            background-color: #f1f5f9;
            color: #64748b;
            border: none;
            cursor: pointer;
            transition: all 0.2s ease;
        }
        
        .quantity-btn:hover {
            background-color: #e2e8f0;
            color: #1e3a8a;
        }
        
        .quantity-input {
            width: 40px;
            height: 32px;
            text-align: center;
            border: none;
            border-left: 1px solid #e2e8f0;
            border-right: 1px solid #e2e8f0;
            font-size: 0.875rem;
        }
        
        .quantity-input::-webkit-inner-spin-button,
        .quantity-input::-webkit-outer-spin-button {
            -webkit-appearance: none;
            margin: 0;
        }
        
        .cart-action-btn {
            padding: 0.5rem;
            border-radius: 6px;
            background-color: #f1f5f9;
            color: #64748b;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.3s ease;
            border: none;
        }
        
        .cart-action-btn:hover {
            background-color: #e2e8f0;
            color: #1e3a8a;
        }
        
        .cart-action-btn.remove {
            color: #ef4444;
        }
        
        .cart-action-btn.remove:hover {
            background-color: #fee2e2;
        }
        
        .cart-item-total {
            font-weight: 600;
            color: #1e3a8a;
            font-size: 1.1rem;
            text-align: right;
            min-width: 100px;
        }
        
        .cart-summary-container {
            background-color: white;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
            padding: 1.5rem;
            position: sticky;
            top: 2rem;
        }
        
        .cart-summary-title {
            font-size: 1.25rem;
            font-weight: 600;
            color: #1e3a8a;
            margin-bottom: 1.5rem;
            padding-bottom: 0.75rem;
            border-bottom: 1px solid #e2e8f0;
        }
        
        .cart-summary-items {
            margin-bottom: 1.5rem;
            max-height: 200px;
            overflow-y: auto;
        }
        
        .cart-summary-item {
            display: flex;
            justify-content: space-between;
            margin-bottom: 0.75rem;
            padding-bottom: 0.75rem;
            border-bottom: 1px dashed #e2e8f0;
            color: #64748b;
            font-size: 0.9rem;
        }
        
        .cart-summary-item:last-child {
            border-bottom: none;
        }
        
        .cart-summary-item-name {
            max-width: 70%;
        }
        
        .cart-summary-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 1rem;
            color: #64748b;
        }
        
        .cart-summary-row.total {
            font-weight: 600;
            color: #1e3a8a;
            font-size: 1.1rem;
            padding-top: 1rem;
            margin-top: 1rem;
            border-top: 1px solid #e2e8f0;
        }
        
        .checkout-button {
            width: 100%;
            padding: 1rem;
            background-color: #2563eb;
            color: white;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            margin-top: 1.5rem;
        }
        
        .checkout-button:hover {
            background-color: #1d4ed8;
        }
        
        .checkout-button:disabled {
            background-color: #94a3b8;
            cursor: not-allowed;
        }
        
        .continue-shopping {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            margin-top: 1rem;
            color: #2563eb;
            font-weight: 500;
            transition: all 0.3s ease;
        }
        
        .continue-shopping:hover {
            color: #1d4ed8;
        }
        
        .cart-error {
            background-color: #fee2e2;
            color: #b91c1c;
            padding: 0.75rem;
            border-radius: 8px;
            margin-bottom: 1rem;
        }
        
        .select-all-container {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin-bottom: 1rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid #e2e8f0;
        }
        
        .select-all-label {
            font-weight: 500;
            color: #1e3a8a;
            cursor: pointer;
        }
        
        /* Loading spinner */
        .spinner {
            display: inline-block;
            width: 16px;
            height: 16px;
            border: 2px solid rgba(0, 0, 0, 0.1);
            border-left-color: #2563eb;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }
        
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
        
        /* Responsive Adjustments */
        @media (max-width: 992px) {
            .cart-layout {
                flex-direction: column;
            }
            
            .cart-summary-container {
                position: static;
            }
        }
        
        @media (max-width: 768px) {
            .cart-item {
                flex-direction: column;
                align-items: flex-start;
                gap: 1rem;
            }
            
            .cart-item-checkbox {
                align-self: flex-start;
                margin-bottom: 0.5rem;
            }
            
            .cart-item-image {
                margin-right: 0;
            }
            
            .cart-item-actions {
                width: 100%;
                justify-content: space-between;
            }
            
            .cart-item-total {
                text-align: left;
                margin-top: 0.5rem;
            }
        }
    </style>
</head>
<body>
    <div class="site-container">
        <?php include 'includes/header.php'; ?>
        
        <main class="main-content">
            <div class="container">
                
                <?php if (isset($checkout_error)): ?>
                    <div class="cart-error">
                        <?php echo $checkout_error; ?>
                    </div>
                <?php endif; ?>
                
                <?php if (empty($cart_items)): ?>
                    <!-- Empty Cart -->
                    <div class="cart-container">
                        <div class="cart-empty">
                            <div class="cart-empty-icon">
                                <i class="fas fa-shopping-cart"></i>
                            </div>
                            <h3 class="cart-empty-text">Your cart is empty</h3>
                            <a href="products.php" class="cart-empty-button">
                                <i class="fas fa-arrow-left"></i>
                                Continue Shopping
                            </a>
                        </div>
                    </div>
                <?php else: ?>
                    <!-- Cart Content -->
                    <form method="post" action="view_cart.php" id="checkout-form">
                        <div class="cart-layout">
                            <div class="cart-items">
                                <div class="cart-container">
                                    <div class="select-all-container">
                                        <input type="checkbox" id="select-all" class="cart-checkbox">
                                        <label for="select-all" class="select-all-label">Select All Items</label>
                                    </div>
                                    
                                    <?php foreach ($cart_items as $item): ?>
                                        <div class="cart-item">
                                            <div class="cart-item-checkbox">
                                                <input type="checkbox" name="selected_items[]" value="<?php echo $item['cart_id']; ?>" class="cart-checkbox item-checkbox" data-price="<?php echo $item['total_price']; ?>" data-name="<?php echo htmlspecialchars($item['product_name']); ?>">
                                            </div>
                                            <div class="cart-item-image">
                                                <?php 
                                                // Get product image from the products array
                                                $image_src = isset($product_images[$item['product_id']]) ? $product_images[$item['product_id']] : 'images/placeholder.jpg';
                                                ?>
                                                <img src="<?php echo htmlspecialchars($image_src); ?>" alt="<?php echo htmlspecialchars($item['product_name']); ?>">
                                            </div>
                                            <div class="cart-item-details">
                                                <h3 class="cart-item-name"><?php echo htmlspecialchars($item['product_name']); ?></h3>
                                                <p class="cart-item-price">₱<?php echo number_format($item['price'], 2); ?> × <span class="item-quantity-display"><?php echo $item['quantity']; ?></span></p>
                                                <div class="cart-item-actions">
                                                    <div class="quantity-control" data-cart-id="<?php echo $item['cart_id']; ?>" data-price="<?php echo $item['price']; ?>">
                                                        <button type="button" class="quantity-btn decrease-btn">
                                                            <i class="fas fa-minus"></i>
                                                        </button>
                                                        <input type="number" class="quantity-input" value="<?php echo $item['quantity']; ?>" min="1" readonly>
                                                        <button type="button" class="quantity-btn increase-btn">
                                                            <i class="fas fa-plus"></i>
                                                        </button>
                                                    </div>
                                                    <form method="post" action="view_cart.php" class="cart-remove-form">
                                                        <input type="hidden" name="cart_id" value="<?php echo $item['cart_id']; ?>">
                                                        <button type="submit" name="remove_item" class="cart-action-btn remove" title="Remove item">
                                                            <i class="fas fa-trash-alt"></i>
                                                        </button>
                                                    </form>
                                                </div>
                                            </div>
                                            <div class="cart-item-total" id="total-<?php echo $item['cart_id']; ?>">
                                                ₱<?php echo number_format($item['total_price'], 2); ?>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            
                            <div class="cart-summary">
                                <div class="cart-summary-container">
                                    <h3 class="cart-summary-title">Order Summary</h3>
                                    
                                    <div class="cart-summary-items" id="selected-items-container">
                                        <!-- Selected items will be populated by JavaScript -->
                                        <p id="empty-selection-message">No items selected</p>
                                    </div>
                                    
                                    <div class="cart-summary-row total">
                                        <span>Total</span>
                                        <span id="total-amount">₱0.00</span>
                                    </div>
                                    
                                    <button type="submit" name="proceed_to_checkout" class="checkout-button" id="checkout-button" disabled>
                                        <i class="fas fa-lock"></i>
                                        Proceed to Checkout
                                    </button>
                                    
                                    <a href="products.php" class="continue-shopping">
                                        <i class="fas fa-arrow-left"></i>
                                        Continue Shopping
                                    </a>
                                </div>
                            </div>
                        </div>
                    </form>
                <?php endif; ?>
            </div>
        </main>
        
        <?php include 'includes/footer.php'; ?>
    </div>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const selectAllCheckbox = document.getElementById('select-all');
            const itemCheckboxes = document.querySelectorAll('.item-checkbox');
            const selectedItemsContainer = document.getElementById('selected-items-container');
            const emptySelectionMessage = document.getElementById('empty-selection-message');
            const totalAmount = document.getElementById('total-amount');
            const checkoutButton = document.getElementById('checkout-button');
            const quantityControls = document.querySelectorAll('.quantity-control');
            
            // Function to update order summary
            function updateOrderSummary() {
                let total = 0;
                let hasSelectedItems = false;
                let summaryHTML = '';
                
                itemCheckboxes.forEach(checkbox => {
                    if (checkbox.checked) {
                        hasSelectedItems = true;
                        const cartId = checkbox.value;
                        const totalElement = document.getElementById(`total-${cartId}`);
                        const priceText = totalElement.textContent.replace('₱', '').replace(',', '');
                        const price = parseFloat(priceText);
                        const name = checkbox.dataset.name;
                        
                        total += price;
                        
                        summaryHTML += `
                            <div class="cart-summary-item">
                                <span class="cart-summary-item-name">${name}</span>
                                <span>₱${price.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2})}</span>
                            </div>
                        `;
                    }
                });
                
                // Update the DOM
                if (hasSelectedItems) {
                    emptySelectionMessage.style.display = 'none';
                    selectedItemsContainer.innerHTML = summaryHTML;
                    checkoutButton.disabled = false;
                } else {
                    emptySelectionMessage.style.display = 'block';
                    selectedItemsContainer.innerHTML = emptySelectionMessage.outerHTML;
                    checkoutButton.disabled = true;
                }
                
                totalAmount.textContent = `₱${total.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2})}`;
            }
            
            // Event listener for select all checkbox
            if (selectAllCheckbox) {
                selectAllCheckbox.addEventListener('change', function() {
                    itemCheckboxes.forEach(checkbox => {
                        checkbox.checked = this.checked;
                    });
                    updateOrderSummary();
                });
            }
            
            // Event listeners for individual checkboxes
            itemCheckboxes.forEach(checkbox => {
                checkbox.addEventListener('change', function() {
                    // If any checkbox is unchecked, uncheck the "select all" checkbox
                    if (!this.checked) {
                        selectAllCheckbox.checked = false;
                    }
                    
                    // Check if all checkboxes are checked
                    const allChecked = Array.from(itemCheckboxes).every(cb => cb.checked);
                    if (allChecked) {
                        selectAllCheckbox.checked = true;
                    }
                    
                    updateOrderSummary();
                });
            });
            
            // Quantity control functionality
            quantityControls.forEach(control => {
                const decreaseBtn = control.querySelector('.decrease-btn');
                const increaseBtn = control.querySelector('.increase-btn');
                const input = control.querySelector('.quantity-input');
                const cartId = control.dataset.cartId;
                const unitPrice = parseFloat(control.dataset.price);
                
                // Function to update quantity via AJAX
                function updateQuantity(newQuantity) {
                    // Show loading indicator
                    const totalElement = document.getElementById(`total-${cartId}`);
                    const originalText = totalElement.textContent;
                    totalElement.innerHTML = '<div class="spinner"></div>';
                    
                    // Update quantity display immediately
                    const quantityDisplay = control.closest('.cart-item').querySelector('.item-quantity-display');
                    quantityDisplay.textContent = newQuantity;
                    
                    // Send AJAX request
                    const formData = new FormData();
                    formData.append('ajax_update_quantity', '1');
                    formData.append('cart_id', cartId);
                    formData.append('quantity', newQuantity);
                    
                    fetch('view_cart.php', {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            // Update the total price display
                            totalElement.textContent = `₱${data.formatted_price}`;
                            
                            // Update the checkbox data-price attribute
                            const checkbox = document.querySelector(`.item-checkbox[value="${cartId}"]`);
                            if (checkbox) {
                                checkbox.dataset.price = data.total_price;
                                
                                // If this item is checked, update the order summary
                                if (checkbox.checked) {
                                    updateOrderSummary();
                                }
                            }
                        } else {
                            // Restore original text if there was an error
                            totalElement.textContent = originalText;
                            console.error('Error updating quantity:', data.error);
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        totalElement.textContent = originalText;
                    });
                }
                
                // Decrease button event
                decreaseBtn.addEventListener('click', function() {
                    let currentValue = parseInt(input.value);
                    if (currentValue > 1) {
                        currentValue--;
                        input.value = currentValue;
                        updateQuantity(currentValue);
                    }
                });
                
                // Increase button event
                increaseBtn.addEventListener('click', function() {
                    let currentValue = parseInt(input.value);
                    currentValue++;
                    input.value = currentValue;
                    updateQuantity(currentValue);
                });
            });
            
            // Form validation
            const checkoutForm = document.getElementById('checkout-form');
            if (checkoutForm) {
                checkoutForm.addEventListener('submit', function(e) {
                    if (e.submitter && e.submitter.name === 'proceed_to_checkout') {
                        const hasSelectedItems = Array.from(itemCheckboxes).some(cb => cb.checked);
                        if (!hasSelectedItems) {
                            e.preventDefault();
                            alert('Please select at least one item to checkout.');
                            return false;
                        }
                    }
                });
            }
            
            // Initialize order summary
            updateOrderSummary();
        });
    </script>
</body>
</html>
