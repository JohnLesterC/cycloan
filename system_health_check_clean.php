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
    'database' => [],
    'email' => [],
    'files' => [],
    'php' => [],
    'errors' => []
];

// 1. PHP Version & Extensions
echo "1. PHP VERSION & EXTENSIONS\n";
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

// 2. Required Files
echo "2. REQUIRED FILES\n";
echo "-----------------\n";

$required_files = [
    'process_registration.php' => 'Registration processor',
    'registration.php' => 'Registration form',
    'CYCLOAN_db.php' => 'Database connection',
    'phpmailer/src/PHPMailer.php' => 'PHPMailer library'
];

foreach ($required_files as $file => $desc) {
    if (file_exists($file)) {
        echo "✅ $file ($desc)\n";
        $results['files'][$file] = true;
    } else {
        echo "❌ $file - MISSING ($desc)\n";
        $results['files'][$file] = false;
        $results['errors'][] = "$file not found";
    }
}

echo "\n";

// 3. Database Connection
echo "3. DATABASE CONNECTION\n";
echo "---------------------\n";

require_once 'CYCLOAN_db.php';

if ($conn && !$conn->connect_error) {
    echo "✅ Database connected successfully\n";
    $results['database']['connection'] = true;

    // Check tables
    $tables = ['users1', 'otps', 'financial_info', 'income_sources', 'expenditure_types', 'spouses'];
    echo "\nDatabase Tables:\n";
    foreach ($tables as $table) {
        $result = $conn->query("SHOW TABLES LIKE '$table'");
        if ($result && $result->num_rows > 0) {
            echo "  ✅ Table '$table' exists\n";
            $results['database'][$table] = true;
        } else {
            echo "  ❌ Table '$table' MISSING\n";
            $results['database'][$table] = false;
            $results['errors'][] = "Table '$table' not found";
        }
    }
} else {
    echo "❌ Database connection FAILED\n";
    echo "Error: " . $conn->connect_error . "\n";
    $results['database']['connection'] = false;
    $results['errors'][] = "Database connection failed";
}

echo "\n";

// 4. Email Configuration (PHPMailer)
echo "4. EMAIL CONFIGURATION\n";
echo "---------------------\n";

$phpmailer_files = [
    'phpmailer/src/Exception.php' => 'Exception.php',
    'phpmailer/src/PHPMailer.php' => 'PHPMailer.php',
    'phpmailer/src/SMTP.php' => 'SMTP.php'
];

$all_found = true;
foreach ($phpmailer_files as $path => $name) {
    if (file_exists($path)) {
        echo "✅ $name found\n";
        $results['email'][$name] = true;
    } else {
        echo "❌ $name MISSING\n";
        $results['email'][$name] = false;
        $results['errors'][] = "$name not found";
        $all_found = false;
    }
}

if ($all_found) {
    echo "\n✅ PHPMailer Installation OK\n";
    echo "Email Configuration (Hardcoded):\n";
    echo "  SMTP Host: smtp.gmail.com (update in code)\n";
    echo "  SMTP Port: 587\n";
    echo "  Email: your_email@gmail.com (update in code)\n";
    echo "  From: support@cycloan-cldd.com\n";
} else {
    echo "\n❌ PHPMailer installation incomplete\n";
}

echo "\n";

// Final Summary
echo "================================================================================\n";
echo "HEALTH CHECK SUMMARY\n";
echo "================================================================================\n\n";

$total_errors = count($results['errors']);

if ($total_errors === 0) {
    echo "✅ ALL CHECKS PASSED - System is ready for registration!\n";
} else {
    echo "❌ ISSUES FOUND:\n";
    foreach ($results['errors'] as $error) {
        echo "  - $error\n";
    }
}

echo "\nTo test registration:\n";
echo "1. Update SMTP credentials in process_registration.php (lines 75-78)\n";
echo "2. Upload registration.php and process_registration.php to production\n";
echo "3. Visit: http://localhost/CYCLOAN/registration.php?step=1\n";

echo "\n================================================================================\n";
?>