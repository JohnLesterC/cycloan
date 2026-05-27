<?php
// Database connection
require_once 'CYCLOAN_db.php';

// SQL to fix the action_type column
$sql = "ALTER TABLE `activity_logs` MODIFY COLUMN `action_type` VARCHAR(50) NOT NULL";

try {
    if ($conn->query($sql) === TRUE) {
        echo "✓ SUCCESS: activity_logs.action_type column expanded from VARCHAR(20) to VARCHAR(50)<br>";
        
        // Verify the change
        $result = $conn->query("DESCRIBE `activity_logs`");
        echo "<br>Updated table structure:<br>";
        echo "<table border='1' style='border-collapse: collapse; margin-top: 10px;'>";
        echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>";
        
        while ($row = $result->fetch_assoc()) {
            if ($row['Field'] === 'action_type') {
                echo "<tr style='background-color: #90EE90;'>";
            } else {
                echo "<tr>";
            }
            echo "<td>{$row['Field']}</td>";
            echo "<td><strong>{$row['Type']}</strong></td>";
            echo "<td>{$row['Null']}</td>";
            echo "<td>{$row['Key']}</td>";
            echo "<td>{$row['Default']}</td>";
            echo "<td>{$row['Extra']}</td>";
            echo "</tr>";
        }
        echo "</table>";
        
        echo "<br><br><strong>✓ Password reset actions will now display correctly!</strong>";
        echo "<br>Database truncation issue resolved.";
        
    } else {
        echo "✗ ERROR: " . $conn->error;
    }
} catch (Exception $e) {
    echo "✗ Exception: " . $e->getMessage();
}

$conn->close();
?>
