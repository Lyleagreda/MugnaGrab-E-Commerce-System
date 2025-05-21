<?php
require_once 'config.php'; // adjust this if your DB connection file has a different name

$cartCount = 0;
$notificationCount = 0;

if (isset($_SESSION['user_id'])) {
    $user_id = $_SESSION['user_id'];
    
    // Get cart count
    $stmt = $conn->prepare("SELECT COUNT(DISTINCT product_id) AS unique_products FROM cart WHERE user_id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $stmt->bind_result($unique_products);
    $stmt->fetch();
    $cartCount = $unique_products ?? 0;
    $stmt->close();
    
    // Get unread notification count
    if (!isset($_SESSION['seen_notifications'])) {
        $_SESSION['seen_notifications'] = [];
    }
    
    // Get recent order status changes
    $stmt = $conn->prepare("SELECT id, status FROM orders 
                           WHERE user_id = ? AND (status = 'processing' OR status = 'shipped' OR status = 'delivered') 
                           ORDER BY COALESCE(updated_at, created_at) DESC");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        $notification_id = 'order_' . $row['id'] . '_' . $row['status'];
        if (!in_array($notification_id, $_SESSION['seen_notifications'])) {
            $notificationCount++;
        }
    }
    $stmt->close();
}

$currentPage = basename($_SERVER['PHP_SELF']);
$isProductsPage = ($currentPage == 'products.php');
?>

<style>
/* Search Autocomplete Styles - Shopee-like */
.search-autocomplete {
    position: relative;
}

.search-suggestions {
    position: absolute;
    top: 100%;
    left: 0;
    right: 0;
    background-color: white;
    border-radius: 0 0 8px 8px;
    box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);
    z-index: 1000;
    max-height: 300px;
    overflow-y: auto;
    display: none;
    border: 1px solid #e2e8f0;
    border-top: none;
}

.search-suggestion {
    padding: 10px 15px;
    cursor: pointer;
    transition: background-color 0.2s;
    font-size: 14px;
    color: #333;
}

.search-suggestion:hover {
    background-color: #f5f5f5;
}

.search-suggestion strong {
    color: #ee4d2d; /* Shopee's orange color */
    font-weight: 600;
}

.no-suggestions {
    padding: 15px;
    text-align: center;
    color: #64748b;
    font-size: 14px;
}

.search-input-wrapper {
    position: relative;
}

.search-input-wrapper .search-input {
    position: relative;
}

.search-input-wrapper .search-clear {
    position: absolute;
    right: 10px;
    top: 50%;
    transform: translateY(-50%);
    color: #94a3b8;
    cursor: pointer;
    display: none;
    font-size: 14px;
}

.search-input-wrapper .search-clear:hover {
    color: #64748b;
}

/* Notification Styles */
.notification-container {
    position: relative;
}

.notification-btn {
    position: relative;
    cursor: pointer;
}

.notification-count {
    position: absolute;
    top: -5px;
    right: -5px;
    background-color: #ee4d2d;
    color: white;
    border-radius: 50%;
    width: 18px;
    height: 18px;
    font-size: 11px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: bold;
}

.notification-dropdown {
    position: absolute;
    top: 100%;
    right: -10px;
    width: 320px;
    background-color: white;
    border-radius: 8px;
    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
    z-index: 1000;
    display: none;
    overflow: hidden;
    margin-top: 10px;
    border: 1px solid #e2e8f0;
}

.notification-container:hover .notification-dropdown {
    display: block;
}

.notification-header {
    padding: 12px 15px;
    border-bottom: 1px solid #e2e8f0;
    display: flex;
    justify-content: space-between;
    align-items: center;
    background-color: #f8f9fa;
}

.notification-header h4 {
    margin: 0;
    font-size: 16px;
    font-weight: 600;
}

.notification-header a {
    font-size: 13px;
    color: #4a5568;
    text-decoration: none;
}

.notification-list {
    max-height: 350px;
    overflow-y: auto;
}

.notification-item {
    padding: 12px 15px;
    border-bottom: 1px solid #e2e8f0;
    transition: background-color 0.2s;
    cursor: pointer;
}

.notification-item:hover {
    background-color: #f7fafc;
}

.notification-item.unread {
    background-color: #ebf8ff;
}

.notification-item .notification-title {
    font-weight: 600;
    margin-bottom: 5px;
    font-size: 14px;
    color: #2d3748;
}

.notification-item .notification-text {
    font-size: 13px;
    color: #4a5568;
    margin-bottom: 5px;
}

.notification-item .notification-time {
    font-size: 12px;
    color: #718096;
}

.notification-footer {
    padding: 10px;
    text-align: center;
    border-top: 1px solid #e2e8f0;
    background-color: #f8f9fa;
}

.notification-footer a {
    font-size: 14px;
    color: #4a5568;
    text-decoration: none;
}

.no-notifications {
    padding: 20px;
    text-align: center;
    color: #718096;
    font-size: 14px;
}

.notification-loading {
    padding: 15px;
    text-align: center;
    color: #718096;
}

.notification-loading i {
    margin-right: 5px;
}
</style>

<header class="site-header">
    <div class="container">
        <div class="header-content">
            <!-- Logo -->
            <a href="home.php" class="logo">
                <img src="images/mugna-logo.png" alt="Mugna" class="mugna-logo">
            </a>

            <!-- Desktop Navigation -->
            <nav class="main-nav desktop-nav">
                <ul>
                    <li><a href="home.php" class="<?= ($currentPage == 'home.php') ? 'active' : '' ?>">Home</a></li>
                    <li><a href="products.php" class="<?= ($currentPage == 'products.php') ? 'active' : '' ?>">Products</a></li>
                    <li><a href="deals.php" class="<?= ($currentPage == 'deals.php') ? 'active' : '' ?>">Deals</a></li>
                    <li><a href="support.php" class="<?= ($currentPage == 'support.php') ? 'active' : '' ?>">Support</a></li>

                </ul>
            </nav>

            <!-- Search Bar -->
            <div class="search-bar desktop-search">
                <div class="search-autocomplete">
                    <form action="products.php" method="get" id="desktop-search-form">
                        <div class="search-input-wrapper">
                            <div class="search-input">
                                <i class="fas fa-search"></i>
                                <input type="search" name="search" id="desktop-search-input" placeholder="Search for products..." autocomplete="off">
                                <span class="search-clear" id="desktop-search-clear">&times;</span>
                            </div>
                        </div>
                    </form>
                    <div class="search-suggestions" id="desktop-search-suggestions"></div>
                </div>
            </div>

            <!-- Desktop Action Buttons -->
            <div class="action-buttons desktop-actions">
                <button class="icon-btn">
                    <i class="fas fa-heart"></i>
                </button>
                
                <!-- Notification Button with Hover Dropdown -->
                <div class="notification-container">
                    <button class="icon-btn notification-btn" id="notificationBtn">
                        <i class="fas fa-bell"></i>
                        <?php if ($notificationCount > 0): ?>
                        <span class="notification-count"><?= $notificationCount ?></span>
                        <?php endif; ?>
                    </button>
                    <div class="notification-dropdown" id="notificationDropdown">
                        <div class="notification-header">
                            <h4>Notifications</h4>
                            <a href="my-notifications.php">See All</a>
                        </div>
                        <div class="notification-list" id="notificationList">
                            <div class="notification-loading">
                                <i class="fas fa-spinner fa-spin"></i> Loading...
                            </div>
                        </div>
                        <div class="notification-footer">
                            <a href="#" id="markAllRead">Mark all as read</a>
                        </div>
                    </div>
                </div>
                
                <div class="dropdown">
                    <button class="icon-btn dropdown-toggle">
                        <i class="fas fa-user"></i>
                    </button>
                    <div class="dropdown-menu">
                        <div class="dropdown-header">My Account</div>
                        <a href="account.php">Profile</a>
                        <a href="#">Orders</a>
                        <div class="dropdown-divider"></div>
                        <a href="logout.php">Sign out</a>
                    </div>
                </div>
                <button class="icon-btn cart-btn">
                    <a href="view_cart.php">
                        <i class="fas fa-shopping-cart"></i>
                        <span class="cart-count"><?= $cartCount ?></span>
                    </a>
                </button>
            </div>

            <!-- Mobile Menu Button -->
            <div class="mobile-menu-toggle">
                <button class="menu-btn">
                    <i class="fas fa-bars"></i>
                </button>
            </div>
        </div>

        <!-- Mobile Search Bar -->
        <div class="search-bar mobile-search">
            <div class="search-autocomplete">
                <form action="products.php" method="get" id="mobile-search-form">
                    <div class="search-input-wrapper">
                        <div class="search-input">
                            <i class="fas fa-search"></i>
                            <input type="search" name="search" id="mobile-search-input" placeholder="Search for products..." autocomplete="off">
                            <span class="search-clear" id="mobile-search-clear">&times;</span>
                        </div>
                    </div>
                </form>
                <div class="search-suggestions" id="mobile-search-suggestions"></div>
            </div>
        </div>

        <!-- Mobile Menu -->
        <div class="mobile-menu">
            <nav class="main-nav">
                <ul>
                    <li><a href="home.php" class="active">Home</a></li>
                    <li><a href="products.php">Products</a></li>
                    <!-- <li><a href="#">Deals</a></li>
                    <li><a href="#">Support</a></li> -->
                </ul>
            </nav>
            <div class="mobile-actions">
                <a href="account.php" class="btn btn-outline btn-sm">
                    <i class="fas fa-user"></i> Account
                </a>
                <a href="my-notifications.php" class="btn btn-outline btn-sm">
                    <i class="fas fa-bell"></i> Notifications <?php if ($notificationCount > 0): ?>(<?= $notificationCount ?>)<?php endif; ?>
                </a>
                <a href="view_cart.php" class="btn btn-outline btn-sm">
                    <i class="fas fa-shopping-cart"></i> Cart (3)
                </a>
            </div>
            <div class="mobile-logout">
                <a href="logout.php" class="btn btn-outline btn-sm btn-logout">
                    <i class="fas fa-sign-out-alt"></i> Sign Out
                </a>
            </div>
        </div>
    </div>
</header>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Check if we're on the products page
    const isProductsPage = <?= json_encode($isProductsPage) ?>;
    
    // Initialize search autocomplete for desktop and mobile
    initSearchAutocomplete('desktop', isProductsPage);
    initSearchAutocomplete('mobile', isProductsPage);
    
    // Initialize notifications
    initNotifications();
    
    function initSearchAutocomplete(device, isProductsPage) {
        const searchInput = document.getElementById(`${device}-search-input`);
        const searchSuggestions = document.getElementById(`${device}-search-suggestions`);
        const searchForm = document.getElementById(`${device}-search-form`);
        const searchClear = document.getElementById(`${device}-search-clear`);
        
        let debounceTimer;
        
        // Listen for input in search box
        searchInput.addEventListener('input', function() {
            const query = this.value.trim();
            
            // Show/hide clear button
            searchClear.style.display = query.length > 0 ? 'block' : 'none';
            
            // Clear previous timer
            clearTimeout(debounceTimer);
            
            // If query is empty, hide suggestions
            if (query.length < 1) {
                searchSuggestions.style.display = 'none';
                
                // If on products page, show all products
                if (isProductsPage) {
                    filterProductsLive('');
                }
                return;
            }
            
            // Debounce the API call to avoid too many requests
            debounceTimer = setTimeout(function() {
                // If on products page, filter products directly
                if (isProductsPage) {
                    filterProductsLive(query);
                    fetchSuggestions(query);
                } else {
                    // On other pages, just show suggestions
                    fetchSuggestions(query);
                }
            }, 200); // Faster response time like Shopee
        });
        
        // Clear search input
        searchClear.addEventListener('click', function() {
            searchInput.value = '';
            searchSuggestions.style.display = 'none';
            searchClear.style.display = 'none';
            searchInput.focus();
            
            // If on products page, show all products
            if (isProductsPage) {
                filterProductsLive('');
            }
        });
        
        // Close suggestions when clicking outside
        document.addEventListener('click', function(e) {
            if (!searchInput.contains(e.target) && !searchSuggestions.contains(e.target)) {
                searchSuggestions.style.display = 'none';
            }
        });
        
        // Fetch suggestions from API
        function fetchSuggestions(query) {
            fetch(`search_suggestions.php?q=${encodeURIComponent(query)}`)
                .then(response => response.json())
                .then(data => {
                    // Clear previous suggestions
                    searchSuggestions.innerHTML = '';
                    
                    if (data.length === 0) {
                        // No suggestions found
                        searchSuggestions.innerHTML = `
                            <div class="no-suggestions">
                                No products found for "${query}"
                            </div>
                        `;
                    } else {
                        // Create suggestion elements - simple text like Shopee
                        data.forEach(productName => {
                            const suggestion = document.createElement('div');
                            suggestion.className = 'search-suggestion';
                            suggestion.innerHTML = highlightMatch(productName, query);
                            
                            // Add click event to search for this product
                            suggestion.addEventListener('click', function() {
                                searchInput.value = productName;
                                
                                // If on products page, filter products directly
                                if (isProductsPage) {
                                    filterProductsLive(productName);
                                    searchSuggestions.style.display = 'none';
                                } else {
                                    // On other pages, submit the form to go to products page
                                    searchForm.submit();
                                }
                            });
                            
                            searchSuggestions.appendChild(suggestion);
                        });
                    }
                    
                    // Show suggestions
                    searchSuggestions.style.display = 'block';
                })
                .catch(error => {
                    console.error('Error fetching search suggestions:', error);
                });
        }
        
        // Highlight matching text in suggestions - Shopee style
        function highlightMatch(text, query) {
            const regex = new RegExp(`(${escapeRegExp(query)})`, 'gi');
            return text.replace(regex, '<strong>$1</strong>');
        }
        
        // Escape special characters for regex
        function escapeRegExp(string) {
            return string.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
        }
        
        // Handle form submission
        searchForm.addEventListener('submit', function(e) {
            // If on products page, prevent form submission and filter directly
            if (isProductsPage) {
                e.preventDefault();
                filterProductsLive(searchInput.value.trim());
                searchSuggestions.style.display = 'none';
            }
            // Otherwise, let the form submit normally to navigate to products.php
        });
    }
    
    // Function to filter products on the products page
    function filterProductsLive(query) {
        // Only run this on the products page
        if (!isProductsPage) return;
        
        // This function will be implemented in products.php
        // We're just making sure it exists before calling it
        if (typeof window.liveFilterProducts === 'function') {
            window.liveFilterProducts(query);
        }
    }
    
    // Initialize notifications
    function initNotifications() {
        const notificationContainer = document.querySelector('.notification-container');
        const notificationList = document.getElementById('notificationList');
        const markAllReadBtn = document.getElementById('markAllRead');
        
        // Load notifications when hovering over the notification container
        if (notificationContainer) {
            notificationContainer.addEventListener('mouseenter', function() {
                loadNotifications();
            });
        }
        
        // Mark all notifications as read
        if (markAllReadBtn) {
            markAllReadBtn.addEventListener('click', function(e) {
                e.preventDefault();
                markAllAsRead();
            });
        }
        
        // Load notifications
        function loadNotifications() {
            if (!notificationList) return;
            
            fetch('user-notifications.php?action=get_notifications')
                .then(response => response.json())
                .then(data => {
                    if (data.error) {
                        console.error('Error fetching notifications:', data.error);
                        notificationList.innerHTML = '<div class="no-notifications">Error loading notifications</div>';
                        return;
                    }
                    
                    if (data.length === 0) {
                        notificationList.innerHTML = '<div class="no-notifications">No notifications</div>';
                        return;
                    }
                    
                    let html = '';
                    
                    data.forEach(notification => {
                        let statusText = '';
                        let statusIcon = '';
                        
                        switch(notification.status) {
                            case 'processing':
                                statusText = 'Your order is now being processed';
                                statusIcon = '<i class="fas fa-cog text-blue-500 mr-2"></i>';
                                break;
                            case 'shipped':
                                statusText = 'Your order has been shipped';
                                statusIcon = '<i class="fas fa-shipping-fast text-green-500 mr-2"></i>';
                                break;
                            case 'delivered':
                                statusText = 'Your order has been delivered';
                                statusIcon = '<i class="fas fa-check-circle text-green-600 mr-2"></i>';
                                break;
                            default:
                                statusText = 'Your order status has been updated';
                                statusIcon = '<i class="fas fa-info-circle text-blue-500 mr-2"></i>';
                        }
                        
                        // Format date
                        const date = notification.updated_at ? new Date(notification.updated_at) : new Date(notification.created_at);
                        const formattedDate = date.toLocaleDateString() + ' ' + date.toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'});
                        
                        html += `
                            <div class="notification-item ${notification.read ? '' : 'unread'}" data-id="${notification.id}" data-notification-id="${notification.notification_id}">
                                <div class="notification-title">${statusIcon} Order #${notification.id}</div>
                                <div class="notification-text">${statusText}</div>
                                <div class="notification-time">${formattedDate}</div>
                            </div>
                        `;
                    });
                    
                    notificationList.innerHTML = html;
                    
                    // Add click event to notification items
                    const notificationItems = document.querySelectorAll('.notification-item');
                    notificationItems.forEach(item => {
                        item.addEventListener('click', function() {
                            const id = this.getAttribute('data-id');
                            
                            // Mark as read
                            if (!this.classList.contains('read')) {
                                markAsRead(id);
                            }
                            
                            // Navigate to order details
                            window.location.href = `order-details.php?id=${id}`;
                        });
                    });
                })
                .catch(error => {
                    console.error('Error loading notifications:', error);
                    notificationList.innerHTML = '<div class="no-notifications">Error loading notifications</div>';
                });
        }
        
        // Mark notification as read
        function markAsRead(id) {
            fetch(`user-notifications.php?action=mark_read&id=${id}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Update UI
                        updateNotificationCount();
                    }
                })
                .catch(error => {
                    console.error('Error marking notification as read:', error);
                });
        }
        
        // Mark all notifications as read
        function markAllAsRead() {
            fetch('user-notifications.php?action=mark_all_read')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Update UI
                        loadNotifications();
                        updateNotificationCount();
                    }
                })
                .catch(error => {
                    console.error('Error marking all notifications as read:', error);
                });
        }
        
        // Update notification count
        function updateNotificationCount() {
            fetch('user-notifications.php?action=get_count')
                .then(response => response.json())
                .then(data => {
                    const notificationCount = document.querySelector('.notification-count');
                    
                    if (data.count > 0) {
                        if (notificationCount) {
                            notificationCount.textContent = data.count;
                        } else {
                            // Create new count element if it doesn't exist
                            const newCount = document.createElement('span');
                            newCount.className = 'notification-count';
                            newCount.textContent = data.count;
                            document.querySelector('.notification-btn').appendChild(newCount);
                        }
                    } else {
                        if (notificationCount) {
                            notificationCount.remove();
                        }
                    }
                    
                    // Update mobile notification count
                    const mobileNotificationLink = document.querySelector('.mobile-actions a[href="my-notifications.php"]');
                    if (mobileNotificationLink) {
                        if (data.count > 0) {
                            mobileNotificationLink.innerHTML = `<i class="fas fa-bell"></i> Notifications (${data.count})`;
                        } else {
                            mobileNotificationLink.innerHTML = `<i class="fas fa-bell"></i> Notifications`;
                        }
                    }
                })
                .catch(error => {
                    console.error('Error updating notification count:', error);
                });
        }
    }
});
</script>
