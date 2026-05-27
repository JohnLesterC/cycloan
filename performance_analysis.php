<?php
/**
 * Complete Performance Analysis Tool
 * Shows query execution plans and actual timing
 */

require_once 'CYCLOAN_db.php';

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html>

<head>
    <title>Modal Performance Analysis</title>
    <style>
        * {
            margin: 0;
            padding: 0;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #1e1e1e;
            color: #d4d4d4;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }

        h1 {
            color: #4ec9b0;
            margin: 20px 0 10px 0;
            border-bottom: 2px solid #4ec9b0;
            padding-bottom: 10px;
        }

        h2 {
            color: #ce9178;
            margin-top: 20px;
            font-size: 16px;
        }

        .section {
            background: #252526;
            padding: 15px;
            margin: 15px 0;
            border-left: 3px solid #4ec9b0;
            border-radius: 4px;
        }

        .metric {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr;
            gap: 10px;
            margin: 10px 0;
        }

        .card {
            background: #1e1e1e;
            padding: 15px;
            border: 1px solid #3e3e42;
            border-radius: 4px;
        }

        .value {
            font-size: 24px;
            font-weight: bold;
            color: #4ec9b0;
        }

        .label {
            font-size: 12px;
            color: #858585;
            text-transform: uppercase;
        }

        .fast {
            color: #4ec9b0;
        }

        .slow {
            color: #f48771;
        }

        .warning {
            color: #dcdcaa;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin: 10px 0;
        }

        th,
        td {
            padding: 10px;
            text-align: left;
            border-bottom: 1px solid #3e3e42;
        }

        th {
            background: #1e1e1e;
            color: #ce9178;
        }

        tr:hover {
            background: #252526;
        }

        code {
            background: #1e1e1e;
            padding: 2px 6px;
            border-radius: 3px;
            font-family: 'Courier New', monospace;
        }

        .explain-plan {
            background: #1e1e1e;
            padding: 10px;
            border-left: 2px solid #4ec9b0;
            margin: 10px 0;
            font-family: monospace;
            font-size: 12px;
        }

        button {
            background: #4ec9b0;
            color: #1e1e1e;
            border: none;
            padding: 10px 20px;
            border-radius: 4px;
            cursor: pointer;
            font-weight: bold;
        }

        button:hover {
            background: #6ec8bf;
        }

        .highlight {
            background: #252526;
        }
    </style>
</head>

<body>
    <div class="container">
        <h1>🔍 Loan Details Modal - Performance Analysis</h1>

        <?php
        // Test a sample application_id (use the first one in the database)
        $result = $conn->query("SELECT application_id LIMIT 1");
        $testApp = $result ? $result->fetch_assoc()['application_id'] : 'TEST-001';

        echo "<div class='section'>";
        echo "<h2>Test Application: <code>$testApp</code></h2>";
        echo "</div>";

        // 1. Loan Query Analysis
        echo "<div class='section'>";
        echo "<h2>1. Loan Details Query (With Joins)</h2>";

        $queries = [
            [
                'name' => 'Main Loan Query (With All Joins)',
                'query' => "
            SELECT la.application_id, la.user_id, la.loan_type_id, la.status, 
                   u.first_name, u.last_name, 
                   COALESCE(lt.type_name, 'Unknown') AS type_name,
                   fi.business_income, fi.salary_income
            FROM loan_applications la
            JOIN users1 u ON la.user_id = u.id
            LEFT JOIN loan_types lt ON la.loan_type_id = lt.loan_type_id
            LEFT JOIN financial_info fi ON la.user_id = fi.user_id
            WHERE la.application_id = ?
        "
            ],
            [
                'name' => 'Documents Query',
                'query' => "
            SELECT d.document_id, dt.document_name, d.file_path, d.status
            FROM documents d
            JOIN document_types dt ON d.document_type_id = dt.document_type_id
            WHERE d.application_id = ?
        "
            ],
            [
                'name' => 'Remarks Query',
                'query' => "
            SELECT remarks, created_at, admin_name
            FROM remarks
            WHERE d.application_id = ?
        "
            ],
            [
                'name' => 'Activity Logs (Filtered by Application)',
                'query' => "
            SELECT al.log_id, al.action_type, al.description
            FROM activity_logs al
            WHERE al.description LIKE ? OR al.module IN ('loan_applications', 'documents', 'remarks')
            ORDER BY al.created_at DESC
            LIMIT 50
        "
            ]
        ];

        foreach ($queries as $q) {
            echo "<div class='highlight' style='margin: 15px 0; padding: 10px; border-left: 3px solid #4ec9b0;'>";
            echo "<h3 style='color: #ce9178; margin-bottom: 10px;'>" . $q['name'] . "</h3>";

            // Get EXPLAIN output
            $explainQuery = "EXPLAIN " . $q['query'];
            $stmt = $conn->prepare($explainQuery);

            if ($q['name'] === 'Activity Logs (Filtered by Application)') {
                $pattern = "%$testApp%";
                $stmt->bind_param("s", $pattern);
            } else {
                $stmt->bind_param("s", $testApp);
            }

            if ($stmt->execute()) {
                $result = $stmt->get_result();
                echo "<table>";
                echo "<tr><th>ID</th><th>Select Type</th><th>Table</th><th>Type</th><th>Key</th><th>Rows</th><th>Extra</th></tr>";

                while ($row = $result->fetch_assoc()) {
                    $keyUsed = $row['key'] ? "✅ <code>" . $row['key'] . "</code>" : "❌ No index used";
                    echo "<tr>";
                    echo "<td>" . $row['id'] . "</td>";
                    echo "<td>" . $row['select_type'] . "</td>";
                    echo "<td>" . $row['table'] . "</td>";
                    echo "<td>" . $row['type'] . "</td>";
                    echo "<td>" . $keyUsed . "</td>";
                    echo "<td><strong>" . $row['rows'] . "</strong></td>";
                    echo "<td>" . $row['Extra'] . "</td>";
                    echo "</tr>";
                }
                echo "</table>";
            }

            $stmt->close();
            echo "</div>";
        }

        echo "</div>";

        // 2. Index Statistics
        echo "<div class='section'>";
        echo "<h2>2. Index Statistics</h2>";

        $tables = ['activity_logs', 'documents', 'remarks', 'loan_applications', 'users1'];
        foreach ($tables as $table) {
            echo "<h3 style='color: #ce9178; margin-top: 10px;'>Table: <code>$table</code></h3>";

            $indexResult = $conn->query("SHOW INDEXES FROM `$table` WHERE Key_name LIKE 'idx_%'");
            if ($indexResult && $indexResult->num_rows > 0) {
                echo "<table>";
                echo "<tr><th>Index Name</th><th>Columns</th><th>Cardinality</th></tr>";

                while ($row = $indexResult->fetch_assoc()) {
                    echo "<tr>";
                    echo "<td><code>" . $row['Key_name'] . "</code></td>";
                    echo "<td>" . $row['Column_name'] . "</td>";
                    echo "<td>" . ($row['Cardinality'] ?? 'N/A') . "</td>";
                    echo "</tr>";
                }
                echo "</table>";
            } else {
                echo "<p class='warning'>⚠️ No custom indexes found</p>";
            }
        }

        echo "</div>";

        // 3. Optimization Recommendations
        echo "<div class='section'>";
        echo "<h2>3. Recommendations</h2>";

        $recommendations = [
            "✅ Run <code>optimize_database.php</code> to rebuild index statistics",
            "✅ Ensure all indexes are being used (check EXPLAIN plans above)",
            "✅ If any query shows 'type=ALL', the index may not be working",
            "✅ Clear browser cache after optimization",
            "✅ Open the modal again and check timing in console"
        ];

        echo "<ul style='line-height: 1.8;'>";
        foreach ($recommendations as $rec) {
            echo "<li>$rec</li>";
        }
        echo "</ul>";

        echo "</div>";

        // 4. Quick Performance Test
        echo "<div class='section'>";
        echo "<h2>4. Quick Performance Test</h2>";

        $testQuery = "
    SELECT la.application_id, la.status, u.first_name
    FROM loan_applications la
    JOIN users1 u ON la.user_id = u.id
    WHERE la.application_id = ?
";

        $start = microtime(true);
        $stmt = $conn->prepare($testQuery);
        $stmt->bind_param("s", $testApp);
        $stmt->execute();
        $result = $stmt->get_result();
        $data = $result->fetch_assoc();
        $time = (microtime(true) - $start) * 1000;

        echo "<div class='metric'>";
        echo "<div class='card'>";
        echo "<div class='label'>Query Execution Time</div>";
        echo "<div class='value " . ($time < 50 ? 'fast' : ($time < 150 ? 'warning' : 'slow')) . "'>" . round($time, 2) . "ms</div>";
        echo "</div>";
        echo "<div class='card'>";
        echo "<div class='label'>Status</div>";
        if ($time < 50) {
            echo "<div class='value fast'>✅ FAST</div>";
        } elseif ($time < 150) {
            echo "<div class='value warning'>⚠️ GOOD</div>";
        } else {
            echo "<div class='value slow'>❌ SLOW</div>";
        }
        echo "</div>";
        echo "<div class='card'>";
        echo "<div class='label'>Expected Modal Load</div>";
        echo "<div class='value warning'>200-400ms</div>";
        echo "</div>";
        echo "</div>";

        $stmt->close();

        echo "</div>";

        $conn->close();
        ?>

        <div class='section' style='text-align: center; margin-top: 30px; padding: 20px;'>
            <p>💡 <strong>If queries show 'type=ALL'</strong>, indexes are not being used.</p>
            <p>Run: <code
                    style='background: #252526; padding: 8px; display: inline-block; margin: 10px 0;'>php optimize_database.php</code>
            </p>
            <p>Then refresh this page to see updated results.</p>
        </div>
    </div>
</body>

</html>
<?php
?>