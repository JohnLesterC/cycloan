<?php
session_start();
echo "<h1>Debug Notifications Page</h1>";
echo "<p>Session email: " . ($_SESSION['email'] ?? 'Not set') . "</p>";
echo "<p>Session user_id: " . ($_SESSION['user_id'] ?? 'Not set') . "</p>";

require_once 'CYCLOAN_db.php';
echo "<p>Database connection: " . (isset($conn) ? 'Connected' : 'Not connected') . "</p>";

if (isset($conn)) {
    echo "<p>Connection type: " . get_class($conn) . "</p>";
}

// Test if NotificationManager works
try {
    require_once 'NotificationManager.php';
    echo "<p>NotificationManager loaded successfully</p>";
} catch (Exception $e) {
    echo "<p>NotificationManager error: " . $e->getMessage() . "</p>";
}

phpinfo();
?>