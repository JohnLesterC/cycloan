<?php
/**
 * Quick Notification System Test
 * Simple test to verify the notification system is working
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
require_once 'CYCLOAN_db.php';

echo "<h1>🔍 Quick Notification System Test</h1>";

// Test database connection
echo "<h2>Database Connection</h2>";
if ($conn && $conn->ping()) {
    echo "✅ Database connected successfully<br>";
} else {
    echo "❌ Database connection failed<br>";
    exit;
}

// Test users1 table exists
echo "<h2>Users Table Test</h2>";
$tablesResult = $conn->query("SHOW TABLES LIKE 'users1'");
if ($tablesResult && $tablesResult->num_rows > 0) {
    echo "✅ users1 table exists<br>";

    // Check if there are any users
    $userCount = $conn->query("SELECT COUNT(*) as count FROM users1");
    if ($userCount) {
        $count = $userCount->fetch_assoc()['count'];
        echo "✅ Found $count users in database<br>";
    }
} else {
    echo "❌ users1 table not found<br>";
}

// Test notification tables
echo "<h2>Notification Tables Test</h2>";
$notificationTables = ['notification_types', 'user_notifications', 'notification_templates'];
$allTablesExist = true;

foreach ($notificationTables as $table) {
    $result = $conn->query("SHOW TABLES LIKE '$table'");
    if ($result && $result->num_rows > 0) {
        echo "✅ $table exists<br>";
    } else {
        echo "❌ $table missing<br>";
        $allTablesExist = false;
    }
}

// Test NotificationManager
echo "<h2>NotificationManager Test</h2>";
if (file_exists('NotificationManager.php')) {
    echo "✅ NotificationManager.php file exists<br>";

    try {
        require_once 'NotificationManager.php';
        $manager = new NotificationManager($conn);
        echo "✅ NotificationManager instantiated successfully<br>";

        // Test with a sample user if available
        $sampleUser = $conn->query("SELECT id as user_id FROM users1 LIMIT 1");
        if ($sampleUser && $sampleUser->num_rows > 0) {
            $user = $sampleUser->fetch_assoc();
            $userId = $user['user_id'];

            echo "🧪 Testing with user ID: $userId<br>";

            try {
                $counts = $manager->getUnreadCounts($userId);
                echo "✅ getUnreadCounts() works - Total: " . $counts['total'] . "<br>";
            } catch (Exception $e) {
                echo "❌ getUnreadCounts() error: " . $e->getMessage() . "<br>";
            }

            try {
                $notifications = $manager->getUserNotifications($userId, [], 1, 5);
                echo "✅ getUserNotifications() works - Found: " . count($notifications['notifications']) . " notifications<br>";
            } catch (Exception $e) {
                echo "❌ getUserNotifications() error: " . $e->getMessage() . "<br>";
            }
        } else {
            echo "⚠️ No users available for testing<br>";
        }

    } catch (Exception $e) {
        echo "❌ NotificationManager error: " . $e->getMessage() . "<br>";
    }
} else {
    echo "❌ NotificationManager.php file not found<br>";
}

// Final status
echo "<h2>🎯 Test Results</h2>";
if ($allTablesExist && $conn) {
    echo "<div style='background: #d4edda; color: #155724; padding: 15px; border-radius: 5px;'>";
    echo "<strong>✅ SYSTEM IS READY!</strong><br>";
    echo "Your notification system appears to be working correctly.";
    echo "</div>";

    echo "<p><a href='notifications.php' style='background: #007bff; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; margin-right: 10px;'>📱 Open Notifications</a>";
    echo "<a href='user_dashboard.php' style='background: #28a745; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>🏠 Dashboard</a></p>";
} else {
    echo "<div style='background: #f8d7da; color: #721c24; padding: 15px; border-radius: 5px;'>";
    echo "<strong>⚠️ ISSUES DETECTED</strong><br>";
    echo "Some components are missing or not working properly. Please check the results above.";
    echo "</div>";

    echo "<p><a href='cleanup_notifications_db.php' style='background: #ffc107; color: #212529; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>🔧 Run Cleanup</a></p>";
}

echo "<p><em>Test completed at " . date('Y-m-d H:i:s') . "</em></p>";
?>