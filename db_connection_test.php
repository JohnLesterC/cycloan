<?php
/**
 * DATABASE CONNECTION DIAGNOSTIC TEST
 * 
 * This script tests:
 * 1. Database connection
 * 2. Connection persistence
 * 3. Query execution capability
 * 4. Prepared statement functionality
 * 
 * ⚠️  SECURITY: Delete this file immediately after testing!
 */

ob_start();
date_default_timezone_set('Asia/Manila');

echo "=== DATABASE CONNECTION DIAGNOSTIC TEST ===\n\n";

// Test 1: Connection parameters
echo "✓ Test 1: Connection Parameters\n";
$host = 'localhost';
$database = 'cycloan_db';
$username = 'root';
$password = '';
echo "  Host: $host\n";
echo "  Database: $database\n";
echo "  Username: $username\n";
echo "  Password: " . str_repeat("*", strlen($password)) . "\n\n";

// Test 2: Create connection
echo "✓ Test 2: Create Connection\n";
try {
    $conn = new mysqli($host, $username, $password, $database);

    if ($conn->connect_error) {
        echo "  ❌ FAILED: Connection error - " . $conn->connect_error . "\n";
        exit(1);
    }

    echo "  ✅ SUCCESS: Connected to database\n";
    echo "  Connection ID: " . $conn->thread_id . "\n\n";
} catch (Exception $e) {
    echo "  ❌ FAILED: " . $e->getMessage() . "\n";
    exit(1);
}

// Test 3: Set timezone
echo "✓ Test 3: Set MySQL Timezone\n";
$tz_result = $conn->query("SET SESSION time_zone = '+08:00'");
if ($tz_result === false) {
    echo "  ⚠️  WARNING: Could not set timezone - " . $conn->error . "\n";
} else {
    echo "  ✅ SUCCESS: Timezone set to +08:00 (Manila)\n\n";
}

// Test 4: Test Ping
echo "✓ Test 4: Connection Ping\n";
if ($conn->ping()) {
    echo "  ✅ SUCCESS: Connection is alive\n\n";
} else {
    echo "  ❌ FAILED: Connection ping failed\n";
    exit(1);
}

// Test 5: Simple SELECT query
echo "✓ Test 5: Simple SELECT Query\n";
$result = $conn->query("SELECT 1 as test_value");
if ($result === false) {
    echo "  ❌ FAILED: Query error - " . $conn->error . "\n";
    exit(1);
}
$row = $result->fetch_assoc();
echo "  ✅ SUCCESS: Query executed\n";
echo "  Result: " . $row['test_value'] . "\n\n";

// Test 6: Prepared statement
echo "✓ Test 6: Prepared Statement\n";
$stmt = $conn->prepare("SELECT COUNT(*) as total FROM admin1");
if ($stmt === false) {
    echo "  ❌ FAILED: Prepare failed - " . $conn->error . "\n";
    exit(1);
}
echo "  ✅ SUCCESS: Prepared statement created\n";
$stmt->execute();
$result = $stmt->get_result();
$row = $result->fetch_assoc();
echo "  Total admin1 records: " . $row['total'] . "\n";
$stmt->close();
echo "\n";

// Test 7: Parameterized query
echo "✓ Test 7: Parameterized Query with bind_param\n";
$test_id = 1;
$stmt = $conn->prepare("SELECT COUNT(*) as total FROM users1 WHERE id = ?");
if ($stmt === false) {
    echo "  ❌ FAILED: Prepare failed - " . $conn->error . "\n";
    exit(1);
}
$stmt->bind_param("i", $test_id);
$stmt->execute();
$result = $stmt->get_result();
$row = $result->fetch_assoc();
echo "  ✅ SUCCESS: Parameterized query executed\n";
echo "  Records with id=$test_id: " . $row['total'] . "\n\n";
$stmt->close();

// Test 8: Connection state after operations
echo "✓ Test 8: Connection State After Operations\n";
if ($conn->ping()) {
    echo "  ✅ SUCCESS: Connection still alive after operations\n";
    echo "  Thread ID: " . $conn->thread_id . "\n";
} else {
    echo "  ❌ FAILED: Connection lost\n";
}

$conn->close();
echo "\n✅ ALL TESTS PASSED - Database connection is working correctly!\n";
echo "\n⚠️  DELETE this file after testing: db_connection_test.php\n";
?>