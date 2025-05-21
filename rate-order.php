<?php
// Enable error reporting for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();

// Check if user is logged in
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header('Location: login.php?redirect=rate-order.php' . (isset($_GET['order_id']) ? '?order_id=' . $_GET['order_id'] : ''));
    exit;
}

// Include database connection
include 'db.php';

// Include products data
include 'data/products.php';

// Get user ID from session
$userEmail = $_SESSION['user_email'] ?? '';

// Check if order_id is provided
if (!isset($_GET['order_id']) || empty($_GET['order_id'])) {
    $error = "No order specified. Please select an order to rate.";
} else {
    $orderId = intval($_GET['order_id']);
    
    // Fetch user data
    try {
        $stmt = $conn->prepare("SELECT id FROM users WHERE email = :email");
        $stmt->bindParam(':email', $userEmail);
        $stmt->execute();
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            $error = "User not found. Please log in again.";
        } else {
            $userId = $user['id'];
            
            // Check if the order belongs to the user and is in received status
            $stmt = $conn->prepare("SELECT * FROM orders WHERE id = :id AND user_id = :user_id AND status = 'received'");
            $stmt->bindParam(':id', $orderId);
            $stmt->bindParam(':user_id', $userId);
            $stmt->execute();
            
            if ($stmt->rowCount() === 0) {
                $error = "Order not found or cannot be rated. Only received orders can be rated.";
            } else {
                $order = $stmt->fetch(PDO::FETCH_ASSOC);
                
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
                    $error = "No products available for rating in this order or all products have already been rated.";
                } else {
                    // Create a lookup array for products by ID
                    $productsById = [];
                    foreach ($products as $product) {
                        $productsById[$product['id']] = $product;
                    }
                    
                    // Enhance items with product details
                    $orderItems = [];
                    foreach ($items as $item) {
                        if (isset($productsById[$item['product_id']])) {
                            $product = $productsById[$item['product_id']];
                            $orderItems[] = [
                                'product_id' => $item['product_id'],
                                'name' => $item['product_name'],
                                'price' => $item['price'],
                                'quantity' => $item['quantity'],
                                'image' => $product['image'] ?? 'images/products/default.jpg',
                                'current_rating' => $product['rating'] ?? 0,
                                'current_reviews' => $product['reviews'] ?? 0
                            ];
                        }
                    }
                }
            }
        }
    } catch (PDOException $e) {
        $error = "Database error: " . $e->getMessage();
    }
}

// Process form submission
$successMessage = '';
$ratingErrors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_ratings'])) {
    $postOrderId = isset($_POST['order_id']) ? intval($_POST['order_id']) : 0;
    $ratings = isset($_POST['ratings']) ? $_POST['ratings'] : [];
    
    if ($postOrderId <= 0) {
        $ratingErrors[] = "Invalid order ID.";
    }
    
    if (empty($ratings)) {
        $ratingErrors[] = "Please rate at least one product.";
    }
    
    if (empty($ratingErrors)) {
        try {
            // Check if the order belongs to the user and is in received status
            $stmt = $conn->prepare("SELECT * FROM orders WHERE id = :id AND user_id = :user_id AND status = 'received'");
            $stmt->bindParam(':id', $postOrderId);
            $stmt->bindParam(':user_id', $userId);
            $stmt->execute();
            
            if ($stmt->rowCount() === 0) {
                $ratingErrors[] = "Order not found or cannot be rated.";
            } else {
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
                
                // Insert ratings
                $stmt = $conn->prepare("
                    INSERT INTO product_ratings (user_id, product_id, order_id, rating)
                    VALUES (:user_id, :product_id, :order_id, :rating)
                    ON DUPLICATE KEY UPDATE rating = :rating
                ");
                
                foreach ($ratings as $productId => $rating) {
                    $productId = intval($productId);
                    $rating = intval($rating);
                    
                    if ($productId <= 0 || $rating < 1 || $rating > 5) {
                        continue;
                    }
                    
                    $stmt->bindParam(':user_id', $userId);
                    $stmt->bindParam(':product_id', $productId);
                    $stmt->bindParam(':order_id', $postOrderId);
                    $stmt->bindParam(':rating', $rating);
                    $stmt->execute();
                    
                    // Update product rating in products.php
                    foreach ($products as &$product) {
                        if ($product['id'] == $productId) {
                            // Calculate new average rating
                            $currentRating = $product['rating'] ?? 0;
                            $currentReviews = $product['reviews'] ?? 0;
                            
                            $totalRatingPoints = $currentRating * $currentReviews;
                            $newTotalRatingPoints = $totalRatingPoints + $rating;
                            $newReviewCount = $currentReviews + 1;
                            $newAverageRating = round($newTotalRatingPoints / $newReviewCount, 1);
                            
                            // Update product data
                            $product['rating'] = $newAverageRating;
                            $product['reviews'] = $newReviewCount;
                            break;
                        }
                    }
                }
                
                // Save updated products array to file
                $productsData = "<?php\n// Sample product data\n\$products = " . var_export($products, true) . ";\n";
                file_put_contents('data/products.php', $productsData);
                
                // Commit transaction
                $conn->commit();
                
                $successMessage = "Thank you for rating these products! Your feedback helps other shoppers make better choices.";
                
                // Redirect after a short delay
                header("Refresh: 3; URL=account.php#orders-section");
            }
        } catch (PDOException $e) {
            // Rollback transaction on error
            if ($conn->inTransaction()) {
                $conn->rollBack();
            }
            
            $ratingErrors[] = "Database error: " . $e->getMessage();
        }
    }
}

// Include common functions
include 'includes/functions.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rate Products - Mugna</title>
    <link rel="stylesheet" href="css/styles.css">
    <link rel="shortcut icon" href="./images/mugna-icon.png" type="image/x-icon">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .rating-page {
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
        }
        
        .rating-header {
            margin-bottom: 30px;
            text-align: center;
        }
        
        .rating-header h1 {
            font-size: 28px;
            color: #333;
            margin-bottom: 10px;
        }
        
        .rating-header p {
            color: #666;
            font-size: 16px;
        }
        
        .rating-product {
            display: flex;
            align-items: center;
            padding: 20px;
            border-radius: 10px;
            background-color: #fff;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
            margin-bottom: 20px;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }
        
        .rating-product:hover {
            transform: translateY(-3px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
        }
        
        .rating-product-image {
            width: 100px;
            height: 100px;
            border-radius: 8px;
            overflow: hidden;
            margin-right: 20px;
            background-color: white;
            border: 1px solid #eee;
        }
        
        .rating-product-image img {
            width: 100%;
            height: 100%;
            object-fit: contain;
        }
        
        .rating-product-info {
            flex: 1;
        }
        
        .rating-product-name {
            font-weight: 600;
            margin-bottom: 8px;
            color: #333;
            font-size: 18px;
        }
        
        .rating-product-price {
            color: #666;
            font-size: 16px;
            margin-bottom: 15px;
        }
        
        .rating-stars {
            display: flex;
            align-items: center;
            flex-wrap: wrap;
        }
        
        .rating-stars-label {
            margin-right: 15px;
            font-weight: 500;
            color: #333;
            margin-bottom: 10px;
        }
        
        .star-rating {
            display: flex;
            gap: 8px;
        }
        
        .star-rating .star {
            color: #d1d5db;
            font-size: 28px;
            cursor: pointer;
            transition: color 0.2s ease, transform 0.1s ease;
        }
        
        .star-rating .star:hover {
            transform: scale(1.1);
        }
        
        .star-rating .star.selected {
            color: #fbbf24;
        }
        
        .rating-actions {
            margin-top: 30px;
            display: flex;
            justify-content: space-between;
        }
        
        .rating-success {
            background-color: #dcfce7;
            color: #059669;
            padding: 20px;
            border-radius: 8px;
            margin: 30px 0;
            text-align: center;
            font-size: 18px;
        }
        
        .rating-success i {
            margin-right: 10px;
        }
        
        .rating-error {
            background-color: #fee2e2;
            color: #dc2626;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        
        .rating-error ul {
            margin: 10px 0 0 20px;
        }
        
        .rating-error li {
            margin-bottom: 5px;
        }
        
        .no-items-message {
            text-align: center;
            padding: 40px 20px;
            background-color: #f9f9f9;
            border-radius: 10px;
            margin: 30px 0;
        }
        
        .no-items-message p {
            margin-bottom: 20px;
            color: #666;
            font-size: 18px;
        }
        
        @media (max-width: 768px) {
            .rating-product {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .rating-product-image {
                margin-right: 0;
                margin-bottom: 15px;
                width: 80px;
                height: 80px;
            }
            
            .rating-stars {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .rating-stars-label {
                margin-bottom: 10px;
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
                        <h1 class="page-title">Rate Products</h1>
                        <div class="breadcrumb">
                            <a href="home.php">Home</a>
                            <span class="breadcrumb-separator">/</span>
                            <a href="account.php">My Account</a>
                            <span class="breadcrumb-separator">/</span>
                            <span>Rate Products</span>
                        </div>
                    </div>
                </section>
                
                <div class="rating-page">
                    <?php if (!empty($error)): ?>
                        <div class="rating-error">
                            <p><i class="fas fa-exclamation-circle"></i> <?php echo $error; ?></p>
                            <div class="rating-actions">
                                <a href="account.php#orders-section" class="btn btn-outline">Back to Orders</a>
                            </div>
                        </div>
                    <?php elseif (!empty($successMessage)): ?>
                        <div class="rating-success">
                            <p><i class="fas fa-check-circle"></i> <?php echo $successMessage; ?></p>
                            <p>Redirecting you back to your orders...</p>
                        </div>
                    <?php else: ?>
                        <div class="rating-header">
                            <h1>Rate Your Purchase</h1>
                            <p>Your feedback helps other shoppers make better choices. Please rate the products from Order #<?php echo $orderId; ?>.</p>
                        </div>
                        
                        <?php if (!empty($ratingErrors)): ?>
                            <div class="rating-error">
                                <p><i class="fas fa-exclamation-circle"></i> Please fix the following errors:</p>
                                <ul>
                                    <?php foreach ($ratingErrors as $error): ?>
                                        <li><?php echo $error; ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        <?php endif; ?>
                        
                        <?php if (empty($orderItems)): ?>
                            <div class="no-items-message">
                                <p>No products available for rating.</p>
                                <a href="account.php#orders-section" class="btn btn-primary">Back to Orders</a>
                            </div>
                        <?php else: ?>
                            <form action="rate-order.php" method="post">
                                <input type="hidden" name="order_id" value="<?php echo $orderId; ?>">
                                
                                <?php foreach ($orderItems as $item): ?>
                                    <div class="rating-product">
                                        <div class="rating-product-image">
                                            <img src="<?php echo $item['image']; ?>" alt="<?php echo $item['name']; ?>">
                                        </div>
                                        <div class="rating-product-info">
                                            <h3 class="rating-product-name"><?php echo $item['name']; ?></h3>
                                            <p class="rating-product-price">₱<?php echo number_format($item['price'], 2); ?></p>
                                            <div class="rating-stars">
                                                <div class="rating-stars-label">Your Rating:</div>
                                                <div class="star-rating" data-product-id="<?php echo $item['product_id']; ?>">
                                                    <i class="fas fa-star star" data-rating="1"></i>
                                                    <i class="fas fa-star star" data-rating="2"></i>
                                                    <i class="fas fa-star star" data-rating="3"></i>
                                                    <i class="fas fa-star star" data-rating="4"></i>
                                                    <i class="fas fa-star star" data-rating="5"></i>
                                                    <input type="hidden" name="ratings[<?php echo $item['product_id']; ?>]" value="0" class="rating-input">
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                                
                                <div class="rating-actions">
                                    <a href="account.php#orders-section" class="btn btn-outline">Cancel</a>
                                    <button type="submit" name="submit_ratings" class="btn btn-primary">Submit Ratings</button>
                                </div>
                            </form>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
        </main>
        
        <?php include 'includes/footer.php'; ?>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Star rating functionality
            const starRatings = document.querySelectorAll('.star-rating');
            
            starRatings.forEach(starRating => {
                const stars = starRating.querySelectorAll('.star');
                const ratingInput = starRating.querySelector('.rating-input');
                
                stars.forEach(star => {
                    star.addEventListener('click', function() {
                        const rating = parseInt(this.dataset.rating);
                        
                        // Update visual state of stars
                        stars.forEach(s => {
                            if (parseInt(s.dataset.rating) <= rating) {
                                s.classList.add('selected');
                            } else {
                                s.classList.remove('selected');
                            }
                        });
                        
                        // Update hidden input value
                        ratingInput.value = rating;
                    });
                    
                    // Hover effects
                    star.addEventListener('mouseenter', function() {
                        const rating = parseInt(this.dataset.rating);
                        
                        stars.forEach(s => {
                            if (parseInt(s.dataset.rating) <= rating) {
                                s.classList.add('hover');
                            } else {
                                s.classList.remove('hover');
                            }
                        });
                    });
                });
                
                starRating.addEventListener('mouseleave', function() {
                    stars.forEach(s => {
                        s.classList.remove('hover');
                    });
                });
            });
            
            // Form validation
            const ratingForm = document.querySelector('form');
            
            if (ratingForm) {
                ratingForm.addEventListener('submit', function(e) {
                    const ratingInputs = document.querySelectorAll('.rating-input');
                    let hasRating = false;
                    
                    ratingInputs.forEach(input => {
                        if (parseInt(input.value) > 0) {
                            hasRating = true;
                        }
                    });
                    
                    if (!hasRating) {
                        e.preventDefault();
                        alert('Please rate at least one product before submitting.');
                    }
                });
            }
        });
    </script>
</body>
</html>
