<?php
/**
 * CYCLOAN Registration Troubleshooting Diagnostics
 * 
 * Run this script to diagnose registration issues
 * Access via: http://your-site.com/registration_diagnostic.php
 */

echo "<h1>CYCLOAN Registration Diagnostics</h1>\n";
echo "<hr>\n";

// 1. Check environment file
echo "<h2>1. Environment Configuration</h2>\n";
if (file_exists('.env')) {
    echo "✅ <strong>.env file exists</strong><br>\n";
    $env_vars = parse_ini_file('.env');
    echo "<pre>";
    foreach ($env_vars as $key => $value) {
        // Mask sensitive data
        $display_value = (stripos($key, 'password') !== false || stripos($key, 'token') !== false)
            ? str_repeat('*', 8)
            : $value;
        echo "$key = $display_value\n";
    }
    echo "</pre>";
} else {
    echo "❌ <strong>.env file NOT found</strong><br>\n";
    echo "Create .env file from .env.example<br>\n";
}

echo "<hr>\n";

// 2. Check Database Connection
echo "<h2>2. Database Connection</h2>\n";
require_once 'CYCLOAN_db.php';

if (isset($conn) && $conn) {
    if ($conn->connect_error) {
        echo "❌ <strong>Database Connection Error:</strong> " . $conn->connect_error . "<br>\n";
    } else {
        echo "✅ <strong>Database Connected Successfully</strong><br>\n";

        // Check tables
        echo "<h3>Database Tables:</h3>\n";
        $required_tables = ['users1', 'otps', 'financial_info', 'income_sources', 'expenditure_types', 'spouses'];
        $result = $conn->query("SHOW TABLES");
        $existing_tables = [];
        while ($row = $result->fetch_row()) {
            $existing_tables[] = $row[0];
        }

        foreach ($required_tables as $table) {
            if (in_array($table, $existing_tables)) {
                echo "✅ Table <strong>$table</strong> exists<br>\n";
            } else {
                echo "❌ Table <strong>$table</strong> NOT found<br>\n";
            }
        }
    }
} else {
    echo "❌ <strong>Database connection not initialized</strong><br>\n";
}

echo "<hr>\n";

// 3. Check Required Files
echo "<h2>3. Required Files</h2>\n";
$required_files = [
    'security_validation.php' => 'Input validation functions',
    'rate_limiter.php' => 'Rate limiting system',
    'two_factor_auth.php' => '2FA system',
    'phpmailer/src/PHPMailer.php' => 'PHPMailer library',
    'process_registration.php' => 'Registration processor',
    'registration.php' => 'Registration form'
];

foreach ($required_files as $file => $description) {
    if (file_exists($file)) {
        echo "✅ <strong>$file</strong> - $description<br>\n";
    } else {
        echo "❌ <strong>$file</strong> - $description (NOT FOUND)<br>\n";
    }
}

echo "<hr>\n";

// 4. Check PHP Extensions
echo "<h2>4. PHP Extensions</h2>\n";
$extensions = ['mysqli', 'json', 'curl', 'mbstring'];
foreach ($extensions as $ext) {
    if (extension_loaded($ext)) {
        echo "✅ <strong>$ext</strong> extension loaded<br>\n";
    } else {
        echo "❌ <strong>$ext</strong> extension NOT loaded<br>\n";
    }
}

echo "<hr>\n";

// 5. Check File Permissions
echo "<h2>5. File Permissions</h2>\n";
$directories = [
    'uploads' => 'File uploads directory',
    '.' => 'Application root'
];

foreach ($directories as $dir => $description) {
    if (is_dir($dir)) {
        if (is_writable($dir)) {
            echo "✅ <strong>$dir</strong> - Writable ($description)<br>\n";
        } else {
            echo "⚠️ <strong>$dir</strong> - NOT writable ($description)<br>\n";
        }
    }
}

echo "<hr>\n";

// 6. Test Email Configuration
echo "<h2>6. Email Configuration</h2>\n";
require_once 'env_config.php';
echo "MAIL_HOST: " . MAIL_HOST . "<br>\n";
echo "MAIL_PORT: " . MAIL_PORT . "<br>\n";
echo "MAIL_USERNAME: " . substr(MAIL_USERNAME, 0, 3) . "***@***.***<br>\n";
echo "MAIL_FROM_NAME: " . MAIL_FROM_NAME . "<br>\n";

echo "<hr>\n";

// 7. Check Error Logs
echo "<h2>7. Recent Errors</h2>\n";
$log_files = ['error_registration.log', 'php_errors.log', 'php_error.log', 'errors.log'];
foreach ($log_files as $log_file) {
    if (file_exists($log_file)) {
        echo "<h3>$log_file</h3>\n";
        echo "<pre style='background: #f0f0f0; padding: 10px; overflow-y: scroll; max-height: 300px;'>";
        $lines = array_slice(file($log_file), -20);
        foreach ($lines as $line) {
            echo htmlspecialchars($line);
        }
        echo "</pre>\n";
        break;
    }
}

echo "<hr>\n";

// 8. Test a Sample Validation
echo "<h2>8. Validation Functions Test</h2>\n";
require_once 'security_validation.php';

echo "<strong>Email Validation:</strong> ";
$test_email = validateEmail('test@example.com');
if ($test_email) {
    echo "✅ Valid<br>\n";
} else {
    echo "❌ Invalid<br>\n";
}

echo "<strong>Phone Number Validation:</strong> ";
$test_phone = validatePhoneNumber('09123456789');
if ($test_phone) {
    echo "✅ Valid (Formatted: " . htmlspecialchars($test_phone) . ")<br>\n";
} else {
    echo "❌ Invalid<br>\n";
}

echo "<strong>Date Validation:</strong> ";
$test_date = validateDate('1990-01-01', 'Y-m-d');
if ($test_date) {
    echo "✅ Valid<br>\n";
} else {
    echo "❌ Invalid<br>\n";
}

echo "<hr>\n";

// 9. Summary
echo "<h2>Summary</h2>\n";
echo "<p>If all checks above show ✅, your system is properly configured.</p>\n";
echo "<p>If you're still getting 500 errors:</p>\n";
echo "<ol>\n";
echo "<li>Check the error logs above for specific error messages</li>\n";
echo "<li>Verify all database credentials in CYCLOAN_db.php</li>\n";
echo "<li>Ensure the .env file has correct MAIL configuration</li>\n";
echo "<li>Review the browser's Network tab for detailed error responses</li>\n";
echo "</ol>\n";

echo "<hr>\n";
echo "<p><small>Diagnostic generated: " . date('Y-m-d H:i:s') . "</small></p>\n";
?>