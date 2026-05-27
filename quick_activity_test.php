<?php
require_once 'CYCLOAN_db.php';

echo "<h2>Quick Activity Logs Test</h2>";

try {
    // Check if table exists
    $result = $conn->query("SHOW TABLES LIKE 'activity_logs'");
    if ($result && $result->num_rows > 0) {
        echo "✅ Table exists<br>";

        // Count records
        $count = $conn->query("SELECT COUNT(*) as total FROM activity_logs");
        $row = $count->fetch_assoc();
        echo "📊 Total records: " . $row['total'] . "<br>";

        // Get latest 5
        $latest = $conn->query("SELECT log_id, user_id, user_role, action_type, description, created_at FROM activity_logs ORDER BY created_at DESC LIMIT 5");
        echo "<h3>Latest 5 Records:</h3>";
        echo "<table border='1'><tr><th>ID</th><th>User</th><th>Role</th><th>Action</th><th>Description</th><th>Created</th></tr>";
        while ($row = $latest->fetch_assoc()) {
            echo "<tr><td>{$row['log_id']}</td><td>{$row['user_id']}</td><td>{$row['user_role']}</td><td>{$row['action_type']}</td><td>" . substr($row['description'], 0, 50) . "</td><td>{$row['created_at']}</td></tr>";
        }
        echo "</table>";

        // Test insert
        echo "<h3>Test Insert:</h3>";
        $test_sql = "INSERT INTO activity_logs (user_id, user_role, admin_name, action_type, module, description, created_at) VALUES (1, 'Admin2', 'Test User', 'test', 'debug', 'Quick test entry - " . date('Y-m-d H:i:s') . "', NOW())";

        if ($conn->query($test_sql)) {
            echo "✅ Test insert successful - Insert ID: " . $conn->insert_id . "<br>";
        } else {
            echo "❌ Test insert failed: " . $conn->error . "<br>";
        }

    } else {
        echo "❌ activity_logs table does NOT exist<br>";
        echo "<p>Creating table...</p>";

        $create_sql = "CREATE TABLE IF NOT EXISTS `activity_logs` (
            `log_id` int(11) NOT NULL AUTO_INCREMENT,
            `user_id` int(11) NOT NULL,
            `user_role` varchar(50) NOT NULL,
            `admin_name` varchar(255) DEFAULT NULL,
            `admin_email` varchar(255) DEFAULT NULL,
            `action_type` varchar(100) NOT NULL,
            `module` varchar(100) NOT NULL,
            `activity_type` varchar(100) DEFAULT NULL,
            `description` text NOT NULL,
            `affected_id` int(11) DEFAULT NULL,
            `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`log_id`),
            KEY `idx_user_id` (`user_id`),
            KEY `idx_user_role` (`user_role`),
            KEY `idx_created_at` (`created_at`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

        if ($conn->query($create_sql)) {
            echo "✅ activity_logs table created successfully<br>";
        } else {
            echo "❌ Failed to create table: " . $conn->error . "<br>";
        }
    }
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "<br>";
}

$conn->close();
?>