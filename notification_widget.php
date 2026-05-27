<?php
/**
 * CYCLOAN Notification Widget Component
 * 
 * Reusable notification bell icon with dropdown widget
 * Include this file at the top of any admin page to display the notification widget
 * 
 * Usage:
 * <?php 
 *     require_once 'notification_widget.php';
 *     echo renderNotificationWidget($conn, $_SESSION['user_id'], $_SESSION['role']);
 * ?>
 * 
 * Features:
 * - Clickable bell icon with unread count badge
 * - Dropdown preview of recent notifications (5 most recent)
 * - Mark as read / Dismiss options
 * - Link to full notification center
 * - Real-time unread count updates
 * - Responsive design
 * - Accessibility support (ARIA labels)
 */

session_start();

require_once 'NotificationManager.php';

/**
 * Render the notification widget
 * 
 * @param mysqli $conn Database connection
 * @param int $user_id Current user ID
 * @param string $user_role Current user role (admin1, admin2, superadmin, user)
 * @return string HTML for notification widget
 */
function renderNotificationWidget($conn, $user_id, $user_role = 'user')
{
    if (!$user_id || !$conn) {
        return '';
    }

    try {
        $notificationManager = new NotificationManager($conn);
        $unreadCountData = $notificationManager->getUnreadCounts($user_id);
        $unreadCount = isset($unreadCountData['unread_count']) ? $unreadCountData['unread_count'] : 0;
        $recentNotifications = $notificationManager->getRecentNotifications($user_id, 5);

        // Generate unique widget ID to support multiple widgets on same page
        $widget_id = 'notification_widget_' . uniqid();
        $dropdown_id = 'notification_dropdown_' . uniqid();

        ob_start();
        ?>
        <!-- Notification Widget Container -->
        <div class="notification-widget-container" id="<?php echo $widget_id; ?>" role="region" aria-label="Notifications">

            <!-- Notification Bell Icon -->
            <button class="notification-bell-btn" onclick="toggleNotificationDropdown(event, '<?php echo $dropdown_id; ?>')"
                title="View notifications" aria-label="Notifications" aria-expanded="false"
                aria-controls="<?php echo $dropdown_id; ?>">
                <i class="fas fa-bell notification-bell-icon"></i>

                <!-- Unread Badge -->
                <?php if ($unreadCount > 0): ?>
                    <span class="notification-badge" data-count="<?php echo $unreadCount; ?>"
                        aria-label="<?php echo $unreadCount; ?> unread notifications">
                        <?php echo min($unreadCount, 99); ?>
                    </span>
                <?php endif; ?>
            </button>

            <!-- Notification Dropdown -->
            <div class="notification-dropdown" id="<?php echo $dropdown_id; ?>" role="menu" aria-hidden="true">
                <!-- Header -->
                <div class="notification-dropdown-header">
                    <h3 class="notification-dropdown-title">
                        <i class="fas fa-bell"></i> Notifications
                    </h3>
                    <button class="notification-close-btn" onclick="closeNotificationDropdown('<?php echo $dropdown_id; ?>')"
                        aria-label="Close notifications" title="Close">
                        <i class="fas fa-times"></i>
                    </button>
                </div>

                <!-- Notifications List -->
                <div class="notification-dropdown-list">
                    <?php if (empty($recentNotifications)): ?>
                        <div class="notification-empty-state">
                            <i class="fas fa-inbox"></i>
                            <p>No notifications yet</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($recentNotifications as $notification): ?>
                            <div class="notification-dropdown-item <?php echo !$notification['is_read'] ? 'unread' : 'read'; ?>"
                                data-notification-id="<?php echo $notification['notification_id']; ?>" role="menuitem">

                                <!-- Icon -->
                                <div class="notification-item-icon <?php echo $notification['color_class'] ?? 'info'; ?>">
                                    <i class="<?php echo $notification['icon_class'] ?? 'fas fa-info-circle'; ?>"></i>
                                </div>

                                <!-- Content -->
                                <div class="notification-item-content">
                                    <div class="notification-item-header">
                                        <h4 class="notification-item-title">
                                            <?php echo htmlspecialchars($notification['title']); ?>
                                        </h4>
                                        <span class="notification-item-type">
                                            <?php echo $notification['type_display_name'] ?? 'Notification'; ?>
                                        </span>
                                    </div>
                                    <p class="notification-item-message">
                                        <?php echo htmlspecialchars(substr($notification['message'], 0, 80)); ?>
                                        <?php if (strlen($notification['message']) > 80): ?>...<?php endif; ?>
                                    </p>
                                    <span class="notification-item-time">
                                        <?php echo formatTimeAgo($notification['created_at']); ?>
                                    </span>
                                </div>

                                <!-- Actions -->
                                <div class="notification-item-actions">
                                    <?php if (!$notification['is_read']): ?>
                                        <button class="action-btn mark-read-btn"
                                            onclick="markNotificationRead(<?php echo $notification['notification_id']; ?>, '<?php echo $widget_id; ?>')"
                                            title="Mark as read" aria-label="Mark as read">
                                            <i class="fas fa-check"></i>
                                        </button>
                                    <?php endif; ?>
                                    <button class="action-btn delete-btn"
                                        onclick="deleteNotification(<?php echo $notification['notification_id']; ?>, '<?php echo $widget_id; ?>')"
                                        title="Delete" aria-label="Delete notification">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                <!-- Footer -->
                <div class="notification-dropdown-footer">
                    <a href="notifications_enhanced.php" class="view-all-link" role="menuitem">
                        <i class="fas fa-list"></i> View All Notifications
                    </a>
                </div>
            </div>

            <!-- Overlay for closing dropdown -->
            <div class="notification-overlay" id="overlay_<?php echo $dropdown_id; ?>"
                onclick="closeNotificationDropdown('<?php echo $dropdown_id; ?>')"></div>
        </div>

        <!-- Notification Widget JavaScript -->
        <script>
            // Toggle dropdown visibility
            function toggleNotificationDropdown(event, dropdownId) {
                event.stopPropagation();
                const dropdown = document.getElementById(dropdownId);
                const button = event.currentTarget;
                const isOpen = dropdown.classList.contains('active');

                if (isOpen) {
                    closeNotificationDropdown(dropdownId);
                } else {
                    dropdown.classList.add('active');
                    document.getElementById('overlay_' + dropdownId).classList.add('active');
                    button.setAttribute('aria-expanded', 'true');
                    dropdown.setAttribute('aria-hidden', 'false');
                    loadRecentNotifications(dropdownId);
                }
            }

            // Close dropdown
            function closeNotificationDropdown(dropdownId) {
                const dropdown = document.getElementById(dropdownId);
                const overlay = document.getElementById('overlay_' + dropdownId);
                const button = dropdown.previousElementSibling;

                dropdown.classList.remove('active');
                overlay.classList.remove('active');
                if (button) {
                    button.setAttribute('aria-expanded', 'false');
                }
                dropdown.setAttribute('aria-hidden', 'true');
            }

            // Load recent notifications via AJAX
            function loadRecentNotifications(dropdownId) {
                const url = 'notification_api.php?action=get_recent&limit=5&timestamp=' + new Date().getTime();

                fetch(url)
                    .then(response => response.json())
                    .then(data => {
                        if (data.success && data.notifications) {
                            updateNotificationDropdown(dropdownId, data.notifications);
                        }
                    })
                    .catch(error => console.error('Error loading notifications:', error));
            }

            // Update dropdown with latest notifications
            function updateNotificationDropdown(dropdownId, notifications) {
                const list = document.querySelector('#' + dropdownId + ' .notification-dropdown-list');

                if (!list) return;

                if (notifications.length === 0) {
                    list.innerHTML = `
                        <div class="notification-empty-state">
                            <i class="fas fa-inbox"></i>
                            <p>No notifications yet</p>
                        </div>
                    `;
                    return;
                }

                list.innerHTML = notifications.map(notif => `
                    <div class="notification-dropdown-item ${notif.is_read ? 'read' : 'unread'}" 
                         data-notification-id="${notif.notification_id}" role="menuitem">
                        <div class="notification-item-icon ${notif.color_class || 'info'}">
                            <i class="${notif.icon_class || 'fas fa-info-circle'}"></i>
                        </div>
                        <div class="notification-item-content">
                            <div class="notification-item-header">
                                <h4 class="notification-item-title">${escapeHtml(notif.title)}</h4>
                                <span class="notification-item-type">${notif.type_display_name || 'Notification'}</span>
                            </div>
                            <p class="notification-item-message">
                                ${escapeHtml(notif.message.substring(0, 80))}${notif.message.length > 80 ? '...' : ''}
                            </p>
                            <span class="notification-item-time">${formatTimeAgoJS(notif.created_at)}</span>
                        </div>
                        <div class="notification-item-actions">
                            ${!notif.is_read ? `
                                <button class="action-btn mark-read-btn" 
                                    onclick="markNotificationRead(${notif.notification_id}, '${dropdownId.replace(/[^a-zA-Z0-9_]/g, '')}')"
                                    title="Mark as read" aria-label="Mark as read">
                                    <i class="fas fa-check"></i>
                                </button>
                            ` : ''}
                            <button class="action-btn delete-btn" 
                                onclick="deleteNotification(${notif.notification_id}, '${dropdownId.replace(/[^a-zA-Z0-9_]/g, '')}')"
                                title="Delete" aria-label="Delete notification">
                                <i class="fas fa-trash"></i>
                            </button>
                        </div>
                    </div>
                `).join('');
            }

            // Mark notification as read
            function markNotificationRead(notificationId, widgetId) {
                fetch('notification_api.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded'
                    },
                    body: 'action=mark_read&notification_id=' + notificationId
                })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            updateNotificationBadge(widgetId);
                            location.reload();
                        }
                    })
                    .catch(error => console.error('Error:', error));
            }

            // Delete notification
            function deleteNotification(notificationId, widgetId) {
                if (!confirm('Delete this notification?')) return;

                fetch('notification_api.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded'
                    },
                    body: 'action=delete&notification_id=' + notificationId
                })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            updateNotificationBadge(widgetId);
                            location.reload();
                        }
                    })
                    .catch(error => console.error('Error:', error));
            }

            // Update badge count
            function updateNotificationBadge(widgetId) {
                fetch('notification_api.php?action=get_unread_count&timestamp=' + new Date().getTime())
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            const badge = document.querySelector('#' + widgetId + ' .notification-badge');
                            if (data.count > 0) {
                                if (badge) {
                                    badge.textContent = Math.min(data.count, 99);
                                    badge.setAttribute('data-count', data.count);
                                    badge.setAttribute('aria-label', data.count + ' unread notifications');
                                }
                            } else if (badge) {
                                badge.remove();
                            }
                        }
                    })
                    .catch(error => console.error('Error updating badge:', error));
            }

            // Helper: Escape HTML
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

            // Helper: Format time ago (JavaScript)
            function formatTimeAgoJS(timestamp) {
                const now = new Date();
                const time = new Date(timestamp);
                const diff = now - time;
                const seconds = Math.floor(diff / 1000);

                if (seconds < 60) return 'Just now';
                if (seconds < 3600) return Math.floor(seconds / 60) + 'm ago';
                if (seconds < 86400) return Math.floor(seconds / 3600) + 'h ago';
                if (seconds < 604800) return Math.floor(seconds / 86400) + 'd ago';

                return time.toLocaleDateString();
            }

            // Close dropdown when clicking outside
            document.addEventListener('click', function (e) {
                const widgets = document.querySelectorAll('.notification-widget-container');
                widgets.forEach(widget => {
                    const dropdowns = widget.querySelectorAll('.notification-dropdown.active');
                    dropdowns.forEach(dropdown => {
                        if (!widget.contains(e.target)) {
                            const id = dropdown.id;
                            closeNotificationDropdown(id);
                        }
                    });
                });
            });

            // Auto-refresh unread count every 30 seconds
            setInterval(function () {
                const widgets = document.querySelectorAll('.notification-widget-container');
                widgets.forEach(widget => {
                    updateNotificationBadge(widget.id);
                });
            }, 30000);
        </script>

        <?php
        return ob_get_clean();
    } catch (Exception $e) {
        error_log("Error rendering notification widget: " . $e->getMessage());
        return '';
    }
}

/**
 * Helper function to format time ago
 */
function formatTimeAgo($timestamp)
{
    $time = strtotime($timestamp);
    $time_diff = time() - $time;

    if ($time_diff < 60)
        return 'Just now';
    if ($time_diff < 3600)
        return round($time_diff / 60) . 'm ago';
    if ($time_diff < 86400)
        return round($time_diff / 3600) . 'h ago';
    if ($time_diff < 604800)
        return round($time_diff / 86400) . 'd ago';

    return date('M j, Y', $time);
}

?>