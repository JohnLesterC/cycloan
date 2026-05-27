<?php
session_start();
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'phpmailer/src/Exception.php';
require 'phpmailer/src/PHPMailer.php';
require 'phpmailer/src/SMTP.php';
require 'CYCLOAN_db.php';

// ========== RATE LIMITING FUNCTION ==========
function isRateLimited($email, $conn)
{
    $ip = $_SERVER['REMOTE_ADDR'];
    $timeLimit = date('Y-m-d H:i:s', strtotime('-1 hour'));

    $query = "SELECT COUNT(*) as attempts FROM password_reset_attempts 
              WHERE email = ? AND ip_address = ? AND attempt_time > ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("sss", $email, $ip, $timeLimit);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();

    return $row['attempts'] >= 5;
}

// ========== LOG RESET ATTEMPT ==========
function logResetAttempt($email, $conn, $success = true)
{
    $ip = $_SERVER['REMOTE_ADDR'];
    $query = "INSERT INTO password_reset_attempts (email, ip_address, attempt_time, success) 
              VALUES (?, ?, NOW(), ?)";
    $stmt = $conn->prepare($query);
    $success_flag = $success ? 1 : 0;
    $stmt->bind_param("ssi", $email, $ip, $success_flag);
    $stmt->execute();
    $stmt->close();
}

// ========== GENERATE SECURE TOKEN ==========
function generateResetToken()
{
    return bin2hex(random_bytes(32));
}

// ========== SEND PASSWORD RESET EMAIL WITH TOKEN ==========
function sendResetEmail($to, $userName, $resetToken, $resetLink)
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

        $mail->setFrom('cycloancldd@gmail.com', 'CYCLOAN Support Team');
        $mail->addAddress($to);

        $mail->isHTML(true);
        $mail->Subject = 'CYCLOAN - Password Reset Request';

        $emailBody = "
        <div style='font-family: Poppins, Arial; max-width: 600px; margin: 0 auto;'>
            <div style='background: linear-gradient(135deg, #1b5e20 0%, #2e7d32 100%); padding: 20px; border-radius: 10px 10px 0 0; text-align: center;'>
                <h2 style='color: white; margin: 0;'>Password Reset Request</h2>
            </div>
            <div style='background: #f9f9f9; padding: 30px; border-radius: 0 0 10px 10px;'>
                <p style='color: #333; font-size: 14px;'>Dear <strong>$userName</strong>,</p>
                
                <p style='color: #666; font-size: 14px; line-height: 1.6;'>
                    We received a request to reset your CYCLOAN account password. 
                    If you didn't make this request, you can safely ignore this email.
                </p>

                <div style='text-align: center; margin: 30px 0;'>
                    <a href='$resetLink' style='display: inline-block; background: linear-gradient(135deg, #1b5e20 0%, #2e7d32 100%); color: white; text-decoration: none; padding: 12px 30px; border-radius: 8px; font-weight: 600; font-size: 14px;'>
                        Reset Password
                    </a>
                </div>

                <p style='color: #666; font-size: 12px; line-height: 1.6;'>
                    Or copy and paste this link in your browser:<br>
                    <code style='background: #f0f0f0; padding: 8px 12px; border-radius: 4px; display: inline-block; margin-top: 10px; word-break: break-all;'>$resetLink</code>
                </p>

                <div style='background: #fffbea; border-left: 4px solid #ffc107; padding: 12px; margin-top: 20px; border-radius: 4px;'>
                    <p style='color: #856404; font-size: 12px; margin: 0;'>
                        <strong>⏱ This link expires in 24 hours.</strong> For security, you'll need to request a new reset link if this one expires.
                    </p>
                </div>

                <hr style='border: none; border-top: 1px solid #ddd; margin: 20px 0;'>

                <p style='color: #999; font-size: 12px; text-align: center; margin: 0;'>
                    © 2025 CYCLOAN. All rights reserved.<br>
                    <a href='landing_page.php' style='color: #1b5e20; text-decoration: none;'>Visit CYCLOAN</a>
                </p>
            </div>
        </div>
        ";

        $mail->Body = $emailBody;
        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("Failed to send reset email to $to. Mailer Error: {$mail->ErrorInfo}");
        return false;
    }
}

// ========== SEND OTP EMAIL ==========
function sendOtpEmail($to, $userName, $otp)
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

        $mail->setFrom('cycloancldd@gmail.com', 'CYCLOAN Support Team');
        $mail->addAddress($to);

        $mail->isHTML(true);
        $mail->Subject = 'CYCLOAN - Password Reset OTP Code';

        $emailBody = "
        <div style='font-family: Poppins, Arial; max-width: 600px; margin: 0 auto;'>
            <div style='background: linear-gradient(135deg, #1b5e20 0%, #2e7d32 100%); padding: 20px; border-radius: 10px 10px 0 0; text-align: center;'>
                <h2 style='color: white; margin: 0;'>Your Password Reset Code</h2>
            </div>
            <div style='background: #f9f9f9; padding: 30px; border-radius: 0 0 10px 10px;'>
                <p style='color: #333; font-size: 14px;'>Dear <strong>$userName</strong>,</p>
                
                <p style='color: #666; font-size: 14px; line-height: 1.6;'>
                    Use the code below to reset your CYCLOAN password. 
                    This code is valid for 15 minutes only.
                </p>

                <div style='text-align: center; margin: 30px 0;'>
                    <div style='background: white; border: 2px solid #1b5e20; padding: 20px; border-radius: 8px; font-size: 32px; font-weight: 700; color: #1b5e20; letter-spacing: 5px;'>
                        $otp
                    </div>
                </div>

                <div style='background: #fff3cd; border-left: 4px solid #ffc107; padding: 12px; margin-top: 20px; border-radius: 4px;'>
                    <p style='color: #856404; font-size: 12px; margin: 0;'>
                        <strong>⏱ This code expires in 15 minutes.</strong> Never share this code with anyone.
                    </p>
                </div>

                <hr style='border: none; border-top: 1px solid #ddd; margin: 20px 0;'>

                <p style='color: #999; font-size: 12px; text-align: center; margin: 0;'>
                    © 2025 CYCLOAN. All rights reserved.<br>
                    <a href='landing_page.php' style='color: #1b5e20; text-decoration: none;'>Visit CYCLOAN</a>
                </p>
            </div>
        </div>
        ";

        $mail->Body = $emailBody;
        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("Failed to send OTP email to $to. Mailer Error: {$mail->ErrorInfo}");
        return false;
    }
}

// ========== MAIN RESET PASSWORD HANDLER ==========
function handlePasswordReset()
{
    global $conn;

    if (!isset($_POST['email'])) {
        header("Location: forget_pass.php?message=" . urlencode('Invalid request') . "&type=error");
        exit();
    }

    $email = trim($_POST['email']);
    $recovery_method = $_POST['recovery_method'] ?? 'email';

    // Validate email
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        header("Location: forget_pass.php?message=" . urlencode('Please enter a valid email address') . "&type=error");
        exit();
    }

    // Check rate limiting
    if (isRateLimited($email, $conn)) {
        logResetAttempt($email, $conn, false);
        header("Location: forget_pass.php?message=" . urlencode('Too many password reset attempts. Please try again in 1 hour.') . "&type=error");
        exit();
    }

    // Find user in any table
    $userTable = null;
    $userId = null;
    $userRole = null;
    $userName = null;

    $tables = ['users1', 'admin1', 'admin2', 'superadmins'];
    foreach ($tables as $table) {
        $stmt = $conn->prepare("SELECT id, first_name, last_name FROM $table WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            $user = $result->fetch_assoc();
            $userTable = $table;
            $userId = $user['id'];
            $userName = trim($user['first_name'] . ' ' . $user['last_name']);
            $userRole = ($table === 'users1') ? 'user' : $table;
            $stmt->close();
            break;
        }
        $stmt->close();
    }

    // User not found
    if (!$userTable) {
        logResetAttempt($email, $conn, false);
        header("Location: forget_pass.php?message=" . urlencode('No account found with this email address.') . "&type=error");
        exit();
    }

    // Handle different recovery methods
    if ($recovery_method === 'otp') {
        // Generate OTP
        $otp = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $otpHash = password_hash($otp, PASSWORD_DEFAULT);
        $otpExpiry = date('Y-m-d H:i:s', strtotime('+15 minutes'));

        // Store OTP
        $stmt = $conn->prepare("INSERT INTO otps (user_id, email, otp, otp_type, expiry) VALUES (?, ?, ?, 'password_reset', ?)");
        $stmt->bind_param("isss", $userId, $email, $otpHash, $otpExpiry);
        $stmt->execute();
        $stmt->close();

        // Send OTP email
        if (sendOtpEmail($email, $userName, $otp)) {
            logResetAttempt($email, $conn, true);
            header("Location: forget_pass.php?message=" . urlencode('A password reset code has been sent to your email. Check your inbox for the 6-digit code.') . "&type=success");
            exit();
        } else {
            logResetAttempt($email, $conn, false);
            header("Location: forget_pass.php?message=" . urlencode('Failed to send OTP email. Please try again later.') . "&type=error");
            exit();
        }
    } else {
        // Generate reset token
        $resetToken = generateResetToken();
        $tokenHash = password_hash($resetToken, PASSWORD_DEFAULT);
        $tokenExpiry = date('Y-m-d H:i:s', strtotime('+24 hours'));

        // Store token
        $stmt = $conn->prepare("INSERT INTO password_reset_tokens (user_id, email, token, expiry, user_table) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("issss", $userId, $email, $tokenHash, $tokenExpiry, $userTable);
        $stmt->execute();
        $stmt->close();

        // Create reset link
        $resetLink = "reset_password.php?token=" . urlencode($resetToken) . "&email=" . urlencode($email);

        // Send reset email
        if (sendResetEmail($email, $userName, $resetToken, $resetLink)) {
            logResetAttempt($email, $conn, true);
            header("Location: forget_pass.php?message=" . urlencode('A password reset link has been sent to your email. Please check your inbox and click the link to reset your password.') . "&type=success");
            exit();
        } else {
            logResetAttempt($email, $conn, false);
            header("Location: forget_pass.php?message=" . urlencode('Failed to send reset email. Please try again later.') . "&type=error");
            exit();
        }
    }
}

// ========== MAIN EXECUTION ==========
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    handlePasswordReset();
} else {
    header("Location: forget_pass.php");
    exit();
}
?>