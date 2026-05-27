<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "Starting debug test...\n";

session_start();
echo "Session started OK\n";

try {
    require "CYCLOAN_db.php";
    echo "CYCLOAN_db.php included OK\n";
} catch (Exception $e) {
    echo "Error with CYCLOAN_db.php: " . $e->getMessage() . "\n";
}

try {
    require_once 'timezone_config.php';
    echo "timezone_config.php included OK\n";
} catch (Exception $e) {
    echo "Error with timezone_config.php: " . $e->getMessage() . "\n";
}

try {
    require "credit_points_manager.php";
    echo "credit_points_manager.php included OK\n";
} catch (Exception $e) {
    echo "Error with credit_points_manager.php: " . $e->getMessage() . "\n";
}

try {
    require_once 'NotificationManager.php';
    echo "NotificationManager.php included OK\n";
} catch (Exception $e) {
    echo "Error with NotificationManager.php: " . $e->getMessage() . "\n";
}

// Test MySQLi connection
if (!isset($conn)) {
    echo "Error: \$conn variable not set\n";
} elseif (!($conn instanceof mysqli)) {
    echo "Error: \$conn is not a MySQLi instance\n";
    echo "Type: " . gettype($conn) . "\n";
} else {
    echo "MySQLi connection OK\n";
}

// Test session variables
if (!isset($_SESSION['email'])) {
    echo "Warning: No email in session\n";
} else {
    echo "Session email: " . $_SESSION['email'] . "\n";
}

if (!isset($_SESSION['user_id'])) {
    echo "Warning: No user_id in session\n";
} else {
    echo "Session user_id: " . $_SESSION['user_id'] . "\n";
}

echo "Debug test completed successfully\n";
?>