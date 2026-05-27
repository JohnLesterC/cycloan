<?php
session_start();
require 'CYCLOAN_db.php';

// Show test form and results
?>
<!DOCTYPE html>
<html>

<head>
    <title>Login Debug - Test Credentials</title>
    <style>
        body {
            font-family: Arial;
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
            font-family: monospace;
        }

        input,
        button {
            padding: 8px;
            margin: 5px;
            font-size: 16px;
        }

        button {
            background: #1b5e20;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
        }

        button:hover {
            background: #2e7d32;
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
    </style>
</head>

<body>
    <div class="container">
        <h1>🔐 Login Debug - Full Process Test</h1>

        <?php
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            $email = trim($_POST['email'] ?? '');
            $password = $_POST['password'] ?? '';

            if (empty($email) || empty($password)) {
                echo "<div class='error'>Please provide both email and password</div>";
            } else {
                echo "<h2>Step 1: Check Failed Attempts</h2>";

                // Step 1: Check failed attempts
                $time_window = 1;
                $stmt = $conn->prepare("SELECT COUNT(*) as failed_count FROM logattempts WHERE email = ? AND success = 0 AND attempt > DATE_SUB(NOW(), INTERVAL ? MINUTE)");

                if (!$stmt) {
                    echo "<div class='error'>Prepare failed: " . $conn->error . "</div>";
                } else {
                    $stmt->bind_param("si", $email, $time_window);
                    if (!$stmt->execute()) {
                        echo "<div class='error'>Execute failed: " . $stmt->error . "</div>";
                    } else {
                        $result = $stmt->get_result();
                        $row = $result->fetch_assoc();
                        $failed_attempts = $row['failed_count'] ?? 0;

                        echo "<div class='info'>Failed attempts in last 1 minute: <strong>" . $failed_attempts . "</strong></div>";

                        if ($failed_attempts >= 5) {
                            echo "<div class='error'>❌ Account is LOCKED (5+ failed attempts)</div>";
                            $stmt->close();
                            die();
                        } else {
                            echo "<div class='success'>✓ Account is not locked</div>";
                        }
                    }
                    $stmt->close();
                }

                echo "<h2>Step 2: Check users1 Table</h2>";

                // Step 2: Check users1 table
                $stmt = $conn->prepare("SELECT id, password, is_active FROM users1 WHERE email = ?");

                if (!$stmt) {
                    echo "<div class='error'>Prepare failed: " . $conn->error . "</div>";
                } else {
                    $stmt->bind_param("s", $email);
                    if (!$stmt->execute()) {
                        echo "<div class='error'>Execute failed: " . $stmt->error . "</div>";
                    } else {
                        $result = $stmt->get_result();

                        if ($result->num_rows > 0) {
                            $user = $result->fetch_assoc();
                            echo "<div class='success'>✓ User found in users1</div>";

                            echo "<table>";
                            echo "<tr><th>Field</th><th>Value</th></tr>";
                            echo "<tr><td>ID</td><td>" . $user['id'] . "</td></tr>";
                            echo "<tr><td>Email</td><td>" . htmlspecialchars($email) . "</td></tr>";
                            echo "<tr><td>Active</td><td>" . ($user['is_active'] ? "YES ✓" : "NO ❌") . "</td></tr>";
                            echo "<tr><td>Hash (first 30 chars)</td><td><code>" . substr($user['password'], 0, 30) . "...</code></td></tr>";
                            echo "</table>";

                            if (!$user['is_active']) {
                                echo "<div class='error'>❌ Account is not active (is_active = 0)</div>";
                                echo "<div class='info'>User needs to verify OTP before login</div>";
                                $stmt->close();
                                die();
                            }

                            echo "<h2>Step 3: Verify Password</h2>";

                            $pwd_match = password_verify($password, $user['password']);

                            if ($pwd_match) {
                                echo "<div class='success'>✓ Password MATCHES!</div>";

                                echo "<h2>Step 4: Create Session</h2>";

                                $_SESSION['user_id'] = $user['id'];
                                $_SESSION['email'] = $email;
                                $_SESSION['role'] = 'user';

                                echo "<div class='success'>✓ Session created</div>";
                                echo "<table>";
                                echo "<tr><th>Session Variable</th><th>Value</th></tr>";
                                echo "<tr><td>\$_SESSION['user_id']</td><td>" . $_SESSION['user_id'] . "</td></tr>";
                                echo "<tr><td>\$_SESSION['email']</td><td>" . $_SESSION['email'] . "</td></tr>";
                                echo "<tr><td>\$_SESSION['role']</td><td>" . $_SESSION['role'] . "</td></tr>";
                                echo "</table>";

                                echo "<h2>Step 5: Log Success & Redirect</h2>";

                                $stmt2 = $conn->prepare("INSERT INTO logattempts (email, success) VALUES (?, 1)");
                                $stmt2->bind_param("s", $email);
                                if ($stmt2->execute()) {
                                    echo "<div class='success'>✓ Login recorded in logattempts</div>";
                                } else {
                                    echo "<div class='error'>Failed to record login: " . $stmt2->error . "</div>";
                                }
                                $stmt2->close();

                                echo "<div class='success'><strong>✓ LOGIN WOULD BE SUCCESSFUL!</strong></div>";
                                echo "<div class='info'>Redirect would go to: <code>https://cycloan-cldd.com/user_dashboard.php</code></div>";
                                echo "<div class='warning' style='background: #fff3e0; border: 2px solid #f57c00; padding: 10px; border-radius: 4px; margin-top: 15px;'>";
                                echo "<strong>⚠️ This is a TEST - actual redirect is commented in real login</strong>";
                                echo "</div>";

                            } else {
                                echo "<div class='error'>❌ Password DOES NOT MATCH</div>";
                                echo "<div class='info'>Entered password hashed: " . password_hash($password, PASSWORD_DEFAULT) . "</div>";
                                echo "<div class='info'>Stored password hash: " . $user['password'] . "</div>";

                                $stmt2 = $conn->prepare("INSERT INTO logattempts (email, success) VALUES (?, 0)");
                                $stmt2->bind_param("s", $email);
                                $stmt2->execute();
                                $stmt2->close();
                                echo "<div class='info'>Failed attempt recorded</div>";
                            }
                        } else {
                            echo "<div class='error'>❌ User NOT found in users1</div>";
                            echo "<div class='info'>Would check admin tables next...</div>";
                        }
                    }
                    $stmt->close();
                }
            }
        } else {
            echo "<div class='test-section'>";
            echo "<h2>Enter Test Credentials</h2>";
            echo "<form method='POST'>";
            echo "<div>";
            echo "<label>Email:</label><br>";
            echo "<input type='email' name='email' value='johnlestercamit@gmail.com' required>";
            echo "</div>";
            echo "<div>";
            echo "<label>Password:</label><br>";
            echo "<input type='password' name='password' placeholder='Enter password' required>";
            echo "</div>";
            echo "<button type='submit'>Test Login Process</button>";
            echo "</form>";
            echo "</div>";

            echo "<div class='test-section'>";
            echo "<h2>What This Tool Does</h2>";
            echo "<ul>";
            echo "<li>✓ Checks failed attempt count</li>";
            echo "<li>✓ Searches users1 table</li>";
            echo "<li>✓ Verifies account is active</li>";
            echo "<li>✓ Verifies password hash</li>";
            echo "<li>✓ Creates session variables</li>";
            echo "<li>✓ Shows redirect URL</li>";
            echo "<li>✓ Simulates EXACT login process</li>";
            echo "</ul>";
            echo "</div>";
        }
        ?>
    </div>
</body>

</html>