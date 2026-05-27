<?php
/**
 * TEST_EMAIL_DEBUG.PHP
 * This script tests the email sending functionality with detailed logging
 * 
 * Usage: Run from command line or browser to trigger a test email send
 * Check error_log (usually error_log.txt or system error_log) for debug messages
 */

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Include the main dashboard file to access functions
require_once 'admin2_dashboard.php';

// Test email parameters
$testApplicationId = 1; // Change this to a real application ID in your DB
$testEmail = 'test@example.com'; // Change this to a valid test email
$testName = 'Test User';

echo "=== EMAIL SEND TEST ===\n";
echo "Testing email sending functionality with detailed logging\n";
echo "Application ID: $testApplicationId\n";
echo "Test Email: $testEmail\n";
echo "\n";

// Simulate a consolidation update
$testUpdates = [
    'status' => 'Approved',
    'reason' => 'All documents verified and financial criteria met',
    'admin_name' => 'Test Admin',
    'admin_updated_at' => date('F j, Y \a\t g:i A'),
    'pre_approval_status' => 'Approved'
];

// Log start
error_log("TEST_EMAIL_START: Initiating test email send", E_USER_NOTICE);

// Try to send test email directly
echo "[*] Calling sendEmail directly...\n";
$testSubject = "TEST: Email System Debug - " . date('Y-m-d H:i:s');
$testBody = "<html><body><h2>Email Test</h2><p>This is a test email to verify the CYCLOAN email system is working correctly.</p><p>Timestamp: " . date('Y-m-d H:i:s') . "</p></body></html>";

$result = sendEmail($testEmail, $testName, $testSubject, $testBody, 'TEST_EMAIL_SYSTEM_DEBUG');

echo "[*] sendEmail returned: " . ($result ? "TRUE (Success)" : "FALSE (Failed)") . "\n";
echo "\n";

// Also test the consolidated email function if valid app ID exists
if (isset($conn)) {
    echo "[*] Calling sendConsolidatedUpdateEmail...\n";
    $consolResult = sendConsolidatedUpdateEmail($conn, $testApplicationId, $testUpdates);
    echo "[*] sendConsolidatedUpdateEmail returned: " . ($consolResult ? "TRUE (Success)" : "FALSE (Failed)") . "\n";
}

echo "\n[!] Check your error_log for detailed SMTP debug messages\n";
echo "[!] Email logs will contain prefixes like: EMAIL_*, SMTP_DEBUG_*, CONSOLIDATED_EMAIL_*\n";
?>