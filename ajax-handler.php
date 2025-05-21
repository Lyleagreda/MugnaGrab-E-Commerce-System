<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit;
}

// Include database connection
include 'db.php';

// Include wishlist functions if needed
if (isset($_POST['action']) && ($_POST['action'] === 'toggle_wishlist' || $_POST['action'] === 'check_wishlist')) {
    include 'includes/wishlist_functions.php';
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
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'User not found']);
        exit;
    }
} catch (PDOException $e) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    exit;
}

// Process AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    // Cancel order
    if ($action === 'cancel_order') {
        $orderId = isset($_POST['order_id']) ? intval($_POST['order_id']) : 0;
        
        if ($orderId <= 0) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Invalid order ID']);
            exit;
        }
        
        try {
            // First check if the order belongs to the user and is in pending status
            $stmt = $conn->prepare("SELECT * FROM orders WHERE id = :id AND user_id = :user_id AND status = 'pending'");
            $stmt->bindParam(':id', $orderId);
            $stmt->bindParam(':user_id', $userId);
            $stmt->execute();
            
            if ($stmt->rowCount() === 0) {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => 'Order not found or cannot be cancelled']);
                exit;
            }
            
            // Update order status to cancelled
            $stmt = $conn->prepare("UPDATE orders SET status = 'cancelled', updated_at = NOW() WHERE id = :id");
            $stmt->bindParam(':id', $orderId);
            
            if ($stmt->execute()) {
                header('Content-Type: application/json');
                echo json_encode([
                    'success' => true, 
                    'message' => "Order #$orderId has been cancelled successfully!",
                    'order_id' => $orderId,
                    'new_status' => 'cancelled'
                ]);
                exit;
            } else {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => 'Failed to cancel order']);
                exit;
            }
        } catch (PDOException $e) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
            exit;
        }
    }
    
    // Mark order as received
    elseif ($action === 'mark_received') {
        $orderId = isset($_POST['order_id']) ? intval($_POST['order_id']) : 0;
        
        if ($orderId <= 0) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Invalid order ID']);
            exit;
        }
        
        try {
            // First check if the order belongs to the user and is in delivered status
            $stmt = $conn->prepare("SELECT * FROM orders WHERE id = :id AND user_id = :user_id AND status = 'delivered'");
            $stmt->bindParam(':id', $orderId);
            $stmt->bindParam(':user_id', $userId);
            $stmt->execute();
            
            if ($stmt->rowCount() === 0) {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => 'Order not found or cannot be marked as received']);
                exit;
            }
            
            // Update order status to received
            $stmt = $conn->prepare("UPDATE orders SET status = 'received', updated_at = NOW() WHERE id = :id");
            $stmt->bindParam(':id', $orderId);
            
            if ($stmt->execute()) {
                header('Content-Type: application/json');
                echo json_encode([
                    'success' => true, 
                    'message' => "Order #$orderId has been marked as received. Thank you!",
                    'order_id' => $orderId,
                    'new_status' => 'received'
                ]);
                exit;
            } else {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => 'Failed to update order status']);
                exit;
            }
        } catch (PDOException $e) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
            exit;
        }
    }
    
    // Toggle wishlist
    elseif ($action === 'toggle_wishlist') {
        $productId = isset($_POST['product_id']) ? intval($_POST['product_id']) : 0;
        
        if ($productId <= 0) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Invalid product ID']);
            exit;
        }
        
        try {
            $result = toggleWishlistItem($userId, $productId, $conn);
            
            header('Content-Type: application/json');
            echo json_encode($result);
            exit;
        } catch (PDOException $e) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
            exit;
        }
    }
    
    // Check if product is in wishlist
    elseif ($action === 'check_wishlist') {
        $productId = isset($_POST['product_id']) ? intval($_POST['product_id']) : 0;
        
        if ($productId <= 0) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Invalid product ID']);
            exit;
        }
        
        try {
            $inWishlist = isInWishlist($userId, $productId, $conn);
            
            header('Content-Type: application/json');
            echo json_encode([
                'success' => true,
                'in_wishlist' => $inWishlist
            ]);
            exit;
        } catch (PDOException $e) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
            exit;
        }
    }
    
    // Invalid action
    else {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
        exit;
    }
}

// Invalid request method
header('Content-Type: application/json');
echo json_encode(['success' => false, 'message' => 'Invalid request method']);
exit;
?>
