<?php
/**
 * Comprehensive Activity Logs Test
 * Tests all aspects of the activity logging system
 */

require_once 'CYCLOAN_db.php';
session_start();

echo "<h2>🧪 Comprehensive Activity Logs Test</h2>\n";
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
    .code { background: #f8f9fa; padding: 10px; border-radius: 5px; margin: 10px 0; font-family: monospace; }
    .section { background: #f9f9f9; padding: 15px; margin: 10px 0; border-radius: 8px; }
    .test-result { padding: 10px; margin: 5px 0; border-radius: 4px; }
    .test-pass { background: #d4edda; border: 1px solid #c3e6cb; color: #155724; }
    .test-fail { background: #f8d7da; border: 1px solid #f5c6cb; color: #721c24; }
</style>";

// Include the logActivity function from admin2_dashboard.php
// Function to log activity (copied from admin2_dashboard.php)
function logActivity($conn, $userId, $userRole, $actionType, $module, $description, $affectedId = null, $activityType = null)
{
    // Get admin name and email based on user role
    $adminName = null;
    $adminEmail = null;

    if ($userRole === 'Admin2') {
        $admin = executeQuery($conn, "SELECT first_name, last_name, email FROM admin2 WHERE id = ?", "i", [$userId]);
        if (!empty($admin)) {
            $adminName = trim($admin[0]['first_name'] . ' ' . $admin[0]['last_name']);
            $adminEmail = $admin[0]['email'];
        }
    } elseif ($userRole === 'Admin1') {
        $admin = executeQuery($conn, "SELECT first_name, last_name, email FROM admin1 WHERE id = ?", "i", [$userId]);
        if (!empty($admin)) {
            $adminName = trim($admin[0]['first_name'] . ' ' . $admin[0]['last_name']);
            $adminEmail = $admin[0]['email'];
        }
    } elseif ($userRole === 'User') {
        $user = executeQuery($conn, "SELECT first_name, last_name, email FROM users1 WHERE id = ?", "i", [$userId]);
        if (!empty($user)) {
            $adminName = trim($user[0]['first_name'] . ' ' . $user[0]['last_name']);
            $adminEmail = $user[0]['email'];
        }
    }

    $query = "INSERT INTO activity_logs (user_id, user_role, admin_name, admin_email, action_type, module, activity_type, description, affected_id, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
    $types = "issssssss";
    $params = [$userId, $userRole, $adminName, $adminEmail, $actionType, $module, $activityType, $description, $affectedId];

    try {
        $result = executeUpdate($conn, $query, $types, $params);
        if ($result === 0) {
            echo "<p class='error'>❌ Failed to insert activity log</p>";
            return false;
        }
        echo "<p class='success'>✅ Activity log inserted successfully (Insert ID: " . $conn->insert_id . ")</p>";
        return true;
    } catch (Exception $e) {
        echo "<p class='error'>❌ Exception inserting activity log: " . $e->getMessage() . "</p>";
        return false;
    }
}

// Helper function for database queries
function executeQuery($conn, $query, $types = "", $params = [])
{
    try {
        if (!empty($types) && !empty($params)) {
            $stmt = $conn->prepare($query);
            if (!$stmt) {
                throw new Exception("Failed to prepare query: " . $conn->error);
            }
            $stmt->bind_param($types, ...$params);
            if (!$stmt->execute()) {
                throw new Exception("Failed to execute query: " . $stmt->error);
            }
            $result = $stmt->get_result();
            $data = $result->fetch_all(MYSQLI_ASSOC);
            $stmt->close();
            return $data;
        } else {
            $result = $conn->query($query);
            if (!$result) {
                throw new Exception("Query failed: " . $conn->error);
            }
            return $result->fetch_all(MYSQLI_ASSOC);
        }
    } catch (Exception $e) {
        echo "<p class='error'>Database query error: " . $e->getMessage() . "</p>";
        return false;
    }
}

function executeUpdate($conn, $query, $types, $params)
{
    try {
        $stmt = $conn->prepare($query);
        if (!$stmt) {
            throw new Exception("Failed to prepare update query: " . $conn->error);
        }
        $stmt->bind_param($types, ...$params);
        if (!$stmt->execute()) {
            throw new Exception("Failed to execute update query: " . $stmt->error);
        }
        $affected_rows = $stmt->affected_rows;
        $stmt->close();
        return $affected_rows;
    } catch (Exception $e) {
        echo "<p class='error'>Database update error: " . $e->getMessage() . "</p>";
        return 0;
    }
}

// Test 1: Database Connection
echo "<div class='section'>";
echo "<h3>Test 1: Database Connection</h3>";
if ($conn && $conn->ping()) {
    echo "<div class='test-result test-pass'>✅ Database connection successful</div>";
    echo "<p><strong>Connection info:</strong> " . $conn->get_server_info() . "</p>";
} else {
    echo "<div class='test-result test-fail'>❌ Database connection failed</div>";
    if ($conn) {
        echo "<p><strong>Error:</strong> " . $conn->error . "</p>";
    }
    exit;
}
echo "</div>";

// Test 2: Check if activity_logs table exists and structure
echo "<div class='section'>";
echo "<h3>Test 2: Activity Logs Table Check</h3>";
try {
    $result = $conn->query("SHOW TABLES LIKE 'activity_logs'");
    if ($result && $result->num_rows > 0) {
        echo "<div class='test-result test-pass'>✅ activity_logs table exists</div>";

        // Check structure
        $structure = $conn->query("DESCRIBE activity_logs");
        if ($structure) {
            echo "<p><strong>Table structure:</strong></p>";
            echo "<table>";
            echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th></tr>";
            while ($row = $structure->fetch_assoc()) {
                echo "<tr><td>{$row['Field']}</td><td>{$row['Type']}</td><td>{$row['Null']}</td><td>{$row['Key']}</td></tr>";
            }
            echo "</table>";
        }
    } else {
        echo "<div class='test-result test-fail'>❌ activity_logs table does NOT exist</div>";
        echo "<p>You need to create the table first. Check the database diagnostic tool.</p>";
    }
} catch (Exception $e) {
    echo "<div class='test-result test-fail'>❌ Error checking table: " . $e->getMessage() . "</div>";
}
echo "</div>";

// Test 3: Count existing records
echo "<div class='section'>";
echo "<h3>Test 3: Current Records Count</h3>";
try {
    $result = $conn->query("SELECT COUNT(*) as total FROM activity_logs");
    if ($result) {
        $row = $result->fetch_assoc();
        $totalRecords = $row['total'];
        echo "<div class='test-result test-pass'>✅ Found {$totalRecords} total records</div>";

        // Count by user role
        $roleCount = $conn->query("SELECT user_role, COUNT(*) as count FROM activity_logs GROUP BY user_role");
        if ($roleCount) {
            echo "<p><strong>Records by role:</strong></p>";
            echo "<table>";
            echo "<tr><th>User Role</th><th>Count</th></tr>";
            while ($row = $roleCount->fetch_assoc()) {
                echo "<tr><td>{$row['user_role']}</td><td>{$row['count']}</td></tr>";
            }
            echo "</table>";
        }
    }
} catch (Exception $e) {
    echo "<div class='test-result test-fail'>❌ Error counting records: " . $e->getMessage() . "</div>";
}
echo "</div>";

// Test 4: Test logActivity function with real data
echo "<div class='section'>";
echo "<h3>Test 4: Test logActivity Function</h3>";

// Set test session data
$_SESSION['admin_id'] = $_SESSION['admin_id'] ?? 1;

echo "<p><strong>Testing logActivity function with test data...</strong></p>";

$testUserId = $_SESSION['admin_id'];
$testUserRole = 'Admin2';
$testActionType = 'test';
$testModule = 'system_test';
$testDescription = 'Comprehensive test log entry - ' . date('Y-m-d H:i:s');

echo "<p><strong>Test parameters:</strong></p>";
echo "<ul>";
echo "<li>User ID: {$testUserId}</li>";
echo "<li>User Role: {$testUserRole}</li>";
echo "<li>Action Type: {$testActionType}</li>";
echo "<li>Module: {$testModule}</li>";
echo "<li>Description: {$testDescription}</li>";
echo "</ul>";

$logResult = logActivity($conn, $testUserId, $testUserRole, $testActionType, $testModule, $testDescription, null, 'comprehensive_test');

if ($logResult) {
    echo "<div class='test-result test-pass'>✅ logActivity function working correctly</div>";
} else {
    echo "<div class='test-result test-fail'>❌ logActivity function failed</div>";
}
echo "</div>";

// Test 5: Verify the test record was inserted
echo "<div class='section'>";
echo "<h3>Test 5: Verify Test Record</h3>";
try {
    $result = $conn->query("SELECT * FROM activity_logs WHERE description LIKE '%Comprehensive test log entry%' ORDER BY created_at DESC LIMIT 1");
    if ($result && $result->num_rows > 0) {
        $record = $result->fetch_assoc();
        echo "<div class='test-result test-pass'>✅ Test record found in database</div>";
        echo "<p><strong>Retrieved record:</strong></p>";
        echo "<table>";
        echo "<tr><th>Field</th><th>Value</th></tr>";
        foreach ($record as $key => $value) {
            echo "<tr><td>{$key}</td><td>" . htmlspecialchars($value) . "</td></tr>";
        }
        echo "</table>";
    } else {
        echo "<div class='test-result test-fail'>❌ Test record NOT found in database</div>";
    }
} catch (Exception $e) {
    echo "<div class='test-result test-fail'>❌ Error verifying test record: " . $e->getMessage() . "</div>";
}
echo "</div>";

// Test 6: Test the AJAX endpoint
echo "<div class='section'>";
echo "<h3>Test 6: AJAX Endpoint Test</h3>";
echo "<p>Testing the get_activity_logs AJAX endpoint...</p>";

// Simulate the AJAX request
try {
    // Use the same query as the AJAX endpoint
    $activityLogs = executeQuery($conn, "
        SELECT al.log_id, al.user_id, al.user_role, al.action_type, al.module, al.description, 
               al.created_at,
               COALESCE(u.first_name, a.first_name, a1.first_name, 'System') AS first_name,
               COALESCE(u.last_name, a.last_name, a1.last_name, '') AS last_name,
               la.loan_id
        FROM activity_logs al
        LEFT JOIN users1 u ON al.user_id = u.id AND al.user_role = 'User'
        LEFT JOIN admin2 a ON al.user_id = a.id AND al.user_role = 'Admin2'
        LEFT JOIN admin1 a1 ON al.user_id = a1.id AND al.user_role = 'Admin1'
        LEFT JOIN loan_applications la ON al.user_id = la.user_id
        ORDER BY al.created_at DESC
        LIMIT 5
    ");

    if ($activityLogs !== false) {
        echo "<div class='test-result test-pass'>✅ AJAX endpoint query working - found " . count($activityLogs) . " records</div>";

        if (count($activityLogs) > 0) {
            echo "<p><strong>Sample records from AJAX endpoint:</strong></p>";
            echo "<table>";
            echo "<tr><th>Log ID</th><th>User</th><th>Role</th><th>Action</th><th>Module</th><th>Description</th><th>Created</th></tr>";
            foreach ($activityLogs as $log) {
                $userName = trim(($log['first_name'] ?? '') . ' ' . ($log['last_name'] ?? ''));
                echo "<tr>";
                echo "<td>{$log['log_id']}</td>";
                echo "<td>" . htmlspecialchars($userName) . "</td>";
                echo "<td>{$log['user_role']}</td>";
                echo "<td>{$log['action_type']}</td>";
                echo "<td>{$log['module']}</td>";
                echo "<td>" . htmlspecialchars(substr($log['description'], 0, 50)) . "...</td>";
                echo "<td>{$log['created_at']}</td>";
                echo "</tr>";
            }
            echo "</table>";
        }
    } else {
        echo "<div class='test-result test-fail'>❌ AJAX endpoint query failed</div>";
    }
} catch (Exception $e) {
    echo "<div class='test-result test-fail'>❌ AJAX endpoint test error: " . $e->getMessage() . "</div>";
}
echo "</div>";

// Test 7: Test recent records
echo "<div class='section'>";
echo "<h3>Test 7: Most Recent Records</h3>";
try {
    $recent = $conn->query("SELECT * FROM activity_logs ORDER BY created_at DESC LIMIT 10");
    if ($recent && $recent->num_rows > 0) {
        echo "<div class='test-result test-pass'>✅ Found " . $recent->num_rows . " recent records</div>";
        echo "<table>";
        echo "<tr><th>Log ID</th><th>User ID</th><th>Role</th><th>Action</th><th>Module</th><th>Created At</th></tr>";
        while ($row = $recent->fetch_assoc()) {
            echo "<tr>";
            echo "<td>{$row['log_id']}</td>";
            echo "<td>{$row['user_id']}</td>";
            echo "<td>{$row['user_role']}</td>";
            echo "<td>{$row['action_type']}</td>";
            echo "<td>{$row['module']}</td>";
            echo "<td>{$row['created_at']}</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<div class='test-result test-fail'>❌ No recent records found</div>";
    }
} catch (Exception $e) {
    echo "<div class='test-result test-fail'>❌ Error getting recent records: " . $e->getMessage() . "</div>";
}
echo "</div>";

// Summary
echo "<div class='section'>";
echo "<h3>🎯 Test Summary</h3>";
echo "<p><strong>Run this test to diagnose activity logs issues:</strong></p>";
echo "<ol>";
echo "<li>Check if all tests pass ✅</li>";
echo "<li>If table doesn't exist, create it using the database diagnostic tool</li>";
echo "<li>If logActivity fails, check database permissions and connection</li>";
echo "<li>If AJAX endpoint fails, check the admin2_dashboard.php file</li>";
echo "<li>If no records appear in UI but exist in database, check JavaScript polling</li>";
echo "</ol>";

echo "<p><strong>Next steps:</strong></p>";
echo "<ul>";
echo "<li><a href='check_activity_logs_database.php'>Run Database Diagnostic Tool</a></li>";
echo "<li><a href='admin2_dashboard.php'>Go to Admin Dashboard</a></li>";
echo "<li><a href='admin2_dashboard.php?action=test_activity_log'>Test Activity Log via AJAX</a></li>";
echo "<li><a href='admin2_dashboard.php?action=get_activity_logs'>Test AJAX Endpoint</a></li>";
echo "</ul>";
echo "</div>";

$conn->close();
?>