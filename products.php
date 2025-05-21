<?php
session_start();

// Check if admin is logged in
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true || !isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header('Location: login.php');
    exit;
}

// Include the products data from data file
include('../data/products.php');

// Page title
$pageTitle = "Products";

// Get unique categories for filter
$categories = [
    'Mens Bag & Accessories',
    'Women Accessories',
    'Womens Bags',
    'Sports & Travel',
    'Hobbies & Stationery',
    'Mobile Accessories',
    'Laptops & Computers'
];

// Process any actions (delete, status change, etc.)
if (isset($_GET['action']) && isset($_GET['id'])) {
    $action = $_GET['action'];
    $id = intval($_GET['id']);
    
    // This is just a placeholder - in a real app, you would update the database
    if ($action === 'delete') {
        // Remove product with matching ID
        foreach ($products as $key => $product) {
            if ($product['id'] === $id) {
                unset($products[$key]);
                break;
            }
        }
        $products = array_values($products); // Re-index array
        
        // Save the updated products array back to the file
        $updatedData = "<?php\n// Sample product data\n\$products = " . var_export($products, true) . ";\n";
        file_put_contents('../data/products.php', $updatedData);
        
        // Redirect to avoid resubmission
        header('Location: products.php?deleted=true');
        exit;
    }
}

// Format price function
function formatPrice($price) {
    return '₱' . number_format($price, 2);
}

// Get status based on stock
function getStatus($stock) {
    if ($stock <= 0) {
        return 'Out of Stock';
    } elseif ($stock <= 20) {
        return 'Low Stock';
    } else {
        return 'Active';
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Products - Mugna Admin</title>
    <link rel="stylesheet" href="css/admin-styles.css">
    <link rel="shortcut icon" href="../images/mugna-icon.png" type="image/x-icon">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* Modern UI Styles */
        :root {
            --primary-color: #2563eb;
            --primary-hover: #1d4ed8;
            --success-color: #10b981;
            --warning-color: #f59e0b;
            --danger-color: #ef4444;
            --light-bg: #f8fafc;
            --card-bg: #ffffff;
            --border-color: #e2e8f0;
            --text-primary: #1e293b;
            --text-secondary: #64748b;
            --border-radius: 0.5rem;
            --shadow-sm: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
            --shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
            --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
            --transition: all 0.2s ease;
        }

        body {
            background-color: var(--light-bg);
            color: var(--text-primary);
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        .content-wrapper {
            padding: 1.5rem;
        }

        .content-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 1.5rem;
        }

        .content-header h1 {
            font-size: 1.5rem;
            font-weight: 600;
            color: var(--text-primary);
            margin: 0;
        }

        /* Card Styles */
        .card {
            background-color: var(--card-bg);
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
            margin-bottom: 1.5rem;
            overflow: hidden;
        }

        .card-header {
            padding: 1rem 1.5rem;
            border-bottom: 1px solid var(--border-color);
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .card-header h2 {
            font-size: 1.125rem;
            font-weight: 600;
            margin: 0;
        }

        .card-body {
            padding: 1.5rem;
        }

        /* Filter Styles */
        .filters-container {
            display: flex;
            flex-wrap: wrap;
            gap: 1rem;
            margin-bottom: 1.5rem;
            align-items: flex-end;
        }

        .filter-group {
            flex: 1;
            min-width: 200px;
        }

        .filter-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-size: 0.875rem;
            color: var(--text-secondary);
        }

        .filter-group select,
        .search-container input {
            width: 100%;
            padding: 0.625rem 0.75rem;
            border: 1px solid var(--border-color);
            border-radius: var(--border-radius);
            background-color: var(--card-bg);
            color: var(--text-primary);
            font-size: 0.875rem;
            transition: var(--transition);
        }

        .filter-group select:focus,
        .search-container input:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
        }

        .search-container {
            flex: 1;
            min-width: 250px;
            position: relative;
        }

        .search-container input {
            padding-left: 2.5rem;
        }

        .search-icon {
            position: absolute;
            left: 0.75rem;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-secondary);
        }

        /* Button Styles */
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 0.625rem 1rem;
            border-radius: var(--border-radius);
            font-weight: 500;
            font-size: 0.875rem;
            transition: var(--transition);
            cursor: pointer;
            border: none;
            gap: 0.5rem;
        }

        .btn-primary {
            background-color: var(--primary-color);
            color: white;
        }

        .btn-primary:hover {
            background-color: var(--primary-hover);
        }

        .btn-outline {
            background-color: transparent;
            border: 1px solid var(--border-color);
            color: var(--text-primary);
        }

        .btn-outline:hover {
            border-color: var(--primary-color);
            color: var(--primary-color);
        }

        .btn-danger {
            background-color: var(--danger-color);
            color: white;
        }

        .btn-danger:hover {
            background-color: #dc2626;
        }

        .btn-icon {
            width: 2rem;
            height: 2rem;
            border-radius: 0.375rem;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background-color: transparent;
            color: var(--text-secondary);
            border: 1px solid transparent;
            transition: var(--transition);
            cursor: pointer;
        }

        .btn-icon:hover {
            background-color: var(--light-bg);
            color: var(--primary-color);
            border-color: var(--border-color);
        }

        .btn-icon.active {
            background-color: var(--primary-color);
            color: white;
        }

        /* Table Styles */
        .table-container {
            overflow-x: auto;
            border-radius: var(--border-radius);
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        table th,
        table td {
            padding: 1rem;
            text-align: left;
            border-bottom: 1px solid var(--border-color);
        }

        table th {
            font-weight: 600;
            color: var(--text-secondary);
            background-color: var(--light-bg);
            font-size: 0.875rem;
        }

        table tr:last-child td {
            border-bottom: none;
        }

        table tr:hover td {
            background-color: var(--light-bg);
        }

        /* Status Badge Styles */
        .status-badge {
            display: inline-flex;
            align-items: center;
            padding: 0.25rem 0.75rem;
            border-radius: 50px;
            font-size: 0.75rem;
            font-weight: 500;
        }

        .status-badge.active {
            background-color: rgba(16, 185, 129, 0.1);
            color: var(--success-color);
        }

        .status-badge.low-stock {
            background-color: rgba(245, 158, 11, 0.1);
            color: var(--warning-color);
        }

        .status-badge.out-of-stock {
            background-color: rgba(239, 68, 68, 0.1);
            color: var(--danger-color);
        }

        /* Action Buttons */
        .action-buttons {
            display: flex;
            gap: 0.5rem;
        }

        /* Checkbox Styles */
        .checkbox-wrapper {
            position: relative;
            display: inline-block;
            width: 18px;
            height: 18px;
        }

        .checkbox-wrapper input[type="checkbox"] {
            opacity: 0;
            position: absolute;
            width: 100%;
            height: 100%;
            cursor: pointer;
            z-index: 2;
        }

        .checkbox-wrapper .checkmark {
            position: absolute;
            top: 0;
            left: 0;
            width: 18px;
            height: 18px;
            border: 1px solid var(--border-color);
            border-radius: 4px;
            background-color: white;
        }

        .checkbox-wrapper input[type="checkbox"]:checked ~ .checkmark {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
        }

        .checkbox-wrapper .checkmark:after {
            content: "";
            position: absolute;
            display: none;
            left: 6px;
            top: 2px;
            width: 5px;
            height: 10px;
            border: solid white;
            border-width: 0 2px 2px 0;
            transform: rotate(45deg);
        }

        .checkbox-wrapper input[type="checkbox"]:checked ~ .checkmark:after {
            display: block;
        }

        /* Table Actions */
        .table-actions {
            display: flex;
            justify-content: space-between;
            padding: 1rem 1.5rem;
            border-bottom: 1px solid var(--border-color);
            align-items: center;
        }

        .bulk-actions {
            display: flex;
            gap: 0.5rem;
            align-items: center;
        }

        .bulk-actions select {
            padding: 0.5rem;
            border: 1px solid var(--border-color);
            border-radius: var(--border-radius);
            background-color: white;
            color: var(--text-primary);
            font-size: 0.875rem;
        }

        .view-options {
            display: flex;
            gap: 0.5rem;
        }

        /* Pagination */
        .pagination {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1rem 1.5rem;
            border-top: 1px solid var(--border-color);
        }

        .pagination-info {
            font-size: 0.875rem;
            color: var(--text-secondary);
        }

        .pagination-controls {
            display: flex;
            gap: 0.25rem;
        }

        .pagination-btn {
            min-width: 2rem;
            height: 2rem;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border: 1px solid var(--border-color);
            border-radius: 0.375rem;
            background-color: white;
            color: var(--text-primary);
            font-size: 0.875rem;
            transition: var(--transition);
            cursor: pointer;
        }

        .pagination-btn:hover:not(.disabled) {
            border-color: var(--primary-color);
            color: var(--primary-color);
        }

        .pagination-btn.active {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
            color: white;
        }

        .pagination-btn.disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        /* Product Grid View */
        .product-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 1.5rem;
        }

        .product-card {
            background-color: var(--card-bg);
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
            overflow: hidden;
            transition: var(--transition);
            position: relative;
        }

        .product-card:hover {
            transform: translateY(-5px);
            box-shadow: var(--shadow-lg);
        }

        .product-card-header {
            padding: 1rem;
            padding-left: 3rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .product-card-body {
            padding: 0 1rem 1rem;
        }

        .product-card-title {
            font-weight: 600;
            margin-bottom: 0.5rem;
            color: var(--text-primary);
            font-size: 1rem;
        }

        .product-card-category {
            color: var(--text-secondary);
            font-size: 0.875rem;
            margin-bottom: 0.5rem;
        }

        .product-card-price {
            font-weight: 600;
            color: var(--primary-color);
            margin-bottom: 0.5rem;
            font-size: 1.125rem;
        }

        .product-card-stock {
            font-size: 0.875rem;
            color: var(--text-secondary);
            margin-bottom: 1rem;
        }

        .product-card-actions {
            display: flex;
            gap: 0.5rem;
            margin-top: 1rem;
        }

        .product-card-checkbox {
            position: absolute;
            top: 1rem;
            left: 1rem;
            z-index: 1;
        }

        /* Responsive Adjustments */
        @media (max-width: 768px) {
            .filters-container {
                flex-direction: column;
                align-items: stretch;
            }
            
            .filter-group, .search-container {
                min-width: 100%;
            }
            
            .table-actions {
                flex-direction: column;
                gap: 1rem;
                align-items: flex-start;
            }
            
            .pagination {
                flex-direction: column;
                gap: 1rem;
                align-items: flex-start;
            }
        }

        /* View Toggle */
        .view-toggle {
            display: none;
        }

        #grid-view:checked ~ .product-grid {
            display: grid;
        }

        #grid-view:checked ~ .table-container {
            display: none;
        }

        #list-view:checked ~ .product-grid {
            display: none;
        }

        #list-view:checked ~ .table-container {
            display: block;
        }

        #grid-view:checked ~ .table-actions .view-options .grid-btn {
            background-color: var(--primary-color);
            color: white;
        }

        #list-view:checked ~ .table-actions .view-options .list-btn {
            background-color: var(--primary-color);
            color: white;
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
        }

        .modal-content {
            background-color: var(--card-bg);
            margin: 5% auto;
            padding: 0;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow-lg);
            width: 90%;
            max-width: 700px;
            animation: modalFadeIn 0.3s;
        }

        @keyframes modalFadeIn {
            from {opacity: 0; transform: translateY(-50px);}
            to {opacity: 1; transform: translateY(0);}
        }

        .modal-header {
            padding: 1.5rem;
            border-bottom: 1px solid var(--border-color);
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .modal-header h3 {
            margin: 0;
            font-size: 1.25rem;
            font-weight: 600;
            color: var(--text-primary);
        }

        .modal-close {
            background: transparent;
            border: none;
            font-size: 1.5rem;
            cursor: pointer;
            color: var(--text-secondary);
        }

        .modal-body {
            padding: 1.5rem;
            max-height: 70vh;
            overflow-y: auto;
        }

        .modal-footer {
            padding: 1rem 1.5rem;
            border-top: 1px solid var(--border-color);
            display: flex;
            justify-content: flex-end;
            gap: 1rem;
        }

        .form-row {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 1.5rem;
            margin-bottom: 1.5rem;
        }

        @media (max-width: 768px) {
            .form-row {
                grid-template-columns: 1fr;
            }
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
            color: var(--text-primary);
        }

        .form-group input[type="text"],
        .form-group input[type="number"],
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid var(--border-color);
            border-radius: var(--border-radius);
            font-size: 0.875rem;
        }

        .form-group textarea {
            min-height: 100px;
            resize: vertical;
        }

        .checkbox-group {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin-bottom: 1rem;
        }

        .checkbox-group label {
            margin-bottom: 0;
            cursor: pointer;
        }

        /* Image Upload Styles */
        .image-upload-container {
            margin-bottom: 15px;
        }

        .image-upload-area {
            border: 2px dashed var(--border-color);
            border-radius: var(--border-radius);
            padding: 2rem 1rem;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s ease;
            background-color: var(--light-bg);
        }

        .image-upload-area:hover, .image-upload-area.dragover {
            border-color: var(--primary-color);
            background-color: rgba(37, 99, 235, 0.05);
        }

        .upload-icon {
            font-size: 2.5rem;
            color: var(--primary-color);
            margin-bottom: 1rem;
        }

        .upload-text p {
            margin-bottom: 0.5rem;
            color: var(--text-secondary);
        }

        .upload-button {
            display: inline-block;
            padding: 0.5rem 1rem;
            background-color: var(--primary-color);
            color: white;
            border-radius: var(--border-radius);
            cursor: pointer;
            transition: background-color 0.3s ease;
        }

        .upload-button:hover {
            background-color: var(--primary-hover);
        }

        .new-image-preview {
            position: relative;
            margin-top: 1rem;
            text-align: center;
            border: 1px solid var(--border-color);
            border-radius: var(--border-radius);
            padding: 0.5rem;
            background-color: white;
        }

        .new-image-preview img {
            max-height: 150px;
            max-width: 100%;
        }

        .remove-image-btn {
            position: absolute;
            top: -10px;
            right: -10px;
            width: 25px;
            height: 25px;
            border-radius: 50%;
            background-color: var(--danger-color);
            color: white;
            border: none;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
        }

        .remove-image-btn:hover {
            background-color: #dc2626;
        }
        
        /* Print Report Button Styles */
        .print-report-btn {
            background-color: var(--success-color);
            color: white;
            margin-left: auto;
        }
        
        .print-report-btn:hover {
            background-color: #0d9488;
        }
        
        .filters-actions {
            display: flex;
            justify-content: flex-end;
            margin-top: 1rem;
        }
    </style>
</head>
<body>
    <div class="admin-container">
        <!-- Sidebar -->
        <?php include 'includes/sidebar.php'; ?>

        <!-- Main Content -->
        <main class="main-content">
            <!-- Header -->
            <?php include 'includes/header.php'; ?>

            <!-- Products Content -->
            <div class="content-wrapper">
                <div class="content-header">
                    <h1>Products</h1>
                    <a href="add_products.php" class="btn btn-primary">
                        <i class="fas fa-plus"></i> Add New Product
                    </a>
                </div>

                <?php if (isset($_GET['deleted']) && $_GET['deleted'] === 'true'): ?>
                <div class="alert alert-success" style="background-color: #dcfce7; color: #166534; padding: 1rem; border-radius: 0.5rem; margin-bottom: 1rem;">
                    <i class="fas fa-check-circle"></i> Product has been deleted successfully.
                </div>
                <?php endif; ?>

                <!-- Filters Card -->
                <div class="card">
                    <div class="card-header">
                        <h2>Filters</h2>
                    </div>
                    <div class="card-body">
                        <div class="filters-container">
                            <div class="filter-group">
                                <label for="category-filter">Category</label>
                                <select id="category-filter">
                                    <option value="">All Categories</option>
                                    <?php foreach ($categories as $category): ?>
                                    <option value="<?php echo $category; ?>"><?php echo $category; ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="filter-group">
                                <label for="status-filter">Status</label>
                                <select id="status-filter">
                                    <option value="">All Status</option>
                                    <option value="Active">Active</option>
                                    <option value="Low Stock">Low Stock</option>
                                    <option value="Out of Stock">Out of Stock</option>
                                </select>
                            </div>
                            <div class="filter-group">
                                <label for="price-filter">Price Range</label>
                                <select id="price-filter">
                                    <option value="">All Prices</option>
                                    <option value="0-1000">₱0 - ₱1,000</option>
                                    <option value="1000-5000">₱1,000 - ₱5,000</option>
                                    <option value="5000-10000">₱5,000 - ₱10,000</option>
                                    <option value="10000-50000">₱10,000 - ₱50,000</option>
                                    <option value="50000+">₱50,000+</option>
                                </select>
                            </div>
                            <div class="search-container">
                                <i class="fas fa-search search-icon"></i>
                                <input type="text" id="search-input" placeholder="Search products...">
                            </div>
                        </div>
                        <div class="filters-actions">
                            <button id="print-report-btn" class="btn print-report-btn">
                                <i class="fas fa-file-pdf"></i> Print Report
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Products Card -->
                <div class="card">
                    <!-- View Toggle -->
                    <input type="radio" name="view" id="grid-view" class="view-toggle" checked>
                    <input type="radio" name="view" id="list-view" class="view-toggle">

                    <div class="table-actions">
                        <div class="bulk-actions">
                            <select id="bulk-action">
                                <option value="">Bulk Actions</option>
                                <option value="activate">Activate</option>
                                <option value="deactivate">Deactivate</option>
                                <option value="delete">Delete</option>
                            </select>
                            <button class="btn btn-outline" id="apply-bulk-action">Apply</button>
                        </div>
                        <div class="view-options">
                            <label for="grid-view" class="btn-icon grid-btn" title="Grid View">
                                <i class="fas fa-th-large"></i>
                            </label>
                            <label for="list-view" class="btn-icon list-btn" title="List View">
                                <i class="fas fa-list"></i>
                            </label>
                        </div>
                    </div>

                    <!-- Grid View -->
                    <div class="product-grid">
                        <?php foreach ($products as $product): 
                            // Determine status based on stock if not set
                            $status = isset($product['status']) ? $product['status'] : getStatus($product['stock'] ?? 0);
                            $category = isset($product['category']) ? $product['category'] : 'Uncategorized';
                            $stock = isset($product['stock']) ? $product['stock'] : 0;
                        ?>
                        <div class="product-card" 
                             data-id="<?php echo $product['id']; ?>" 
                             data-category="<?php echo $category; ?>" 
                             data-status="<?php echo $status; ?>"
                             data-price="<?php echo $product['price']; ?>"
                             data-sale="<?php echo $product['sale'] ? 'true' : 'false'; ?>"
                             data-description="<?php echo htmlspecialchars($product['description'] ?? ''); ?>">
                            <div class="product-card-checkbox">
                                <label class="checkbox-wrapper">
                                    <input type="checkbox" class="product-select" value="<?php echo $product['id']; ?>">
                                    <span class="checkmark"></span>
                                </label>
                            </div>
                            <div class="product-card-header">
                                <span class="status-badge <?php echo strtolower(str_replace(' ', '-', $status)); ?>">
                                    <?php echo $status; ?>
                                </span>
                            </div>
                            <div class="product-card-body">
                                <div class="product-image" style="height: 150px; display: flex; align-items: center; justify-content: center; margin-bottom: 1rem;">
                                    <img src="../<?php echo $product['image'] ?? 'images/products/default-product.jpg'; ?>" alt="<?php echo $product['name']; ?>" style="max-height: 100%; max-width: 100%; object-fit: contain;">
                                </div>
                                <h3 class="product-card-title"><?php echo $product['name']; ?></h3>
                                <div class="product-card-category">
                                    <i class="fas fa-tag"></i> <?php echo $category; ?>
                                </div>
                                <div class="product-card-price"><?php echo formatPrice($product['price']); ?></div>
                                <div class="product-card-stock">
                                    <i class="fas fa-cubes"></i> Stock: <?php echo $stock; ?> units
                                </div>
                                <div class="product-card-actions">
                                    <button class="btn-icon view-product" title="View" data-id="<?php echo $product['id']; ?>">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                    <button class="btn-icon edit-product" title="Edit" data-id="<?php echo $product['id']; ?>">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button class="btn-icon" title="Delete" onclick="deleteProduct(<?php echo $product['id']; ?>)">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>

                    <!-- List View -->
                    <div class="table-container">
                        <table>
                            <thead>
                                <tr>
                                    <th>
                                        <label class="checkbox-wrapper">
                                            <input type="checkbox" id="select-all">
                                            <span class="checkmark"></span>
                                        </label>
                                    </th>
                                    <th>ID</th>
                                    <th>Product</th>
                                    <th>Category</th>
                                    <th>Price</th>
                                    <th>Stock</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($products as $product): 
                                    // Determine status based on stock if not set
                                    $status = isset($product['status']) ? $product['status'] : getStatus($product['stock'] ?? 0);
                                    $category = isset($product['category']) ? $product['category'] : 'Uncategorized';
                                    $stock = isset($product['stock']) ? $product['stock'] : 0;
                                ?>
                                <tr data-id="<?php echo $product['id']; ?>" 
                                    data-category="<?php echo $category; ?>" 
                                    data-status="<?php echo $status; ?>"
                                    data-price="<?php echo $product['price']; ?>"
                                    data-sale="<?php echo $product['sale'] ? 'true' : 'false'; ?>"
                                    data-description="<?php echo htmlspecialchars($product['description'] ?? ''); ?>">
                                    <td>
                                        <label class="checkbox-wrapper">
                                            <input type="checkbox" class="product-select" value="<?php echo $product['id']; ?>">
                                            <span class="checkmark"></span>
                                        </label>
                                    </td>
                                    <td><?php echo $product['id']; ?></td>
                                    <td><?php echo $product['name']; ?></td>
                                    <td><?php echo $category; ?></td>
                                    <td><?php echo formatPrice($product['price']); ?></td>
                                    <td><?php echo $stock; ?></td>
                                    <td>
                                        <span class="status-badge <?php echo strtolower(str_replace(' ', '-', $status)); ?>">
                                            <?php echo $status; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="action-buttons">
                                            <button class="btn-icon view-product" title="View" data-id="<?php echo $product['id']; ?>">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                            <button class="btn-icon edit-product" title="Edit" data-id="<?php echo $product['id']; ?>">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <button class="btn-icon" title="Delete" onclick="deleteProduct(<?php echo $product['id']; ?>)">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <div class="pagination">
                        <span class="pagination-info">Showing 1 to <?php echo count($products); ?> of <?php echo count($products); ?> entries</span>
                        <div class="pagination-controls">
                            <button class="pagination-btn disabled">
                                <i class="fas fa-chevron-left"></i>
                            </button>
                            <button class="pagination-btn active">1</button>
                            <button class="pagination-btn disabled">
                                <i class="fas fa-chevron-right"></i>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <!--Update the edit product modal to include image upload -->
    <!-- Edit Product Modal -->
    <div id="editProductModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Edit Product</h3>
                <button class="modal-close">&times;</button>
            </div>
            <div class="modal-body">
                <form id="editProductForm" enctype="multipart/form-data">
                    <input type="hidden" id="edit-product-id" name="id">
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="edit-name">Product Name</label>
                            <input type="text" id="edit-name" name="name" required>
                        </div>
                        <div class="form-group">
                            <label for="edit-category">Category</label>
                            <select id="edit-category" name="category" required>
                                <?php foreach ($categories as $category): ?>
                                <option value="<?php echo $category; ?>"><?php echo $category; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="edit-price">Price (₱)</label>
                            <input type="number" id="edit-price" name="price" step="0.01" min="0" required>
                        </div>
                        <div class="form-group">
                            <label for="edit-stock">Stock</label>
                            <input type="number" id="edit-stock" name="stock" min="0" required>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="edit-status">Status</label>
                            <select id="edit-status" name="status">
                                <option value="Active">Active</option>
                                <option value="Low Stock">Low Stock</option>
                                <option value="Out of Stock">Out of Stock</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <div class="checkbox-group">
                                <input type="checkbox" id="edit-sale" name="sale">
                                <label for="edit-sale">On Sale</label>
                            </div>
                            <div id="old-price-container" style="display: none; margin-top: 10px;">
                                <label for="edit-old-price">Old Price (₱)</label>
                                <input type="number" id="edit-old-price" name="oldPrice" step="0.01" min="0">
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="edit-image">Product Image</label>
                        <div id="current-image-preview" style="margin-bottom: 10px; text-align: center;">
                            <img src="/placeholder.svg" alt="Current product image" style="max-height: 150px; max-width: 100%;">
                        </div>
                        
                        <div class="image-upload-container" id="image-upload-container">
                            <div class="image-upload-area" id="image-upload-area">
                                <div class="upload-icon">
                                    <i class="fas fa-cloud-upload-alt"></i>
                                </div>
                                <div class="upload-text">
                                    <p>Drag & drop a new image here or</p>
                                    <label for="edit-image" class="upload-button">Browse Files</label>
                                </div>
                                <input type="file" id="edit-image" name="image" accept="image/*" style="display: none;">
                            </div>
                            <div class="new-image-preview" id="new-image-preview" style="display: none;">
                                <img src="/placeholder.svg" alt="New image preview">
                                <button type="button" class="remove-image-btn" id="remove-image-btn">
                                    <i class="fas fa-times"></i>
                                </button>
                            </div>
                        </div>
                        <p class="input-hint">Leave empty to keep current image</p>
                    </div>
                    
                    <div class="form-group">
                        <label for="edit-description">Description</label>
                        <textarea id="edit-description" name="description"></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline modal-close-btn">Cancel</button>
                <button type="button" class="btn btn-primary" id="saveProductChanges">Save Changes</button>
            </div>
        </div>
    </div>

    <!-- View Product Modal -->
    <div id="viewProductModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Product Details</h3>
                <button class="modal-close">&times;</button>
            </div>
            <div class="modal-body">
                <div id="product-details">
                    <!-- Product details will be loaded here -->
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline modal-close-btn">Close</button>
                <button type="button" class="btn btn-primary edit-from-view">Edit Product</button>
            </div>
        </div>
    </div>

    <script src="js/admin.js"></script>
    <script>
        // Select all functionality
        document.getElementById('select-all').addEventListener('change', function() {
            const checkboxes = document.querySelectorAll('.product-select');
            checkboxes.forEach(checkbox => {
                checkbox.checked = this.checked;
            });
        });

        // Filter functionality
        const categoryFilter = document.getElementById('category-filter');
        const statusFilter = document.getElementById('status-filter');
        const priceFilter = document.getElementById('price-filter');
        const searchInput = document.getElementById('search-input');

        function applyFilters() {
            const category = categoryFilter.value.toLowerCase();
            const status = statusFilter.value.toLowerCase();
            const priceRange = priceFilter.value;
            const searchTerm = searchInput.value.toLowerCase();

            // Get all products (both in grid and list view)
            const productElements = document.querySelectorAll('.product-card, tbody tr');

            productElements.forEach(element => {
                let show = true;
                const elementCategory = element.getAttribute('data-category').toLowerCase();
                const elementStatus = element.getAttribute('data-status').toLowerCase();
                const elementPrice = parseFloat(element.getAttribute('data-price'));
                
                // Category filter
                if (category && elementCategory !== category) {
                    show = false;
                }

                // Status filter
                if (status && elementStatus !== status) {
                    show = false;
                }

                // Search filter
                if (searchTerm) {
                    const productName = element.querySelector('.product-card-title, td:nth-child(3)').textContent.toLowerCase();
                    if (!productName.includes(searchTerm)) {
                        show = false;
                    }
                }

                // Price filter
                if (priceRange) {
                    const [min, max] = priceRange.split('-');
                    if (min && max) {
                        if (elementPrice < parseFloat(min) || elementPrice > parseFloat(max)) {
                            show = false;
                        }
                    } else if (min.endsWith('+')) {
                        const minValue = parseFloat(min);
                        if (elementPrice < minValue) {
                            show = false;
                        }
                    }
                }

                // Show or hide the element
                element.style.display = show ? '' : 'none';
            });
        }

        // Add event listeners to filters
        categoryFilter.addEventListener('change', applyFilters);
        statusFilter.addEventListener('change', applyFilters);
        priceFilter.addEventListener('change', applyFilters);
        searchInput.addEventListener('input', applyFilters);

        // Delete product function
        function deleteProduct(id) {
            if (confirm('Are you sure you want to delete this product?')) {
                window.location.href = `products.php?action=delete&id=${id}`;
            }
        }

        // Bulk actions
        document.getElementById('apply-bulk-action').addEventListener('click', function() {
            const action = document.getElementById('bulk-action').value;
            if (!action) {
                alert('Please select an action');
                return;
            }

            const selectedProducts = document.querySelectorAll('.product-select:checked');
            if (selectedProducts.length === 0) {
                alert('Please select at least one product');
                return;
            }

            if (action === 'delete' && !confirm(`Are you sure you want to delete ${selectedProducts.length} products?`)) {
                return;
            }

            // In a real application, you would send this to the server
            // For now, just show an alert
            alert(`Action "${action}" would be applied to ${selectedProducts.length} products`);
        });

        // Modal functionality
        const editModal = document.getElementById('editProductModal');
        const viewModal = document.getElementById('viewProductModal');
        const closeButtons = document.querySelectorAll('.modal-close, .modal-close-btn');

        // Close modal when clicking the close button
        closeButtons.forEach(button => {
            button.addEventListener('click', function() {
                editModal.style.display = 'none';
                viewModal.style.display = 'none';
            });
        });

        // Close modal when clicking outside the modal
        window.addEventListener('click', function(event) {
            if (event.target === editModal) {
                editModal.style.display = 'none';
            }
            if (event.target === viewModal) {
                viewModal.style.display = 'none';
            }
        });

        // Edit product functionality
        const editButtons = document.querySelectorAll('.edit-product');
        editButtons.forEach(button => {
            button.addEventListener('click', function() {
                const productId = this.getAttribute('data-id');
                loadProductForEdit(productId);
            });
        });

        // View product functionality
        const viewButtons = document.querySelectorAll('.view-product');
        viewButtons.forEach(button => {
            button.addEventListener('click', function() {
                const productId = this.getAttribute('data-id');
                loadProductForView(productId);
            });
        });

        // Edit from view button
        document.querySelector('.edit-from-view').addEventListener('click', function() {
            const productId = this.getAttribute('data-id');
            viewModal.style.display = 'none';
            loadProductForEdit(productId);
        });

        // Toggle old price input when sale checkbox is clicked
        document.getElementById('edit-sale').addEventListener('change', function() {
            const oldPriceContainer = document.getElementById('old-price-container');
            oldPriceContainer.style.display = this.checked ? 'block' : 'none';
        });

        // Update the loadProductForEdit function to include the image
        // Load product data for editing
        function loadProductForEdit(productId) {
            // In a real application, you would fetch this data from the server
            // For this demo, we'll use the data from the DOM
            const productCard = document.querySelector(`.product-card[data-id="${productId}"]`);
            const productRow = document.querySelector(`tbody tr[data-id="${productId}"]`);
            
            if (productCard || productRow) {
                const element = productCard || productRow;
                const name = element.querySelector('.product-card-title, td:nth-child(3)').textContent;
                const category = element.getAttribute('data-category');
                const price = parseFloat(element.getAttribute('data-price'));
                const status = element.getAttribute('data-status');
                const description = element.getAttribute('data-description');
                const sale = element.getAttribute('data-sale') === 'true';
                
                // Set form values
                document.getElementById('edit-product-id').value = productId;
                document.getElementById('edit-name').value = name;
                document.getElementById('edit-category').value = category;
                document.getElementById('edit-price').value = price;
                document.getElementById('edit-status').value = status;
                document.getElementById('edit-description').value = description;
                document.getElementById('edit-sale').checked = sale;

                // Show/hide old price container based on sale status
                const oldPriceContainer = document.getElementById('old-price-container');
                oldPriceContainer.style.display = sale ? 'block' : 'none';
                
                // Set stock value
                const stockText = element.querySelector('.product-card-stock, td:nth-child(6)').textContent;
                const stockMatch = stockText.match(/\d+/);
                if (stockMatch) {
                    document.getElementById('edit-stock').value = stockMatch[0];
                }
                
                // Set current image
                const currentImagePreview = document.getElementById('current-image-preview');
                const currentImageElement = currentImagePreview.querySelector('img');
                
                if (productCard) {
                    const productImage = productCard.querySelector('.product-image img');
                    if (productImage) {
                        currentImageElement.src = productImage.src;
                        currentImageElement.alt = name;
                    }
                } else {
                    // For list view, we don't have the image directly, so use a default or fetch it
                    currentImageElement.src = `../images/products/default-product.jpg`;
                    currentImageElement.alt = name;
                }
                
                // Show the modal
                editModal.style.display = 'block';
            }
        }

        // Load product data for viewing
        function loadProductForView(productId) {
            // In a real application, you would fetch this data from the server
            // For this demo, we'll use the data from the DOM
            const productCard = document.querySelector(`.product-card[data-id="${productId}"]`);
            const productRow = document.querySelector(`tbody tr[data-id="${productId}"]`);
            
            if (productCard || productRow) {
                const element = productCard || productRow;
                const name = element.querySelector('.product-card-title, td:nth-child(3)').textContent;
                const category = element.getAttribute('data-category');
                const price = element.querySelector('.product-card-price, td:nth-child(5)').textContent;
                const status = element.getAttribute('data-status');
                const description = element.getAttribute('data-description');
                const sale = element.getAttribute('data-sale') === 'true';
                
                // Get stock value
                const stockText = element.querySelector('.product-card-stock, td:nth-child(6)').textContent;
                const stockMatch = stockText.match(/\d+/);
                const stock = stockMatch ? stockMatch[0] : '0';
                
                // Get image path
                let imagePath = '../images/products/default-product.jpg';
                if (productCard) {
                    const imgElement = productCard.querySelector('.product-image img');
                    if (imgElement) {
                        imagePath = imgElement.getAttribute('src');
                    }
                }
                
                // Create HTML for product details with image at the top
                const detailsHTML = `
            <div style="text-align: center; margin-bottom: 20px;">
                <img src="${imagePath}" alt="${name}" style="max-height: 200px; max-width: 100%; object-fit: contain;">
            </div>
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                <div>
                    <h4 style="margin-bottom: 0.5rem;">Product Name</h4>
                    <p>${name}</p>
                </div>
                <div>
                    <h4 style="margin-bottom: 0.5rem;">Category</h4>
                    <p>${category}</p>
                </div>
                <div>
                    <h4 style="margin-bottom: 0.5rem;">Price</h4>
                    <p>${price}</p>
                </div>
                <div>
                    <h4 style="margin-bottom: 0.5rem;">Stock</h4>
                    <p>${stock} units</p>
                </div>
                <div>
                    <h4 style="margin-bottom: 0.5rem;">Status</h4>
                    <p><span class="status-badge ${status.toLowerCase().replace(' ', '-')}">${status}</span></p>
                </div>
                <div>
                    <h4 style="margin-bottom: 0.5rem;">On Sale</h4>
                    <p>${sale ? 'Yes' : 'No'}</p>
                </div>
            </div>
            <div style="margin-top: 1rem;">
                <h4 style="margin-bottom: 0.5rem;">Description</h4>
                <p>${description || 'No description available.'}</p>
            </div>
        `;
                
                // Set the product details
                document.getElementById('product-details').innerHTML = detailsHTML;
                
                // Set the product ID for the edit button
                document.querySelector('.edit-from-view').setAttribute('data-id', productId);
                
                // Show the modal
                viewModal.style.display = 'block';
            }
        }

        // Save product changes
        document.getElementById('saveProductChanges').addEventListener('click', function() {
    // Get form data
    const form = document.getElementById('editProductForm');
    const productId = document.getElementById('edit-product-id').value;
    const name = document.getElementById('edit-name').value;
    const category = document.getElementById('edit-category').value;
    const price = document.getElementById('edit-price').value;
    const stock = document.getElementById('edit-stock').value;
    
    // Automatically determine status based on stock level
    let status;
    if (stock <= 0) {
        status = 'Out of Stock';
    } else if (stock <= 20) {
        status = 'Low Stock';
    } else {
        status = 'Active';
    }
    
    // Update the status dropdown to reflect the calculated status
    document.getElementById('edit-status').value = status;
    
    const description = document.getElementById('edit-description').value;
    const sale = document.getElementById('edit-sale').checked ? 1 : 0;
    const oldPrice = document.getElementById('edit-old-price').value || '';
    const imageFile = document.getElementById('edit-image').files[0];
    
    // Validate form data
    if (!name || !category || !price || stock === '') {
        alert('Please fill in all required fields');
        return;
    }
    
    // Show loading state
    const saveButton = document.getElementById('saveProductChanges');
    const originalText = saveButton.textContent;
    saveButton.disabled = true;
    saveButton.textContent = 'Saving...';
    
    // Create form data object
    const formData = new FormData();
    formData.append('action', 'update');
    formData.append('id', productId);
    formData.append('name', name);
    formData.append('category', category);
    formData.append('price', price);
    formData.append('stock', stock);
    formData.append('status', status);
    formData.append('description', description);
    formData.append('sale', sale);
    if (sale && oldPrice) {
        formData.append('oldPrice', oldPrice);
    }
    
    // Add image if selected
    if (imageFile) {
        formData.append('image', imageFile);
    }
    
    // Send AJAX request
    const xhr = new XMLHttpRequest();
    xhr.open('POST', 'update_product.php', true);
    xhr.onload = function() {
        saveButton.disabled = false;
        saveButton.textContent = originalText;
        
        if (xhr.status === 200) {
            try {
                const response = JSON.parse(xhr.responseText);
                if (response.success) {
                    // Update UI
                    const productCard = document.querySelector(`.product-card[data-id="${productId}"]`);
                    const productRow = document.querySelector(`tbody tr[data-id="${productId}"]`);

                    if (productCard) {
                        productCard.querySelector('.product-card-title').textContent = name;
                        productCard.querySelector('.product-card-category').innerHTML = `<i class="fas fa-tag"></i> ${category}`;
                        productCard.querySelector('.product-card-price').textContent = formatPrice(price);
                        productCard.querySelector('.product-card-stock').innerHTML = `<i class="fas fa-cubes"></i> Stock: ${stock} units`;
                        productCard.setAttribute('data-category', category);
                        productCard.setAttribute('data-price', price);
                        productCard.setAttribute('data-status', status);
                        productCard.setAttribute('data-description', description);
                        productCard.setAttribute('data-sale', sale ? 'true' : 'false');
                        productCard.querySelector('.status-badge').textContent = status;
                        productCard.querySelector('.status-badge').className = `status-badge ${status.toLowerCase().replace(' ', '-')}`;
                        
                        // Update image if a new one was uploaded
                        if (response.imagePath) {
                            const imgElement = productCard.querySelector('.product-image img');
                            if (imgElement) {
                                imgElement.src = response.imagePath;
                            }
                        }
                    }

                    if (productRow) {
                        productRow.querySelector('td:nth-child(3)').textContent = name;
                        productRow.querySelector('td:nth-child(4)').textContent = category;
                        productRow.querySelector('td:nth-child(5)').textContent = formatPrice(price);
                        productRow.querySelector('td:nth-child(6)').textContent = stock;
                        productRow.setAttribute('data-category', category);
                        productRow.setAttribute('data-price', price);
                        productRow.setAttribute('data-status', status);
                        productRow.setAttribute('data-description', description);
                        productRow.querySelector('.status-badge').textContent = status;
                        productRow.querySelector('.status-badge').className = `status-badge ${status.toLowerCase().replace(' ', '-')}`;
                    }
                    
                    // Close modal
                    editModal.style.display = 'none';
                    
                    // Show success message
                    alert('Product updated successfully!');
                    window.location.href = 'products.php';
                    
                } else {
                    alert('Error: ' + response.message);
                }
            } catch (e) {
                alert('Error processing response: ' + e.message);
                console.error(xhr.responseText);
            }
        } else {
            alert('Error: ' + xhr.status);
        }
    };
    xhr.onerror = function() {
        saveButton.disabled = false;
        saveButton.textContent = originalText;
        alert('Request failed. Please try again.');
    };
    xhr.send(formData);
});

// Add an event listener to automatically update status when stock changes
document.getElementById('edit-stock').addEventListener('change', function() {
    const stockValue = parseInt(this.value) || 0;
    const statusDropdown = document.getElementById('edit-status');
    
    // Automatically set status based on stock level
    if (stockValue <= 0) {
        statusDropdown.value = 'Out of Stock';
    } else if (stockValue <= 20) {
        statusDropdown.value = 'Low Stock';
    } else {
        statusDropdown.value = 'Active';
    }
});

// Update the loadProductForEdit function to ensure status is set correctly
function loadProductForEdit(productId) {
    // In a real application, you would fetch this data from the server
    // For this demo, we'll use the data from the DOM
    const productCard = document.querySelector(`.product-card[data-id="${productId}"]`);
    const productRow = document.querySelector(`tbody tr[data-id="${productId}"]`);
    
    if (productCard || productRow) {
        const element = productCard || productRow;
        const name = element.querySelector('.product-card-title, td:nth-child(3)').textContent;
        const category = element.getAttribute('data-category');
        const price = parseFloat(element.getAttribute('data-price'));
        const description = element.getAttribute('data-description');
        const sale = element.getAttribute('data-sale') === 'true';
        
        // Set form values
        document.getElementById('edit-product-id').value = productId;
        document.getElementById('edit-name').value = name;
        document.getElementById('edit-category').value = category;
        document.getElementById('edit-price').value = price;
        document.getElementById('edit-description').value = description;
        document.getElementById('edit-sale').checked = sale;

        // Show/hide old price container based on sale status
        const oldPriceContainer = document.getElementById('old-price-container');
        oldPriceContainer.style.display = sale ? 'block' : 'none';
        
        // Set stock value
        const stockText = element.querySelector('.product-card-stock, td:nth-child(6)').textContent;
        const stockMatch = stockText.match(/\d+/);
        if (stockMatch) {
            const stockValue = parseInt(stockMatch[0]);
            document.getElementById('edit-stock').value = stockValue;
            
            // Set status based on stock value
            if (stockValue <= 0) {
                document.getElementById('edit-status').value = 'Out of Stock';
            } else if (stockValue <= 20) {
                document.getElementById('edit-status').value = 'Low Stock';
            } else {
                document.getElementById('edit-status').value = 'Active';
            }
        }
        
        // Set current image
        const currentImagePreview = document.getElementById('current-image-preview');
        const currentImageElement = currentImagePreview.querySelector('img');
        
        if (productCard) {
            const productImage = productCard.querySelector('.product-image img');
            if (productImage) {
                currentImageElement.src = productImage.src;
                currentImageElement.alt = name;
            }
        } else {
            // For list view, we don't have the image directly, so use a default or fetch it
            currentImageElement.src = `../images/products/default-product.jpg`;
            currentImageElement.alt = name;
        }
        
        // Show the modal
        editModal.style.display = 'block';
    }
}

        function formatPrice(price) {
            return '₱' + Number(price).toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2});
        }

        // Modern image upload functionality
        document.addEventListener('DOMContentLoaded', function() {
            const imageUploadArea = document.getElementById('image-upload-area');
            const imageInput = document.getElementById('edit-image');
            const newImagePreview = document.getElementById('new-image-preview');
            const newImagePreviewImg = newImagePreview.querySelector('img');
            const removeImageBtn = document.getElementById('remove-image-btn');
            
            // Click on upload area to trigger file input
            imageUploadArea.addEventListener('click', function() {
                imageInput.click();
            });
            
            // Handle file selection
            imageInput.addEventListener('change', function() {
                if (this.files && this.files[0]) {
                    const file = this.files[0];
                    
                    // Check if file is an image
                    if (!file.type.match('image.*')) {
                        alert('Please select an image file');
                        return;
                    }
                    
                    // Check file size (max 5MB)
                    if (file.size > 5 * 1024 * 1024) {
                        alert('File size should not exceed 5MB');
                        return;
                    }
                    
                    const reader = new FileReader();
                    
                    reader.onload = function(e) {
                        // Show preview
                        newImagePreviewImg.src = e.target.result;
                        imageUploadArea.style.display = 'none';
                        newImagePreview.style.display = 'block';
                    };
                    
                    reader.readAsDataURL(file);
                }
            });
            
            // Remove selected image
            removeImageBtn.addEventListener('click', function() {
                imageInput.value = '';
                newImagePreview.style.display = 'none';
                imageUploadArea.style.display = 'block';
            });
            
            // Drag and drop functionality
            ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
                imageUploadArea.addEventListener(eventName, preventDefaults, false);
            });
            
            function preventDefaults(e) {
                e.preventDefault();
                e.stopPropagation();
            }
            
            ['dragenter', 'dragover'].forEach(eventName => {
                imageUploadArea.addEventListener(eventName, highlight, false);
            });
            
            ['dragleave', 'drop'].forEach(eventName => {
                imageUploadArea.addEventListener(eventName, unhighlight, false);
            });
            
            function highlight() {
                imageUploadArea.classList.add('dragover');
            }
            
            function unhighlight() {
                imageUploadArea.classList.remove('dragover');
            }
            
            imageUploadArea.addEventListener('drop', handleDrop, false);
            
            function handleDrop(e) {
                const dt = e.dataTransfer;
                const files = dt.files;
                
                if (files && files.length) {
                    imageInput.files = files;
                    
                    // Trigger change event
                    const event = new Event('change', { bubbles: true });
                    imageInput.dispatchEvent(event);
                }
            }
            
            // Print Report functionality
            const printReportBtn = document.getElementById('print-report-btn');
            if (printReportBtn) {
                printReportBtn.addEventListener('click', function() {
                    generateProductReport();
                });
            }
            
            // Function to generate product report based on current filters
            function generateProductReport() {
                // Get current filter values
                const category = categoryFilter.value;
                const status = statusFilter.value;
                const priceRange = priceFilter.value;
                const searchTerm = searchInput.value;
                
                // Create form for report generation
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = 'generate_product_report.php';
                form.target = '_blank'; // Open in new tab
                
                // Add filter parameters
                if (category) {
                    const categoryInput = document.createElement('input');
                    categoryInput.type = 'hidden';
                    categoryInput.name = 'category';
                    categoryInput.value = category;
                    form.appendChild(categoryInput);
                }
                
                if (status) {
                    const statusInput = document.createElement('input');
                    statusInput.type = 'hidden';
                    statusInput.name = 'status';
                    statusInput.value = status;
                    form.appendChild(statusInput);
                }
                
                if (priceRange) {
                    const priceInput = document.createElement('input');
                    priceInput.type = 'hidden';
                    priceInput.name = 'price_range';
                    priceInput.value = priceRange;
                    form.appendChild(priceInput);
                }
                
                if (searchTerm) {
                    const searchInput = document.createElement('input');
                    searchInput.type = 'hidden';
                    searchInput.name = 'search';
                    searchInput.value = searchTerm;
                    form.appendChild(searchInput);
                }
                
                // Add the form to the document and submit it
                document.body.appendChild(form);
                form.submit();
                document.body.removeChild(form);
            }
        });
    </script>
</body>
</html>
