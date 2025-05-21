<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
   header('Location: index.php');
   exit;
}

// Include common functions
include 'includes/functions.php';

// Include product data
include 'data/products.php';

// Get categories for filter
$categories = [];
foreach ($products as $product) {
   if (isset($product['category']) && !in_array($product['category'], $categories)) {
      $categories[] = $product['category'];
   }
}
sort($categories);

// Handle filtering
$filteredProducts = $products;

// Category filter
if (isset($_GET['category'])) {
  $categoryFilter = $_GET['category'];
  
  // Handle both array and string formats
  if (is_array($categoryFilter)) {
    $filteredProducts = array_filter($filteredProducts, function($product) use ($categoryFilter) {
      return isset($product['category']) && in_array($product['category'], $categoryFilter);
    });
  } else {
    $filteredProducts = array_filter($filteredProducts, function($product) use ($categoryFilter) {
      return isset($product['category']) && $product['category'] === $categoryFilter;
    });
  }
}

// Price filter
if (isset($_GET['price_min']) || isset($_GET['price_max'])) {
  $minPrice = isset($_GET['price_min']) && !empty($_GET['price_min']) ? floatval($_GET['price_min']) : 0;
  $maxPrice = isset($_GET['price_max']) && !empty($_GET['price_max']) ? floatval($_GET['price_max']) : PHP_FLOAT_MAX;
  
  $filteredProducts = array_filter($filteredProducts, function($product) use ($minPrice, $maxPrice) {
    return $product['price'] >= $minPrice && $product['price'] <= $maxPrice;
  });
}

// In stock filter
if (isset($_GET['in_stock']) && $_GET['in_stock'] === '1') {
  $filteredProducts = array_filter($filteredProducts, function($product) {
    return isset($product['stock']) && $product['stock'] > 0;
  });
}

// On sale filter
if (isset($_GET['on_sale']) && $_GET['on_sale'] === '1') {
  $filteredProducts = array_filter($filteredProducts, function($product) {
    return isset($product['sale']) && $product['sale'] === true;
  });
}

// Search filter
if ((isset($_GET['search']) && !empty($_GET['search'])) || (isset($_GET['q']) && !empty($_GET['q']))) {
   $search = strtolower($_GET['search'] ?? $_GET['q']);
   
   $filteredProducts = array_filter($filteredProducts, function($product) use ($search) {
      return strpos(strtolower($product['name']), $search) !== false || 
             strpos(strtolower($product['category']), $search) !== false ||
             (isset($product['description']) && strpos(strtolower($product['description']), $search) !== false);
   });
}

// Sort products
$sortBy = $_GET['sort'] ?? 'default';
switch ($sortBy) {
   case 'price_low':
      usort($filteredProducts, function($a, $b) {
         return $a['price'] - $b['price'];
      });
      break;
   case 'price_high':
      usort($filteredProducts, function($a, $b) {
         return $b['price'] - $a['price'];
      });
      break;
   case 'name_asc':
      usort($filteredProducts, function($a, $b) {
         return strcmp($a['name'], $b['name']);
      });
      break;
   case 'name_desc':
      usort($filteredProducts, function($a, $b) {
         return strcmp($b['name'], $a['name']);
      });
      break;
   case 'newest':
      // Assuming newer products have higher IDs
      usort($filteredProducts, function($a, $b) {
         return $b['id'] - $a['id'];
      });
      break;
   default:
      // Default sorting (featured)
      break;
}

// No pagination - show all products
$totalProducts = count($filteredProducts);
$paginatedProducts = $filteredProducts; // Use all filtered products

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
   <title>Products - Mugna</title>
   <link rel="stylesheet" href="css/styles.css">
   <link rel="shortcut icon" href="./images/mugna-icon.png" type="image/x-icon">
   <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
   <style>
      /* Products Page Styles */
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
      
      /* Filter Sidebar */
      .products-container {
         display: grid;
         grid-template-columns: 250px 1fr;
         gap: 2rem;
      }
      
      .filter-sidebar {
         background-color: white;
         border-radius: 12px;
         box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
         padding: 1.5rem;
         height: 35rem;
         position: sticky;
         top: 2rem;
         overflow-y: hidden; /* Hide scrollbar by default */
         transition: all 0.3s ease;
      }
      .filter-sidebar:hover {
   overflow-y: auto; /* Show scrollbar when hovered */
}
      
      .filter-header {
         display: flex;
         align-items: center;
         justify-content: space-between;
         margin-bottom: 1.5rem;
      }
      
      .filter-title {
         font-size: 1.25rem;
         font-weight: 600;
         color: #1e3a8a;
      }
      
      .filter-clear {
         color: #2563eb;
         font-size: 0.875rem;
         cursor: pointer;
         transition: all 0.3s ease;
      }
      
      .filter-clear:hover {
         color: #1d4ed8;
      }
      
      .filter-group {
         margin-bottom: 1.5rem;
         border-bottom: 1px solid #e2e8f0;
         padding-bottom: 1.5rem;
      }
      
      .filter-group:last-child {
         border-bottom: none;
         padding-bottom: 0;
         margin-bottom: 0;
      }
      
      .filter-group-title {
         font-size: 1rem;
         font-weight: 600;
         color: #1e3a8a;
         margin-bottom: 1rem;
      }
      
      .filter-options {
         display: flex;
         flex-direction: column;
         gap: 0.75rem;
      }
      
      .filter-checkbox {
         display: flex;
         align-items: center;
         gap: 0.5rem;
      }
      
      .filter-checkbox input[type="checkbox"] {
         width: 16px;
         height: 16px;
         accent-color: #2563eb;
      }
      
      .filter-checkbox label {
         color: #64748b;
         font-size: 0.875rem;
         cursor: pointer;
      }
      
      .filter-checkbox:hover label {
         color: #1e3a8a;
      }
      
      .price-range {
         display: flex;
         align-items: center;
         gap: 0.5rem;
      }
      
      .price-input {
         width: 100%;
         padding: 0.5rem;
         border: 1px solid #e2e8f0;
         border-radius: 6px;
         font-size: 0.875rem;
      }
      
      .filter-button {
         width: 100%;
         padding: 0.75rem;
         background-color: #2563eb;
         color: white;
         border: none;
         border-radius: 8px;
         font-weight: 600;
         cursor: pointer;
         transition: all 0.3s ease;
         margin-top: 1rem;
      }
      
      .filter-button:hover {
         background-color: #1d4ed8;
      }
      
      /* Products Content */
      .products-content {
         flex: 1;
      }
      
      .products-toolbar {
         display: flex;
         align-items: center;
         justify-content: space-between;
         margin-bottom: 1.5rem;
         background-color: white;
         border-radius: 12px;
         box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
         padding: 1rem 1.5rem;
      }
      
      .products-count {
         color: #64748b;
         font-size: 0.875rem;
      }
      
      .products-count strong {
         color: #1e3a8a;
      }
      
      .products-sort {
         display: flex;
         align-items: center;
         gap: 0.5rem;
      }
      
      .sort-label {
         color: #64748b;
         font-size: 0.875rem;
      }
      
      .sort-select {
         padding: 0.5rem;
         border: 1px solid #e2e8f0;
         border-radius: 6px;
         font-size: 0.875rem;
         color: #1e3a8a;
      }
      
      .view-options {
         display: flex;
         align-items: center;
         gap: 0.5rem;
      }
      
      .view-option {
         width: 35px;
         height: 35px;
         border-radius: 6px;
         background-color: #f1f5f9;
         color: #64748b;
         display: flex;
         align-items: center;
         justify-content: center;
         cursor: pointer;
         transition: all 0.3s ease;
      }
      
      .view-option:hover, .view-option.active {
         background-color: #2563eb;
         color: white;
      }
      
      /* Products Grid */
      .products-grid {
         display: grid;
         grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
         gap: 1.5rem;
      }
      
      /* Product Card - Same as in home.php */
      .product-card {
         background-color: white;
         border-radius: 12px;
         overflow: hidden;
         box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
         transition: all 0.3s ease;
         position: relative;
      }
      
      .product-card:hover {
         transform: translateY(-5px);
         box-shadow: 0 10px 15px rgba(0, 0, 0, 0.1);
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
      }
      
      .product-button:hover {
         background-color: #1d4ed8;
      }
      
      /* Products List View */
      .products-list {
         display: none; /* Hidden by default, shown when list view is active */
      }
      
      .product-list-item {
         display: grid;
         grid-template-columns: 200px 1fr auto;
         gap: 1.5rem;
         background-color: white;
         border-radius: 12px;
         overflow: hidden;
         box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
         transition: all 0.3s ease;
         margin-bottom: 1.5rem;
         padding: 1.5rem;
      }
      
      .product-list-item:hover {
         transform: translateY(-5px);
         box-shadow: 0 10px 15px rgba(0, 0, 0, 0.1);
      }
      
      .product-list-image {
         height: 150px;
         display: flex;
         align-items: center;
         justify-content: center;
         background-color: #f8fafc;
         border-radius: 8px;
         position: relative;
      }
      
      .product-list-image img {
         max-width: 80%;
         max-height: 80%;
         object-fit: contain;
      }
      
      .product-list-badge {
         position: absolute;
         top: 0.5rem;
         left: 0.5rem;
         padding: 0.25rem 0.5rem;
         border-radius: 20px;
         font-size: 0.75rem;
         font-weight: 600;
      }
      
      .product-list-badge.sale {
         background-color: #ef4444;
         color: white;
      }
      
      .product-list-badge.new {
         background-color: #2563eb;
         color: white;
      }
      
      .product-list-info {
         display: flex;
         flex-direction: column;
      }
      
      .product-list-title {
         font-size: 1.25rem;
         font-weight: 600;
         color: #1e3a8a;
         margin-bottom: 0.5rem;
      }
      
      .product-list-category {
         color: #64748b;
         font-size: 0.875rem;
         margin-bottom: 0.5rem;
      }
      
      .product-list-description {
         color: #64748b;
         font-size: 0.875rem;
         margin-bottom: 1rem;
         display: -webkit-box;
         -webkit-line-clamp: 3;
         -webkit-box-orient: vertical;
         overflow: hidden;
      }
      
      .product-list-rating {
         display: flex;
         align-items: center;
         gap: 0.25rem;
         margin-bottom: 0.5rem;
      }
      
      .product-list-rating i {
         color: #f59e0b;
         font-size: 0.875rem;
      }
      
      .product-list-rating span {
         color: #64748b;
         font-size: 0.875rem;
      }
      
      .product-list-actions {
         display: flex;
         flex-direction: column;
         justify-content: center;
         gap: 1rem;
      }
      
      .product-list-price {
         text-align: right;
      }
      
      .product-list-current-price {
         font-size: 1.5rem;
         font-weight: 700;
         color: #2563eb;
         display: block;
      }
      
      .product-list-old-price {
         font-size: 1rem;
         color: #64748b;
         text-decoration: line-through;
      }
      
      .product-list-button {
         padding: 0.75rem 1.5rem;
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
      }
      
      .product-list-button:hover {
         background-color: #1d4ed8;
      }
      
      .product-list-action {
         width: 40px;
         height: 40px;
         border-radius: 50%;
         background-color: #f1f5f9;
         color: #64748b;
         display: flex;
         align-items: center;
         justify-content: center;
         cursor: pointer;
         transition: all 0.3s ease;
      }
      
      .product-list-action:hover {
         background-color: #2563eb;
         color: white;
      }
      
      .product-list-action.active {
         background-color: #ef4444;
         color: white;
      }
      
      /* Pagination */
      .pagination {
         display: flex;
         align-items: center;
         justify-content: center;
         gap: 0.5rem;
         margin-top: 3rem;
      }
      
      .pagination-item {
         width: 40px;
         height: 40px;
         border-radius: 8px;
         background-color: white;
         color: #64748b;
         display: flex;
         align-items: center;
         justify-content: center;
         cursor: pointer;
         transition: all 0.3s ease;
         box-shadow: 0 2px 5px rgba(0, 0, 0, 0.05);
      }
      
      .pagination-item:hover {
         background-color: #f1f5f9;
         color: #1e3a8a;
      }
      
      .pagination-item.active {
         background-color: #2563eb;
         color: white;
      }
      
      .pagination-item.disabled {
         opacity: 0.5;
         cursor: not-allowed;
      }
      
      /* Mobile Filter Toggle */
      .mobile-filter-toggle {
         display: none;
         margin-bottom: 1.5rem;
      }
      
      .filter-toggle-button {
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
      }
      
      .filter-toggle-button:hover {
         background-color: #1d4ed8;
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
      
      /* Responsive Adjustments */
      @media (max-width: 992px) {
         .products-container {
            grid-template-columns: 1fr;
         }
         
         .filter-sidebar {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: 1000;
            overflow-y: auto;
            border-radius: 0;
         }
         
         .filter-sidebar.active {
            display: block;
         }
         
         .mobile-filter-toggle {
            display: block;
         }
         
         .filter-close {
            display: block;
            position: absolute;
            top: 1rem;
            right: 1rem;
            font-size: 1.5rem;
            cursor: pointer;
         }
      }
      
      @media (max-width: 768px) {
         .products-toolbar {
            flex-direction: column;
            gap: 1rem;
            align-items: flex-start;
         }
         
         .products-sort {
            width: 100%;
            justify-content: space-between;
         }
         
         .product-list-item {
            grid-template-columns: 1fr;
         }
         
         .product-list-image {
            height: 200px;
         }
      }
      
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
            <?php if (isset($_GET['search']) || isset($_GET['q'])): ?>
               <h1 class="page-title">Search Results: "<?php echo htmlspecialchars($_GET['search'] ?? $_GET['q']); ?>"</h1>
               <div class="breadcrumb">
                  <a href="products.php">Products</a>
                  <span class="breadcrumb-separator">/</span>
                  <span>Search Results</span>
               </div>
            <?php else: ?>
               <h1 class="page-title">Products</h1>
               <div class="breadcrumb">
               </div>
            <?php endif; ?>
         </div>
      </section>
      
      <!-- Mobile Filter Toggle -->
      <div class="mobile-filter-toggle">
         <button class="filter-toggle-button" id="filterToggle">
            <i class="fas fa-filter"></i>
            Filter Products
         </button>
      </div>
      
      <!-- Products Container -->
      <div class="products-container">
         <!-- Filter Sidebar -->
         <div class="filter-sidebar" id="filterSidebar">
   <div class="filter-header">
       <h3 class="filter-title">Filters</h3>
       <span class="filter-clear" id="clearFilters">Clear All</span>
       <span class="filter-close" id="filterClose">&times;</span>
   </div>
   
   <form id="filterForm" action="products.php" method="get">
       <!-- Keep the sort parameter when filtering -->
       <input type="hidden" name="sort" value="<?php echo htmlspecialchars($sortBy); ?>">
       
       <!-- Categories Filter -->
       <div class="filter-group">
           <h4 class="filter-group-title">Categories</h4>
           <div class="filter-options">
               <?php foreach ($categories as $category): ?>
                   <div class="filter-checkbox">
                       <input type="checkbox" id="category-<?php echo strtolower(str_replace(' ', '-', $category)); ?>" 
                              name="category[]" value="<?php echo $category; ?>" 
                              class="category-filter"
                              <?php echo (isset($_GET['category']) && ((is_array($_GET['category']) && in_array($category, $_GET['category'])) || (!is_array($_GET['category']) && $_GET['category'] === $category))) ? 'checked' : ''; ?>>
                       <label for="category-<?php echo strtolower(str_replace(' ', '-', $category)); ?>"><?php echo $category; ?></label>
                   </div>
               <?php endforeach; ?>
           </div>
       </div>
       
       <!-- Price Range Filter -->
       <div class="filter-group">
           <h4 class="filter-group-title">Price Range</h4>
           <div class="filter-options">
               <div class="price-range">
                   <input type="number" class="price-input" name="price_min" id="price_min" placeholder="Min" value="<?php echo $_GET['price_min'] ?? ''; ?>">
                   <span>to</span>
                   <input type="number" class="price-input" name="price_max" id="price_max" placeholder="Max" value="<?php echo $_GET['price_max'] ?? ''; ?>">
               </div>
           </div>
       </div>
       
       <!-- Availability Filter -->
       <div class="filter-group">
           <h4 class="filter-group-title">Availability</h4>
           <div class="filter-options">
               <div class="filter-checkbox">
                   <input type="checkbox" id="in-stock" name="in_stock" value="1" class="availability-filter"
                          <?php echo (isset($_GET['in_stock']) && $_GET['in_stock'] === '1') ? 'checked' : ''; ?>>
                   <label for="in-stock">In Stock</label>
               </div>
               <div class="filter-checkbox">
                   <input type="checkbox" id="on-sale" name="on_sale" value="1" class="availability-filter"
                          <?php echo (isset($_GET['on_sale']) && $_GET['on_sale'] === '1') ? 'checked' : ''; ?>>
                   <label for="on-sale">On Sale</label>
               </div>
           </div>
       </div>

<!-- Add the Apply Filter button here -->
<button type="submit" class="filter-button">
   <i class="fas fa-filter"></i> Apply Filters
</button>
   </form>
</div>
               
               <!-- Products Content -->
               <div class="products-content">
                  <!-- Products Toolbar -->
                  <div class="products-toolbar">
                     <div class="products-count">
                        Showing <strong><?php echo count($paginatedProducts); ?></strong> of <strong><?php echo $totalProducts; ?></strong> products
                     </div>
                     
                     <div class="products-sort">
                        <span class="sort-label">Sort by:</span>
                        <select class="sort-select" id="sortSelect" name="sort">
                           <option value="default" <?php echo ($sortBy === 'default') ? 'selected' : ''; ?>>Featured</option>
                           <option value="price_low" <?php echo ($sortBy === 'price_low') ? 'selected' : ''; ?>>Price: Low to High</option>
                           <option value="price_high" <?php echo ($sortBy === 'price_high') ? 'selected' : ''; ?>>Price: High to Low</option>
                           <option value="name_asc" <?php echo ($sortBy === 'name_asc') ? 'selected' : ''; ?>>Name: A to Z</option>
                           <option value="name_desc" <?php echo ($sortBy === 'name_desc') ? 'selected' : ''; ?>>Name: Z to A</option>
                           <option value="newest" <?php echo ($sortBy === 'newest') ? 'selected' : ''; ?>>Newest First</option>
                        </select>
                        
                        <div class="view-options">
                           <div class="view-option grid-view active" id="gridView">
                              <i class="fas fa-th-large"></i>
                           </div>
                           <div class="view-option list-view" id="listView">
                              <i class="fas fa-list"></i>
                           </div>
                        </div>
                     </div>
                  </div>
                  
                  <!-- Products Grid View -->
                  <div class="products-grid" id="productsGrid">
                     <?php if (count($paginatedProducts) > 0): ?>
                        <?php foreach ($paginatedProducts as $product): ?>
                           <div class="product-card">
                              <?php if ($product['sale']): ?>
                                 <div class="product-badge sale">Sale</div>
                              <?php elseif ($product['new']): ?>
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
                                    <span class="current-price"><?php echo formatPrice($product['price']); ?></span>
                                    <?php if ($product['sale'] && isset($product['oldPrice'])): ?>
                                       <span class="old-price"><?php echo formatPrice($product['oldPrice']); ?></span>
                                    <?php endif; ?>
                                 </div>
                                 
                                 <button class="product-button add-to-cart" data-product-id="<?php echo $product['id']; ?>">
                                    <i class="fas fa-shopping-cart"></i>
                                      Add to Cart
                                  </button>
                              </div>
                           </div>
                        <?php endforeach; ?>
                     <?php else: ?>
                        <div class="no-products">
                           <p>No products found matching your criteria. Please try different filters.</p>
                        </div>
                     <?php endif; ?>
                  </div>
                  
                  <!-- Products List View -->
                  <div class="products-list" id="productsList">
                     <?php if (count($paginatedProducts) > 0): ?>
                        <?php foreach ($paginatedProducts as $product): ?>
                           <div class="product-list-item">
                              <div class="product-list-image">
                                 <?php if ($product['sale']): ?>
                                    <div class="product-list-badge sale">Sale</div>
                                 <?php elseif ($product['new']): ?>
                                    <div class="product-list-badge new">New</div>
                                 <?php endif; ?>
                                 <img src="<?php echo $product['image']; ?>" alt="<?php echo $product['name']; ?>">
                              </div>
                              
                              <div class="product-list-info">
                                 <h3 class="product-list-title"><?php echo $product['name']; ?></h3>
                                 <p class="product-list-category"><?php echo $product['category']; ?></p>
                                 
                                 <div class="product-list-rating">
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
                                 
                                 <p class="product-list-description">
                                    <?php echo isset($product['description']) ? $product['description'] : 'No description available.'; ?>
                                 </p>
                              </div>
                              
                              <div class="product-list-actions">
                                 <div class="product-list-price">
                                    <span class="product-list-current-price"><?php echo formatPrice($product['price']); ?></span>
                                    <?php if ($product['sale'] && isset($product['oldPrice'])): ?>
                                       <span class="product-list-old-price"><?php echo formatPrice($product['oldPrice']); ?></span>
                                    <?php endif; ?>
                                 </div>
                                 
                                 <button class="product-list-button add-to-cart" data-product-id="<?php echo $product['id']; ?>">
                                    <i class="fas fa-shopping-cart"></i>
                                    Add to Cart
                                 </button>
                                 
                                 <div class="product-list-action wishlist-btn" data-product-id="<?php echo $product['id']; ?>">
                                    <i class="far fa-heart"></i>
                                 </div>
                              </div>
                           </div>
                        <?php endforeach; ?>
                     <?php else: ?>
                        <div class="no-products">
                           <p>No products found matching your criteria. Please try different filters.</p>
                        </div>
                     <?php endif; ?>
                  </div>
                  
                  
               </div>
            </div>
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

   <script>
      document.addEventListener('DOMContentLoaded', function() {
         // View Toggle
         const gridView = document.getElementById('gridView');
         const listView = document.getElementById('listView');
         const productsGrid = document.getElementById('productsGrid');
         const productsList = document.getElementById('productsList');
         
         gridView.addEventListener('click', function() {
            gridView.classList.add('active');
            listView.classList.remove('active');
            productsGrid.style.display = 'grid';
            productsList.style.display = 'none';
         });
         
         listView.addEventListener('click', function() {
   listView.classList.add('active');
   gridView.classList.remove('active');
   productsList.style.display = 'block';
   productsGrid.style.display = 'none';
   
   // Re-initialize product modal triggers for list view
   initProductModalTriggers();
});
         
         // Mobile Filter Toggle
         const filterToggle = document.getElementById('filterToggle');
         const filterSidebar = document.getElementById('filterSidebar');
         const filterClose = document.getElementById('filterClose');
         
         if (filterToggle) {
            filterToggle.addEventListener('click', function() {
               filterSidebar.classList.add('active');
               document.body.style.overflow = 'hidden';
            });
         }
         
         if (filterClose) {
            filterClose.addEventListener('click', function() {
               filterSidebar.classList.remove('active');
               document.body.style.overflow = '';
            });
         }
         
         // Clear Filters
         const clearFilters = document.getElementById('clearFilters');
         
         if (clearFilters) {
            clearFilters.addEventListener('click', function() {
               window.location.href = 'products.php';
            });
         }
         
         // Sort Select
         const sortSelect = document.getElementById('sortSelect');
         
         if (sortSelect) {
            sortSelect.addEventListener('change', function() {
               const currentUrl = new URL(window.location.href);
               currentUrl.searchParams.set('sort', this.value);
               window.location.href = currentUrl.toString();
            });
         }
         
         // Wishlist functionality
         const wishlistBtns = document.querySelectorAll('.wishlist-btn');
         wishlistBtns.forEach(btn => {
            btn.addEventListener('click', function() {
               const icon = this.querySelector('i');
               if (icon.classList.contains('far')) {
                  icon.classList.remove('far');
                  icon.classList.add('fas');
                  this.classList.add('active');
               } else {
                  icon.classList.remove('fas');
                  icon.classList.add('far');
                  this.classList.remove('active');
               }
            });
         });
         
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

         // Add to cart
         modalAddToCartBtn.addEventListener('click', function() {
            if (currentProduct) {
               addToCart(currentProduct.id, quantity);
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
            if (product.oldPrice) {
               modalOldPrice.textContent = formatPrice(product.oldPrice);
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

         // Add to cart
         function addToCart(productId, quantity) {
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

            // Append the form to the body and submit it
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

         // Toggle wishlist
         function toggleWishlist(productId) {
            // Find the wishlist button for this product in the main page
            const wishlistBtn = document.querySelector(`.wishlist-btn[data-product-id="${productId}"]`);
            if (wishlistBtn) {
               // Simulate click on the wishlist button
               wishlistBtn.click();

               // Update the wishlist button in the modal
               const icon = wishlistBtn.querySelector('i');
               if (icon.classList.contains('fas')) {
                  modalAddToWishlistBtn.innerHTML = '<i class="fas fa-heart"></i> Remove from Wishlist';
                  modalAddToWishlistBtn.classList.add('active');
               } else {
                  modalAddToWishlistBtn.innerHTML = '<i class="far fa-heart"></i> Add to Wishlist';
                  modalAddToWishlistBtn.classList.remove('active');
               }
            }
         }

         // Find the getProductData function in the JavaScript section and replace it with this updated version:

// Get product data from the server using AJAX
function getProductData(productId) {
    // Use AJAX to fetch the product data from the server
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

// Update the quick view button click handler to use the Promise-based getProductData
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

// Update the product card click handler to use the Promise-based getProductData
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

// Update the initProductModalTriggers function to use the Promise-based getProductData
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
    
    // For list view items
    document.querySelectorAll('.product-list-item').forEach(item => {
        item.addEventListener('click', function(e) {
            // Don't trigger if clicking on buttons or actions
            if (!e.target.closest('.product-list-button') && !e.target.closest('.product-list-action')) {
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
}

         // Initialize modal triggers
         initProductModalTriggers();
         
         // Original Add to cart functionality
         const addToCartBtns = document.querySelectorAll('.add-to-cart');
         addToCartBtns.forEach(btn => {
            btn.addEventListener('click', function(e) {
               e.preventDefault();
               const productId = this.getAttribute('data-product-id');
               
               // Create a form to submit the data
               const form = document.createElement('form');
               form.method = 'POST';
               form.action = 'add_to_cart.php';
               
               // Create hidden inputs for the product data
               const productIdInput = document.createElement('input');
               productIdInput.type = 'hidden';
               productIdInput.name = 'product_id';
               productIdInput.value = productId;
               
               // Find the product data
               const productCard = this.closest('.product-card') || this.closest('.product-list-item');
               const productName = productCard.querySelector('.product-title')?.textContent || 
                                  productCard.querySelector('.product-list-title')?.textContent;
               const priceText = productCard.querySelector('.current-price')?.textContent || 
                               productCard.querySelector('.product-list-current-price')?.textContent;
               const price = parseFloat(priceText.replace(/[^0-9.]/g, ''));
               
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
               
               // Append the form to the body and submit it
               document.body.appendChild(form);
               form.submit();
            });
         });
      });
   </script>
<script>
// Global function for live filtering products
window.liveFilterProducts = function(query) {
    query = query.toLowerCase();
    
    // Get all product cards and list items
    const productCards = document.querySelectorAll('.product-card');
    const productListItems = document.querySelectorAll('.product-list-item');
    
    // Get the current view (grid or list)
    const isGridView = document.getElementById('productsGrid').style.display !== 'none';
    
    // Get the products count element
    const productsCount = document.querySelector('.products-count');
    
    let visibleCount = 0;
    
    // Filter grid view products
    productCards.forEach(card => {
        const productName = card.querySelector('.product-title').textContent.toLowerCase();
        const productCategory = card.querySelector('.product-category').textContent.toLowerCase();
        
        // Check if product matches the query
        const matches = productName.includes(query) || productCategory.includes(query);
        
        // Show or hide the product
        card.style.display = matches ? '' : 'none';
        
        // Count visible products
        if (matches) visibleCount++;
    });
    
    // Filter list view products
    productListItems.forEach(item => {
        const productName = item.querySelector('.product-list-title').textContent.toLowerCase();
        const productCategory = item.querySelector('.product-list-category').textContent.toLowerCase();
        const productDescription = item.querySelector('.product-list-description').textContent.toLowerCase();
        
        // Check if product matches the query
        const matches = productName.includes(query) || 
                        productCategory.includes(query) || 
                        productDescription.includes(query);
        
        // Show or hide the product
        item.style.display = matches ? '' : 'none';
    });
    
    // Update the products count
    if (productsCount) {
        const totalCount = productCards.length;
        productsCount.innerHTML = `Showing <strong>${visibleCount}</strong> of <strong>${totalCount}</strong> products`;
    }
    
    // Show "no products found" message if needed
    const noProductsGrid = document.querySelector('#productsGrid .no-products');
    const noProductsList = document.querySelector('#productsList .no-products');
    
    // Remove existing "no products" messages
    if (noProductsGrid) noProductsGrid.remove();
    if (noProductsList) noProductsList.remove();
    
    // Add "no products" message if no results
    if (visibleCount === 0) {
        const noProductsMessage = `
            <div class="no-products">
                <p>No products found matching your search: "${query}". Please try a different search term.</p>
            </div>
        `;
        
        if (isGridView) {
            document.getElementById('productsGrid').insertAdjacentHTML('beforeend', noProductsMessage);
        } else {
            document.getElementById('productsList').insertAdjacentHTML('beforeend', noProductsMessage);
        }
    }
};

// Initialize with URL parameters if any
document.addEventListener('DOMContentLoaded', function() {
    // Check if there's a search parameter in the URL
    const urlParams = new URLSearchParams(window.location.search);
    const searchQuery = urlParams.get('search') || urlParams.get('q') || '';
    
    // If there's a search query, populate the search input
    if (searchQuery) {
        const desktopSearchInput = document.getElementById('desktop-search-input');
        const mobileSearchInput = document.getElementById('mobile-search-input');
        
        if (desktopSearchInput) desktopSearchInput.value = searchQuery;
        if (mobileSearchInput) mobileSearchInput.value = searchQuery;
        
        // Show the clear button
        const desktopSearchClear = document.getElementById('desktop-search-clear');
        const mobileSearchClear = document.getElementById('mobile-search-clear');
        
        if (desktopSearchClear) desktopSearchClear.style.display = 'block';
        if (mobileSearchClear) mobileSearchClear.style.display = 'block';
    }
});
</script>
</body>
</html>
