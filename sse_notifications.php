<?php
session_start();

require_once 'CYCLOAN_db.php';
require_once 'NotificationStream.php';

if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    header('HTTP/1.1 401 Unauthorized');
    exit;
}

$userId = $_SESSION['user_id'];
$role = $_SESSION['role'];

header('Content-Type: text/event-stream');
header('Cache-Control: no-cache');
header('Connection: keep-alive');
header('X-Accel-Buffering: no');

if (ob_get_level()) {
    ob_end_clean();
}

$notificationStream = new NotificationStream($conn, $userId, $role);
$lastNotificationId = isset($_GET['lastId']) ? (int) $_GET['lastId'] : 0;

set_time_limit(1800);
$startTime = time();
$connectionTimeout = 1800;

function sendSSEEvent($eventType, $data)
{
    $json = json_encode($data);
    echo "event: " . $eventType . "\n";
    echo "data: " . $json . "\n\n";
    flush();
    if (ob_get_level()) {
        ob_flush();
    }
}

function isClientConnected()
{
    return !connection_aborted();
}

try {
    $connected = array('status' => 'success', 'message' => 'Connected to real-time notifications', 'timestamp' => date('Y-m-d H:i:s'));
    sendSSEEvent('connected', $connected);

    while (isClientConnected()) {
        if (time() - $startTime > $connectionTimeout) {
            $timeout = array('status' => 'info', 'message' => 'Connection timeout', 'timestamp' => date('Y-m-d H:i:s'));
            sendSSEEvent('timeout', $timeout);
            break;
        }

        $notifications = $notificationStream->getNewNotifications($lastNotificationId);

        if (!empty($notifications)) {
            foreach ($notifications as $notification) {
                $lastNotificationId = $notification['id'];

                $icon = $notificationStream->getNotificationIcon($notification['type']);
                $actionUrl = $notificationStream->getNotificationActionUrl($notification);
                $sender = isset($notification['sender_name']) ? $notification['sender_name'] : 'System';
                $module = isset($notification['module']) ? $notification['module'] : 'system';
                $action = isset($notification['action']) ? $notification['action'] : 'update';
                $priority = isset($notification['priority']) ? $notification['priority'] : 'normal';
                $relatedId = isset($notification['related_id']) ? $notification['related_id'] : null;

                $notificationData = array(
                    'id' => $notification['id'],
                    'type' => $notification['type'],
                    'title' => $notification['title'],
                    'message' => $notification['message'],
                    'module' => $module,
                    'action' => $action,
                    'relatedId' => $relatedId,
                    'sender' => $sender,
                    'priority' => $priority,
                    'timestamp' => $notification['created_at'],
                    'icon' => $icon,
                    'actionUrl' => $actionUrl
                );

                sendSSEEvent('notification', $notificationData);
                $notificationStream->markNotificationAsSent($notification['id']);
            }
        }

        $heartbeat = array('status' => 'ok', 'timestamp' => date('Y-m-d H:i:s'), 'uptime' => time() - $startTime);
        sendSSEEvent('heartbeat', $heartbeat);

        sleep(5);
    }

} catch (Exception $e) {
    $error = array('status' => 'error', 'message' => 'An error occurred', 'timestamp' => date('Y-m-d H:i:s'));
    sendSSEEvent('error', $error);
    error_log('SSE Error: ' . $e->getMessage());
}

exit;
?>