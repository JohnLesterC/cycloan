<?php
/**
 * Modal Performance Diagnostic Report
 * 
 * This script shows timing data from the most recent modal load
 * Check browser console after opening a loan modal to see timings
 */

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html>

<head>
    <title>Modal Load Performance Diagnostic</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 900px;
            margin: 20px auto;
            background: #f5f5f5;
        }

        .container {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }

        h1 {
            color: #2d7d32;
            border-bottom: 3px solid #2d7d32;
            padding-bottom: 10px;
        }

        .instructions {
            background: #e8f5e9;
            padding: 15px;
            border-left: 4px solid #2d7d32;
            border-radius: 4px;
            margin: 20px 0;
        }

        .code {
            background: #f5f5f5;
            padding: 10px;
            border-radius: 4px;
            font-family: monospace;
            margin: 10px 0;
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
        }

        th,
        td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }

        th {
            background: #f5f5f5;
            font-weight: bold;
        }

        tr:hover {
            background: #f5f5f5;
        }

        .fast {
            color: #2d7d32;
            font-weight: bold;
        }

        .slow {
            color: #d32f2f;
            font-weight: bold;
        }

        .warning {
            color: #f57c00;
            font-weight: bold;
        }

        .step {
            margin: 15px 0;
            padding: 10px;
            background: #f9f9f9;
            border-left: 4px solid #2d7d32;
        }

        .console-code {
            background: #222;
            color: #0f0;
            padding: 15px;
            border-radius: 4px;
            font-family: monospace;
            font-size: 13px;
            margin: 10px 0;
        }
    </style>
</head>

<body>
    <div class="container">
        <h1>🔍 Modal Load Performance Diagnostic</h1>

        <div class="instructions">
            <strong>📋 Instructions:</strong>
            <p>Follow these steps to capture and analyze modal loading performance:</p>
        </div>

        <div class="step">
            <strong>Step 1: Open Admin Dashboard</strong>
            <p>Navigate to your admin2_dashboard.php page</p>
        </div>

        <div class="step">
            <strong>Step 2: Open Browser DevTools</strong>
            <p>Press <code>F12</code> to open developer tools, then go to the <code>Console</code> tab</p>
        </div>

        <div class="step">
            <strong>Step 3: Paste Monitoring Script</strong>
            <p>Paste this JavaScript code in the console:</p>
            <div class="console-code">
                // Monitor performance
                let lastResponse = null;
                const originalFetch = window.fetch;

                window.fetch = function(...args) {
                const start = performance.now();
                return originalFetch.apply(this, args).then(response => {
                return response.clone().json().then(data => {
                const time = performance.now() - start;
                if (data._debug) {
                console.log('%c⏱️ MODAL LOAD TIMING:', 'color: #2d7d32; font-weight: bold; font-size: 14px;');
                console.table(data._debug);
                lastResponse = data._debug;
                }
                return response;
                }).catch(() => response);
                });
                };
            </div>
        </div>

        <div class="step">
            <strong>Step 4: Click "View" on Any Loan</strong>
            <p>This will trigger the monitoring script and show timing data in the console</p>
        </div>

        <div class="step">
            <strong>Step 5: Analyze Results</strong>
            <p>Look for these columns in the console output:</p>
            <table>
                <tr>
                    <th>Metric</th>
                    <th>Good (&lt;100ms)</th>
                    <th>Warning (100-200ms)</th>
                    <th>Slow (&gt;200ms)</th>
                </tr>
                <tr>
                    <td><strong>loan_query_ms</strong></td>
                    <td class="fast">✓ Index working</td>
                    <td class="warning">≈ Check indexes</td>
                    <td class="slow">✗ Needs optimization</td>
                </tr>
                <tr>
                    <td><strong>documents_query_ms</strong></td>
                    <td class="fast">✓ Good</td>
                    <td class="warning">≈ Monitor</td>
                    <td class="slow">✗ Slow JOIN</td>
                </tr>
                <tr>
                    <td><strong>remarks_query_ms</strong></td>
                    <td class="fast">✓ Good</td>
                    <td class="warning">≈ Monitor</td>
                    <td class="slow">✗ Too many remarks</td>
                </tr>
                <tr>
                    <td><strong>logs_query_ms</strong></td>
                    <td class="fast">✓ Excellent</td>
                    <td class="warning">≈ Improve filter</td>
                    <td class="slow">✗ Need better filtering</td>
                </tr>
                <tr>
                    <td><strong>total_ms</strong></td>
                    <td class="fast">✓ &lt;300ms</td>
                    <td class="warning">300-500ms</td>
                    <td class="slow">✗ &gt;500ms</td>
                </tr>
            </table>
        </div>

        <h2>🎯 Troubleshooting Guide</h2>

        <h3>If loan_query_ms is Slow (&gt;100ms):</h3>
        <div class="code">
            -- Check if indexes exist:
            SHOW INDEXES FROM loan_applications WHERE Key_name LIKE 'idx_%';
            SHOW INDEXES FROM users1 WHERE Key_name LIKE 'idx_%';

            -- If missing, run:
            php apply_additional_indexes.php
        </div>

        <h3>If logs_query_ms is Slow (&gt;200ms):</h3>
        <div class="code">
            -- Check activity_logs indexes:
            SHOW INDEXES FROM activity_logs;

            -- Verify index is being used:
            EXPLAIN SELECT * FROM activity_logs
            WHERE description LIKE '%APP-001%' LIMIT 50;
            -- Should show "idx_description_search" in "possible_keys"
        </div>

        <h3>If Total Slow (&gt;500ms) but Individual Queries Fast:</h3>
        <p>Network latency or PHP processing. Check:</p>
        <ul>
            <li>Server CPU usage (may be overloaded)</li>
            <li>Network connection speed</li>
            <li>Browser network tab for actual request time vs query time</li>
        </ul>

        <h2>📊 Performance Baseline (After Optimization)</h2>
        <table>
            <tr>
                <th>Scenario</th>
                <th>Expected Time</th>
                <th>Status</th>
            </tr>
            <tr>
                <td>First modal load (no cache)</td>
                <td>200-400ms</td>
                <td>✅ Good</td>
            </tr>
            <tr>
                <td>Subsequent loads (cached)</td>
                <td>50-100ms</td>
                <td>✅ Excellent</td>
            </tr>
            <tr>
                <td>With large activity logs (5000+ records)</td>
                <td>200-600ms</td>
                <td>✅ Acceptable</td>
            </tr>
            <tr>
                <td>With many documents/remarks</td>
                <td>300-500ms</td>
                <td>✅ Good</td>
            </tr>
        </table>

        <h2>📝 Report Results</h2>
        <p><strong>If still slow, provide the console timing data:</strong></p>
        <div class="console-code">
            // Copy and paste this in console to see formatted results:
            console.table(lastResponse);

            // Or get the text for a bug report:
            JSON.stringify(lastResponse, null, 2);
        </div>

    </div>
</body>

</html>