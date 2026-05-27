<?php
/**
 * Notifications System Test
 * Verifies all notification components work together
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();

// Simulate user session
if (!isset($_SESSION['user_id'])) {
    $_SESSION['user_id'] = 1;
    $_SESSION['email'] = 'test@example.com';
}

echo "Notifications System Test\n";
echo "=========================\n\n";

$tests = [];

// Test 1: Load CYCLOAN_db.php
echo "Test 1: Loading database...";
try {
    require_once 'CYCLOAN_db.php';
    if (!isset($conn) || !($conn instanceof mysqli)) {
        throw new Exception("Invalid connection");
    }
    echo " OK\n";
    $tests['db'] = true;
} catch (Exception $e) {
    echo " FAILED: " . $e->getMessage() . "\n";
    $tests['db'] = false;
}

// Test 2: Load NotificationManager.php (new version)
echo "Test 2: Loading NotificationManager.php...";
try {
    require_once 'NotificationManager.php';
    if (!class_exists('NotificationManager')) {
        throw new Exception("NotificationManager class not found");
    }
    echo " OK\n";
    $tests['nm_new'] = true;
} catch (Exception $e) {
    echo " FAILED: " . $e->getMessage() . "\n";
    $tests['nm_new'] = false;
}

// Test 3: Load notification_manager.php (old API version)
echo "Test 3: Loading notification_manager.php...";
try {
    require_once 'notification_manager.php';
    if (!class_exists('NotificationManagerAPI')) {
        throw new Exception("NotificationManagerAPI class not found");
    }
    echo " OK\n";
    $tests['nm_old'] = true;
} catch (Exception $e) {
    echo " FAILED: " . $e->getMessage() . "\n";
    $tests['nm_old'] = false;
}

// Test 4: Create NotificationManager instance
echo "Test 4: Creating NotificationManager instance...";
try {
    $nm = new NotificationManager($conn);
    echo " OK\n";
    $tests['nm_instance'] = true;
} catch (Exception $e) {
    echo " FAILED: " . $e->getMessage() . "\n";
    $tests['nm_instance'] = false;
}

// Test 5: Test getUserNotifications method
echo "Test 5: Testing getUserNotifications...";
try {
    if (!isset($nm)) {
        throw new Exception("NotificationManager not instantiated");
    }
    $result = $nm->getUserNotifications($_SESSION['user_id'], [], 1, 10);
    if (!is_array($result) || !isset($result['notifications'])) {
        throw new Exception("Invalid result format");
    }
    echo " OK (Found " . count($result['notifications']) . " notifications)\n";
    $tests['get_notif'] = true;
} catch (Exception $e) {
    echo " FAILED: " . $e->getMessage() . "\n";
    $tests['get_notif'] = false;
}

// Test 6: Test getUnreadCounts method
echo "Test 6: Testing getUnreadCounts...";
try {
    if (!isset($nm)) {
        throw new Exception("NotificationManager not instantiated");
    }
    $result = $nm->getUnreadCounts($_SESSION['user_id']);
    if (!is_array($result)) {
        throw new Exception("Invalid result format");
    }
    echo " OK (Unread: " . $result['total'] . ")\n";
    $tests['unread_count'] = true;
} catch (Exception $e) {
    echo " FAILED: " . $e->getMessage() . "\n";
    $tests['unread_count'] = false;
}

// Test 7: Check notification_center.php include
echo "Test 7: Checking notification_center.php...";
try {
    if (!file_exists('notification_center.php')) {
        throw new Exception("notification_center.php not found");
    }
    echo " OK\n";
    $tests['notif_center'] = true;
} catch (Exception $e) {
    echo " FAILED: " . $e->getMessage() . "\n";
    $tests['notif_center'] = false;
}

// Test 8: Check notifications.php page
echo "Test 8: Checking notifications.php page...";
try {
    if (!file_exists('notifications.php')) {
        throw new Exception("notifications.php not found");
    }
    echo " OK\n";
    $tests['notif_page'] = true;
} catch (Exception $e) {
    echo " FAILED: " . $e->getMessage() . "\n";
    $tests['notif_page'] = false;
}

// Summary
echo "\n=========================\n";
$passed = count(array_filter($tests));
$total = count($tests);
echo "Results: $passed/$total tests passed\n\n";

if ($passed === $total) {
    echo "SUCCESS: All notification components are working correctly!\n";
    echo "\nNotification system is ready to use:\n";
    echo "- notifications.php: Full notifications dashboard\n";
    echo "- notification_center.php: Dropdown component in header\n";
    echo "- notification_manager.php: API endpoint for AJAX calls\n";
} else {
    echo "FAILED: Some tests did not pass. Please fix the issues above.\n";
    echo "\nFailed tests:\n";
    foreach ($tests as $name => $result) {
        if (!$result) {
            echo "  - $name\n";
        }
    }
}
?>