<?php
// Simple debug script to test database queries
error_reporting(E_ALL);
ini_set('display_errors', 1);

require "CYCLOAN_db.php";

echo "<h1>Database Query Test</h1>";

// Test 1: Basic connection
echo "<h2>1. Database Connection</h2>";
if ($conn) {
    echo "✓ Connected successfully<br>";
    echo "Database: " . $conn->server_info . "<br>";
} else {
    echo "✗ Connection failed: " . mysqli_connect_error() . "<br>";
    exit;
}

// Test 2: Check tables exist
echo "<h2>2. Tables Check</h2>";
$tables = ['loan_applications', 'payment_schedules', 'loans', 'loan_types', 'users1'];
foreach ($tables as $table) {
    $result = $conn->query("SHOW TABLES LIKE '$table'");
    if ($result && $result->num_rows > 0) {
        echo "✓ Table '$table' exists<br>";
    } else {
        echo "✗ Table '$table' NOT FOUND<br>";
    }
}

// Test 3: KPI Query
echo "<h2>3. KPI Query Test</h2>";
$query = "SELECT 
            COUNT(*) as total_applications,
            SUM(CASE WHEN status = 'Approved' THEN 1 ELSE 0 END) as approved,
            SUM(CASE WHEN status = 'Approved' THEN final_loan_amount ELSE 0 END) as total_disbursed,
            AVG(CASE WHEN status = 'Approved' THEN final_loan_amount END) as avg_loan_amount
          FROM loan_applications";
$result = $conn->query($query);
if ($result) {
    $row = $result->fetch_assoc();
    echo "✓ KPI Query successful<br>";
    echo "Total Applications: " . $row['total_applications'] . "<br>";
} else {
    echo "✗ KPI Query failed: " . $conn->error . "<br>";
}

// Test 4: Repayment Metrics Query  
echo "<h2>4. Repayment Metrics Query Test</h2>";
$query = "SELECT 
            SUM(ps.amount_paid) as total_repaid,
            SUM(ps.amount) as total_expected
          FROM payment_schedules ps 
          JOIN loans l ON ps.loan_id = l.loan_id";
$result = $conn->query($query);
if ($result) {
    echo "✓ Repayment Metrics Query successful<br>";
    $row = $result->fetch_assoc();
    echo "Total Repaid: " . ($row['total_repaid'] ?? 'NULL') . "<br>";
} else {
    echo "✗ Repayment Metrics Query failed: " . $conn->error . "<br>";
}

// Test 5: Monthly Trends Query
echo "<h2>5. Monthly Trends Query Test</h2>";
$query = "SELECT 
            DATE_FORMAT(created_at, '%Y-%m') as month,
            COUNT(*) as applications
          FROM loan_applications
          WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
          GROUP BY month
          ORDER BY month ASC";
$result = $conn->query($query);
if ($result) {
    echo "✓ Monthly Trends Query successful<br>";
    echo "Rows returned: " . $result->num_rows . "<br>";
} else {
    echo "✗ Monthly Trends Query failed: " . $conn->error . "<br>";
}

// Test 6: Loan Type Performance Query
echo "<h2>6. Loan Type Performance Query Test</h2>";
$query = "SELECT 
            lt.type_name,
            COUNT(la.application_id) as total_count
          FROM loan_types lt
          LEFT JOIN loan_applications la ON lt.loan_type_id = la.loan_type_id
          GROUP BY lt.type_name";
$result = $conn->query($query);
if ($result) {
    echo "✓ Loan Type Performance Query successful<br>";
    echo "Rows returned: " . $result->num_rows . "<br>";
} else {
    echo "✗ Loan Type Performance Query failed: " . $conn->error . "<br>";
}

echo "<h2>Test Complete</h2>";
echo "Check which queries failed above to identify the issue.";
?>