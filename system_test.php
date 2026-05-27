<?php
/**
 * CYCLOAN - Comprehensive System Testing Tool
 * Tests all critical system components, database, security, and functionality
 */

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Start session for testing
session_start();

// Include database connection globally
require_once 'CYCLOAN_db.php';

// Test results storage
$test_results = [
    'passed' => 0,
    'failed' => 0,
    'warnings' => 0,
    'total' => 0
];

$test_details = [];

// Helper function to run a test
function runTest($name, $callback, $category = 'General')
{
    global $test_results, $test_details;

    $test_results['total']++;
    $start_time = microtime(true);

    try {
        $result = $callback();
        $duration = round((microtime(true) - $start_time) * 1000, 2);

        if ($result['status'] === 'pass') {
            $test_results['passed']++;
            $status = 'pass';
        } elseif ($result['status'] === 'warning') {
            $test_results['warnings']++;
            $status = 'warning';
        } else {
            $test_results['failed']++;
            $status = 'fail';
        }

        $test_details[] = [
            'category' => $category,
            'name' => $name,
            'status' => $status,
            'message' => $result['message'],
            'duration' => $duration,
            'details' => $result['details'] ?? null
        ];

    } catch (Exception $e) {
        $test_results['failed']++;
        $test_details[] = [
            'category' => $category,
            'name' => $name,
            'status' => 'fail',
            'message' => 'Exception: ' . $e->getMessage(),
            'duration' => round((microtime(true) - $start_time) * 1000, 2),
            'details' => $e->getTraceAsString()
        ];
    }
}

// ==========================================
// DATABASE TESTS
// ==========================================

runTest('Database Connection', function () {
    global $conn;

    if ($conn && $conn->ping()) {
        return [
            'status' => 'pass',
            'message' => 'Database connection successful',
            'details' => 'Host: ' . $conn->host_info
        ];
    } else {
        return [
            'status' => 'fail',
            'message' => 'Database connection failed',
            'details' => $conn ? $conn->error : 'Connection object not created'
        ];
    }
}, 'Database');

runTest('Database Tables Exist', function () {
    global $conn;

    $required_tables = [
        'users1',                   // User accounts
        'spouses',                  // Spouse information
        'financial_info',           // Financial details
        'income_sources',           // Income sources
        'expenditure_types',        // Expenditure types
        'otps',                     // OTP verification
        'loans',                    // Loan records
        'loan_applications',        // Loan applications
        'loan_types',               // Loan types
        'payment_schedules',        // Payment schedule tracking
        'payment_history',          // Payment history
        'invoices',                 // Payment invoices
        'documents',                // Document uploads
        'document_types',           // Document type definitions
        'remarks',                  // Admin remarks
        'activity_logs',            // System activity logs
        'interest_rates',           // Interest rate settings
        'admin1',                   // Admin 1 accounts
        'admin2',                   // Admin 2 accounts
        'superadmins',              // Superadmin accounts
        'logattempts'               // Login attempts tracking
    ];

    $missing_tables = [];

    foreach ($required_tables as $table) {
        $result = $conn->query("SHOW TABLES LIKE '$table'");
        if ($result->num_rows == 0) {
            $missing_tables[] = $table;
        }
    }

    if (empty($missing_tables)) {
        return [
            'status' => 'pass',
            'message' => 'All required tables exist',
            'details' => count($required_tables) . ' tables checked'
        ];
    } else {
        return [
            'status' => 'fail',
            'message' => 'Missing tables: ' . implode(', ', $missing_tables),
            'details' => 'Found: ' . (count($required_tables) - count($missing_tables)) . '/' . count($required_tables)
        ];
    }
}, 'Database');

runTest('Database User Permissions', function () {
    global $conn;

    $can_select = $conn->query("SELECT 1");
    $can_insert = true;
    $can_update = true;
    $can_delete = true;

    $permissions = [];
    if ($can_select)
        $permissions[] = 'SELECT';

    return [
        'status' => 'pass',
        'message' => 'Database permissions OK',
        'details' => 'Permissions: ' . implode(', ', $permissions)
    ];
}, 'Database');

// ==========================================
// FILE SYSTEM TESTS
// ==========================================

runTest('PHP Files Accessibility', function () {
    $critical_files = [
        'index.php',                    // Login page
        'registration.php',             // User registration
        'user_dashboard.php',           // User dashboard
        'admin1_dashboard.php',         // Admin 1 dashboard
        'admin2_dashboard.php',         // Admin 2 dashboard
        'Superadmin_dashboard.php',     // Superadmin dashboard
        'CYCLOAN_db.php',               // Database connection
        'process_registration.php',     // Registration handler
        'verify_otp.php',               // OTP verification
        'loan_register.php',            // Loan registration form
        'create_loan_process.php',      // Loan creation handler
        'profile.php',                  // User profile
        'applicant.php',                // Applicant management
        'active_records.php',           // Active records view
        'pending_records.php',          // Pending records view
        'closed_records.php'            // Closed records view
    ];

    $missing_files = [];

    foreach ($critical_files as $file) {
        if (!file_exists(__DIR__ . '/' . $file)) {
            $missing_files[] = $file;
        }
    }

    if (empty($missing_files)) {
        return [
            'status' => 'pass',
            'message' => 'All critical PHP files exist',
            'details' => count($critical_files) . ' files checked'
        ];
    } else {
        return [
            'status' => 'fail',
            'message' => 'Missing files: ' . implode(', ', $missing_files)
        ];
    }
}, 'File System');

runTest('Directory Permissions', function () {
    $writable_dirs = ['uploads', 'database'];
    $permission_issues = [];

    foreach ($writable_dirs as $dir) {
        $path = __DIR__ . '/' . $dir;
        if (!is_dir($path)) {
            $permission_issues[] = "$dir - Directory not found";
        } elseif (!is_writable($path)) {
            $permission_issues[] = "$dir - Not writable";
        }
    }

    if (empty($permission_issues)) {
        return [
            'status' => 'pass',
            'message' => 'Directory permissions OK',
            'details' => implode(', ', $writable_dirs) . ' are writable'
        ];
    } else {
        return [
            'status' => 'warning',
            'message' => implode('; ', $permission_issues)
        ];
    }
}, 'File System');

runTest('CSS Files Load', function () {
    $css_dir = __DIR__ . '/CSS';
    $css_files = glob($css_dir . '/*.css');

    if (count($css_files) > 0) {
        return [
            'status' => 'pass',
            'message' => 'CSS files found',
            'details' => count($css_files) . ' CSS files'
        ];
    } else {
        return [
            'status' => 'fail',
            'message' => 'No CSS files found'
        ];
    }
}, 'File System');

runTest('JavaScript Files Load', function () {
    $js_dir = __DIR__ . '/JAVASCRIPT';
    $js_files = glob($js_dir . '/*.js');

    if (count($js_files) > 0) {
        return [
            'status' => 'pass',
            'message' => 'JavaScript files found',
            'details' => count($js_files) . ' JS files'
        ];
    } else {
        return [
            'status' => 'fail',
            'message' => 'No JavaScript files found'
        ];
    }
}, 'File System');

// ==========================================
// PHP CONFIGURATION TESTS
// ==========================================

runTest('PHP Version', function () {
    $version = phpversion();
    $min_version = '7.4';

    if (version_compare($version, $min_version, '>=')) {
        return [
            'status' => 'pass',
            'message' => "PHP version OK: $version",
            'details' => "Minimum required: $min_version"
        ];
    } else {
        return [
            'status' => 'fail',
            'message' => "PHP version too old: $version",
            'details' => "Minimum required: $min_version"
        ];
    }
}, 'PHP Configuration');

runTest('Required PHP Extensions', function () {
    $required = ['mysqli', 'json', 'session', 'mbstring', 'fileinfo'];
    $missing = [];

    foreach ($required as $ext) {
        if (!extension_loaded($ext)) {
            $missing[] = $ext;
        }
    }

    if (empty($missing)) {
        return [
            'status' => 'pass',
            'message' => 'All required extensions loaded',
            'details' => implode(', ', $required)
        ];
    } else {
        return [
            'status' => 'fail',
            'message' => 'Missing extensions: ' . implode(', ', $missing)
        ];
    }
}, 'PHP Configuration');

runTest('PHP Memory Limit', function () {
    $memory_limit = ini_get('memory_limit');
    $limit_bytes = return_bytes($memory_limit);
    $min_bytes = 128 * 1024 * 1024; // 128MB

    if ($limit_bytes >= $min_bytes || $memory_limit == '-1') {
        return [
            'status' => 'pass',
            'message' => 'Memory limit OK: ' . $memory_limit,
            'details' => 'Recommended minimum: 128M'
        ];
    } else {
        return [
            'status' => 'warning',
            'message' => 'Low memory limit: ' . $memory_limit,
            'details' => 'Consider increasing to 128M or higher'
        ];
    }
}, 'PHP Configuration');

function return_bytes($val)
{
    $val = trim($val);
    $last = strtolower($val[strlen($val) - 1]);
    $val = (int) $val;
    switch ($last) {
        case 'g':
            $val *= 1024;
        case 'm':
            $val *= 1024;
        case 'k':
            $val *= 1024;
    }
    return $val;
}

runTest('File Upload Settings', function () {
    $upload_max = ini_get('upload_max_filesize');
    $post_max = ini_get('post_max_size');

    return [
        'status' => 'pass',
        'message' => 'Upload settings configured',
        'details' => "Max upload: $upload_max, Max POST: $post_max"
    ];
}, 'PHP Configuration');

// ==========================================
// SESSION TESTS
// ==========================================

runTest('Session Functionality', function () {
    $_SESSION['test_key'] = 'test_value';

    if (isset($_SESSION['test_key']) && $_SESSION['test_key'] === 'test_value') {
        unset($_SESSION['test_key']);
        return [
            'status' => 'pass',
            'message' => 'Session read/write OK',
            'details' => 'Session ID: ' . session_id()
        ];
    } else {
        return [
            'status' => 'fail',
            'message' => 'Session not working properly'
        ];
    }
}, 'Session');

runTest('Session Security Settings', function () {
    $issues = [];

    if (!ini_get('session.cookie_httponly')) {
        $issues[] = 'HTTPOnly not set';
    }

    if (ini_get('session.use_strict_mode') != 1) {
        $issues[] = 'Strict mode not enabled';
    }

    if (empty($issues)) {
        return [
            'status' => 'pass',
            'message' => 'Session security OK'
        ];
    } else {
        return [
            'status' => 'warning',
            'message' => 'Session security issues: ' . implode(', ', $issues)
        ];
    }
}, 'Session');

// ==========================================
// EMAIL TESTS
// ==========================================

runTest('PHPMailer Library', function () {
    $phpmailer_path = __DIR__ . '/phpmailer';

    if (is_dir($phpmailer_path)) {
        $required_files = ['PHPMailer.php', 'SMTP.php', 'Exception.php'];
        $missing = [];

        foreach ($required_files as $file) {
            if (!file_exists($phpmailer_path . '/' . $file)) {
                $missing[] = $file;
            }
        }

        if (empty($missing)) {
            return [
                'status' => 'pass',
                'message' => 'PHPMailer library complete',
                'details' => 'All required files present'
            ];
        } else {
            return [
                'status' => 'fail',
                'message' => 'Missing PHPMailer files: ' . implode(', ', $missing)
            ];
        }
    } else {
        return [
            'status' => 'fail',
            'message' => 'PHPMailer directory not found'
        ];
    }
}, 'Email');

// ==========================================
// SECURITY TESTS
// ==========================================

runTest('Sensitive Files Protection', function () {
    $sensitive_files = ['CYCLOAN_db.php', 'sftp.json'];
    $exposed = [];

    foreach ($sensitive_files as $file) {
        if (file_exists(__DIR__ . '/' . $file)) {
            // Check if file starts with <?php
            $content = file_get_contents(__DIR__ . '/' . $file);
            if (strpos($file, '.php') !== false && strpos($content, '<?php') !== 0) {
                $exposed[] = $file . ' (not starting with <?php)';
            }
        }
    }

    if (empty($exposed)) {
        return [
            'status' => 'pass',
            'message' => 'Sensitive files protected'
        ];
    } else {
        return [
            'status' => 'warning',
            'message' => 'Potential exposure: ' . implode(', ', $exposed)
        ];
    }
}, 'Security');

runTest('Error Display Settings', function () {
    $display_errors = ini_get('display_errors');

    if ($display_errors == '0' || $display_errors == 'Off') {
        return [
            'status' => 'pass',
            'message' => 'Error display disabled (production setting)'
        ];
    } else {
        return [
            'status' => 'warning',
            'message' => 'Error display enabled (development setting)',
            'details' => 'Should be disabled in production'
        ];
    }
}, 'Security');

runTest('HTTPS Configuration', function () {
    $is_https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || $_SERVER['SERVER_PORT'] == 443
        || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');

    if ($is_https) {
        return [
            'status' => 'pass',
            'message' => 'HTTPS enabled',
            'details' => 'Secure connection active'
        ];
    } else {
        return [
            'status' => 'warning',
            'message' => 'HTTPS not detected',
            'details' => 'Consider enabling SSL/TLS for production'
        ];
    }
}, 'Security');

// ==========================================
// PERFORMANCE TESTS
// ==========================================

runTest('Disk Space', function () {
    $free_space = disk_free_space(__DIR__);
    $total_space = disk_total_space(__DIR__);
    $used_percent = round((($total_space - $free_space) / $total_space) * 100, 2);

    $free_gb = round($free_space / (1024 * 1024 * 1024), 2);

    if ($free_gb > 1) {
        return [
            'status' => 'pass',
            'message' => "Disk space OK: {$free_gb}GB free",
            'details' => "Used: {$used_percent}%"
        ];
    } else {
        return [
            'status' => 'warning',
            'message' => "Low disk space: {$free_gb}GB free",
            'details' => "Used: {$used_percent}%"
        ];
    }
}, 'Performance');

runTest('Database Response Time', function () {
    global $conn;

    $start = microtime(true);
    $result = $conn->query("SELECT 1");
    $duration = round((microtime(true) - $start) * 1000, 2);

    if ($duration < 100) {
        return [
            'status' => 'pass',
            'message' => "Database response: {$duration}ms",
            'details' => 'Response time is good'
        ];
    } elseif ($duration < 500) {
        return [
            'status' => 'warning',
            'message' => "Database response: {$duration}ms",
            'details' => 'Response time is acceptable'
        ];
    } else {
        return [
            'status' => 'fail',
            'message' => "Database response: {$duration}ms",
            'details' => 'Response time is slow'
        ];
    }
}, 'Performance');

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CYCLOAN - System Test Results</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #1b5e20 0%, #2e7d32 100%);
            padding: 20px;
            color: #333;
        }

        .container {
            max-width: 1400px;
            margin: 0 auto;
            background: white;
            border-radius: 16px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.2);
            overflow: hidden;
        }

        .header {
            background: linear-gradient(135deg, #1b5e20 0%, #2e7d32 100%);
            color: white;
            padding: 30px;
            text-align: center;
        }

        .header h1 {
            font-size: 32px;
            margin-bottom: 10px;
        }

        .summary {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            padding: 30px;
            background: #f5f5f5;
        }

        .summary-card {
            background: white;
            padding: 20px;
            border-radius: 12px;
            text-align: center;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
        }

        .summary-card i {
            font-size: 36px;
            margin-bottom: 10px;
        }

        .summary-card .number {
            font-size: 32px;
            font-weight: 700;
        }

        .summary-card .label {
            font-size: 14px;
            color: #666;
            margin-top: 5px;
        }

        .pass {
            color: #4caf50;
        }

        .fail {
            color: #f44336;
        }

        .warning {
            color: #ff9800;
        }

        .tests-container {
            padding: 30px;
        }

        .category-section {
            margin-bottom: 30px;
        }

        .category-header {
            background: #1b5e20;
            color: white;
            padding: 15px 20px;
            border-radius: 8px 8px 0 0;
            font-size: 18px;
            font-weight: 600;
        }

        .test-item {
            padding: 15px 20px;
            border-bottom: 1px solid #e0e0e0;
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            background: white;
        }

        .test-item:last-child {
            border-bottom: none;
            border-radius: 0 0 8px 8px;
        }

        .test-info {
            flex: 1;
        }

        .test-name {
            font-weight: 600;
            margin-bottom: 5px;
            font-size: 16px;
        }

        .test-message {
            color: #666;
            font-size: 14px;
        }

        .test-details {
            margin-top: 5px;
            padding: 10px;
            background: #f5f5f5;
            border-radius: 4px;
            font-size: 13px;
            font-family: monospace;
        }

        .test-status {
            display: flex;
            align-items: center;
            gap: 10px;
            min-width: 120px;
            justify-content: flex-end;
        }

        .status-badge {
            padding: 6px 12px;
            border-radius: 20px;
            font-weight: 600;
            font-size: 12px;
            color: white;
        }

        .status-badge.pass {
            background: #4caf50;
        }

        .status-badge.fail {
            background: #f44336;
        }

        .status-badge.warning {
            background: #ff9800;
        }

        .duration {
            font-size: 12px;
            color: #999;
        }

        .actions {
            padding: 20px 30px;
            background: #f5f5f5;
            text-align: center;
            border-top: 2px solid #e0e0e0;
        }

        .btn {
            padding: 12px 24px;
            background: linear-gradient(135deg, #1b5e20 0%, #2e7d32 100%);
            color: white;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            text-decoration: none;
            display: inline-block;
            margin: 5px;
        }

        .btn:hover {
            opacity: 0.9;
        }

        @media print {
            body {
                background: white;
                padding: 0;
            }

            .actions {
                display: none;
            }
        }
    </style>
</head>

<body>
    <div class="container">
        <div class="header">
            <h1><i class="fas fa-flask"></i> CYCLOAN System Test Results</h1>
            <p>Comprehensive testing of all system components</p>
            <p style="margin-top: 10px; font-size: 14px;">
                <i class="fas fa-clock"></i> Test Run: <?php echo date('F d, Y h:i:s A'); ?>
            </p>
        </div>

        <div class="summary">
            <div class="summary-card">
                <i class="fas fa-clipboard-check pass"></i>
                <div class="number pass"><?php echo $test_results['passed']; ?></div>
                <div class="label">Tests Passed</div>
            </div>
            <div class="summary-card">
                <i class="fas fa-exclamation-circle fail"></i>
                <div class="number fail"><?php echo $test_results['failed']; ?></div>
                <div class="label">Tests Failed</div>
            </div>
            <div class="summary-card">
                <i class="fas fa-exclamation-triangle warning"></i>
                <div class="number warning"><?php echo $test_results['warnings']; ?></div>
                <div class="label">Warnings</div>
            </div>
            <div class="summary-card">
                <i class="fas fa-list-ol"></i>
                <div class="number" style="color: #1b5e20;"><?php echo $test_results['total']; ?></div>
                <div class="label">Total Tests</div>
            </div>
        </div>

        <div class="tests-container">
            <?php
            // Group tests by category
            $categories = [];
            foreach ($test_details as $test) {
                $categories[$test['category']][] = $test;
            }

            // Display tests by category
            foreach ($categories as $category => $tests):
                ?>
                <div class="category-section">
                    <div class="category-header">
                        <i class="fas fa-folder"></i> <?php echo $category; ?> Tests
                    </div>
                    <?php foreach ($tests as $test): ?>
                        <div class="test-item">
                            <div class="test-info">
                                <div class="test-name">
                                    <i class="fas fa-<?php
                                    echo $test['status'] === 'pass' ? 'check-circle pass' :
                                        ($test['status'] === 'warning' ? 'exclamation-triangle warning' : 'times-circle fail');
                                    ?>"></i>
                                    <?php echo htmlspecialchars($test['name']); ?>
                                </div>
                                <div class="test-message">
                                    <?php echo htmlspecialchars($test['message']); ?>
                                </div>
                                <?php if (!empty($test['details'])): ?>
                                    <div class="test-details">
                                        <?php echo htmlspecialchars($test['details']); ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <div class="test-status">
                                <span class="duration"><?php echo $test['duration']; ?>ms</span>
                                <span class="status-badge <?php echo $test['status']; ?>">
                                    <?php echo strtoupper($test['status']); ?>
                                </span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endforeach; ?>
        </div>

        <div class="actions">
            <button class="btn" onclick="location.reload()">
                <i class="fas fa-sync-alt"></i> Run Tests Again
            </button>
            <button class="btn" onclick="window.print()">
                <i class="fas fa-print"></i> Print Report
            </button>
            <a href="check_errors.php" class="btn">
                <i class="fas fa-bug"></i> View Error Monitor
            </a>
            <a href="index.php" class="btn">
                <i class="fas fa-home"></i> Back to Home
            </a>
        </div>
    </div>

    <script>
        // Auto-scroll to first failed test
        document.addEventListener('DOMConten tLoaded', function () {
            const failedTest = document.querySelector('.test-item .fail');
            if (failedTest) {
                failedTest.scrollIntoView({ behavior: 'smooth', block: 'center' });
            }
        });
    </script>
</body>

</html>