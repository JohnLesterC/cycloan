<?php
/**
 * Migration: Add activity_type column to activity_logs table
 * This enables activity notifications to be properly recorded and displayed
 */

require_once 'CYCLOAN_db.php';

echo "Step 1: Checking if activity_logs table exists...\n";
$result = $conn->query("SHOW TABLES LIKE 'activity_logs'");
if ($result && $result->num_rows > 0) {
    echo "✓ activity_logs table exists\n\n";
} else {
    echo "✗ activity_logs table does not exist. Please create it first.\n";
    exit;
}

echo "Step 2: Checking if activity_type column exists...\n";
$check = $conn->query("SHOW COLUMNS FROM activity_logs LIKE 'activity_type'");
if ($check && $check->num_rows > 0) {
    echo "✓ activity_type column already exists\n";
} else {
    echo "Adding activity_type column...\n";
    $alter_sql = "ALTER TABLE activity_logs 
                  ADD COLUMN activity_type VARCHAR(50) DEFAULT 'other' 
                  AFTER module";

    if ($conn->query($alter_sql)) {
        echo "✓ activity_type column added successfully\n";
    } else {
        echo "✗ Error adding column: " . $conn->error . "\n";
        exit;
    }
}

echo "\nStep 3: Populating activity_type based on existing data...\n";

// Map action_type and description to activity_type
$updates = [
    "UPDATE activity_logs 
     SET activity_type = 'pre-approval' 
     WHERE module = 'loan_application' 
     AND (description LIKE '%Pre-Approval%' OR description LIKE '%pre-approval%')",

    "UPDATE activity_logs 
     SET activity_type = 'credit-investigation' 
     WHERE module = 'loan_application' 
     AND (description LIKE '%Credit%Investigation%' OR description LIKE '%credit%investigation%')",

    "UPDATE activity_logs 
     SET activity_type = 'loan-status' 
     WHERE module = 'loan_application' 
     AND (description LIKE '%Loan Status%' OR action_type = 'update' AND description LIKE '%status%')"
];

$count = 0;
foreach ($updates as $sql) {
    if ($conn->query($sql)) {
        $affected = $conn->affected_rows;
        echo "✓ Updated $affected records\n";
        $count += $affected;
    } else {
        echo "✗ Error: " . $conn->error . "\n";
    }
}

echo "\nStep 4: Adding index for activity_type column...\n";
$index_check = $conn->query("SHOW INDEX FROM activity_logs WHERE Column_name = 'activity_type'");
if ($index_check && $index_check->num_rows > 0) {
    echo "✓ Index already exists\n";
} else {
    if ($conn->query("CREATE INDEX idx_activity_type ON activity_logs (activity_type)")) {
        echo "✓ Index created successfully\n";
    } else {
        echo "⚠ Could not create index: " . $conn->error . "\n";
    }
}

echo "\n✅ Migration complete!\n";
echo "\nSummary:\n";
echo "- activity_type column added to activity_logs table\n";
echo "- $count existing records updated with activity_type values\n";
echo "- Pre-approval, credit investigation, and loan status updates will now appear in notifications\n";
?>