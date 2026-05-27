<?php
require_once 'CYCLOAN_db.php';

header('Content-Type: text/plain');

echo "🔧 APPLYING DATABASE PERFORMANCE INDEXES\n";
echo "=========================================\n\n";

// Read the SQL file
$sqlContent = file_get_contents('LOAN_DETAILS_PERFORMANCE_OPTIMIZATION.sql');
if (!$sqlContent) {
    die("❌ Could not read SQL file\n");
}

// Execute each CREATE INDEX statement
$lines = explode("\n", $sqlContent);
$inStatement = false;
$statement = "";
$count = 0;

foreach ($lines as $line) {
    $trimmed = trim($line);

    // Skip empty lines and comments
    if (empty($trimmed) || strpos($trimmed, '--') === 0) {
        continue;
    }

    $statement .= $line . " ";

    if (strpos($trimmed, ';') !== false) {
        if (!empty(trim($statement))) {
            $count++;
            echo "[$count] Executing: " . substr($statement, 0, 60) . "...\n";

            if ($conn->query($statement)) {
                echo "    ✅ SUCCESS\n";
            } else {
                if (stripos($conn->error, 'duplicate') !== false) {
                    echo "    ℹ️  Already exists\n";
                } else {
                    echo "    ❌ ERROR: " . $conn->error . "\n";
                }
            }
        }
        $statement = "";
    }
}

echo "\n✨ DONE!\n";

// Verify
echo "\n📊 VERIFICATION:\n";
$tables = ['activity_logs', 'documents', 'remarks', 'loan_applications'];
foreach ($tables as $tbl) {
    $res = $conn->query("SHOW INDEXES FROM $tbl WHERE Key_name LIKE 'idx_%'");
    echo "  $tbl: " . ($res ? $res->num_rows : 0) . " indexes\n";
}

$conn->close();
?>