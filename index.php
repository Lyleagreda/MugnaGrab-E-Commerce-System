<?php
session_start();
include '../db.php';

// Check if the user is logged in
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    // Display JavaScript alert if not logged in
    echo "<script>alert('You are not logged in. Please log in to access your account.');</script>";
    header('Location: ../index.php');
    exit;  // Stop further execution of the script
}

// Admin is logged in
$adminEmail = $_SESSION['email'] ?? 'Admin'; // Using email from your session

// Include products data
include_once '../data/products.php';

// Page title
$pageTitle = "Dashboard";
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Mugna</title>
    <link rel="stylesheet" href="css/admin-styles.css">
    <link rel="shortcut icon" href="../images/mugna-icon.png" type="image/x-icon">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <!-- Add jQuery for AJAX -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <!-- Add Sortable.js for smooth dragging -->
    <script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js"></script>
    <style>
        /* Dashboard Container */
        .dashboard-container {
            display: grid;
            grid-template-columns: repeat(12, 1fr);
            gap: 1.5rem;
            margin-bottom: 1.5rem;
        }

        /* Dashboard Card */
        .dashboard-card {
            background-color: white;
            border-radius: var(--border-radius);
            box-shadow: var(--box-shadow);
            overflow: hidden;
            transition: all 0.3s ease;
            cursor: grab;
            grid-column: span 12;
        }

        .dashboard-card:active {
            cursor: grabbing;
        }

        .dashboard-card.card-md {
            grid-column: span 6;
        }

        .dashboard-card.card-sm {
            grid-column: span 4;
        }

        /* Card Header */
        .card-header {
            padding: 1rem 1.5rem;
            background-color: #f8fafc;
            border-bottom: 1px solid #e2e8f0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .card-header h3 {
            margin: 0;
            font-size: 1rem;
            font-weight: 600;
            color: var(--gray-800);
            display: flex;
            align-items: center;
        }

        .card-header h3 i {
            margin-right: 0.5rem;
            color: var(--gray-500);
        }

        .card-header-actions {
            display: flex;
            gap: 0.5rem;
        }

        /* Card Body */
        .card-body {
            padding: 1.5rem;
        }

        /* Sortable Styles */
        .sortable-ghost {
            opacity: 0.4;
            background-color: #f1f5f9;
        }

        .sortable-drag {
            opacity: 0.8;
            transform: scale(1.02);
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
        }

        .sortable-chosen {
            box-shadow: 0 0 15px rgba(0, 0, 0, 0.1);
        }

        /* Responsive Adjustments */
        @media (max-width: 1200px) {

            .dashboard-card.card-md,
            .dashboard-card.card-sm {
                grid-column: span 6;
            }
        }

        @media (max-width: 768px) {
            .dashboard-container {
                grid-template-columns: 1fr;
            }

            .dashboard-card,
            .dashboard-card.card-md,
            .dashboard-card.card-sm {
                grid-column: span 1;
            }
        }

        /* Toggle Size Button */
        .toggle-size {
            background: none;
            border: none;
            color: var(--gray-500);
            cursor: pointer;
            font-size: 1rem;
            padding: 0.25rem;
            border-radius: 0.25rem;
            transition: all 0.2s ease;
        }

        .toggle-size:hover {
            background-color: #f1f5f9;
            color: var(--primary-color);
        }

        /* Hide Card Button */
        .hide-card {
            background: none;
            border: none;
            color: var(--gray-500);
            cursor: pointer;
            font-size: 1rem;
            padding: 0.25rem;
            border-radius: 0.25rem;
            transition: all 0.2s ease;
        }

        .hide-card:hover {
            background-color: #fee2e2;
            color: #ef4444;
        }

        /* Chart Container */
        .chart-container {
            height: 300px;
            position: relative;
        }

        /* Stats Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
        }

        /* Drag Handle */
        .drag-handle {
            cursor: grab;
            margin-right: 0.5rem;
            color: var(--gray-400);
        }

        .drag-handle:hover {
            color: var(--gray-600);
        }

        /* Dashboard Controls */
        .dashboard-controls {
            margin-bottom: 1.5rem;
            display: flex;
            flex-wrap: wrap;
            gap: 1rem;
            align-items: center;
        }

        /* Hidden Cards Menu */
        .hidden-cards-menu {
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
            align-items: center;
        }

        .hidden-cards-list {
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
        }

        .restore-card-btn {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.375rem 0.75rem;
            background-color: #f1f5f9;
            border: 1px solid #e2e8f0;
            border-radius: 0.375rem;
            font-size: 0.875rem;
            color: var(--gray-700);
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .restore-card-btn:hover {
            background-color: #e2e8f0;
            color: var(--gray-900);
        }

        .restore-card-btn i {
            font-size: 0.75rem;
        }

        /* Card Animation */
        .card-removing {
            transform: scale(0.9);
            opacity: 0;
        }

        /* Opening Animation Styles */
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .dashboard-card {
            opacity: 0;
            animation: fadeInUp 0.5s ease forwards;
        }

        /* Add animation delay for each card to create a staggered effect */
        #stats-card {
            animation-delay: 0.1s;
        }

        #sales-chart-card {
            animation-delay: 0.2s;
        }

        #category-chart-card {
            animation-delay: 0.3s;
        }

        #recent-orders-card {
            animation-delay: 0.4s;
        }

        #top-products-card {
            animation-delay: 0.5s;
        }

        /* Add a fade-in animation for the dashboard header */
        .dashboard-header {
            opacity: 0;
            animation: fadeIn 0.5s ease forwards;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
            }

            to {
                opacity: 1;
            }
        }

        /* Add a subtle animation for the dashboard controls */
        .dashboard-controls {
            opacity: 0;
            animation: fadeIn 0.5s ease 0.6s forwards;
        }
        
        /* Loading indicator */
        .loading-spinner {
            display: inline-block;
            width: 20px;
            height: 20px;
            border: 3px solid rgba(0, 0, 0, 0.1);
            border-radius: 50%;
            border-top-color: var(--primary-color);
            animation: spin 1s ease-in-out infinite;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }
        
        /* Refresh button */
        .refresh-btn {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.5rem 1rem;
            background-color: #f1f5f9;
            border: 1px solid #e2e8f0;
            border-radius: 0.375rem;
            font-size: 0.875rem;
            color: var(--gray-700);
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .refresh-btn:hover {
            background-color: #e2e8f0;
            color: var(--gray-900);
        }
        
        .refresh-btn i {
            transition: transform 0.3s ease;
        }
        
        .refresh-btn:hover i {
            transform: rotate(180deg);
        }
        
        /* Last updated text */
        .last-updated {
            font-size: 0.75rem;
            color: var(--gray-500);
            margin-left: auto;
        }
        
        /* Low stock notification */
        .low-stock-notification {
            position: fixed;
            bottom: 20px;
            right: 20px;
            width: 300px;
            background-color: white;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
            z-index: 1000;
            transform: translateY(100%);
            opacity: 0;
            transition: transform 0.3s ease, opacity 0.3s ease;
            overflow: hidden;
        }

        .notification-visible {
            transform: translateY(0);
            opacity: 1;
        }

        .notification-hiding {
            transform: translateY(10px);
            opacity: 0;
        }

        .notification-header {
            display: flex;
            align-items: center;
            padding: 12px 15px;
            background-color: #f97316;
            color: white;
        }

        .notification-header i {
            margin-right: 8px;
        }

        .notification-header .close-notification {
            margin-left: auto;
            background: none;
            border: none;
            color: white;
            cursor: pointer;
            padding: 0;
            font-size: 14px;
        }

        .notification-body {
            padding: 15px;
        }

        .notification-body p {
            margin-top: 0;
            margin-bottom: 10px;
        }

        .low-stock-list {
            list-style: none;
            padding: 0;
            margin: 0 0 15px 0;
        }

        .low-stock-list li {
            padding: 8px 0;
            border-bottom: 1px solid #e2e8f0;
            display: flex;
            justify-content: space-between;
        }

        .low-stock-list li:last-child {
            border-bottom: none;
        }

        .more-items {
            text-align: center;
            color: #64748b;
            font-style: italic;
        }

        .view-all-btn {
            display: block;
            text-align: center;
            padding: 8px;
            background-color: #f1f5f9;
            color: #0f172a;
            border-radius: 4px;
            text-decoration: none;
            transition: background-color 0.2s ease;
        }

        .view-all-btn:hover {
            background-color: #e2e8f0;
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

            <!-- Dashboard Content -->
            <div class="dashboard">
                <div class="dashboard-header">
                    <h1>Dashboard</h1>
                    <div class="date-filter">
                        <select id="dateRangeFilter">
                            <option value="today">Today</option>
                            <option value="yesterday">Yesterday</option>
                            <option value="last7days" selected>Last 7 days</option>
                            <option value="last30days">Last 30 days</option>
                            <option value="thisMonth">This Month</option>
                            <option value="lastMonth">Last Month</option>
                            <option value="custom">Custom Range</option>
                        </select>
                    </div>
                </div>

                <!-- Dashboard Controls -->
                <div class="dashboard-controls">
                    <button class="refresh-btn" id="refreshDashboard">
                        <i class="fas fa-sync-alt"></i> Refresh Data
                    </button>
                    <span class="last-updated" id="lastUpdated">Last updated: Just now</span>
                    <div class="hidden-cards-menu" id="hidden-cards-menu">
                        <button class="btn-outline btn-sm" id="restore-all-cards" style="display: none;">
                            <i class="fas fa-undo"></i> Restore All Cards
                        </button>
                        <div class="hidden-cards-list" id="hidden-cards-list"></div>
                    </div>
                </div>

                <!-- Draggable Dashboard Container -->
                <div class="dashboard-container" id="draggable-dashboard">
                    <!-- Stats Card -->
                    <div class="dashboard-card" id="stats-card">
                        <div class="card-header">
                            <h3>
                                <i class="fas fa-grip-vertical drag-handle"></i>
                                <span>Key Metrics</span>
                            </h3>
                            <div class="card-header-actions">
                                <button class="toggle-size" data-size="lg">
                                    <i class="fas fa-compress-alt"></i>
                                </button>
                                <button class="hide-card" title="Hide card">
                                    <i class="fas fa-times"></i>
                                </button>
                            </div>
                        </div>
                        <div class="card-body">
                            <div class="stats-grid">
                                <div class="stat-card">
                                    <div class="stat-icon sales">
                                        <i class="fas fa-peso-sign"></i>
                                    </div>
                                    <div class="stat-details">
                                        <h3>Total Sales</h3>
                                        <p class="stat-value" id="totalSales"><div class="loading-spinner"></div></p>
                                        <p class="stat-change positive">+12.5% <span>vs last period</span></p>
                                    </div>
                                </div>

                                <div class="stat-card">
                                    <div class="stat-icon orders">
                                        <i class="fas fa-shopping-cart"></i>
                                    </div>
                                    <div class="stat-details">
                                        <h3>Total Orders</h3>
                                        <p class="stat-value" id="totalOrders"><div class="loading-spinner"></div></p>
                                        <p class="stat-change positive">+8.2% <span>vs last period</span></p>
                                    </div>
                                </div>

                                <div class="stat-card">
                                    <div class="stat-icon pending">
                                        <i class="fas fa-clock"></i>
                                    </div>
                                    <div class="stat-details">
                                        <h3>Pending Orders</h3>
                                        <p class="stat-value" id="pendingOrders"><div class="loading-spinner"></div></p>
                                    </div>
                                </div>

                                <div class="stat-card">
                                    <div class="stat-icon stock">
                                        <i class="fas fa-exclamation-triangle"></i>
                                    </div>
                                    <div class="stat-details">
                                        <h3>Low Stock Items</h3>
                                        <p class="stat-value" id="lowStockItems"><div class="loading-spinner"></div></p>
                                        <p class="stat-change neutral">0% <span>vs last period</span></p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Sales Chart -->
                    <div class="dashboard-card card-md" id="sales-chart-card">
                        <div class="card-header">
                            <h3>
                                <i class="fas fa-grip-vertical drag-handle"></i>
                                <span>Sales Overview</span>
                            </h3>
                            <div class="card-header-actions">
                                <button class="toggle-size" data-size="md">
                                    <i class="fas fa-expand-alt"></i>
                                </button>
                                <button class="hide-card" title="Hide card">
                                    <i class="fas fa-times"></i>
                                </button>
                            </div>
                        </div>
                        <div class="card-body">
                            <div class="chart-actions" style="margin-bottom: 1rem;">
                                <button class="btn-outline btn-sm active" data-period="weekly">Weekly</button>
                                <button class="btn-outline btn-sm" data-period="monthly">Monthly</button>
                                <button class="btn-outline btn-sm" data-period="yearly">Yearly</button>
                            </div>
                            <div class="chart-container">
                                <canvas id="salesChart"></canvas>
                            </div>
                        </div>
                    </div>

                    <!-- Category Chart -->
                    <div class="dashboard-card card-md" id="category-chart-card">
                        <div class="card-header">
                            <h3>
                                <i class="fas fa-grip-vertical drag-handle"></i>
                                <span>Sales by Category</span>
                            </h3>
                            <div class="card-header-actions">
                                <button class="toggle-size" data-size="md">
                                    <i class="fas fa-expand-alt"></i>
                                </button>
                                <button class="hide-card" title="Hide card">
                                    <i class="fas fa-times"></i>
                                </button>
                            </div>
                        </div>
                        <div class="card-body">
                            <div class="chart-container">
                                <canvas id="categoryChart"></canvas>
                            </div>
                        </div>
                    </div>

                    <!-- Recent Orders -->
                    <div class="dashboard-card card-md" id="recent-orders-card">
                        <div class="card-header">
                            <h3>
                                <i class="fas fa-grip-vertical drag-handle"></i>
                                <span>Recent Orders</span>
                            </h3>
                            <div class="card-header-actions">
                                <a href="orders.php" class="btn-icon" title="View All">
                                    <i class="fas fa-external-link-alt"></i>
                                </a>
                                <button class="toggle-size" data-size="md">
                                    <i class="fas fa-expand-alt"></i>
                                </button>
                                <button class="hide-card" title="Hide card">
                                    <i class="fas fa-times"></i>
                                </button>
                            </div>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table>
                                    <thead>
                                        <tr>
                                            <th>Order ID</th>
                                            <th>Customer</th>
                                            <th>Date</th>
                                            <th>Amount</th>
                                            <th>Status</th>
                                        </tr>
                                    </thead>
                                    <tbody id="recentOrdersTable">
                                        <tr>
                                            <td colspan="5" class="text-center">
                                                <div class="loading-spinner"></div> Loading...
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <!-- Top Products -->
                    <div class="dashboard-card card-md" id="top-products-card">
                        <div class="card-header">
                            <h3>
                                <i class="fas fa-grip-vertical drag-handle"></i>
                                <span>Top Selling Products</span>
                            </h3>
                            <div class="card-header-actions">
                                <a href="products.php" class="btn-icon" title="View All">
                                    <i class="fas fa-external-link-alt"></i>
                                </a>
                                <button class="toggle-size" data-size="md">
                                    <i class="fas fa-expand-alt"></i>
                                </button>
                                <button class="hide-card" title="Hide card">
                                    <i class="fas fa-times"></i>
                                </button>
                            </div>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table>
                                    <thead>
                                        <tr>
                                            <th>Product</th>
                                            <th>Category</th>
                                            <th>Sold</th>
                                            <th>Revenue</th>
                                        </tr>
                                    </thead>
                                    <tbody id="topProductsTable">
                                        <tr>
                                            <td colspan="4" class="text-center">
                                                <div class="loading-spinner"></div> Loading...
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script src="js/admin.js"></script>
    <script>
        // Global chart variables
        let salesChart;
        let categoryChart;
        
        document.addEventListener('DOMContentLoaded', function () {
            // Load all dashboard data
            loadDashboardData();
            
            // Initialize Sortable for smooth dragging
            const dashboardContainer = document.getElementById('draggable-dashboard');
            new Sortable(dashboardContainer, {
                animation: 150,
                handle: '.drag-handle',
                ghostClass: 'sortable-ghost',
                chosenClass: 'sortable-chosen',
                dragClass: 'sortable-drag',
                easing: "cubic-bezier(1, 0, 0, 1)",
                onEnd: function () {
                    // Redraw charts after drag to fix any rendering issues
                    if (salesChart) salesChart.update();
                    if (categoryChart) categoryChart.update();
                }
            });

            // Toggle card size
            const sizeButtons = document.querySelectorAll('.toggle-size');
            sizeButtons.forEach(button => {
                button.addEventListener('click', function () {
                    const card = this.closest('.dashboard-card');
                    const currentSize = this.getAttribute('data-size');
                    const icon = this.querySelector('i');

                    if (currentSize === 'lg') {
                        // Change to medium size
                        card.classList.add('card-md');
                        this.setAttribute('data-size', 'md');
                        icon.classList.remove('fa-compress-alt');
                        icon.classList.add('fa-expand-alt');
                    } else if (currentSize === 'md') {
                        // Change to large size
                        card.classList.remove('card-md');
                        this.setAttribute('data-size', 'lg');
                        icon.classList.remove('fa-expand-alt');
                        icon.classList.add('fa-compress-alt');
                    }

                    // Redraw charts if they exist in this card
                    setTimeout(() => {
                        const salesChartEl = card.querySelector('#salesChart');
                        const categoryChartEl = card.querySelector('#categoryChart');

                        if (salesChartEl && salesChart) {
                            salesChart.update();
                        }

                        if (categoryChartEl && categoryChart) {
                            categoryChart.update();
                        }
                    }, 300);
                });
            });

            // Hide card functionality
            const hideButtons = document.querySelectorAll('.hide-card');
            const hiddenCardsList = document.getElementById('hidden-cards-list');
            const restoreAllBtn = document.getElementById('restore-all-cards');
            const hiddenCards = {};

            hideButtons.forEach(button => {
                button.addEventListener('click', function () {
                    const card = this.closest('.dashboard-card');
                    const cardId = card.id;
                    const cardTitle = card.querySelector('.card-header h3 span').textContent.trim();

                    // Animate card removal
                    card.classList.add('card-removing');

                    // Store card reference
                    hiddenCards[cardId] = {
                        element: card,
                        title: cardTitle
                    };

                    // Remove card after animation
                    setTimeout(() => {
                        card.style.display = 'none';
                        card.classList.remove('card-removing');

                        // Add restore button
                        const restoreBtn = document.createElement('button');
                        restoreBtn.className = 'restore-card-btn';
                        restoreBtn.dataset.cardId = cardId;
                        restoreBtn.innerHTML = `<i class="fas fa-plus"></i> ${cardTitle}`;
                        restoreBtn.addEventListener('click', function () {
                            restoreCard(this.dataset.cardId);
                        });

                        hiddenCardsList.appendChild(restoreBtn);

                        // Show restore all button if there are hidden cards
                        if (Object.keys(hiddenCards).length > 0) {
                            restoreAllBtn.style.display = 'inline-flex';
                        }

                        // Redraw charts after layout changes
                        setTimeout(() => {
                            if (salesChart) salesChart.update();
                            if (categoryChart) categoryChart.update();
                        }, 300);
                    }, 300);
                });
            });

            // Restore card function
            function restoreCard(cardId) {
                if (hiddenCards[cardId]) {
                    const card = hiddenCards[cardId].element;
                    card.style.display = '';

                    // Remove restore button
                    const restoreBtn = hiddenCardsList.querySelector(`[data-card-id="${cardId}"]`);
                    if (restoreBtn) {
                        restoreBtn.remove();
                    }

                    // Delete from hidden cards
                    delete hiddenCards[cardId];

                    // Hide restore all button if no more hidden cards
                    if (Object.keys(hiddenCards).length === 0) {
                        restoreAllBtn.style.display = 'none';
                    }

                    // Redraw charts if they exist in this card
                    setTimeout(() => {
                        const salesChartEl = card.querySelector('#salesChart');
                        const categoryChartEl = card.querySelector('#categoryChart');

                        if (salesChartEl && salesChart) {
                            salesChart.update();
                        }

                        if (categoryChartEl && categoryChart) {
                            categoryChart.update();
                        }
                    }, 300);
                }
            }

            // Restore all cards
            restoreAllBtn.addEventListener('click', function () {
                const cardIds = Object.keys(hiddenCards);
                cardIds.forEach(cardId => {
                    restoreCard(cardId);
                });
            });
            
            // Refresh dashboard data when refresh button is clicked
            document.getElementById('refreshDashboard').addEventListener('click', function() {
                loadDashboardData();
                updateLastUpdated();
            });
            
            // Date range filter change
            document.getElementById('dateRangeFilter').addEventListener('change', function() {
                loadDashboardData();
                updateLastUpdated();
            });
            
            // Auto refresh dashboard data every 5 minutes
            setInterval(function() {
                loadDashboardData();
                updateLastUpdated();
            }, 300000); // 5 minutes
        });
        
        // Function to load all dashboard data
        function loadDashboardData() {
            // Load metrics
            loadMetrics();
            
            // Load sales overview chart
            loadSalesOverviewChart();
            
            // Load sales by category chart
            loadSalesByCategoryChart();
            
            // Load recent orders
            loadRecentOrders();
            
            // Load top products
            loadTopProducts();
            
            // Add this line after loadTopProducts();
            checkProductsData();
        }
        
        // Function to load metrics
        function loadMetrics() {
            $.ajax({
                url: 'dashboard-metrics.php',
                type: 'GET',
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        // Format total sales with commas and PHP currency symbol
                        $('#totalSales').text('₱' + numberWithCommas(response.data.totalSales.toFixed(2)));
                        $('#totalOrders').text(response.data.totalOrders);
                        $('#pendingOrders').text(response.data.pendingOrders);
                        $('#lowStockItems').text(response.data.lowStockItems);
                    } else {
                        console.error('Error loading metrics:', response.message);
                    }
                },
                error: function(xhr, status, error) {
                    console.error('AJAX error:', error);
                }
            });
        }
        
        // Function to load sales overview chart
        function loadSalesOverviewChart() {
            $.ajax({
                url: 'dashboard-sales-overview.php',
                type: 'GET',
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        renderSalesOverviewChart(response.data);
                    } else {
                        console.error('Error loading sales overview chart:', response.message);
                    }
                },
                error: function(xhr, status, error) {
                    console.error('AJAX error:', error);
                }
            });
        }
        
        // Function to load sales by category chart
        function loadSalesByCategoryChart() {
            $.ajax({
                url: 'dashboard-sales-by-category.php',
                type: 'GET',
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        renderSalesByCategoryChart(response.data);
                    } else {
                        console.error('Error loading sales by category chart:', response.message);
                    }
                },
                error: function(xhr, status, error) {
                    console.error('AJAX error:', error);
                }
            });
        }
        
        // Function to load recent orders
        function loadRecentOrders() {
            $.ajax({
                url: 'dashboard-recent-orders.php',
                type: 'GET',
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        renderRecentOrders(response.data);
                    } else {
                        console.error('Error loading recent orders:', response.message);
                    }
                },
                error: function(xhr, status, error) {
                    console.error('AJAX error:', error);
                }
            });
        }
        
        // Function to load top products
        function loadTopProducts() {
            $.ajax({
                url: 'dashboard-top-products.php',
                type: 'GET',
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        renderTopProducts(response.data);
                    } else {
                        console.error('Error loading top products:', response.message);
                    }
                },
                error: function(xhr, status, error) {
                    console.error('AJAX error:', error);
                }
            });
        }
        
        // Function to check products data
        function checkProductsData() {
            $.ajax({
                url: 'dashboard-products-check.php',
                type: 'GET',
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        console.log('Products data loaded successfully');
                        console.log('Total products:', response.data.totalProducts);
                        console.log('Low stock items:', response.data.lowStockItems);
                        console.log('Low stock threshold:', response.data.lowStockThreshold);
                        
                        // Update low stock items count
                        $('#lowStockItems').text(response.data.lowStockItems);
                        
                        // If there are low stock items, show a notification
                        if (response.data.lowStockItems > 0) {
                            showLowStockNotification(response.data.lowStockItems, response.data.lowStockProducts);
                        }
                    } else {
                        console.error('Error checking products data:', response.message);
                    }
                },
                error: function(xhr, status, error) {
                    console.error('AJAX error:', error);
                }
            });
        }

        // Function to show low stock notification
        function showLowStockNotification(count, products) {
            // Create notification if it doesn't exist
            if (!document.getElementById('lowStockNotification')) {
                const notification = document.createElement('div');
                notification.id = 'lowStockNotification';
                notification.className = 'low-stock-notification';
                notification.innerHTML = `
                    <div class="notification-header">
                        <i class="fas fa-exclamation-triangle"></i>
                        <span>Low Stock Alert</span>
                        <button class="close-notification"><i class="fas fa-times"></i></button>
                    </div>
                    <div class="notification-body">
                        <p>You have <strong>${count}</strong> products with low stock.</p>
                        <ul class="low-stock-list">
                            ${products.slice(0, 3).map(product => `
                                <li>
                                    <span class="product-name">${product.name}</span>
                                    <span class="product-stock">Stock: ${product.stock}</span>
                                </li>
                            `).join('')}
                            ${products.length > 3 ? `<li class="more-items">+${products.length - 3} more items</li>` : ''}
                        </ul>
                        <a href="products.php?filter=low_stock" class="view-all-btn">View All</a>
                    </div>
                `;

                document.body.appendChild(notification);

                // Add event listener to close button
                notification.querySelector('.close-notification').addEventListener('click', function() {
                    notification.classList.add('notification-hiding');
                    setTimeout(() => {
                        notification.remove();
                    }, 300);
                });

                // Show notification with animation
                setTimeout(() => {
                    notification.classList.add('notification-visible');
                }, 500);
            }
        }
        
        // Function to render sales overview chart
        function renderSalesOverviewChart(data) {
            const ctx = document.getElementById('salesChart').getContext('2d');
            
            // Destroy existing chart if it exists
            if (salesChart) {
                salesChart.destroy();
            }
            
            salesChart = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: data.labels,
                    datasets: [
                        {
                            label: 'This Week',
                            data: data.currentWeek,
                            borderColor: '#2563eb',
                            backgroundColor: 'rgba(37, 99, 235, 0.1)',
                            tension: 0.4,
                            fill: true
                        },
                        {
                            label: 'Last Week',
                            data: data.prevWeek,
                            borderColor: '#94a3b8',
                            backgroundColor: 'rgba(148, 163, 184, 0.1)',
                            tension: 0.4,
                            fill: true
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'top'
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    let label = context.dataset.label || '';
                                    if (label) {
                                        label += ': ';
                                    }
                                    if (context.parsed.y !== null) {
                                        label += '₱' + context.parsed.y.toLocaleString();
                                    }
                                    return label;
                                }
                            }
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                callback: function(value) {
                                    return '₱' + value / 1000 + 'k';
                                }
                            }
                        }
                    }
                }
            });
        }
        
        // Function to render sales by category chart
        function renderSalesByCategoryChart(data) {
            const ctx = document.getElementById('categoryChart').getContext('2d');
            
            // Destroy existing chart if it exists
            if (categoryChart) {
                categoryChart.destroy();
            }
            
            categoryChart = new Chart(ctx, {
                type: 'doughnut',
                data: data,
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'right'
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    const label = context.label || '';
                                    const value = context.raw || 0;
                                    const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                    const percentage = Math.round((value / total) * 100);
                                    return `${label}: ₱${numberWithCommas(value.toFixed(2))} (${percentage}%)`;
                                }
                            }
                        }
                    },
                    cutout: '70%'
                }
            });
        }
        
        // Function to render recent orders
        function renderRecentOrders(data) {
            let html = '';
            
            if (data.length === 0) {
                html = '<tr><td colspan="5" class="text-center">No pending orders found</td></tr>';
            } else {
                data.forEach(order => {
                    html += `
                        <tr>
                            <td>${order.id}</td>
                            <td>${order.customer}</td>
                            <td>${order.date}</td>
                            <td>₱${order.amount}</td>
                            <td>
                                <span class="status-badge ${order.status.toLowerCase()}">
                                    ${order.status}
                                </span>
                            </td>
                        </tr>
                    `;
                });
            }
            
            $('#recentOrdersTable').html(html);
        }
        
        // Function to render top products
        function renderTopProducts(data) {
            let html = '';
            
            if (!data || data.length === 0) {
                html = '<tr><td colspan="4" class="text-center">No products found</td></tr>';
            } else {
                data.forEach(product => {
                    html += `
                        <tr>
                            <td>
                                <div class="product-info">
                                    <span class="product-name">${product.product_name}</span>
                                </div>
                            </td>
                            <td>${product.category || 'Uncategorized'}</td>
                            <td>${product.quantity_sold}</td>
                            <td>₱${numberWithCommas(parseFloat(product.revenue).toFixed(2))}</td>
                        </tr>
                    `;
                });
            }
            
            $('#topProductsTable').html(html);
        }
        
        // Helper function to format numbers with commas
        function numberWithCommas(x) {
            return x.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ",");
        }
        
        // Function to update last updated text
        function updateLastUpdated() {
            const now = new Date();
            const hours = now.getHours().toString().padStart(2, '0');
            const minutes = now.getMinutes().toString().padStart(2, '0');
            const seconds = now.getSeconds().toString().padStart(2, '0');
            const timeString = `${hours}:${minutes}:${seconds}`;
            
            document.getElementById('lastUpdated').textContent = `Last updated: ${timeString}`;
        }
    </script>
</body>

</html>
