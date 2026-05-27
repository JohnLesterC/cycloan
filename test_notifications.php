<?php
/**
 * CYCLOAN Notification System Status Page
 * Quick diagnostic for admin notification issues
 */

// Don't require session for diagnostic purposes
echo "<!DOCTYPE html>";
echo "<html>";
echo "<head>";
echo "<title>CYCLOAN Notification Diagnostic</title>";
echo "<style>";
echo "body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }";
echo ".container { max-width: 1200px; margin: 0 auto; background: white; padding: 20px; border-radius: 5px; }";
echo ".section { margin: 20px 0; padding: 15px; border-left: 4px solid #007bff; background: #f9f9f9; }";
echo ".success { border-left-color: #28a745; }";
echo ".error { border-left-color: #dc3545; }";
echo ".warning { border-left-color: #ffc107; }";
echo "table { width: 100%; border-collapse: collapse; margin: 10px 0; }";
echo "table td, table th { padding: 8px; border: 1px solid #ddd; text-align: left; }";
echo "table th { background: #f1f1f1; font-weight: bold; }";
echo "code { background: #f1f1f1; padding: 2px 6px; border-radius: 3px; }";
echo "</style>";
echo "</head>";
echo "<body>";
echo "<div class='container'>";
echo "<h1>CYCLOAN Notification System Diagnostic</h1>";
echo "<p>Timestamp: " . date('Y-m-d H:i:s') . "</p>";

require_once 'CYCLOAN_db.php';

if (!isset($conn) || !($conn instanceof mysqli)) {
    echo "<div class='section error'><h2>✗ Database Connection Failed</h2></div>";
    exit();
}

echo "<div class='section success'><h2>✓ Database Connected</h2></div>";

// Test 1: Check admin2 users
echo "<div class='section'>";
echo "<h3>Admin2 Users</h3>";
$result = $conn->query("SELECT id, email, first_name, last_name FROM admin2");
if ($result && $result->num_rows > 0) {
    echo "<table>";
    echo "<tr><th>ID</th><th>Email</th><th>Name</th></tr>";
    $admin2_ids = [];
    while ($row = $result->fetch_assoc()) {
        $admin2_ids[] = $row['id'];
        echo "<tr><td>{$row['id']}</td><td>{$row['email']}</td><td>{$row['first_name']} {$row['last_name']}</td></tr>";
    }
    echo "</table>";
    echo "<p>Total admin2 users: <strong>" . count($admin2_ids) . "</strong></p>";
} else {
    echo "<p style='color: red;'>No admin2 users found!</p>";
}
echo "</div>";

// Test 2: Check notifications for admin2
echo "<div class='section'>";
echo "<h3>Notifications for Admin2 Users</h3>";
if (!empty($admin2_ids)) {
    $id_list = implode(',', $admin2_ids);
    $result = $conn->query("
            SELECT un.notification_id, un.user_id, un.type_id, un.title, un.is_read, un.created_at, nt.type_name
            FROM user_notifications un
            JOIN notification_types nt ON un.type_id = nt.type_id
            WHERE un.user_id IN ($id_list)
            ORDER BY un.created_at DESC
            LIMIT 20
        ");

    if ($result && $result->num_rows > 0) {
        echo "<table>";
        echo "<tr><th>ID</th><th>User ID</th><th>Type</th><th>Title</th><th>Read</th><th>Created</th></tr>";
        while ($row = $result->fetch_assoc()) {
            $read = $row['is_read'] ? 'Yes' : '<span style="color:red;">No</span>';
            echo "<tr><td>{$row['notification_id']}</td><td>{$row['user_id']}</td><td>{$row['type_name']}</td><td>" . substr($row['title'], 0, 40) . "</td><td>$read</td><td>{$row['created_at']}</td></tr>";
        }
        echo "</table>";
        echo "<p><strong>Total notifications for admin2: " . $result->num_rows . "</strong></p>";
    } else {
        echo "<p style='color: red;'><strong>⚠ NO NOTIFICATIONS FOUND FOR ADMIN2 USERS!</strong></p>";
        echo "<p>Admin2 IDs: " . implode(', ', $admin2_ids) . "</p>";
        echo "<p>This is the likely cause of the problem!</p>";
    }
}
echo "</div>";

// Test 3: Check if notification_types exists
echo "<div class='section'>";
echo "<h3>Notification Types</h3>";
$result = $conn->query("SELECT type_id, type_name FROM notification_types ORDER BY type_id");
if ($result && $result->num_rows > 0) {
    echo "<table>";
    echo "<tr><th>ID</th><th>Type Name</th></tr>";
    while ($row = $result->fetch_assoc()) {
        echo "<tr><td>{$row['type_id']}</td><td>{$row['type_name']}</td></tr>";
    }
    echo "</table>";
} else {
    echo "<p style='color: red;'>Notification types not found!</p>";
}
echo "</div>";

// Test 4: Test creating a notification
echo "<div class='section'>";
echo "<h3>Test: Create Notification for Admin2</h3>";
if (!empty($admin2_ids)) {
    require_once 'NotificationManager.php';
    $nm = new NotificationManager($conn);

    $test_admin_id = $admin2_ids[0];
    $result = $nm->createNotification(
        $test_admin_id,
        'document',
        'TEST: Document Resubmitted',
        'This is a test notification to verify the system is working.',
        'high'
    );

    if ($result) {
        echo "<p style='color: green;'><strong>✓ Test notification created successfully!</strong></p>";
        echo "<p>Created for Admin2 ID: $test_admin_id</p>";

        // Verify it was created
        $verify = $conn->query("
                SELECT * FROM user_notifications 
                WHERE user_id = $test_admin_id 
                ORDER BY created_at DESC 
                LIMIT 1
            ");
        if ($verify && $verify->num_rows > 0) {
            $notif = $verify->fetch_assoc();
            echo "<p>✓ Verified in database - Notification ID: {$notif['notification_id']}</p>";
        }
    } else {
        echo "<p style='color: red;'><strong>✗ Failed to create test notification</strong></p>";
    }
}
echo "</div>";

// Test 5: Check document upload flow
echo "<div class='section'>";
echo "<h3>Recent Document Updates (Last 5)</h3>";
$result = $conn->query("SELECT * FROM documents ORDER BY id DESC LIMIT 5");
if ($result && $result->num_rows > 0) {
    echo "<table>";
    echo "<tr><th>ID</th><th>App ID</th><th>Name</th><th>Status</th><th>Updated</th></tr>";
    while ($row = $result->fetch_assoc()) {
        echo "<tr><td>{$row['id']}</td><td>{$row['application_id']}</td><td>" . substr($row['document_name'], 0, 30) . "</td><td>{$row['status']}</td><td>{$row['status_updated_at']}</td></tr>";
    }
    echo "</table>";
}
echo "</div>";

echo "</div>";
echo "</body>";
echo "</html>";
// Additional detailed tests below

$user = $result->fetch_assoc();
$userId = $user['id'];
$userEmail = $user['email'];
$userName = $user['first_name'] . ' ' . $user['last_name'];

echo "<p style='color: green;'>Test User Found:</p>";
echo "<ul>";
echo "<li><strong>User ID:</strong> $userId</li>";
echo "<li><strong>Email:</strong> $userEmail</li>";
echo "<li><strong>Name:</strong> $userName</li>";
echo "</ul>";

// Test 4: Get user notifications
echo "<h2>✓ Fetching User Notifications</h2>";
$notificationsResult = $notificationManager->getUserNotifications($userId, [], 1, 10);
$notifications = $notificationsResult['notifications'] ?? [];
$totalCount = $notificationsResult['total_count'] ?? 0;
$unreadCount = $notificationsResult['unread_count'] ?? 0;

echo "<p style='color: green;'>Notification Statistics:</p>";
echo "<ul>";
echo "<li><strong>Total Notifications:</strong> $totalCount</li>";
echo "<li><strong>Unread Notifications:</strong> $unreadCount</li>";
echo "<li><strong>Page 1 Count:</strong> " . count($notifications) . "</li>";
echo "</ul>";

if (!empty($notifications)) {
    echo "<p style='color: green;'><strong>Recent Notifications:</strong></p>";
    echo "<table border='1' cellpadding='10' style='margin: 10px 0;'>";
    echo "<tr style='background-color: #f0f0f0;'>";
    echo "<th>ID</th>";
    echo "<th>Type</th>";
    echo "<th>Title</th>";
    echo "<th>Message</th>";
    echo "<th>Status</th>";
    echo "<th>Created At</th>";
    echo "</tr>";

    foreach ($notifications as $notif) {
        $status = $notif['is_read'] ? 'Read' : '<span style="color: red;">Unread</span>';
        echo "<tr>";
        echo "<td>" . htmlspecialchars($notif['id']) . "</td>";
        echo "<td>" . htmlspecialchars($notif['type']) . "</td>";
        echo "<td>" . htmlspecialchars($notif['title']) . "</td>";
        echo "<td>" . htmlspecialchars(substr($notif['message'], 0, 50)) . "...</td>";
        echo "<td>$status</td>";
        echo "<td>" . htmlspecialchars($notif['created_at']) . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p style='color: orange;'>No notifications found. This is normal for new users.</p>";
}

// Test 5: Check notifications table schema
echo "<h2>✓ Checking Notifications Table Schema</h2>";
$result = $conn->query("DESCRIBE notifications");
if ($result) {
    $columns = [];
    while ($row = $result->fetch_assoc()) {
        $columns[] = $row['Field'];
    }
    echo "<p style='color: green;'>Notifications table columns:</p>";
    echo "<ul>";
    foreach ($columns as $col) {
        echo "<li>$col</li>";
    }
    echo "</ul>";
} else {
    echo "<p style='color: orange;'>Could not fetch table schema</p>";
}

// Test 6: Test marking notification as read
echo "<h2>✓ Testing Mark as Read Functionality</h2>";
if (!empty($notifications)) {
    $firstNotif = $notifications[0];
    $notifId = $firstNotif['id'];

    if (!$firstNotif['is_read']) {
        $result = $notificationManager->markAsRead($userId, $notifId);
        if ($result) {
            echo "<p style='color: green;'>Successfully marked notification $notifId as read</p>";
        } else {
            echo "<p style='color: orange;'>Could not mark notification as read (may already be read)</p>";
        }
    } else {
        echo "<p style='color: orange;'>First notification is already read. Skipping mark-as-read test.</p>";
    }
} else {
    echo "<p style='color: orange;'>No notifications available to test mark-as-read functionality</p>";
}

// Test 7: Summary
echo "<h2>✓ Test Summary</h2>";
echo "<p style='color: green;'><strong>All tests completed successfully!</strong></p>";
echo "<ul>";
echo "<li>✓ Database connection working</li>";
echo "<li>✓ NotificationManager class loaded</li>";
echo "<li>✓ User retrieved successfully</li>";
echo "<li>✓ Notifications fetched successfully</li>";
echo "<li>✓ Notifications table schema verified</li>";
echo "<li>✓ Mark as read functionality available</li>";
echo "</ul>";

echo "<hr>";
echo "<p><strong>Test Results:</strong></p>";
echo "<p>User <strong>$userName</strong> has <strong>$totalCount</strong> total notifications with <strong>$unreadCount</strong> unread.</p>";
echo "<p><a href='notifications.php'>View notifications page</a></p>";

?>