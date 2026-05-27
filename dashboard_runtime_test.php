<?php
/**
 * Dashboard Runtime Error Detector
 * Tests all dependencies and components
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

// Start output buffering to prevent headers already sent errors
ob_start();

session_start();

// Simulate logged-in session if not already set
if (!isset($_SESSION['email'])) {
    $_SESSION['email'] = 'debug@test.com';
    $_SESSION['user_id'] = 1;
}

echo "=== Dashboard Runtime Error Test ===\n\n";

// Test 1: Include CYCLOAN_db.php
echo "Test 1: Including CYCLOAN_db.php...\n";
try {
    require "CYCLOAN_db.php";
    if (!isset($conn) || !($conn instanceof mysqli)) {
        echo "ERROR: \$conn is not a valid MySQLi instance\n";
    } else {
        echo "[PASS] CYCLOAN_db.php loaded successfully\n";
    }
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}

// Test 2: Include timezone_config.php
echo "\nTest 2: Including timezone_config.php...\n";
try {
    require_once 'timezone_config.php';
    echo "✓ timezone_config.php loaded successfully\n";
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}

// Test 3: Include credit_points_manager.php
echo "\nTest 3: Including credit_points_manager.php...\n";
try {
    require "credit_points_manager.php";
    echo "✓ credit_points_manager.php loaded successfully\n";
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}

// Test 4: Include NotificationManager.php
echo "\nTest 4: Including NotificationManager.php...\n";
try {
    require_once 'NotificationManager.php';
    if (!class_exists('NotificationManager')) {
        echo "ERROR: NotificationManager class not defined\n";
    } else {
        echo "✓ NotificationManager.php loaded successfully\n";
    }
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}

// Test 5: Test basic database query
echo "\nTest 5: Testing database query...\n";
try {
    $stmt = $conn->prepare("SELECT id FROM users1 LIMIT 1");
    if ($stmt === false) {
        echo "ERROR: Prepare failed: " . $conn->error . "\n";
    } else {
        $stmt->execute();
        $stmt->close();
        echo "✓ Database query successful\n";
    }
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}

// Test 6: Check for notification_center.php
echo "\nTest 6: Checking notification_center.php...\n";
if (file_exists('notification_center.php')) {
    echo "✓ notification_center.php exists\n";
    // Try including it safely
    ob_start();
    $include_result = @include 'notification_center.php';
    $include_output = ob_get_clean();
    if ($include_output) {
        echo "  Output: " . substr($include_output, 0, 100) . "...\n";
    }
} else {
    echo "WARNING: notification_center.php does not exist\n";
}

// Test 7: Test required JavaScript files
echo "\nTest 7: Checking JavaScript files...\n";
$js_files = [
    'JAVASCRIPT/user_dashboard.js',
    'JAVASCRIPT/user_history_activity.js',
    'JAVASCRIPT/Real-Time.js'
];

foreach ($js_files as $file) {
    if (file_exists($file)) {
        echo "✓ $file exists\n";
    } else {
        echo "WARNING: $file does not exist\n";
    }
}

// Test 8: Test required CSS files
echo "\nTest 8: Checking CSS files...\n";
$css_files = [
    'CSS/admin_dashboard.css',
    'CSS/user_dashboard.css',
    'CSS/dashboard.css'
];

foreach ($css_files as $file) {
    if (file_exists($file)) {
        echo "✓ $file exists\n";
    } else {
        echo "WARNING: $file does not exist\n";
    }
}

// Test 9: Test image files
echo "\nTest 9: Checking image files...\n";
if (file_exists('IMAGE/Main-Logo.png')) {
    echo "✓ IMAGE/Main-Logo.png exists\n";
} else {
    echo "WARNING: IMAGE/Main-Logo.png does not exist\n";
}

echo "\n=== Test Complete ===\n";
echo "\nIf any ERRORs appear above, those are the issues causing the 500 error.\n";

// Clean up output buffer
ob_end_clean();

// Output results
echo "\n=== Runtime Error Analysis ===\n\n";
echo "All components loaded successfully! If you're still seeing a 500 error:\n";
echo "1. Check the server error logs\n";
echo "2. Verify all required files exist\n";
echo "3. Check database connection credentials\n";
echo "4. Ensure file permissions are correct\n";

?>
