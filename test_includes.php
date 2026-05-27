<?php
// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Start session
session_start();

// Check includes one by one
echo "Starting includes test...\n";

try {
    echo "1. Including CYCLOAN_db.php...";
    require "CYCLOAN_db.php";
    echo " OK\n";
} catch (Throwable $e) {
    echo " FAIL: " . $e->getMessage() . "\n";
    exit(1);
}

try {
    echo "2. Including timezone_config.php...";
    require_once 'timezone_config.php';
    echo " OK\n";
} catch (Throwable $e) {
    echo " FAIL: " . $e->getMessage() . "\n";
    exit(1);
}

try {
    echo "3. Including credit_points_manager.php...";
    require "credit_points_manager.php";
    echo " OK\n";
} catch (Throwable $e) {
    echo " FAIL: " . $e->getMessage() . "\n";
    exit(1);
}

try {
    echo "4. Including NotificationManager.php...";
    require_once 'NotificationManager.php';
    echo " OK\n";
} catch (Throwable $e) {
    echo " FAIL: " . $e->getMessage() . "\n";
    exit(1);
}

echo "\nAll includes successful!\n";
echo "If you're seeing a 500 error, it's likely from:\n";
echo "1. Database query failure\n";
echo "2. Missing database tables\n";
echo "3. Authentication issue\n";
?>