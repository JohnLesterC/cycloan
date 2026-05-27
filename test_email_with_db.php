<?php
/**
 * TEST_EMAIL_WITH_DB.PHP
 * Test email sending with actual database connection
 * 
 * This script will:
 * 1. Connect to database
 * 2. Find a real loan application with a user
 * 3. Send a test email to that user
 * 4. Capture all logs
 */

// Enable all error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Set up error logging to capture SMTP details
ini_set('log_errors', 1);
$logFile = __DIR__ . '/email_test_' . date('Y-m-d_H-i-s') . '.log';
ini_set('error_log', $logFile);

echo "=== EMAIL SYSTEM TEST WITH DATABASE ===\n";
echo "Log file: $logFile\n\n";

// Include database connection
require_once 'CYCLOAN_db.php';

if (!isset($conn) || $conn->connect_error) {
    echo "[ERROR] Database connection failed\n";
    if (isset($conn)) {
        echo "Error: " . $conn->connect_error . "\n";
    }
    exit(1);
}

echo "[OK] Database connection established\n\n";

// Include email functions from dashboard
// We need to extract just the essential functions

// Load PHPMailer
require __DIR__ . '/phpmailer/src/PHPMailer.php';
require __DIR__ . '/phpmailer/src/SMTP.php';
require __DIR__ . '/phpmailer/src/Exception.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

// Find a real application with a valid user email
echo "[*] Searching for a real loan application with user email...\n";

$query = "SELECT la.application_id, u.email, u.first_name, u.last_name 
          FROM loan_applications la 
          JOIN users1 u ON la.user_id = u.id 
          WHERE u.email IS NOT NULL AND u.email != '' 
          LIMIT 1";

$result = $conn->query($query);

if (!$result || $result->num_rows === 0) {
    echo "[ERROR] No loan applications with user emails found\n";
    exit(1);
}

$row = $result->fetch_assoc();
$applicationId = $row['application_id'];
$to = $row['email'];
$name = trim($row['first_name'] . ' ' . $row['last_name']);

echo "[OK] Found application:\n";
echo "    Application ID: $applicationId\n";
echo "    User Email: $to\n";
echo "    User Name: $name\n\n";

// Now test email sending to this real user
echo "[*] Attempting to send test email...\n";

try {
    $mail = new PHPMailer(true);

    // Enable detailed debugging
    $mail->SMTPDebug = 2;

    $mail->Debugoutput = function ($str, $level) use ($logFile) {
        error_log("SMTP_DEBUG_$level: $str");
    };

    // Configure SMTP
    $mail->isSMTP();
    $mail->Host = 'smtp.gmail.com';
    $mail->SMTPAuth = true;
    $mail->Username = 'cycloancldd@gmail.com';
    $mail->Password = 'hbfh ukgh tmzw nqbq';
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port = 587;
    $mail->Timeout = 30;
    $mail->SMTPKeepAlive = true;

    // Set message
    $mail->setFrom('cycloancldd@gmail.com', 'CYCLOAN Support');
    $mail->addAddress($to, $name);
    $mail->isHTML(true);
    $mail->Subject = 'TEST: CYCLOAN Email System - ' . date('Y-m-d H:i:s');
    $mail->Body = '<html><body>
    <h2>Email System Test</h2>
    <p>This is a test email from the CYCLOAN application.</p>
    <p><strong>Application ID:</strong> ' . $applicationId . '</p>
    <p><strong>Recipient:</strong> ' . htmlspecialchars($name) . '</p>
    <p><strong>Time Sent:</strong> ' . date('Y-m-d H:i:s') . '</p>
    <p>If you received this, the email system is working correctly!</p>
    </body></html>';
    $mail->AltBody = "Test email from CYCLOAN for application $applicationId sent at " . date('Y-m-d H:i:s');

    echo "[*] Sending email...\n";
    $sendResult = $mail->send();

    if ($sendResult) {
        echo "[SUCCESS] Email sent successfully to $to\n";
        echo "[*] Please check your inbox (or spam folder) to verify receipt\n";
    } else {
        echo "[FAILED] Email send failed\n";
        echo "Error: " . $mail->ErrorInfo . "\n";
    }

} catch (Exception $e) {
    echo "[EXCEPTION] " . $e->getMessage() . "\n";
}

echo "\n[*] Test complete\n";
echo "[*] Check the log file for SMTP debug details: $logFile\n";

// Also try to display the error log contents if possible
echo "\n[*] Log file contents:\n";
echo str_repeat("-", 60) . "\n";
if (file_exists($logFile)) {
    echo file_get_contents($logFile);
} else {
    echo "Log file not yet created\n";
}
echo str_repeat("-", 60) . "\n";

$conn->close();
?>