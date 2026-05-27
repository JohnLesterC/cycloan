<?php
/**
 * EMAIL CONFIGURATION TEST
 * 
 * This file tests if your email configuration is working correctly.
 * Run: php test_email.php
 */

echo "========================================\n";
echo "EMAIL CONFIGURATION TEST\n";
echo "========================================\n\n";

// Check PHPMailer
echo "Checking PHPMailer Installation...\n";
$phpmailer_files = [
    'phpmailer/src/Exception.php' => 'Exception.php',
    'phpmailer/src/PHPMailer.php' => 'PHPMailer.php',
    'phpmailer/src/SMTP.php' => 'SMTP.php'
];

$all_found = true;
foreach ($phpmailer_files as $path => $name) {
    if (file_exists($path)) {
        echo "  ✅ $name found\n";
    } else {
        echo "  ❌ $name MISSING (expected at: $path)\n";
        $all_found = false;
    }
}

if (!$all_found) {
    echo "\n❌ PHPMailer installation incomplete!\n";
    echo "Download from: https://github.com/PHPMailer/PHPMailer/releases\n";
    echo "Extract to: phpmailer/ folder\n";
    exit(1);
}

echo "\n✅ PHPMailer files found\n\n";

// Load PHPMailer
require_once 'phpmailer/src/Exception.php';
require_once 'phpmailer/src/PHPMailer.php';
require_once 'phpmailer/src/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Test email configuration
echo "Email Configuration:\n";
echo "  MAIL_HOST: smtp.gmail.com\n";
echo "  MAIL_PORT: 587\n";
echo "  MAIL_USERNAME: your_email@gmail.com\n";
echo "  MAIL_ENCRYPTION: STARTTLS\n";
echo "  MAIL_FROM_NAME: CYCLOAN\n";
echo "\n";

echo "Testing SMTP Connection...\n";

try {
    $mail = new PHPMailer(true);

    // Enable debugging during test
    $mail->SMTPDebug = 0; // Set to 2 for verbose debugging

    // SMTP configuration
    $mail->isSMTP();
    $mail->Host = 'smtp.gmail.com';
    $mail->SMTPAuth = true;
    $mail->Username = 'your_email@gmail.com';
    $mail->Password = 'your_app_password';
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port = 587;

    // Set from
    $mail->setFrom('your_email@gmail.com', 'CYCLOAN');

    // Test connection
    if ($mail->smtpConnect()) {
        echo "✅ SMTP Connection successful!\n";
        $mail->smtpClose();
    } else {
        echo "❌ SMTP Connection failed\n";
        echo "Error: " . $mail->ErrorInfo . "\n";
        exit(1);
    }

    echo "\n========================================\n";
    echo "RESULT: ✅ EMAIL CONFIGURATION OK\n";
    echo "========================================\n";
    echo "\nNote: To test actual email sending, you can:\n";
    echo "1. Use test_send_otp.php to send a test OTP\n";
    echo "2. Check your email for the OTP code\n";

} catch (Exception $e) {
    echo "❌ EMAIL ERROR!\n";
    echo "Exception: " . $e->getMessage() . "\n";

    if (isset($mail)) {
        echo "PHPMailer Error: " . $mail->ErrorInfo . "\n";
    }

    exit(1);
}
?>