<?php
require_once 'CYCLOAN_db.php';

echo "Checking activity_logs table structure...\n\n";

$result = $conn->query("DESCRIBE activity_logs");
if ($result) {
    echo "COLUMNS:\n";
    while ($row = $result->fetch_assoc()) {
        echo $row['Field'] . " - " . $row['Type'] . " - " . ($row['Null'] == 'YES' ? 'NULL' : 'NOT NULL') . "\n";
    }
}

echo "\n\nSample activity logs:\n";
$sample = $conn->query("SELECT * FROM activity_logs ORDER BY created_at DESC LIMIT 10");
if ($sample && $sample->num_rows > 0) {
    while ($row = $sample->fetch_assoc()) {
        echo "ID: " . $row['id'] . " | Type: " . $row['action_type'] . " | Module: " . $row['module'] . " | Description: " . substr($row['description'], 0, 60) . "\n";
    }
}

echo "\n\nChecking for pre-approval logs:\n";
$prelog = $conn->query("SELECT * FROM activity_logs WHERE description LIKE '%Pre-Approval%' LIMIT 5");
if ($prelog && $prelog->num_rows > 0) {
    echo "Found " . $prelog->num_rows . " pre-approval logs:\n";
    while ($row = $prelog->fetch_assoc()) {
        echo json_encode($row, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n\n";
    }
} else {
    echo "No pre-approval logs found!\n";
}

echo "\n\nChecking activity_type column values:\n";
$types = $conn->query("SELECT DISTINCT action_type, module FROM activity_logs");
if ($types) {
    while ($row = $types->fetch_assoc()) {
        echo "action_type: " . $row['action_type'] . " | module: " . $row['module'] . "\n";
    }
}
?>
