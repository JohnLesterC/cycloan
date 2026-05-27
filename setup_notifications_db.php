<?php
/**
 * CYCLOAN Notification System Database Setup
 * Deploy the notification system database schema
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'CYCLOAN_db.php';

echo "<h1>CYCLOAN Notification System Database Setup</h1>";

// Check if database connection exists
if (!$conn || !$conn->ping()) {
    die("❌ Database connection failed. Please check CYCLOAN_db.php configuration.");
}

echo "✅ Database connection successful<br><br>";

// Read the schema file
$schemaFile = 'database/notification_system_schema.sql';
if (!file_exists($schemaFile)) {
    die("❌ Schema file not found: $schemaFile");
}

echo "📄 Reading schema file: $schemaFile<br>";
$sql = file_get_contents($schemaFile);

if (!$sql) {
    die("❌ Could not read schema file");
}

// Split SQL into individual statements
$statements = array_filter(
    array_map('trim', explode(';', $sql)),
    function ($stmt) {
        return !empty($stmt) && !preg_match('/^\s*--/', $stmt);
    }
);

echo "📊 Found " . count($statements) . " SQL statements to execute<br><br>";

// Execute each statement
$success = 0;
$errors = 0;

foreach ($statements as $index => $statement) {
    // Skip comments and empty statements
    if (empty(trim($statement)) || preg_match('/^\s*--/', $statement)) {
        continue;
    }

    echo "Executing statement " . ($index + 1) . "... ";

    try {
        if ($conn->query($statement)) {
            echo "✅ SUCCESS<br>";
            $success++;
        } else {
            echo "❌ ERROR: " . $conn->error . "<br>";
            $errors++;
        }
    } catch (Exception $e) {
        echo "❌ EXCEPTION: " . $e->getMessage() . "<br>";
        $errors++;
    }
}

echo "<br><h2>Setup Summary</h2>";
echo "✅ Successful statements: $success<br>";
echo "❌ Failed statements: $errors<br>";

// Verify tables were created
echo "<br><h2>Table Verification</h2>";
$tables = [
    'notification_types',
    'user_notifications',
    'user_notification_settings',
    'notification_templates',
    'notification_delivery_log'
];

$tablesCreated = 0;
foreach ($tables as $table) {
    $query = "SHOW TABLES LIKE '$table'";
    $result = $conn->query($query);
    if ($result && $result->num_rows > 0) {
        echo "✅ Table '$table': EXISTS<br>";
        $tablesCreated++;
    } else {
        echo "❌ Table '$table': NOT FOUND<br>";
    }
}

echo "<br><h2>Final Status</h2>";
if ($tablesCreated === count($tables)) {
    echo "🎉 <strong>SUCCESS!</strong> All notification system tables have been created successfully.<br>";
    echo "<br><a href='test_notifications.php'>Run Diagnostic Test</a> | <a href='notifications.php'>View Notifications</a>";
} else {
    echo "⚠️ <strong>PARTIAL SUCCESS:</strong> Some tables are missing. Please check the errors above.<br>";
}

$conn->close();
?>