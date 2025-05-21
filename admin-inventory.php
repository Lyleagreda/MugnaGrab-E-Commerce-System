<?php
session_start();
include '../db.php';
include '../data/products.php';

// Check if the user is logged in
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    // Display JavaScript alert if not logged in
    echo "<script>alert('You are not logged in. Please log in to access your account.');</script>";
    header('Location: ../index.php');
    exit;  // Stop further execution of the script
}

// Admin is logged in
$adminEmail = $_SESSION['email'] ?? 'Admin'; // Using email from your session

// Get all product categories for filter
$categories = [];
foreach ($products as $product) {
    if (isset($product['category']) && !in_array($product['category'], $categories)) {
        $categories[] = $product['category'];
    }
}
sort($categories); // Sort categories alphabetically

// Page title
$pageTitle = 'Inventory';
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inventory Management - Mugna</title>
    <link rel="stylesheet" href="css/admin-styles.css">
    <link rel="shortcut icon" href="../images/mugna-icon.png" type="image/x-icon">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Add jQuery for AJAX -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <style>
        :root {
            --primary-color: #2563eb;
            --primary-light: #3b82f6;
            --primary-dark: #1d4ed8;
            --primary-bg: rgba(37, 99, 235, 0.05);
            --success-color: #10b981;
            --success-bg: rgba(16, 185, 129, 0.05);
            --warning-color: #f59e0b;
            --warning-bg: rgba(245, 158, 11, 0.05);
            --danger-color: #ef4444;
            --danger-bg: rgba(239, 68, 68, 0.05);
            --gray-50: #f9fafb;
            --gray-100: #f3f4f6;
            --gray-200: #e5e7eb;
            --gray-300: #d1d5db;
            --gray-400: #9ca3af;
            --gray-500: #6b7280;
            --gray-600: #4b5563;
            --gray-700: #374151;
            --gray-800: #1f2937;
            --gray-900: #111827;
            --border-radius: 0.5rem;
            --shadow-sm: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
            --shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
            --shadow-md: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
            --transition: all 0.2s ease;
        }

        /* Modern Inventory Management Styles */
        .inventory-container {
            padding: 2rem;
            max-width: 1400px;
            margin: 0 auto;
        }

        .inventory-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
        }

        .inventory-header h1 {
            font-size: 1.75rem;
            font-weight: 700;
            color: var(--gray-900);
            margin: 0;
            letter-spacing: -0.025em;
        }

        .header-actions {
            display: flex;
            gap: 1rem;
        }

        /* Search and Filter Section */
        .filter-section {
            background-color: white;
            border-radius: var(--border-radius);
            padding: 1.5rem;
            margin-bottom: 2rem;
            box-shadow: var(--shadow);
            border: 1px solid var(--gray-200);
        }

        .filter-form {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
        }

        .form-group {
            margin-bottom: 0;
        }

        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
            color: var(--gray-700);
            font-size: 0.875rem;
        }

        .form-control {
            width: 100%;
            padding: 0.75rem 1rem;
            border: 1px solid var(--gray-300);
            border-radius: var(--border-radius);
            background-color: white;
            color: var(--gray-800);
            font-size: 0.875rem;
            transition: var(--transition);
        }

        .form-control:focus {
            outline: none;
            border-color: var(--primary-light);
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.2);
        }

        /* Inventory Table */
        .inventory-table-wrapper {
            background-color: white;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
            overflow: hidden;
            border: 1px solid var(--gray-200);
        }

        .inventory-table {
            width: 100%;
            border-collapse: collapse;
        }

        .inventory-table th,
        .inventory-table td {
            padding: 1rem;
            text-align: left;
            border-bottom: 1px solid var(--gray-200);
        }

        .inventory-table th {
            background-color: var(--gray-50);
            font-weight: 600;
            color: var(--gray-700);
            font-size: 0.875rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .inventory-table tbody tr {
            transition: var(--transition);
        }

        .inventory-table tbody tr:hover {
            background-color: var(--primary-bg);
        }

        .inventory-table tbody tr:last-child td {
            border-bottom: none;
        }

        /* Product Info */
        .product-info {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .product-image {
            width: 60px;
            height: 60px;
            border-radius: 0.5rem;
            object-fit: cover;
            background-color: var(--gray-100);
            border: 1px solid var(--gray-200);
        }

        .product-name {
            font-weight: 600;
            color: var(--gray-800);
            margin-bottom: 0.25rem;
        }

        .product-meta {
            font-size: 0.75rem;
            color: var(--gray-500);
        }

        /* Stock Status */
        .stock-status {
            display: inline-flex;
            align-items: center;
            padding: 0.375rem 0.75rem;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: capitalize;
        }

        .in-stock {
            background-color: var(--success-bg);
            color: var(--success-color);
        }

        .low-stock {
            background-color: var(--warning-bg);
            color: var(--warning-color);
        }

        .out-of-stock {
            background-color: var(--danger-bg);
            color: var(--danger-color);
        }

        /* Quantity Update */
        .quantity-update {
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .quantity-input {
            width: 80px;
            padding: 0.625rem;
            border: 1px solid var(--gray-300);
            border-radius: var(--border-radius);
            text-align: center;
            font-size: 0.875rem;
            font-weight: 500;
        }

        .update-btn {
            padding: 0.625rem 1.25rem;
            background-color: var(--primary-color);
            color: white;
            border: none;
            border-radius: var(--border-radius);
            font-size: 0.875rem;
            font-weight: 500;
            cursor: pointer;
            transition: var(--transition);
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }

        .update-btn:hover {
            background-color: var(--primary-dark);
            transform: translateY(-1px);
            box-shadow: var(--shadow);
        }

        .update-btn:active {
            transform: translateY(0);
        }

        .update-btn:disabled {
            background-color: var(--gray-400);
            cursor: not-allowed;
        }

        /* Pagination */
        .pagination {
            display: flex;
            justify-content: center;
            margin-top: 2rem;
            gap: 0.5rem;
        }

        .pagination-btn {
            min-width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: var(--border-radius);
            background-color: white;
            border: 1px solid var(--gray-300);
            color: var(--gray-700);
            font-weight: 500;
            transition: var(--transition);
            cursor: pointer;
            padding: 0 0.75rem;
        }

        .pagination-btn:hover {
            background-color: var(--gray-100);
            color: var(--primary-color);
            border-color: var(--primary-color);
        }

        .pagination-btn.active {
            background-color: var(--primary-color);
            color: white;
            border-color: var(--primary-color);
        }

        .pagination-btn.disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        /* Alert Styles */
        .alert {
            padding: 1rem 1.25rem;
            border-radius: var(--border-radius);
            margin-bottom: 2rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            animation: slideDown 0.3s ease-out forwards;
            box-shadow: var(--shadow);
        }

        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .alert-success {
            background-color: var(--success-bg);
            color: var(--success-color);
            border-left: 4px solid var(--success-color);
        }

        .alert-error {
            background-color: var(--danger-bg);
            color: var(--danger-color);
            border-left: 4px solid var(--danger-color);
        }

        .alert-icon {
            width: 24px;
            height: 24px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }

        .alert-success .alert-icon {
            background-color: var(--success-color);
            color: white;
        }

        .alert-error .alert-icon {
            background-color: var(--danger-color);
            color: white;
        }

        .alert-content {
            flex: 1;
            font-weight: 500;
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 3rem 1.5rem;
            color: var(--gray-600);
        }

        .empty-state i {
            font-size: 3rem;
            margin-bottom: 1rem;
            color: var(--gray-300);
        }

        .empty-state h3 {
            font-size: 1.25rem;
            font-weight: 600;
            margin-bottom: 0.5rem;
            color: var(--gray-800);
        }

        .empty-state p {
            margin-bottom: 1.5rem;
            max-width: 500px;
            margin-left: auto;
            margin-right: auto;
        }

        /* Loading Indicator */
        .loading-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(255, 255, 255, 0.8);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 1000;
            opacity: 0;
            visibility: hidden;
            transition: opacity 0.3s ease, visibility 0.3s ease;
        }

        .loading-overlay.active {
            opacity: 1;
            visibility: visible;
        }

        .loading-spinner {
            width: 40px;
            height: 40px;
            border: 3px solid rgba(59, 130, 246, 0.2);
            border-radius: 50%;
            border-top-color: var(--primary-color);
            animation: spin 1s ease-in-out infinite;
        }

        @keyframes spin {
            to {
                transform: rotate(360deg);
            }
        }

        /* Responsive Adjustments */
        @media (max-width: 768px) {
            .inventory-container {
                padding: 1.5rem;
            }

            .inventory-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 1rem;
            }

            .header-actions {
                width: 100%;
                flex-direction: column;
            }

            .filter-form {
                grid-template-columns: 1fr;
            }

            .inventory-table {
                display: block;
                overflow-x: auto;
            }

            .quantity-update {
                flex-direction: column;
                align-items: flex-start;
            }

            .quantity-input {
                width: 100%;
            }

            .update-btn {
                width: 100%;
            }
        }

        /* Current Stock Display */
        .current-stock {
            font-size: 1.125rem;
            font-weight: 700;
            color: var(--gray-800);
        }

        /* Table Header Sticky */
        .inventory-table thead {
            position: sticky;
            top: 0;
            z-index: 10;
        }

        /* Modern Card Design */
        .card {
            background-color: white;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
            border: 1px solid var(--gray-200);
            overflow: hidden;
        }

        .card-header {
            padding: 1.25rem 1.5rem;
            background-color: var(--gray-50);
            border-bottom: 1px solid var(--gray-200);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .card-header h2 {
            font-size: 1.125rem;
            font-weight: 600;
            color: var(--gray-800);
            margin: 0;
        }

        .card-body {
            padding: 1.5rem;
        }

        /* Button Styles */
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            padding: 0.625rem 1.25rem;
            border-radius: var(--border-radius);
            font-size: 0.875rem;
            font-weight: 500;
            cursor: pointer;
            transition: var(--transition);
            border: none;
        }

        .btn-primary {
            background-color: var(--primary-color);
            color: white;
        }

        .btn-primary:hover {
            background-color: var(--primary-dark);
            transform: translateY(-1px);
            box-shadow: var(--shadow);
        }

        .btn-secondary {
            background-color: white;
            color: var(--gray-700);
            border: 1px solid var(--gray-300);
        }

        .btn-secondary:hover {
            background-color: var(--gray-100);
            color: var(--gray-900);
            border-color: var(--gray-400);
        }

        .btn-success {
            background-color: var(--success-color);
            color: white;
        }

        .btn-success:hover {
            background-color: #0d9488;
            transform: translateY(-1px);
            box-shadow: var(--shadow);
        }

        /* Print Button */
        .print-btn {
            background-color: #4f46e5;
            color: white;
        }

        .print-btn:hover {
            background-color: #4338ca;
            transform: translateY(-1px);
            box-shadow: var(--shadow);
        }

        /* Image Preview */
        .image-preview {
            cursor: pointer;
            transition: transform 0.2s ease;
        }

        .image-preview:hover {
            transform: scale(1.05);
        }

        /* Image Modal */
        .image-modal {
            display: none;
            position: fixed;
            z-index: 1100;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            overflow: auto;
            background-color: rgba(0, 0, 0, 0.8);
            opacity: 0;
            transition: opacity 0.3s ease;
        }

        .image-modal.active {
            display: flex;
            opacity: 1;
            align-items: center;
            justify-content: center;
        }

        .modal-image {
            max-width: 90%;
            max-height: 90vh;
            border-radius: 0.5rem;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.5);
        }

        .modal-close {
            position: absolute;
            top: 20px;
            right: 20px;
            color: white;
            font-size: 2rem;
            cursor: pointer;
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            background-color: rgba(0, 0, 0, 0.5);
            transition: background-color 0.2s ease;
        }

        .modal-close:hover {
            background-color: rgba(0, 0, 0, 0.8);
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

            <!-- Inventory Content -->
            <div class="inventory-container">
                <div class="inventory-header">
                    <h1>Inventory Management</h1>
                    <div class="header-actions">
                        <button class="btn btn-secondary" id="refreshInventory">
                            <i class="fas fa-sync-alt"></i> Refresh Inventory
                        </button>
                        <button class="btn print-btn" id="printInventory">
                            <i class="fas fa-print"></i> Print Inventory
                        </button>
                    </div>
                </div>

                <!-- Alert Container -->
                <div id="alertContainer"></div>

                <!-- Search and Filter Section -->
                <div class="card">
                    <div class="card-header">
                        <h2>Search & Filter</h2>
                    </div>
                    <div class="card-body">
                        <form id="filterForm" class="filter-form">
                            <div class="form-group">
                                <label for="search">Search Products</label>
                                <input type="text" id="search" class="form-control" placeholder="Search by name or ID...">
                            </div>
                            <div class="form-group">
                                <label for="category">Category</label>
                                <select id="category" class="form-control">
                                    <option value="">All Categories</option>
                                    <?php foreach ($categories as $category): ?>
                                        <option value="<?php echo htmlspecialchars($category); ?>"><?php echo htmlspecialchars($category); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="stockStatus">Stock Status</label>
                                <select id="stockStatus" class="form-control">
                                    <option value="">All</option>
                                    <option value="in-stock">In Stock</option>
                                    <option value="low-stock">Low Stock</option>
                                    <option value="out-of-stock">Out of Stock</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="sortBy">Sort By</label>
                                <select id="sortBy" class="form-control">
                                    <option value="name-asc">Name (A-Z)</option>
                                    <option value="name-desc">Name (Z-A)</option>
                                    <option value="stock-asc">Stock (Low to High)</option>
                                    <option value="stock-desc">Stock (High to Low)</option>
                                    <option value="price-asc">Price (Low to High)</option>
                                    <option value="price-desc">Price (High to Low)</option>
                                </select>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Inventory Table -->
                <div class="inventory-table-wrapper" style="margin-top: 2rem;">
                    <table class="inventory-table">
                        <thead>
                            <tr>
                                <th>Product</th>
                                <th>Category</th>
                                <th>Price</th>
                                <th>Current Stock</th>
                                <th>Status</th>
                                <th>Update Stock</th>
                            </tr>
                        </thead>
                        <tbody id="inventoryTableBody">
                            <!-- Table content will be loaded dynamically -->
                            <tr>
                                <td colspan="6" class="text-center">
                                    <div style="display: flex; justify-content: center; padding: 2rem;">
                                        <div class="loading-spinner"></div>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <div class="pagination" id="pagination">
                    <!-- Pagination will be generated dynamically -->
                </div>
            </div>
        </main>
    </div>

    <!-- Loading Overlay -->
    <div class="loading-overlay" id="loadingOverlay">
        <div class="loading-spinner"></div>
    </div>

    <!-- Image Modal -->
    <div id="imageModal" class="image-modal">
        <span class="modal-close">&times;</span>
        <img id="modalImage" class="modal-image" src="/placeholder.svg" alt="Product Image">
    </div>

    <script>
        // Global variables
        let allProducts = <?php echo json_encode($products); ?>;
        let filteredProducts = [];
        let currentPage = 1;
        const itemsPerPage = 10;

        // Initialize the page
        document.addEventListener('DOMContentLoaded', function() {
            // Apply initial filters and render products
            applyFilters();

            // Set up event listeners for filters
            document.getElementById('search').addEventListener('input', debounce(applyFilters, 300));
            document.getElementById('category').addEventListener('change', applyFilters);
            document.getElementById('stockStatus').addEventListener('change', applyFilters);
            document.getElementById('sortBy').addEventListener('change', applyFilters);

            // Set up event listener for refresh button
            document.getElementById('refreshInventory').addEventListener('click', function() {
                // Show loading overlay
                document.getElementById('loadingOverlay').classList.add('active');
                
                // Simulate refresh delay
                setTimeout(() => {
                    applyFilters();
                    document.getElementById('loadingOverlay').classList.remove('active');
                    showAlert('Inventory refreshed successfully.', 'success');
                }, 800);
            });

            // Set up event listener for print button
            document.getElementById('printInventory').addEventListener('click', function() {
                printInventoryPDF();
            });

            // Set up event listener for image modal close button
            document.querySelector('.modal-close').addEventListener('click', function() {
                document.getElementById('imageModal').classList.remove('active');
            });

            // Close modal when clicking outside the image
            window.addEventListener('click', function(event) {
                const modal = document.getElementById('imageModal');
                if (event.target === modal) {
                    modal.classList.remove('active');
                }
            });
        });

        // Function to apply filters and render products
        function applyFilters() {
            const searchTerm = document.getElementById('search').value.toLowerCase();
            const categoryFilter = document.getElementById('category').value;
            const stockStatusFilter = document.getElementById('stockStatus').value;
            const sortBy = document.getElementById('sortBy').value;

            // Filter products
            filteredProducts = allProducts.filter(product => {
                // Search filter
                const nameMatch = product.name.toLowerCase().includes(searchTerm);
                const idMatch = product.id.toString().includes(searchTerm);
                
                if (searchTerm && !nameMatch && !idMatch) {
                    return false;
                }

                // Category filter
                if (categoryFilter && product.category !== categoryFilter) {
                    return false;
                }

                // Stock status filter
                if (stockStatusFilter) {
                    const stock = product.stock || 0;
                    
                    if (stockStatusFilter === 'in-stock' && (stock <= 20 || stock === 0)) {
                        return false;
                    } else if (stockStatusFilter === 'low-stock' && (stock > 20 || stock === 0)) {
                        return false;
                    } else if (stockStatusFilter === 'out-of-stock' && stock > 0) {
                        return false;
                    }
                }

                return true;
            });

            // Sort products
            filteredProducts.sort((a, b) => {
                const stockA = a.stock || 0;
                const stockB = b.stock || 0;
                const priceA = a.price || 0;
                const priceB = b.price || 0;

                switch (sortBy) {
                    case 'name-asc':
                        return a.name.localeCompare(b.name);
                    case 'name-desc':
                        return b.name.localeCompare(a.name);
                    case 'stock-asc':
                        return stockA - stockB;
                    case 'stock-desc':
                        return stockB - stockA;
                    case 'price-asc':
                        return priceA - priceB;
                    case 'price-desc':
                        return priceB - priceA;
                    default:
                        return 0;
                }
            });

            // Reset to first page when filters change
            currentPage = 1;

            // Render products and pagination
            renderProducts();
            renderPagination();
        }

        // Function to render products
        function renderProducts() {
            const tableBody = document.getElementById('inventoryTableBody');
            const startIndex = (currentPage - 1) * itemsPerPage;
            const endIndex = Math.min(startIndex + itemsPerPage, filteredProducts.length);
            const displayedProducts = filteredProducts.slice(startIndex, endIndex);

            if (displayedProducts.length === 0) {
                tableBody.innerHTML = `
                    <tr>
                        <td colspan="6">
                            <div class="empty-state">
                                <i class="fas fa-box-open"></i>
                                <h3>No products found</h3>
                                <p>Try adjusting your search or filter criteria.</p>
                            </div>
                        </td>
                    </tr>
                `;
                return;
            }

            let html = '';
            displayedProducts.forEach(product => {
                const stock = product.stock || 0;
                let statusClass = '';
                let statusText = '';

                if (stock === 0) {
                    statusClass = 'out-of-stock';
                    statusText = 'Out of Stock';
                } else if (stock <= 20) {
                    statusClass = 'low-stock';
                    statusText = 'Low Stock';
                } else {
                    statusClass = 'in-stock';
                    statusText = 'In Stock';
                }

                // Use the direct image path approach
                const imagePath = `../${product.image || 'images/products/default-product.jpg'}`;

                html += `
                    <tr data-product-id="${product.id}">
                        <td>
                            <div class="product-info">
                                <div class="product-image-container" style="height: 60px; display: flex; align-items: center; justify-content: center;">
                                    <img src="${imagePath}" alt="${product.name}" class="product-image image-preview" 
                                         style="max-height: 100%; max-width: 100%; object-fit: contain;"
                                         onclick="openImageModal('${imagePath}', '${product.name}')">
                                </div>
                                <div>
                                    <div class="product-name">${product.name}</div>
                                    <div class="product-meta">ID: ${product.id}</div>
                                </div>
                            </div>
                        </td>
                        <td>${product.category || 'Uncategorized'}</td>
                        <td>₱${numberWithCommas(product.price.toFixed(2))}</td>
                        <td><span class="current-stock">${stock}</span></td>
                        <td>
                            <span class="stock-status ${statusClass}">${statusText}</span>
                        </td>
                        <td>
                            <div class="quantity-update">
                                <input type="number" class="quantity-input" min="1" value="1" data-product-id="${product.id}">
                                <button class="update-btn" onclick="updateStock(${product.id})">
                                    <i class="fas fa-plus"></i> Update
                                </button>
                            </div>
                        </td>
                    </tr>
                `;
            });

            tableBody.innerHTML = html;
        }

        // Function to render pagination
        function renderPagination() {
            const paginationContainer = document.getElementById('pagination');
            const totalPages = Math.ceil(filteredProducts.length / itemsPerPage);

            if (totalPages <= 1) {
                paginationContainer.innerHTML = '';
                return;
            }

            let html = '';

            // Previous button
            html += `
                <button class="pagination-btn ${currentPage === 1 ? 'disabled' : ''}" 
                    ${currentPage === 1 ? 'disabled' : 'onclick="changePage(' + (currentPage - 1) + ')"'}>
                    <i class="fas fa-chevron-left"></i>
                </button>
            `;

            // Page numbers
            const maxVisiblePages = 5;
            let startPage = Math.max(1, currentPage - Math.floor(maxVisiblePages / 2));
            let endPage = Math.min(totalPages, startPage + maxVisiblePages - 1);

            if (endPage - startPage + 1 < maxVisiblePages) {
                startPage = Math.max(1, endPage - maxVisiblePages + 1);
            }

            if (startPage > 1) {
                html += `<button class="pagination-btn" onclick="changePage(1)">1</button>`;
                if (startPage > 2) {
                    html += `<span class="pagination-ellipsis">...</span>`;
                }
            }

            for (let i = startPage; i <= endPage; i++) {
                html += `
                    <button class="pagination-btn ${i === currentPage ? 'active' : ''}" 
                        onclick="changePage(${i})">
                        ${i}
                    </button>
                `;
            }

            if (endPage < totalPages) {
                if (endPage < totalPages - 1) {
                    html += `<span class="pagination-ellipsis">...</span>`;
                }
                html += `<button class="pagination-btn" onclick="changePage(${totalPages})">${totalPages}</button>`;
            }

            // Next button
            html += `
                <button class="pagination-btn ${currentPage === totalPages ? 'disabled' : ''}" 
                    ${currentPage === totalPages ? 'disabled' : 'onclick="changePage(' + (currentPage + 1) + ')"'}>
                    <i class="fas fa-chevron-right"></i>
                </button>
            `;

            paginationContainer.innerHTML = html;
        }

        // Function to change page
        function changePage(page) {
            currentPage = page;
            renderProducts();
            renderPagination();
            window.scrollTo(0, 0);
        }

        // Function to update stock
        function updateStock(productId) {
            const quantityInput = document.querySelector(`.quantity-input[data-product-id="${productId}"]`);
            const quantity = parseInt(quantityInput.value);

            if (isNaN(quantity) || quantity < 1) {
                showAlert('Please enter a valid quantity (minimum 1).', 'error');
                return;
            }

            // Show loading overlay
            document.getElementById('loadingOverlay').classList.add('active');

            // Find the product
            const productIndex = allProducts.findIndex(p => p.id === productId);
            if (productIndex === -1) {
                showAlert('Product not found.', 'error');
                document.getElementById('loadingOverlay').classList.remove('active');
                return;
            }

            const product = allProducts[productIndex];
            const oldStock = product.stock || 0;
            const newStock = oldStock + quantity;

            // Update stock in the products array
            allProducts[productIndex].stock = newStock;

            // Simulate AJAX request with setTimeout
            setTimeout(() => {
                // Update the products.php file
                updateProductsFile();

                // Hide loading overlay
                document.getElementById('loadingOverlay').classList.remove('active');

                // Show success message
                showAlert(`Stock for "${product.name}" updated successfully. Added ${quantity} units.`, 'success');

                // Reset quantity input
                quantityInput.value = 1;

                // Re-apply filters to update the table
                applyFilters();
            }, 800);
        }

        // Function to update products.php file
        function updateProductsFile() {
            $.ajax({
                url: 'update-inventory.php',
                type: 'POST',
                data: {
                    products: JSON.stringify(allProducts)
                },
                success: function(response) {
                    console.log('Products file updated successfully');
                },
                error: function(xhr, status, error) {
                    console.error('Error updating products file:', error);
                    showAlert('Error updating products file. Please try again.', 'error');
                }
            });
        }

        // Function to print inventory PDF
        function printInventoryPDF() {
            // Show loading overlay
            document.getElementById('loadingOverlay').classList.add('active');
            
            // Get current filter parameters
            const searchTerm = document.getElementById('search').value;
            const categoryFilter = document.getElementById('category').value;
            const stockStatusFilter = document.getElementById('stockStatus').value;
            const sortBy = document.getElementById('sortBy').value;
            
            // Create form data
            const formData = new FormData();
            formData.append('search', searchTerm);
            formData.append('category', categoryFilter);
            formData.append('stockStatus', stockStatusFilter);
            formData.append('sortBy', sortBy);
            formData.append('products', JSON.stringify(filteredProducts));
            
            // Send request to generate PDF
            fetch('generate-inventory-pdf.php', {
                method: 'POST',
                body: formData
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error('Network response was not ok');
                }
                return response.blob();
            })
            .then(blob => {
                // Hide loading overlay
                document.getElementById('loadingOverlay').classList.remove('active');
                
                // Create a URL for the blob
                const url = window.URL.createObjectURL(blob);
                
                // Create a link to download the PDF
                const a = document.createElement('a');
                a.style.display = 'none';
                a.href = url;
                a.download = 'inventory_report.pdf';
                
                // Append to the document and trigger the download
                document.body.appendChild(a);
                a.click();
                
                // Clean up
                window.URL.revokeObjectURL(url);
                document.body.removeChild(a);
                
                showAlert('Inventory report generated successfully.', 'success');
            })
            .catch(error => {
                // Hide loading overlay
                document.getElementById('loadingOverlay').classList.remove('active');
                console.error('Error generating PDF:', error);
                showAlert('Error generating inventory report. Please try again.', 'error');
            });
        }

        // Function to open image modal
        function openImageModal(imageSrc, productName) {
            const modal = document.getElementById('imageModal');
            const modalImage = document.getElementById('modalImage');
            
            modalImage.src = imageSrc;
            modalImage.alt = productName;
            modalImage.style.objectFit = 'contain';
            
            modal.classList.add('active');
        }

        // Function to show alert
        function showAlert(message, type) {
            const alertContainer = document.getElementById('alertContainer');
            const alertId = 'alert-' + Date.now();
            
            const alertHTML = `
                <div id="${alertId}" class="alert alert-${type}">
                    <div class="alert-icon">
                        <i class="fas fa-${type === 'success' ? 'check' : 'exclamation'}"></i>
                    </div>
                    <div class="alert-content">
                        ${message}
                    </div>
                </div>
            `;
            
            alertContainer.insertAdjacentHTML('afterbegin', alertHTML);
            
            // Remove alert after 5 seconds
            setTimeout(() => {
                const alertElement = document.getElementById(alertId);
                if (alertElement) {
                    alertElement.style.opacity = '0';
                    alertElement.style.transform = 'translateY(-10px)';
                    
                    setTimeout(() => {
                        if (alertElement && alertElement.parentNode) {
                            alertElement.parentNode.removeChild(alertElement);
                        }
                    }, 300);
                }
            }, 5000);
        }

        // Helper function to format numbers with commas
        function numberWithCommas(x) {
            return x.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ",");
        }

        // Debounce function to limit how often a function can be called
        function debounce(func, delay) {
            let timeout;
            return function() {
                const context = this;
                const args = arguments;
                clearTimeout(timeout);
                timeout = setTimeout(() => func.apply(context, args), delay);
            };
        }
    </script>
</body>

</html>
