<?php
require 'CYCLOAN_db.php';

// Check logattempts table columns
$result = $conn->query("DESCRIBE logattempts");
if ($result) {
    echo "LOGATTEMPTS Table Structure:\n";
    echo "==============================\n";
    while ($row = $result->fetch_assoc()) {
        echo "Field: " . $row['Field'] . " | Type: " . $row['Type'] . " | Null: " . $row['Null'] . " | Key: " . $row['Key'] . "\n";
    }
} else {
    echo "Error: " . $conn->error;
}
?>