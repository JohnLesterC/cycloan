<?php
/**
 * EMAIL DEBUG TEST - Diagnose Gmail SMTP Connection Issues
 * Run this in your browser: http://yoursite.com/email_debug_test.php
 * 
 * IMPORTANT: Delete this file after testing in production!
 */

require 'phpmailer/src/Exception.php';
require 'phpmailer/src/PHPMailer.php';
require 'phpmailer/src/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\SMTP;

?>
<!DOCTYPE html>
<html>

<head>
    <title>Email Debug Test</title>
    <style>
        body {
            font-family: Arial;
            margin: 20px;
        }

        .success {
            color: green;
            padding: 10px;
            background: #e6ffe6;
            border: 1px solid green;
            margin: 10px 0;
        }

        .error {
            color: red;
            padding: 10px;
            background: #ffe6e6;
            border: 1px solid red;
            margin: 10px 0;
        }

        .info {
            color: blue;
            padding: 10px;
            background: #e6f2ff;
            border: 1px solid blue;
            margin: 10px 0;
        }

        .warning {
            color: orange;
            padding: 10px;
            background: #fff9e6;
            border: 1px solid orange;
            margin: 10px 0;
        }

        .code {
            background: #f0f0f0;
            padding: 10px;
            border-left: 3px solid #666;
            margin: 10px 0;
            font-family: monospace;
        }

        h1 {
            color: #333;
        }

        h2 {
            color: #666;
            border-bottom: 2px solid #ddd;
            padding-bottom: 10px;
        }
    </style>
</head>

<body>
    <h1>📧 CYCLOAN Email Debug Test</h1>

    <h2>Step 1: Check Gmail Credentials</h2>
    <div class="warning">
        <strong>⚠️ CURRENT CREDENTIALS:</strong>
        <div class="code">
            Username: cycloancldd@gmail.com<br>
            Password: hbfh ukgh tmzw nqbq
        </div>
    </div>

    <h2>Step 2: Test SMTP Connection</h2>
    <?php
    $gmail_user = 'cycloancldd@gmail.com';
    $gmail_pass = 'hbfh ukgh tmzw nqbq';
    $gmail_host = 'smtp.gmail.com';
    $gmail_port = 587;

    $mailer = new PHPMailer(true);
    $mailer->SMTPDebug = SMTP::DEBUG_CONNECTION; // Enable verbose debug output
    $mailer->isSMTP();
    $mailer->Host = $gmail_host;
    $mailer->SMTPAuth = true;
    $mailer->Username = $gmail_user;
    $mailer->Password = $gmail_pass;
    $mailer->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mailer->Port = $gmail_port;

    // Capture debug output
    ob_start();

    try {
        // Just connect and authenticate, don't send
        $mailer->smtpConnect();
        ob_end_clean();
        echo '<div class="success">✅ <strong>SMTP Connection SUCCESSFUL!</strong></div>';
        echo '<div class="info">The Gmail server accepted your credentials. Emails should work!</div>';
        $mailer->smtpClose();
    } catch (Exception $e) {
        $debug_output = ob_get_clean();
        echo '<div class="error">❌ <strong>SMTP Connection FAILED!</strong></div>';
        echo '<div class="code">Error: ' . htmlspecialchars($e->getMessage()) . '</div>';
        echo '<div class="warning"><strong>Debug Output:</strong><br>';
        echo '<pre>' . htmlspecialchars($debug_output) . '</pre></div>';
    }
    ?>

    <h2>Step 3: Test Email Sending</h2>
    <?php
    $test_email = 'test@example.com'; // Replace with a test email
    
    if (isset($_POST['test_send'])) {
        $test_email = $_POST['test_email'] ?? $test_email;

        $mailer = new PHPMailer(true);
        try {
            $mailer->isSMTP();
            $mailer->Host = $gmail_host;
            $mailer->SMTPAuth = true;
            $mailer->Username = $gmail_user;
            $mailer->Password = $gmail_pass;
            $mailer->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mailer->Port = $gmail_port;

            $mailer->setFrom('cycloancldd@gmail.com', 'CYCLOAN Loan Support');
            $mailer->addAddress($test_email);
            $mailer->Subject = 'CYCLOAN - Email Test (' . date('Y-m-d H:i:s') . ')';
            $mailer->isHTML(true);
            $mailer->Body = '<h2>Email Test Successful!</h2><p>This is a test email from CYCLOAN system.</p>';
            $mailer->AltBody = 'Email Test Successful! This is a test email from CYCLOAN system.';

            if ($mailer->send()) {
                echo '<div class="success">✅ <strong>TEST EMAIL SENT SUCCESSFULLY!</strong></div>';
                echo '<div class="info">Email sent to: ' . htmlspecialchars($test_email) . '</div>';
            }
        } catch (Exception $e) {
            echo '<div class="error">❌ <strong>FAILED TO SEND TEST EMAIL!</strong></div>';
            echo '<div class="code">Error: ' . htmlspecialchars($e->getMessage()) . '<br>PHPMailer Info: ' . htmlspecialchars($mailer->ErrorInfo) . '</div>';
        }
    }
    ?>

    <form method="POST">
        <input type="email" name="test_email" placeholder="Enter test email address"
            value="<?php echo htmlspecialchars($test_email); ?>" required>
        <button type="submit" name="test_send">Send Test Email</button>
    </form>

    <h2>Step 4: What to Do Next</h2>
    <div class="info">
        <h3>If SMTP Connection Failed:</h3>
        <ol>
            <li><strong>Verify Gmail App Password:</strong>
                <ul>
                    <li>Go to: <code>https://myaccount.google.com/apppasswords</code></li>
                    <li>Select "Mail" and "Windows Computer"</li>
                    <li>Copy the 16-character password</li>
                    <li>Replace the password in the code above</li>
                </ul>
            </li>
            <li><strong>Ensure 2-Factor Authentication is enabled:</strong>
                <ul>
                    <li>Go to: <code>https://myaccount.google.com/security</code></li>
                    <li>Enable "2-Step Verification"</li>
                </ul>
            </li>
            <li><strong>Check if less secure apps is allowed:</strong>
                <ul>
                    <li>SMTP requires app passwords, not regular passwords</li>
                </ul>
            </li>
        </ol>
    </div>

    <div class="warning">
        <h3>🔒 SECURITY WARNING:</h3>
        <p><strong>DELETE THIS FILE AFTER TESTING!</strong> This debug script is for testing only and should not remain
            on production server.</p>
    </div>

    <hr>
    <p><small>Generated: <?php echo date('Y-m-d H:i:s'); ?></small></p>
</body>

</html>