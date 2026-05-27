<?php
/**
 * Quick Test - Create Notification for Admin2
 * This verifies the notification system works
 */

// Start output
ob_start();
header('Content-Type: application/json');

require_once 'CYCLOAN_db.php';
require_once 'NotificationManager.php';

$response = ['success' => false, 'message' => '', 'debug' => []];

try {
    // Get admin2 IDs
    $admins = $conn->query("SELECT id FROM admin2 LIMIT 1");
    if (!$admins || $admins->num_rows === 0) {
        throw new Exception("No admin2 users found");
    }

    $admin = $admins->fetch_assoc();
    $admin_id = $admin['id'];

    $response['debug'][] = "Found admin2 ID: $admin_id";

    // Create notification
    $nm = new NotificationManager($conn);
    $result = $nm->createNotification(
        $admin_id,
        'document',
        'TEST: System Check',
        'This is a test notification to verify the system is working correctly.',
        'normal'
    );

    $response['debug'][] = "createNotification returned: " . ($result ? 'true' : 'false');

    if ($result) {
        // Verify it was created
        $verify = $conn->query("
            SELECT notification_id, created_at FROM user_notifications 
            WHERE user_id = $admin_id 
            ORDER BY created_at DESC 
            LIMIT 1
        ");

        if ($verify && $verify->num_rows > 0) {
            $notif = $verify->fetch_assoc();
            $response['success'] = true;
            $response['message'] = 'Notification created and verified!';
            $response['notification_id'] = $notif['notification_id'];
            $response['created_at'] = $notif['created_at'];
        } else {
            $response['message'] = 'Notification creation returned true but not found in database!';
        }
    } else {
        $response['message'] = 'Failed to create notification';
    }

} catch (Exception $e) {
    $response['message'] = 'Error: ' . $e->getMessage();
    $response['debug'][] = $e->getTraceAsString();
}

ob_end_clean();
echo json_encode($response);
?>