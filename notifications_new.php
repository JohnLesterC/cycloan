<?php
/**
 * CYCLOAN Notifications Dashboard Page
 * Comprehensive notification management interface for users
 * 
 * @author CYCLOAN Development Team
 * @version 2.0
 * @created 2025-11-13
 */

session_start();
require_once 'CYCLOAN_db.php';
require_once 'NotificationManager.php';

// Check if user is authenticated
if (!isset($_SESSION['email']) || empty($_SESSION['email'])) {
    header("Location: index.php");
    exit();
}

// Get user info
$userEmail = $_SESSION['email'];
$userRole = $_SESSION['role'] ?? 'user';

// Get user ID
$stmt = $conn->prepare("SELECT user_id, first_name, last_name FROM users1 WHERE email = ?");
$stmt->bind_param("s", $userEmail);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    header("Location: index.php");
    exit();
}

$user = $result->fetch_assoc();
$userId = $user['user_id'];
$userName = trim($user['first_name'] . ' ' . $user['last_name']);
$stmt->close();

// Initialize NotificationManager
$notificationManager = new NotificationManager($conn);

// Get filters from request
$currentPage = (int)($_GET['page'] ?? 1);
$perPage = min((int)($_GET['per_page'] ?? 20), 50); // Max 50 per page
$typeFilter = $_GET['type'] ?? '';
$statusFilter = $_GET['status'] ?? 'all';
$searchQuery = $_GET['search'] ?? '';

// Build filters array
$filters = [];
if ($typeFilter) $filters['type'] = $typeFilter;
if ($statusFilter !== 'all') $filters['is_read'] = ($statusFilter === 'read');
if ($searchQuery) $filters['search'] = $searchQuery;

// Get notifications
$notificationsResult = $notificationManager->getUserNotifications($userId, $filters, $currentPage, $perPage);
$notifications = $notificationsResult['notifications'];
$totalPages = $notificationsResult['total_pages'];
$totalCount = $notificationsResult['total_count'];

// Get unread counts
$unreadCounts = $notificationManager->getUnreadCounts($userId);

// Get user settings
$userSettings = $notificationManager->getUserSettings($userId);

// Get notification types for filter dropdown
$typesQuery = "SELECT type_id, type_name, display_name FROM notification_types ORDER BY display_name";
$typesResult = $conn->query($typesQuery);
$notificationTypes = [];
while ($row = $typesResult->fetch_assoc()) {
    $notificationTypes[] = $row;
}

// Get user profile for header
try {
    $stmt = $conn->prepare("SELECT profile_image, first_name FROM users1 WHERE user_id = ?");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    $userProfile = $result->fetch_assoc();
    $profile_image = !empty($userProfile['profile_image']) ? $userProfile['profile_image'] : 'IMAGE/default.jpg';
    $first_name = $userProfile['first_name'] ?? 'User';
    $stmt->close();
} catch (Exception $e) {
    $profile_image = 'IMAGE/default.jpg';
    $first_name = 'User';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notifications - CYCLOAN</title>
    <link rel="stylesheet" href="CSS/notifications.css">
    <link rel="stylesheet" href="CSS/user_dashboard.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .notifications-page {
            background: #f8f9fa;
            min-height: 100vh;
            padding: 2rem 0;
        }

        .notifications-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 1rem;
        }

        .page-header {
            background: linear-gradient(135deg, #1976d2 0%, #1565c0 100%);
            color: white;
            padding: 2rem;
            border-radius: 12px;
            margin-bottom: 2rem;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
        }

        .header-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 1rem;
        }

        .page-title {
            font-size: 2rem;
            font-weight: 600;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .page-subtitle {
            opacity: 0.9;
            font-size: 1.1rem;
            margin: 0.5rem 0 0 0;
        }

        .header-actions {
            display: flex;
            gap: 1rem;
            flex-wrap: wrap;
        }

        .btn {
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 0.9rem;
            font-weight: 500;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            transition: all 0.3s ease;
        }

        .btn-primary {
            background: rgba(255,255,255,0.2);
            color: white;
            border: 1px solid rgba(255,255,255,0.3);
        }

        .btn-primary:hover {
            background: rgba(255,255,255,0.3);
            transform: translateY(-2px);
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: white;
            padding: 1.5rem;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
            text-align: center;
            transition: transform 0.3s ease;
        }

        .stat-card:hover {
            transform: translateY(-5px);
        }

        .stat-number {
            font-size: 2rem;
            font-weight: bold;
            color: #1976d2;
            margin-bottom: 0.5rem;
        }

        .stat-label {
            color: #666;
            font-size: 0.9rem;
        }

        .controls-panel {
            background: white;
            padding: 1.5rem;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
            margin-bottom: 2rem;
        }

        .filters-row {
            display: grid;
            grid-template-columns: 1fr 1fr 2fr auto;
            gap: 1rem;
            align-items: end;
            margin-bottom: 1rem;
        }

        .form-group {
            display: flex;
            flex-direction: column;
        }

        .form-label {
            font-weight: 500;
            margin-bottom: 0.5rem;
            color: #333;
        }

        .form-control {
            padding: 0.75rem;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 1rem;
            transition: border-color 0.3s ease;
        }

        .form-control:focus {
            outline: none;
            border-color: #1976d2;
            box-shadow: 0 0 0 3px rgba(25, 118, 210, 0.1);
        }

        .search-container {
            position: relative;
        }

        .search-icon {
            position: absolute;
            left: 0.75rem;
            top: 50%;
            transform: translateY(-50%);
            color: #999;
        }

        .search-input {
            padding-left: 2.5rem;
        }

        .bulk-actions {
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
        }

        .btn-success {
            background: #28a745;
            color: white;
        }

        .btn-success:hover {
            background: #218838;
        }

        .btn-danger {
            background: #dc3545;
            color: white;
        }

        .btn-danger:hover {
            background: #c82333;
        }

        .btn-secondary {
            background: #6c757d;
            color: white;
        }

        .btn-secondary:hover {
            background: #5a6268;
        }

        .notifications-list {
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
            overflow: hidden;
        }

        .list-header {
            background: #f8f9fa;
            padding: 1rem 1.5rem;
            border-bottom: 1px solid #e9ecef;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .list-title {
            font-weight: 600;
            font-size: 1.1rem;
            color: #333;
        }

        .select-all-container {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .notifications-empty {
            text-align: center;
            padding: 4rem 2rem;
            color: #666;
        }

        .empty-icon {
            font-size: 4rem;
            margin-bottom: 1rem;
            opacity: 0.5;
        }

        .empty-title {
            font-size: 1.5rem;
            font-weight: 600;
            margin-bottom: 0.5rem;
        }

        .empty-message {
            margin-bottom: 2rem;
        }

        .pagination {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 0.5rem;
            margin-top: 2rem;
            flex-wrap: wrap;
        }

        .pagination-btn {
            padding: 0.5rem 1rem;
            border: 1px solid #ddd;
            background: white;
            color: #333;
            text-decoration: none;
            border-radius: 6px;
            transition: all 0.3s ease;
        }

        .pagination-btn:hover:not(.disabled) {
            background: #1976d2;
            color: white;
            border-color: #1976d2;
        }

        .pagination-btn.active {
            background: #1976d2;
            color: white;
            border-color: #1976d2;
        }

        .pagination-btn.disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        .pagination-info {
            color: #666;
            font-size: 0.9rem;
            margin: 0 1rem;
        }

        @media (max-width: 768px) {
            .filters-row {
                grid-template-columns: 1fr;
                gap: 1rem;
            }

            .header-content {
                flex-direction: column;
                align-items: flex-start;
            }

            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }

            .pagination {
                justify-content: flex-start;
            }
        }
    </style>
</head>
<body data-user-id="<?php echo htmlspecialchars($userId); ?>" data-user-role="<?php echo htmlspecialchars($userRole); ?>">
    <!-- Header -->
    <div class="header">
        <div class="profileXdate">
            <div id="datetime" class="datetime"></div>
            <div class="profile-container" onclick="toggleDropdown(event)">
                <img src="<?= htmlspecialchars($profile_image) ?>" alt="Profile Image" class="profile">
                <div class="dropdown-menu" id="dropdown">
                    <ul>
                        <li>
                            <a href="profile.php">
                                <img src="<?= htmlspecialchars($profile_image) ?>" alt="Profile Image" class="profile-icon"> Profile
                            </a>
                        </li>
                        <li><a class="logout" href="index.php"><i class="fa-solid fa-sign-out"></i> Logout</a></li>
                    </ul>
                </div>
            </div>
        </div>
    </div>

    <div class="notifications-page">
        <div class="notifications-container">
            <!-- Page Header -->
            <div class="page-header">
                <div class="header-content">
                    <div>
                        <h1 class="page-title">
                            <i class="fas fa-bell"></i>
                            Notifications
                        </h1>
                        <p class="page-subtitle">Manage your notifications and stay updated with your loan activities</p>
                    </div>
                    <div class="header-actions">
                        <a href="user_dashboard.php" class="btn btn-primary">
                            <i class="fas fa-arrow-left"></i> Back to Dashboard
                        </a>
                    </div>
                </div>
            </div>

            <!-- Statistics Cards -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-number"><?php echo $totalCount; ?></div>
                    <div class="stat-label">Total Notifications</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?php echo $unreadCounts['total']; ?></div>
                    <div class="stat-label">Unread</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?php echo $unreadCounts['payment'] ?? 0; ?></div>
                    <div class="stat-label">Payment Updates</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?php echo $unreadCounts['status'] ?? 0; ?></div>
                    <div class="stat-label">Status Updates</div>
                </div>
            </div>

            <!-- Controls Panel -->
            <div class="controls-panel">
                <form method="GET" action="notifications_new.php" id="filterForm">
                    <div class="filters-row">
                        <div class="form-group">
                            <label for="type" class="form-label">Filter by Type</label>
                            <select name="type" id="type" class="form-control" onchange="submitForm()">
                                <option value="">All Types</option>
                                <?php foreach ($notificationTypes as $type): ?>
                                <option value="<?php echo htmlspecialchars($type['type_name']); ?>" 
                                        <?php echo $typeFilter === $type['type_name'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($type['display_name']); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="status" class="form-label">Filter by Status</label>
                            <select name="status" id="status" class="form-control" onchange="submitForm()">
                                <option value="all" <?php echo $statusFilter === 'all' ? 'selected' : ''; ?>>All</option>
                                <option value="unread" <?php echo $statusFilter === 'unread' ? 'selected' : ''; ?>>Unread</option>
                                <option value="read" <?php echo $statusFilter === 'read' ? 'selected' : ''; ?>>Read</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="search" class="form-label">Search Notifications</label>
                            <div class="search-container">
                                <i class="fas fa-search search-icon"></i>
                                <input type="text" name="search" id="search" class="form-control search-input" 
                                       placeholder="Search notifications..." 
                                       value="<?php echo htmlspecialchars($searchQuery); ?>">
                            </div>
                        </div>
                        <div class="bulk-actions">
                            <button type="button" class="btn btn-success" onclick="markSelectedAsRead()">
                                <i class="fas fa-check"></i> Mark Read
                            </button>
                            <button type="button" class="btn btn-danger" onclick="deleteSelected()">
                                <i class="fas fa-trash"></i> Delete
                            </button>
                        </div>
                    </div>
                </form>
            </div>

            <!-- Notifications List -->
            <div class="notifications-list">
                <div class="list-header">
                    <div class="list-title">
                        Notifications 
                        <?php if ($totalCount > 0): ?>
                        (<?php echo $totalCount; ?> total, <?php echo $unreadCounts['total']; ?> unread)
                        <?php endif; ?>
                    </div>
                    <?php if (!empty($notifications)): ?>
                    <div class="select-all-container">
                        <input type="checkbox" id="selectAll" class="checkbox" onchange="toggleSelectAll()">
                        <label for="selectAll">Select All</label>
                    </div>
                    <?php endif; ?>
                </div>

                <div class="notification-list-container">
                    <?php if (empty($notifications)): ?>
                    <div class="notifications-empty">
                        <i class="fas fa-bell-slash empty-icon"></i>
                        <h3 class="empty-title">No Notifications Found</h3>
                        <p class="empty-message">
                            <?php if ($searchQuery || $typeFilter || $statusFilter !== 'all'): ?>
                            No notifications match your current filters. Try adjusting your search criteria.
                            <?php else: ?>
                            You're all caught up! No notifications to display.
                            <?php endif; ?>
                        </p>
                        <a href="user_dashboard.php" class="btn btn-primary">
                            <i class="fas fa-home"></i> Go to Dashboard
                        </a>
                    </div>
                    <?php else: ?>
                    <?php foreach ($notifications as $notification): ?>
                    <?php
                    $isUnread = !$notification['is_read'];
                    $priorityClass = $notification['priority'] ?? 'normal';
                    $timeAgo = getTimeAgo($notification['created_at']);
                    ?>
                    <div class="notification-item <?php echo $isUnread ? 'unread' : ''; ?> <?php echo $priorityClass; ?>"
                         data-id="<?php echo $notification['notification_id']; ?>">
                        <div class="notification-checkbox">
                            <input type="checkbox" class="checkbox notification-checkbox-input" 
                                   value="<?php echo $notification['notification_id']; ?>">
                        </div>
                        <div class="notification-icon <?php echo $notification['color_class'] ?? 'primary'; ?>">
                            <i class="<?php echo $notification['icon_class'] ?? 'fa-bell'; ?>"></i>
                        </div>
                        <div class="notification-content">
                            <div class="notification-title"><?php echo htmlspecialchars($notification['title']); ?></div>
                            <div class="notification-message"><?php echo htmlspecialchars($notification['short_message'] ?? $notification['message']); ?></div>
                            <div class="notification-meta">
                                <span class="notification-time">
                                    <i class="fas fa-clock"></i> <?php echo $timeAgo; ?>
                                </span>
                                <span class="notification-type">
                                    <i class="fas fa-tag"></i> <?php echo htmlspecialchars($notification['type_display_name']); ?>
                                </span>
                                <?php if ($notification['priority'] && $notification['priority'] !== 'normal'): ?>
                                <span class="notification-priority priority-<?php echo $notification['priority']; ?>">
                                    <i class="fas fa-exclamation-triangle"></i> <?php echo ucfirst($notification['priority']); ?>
                                </span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="notification-actions-item">
                            <?php if ($isUnread): ?>
                            <button onclick="markAsRead('<?php echo $notification['notification_id']; ?>')" 
                                    title="Mark as read" class="btn-icon">
                                <i class="fas fa-check"></i>
                            </button>
                            <?php endif; ?>
                            <?php if ($notification['action_url'] && $notification['action_url'] !== '#'): ?>
                            <button onclick="viewNotification('<?php echo $notification['notification_id']; ?>', '<?php echo htmlspecialchars($notification['action_url']); ?>')" 
                                    title="View details" class="btn-icon">
                                <i class="fas fa-eye"></i>
                            </button>
                            <?php endif; ?>
                            <button onclick="deleteNotification('<?php echo $notification['notification_id']; ?>')" 
                                    title="Delete" class="btn-icon btn-danger">
                                <i class="fas fa-trash"></i>
                            </button>
                        </div>
                    </div>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Pagination -->
            <?php if ($totalPages > 1): ?>
            <div class="pagination">
                <div class="pagination-info">
                    Showing <?php echo (($currentPage - 1) * $perPage + 1); ?> to 
                    <?php echo min($currentPage * $perPage, $totalCount); ?> of <?php echo $totalCount; ?> notifications
                </div>
                
                <?php if ($currentPage > 1): ?>
                <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $currentPage - 1])); ?>" 
                   class="pagination-btn">
                    <i class="fas fa-chevron-left"></i> Previous
                </a>
                <?php endif; ?>
                
                <?php
                $startPage = max(1, $currentPage - 2);
                $endPage = min($totalPages, $currentPage + 2);
                for ($i = $startPage; $i <= $endPage; $i++):
                ?>
                <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>" 
                   class="pagination-btn <?php echo $i === $currentPage ? 'active' : ''; ?>">
                    <?php echo $i; ?>
                </a>
                <?php endfor; ?>
                
                <?php if ($currentPage < $totalPages): ?>
                <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $currentPage + 1])); ?>" 
                   class="pagination-btn">
                    Next <i class="fas fa-chevron-right"></i>
                </a>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Toast Container -->
    <div id="toastContainer" class="toast-container"></div>

    <script src="JAVASCRIPT/notifications.js"></script>
    <script>
        // Date and time update
        function updateDateTime() {
            const now = new Date();
            const options = {
                weekday: 'short',
                year: 'numeric',
                month: 'short',
                day: 'numeric',
                hour: '2-digit',
                minute: '2-digit'
            };
            document.getElementById('datetime').textContent = now.toLocaleDateString('en-US', options);
        }

        updateDateTime();
        setInterval(updateDateTime, 60000);

        // Toggle profile dropdown
        function toggleDropdown(event) {
            event.stopPropagation();
            document.getElementById('dropdown').style.display =
                document.getElementById('dropdown').style.display === 'block' ? 'none' : 'block';
        }

        document.addEventListener('click', function (event) {
            const dropdown = document.getElementById('dropdown');
            if (!event.target.closest('.profile-container')) {
                dropdown.style.display = 'none';
            }
        });

        function submitForm() {
            document.getElementById('filterForm').submit();
        }
        
        function toggleSelectAll() {
            const selectAll = document.getElementById('selectAll');
            const checkboxes = document.querySelectorAll('.notification-checkbox-input');
            checkboxes.forEach(cb => cb.checked = selectAll.checked);
        }
        
        function markSelectedAsRead() {
            const selected = getSelectedNotifications();
            if (selected.length === 0) {
                showToast('No notifications selected', 'Please select notifications to mark as read', 'warning');
                return;
            }
            
            fetch('api/notifications.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    action: 'mark_read',
                    notification_ids: selected
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showToast('Success', `${selected.length} notifications marked as read`, 'success');
                    setTimeout(() => location.reload(), 1000);
                } else {
                    showToast('Error', data.message || 'Failed to mark notifications as read', 'error');
                }
            })
            .catch(error => {
                showToast('Error', 'Failed to mark notifications as read', 'error');
                console.error('Error:', error);
            });
        }
        
        function deleteSelected() {
            const selected = getSelectedNotifications();
            if (selected.length === 0) {
                showToast('No notifications selected', 'Please select notifications to delete', 'warning');
                return;
            }
            
            if (!confirm(`Are you sure you want to delete ${selected.length} notification(s)? This action cannot be undone.`)) {
                return;
            }
            
            fetch('api/notifications.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    action: 'delete',
                    notification_ids: selected
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showToast('Success', `${selected.length} notifications deleted`, 'success');
                    setTimeout(() => location.reload(), 1000);
                } else {
                    showToast('Error', data.message || 'Failed to delete notifications', 'error');
                }
            })
            .catch(error => {
                showToast('Error', 'Failed to delete notifications', 'error');
                console.error('Error:', error);
            });
        }
        
        function getSelectedNotifications() {
            const checkboxes = document.querySelectorAll('.notification-checkbox-input:checked');
            return Array.from(checkboxes).map(cb => cb.value);
        }
        
        function markAsRead(notificationId) {
            fetch('api/notifications.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    action: 'mark_read',
                    notification_ids: [notificationId]
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showToast('Success', 'Notification marked as read', 'success');
                    const item = document.querySelector(`[data-id="${notificationId}"]`);
                    if (item) {
                        item.classList.remove('unread');
                        const button = item.querySelector('.notification-actions-item .btn-icon:first-child');
                        if (button) button.remove();
                    }
                } else {
                    showToast('Error', data.message || 'Failed to mark notification as read', 'error');
                }
            })
            .catch(error => {
                showToast('Error', 'Failed to mark notification as read', 'error');
                console.error('Error:', error);
            });
        }
        
        function viewNotification(notificationId, actionUrl) {
            markAsRead(notificationId);
            setTimeout(() => {
                window.location.href = actionUrl;
            }, 200);
        }
        
        function deleteNotification(notificationId) {
            if (!confirm('Are you sure you want to delete this notification? This action cannot be undone.')) {
                return;
            }
            
            fetch('api/notifications.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    action: 'delete',
                    notification_ids: [notificationId]
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showToast('Success', 'Notification deleted', 'success');
                    const item = document.querySelector(`[data-id="${notificationId}"]`);
                    if (item) {
                        item.style.opacity = '0';
                        setTimeout(() => item.remove(), 300);
                    }
                } else {
                    showToast('Error', data.message || 'Failed to delete notification', 'error');
                }
            })
            .catch(error => {
                showToast('Error', 'Failed to delete notification', 'error');
                console.error('Error:', error);
            });
        }
        
        function showToast(title, message, type, duration = 4000) {
            if (typeof notificationSystem !== 'undefined') {
                notificationSystem.showToast(title, message, type, duration);
            } else {
                // Fallback alert if notification system not loaded
                alert(`${title}: ${message}`);
            }
        }
        
        // Search functionality
        document.getElementById('search').addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                submitForm();
            }
        });
    </script>
</body>
</html>

<?php
/**
 * Helper function to format time ago
 */
function getTimeAgo($timestamp) {
    $now = new DateTime();
    $time = new DateTime($timestamp);
    $diffInSeconds = $now->getTimestamp() - $time->getTimestamp();
    
    if ($diffInSeconds < 60) {
        return 'Just now';
    } elseif ($diffInSeconds < 3600) {
        $minutes = floor($diffInSeconds / 60);
        return $minutes . ' min' . ($minutes > 1 ? 's' : '') . ' ago';
    } elseif ($diffInSeconds < 86400) {
        $hours = floor($diffInSeconds / 3600);
        return $hours . ' hr' . ($hours > 1 ? 's' : '') . ' ago';
    } elseif ($diffInSeconds < 604800) {
        $days = floor($diffInSeconds / 86400);
        return $days . ' day' . ($days > 1 ? 's' : '') . ' ago';
    } else {
        return $time->format('M j, Y');
    }
}
?>