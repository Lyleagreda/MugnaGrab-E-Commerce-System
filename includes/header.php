<header class="admin-header">
    <div class="header-left">
        <button class="menu-toggle" id="menuToggle">
            <i class="fas fa-bars"></i>
        </button>
        <div class="breadcrumb">
            <a href="index.php">Admin</a>
            <span>/</span>
            <span><?php echo $pageTitle; ?></span>
        </div>
    </div>
    
    <div class="header-right">
        <div class="header-search">
            <i class="fas fa-search"></i>
            <input type="text" placeholder="Search...">
        </div>
        
        <div class="header-actions">
            <div class="notification-dropdown">
                <button class="notification-btn" id="notificationBtn">
                    <i class="fas fa-bell"></i>
                    <span class="badge" id="notificationBadge">0</span>
                </button>
                <div class="notification-dropdown-menu" id="notificationDropdown">
                    <div class="notification-header">
                        <h3>Notifications</h3>
                        <button class="mark-all-read" id="markAllRead">Mark all as read</button>
                    </div>
                    <div class="notification-list" id="notificationList">
                        <div class="notification-loading">
                            <i class="fas fa-spinner fa-spin"></i>
                            <p>Loading notifications...</p>
                        </div>
                    </div>
                    <div class="notification-footer">
                        <a href="admin-notifications-all.php">View all notifications</a>
                    </div>
                </div>
            </div>
            
            <div class="admin-profile">
                <div class="profile-info">
                    <span class="role">Administrator</span>
                </div>
                <div class="dropdown">
                    <button class="dropdown-toggle">
                        <i class="fas fa-chevron-down"></i>
                    </button>
                    <div class="dropdown-menu">
                        <a href="account.php">
                            <i class="fas fa-user"></i>
                            My Profile
                        </a>
                        <a href="settings.php">
                            <i class="fas fa-cog"></i>
                            Settings
                        </a>
                        <div class="dropdown-divider"></div>
                        <a href="../logout.php">
                            <i class="fas fa-sign-out-alt"></i>
                            Logout
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</header>

<!-- Add notification styles -->
<style>
    /* Notification Dropdown */
    .notification-dropdown {
        position: relative;
    }

    .notification-btn {
        position: relative;
        width: 40px;
        height: 40px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        background-color: var(--light-bg);
        color: var(--text-secondary);
        border: 1px solid var(--border-color);
        cursor: pointer;
        transition: var(--transition-fast);
    }

    .notification-btn:hover {
        background-color: var(--primary-bg);
        color: var(--primary-color);
    }

    .notification-btn .badge {
        position: absolute;
        top: -5px;
        right: -5px;
        background-color: var(--danger-color);
        color: white;
        font-size: 0.7rem;
        font-weight: 600;
        width: 18px;
        height: 18px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .notification-dropdown-menu {
        position: absolute;
        top: 100%;
        right: 0;
        width: 350px;
        max-height: 500px;
        background-color: var(--card-bg);
        border-radius: var(--border-radius);
        box-shadow: var(--shadow-lg);
        border: 1px solid var(--border-color);
        z-index: 1000;
        display: none;
        flex-direction: column;
        overflow: hidden;
    }

    .notification-dropdown-menu.show {
        display: flex;
    }

    .notification-header {
        padding: 1rem;
        border-bottom: 1px solid var(--border-color);
        display: flex;
        align-items: center;
        justify-content: space-between;
    }

    .notification-header h3 {
        font-size: 1rem;
        font-weight: 600;
        margin: 0;
    }

    .mark-all-read {
        background: none;
        border: none;
        color: var(--primary-color);
        font-size: 0.8rem;
        cursor: pointer;
        padding: 0;
    }

    .mark-all-read:hover {
        text-decoration: underline;
    }

    .notification-list {
        overflow-y: auto;
        max-height: 350px;
    }

    .notification-item {
        padding: 1rem;
        border-bottom: 1px solid var(--border-color);
        cursor: pointer;
        transition: var(--transition-fast);
        display: flex;
        gap: 0.75rem;
        background-color: var(--card-bg);
    }

    .notification-item:hover {
        background-color: var(--light-bg);
    }

    .notification-item.unread {
        background-color: var(--primary-bg);
    }

    .notification-item.unread:hover {
        background-color: rgba(37, 99, 235, 0.1);
    }

    .notification-icon {
        width: 40px;
        height: 40px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
    }

    .notification-icon.new-order {
        background-color: var(--primary-bg);
        color: var(--primary-color);
    }

    .notification-icon.status-change {
        background-color: var(--success-bg);
        color: var(--success-color);
    }

    .notification-icon.new-user {
        background-color: var(--info-bg);
        color: var(--info-color);
    }

    .notification-content {
        flex: 1;
    }

    .notification-title {
        font-weight: 600;
        font-size: 0.9rem;
        margin-bottom: 0.25rem;
        color: var(--text-primary);
    }

    .notification-message {
        font-size: 0.8rem;
        color: var(--text-secondary);
        margin-bottom: 0.5rem;
    }

    .notification-time {
        font-size: 0.75rem;
        color: var(--text-muted);
    }

    .notification-footer {
        padding: 0.75rem;
        text-align: center;
        border-top: 1px solid var(--border-color);
    }

    .notification-footer a {
        color: var(--primary-color);
        font-size: 0.85rem;
        text-decoration: none;
    }

    .notification-footer a:hover {
        text-decoration: underline;
    }

    .notification-empty {
        padding: 2rem;
        text-align: center;
        color: var(--text-secondary);
    }

    .notification-empty i {
        font-size: 2rem;
        color: var(--border-color);
        margin-bottom: 1rem;
    }

    .notification-empty p {
        margin: 0;
    }

    .notification-loading {
        padding: 2rem;
        text-align: center;
        color: var(--text-secondary);
    }

    .notification-loading i {
        font-size: 1.5rem;
        color: var(--primary-color);
        margin-bottom: 1rem;
    }

    .notification-loading p {
        margin: 0;
    }

    /* Responsive adjustments */
    @media (max-width: 576px) {
        .notification-dropdown-menu {
            width: 300px;
            right: -100px;
        }
    }
</style>

<!-- Add notification scripts -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    const notificationBtn = document.getElementById('notificationBtn');
    const notificationDropdown = document.getElementById('notificationDropdown');
    const notificationBadge = document.getElementById('notificationBadge');
    const notificationList = document.getElementById('notificationList');
    const markAllReadBtn = document.getElementById('markAllRead');
    
    let notifications = [];
    
    // Toggle notification dropdown
    notificationBtn.addEventListener('click', function(e) {
        e.stopPropagation();
        notificationDropdown.classList.toggle('show');
        
        if (notificationDropdown.classList.contains('show')) {
            fetchNotifications();
        }
    });
    
    // Close dropdown when clicking outside
    document.addEventListener('click', function(e) {
        if (!notificationDropdown.contains(e.target) && e.target !== notificationBtn) {
            notificationDropdown.classList.remove('show');
        }
    });
    
    // Mark all notifications as read
    markAllReadBtn.addEventListener('click', function() {
        markAllAsRead();
    });
    
    // Fetch notifications
    function fetchNotifications() {
        fetch('admin-notifications.php')
            .then(response => response.json())
            .then(data => {
                if (data.error) {
                    console.error('Error fetching notifications:', data.error);
                    return;
                }
                
                notifications = data.notifications;
                updateNotificationBadge(data.unread_count);
                renderNotifications();
            })
            .catch(error => {
                console.error('Error fetching notifications:', error);
                notificationList.innerHTML = `
                    <div class="notification-empty">
                        <i class="fas fa-exclamation-circle"></i>
                        <p>Failed to load notifications</p>
                    </div>
                `;
            });
    }
    
    // Render notifications
    function renderNotifications() {
        if (notifications.length === 0) {
            notificationList.innerHTML = `
                <div class="notification-empty">
                    <i class="fas fa-bell-slash"></i>
                    <p>No new notifications</p>
                </div>
            `;
            return;
        }
        
        let html = '';
        
        notifications.forEach(notification => {
            let iconClass = '';
            let icon = '';
            
            if (notification.type === 'new_order') {
                iconClass = 'new-order';
                icon = 'shopping-cart';
            } else if (notification.type === 'status_change') {
                iconClass = 'status-change';
                icon = 'truck';
            } else if (notification.type === 'new_user') {
                iconClass = 'new-user';
                icon = 'user-plus';
            }
            
            const timeAgo = getTimeAgo(new Date(notification.time));
            
            html += `
                <div class="notification-item ${notification.is_read ? '' : 'unread'}" data-id="${notification.id}" data-link="${notification.link}">
                    <div class="notification-icon ${iconClass}">
                        <i class="fas fa-${icon}"></i>
                    </div>
                    <div class="notification-content">
                        <div class="notification-title">${notification.title}</div>
                        <div class="notification-message">${notification.message}</div>
                        <div class="notification-time">${timeAgo}</div>
                    </div>
                </div>
            `;
        });
        
        notificationList.innerHTML = html;
        
        // Add click event to notification items
        const notificationItems = document.querySelectorAll('.notification-item');
        notificationItems.forEach(item => {
            item.addEventListener('click', function() {
                const id = this.getAttribute('data-id');
                const link = this.getAttribute('data-link');
                
                markAsRead(id);
                window.location.href = link;
            });
        });
    }
    
    // Update notification badge
    function updateNotificationBadge(count) {
        notificationBadge.textContent = count;
        
        if (count > 0) {
            notificationBadge.style.display = 'flex';
        } else {
            notificationBadge.style.display = 'none';
        }
    }
    
    // Mark notification as read
    function markAsRead(id) {
        const formData = new FormData();
        formData.append('mark_read', '1');
        formData.append('notification_id', id);
        
        fetch('admin-notifications.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Update local notification data
                notifications.forEach(notification => {
                    if (notification.id === id) {
                        notification.is_read = true;
                    }
                });
                
                // Update UI
                const unreadCount = notifications.filter(n => !n.is_read).length;
                updateNotificationBadge(unreadCount);
                renderNotifications();
            }
        })
        .catch(error => {
            console.error('Error marking notification as read:', error);
        });
    }
    
    // Mark all notifications as read
    function markAllAsRead() {
        const formData = new FormData();
        formData.append('mark_all_read', '1');
        
        fetch('admin-notifications.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Update local notification data
                notifications.forEach(notification => {
                    notification.is_read = true;
                });
                
                // Update UI
                updateNotificationBadge(0);
                renderNotifications();
            }
        })
        .catch(error => {
            console.error('Error marking all notifications as read:', error);
        });
    }
    
    // Get time ago string
    function getTimeAgo(date) {
        const seconds = Math.floor((new Date() - date) / 1000);
        
        let interval = Math.floor(seconds / 31536000);
        if (interval >= 1) {
            return interval + " year" + (interval === 1 ? "" : "s") + " ago";
        }
        
        interval = Math.floor(seconds / 2592000);
        if (interval >= 1) {
            return interval + " month" + (interval === 1 ? "" : "s") + " ago";
        }
        
        interval = Math.floor(seconds / 86400);
        if (interval >= 1) {
            return interval + " day" + (interval === 1 ? "" : "s") + " ago";
        }
        
        interval = Math.floor(seconds / 3600);
        if (interval >= 1) {
            return interval + " hour" + (interval === 1 ? "" : "s") + " ago";
        }
        
        interval = Math.floor(seconds / 60);
        if (interval >= 1) {
            return interval + " minute" + (interval === 1 ? "" : "s") + " ago";
        }
        
        return "Just now";
    }
    
    // Check for new notifications periodically
    function checkForNewNotifications() {
        fetchNotifications();
    }
    
    // Initial fetch
    fetchNotifications();
    
    // Set interval to check for new notifications (every 60 seconds)
    setInterval(checkForNewNotifications, 60000);
});
</script>
