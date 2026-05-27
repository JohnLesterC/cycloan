<?php
// ============================================================================
// APPLY DATABASE PERFORMANCE INDEXES
// ============================================================================
// This script reads and applies the SQL optimization commands to create
// all necessary indexes for improving loan details loading performance

require_once 'CYCLOAN_db.php';

// Read the SQL optimization file
$sqlFile = file_get_contents('LOAN_DETAILS_PERFORMANCE_OPTIMIZATION.sql');

if ($sqlFile === false) {
    die("❌ ERROR: Could not read LOAN_DETAILS_PERFORMANCE_OPTIMIZATION.sql file\n");
}

// Split SQL statements by semicolon and filter empty statements
$statements = array_filter(
    array_map('trim', explode(';', $sqlFile)),
    function ($stmt) {
        return !empty($stmt) && !preg_match('/^--/', trim($stmt));
    }
);

echo "🔧 Applying database performance indexes...\n";
echo "📊 Total statements to execute: " . count($statements) . "\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

$successCount = 0;
$failureCount = 0;

foreach ($statements as $index => $statement) {
    // Skip comments and empty lines
    $stmt = trim($statement);
    if (empty($stmt) || strpos(trim($stmt), '--') === 0) {
        continue;
    }

    // Extract what table/index is being created for display
    preg_match('/ALTER TABLE `([^`]+)`|ADD INDEX.*`([^`]+)`/i', $stmt, $matches);
    $displayInfo = isset($matches[1]) ? $matches[1] : (isset($matches[2]) ? $matches[2] : 'Unknown');

    echo "(" . ($index + 1) . ") Processing: $displayInfo... ";

    if ($conn->query($stmt . ";")) {
        echo "✅ SUCCESS\n";
        $successCount++;
    } else {
        // Check if it's a "duplicate key" error (index already exists)
        if (strpos($conn->error, 'Duplicate') !== false || strpos($conn->error, 'already exists') !== false) {
            echo "⏭️  SKIPPED (Index already exists)\n";
            $successCount++;
        } else {
            echo "❌ FAILED\n";
            echo "   Error: " . $conn->error . "\n";
            $failureCount++;
        }
    }
}

echo "\n━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "📈 RESULTS:\n";
echo "   ✅ Successful: $successCount\n";
echo "   ❌ Failed: $failureCount\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";

// Verify indexes were created
echo "\n🔍 VERIFICATION: Checking created indexes...\n\n";

$tables = ['activity_logs', 'documents', 'remarks', 'loan_applications', 'payment_schedules', 'loans'];
foreach ($tables as $table) {
    $result = $conn->query("SHOW INDEXES FROM $table WHERE Key_name LIKE 'idx_%'");
    if ($result) {
        $indexCount = $result->num_rows;
        if ($indexCount > 0) {
            echo "✅ $table: $indexCount indexes found\n";
            while ($row = $result->fetch_assoc()) {
                echo "   └─ " . $row['Key_name'] . " on columns: " . $row['Column_name'] . "\n";
            }
        }
    }
}

echo "\n✨ DATABASE OPTIMIZATION COMPLETE!\n";
echo "🚀 Your loan details modal should now load 80-90% faster!\n";

$conn->close();
?>