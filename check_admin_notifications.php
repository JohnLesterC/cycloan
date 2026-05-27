<?php
/**
 * CHECK ADMIN NOTIFICATIONS - Debug Status
 * Quickly verifies if admin2 notifications are being created and retrieved
 */

// No session check - diagnostic page
echo "<!DOCTYPE html><html><head>";
echo "<title>Admin2 Notifications Check</title>";
echo "<style>";
echo "body { font-family: Arial; margin: 20px; background: #f5f5f5; }";
echo ".box { background: white; padding: 20px; margin: 10px 0; border-radius: 5px; border-left: 4px solid #007bff; }";
echo ".success { border-left-color: #28a745; }";
echo ".error { border-left-color: #dc3545; }";
echo ".warning { border-left-color: #ffc107; }";
echo "table { width: 100%; border-collapse: collapse; }";
echo "table td, th { padding: 10px; border: 1px solid #ddd; }";
echo "th { background: #f1f1f1; }";
echo "</style></head><body>";
echo "<h1>Admin2 Notifications Diagnostic</h1>";
echo "<p>Time: " . date('Y-m-d H:i:s') . "</p>";

require_once 'CYCLOAN_db.php';

// 1. Get admin2 users
echo "<div class='box success'><h2>Admin2 Users</h2>";
$admins = $conn->query("SELECT id, email, first_name, last_name FROM admin2");
$admin_ids = [];
echo "<table>";
echo "<tr><th>ID</th><th>Email</th><th>Name</th></tr>";
while ($a = $admins->fetch_assoc()) {
    $admin_ids[] = $a['id'];
    echo "<tr><td>{$a['id']}</td><td>{$a['email']}</td><td>{$a['first_name']} {$a['last_name']}</td></tr>";
}
echo "</table>";
echo "<p><strong>Total: " . count($admin_ids) . " admin2 users (IDs: " . implode(', ', $admin_ids) . ")</strong></p>";
echo "</div>";

// 2. Check notifications for admin2
echo "<div class='box'><h2>Notifications for Admin2 Users</h2>";
if (!empty($admin_ids)) {
    $id_list = implode(',', $admin_ids);
    $notifs = $conn->query("
        SELECT un.notification_id, un.user_id, un.title, un.type_id, un.is_read, un.created_at
        FROM user_notifications un
        WHERE un.user_id IN ($id_list)
        ORDER BY un.created_at DESC
        LIMIT 10
    ");

    if ($notifs->num_rows > 0) {
        echo "<p style='color: green;'><strong>✓ Found {$notifs->num_rows} notifications for admin2</strong></p>";
        echo "<table>";
        echo "<tr><th>ID</th><th>User ID</th><th>Title</th><th>Read</th><th>Created</th></tr>";
        while ($n = $notifs->fetch_assoc()) {
            $read = $n['is_read'] ? 'Yes' : '<span style="color: red;">No</span>';
            echo "<tr><td>{$n['notification_id']}</td><td>{$n['user_id']}</td><td>" . substr($n['title'], 0, 40) . "</td><td>$read</td><td>{$n['created_at']}</td></tr>";
        }
        echo "</table>";
    } else {
        echo "<p style='color: red;'><strong>✗ NO NOTIFICATIONS FOR ADMIN2!</strong></p>";
        echo "<p>Admin2 IDs: " . implode(', ', $admin_ids) . "</p>";
        echo "<p><strong style='color: red;'>This is the problem - notifications are not being created for admin2 users!</strong></p>";
    }
} else {
    echo "<p style='color: red;'>No admin2 users found!</p>";
}
echo "</div>";

// 3. Check user (non-admin) notifications
echo "<div class='box'><h2>User Notifications (for comparison)</h2>";
$user_notifs = $conn->query("SELECT COUNT(*) as count FROM user_notifications WHERE user_id NOT IN (SELECT id FROM admin2) LIMIT 1");
$count_row = $user_notifs->fetch_assoc();
echo "<p>Regular users have " . $count_row['count'] . " notifications</p>";
echo "</div>";

// 4. Try to create a test notification
echo "<div class='box warning'><h2>Test: Create Notification for Admin2</h2>";
if (!empty($admin_ids)) {
    require_once 'NotificationManager.php';
    $nm = new NotificationManager($conn);

    $test_id = $admin_ids[0];
    echo "<p>Testing with Admin2 ID: $test_id</p>";

    $result = $nm->createNotification(
        $test_id,
        'document',
        'TEST NOTIFICATION - Document Resubmitted',
        'This is a test to verify notifications are being created for admin2',
        'high'
    );

    if ($result) {
        echo "<p style='color: green;'><strong>✓ Test notification created!</strong></p>";

        // Verify
        $verify = $conn->query("SELECT * FROM user_notifications WHERE user_id = $test_id ORDER BY created_at DESC LIMIT 1");
        if ($verify && $verify->num_rows > 0) {
            $n = $verify->fetch_assoc();
            echo "<p>✓ Verified - Notification ID: {$n['notification_id']}, Created: {$n['created_at']}</p>";
        }
    } else {
        echo "<p style='color: red;'><strong>✗ Failed to create test notification</strong></p>";
    }
}
echo "</div>";

echo "</body></html>";
?>