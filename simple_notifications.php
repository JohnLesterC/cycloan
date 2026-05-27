<?php
/**
 * Simple Notifications Page - Minimal Version for Testing
 * This is a simplified version to isolate any issues
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
require_once 'CYCLOAN_db.php';
require_once 'NotificationManager.php';

// Check if user is authenticated
if (!isset($_SESSION['email']) || empty($_SESSION['email'])) {
    header("Location: index.php");
    exit();
}

$userEmail = $_SESSION['email'];

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
$stmt->close();

// Initialize NotificationManager
$notificationManager = new NotificationManager($conn);

// Get notifications
try {
    $notificationsResult = $notificationManager->getUserNotifications($userId, [], 1, 10);
    $notifications = $notificationsResult['notifications'];
    $totalCount = $notificationsResult['total_count'];
    
    // Get unread counts
    $unreadCounts = $notificationManager->getUnreadCounts($userId);
} catch (Exception $e) {
    $notifications = [];
    $totalCount = 0;
    $unreadCounts = ['total' => 0];
    $error_message = $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Simple Notifications - CYCLOAN</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 20px;
            background: #f5f5f5;
        }
        .container {
            max-width: 800px;
            margin: 0 auto;
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .header {
            border-bottom: 1px solid #eee;
            padding-bottom: 20px;
            margin-bottom: 20px;
        }
        .stats {
            display: flex;
            gap: 20px;
            margin-bottom: 20px;
        }
        .stat-card {
            background: #007bff;
            color: white;
            padding: 15px;
            border-radius: 8px;
            text-align: center;
            flex: 1;
        }
        .notification-item {
            border: 1px solid #ddd;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 10px;
            background: white;
        }
        .notification-item.unread {
            border-left: 4px solid #007bff;
            background: #f8f9ff;
        }
        .notification-title {
            font-weight: bold;
            margin-bottom: 5px;
        }
        .notification-message {
            color: #666;
            margin-bottom: 10px;
        }
        .notification-meta {
            font-size: 12px;
            color: #999;
        }
        .error {
            background: #f8d7da;
            color: #721c24;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        .back-link {
            background: #28a745;
            color: white;
            padding: 10px 20px;
            text-decoration: none;
            border-radius: 5px;
            display: inline-block;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>📱 Simple Notifications</h1>
            <p>Welcome, <?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?>!</p>
            <a href="user_dashboard.php" class="back-link">← Back to Dashboard</a>
        </div>

        <?php if (isset($error_message)): ?>
        <div class="error">
            <strong>Error:</strong> <?php echo htmlspecialchars($error_message); ?>
        </div>
        <?php endif; ?>

        <div class="stats">
            <div class="stat-card">
                <div style="font-size: 24px; font-weight: bold;"><?php echo $totalCount; ?></div>
                <div>Total Notifications</div>
            </div>
            <div class="stat-card">
                <div style="font-size: 24px; font-weight: bold;"><?php echo $unreadCounts['total']; ?></div>
                <div>Unread</div>
            </div>
        </div>

        <h2>Recent Notifications</h2>
        
        <?php if (empty($notifications)): ?>
        <div style="text-align: center; padding: 40px; color: #666;">
            <h3>📭 No Notifications</h3>
            <p>You're all caught up! No notifications to display.</p>
        </div>
        <?php else: ?>
        <?php foreach ($notifications as $notification): ?>
        <div class="notification-item <?php echo !$notification['is_read'] ? 'unread' : ''; ?>">
            <div class="notification-title">
                <?php echo htmlspecialchars($notification['title']); ?>
                <?php if (!$notification['is_read']): ?>
                <span style="background: #007bff; color: white; padding: 2px 6px; border-radius: 12px; font-size: 10px; margin-left: 10px;">NEW</span>
                <?php endif; ?>
            </div>
            <div class="notification-message">
                <?php echo htmlspecialchars($notification['short_message'] ?? $notification['message']); ?>
            </div>
            <div class="notification-meta">
                📅 <?php echo date('M j, Y g:i A', strtotime($notification['created_at'])); ?> • 
                🏷️ <?php echo htmlspecialchars($notification['type_display_name']); ?>
            </div>
        </div>
        <?php endforeach; ?>
        <?php endif; ?>

        <div style="margin-top: 30px; text-align: center;">
            <a href="debug_notifications.php" style="color: #007bff;">🔍 Run Full Diagnostic</a> | 
            <a href="notifications.php" style="color: #007bff;">📱 Try Full Notifications Page</a>
        </div>
    </div>
</body>
</html>