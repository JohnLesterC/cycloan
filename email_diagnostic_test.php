<?php
/**
 * Email Configuration Diagnostic Test
 * Tests all aspects of email sending for credit investigation
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'phpmailer/src/PHPMailer.php';
require_once 'phpmailer/src/SMTP.php';
require_once 'phpmailer/src/Exception.php';
require_once 'CYCLOAN_db.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

echo "🔍 Email Configuration Diagnostic Test\n";
echo "=====================================\n\n";

// Test 1: Check database connection
echo "Test 1: Database Connection\n";
if ($conn && !mysqli_connect_error()) {
    echo "✅ Database connection successful\n";

    // Check for test application
    $testApp = $conn->query("
        SELECT la.application_id, la.user_id, u.email, u.first_name, u.last_name
        FROM loan_applications la
        JOIN users1 u ON la.user_id = u.id
        ORDER BY la.created_at DESC
        LIMIT 1
    ")->fetch_assoc();

    if ($testApp) {
        echo "✅ Found test application:\n";
        echo "   - ID: " . $testApp['application_id'] . "\n";
        echo "   - Email: " . $testApp['email'] . "\n";
        echo "   - Name: " . $testApp['first_name'] . " " . $testApp['last_name'] . "\n";
    } else {
        echo "❌ No loan applications found in database\n";
    }
} else {
    echo "❌ Database connection failed: " . mysqli_connect_error() . "\n";
    exit;
}

echo "\n";

// Test 2: SMTP Connection
echo "Test 2: SMTP Connection to Gmail\n";
$mailer = new PHPMailer(true);
try {
    $mailer->isSMTP();
    $mailer->Host = 'smtp.gmail.com';
    $mailer->SMTPAuth = true;
    $mailer->Username = 'cycloancldd@gmail.com';
    $mailer->Password = 'hbfh ukgh tmzw nqbq';
    $mailer->SMTPSecure = 'tls';
    $mailer->Port = 587;

    // Try to connect without sending
    $mailer->smtpConnect();
    echo "✅ SMTP connection successful\n";
    echo "   - Server: " . $mailer->Host . "\n";
    echo "   - Port: " . $mailer->Port . "\n";
    echo "   - Security: TLS\n";
    $mailer->smtpClose();
} catch (Exception $e) {
    echo "❌ SMTP connection failed\n";
    echo "   Error: " . $e->getMessage() . "\n";
    echo "   PHPMailer Error: " . $mailer->ErrorInfo . "\n\n";
    echo "   This typically means:\n";
    echo "   1. Gmail app password has expired\n";
    echo "   2. Two-factor authentication not enabled\n";
    echo "   3. Network/firewall blocking SMTP\n";
}

echo "\n";

// Test 3: Send Test Email
if ($testApp) {
    echo "Test 3: Send Test Email\n";
    $testMailer = new PHPMailer(true);
    try {
        $testMailer->isSMTP();
        $testMailer->Host = 'smtp.gmail.com';
        $testMailer->SMTPAuth = true;
        $testMailer->Username = 'cycloancldd@gmail.com';
        $testMailer->Password = 'hbfh ukgh tmzw nqbq';
        $testMailer->SMTPSecure = 'tls';
        $testMailer->Port = 587;
        $testMailer->setFrom('cycloancldd@gmail.com', 'CYCLOAN Test');
        $testMailer->addAddress($testApp['email'], $testApp['first_name']);
        $testMailer->isHTML(true);
        $testMailer->Subject = '🧪 Email Configuration Test - CYCLOAN';
        $testMailer->Body = "
            <html>
            <head><style>
                body { font-family: Arial, sans-serif; color: #333; }
                .container { background: #f0f0f0; padding: 20px; border-radius: 8px; }
            </style></head>
            <body>
                <div class='container'>
                    <h2>✅ Email Configuration is Working!</h2>
                    <p>Dear " . $testApp['first_name'] . ",</p>
                    <p>This is a test email to verify that the CYCLOAN email system is configured correctly.</p>
                    <p><strong>Test Details:</strong></p>
                    <ul>
                        <li>Timestamp: " . date('F j, Y g:i A') . "</li>
                        <li>Application: " . $testApp['application_id'] . "</li>
                        <li>Email: " . $testApp['email'] . "</li>
                    </ul>
                    <p>If you received this email, it means credit investigation status emails will be sent successfully to applicants.</p>
                    <p>Best regards,<br/>CYCLOAN Team</p>
                </div>
            </body>
            </html>
        ";
        $testMailer->AltBody = "Test email - Email configuration is working";

        if ($testMailer->send()) {
            echo "✅ Test email sent successfully to: " . $testApp['email'] . "\n";
        }
    } catch (Exception $e) {
        echo "❌ Failed to send test email\n";
        echo "   Error: " . $e->getMessage() . "\n";
        echo "   PHPMailer Error: " . $testMailer->ErrorInfo . "\n";
    }
}

echo "\n";

// Test 4: Check Gmail App Password Status
echo "Test 4: Gmail App Password Status\n";
echo "Current Password: hbfh ukgh tmzw nqbq\n";
echo "⚠️  If tests fail, the app password may be expired.\n\n";
echo "To fix this:\n";
echo "1. Go to: https://myaccount.google.com/apppasswords\n";
echo "2. Sign in with: cycloancldd@gmail.com\n";
echo "3. Select 'Mail' and 'Windows Computer' (or appropriate)\n";
echo "4. Generate a new app password\n";
echo "5. Replace the password in admin1_dashboard.php (line 2378 and 2467)\n";
echo "6. Upload updated file to server\n";

echo "\n";

// Test 5: Check Email Fields in Database
echo "Test 5: Email Fields in Database\n";
$emailCheck = $conn->query("
    SELECT COUNT(*) as total, 
           SUM(CASE WHEN email IS NOT NULL AND email != '' THEN 1 ELSE 0 END) as with_email,
           SUM(CASE WHEN email IS NULL OR email = '' THEN 1 ELSE 0 END) as without_email
    FROM users1
    WHERE role = 'user'
")->fetch_assoc();

echo "User Accounts:\n";
echo "   - Total: " . $emailCheck['total'] . "\n";
echo "   - With email: " . $emailCheck['with_email'] . "\n";
echo "   - Without email: " . $emailCheck['without_email'] . "\n";

if ($emailCheck['without_email'] > 0) {
    echo "⚠️  Some users don't have email addresses. They won't receive emails.\n";
}

echo "\n=====================================\n";
echo "Diagnostic test complete!\n";
?>