<?php
/**
 * CYCLOAN Notifications Dashboard Page
 * Enhanced notification management interface matching user dashboard design
 * 
 * @author CYCLOAN Development Team
 * @version 3.0 - Enhanced UI/UX
 * @created 2025-11-13
 */

session_start();
require_once 'CYCLOAN_db.php';
require_once 'NotificationManager.php';
require_once 'timezone_config.php';

// Centralized debug logging function
function debugLog($message, $userId = null)
{
    $timestamp = date('Y-m-d H:i:s');
    $userContext = $userId ? "User ID: $userId | " : "";
    $logMessage = "[$timestamp] $userContext$message\n";
    error_log($logMessage, 3, 'debug.log');
}

// Check if the user is logged in
if (!isset($_SESSION['email']) || !isset($_SESSION['user_id'])) {
    debugLog("Unauthorized access attempt to notifications", null);
    $_SESSION['error'] = "Please log in to access notifications.";
    header("Location: index.php");
    exit();
}

$userId = $_SESSION['user_id'];
$userEmail = $_SESSION['email'];
debugLog("Notifications page accessed", $userId);

// Ensure global $conn is a valid MySQLi object
global $conn;
if (!($conn instanceof mysqli)) {
    debugLog("Connection failed: MySQLi connection not initialized", $userId);
    $_SESSION['error'] = 'Connection failed. Please try again later.';
    header('Location: user_dashboard.php');
    exit;
}

// Get user info with profile image
try {
    $stmt = $conn->prepare("SELECT id as user_id, first_name, last_name, profile_image FROM users1 WHERE email = ?");
    if ($stmt === false) {
        throw new Exception("Prepare failed: " . $conn->error);
    }
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
    $profile_image = !empty($user['profile_image']) ? $user['profile_image'] : '/assets/default.jpg';
    $stmt->close();
    debugLog("User data fetched successfully", $userId);
} catch (Exception $e) {
    debugLog("User data fetch error: " . $e->getMessage(), $userId);
    $_SESSION['error'] = 'Failed to fetch user information.';
    header('Location: user_dashboard.php');
    exit;
}

// Get current page for active navigation highlighting
$current_page = basename($_SERVER['PHP_SELF']);

// Initialize NotificationManager
$notificationManager = new NotificationManager($conn);

// Get filters from request
$currentPage = (int) ($_GET['page'] ?? 1);
$perPage = min((int) ($_GET['per_page'] ?? 20), 50); // Max 50 per page
$typeFilter = $_GET['type'] ?? '';
$statusFilter = $_GET['status'] ?? 'all';
$searchQuery = $_GET['search'] ?? '';

// Build filters array
$filters = [];
if (!empty($typeFilter)) {
    $filters['type'] = $typeFilter;
}
if ($statusFilter === 'unread') {
    $filters['is_read'] = false;
} elseif ($statusFilter === 'read') {
    $filters['is_read'] = true;
}
if (!empty($searchQuery)) {
    $filters['search'] = $searchQuery;
}

// Handle POST actions
$successMessage = '';
$errorMessage = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    try {
        switch ($action) {
            case 'mark_read':
                $notificationIds = $_POST['notification_ids'] ?? [];
                if (!empty($notificationIds)) {
                    $result = $notificationManager->markAsRead($userId, $notificationIds);
                    if ($result) {
                        $successMessage = "Selected notifications marked as read.";
                    } else {
                        $errorMessage = "Failed to mark notifications as read.";
                    }
                }
                break;

            case 'delete':
                $notificationIds = $_POST['notification_ids'] ?? [];
                if (!empty($notificationIds)) {
                    $result = $notificationManager->deleteNotifications($userId, $notificationIds);
                    if ($result) {
                        $successMessage = "Selected notifications deleted.";
                    } else {
                        $errorMessage = "Failed to delete notifications.";
                    }
                }
                break;

            case 'mark_all_read':
                $result = $notificationManager->markAllAsRead($userId);
                if ($result) {
                    $successMessage = "All notifications marked as read.";
                } else {
                    $errorMessage = "Failed to mark all notifications as read.";
                }
                break;
        }
    } catch (Exception $e) {
        $errorMessage = "Error processing action: " . $e->getMessage();
    }

    // Redirect to prevent form resubmission
    $redirectUrl = $_SERVER['REQUEST_URI'];
    if ($successMessage) {
        $_SESSION['notification_success'] = $successMessage;
    }
    if ($errorMessage) {
        $_SESSION['notification_error'] = $errorMessage;
    }
    header("Location: $redirectUrl");
    exit();
}

// Check for session messages
if (isset($_SESSION['notification_success'])) {
    $successMessage = $_SESSION['notification_success'];
    unset($_SESSION['notification_success']);
}
if (isset($_SESSION['notification_error'])) {
    $errorMessage = $_SESSION['notification_error'];
    unset($_SESSION['notification_error']);
}

// Get notifications
$notificationsResult = $notificationManager->getUserNotifications($userId, $filters, $currentPage, $perPage);
$notifications = $notificationsResult['notifications'];
$totalCount = $notificationsResult['total_count'];
$totalPages = ceil($totalCount / $perPage);

// Get statistics
$unreadCountsResult = $notificationManager->getUnreadCounts($userId);
$totalUnread = $unreadCountsResult['total'];
$typeUnreadCounts = isset($unreadCountsResult['by_type']) ? $unreadCountsResult['by_type'] : [];

// End of data processing section

// If we get here, everything loaded successfully
ob_end_flush();
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notifications - CYCLOAN</title>
    <link rel="stylesheet" href="CSS/notifications.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
</head>

<body>
    <div class="notifications-container">
        <!-- Header -->
        <div class="notifications-header">
            <div class="header-left">
                <h1><i class="fas fa-bell"></i> Notifications</h1>
                <p class="welcome-text">Welcome back, <?php echo htmlspecialchars($userName); ?>!</p>
            </div>
            <div class="header-right">
                <a href="user_dashboard.php" class="btn btn-secondary">
                    <i class="fas fa-arrow-left"></i> Back to Dashboard
                </a>
            </div>
        </div>

        <!-- Success/Error Messages -->
        <?php if ($successMessage): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i>
                <?php echo htmlspecialchars($successMessage); ?>
            </div>
        <?php endif; ?>

        <?php if ($errorMessage): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-circle"></i>
                <?php echo htmlspecialchars($errorMessage); ?>
            </div>
        <?php endif; ?>

        <!-- Stats Cards -->
        <div class="stats-grid">
            <div class="stat-card total">
                <div class="stat-icon">
                    <i class="fas fa-envelope"></i>
                </div>
                <div class="stat-content">
                    <div class="stat-number"><?php echo number_format($totalCount); ?></div>
                    <div class="stat-label">Total Notifications</div>
                </div>
            </div>
            <div class="stat-card unread">
                <div class="stat-icon">
                    <i class="fas fa-envelope-open"></i>
                </div>
                <div class="stat-content">
                    <div class="stat-number"><?php echo number_format($totalUnread); ?></div>
                    <div class="stat-label">Unread</div>
                </div>
            </div>
        </div>

        <!-- Notification List -->
        <div class="notifications-list">
            <div class="list-header">
                <h2>Your Notifications</h2>
                <?php if ($totalUnread > 0): ?>
                    <form method="POST" style="display: inline;">
                        <input type="hidden" name="action" value="mark_all_read">
                        <button type="submit" class="btn btn-sm btn-outline">
                            <i class="fas fa-check-double"></i> Mark All Read
                        </button>
                    </form>
                <?php endif; ?>
            </div>

            <?php if (empty($notifications)): ?>
                <div class="no-notifications">
                    <i class="fas fa-inbox"></i>
                    <h3>No Notifications</h3>
                    <p>You're all caught up! No new notifications to display.</p>
                </div>
            <?php else: ?>

                <div class="notifications-grid">
                    <?php foreach ($notifications as $notification): ?>
                        <div class="notification-item <?php echo !$notification['is_read'] ? 'unread' : 'read'; ?>"
                            data-id="<?php echo $notification['notification_id']; ?>">
                            <div class="notification-content">
                                <div class="notification-header">
                                    <span class="notification-title">
                                        <?php echo htmlspecialchars($notification['title']); ?>
                                    </span>
                                    <div class="notification-actions">
                                        <?php if (!$notification['is_read']): ?>
                                            <span class="unread-badge">NEW</span>
                                        <?php endif; ?>
                                        <div class="action-buttons">
                                            <?php if (!$notification['is_read']): ?>
                                                <form method="POST" style="display: inline;">
                                                    <input type="hidden" name="action" value="mark_read">
                                                    <input type="hidden" name="notification_ids[]"
                                                        value="<?php echo $notification['notification_id']; ?>">
                                                    <button type="submit" class="btn-icon" title="Mark as read">
                                                        <i class="fas fa-check"></i>
                                                    </button>
                                                </form>
                                            <?php endif; ?>
                                            <form method="POST" style="display: inline;">
                                                <input type="hidden" name="action" value="delete">
                                                <input type="hidden" name="notification_ids[]"
                                                    value="<?php echo $notification['notification_id']; ?>">
                                                <button type="submit" class="btn-icon btn-danger" title="Delete"
                                                    onclick="return confirm('Are you sure you want to delete this notification?')">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                                <div class="notification-message">
                                    <?php echo htmlspecialchars($notification['short_message'] ?? $notification['message']); ?>
                                </div>
                                <div class="notification-meta">
                                    <span class="notification-date">
                                        <i class="far fa-clock"></i>
                                        <?php echo date('M j, Y g:i A', strtotime($notification['created_at'])); ?>
                                    </span>
                                    <span class="notification-type">
                                        <i class="fas fa-tag"></i>
                                        <?php echo htmlspecialchars($notification['type_display_name']); ?>
                                    </span>
                                    <?php if (isset($notification['priority']) && $notification['priority'] !== 'normal'): ?>
                                        <span class="notification-priority priority-<?php echo $notification['priority']; ?>">
                                            <i class="fas fa-exclamation"></i>
                                            <?php echo ucfirst($notification['priority']); ?>
                                        </span>
                                    <?php endif; ?>
                                </div>
                                <?php if (!empty($notification['action_url'])): ?>
                                    <div class="notification-action">
                                        <a href="<?php echo htmlspecialchars($notification['action_url']); ?>"
                                            class="btn btn-sm btn-primary">
                                            <?php echo htmlspecialchars($notification['action_text'] ?? 'View Details'); ?>
                                        </a>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

            <?php endif; ?>
        </div>

        <!-- Pagination -->
        <?php if ($totalPages > 1): ?>
            <div class="pagination-wrapper">
                <div class="pagination">
                    <?php if ($currentPage > 1): ?>
                        <a href="?page=<?php echo $currentPage - 1; ?><?php echo $typeFilter ? '&type=' . urlencode($typeFilter) : ''; ?><?php echo $statusFilter !== 'all' ? '&status=' . urlencode($statusFilter) : ''; ?><?php echo $searchQuery ? '&search=' . urlencode($searchQuery) : ''; ?>"
                            class="page-link">
                            <i class="fas fa-chevron-left"></i> Previous
                        </a>
                    <?php endif; ?>

                    <span class="page-info">
                        Page <?php echo $currentPage; ?> of <?php echo $totalPages; ?>
                        (<?php echo number_format($totalCount); ?> total)
                    </span>

                    <?php if ($currentPage < $totalPages): ?>
                        <a href="?page=<?php echo $currentPage + 1; ?><?php echo $typeFilter ? '&type=' . urlencode($typeFilter) : ''; ?><?php echo $statusFilter !== 'all' ? '&status=' . urlencode($statusFilter) : ''; ?><?php echo $searchQuery ? '&search=' . urlencode($searchQuery) : ''; ?>"
                            class="page-link">
                            Next <i class="fas fa-chevron-right"></i>
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <style>
        /* Fallback inline CSS */
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 20px;
            background: #f5f5f5;
        }

        .notifications-container {
            max-width: 1000px;
            margin: 0 auto;
        }

        .notifications-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }

        .btn {
            padding: 10px 20px;
            text-decoration: none;
            border-radius: 5px;
            display: inline-block;
            border: none;
            cursor: pointer;
        }

        .btn-secondary {
            background: #6c757d;
            color: white;
        }

        .btn-sm {
            padding: 5px 10px;
            font-size: 12px;
        }

        .btn-outline {
            background: transparent;
            border: 1px solid #007bff;
            color: #007bff;
        }

        .btn-primary {
            background: #007bff;
            color: white;
        }

        .btn-danger {
            background: #dc3545;
            color: white;
        }

        .btn-icon {
            background: none;
            border: none;
            padding: 5px;
            color: #666;
            cursor: pointer;
            border-radius: 3px;
        }

        .btn-icon:hover {
            background: #f0f0f0;
        }

        .btn-icon.btn-danger:hover {
            background: #ffebee;
            color: #d32f2f;
        }

        .alert {
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
        }

        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            display: flex;
            align-items: center;
        }

        .stat-icon {
            font-size: 40px;
            margin-right: 20px;
        }

        .stat-card.total .stat-icon {
            color: #007bff;
        }

        .stat-card.unread .stat-icon {
            color: #28a745;
        }

        .stat-number {
            font-size: 32px;
            font-weight: bold;
        }

        .stat-label {
            color: #666;
        }

        .notifications-list {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }

        .list-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            border-bottom: 1px solid #eee;
            padding-bottom: 15px;
        }

        .no-notifications {
            text-align: center;
            padding: 40px;
            color: #666;
        }

        .notification-item {
            border-bottom: 1px solid #eee;
            padding: 15px 0;
        }

        .notification-item.unread {
            background: #f8f9ff;
            border-left: 4px solid #007bff;
            padding-left: 15px;
            margin-left: -15px;
        }

        .notification-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 8px;
        }

        .notification-title {
            font-weight: bold;
            flex: 1;
        }

        .notification-actions {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .action-buttons {
            display: flex;
            gap: 5px;
        }

        .unread-badge {
            background: #007bff;
            color: white;
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 11px;
        }

        .notification-message {
            color: #666;
            margin-bottom: 8px;
        }

        .notification-meta {
            font-size: 12px;
            color: #999;
            display: flex;
            gap: 15px;
            align-items: center;
        }

        .notification-action {
            margin-top: 10px;
        }

        .pagination-wrapper {
            margin-top: 30px;
            text-align: center;
        }

        .pagination {
            display: inline-flex;
            align-items: center;
            gap: 20px;
        }

        .page-link {
            background: #007bff;
            color: white;
            padding: 8px 16px;
            text-decoration: none;
            border-radius: 5px;
        }

        .page-info {
            color: #666;
        }

        .priority-high {
            color: #d32f2f;
            font-weight: bold;
        }

        .priority-urgent {
            color: #d32f2f;
            font-weight: bold;
            background: #ffebee;
            padding: 2px 6px;
            border-radius: 3px;
        }
    </style>
</body>

</html>