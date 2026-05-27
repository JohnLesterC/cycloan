<?php
/**
 * Activity Logs Database Diagnostic Tool
 * Checks the structure and content of the activity_logs table
 */

require_once 'CYCLOAN_db.php';

echo "<h2>🔍 Activity Logs Database Diagnostic</h2>\n";
echo "<style>
    body { font-family: Arial, sans-serif; line-height: 1.6; margin: 20px; }
    h2 { color: #2c3e50; border-bottom: 2px solid #3498db; padding-bottom: 10px; }
    h3 { color: #27ae60; margin-top: 30px; }
    .success { color: #27ae60; font-weight: bold; }
    .error { color: #e74c3c; font-weight: bold; }
    .warning { color: #f39c12; font-weight: bold; }
    table { border-collapse: collapse; width: 100%; margin: 15px 0; }
    th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
    th { background-color: #f2f2f2; }
    .code { background: #f8f9fa; padding: 10px; border-radius: 5px; margin: 10px 0; font-family: monospace; }
    .section { background: #f9f9f9; padding: 15px; margin: 10px 0; border-radius: 8px; }
</style>";

// Test 1: Check if activity_logs table exists
echo "<div class='section'>";
echo "<h3>1. Table Existence Check</h3>";
try {
    $result = $conn->query("SHOW TABLES LIKE 'activity_logs'");
    if ($result && $result->num_rows > 0) {
        echo "<span class='success'>✅ activity_logs table exists</span><br>";
    } else {
        echo "<span class='error'>❌ activity_logs table does NOT exist</span><br>";
        echo "<p><strong>Action needed:</strong> Create the activity_logs table</p>";

        // Provide SQL to create the table
        echo "<div class='code'>
        CREATE TABLE activity_logs (
            log_id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT,
            user_role VARCHAR(50),
            admin_name VARCHAR(255),
            admin_email VARCHAR(255),
            action_type VARCHAR(50),
            module VARCHAR(50),
            activity_type VARCHAR(50),
            description TEXT,
            affected_id VARCHAR(255),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        );
        </div>";
    }
} catch (Exception $e) {
    echo "<span class='error'>❌ Error checking table existence: " . $e->getMessage() . "</span><br>";
}
echo "</div>";

// Test 2: Check table structure
echo "<div class='section'>";
echo "<h3>2. Table Structure</h3>";
try {
    $result = $conn->query("DESCRIBE activity_logs");
    if ($result) {
        echo "<table>";
        echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>";
        while ($row = $result->fetch_assoc()) {
            echo "<tr>";
            echo "<td>{$row['Field']}</td>";
            echo "<td>{$row['Type']}</td>";
            echo "<td>{$row['Null']}</td>";
            echo "<td>{$row['Key']}</td>";
            echo "<td>" . ($row['Default'] ?? 'NULL') . "</td>";
            echo "<td>{$row['Extra']}</td>";
            echo "</tr>";
        }
        echo "</table>";
        echo "<span class='success'>✅ Table structure retrieved successfully</span><br>";
    } else {
        echo "<span class='error'>❌ Could not retrieve table structure</span><br>";
    }
} catch (Exception $e) {
    echo "<span class='error'>❌ Error checking table structure: " . $e->getMessage() . "</span><br>";
}
echo "</div>";

// Test 3: Check recent records
echo "<div class='section'>";
echo "<h3>3. Recent Records (Last 10)</h3>";
try {
    $result = $conn->query("SELECT * FROM activity_logs ORDER BY created_at DESC LIMIT 10");
    if ($result) {
        $recordCount = $result->num_rows;
        echo "<p><strong>Found {$recordCount} recent records</strong></p>";

        if ($recordCount > 0) {
            echo "<table>";
            echo "<tr><th>ID</th><th>User ID</th><th>Role</th><th>Action</th><th>Module</th><th>Description</th><th>Created At</th></tr>";
            while ($row = $result->fetch_assoc()) {
                echo "<tr>";
                echo "<td>{$row['log_id']}</td>";
                echo "<td>{$row['user_id']}</td>";
                echo "<td>{$row['user_role']}</td>";
                echo "<td>{$row['action_type']}</td>";
                echo "<td>{$row['module']}</td>";
                echo "<td>" . htmlspecialchars(substr($row['description'], 0, 50)) . "...</td>";
                echo "<td>{$row['created_at']}</td>";
                echo "</tr>";
            }
            echo "</table>";
            echo "<span class='success'>✅ Records found and displayed</span><br>";
        } else {
            echo "<span class='warning'>⚠️ No records found in activity_logs table</span><br>";
            echo "<p>This could mean:</p>";
            echo "<ul>";
            echo "<li>No activities have been logged yet</li>";
            echo "<li>The logging function is not working properly</li>";
            echo "<li>There's an issue with the insert queries</li>";
            echo "</ul>";
        }
    } else {
        echo "<span class='error'>❌ Could not query recent records</span><br>";
    }
} catch (Exception $e) {
    echo "<span class='error'>❌ Error querying recent records: " . $e->getMessage() . "</span><br>";
}
echo "</div>";

// Test 4: Test inserting a record
echo "<div class='section'>";
echo "<h3>4. Test Insert Record</h3>";
try {
    $testQuery = "INSERT INTO activity_logs (user_id, user_role, admin_name, admin_email, action_type, module, activity_type, description, affected_id, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
    $stmt = $conn->prepare($testQuery);

    if ($stmt) {
        $testUserId = 1;
        $testUserRole = 'Admin2';
        $testAdminName = 'Test Admin';
        $testAdminEmail = 'test@example.com';
        $testActionType = 'test';
        $testModule = 'diagnostic';
        $testActivityType = 'system_test';
        $testDescription = 'Database diagnostic test entry - ' . date('Y-m-d H:i:s');
        $testAffectedId = null;

        $stmt->bind_param("issssssss", $testUserId, $testUserRole, $testAdminName, $testAdminEmail, $testActionType, $testModule, $testActivityType, $testDescription, $testAffectedId);

        if ($stmt->execute()) {
            $insertId = $conn->insert_id;
            echo "<span class='success'>✅ Test record inserted successfully! Insert ID: {$insertId}</span><br>";
            echo "<p><strong>Test record details:</strong></p>";
            echo "<div class='code'>";
            echo "User ID: {$testUserId}<br>";
            echo "Role: {$testUserRole}<br>";
            echo "Action: {$testActionType}<br>";
            echo "Module: {$testModule}<br>";
            echo "Description: {$testDescription}<br>";
            echo "</div>";
        } else {
            echo "<span class='error'>❌ Failed to insert test record: " . $stmt->error . "</span><br>";
        }
        $stmt->close();
    } else {
        echo "<span class='error'>❌ Could not prepare insert statement: " . $conn->error . "</span><br>";
    }
} catch (Exception $e) {
    echo "<span class='error'>❌ Error testing insert: " . $e->getMessage() . "</span><br>";
}
echo "</div>";

// Test 5: Check total record count
echo "<div class='section'>";
echo "<h3>5. Total Record Count</h3>";
try {
    $result = $conn->query("SELECT COUNT(*) as total FROM activity_logs");
    if ($result) {
        $row = $result->fetch_assoc();
        $total = $row['total'];
        echo "<p><strong>Total records in activity_logs: {$total}</strong></p>";

        if ($total == 0) {
            echo "<span class='warning'>⚠️ No records found - this indicates the logging system may not be working</span><br>";
        } elseif ($total < 10) {
            echo "<span class='warning'>⚠️ Very few records found ({$total}) - logging may be working but infrequent</span><br>";
        } else {
            echo "<span class='success'>✅ Good amount of records found - logging appears to be working</span><br>";
        }
    }
} catch (Exception $e) {
    echo "<span class='error'>❌ Error counting records: " . $e->getMessage() . "</span><br>";
}
echo "</div>";

// Test 6: Check for recent Admin2 activities
echo "<div class='section'>";
echo "<h3>6. Recent Admin2 Activities</h3>";
try {
    $result = $conn->query("SELECT * FROM activity_logs WHERE user_role = 'Admin2' ORDER BY created_at DESC LIMIT 5");
    if ($result) {
        $admin2Count = $result->num_rows;
        echo "<p><strong>Recent Admin2 activities: {$admin2Count}</strong></p>";

        if ($admin2Count > 0) {
            echo "<table>";
            echo "<tr><th>ID</th><th>Admin Name</th><th>Action</th><th>Module</th><th>Description</th><th>Created At</th></tr>";
            while ($row = $result->fetch_assoc()) {
                echo "<tr>";
                echo "<td>{$row['log_id']}</td>";
                echo "<td>{$row['admin_name']}</td>";
                echo "<td>{$row['action_type']}</td>";
                echo "<td>{$row['module']}</td>";
                echo "<td>" . htmlspecialchars(substr($row['description'], 0, 60)) . "...</td>";
                echo "<td>{$row['created_at']}</td>";
                echo "</tr>";
            }
            echo "</table>";
            echo "<span class='success'>✅ Admin2 activities found</span><br>";
        } else {
            echo "<span class='warning'>⚠️ No Admin2 activities found</span><br>";
            echo "<p>This suggests that Admin2 actions are not being logged properly.</p>";
        }
    }
} catch (Exception $e) {
    echo "<span class='error'>❌ Error checking Admin2 activities: " . $e->getMessage() . "</span><br>";
}
echo "</div>";

// Summary and recommendations
echo "<div class='section'>";
echo "<h3>7. Summary & Recommendations</h3>";

echo "<h4>Database Connection:</h4>";
if ($conn && $conn->ping()) {
    echo "<span class='success'>✅ Database connection is working</span><br>";
} else {
    echo "<span class='error'>❌ Database connection issue</span><br>";
}

echo "<h4>Recommendations:</h4>";
echo "<ul>";
echo "<li>If the table doesn't exist, create it using the provided SQL</li>";
echo "<li>If no records are found, check the logActivity() function in admin2_dashboard.php</li>";
echo "<li>If records exist but the UI doesn't show them, check the AJAX endpoint</li>";
echo "<li>If Admin2 activities aren't logged, verify the document update handlers call logActivity()</li>";
echo "</ul>";

echo "<h4>Next Steps:</h4>";
echo "<ol>";
echo "<li>Run this diagnostic to identify the issue</li>";
echo "<li>Check error logs for any database errors</li>";
echo "<li>Test creating a document status change to see if it logs</li>";
echo "<li>Check the admin2_dashboard.php logActivity() function</li>";
echo "</ol>";
echo "</div>";

$conn->close();
?>

<p><a href="admin2_dashboard.php">← Back to Admin Dashboard</a></p>