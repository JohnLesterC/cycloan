<?php
// Test database connection
echo "Testing database connection...\n";
echo "Host: localhost\n";
echo "User: root\n";
echo "Database: cycloan_db\n\n";

$host = 'localhost';
$database = 'cycloan_db';
$username = 'root';
$password = '';

// Test connection
$conn = new mysqli($host, $username, $password);

if ($conn->connect_error) {
    echo "❌ Connection FAILED: " . $conn->connect_error . "\n";
    exit(1);
} else {
    echo "✓ Connection to MySQL Server: SUCCESS\n";
}

// Check if database exists
$result = $conn->query("SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = 'cycloan_db'");

if ($result && $result->num_rows > 0) {
    echo "✓ Database 'cycloan_db': EXISTS\n";

    // Try to select the database
    if ($conn->select_db($database)) {
        echo "✓ Selected database 'cycloan_db': SUCCESS\n";

        // Check a few key tables
        $tables = ['users1', 'spouses', 'financial_info', 'loan_applications'];
        foreach ($tables as $table) {
            $result = $conn->query("SHOW TABLES LIKE '$table'");
            if ($result && $result->num_rows > 0) {
                echo "  ✓ Table '$table': EXISTS\n";
            } else {
                echo "  ⚠ Table '$table': NOT FOUND\n";
            }
        }
    } else {
        echo "❌ Failed to select database: " . $conn->error . "\n";
    }
} else {
    echo "❌ Database 'cycloan_db': NOT FOUND\n";
    echo "\nAvailable databases:\n";
    $result = $conn->query("SHOW DATABASES");
    while ($row = $result->fetch_row()) {
        echo "  - " . $row[0] . "\n";
    }
}

$conn->close();
?>