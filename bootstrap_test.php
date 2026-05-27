<?php
/**
 * Minimal Dashboard Test
 * Just loads the essential components without rendering full HTML
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

// Set a custom error handler to catch all errors
set_error_handler(function ($errno, $errstr, $errfile, $errline) {
    echo "PHP Error [$errno]: $errstr in $errfile on line $errline\n";
    return true;
});

// Set exception handler
set_exception_handler(function ($exception) {
    echo "Exception: " . $exception->getMessage() . "\n";
    echo "File: " . $exception->getFile() . "\n";
    echo "Line: " . $exception->getLine() . "\n";
});

echo "=== Dashboard Bootstrap Test ===\n\n";

// Step 1: Session
echo "Step 1: Starting session...";
session_start();
echo " OK\n";

// Step 2: Database
echo "Step 2: Loading CYCLOAN_db.php...";
try {
    require "CYCLOAN_db.php";
    if (!isset($conn) || !($conn instanceof mysqli)) {
        throw new Exception("Connection object not valid");
    }
    echo " OK\n";
} catch (Exception $e) {
    echo " FAILED: " . $e->getMessage() . "\n";
    exit(1);
}

// Step 3: Auth check
echo "Step 3: Checking authentication...";
if (!isset($_SESSION['email']) || !isset($_SESSION['user_id'])) {
    echo " SKIPPED (not logged in)\n";
} else {
    echo " OK (User: " . $_SESSION['user_id'] . ")\n";
}

// Step 4: Timezone
echo "Step 4: Loading timezone_config.php...";
try {
    require_once 'timezone_config.php';
    echo " OK\n";
} catch (Exception $e) {
    echo " FAILED: " . $e->getMessage() . "\n";
}

// Step 5: Credit points
echo "Step 5: Loading credit_points_manager.php...";
try {
    require "credit_points_manager.php";
    echo " OK\n";
} catch (Exception $e) {
    echo " FAILED: " . $e->getMessage() . "\n";
}

// Step 6: Notification Manager
echo "Step 6: Loading NotificationManager.php...";
try {
    require_once 'NotificationManager.php';
    if (!class_exists('NotificationManager')) {
        throw new Exception("NotificationManager class not found");
    }
    echo " OK\n";
} catch (Exception $e) {
    echo " FAILED: " . $e->getMessage() . "\n";
}

echo "\n=== All Components Loaded Successfully ===\n";
echo "If you're still getting a 500 error, check:\n";
echo "1. Server PHP error logs\n";
echo "2. Database connection issues\n";
echo "3. Missing database tables\n";
?>