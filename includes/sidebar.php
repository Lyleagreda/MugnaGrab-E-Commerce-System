<aside class="sidebar">
    <div class="sidebar-header">
        <div class="logo">
            <img src="../images/mugna-logo.png" alt="Mugna Leather Arts">
        </div>
        <button class="sidebar-toggle" id="sidebarToggle">
            <i class="fas fa-bars"></i>
        </button>
    </div>
    
    <nav class="sidebar-nav">
        <ul>
            <li class="<?php echo $pageTitle === 'Dashboard' ? 'active' : ''; ?>">
                <a href="index.php">
                    <i class="fas fa-tachometer-alt"></i>
                    <span>Dashboard</span>
                </a>
            </li>
            <li class="<?php echo $pageTitle === 'Products' ? 'active' : ''; ?>">
                <a href="products.php">
                    <i class="fas fa-box"></i>
                    <span>Products</span>
                </a>
            </li>
            <li class="<?php echo $pageTitle === 'Slideshow' ? 'active' : ''; ?>">
                <a href="slideshow.php">
                    <i class="fas fa-images"></i>
                    <span>Homepage Slideshow</span>
                </a>
            </li>
            <li class="<?php echo $pageTitle === 'Users' ? 'active' : ''; ?>">
                <a href="users_accounts.php">
                    <i class="fas fa-users"></i>
                    <span>User Accounts</span>
                </a>
            </li>
            <li class="<?php echo $pageTitle === 'Orders' ? 'active' : ''; ?>">
                <a href="admin-orders.php">
                    <i class="fas fa-shopping-cart"></i>
                    <span>Orders</span>
                </a>
            </li>
            <li class="<?php echo $pageTitle === 'Inventory' ? 'active' : ''; ?>">
                <a href="admin-inventory.php">
                    <i class="fas fa-warehouse"></i>
                    <span>Inventory</span>
                </a>
            </li>
            <li class="<?php echo $pageTitle === 'Payments' ? 'active' : ''; ?>">
                <a href="admin-payment-history.php">
                    <i class="fas fa-credit-card"></i>
                    <span>Payment History</span>
                </a>
            </li>
            <li class="<?php echo $pageTitle === 'Analytics' ? 'active' : ''; ?>">
                <a href="admin-sales-analytics.php">
                    <i class="fas fa-chart-line"></i>
                    <span>Sales Analytics</span>
                </a>
            </li>
            <li class="<?php echo $pageTitle === 'Delivery Fees' ? 'active' : ''; ?>">
                <a href="delivery_fees.php">
                    <i class="fas fa-truck"></i>
                    <span>Delivery Fees</span>
                </a>
            </li>
            <li class="<?php echo $pageTitle === 'Account' ? 'active' : ''; ?>">
                <a href="account.php">
                    <i class="fas fa-user-shield"></i>
                    <span>Admin Account</span>
                </a>
            </li>
        </ul>
    </nav>
    
    <div class="sidebar-footer">
        <a href="../logout.php" class="logout-btn">
            <i class="fas fa-sign-out-alt"></i>
            <span>Logout</span>
        </a>
    </div>
</aside>
