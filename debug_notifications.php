<?php
/**
 * Simple Notifications Error Diagnostic
 * Check what's causing the notifications page to fail
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>🔍 Notifications Page Error Diagnostic</h1>";

echo "<h2>1. Basic PHP Test</h2>";
echo "✅ PHP is working<br>";

echo "<h2>2. Session Test</h2>";
session_start();
if (isset($_SESSION['email'])) {
    echo "✅ Session active for: " . htmlspecialchars($_SESSION['email']) . "<br>";
} else {
    echo "❌ No active session - this might be the issue<br>";
    echo "<p><a href='index.php'>Login First</a></p>";
}

echo "<h2>3. Database Connection Test</h2>";
try {
    require_once 'CYCLOAN_db.php';
    if ($conn && $conn->ping()) {
        echo "✅ Database connection working<br>";
    } else {
        echo "❌ Database connection failed<br>";
    }
} catch (Exception $e) {
    echo "❌ Database error: " . $e->getMessage() . "<br>";
}

echo "<h2>4. NotificationManager Test</h2>";
try {
    require_once 'NotificationManager.php';
    $manager = new NotificationManager($conn);
    echo "✅ NotificationManager loaded successfully<br>";
} catch (Exception $e) {
    echo "❌ NotificationManager error: " . $e->getMessage() . "<br>";
}

echo "<h2>5. User Data Test</h2>";
if (isset($_SESSION['email'])) {
    try {
        $stmt = $conn->prepare("SELECT id as user_id, first_name, last_name FROM users1 WHERE email = ?");
        if ($stmt) {
            $stmt->bind_param("s", $_SESSION['email']);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows > 0) {
                $user = $result->fetch_assoc();
                echo "✅ User found: ID " . $user['user_id'] . ", " . $user['first_name'] . " " . $user['last_name'] . "<br>";
                
                // Test getting notifications
                echo "<h2>6. Notifications Query Test</h2>";
                $notificationsResult = $manager->getUserNotifications($user['user_id'], [], 1, 5);
                echo "✅ Notifications query successful<br>";
                echo "Found " . count($notificationsResult['notifications']) . " notifications<br>";
                
            } else {
                echo "❌ User not found in database<br>";
            }
            
            $stmt->close();
        } else {
            echo "❌ Failed to prepare user query: " . $conn->error . "<br>";
        }
    } catch (Exception $e) {
        echo "❌ User data error: " . $e->getMessage() . "<br>";
    }
} else {
    echo "⚠️ Skipped - no active session<br>";
}

echo "<h2>7. Required Files Check</h2>";
$required_files = [
    'CSS/notifications.css',
    'JAVASCRIPT/notifications.js',
    'api/notifications.php'
];

foreach ($required_files as $file) {
    if (file_exists($file)) {
        echo "✅ $file exists<br>";
    } else {
        echo "❌ $file missing<br>";
    }
}

echo "<h2>8. Try Simple Notifications Page</h2>";
echo "<p>If all above tests pass, try this minimal notifications page:</p>";
echo "<p><a href='simple_notifications.php' style='background: #007bff; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>Simple Notifications Test</a></p>";

echo "<hr>";
echo "<p><em>Diagnostic completed at " . date('Y-m-d H:i:s') . "</em></p>";
?>