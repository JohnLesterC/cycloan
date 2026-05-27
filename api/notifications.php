<?php
/**
 * CYCLOAN Notification System API
 * Handles AJAX requests for notification management
 * 
 * @author CYCLOAN Development Team
 * @version 1.0
 * @created 2025-11-13
 */

session_start();
require_once '../CYCLOAN_db.php';
require_once '../NotificationManager.php';

// Set JSON response headers
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache, must-revalidate');

// CSRF Protection
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Initialize response
$response = [
    'success' => false,
    'message' => '',
    'data' => null
];

try {
    // Check if user is authenticated
    if (!isset($_SESSION['email']) || empty($_SESSION['email'])) {
        throw new Exception('Authentication required');
    }

    // Get user ID
    $userEmail = $_SESSION['email'];
    $stmt = $conn->prepare("SELECT user_id FROM users1 WHERE email = ?");
    $stmt->bind_param("s", $userEmail);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        throw new Exception('User not found');
    }

    $user = $result->fetch_assoc();
    $userId = $user['user_id'];

    // Initialize NotificationManager
    $notificationManager = new NotificationManager($conn);

    // Get request method and action
    $method = $_SERVER['REQUEST_METHOD'];
    $action = '';

    if ($method === 'GET') {
        $action = $_GET['action'] ?? '';
    } elseif ($method === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true);
        $action = $input['action'] ?? $_POST['action'] ?? '';
    }

    // Handle different actions
    switch ($action) {
        case 'get_notifications':
            handleGetNotifications($notificationManager, $userId);
            break;

        case 'get_recent':
            handleGetRecent($notificationManager, $userId);
            break;

        case 'get_unread_count':
            handleGetUnreadCount($notificationManager, $userId);
            break;

        case 'check_new':
            handleCheckNew($notificationManager, $userId);
            break;

        case 'mark_read':
            handleMarkRead($notificationManager, $userId);
            break;

        case 'mark_all_read':
            handleMarkAllRead($notificationManager, $userId);
            break;

        case 'archive':
            handleArchive($notificationManager, $userId);
            break;

        case 'delete':
            handleDelete($notificationManager, $userId);
            break;

        case 'get_settings':
            handleGetSettings($notificationManager, $userId);
            break;

        case 'update_settings':
            handleUpdateSettings($notificationManager, $userId);
            break;

        default:
            throw new Exception('Invalid action specified');
    }

} catch (Exception $e) {
    $response['message'] = $e->getMessage();
    error_log("Notification API Error: " . $e->getMessage());
}

// Send response
echo json_encode($response);
exit;

/**
 * Get paginated notifications with filters
 */
function handleGetNotifications($notificationManager, $userId)
{
    global $response;

    $page = (int) ($_GET['page'] ?? 1);
    $limit = min((int) ($_GET['limit'] ?? 20), 100); // Max 100 per page
    $type = $_GET['type'] ?? null;
    $status = $_GET['status'] ?? 'all';
    $search = $_GET['search'] ?? '';

    $filters = [];
    if ($type)
        $filters['type'] = $type;
    if ($status !== 'all')
        $filters['is_read'] = ($status === 'read');
    if ($search)
        $filters['search'] = $search;

    $result = $notificationManager->getUserNotifications($userId, $filters, $page, $limit);

    $response['success'] = true;
    $response['data'] = [
        'notifications' => $result['notifications'],
        'pagination' => [
            'current_page' => $page,
            'total_pages' => $result['total_pages'],
            'total_count' => $result['total_count'],
            'per_page' => $limit
        ]
    ];
}

/**
 * Get recent notifications for dropdown
 */
function handleGetRecent($notificationManager, $userId)
{
    global $response;

    $limit = min((int) ($_GET['limit'] ?? 10), 20); // Max 20 for dropdown

    $result = $notificationManager->getUserNotifications($userId, [], 1, $limit);

    $response['success'] = true;
    $response['data'] = [
        'notifications' => $result['notifications']
    ];
}

/**
 * Get unread notification counts
 */
function handleGetUnreadCount($notificationManager, $userId)
{
    global $response;

    $counts = $notificationManager->getUnreadCounts($userId);

    $response['success'] = true;
    $response['data'] = $counts;
}

/**
 * Check for new notifications since last check
 */
function handleCheckNew($notificationManager, $userId)
{
    global $response;

    $lastCheck = $_GET['last_check'] ?? null;
    $newNotifications = [];

    if ($lastCheck) {
        // Get notifications created after last check
        $stmt = $notificationManager->getConnection()->prepare("
            SELECT n.*, nt.type_name, nt.icon_class, nt.color_class, nt.display_name as type_display_name
            FROM user_notifications n
            JOIN notification_types nt ON n.type_id = nt.type_id
            WHERE n.user_id = ? AND n.created_at > ? AND n.is_read = FALSE
            ORDER BY n.created_at DESC
        ");
        $stmt->bind_param("is", $userId, $lastCheck);
        $stmt->execute();
        $result = $stmt->get_result();

        while ($row = $result->fetch_assoc()) {
            $newNotifications[] = $row;
        }
    }

    $counts = $notificationManager->getUnreadCounts($userId);

    $response['success'] = true;
    $response['data'] = [
        'unread_count' => $counts['total'],
        'new_notifications' => $newNotifications,
        'last_check' => date('Y-m-d H:i:s')
    ];
}

/**
 * Mark notifications as read
 */
function handleMarkRead($notificationManager, $userId)
{
    global $response;

    $input = json_decode(file_get_contents('php://input'), true);
    $notificationIds = $input['notification_ids'] ?? [];

    if (empty($notificationIds)) {
        throw new Exception('No notification IDs provided');
    }

    // Verify notifications belong to user
    $placeholders = str_repeat('?,', count($notificationIds) - 1) . '?';
    $stmt = $notificationManager->getConnection()->prepare("
        SELECT notification_id FROM user_notifications 
        WHERE user_id = ? AND notification_id IN ($placeholders)
    ");

    $types = str_repeat('s', count($notificationIds));
    $stmt->bind_param("i$types", $userId, ...$notificationIds);
    $stmt->execute();
    $result = $stmt->get_result();

    $validIds = [];
    while ($row = $result->fetch_assoc()) {
        $validIds[] = $row['notification_id'];
    }

    if (empty($validIds)) {
        throw new Exception('No valid notifications found');
    }

    $success = $notificationManager->markAsRead($validIds);

    if ($success) {
        $response['success'] = true;
        $response['message'] = 'Notifications marked as read';
        $response['data'] = ['marked_count' => count($validIds)];
    } else {
        throw new Exception('Failed to mark notifications as read');
    }
}

/**
 * Mark all notifications as read for user
 */
function handleMarkAllRead($notificationManager, $userId)
{
    global $response;

    $success = $notificationManager->markAllAsRead($userId);

    if ($success) {
        $response['success'] = true;
        $response['message'] = 'All notifications marked as read';
    } else {
        throw new Exception('Failed to mark all notifications as read');
    }
}

/**
 * Archive notifications
 */
function handleArchive($notificationManager, $userId)
{
    global $response;

    $input = json_decode(file_get_contents('php://input'), true);
    $notificationIds = $input['notification_ids'] ?? [];

    if (empty($notificationIds)) {
        throw new Exception('No notification IDs provided');
    }

    // Verify notifications belong to user
    $placeholders = str_repeat('?,', count($notificationIds) - 1) . '?';
    $stmt = $notificationManager->getConnection()->prepare("
        SELECT notification_id FROM user_notifications 
        WHERE user_id = ? AND notification_id IN ($placeholders)
    ");

    $types = str_repeat('s', count($notificationIds));
    $stmt->bind_param("i$types", $userId, ...$notificationIds);
    $stmt->execute();
    $result = $stmt->get_result();

    $validIds = [];
    while ($row = $result->fetch_assoc()) {
        $validIds[] = $row['notification_id'];
    }

    if (empty($validIds)) {
        throw new Exception('No valid notifications found');
    }

    $success = $notificationManager->archiveNotifications($validIds);

    if ($success) {
        $response['success'] = true;
        $response['message'] = 'Notifications archived';
        $response['data'] = ['archived_count' => count($validIds)];
    } else {
        throw new Exception('Failed to archive notifications');
    }
}

/**
 * Delete notifications
 */
function handleDelete($notificationManager, $userId)
{
    global $response;

    $input = json_decode(file_get_contents('php://input'), true);
    $notificationIds = $input['notification_ids'] ?? [];

    if (empty($notificationIds)) {
        throw new Exception('No notification IDs provided');
    }

    // Verify notifications belong to user
    $placeholders = str_repeat('?,', count($notificationIds) - 1) . '?';
    $stmt = $notificationManager->getConnection()->prepare("
        SELECT notification_id FROM user_notifications 
        WHERE user_id = ? AND notification_id IN ($placeholders)
    ");

    $types = str_repeat('s', count($notificationIds));
    $stmt->bind_param("i$types", $userId, ...$notificationIds);
    $stmt->execute();
    $result = $stmt->get_result();

    $validIds = [];
    while ($row = $result->fetch_assoc()) {
        $validIds[] = $row['notification_id'];
    }

    if (empty($validIds)) {
        throw new Exception('No valid notifications found');
    }

    $success = $notificationManager->deleteNotifications($validIds);

    if ($success) {
        $response['success'] = true;
        $response['message'] = 'Notifications deleted';
        $response['data'] = ['deleted_count' => count($validIds)];
    } else {
        throw new Exception('Failed to delete notifications');
    }
}

/**
 * Get user notification settings
 */
function handleGetSettings($notificationManager, $userId)
{
    global $response;

    $settings = $notificationManager->getUserSettings($userId);

    $response['success'] = true;
    $response['data'] = $settings;
}

/**
 * Update user notification settings
 */
function handleUpdateSettings($notificationManager, $userId)
{
    global $response;

    $input = json_decode(file_get_contents('php://input'), true);
    $settings = $input['settings'] ?? [];

    if (empty($settings)) {
        throw new Exception('No settings provided');
    }

    $success = $notificationManager->updateUserSettings($userId, $settings);

    if ($success) {
        $response['success'] = true;
        $response['message'] = 'Settings updated successfully';
    } else {
        throw new Exception('Failed to update settings');
    }
}
?>