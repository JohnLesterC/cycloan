<?php
/**
 * CYCLOAN Notifications - Simple Version
 * Basic notification list without dashboard design
 */

session_start();

if (!isset($_SESSION['email']) || !isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

$userId = $_SESSION['user_id'];
$userEmail = $_SESSION['email'];

try {
    require_once 'CYCLOAN_db.php';
} catch (Exception $e) {
    die("Database connection failed: " . $e->getMessage());
}

if (!isset($conn) || !($conn instanceof mysqli)) {
    die("Database connection not available");
}

// Get user information
try {
    $stmt = $conn->prepare("SELECT id as user_id, first_name, last_name FROM users1 WHERE email = ?");
    if (!$stmt) {
        throw new Exception("Database prepare failed: " . $conn->error);
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
    $stmt->close();

} catch (Exception $e) {
    die("Failed to fetch user information: " . $e->getMessage());
}

$notifications = [];
$totalCount = 0;
$totalUnread = 0;
$successMessage = '';
$errorMessage = '';

try {
    require_once 'NotificationManager.php';
    $notificationManager = new NotificationManager($conn);

    $currentPage = max(1, (int) ($_GET['page'] ?? 1));
    $perPage = 20;

    // Handle POST actions
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $action = $_POST['action'] ?? '';

        if ($action === 'mark_read') {
            $notificationIds = $_POST['notification_ids'] ?? [];
            if (!empty($notificationIds)) {
                foreach ($notificationIds as $notifId) {
                    $notificationManager->markAsRead($userId, intval($notifId));
                }
                $successMessage = "Notification marked as read.";
                header("Location: " . $_SERVER['REQUEST_URI']);
                exit();
            }
        } elseif ($action === 'mark_all_read') {
            $notificationManager->markAllAsRead($userId);
            $successMessage = "All notifications marked as read.";
            header("Location: " . $_SERVER['REQUEST_URI']);
            exit();
        } elseif ($action === 'delete') {
            $notificationIds = $_POST['notification_ids'] ?? [];
            if (!empty($notificationIds)) {
                foreach ($notificationIds as $notifId) {
                    $notificationManager->deleteNotifications($userId, intval($notifId));
                }
                $successMessage = "Notification deleted.";
                header("Location: " . $_SERVER['REQUEST_URI']);
                exit();
            }
        }
    }

    $notificationsResult = $notificationManager->getUserNotifications($userId, [], $currentPage, $perPage);
    $notifications = $notificationsResult['notifications'] ?? [];
    $totalCount = $notificationsResult['total_count'] ?? 0;

    $unreadCountsResult = $notificationManager->getUnreadCounts($userId);
    $totalUnread = $unreadCountsResult['total'] ?? 0;

} catch (Exception $e) {
    $errorMessage = "Error: " . $e->getMessage();
}

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notifications - CYCLOAN</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap"
        rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Poppins', sans-serif;
            background: #f5f5f5;
            color: #333;
            line-height: 1.6;
        }

        .container {
            max-width: 900px;
            margin: 20px auto;
            padding: 20px;
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 2px solid #1b5e20;
        }

        h1 {
            color: #1b5e20;
            font-size: 2rem;
        }

        .user-info {
            text-align: right;
            font-size: 0.9rem;
        }

        .user-info p {
            color: #666;
            margin: 5px 0;
        }

        .message {
            padding: 15px;
            border-radius: 6px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .message.success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .message.error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .controls {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            gap: 10px;
        }

        .stats {
            display: flex;
            gap: 20px;
            font-size: 0.95rem;
        }

        .stat {
            background: #f0f0f0;
            padding: 10px 15px;
            border-radius: 6px;
            border-left: 3px solid #1b5e20;
        }

        .stat strong {
            color: #1b5e20;
        }

        button {
            background: #1b5e20;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 6px;
            cursor: pointer;
            font-family: 'Poppins', sans-serif;
            transition: background 0.3s;
        }

        button:hover {
            background: #2e7d32;
        }

        .notifications-list {
            display: flex;
            flex-direction: column;
            gap: 15px;
        }

        .notification-item {
            background: #fafafa;
            border-left: 4px solid #ddd;
            padding: 15px;
            border-radius: 6px;
            transition: all 0.3s;
        }

        .notification-item.unread {
            background: #f0f8ff;
            border-left-color: #1b5e20;
            box-shadow: 0 2px 8px rgba(27, 94, 32, 0.1);
        }

        .notification-item h3 {
            color: #1b5e20;
            margin-bottom: 8px;
            font-size: 1.1rem;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .badge-new {
            background: #1b5e20;
            color: white;
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 0.75rem;
        }

        .notification-item p {
            color: #555;
            margin-bottom: 10px;
            font-size: 0.95rem;
        }

        .notification-meta {
            font-size: 0.85rem;
            color: #888;
            margin-bottom: 12px;
        }

        .notification-actions {
            display: flex;
            gap: 10px;
        }

        .notification-actions form {
            display: inline;
        }

        .notification-actions button {
            background: #666;
            padding: 6px 12px;
            font-size: 0.85rem;
        }

        .notification-actions button:hover {
            background: #444;
        }

        .notification-actions button.delete {
            background: #d32f2f;
        }

        .notification-actions button.delete:hover {
            background: #b71c1c;
        }

        .empty-state {
            text-align: center;
            padding: 40px 20px;
            color: #999;
        }

        .empty-state i {
            font-size: 3rem;
            color: #ddd;
            margin-bottom: 15px;
        }

        .empty-state h3 {
            color: #666;
            margin-bottom: 10px;
        }

        .pagination {
            text-align: center;
            margin-top: 30px;
            display: flex;
            justify-content: center;
            gap: 10px;
        }

        .pagination a,
        .pagination span {
            padding: 8px 12px;
            border-radius: 4px;
            background: #f0f0f0;
            text-decoration: none;
            color: #333;
        }

        .pagination a:hover {
            background: #1b5e20;
            color: white;
        }

        .back-link {
            margin-bottom: 20px;
        }

        .back-link a {
            color: #1b5e20;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .back-link a:hover {
            text-decoration: underline;
        }
    </style>
</head>

<body>
    <div class="container">
        <div class="back-link">
            <a href="user_dashboard.php">
                <i class="fas fa-arrow-left"></i> Back to Dashboard
            </a>
        </div>

        <header>
            <h1><i class="fas fa-bell"></i> Notifications</h1>
            <div class="user-info">
                <p><?php echo htmlspecialchars($userName); ?></p>
                <p><?php echo htmlspecialchars($userEmail); ?></p>
            </div>
        </header>

        <?php if ($successMessage): ?>
            <div class="message success">
                <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($successMessage); ?>
            </div>
        <?php endif; ?>

        <?php if ($errorMessage): ?>
            <div class="message error">
                <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($errorMessage); ?>
            </div>
        <?php endif; ?>

        <div class="controls">
            <div class="stats">
                <div class="stat">
                    <strong><?php echo number_format($totalCount); ?></strong> Total
                </div>
                <div class="stat">
                    <strong><?php echo number_format($totalUnread); ?></strong> Unread
                </div>
            </div>
            <?php if ($totalUnread > 0): ?>
                <form method="POST" style="display: inline;">
                    <input type="hidden" name="action" value="mark_all_read">
                    <button type="submit">
                        <i class="fas fa-check-double"></i> Mark All Read
                    </button>
                </form>
            <?php endif; ?>
        </div>

        <div class="notifications-list">
            <?php if (empty($notifications)): ?>
                <div class="empty-state">
                    <div><i class="fas fa-inbox"></i></div>
                    <h3>No Notifications</h3>
                    <p>You're all caught up! Check back later for new updates.</p>
                </div>
            <?php else: ?>
                <?php foreach ($notifications as $notification): ?>
                    <div class="notification-item <?php echo !$notification['is_read'] ? 'unread' : ''; ?>">
                        <h3>
                            <?php echo htmlspecialchars($notification['title']); ?>
                            <?php if (!$notification['is_read']): ?>
                                <span class="badge-new">NEW</span>
                            <?php endif; ?>
                        </h3>
                        <p><?php echo htmlspecialchars($notification['short_message'] ?? $notification['message']); ?></p>
                        <div class="notification-meta">
                            <i class="far fa-clock"></i>
                            <?php echo date('M j, Y g:i A', strtotime($notification['created_at'])); ?>
                            | <i class="fas fa-tag"></i> <?php echo htmlspecialchars($notification['type_display_name']); ?>
                        </div>
                        <div class="notification-actions">
                            <?php if (!$notification['is_read']): ?>
                                <form method="POST">
                                    <input type="hidden" name="action" value="mark_read">
                                    <input type="hidden" name="notification_ids[]"
                                        value="<?php echo $notification['notification_id']; ?>">
                                    <button type="submit">Mark as Read</button>
                                </form>
                            <?php endif; ?>
                            <form method="POST" onsubmit="return confirm('Delete this notification?');">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="notification_ids[]"
                                    value="<?php echo $notification['notification_id']; ?>">
                                <button type="submit" class="delete">Delete</button>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</body>

</html>