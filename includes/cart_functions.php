<?php
// Cart functions for the e-commerce system

/**
 * Get user's cart or create a new one if it doesn't exist
 *
 * @param int $userId The user ID
 * @param PDO $conn Database connection
 * @return array Cart information
 */
function getUserCart($userId, $conn) {
    try {
        // Check if user already has a cart
        $stmt = $conn->prepare("SELECT * FROM carts WHERE user_id = :user_id");
        $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
        $stmt->execute();
        $cart = $stmt->fetch(PDO::FETCH_ASSOC);

        // If no cart exists, create one
        if (!$cart) {
            $stmt = $conn->prepare("INSERT INTO carts (user_id) VALUES (:user_id)");
            $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
            $stmt->execute();

            $cartId = $conn->lastInsertId();

            $cart = [
                'id' => $cartId,
                'user_id' => $userId,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s')
            ];
        }

        return $cart;
    } catch (PDOException $e) {
        // Log error
        error_log("Error getting user cart: " . $e->getMessage());
        return false;
    }
}

/**
 * Get cart items for a specific cart
 *
 * @param int $cartId The cart ID
 * @param PDO $conn Database connection
 * @return array Cart items with product details
 */
function getCartItems($cartId, $conn, $products) {
    try {
        $stmt = $conn->prepare("
            SELECT ci.*
            FROM cart_items ci
            WHERE ci.cart_id = :cart_id
        ");
        $stmt->bindParam(':cart_id', $cartId, PDO::PARAM_INT);
        $stmt->execute();
        $cartItems = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Add product details from $products array
        $cartItemsWithDetails = [];
        foreach ($cartItems as $item) {
            $product = null;
            foreach ($products as $p) {
                if ($p['id'] == $item['product_id']) {
                    $product = $p;
                    break;
                }
            }

            if ($product) {
                $item['name'] = $product['name'];
                $item['price'] = $product['price'];
                $item['image'] = $product['image'];
                $item['stock'] = $product['stock'];
                $cartItemsWithDetails[] = $item;
            }
        }

        return $cartItemsWithDetails;
    } catch (PDOException $e) {
        // Log error
        error_log("Error getting cart items: " . $e->getMessage());
        return [];
    }
}

/**
 * Add a product to the cart
 *
 * @param int $cartId The cart ID
 * @param int $productId The product ID
 * @param int $quantity The quantity to add
 * @param PDO $conn Database connection
 * @param array $products Array of products from data/products.php
 * @return bool Success or failure
 */
function addToCart($cartId, $productId, $quantity, $conn, $products) {
    // Find the product in the $products array
    $product = null;
    foreach ($products as $p) {
        if ($p['id'] == $productId) {
            $product = $p;
            break;
        }
    }

    if (!$product) {
        return ['success' => false, 'message' => 'Product not found'];
    }

    if ($product['stock'] < $quantity) {
        return ['success' => false, 'message' => 'Not enough stock available'];
    }

    try {
        // Check if product already in cart
        $stmt = $conn->prepare("SELECT * FROM cart_items WHERE cart_id = :cart_id AND product_id = :product_id");
        $stmt->bindParam(':cart_id', $cartId, PDO::PARAM_INT);
        $stmt->bindParam(':product_id', $productId, PDO::PARAM_INT);
        $stmt->execute();
        $cartItem = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($cartItem) {
            // Update quantity if product already in cart
            $newQuantity = $cartItem['quantity'] + $quantity;

            // Check if new quantity exceeds stock
            if ($newQuantity > $product['stock']) {
                return ['success' => false, 'message' => 'Cannot add more of this item (stock limit reached)'];
            }

            $stmt = $conn->prepare("UPDATE cart_items SET quantity = :quantity WHERE id = :id");
            $stmt->bindParam(':quantity', $newQuantity, PDO::PARAM_INT);
            $stmt->bindParam(':id', $cartItem['id'], PDO::PARAM_INT);
            $stmt->execute();
        } else {
            // Add new item to cart
            $stmt = $conn->prepare("INSERT INTO cart_items (cart_id, product_id, quantity) VALUES (:cart_id, :product_id, :quantity)");
            $stmt->bindParam(':cart_id', $cartId, PDO::PARAM_INT);
            $stmt->bindParam(':product_id', $productId, PDO::PARAM_INT);
            $stmt->bindParam(':quantity', $quantity, PDO::PARAM_INT);
            $stmt->execute();
        }

        // Update cart timestamp
        $stmt = $conn->prepare("UPDATE carts SET updated_at = NOW() WHERE id = :cart_id");
        $stmt->bindParam(':cart_id', $cartId, PDO::PARAM_INT);
        $stmt->execute();

        return ['success' => true, 'message' => 'Product added to cart successfully'];
    } catch (PDOException $e) {
        // Log error
        error_log("Error adding to cart: " . $e->getMessage());
        return ['success' => false, 'message' => 'An error occurred while adding to cart'];
    }
}

/**
 * Update cart item quantity
 *
 * @param int $cartItemId The cart item ID
 * @param int $quantity The new quantity
 * @param PDO $conn Database connection
 * @param array $products Array of products from data/products.php
 * @return bool Success or failure
 */
function updateCartItemQuantity($cartItemId, $quantity, $conn, $products) {
    try {
        // Get cart item
        $stmt = $conn->prepare("SELECT * FROM cart_items WHERE id = :cart_item_id");
        $stmt->bindParam(':cart_item_id', $cartItemId, PDO::PARAM_INT);
        $stmt->execute();
        $cartItem = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$cartItem) {
            return ['success' => false, 'message' => 'Cart item not found'];
        }

        // Find the product in the $products array
        $product = null;
        foreach ($products as $p) {
            if ($p['id'] == $cartItem['product_id']) {
                $product = $p;
                break;
            }
        }

        if (!$product) {
            return ['success' => false, 'message' => 'Product not found'];
        }

        // Check if quantity is valid
        if ($quantity <= 0) {
            // Remove item from cart if quantity is 0 or negative
            return removeCartItem($cartItemId, $conn);
        }

        // Check if quantity exceeds stock
        if ($quantity > $product['stock']) {
            return ['success' => false, 'message' => 'Quantity exceeds available stock'];
        }

        // Update quantity
        $stmt = $conn->prepare("UPDATE cart_items SET quantity = :quantity WHERE id = :id");
        $stmt->bindParam(':quantity', $quantity, PDO::PARAM_INT);
        $stmt->bindParam(':id', $cartItemId, PDO::PARAM_INT);
        $stmt->execute();

        // Update cart timestamp
        $stmt = $conn->prepare("UPDATE carts SET updated_at = NOW() WHERE id = :cart_id");
        $stmt->bindParam(':cart_id', $cartItem['cart_id'], PDO::PARAM_INT);
        $stmt->execute();

        return ['success' => true, 'message' => 'Cart updated successfully'];
    } catch (PDOException $e) {
        // Log error
        error_log("Error updating cart item: " . $e->getMessage());
        return ['success' => false, 'message' => 'An error occurred while updating cart'];
    }
}

/**
 * Remove an item from the cart
 *
 * @param int $cartItemId The cart item ID
 * @param PDO $conn Database connection
 * @return bool Success or failure
 */
function removeCartItem($cartItemId, $conn) {
    try {
        // Get cart ID before deleting
        $stmt = $conn->prepare("SELECT cart_id FROM cart_items WHERE id = :id");
        $stmt->bindParam(':id', $cartItemId, PDO::PARAM_INT);
        $stmt->execute();
        $cartItem = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$cartItem) {
            return ['success' => false, 'message' => 'Cart item not found'];
        }

        // Delete cart item
        $stmt = $conn->prepare("DELETE FROM cart_items WHERE id = :id");
        $stmt->bindParam(':id', $cartItemId, PDO::PARAM_INT);
        $stmt->execute();

        // Update cart timestamp
        $stmt = $conn->prepare("UPDATE carts SET updated_at = NOW() WHERE id = :cart_id");
        $stmt->bindParam(':cart_id', $cartItem['cart_id'], PDO::PARAM_INT);
        $stmt->execute();

        return ['success' => true, 'message' => 'Item removed from cart'];
    } catch (PDOException $e) {
        // Log error
        error_log("Error removing cart item: " . $e->getMessage());
        return ['success' => false, 'message' => 'An error occurred while removing item from cart'];
    }
}

/**
 * Get cart total
 *
 * @param int $cartId The cart ID
 * @param PDO $conn Database connection
 * @param array $products Array of products from data/products.php
 * @return float Cart total
 */
function getCartTotal($cartId, $conn, $products) {
    try {
        $cartItems = getCartItems($cartId, $conn, $products);
        $total = 0;

        foreach ($cartItems as $item) {
            $total += $item['price'] * $item['quantity'];
        }

        return $total;
    } catch (PDOException $e) {
        // Log error
        error_log("Error calculating cart total: " . $e->getMessage());
        return 0;
    }
}

/**
 * Get cart count (number of items)
 *
 * @param int $cartId The cart ID
 * @param PDO $conn Database connection
 * @return int Number of items in cart
 */
function getCartItemCount($cartId, $conn) {
    try {
        $stmt = $conn->prepare("
            SELECT SUM(quantity) as count
            FROM cart_items
            WHERE cart_id = :cart_id
        ");
        $stmt->bindParam(':cart_id', $cartId, PDO::PARAM_INT);
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        return $result['count'] ? intval($result['count']) : 0;
    } catch (PDOException $e) {
        // Log error
        error_log("Error counting cart items: " . $e->getMessage());
        return 0;
    }
}

/**
 * Get delivery fee for a specific location
 *
 * @param string $state The state/province
 * @param string $city The city
 * @param PDO $conn Database connection
 * @return float Delivery fee
 */
function getDeliveryFee($state, $city, $conn) {
    try {
        $stmt = $conn->prepare("
            SELECT fee FROM delivery_fees
            WHERE state = :state AND city = :city
        ");
        $stmt->bindParam(':state', $state, PDO::PARAM_STR);
        $stmt->bindParam(':city', $city, PDO::PARAM_STR);
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($result) {
            return floatval($result['fee']);
        }

        // If no specific fee for city, try to get default fee for state
        $stmt = $conn->prepare("
            SELECT fee FROM delivery_fees
            WHERE state = :state LIMIT 1
        ");
        $stmt->bindParam(':state', $state, PDO::PARAM_STR);
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($result) {
            return floatval($result['fee']);
        }

        // Default fee if nothing found
        return 150.00;
    } catch (PDOException $e) {
        // Log error
        error_log("Error getting delivery fee: " . $e->getMessage());
        return 150.00; // Default fee
    }
}

/**
 * Create a new order from cart
 *
 * @param array $orderData Order information
 * @param PDO $conn Database connection
 * @param array $products Array of products from data/products.php
 * @return array Result with order ID or error message
 */
function createOrder($orderData, $conn, $products) {
    try {
        $conn->beginTransaction();

        // Generate unique order number
        $orderNumber = 'CZ' . date('Ymd') . rand(1000, 9999);

        // Insert order
        $stmt = $conn->prepare("
            INSERT INTO orders (
                user_id, order_number, first_name, last_name, email, phone,
                address_id, subtotal, delivery_fee, total, payment_method, notes
            ) VALUES (
                :user_id, :order_number, :first_name, :last_name, :email, :phone,
                :address_id, :subtotal, :delivery_fee, :total, :payment_method, :notes
            )
        ");

        $stmt->bindParam(':user_id', $orderData['user_id'], PDO::PARAM_INT);
        $stmt->bindParam(':order_number', $orderNumber, PDO::PARAM_STR);
        $stmt->bindParam(':first_name', $orderData['first_name'], PDO::PARAM_STR);
        $stmt->bindParam(':last_name', $orderData['last_name'], PDO::PARAM_STR);
        $stmt->bindParam(':email', $orderData['email'], PDO::PARAM_STR);
        $stmt->bindParam(':phone', $orderData['phone'], PDO::PARAM_STR);
        $stmt->bindParam(':address_id', $orderData['address_id'], PDO::PARAM_INT);
        $stmt->bindParam(':subtotal', $orderData['subtotal']);
        $stmt->bindParam(':delivery_fee', $orderData['delivery_fee']);
        $stmt->bindParam(':total', $orderData['total']);
        $stmt->bindParam(':payment_method', $orderData['payment_method'], PDO::PARAM_STR);
        $stmt->bindParam(':notes', $orderData['notes'], PDO::PARAM_STR);

        $stmt->execute();
        $orderId = $conn->lastInsertId();

        // Get cart items
        $cartItems = getCartItems($orderData['cart_id'], $conn, $products);

        // Insert order items
        foreach ($cartItems as $item) {
            $product = null;
            foreach ($products as $p) {
                if ($p['id'] == $item['product_id']) {
                    $product = $p;
                    break;
                }
            }

            if ($product) {
                $subtotal = $product['price'] * $item['quantity'];

                $stmt = $conn->prepare("
                    INSERT INTO order_items (
                        order_id, product_id, product_name, product_price, quantity, subtotal
                    ) VALUES (
                        :order_id, :product_id, :product_name, :product_price, :quantity, :subtotal
                    )
                ");

                $stmt->bindParam(':order_id', $orderId, PDO::PARAM_INT);
                $stmt->bindParam(':product_id', $item['product_id'], PDO::PARAM_INT);
                $stmt->bindParam(':product_name', $product['name'], PDO::PARAM_STR);
                $stmt->bindParam(':product_price', $product['price']);
                $stmt->bindParam(':quantity', $item['quantity'], PDO::PARAM_INT);
                $stmt->bindParam(':subtotal', $subtotal);

                $stmt->execute();

                // Update product stock (in memory)
                foreach ($products as $key => $p) {
                    if ($p['id'] == $item['product_id']) {
                        $products[$key]['stock'] -= $item['quantity'];
                        break;
                    }
                }
            }
        }

        // Clear cart
        $stmt = $conn->prepare("DELETE FROM cart_items WHERE cart_id = :cart_id");
        $stmt->bindParam(':cart_id', $orderData['cart_id'], PDO::PARAM_INT);
        $stmt->execute();

        $conn->commit();

        // Save the updated products array back to the products.php file
        $updatedData = "<?php
// Sample product data
\$products = " . var_export($products, true) . ";
";
        file_put_contents('data/products.php', $updatedData);

        return [
            'success' => true,
            'order_id' => $orderId,
            'order_number' => $orderNumber
        ];
    } catch (PDOException $e) {
        $conn->rollBack();
        // Log error
        error_log("Error creating order: " . $e->getMessage());
        return [
            'success' => false,
            'message' => 'An error occurred while processing your order: ' . $e->getMessage()
        ];
    }
}

/**
 * Upload payment screenshot for an order
 *
 * @param int $orderId The order ID
 * @param array $file The uploaded file ($_FILES array)
 * @param PDO $conn Database connection
 * @return array Result with success status and message
 */
function uploadPaymentScreenshot($orderId, $file, $conn) {
    try {
        // Check if order exists
        $stmt = $conn->prepare("SELECT id FROM orders WHERE id = :order_id");
        $stmt->bindParam(':order_id', $orderId, PDO::PARAM_INT);
        $stmt->execute();

        if ($stmt->rowCount() === 0) {
            return ['success' => false, 'message' => 'Order not found'];
        }

        // Validate file
        $allowedTypes = ['image/jpeg', 'image/png', 'image/jpg'];
        if (!in_array($file['type'], $allowedTypes)) {
            return ['success' => false, 'message' => 'Only JPG and PNG files are allowed'];
        }

        if ($file['size'] > 5 * 1024 * 1024) { // 5MB limit
            return ['success' => false, 'message' => 'File size must be less than 5MB'];
        }

        // Create upload directory if it doesn't exist
        $uploadDir = 'uploads/payments/';
        if (!file_exists($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }

        // Generate unique filename
        $filename = 'payment_' . $orderId . '_' . time() . '_' . basename($file['name']);
        $targetPath = $uploadDir . $filename;

        // Move uploaded file
        if (move_uploaded_file($file['tmp_name'], $targetPath)) {
            // Update order with payment screenshot
            $stmt = $conn->prepare("
                UPDATE orders
                SET payment_screenshot = :screenshot, status = 'Processing'
                WHERE id = :order_id
            ");
            $stmt->bindParam(':screenshot', $targetPath, PDO::PARAM_STR);
            $stmt->bindParam(':order_id', $orderId, PDO::PARAM_INT);
            $stmt->execute();

            return ['success' => true, 'message' => 'Payment screenshot uploaded successfully'];
        } else {
            return ['success' => false, 'message' => 'Failed to upload payment screenshot'];
        }
    } catch (PDOException $e) {
        // Log error
        error_log("Error uploading payment screenshot: " . $e->getMessage());
        return ['success' => false, 'message' => 'An error occurred while uploading payment screenshot'];
    }
}
?>
