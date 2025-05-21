<?php
session_start();

// Check if the user is logged in
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    // Display JavaScript alert if not logged in
    echo "<script>alert('You are not logged in. Please log in to access your account.');</script>";
    
    // Redirect after alert
    echo "<script>window.location.href = 'index.php';</script>";
    exit;  // Stop further execution of the script
}

$user_email = $_SESSION['user_email'];

// Include common functions
include 'includes/functions.php';

// Sample product data - in a real app, this would come from a database
include 'data/products.php';
include 'data/categories.php';
include 'data/slides.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mugna - Home</title>
    <link rel="stylesheet" href="css/styles.css">
    <link rel="shortcut icon" href="./images/mugna-icon.png" type="image/x-icon">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* Product Modal Styles */
        .product-modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            overflow: auto;
            background-color: rgba(0, 0, 0, 0.5);
            opacity: 0;
            transition: opacity 0.3s ease;
        }

        .product-modal.active {
            display: block;
            opacity: 1;
        }

        .product-modal-content {
            background-color: #fff;
            margin: 5% auto;
            width: 90%;
            max-width: 900px;
            border-radius: 12px;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
            position: relative;
            transform: translateY(-20px);
            transition: transform 0.3s ease;
            overflow: hidden;
        }

        .product-modal.active .product-modal-content {
            transform: translateY(0);
        }

        .product-modal-close {
            position: absolute;
            right: 20px;
            top: 20px;
            font-size: 28px;
            font-weight: bold;
            color: #64748b;
            cursor: pointer;
            z-index: 10;
            width: 36px;
            height: 36px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            background-color: rgba(255, 255, 255, 0.8);
            transition: all 0.2s ease;
        }

        .product-modal-close:hover {
            color: #1e3a8a;
            background-color: #f1f5f9;
        }

        .product-modal-body {
            padding: 0;
        }

        .product-modal-grid {
            display: grid;
            grid-template-columns: 1fr;
        }

        @media (min-width: 768px) {
            .product-modal-grid {
                grid-template-columns: 1fr 1fr;
            }
        }

        /* Product Image */
        .product-modal-image {
            position: relative;
            height: 300px;
            background-color: #f8fafc;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px;
        }

        @media (min-width: 768px) {
            .product-modal-image {
                height: 500px;
            }
        }

        .product-modal-image img {
            max-height: 100%;
            max-width: 100%;
            object-fit: contain;
        }

        .product-modal-badges {
            position: absolute;
            top: 16px;
            left: 16px;
            z-index: 5;
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .product-modal-badge {
            display: none;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }

        .product-modal-badge.sale {
            background-color: #ef4444;
            color: white;
        }

        .product-modal-badge.new {
            background-color: #2563eb;
            color: white;
        }

        /* Product Details */
        .product-modal-details {
            padding: 24px;
            display: flex;
            flex-direction: column;
        }

        .product-modal-title {
            font-size: 24px;
            font-weight: 700;
            color: #1e3a8a;
            margin-bottom: 4px;
        }

        .product-modal-category {
            font-size: 14px;
            color: #64748b;
            margin-bottom: 16px;
        }

        .product-modal-rating {
            display: flex;
            align-items: center;
            margin-bottom: 16px;
        }

        .modal-stars {
            display: flex;
            gap: 2px;
        }

        .modal-stars i {
            color: #f59e0b;
            font-size: 16px;
        }

        .modal-reviews {
            margin-left: 8px;
            font-size: 14px;
            color: #64748b;
        }

        .product-modal-price {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 16px;
        }

        .modal-current-price {
            font-size: 24px;
            font-weight: 700;
            color: #2563eb;
        }

        .modal-old-price {
            font-size: 16px;
            color: #64748b;
            text-decoration: line-through;
        }

        .product-modal-separator {
            height: 1px;
            background-color: #e2e8f0;
            margin: 16px 0;
        }

        .product-modal-description {
            font-size: 14px;
            line-height: 1.6;
            color: #334155;
            margin-bottom: 24px;
        }

        .product-modal-stock {
            margin-bottom: 24px;
        }

        .product-modal-stock span {
            font-size: 14px;
            font-weight: 500;
        }

        .product-modal-stock span.in-stock {
            color: #10b981;
        }

        .product-modal-stock span.out-of-stock {
            color: #ef4444;
        }

        /* Quantity Selector */
        .product-modal-quantity {
            margin-bottom: 24px;
        }

        .quantity-label {
            display: block;
            font-size: 14px;
            font-weight: 500;
            color: #1e3a8a;
            margin-bottom: 8px;
        }

        .quantity-selector {
            display: flex;
            align-items: center;
            max-width: 120px;
        }

        .quantity-btn {
            width: 36px;
            height: 36px;
            border: 1px solid #e2e8f0;
            background-color: #f8fafc;
            border-radius: 6px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .quantity-btn:hover:not(:disabled) {
            background-color: #e2e8f0;
        }

        .quantity-btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        .quantity-value {
            width: 48px;
            text-align: center;
            font-size: 16px;
            font-weight: 500;
        }

        /* Action Buttons */
        .product-modal-actions {
            display: flex;
            flex-direction: column;
            gap: 12px;
            margin-top: auto;
        }

        @media (min-width: 640px) {
            .product-modal-actions {
                flex-direction: row;
            }
        }

        .modal-add-to-cart-btn,
        .modal-wishlist-btn {
            flex: 1;
            padding: 12px;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            transition: all 0.2s ease;
        }

        .modal-add-to-cart-btn {
            background-color: #2563eb;
            color: white;
            border: none;
        }

        .modal-add-to-cart-btn:hover:not(:disabled) {
            background-color: #1d4ed8;
        }

        .modal-add-to-cart-btn:disabled {
            opacity: 0.7;
            cursor: not-allowed;
        }

        .modal-wishlist-btn {
            background-color: white;
            color: #1e3a8a;
            border: 1px solid #e2e8f0;
        }

        .modal-wishlist-btn:hover {
            background-color: #f1f5f9;
        }

        /* Success message for add to cart */
        .add-to-cart-success {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 1000;
        }

        .success-message {
            background-color: #10b981;
            color: white;
            padding: 1rem;
            border-radius: 0.5rem;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            display: flex;
            align-items: center;
            gap: 0.5rem;
            animation: slideIn 0.3s ease-out;
        }

        .success-message a {
            color: white;
            text-decoration: underline;
            margin-left: 0.5rem;
            font-weight: bold;
        }

        @keyframes slideIn {
            from {
                transform: translateX(100%);
                opacity: 0;
            }
            to {
                transform: translateX(0);
                opacity: 1;
            }
        }

        /* Modern Product Grid Styles */
        .products-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .product-card {
            background-color: white;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.05);
            transition: all 0.3s ease;
            position: relative;
            height: 100%;
            display: flex;
            flex-direction: column;
        }

        .product-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.1);
        }

        .product-badge {
            position: absolute;
            top: 1rem;
            left: 1rem;
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
            z-index: 1;
        }

        .product-badge.sale {
            background-color: #ef4444;
            color: white;
        }

        .product-badge.new {
            background-color: #2563eb;
            color: white;
        }

        .product-image {
            height: 200px;
            display: flex;
            align-items: center;
            justify-content: center;
            background-color: #f8fafc;
            position: relative;
            overflow: hidden;
        }

        .product-image img {
            max-width: 80%;
            max-height: 80%;
            object-fit: contain;
            transition: all 0.3s ease;
        }

        .product-card:hover .product-image img {
            transform: scale(1.05);
        }

        .product-actions {
            position: absolute;
            top: 1rem;
            right: 1rem;
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
            opacity: 0;
            transform: translateX(10px);
            transition: all 0.3s ease;
        }

        .product-card:hover .product-actions {
            opacity: 1;
            transform: translateX(0);
        }

        .product-action {
            width: 35px;
            height: 35px;
            border-radius: 50%;
            background-color: white;
            color: #64748b;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .product-action:hover {
            background-color: #2563eb;
            color: white;
        }

        .product-action.active {
            background-color: #ef4444;
            color: white;
        }

        .product-content {
            padding: 1.5rem;
            display: flex;
            flex-direction: column;
            flex-grow: 1;
        }

        .product-title {
            font-size: 1rem;
            font-weight: 600;
            color: #1e3a8a;
            margin-bottom: 0.5rem;
            height: 2.5rem;
            overflow: hidden;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
        }

        .product-category {
            color: #64748b;
            font-size: 0.875rem;
            margin-bottom: 0.5rem;
        }

        .product-rating {
            display: flex;
            align-items: center;
            gap: 0.25rem;
            margin-bottom: 0.5rem;
        }

        .product-rating i {
            color: #f59e0b;
            font-size: 0.875rem;
        }

        .product-rating span {
            color: #64748b;
            font-size: 0.875rem;
        }

        .product-price {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin-bottom: 1rem;
        }

        .current-price {
            font-size: 1.25rem;
            font-weight: 700;
            color: #2563eb;
        }

        .old-price {
            font-size: 0.875rem;
            color: #64748b;
            text-decoration: line-through;
        }

        .product-button {
            width: 100%;
            padding: 0.75rem;
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
            margin-top: auto;
        }

        .product-button:hover {
            background-color: #1d4ed8;
        }

        /* Responsive adjustments */
        @media (max-width: 768px) {
            .products-grid {
                grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            }
        }

        @media (max-width: 480px) {
            .products-grid {
                grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
            }
            
            .product-content {
                padding: 1rem;
            }
            
            .product-title {
                font-size: 0.9rem;
            }
        }

        /* Section header styling */
        .section-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 1.5rem;
            padding-bottom: 0.75rem;
            border-bottom: 1px solid #e2e8f0;
        }

        .section-header h2 {
            font-size: 1.5rem;
            font-weight: 700;
            color: #1e3a8a;
            position: relative;
        }

        .section-header h2:after {
            content: '';
            position: absolute;
            bottom: -0.75rem;
            left: 0;
            width: 50px;
            height: 3px;
            background-color: #2563eb;
            border-radius: 3px;
        }
    </style>
</head>
<body>
    <div class="site-container">
        <?php include 'includes/header.php'; ?>
        
        <main class="main-content">
            <div class="container">
                <!-- Slideshow -->
                <div class="slideshow-container">
                    <div class="slideshow" id="productSlideshow">
                        <?php foreach ($slides as $index => $slide): ?>
                            <div class="slide <?php echo $index === 0 ? 'active' : ''; ?>" 
                                 style="background-image: linear-gradient(to right, <?php echo $slide['gradient']; ?>);">
                                <div class="slide-content">
                                    <h2><?php echo $slide['title']; ?></h2>
                                    <p><?php echo $slide['description']; ?></p>
                                    <a href="#" class="btn btn-light"><?php echo $slide['cta']; ?></a>
                                </div>
                                <img src="<?php echo $slide['image']; ?>" alt="<?php echo $slide['title']; ?>" class="slide-image">
                            </div>
                        <?php endforeach; ?>
                        
                        <!-- Navigation Arrows -->
                        <button class="slide-arrow prev-arrow">
                            <i class="fas fa-chevron-left"></i>
                        </button>
                        <button class="slide-arrow next-arrow">
                            <i class="fas fa-chevron-right"></i>
                        </button>
                        
                        <!-- Slide Indicators -->
                        <div class="slide-indicators">
                            <?php foreach ($slides as $index => $slide): ?>
                                <button class="indicator <?php echo $index === 0 ? 'active' : ''; ?>" 
                                        data-slide="<?php echo $index; ?>"></button>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
                
                <!-- Featured Categories -->
                <section class="featured-categories">
                    <div class="section-header">
                        <h2>Shop by Category</h2>
                    </div>
                    
                    <div class="categories-grid">
                        <?php foreach ($categories as $category): ?>
                            <div class="category-card">
                                <a href="#">
                                    <div class="category-content">
                                        <div class="category-image">
                                            <img src="<?php echo $category['image']; ?>" alt="<?php echo $category['name']; ?>">
                                        </div>
                                        <h3><?php echo $category['name']; ?></h3>
                                        <p><?php echo $category['count']; ?> products</p>
                                    </div>
                                </a>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </section>
                
                <!-- Featured Products -->
                <section class="featured-products">
                    <div class="section-header">
                        <h2>Our Products</h2>
                    </div>
                    
                    <div class="products-grid">
                        <?php 
                        // Display all products without limiting
                        foreach ($products as $product): 
                        ?>
                            <div class="product-card">
                                <?php if (isset($product['sale']) && $product['sale']): ?>
                                    <div class="product-badge sale">Sale</div>
                                <?php elseif (isset($product['new']) && $product['new']): ?>
                                    <div class="product-badge new">New</div>
                                <?php endif; ?>
                                
                                <div class="product-image">
                                    <img src="<?php echo $product['image']; ?>" alt="<?php echo $product['name']; ?>">
                                </div>
                                
                                <div class="product-actions">
                                    <div class="product-action wishlist-btn" data-product-id="<?php echo $product['id']; ?>">
                                        <i class="far fa-heart"></i>
                                    </div>
                                    <div class="product-action quick-view-btn" data-product-id="<?php echo $product['id']; ?>">
                                        <i class="far fa-eye"></i>
                                    </div>
                                </div>
                                
                                <div class="product-content">
                                    <h3 class="product-title"><?php echo $product['name']; ?></h3>
                                    <p class="product-category"><?php echo $product['category']; ?></p>
                                    
                                    <div class="product-rating">
                                        <?php for ($i = 1; $i <= 5; $i++): ?>
                                            <?php if ($i <= floor($product['rating'])): ?>
                                                <i class="fas fa-star"></i>
                                            <?php elseif ($i - 0.5 <= $product['rating']): ?>
                                                <i class="fas fa-star-half-alt"></i>
                                            <?php else: ?>
                                                <i class="far fa-star"></i>
                                            <?php endif; ?>
                                        <?php endfor; ?>
                                        <span>(<?php echo $product['reviews']; ?>)</span>
                                    </div>
                                    
                                    <div class="product-price">
                                        <span class="current-price">₱<?php echo number_format($product['price'], 2); ?></span>
                                        <?php if (isset($product['sale']) && $product['sale']): ?>
                                            <?php 
                                            // If oldPrice is set, use it; otherwise calculate a default old price (20% higher)
                                            $displayOldPrice = isset($product['oldPrice']) && $product['oldPrice'] ? $product['oldPrice'] : ($product['price'] * 1.2); 
                                            ?>
                                            <span class="old-price">₱<?php echo number_format($displayOldPrice, 2); ?></span>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <button class="product-button add-to-cart" data-product-id="<?php echo $product['id']; ?>">
                                        <i class="fas fa-shopping-cart"></i>
                                        Add to Cart
                                    </button>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
</section>
            </div>
        </main>
        
        <?php include 'includes/footer.php'; ?>
    </div>

    <!-- Product Modal -->
    <div id="productModal" class="product-modal">
        <div class="product-modal-content">
            <span class="product-modal-close">&times;</span>
            <div class="product-modal-body">
                <div class="product-modal-grid">
                    <!-- Product Image -->
                    <div class="product-modal-image">
                        <div class="product-modal-badges">
                            <span class="product-modal-badge sale" id="modalSaleBadge">Sale</span>
                            <span class="product-modal-badge new" id="modalNewBadge">New</span>
                        </div>
                        <img id="modalProductImage" src="/placeholder.svg" alt="Product Image">
                    </div>
                    
                    <!-- Product Details -->
                    <div class="product-modal-details">
                        <h2 id="modalProductName" class="product-modal-title"></h2>
                        <p id="modalProductCategory" class="product-modal-category"></p>
                        
                        <!-- Rating -->
                        <div class="product-modal-rating">
                            <div id="modalRatingStars" class="modal-stars"></div>
                            <span id="modalReviews" class="modal-reviews"></span>
                        </div>
                        
                        <!-- Price -->
                        <div class="product-modal-price">
                            <span id="modalCurrentPrice" class="modal-current-price"></span>
                            <span id="modalOldPrice" class="modal-old-price"></span>
                        </div>
                        
                        <div class="product-modal-separator"></div>
                        
                        <!-- Description -->
                        <p id="modalDescription" class="product-modal-description"></p>
                        
                        <!-- Stock Status -->
                        <div class="product-modal-stock">
                            <span id="modalStockStatus"></span>
                        </div>
                        
                        <!-- Quantity Selector -->
                        <div class="product-modal-quantity">
                            <label for="modalQuantity" class="quantity-label">Quantity</label>
                            <div class="quantity-selector">
                                <button id="decreaseQuantity" class="quantity-btn">
                                    <i class="fas fa-minus"></i>
                                </button>
                                <span id="modalQuantity" class="quantity-value">1</span>
                                <button id="increaseQuantity" class="quantity-btn">
                                    <i class="fas fa-plus"></i>
                                </button>
                            </div>
                        </div>
                        
                        <!-- Action Buttons -->
                        <div class="product-modal-actions">
                            <button id="modalAddToCart" class="modal-add-to-cart-btn">
                                <i class="fas fa-shopping-cart"></i>
                                Add to Cart
                            </button>
                            <button id="modalAddToWishlist" class="modal-wishlist-btn">
                                <i class="far fa-heart"></i>
                                Add to Wishlist
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="js/main.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Product Modal Functionality
            const modal = document.getElementById('productModal');
            const closeBtn = document.querySelector('.product-modal-close');
            const modalProductName = document.getElementById('modalProductName');
            const modalProductCategory = document.getElementById('modalProductCategory');
            const modalProductImage = document.getElementById('modalProductImage');
            const modalCurrentPrice = document.getElementById('modalCurrentPrice');
            const modalOldPrice = document.getElementById('modalOldPrice');
            const modalRatingStars = document.getElementById('modalRatingStars');
            const modalReviews = document.getElementById('modalReviews');
            const modalDescription = document.getElementById('modalDescription');
            const modalStockStatus = document.getElementById('modalStockStatus');
            const modalSaleBadge = document.getElementById('modalSaleBadge');
            const modalNewBadge = document.getElementById('modalNewBadge');
            const modalQuantity = document.getElementById('modalQuantity');
            const decreaseQuantityBtn = document.getElementById('decreaseQuantity');
            const increaseQuantityBtn = document.getElementById('increaseQuantity');
            const modalAddToCartBtn = document.getElementById('modalAddToCart');
            const modalAddToWishlistBtn = document.getElementById('modalAddToWishlist');

            // Current product data
            let currentProduct = null;
            let quantity = 1;

            // Close modal when clicking the close button
            closeBtn.addEventListener('click', function() {
                closeModal();
            });

            // Close modal when clicking outside the modal content
            window.addEventListener('click', function(event) {
                if (event.target === modal) {
                    closeModal();
                }
            });

            // Decrease quantity
            decreaseQuantityBtn.addEventListener('click', function() {
                if (quantity > 1) {
                    quantity--;
                    updateQuantityDisplay();
                }
            });

            // Increase quantity
            increaseQuantityBtn.addEventListener('click', function() {
                if (currentProduct && quantity < currentProduct.stock) {
                    quantity++;
                    updateQuantityDisplay();
                }
            });

            // Add to cart from modal
            modalAddToCartBtn.addEventListener('click', function() {
                if (currentProduct) {
                    // Create a form to submit the data
                    const form = document.createElement('form');
                    form.method = 'POST';
                    form.action = 'add_to_cart.php';
                    
                    // Create hidden inputs for the product data
                    const productIdInput = document.createElement('input');
                    productIdInput.type = 'hidden';
                    productIdInput.name = 'product_id';
                    productIdInput.value = currentProduct.id;
                    
                    // Create inputs for product name and price
                    const productNameInput = document.createElement('input');
                    productNameInput.type = 'hidden';
                    productNameInput.name = 'product_name';
                    productNameInput.value = currentProduct.name;
                    
                    const priceInput = document.createElement('input');
                    priceInput.type = 'hidden';
                    priceInput.name = 'price';
                    priceInput.value = currentProduct.price;
                    
                    // Create input for quantity
                    const quantityInput = document.createElement('input');
                    quantityInput.type = 'hidden';
                    quantityInput.name = 'quantity';
                    quantityInput.value = quantity;
                    
                    // Append all inputs to the form
                    form.appendChild(productIdInput);
                    form.appendChild(productNameInput);
                    form.appendChild(priceInput);
                    form.appendChild(quantityInput);
                    
                    // Append the form to the body
                    document.body.appendChild(form);
                    
                    // Close the modal
                    closeModal();
                    
                    // Show success message
                    const successDiv = document.createElement('div');
                    successDiv.className = 'add-to-cart-success';
                    successDiv.innerHTML = `
                        <div class="success-message">
                            <i class="fas fa-check-circle"></i>
                            ${currentProduct.name} added to cart!
                            <a href="cart.php">View Cart</a>
                        </div>
                    `;
                    document.body.appendChild(successDiv);
                    
                    // Remove the message after 3 seconds
                    setTimeout(function() {
                        document.body.removeChild(successDiv);
                    }, 3000);
                    
                    // Submit the form
                    form.submit();
                }
            });

            // Add to wishlist
            modalAddToWishlistBtn.addEventListener('click', function() {
                if (currentProduct) {
                    toggleWishlist(currentProduct.id);
                }
            });

            // Update quantity display
            function updateQuantityDisplay() {
                modalQuantity.textContent = quantity;

                // Disable decrease button if quantity is 1
                if (quantity <= 1) {
                    decreaseQuantityBtn.disabled = true;
                } else {
                    decreaseQuantityBtn.disabled = false;
                }

                // Disable increase button if quantity is max stock
                if (currentProduct && quantity >= currentProduct.stock) {
                    increaseQuantityBtn.disabled = true;
                } else {
                    increaseQuantityBtn.disabled = false;
                }
            }

            // Open modal with product data
            function openProductModal(product) {
                currentProduct = product;
                quantity = 1;

                // Set product details
                modalProductName.textContent = product.name;
                modalProductCategory.textContent = product.category;
                modalProductImage.src = product.image;
                modalProductImage.alt = product.name;
                modalCurrentPrice.textContent = formatPrice(product.price);

                // Handle old price
                if (product.sale) {
                    // If oldPrice is set, use it; otherwise calculate a default old price (20% higher)
                    const displayOldPrice = product.oldPrice ? product.oldPrice : (product.price * 1.2);
                    modalOldPrice.textContent = formatPrice(displayOldPrice);
                    modalOldPrice.style.display = 'inline';
                } else {
                    modalOldPrice.style.display = 'none';
                }

                // Handle badges
                if (product.sale) {
                    modalSaleBadge.style.display = 'inline-block';
                } else {
                    modalSaleBadge.style.display = 'none';
                }

                if (product.new) {
                    modalNewBadge.style.display = 'inline-block';
                } else {
                    modalNewBadge.style.display = 'none';
                }

                // Set rating stars
                modalRatingStars.innerHTML = '';
                for (let i = 1; i <= 5; i++) {
                    const star = document.createElement('i');
                    if (i <= Math.floor(product.rating)) {
                        star.className = 'fas fa-star';
                    } else if (i - 0.5 <= product.rating) {
                        star.className = 'fas fa-star-half-alt';
                    } else {
                        star.className = 'far fa-star';
                    }
                    modalRatingStars.appendChild(star);
                }

                // Set reviews
                modalReviews.textContent = `(${product.reviews} reviews)`;

                // Set description
                modalDescription.textContent = product.description || 'No description available.';

                // Set stock status
                if (product.stock > 0) {
                    modalStockStatus.textContent = `In Stock (${product.stock} available)`;
                    modalStockStatus.className = 'in-stock';
                    modalAddToCartBtn.disabled = false;
                } else {
                    modalStockStatus.textContent = 'Out of Stock';
                    modalStockStatus.className = 'out-of-stock';
                    modalAddToCartBtn.disabled = true;
                }

                // Reset quantity
                updateQuantityDisplay();

                // Show modal
                modal.classList.add('active');
                document.body.style.overflow = 'hidden'; // Prevent scrolling
            }

            // Close modal
            function closeModal() {
                modal.classList.remove('active');
                document.body.style.overflow = ''; // Restore scrolling

                // Reset current product after animation completes
                setTimeout(function() {
                    currentProduct = null;
                }, 300);
            }

            // Format price
            function formatPrice(price) {
                return '₱' + parseFloat(price).toLocaleString(undefined, {
                    minimumFractionDigits: 2,
                    maximumFractionDigits: 2
                });
            }

            // Get product data from the server using AJAX
            function getProductData(productId) {
                return new Promise((resolve, reject) => {
                    const xhr = new XMLHttpRequest();
                    xhr.open('GET', 'get_product_data.php?product_id=' + productId, true); // Asynchronous request
                    
                    xhr.onload = function() {
                        if (xhr.status === 200) {
                            try {
                                const product = JSON.parse(xhr.responseText);
                                resolve(product);
                            } catch (e) {
                                console.error('Error parsing product data:', e);
                                reject(e);
                            }
                        } else {
                            console.error('Failed to fetch product data. Status:', xhr.status);
                            reject(new Error('Failed to fetch product data'));
                        }
                    };
                    
                    xhr.onerror = function() {
                        console.error('Network error when fetching product data');
                        reject(new Error('Network error'));
                    };
                    
                    xhr.send();
                });
            }

            // Initialize product modal triggers
            function initProductModalTriggers() {
                // For product cards (excluding buttons and actions)
                document.querySelectorAll('.product-card').forEach(card => {
                    card.addEventListener('click', function(e) {
                        // Don't trigger if clicking on buttons or actions
                        if (!e.target.closest('.product-button') && !e.target.closest('.product-action')) {
                            const productId = parseInt(this.querySelector('.add-to-cart')?.dataset.productId || '0');
                            if (productId) {
                                getProductData(productId)
                                    .then(product => {
                                        if (product) {
                                            openProductModal(product);
                                        }
                                    })
                                    .catch(error => {
                                        console.error('Error loading product data:', error);
                                    });
                            }
                        }
                    });
                });

                // For quick view buttons
                document.querySelectorAll('.quick-view-btn').forEach(btn => {
                    btn.addEventListener('click', function(e) {
                        e.stopPropagation();
                        const productId = parseInt(this.dataset.productId || '0');
                        if (productId) {
                            getProductData(productId)
                                .then(product => {
                                    if (product) {
                                        openProductModal(product);
                                    }
                                })
                                .catch(error => {
                                    console.error('Error loading product data:', error);
                                });
                        }
                    });
                });
            }

            // Initialize modal triggers
            initProductModalTriggers();
            
            // Wishlist functionality
            const wishlistBtns = document.querySelectorAll('.wishlist-btn');
            wishlistBtns.forEach(btn => {
                btn.addEventListener('click', function(e) {
                    e.stopPropagation();
                    const productId = this.getAttribute('data-product-id');
                    if (productId) {
                        // Send AJAX request to toggle wishlist status
                        fetch('ajax-handler.php', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/x-www-form-urlencoded',
                            },
                            body: 'action=toggle_wishlist&product_id=' + productId
                        })
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                const icon = this.querySelector('i');
                                
                                // Update UI based on the action taken
                                if (data.action === 'added') {
                                    icon.classList.remove('far');
                                    icon.classList.add('fas');
                                    this.classList.add('active');
                                    
                                    // Show success message
                                    const successDiv = document.createElement('div');
                                    successDiv.className = 'add-to-wishlist-success';
                                    successDiv.innerHTML = `
                                        <div class="success-message">
                                            <i class="fas fa-check-circle"></i>
                                            Product added to wishlist!
                                            <a href="wishlist.php">View Wishlist</a>
                                        </div>
                                    `;
                                    document.body.appendChild(successDiv);
                                    
                                    // Remove the message after 3 seconds
                                    setTimeout(function() {
                                        document.body.removeChild(successDiv);
                                    }, 3000);
                                } else if (data.action === 'removed') {
                                    icon.classList.remove('fas');
                                    icon.classList.add('far');
                                    this.classList.remove('active');
                                }
                            } else {
                                console.error('Error:', data.message);
                            }
                        })
                        .catch(error => {
                            console.error('Error:', error);
                        });
                    }
                });
            });
            
            // Add to cart functionality
            const addToCartBtns = document.querySelectorAll('.add-to-cart');
            addToCartBtns.forEach(btn => {
                btn.addEventListener('click', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    const productId = this.getAttribute('data-product-id');
                    
                    // Find the product data
                    const productCard = this.closest('.product-card');
                    const productName = productCard.querySelector('.product-title').textContent;
                    const priceText = productCard.querySelector('.current-price').textContent;
                    const price = parseFloat(priceText.replace(/[^0-9.]/g, ''));
                    
                    // Create a form to submit the data
                    const form = document.createElement('form');
                    form.method = 'POST';
                    form.action = 'add_to_cart.php';
                    
                    // Create hidden inputs for the product data
                    const productIdInput = document.createElement('input');
                    productIdInput.type = 'hidden';
                    productIdInput.name = 'product_id';
                    productIdInput.value = productId;
                    
                    // Create inputs for product name and price
                    const productNameInput = document.createElement('input');
                    productNameInput.type = 'hidden';
                    productNameInput.name = 'product_name';
                    productNameInput.value = productName;
                    
                    const priceInput = document.createElement('input');
                    priceInput.type = 'hidden';
                    priceInput.name = 'price';
                    priceInput.value = price;
                    
                    // Create input for quantity (default to 1)
                    const quantityInput = document.createElement('input');
                    quantityInput.type = 'hidden';
                    quantityInput.name = 'quantity';
                    quantityInput.value = 1;
                    
                    // Append all inputs to the form
                    form.appendChild(productIdInput);
                    form.appendChild(productNameInput);
                    form.appendChild(priceInput);
                    form.appendChild(quantityInput);
                    
                    // Append the form to the body
                    document.body.appendChild(form);
                    
                    // Show success message
                    const successDiv = document.createElement('div');
                    successDiv.className = 'add-to-cart-success';
                    successDiv.innerHTML = `
                        <div class="success-message">
                            <i class="fas fa-check-circle"></i>
                            ${productName} added to cart!
                            <a href="cart.php">View Cart</a>
                        </div>
                    `;
                    document.body.appendChild(successDiv);
                    
                    // Remove the message after 3 seconds
                    setTimeout(function() {
                        document.body.removeChild(successDiv);
                    }, 3000);
                    
                    // Submit the form
                    form.submit();
                });
            });
        });
    </script>
</body>
</html>
