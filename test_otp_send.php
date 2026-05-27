<?php
/**
 * OTP Email Send Test Script
 * Runs diagnostic checks on the OTP sending workflow
 */

session_start();
require 'email_config.php';
require 'CYCLOAN_db.php';

echo "<pre>";
echo "===== OTP EMAIL SEND DIAGNOSTIC TEST =====\n\n";

// Test 1: Database Connection
echo "Test 1: Database Connection\n";
echo "Status: " . ($conn && !$conn->connect_error ? "[OK]" : "[FAIL]") . "\n";
if ($conn->connect_error) {
    echo "Error: " . $conn->connect_error . "\n";
}
echo "\n";

// Test 2: Check otps table
echo "Test 2: Check 'otps' Table Structure\n";
$result = $conn->query("DESCRIBE otps");
if ($result && $result->num_rows > 0) {
    echo "[OK] Table exists. Columns:\n";
    while ($row = $result->fetch_assoc()) {
        echo "  - {$row['Field']} ({$row['Type']})\n";
    }
} else {
    echo "[FAIL] Table does not exist or query failed\n";
}
echo "\n";

// Test 3: Email Configuration
echo "Test 3: Email Configuration\n";
echo "SMTP Host: " . SMTP_HOST . "\n";
echo "SMTP Port: " . SMTP_PORT . "\n";
echo "SMTP Username: " . SMTP_USERNAME . "\n";
echo "SMTP From: " . SMTP_FROM_EMAIL . "\n";
echo "SMTP Encryption: " . SMTP_ENCRYPTION . "\n";
echo "\n";

// Test 4: PHPMailer Libraries
echo "Test 4: PHPMailer Libraries\n";
$files = [
    'phpmailer/src/PHPMailer.php',
    'phpmailer/src/SMTP.php',
    'phpmailer/src/Exception.php'
];
foreach ($files as $file) {
    echo "  " . basename($file) . ": " . (file_exists($file) ? "✅ Found" : "❌ Missing") . "\n";
}
echo "\n";

// Test 5: PHP mail() function
echo "Test 5: PHP mail() Function\n";
echo "mail() enabled: " . (ini_get('enable_mail') ? "✅ YES" : "❌ NO") . "\n";
echo "\n";

// Test 6: Sample OTP Generation
echo "Test 6: Sample OTP Generation\n";
function generateOTP()
{
    return str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
}
$sample_otp = generateOTP();
echo "Generated Sample OTP: $sample_otp\n";
echo "Format Valid (6 digits): " . (preg_match('/^\d{6}$/', $sample_otp) ? "✅ YES" : "❌ NO") . "\n";
echo "\n";

// Test 7: Test email address lookup
echo "Test 7: Email Lookup\n";
$test_email = 'john@example.com'; // Change this to test with actual email
$tables = ['users1', 'admin1', 'admin2', 'superadmins'];
$found = false;
foreach ($tables as $table) {
    $stmt = $conn->prepare("SELECT id FROM $table WHERE email = ?");
    $stmt->bind_param("s", $test_email);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        echo "✅ Email found in '$table' with ID: " . $row['id'] . "\n";
        $found = true;
    }
    $stmt->close();
}
if (!$found) {
    echo "⚠️  Email '$test_email' not found in any user table\n";
}
echo "\n";

echo "===== END DIAGNOSTIC TEST =====\n";
echo "</pre>";
?>