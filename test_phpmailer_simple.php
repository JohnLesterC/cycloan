<?php
/**
 * TEST_PHPMAILER_SIMPLE.PHP
 * Simpler test that only checks PHPMailer without requiring database
 */

// Enable all error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Check if PHPMailer is installed
$phpmailerPath = __DIR__ . '/phpmailer/src/PHPMailer.php';

if (!file_exists($phpmailerPath)) {
    echo "ERROR: PHPMailer not found at: $phpmailerPath\n";
    exit(1);
}

// Load PHPMailer
require $phpmailerPath;
require __DIR__ . '/phpmailer/src/SMTP.php';
require __DIR__ . '/phpmailer/src/Exception.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

echo "=== PHPMAILER DIRECT TEST ===\n\n";

try {
    $mail = new PHPMailer(true);

    // Enable detailed debugging
    echo "[*] Enabling SMTP debug mode 2 (verbose)\n";
    $mail->SMTPDebug = 2;

    // Capture debug output
    $mail->Debugoutput = function ($str, $level) {
        echo "[SMTP_DEBUG_$level] $str\n";
    };

    echo "[*] Setting up SMTP configuration...\n";

    $mail->isSMTP();
    $mail->Host = 'smtp.gmail.com';
    $mail->SMTPAuth = true;
    $mail->Username = 'cycloancldd@gmail.com';
    $mail->Password = 'hbfh ukgh tmzw nqbq';
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port = 587;
    $mail->Timeout = 30;
    $mail->ConnectionTimeout = 20;
    $mail->SMTPKeepAlive = true;

    echo "[*] SMTP configuration set\n";
    echo "[*] Host: " . $mail->Host . "\n";
    echo "[*] Port: " . $mail->Port . "\n";
    echo "[*] Secure: STARTTLS\n\n";

    echo "[*] Setting sender and recipient...\n";
    $mail->setFrom('cycloancldd@gmail.com', 'CYCLOAN Test');
    $mail->addAddress('test@example.com', 'Test User');

    echo "[*] Setting email content...\n";
    $mail->isHTML(true);
    $mail->Subject = 'Test Email - ' . date('Y-m-d H:i:s');
    $mail->Body = '<html><body><h2>Test Email</h2><p>This is a test email sent at ' . date('Y-m-d H:i:s') . '</p></body></html>';
    $mail->AltBody = 'Test Email Body';

    echo "[*] Email content set\n\n";

    echo "[!] ATTEMPTING TO SEND EMAIL...\n";
    echo str_repeat("-", 60) . "\n";

    $result = $mail->send();

    echo str_repeat("-", 60) . "\n";
    echo "[!] SEND ATTEMPT COMPLETE\n\n";

    if ($result) {
        echo "[SUCCESS] Email sent successfully!\n";
    } else {
        echo "[FAILED] Email send failed\n";
        echo "Error Info: " . $mail->ErrorInfo . "\n";
        if (isset($mail->Exception)) {
            echo "Last Exception: " . $mail->Exception . "\n";
        }
    }

} catch (Exception $e) {
    echo "[EXCEPTION] " . $e->getMessage() . "\n";
    echo "[CODE] " . $e->getCode() . "\n";
}

echo "\n[*] Test complete\n";
?>