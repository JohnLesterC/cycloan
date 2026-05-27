<?php
/**
 * Check users1 table structure
 */
require_once 'CYCLOAN_db.php';

echo "<h1>Users1 Table Structure Check</h1>";

if ($conn && $conn->ping()) {
    echo "✅ Database connected<br><br>";

    echo "<h2>Table Structure:</h2>";
    $result = $conn->query("DESCRIBE users1");

    if ($result) {
        echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
        echo "<tr style='background: #f0f0f0;'><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>";

        while ($row = $result->fetch_assoc()) {
            echo "<tr>";
            echo "<td><strong>" . $row['Field'] . "</strong></td>";
            echo "<td>" . $row['Type'] . "</td>";
            echo "<td>" . $row['Null'] . "</td>";
            echo "<td>" . $row['Key'] . "</td>";
            echo "<td>" . ($row['Default'] ?? 'NULL') . "</td>";
            echo "<td>" . $row['Extra'] . "</td>";
            echo "</tr>";
        }
        echo "</table>";

        echo "<br><h2>Sample Data (first 2 rows):</h2>";
        $sampleData = $conn->query("SELECT * FROM users1 LIMIT 2");
        if ($sampleData && $sampleData->num_rows > 0) {
            echo "<table border='1' style='border-collapse: collapse; width: 100%; font-size: 12px;'>";

            // Get column names
            $fields = $sampleData->fetch_fields();
            echo "<tr style='background: #f0f0f0;'>";
            foreach ($fields as $field) {
                echo "<th>" . $field->name . "</th>";
            }
            echo "</tr>";

            // Reset pointer and show data
            $sampleData->data_seek(0);
            while ($row = $sampleData->fetch_assoc()) {
                echo "<tr>";
                foreach ($row as $value) {
                    echo "<td>" . (strlen($value) > 20 ? substr($value, 0, 20) . '...' : $value) . "</td>";
                }
                echo "</tr>";
            }
            echo "</table>";
        } else {
            echo "No sample data available or table is empty.";
        }

    } else {
        echo "❌ Error: " . $conn->error;
    }
} else {
    echo "❌ Database connection failed";
}
?>