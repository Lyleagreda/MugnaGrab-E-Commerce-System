<?php
// Enable error reporting for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();

// Check if user is logged in
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header('Location: index.php');
    exit;
}

// Include database connection
include 'db.php';

// Include products data
include 'data/products.php';

// AJAX endpoint to get order shipping address - MOVED TO TOP OF FILE
if (isset($_GET['action']) && $_GET['action'] === 'get_order_address' && isset($_GET['order_id'])) {
    $orderId = intval($_GET['order_id']);
    
    try {
        // Get the shipping_address_id for the order
        $stmt = $conn->prepare("SELECT shipping_address_id FROM orders WHERE id = :order_id");
        $stmt->bindParam(':order_id', $orderId);
        $stmt->execute();
        $order = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$order) {
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'message' => 'Order not found'
            ]);
            exit;
        }
        
        // Get the address details
        $addressId = $order['shipping_address_id'];
        $stmt = $conn->prepare("SELECT * FROM user_addresses WHERE id = :address_id");
        $stmt->bindParam(':address_id', $addressId);
        $stmt->execute();
        $address = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$address) {
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'message' => 'Address not found'
            ]);
            exit;
        }
        
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'address' => $address
        ]);
        exit;
        
    } catch (PDOException $e) {
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'message' => 'Database error: ' . $e->getMessage()
        ]);
        exit;
    }
}

// AJAX endpoint to mark order as received
if (isset($_POST['ajax_action']) && $_POST['ajax_action'] === 'mark_received' && isset($_POST['order_id'])) {
    $orderId = intval($_POST['order_id']);
    $userEmail = $_SESSION['user_email'] ?? '';
    
    try {
        // Get user ID
        $stmt = $conn->prepare("SELECT id FROM users WHERE email = :email");
        $stmt->bindParam(':email', $userEmail);
        $stmt->execute();
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$user) {
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'message' => 'User not found'
            ]);
            exit;
        }
        
        $userId = $user['id'];
        
        // Check if the order belongs to the user and is in delivered status
        $stmt = $conn->prepare("SELECT * FROM orders WHERE id = :id AND user_id = :user_id AND status = 'delivered'");
        $stmt->bindParam(':id', $orderId);
        $stmt->bindParam(':user_id', $userId);
        $stmt->execute();
        
        if ($stmt->rowCount() > 0) {
            // Update order status to received
            $stmt = $conn->prepare("UPDATE orders SET status = 'received', updated_at = NOW() WHERE id = :id");
            $stmt->bindParam(':id', $orderId);
            
            if ($stmt->execute()) {
                header('Content-Type: application/json');
                echo json_encode([
                    'success' => true,
                    'message' => "Order #" . $orderId . " has been marked as received. Thank you!"
                ]);
                exit;
            } else {
                header('Content-Type: application/json');
                echo json_encode([
                    'success' => false,
                    'message' => "Failed to update order status. Please try again."
                ]);
                exit;
            }
        } else {
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'message' => "Order not found or cannot be marked as received."
            ]);
            exit;
        }
    } catch (PDOException $e) {
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'message' => "Database error: " . $e->getMessage()
        ]);
        exit;
    }
}

// AJAX endpoint to cancel order
if (isset($_POST['ajax_action']) && $_POST['ajax_action'] === 'cancel_order' && isset($_POST['order_id'])) {
    $orderId = intval($_POST['order_id']);
    $userEmail = $_SESSION['user_email'] ?? '';
    
    try {
        // Get user ID
        $stmt = $conn->prepare("SELECT id FROM users WHERE email = :email");
        $stmt->bindParam(':email', $userEmail);
        $stmt->execute();
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$user) {
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'message' => 'User not found'
            ]);
            exit;
        }
        
        $userId = $user['id'];
        
        // Check if the order belongs to the user and is in pending status
        $stmt = $conn->prepare("SELECT * FROM orders WHERE id = :id AND user_id = :user_id AND status = 'pending'");
        $stmt->bindParam(':id', $orderId);
        $stmt->bindParam(':user_id', $userId);
        $stmt->execute();
        
        if ($stmt->rowCount() > 0) {
            // Update order status to cancelled
            $stmt = $conn->prepare("UPDATE orders SET status = 'cancelled', updated_at = NOW() WHERE id = :id");
            $stmt->bindParam(':id', $orderId);
            
            if ($stmt->execute()) {
                header('Content-Type: application/json');
                echo json_encode([
                    'success' => true,
                    'message' => "Order #" . $orderId . " has been cancelled successfully!"
                ]);
                exit;
            } else {
                header('Content-Type: application/json');
                echo json_encode([
                    'success' => false,
                    'message' => "Failed to cancel order. Please try again."
                ]);
                exit;
            }
        } else {
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'message' => "Order not found or cannot be cancelled."
            ]);
            exit;
        }
    } catch (PDOException $e) {
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'message' => "Database error: " . $e->getMessage()
        ]);
        exit;
    }
}

// Add a new AJAX endpoint to handle product ratings
// Add this code right after the existing AJAX endpoints at the top of the file (around line 200)

// AJAX endpoint to submit product ratings
if (isset($_POST['ajax_action']) && $_POST['ajax_action'] === 'submit_rating') {
    $productId = isset($_POST['product_id']) ? intval($_POST['product_id']) : 0;
    $rating = isset($_POST['rating']) ? floatval($_POST['rating']) : 0;
    $orderId = isset($_POST['order_id']) ? intval($_POST['order_id']) : 0;
    $userEmail = $_SESSION['user_email'] ?? '';
    
    if ($productId <= 0 || $rating <= 0 || $rating > 5 || $orderId <= 0) {
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'message' => 'Invalid product ID, rating, or order ID'
        ]);
        exit;
    }
    
    try {
        // Get user ID
        $stmt = $conn->prepare("SELECT id FROM users WHERE email = :email");
        $stmt->bindParam(':email', $userEmail);
        $stmt->execute();
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$user) {
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'message' => 'User not found'
            ]);
            exit;
        }
        
        $userId = $user['id'];
        
        // Check if the order belongs to the user and is in received status
        $stmt = $conn->prepare("SELECT * FROM orders WHERE id = :id AND user_id = :user_id AND status = 'received'");
        $stmt->bindParam(':id', $orderId);
        $stmt->bindParam(':user_id', $userId);
        $stmt->execute();
        
        if ($stmt->rowCount() === 0) {
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'message' => 'Order not found or not marked as received'
            ]);
            exit;
        }
        
        // Check if the product is in the order
        $stmt = $conn->prepare("SELECT * FROM order_items WHERE order_id = :order_id AND product_id = :product_id");
        $stmt->bindParam(':order_id', $orderId);
        $stmt->bindParam(':product_id', $productId);
        $stmt->execute();
        
        if ($stmt->rowCount() === 0) {
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'message' => 'Product not found in this order'
            ]);
            exit;
        }
        
        // Update the product rating in the products.php file
        $productsFile = 'data/products.php';
        $productsContent = file_get_contents($productsFile);
        
        // Load the products array
        include 'data/products.php';
        
        // Find the product and update its rating
        foreach ($products as &$product) {
            if ($product['id'] == $productId) {
                // Calculate new average rating
                $currentRating = $product['rating'];
                $currentReviews = $product['reviews'];
                $totalRatingPoints = $currentRating * $currentReviews;
                $newTotalPoints = $totalRatingPoints + $rating;
                $newReviews = $currentReviews + 1;
                $newRating = round($newTotalPoints / $newReviews, 1);
                
                // Update the product
                $product['rating'] = $newRating;
                $product['reviews'] = $newReviews;
                break;
            }
        }
        
        // Save the updated products array back to the file
        $productsData = "<?php\n// Sample product data\n\$products = " . var_export($products, true) . ";\n";
        file_put_contents($productsFile, $productsData);
        
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'message' => 'Rating submitted successfully',
            'new_rating' => $newRating,
            'new_reviews' => $newReviews
        ]);
        exit;
        
    } catch (Exception $e) {
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'message' => 'Error: ' . $e->getMessage()
        ]);
        exit;
    }
}

// Get user ID from session
$userEmail = $_SESSION['user_email'] ?? '';

// Fetch user data
try {
    $stmt = $conn->prepare("SELECT * FROM users WHERE email = :email");
    $stmt->bindParam(':email', $userEmail);
    $stmt->execute();
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user) {
        $userId = $user['id'];
    } else {
        // User not found in database
        session_destroy();
        header('Location: index.php?error=account_not_found');
        exit;
    }

    // Fetch user addresses
    $stmt = $conn->prepare("SELECT * FROM user_addresses WHERE user_id = :user_id ORDER BY is_primary DESC");
    $stmt->bindParam(':user_id', $userId);
    $stmt->execute();
    $addresses = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Fetch user orders
    $stmt = $conn->prepare("SELECT o.* FROM orders o WHERE o.user_id = :user_id ORDER BY o.created_at DESC");
    $stmt->bindParam(':user_id', $userId);
    $stmt->execute();
    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Fetch order items for each order
    $orderItems = [];
    foreach ($orders as $order) {
        $stmt = $conn->prepare("SELECT oi.* FROM order_items oi WHERE oi.order_id = :order_id");
        $stmt->bindParam(':order_id', $order['id']);
        $stmt->execute();
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $orderItems[$order['id']] = $items;
    }
    
    // Create a lookup array for products by ID
    $productsById = [];
    foreach ($products as $product) {
        $productsById[$product['id']] = $product;
    }
} catch (PDOException $e) {
    $error = "Database error: " . $e->getMessage();
}

// Process form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $formType = $_POST['form_type'] ?? '';
    
    // Process profile update
    if ($formType === 'profile_update') {
        $firstName = trim($_POST['first_name'] ?? '');
        $lastName = trim($_POST['last_name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        
        // Validate inputs
        $errors = [];
        
        if (empty($firstName)) {
            $errors[] = "First name is required";
        }
        
        if (empty($lastName)) {
            $errors[] = "Last name is required";
        }
        
        if (empty($email)) {
            $errors[] = "Email is required";
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = "Invalid email format";
        } elseif ($email !== $user['email']) {
            // Check if email already exists for another user
            $stmt = $conn->prepare("SELECT id FROM users WHERE email = :email AND id != :id");
            $stmt->bindParam(':email', $email);
            $stmt->bindParam(':id', $userId);
            $stmt->execute();
            if ($stmt->rowCount() > 0) {
                $errors[] = "Email is already in use by another account";
            }
        }
        
        // Process profile picture upload
        $profilePicture = $user['profile_picture'] ?? null;
        if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] === UPLOAD_ERR_OK) {
            $allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
            $fileType = $_FILES['profile_picture']['type'];
            
            if (!in_array($fileType, $allowedTypes)) {
                $errors[] = "Invalid file type. Only JPG, PNG, and GIF are allowed.";
            } else {
                $maxSize = 2 * 1024 * 1024; // 2MB
                if ($_FILES['profile_picture']['size'] > $maxSize) {
                    $errors[] = "File size exceeds the maximum limit of 2MB.";
                } else {
                    $uploadDir = 'uploads/profile_pictures/';
                    
                    // Create directory if it doesn't exist
                    if (!file_exists($uploadDir)) {
                        mkdir($uploadDir, 0777, true);
                    }
                    
                    $fileName = $userId . '_' . time() . '_' . basename($_FILES['profile_picture']['name']);
                    $targetFilePath = $uploadDir . $fileName;
                    
                    if (move_uploaded_file($_FILES['profile_picture']['tmp_name'], $targetFilePath)) {
                        // Delete old profile picture if exists
                        if ($profilePicture && file_exists($profilePicture)) {
                            unlink($profilePicture);
                        }
                        $profilePicture = $targetFilePath;
                    } else {
                        $errors[] = "Failed to upload profile picture. Please try again.";
                    }
                }
            }
        }
        
        // Update user profile if no errors
        if (empty($errors)) {
            try {
                $stmt = $conn->prepare("UPDATE users SET first_name = :first_name, last_name = :last_name, email = :email, phone = :phone, profile_picture = :profile_picture WHERE id = :id");
                $stmt->bindParam(':first_name', $firstName);
                $stmt->bindParam(':last_name', $lastName);
                $stmt->bindParam(':email', $email);
                $stmt->bindParam(':phone', $phone);
                $stmt->bindParam(':profile_picture', $profilePicture);
                $stmt->bindParam(':id', $userId);
                
                if ($stmt->execute()) {
                    // Update session data
                    $_SESSION['user_email'] = $email;
                    
                    // Refresh user data
                    $user['first_name'] = $firstName;
                    $user['last_name'] = $lastName;
                    $user['email'] = $email;
                    $user['phone'] = $phone;
                    $user['profile_picture'] = $profilePicture;
                    
                    $successMessage = "Profile updated successfully!";
                } else {
                    $errors[] = "Failed to update profile. Please try again.";
                }
            } catch (PDOException $e) {
                $errors[] = "Database error: " . $e->getMessage();
            }
        }
    }
    
    // Process password change
    elseif ($formType === 'password_change') {
        $currentPassword = $_POST['current_password'] ?? '';
        $newPassword = $_POST['new_password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';
        
        // Validate inputs
        $errors = [];
        
        if (empty($currentPassword)) {
            $errors[] = "Current password is required";
        } elseif (!password_verify($currentPassword, $user['password'])) {
            $errors[] = "Current password is incorrect";
        }
        
        if (empty($newPassword)) {
            $errors[] = "New password is required";
        } elseif (strlen($newPassword) < 8) {
            $errors[] = "New password must be at least 8 characters long";
        }
        
        if ($newPassword !== $confirmPassword) {
            $errors[] = "New passwords do not match";
        }
        
        // Update password if no errors
        if (empty($errors)) {
            try {
                $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
                
                $stmt = $conn->prepare("UPDATE users SET password = :password WHERE id = :id");
                $stmt->bindParam(':password', $hashedPassword);
                $stmt->bindParam(':id', $userId);
                
                if ($stmt->execute()) {
                    $passwordSuccess = "Password changed successfully!";
                    echo "
                    <script>
                        alert('Password changed successfully!');
                    </script>";

                } else {
                    $errors[] = "Failed to update password. Please try again.";
                }
            } catch (PDOException $e) {
                $errors[] = "Database error: " . $e->getMessage();
            }
        }
    }
    
    // Process address update/add
    elseif ($formType === 'address_update') {
        $addressId = isset($_POST['address_id']) ? intval($_POST['address_id']) : 0;
        $addressLine1 = trim($_POST['address_line1'] ?? '');
        $addressLine2 = trim($_POST['address_line2'] ?? '');
        $city = trim($_POST['city'] ?? '');
        $state = trim($_POST['state'] ?? '');
        $postalCode = trim($_POST['postal_code'] ?? '');
        $isPrimary = isset($_POST['is_primary']) ? 1 : 0;
        
        // Validate inputs
        $addressErrors = [];
        
        if (empty($addressLine1)) {
            $addressErrors[] = "Address line 1 is required";
        }
        
        if (empty($city)) {
            $addressErrors[] = "City is required";
        }
        
        if (empty($state)) {
            $addressErrors[] = "State/Province is required";
        }
        
        if (empty($postalCode)) {
            $addressErrors[] = "Postal code is required";
        }
        
        // Update or add address if no errors
        if (empty($addressErrors)) {
            try {
                // If primary address is selected, set all other addresses as non-primary
                if ($isPrimary) {
                    $stmt = $conn->prepare("UPDATE user_addresses SET is_primary = 0 WHERE user_id = :user_id");
                    $stmt->bindParam(':user_id', $userId);
                    $stmt->execute();
                }
                
                if ($addressId > 0) {
                    // Update existing address
                    $stmt = $conn->prepare("UPDATE user_addresses SET address_line1 = :address_line1, address_line2 = :address_line2, city = :city, state = :state, postal_code = :postal_code, is_primary = :is_primary WHERE id = :id AND user_id = :user_id");
                    $stmt->bindParam(':id', $addressId);
                } else {
                    // Add new address
                    $stmt = $conn->prepare("INSERT INTO user_addresses (user_id, address_line1, address_line2, city, state, postal_code, is_primary) VALUES (:user_id, :address_line1, :address_line2, :city, :state, :postal_code, :is_primary)");
                }
                
                $stmt->bindParam(':user_id', $userId);
                $stmt->bindParam(':address_line1', $addressLine1);
                $stmt->bindParam(':address_line2', $addressLine2);
                $stmt->bindParam(':city', $city);
                $stmt->bindParam(':state', $state);
                $stmt->bindParam(':postal_code', $postalCode);
                $stmt->bindParam(':is_primary', $isPrimary);
                
                if ($stmt->execute()) {
                    $addressSuccess = $addressId > 0 ? "Address updated successfully!" : "Address added successfully!";
                    
                    // Refresh addresses
                    $stmt = $conn->prepare("SELECT * FROM user_addresses WHERE user_id = :user_id ORDER BY is_primary DESC");
                    $stmt->bindParam(':user_id', $userId);
                    $stmt->execute();
                    $addresses = $stmt->fetchAll(PDO::FETCH_ASSOC);
                } else {
                    $addressErrors[] = "Failed to save address. Please try again.";
                }
            } catch (PDOException $e) {
                $addressErrors[] = "Database error: " . $e->getMessage();
            }
        }
    }
    
    // Process address deletion
    elseif ($formType === 'address_delete') {
        $addressId = isset($_POST['address_id']) ? intval($_POST['address_id']) : 0;
        
        if ($addressId > 0) {
            try {
                $stmt = $conn->prepare("DELETE FROM user_addresses WHERE id = :id AND user_id = :user_id");
                $stmt->bindParam(':id', $addressId);
                $stmt->bindParam(':user_id', $userId);
                
                if ($stmt->execute()) {
                    $addressSuccess = "Address deleted successfully!";
                    
                    // Refresh addresses
                    $stmt = $conn->prepare("SELECT * FROM user_addresses WHERE user_id = :user_id ORDER BY is_primary DESC");
                    $stmt->bindParam(':user_id', $userId);
                    $stmt->execute();
                    $addresses = $stmt->fetchAll(PDO::FETCH_ASSOC);
                } else {
                    $addressErrors[] = "Failed to delete address. Please try again.";
                }
            } catch (PDOException $e) {
                $addressErrors[] = "Database error: " . $e->getMessage();
            }
        }
    }
    
    // Process order cancellation
    elseif ($formType === 'cancel_order') {
        $orderId = isset($_POST['order_id']) ? intval($_POST['order_id']) : 0;
        
        if ($orderId > 0) {
            try {
                // First check if the order belongs to the user and is in pending status
                $stmt = $conn->prepare("SELECT * FROM orders WHERE id = :id AND user_id = :user_id AND status = 'pending'");
                $stmt->bindParam(':id', $orderId);
                $stmt->bindParam(':user_id', $userId);
                $stmt->execute();
                
                if ($stmt->rowCount() > 0) {
                    // Update order status to cancelled
                    $stmt = $conn->prepare("UPDATE orders SET status = 'cancelled', updated_at = NOW() WHERE id = :id");
                    $stmt->bindParam(':id', $orderId);
                    
                    if ($stmt->execute()) {
                        $orderSuccess = "Order #" . $orderId . " has been cancelled successfully!";
                        
                        // Refresh orders
                        $stmt = $conn->prepare("SELECT o.* FROM orders o WHERE o.user_id = :user_id ORDER BY o.created_at DESC");
                        $stmt->bindParam(':user_id', $userId);
                        $stmt->execute();
                        $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    } else {
                        $orderErrors[] = "Failed to cancel order. Please try again.";
                    }
                } else {
                    $orderErrors[] = "Order not found or cannot be cancelled.";
                }
            } catch (PDOException $e) {
                $orderErrors[] = "Database error: " . $e->getMessage();
            }
        }
    }

    // Process mark order as received
    elseif ($formType === 'mark_received') {
        $orderId = isset($_POST['order_id']) ? intval($_POST['order_id']) : 0;
        
        if ($orderId > 0) {
            try {
                // First check if the order belongs to the user and is in delivered status
                $stmt = $conn->prepare("SELECT * FROM orders WHERE id = :id AND user_id = :user_id AND status = 'delivered'");
                $stmt->bindParam(':id', $orderId);
                $stmt->bindParam(':user_id', $userId);
                $stmt->execute();
                
                if ($stmt->rowCount() > 0) {
                    // Update order status to received
                    $stmt = $conn->prepare("UPDATE orders SET status = 'received', updated_at = NOW() WHERE id = :id");
                    $stmt->bindParam(':id', $orderId);
                    
                    if ($stmt->execute()) {
                        $orderSuccess = "Order #" . $orderId . " has been marked as received. Thank you!";
                        
                        // Refresh orders
                        $stmt = $conn->prepare("SELECT o.* FROM orders o WHERE o.user_id = :user_id ORDER BY o.created_at DESC");
                        $stmt->bindParam(':user_id', $userId);
                        $stmt->execute();
                        $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    } else {
                        $orderErrors[] = "Failed to update order status. Please try again.";
                    }
                } else {
                    $orderErrors[] = "Order not found or cannot be marked as received.";
                }
            } catch (PDOException $e) {
                $orderErrors[] = "Database error: " . $e->getMessage();
            }
        }
    }

    // Process product rating submission
    elseif ($formType === 'submit_rating') {
        $productId = isset($_POST['product_id']) ? intval($_POST['product_id']) : 0;
        $rating = isset($_POST['rating']) ? floatval($_POST['rating']) : 0;
        $orderId = isset($_POST['order_id']) ? intval($_POST['order_id']) : 0;

        if ($productId <= 0 || $rating <= 0 || $rating > 5 || $orderId <= 0) {
            $orderErrors[] = 'Invalid product ID, rating, or order ID';
        } else {
            try {
                // Get user ID
                $stmt = $conn->prepare("SELECT id FROM users WHERE email = :email");
                $stmt->bindParam(':email', $userEmail);
                $stmt->execute();
                $user = $stmt->fetch(PDO::FETCH_ASSOC);

                if (!$user) {
                    $orderErrors[] = 'User not found';
                } else {
                    $userId = $user['id'];

                    // Check if the order belongs to the user and is in received status
                    $stmt = $conn->prepare("SELECT * FROM orders WHERE id = :id AND user_id = :user_id AND status = 'received'");
                    $stmt->bindParam(':id', $orderId);
                    $stmt->bindParam(':user_id', $userId);
                    $stmt->execute();

                    if ($stmt->rowCount() === 0) {
                        $orderErrors[] = 'Order not found or not marked as received';
                    } else {
                        // Check if the product is in the order
                        $stmt = $conn->prepare("SELECT * FROM order_items WHERE order_id = :order_id AND product_id = :product_id");
                        $stmt->bindParam(':order_id', $orderId);
                        $stmt->bindParam(':product_id', $productId);
                        $stmt->execute();

                        if ($stmt->rowCount() === 0) {
                            $orderErrors[] = 'Product not found in this order';
                        } else {
                            // Update the product rating in the products.php file
                            $productsFile = 'data/products.php';
                            $productsContent = file_get_contents($productsFile);

                            // Load the products array
                            include 'data/products.php';

                            // Find the product and update its rating
                            foreach ($products as &$product) {
                                if ($product['id'] == $productId) {
                                    // Calculate new average rating
                                    $currentRating = $product['rating'];
                                    $currentReviews = $product['reviews'];
                                    $totalRatingPoints = $currentRating * $currentReviews;
                                    $newTotalPoints = $totalRatingPoints + $rating;
                                    $newReviews = $currentReviews + 1;
                                    $newRating = round($newTotalPoints / $newReviews, 1);

                                    // Update the product
                                    $product['rating'] = $newRating;
                                    $product['reviews'] = $newReviews;
                                    break;
                                }
                            }

                            // Save the updated products array back to the file
                            $productsData = "<?php\n// Sample product data\n\$products = " . var_export($products, true) . ";\n";
                            file_put_contents($productsFile, $productsData);

                            $orderSuccess = 'Rating submitted successfully';
                        }
                    }
                }
            } catch (Exception $e) {
                $orderErrors[] = 'Error: ' . $e->getMessage();
            }
        }
    }
}  // Add this closing bracket for the if ($_SERVER['REQUEST_METHOD'] === 'POST') condition

// Include common functions
include 'includes/functions.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Account - Mugna</title>
    <link rel="stylesheet" href="css/styles.css">
    <link rel="stylesheet" href="css/account.css">
    <link rel="shortcut icon" href="./images/mugna-icon.png" type="image/x-icon">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* Fixed sidebar styles */
        .account-container {
            display: flex;
            gap: 30px;
            position: relative;
        }
        
        .account-sidebar {
            width: 280px;
            position: sticky;
            top: 20px;
            height: calc(100vh - 40px);
            overflow-y: auto;
            padding-right: 10px;
            transition: all 0.3s ease;
        }
        
        .account-content {
            flex: 1;
            min-width: 0;
        }
        
        /* Additional styles for the orders section */
        .orders-container {
            margin-top: 20px;
        }
        
        .order-filters {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-bottom: 24px;
            background-color: #f9f9f9;
            padding: 15px;
            border-radius: 10px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
        }
        
        .order-filter-btn {
            padding: 8px 16px;
            border-radius: 20px;
            background-color: #fff;
            border: 1px solid #e5e7eb;
            color: #6b7280;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s ease;
        }
        
        .order-filter-btn:hover {
            background-color: #f3f4f6;
            border-color: #d1d5db;
        }
        
        .order-filter-btn.active {
            background-color: #2563eb;
            border-color: #2563eb;
            color: white;
        }
        
        .order-card {
            background-color: #fff;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.08);
            margin-bottom: 24px;
            overflow: hidden;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }
        
        .order-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        }
        
        .order-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 16px 20px;
            background-color: #f9f9f9;
            border-bottom: 1px solid #eee;
        }
        
        .order-info h3 {
            margin: 0;
            font-size: 18px;
            color: #333;
        }
        
        .order-date {
            margin: 4px 0 0;
            font-size: 14px;
            color: #666;
        }
        
        .order-status {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 14px;
            font-weight: 600;
            text-transform: capitalize;
        }
        
        .order-status.pending {
            background-color: #fff8e6;
            color: #f59e0b;
        }
        
        .order-status.processing {
            background-color: #e6f7ff;
            color: #0891b2;
        }
        
        .order-status.shipped {
            background-color: #e6f6ff;
            color: #0284c7;
        }
        
        .order-status.delivered {
            background-color: #e6ffee;
            color: #059669;
        }

        // Add styling for the received status badge
        // Find the order-status CSS styles (around line 600)
        // Add the received status style after the delivered style:
        .order-status.received {
            background-color: #dcfce7;
            color: #059669;
        }
        
        .order-status.cancelled {
            background-color: #fee6e6;
            color: #dc2626;
        }
        
        .order-details {
            padding: 16px 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
        }
        
        .order-summary {
            flex: 1;
            min-width: 200px;
        }
        
        .order-summary p {
            margin: 6px 0;
            font-size: 15px;
        }
        
        .order-actions {
            display: flex;
            gap: 10px;
            margin-top: 10px;
        }
        
        .btn-sm {
            padding: 8px 16px;
            font-size: 14px;
        }
        
        .btn-danger {
            background-color: #dc2626;
            color: white;
            border: none;
        }
        
        .btn-danger:hover {
            background-color: #b91c1c;
        }

        // Add the CSS for the success button style in the <style> section (around line 400-500)
        // Add this after the .btn-danger styles:

        .btn-success {
            background-color: #10b981;
            color: white;
            border: none;
        }

        .btn-success:hover {
            background-color: #059669;
        }
        
        .order-items {
            padding: 0 20px 16px;
            border-top: 1px solid #eee;
        }
        
        .order-items-title {
            margin: 16px 0;
            font-size: 16px;
            font-weight: 600;
        }
        
        .order-items-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 16px;
        }
        
        .order-item {
            display: flex;
            flex-direction: column;
            background-color: #f9f9f9;
            border-radius: 8px;
            overflow: hidden;
            transition: transform 0.2s ease;
        }
        
        .order-item:hover {
            transform: translateY(-2px);
        }
        
        .order-item-image {
            height: 120px;
            overflow: hidden;
            position: relative;
            background-color: #fff;
        }
        
        .order-item-image img {
            width: 100%;
            height: 100%;
            object-fit: contain;
        }
        
        .order-item-details {
            padding: 12px;
        }
        
        .order-item-name {
            margin: 0 0 6px;
            font-size: 14px;
            font-weight: 600;
            color: #333;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        
        .order-item-price {
            font-size: 14px;
            color: #333;
            margin: 0 0 4px;
        }
        
        .order-item-quantity {
            font-size: 13px;
            color: #666;
            margin: 0;
        }
        
        .tracking-timeline {
            display: flex;
            justify-content: space-between;
            margin: 20px 0;
            padding: 0 20px 20px;
            position: relative;
        }
        
        .tracking-timeline::before {
            content: '';
            position: absolute;
            top: 15px;
            left: 40px;
            right: 40px;
            height: 2px;
            background-color: #e5e7eb;
            z-index: 1;
        }
        
        .tracking-step {
            display: flex;
            flex-direction: column;
            align-items: center;
            position: relative;
            z-index: 2;
            flex: 1;
        }
        
        .step-icon {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            background-color: #e5e7eb;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 8px;
            color: #9ca3af;
        }
        
        .step-label {
            font-size: 12px;
            color: #6b7280;
            text-align: center;
        }
        
        .tracking-step.active .step-icon {
            background-color: #10b981;
            color: white;
        }
        
        .tracking-step.active .step-label {
            color: #10b981;
            font-weight: 600;
        }
        
        .tracking-step.cancelled .step-icon {
            background-color: #dc2626;
            color: white;
        }
        
        .tracking-step.cancelled .step-label {
            color: #dc2626;
            font-weight: 600;
        }
        
        .no-orders {
            text-align: center;
            padding: 40px 20px;
            background-color: #f9f9f9;
            border-radius: 10px;
        }
        
        .no-orders p {
            margin-bottom: 20px;
            color: #666;
        }
        
        @media (max-width: 768px) {
            .account-container {
                flex-direction: column;
            }
            
            .account-sidebar {
                width: 100%;
                position: relative;
                height: auto;
                margin-bottom: 20px;
            }
            
            .order-details {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .order-actions {
                margin-top: 16px;
                width: 100%;
                justify-content: flex-start;
            }
            
            .order-items-grid {
                grid-template-columns: repeat(auto-fill, minmax(140px, 1fr));
            }
            
            .tracking-timeline {
                padding: 0 10px 20px;
            }
            
            .tracking-timeline::before {
                left: 20px;
                right: 20px;
            }
            
            .step-label {
                font-size: 10px;
            }
        }

    /* Modal Styles */
    .modal {
        display: none;
        position: fixed;
        z-index: 1000;
        left: 0;
        top: 0;
        width: 100%;
        height: 100%;
        overflow: auto;
        background-color: rgba(0, 0, 0, 0.5);
        animation: fadeIn 0.3s;
    }
    
    .modal-content {
        position: relative;
        background-color: #fff;
        margin: 50px auto;
        padding: 0;
        border-radius: 10px;
        width: 90%;
        max-width: 800px;
        box-shadow: 0 5px 15px rgba(0, 0, 0, 0.3);
        animation: slideIn 0.3s;
        max-height: 90vh;
        display: flex;
        flex-direction: column;
    }
    
    .modal-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 20px;
        border-bottom: 1px solid #eee;
    }
    
    .modal-title {
        margin: 0;
        font-size: 20px;
        color: #333;
    }
    
    .modal-close {
        background: none;
        border: none;
        font-size: 24px;
        cursor: pointer;
        color: #666;
        transition: color 0.2s;
    }
    
    .modal-close:hover {
        color: #333;
    }
    
    .modal-body {
        padding: 20px;
        overflow-y: auto;
    }
    
    .modal-footer {
        padding: 15px 20px;
        border-top: 1px solid #eee;
        text-align: right;
    }
    
    .order-info-section,
    .shipping-info-section,
    .order-items-section,
    .order-tracking-section {
        margin-bottom: 25px;
    }
    
    .order-info-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 15px;
    }
    
    .order-info-details {
        display: flex;
        flex-wrap: wrap;
        gap: 30px;
        background-color: #f9f9f9;
        padding: 15px;
        border-radius: 8px;
    }
    
    .info-group {
        flex: 1;
        min-width: 200px;
    }
    
    .info-group p {
        margin: 8px 0;
    }
    
    .modal-order-items {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
        gap: 15px;
        margin-top: 15px;
    }
    
    #modal-shipping-address {
        background-color: #f9f9f9;
        padding: 15px;
        border-radius: 8px;
        margin-top: 10px;
    }
    
    @keyframes fadeIn {
        from { opacity: 0; }
        to { opacity: 1; }
    }
    
    @keyframes slideIn {
        from { transform: translateY(-50px); opacity: 0; }
        to { transform: translateY(0); opacity: 1; }
    }
    
    @media (max-width: 768px) {
        .modal-content {
            width: 95%;
            margin: 20px auto;
        }
        
        .order-info-details {
            flex-direction: column;
            gap: 10px;
        }
    }

    /* Toast notification styles */
    .toast-container {
        position: fixed;
        top: 20px;
        right: 20px;
        z-index: 9999;
        max-width: 350px;
    }

    .toast {
        background-color: white;
        border-radius: 8px;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
        margin-bottom: 10px;
        overflow: hidden;
        animation: slideInRight 0.3s ease-out forwards;
        display: flex;
        align-items: center;
        padding-right: 15px;
    }

    .toast.hide {
        animation: slideOutRight 0.3s ease-out forwards;
    }

    .toast-icon {
        width: 50px;
        height: 100%;
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        padding: 15px 0;
    }

    .toast-success .toast-icon {
        background-color: #10b981;
    }

    .toast-error .toast-icon {
        background-color: #ef4444;
    }

    .toast-content {
        padding: 15px;
        flex: 1;
    }

    .toast-title {
        font-weight: 600;
        margin-bottom: 5px;
        color: #1f2937;
    }

    .toast-message {
        color: #4b5563;
        font-size: 14px;
    }

    .toast-close {
        background: none;
        border: none;
        cursor: pointer;
        font-size: 18px;
        color: #9ca3af;
        padding: 0 5px;
    }

    .toast-close:hover {
        color: #4b5563;
    }

    @keyframes slideInRight {
        from {
            transform: translateX(100%);
            opacity: 0;
        }
        to {
            transform: translateX(0);
            opacity: 1;
        }
    }

    @keyframes slideOutRight {
        from {
            transform: translateX(0);
            opacity: 1;
        }
        to {
            transform: translateX(100%);
            opacity: 0;
        }
    }
</style>
</head>
<body>
    <div class="site-container">
        <?php include 'includes/header.php'; ?>
        
        <main class="main-content">
            <div class="container">
                <!-- Page Header -->
                <section class="page-header">
                    <div class="container">
                        <h1 class="page-title">My Account</h1>
                        <div class="breadcrumb">
                            <a href="home.php">Home</a>
                            <span class="breadcrumb-separator">/</span>
                            <span>My Account</span>
                        </div>
                    </div>
                </section>
                
                <!-- Account Container -->
                <div class="account-container">
                    <!-- Account Sidebar -->
                    <div class="account-sidebar">
                        <div class="user-profile">
                            <div class="profile-picture">
                                <?php if (!empty($user['profile_picture']) && file_exists($user['profile_picture'])): ?>
                                    <img src="<?php echo $user['profile_picture']; ?>" alt="<?php echo $user['first_name']; ?>">
                                <?php else: ?>
                                    <div class="profile-initials">
                                        <?php echo substr($user['first_name'] ?? '', 0, 1) . substr($user['last_name'] ?? '', 0, 1); ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <div class="user-info">
                                <h3><?php echo $user['first_name'] . ' ' . $user['last_name']; ?></h3>
                                <p><?php echo $user['email']; ?></p>
                            </div>
                        </div>
                        
                        <nav class="account-nav">
                            <ul>
                                <li class="active">
                                    <a href="#profile-section" class="account-nav-link" data-section="profile-section">
                                        <i class="fas fa-user"></i> Profile Information
                                    </a>
                                </li>
                                <li>
                                    <a href="#password-section" class="account-nav-link" data-section="password-section">
                                        <i class="fas fa-lock"></i> Change Password
                                    </a>
                                </li>
                                <li>
                                    <a href="#addresses-section" class="account-nav-link" data-section="addresses-section">
                                        <i class="fas fa-map-marker-alt"></i> Manage Addresses
                                    </a>
                                </li>
                                <li>
                                    <a href="#orders-section" class="account-nav-link" data-section="orders-section">
                                        <i class="fas fa-shopping-bag"></i> My Orders
                                    </a>
                                </li>
                                <li>
                                    <a href="wishlist.php">
                                        <i class="fas fa-heart"></i> Wishlist
                                    </a>
                                </li>
                                <li>
                                    <a href="logout.php" class="logout-link">
                                        <i class="fas fa-sign-out-alt"></i> Logout
                                    </a>
                                </li>
                            </ul>
                        </nav>
                    </div>
                    
                    <!-- Account Content -->
                    <div class="account-content">
                        <!-- Profile Section -->
                        <section id="profile-section" class="account-section active">
                            <div class="section-header">
                                <h2>Profile Information</h2>
                                <p>Update your personal information and profile picture</p>
                            </div>
                            
                            <?php if (isset($errors) && !empty($errors)): ?>
                                <div class="alert alert-danger">
                                    <ul>
                                        <?php foreach ($errors as $error): ?>
                                            <li><?php echo $error; ?></li>
                                        <?php endforeach; ?>
                                    </ul>
                                </div>
                            <?php endif; ?>
                            
                            <?php if (isset($successMessage)): ?>
                                <div class="alert alert-success">
                                    <?php echo $successMessage; ?>
                                </div>
                            <?php endif; ?>
                            
                            <form action="account.php" method="post" enctype="multipart/form-data" class="account-form">
                                <input type="hidden" name="form_type" value="profile_update">
                                
                                <div class="profile-picture-upload">
                                    <div class="current-picture">
                                        <?php if (!empty($user['profile_picture']) && file_exists($user['profile_picture'])): ?>
                                            <img src="<?php echo $user['profile_picture']; ?>" alt="Profile Picture">
                                        <?php else: ?>
                                            <div class="profile-initials large">
                                                <?php echo substr($user['first_name'] ?? '', 0, 1) . substr($user['last_name'] ?? '', 0, 1); ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    <div class="upload-controls">
                                        <label for="profile_picture" class="upload-btn">
                                            <i class="fas fa-camera"></i> Change Picture
                                        </label>
                                        <input type="file" id="profile_picture" name="profile_picture" accept="image/*" style="display: none;">
                                        <p class="upload-hint">Maximum file size: 2MB. Supported formats: JPG, PNG, GIF</p>
                                    </div>
                                </div>
                                
                                <div class="form-row">
                                    <div class="form-group">
                                        <label for="first_name">First Name <span class="required">*</span></label>
                                        <input type="text" id="first_name" name="first_name" value="<?php echo $user['first_name'] ?? ''; ?>" required>
                                    </div>
                                    
                                    <div class="form-group">
                                        <label for="last_name">Last Name <span class="required">*</span></label>
                                        <input type="text" id="last_name" name="last_name" value="<?php echo $user['last_name'] ?? ''; ?>" required>
                                    </div>
                                </div>
                                
                                <div class="form-row">
                                    <div class="form-group">
                                        <label for="email">Email Address <span class="required">*</span></label>
                                        <input type="email" id="email" name="email" value="<?php echo $user['email'] ?? ''; ?>" required>
                                    </div>
                                    
                                    <div class="form-group">
                                        <label for="phone">Phone Number</label>
                                        <input type="tel" id="phone" name="phone" value="<?php echo $user['phone'] ?? ''; ?>">
                                    </div>
                                </div>
                                
                                <div class="form-actions">
                                    <button type="submit" class="btn btn-primary">Save Changes</button>
                                </div>
                            </form>
                        </section>
                        
                        <!-- Password Section -->
                        <section id="password-section" class="account-section">
                            <div class="section-header">
                                <h2>Change Password</h2>
                                <p>Update your password to keep your account secure</p>
                            </div>
                            
                            <?php if (isset($errors) && !empty($errors) && $formType === 'password_change'): ?>
                                <div class="alert alert-danger">
                                    <ul>
                                        <?php foreach ($errors as $error): ?>
                                            <li><?php echo $error; ?></li>
                                        <?php endforeach; ?>
                                    </ul>
                                </div>
                            <?php endif; ?>
                            
                            <?php if (isset($passwordSuccess)): ?>
                                <div class="alert alert-success">
                                    <?php echo $passwordSuccess; ?>
                                </div>
                            <?php endif; ?>
                            
                            <form action="account.php" method="post" class="account-form">
                                <input type="hidden" name="form_type" value="password_change">
                                
                                <div class="form-group">
                                    <label for="current_password">Current Password <span class="required">*</span></label>
                                    <div class="password-input-wrapper">
                                        <input type="password" id="current_password" name="current_password" required>
                                        <button type="button" class="toggle-password" data-target="current_password">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                    </div>
                                </div>
                                
                                <div class="form-group">
                                    <label for="new_password">New Password <span class="required">*</span></label>
                                    <div class="password-input-wrapper">
                                        <input type="password" id="new_password" name="new_password" required minlength="8">
                                        <button type="button" class="toggle-password" data-target="new_password">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                    </div>
                                    <p class="input-hint">Password must be at least 8 characters long</p>
                                </div>
                                
                                <div class="form-group">
                                    <label for="confirm_password">Confirm New Password <span class="required">*</span></label>
                                    <div class="password-input-wrapper">
                                        <input type="password" id="confirm_password" name="confirm_password" required minlength="8">
                                        <button type="button" class="toggle-password" data-target="confirm_password">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                    </div>
                                </div>
                                
                                <div class="form-actions">
                                    <button type="submit" class="btn btn-primary">Update Password</button>
                                </div>
                            </form>
                        </section>
                        
                        <!-- Addresses Section -->
                        <section id="addresses-section" class="account-section">
                            <div class="section-header">
                                <h2>Manage Addresses</h2>
                                <p>Add or update your delivery addresses</p>
                            </div>
                            
                            <?php if (isset($addressErrors) && !empty($addressErrors)): ?>
                                <div class="alert alert-danger">
                                    <ul>
                                        <?php foreach ($addressErrors as $error): ?>
                                            <li><?php echo $error; ?></li>
                                        <?php endforeach; ?>
                                    </ul>
                                </div>
                            <?php endif; ?>
                            
                            <?php if (isset($addressSuccess)): ?>
                                <div class="alert alert-success">
                                    <?php echo $addressSuccess; ?>
                                </div>
                            <?php endif; ?>
                            
                            <div class="addresses-container">
                                <?php if (empty($addresses)): ?>
                                    <div class="no-addresses">
                                        <p>You don't have any saved addresses yet.</p>
                                    </div>
                                <?php else: ?>
                                    <div class="addresses-grid">
                                        <?php foreach ($addresses as $address): ?>
                                            <div class="address-card">
                                                <?php if ($address['is_primary']): ?>
                                                    <div class="primary-badge">Primary</div>
                                                <?php endif; ?>
                                                
                                                <div class="address-content">
                                                    <p class="address-line"><?php echo $address['address_line1']; ?></p>
                                                    <?php if (!empty($address['address_line2'])): ?>
                                                        <p class="address-line"><?php echo $address['address_line2']; ?></p>
                                                    <?php endif; ?>
                                                    <p class="address-line"><?php echo $address['city'] . ', ' . $address['state'] . ' ' . $address['postal_code']; ?></p>
                                                </div>
                                                
                                                <div class="address-actions">
                                                    <button type="button" class="btn-edit-address" data-address-id="<?php echo $address['id']; ?>">
                                                        <i class="fas fa-edit"></i> Edit
                                                    </button>
                                                    
                                                    <form action="account.php" method="post" class="delete-address-form">
                                                        <input type="hidden" name="form_type" value="address_delete">
                                                        <input type="hidden" name="address_id" value="<?php echo $address['id']; ?>">
                                                        <button type="submit" class="btn-delete-address" onclick="return confirm('Are you sure you want to delete this address?')">
                                                            <i class="fas fa-trash"></i> Delete
                                                        </button>
                                                    </form>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                                
                                <button type="button" class="btn btn-outline" id="add-address-btn">
                                    <i class="fas fa-plus"></i> Add New Address
                                </button>
                            </div>
                            
                            <div class="address-form-container" id="address-form-container" style="display: none;">
                                <form action="account.php" method="post" class="account-form" id="address-form">
                                    <input type="hidden" name="form_type" value="address_update">
                                    <input type="hidden" name="address_id" id="address_id" value="0">
                                    
                                    <div class="form-group">
                                        <label for="address_line1">BLK LOT & Village <span class="required">*</span></label>
                                        <input type="text" id="address_line1" name="address_line1" required>
                                    </div>
                                    
                                    <div class="form-group">
                                        <label for="address_line2">Barangay <span class="required">*</label>
                                        <input type="text" id="address_line2" name="address_line2">
                                    </div>
                                    
                                    <div class="form-row">
                                        <div class="form-group">
                                            <label for="city">City <span class="required">*</span></label>
                                            <input type="text" id="city" name="city" required>
                                        </div>
                                        
                                        <div class="form-group">
                                            <label for="state">State/Province <span class="required">*</span></label>
                                            <input type="text" id="state" name="state" required>
                                        </div>
                                    </div>
                                    
                                    <div class="form-group">
                                        <label for="postal_code">Postal Code <span class="required">*</span></label>
                                        <input type="text" id="postal_code" name="postal_code" required>
                                    </div>
                                    
                                    <div class="form-group checkbox-group">
                                        <label class="checkbox-label">
                                            <input type="checkbox" id="is_primary" name="is_primary">
                                            <span>Set as primary address</span>
                                        </label>
                                    </div>
                                    
                                    <div class="form-actions">
                                        <button type="button" class="btn btn-outline" id="cancel-address-btn">Cancel</button>
                                        <button type="submit" class="btn btn-primary">Save Address</button>
                                    </div>
                                </form>
                            </div>
                        </section>
                        
                        <!-- Orders Section -->
                        <section id="orders-section" class="account-section">
                            <div class="section-header">
                                <h2>My Orders</h2>
                                <p>View and manage your order history</p>
                            </div>
                            
                            <?php if (isset($orderErrors) && !empty($orderErrors)): ?>
                                <div class="alert alert-danger">
                                    <ul>
                                        <?php foreach ($orderErrors as $error): ?>
                                            <li><?php echo $error; ?></li>
                                        <?php endforeach; ?>
                                    </ul>
                                </div>
                            <?php endif; ?>
                            
                            <?php if (isset($orderSuccess)): ?>
                                <div class="alert alert-success">
                                    <?php echo $orderSuccess; ?>
                                </div>
                            <?php endif; ?>
                            
                            <div class="orders-container">
                                <?php if (empty($orders)): ?>
                                    <div class="no-orders">
                                        <p>You haven't placed any orders yet.</p>
                                        <a href="shop.php" class="btn btn-primary">Start Shopping</a>
                                    </div>
                                <?php else: ?>
                                    <!-- Order Filters -->
                                    <div class="order-filters">
                                        <button type="button" class="order-filter-btn active" data-filter="all">All Orders</button>
                                        <button type="button" class="order-filter-btn" data-filter="pending">Order Placed</button>
                                        <button type="button" class="order-filter-btn" data-filter="processing">Processing</button>
                                        <button type="button" class="order-filter-btn" data-filter="shipped">Shipped</button>
                                        <button type="button" class="order-filter-btn" data-filter="delivered">Delivered</button>
                                        <button type="button" class="order-filter-btn" data-filter="received">Received</button>
                                        <button type="button" class="order-filter-btn" data-filter="cancelled">Cancelled</button>
                                    </div>
                                    
                                    <div class="orders-list">
                                        <?php foreach ($orders as $order): ?>
                                            <div class="order-card" data-status="<?php echo strtolower($order['status']); ?>">
                                                <div class="order-header">
                                                    <div class="order-info">
                                                        <h3>Order #<?php echo $order['id']; ?></h3>
                                                        <p class="order-date">Placed on <?php echo date('F j, Y', strtotime($order['created_at'])); ?></p>
                                                    </div>
                                                    <div class="order-status <?php echo strtolower($order['status']); ?>">
                                                        <?php echo ucfirst($order['status']); ?>
                                                    </div>
                                                </div>
                                                
                                                <div class="order-details">
                                                    <div class="order-summary">
                                                        <p><strong>Total:</strong> ₱<?php echo number_format($order['total'], 2); ?></p>
                                                        <p><strong>Payment Method:</strong> <?php echo ucwords(str_replace('_', ' ', $order['payment_method'])); ?></p>
                                                    </div>
                                                    
                                                    
                                                    
                                                    <div class="order-actions">
                                                        <a href="order-details.php?id=<?php echo $order['id']; ?>" class="btn btn-outline btn-sm">
                                                            <i class="fas fa-eye"></i> View Details
                                                        </a>
                                                        
                                                        <?php if ($order['status'] === 'pending'): ?>
                                                            <button type="button" class="btn btn-danger btn-sm cancel-order-btn" data-order-id="<?php echo $order['id']; ?>">
                                                                <i class="fas fa-times"></i> Cancel Order
                                                            </button>
                                                        <?php elseif ($order['status'] === 'delivered'): ?>
                                                            <button type="button" class="btn btn-primary btn-sm mark-received-btn" data-order-id="<?php echo $order['id']; ?>">
                                                                <i class="fas fa-check"></i> Mark as Received
                                                            </button>
                                                        <?php elseif ($order['status'] === 'received'): ?>
                                                            <button type="button" class="btn btn-success btn-sm rate-products-btn" data-order-id="<?php echo $order['id']; ?>">
                                                                <i class="fas fa-star"></i> Rate Products
                                                            </button>
                                                        <?php endif; ?>
                                                    </div>
                                                
                                                </div>
                                                
                                                <?php if (isset($orderItems[$order['id']]) && !empty($orderItems[$order['id']])): ?>
                                                <div class="order-items">
                                                    <h4 class="order-items-title">Ordered Items</h4>
                                                    <div class="order-items-grid">
                                                        <?php foreach ($orderItems[$order['id']] as $item): ?>
                                                            <div class="order-item">
                                                                <div class="order-item-image">
                                                                    <?php 
                                                                    $productImage = 'images/products/default.jpg';
                                                                    if (isset($productsById[$item['product_id']]) && !empty($productsById[$item['product_id']]['image'])) {
                                                                        $productImage = $productsById[$item['product_id']]['image'];
                                                                    }
                                                                    ?>
                                                                    <img src="<?php echo $productImage; ?>" alt="<?php echo $item['product_name']; ?>">
                                                                </div>
                                                                <div class="order-item-details">
                                                                    <h5 class="order-item-name"><?php echo $item['product_name']; ?></h5>
                                                                    <p class="order-item-price">₱<?php echo number_format($item['price'], 2); ?></p>
                                                                    <p class="order-item-quantity">Qty: <?php echo $item['quantity']; ?></p>
                                                                </div>
                                                            </div>
                                                        <?php endforeach; ?>
                                                    </div>
                                                </div>
                                                <?php endif; ?>
                                                
                                                <div class="order-tracking">
                                                    <div class="tracking-timeline">
                                                        <div class="tracking-step <?php echo ($order['status'] != 'cancelled') ? 'active' : 'cancelled'; ?>">
                                                            <div class="step-icon"><i class="fas fa-clipboard-check"></i></div>
                                                            <div class="step-label">Order Placed</div>
                                                        </div>
                                                        <div class="tracking-step <?php echo (in_array($order['status'], ['processing', 'shipped', 'delivered', 'received'])) ? 'active' : ''; ?>">
                                                            <div class="step-icon"><i class="fas fa-cog"></i></div>
                                                            <div class="step-label">Processing</div>
                                                        </div>
                                                        <div class="tracking-step <?php echo (in_array($order['status'], ['shipped', 'delivered', 'received'])) ? 'active' : ''; ?>">
                                                            <div class="step-icon"><i class="fas fa-truck"></i></div>
                                                            <div class="step-label">Shipped</div>
                                                        </div>
                                                        <div class="tracking-step <?php echo (in_array($order['status'], ['delivered', 'received'])) ? 'active' : ''; ?>">
                                                            <div class="step-icon"><i class="fas fa-box-open"></i></div>
                                                            <div class="step-label">Delivered</div>
                                                        </div>
                                                        <div class="tracking-step <?php echo ($order['status'] == 'received') ? 'active' : ''; ?>">
                                                            <div class="step-icon"><i class="fas fa-check-circle"></i></div>
                                                            <div class="step-label">Received</div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </section>
                    </div>
                </div>
            </div>
        </main>
        
        <?php include 'includes/footer.php'; ?>
    </div>

    <!-- Order Details Modal -->
<div id="order-details-modal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2 class="modal-title">Order Details</h2>
            <button type="button" class="modal-close">&times;</button>
        </div>
        <div class="modal-body">
            <div class="order-info-section">
                <div class="order-info-header">
                    <h3>Order #<span id="modal-order-id"></span></h3>
                    <div class="order-status" id="modal-order-status"></div>
                </div>
                <div class="order-info-details">
                    <div class="info-group">
                        <p><strong>Date Placed:</strong> <span id="modal-order-date"></span></p>
                        <p><strong>Payment Method:</strong> <span id="modal-payment-method"></span></p>
                    </div>
                    <div class="info-group">
                        <p><strong>Subtotal:</strong> ₱<span id="modal-subtotal"></span></p>
                        <p><strong>Total:</strong> ₱<span id="modal-total"></span></p>
                    </div>
                </div>
            </div>
            
            <div class="shipping-info-section">
                <h3>Shipping Address</h3>
                <div id="modal-shipping-address"></div>
            </div>
            
            <div class="order-items-section">
                <h3>Order Items</h3>
                <div class="modal-order-items" id="modal-order-items">
                    <!-- Order items will be populated here -->
                </div>
            </div>
            
            <div class="order-tracking-section">
                <h3>Order Status</h3>
                <div class="tracking-timeline" id="modal-tracking-timeline">
                    <!-- Tracking timeline will be populated here -->
                </div>
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-outline modal-close-btn">Close</button>
        </div>
    </div>
</div>

    <!-- Toast container -->
    <div id="toast-container" class="toast-container"></div>

    <script src="js/main.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Tab navigation
            const navLinks = document.querySelectorAll('.account-nav-link');
            const sections = document.querySelectorAll('.account-section');
            
            navLinks.forEach(link => {
                link.addEventListener('click', function(e) {
                    e.preventDefault();
                    
                    const targetId = this.getAttribute('data-section');
                    
                    // Hide all sections
                    sections.forEach(section => {
                        section.classList.remove('active');
                    });
                    
                    // Show target section
                    document.getElementById(targetId).classList.add('active');
                    
                    // Update active nav link
                    navLinks.forEach(navLink => {
                        navLink.parentElement.classList.remove('active');
                    });
                    this.parentElement.classList.add('active');
                });
            });
            
            // Profile picture preview
            const profilePictureInput = document.getElementById('profile_picture');
            const currentPicture = document.querySelector('.current-picture');
            
            if (profilePictureInput) {
                profilePictureInput.addEventListener('change', function() {
                    if (this.files && this.files[0]) {
                        const reader = new FileReader();
                        
                        reader.onload = function(e) {
                            // Remove existing content
                            currentPicture.innerHTML = '';
                            
                            // Create and add new image
                            const img = document.createElement('img');
                            img.src = e.target.result;
                            img.alt = 'Profile Picture Preview';
                            currentPicture.appendChild(img);
                        };
                        
                        reader.readAsDataURL(this.files[0]);
                    }
                });
            }
            
            // Toggle password visibility
            const togglePasswordButtons = document.querySelectorAll('.toggle-password');
            
            togglePasswordButtons.forEach(button => {
                button.addEventListener('click', function() {
                    const targetId = this.getAttribute('data-target');
                    const passwordInput = document.getElementById(targetId);
                    const icon = this.querySelector('i');
                    
                    if (passwordInput.type === 'password') {
                        passwordInput.type = 'text';
                        icon.classList.remove('fa-eye');
                        icon.classList.add('fa-eye-slash');
                    } else {
                        passwordInput.type = 'password';
                        icon.classList.remove('fa-eye-slash');
                        icon.classList.add('fa-eye');
                    }
                });
            });
            
            // Address management
            const addAddressBtn = document.getElementById('add-address-btn');
            const cancelAddressBtn = document.getElementById('cancel-address-btn');
            const addressFormContainer = document.getElementById('address-form-container');
            const addressForm = document.getElementById('address-form');
            const addressIdInput = document.getElementById('address_id');
            
            // Show address form when Add New Address button is clicked
            if (addAddressBtn) {
                addAddressBtn.addEventListener('click', function() {
                    // Reset form
                    addressForm.reset();
                    addressIdInput.value = '0';
                    
                    // Show form
                    addressFormContainer.style.display = 'block';
                    this.style.display = 'none';
                    
                    // Scroll to form
                    addressFormContainer.scrollIntoView({ behavior: 'smooth' });
                });
            }
            
            // Hide address form when Cancel button is clicked
            if (cancelAddressBtn) {
                cancelAddressBtn.addEventListener('click', function() {
                    addressFormContainer.style.display = 'none';
                    addAddressBtn.style.display = 'block';
                });
            }
            
            // Edit address
            const editAddressBtns = document.querySelectorAll('.btn-edit-address');
            
            editAddressBtns.forEach(button => {
                button.addEventListener('click', function() {
                    const addressId = this.getAttribute('data-address-id');
                    const addressCard = this.closest('.address-card');
                    
                    // Get address data from the card
                    const addressLines = addressCard.querySelectorAll('.address-line');
                    const addressLine1 = addressLines[0].textContent;
                    const addressLine2 = addressLines.length > 2 ? addressLines[1].textContent : '';
                    
                    // Parse city, state, postal code
                    const cityStateZip = addressLines[addressLines.length > 2 ? 2 : 1].textContent.split(', ');
                    const city = cityStateZip[0];
                    const stateZip = cityStateZip[1].split(' ');
                    const state = stateZip[0];
                    const postalCode = stateZip[1];
                    
                    // Check if address is primary
                    const isPrimary = addressCard.querySelector('.primary-badge') !== null;
                    
                    // Fill form with address data
                    addressIdInput.value = addressId;
                    document.getElementById('address_line1').value = addressLine1;
                    document.getElementById('address_line2').value = addressLine2;
                    document.getElementById('city').value = city;
                    document.getElementById('state').value = state;
                    document.getElementById('postal_code').value = postalCode;
                    document.getElementById('is_primary').checked = isPrimary;
                    
                    // Show form
                    addressFormContainer.style.display = 'block';
                    addAddressBtn.style.display = 'none';
                    
                    // Scroll to form
                    addressFormContainer.scrollIntoView({ behavior: 'smooth' });
                });
            });
            
            // Order filtering
            const orderFilterBtns = document.querySelectorAll('.order-filter-btn');
            const orderCards = document.querySelectorAll('.order-card');
            const ordersList = document.querySelector('.orders-list');
            
            orderFilterBtns.forEach(btn => {
                btn.addEventListener('click', function() {
                    const filter = this.getAttribute('data-filter');
                    
                    // Update active filter button
                    orderFilterBtns.forEach(filterBtn => {
                        filterBtn.classList.remove('active');
                    });
                    this.classList.add('active');
                    
                    // Remove any existing "no orders" message
                    const existingMessage = document.querySelector('.no-filtered-orders');
                    if (existingMessage) {
                        existingMessage.remove();
                    }
                    
                    // Count visible orders
                    let visibleCount = 0;
                    
                    // Filter orders
                    orderCards.forEach(card => {
                        const cardStatus = card.getAttribute('data-status');
                        if (filter === 'all' || cardStatus === filter) {
                            card.style.display = 'block';
                            visibleCount++;
                        } else {
                            card.style.display = 'none';
                        }
                    });
                    
                    // Show message if no orders match the filter
                    if (visibleCount === 0) {
                        const message = document.createElement('div');
                        message.className = 'no-filtered-orders';
                        message.style.textAlign = 'center';
                        message.style.padding = '30px';
                        message.style.backgroundColor = '#f9f9f9';
                        message.style.borderRadius = '10px';
                        message.style.marginTop = '20px';
                        message.innerHTML = `<p>No ${filter === 'all' ? '' : filter} orders found.</p>`;
                        
                        ordersList.appendChild(message);
                    }
                });
            });

    // Add this to the existing DOMContentLoaded event listener
    // Modal functionality
    const modal = document.getElementById('order-details-modal');
    const closeButtons = document.querySelectorAll('.modal-close, .modal-close-btn');
    const viewDetailsBtns = document.querySelectorAll('.btn-outline.btn-sm');
    
    // Close modal when clicking close button or outside the modal
    closeButtons.forEach(button => {
        button.addEventListener('click', () => {
            modal.style.display = 'none';
            document.body.style.overflow = 'auto';
        });
    });
    
    window.addEventListener('click', (event) => {
        if (event.target === modal) {
            modal.style.display = 'none';
            document.body.style.overflow = 'auto';
        }
    });
    
    // Open modal with order details when View Details button is clicked
    viewDetailsBtns.forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            
            const orderCard = this.closest('.order-card');
            const orderId = orderCard.querySelector('.order-info h3').textContent.replace('Order #', '');
            const orderDate = orderCard.querySelector('.order-date').textContent.replace('Placed on ', '');
            const orderStatus = orderCard.querySelector('.order-status').textContent.trim();
            const orderTotal = orderCard.querySelector('.order-summary p:first-child').textContent.replace('Total: ₱', '');
            const paymentMethod = orderCard.querySelector('.order-summary p:last-child').textContent.replace('Payment Method: ', '');
            
            // Get order items
            const orderItemsContainer = orderCard.querySelector('.order-items-grid');
            const orderItems = orderItemsContainer ? orderItemsContainer.innerHTML : '<p>No items available</p>';
            
            // Get tracking timeline
            const trackingTimeline = orderCard.querySelector('.tracking-timeline').innerHTML;
            
            // Populate modal with order details
            document.getElementById('modal-order-id').textContent = orderId;
            document.getElementById('modal-order-date').textContent = orderDate;
            document.getElementById('modal-order-status').textContent = orderStatus;
            document.getElementById('modal-order-status').className = 'order-status ' + orderStatus.toLowerCase();
            document.getElementById('modal-total').textContent = orderTotal;
            document.getElementById('modal-subtotal').textContent = orderTotal; // Using total as subtotal since we don't have subtotal in the card
            document.getElementById('modal-payment-method').textContent = paymentMethod;
            document.getElementById('modal-order-items').innerHTML = orderItems;
            document.getElementById('modal-tracking-timeline').innerHTML = trackingTimeline;
            
            // Show loading message in shipping address section
            document.getElementById('modal-shipping-address').innerHTML = '<p>Loading address information...</p>';
            
            // Fetch shipping address from the server using AJAX
            fetchOrderDetails(orderId);
            
            // Show modal
            modal.style.display = 'block';
            document.body.style.overflow = 'hidden';
        });
    });
    

    // Function to fetch order details from the server
    function fetchOrderDetails(orderId) {
        // Fetch the shipping address for this order
        fetch('account.php?action=get_order_address&order_id=' + orderId)
            .then(response => {
                if (!response.ok) {
                    throw new Error('Network response was not ok');
                }
                return response.json();
            })
            .then(data => {
                if (data.success) {
                    const address = data.address;
                    let addressHtml = `
                        <p>${address.address_line1}</p>
                    `;
                    
                    if (address.address_line2) {
                        addressHtml += `<p>${address.address_line2}</p>`;
                    }
                    
                    addressHtml += `
                        <p>${address.city}, ${address.state} ${address.postal_code}</p>
                    `;
                    
                    document.getElementById('modal-shipping-address').innerHTML = addressHtml;
                } else {
                    document.getElementById('modal-shipping-address').innerHTML = '<p>Address information not available: ' + data.message + '</p>';
                }
            })
            .catch(error => {
                console.error('Error fetching order address:', error);
                document.getElementById('modal-shipping-address').innerHTML = '<p>Error loading address information. Please try again later.</p>';
            });
    }

    // Function to show a toast notification
    function showToast(type, message) {
        const toastContainer = document.getElementById('toast-container');
        const toast = document.createElement('div');
        toast.className = `toast toast-${type}`;

        const iconDiv = document.createElement('div');
        iconDiv.className = 'toast-icon';
        let icon = '';
        if (type === 'success') {
            icon = '<i class="fas fa-check-circle"></i>';
        } else if (type === 'error') {
            icon = '<i class="fas fa-times-circle"></i>';
        }
        iconDiv.innerHTML = icon;

        const contentDiv = document.createElement('div');
        contentDiv.className = 'toast-content';
        contentDiv.innerHTML = `
            <h6 class="toast-title">${type === 'success' ? 'Success' : 'Error'}</h6>
            <p class="toast-message">${message}</p>
        `;

        const closeButton = document.createElement('button');
        closeButton.className = 'toast-close';
        closeButton.innerHTML = '&times;';
        closeButton.addEventListener('click', () => {
            toast.classList.add('hide');
            toast.addEventListener('animationend', () => {
                toast.remove();
            }, { once: true });
        });

        toast.appendChild(iconDiv);
        toast.appendChild(contentDiv);
        toast.appendChild(closeButton);
        toastContainer.appendChild(toast);

        // Automatically remove the toast after a delay
        setTimeout(() => {
            toast.classList.add('hide');
            toast.addEventListener('animationend', () => {
                toast.remove();
            }, { once: true });
        }, 5000);
    }

    // Event listeners for cancel and mark as received buttons
    document.querySelectorAll('.cancel-order-btn').forEach(button => {
        button.addEventListener('click', function() {
            const orderId = this.dataset.orderId;
            if (confirm('Are you sure you want to cancel this order?')) {
                // Send AJAX request to cancel the order
                fetch('account.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded'
                    },
                    body: `ajax_action=cancel_order&order_id=${orderId}`
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showToast('success', data.message);
                        // Optionally, update the order status on the page
                        const orderCard = this.closest('.order-card');
                        orderCard.dataset.status = 'cancelled';
                        orderCard.querySelector('.order-status').textContent = 'Cancelled';
                        orderCard.querySelector('.order-status').className = 'order-status cancelled';
                        this.style.display = 'none'; // Hide the cancel button
                    } else {
                        showToast('error', data.message);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    showToast('error', 'An error occurred. Please try again.');
                });
            }
        });
    });

    document.querySelectorAll('.mark-received-btn').forEach(button => {
        button.addEventListener('click', function() {
            const orderId = this.dataset.orderId;
            if (confirm('Confirm that you have received this order?')) {
                // Send AJAX request to mark the order as received
                fetch('account.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded'
                    },
                    body: `ajax_action=mark_received&order_id=${orderId}`
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showToast('success', data.message);
                        // Optionally, update the order status on the page
                        const orderCard = this.closest('.order-card');
                        orderCard.dataset.status = 'received';
                        orderCard.querySelector('.order-status').textContent = 'Received';
                        orderCard.querySelector('.order-status').className = 'order-status received';
                        this.style.display = 'none'; // Hide the mark as received button
                    } else {
                        showToast('error', data.message);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    showToast('error', 'An error occurred. Please try again.');
                });
            }
        });
    });

// Add the JavaScript for the Rate Products button (around line 1000-1100)
// Add this after the existing event listeners for mark-received-btn:

// Add event listeners to the "Rate Products" buttons
document.querySelectorAll('.rate-products-btn').forEach(button => {
    button.addEventListener('click', function() {
        const orderId = this.dataset.orderId;
        const orderCard = this.closest('.order-card');
        const orderItemsGrid = orderCard.querySelector('.order-items-grid');
        
        if (orderItemsGrid) {
            // Get the first product in the order to rate
            const firstItem = orderItemsGrid.querySelector('.order-item');
            if (firstItem) {
                const productImage = firstItem.querySelector('.order-item-image img');
                const productName = firstItem.querySelector('.order-item-name');
                const productPrice = firstItem.querySelector('.order-item-price');
                
                // Extract product ID from image source or data attribute
                let productId;
                if (productImage.src.includes('/')) {
                    // Try to extract from the image filename
                    const filename = productImage.src.split('/').pop();
                    productId = filename.split('.')[0];
                    // If it's not a number, try to find it another way
                    if (isNaN(parseInt(productId))) {
                        // Look for a data attribute or try to extract from another source
                        productId = firstItem.dataset.productId || 1; // Fallback to 1 if not found
                    }
                } else {
                    productId = firstItem.dataset.productId || 1;
                }
                
                // Populate the rating modal with product information
                document.getElementById('rating-product-id').value = productId;
                document.getElementById('rating-order-id').value = orderId;
                document.getElementById('rating-product-img').src = productImage.src;
                document.getElementById('rating-product-name').textContent = productName.textContent;
                document.getElementById('rating-product-price').textContent = productPrice.textContent;
                
                // Show the rating modal
                document.getElementById('rating-modal').style.display = 'block';
                document.body.style.overflow = 'hidden';
            } else {
                showToast('error', 'No products found in this order');
            }
        } else {
            showToast('error', 'No products found in this order');
        }
    });
});

// AJAX form submissions for order actions
const cancelOrderForms = document.querySelectorAll('.cancel-order-form');
const receiveOrderForms = document.querySelectorAll('.receive-order-form');

// Function to show notification
function showNotification(message, type = 'success') {
    // Create notification element
    const notification = document.createElement('div');
    notification.className = `alert alert-${type}`;
    notification.style.position = 'fixed';
    notification.style.top = '20px';
    notification.style.right = '20px';
    notification.style.zIndex = '9999';
    notification.style.maxWidth = '300px';
    notification.style.padding = '15px';
    notification.style.borderRadius = '5px';
    notification.style.boxShadow = '0 4px 8px rgba(0,0,0,0.1)';
    notification.style.animation = 'slideIn 0.3s ease-out forwards';
    
    if (type === 'success') {
        notification.style.backgroundColor = '#dcfce7';
        notification.style.color = '#059669';
        notification.style.borderLeft = '4px solid #059669';
    } else {
        notification.style.backgroundColor = '#fee2e2';
        notification.style.color = '#dc2626';
        notification.style.borderLeft = '4px solid #dc2626';
    }
    
    notification.innerHTML = message;
    
    // Add to document
    document.body.appendChild(notification);
    
    // Remove after 5 seconds
    setTimeout(() => {
        notification.style.animation = 'slideOut 0.3s ease-out forwards';
        setTimeout(() => {
            document.body.removeChild(notification);
        }, 300);
    }, 5000);
}

// Add keyframes for animations
const style = document.createElement('style');
style.textContent = `
    @keyframes slideIn {
        from { transform: translateX(100%); opacity: 0; }
        to { transform: translateX(0); opacity: 1; }
    }
    @keyframes slideOut {
        from { transform: translateX(0); opacity: 1; }
        to { transform: translateX(100%); opacity: 0; }
    }
`;
document.head.appendChild(style);

// Function to update order card status
function updateOrderCard(orderId, newStatus) {
    const orderCard = document.querySelector(`.order-card:has(.order-info h3:contains("Order #${orderId}"))`);
    if (!orderCard) return;
    
    // Update status badge
    const statusBadge = orderCard.querySelector('.order-status');
    statusBadge.className = `order-status ${newStatus}`;
    statusBadge.textContent = newStatus.charAt(0).toUpperCase() + newStatus.slice(1);
    
    // Update data-status attribute
    orderCard.setAttribute('data-status', newStatus);
    
    // Update tracking timeline
    const trackingSteps = orderCard.querySelectorAll('.tracking-step');
    
    if (newStatus === 'cancelled') {
        // For cancelled orders, only the first step should be active and cancelled
        trackingSteps.forEach((step, index) => {
            if (index === 0) {
                step.className = 'tracking-step cancelled';
            } else {
                step.className = 'tracking-step';
            }
        });
    } else if (newStatus === 'received') {
        // For received orders, all steps should be active
        trackingSteps.forEach(step => {
            step.className = 'tracking-step active';
        });
    }
    
    // Remove action buttons
    const orderActions = orderCard.querySelector('.order-actions');
    const actionForms = orderActions.querySelectorAll('form');
    actionForms.forEach(form => {
        form.remove();
    });
}

// Handle cancel order forms
cancelOrderForms.forEach(form => {
    form.addEventListener('submit', function(e) {
        e.preventDefault();
        
        if (confirm('Are you sure you want to cancel this order?')) {
            const formData = new FormData(this);
            const orderId = formData.get('order_id');
            
            // Add action parameter
            formData.append('action', 'cancel_order');
            
            fetch('ajax-handler.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showNotification(data.message, 'success');
                    updateOrderCard(data.order_id, data.new_status);
                } else {
                    showNotification(data.message, 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showNotification('An error occurred. Please try again.', 'error');
            });
        }
    });
});

// Handle receive order forms
receiveOrderForms.forEach(form => {
    form.addEventListener('submit', function(e) {
        e.preventDefault();
        
        if (confirm('Confirm that you have received this order?')) {
            const formData = new FormData(this);
            const orderId = formData.get('order_id');
            
            // Add action parameter
            formData.append('action', 'mark_received');
            
            fetch('ajax-handler.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showNotification(data.message, 'success');
                    updateOrderCard(data.order_id, data.new_status);
                } else {
                    showNotification(data.message, 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showNotification('An error occurred. Please try again.', 'error');
            });
        }
    });
});

// Fix for the :has selector in older browsers
document.querySelectorAll = function(selector) {
    if (selector.includes(':has')) {
        // For the specific case of finding order cards by order ID
        if (selector.includes('.order-card:has(.order-info h3:contains("Order #')) {
            const orderId = selector.match(/Order #(\d+)/)[1];
            const allCards = document.querySelectorAll('.order-card');
            return Array.from(allCards).filter(card => {
                const orderIdText = card.querySelector('.order-info h3').textContent;
                return orderIdText.includes(`Order #${orderId}`);
            });
        }
        return document.querySelectorAll('.order-card');
    }
    return document.querySelectorAll(selector);
};
});
    </script>

<!-- Product Rating Modal -->
<div id="rating-modal" class="modal">
    <div class="modal-content" style="max-width: 500px;">
        <div class="modal-header">
            <h2 class="modal-title">Rate Your Purchase</h2>
            <button type="button" class="modal-close">&times;</button>
        </div>
        <div class="modal-body">
            <div class="rating-product-info" style="display: flex; align-items: center; margin-bottom: 20px;">
                <div class="rating-product-image" style="width: 80px; height: 80px; overflow: hidden; margin-right: 15px; background-color: #f9f9f9; border-radius: 8px;">
                    <img id="rating-product-img" src="/placeholder.svg" alt="Product" style="width: 100%; height: 100%; object-fit: contain;">
                </div>
                <div class="rating-product-details">
                    <h4 id="rating-product-name" style="margin: 0 0 5px; font-size: 16px;"></h4>
                    <p id="rating-product-price" style="margin: 0; color: #666; font-size: 14px;"></p>
                </div>
            </div>
            
            <div class="rating-stars-container" style="text-align: center; margin-bottom: 20px;">
                <p style="margin-bottom: 10px; font-size: 16px;">How would you rate this product?</p>
                <div class="rating-stars" style="font-size: 40px; color: #ccc; cursor: pointer;">
                    <span class="star" data-rating="1">★</span>
                    <span class="star" data-rating="2">★</span>
                    <span class="star" data-rating="3">★</span>
                    <span class="star" data-rating="4">★</span>
                    <span class="star" data-rating="5">★</span>
                </div>
                <p id="rating-text" style="margin-top: 10px; font-size: 14px; height: 20px;">Click to rate</p>
            </div>
            
            <input type="hidden" id="rating-product-id" value="">
            <input type="hidden" id="rating-order-id" value="">
            <input type="hidden" id="rating-value" value="0">
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-outline modal-close-btn">Cancel</button>
            <button type="button" class="btn btn-primary" id="submit-rating-btn" disabled>Submit Rating</button>
        </div>
    </div>
</div>

<style>
    .rating-stars .star {
        display: inline-block;
        transition: color 0.2s ease;
    }
    
    .rating-stars .star.active {
        color: #FFD700;
    }
    
    .rating-stars .star:hover,
    .rating-stars .star.hover {
        color: #FFED85;
    }
    
    #submit-rating-btn:disabled {
        opacity: 0.6;
        cursor: not-allowed;
    }
    
    /* Animation for the rating modal */
    #rating-modal .modal-content {
        animation: zoomIn 0.3s;
    }
    
    @keyframes zoomIn {
        from { transform: scale(0.8); opacity: 0; }
        to { transform: scale(1); opacity: 1; }
    }
</style>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Existing code...

            // Add this new code for handling product ratings
            // Rating Modal Functionality
            const ratingModal = document.getElementById('rating-modal');
            const ratingCloseButtons = document.querySelectorAll('#rating-modal .modal-close, #rating-modal .modal-close-btn');
            const ratingStars = document.querySelectorAll('.rating-stars .star');
            const ratingText = document.getElementById('rating-text');
            const ratingProductId = document.getElementById('rating-product-id');
            const ratingOrderId = document.getElementById('rating-order-id');
            const ratingValue = document.getElementById('rating-value');
            const submitRatingBtn = document.getElementById('submit-rating-btn');
            const ratingProductImg = document.getElementById('rating-product-img');
            const ratingProductName = document.getElementById('rating-product-name');
            const ratingProductPrice = document.getElementById('rating-product-price');

            // Close rating modal
            ratingCloseButtons.forEach(button => {
                button.addEventListener('click', () => {
                    ratingModal.style.display = 'none';
                    document.body.style.overflow = 'auto';
                    resetRating();
                });
            });

            // Reset rating function
            function resetRating() {
                ratingStars.forEach(star => star.classList.remove('active', 'hover'));
                ratingValue.value = 0;
                ratingText.textContent = 'Click to rate';
                submitRatingBtn.disabled = true;
            }

            // Star rating functionality
            ratingStars.forEach(star => {
                star.addEventListener('click', function() {
                    const rating = parseInt(this.dataset.rating);

                    ratingStars.forEach(s => {
                        if (parseInt(s.dataset.rating) <= rating) {
                            s.classList.add('active');
                        } else {
                            s.classList.remove('active');
                        }
                    });

                    ratingValue.value = rating;
                    ratingText.textContent = `You rated this ${rating} star(s)`;
                    submitRatingBtn.disabled = false;
                });

                star.addEventListener('mouseover', function() {
                    const rating = parseInt(this.dataset.rating);

                    ratingStars.forEach(s => {
                        if (parseInt(s.dataset.rating) <= rating) {
                            s.classList.add('hover');
                        } else {
                            s.classList.remove('hover');
                        }
                    });
                });

                star.addEventListener('mouseout', function() {
                    ratingStars.forEach(s => s.classList.remove('hover'));
                });
            });

            // Submit rating functionality
            submitRatingBtn.addEventListener('click', function() {
                const productId = ratingProductId.value;
                const orderId = ratingOrderId.value;
                const rating = ratingValue.value;

                // Send AJAX request to submit the rating
                fetch('account.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded'
                    },
                    body: `ajax_action=submit_rating&product_id=${productId}&order_id=${orderId}&rating=${rating}&form_type=submit_rating`
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showToast('success', data.message);
                        ratingModal.style.display = 'none';
                        document.body.style.overflow = 'auto';
                        resetRating();

                        // Update the rating and reviews count on the page if needed
                        // For example, you can update the order card with the new rating
                    } else {
                        showToast('error', data.message);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    showToast('error', 'An error occurred. Please try again.');
                });
            });

            // Add event listeners to the "Mark as Received" buttons
            document.querySelectorAll('.mark-received-btn').forEach(button => {
                button.addEventListener('click', function() {
                    const orderId = this.dataset.orderId;
                    const orderCard = this.closest('.order-card'); // Get the order card
                    const orderItemsGrid = orderCard.querySelector('.order-items-grid'); // Get the order items grid

                    if (confirm('Confirm that you have received this order?')) {
                        // Send AJAX request to mark the order as received
                        fetch('account.php', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/x-www-form-urlencoded'
                            },
                            body: `ajax_action=mark_received&order_id=${orderId}`
                        })
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                showToast('success', data.message);
                                // Optionally, update the order status on the page
                                orderCard.dataset.status = 'received';
                                orderCard.querySelector('.order-status').textContent = 'Received';
                                orderCard.querySelector('.order-status').className = 'order-status received';
                                this.style.display = 'none'; // Hide the mark as received button

                                // Open the rating modal for each product in the order
                                if (orderItemsGrid) {
                                    const orderItems = orderItemsGrid.querySelectorAll('.order-item');
                                    orderItems.forEach(item => {
                                        const productId = item.querySelector('.order-item-image img').src.split('/').pop().split('.')[0]; // Extract product ID from image source
                                        const productName = item.querySelector('.order-item-name').textContent;
                                        const productPrice = item.querySelector('.order-item-price').textContent;
                                        const productImage = item.querySelector('.order-item-image img').src;

                                        // Populate the rating modal with product information
                                        ratingProductId.value = productId;
                                        ratingOrderId.value = orderId;
                                        ratingProductImg.src = productImage;
                                        ratingProductName.textContent = productName;
                                        ratingProductPrice.textContent = productPrice;

                                        // Show the rating modal
                                        ratingModal.style.display = 'block';
                                        document.body.style.overflow = 'hidden';
                                    });
                                }
                            } else {
                                showToast('error', data.message);
                            }
                        })
                        .catch(error => {
                            console.error('Error:', error);
                            showToast('error', 'An error occurred. Please try again.');
                        });
                    }
                });
            });

            // Existing code...
        });
    </script>
</body>
</html>
<?php
// Add this new PHP function at the end of the file, just before the closing PHP tag (before the DOCTYPE)
// This will be our AJAX endpoint to get the shipping address for an order

function getOrderShippingAddress($orderId, $conn) {
    try {
        // Get the shipping_address_id for the order
        $stmt = $conn->prepare("SELECT shipping_address_id FROM orders WHERE id = :order_id");
        $stmt->bindParam(':order_id', $orderId);
        $stmt->execute();
        $order = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$order) {
            return null;
        }
        
        // Get the address details
        $addressId = $order['shipping_address_id'];
        $stmt = $conn->prepare("SELECT * FROM user_addresses WHERE id = :address_id");
        $stmt->bindParam(':address_id', $addressId);
        $stmt->execute();
        $address = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $address;
    } catch (PDOException $e) {
        return null;
    }
}

// Add an AJAX endpoint to get order shipping address
if (isset($_GET['action']) && $_GET['action'] === 'get_order_address' && isset($_GET['order_id'])) {
    $orderId = intval($_GET['order_id']);
    $address = getOrderShippingAddress($orderId, $conn);
    
    header('Content-Type: application/json');
    if ($address) {
        echo json_encode([
            'success' => true,
            'address' => $address
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Address not found'
        ]);
    }
    exit;
}

?>
