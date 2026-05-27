<?php
/**
 * Fix Activity Logs Table Structure
 * Adds missing columns to match the logActivity function requirements
 */

require_once 'CYCLOAN_db.php';

echo "<h2>🔧 Activity Logs Table Structure Fix</h2>\n";
echo "<style>
    body { font-family: Arial, sans-serif; line-height: 1.6; margin: 20px; }
    h2 { color: #2c3e50; border-bottom: 2px solid #3498db; padding-bottom: 10px; }
    h3 { color: #27ae60; margin-top: 25px; }
    .success { color: #27ae60; font-weight: bold; }
    .error { color: #e74c3c; font-weight: bold; }
    .warning { color: #f39c12; font-weight: bold; }
    .info { color: #3498db; font-weight: bold; }
    table { border-collapse: collapse; width: 100%; margin: 15px 0; }
    th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
    th { background-color: #f2f2f2; }
    .section { background: #f9f9f9; padding: 15px; margin: 10px 0; border-radius: 8px; }
</style>";

echo "<div class='section'>";
echo "<h3>Current Table Structure</h3>";

// Check current structure
try {
    $result = $conn->query("DESCRIBE activity_logs");
    if ($result) {
        echo "<table>";
        echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th></tr>";
        $existingColumns = [];
        while ($row = $result->fetch_assoc()) {
            $existingColumns[] = $row['Field'];
            echo "<tr><td>{$row['Field']}</td><td>{$row['Type']}</td><td>{$row['Null']}</td><td>{$row['Key']}</td><td>{$row['Default']}</td></tr>";
        }
        echo "</table>";

        echo "<p class='info'>Existing columns: " . implode(', ', $existingColumns) . "</p>";

        // Check which columns are missing
        $requiredColumns = [
            'log_id' => 'int(11) NOT NULL AUTO_INCREMENT',
            'user_id' => 'int(11) NOT NULL',
            'user_role' => 'varchar(50) NOT NULL',
            'admin_name' => 'varchar(255) DEFAULT NULL',
            'admin_email' => 'varchar(255) DEFAULT NULL',
            'action_type' => 'varchar(100) NOT NULL',
            'module' => 'varchar(100) NOT NULL',
            'activity_type' => 'varchar(100) DEFAULT NULL',
            'description' => 'text NOT NULL',
            'affected_id' => 'int(11) DEFAULT NULL',
            'created_at' => 'timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP'
        ];

        $missingColumns = [];
        foreach ($requiredColumns as $column => $definition) {
            if (!in_array($column, $existingColumns)) {
                $missingColumns[] = $column;
            }
        }

        if (!empty($missingColumns)) {
            echo "<p class='warning'>Missing columns: " . implode(', ', $missingColumns) . "</p>";
        } else {
            echo "<p class='success'>✅ All required columns present!</p>";
        }

    } else {
        echo "<p class='error'>❌ Could not get table structure</p>";
    }
} catch (Exception $e) {
    echo "<p class='error'>❌ Error: " . $e->getMessage() . "</p>";
}
echo "</div>";

echo "<div class='section'>";
echo "<h3>Adding Missing Columns</h3>";

// Add missing columns
$alterStatements = [
    "ALTER TABLE activity_logs ADD COLUMN admin_name VARCHAR(255) DEFAULT NULL AFTER user_role",
    "ALTER TABLE activity_logs ADD COLUMN admin_email VARCHAR(255) DEFAULT NULL AFTER admin_name",
    "ALTER TABLE activity_logs ADD COLUMN activity_type VARCHAR(100) DEFAULT NULL AFTER module",
    "ALTER TABLE activity_logs MODIFY COLUMN user_role VARCHAR(50) NOT NULL",
    "ALTER TABLE activity_logs MODIFY COLUMN action_type VARCHAR(100) NOT NULL",
    "ALTER TABLE activity_logs MODIFY COLUMN module VARCHAR(100) NOT NULL"
];

foreach ($alterStatements as $sql) {
    try {
        echo "<p>Executing: <code>" . htmlspecialchars($sql) . "</code></p>";
        if ($conn->query($sql)) {
            echo "<p class='success'>✅ Success</p>";
        } else {
            // Check if error is about column already existing
            if (strpos($conn->error, 'Duplicate column name') !== false) {
                echo "<p class='info'>ℹ️ Column already exists, skipping</p>";
            } else {
                echo "<p class='error'>❌ Error: " . $conn->error . "</p>";
            }
        }
    } catch (Exception $e) {
        echo "<p class='error'>❌ Exception: " . $e->getMessage() . "</p>";
    }
    echo "<br>";
}

echo "</div>";

echo "<div class='section'>";
echo "<h3>Updated Table Structure</h3>";

// Check updated structure
try {
    $result = $conn->query("DESCRIBE activity_logs");
    if ($result) {
        echo "<table>";
        echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th></tr>";
        while ($row = $result->fetch_assoc()) {
            echo "<tr><td>{$row['Field']}</td><td>{$row['Type']}</td><td>{$row['Null']}</td><td>{$row['Key']}</td><td>{$row['Default']}</td></tr>";
        }
        echo "</table>";
    }
} catch (Exception $e) {
    echo "<p class='error'>❌ Error getting updated structure: " . $e->getMessage() . "</p>";
}

echo "</div>";

echo "<div class='section'>";
echo "<h3>Testing logActivity Function</h3>";

// Test the logActivity function after structure update
function logActivity($conn, $userId, $userRole, $actionType, $module, $description, $affectedId = null, $activityType = null)
{
    // Get admin name and email based on user role
    $adminName = null;
    $adminEmail = null;

    if ($userRole === 'Admin2') {
        $stmt = $conn->prepare("SELECT first_name, last_name, email FROM admin2 WHERE id = ?");
        if ($stmt) {
            $stmt->bind_param("i", $userId);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($admin = $result->fetch_assoc()) {
                $adminName = trim($admin['first_name'] . ' ' . $admin['last_name']);
                $adminEmail = $admin['email'];
            }
            $stmt->close();
        }
    } elseif ($userRole === 'Admin1') {
        $stmt = $conn->prepare("SELECT first_name, last_name, email FROM admin1 WHERE id = ?");
        if ($stmt) {
            $stmt->bind_param("i", $userId);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($admin = $result->fetch_assoc()) {
                $adminName = trim($admin['first_name'] . ' ' . $admin['last_name']);
                $adminEmail = $admin['email'];
            }
            $stmt->close();
        }
    } elseif ($userRole === 'User') {
        $stmt = $conn->prepare("SELECT first_name, last_name, email FROM users1 WHERE id = ?");
        if ($stmt) {
            $stmt->bind_param("i", $userId);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($user = $result->fetch_assoc()) {
                $adminName = trim($user['first_name'] . ' ' . $user['last_name']);
                $adminEmail = $user['email'];
            }
            $stmt->close();
        }
    }

    $query = "INSERT INTO activity_logs (user_id, user_role, admin_name, admin_email, action_type, module, activity_type, description, affected_id, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";

    try {
        $stmt = $conn->prepare($query);
        if (!$stmt) {
            echo "<p class='error'>❌ Failed to prepare statement: " . $conn->error . "</p>";
            return false;
        }

        $stmt->bind_param("issssssss", $userId, $userRole, $adminName, $adminEmail, $actionType, $module, $activityType, $description, $affectedId);

        if (!$stmt->execute()) {
            echo "<p class='error'>❌ Failed to execute statement: " . $stmt->error . "</p>";
            $stmt->close();
            return false;
        }

        $insertId = $conn->insert_id;
        $stmt->close();
        echo "<p class='success'>✅ Activity log inserted successfully (Insert ID: {$insertId})</p>";
        return true;

    } catch (Exception $e) {
        echo "<p class='error'>❌ Exception: " . $e->getMessage() . "</p>";
        return false;
    }
}

// Test with sample data
echo "<p>Testing logActivity function with updated table structure...</p>";
$testResult = logActivity($conn, 1, 'Admin2', 'test', 'structure_fix', 'Testing activity log after structure update - ' . date('Y-m-d H:i:s'), null, 'table_fix_test');

if ($testResult) {
    echo "<p class='success'>✅ logActivity function is now working correctly!</p>";
} else {
    echo "<p class='error'>❌ logActivity function still has issues</p>";
}

echo "</div>";

echo "<div class='section'>";
echo "<h3>Recent Activity Logs</h3>";

try {
    $result = $conn->query("SELECT log_id, user_id, user_role, admin_name, action_type, module, activity_type, description, created_at FROM activity_logs ORDER BY created_at DESC LIMIT 5");
    if ($result && $result->num_rows > 0) {
        echo "<table>";
        echo "<tr><th>ID</th><th>User ID</th><th>Role</th><th>Admin Name</th><th>Action</th><th>Module</th><th>Type</th><th>Description</th><th>Created</th></tr>";
        while ($row = $result->fetch_assoc()) {
            echo "<tr>";
            echo "<td>{$row['log_id']}</td>";
            echo "<td>{$row['user_id']}</td>";
            echo "<td>{$row['user_role']}</td>";
            echo "<td>" . htmlspecialchars($row['admin_name'] ?? 'N/A') . "</td>";
            echo "<td>{$row['action_type']}</td>";
            echo "<td>{$row['module']}</td>";
            echo "<td>" . htmlspecialchars($row['activity_type'] ?? 'N/A') . "</td>";
            echo "<td>" . htmlspecialchars(substr($row['description'], 0, 50)) . "...</td>";
            echo "<td>{$row['created_at']}</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<p class='warning'>No activity logs found</p>";
    }
} catch (Exception $e) {
    echo "<p class='error'>❌ Error getting recent logs: " . $e->getMessage() . "</p>";
}

echo "</div>";

echo "<div class='section'>";
echo "<h3>✅ Fix Complete</h3>";
echo "<p>The activity_logs table structure has been updated to match the logActivity function requirements.</p>";
echo "<p><strong>Next steps:</strong></p>";
echo "<ul>";
echo "<li><a href='admin2_dashboard.php'>Go to Admin Dashboard</a> - Activity logs should now work</li>";
echo "<li><a href='test_activity_logs_comprehensive.php'>Run Comprehensive Test</a> - Verify everything works</li>";
echo "<li>Test the document status updates - they should now log activities properly</li>";
echo "</ul>";
echo "</div>";

$conn->close();
?>