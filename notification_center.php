<?php
/**
 * Notification Center HTML Component
 * Include this file to display notifications in any page
 */

// This should be included at the top of pages
if (!isset($_SESSION['user_id'])) {
    return; // Don't show if user not logged in
}

require_once 'NotificationManager.php';
$notificationManager = new NotificationManager($conn);
$unreadCount = 0;
$userId = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;

if ($userId !== null) {
    // Use the correct method name: getUnreadCounts
    if (method_exists($notificationManager, 'getUnreadCounts')) {
        $result = $notificationManager->getUnreadCounts($userId);
        if (is_array($result) && isset($result['unread_count'])) {
            $unreadCount = (int) $result['unread_count'];
        } else {
            $unreadCount = (int) $result;
        }
    }
}
?>

<!-- Notification Center CSS -->
<style>
    /* Notification Bell Icon */
    .notification-bell {
        position: relative;
        cursor: pointer;
        font-size: 20px;
        margin: 0 15px;
    }

    .notification-bell:hover {
        color: var(--primary, #007bff);
    }

    .notification-badge {
        position: absolute;
        top: -8px;
        right: -8px;
        background: #ff4757;
        color: white;
        border-radius: 50%;
        width: 24px;
        height: 24px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 12px;
        font-weight: bold;
        animation: pulse 2s infinite;
    }

    @keyframes pulse {
        0% {
            box-shadow: 0 0 0 0 rgba(255, 71, 87, 0.7);
        }

        70% {
            box-shadow: 0 0 0 10px rgba(255, 71, 87, 0);
        }

        100% {
            box-shadow: 0 0 0 0 rgba(255, 71, 87, 0);
        }
    }

    /* Notification Dropdown */
    .notification-dropdown {
        position: absolute;
        top: 100%;
        right: 0;
        background: white;
        border-radius: 8px;
        box-shadow: 0 4px 20px rgba(0, 0, 0, 0.15);
        width: 360px;
        max-height: 500px;
        overflow-y: auto;
        margin-top: 10px;
        z-index: 1000;
        display: none;
        flex-direction: column;
    }

    .notification-dropdown.active {
        display: flex;
    }

    .notification-dropdown-header {
        padding: 15px;
        border-bottom: 1px solid #eee;
        display: flex;
        justify-content: space-between;
        align-items: center;
        font-weight: bold;
        background: var(--primary, #007bff);
        color: white;
        border-radius: 8px 8px 0 0;
    }

    .notification-dropdown-header .close-btn {
        cursor: pointer;
        font-size: 18px;
        opacity: 0.8;
    }

    .notification-dropdown-header .close-btn:hover {
        opacity: 1;
    }

    .notification-item {
        padding: 12px 15px;
        border-bottom: 1px solid #f0f0f0;
        cursor: pointer;
        transition: background 0.2s;
        display: flex;
        gap: 10px;
    }

    .notification-item:hover {
        background: #f9f9f9;
    }

    .notification-item.unread {
        background: #f0f7ff;
        border-left: 3px solid var(--primary, #007bff);
    }

    .notification-item-icon {
        min-width: 40px;
        width: 40px;
        height: 40px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 18px;
    }

    .notification-item-icon.success {
        background: #d4edda;
        color: #155724;
    }

    .notification-item-icon.warning {
        background: #fff3cd;
        color: #856404;
    }

    .notification-item-icon.error {
        background: #f8d7da;
        color: #721c24;
    }

    .notification-item-icon.info {
        background: #d1ecf1;
        color: #0c5460;
    }

    .notification-item-content {
        flex: 1;
        min-width: 0;
    }

    .notification-item-title {
        font-weight: 600;
        color: #333;
        margin-bottom: 4px;
    }

    .notification-item-message {
        font-size: 13px;
        color: #666;
        line-height: 1.4;
        display: -webkit-box;
        -webkit-box-orient: vertical;
        -webkit-line-clamp: 2;
        line-clamp: 2;
        overflow: hidden;
    }

    .notification-item-time {
        font-size: 11px;
        color: #999;
        margin-top: 4px;
    }

    .notification-item-actions {
        display: flex;
        gap: 8px;
        justify-content: flex-end;
        margin-top: 8px;
    }

    .notification-item-actions button {
        background: none;
        border: none;
        cursor: pointer;
        font-size: 13px;
        color: var(--primary, #007bff);
        padding: 4px 8px;
    }

    .notification-item-actions button:hover {
        text-decoration: underline;
    }

    .notification-empty {
        padding: 30px 15px;
        text-align: center;
        color: #999;
    }

    .notification-empty-icon {
        font-size: 40px;
        margin-bottom: 10px;
        opacity: 0.5;
    }

    .notification-footer {
        padding: 10px;
        text-align: center;
        border-top: 1px solid #eee;
    }

    .notification-footer a {
        color: var(--primary, #007bff);
        text-decoration: none;
        font-size: 13px;
    }

    .notification-footer a:hover {
        text-decoration: underline;
    }

    /* Overlay for dropdown */
    .notification-overlay {
        display: none;
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        z-index: 999;
    }

    .notification-overlay.active {
        display: block;
    }
</style>

<!-- Notification Bell HTML -->
<div class="notification-center" style="position: relative;">
    <div class="notification-bell" onclick="toggleNotificationDropdown()" title="Notifications">
        <i class="fas fa-bell"></i>
        <?php if ($unreadCount > 0): ?>
            <span class="notification-badge"><?php echo min($unreadCount, 99); ?></span>
        <?php endif; ?>
    </div>

    <!-- Notification Dropdown -->
    <div class="notification-dropdown" id="notificationDropdown">
        <div class="notification-dropdown-header">
            <span>Notifications</span>
            <span class="close-btn" onclick="closeNotificationDropdown()">×</span>
        </div>

        <div id="notificationList" style="flex: 1; overflow-y: auto;">
            <!-- Notifications will be loaded here via JavaScript -->
            <div class="notification-empty">
                <div class="notification-empty-icon">
                    <i class="fas fa-inbox"></i>
                </div>
                <p>Loading notifications...</p>
            </div>
        </div>

        <div class="notification-footer">
            <a href="javascript:void(0)" onclick="viewAllNotifications()">
                <i class="fas fa-list"></i> View All Notifications
            </a>
        </div>
    </div>

    <!-- Overlay -->
    <div class="notification-overlay" id="notificationOverlay" onclick="closeNotificationDropdown()"></div>
</div>

<!-- Notification JavaScript -->
<script>
    // Load notifications on page load
    document.addEventListener('DOMContentLoaded', function () {
        loadNotifications();
        // Refresh notifications every 30 seconds
        setInterval(loadNotifications, 30000);
    });

    function toggleNotificationDropdown() {
        const dropdown = document.getElementById('notificationDropdown');
        const overlay = document.getElementById('notificationOverlay');

        if (dropdown.classList.contains('active')) {
            closeNotificationDropdown();
        } else {
            dropdown.classList.add('active');
            overlay.classList.add('active');
            loadNotifications();
        }
    }

    function closeNotificationDropdown() {
        const dropdown = document.getElementById('notificationDropdown');
        const overlay = document.getElementById('notificationOverlay');
        dropdown.classList.remove('active');
        overlay.classList.remove('active');
    }

    function loadNotifications() {
        fetch('notification_manager.php?action=get_unread')
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    displayNotifications(data.notifications);
                    updateNotificationBadge();
                }
            })
            .catch(error => console.error('Error loading notifications:', error));
    }

    function displayNotifications(notifications) {
        const notificationList = document.getElementById('notificationList');

        if (notifications.length === 0) {
            notificationList.innerHTML = `
                <div class="notification-empty">
                    <div class="notification-empty-icon">
                        <i class="fas fa-inbox"></i>
                    </div>
                    <p>No new notifications</p>
                </div>
            `;
            return;
        }

        notificationList.innerHTML = notifications.map(notification => `
            <div class="notification-item unread" data-notification-id="${notification.id}">
                <div class="notification-item-icon ${notification.type}">
                    <i class="fas fa-${getIconForType(notification.type)}"></i>
                </div>
                <div class="notification-item-content">
                    <div class="notification-item-title">${escapeHtml(notification.title)}</div>
                    <div class="notification-item-message">${escapeHtml(notification.message)}</div>
                    <div class="notification-item-time">${getTimeAgo(notification.created_at)}</div>
                    <div class="notification-item-actions">
                        ${notification.action_url ? `<button onclick="window.location.href='${notification.action_url}'">View</button>` : ''}
                        <button onclick="markNotificationAsRead(${notification.id})">Dismiss</button>
                    </div>
                </div>
            </div>
        `).join('');
    }

    function markNotificationAsRead(notificationId) {
        fetch(`notification_manager.php?action=mark_as_read&notification_id=${notificationId}`)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    loadNotifications();
                }
            })
            .catch(error => console.error('Error marking notification as read:', error));
    }

    function updateNotificationBadge() {
        fetch('notification_manager.php?action=get_unread_count')
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const badge = document.querySelector('.notification-badge');
                    if (data.count > 0) {
                        if (!badge) {
                            const bell = document.querySelector('.notification-bell');
                            const newBadge = document.createElement('span');
                            newBadge.className = 'notification-badge';
                            newBadge.textContent = Math.min(data.count, 99);
                            bell.appendChild(newBadge);
                        } else {
                            badge.textContent = Math.min(data.count, 99);
                        }
                    } else if (badge) {
                        badge.remove();
                    }
                }
            })
            .catch(error => console.error('Error updating badge:', error));
    }

    function getIconForType(type) {
        const icons = {
            'success': 'check-circle',
            'warning': 'exclamation-triangle',
            'error': 'times-circle',
            'info': 'info-circle',
            'reminder': 'clock'
        };
        return icons[type] || 'bell';
    }

    function getTimeAgo(datetime) {
        const now = new Date();
        const date = new Date(datetime);
        const seconds = Math.floor((now - date) / 1000);

        if (seconds < 60) return 'just now';
        if (seconds < 3600) return Math.floor(seconds / 60) + 'm ago';
        if (seconds < 86400) return Math.floor(seconds / 3600) + 'h ago';
        if (seconds < 604800) return Math.floor(seconds / 86400) + 'd ago';

        return date.toLocaleDateString();
    }

    function escapeHtml(text) {
        const map = {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        };
        return text.replace(/[&<>"']/g, m => map[m]);
    }

    function viewAllNotifications() {
        window.location.href = 'notifications.php';
    }
</script>