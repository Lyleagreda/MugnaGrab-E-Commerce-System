<?php
// Enable error reporting for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();

// Check if user is logged in
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => 'You must be logged in to rate products'
    ]);
    exit;
}

// Include database connection
include 'db.php';

// Include products data
include 'data/products.php';

// Get user ID from session
$userEmail = $_SESSION['user_email'] ?? '';

// Fetch user data
try {
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
} catch (PDOException $e) {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
    exit;
}

// Handle GET request to fetch order items for rating
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'get_order_items') {
    $orderId = isset($_GET['order_id']) ? intval($_GET['order_id']) : 0;
    
    if ($orderId <= 0) {
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'message' => 'Invalid order ID'
        ]);
        exit;
    }
    
    try {
        // Check if the order belongs to the user and is in received status
        $stmt = $conn->prepare("SELECT * FROM orders WHERE id = :id AND user_id = :user_id AND status = 'received'");
        $stmt->bindParam(':id', $orderId);
        $stmt->bindParam(':user_id', $userId);
        $stmt->execute();
        
        if ($stmt->rowCount() === 0) {
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'message' => 'Order not found or cannot be rated'
            ]);
            exit;
        }
        
        // Fetch order items that haven't been rated yet
        $stmt = $conn->prepare("
            SELECT oi.* 
            FROM order_items oi
            LEFT JOIN product_ratings pr ON pr.product_id = oi.product_id AND pr.order_id = :order_id AND pr.user_id = :user_id
            WHERE oi.order_id = :order_id AND pr.id IS NULL
        ");
        $stmt->bindParam(':order_id', $orderId);
        $stmt->bindParam(':user_id', $userId);
        $stmt->execute();
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (empty($items)) {
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'message' => 'No items found or all items have already been rated'
            ]);
            exit;
        }
        
        // Create a lookup array for products by ID
        $productsById = [];
        foreach ($products as $product) {
            $productsById[$product['id']] = $product;
        }
        
        // Enhance items with product details
        $enhancedItems = [];
        foreach ($items as $item) {
            $productId = $item['product_id'];
            if (isset($productsById[$productId])) {
                $enhancedItems[] = [
                    'product_id' => $productId,
                    'name' => $item['product_name'],
                    'price' => $item['price'],
                    'quantity' => $item['quantity'],
                    'image' => $productsById[$productId]['image'] ?? 'images/products/default.jpg',
                    'current_rating' => $productsById[$productId]['rating'] ?? 0,
                    'current_reviews' => $productsById[$productId]['reviews'] ?? 0
                ];
            }
        }
        
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'items' => $enhancedItems
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

// Handle POST request to submit ratings
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get JSON data from request body
    $json = file_get_contents('php://input');
    $data = json_decode($json, true);
    
    if (!$data || !isset($data['action']) || $data['action'] !== 'submit_ratings' || !isset($data['order_id']) || !isset($data['ratings']) || empty($data['ratings'])) {
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'message' => 'Invalid request data'
        ]);
        exit;
    }
    
    $orderId = intval($data['order_id']);
    $ratings = $data['ratings'];
    
    try {
        // Check if the order belongs to the user and is in received status
        $stmt = $conn->prepare("SELECT * FROM orders WHERE id = :id AND user_id = :user_id AND status = 'received'");
        $stmt->bindParam(':id', $orderId);
        $stmt->bindParam(':user_id', $userId);
        $stmt->execute();
        
        if ($stmt->rowCount() === 0) {
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'message' => 'Order not found or cannot be rated'
            ]);
            exit;
        }
        
        // Begin transaction
        $conn->beginTransaction();
        
        // Make sure the product_ratings table exists
        $conn->exec("
            CREATE TABLE IF NOT EXISTS product_ratings (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NOT NULL,
                product_id INT NOT NULL,
                order_id INT NOT NULL,
                rating INT NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY unique_rating (user_id, product_id, order_id)
            )
        ");
        
        // Insert product ratings
        $stmt = $conn->prepare("
            INSERT INTO product_ratings (user_id, product_id, order_id, rating)
            VALUES (:user_id, :product_id, :order_id, :rating)
            ON DUPLICATE KEY UPDATE rating = :rating
        ");
        
        foreach ($ratings as $rating) {
            $productId = intval($rating['product_id']);
            $ratingValue = intval($rating['rating']);
            
            if ($productId <= 0 || $ratingValue < 1 || $ratingValue > 5) {
                continue;
            }
            
            $stmt->bindParam(':user_id', $userId);
            $stmt->bindParam(':product_id', $productId);
            $stmt->bindParam(':order_id', $orderId);
            $stmt->bindParam(':rating', $ratingValue);
            $stmt->execute();
            
            // Update product ratings in the products array
            foreach ($products as &$product) {
                if ($product['id'] == $productId) {
                    // Calculate new average rating
                    $currentRating = $product['rating'] ?? 0;
                    $currentReviews = $product['reviews'] ?? 0;
                    
                    $totalRatingPoints = $currentRating * $currentReviews;
                    $newTotalRatingPoints = $totalRatingPoints + $ratingValue;
                    $newReviewCount = $currentReviews + 1;
                    $newAverageRating = $newTotalRatingPoints / $newReviewCount;
                    
                    // Update product data
                    $product['rating'] = round($newAverageRating, 1);
                    $product['reviews'] = $newReviewCount;
                    break;
                }
            }
        }
        
        // Write updated products array back to file
        $productsData = "<?php\n// Sample product data\n\$products = " . var_export($products, true) . ";\n";
        file_put_contents('data/products.php', $productsData);
        
        // Commit transaction
        $conn->commit();
        
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'message' => 'Thank you for rating these products!'
        ]);
        exit;
        
    } catch (PDOException $e) {
        // Rollback transaction on error
        if ($conn->inTransaction()) {
            $conn->rollBack();
        }
        
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'message' => 'Database error: ' . $e->getMessage()
        ]);
        exit;
    } catch (Exception $e) {
        // Rollback transaction on error
        if ($conn->inTransaction()) {
            $conn->rollBack();
        }
        
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'message' => 'Error: ' . $e->getMessage()
        ]);
        exit;
    }
}

// Default response for invalid requests
header('Content-Type: application/json');
echo json_encode([
    'success' => false,
    'message' => 'Invalid request'
]);
exit;
?>
