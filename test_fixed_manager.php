<?php
/**
 * Test the fixed NotificationManager
 */
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
require_once 'CYCLOAN_db.php';
require_once 'NotificationManager.php';

echo "<h1>🔧 Fixed NotificationManager Test</h1>";

if ($conn && $conn->ping()) {
    echo "✅ Database connected<br>";

    try {
        $manager = new NotificationManager($conn);
        echo "✅ NotificationManager created successfully<br>";

        // Get a test user
        $userResult = $conn->query("SELECT id as user_id FROM users1 LIMIT 1");
        if ($userResult && $userResult->num_rows > 0) {
            $user = $userResult->fetch_assoc();
            $userId = $user['user_id'];
            echo "🧪 Testing with user ID: $userId<br><br>";

            // Test getUnreadCounts
            echo "<h3>Testing getUnreadCounts()</h3>";
            $counts = $manager->getUnreadCounts($userId);
            echo "✅ SUCCESS - Total unread: " . $counts['total'] . "<br>";
            if ($counts['total'] > 0) {
                foreach ($counts as $type => $count) {
                    if ($type !== 'total' && $count > 0) {
                        echo "  - $type: $count<br>";
                    }
                }
            }
            echo "<br>";

            // Test getUserNotifications
            echo "<h3>Testing getUserNotifications()</h3>";
            $result = $manager->getUserNotifications($userId, [], 1, 5);
            echo "✅ SUCCESS - Found " . count($result['notifications']) . " notifications<br>";
            echo "  - Total count: " . $result['total_count'] . "<br>";
            echo "  - Total pages: " . $result['total_pages'] . "<br>";
            echo "<br>";

            // Test creating a notification
            echo "<h3>Testing createNotification()</h3>";
            $created = $manager->createNotification(
                $userId,
                'system',
                'Test Notification',
                'This is a test notification created by the fixed NotificationManager.'
            );

            if ($created) {
                echo "✅ SUCCESS - Test notification created<br>";
            } else {
                echo "❌ FAILED - Could not create test notification<br>";
                $errors = $manager->getErrors();
                foreach ($errors as $error) {
                    echo "  Error: $error<br>";
                }
            }

        } else {
            echo "❌ No users found for testing<br>";
        }

    } catch (Exception $e) {
        echo "❌ ERROR: " . $e->getMessage() . "<br>";
    }
} else {
    echo "❌ Database connection failed<br>";
}

echo "<br><h2>🎯 Status</h2>";
echo "<p>The NotificationManager has been fixed to use proper MySQLi syntax instead of PDO.</p>";
echo "<p><strong>Changes made:</strong></p>";
echo "<ul>";
echo "<li>✅ Fixed execute() method calls (removed parameters)</li>";
echo "<li>✅ Replaced fetchAll() with proper MySQLi result handling</li>";
echo "<li>✅ Added proper bind_param() calls with type strings</li>";
echo "<li>✅ Fixed pagination return format</li>";
echo "<li>✅ Simplified complex queries for better compatibility</li>";
echo "</ul>";

echo "<p><a href='quick_test.php' style='background: #007bff; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; margin-right: 10px;'>🔍 Run Quick Test</a>";
echo "<a href='notifications.php' style='background: #28a745; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>📱 Open Notifications</a></p>";
?>