<?php
session_start();

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'phpmailer/src/Exception.php';
require 'phpmailer/src/PHPMailer.php';
require 'phpmailer/src/SMTP.php';
require_once 'CYCLOAN_db.php';

header('Content-Type: application/json');

// Check if user has email session
if (!isset($_SESSION['otp_email'])) {
    echo json_encode(['success' => false, 'message' => 'No OTP session found. Please register first.']);
    exit();
}

$email = filter_var($_SESSION['otp_email'], FILTER_SANITIZE_EMAIL);

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['success' => false, 'message' => 'Invalid email address.']);
    exit();
}

// Check database connection
if ($conn->connect_error) {
    error_log("Database connection failed: " . $conn->connect_error);
    echo json_encode(['success' => false, 'message' => 'Database connection failed.']);
    exit();
}

// Rate limiting: Check last resend time
if (isset($_SESSION['last_resend_time'])) {
    $time_since_last_resend = time() - $_SESSION['last_resend_time'];
    if ($time_since_last_resend < 60) {
        echo json_encode([
            'success' => false,
            'message' => 'Please wait ' . (60 - $time_since_last_resend) . ' seconds before requesting another code.'
        ]);
        exit();
    }
}

// Find user by email
$user_query = "SELECT id, first_name FROM users1 WHERE email = ? AND is_active = 0";
$stmt = $conn->prepare($user_query);

if ($stmt === false) {
    error_log("Prepare failed for user query: " . $conn->error);
    echo json_encode(['success' => false, 'message' => 'Database error occurred.']);
    exit();
}

$stmt->bind_param("s", $email);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    error_log("No pending user found for email $email");
    echo json_encode(['success' => false, 'message' => 'No pending registration found for this email.']);
    $stmt->close();
    exit();
}

$user = $result->fetch_assoc();
$user_id = $user['id'];
$first_name = $user['first_name'];
$stmt->close();

// Delete old OTP
$delete_old_otp = "DELETE FROM otps WHERE user_id = ?";
$stmt = $conn->prepare($delete_old_otp);
if ($stmt) {
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $stmt->close();
}

// Generate new OTP using cryptographic randomness
function generateOTP()
{
    // Use random_bytes for cryptographic randomness (24 bits of entropy)
    $randomBytes = random_bytes(3);
    $randomInt = abs((int) bindec(implode('', array_map(
        fn($b) => sprintf('%08b', ord($b)),
        str_split($randomBytes)
    ))));
    return str_pad($randomInt % 1000000, 6, '0', STR_PAD_LEFT);
}

// Hash OTP using bcrypt (one-way encryption)
function hashOTP($otp)
{
    return password_hash($otp, PASSWORD_BCRYPT, ['cost' => 12]);
}

$new_otp = generateOTP();
$otp_hash = hashOTP($new_otp);
$created_at = date('Y-m-d H:i:s');
$expires_at = date('Y-m-d H:i:s', strtotime('+10 minutes'));

// Insert new OTP with hash (NEW SCHEMA)
$insert_otp = "INSERT INTO otps (user_id, otp_hash, created_at, expires_at, attempt_count) VALUES (?, ?, ?, ?, 0)";
$stmt = $conn->prepare($insert_otp);

if ($stmt === false) {
    error_log("Prepare failed for OTP insertion: " . $conn->error);
    echo json_encode(['success' => false, 'message' => 'Failed to generate new OTP.']);
    exit();
}

$stmt->bind_param("isss", $user_id, $otp_hash, $created_at, $expires_at);

if (!$stmt->execute()) {
    error_log("Failed to insert new OTP for user_id $user_id: " . $conn->error);
    echo json_encode(['success' => false, 'message' => 'Failed to generate new OTP.']);
    $stmt->close();
    exit();
}
$stmt->close();

// Send OTP email
if (sendOTPEmail($email, $first_name, $new_otp)) {
    $_SESSION['last_resend_time'] = time();
    $_SESSION['otp_attempts'] = 0; // Reset attempts on resend
    echo json_encode(['success' => true, 'message' => 'New verification code sent successfully!']);
} else {
    error_log("Failed to send OTP email to $email");
    echo json_encode(['success' => false, 'message' => 'Failed to send email. Please try again.']);
}

$conn->close();

// Function to send OTP email
function sendOTPEmail($to, $name, $otp)
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

        $mail->setFrom('cycloancldd@gmail.com', 'CYCLOAN Verification');
        $mail->addAddress($to);

        $mail->isHTML(true);
        $mail->Subject = 'Your CYCLOAN Verification Code';
        $mail->Body = '
            <!DOCTYPE html>
            <html lang="en">
            <head>
                <meta charset="UTF-8">
                <meta name="viewport" content="width=device-width, initial-scale=1.0">
                <style>
                    body { margin: 0; padding: 0; font-family: "Segoe UI", Tahoma, Geneva, Verdana, sans-serif; background-color: #f4f7f6; }
                    .container { max-width: 600px; margin: 0 auto; background-color: #ffffff; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1); }
                    .header { background: linear-gradient(135deg, #2e7d32, #1b5e20); padding: 30px 20px; text-align: center; }
                    .header h1 { color: #ffffff; margin: 0; font-size: 24px; font-weight: 600; }
                    .content { padding: 40px 30px; color: #333333; }
                    .greeting { font-size: 18px; margin-bottom: 20px; color: #1b5e20; font-weight: 600; }
                    .message { font-size: 16px; line-height: 1.6; margin-bottom: 30px; color: #555; }
                    .otp-box { background: linear-gradient(135deg, #e8f5e9, #f1f8e9); border: 2px dashed #1b5e20; border-radius: 12px; padding: 25px; text-align: center; margin: 30px 0; }
                    .otp-code { font-size: 36px; font-weight: 700; letter-spacing: 8px; color: #1b5e20; font-family: "Courier New", monospace; }
                    .otp-label { font-size: 14px; color: #666; margin-top: 10px; }
                    .warning { background: #fff3e0; border-left: 4px solid #f57c00; padding: 15px; margin: 20px 0; border-radius: 4px; }
                    .warning p { margin: 0; font-size: 14px; color: #e65100; }
                    .footer { background: #f4f7f6; padding: 20px 30px; text-align: center; font-size: 13px; color: #666; border-top: 1px solid #e0e0e0; }
                    .footer a { color: #1b5e20; text-decoration: none; font-weight: 500; }
                    .security-note { font-size: 12px; color: #999; margin-top: 15px; font-style: italic; }
                    @media only screen and (max-width: 600px) {
                        .container { width: 100% !important; }
                        .content { padding: 30px 20px !important; }
                        .otp-code { font-size: 28px !important; letter-spacing: 6px !important; }
                    }
                </style>
            </head>
            <body>
                <div class="container">
                    <div class="header">
                        <h1>🔐 Email Verification</h1>
                    </div>
                    <div class="content">
                        <p class="greeting">Hello ' . htmlspecialchars($name) . ',</p>
                        <p class="message">Thank you for registering with <strong>CYCLOAN</strong>! To complete your registration, please verify your email address using the code below:</p>
                        
                        <div class="otp-box">
                            <div class="otp-code">' . $otp . '</div>
                            <p class="otp-label">Your Verification Code</p>
                        </div>
                        
                        <p class="message">This code will expire in <strong>10 minutes</strong>. Please enter it on the verification page to activate your account.</p>
                        
                        <div class="warning">
                            <p><strong>⚠️ Security Notice:</strong> If you didn\'t request this code, please ignore this email. Do not share this code with anyone.</p>
                        </div>
                        
                        <p class="security-note">This is an automated message. Please do not reply to this email.</p>
                    </div>
                    <div class="footer">
                        <p>&copy; ' . date('Y') . ' CYCLOAN - CLDD Loan Program. All rights reserved.</p>
                        <p><a href="landing_page.php">Visit CYCLOAN</a></p>
                    </div>
                </div>
            </body>
            </html>
        ';

        $mail->AltBody = "Hello $name,\n\nYour CYCLOAN verification code is: $otp\n\nThis code will expire in 10 minutes.\n\nIf you didn't request this code, please ignore this email.\n\nBest regards,\nThe CYCLOAN Team";

        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("Mailer Error for email $to: {$mail->ErrorInfo} | Exception: " . $e->getMessage());
        return false;
    }
}
?>