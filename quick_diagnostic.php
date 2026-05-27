<?php
/**
 * Quick Dashboard Diagnostic
 */
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();

// Simulate session
if (!isset($_SESSION['email'])) {
    $_SESSION['email'] = 'test@example.com';
    $_SESSION['user_id'] = 1;
}

echo "Dashboard Diagnostic Report\n";
echo "===========================\n\n";

// Test 1: File Existence
echo "1. File Existence Check:\n";
$files = [
    'CYCLOAN_db.php' => 'required',
    'timezone_config.php' => 'required',
    'credit_points_manager.php' => 'required',
    'NotificationManager.php' => 'required',
    'notification_center.php' => 'optional',
    'CSS/admin_dashboard.css' => 'required',
    'CSS/user_dashboard.css' => 'required',
    'CSS/dashboard.css' => 'required'
];

foreach ($files as $file => $type) {
    $status = file_exists($file) ? 'OK' : 'MISSING';
    echo "  [$status] $file ($type)\n";
}

// Test 2: Include Tests
echo "\n2. Include Tests:\n";

$errors = [];

try {
    require_once 'CYCLOAN_db.php';
    echo "  [OK] CYCLOAN_db.php included\n";
} catch (Exception $e) {
    echo "  [ERROR] CYCLOAN_db.php: " . $e->getMessage() . "\n";
    $errors[] = $e->getMessage();
}

try {
    require_once 'timezone_config.php';
    echo "  [OK] timezone_config.php included\n";
} catch (Exception $e) {
    echo "  [ERROR] timezone_config.php: " . $e->getMessage() . "\n";
    $errors[] = $e->getMessage();
}

try {
    require_once 'credit_points_manager.php';
    echo "  [OK] credit_points_manager.php included\n";
} catch (Exception $e) {
    echo "  [ERROR] credit_points_manager.php: " . $e->getMessage() . "\n";
    $errors[] = $e->getMessage();
}

try {
    require_once 'NotificationManager.php';
    echo "  [OK] NotificationManager.php included\n";
} catch (Exception $e) {
    echo "  [ERROR] NotificationManager.php: " . $e->getMessage() . "\n";
    $errors[] = $e->getMessage();
}

// Test 3: Summary
echo "\n3. Summary:\n";
if (count($errors) === 0) {
    echo "  [OK] All required includes are working correctly\n";
    echo "  The 500 error should be resolved now.\n";
} else {
    echo "  [ERROR] Found " . count($errors) . " issue(s):\n";
    foreach ($errors as $error) {
        echo "    - $error\n";
    }
}

echo "\n";

?>