<?php
/**
 * Optimize Database Tables & Rebuild Indexes
 * Run this to ensure MySQL uses indexes efficiently
 */

require_once 'CYCLOAN_db.php';

echo "🔧 Optimizing database tables and rebuilding index statistics...\n\n";

$tables = [
    'activity_logs',
    'documents',
    'document_types',
    'remarks',
    'loan_applications',
    'users1',
    'loan_types',
    'financial_info',
    'payment_schedules',
    'loans'
];

foreach ($tables as $table) {
    echo "Processing: $table... ";

    // ANALYZE TABLE rebuilds index statistics
    $analyzeResult = $conn->query("ANALYZE TABLE `$table`");

    // OPTIMIZE TABLE defragments table and rebuilds indexes
    $optimizeResult = $conn->query("OPTIMIZE TABLE `$table`");

    if ($analyzeResult && $optimizeResult) {
        echo "✅ DONE\n";
    } else {
        echo "⚠️ Partial - " . $conn->error . "\n";
    }
}

echo "\n✨ Database optimization complete!\n";
echo "\n📊 Performance impact:\n";
echo "  • Index statistics rebuilt\n";
echo "  • Tables defragmented\n";
echo "  • Queries should now execute faster with indexes\n";
echo "  • Next modal load should be significantly faster\n";

// Show table sizes before/after
echo "\n📈 Table Statistics:\n";
$result = $conn->query("
    SELECT TABLE_NAME, 
           ROUND(((data_length + index_length) / 1024 / 1024), 2) AS 'Size_MB',
           table_rows AS 'Rows'
    FROM information_schema.TABLES
    WHERE TABLE_SCHEMA = DATABASE()
    ORDER BY (data_length + index_length) DESC
");

if ($result) {
    echo str_pad('Table', 20) . ' | ' . str_pad('Size (MB)', 12) . ' | ' . str_pad('Rows', 10) . "\n";
    echo str_repeat('-', 50) . "\n";

    while ($row = $result->fetch_assoc()) {
        echo str_pad($row['TABLE_NAME'], 20) . ' | ' . str_pad($row['Size_MB'], 12) . ' | ' . str_pad($row['Rows'], 10) . "\n";
    }
}

$conn->close();
?>