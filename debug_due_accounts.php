<?php
/**
 * Due Accounts Debugging Tool
 * Comprehensive test for due accounts functionality
 */

require_once 'CYCLOAN_db.php';
session_start();

echo "<h2>🔍 Due Accounts Debug Tool</h2>\n";
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
    .test-pass { background: #d4edda; border: 1px solid #c3e6cb; color: #155724; padding: 10px; border-radius: 5px; margin: 5px 0; }
    .test-fail { background: #f8d7da; border: 1px solid #f5c6cb; color: #721c24; padding: 10px; border-radius: 5px; margin: 5px 0; }
    .code { background: #f8f9fa; padding: 10px; border-radius: 5px; margin: 10px 0; font-family: monospace; overflow-x: auto; }
    .sql-query { background: #e8f5e8; border-left: 4px solid #27ae60; padding: 10px; margin: 10px 0; }
</style>";

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

// Test 1: Database Connection
echo "<div class='section'>";
echo "<h3>Test 1: Database Connection</h3>";
if ($conn && $conn->ping()) {
    echo "<div class='test-pass'>✅ Database connection successful</div>";
} else {
    echo "<div class='test-fail'>❌ Database connection failed</div>";
    if ($conn) {
        echo "<p><strong>Error:</strong> " . $conn->error . "</p>";
    }
    exit;
}
echo "</div>";

// Test 2: Check Required Tables
echo "<div class='section'>";
echo "<h3>Test 2: Required Tables Check</h3>";
$requiredTables = ['payment_schedules', 'loans', 'loan_applications', 'users1'];
foreach ($requiredTables as $table) {
    try {
        $result = $conn->query("SHOW TABLES LIKE '$table'");
        if ($result && $result->num_rows > 0) {
            echo "<div class='test-pass'>✅ Table '$table' exists</div>";

            // Count records
            $count = $conn->query("SELECT COUNT(*) as total FROM $table");
            if ($count) {
                $row = $count->fetch_assoc();
                echo "<p class='info'>Records in $table: {$row['total']}</p>";
            }
        } else {
            echo "<div class='test-fail'>❌ Table '$table' does NOT exist</div>";
        }
    } catch (Exception $e) {
        echo "<div class='test-fail'>❌ Error checking table '$table': " . $e->getMessage() . "</div>";
    }
}
echo "</div>";

// Test 3: Check payment_schedules structure
echo "<div class='section'>";
echo "<h3>Test 3: Payment Schedules Structure</h3>";
try {
    $result = $conn->query("DESCRIBE payment_schedules");
    if ($result) {
        echo "<table>";
        echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th></tr>";
        while ($row = $result->fetch_assoc()) {
            echo "<tr><td>{$row['Field']}</td><td>{$row['Type']}</td><td>{$row['Null']}</td><td>{$row['Key']}</td><td>{$row['Default']}</td></tr>";
        }
        echo "</table>";
        echo "<div class='test-pass'>✅ payment_schedules table structure retrieved</div>";
    }
} catch (Exception $e) {
    echo "<div class='test-fail'>❌ Error getting payment_schedules structure: " . $e->getMessage() . "</div>";
}
echo "</div>";

// Test 4: Check for sample data
echo "<div class='section'>";
echo "<h3>Test 4: Sample Data Check</h3>";

echo "<h4>Payment Schedules Sample:</h4>";
try {
    $samplePayments = executeQuery($conn, "SELECT payment_id, loan_id, due_date, amount, status FROM payment_schedules LIMIT 5");
    if ($samplePayments && count($samplePayments) > 0) {
        echo "<table>";
        echo "<tr><th>Payment ID</th><th>Loan ID</th><th>Due Date</th><th>Amount</th><th>Status</th></tr>";
        foreach ($samplePayments as $payment) {
            echo "<tr><td>{$payment['payment_id']}</td><td>{$payment['loan_id']}</td><td>{$payment['due_date']}</td><td>{$payment['amount']}</td><td>{$payment['status']}</td></tr>";
        }
        echo "</table>";
        echo "<div class='test-pass'>✅ Sample payment schedules found</div>";
    } else {
        echo "<div class='test-fail'>❌ No payment schedules found</div>";
    }
} catch (Exception $e) {
    echo "<div class='test-fail'>❌ Error getting sample payments: " . $e->getMessage() . "</div>";
}

echo "<h4>Loans Sample:</h4>";
try {
    $sampleLoans = executeQuery($conn, "SELECT loan_id, application_id, status FROM loans LIMIT 5");
    if ($sampleLoans && count($sampleLoans) > 0) {
        echo "<table>";
        echo "<tr><th>Loan ID</th><th>Application ID</th><th>Status</th></tr>";
        foreach ($sampleLoans as $loan) {
            echo "<tr><td>{$loan['loan_id']}</td><td>{$loan['application_id']}</td><td>{$loan['status']}</td></tr>";
        }
        echo "</table>";
        echo "<div class='test-pass'>✅ Sample loans found</div>";
    } else {
        echo "<div class='test-fail'>❌ No loans found</div>";
    }
} catch (Exception $e) {
    echo "<div class='test-fail'>❌ Error getting sample loans: " . $e->getMessage() . "</div>";
}
echo "</div>";

// Test 5: Test the exact due accounts query
echo "<div class='section'>";
echo "<h3>Test 5: Due Accounts Query Test</h3>";

$dueAccountsQuery = "
    SELECT ps.due_date, u.first_name, u.last_name, ps.amount, ps.payment_id, la.loan_id, la.application_id
    FROM payment_schedules ps
    JOIN loans l ON ps.loan_id = l.loan_id
    JOIN loan_applications la ON l.application_id = la.application_id
    JOIN users1 u ON la.user_id = u.id
    WHERE ps.status IN ('pending', 'partial', 'Unpaid')
    AND l.status IN ('active', 'Active')
    AND la.status = 'Active'
    AND ps.due_date <= DATE_ADD(CURDATE(), INTERVAL 30 DAY)
    AND ps.due_date >= CURDATE()
    ORDER BY ps.due_date ASC
    LIMIT 50
";

echo "<div class='sql-query'>";
echo "<h4>SQL Query Being Used:</h4>";
echo "<pre>" . htmlspecialchars($dueAccountsQuery) . "</pre>";
echo "</div>";

try {
    $dueAccounts = executeQuery($conn, $dueAccountsQuery);
    if ($dueAccounts !== false) {
        if (count($dueAccounts) > 0) {
            echo "<div class='test-pass'>✅ Query executed successfully - Found " . count($dueAccounts) . " due accounts</div>";
            echo "<table>";
            echo "<tr><th>Payment ID</th><th>Name</th><th>Due Date</th><th>Amount</th><th>Loan ID</th><th>App ID</th></tr>";
            foreach ($dueAccounts as $due) {
                $name = trim($due['first_name'] . ' ' . $due['last_name']);
                echo "<tr><td>{$due['payment_id']}</td><td>{$name}</td><td>{$due['due_date']}</td><td>₱{$due['amount']}</td><td>{$due['loan_id']}</td><td>{$due['application_id']}</td></tr>";
            }
            echo "</table>";
        } else {
            echo "<div class='test-fail'>❌ Query executed but returned 0 results</div>";
            echo "<p class='warning'>This means there are no accounts due within the next 30 days with the specified criteria.</p>";
        }
    } else {
        echo "<div class='test-fail'>❌ Query execution failed</div>";
    }
} catch (Exception $e) {
    echo "<div class='test-fail'>❌ Error executing due accounts query: " . $e->getMessage() . "</div>";
}
echo "</div>";

// Test 6: Breakdown the query to find issues
echo "<div class='section'>";
echo "<h3>Test 6: Query Breakdown Analysis</h3>";

echo "<h4>Step 1: Check payment_schedules with criteria</h4>";
try {
    $paymentCount = executeQuery($conn, "SELECT COUNT(*) as total FROM payment_schedules WHERE status IN ('pending', 'partial', 'Unpaid')");
    if ($paymentCount && count($paymentCount) > 0) {
        echo "<p class='info'>Payment schedules with pending/partial/unpaid status: {$paymentCount[0]['total']}</p>";
    }

    $dateCount = executeQuery($conn, "SELECT COUNT(*) as total FROM payment_schedules WHERE due_date <= DATE_ADD(CURDATE(), INTERVAL 30 DAY) AND due_date >= CURDATE()");
    if ($dateCount && count($dateCount) > 0) {
        echo "<p class='info'>Payment schedules due within 30 days: {$dateCount[0]['total']}</p>";
    }

    $combinedCount = executeQuery($conn, "SELECT COUNT(*) as total FROM payment_schedules WHERE status IN ('pending', 'partial', 'Unpaid') AND due_date <= DATE_ADD(CURDATE(), INTERVAL 30 DAY) AND due_date >= CURDATE()");
    if ($combinedCount && count($combinedCount) > 0) {
        echo "<p class='info'>Payment schedules matching both criteria: {$combinedCount[0]['total']}</p>";
    }
} catch (Exception $e) {
    echo "<p class='error'>Error in step 1: " . $e->getMessage() . "</p>";
}

echo "<h4>Step 2: Check loans status</h4>";
try {
    $loanCount = executeQuery($conn, "SELECT status, COUNT(*) as total FROM loans GROUP BY status");
    if ($loanCount && count($loanCount) > 0) {
        echo "<p class='info'>Loan statuses:</p>";
        echo "<table>";
        echo "<tr><th>Status</th><th>Count</th></tr>";
        foreach ($loanCount as $loan) {
            echo "<tr><td>{$loan['status']}</td><td>{$loan['total']}</td></tr>";
        }
        echo "</table>";
    }
} catch (Exception $e) {
    echo "<p class='error'>Error in step 2: " . $e->getMessage() . "</p>";
}

echo "<h4>Step 3: Check loan_applications status</h4>";
try {
    $appCount = executeQuery($conn, "SELECT status, COUNT(*) as total FROM loan_applications GROUP BY status");
    if ($appCount && count($appCount) > 0) {
        echo "<p class='info'>Loan application statuses:</p>";
        echo "<table>";
        echo "<tr><th>Status</th><th>Count</th></tr>";
        foreach ($appCount as $app) {
            echo "<tr><td>{$app['status']}</td><td>{$app['total']}</td></tr>";
        }
        echo "</table>";
    }
} catch (Exception $e) {
    echo "<p class='error'>Error in step 3: " . $e->getMessage() . "</p>";
}
echo "</div>";

// Test 7: Test AJAX endpoint
echo "<div class='section'>";
echo "<h3>Test 7: AJAX Endpoint Test</h3>";
echo "<p>Testing the AJAX endpoint that the JavaScript calls...</p>";

echo "<h4>Direct AJAX URL Test:</h4>";
echo "<p><a href='admin2_dashboard.php?action=get_due_accounts' target='_blank'>Test AJAX Endpoint</a></p>";

echo "<h4>Manual AJAX Test:</h4>";
echo "<div class='code'>";
echo "<p><strong>JavaScript test code you can run in browser console:</strong></p>";
echo "<pre>";
echo "fetch('admin2_dashboard.php?action=get_due_accounts')
  .then(response => response.json())
  .then(data => console.log('Due accounts data:', data))
  .catch(error => console.error('Error:', error));";
echo "</pre>";
echo "</div>";
echo "</div>";

// Test 8: Recommendations
echo "<div class='section'>";
echo "<h3>🎯 Troubleshooting Recommendations</h3>";
echo "<ul>";
echo "<li>🔍 <strong>If no due accounts found:</strong> Check if there are any payment schedules with due dates in the next 30 days</li>";
echo "<li>🔧 <strong>If query fails:</strong> Check table relationships and foreign key constraints</li>";
echo "<li>📱 <strong>If UI not updating:</strong> Check if admin2_dashboard.js is loading and functions are defined</li>";
echo "<li>🔄 <strong>If AJAX fails:</strong> Check browser network tab for errors and server response</li>";
echo "<li>📋 <strong>If data exists but not showing:</strong> Check the generateDueAccountsTable() JavaScript function</li>";
echo "</ul>";

echo "<p><strong>Next steps to test:</strong></p>";
echo "<ul>";
echo "<li><a href='admin2_dashboard.php'>Go to Admin Dashboard</a> - Check if due accounts section works</li>";
echo "<li><a href='admin2_dashboard.php?action=get_due_accounts'>Test AJAX Endpoint</a> - Should return JSON</li>";
echo "<li>Open browser console on admin dashboard and run: <code>refreshDueAccountsTable()</code></li>";
echo "<li>Check if any JavaScript errors appear in browser console</li>";
echo "</ul>";
echo "</div>";

$conn->close();
?>