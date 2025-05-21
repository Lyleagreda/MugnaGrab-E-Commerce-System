<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header('Location: index.php');
    exit;
}

// Include database connection
include 'config.php';

// Include common functions
include 'includes/functions.php';

// Include cart functions
include 'includes/cart_functions.php';

// Get user ID from session
$userId = $_SESSION['user_id'] ?? 0;

// Include product data
include 'data/products.php';

// Handle AJAX requests
if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
    $response = ['success' => false, 'message' => 'Invalid request'];

    try {
        $pdo = new PDO("mysql:host=$servername;dbname=$dbname", $username, $password);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        $cart = getUserCart($userId, $pdo);

        // Add to cart
        if (isset($_POST['action']) && $_POST['action'] === 'add_to_cart') {
            $productId = isset($_POST['product_id']) ? intval($_POST['product_id']) : 0;
            $quantity = isset($_POST['quantity']) ? intval($_POST['quantity']) : 1;

            if ($productId > 0) {
                $response = addToCart($cart['id'], $productId, $quantity, $pdo, $products);
            }
        }

        // Update cart item quantity
        elseif (isset($_POST['action']) && $_POST['action'] === 'update_quantity') {
            $cartItemId = isset($_POST['cart_item_id']) ? intval($_POST['cart_item_id']) : 0;
            $quantity = isset($_POST['quantity']) ? intval($_POST['quantity']) : 1;

            if ($cartItemId > 0) {
                $response = updateCartItemQuantity($cartItemId, $quantity, $pdo, $products);
            }
        }

        // Remove cart item
        elseif (isset($_POST['action']) && $_POST['action'] === 'remove_item') {
            $cartItemId = isset($_POST['cart_item_id']) ? intval($_POST['cart_item_id']) : 0;

            if ($cartItemId > 0) {
                $response = removeCartItem($cartItemId, $pdo);
            }
        }
    } catch (PDOException $e) {
        error_log("Database error: " . $e->getMessage());
        $response = ['success' => false, 'message' => 'Database error occurred'];
    }

    // Return JSON response
    header('Content-Type: application/json');
    echo json_encode($response);
    exit;
}

// Get user's cart
try {
    $pdo = new PDO("mysql:host=$servername;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $cart = getUserCart($userId, $pdo);
    $cartItems = [];
    $cartTotal = 0;

    if ($cart) {
        $cartItems = getCartItems($cart['id'], $pdo, $products);
        $cartTotal = getCartTotal($cart['id'], $pdo, $products);
    }
} catch (PDOException $e) {
    error_log("Database error: " . $e->getMessage());
    // Handle the exception, show an error message
    $error = "An error occurred while accessing the database. Please try again later.";
}

// Helper function to format price if not available in functions.php
if (!function_exists('formatPrice')) {
    function formatPrice($price) {
        return '₱' . number_format($price, 2);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Shopping Cart - Mugna</title>
    <link rel="stylesheet" href="css/styles.css">
    <link rel="shortcut icon" href="./images/mugna-icon.png" type="image/x-icon">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* Cart Page Styles */
        .cart-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 2rem 1rem;
        }

        .cart-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
        }

        .cart-title {
            font-size: 2rem;
            font-weight: 700;
            color: #1e3a8a;
            margin-bottom: 0.5rem;
        }

        .continue-shopping {
            display: flex;
            align-items: center;
            color: #2563eb;
            font-weight: 500;
        }

        .continue-shopping i {
            margin-right: 0.5rem;
        }

        .cart-empty {
            text-align: center;
            padding: 3rem;
            background-color: white;
            border-radius: 0.5rem;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
        }

        .cart-empty i {
            font-size: 4rem;
            color: #94a3b8;
            margin-bottom: 1rem;
        }

        .cart-empty h3 {
            font-size: 1.5rem;
            color: #1e3a8a;
            margin-bottom: 1rem;
        }

        .cart-empty p {
            color: #64748b;
            margin-bottom: 1.5rem;
        }
        
        .cart-content {
            display: grid;
            grid-template-columns: 1fr 350px;
            gap: 2rem;
        }

        @media (max-width: 992px) {
            .cart-content {
                grid-template-columns: 1fr;
            }
        }

        .cart-items {
            background-color: white;
            border-radius: 0.5rem;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
            overflow: hidden;
        }

        .cart-items-header {
            display: grid;
            grid-template-columns: 3fr 1fr 1fr 1fr auto;
            padding: 1rem 1.5rem;
            background-color: #f8fafc;
            border-bottom: 1px solid #e2e8f0;
        }

        .cart-items-header span {
            font-weight: 600;
            color: #1e3a8a;
        }

        .cart-item {
            display: grid;
            grid-template-columns: 3fr 1fr 1fr 1fr auto;
            padding: 1.5rem;
            border-bottom: 1px solid #e2e8f0;
            align-items: center;

        }

        .cart-item:last-child {
            border-bottom: none;
        }

        .cart-item-info {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .cart-item-image {
            width: 80px;
            height: 80px;
            border-radius: 0.25rem;
            overflow: hidden;
            background-color: #f8fafc;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .cart-item-image img {
            max-width: 90%;
            max-height: 90%;
            object-fit: contain;
        }

        .cart-item-details h4 {
            font-weight: 600;
            color: #1e3a8a;
            margin-bottom: 0.25rem;
        }

        .cart-item-details p {
            color: #64748b;
            font-size: 0.875rem;
        }

        .cart-item-price {
            font-weight: 600;
            color: #1e3a8a;
        }

        .cart-item-quantity {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .quantity-btn {
            width: 30px;
            height: 30px;
            border-radius: 50%;
            background-color: #f1f5f9;
            border: none;
            color: #1e3a8a;
            font-weight: 600;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s ease;
        }

        .quantity-btn:hover {
            background-color: #e2e8f0;
        }

        .quantity-input {
            width: 40px;
            height: 30px;
            border: 1px solid #e2e8f0;
            border-radius: 0.25rem;
            text-align: center;
            font-weight: 600;
            color: #1e3a8a;
        }

        .cart-item-subtotal {
            font-weight: 600;
            color: #1e3a8a;
        }

        .cart-item-remove {
            color: #ef4444;
            background: none;
            border: none;
            cursor: pointer;
            font-size: 1.25rem;
            transition: all 0.3s ease;
        }

        .cart-item-remove:hover {
            color: #b91c1c;
        }

        .cart-summary {
            background-color: white;
            border-radius: 0.5rem;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
            padding: 1.5rem;
            height: fit-content;
            position: sticky;
            top: 2rem;
        }

        .cart-summary h3 {
            font-size: 1.25rem;
            font-weight: 600;
            color: #1e3a8a;
            margin-bottom: 1.5rem;
            padding-bottom: 0.5rem;
            border-bottom: 1px solid #e2e8f0;
        }

        .summary-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 1rem;
        }

        .summary-row span {
            color: #64748b;
        }

        .summary-row.total {
            font-weight: 600;
            color: #1e3a8a;
            font-size: 1.125rem;
            padding-top: 1rem;
            margin-top: 1rem;
            border-top: 1px solid #e2e8f0;
        }

        .checkout-btn {
            width: 100%;
            padding: 0.75rem;
            background-color: #2563eb;
            color: white;
            border: none;
            border-radius: 0.5rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            margin-top: 1.5rem;
        }

        .checkout-btn:hover {
            background-color: #1d4ed8;
        }

        .checkout-btn:disabled {
            background-color: #94a3b8;
            cursor: not-allowed;
        }

        /* Mobile Responsive */
        @media (max-width: 768px) {
            .cart-items-header {
                display: none;
            }

            .cart-item {
                grid-template-columns: 1fr;
                gap: 1rem;
            }

            .cart-item-info {
                grid-column: 1 / -1;
            }

            .cart-item-actions {
                display: grid;
                grid-template-columns: 1fr 1fr 1fr auto;
                align-items: center;
                grid-column: 1 / -1;
            }

            .cart-item-actions::before {
                content: "Price:";
                font-weight: 600;
                color: #1e3a8a;
            }

            .cart-item-actions::after {
                content: "Subtotal:";
                font-weight: 600;
                color: #1e3a8a;
                grid-column: 3;
            }

            .cart-item-quantity::before {
                content: "Qty:";
                font-weight: 600;
                color: #1e3a8a;
            }
        }
   </style>
</head>
<body>
   <div class="site-container">
       <?php include 'includes/header.php'; ?>
       
       <main class="main-content">
           <div class="cart-container">
               <div class="cart-header">
                   <h1 class="cart-title">Shopping Cart</h1>
                   <a href="products.php" class="continue-shopping">
                       <i class="fas fa-arrow-left"></i> Continue Shopping
                   </a>
               </div>
               
               <?php if (isset($error)): ?>
                   <div class="alert alert-danger">
                       <?php echo $error; ?>
                   </div>
               <?php elseif (empty($cartItems)): ?>
                   <div class="cart-empty">
                       <i class="fas fa-shopping-cart"></i>
                       <h3>Your cart is empty</h3>
                       <p>Looks like you haven't added any products to your cart yet.</p>
                       <a href="products.php" class="btn btn-primary">Start Shopping</a>
                   </div>
               <?php else: ?>
                   <div class="cart-content">
                       <div class="cart-items">
                           <div class="cart-items-header">
                               <span>Product</span>
                               <span>Price</span>
                               <span>Quantity</span>
                               <span>Subtotal</span>
                               <span></span>
                           </div>
                           
                           <?php foreach ($cartItems as $item): ?>
                               <div class="cart-item" data-id="<?php echo $item['id']; ?>">
                                   <div class="cart-item-info">
                                       <div class="cart-item-image">
                                           <img src="<?php echo $item['image']; ?>" alt="<?php echo $item['name']; ?>">
                                       </div>
                                       <div class="cart-item-details">
                                           <h4><?php echo $item['name']; ?></h4>
                                           <p>Available: <?php echo $item['stock']; ?> in stock</p>
                                       </div>
                                   </div>
                                   
                                   <div class="cart-item-price">
                                       <?php echo formatPrice($item['price']); ?>
                                   </div>
                                   
                                   <div class="cart-item-quantity">
                                       <button class="quantity-btn decrease-qty" data-id="<?php echo $item['id']; ?>">-</button>
                                       <input type="number" class="quantity-input" value="<?php echo $item['quantity']; ?>" min="1" max="<?php echo $item['stock']; ?>" data-id="<?php echo $item['id']; ?>">
                                       <button class="quantity-btn increase-qty" data-id="<?php echo $item['id']; ?>">+</button>
                                   </div>
                                   
                                   <div class="cart-item-subtotal">
                                       <?php echo formatPrice($item['price'] * $item['quantity']); ?>
                                   </div>
                                   
                                   <button class="cart-item-remove" data-id="<?php echo $item['id']; ?>">
                                       <i class="fas fa-trash"></i>
                                   </button>
                               </div>
                           <?php endforeach; ?>
                       </div>
                       
                       <div class="cart-summary">
                           <h3>Order Summary</h3>
                           
                           <div class="summary-row">
                               <span>Subtotal</span>
                               <span id="cart-subtotal"><?php echo formatPrice($cartTotal); ?></span>
                           </div>
                           
                           <div class="summary-row">
                               <span>Shipping</span>
                               <span>Calculated at checkout</span>
                           </div>
                           
                           <div class="summary-row total">
                               <span>Total</span>
                               <span id="cart-total"><?php echo formatPrice($cartTotal); ?></span>
                           </div>
                           
                           <a href="checkout.php" class="checkout-btn" <?php echo $cartTotal <= 0 ? 'disabled' : ''; ?>>
                               <i class="fas fa-lock"></i> Proceed to Checkout
                           </a>
                       </div>
                   </div>
               <?php endif; ?>
           </div>
       </main>
       
       <?php include 'includes/footer.php'; ?>
   </div>

   <script>
       document.addEventListener('DOMContentLoaded', function() {
           // Quantity buttons
           const decreaseBtns = document.querySelectorAll('.decrease-qty');
           const increaseBtns = document.querySelectorAll('.increase-qty');
           const quantityInputs = document.querySelectorAll('.quantity-input');
           const removeButtons = document.querySelectorAll('.cart-item-remove');
           
           // Decrease quantity
           decreaseBtns.forEach(btn => {
               btn.addEventListener('click', function() {
                   const id = this.getAttribute('data-id');
                   const input = document.querySelector(`.quantity-input[data-id="${id}"]`);
                   let value = parseInt(input.value);
                   
                   if (value > 1) {
                       value--;
                       input.value = value;
                       updateCartItem(id, value);
                   }
               });
           });
           
           // Increase quantity
           increaseBtns.forEach(btn => {
               btn.addEventListener('click', function() {
                   const id = this.getAttribute('data-id');
                   const input = document.querySelector(`.quantity-input[data-id="${id}"]`);
                   let value = parseInt(input.value);
                   const max = parseInt(input.getAttribute('max'));
                   
                   if (value < max) {
                       value++;
                       input.value = value;
                       updateCartItem(id, value);
                   }
               });
           });
           
           // Quantity input change
           quantityInputs.forEach(input => {
               input.addEventListener('change', function() {
                   const id = this.getAttribute('data-id');
                   let value = parseInt(this.value);
                   const max = parseInt(this.getAttribute('max'));
                   
                   if (isNaN(value) || value < 1) {
                       value = 1;
                   } else if (value > max) {
                       value = max;
                   }
                   
                   this.value = value;
                   updateCartItem(id, value);
               });
           });
           
           // Remove item
           removeButtons.forEach(button => {
               button.addEventListener('click', function() {
                   const id = this.getAttribute('data-id');
                   removeCartItem(id);
               });
           });
           
           // Update cart item
           function updateCartItem(id, quantity) {
               fetch('cart.php', {
                   method: 'POST',
                   headers: {
                       'Content-Type': 'application/x-www-form-urlencoded',
                       'X-Requested-With': 'XMLHttpRequest'
                   },
                   body: `action=update_quantity&cart_item_id=${id}&quantity=${quantity}`
               })
               .then(response => response.json())
               .then(data => {
                   if (data.success) {
                       updateCartDisplay();
                   } else {
                       alert(data.message);
                   }
               })
               .catch(error => {
                   console.error('Error:', error);
               });
           }
           
           // Remove cart item
           function removeCartItem(id) {
               if (confirm('Are you sure you want to remove this item from your cart?')) {
                   fetch('cart.php', {
                       method: 'POST',
                       headers: {
                           'Content-Type': 'application/x-www-form-urlencoded',
                           'X-Requested-With': 'XMLHttpRequest'
                       },
                       body: `action=remove_item&cart_item_id=${id}`
                   })
                   .then(response => response.json())
                   .then(data => {
                       if (data.success) {
                           const cartItem = document.querySelector(`.cart-item[data-id="${id}"]`);
                           cartItem.remove();
                           updateCartDisplay();
                           
                           // Reload if cart is empty
                           const cartItems = document.querySelectorAll('.cart-item');
                           if (cartItems.length === 0) {
                               location.reload();
                           }
                       } else {
                           alert(data.message);
                       }
                   })
                   .catch(error => {
                       console.error('Error:', error);
                   });
               }
           }
           
           // Update cart display (recalculate subtotals and total)
           function updateCartDisplay() {
               let total = 0;
               
               document.querySelectorAll('.cart-item').forEach(item => {
                   const id = item.getAttribute('data-id');
                   const price = parseFloat(item.querySelector('.cart-item-price').textContent.replace('₱', '').replace(',', ''));
                   const quantity = parseInt(item.querySelector('.quantity-input').value);
                   const subtotal = price * quantity;
                   
                   item.querySelector('.cart-item-subtotal').textContent = `₱${subtotal.toFixed(2).replace(/\d(?=(\d{3})+\.)/g, '$&,')}`;
                   total += subtotal;
               });
               
               document.getElementById('cart-subtotal').textContent = `₱${total.toFixed(2).replace(/\d(?=(\d{3})+\.)/g, '$&,')}`;
               document.getElementById('cart-total').textContent = `₱${total.toFixed(2).replace(/\d(?=(\d{3})+\.)/g, '$&,')}`;
               
               // Update cart count in header
               const cartCount = document.querySelector('.cart-count');
               if (cartCount) {
                   let count = 0;
                   document.querySelectorAll('.quantity-input').forEach(input => {
                       count += parseInt(input.value);
                   });
                   cartCount.textContent = count;
               }
               
               // Disable checkout button if total is 0
               const checkoutBtn = document.querySelector('.checkout-btn');
               if (checkoutBtn) {
                   if (total <= 0) {
                       checkoutBtn.setAttribute('disabled', 'disabled');
                   } else {
                       checkoutBtn.removeAttribute('disabled');
                   }
               }
           }
       });
   </script>
</body>
</html>