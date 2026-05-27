<?php
// Test script to verify document_audit integration
require_once 'CYCLOAN_db.php';

echo "<h2>Document Audit Integration Test</h2>\n";

// 1. Check if document_audit table exists and show its structure
echo "<h3>1. Document Audit Table Structure</h3>\n";
$tableCheck = $conn->query("SHOW TABLES LIKE 'document_audit'");
if ($tableCheck && $tableCheck->num_rows > 0) {
    echo "✅ document_audit table exists<br>\n";

    $structure = $conn->query("DESCRIBE document_audit");
    if ($structure) {
        echo "<table border='1' style='border-collapse: collapse;'>\n";
        echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>\n";
        while ($row = $structure->fetch_assoc()) {
            echo "<tr>";
            foreach ($row as $col => $val) {
                echo "<td>" . htmlspecialchars($val ?? '') . "</td>";
            }
            echo "</tr>\n";
        }
        echo "</table><br>\n";
    }
} else {
    echo "❌ document_audit table does not exist<br>\n";
    exit;
}

// 2. Check recent document_audit entries
echo "<h3>2. Recent Document Audit Entries (Last 10)</h3>\n";
$auditQuery = "SELECT * FROM document_audit ORDER BY created_at DESC LIMIT 10";
$result = $conn->query($auditQuery);

if ($result && $result->num_rows > 0) {
    echo "<table border='1' style='border-collapse: collapse;'>\n";
    echo "<tr><th>ID</th><th>Doc ID</th><th>App ID</th><th>Old Status</th><th>New Status</th><th>Admin Name</th><th>Admin Role</th><th>Notes</th><th>Created At</th></tr>\n";

    while ($row = $result->fetch_assoc()) {
        echo "<tr>";
        echo "<td>" . htmlspecialchars($row['id'] ?? '') . "</td>";
        echo "<td>" . htmlspecialchars($row['document_id'] ?? '') . "</td>";
        echo "<td>" . htmlspecialchars($row['application_id'] ?? '') . "</td>";
        echo "<td>" . htmlspecialchars($row['old_status'] ?? '') . "</td>";
        echo "<td>" . htmlspecialchars($row['new_status'] ?? '') . "</td>";
        echo "<td>" . htmlspecialchars($row['admin_name'] ?? '') . "</td>";
        echo "<td>" . htmlspecialchars($row['admin_role'] ?? '') . "</td>";
        echo "<td>" . htmlspecialchars(substr($row['notes'] ?? '', 0, 50)) . "...</td>";
        echo "<td>" . htmlspecialchars($row['created_at'] ?? '') . "</td>";
        echo "</tr>\n";
    }
    echo "</table><br>\n";
    echo "✅ Found " . $result->num_rows . " recent audit entries<br>\n";
} else {
    echo "ℹ️ No audit entries found yet<br>\n";
}

// 3. Check if documents table exists and show sample data
echo "<h3>3. Sample Documents (for testing)</h3>\n";
$docsQuery = "SELECT document_id, application_id, document_type_id, status FROM documents LIMIT 5";
$docsResult = $conn->query($docsQuery);

if ($docsResult && $docsResult->num_rows > 0) {
    echo "<table border='1' style='border-collapse: collapse;'>\n";
    echo "<tr><th>Document ID</th><th>Application ID</th><th>Document Type ID</th><th>Status</th></tr>\n";

    while ($row = $docsResult->fetch_assoc()) {
        echo "<tr>";
        echo "<td>" . htmlspecialchars($row['document_id'] ?? '') . "</td>";
        echo "<td>" . htmlspecialchars($row['application_id'] ?? '') . "</td>";
        echo "<td>" . htmlspecialchars($row['document_type_id'] ?? '') . "</td>";
        echo "<td>" . htmlspecialchars($row['status'] ?? '') . "</td>";
        echo "</tr>\n";
    }
    echo "</table><br>\n";
    echo "✅ Found " . $docsResult->num_rows . " sample documents for testing<br>\n";
} else {
    echo "ℹ️ No documents found for testing<br>\n";
}

// 4. Instructions for testing
echo "<h3>4. Testing Instructions</h3>\n";
echo "<p><strong>To test the document audit integration:</strong></p>\n";
echo "<ol>\n";
echo "<li>Open the admin2_dashboard.php</li>\n";
echo "<li>Find a loan application with documents</li>\n";
echo "<li>Click 'Add Test Queue' to create a queue entry</li>\n";
echo "<li>Change a document status from Pending to Approved or Rejected</li>\n";
echo "<li>Check back on this page to see if a new audit entry was created</li>\n";
echo "<li>The audit entry should show the old status, new status, admin details, and timestamp</li>\n";
echo "</ol>\n";

echo "<p><a href='admin2_dashboard.php'>➜ Go to Admin2 Dashboard</a></p>\n";
echo "<p><a href='test_document_audit.php'>🔄 Refresh this test page</a></p>\n";

$conn->close();
?>