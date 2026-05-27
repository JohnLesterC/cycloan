<?php
/**
 * CYCLOAN Notifications Dashboard Page - Error Protected Version
 * Comprehensive notification management interface for users
 * 
 * @author CYCLOAN Development Team
 * @version 2.1 - With Error Protection
 * @created 2025-11-13
 */

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Start output buffering to catch any errors
ob_start();

try {
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
    $stmt = $conn->prepare("SELECT id as user_id, first_name, last_name FROM users1 WHERE email = ?");
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

                case 'mark_unread':
                    // Mark as unread functionality not available
                    $errorMessage = "Mark as unread functionality is not currently available.";
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

    // Available notification types would be loaded here if needed
    $availableTypes = [];

} catch (Exception $e) {
    // If any error occurs, show a simple error page
    ob_clean();
    ?>
    <!DOCTYPE html>
    <html lang="en">

    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Notifications Error - CYCLOAN</title>
        <style>
            body {
                font-family: Arial, sans-serif;
                margin: 40px;
                background: #f5f5f5;
            }

            .error-container {
                max-width: 600px;
                margin: 0 auto;
                background: white;
                padding: 30px;
                border-radius: 8px;
                box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            }

            .error-header {
                color: #d32f2f;
                margin-bottom: 20px;
            }

            .error-message {
                background: #ffebee;
                color: #c62828;
                padding: 15px;
                border-radius: 5px;
                margin: 20px 0;
            }

            .back-btn {
                background: #1976d2;
                color: white;
                padding: 10px 20px;
                text-decoration: none;
                border-radius: 5px;
                display: inline-block;
            }
        </style>
    </head>

    <body>
        <div class="error-container">
            <h1 class="error-header">🚨 Notifications System Error</h1>
            <div class="error-message">
                <strong>Error Details:</strong><br>
                <?php echo htmlspecialchars($e->getMessage()); ?>
            </div>
            <p>We're experiencing technical difficulties with the notifications system. Please try again later or contact
                support if the problem persists.</p>

            <div style="margin-top: 30px;">
                <a href="user_dashboard.php" class="back-btn">← Back to Dashboard</a>
                <a href="simple_notifications.php" class="back-btn" style="background: #388e3c; margin-left: 10px;">Try
                    Simple View</a>
                <a href="debug_notifications.php" class="back-btn" style="background: #f57c00; margin-left: 10px;">Run
                    Diagnostic</a>
            </div>
        </div>
    </body>

    </html>
    <?php
    exit();
}

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

        <!-- Quick Success Message -->
        <div class="quick-info">
            <p><strong>System Status:</strong> ✅ Notifications system is working properly!</p>
            <p>If you see this page, the error has been resolved. Total notifications loaded:
                <strong><?php echo $totalCount; ?></strong>
            </p>
        </div>

        <!-- Simple Notification List -->
        <div class="notifications-simple">
            <h2>Recent Notifications</h2>

            <?php if (empty($notifications)): ?>
                <div class="no-notifications">
                    <i class="fas fa-inbox"></i>
                    <h3>No Notifications</h3>
                    <p>You're all caught up! No new notifications to display.</p>
                </div>
            <?php else: ?>

                <?php foreach ($notifications as $notification): ?>
                    <div class="notification-item <?php echo !$notification['is_read'] ? 'unread' : 'read'; ?>">
                        <div class="notification-content">
                            <div class="notification-header">
                                <span class="notification-title"><?php echo htmlspecialchars($notification['title']); ?></span>
                                <?php if (!$notification['is_read']): ?>
                                    <span class="unread-badge">NEW</span>
                                <?php endif; ?>
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
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>

            <?php endif; ?>
        </div>

        <!-- Pagination -->
        <?php if ($totalPages > 1): ?>
            <div class="pagination-wrapper">
                <div class="pagination">
                    <?php if ($currentPage > 1): ?>
                        <a href="?page=<?php echo $currentPage - 1; ?>" class="page-link">
                            <i class="fas fa-chevron-left"></i> Previous
                        </a>
                    <?php endif; ?>

                    <span class="page-info">
                        Page <?php echo $currentPage; ?> of <?php echo $totalPages; ?>
                    </span>

                    <?php if ($currentPage < $totalPages): ?>
                        <a href="?page=<?php echo $currentPage + 1; ?>" class="page-link">
                            Next <i class="fas fa-chevron-right"></i>
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>

        <!-- Footer -->
        <div class="notifications-footer">
            <div class="footer-links">
                <a href="simple_notifications.php"><i class="fas fa-list"></i> Simple View</a>
                <a href="debug_notifications.php"><i class="fas fa-bug"></i> System Check</a>
                <a href="user_dashboard.php"><i class="fas fa-home"></i> Dashboard</a>
            </div>
        </div>
    </div>

    <style>
        /* Inline CSS for immediate styling */
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
        }

        .btn-secondary {
            background: #6c757d;
            color: white;
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

        .quick-info {
            background: #e7f3ff;
            border: 1px solid #b3d9ff;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
        }

        .notifications-simple {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
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
        }

        .notification-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 8px;
        }

        .notification-title {
            font-weight: bold;
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
        }

        .notification-meta span {
            margin-right: 15px;
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

        .notifications-footer {
            margin-top: 30px;
            text-align: center;
        }

        .footer-links a {
            margin: 0 15px;
            color: #007bff;
            text-decoration: none;
        }
    </style>
</body>

</html>