<?php
/**
 * CYCLOAN Notification API
 * 
 * Handles AJAX requests for notification operations:
 * - Get unread count
 * - Get recent notifications
 * - Mark as read
 * - Delete notifications
 * - Get all notifications with filtering
 * 
 * @author CYCLOAN Development Team
 * @version 1.0
 */

session_start();
header('Content-Type: application/json');

// Check authentication
if (!isset($_SESSION['user_id']) || !isset($_SESSION['email'])) {
    http_response_code(401);
    die(json_encode([
        'success' => false,
        'message' => 'Unauthorized'
    ]));
}

require_once 'CYCLOAN_db.php';
require_once 'NotificationManager.php';

$user_id = $_SESSION['user_id'];
$action = $_GET['action'] ?? $_POST['action'] ?? '';

$notificationManager = new NotificationManager($conn);

try {
    switch ($action) {
        case 'get_unread_count':
            handleGetUnreadCount($notificationManager, $user_id);
            break;

        case 'get_recent':
            handleGetRecent($notificationManager, $user_id);
            break;

        case 'get_all':
            handleGetAll($notificationManager, $user_id);
            break;

        case 'mark_read':
            handleMarkRead($notificationManager, $user_id);
            break;

        case 'mark_all_read':
            handleMarkAllRead($notificationManager, $user_id);
            break;

        case 'delete':
            handleDelete($notificationManager, $user_id);
            break;

        case 'delete_multiple':
            handleDeleteMultiple($notificationManager, $user_id);
            break;

        default:
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'Invalid action'
            ]);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

/**
 * Get unread notification count
 */
function handleGetUnreadCount($manager, $user_id)
{
    $count = $manager->getUnreadCount($user_id);
    echo json_encode([
        'success' => true,
        'count' => $count,
        'timestamp' => date('Y-m-d H:i:s')
    ]);
}

/**
 * Get recent notifications
 */
function handleGetRecent($manager, $user_id)
{
    $limit = min((int) ($_GET['limit'] ?? 5), 50);
    $notifications = $manager->getRecentNotifications($user_id, $limit);

    echo json_encode([
        'success' => true,
        'notifications' => $notifications ?? [],
        'count' => count($notifications ?? [])
    ]);
}

/**
 * Get all notifications with filters
 */
function handleGetAll($manager, $user_id)
{
    $page = max(1, (int) ($_GET['page'] ?? 1));
    $per_page = max(1, min((int) ($_GET['per_page'] ?? 20), 100));

    $filters = [];
    if (isset($_GET['type']) && !empty($_GET['type'])) {
        $filters['type'] = $_GET['type'];
    }
    if (isset($_GET['status'])) {
        $filters['is_read'] = $_GET['status'] === 'read' ? 1 : 0;
    }
    if (isset($_GET['search']) && !empty($_GET['search'])) {
        $filters['search'] = $_GET['search'];
    }

    $result = $manager->getUserNotifications($user_id, $filters, $page, $per_page);

    echo json_encode([
        'success' => true,
        'notifications' => $result['notifications'] ?? [],
        'total' => $result['total_count'] ?? 0,
        'pages' => $result['total_pages'] ?? 0,
        'current_page' => $page
    ]);
}

/**
 * Mark single notification as read
 */
function handleMarkRead($manager, $user_id)
{
    $notification_id = (int) ($_POST['notification_id'] ?? 0);

    if (!$notification_id) {
        throw new Exception('Invalid notification ID');
    }

    $result = $manager->markAsRead($user_id, $notification_id);

    echo json_encode([
        'success' => $result,
        'message' => $result ? 'Marked as read' : 'Failed to mark as read'
    ]);
}

/**
 * Mark all notifications as read
 */
function handleMarkAllRead($manager, $user_id)
{
    $result = $manager->markAllAsRead($user_id);

    echo json_encode([
        'success' => $result,
        'message' => $result ? 'All notifications marked as read' : 'Failed to mark all as read'
    ]);
}

/**
 * Delete single notification
 */
function handleDelete($manager, $user_id)
{
    $notification_id = (int) ($_POST['notification_id'] ?? 0);

    if (!$notification_id) {
        throw new Exception('Invalid notification ID');
    }

    $result = $manager->deleteNotifications($user_id, $notification_id);

    echo json_encode([
        'success' => $result,
        'message' => $result ? 'Notification deleted' : 'Failed to delete notification'
    ]);
}

/**
 * Delete multiple notifications
 */
function handleDeleteMultiple($manager, $user_id)
{
    $notification_ids = $_POST['notification_ids'] ?? [];

    if (empty($notification_ids) || !is_array($notification_ids)) {
        throw new Exception('Invalid notification IDs');
    }

    $deleted_count = 0;
    foreach ($notification_ids as $id) {
        if ($manager->deleteNotifications($user_id, (int) $id)) {
            $deleted_count++;
        }
    }

    echo json_encode([
        'success' => true,
        'deleted' => $deleted_count,
        'message' => "$deleted_count notification(s) deleted"
    ]);
}

?>