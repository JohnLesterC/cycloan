<?php
/**
 * DATABASE CONNECTION TEST
 * 
 * This file tests if your database connection is working correctly.
 * Run: php test_db.php
 */

echo "========================================\n";
echo "DATABASE CONNECTION TEST\n";
echo "========================================\n\n";

// Display configuration
echo "Configuration:\n";
echo "  DB_HOST: localhost\n";
echo "  DB_USER: root\n";
echo "  DB_NAME: cycloan_db\n";
echo "  DB_PORT: 3306\n";
echo "\n";

// Test connection
echo "Attempting connection...\n";
$conn = mysqli_connect('localhost', 'root', '', 'cycloan_db', 3306);

if ($conn->connect_error) {
    echo "❌ CONNECTION FAILED!\n";
    echo "Error: " . $conn->connect_error . "\n";
    exit(1);
} else {
    echo "✅ CONNECTION SUCCESSFUL!\n\n";

    // Get server info
    echo "Server Info:\n";
    echo "  MySQL Version: " . mysqli_get_server_info($conn) . "\n";
    echo "  Selected Database: u455107563_cycloan_db1\n\n";

    // Check required tables
    echo "Checking Required Tables:\n";
    $tables = ['users1', 'otps', 'financial_info', 'income_sources', 'expenditure_types', 'spouses'];

    $missing_tables = [];
    foreach ($tables as $table) {
        $result = $conn->query("SHOW TABLES LIKE '$table'");
        if ($result && $result->num_rows > 0) {
            echo "  ✅ Table '$table' exists\n";
        } else {
            echo "  ❌ Table '$table' MISSING\n";
            $missing_tables[] = $table;
        }
    }

    echo "\n";

    if (count($missing_tables) > 0) {
        echo "⚠️  MISSING TABLES: " . implode(', ', $missing_tables) . "\n";
        echo "You need to import the database dump:\n";
        echo "  mysql -u root -p cycloan_db < database/cycloan_db.sql\n";
    } else {
        echo "✅ ALL REQUIRED TABLES EXIST!\n";
    }

    // Test a simple query
    echo "\nTesting Simple Query...\n";
    $test_result = $conn->query("SELECT 1 as test");
    if ($test_result) {
        echo "✅ Query execution works\n";
    } else {
        echo "❌ Query failed: " . $conn->error . "\n";
    }

    $conn->close();

    echo "\n========================================\n";
    echo "RESULT: ✅ DATABASE CONNECTION OK\n";
    echo "========================================\n";
}
?>