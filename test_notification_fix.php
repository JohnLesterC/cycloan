<?php
// Quick test to verify notification creation works NOW
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once 'CYCLOAN_db.php';
require_once 'NotificationManager.php';

$debug_info = [];

// Step 1: Check if admin2 ID 9 exists
$admin_check = $conn->query("SELECT id, email FROM admin2 WHERE id = 9");
if ($admin_check && $admin_check->num_rows > 0) {
    $debug_info['admin2_9_exists'] = true;
    $admin = $admin_check->fetch_assoc();
    $debug_info['admin2_9_email'] = $admin['email'];
} else {
    $debug_info['admin2_9_exists'] = false;
}

// Step 2: Check if 'document' type exists
$type_check = $conn->query("SELECT type_id FROM notification_types WHERE type_name = 'document'");
if ($type_check && $type_check->num_rows > 0) {
    $debug_info['document_type_exists'] = true;
    $type_row = $type_check->fetch_assoc();
    $debug_info['document_type_id'] = $type_row['type_id'];
} else {
    $debug_info['document_type_exists'] = false;
}

// Step 3: Try to create notification
$notificationManager = new NotificationManager($conn);

$result = $notificationManager->createNotification(
    9,  // admin2 user_id
    'document',  // type_name
    'Test Document Notification',
    'This is a test notification for admin2 user',
    'high'  // priority
);

$debug_info['create_result'] = $result ? true : false;

// Step 4: Check if it was created
if ($result) {
    $check = $conn->query("SELECT notification_id, user_type FROM user_notifications WHERE user_id = 9 ORDER BY created_at DESC LIMIT 1");
    if ($check && $check->num_rows > 0) {
        $row = $check->fetch_assoc();
        $debug_info['notification_created'] = true;
        $debug_info['notification_id'] = $row['notification_id'];
        $debug_info['user_type'] = $row['user_type'];
    } else {
        $debug_info['notification_created'] = false;
    }
} else {
    $debug_info['notification_created'] = false;
}

// Step 5: Get all recent notifications in the table to see what's happening
$all_check = $conn->query("SELECT COUNT(*) as total FROM user_notifications");
if ($all_check) {
    $count_row = $all_check->fetch_assoc();
    $debug_info['total_notifications_in_table'] = $count_row['total'];
}

echo json_encode([
    'success' => $result ? true : false,
    'message' => $result ? 'Notification created successfully!' : 'Failed to create notification',
    'user_id' => 9,
    'user_type' => 'admin2',
    'timestamp' => date('Y-m-d H:i:s'),
    'debug' => $debug_info
], JSON_PRETTY_PRINT);
?>