<?php
/**
 * CYCLOAN Login Debug Script
 * This script helps identify login issues
 * Place in your project root and access via: http://localhost:8000/debug_login.php
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);
?>
<!DOCTYPE html>
<html>

<head>
    <title>CYCLOAN Login Debug</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 20px;
            background: #f5f5f5;
        }

        .container {
            max-width: 900px;
            margin: 0 auto;
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }

        h2 {
            color: #1b5e20;
            border-bottom: 2px solid #1b5e20;
            padding-bottom: 10px;
        }

        .test-section {
            margin: 20px 0;
            padding: 15px;
            background: #f9f9f9;
            border-left: 4px solid #1b5e20;
            border-radius: 4px;
        }

        .success {
            background: #e8f5e9;
            color: #1b5e20;
            padding: 10px;
            border-radius: 4px;
            margin: 10px 0;
        }

        .error {
            background: #ffebee;
            color: #c62828;
            padding: 10px;
            border-radius: 4px;
            margin: 10px 0;
        }

        .info {
            background: #e3f2fd;
            color: #1565c0;
            padding: 10px;
            border-radius: 4px;
            margin: 10px 0;
        }

        code {
            background: #f0f0f0;
            padding: 2px 6px;
            border-radius: 3px;
            font-family: 'Courier New', monospace;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin: 10px 0;
        }

        th,
        td {
            padding: 10px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }

        th {
            background: #1b5e20;
            color: white;
        }

        tr:hover {
            background: #f5f5f5;
        }

        .form-group {
            margin: 15px 0;
        }

        label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
            color: #333;
        }

        input {
            width: 100%;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
            box-sizing: border-box;
        }

        button {
            background: #1b5e20;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 16px;
        }

        button:hover {
            background: #2e7d32;
        }

        .hash-display {
            word-break: break-all;
            background: #f0f0f0;
            padding: 10px;
            border-radius: 4px;
            font-family: monospace;
            font-size: 12px;
            margin: 10px 0;
        }

        .warning {
            background: #fff3e0;
            border-left: 4px solid #f57c00;
        }
    </style>
</head>

<body>
    <div class="container">
        <h1>🔍 CYCLOAN Login Debug Tool</h1>

        <?php
        // Test 1: Database Connection
        echo "<div class='test-section'>";
        echo "<h2>Test 1: Database Connection</h2>";

        require_once 'CYCLOAN_db.php';

        if ($conn->connect_error) {
            echo "<div class='error'>❌ Database connection failed: " . $conn->connect_error . "</div>";
        } else {
            echo "<div class='success'>✓ Database connection successful</div>";
            echo "<div class='info'>Server: " . $conn->server_info . "</div>";
        }
        echo "</div>";

        // Test 2: Check Tables
        echo "<div class='test-section'>";
        echo "<h2>Test 2: Table Verification</h2>";

        $tables_to_check = ['users1', 'admin1', 'admin2', 'superadmins', 'logattempts'];
        foreach ($tables_to_check as $table) {
            $result = $conn->query("SHOW TABLES LIKE '" . $table . "'");
            if ($result && $result->num_rows > 0) {
                echo "<div class='success'>✓ Table <code>" . $table . "</code> exists</div>";
            } else {
                echo "<div class='error'>❌ Table <code>" . $table . "</code> NOT FOUND</div>";
            }
        }
        echo "</div>";

        // Test 3: User Lookup Form
        echo "<div class='test-section'>";
        echo "<h2>Test 3: User Lookup & Password Verification</h2>";

        if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['test_email'])) {
            $test_email = trim($_POST['test_email']);
            $test_password = $_POST['test_password'] ?? '';

            echo "<div class='info'>Testing email: <code>" . htmlspecialchars($test_email) . "</code></div>";

            // Check users1
            echo "<h3>Checking users1 table:</h3>";
            $stmt = $conn->prepare("SELECT id, email, password, is_active FROM users1 WHERE email = ?");
            $stmt->bind_param("s", $test_email);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result->num_rows > 0) {
                $user = $result->fetch_assoc();
                echo "<div class='success'>✓ User found in users1</div>";
                echo "<table>";
                echo "<tr><th>Field</th><th>Value</th></tr>";
                echo "<tr><td>ID</td><td>" . $user['id'] . "</td></tr>";
                echo "<tr><td>Email</td><td>" . htmlspecialchars($user['email']) . "</td></tr>";
                echo "<tr><td>Active</td><td>" . ($user['is_active'] ? "✓ YES" : "❌ NO") . "</td></tr>";
                echo "</table>";

                echo "<h3>Password Hash:</h3>";
                echo "<div class='hash-display'>" . htmlspecialchars($user['password']) . "</div>";

                // Verify hash format
                if (strpos($user['password'], '$2y$') === 0 || strpos($user['password'], '$2a$') === 0) {
                    echo "<div class='success'>✓ Hash format is valid (bcrypt)</div>";
                } else {
                    echo "<div class='error'>❌ Hash format invalid! Expected bcrypt hash starting with \$2y\$ or \$2a\$</div>";
                }

                if (!empty($test_password)) {
                    echo "<h3>Password Verification:</h3>";
                    if (password_verify($test_password, $user['password'])) {
                        echo "<div class='success'>✓ Password MATCHES</div>";
                    } else {
                        echo "<div class='error'>❌ Password DOES NOT MATCH</div>";
                        echo "<div class='info'>Entered password: <code>" . str_repeat('*', strlen($test_password)) . "</code> (length: " . strlen($test_password) . ")</div>";
                    }
                }

                if (!$user['is_active']) {
                    echo "<div class='warning'><strong>⚠ Account Not Verified!</strong> Set is_active = 1 to enable login</div>";
                }
            } else {
                echo "<div class='info'>ℹ User NOT found in users1. Checking admin tables...</div>";

                // Check admin tables
                $admin_tables = ['admin1', 'admin2', 'superadmins'];
                $found = false;

                foreach ($admin_tables as $table) {
                    $stmt = $conn->prepare("SELECT id, email, password, is_active FROM " . $table . " WHERE email = ?");
                    $stmt->bind_param("s", $test_email);
                    $stmt->execute();
                    $result = $stmt->get_result();

                    if ($result->num_rows > 0) {
                        $found = true;
                        $user = $result->fetch_assoc();
                        echo "<div class='success'>✓ User found in <code>" . $table . "</code></div>";
                        echo "<table>";
                        echo "<tr><th>Field</th><th>Value</th></tr>";
                        echo "<tr><td>ID</td><td>" . $user['id'] . "</td></tr>";
                        echo "<tr><td>Email</td><td>" . htmlspecialchars($user['email']) . "</td></tr>";
                        echo "<tr><td>Active</td><td>" . ($user['is_active'] ? "✓ YES" : "❌ NO") . "</td></tr>";
                        echo "</table>";

                        echo "<h3>Password Hash:</h3>";
                        echo "<div class='hash-display'>" . htmlspecialchars($user['password']) . "</div>";

                        if (!empty($test_password)) {
                            echo "<h3>Password Verification:</h3>";
                            if (password_verify($test_password, $user['password'])) {
                                echo "<div class='success'>✓ Password MATCHES</div>";
                            } else {
                                echo "<div class='error'>❌ Password DOES NOT MATCH</div>";
                            }
                        }
                    }
                }

                if (!$found) {
                    echo "<div class='error'>❌ User NOT found in any table (users1, admin1, admin2, superadmins)</div>";
                }
            }
        } else {
            echo "<form method='POST'>";
            echo "<div class='form-group'>";
            echo "<label for='test_email'>Email:</label>";
            echo "<input type='email' id='test_email' name='test_email' required>";
            echo "</div>";
            echo "<div class='form-group'>";
            echo "<label for='test_password'>Password (optional, for verification):</label>";
            echo "<input type='password' id='test_password' name='test_password'>";
            echo "</div>";
            echo "<button type='submit'>Search User</button>";
            echo "</form>";
        }
        echo "</div>";

        // Test 4: Failed Attempts Check
        echo "<div class='test-section'>";
        echo "<h2>Test 4: Failed Login Attempts</h2>";

        if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['test_email'])) {
            $test_email = trim($_POST['test_email']);

            // First, let's check the table structure
            $columns_result = $conn->query("DESCRIBE logattempts");
            $columns = [];
            while ($col = $columns_result->fetch_assoc()) {
                $columns[] = $col['Field'];
            }
            echo "<div class='info'><strong>Table Columns:</strong> " . implode(", ", $columns) . "</div>";

            // Try to get failed attempts - use generic query first
            $query = "SELECT * FROM logattempts WHERE email = ? ORDER BY id DESC LIMIT 1";
            $stmt = $conn->prepare($query);
            if (!$stmt) {
                echo "<div class='error'><strong>Query Error:</strong> " . $conn->error . "</div>";
            } else {
                $stmt->bind_param("s", $test_email);
                if (!$stmt->execute()) {
                    echo "<div class='error'><strong>Execute Error:</strong> " . $stmt->error . "</div>";
                } else {
                    $result = $stmt->get_result();
                    echo "<div class='info'>Found " . $result->num_rows . " records for this email</div>";

                    if ($result->num_rows > 0) {
                        echo "<h3>Sample Record (to verify column names):</h3>";
                        $row = $result->fetch_assoc();
                        echo "<table>";
                        foreach ($row as $key => $value) {
                            echo "<tr><td><strong>" . htmlspecialchars($key) . "</strong></td><td>" . htmlspecialchars($value) . "</td></tr>";
                        }
                        echo "</table>";
                    }
                }
                $stmt->close();
            }
        }
        echo "</div>";

        // Test 5: PHP Configuration
        echo "<div class='test-section'>";
        echo "<h2>Test 5: PHP Configuration</h2>";
        echo "<table>";
        echo "<tr><th>Setting</th><th>Value</th></tr>";
        echo "<tr><td>PHP Version</td><td>" . phpversion() . "</td></tr>";
        echo "<tr><td>Session Save Path</td><td>" . session_save_path() . "</td></tr>";
        echo "<tr><td>Error Log</td><td>" . ini_get('error_log') . "</td></tr>";
        echo "<tr><td>Display Errors</td><td>" . (ini_get('display_errors') ? "ON" : "OFF") . "</td></tr>";
        echo "<tr><td>Error Reporting</td><td>" . error_reporting() . "</td></tr>";
        echo "</table>";
        echo "</div>";

        // Test 6: File Permissions
        echo "<div class='test-section'>";
        echo "<h2>Test 6: File Permissions</h2>";
        $files_to_check = ['index.php', 'CYCLOAN_db.php', 'user_dashboard.php'];
        foreach ($files_to_check as $file) {
            if (file_exists($file)) {
                $perms = substr(sprintf('%o', fileperms($file)), -4);
                echo "<div class='success'>✓ <code>" . $file . "</code> exists (perms: " . $perms . ")</div>";
            } else {
                echo "<div class='error'>❌ <code>" . $file . "</code> NOT FOUND</div>";
            }
        }
        echo "</div>";
        ?>
    </div>
</body>

</html>