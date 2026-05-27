<?php
// Dashboard Component Test
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();

// Simulate logged-in user for testing
if (!isset($_SESSION['email'])) {
    $_SESSION['email'] = 'test@example.com';
    $_SESSION['user_id'] = 1;
}

echo "Dashboard Load Test\n";
echo "==================\n\n";

$tests_passed = 0;
$tests_failed = 0;

// Test 1: CYCLOAN_db.php
echo "Test 1: Loading CYCLOAN_db.php...";
try {
    require "CYCLOAN_db.php";
    if (!isset($conn) || !($conn instanceof mysqli)) {
        echo " FAILED (Invalid connection)\n";
        $tests_failed++;
    } else {
        echo " PASSED\n";
        $tests_passed++;
    }
} catch (Throwable $e) {
    echo " FAILED (" . $e->getMessage() . ")\n";
    $tests_failed++;
}

// Test 2: timezone_config.php
echo "Test 2: Loading timezone_config.php...";
try {
    require_once 'timezone_config.php';
    echo " PASSED\n";
    $tests_passed++;
} catch (Throwable $e) {
    echo " FAILED (" . $e->getMessage() . ")\n";
    $tests_failed++;
}

// Test 3: credit_points_manager.php
echo "Test 3: Loading credit_points_manager.php...";
try {
    require "credit_points_manager.php";
    echo " PASSED\n";
    $tests_passed++;
} catch (Throwable $e) {
    echo " FAILED (" . $e->getMessage() . ")\n";
    $tests_failed++;
}

// Test 4: NotificationManager.php
echo "Test 4: Loading NotificationManager.php...";
try {
    require_once 'NotificationManager.php';
    if (!class_exists('NotificationManager')) {
        echo " FAILED (Class not found)\n";
        $tests_failed++;
    } else {
        echo " PASSED\n";
        $tests_passed++;
    }
} catch (Throwable $e) {
    echo " FAILED (" . $e->getMessage() . ")\n";
    $tests_failed++;
}

// Test 5: Database connectivity
echo "Test 5: Database connectivity...";
try {
    if (!isset($conn)) {
        throw new Exception("Connection not initialized");
    }
    $result = $conn->query("SELECT 1");
    if ($result) {
        echo " PASSED\n";
        $tests_passed++;
    } else {
        echo " FAILED (" . $conn->error . ")\n";
        $tests_failed++;
    }
} catch (Throwable $e) {
    echo " FAILED (" . $e->getMessage() . ")\n";
    $tests_failed++;
}

echo "\n==================\n";
echo "Results: " . $tests_passed . " passed, " . $tests_failed . " failed\n";

if ($tests_failed === 0) {
    echo "All tests passed! Dashboard should load without errors.\n";
} else {
    echo "Some tests failed. Fix the issues above.\n";
}
?>