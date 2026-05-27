<?php
/**
 * SYSTEM HEALTH CHECK - REGISTRATION DIAGNOSTIC
 * 
 * This comprehensive test checks all components needed for registration to work.
 * Run: php system_health_check.php
 */

echo "================================================================================\n";
echo "CYCLOAN REGISTRATION SYSTEM HEALTH CHECK\n";
echo "================================================================================\n\n";

$results = [
    'config' => [],
    'database' => [],
    'email' => [],
    'files' => [],
    'php' => [],
    'errors' => []
];

// 1. Configuration Check
echo "1. CONFIGURATION CHECK\n";
echo "------------------------\n";

if (file_exists('env_config.php')) {
    echo "✅ env_config.php exists\n";
    $results['config']['env_config'] = true;
    require_once 'env_config.php';
} else {
    echo "❌ env_config.php MISSING\n";
    $results['config']['env_config'] = false;
    $results['errors'][] = "env_config.php not found";
    exit(1);
}

if (file_exists('.env')) {
    echo "✅ .env exists\n";
    $results['config']['.env'] = true;
} else {
    echo "❌ .env MISSING\n";
    $results['config']['.env'] = false;
    $results['errors'][] = ".env file not found";
}

echo "\n";

// 2. PHP Version & Extensions
echo "2. PHP VERSION & EXTENSIONS\n";
echo "---------------------------\n";
echo "PHP Version: " . phpversion() . "\n";

$required_extensions = ['mysqli', 'json', 'curl', 'mbstring'];
foreach ($required_extensions as $ext) {
    if (extension_loaded($ext)) {
        echo "✅ Extension '$ext' loaded\n";
        $results['php'][$ext] = true;
    } else {
        echo "❌ Extension '$ext' NOT loaded\n";
        $results['php'][$ext] = false;
        $results['errors'][] = "PHP extension '$ext' missing";
    }
}

echo "\n";

// 3. Required Files
echo "3. REQUIRED FILES\n";
echo "-----------------\n";

$required_files = [
    'process_registration.php' => 'Registration processor',
    'security_validation.php' => 'Validation functions',
    'CYCLOAN_db.php' => 'Database connection',
    'rate_limiter.php' => 'Rate limiting',
    'two_factor_auth.php' => '2FA system',
    'phpmailer/src/PHPMailer.php' => 'PHPMailer library'
];

foreach ($required_files as $file => $desc) {
    if (file_exists($file)) {
        echo "✅ $desc ($file)\n";
        $results['files'][$file] = true;
    } else {
        echo "❌ $desc ($file) MISSING\n";
        $results['files'][$file] = false;
        $results['errors'][] = "Required file '$file' not found";
    }
}

echo "\n";

// 4. Database Connection
echo "4. DATABASE CONNECTION\n";
echo "----------------------\n";

echo "Config:\n";
echo "  DB_HOST: " . DB_HOST . "\n";
echo "  DB_USER: " . DB_USER . "\n";
echo "  DB_NAME: " . DB_NAME . "\n";
echo "  DB_PORT: " . DB_PORT . "\n\n";

$conn = mysqli_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME, DB_PORT);

if ($conn->connect_error) {
    echo "❌ Connection FAILED: " . $conn->connect_error . "\n";
    $results['database']['connection'] = false;
    $results['errors'][] = "Database connection failed: " . $conn->connect_error;
} else {
    echo "✅ Connection successful\n";
    echo "   MySQL Version: " . mysqli_get_server_info($conn) . "\n";
    $results['database']['connection'] = true;

    // Check tables
    echo "\nChecking Tables:\n";
    $tables = ['users1', 'otps', 'financial_info', 'income_sources', 'expenditure_types', 'spouses'];

    foreach ($tables as $table) {
        $result = $conn->query("SHOW TABLES LIKE '$table'");
        if ($result && $result->num_rows > 0) {
            echo "  ✅ Table '$table' exists\n";
            $results['database']["table_$table"] = true;
        } else {
            echo "  ❌ Table '$table' MISSING\n";
            $results['database']["table_$table"] = false;
            $results['errors'][] = "Database table '$table' not found";
        }
    }

    $conn->close();
}

echo "\n";

// 5. Email Configuration
echo "5. EMAIL CONFIGURATION\n";
echo "----------------------\n";

echo "Config:\n";
echo "  MAIL_HOST: " . MAIL_HOST . "\n";
echo "  MAIL_PORT: " . MAIL_PORT . "\n";
echo "  MAIL_USERNAME: " . MAIL_USERNAME . "\n";
echo "  MAIL_ENCRYPTION: " . MAIL_ENCRYPTION . "\n\n";

if (!file_exists('phpmailer/src/PHPMailer.php')) {
    echo "❌ PHPMailer not installed\n";
    $results['email']['phpmailer'] = false;
    $results['errors'][] = "PHPMailer not installed";
} else {
    try {
        require_once 'phpmailer/src/Exception.php';
        require_once 'phpmailer/src/PHPMailer.php';
        require_once 'phpmailer/src/SMTP.php';

        $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
        $mail->SMTPDebug = 0;
        $mail->isSMTP();
        $mail->Host = MAIL_HOST;
        $mail->SMTPAuth = true;
        $mail->Username = MAIL_USERNAME;
        $mail->Password = MAIL_PASSWORD;
        $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = MAIL_PORT;

        if ($mail->smtpConnect()) {
            echo "✅ SMTP connection successful\n";
            $results['email']['smtp'] = true;
            $mail->smtpClose();
        } else {
            echo "❌ SMTP connection failed: " . $mail->ErrorInfo . "\n";
            $results['email']['smtp'] = false;
            $results['errors'][] = "SMTP connection failed";
        }
    } catch (Exception $e) {
        echo "❌ Email error: " . $e->getMessage() . "\n";
        $results['email']['phpmailer'] = false;
        $results['errors'][] = "PHPMailer error: " . $e->getMessage();
    }
}

echo "\n";

// 6. Application Status
echo "6. APPLICATION STATUS\n";
echo "----------------------\n";

echo "APP_NAME: " . APP_NAME . "\n";
echo "APP_URL: " . APP_URL . "\n";
echo "APP_ENV: " . APP_ENV . "\n";
echo "APP_DEBUG: " . (APP_DEBUG ? 'true' : 'false') . "\n\n";

if (APP_ENV !== 'production') {
    echo "⚠️  WARNING: APP_ENV is not set to 'production'\n";
}

if (APP_DEBUG === true) {
    echo "⚠️  WARNING: APP_DEBUG is set to true (should be false in production)\n";
}

echo "\n";

// 7. Summary
echo "================================================================================\n";
echo "SUMMARY\n";
echo "================================================================================\n\n";

$total_checks = count($results) - 1; // Exclude 'errors'
$failed_checks = count($results['errors']);

if ($failed_checks === 0) {
    echo "✅ ALL CHECKS PASSED!\n\n";
    echo "Your system is configured correctly for registration.\n";
    echo "If registration is still failing, check:\n";
    echo "1. Browser console (F12 → Console tab) for JavaScript errors\n";
    echo "2. Server error logs:\n";
    echo "   tail -f /var/log/php-fpm/error.log\n";
    echo "   tail -f /var/log/apache2/error.log\n";
    echo "3. Application error log:\n";
    echo "   tail -f error_registration.log\n";
} else {
    echo "❌ FOUND " . count($results['errors']) . " ISSUE(S):\n\n";
    foreach ($results['errors'] as $i => $error) {
        echo "  " . ($i + 1) . ". " . $error . "\n";
    }
    echo "\nPlease fix these issues and run this check again.\n";
}

echo "\n";

// 8. Debugging Tips
echo "================================================================================\n";
echo "DEBUGGING TIPS\n";
echo "================================================================================\n\n";

echo "If registration is still failing:\n\n";

echo "Option 1: Enable Verbose Logging\n";
echo "Edit process_registration.php and add:\n";
echo "  ini_set('display_errors', 1);\n";
echo "  ini_set('log_errors', 1);\n";
echo "  error_reporting(E_ALL);\n\n";

echo "Option 2: Check Error Logs\n";
echo "  tail -f error_registration.log\n";
echo "  tail -f /var/log/php-fpm/error.log\n\n";

echo "Option 3: Test Individual Components\n";
echo "  php test_db.php         # Test database\n";
echo "  php test_email.php      # Test email\n\n";

echo "Option 4: Browser Network Tab\n";
echo "  1. Open F12 (Developer Tools)\n";
echo "  2. Go to Network tab\n";
echo "  3. Submit registration form\n";
echo "  4. Click on POST request to process_registration.php\n";
echo "  5. Check Response tab for error message\n\n";

echo "================================================================================\n";
echo "END OF HEALTH CHECK\n";
echo "================================================================================\n";
?>