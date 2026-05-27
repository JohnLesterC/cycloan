<?php
/**
 * CHECK_USER_EMAILS.PHP
 * Simple script to check what email addresses are in the database
 * Run this in a browser to see what emails exist
 */

// Include database connection
require_once 'CYCLOAN_db.php';

echo "<h2>CYCLOAN - User Email Check</h2>";
echo "<p>This script checks the email addresses in your database for active loan applications.</p>";
echo "<hr>";

if (!isset($conn)) {
    echo "<p style='color: red;'>ERROR: Database connection not established</p>";
    exit;
}

// Check users table
echo "<h3>📧 User Emails in Database</h3>";
echo "<p>Checking loan_applications with associated user emails...</p>";

$query = "SELECT la.application_id, la.application_status, u.id, u.email, u.first_name, u.last_name, u.phone
          FROM loan_applications la
          JOIN users1 u ON la.user_id = u.id
          ORDER BY la.application_id DESC
          LIMIT 20";

$result = $conn->query($query);

if ($result && $result->num_rows > 0) {
    echo "<table border='1' cellpadding='10' cellspacing='0' style='border-collapse: collapse; width: 100%;'>";
    echo "<tr style='background-color: #4caf50; color: white;'>";
    echo "<th>App ID</th>";
    echo "<th>Status</th>";
    echo "<th>User Name</th>";
    echo "<th>Email Address</th>";
    echo "<th>Phone</th>";
    echo "<th>Email Valid?</th>";
    echo "</tr>";

    $count = 0;
    while ($row = $result->fetch_assoc()) {
        $count++;
        $email = $row['email'];
        $isValid = filter_var($email, FILTER_VALIDATE_EMAIL) ? '<span style="color: green;">✓ Valid</span>' : '<span style="color: red;">✗ Invalid</span>';
        $isEmpty = empty($email) ? '<span style="color: orange;">⚠ EMPTY</span>' : $isValid;

        echo "<tr>";
        echo "<td>" . htmlspecialchars($row['application_id']) . "</td>";
        echo "<td>" . htmlspecialchars($row['application_status']) . "</td>";
        echo "<td>" . htmlspecialchars($row['first_name'] . ' ' . $row['last_name']) . "</td>";
        echo "<td><code>" . htmlspecialchars($email) . "</code></td>";
        echo "<td>" . htmlspecialchars($row['phone']) . "</td>";
        echo "<td>" . $isEmpty . "</td>";
        echo "</tr>";
    }

    echo "</table>";
    echo "<p><strong>Total records shown:</strong> $count</p>";
} else {
    echo "<p style='color: red;'>ERROR: No loan applications found</p>";
}

// Summary
echo "<hr>";
echo "<h3>📊 Summary</h3>";

$summaryQuery = "SELECT 
    COUNT(*) as total,
    SUM(CASE WHEN u.email IS NULL THEN 1 ELSE 0 END) as null_emails,
    SUM(CASE WHEN u.email = '' THEN 1 ELSE 0 END) as empty_emails,
    SUM(CASE WHEN u.email IS NOT NULL AND u.email != '' THEN 1 ELSE 0 END) as valid_emails
FROM loan_applications la
JOIN users1 u ON la.user_id = u.id";

$summaryResult = $conn->query($summaryQuery);

if ($summaryResult && $summaryResult->num_rows > 0) {
    $summary = $summaryResult->fetch_assoc();
    echo "<ul>";
    echo "<li><strong>Total Applications:</strong> " . $summary['total'] . "</li>";
    echo "<li><strong>NULL Emails:</strong> <span style='color: red;'>" . $summary['null_emails'] . "</span></li>";
    echo "<li><strong>Empty Emails:</strong> <span style='color: red;'>" . $summary['empty_emails'] . "</span></li>";
    echo "<li><strong>Valid Emails:</strong> <span style='color: green;'>" . $summary['valid_emails'] . "</span></li>";
    echo "</ul>";

    if ($summary['valid_emails'] == 0) {
        echo "<p style='color: red; font-weight: bold;'>⚠️ WARNING: No valid email addresses found in database!</p>";
    }
} else {
    echo "<p>Could not retrieve summary</p>";
}

echo "<hr>";
echo "<p style='color: #666; font-size: 12px;'>";
echo "Page generated: " . date('Y-m-d H:i:s') . "<br>";
echo "Script: check_user_emails.php";
echo "</p>";

?>