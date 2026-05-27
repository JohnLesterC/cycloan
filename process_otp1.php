<?php
require_once __DIR__ . '/env_config.php';
if (defined('APP_DEBUG') && APP_DEBUG) {
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', 0);
    ini_set('display_startup_errors', 0);
    error_reporting(0);
}
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'phpmailer/src/Exception.php';
require 'phpmailer/src/PHPMailer.php';
require 'phpmailer/src/SMTP.php';

require_once 'CYCLOAN_db.php';
// Ensure errors are logged to file regardless of display settings
ini_set('log_errors', '1');

// Check database connection
if ($conn->connect_error) {
    error_log("Database connection failed: " . $conn->connect_error . " | Timestamp: " . date('Y-m-d H:i:s'));
    $_SESSION['fresh_redirect'] = true;
    $_SESSION['error_message'] = "We're sorry, but we couldn't connect to our servers. Please try again later.";
    header("Location: verify_otp.php");
    exit();
}

// Welcome email function with improved template
function sendWelcomeEmail($to, $name)
{
    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com';
        $mail->SMTPAuth = true;
        $mail->Username = 'cycloancldd@gmail.com';
        $mail->Password = 'hbfh ukgh tmzw nqbq';
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = 587;

        $mail->setFrom('cycloancldd@gmail.com', 'CYCLOAN Support');
        $mail->addAddress($to);

        $mail->isHTML(true);
        $mail->Subject = '🎉 Welcome to CYCLOAN - Account Verified!';
        $mail->Body = '
            <!DOCTYPE html>
            <html lang="en">
            <head>
                <meta charset="UTF-8">
                <meta name="viewport" content="width=device-width, initial-scale=1.0">
                <style>
                    body { margin: 0; padding: 0; font-family: "Segoe UI", Tahoma, Geneva, Verdana, sans-serif; background-color: #f4f7f6; }
                    .container { max-width: 600px; margin: 0 auto; background-color: #ffffff; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1); }
                    .header { background: linear-gradient(135deg, #2e7d32, #1b5e20); padding: 40px 20px; text-align: center; }
                    .header-icon { font-size: 64px; margin-bottom: 10px; }
                    .header h1 { color: #ffffff; margin: 0; font-size: 28px; font-weight: 600; }
                    .content { padding: 40px 30px; color: #333333; }
                    .greeting { font-size: 20px; margin-bottom: 20px; color: #1b5e20; font-weight: 600; }
                    .message { font-size: 16px; line-height: 1.8; margin-bottom: 25px; color: #555; }
                    .success-box { background: linear-gradient(135deg, #e8f5e9, #f1f8e9); border-radius: 12px; padding: 25px; text-align: center; margin: 30px 0; }
                    .success-icon { font-size: 48px; margin-bottom: 15px; }
                    .button { display: inline-block; padding: 16px 36px; background: linear-gradient(135deg, #2e7d32, #1b5e20); color: #ffffff !important; text-decoration: none; border-radius: 8px; font-size: 16px; font-weight: 600; margin: 20px 0; box-shadow: 0 4px 12px rgba(27, 94, 32, 0.3); }
                    .features { margin: 30px 0; }
                    .feature-item { display: flex; align-items: start; margin-bottom: 20px; padding: 15px; background: #f8f9fa; border-radius: 8px; border-left: 4px solid #1b5e20; }
                    .feature-icon { font-size: 24px; margin-right: 15px; color: #1b5e20; flex-shrink: 0; }
                    .feature-text { flex: 1; }
                    .feature-title { font-weight: 600; color: #1b5e20; margin-bottom: 5px; }
                    .feature-desc { font-size: 14px; color: #666; margin: 0; }
                    .footer { background: #f4f7f6; padding: 25px 30px; text-align: center; font-size: 13px; color: #666; border-top: 1px solid #e0e0e0; }
                    .footer a { color: #1b5e20; text-decoration: none; font-weight: 500; }
                    .social-links { margin: 15px 0; }
                    .social-links a { display: inline-block; margin: 0 10px; color: #1b5e20; font-size: 20px; text-decoration: none; }
                    @media only screen and (max-width: 600px) {
                        .container { width: 100% !important; }
                        .content { padding: 30px 20px !important; }
                        .button { padding: 14px 28px !important; font-size: 15px !important; }
                    }
                </style>
            </head>
            <body>
                <div class="container">
                    <div class="header">
                        <div class="header-icon">🎉</div>
                        <h1>Welcome to CYCLOAN!</h1>
                    </div>
                    <div class="content">
                        <p class="greeting">Congratulations, ' . htmlspecialchars($name) . '!</p>
                        
                        <div class="success-box">
                            <div class="success-icon">✅</div>
                            <h2 style="color: #1b5e20; margin: 0 0 10px 0;">Account Verified Successfully!</h2>
                            <p style="margin: 0; color: #666;">Your email has been verified and your account is now active.</p>
                        </div>
                        
                        <p class="message">Thank you for joining the <strong>CYCLOAN - CLDD Loan Program</strong>. Were excited to have you as part of our community!</p>
                        
                        <div style="text-align: center;">
                            <a href="index.php" class="button">Login to Your Account</a>
                        </div>
                        
                        <div class="features">
                            <h3 style="color: #1b5e20; margin-bottom: 20px;">What You Can Do Now:</h3>
                            
                            <div class="feature-item">
                                <div class="feature-icon">📝</div>
                                <div class="feature-text">
                                    <div class="feature-title">Apply for Loans</div>
                                    <p class="feature-desc">Submit loan applications quickly and easily through our streamlined process.</p>
                                </div>
                            </div>
                            
                            <div class="feature-item">
                                <div class="feature-icon">📊</div>
                                <div class="feature-text">
                                    <div class="feature-title">Track Your Applications</div>
                                    <p class="feature-desc">Monitor the status of your loan applications in real-time.</p>
                                </div>
                            </div>
                            
                            <div class="feature-item">
                                <div class="feature-icon">💳</div>
                                <div class="feature-text">
                                    <div class="feature-title">Manage Payments</div>
                                    <p class="feature-desc">View payment schedules and track your loan repayment progress.</p>
                                </div>
                            </div>
                            
                            <div class="feature-item">
                                <div class="feature-icon">📞</div>
                                <div class="feature-text">
                                    <div class="feature-title">24/7 Support</div>
                                    <p class="feature-desc">Our support team is always here to help you with any questions.</p>
                                </div>
                            </div>
                        </div>
                        
                        <p class="message" style="margin-top: 30px;">If you have any questions or need assistance, please don\'t hesitate to reach out to our support team at <a href="mailto:support@cycloan-cldd.com" style="color: #1b5e20; font-weight: 600;">support@cycloan-cldd.com</a></p>
                    </div>
                    <div class="footer">
                        <p style="margin-bottom: 10px;"><strong>CYCLOAN - Community Youth Cooperative Loan</strong></p>
                        <p>CLDD Loan Program</p>
                        <p style="margin: 15px 0;"><a href="landing_page.php">Visit CYCLOAN</a></p>
                        <p style="color: #999; font-size: 12px; margin-top: 20px;">&copy; ' . date('Y') . ' CYCLOAN. All rights reserved.</p>
                    </div>
                </div>
            </body>
            </html>
        ';

        $mail->AltBody = "Hello $name,\n\nCongratulations! Your CYCLOAN account has been successfully verified.\n\nYou can now log in at http://localhost/CYCLOAN/index.php to:\n- Apply for loans\n- Track your applications\n- Manage payments\n- Access 24/7 support\n\nIf you have any questions, contact us at support@cycloan-cldd.com\n\nBest regards,\nThe CYCLOAN Team";

        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("Mailer Error for email $to: {$mail->ErrorInfo} | Timestamp: " . date('Y-m-d H:i:s') . " | Exception: " . $e->getMessage());
        return false;
    }
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Sanitize and validate OTP
    $otp = trim($_POST['otp'] ?? '');
    if (empty($otp) || !preg_match("/^\d{6}$/", $otp)) {
        $_SESSION['fresh_redirect'] = true;
        $_SESSION['error_message'] = "Please enter a valid 6-digit OTP.";
        header("Location: verify_otp.php");
        exit();
    }

    // Validate session email
    $email = isset($_SESSION['otp_email']) ? filter_var($_SESSION['otp_email'], FILTER_SANITIZE_EMAIL) : '';
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $_SESSION['fresh_redirect'] = true;
        $_SESSION['error_message'] = "Session expired or invalid email. Please register again.";
        header("Location: registration.php?step=1");
        exit();
    }

    // Initialize or increment OTP attempts
    if (!isset($_SESSION['otp_attempts'])) {
        $_SESSION['otp_attempts'] = 0;
    }
    $_SESSION['otp_attempts']++;

    // Check maximum OTP attempts (5 attempts)
    if ($_SESSION['otp_attempts'] > 5) {
        error_log("Too many OTP attempts for email $email | Timestamp: " . date('Y-m-d H:i:s'));
        $_SESSION['fresh_redirect'] = true;
        $_SESSION['error_message'] = "Too many incorrect attempts. Please request a new OTP code.";
        $_SESSION['otp_attempts'] = 0;
        header("Location: verify_otp.php");
        exit();
    }

    // Find user by email
    $user_query = "SELECT id, first_name, middle_name, last_name, name_extension FROM users1 WHERE email = ? AND is_active = 0";
    $stmt = $conn->prepare($user_query);
    if ($stmt === false) {
        error_log("Prepare failed for user query: " . $conn->error . " | Timestamp: " . date('Y-m-d H:i:s'));
        $_SESSION['fresh_redirect'] = true;
        $_SESSION['error_message'] = "An internal error occurred. Please try again later.";
        header("Location: verify_otp.php");
        exit();
    }
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        error_log("No pending user found for email $email | Timestamp: " . date('Y-m-d H:i:s'));
        $_SESSION['fresh_redirect'] = true;
        $_SESSION['error_message'] = "No pending registration found for this email or account already verified.";
        $stmt->close();
        header("Location: verify_otp.php");
        exit();
    }

    $user = $result->fetch_assoc();
    $user_id = $user['id'];
    $stmt->close();

    // Verify OTP
    $otp_query = "SELECT otp_code, expires_at FROM otps WHERE user_id = ? ORDER BY created_at DESC LIMIT 1";
    $stmt = $conn->prepare($otp_query);
    if ($stmt === false) {
        error_log("Prepare failed for OTP query: " . $conn->error . " | Timestamp: " . date('Y-m-d H:i:s'));
        $_SESSION['fresh_redirect'] = true;
        $_SESSION['error_message'] = "An internal error occurred. Please try again later.";
        header("Location: verify_otp.php");
        exit();
    }
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        error_log("No OTP found for user_id $user_id | Timestamp: " . date('Y-m-d H:i:s'));
        $_SESSION['fresh_redirect'] = true;
        $_SESSION['error_message'] = "No OTP found. Please request a new verification code.";
        $stmt->close();
        header("Location: verify_otp.php");
        exit();
    }

    $otp_data = $result->fetch_assoc();
    $stmt->close();

    // Check if OTP is expired
    $current_time = new DateTime();
    $expires_at = new DateTime($otp_data['expires_at']);
    if ($current_time > $expires_at) {
        error_log("OTP expired for user_id $user_id | Timestamp: " . date('Y-m-d H:i:s'));
        $_SESSION['fresh_redirect'] = true;
        $_SESSION['error_message'] = "OTP has expired. Please request a new verification code.";
        unset($_SESSION['otp_attempts']);
        header("Location: verify_otp.php");
        exit();
    }

    // Check if OTP matches
    if ($otp !== $otp_data['otp_code']) {
        $remaining_attempts = 5 - $_SESSION['otp_attempts'];
        error_log("Invalid OTP attempt for user_id $user_id | Attempt: {$_SESSION['otp_attempts']} | Timestamp: " . date('Y-m-d H:i:s'));
        $_SESSION['fresh_redirect'] = true;
        $_SESSION['error_message'] = "Invalid verification code. " . $remaining_attempts . " attempt" . ($remaining_attempts != 1 ? 's' : '') . " remaining.";
        header("Location: verify_otp.php");
        exit();
    }

    // Activate user account
    $update_query = "UPDATE users1 SET is_active = 1 WHERE id = ?";
    $stmt = $conn->prepare($update_query);
    if ($stmt === false) {
        error_log("Prepare failed for update query: " . $conn->error . " | Timestamp: " . date('Y-m-d H:i:s'));
        $_SESSION['fresh_redirect'] = true;
        $_SESSION['error_message'] = "An internal error occurred. Please try again later.";
        header("Location: verify_otp.php");
        exit();
    }
    $stmt->bind_param("i", $user_id);
    if (!$stmt->execute()) {
        error_log("Failed to activate user_id $user_id: " . $conn->error . " | Timestamp: " . date('Y-m-d H:i:s'));
        $_SESSION['fresh_redirect'] = true;
        $_SESSION['error_message'] = "Failed to activate account. Please try again.";
        $stmt->close();
        header("Location: verify_otp.php");
        exit();
    }
    $stmt->close();

    // Delete OTP after successful verification
    $delete_otp_query = "DELETE FROM otps WHERE user_id = ?";
    $stmt = $conn->prepare($delete_otp_query);
    if ($stmt === false) {
        error_log("Prepare failed for OTP deletion: " . $conn->error . " | Timestamp: " . date('Y-m-d H:i:s'));
    } else {
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $stmt->close();
    }

    // Send welcome email
    $full_name = trim("{$user['first_name']} " . ($user['middle_name'] ? "{$user['middle_name']} " : '') . "{$user['last_name']}" . ($user['name_extension'] ? " {$user['name_extension']}" : ''));
    sendWelcomeEmail($email, $full_name); // Don't block on email failure

    // Clear session
    unset($_SESSION['otp_email']);
    unset($_SESSION['otp_attempts']);
    unset($_SESSION['last_resend_time']);
    $_SESSION['fresh_redirect'] = true;
    $_SESSION['success_message'] = "🎉 Account verified successfully! You can now log in to your account.";

    $conn->close();
    header("Location: index.php");
    exit();
} else {
    $conn->close();
    $_SESSION['fresh_redirect'] = true;
    $_SESSION['error_message'] = "Invalid request method. Please submit the OTP form.";
    header("Location: verify_otp.php");
    exit();
}
?>