<?php
require_once 'CYCLOAN_db.php';

echo "<h2>📊 Database Index Status</h2>";
echo "<pre>";

$tables = [
    'activity_logs' => ['idx_description_search', 'idx_module_created', 'idx_application_search', 'idx_user_role_id'],
    'documents' => ['idx_application_updated', 'idx_docid_appid'],
    'remarks' => ['idx_application_created'],
    'loan_applications' => ['idx_application_status', 'idx_created_at'],
    'payment_schedules' => ['idx_due_date_status'],
    'loans' => ['idx_loans_status']
];

foreach ($tables as $tableName => $expectedIndexes) {
    echo "\n📋 Table: $tableName\n";
    echo "─────────────────────────────────────\n";
    
    $result = $conn->query("SHOW INDEXES FROM $tableName");
    if (!$result) {
        echo "❌ Error: " . $conn->error . "\n";
        continue;
    }
    
    $existingIndexes = [];
    while ($row = $result->fetch_assoc()) {
        $existingIndexes[$row['Key_name']] = $row['Column_name'];
    }
    
    foreach ($expectedIndexes as $idxName) {
        if (isset($existingIndexes[$idxName])) {
            echo "✅ $idxName: EXISTS on " . $existingIndexes[$idxName] . "\n";
        } else {
            echo "❌ $idxName: MISSING\n";
        }
    }
}

echo "\n\n🚀 To apply missing indexes, visit: http://localhost:8000/apply_indexes.php\n";

$conn->close();
?>
