<?php
require_once 'CYCLOAN_db.php';

echo "Checking user_notifications table structure...\n\n";

// Get table structure
$result = $conn->query("DESCRIBE user_notifications");
echo "TABLE COLUMNS:\n";
echo "===============\n";
while ($row = $result->fetch_assoc()) {
    echo $row['Field'] . " - " . $row['Type'] . " - " . ($row['Null'] == 'YES' ? 'NULL' : 'NOT NULL') . " - " . $row['Key'] . "\n";
}

echo "\n\nFOREIGN KEY CONSTRAINTS:\n";
echo "==========================\n";

// Get foreign key info
$fk_result = $conn->query("
    SELECT CONSTRAINT_NAME, COLUMN_NAME, REFERENCED_TABLE_NAME, REFERENCED_COLUMN_NAME
    FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE
    WHERE TABLE_NAME = 'user_notifications' AND REFERENCED_TABLE_NAME IS NOT NULL
");

if ($fk_result && $fk_result->num_rows > 0) {
    while ($row = $fk_result->fetch_assoc()) {
        echo "Constraint: " . $row['CONSTRAINT_NAME'] . "\n";
        echo "  Column: " . $row['COLUMN_NAME'] . "\n";
        echo "  References: " . $row['REFERENCED_TABLE_NAME'] . "." . $row['REFERENCED_COLUMN_NAME'] . "\n\n";
    }
} else {
    echo "No foreign keys found.\n";
}

echo "\n\nADMIN2 USERS:\n";
echo "==============\n";
$admin2 = $conn->query("SELECT id, email FROM admin2 LIMIT 5");
while ($row = $admin2->fetch_assoc()) {
    echo "Admin2 ID: " . $row['id'] . " - " . $row['email'] . "\n";
}

echo "\n\nUSERS1 TABLE:\n";
echo "==============\n";
$users = $conn->query("SELECT id, email FROM users1 LIMIT 5");
$count = $users->num_rows;
echo "Total users in users1: $count\n";
while ($row = $users->fetch_assoc()) {
    echo "User ID: " . $row['id'] . " - " . $row['email'] . "\n";
}
?>