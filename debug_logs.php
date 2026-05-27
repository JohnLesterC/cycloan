<?php
// Simple Debug Log Viewer
session_start();

// Security check - only allow admin access
if (!isset($_SESSION['email']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'admin2') {
    die('Access denied - Admin only');
}

echo "<h2>🔍 PHP Error Log Viewer</h2>";

// Try to find error log location
$possible_log_locations = [
    'error_log',
    '../error_log',
    '../../error_log',
    'logs/error.log',
    '../logs/error.log',
    'error_logs/error_log',
    '../error_logs/error_log',
    __DIR__ . '/logs/error_log_local.txt'  // Local fallback log file for localhost
];

echo "<h3>📂 Checking possible log locations:</h3>";
$found_logs = [];

foreach ($possible_log_locations as $log_path) {
    if (file_exists($log_path)) {
        $found_logs[] = $log_path;
        echo "<p>✅ Found: <code>$log_path</code></p>";
    } else {
        echo "<p>❌ Not found: <code>$log_path</code></p>";
    }
}

if (empty($found_logs)) {
    echo "<h3>🔍 Alternative: Check PHP Error Log Configuration</h3>";
    echo "<p><strong>Error Log Setting:</strong> " . (ini_get('error_log') ?: 'Not set') . "</p>";
    echo "<p><strong>Log Errors:</strong> " . (ini_get('log_errors') ? 'Enabled' : 'Disabled') . "</p>";

    echo "<h3>💡 Manual Check Suggestions:</h3>";
    echo "<ul>";
    echo "<li>Check your hosting control panel for 'Error Logs' section</li>";
    echo "<li>Look for 'error_log' files in your domain root directory</li>";
    echo "<li>Contact your hosting provider for log location</li>";
    echo "</ul>";
} else {
    echo "<h3>📋 Recent Error Log Entries:</h3>";
    foreach ($found_logs as $log_file) {
        echo "<h4>Log: $log_file</h4>";
        if (is_readable($log_file)) {
            $lines = file($log_file);
            if ($lines && count($lines) > 0) {
                $recent_lines = array_slice($lines, -20); // Last 20 lines
                echo "<pre style='background:#f5f5f5;padding:10px;border:1px solid #ddd;'>";
                foreach ($recent_lines as $line) {
                    // Highlight our debug messages
                    if (strpos($line, '🔥') !== false || strpos($line, '🚀') !== false || strpos($line, '🔄') !== false) {
                        echo "<strong style='color:red;'>" . htmlspecialchars($line) . "</strong>";
                    } else {
                        echo htmlspecialchars($line);
                    }
                }
                echo "</pre>";
            } else {
                echo "<p>Log file is empty</p>";
            }
        } else {
            echo "<p>⚠️ Cannot read log file (permission issue)</p>";
        }
    }
}

echo "<h3>🧪 Test PHP Error Logging:</h3>";
if (isset($_GET['test'])) {
    // Enable error logging for this test
    ini_set('log_errors', 1);
    ini_set('error_log', __DIR__ . '/logs/error_log_local.txt');

    error_log("🧪 TEST LOG ENTRY from debug_logs.php - " . date('Y-m-d H:i:s'));
    echo "<p>✅ Test log entry sent! Refresh page to see if it appears above.</p>";
} else {
    echo "<p><a href='?test=1'>Click here to send a test log entry</a></p>";
}

echo "<h3>🎯 Next Testing Steps:</h3>";
echo "<div style='background:#e7f3ff;padding:15px;border:1px solid #b3d9ff;border-radius:5px;'>";
echo "<h4>To test the View button fix:</h4>";
echo "<ol>";
echo "<li>Go to your <a href='admin2_dashboard.php' target='_blank'>Admin2 Dashboard</a></li>";
echo "<li>Find any loan application in the table</li>";
echo "<li>Click the <strong>View</strong> button</li>";
echo "<li>Come back here and refresh this page</li>";
echo "<li>Look for these log entries:</li>";
echo "<ul style='margin-top:10px;'>";
echo "<li>🔥 <strong style='color:red;'>PRIORITY HANDLER</strong> - Good! Handler executed correctly</li>";
echo "<li>🚀 <strong style='color:orange;'>SECONDARY HANDLER</strong> - Bad! Should NOT appear</li>";
echo "<li><strong>Admin2 Dashboard POST Request</strong> - Shows the button was clicked</li>";
echo "<li><strong>Invalid action</strong> - Shows if there are still errors</li>";
echo "</ul>";
echo "</ol>";
echo "</div>";

echo "<hr style='margin:20px 0;'>";
echo "<p><em>💡 Tip: Keep this page open in another tab and refresh after testing to see real-time logs!</em></p>";
?>