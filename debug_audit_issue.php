<?php
require_once 'CYCLOAN_db.php';

echo "<h2>DOCUMENT AUDIT DEBUG ANALYSIS</h2>";

// Check for documents with application_id = 0 or NULL
echo "<h3>1. Documents with problematic application_id values:</h3>";
$problematicDocs = executeQuery($conn, "
    SELECT document_id, application_id, document_type_id, status, file_path, created_at 
    FROM documents 
    WHERE application_id IS NULL OR application_id = '' OR application_id = '0'
    ORDER BY created_at DESC 
    LIMIT 10
", "", []);

if (empty($problematicDocs)) {
    echo "<p>✅ No documents found with NULL, empty, or '0' application_id</p>";
} else {
    echo "<table border='1'>";
    echo "<tr><th>Document ID</th><th>Application ID</th><th>Type ID</th><th>Status</th><th>File</th><th>Created</th></tr>";
    foreach ($problematicDocs as $doc) {
        echo "<tr>";
        echo "<td>{$doc['document_id']}</td>";
        echo "<td>" . (empty($doc['application_id']) ? 'EMPTY/NULL' : $doc['application_id']) . "</td>";
        echo "<td>{$doc['document_type_id']}</td>";
        echo "<td>{$doc['status']}</td>";
        echo "<td>" . basename($doc['file_path']) . "</td>";
        echo "<td>{$doc['created_at']}</td>";
        echo "</tr>";
    }
    echo "</table>";
}

// Check recent document status changes
echo "<h3>2. Recent documents activity (last 10):</h3>";
$recentDocs = executeQuery($conn, "
    SELECT d.document_id, d.application_id, dt.document_name, d.status, d.updated_at
    FROM documents d
    LEFT JOIN document_types dt ON d.document_type_id = dt.document_type_id
    ORDER BY d.updated_at DESC 
    LIMIT 10
", "", []);

echo "<table border='1'>";
echo "<tr><th>Document ID</th><th>Application ID</th><th>Document Name</th><th>Status</th><th>Updated</th></tr>";
foreach ($recentDocs as $doc) {
    echo "<tr>";
    echo "<td>{$doc['document_id']}</td>";
    echo "<td>" . (empty($doc['application_id']) ? '<span style="color:red">EMPTY/NULL</span>' : $doc['application_id']) . "</td>";
    echo "<td>{$doc['document_name']}</td>";
    echo "<td>{$doc['status']}</td>";
    echo "<td>{$doc['updated_at']}</td>";
    echo "</tr>";
}
echo "</table>";

// Check document_audit records for recent activity
echo "<h3>3. Recent document_audit entries:</h3>";
$recentAudits = executeQuery($conn, "
    SELECT audit_id, document_id, application_id, old_status, new_status, admin_name, notes, created_at
    FROM document_audit 
    ORDER BY created_at DESC 
    LIMIT 10
", "", []);

if (empty($recentAudits)) {
    echo "<p>❌ No audit records found</p>";
} else {
    echo "<table border='1'>";
    echo "<tr><th>Audit ID</th><th>Doc ID</th><th>App ID</th><th>Old Status</th><th>New Status</th><th>Admin</th><th>Notes</th><th>Created</th></tr>";
    foreach ($recentAudits as $audit) {
        echo "<tr>";
        echo "<td>{$audit['audit_id']}</td>";
        echo "<td>{$audit['document_id']}</td>";
        echo "<td>" . (empty($audit['application_id']) ? '<span style="color:red">EMPTY/NULL</span>' : $audit['application_id']) . "</td>";
        echo "<td>{$audit['old_status']}</td>";
        echo "<td>{$audit['new_status']}</td>";
        echo "<td>{$audit['admin_name']}</td>";
        echo "<td>" . (empty($audit['notes']) ? 'No notes' : substr($audit['notes'], 0, 30) . '...') . "</td>";
        echo "<td>{$audit['created_at']}</td>";
        echo "</tr>";
    }
    echo "</table>";
}

// Check for documents that should have audit records but don't
echo "<h3>4. Cross-check: Documents vs Audit Records:</h3>";
echo "<p>Checking if any documents have been updated but lack corresponding audit entries...</p>";

$docsWithoutAudit = executeQuery($conn, "
    SELECT d.document_id, d.application_id, dt.document_name, d.status, d.updated_at,
           COUNT(da.audit_id) as audit_count
    FROM documents d
    LEFT JOIN document_types dt ON d.document_type_id = dt.document_type_id
    LEFT JOIN document_audit da ON d.document_id = da.document_id
    WHERE d.updated_at > DATE_SUB(NOW(), INTERVAL 7 DAYS)
    GROUP BY d.document_id
    HAVING audit_count = 0
    ORDER BY d.updated_at DESC
    LIMIT 10
", "", []);

if (empty($docsWithoutAudit)) {
    echo "<p>✅ All recently updated documents have audit records</p>";
} else {
    echo "<p>❌ Found documents with recent updates but NO audit records:</p>";
    echo "<table border='1'>";
    echo "<tr><th>Document ID</th><th>Application ID</th><th>Document Name</th><th>Status</th><th>Updated</th><th>Audit Count</th></tr>";
    foreach ($docsWithoutAudit as $doc) {
        echo "<tr style='background-color: #ffe6e6;'>";
        echo "<td>{$doc['document_id']}</td>";
        echo "<td>" . (empty($doc['application_id']) ? '<span style="color:red">EMPTY/NULL</span>' : $doc['application_id']) . "</td>";
        echo "<td>{$doc['document_name']}</td>";
        echo "<td>{$doc['status']}</td>";
        echo "<td>{$doc['updated_at']}</td>";
        echo "<td>{$doc['audit_count']}</td>";
        echo "</tr>";
    }
    echo "</table>";
}

// Check for specific document ID 102 (mentioned in logs)
echo "<h3>5. Document ID 102 Analysis (from debug logs):</h3>";
$doc102 = executeQuery($conn, "
    SELECT d.document_id, d.application_id, dt.document_name, d.status, d.file_path, d.created_at, d.updated_at
    FROM documents d
    LEFT JOIN document_types dt ON d.document_type_id = dt.document_type_id
    WHERE d.document_id = 102
", "", []);

if (empty($doc102)) {
    echo "<p>Document ID 102 not found</p>";
} else {
    $doc = $doc102[0];
    echo "<table border='1'>";
    echo "<tr><th>Field</th><th>Value</th></tr>";
    echo "<tr><td>Document ID</td><td>{$doc['document_id']}</td></tr>";
    echo "<tr><td>Application ID</td><td>" . (empty($doc['application_id']) ? '<span style="color:red">EMPTY/NULL</span>' : $doc['application_id']) . "</td></tr>";
    echo "<tr><td>Document Name</td><td>{$doc['document_name']}</td></tr>";
    echo "<tr><td>Status</td><td>{$doc['status']}</td></tr>";
    echo "<tr><td>File Path</td><td>{$doc['file_path']}</td></tr>";
    echo "<tr><td>Created</td><td>{$doc['created_at']}</td></tr>";
    echo "<tr><td>Updated</td><td>{$doc['updated_at']}</td></tr>";
    echo "</table>";

    // Check audit records for document 102
    $audit102 = executeQuery($conn, "
        SELECT * FROM document_audit WHERE document_id = 102 ORDER BY created_at DESC
    ", "", []);

    echo "<h4>Audit records for Document 102:</h4>";
    if (empty($audit102)) {
        echo "<p>❌ No audit records found for Document ID 102</p>";
    } else {
        echo "<table border='1'>";
        echo "<tr><th>Audit ID</th><th>App ID</th><th>Old Status</th><th>New Status</th><th>Admin</th><th>Created</th></tr>";
        foreach ($audit102 as $audit) {
            echo "<tr>";
            echo "<td>{$audit['audit_id']}</td>";
            echo "<td>" . (empty($audit['application_id']) ? '<span style="color:red">EMPTY/NULL</span>' : $audit['application_id']) . "</td>";
            echo "<td>{$audit['old_status']}</td>";
            echo "<td>{$audit['new_status']}</td>";
            echo "<td>{$audit['admin_name']}</td>";
            echo "<td>{$audit['created_at']}</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
}

function executeQuery($conn, $query, $types = '', $params = [])
{
    $stmt = $conn->prepare($query);
    if ($stmt === false) {
        die("Query preparation failed: " . $conn->error);
    }

    if (!empty($types) && !empty($params)) {
        $stmt->bind_param($types, ...$params);
    }

    $stmt->execute();
    $result = $stmt->get_result();
    $data = [];
    while ($row = $result->fetch_assoc()) {
        $data[] = $row;
    }
    $stmt->close();
    return $data;
}
?>