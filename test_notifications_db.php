<?php
/**
 * CYCLOAN Notifications System - Diagnostic Test
 * Use this to check if the notification system is properly set up
 * Access this file at: https://cycloan-cldd.com/test_notifications_db.php
 */

session_start();

// Redirect to login if not authenticated
if (!isset($_SESSION['email']) || !isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

require_once 'CYCLOAN_db.php';

echo "<!DOCTYPE html>
<html>
<head>
    <title>CYCLOAN - Notifications System Diagnostic</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Poppins', sans-serif; background: #f5f5f5; padding: 20px; }
        .container { max-width: 900px; margin: 0 auto; background: white; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); padding: 30px; }
        h1 { color: #1b5e20; margin-bottom: 30px; }
        .test-item { margin: 20px 0; padding: 15px; border-left: 4px solid #ddd; border-radius: 4px; }
        .test-item.pass { border-left-color: #4caf50; background: #f1f8f6; }
        .test-item.fail { border-left-color: #f44336; background: #fef5f5; }
        .test-item.warning { border-left-color: #ff9800; background: #fff3f0; }
        .status { font-weight: 600; margin-right: 10px; }
        .status.pass::before { content: '✓ '; color: #4caf50; }
        .status.fail::before { content: '✗ '; color: #f44336; }
        .status.warning::before { content: '⚠ '; color: #ff9800; }
        .code { background: #f0f0f0; padding: 10px; margin-top: 10px; border-radius: 4px; font-family: monospace; font-size: 12px; }
        .action-btn { background: #1b5e20; color: white; padding: 10px 20px; border: none; border-radius: 4px; cursor: pointer; margin-top: 10px; }
        .action-btn:hover { background: #2e7d32; }
        table { width: 100%; margin-top: 10px; border-collapse: collapse; }
        th, td { text-align: left; padding: 10px; border-bottom: 1px solid #ddd; }
        th { background: #f9f9f9; font-weight: 600; }
    </style>
</head>
<body>
    <div class='container'>
        <h1>🔍 CYCLOAN Notifications System Diagnostic</h1>";

// Test 1: Database Connection
echo "<div class='test-item " . ($conn ? "pass" : "fail") . "'>
    <span class='status " . ($conn ? "pass" : "fail") . "'>Database Connection</span>";

if (!$conn) {
    echo "<div class='code'>Error: " . mysqli_connect_error() . "</div>";
} else {
    echo "<p>Connected to: {$_SESSION['email']}</p>";
}
echo "</div>";

// Test 2: notification_types table
$types_table = false;
if ($conn) {
    $result = $conn->query("SHOW TABLES LIKE 'notification_types'");
    $types_table = $result && $result->num_rows > 0;
}

echo "<div class='test-item " . ($types_table ? "pass" : "fail") . "'>
    <span class='status " . ($types_table ? "pass" : "fail") . "'>notification_types Table</span>";

if (!$types_table && $conn) {
    echo "<p>Table not found. This table must be created for the notification system to work.</p>";
} elseif ($types_table) {
    $count = $conn->query("SELECT COUNT(*) as cnt FROM notification_types")->fetch_assoc()['cnt'];
    echo "<p>✓ Table exists with " . $count . " notification types</p>";

    $types = $conn->query("SELECT type_name, type_display_name FROM notification_types ORDER BY type_name");
    echo "<table><tr><th>Type</th><th>Display Name</th></tr>";
    while ($row = $types->fetch_assoc()) {
        echo "<tr><td>" . htmlspecialchars($row['type_name']) . "</td><td>" . htmlspecialchars($row['type_display_name']) . "</td></tr>";
    }
    echo "</table>";
}
echo "</div>";

// Test 3: user_notifications table
$notif_table = false;
if ($conn) {
    $result = $conn->query("SHOW TABLES LIKE 'user_notifications'");
    $notif_table = $result && $result->num_rows > 0;
}

echo "<div class='test-item " . ($notif_table ? "pass" : "fail") . "'>
    <span class='status " . ($notif_table ? "pass" : "fail") . "'>user_notifications Table</span>";

if (!$notif_table && $conn) {
    echo "<p>Table not found. This table stores all user notifications.</p>";
} elseif ($notif_table) {
    $count = $conn->query("SELECT COUNT(*) as cnt FROM user_notifications")->fetch_assoc()['cnt'];
    echo "<p>✓ Table exists with " . $count . " total notifications</p>";

    $user_count = $conn->query("SELECT COUNT(DISTINCT user_id) as cnt FROM user_notifications")->fetch_assoc()['cnt'];
    echo "<p>✓ Notifications for " . $user_count . " unique users</p>";
}
echo "</div>";

// Test 4: user_notification_settings table
$settings_table = false;
if ($conn) {
    $result = $conn->query("SHOW TABLES LIKE 'user_notification_settings'");
    $settings_table = $result && $result->num_rows > 0;
}

echo "<div class='test-item " . ($settings_table ? "pass" : "warning") . "'>
    <span class='status " . ($settings_table ? "pass" : "warning") . "'>user_notification_settings Table</span>";

if (!$settings_table && $conn) {
    echo "<p>Optional table not found. Users can still receive notifications.</p>";
} elseif ($settings_table) {
    echo "<p>✓ Notification settings table exists</p>";
}
echo "</div>";

// Test 5: NotificationManager class
$manager_exists = file_exists('NotificationManager.php');

echo "<div class='test-item " . ($manager_exists ? "pass" : "fail") . "'>
    <span class='status " . ($manager_exists ? "pass" : "fail") . "'>NotificationManager Class</span>";

if (!$manager_exists) {
    echo "<p>NotificationManager.php not found</p>";
} else {
    require_once 'NotificationManager.php';
    $manager_loaded = class_exists('NotificationManager');
    echo "<p>" . ($manager_loaded ? "✓ Class loaded successfully" : "✗ Class failed to load") . "</p>";
}
echo "</div>";

// Test 6: Check user's notifications
if ($conn && $notif_table) {
    $userId = $_SESSION['user_id'];
    $user_notif_count = $conn->query("SELECT COUNT(*) as cnt FROM user_notifications WHERE user_id = {$userId}")->fetch_assoc()['cnt'];
    $user_unread_count = $conn->query("SELECT COUNT(*) as cnt FROM user_notifications WHERE user_id = {$userId} AND is_read = 0")->fetch_assoc()['cnt'];

    echo "<div class='test-item pass'>
        <span class='status pass'>Your Notifications</span>
        <p>Total: " . $user_notif_count . " | Unread: " . $user_unread_count . "</p>";

    if ($user_notif_count > 0) {
        echo "<table><tr><th>Title</th><th>Type</th><th>Status</th><th>Created</th></tr>";
        $recent = $conn->query("
            SELECT un.title, nt.type_display_name, IF(un.is_read=1, 'Read', 'Unread') as status, DATE_FORMAT(un.created_at, '%Y-%m-%d %H:%i') as created_at
            FROM user_notifications un
            JOIN notification_types nt ON un.type_id = nt.type_id
            WHERE un.user_id = {$userId}
            ORDER BY un.created_at DESC
            LIMIT 5
        ");

        while ($row = $recent->fetch_assoc()) {
            echo "<tr>
                <td>" . htmlspecialchars(substr($row['title'], 0, 50)) . "</td>
                <td>" . htmlspecialchars($row['type_display_name']) . "</td>
                <td>" . $row['status'] . "</td>
                <td>" . $row['created_at'] . "</td>
            </tr>";
        }
        echo "</table>";
    }
    echo "</div>";
}

// Summary
$all_pass = $conn && $types_table && $notif_table && $manager_exists;

echo "<div style='margin-top: 30px; padding: 20px; background: " . ($all_pass ? "#f1f8f6" : "#fef5f5") . "; border-radius: 4px;'>
    <h3>" . ($all_pass ? "✓ All Systems Operational" : "⚠ Issues Detected") . "</h3>";

if (!$all_pass) {
    echo "<p>The notification system is not fully set up. Missing components:</p>
    <ul style='margin-left: 20px;'>";

    if (!$types_table)
        echo "<li>notification_types table</li>";
    if (!$notif_table)
        echo "<li>user_notifications table</li>";
    if (!$manager_exists)
        echo "<li>NotificationManager.php</li>";

    echo "</ul>
    <p><strong>Solution:</strong> Run the setup script at the admin panel or contact your system administrator.</p>";
}

echo "</div>
    </div>
</body>
</html>";
?>