<?php
session_start();
require 'CYCLOAN_db.php';

// Simulate what happens on login
$email = 'test@example.com';  // Admin2 email from documentation
$_SESSION['email'] = $email;
$_SESSION['user_id'] = 1;  // Dummy user_id

echo "<h2>Testing Admin2 Dashboard Email Lookup</h2>";
echo "<p>Session Email: " . htmlspecialchars($_SESSION['email']) . "</p>";

// Test 1: Direct query without normalization
echo "<h3>Test 1: Direct Query (Original Method)</h3>";
$stmt = $conn->prepare("SELECT id, first_name, last_name, email FROM admin2 WHERE email = ? LIMIT 1");
if ($stmt === false) {
    echo "Failed to prepare statement: " . $conn->error;
} else {
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        $admin = $result->fetch_assoc();
        echo "<p style='color: green;'>✓ FOUND Admin2 Record:</p>";
        echo "<pre>" . print_r($admin, true) . "</pre>";
    } else {
        echo "<p style='color: red;'>✗ NOT FOUND with direct email match</p>";
    }
    $stmt->close();
}

// Test 2: Query with LOWER/TRIM (New Method)
echo "<h3>Test 2: Normalized Query (New Method)</h3>";
$normalizedEmail = trim(strtolower($email));
echo "<p>Normalized Email: " . htmlspecialchars($normalizedEmail) . "</p>";

$stmt = $conn->prepare("SELECT id, first_name, last_name, email FROM admin2 WHERE LOWER(TRIM(email)) = LOWER(TRIM(?)) LIMIT 1");
if ($stmt === false) {
    echo "Failed to prepare statement: " . $conn->error;
} else {
    $stmt->bind_param("s", $normalizedEmail);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        $admin = $result->fetch_assoc();
        echo "<p style='color: green;'>✓ FOUND Admin2 Record:</p>";
        echo "<pre>" . print_r($admin, true) . "</pre>";
    } else {
        echo "<p style='color: red;'>✗ NOT FOUND with normalized email match</p>";
    }
    $stmt->close();
}

// Test 3: List all admin2 records
echo "<h3>Test 3: All Admin2 Records in Database</h3>";
$result = $conn->query("SELECT id, first_name, last_name, email FROM admin2 LIMIT 10");
if ($result) {
    if ($result->num_rows > 0) {
        echo "<table border='1' cellpadding='10'>";
        echo "<tr><th>ID</th><th>First Name</th><th>Last Name</th><th>Email</th></tr>";
        while ($row = $result->fetch_assoc()) {
            echo "<tr>";
            echo "<td>" . htmlspecialchars($row['id']) . "</td>";
            echo "<td>" . htmlspecialchars($row['first_name']) . "</td>";
            echo "<td>" . htmlspecialchars($row['last_name']) . "</td>";
            echo "<td>" . htmlspecialchars($row['email']) . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<p style='color: red;'>No admin2 records found in database</p>";
    }
} else {
    echo "Query error: " . $conn->error;
}
?>