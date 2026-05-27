<?php
session_start();
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'phpmailer/src/Exception.php';
require 'phpmailer/src/PHPMailer.php';
require 'phpmailer/src/SMTP.php';
require 'email_config.php';
require 'CYCLOAN_db.php';

function generateOTP()
{
    // Generate 6-digit OTP
    return str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
}

function sendOTPEmail($email, $otp, $conn)
{
    // Find user first to get ID
    $userTable = null;
    $userId = null;

    $tables = ['users1', 'admin1', 'admin2', 'superadmins'];
    foreach ($tables as $table) {
        $stmt = $conn->prepare("SELECT id FROM $table WHERE email = ?");
        if (!$stmt) {
            error_log("Prepare error for $table: " . $conn->error);
            continue;
        }
        $stmt->bind_param("s", $email);
        if (!$stmt->execute()) {
            error_log("Execute error for $table: " . $stmt->error);
            $stmt->close();
            continue;
        }
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            $user = $result->fetch_assoc();
            $userId = $user['id'];
            $userTable = $table;
            $stmt->close();
            break;
        }
        $stmt->close();
    }

    if (!$userId) {
        error_log("User not found for email: $email");
        return false;
    }

    $expireTime = date('Y-m-d H:i:s', strtotime('+15 minutes'));
    $otpHash = password_hash($otp, PASSWORD_DEFAULT); // Hash OTP for security

    // Check if OTP record exists for user
    $checkStmt = $conn->prepare("SELECT id FROM otps WHERE user_id = ?");
    if (!$checkStmt) {
        error_log("Prepare error checking OTP: " . $conn->error);
        return false;
    }
    $checkStmt->bind_param("i", $userId);
    $checkStmt->execute();
    $checkResult = $checkStmt->get_result();
    $checkStmt->close();

    if ($checkResult->num_rows > 0) {
        // Update existing OTP record
        $updateStmt = $conn->prepare("UPDATE otps SET otp_hash = ?, expires_at = ?, attempt_count = 0, verified_at = NULL, created_at = NOW() WHERE user_id = ?");
        if (!$updateStmt) {
            error_log("Prepare error for OTP update: " . $conn->error);
            return false;
        }
        $updateStmt->bind_param("ssi", $otpHash, $expireTime, $userId);
        if (!$updateStmt->execute()) {
            error_log("Failed to update OTP for user: $userId. Error: " . $updateStmt->error);
            $updateStmt->close();
            return false;
        }
        $updateStmt->close();
    } else {
        // Insert new OTP record
        $insertStmt = $conn->prepare("INSERT INTO otps (user_id, otp_hash, attempt_count, created_at, expires_at, verified_at) VALUES (?, ?, 0, NOW(), ?, NULL)");
        if (!$insertStmt) {
            error_log("Prepare error for OTP insert: " . $conn->error);
            return false;
        }
        $insertStmt->bind_param("iss", $userId, $otpHash, $expireTime);
        if (!$insertStmt->execute()) {
            error_log("Failed to store OTP for user: $userId. Error: " . $insertStmt->error);
            $insertStmt->close();
            return false;
        }
        $insertStmt->close();
    }

    // Send OTP via email
    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host = SMTP_HOST;
        $mail->SMTPAuth = true;
        $mail->Username = SMTP_USERNAME;
        $mail->Password = SMTP_PASSWORD;
        $mail->SMTPSecure = (SMTP_ENCRYPTION === 'ssl') ? PHPMailer::ENCRYPTION_SMTPS : PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = SMTP_PORT;
        $mail->Timeout = 30;
        $mail->SMTPDebug = 0; // Set to 2 for debugging

        $mail->setFrom(SMTP_FROM_EMAIL, SMTP_FROM_NAME);
        $mail->addAddress($email);

        $mail->isHTML(true);
        $mail->Subject = 'CYCLOAN - Your Password Reset OTP Code';

        $year = date('Y');
        $resetTime = date('F j, Y \a\t g:i A');

        $emailBody = "
            <div style='font-family: Poppins, Arial, sans-serif; max-width: 600px; margin: 0 auto;'>
                <!-- Header Section -->
                <div style='background: linear-gradient(135deg, #1b5e20 0%, #2e7d32 100%); border-radius: 8px 8px 0 0; padding: 30px 20px; text-align: center; color: white;'>
                    <h1 style='margin: 0; font-size: 28px; font-weight: 700;'>🔐 Password Reset OTP</h1>
                    <p style='margin: 10px 0 0 0; font-size: 16px; opacity: 0.9;'>Secure Verification Code for Your CYCLOAN Account</p>
                </div>

                <!-- Success Banner -->
                <div style='background: linear-gradient(135deg, #e8f5e9 0%, #f1f8e9 100%); border: 2px solid #2e7d32; border-top: none; padding: 20px; text-align: center;'>
                    <span style='background-color: #2e7d32; color: white; padding: 8px 20px; border-radius: 20px; font-size: 14px; font-weight: bold; display: inline-block;'>✅ CODE GENERATED</span>
                    <p style='color: #1b5e20; margin: 15px 0 0 0; font-size: 16px; font-weight: 600;'>Valid for 15 minutes</p>
                </div>

                <!-- Main Content -->
                <div style='background-color: #f9f9f9; padding: 30px 20px; border-bottom: 1px solid #e0e0e0;'>
                    <p style='margin: 0 0 20px 0; color: #333; font-size: 16px; line-height: 1.6;'>
                        Dear User,
                    </p>

                    <p style='margin: 0 0 20px 0; color: #666; font-size: 14px; line-height: 1.8;'>
                        Your password reset request has been received. Use the One-Time Password (OTP) code below to verify your identity and proceed with resetting your password. 
                        This code will expire in <strong>15 minutes</strong> for your security.
                    </p>

                    <!-- OTP Display Box -->
                    <div style='background-color: #e8f5e9; border: 3px solid #2e7d32; border-radius: 8px; padding: 30px; margin: 25px 0; text-align: center;'>
                        <p style='margin: 0 0 20px 0; color: #1b5e20; font-size: 14px; font-weight: 600; text-transform: uppercase; letter-spacing: 1px;'>
                            Your OTP Code
                        </p>
                        <div style='background: white; border-radius: 6px; padding: 20px; margin: 0;'>
                            <code style='font-size: 36px; font-weight: 900; color: #1b5e20; letter-spacing: 4px; font-family: monospace;'>{$otp}</code>
                        </div>
                        <p style='margin: 15px 0 0 0; color: #666; font-size: 12px;'>Copy this code to verify your identity</p>
                        <div style='margin-top: 15px; padding: 10px; background: #fff3cd; border-radius: 6px; color: #856404; font-size: 12px;'>
                            <strong>⏰ Expires at:</strong> {$expireTime}
                        </div>
                    </div>

                    <!-- Important Reminders -->
                    <div style='background: linear-gradient(135deg, #fff3e0 0%, #ffe0b2 100%); border-left: 5px solid #f57c00; border-radius: 8px; padding: 20px; margin: 25px 0;'>
                        <h4 style='color: #e65100; margin: 0 0 15px 0; font-size: 16px; font-weight: 600;'>
                            <span style='font-size: 20px;'>⚠️</span> Important Security Reminders
                        </h4>
                        <table style='width: 100%;'>
                            <tr>
                                <td style='padding: 8px 0; color: #e65100; width: 25px; vertical-align: top;'>🔐</td>
                                <td style='padding: 8px 0 8px 10px; color: #e65100; font-size: 13px;'><strong>Never share this code</strong> - CYCLOAN staff will never ask for your OTP</td>
                            </tr>
                            <tr>
                                <td style='padding: 8px 0; color: #e65100; width: 25px; vertical-align: top;'>⏰</td>
                                <td style='padding: 8px 0 8px 10px; color: #e65100; font-size: 13px;'><strong>Valid for 15 minutes only</strong> - Generate a new code if this one expires</td>
                            </tr>
                            <tr>
                                <td style='padding: 8px 0; color: #e65100; width: 25px; vertical-align: top;'>✋</td>
                                <td style='padding: 8px 0 8px 10px; color: #e65100; font-size: 13px;'><strong>If you didn't request this</strong> - Your account may be at risk. Contact support immediately</td>
                            </tr>
                        </table>
                    </div>

                    <!-- Next Steps -->
                    <div style='background-color: #e3f2fd; border-left: 5px solid #1976d2; border-radius: 8px; padding: 20px; margin: 25px 0;'>
                        <h4 style='color: #0d47a1; margin: 0 0 15px 0; font-size: 16px; font-weight: 600;'>
                            <span style='font-size: 20px;'>📋</span> Next Steps
                        </h4>
                        <ol style='margin: 0; padding-left: 20px; color: #1565c0;'>
                            <li style='padding: 8px 0; font-size: 13px;'><strong>Copy this OTP code</strong> - You'll need it in the next step</li>
                            <li style='padding: 8px 0; font-size: 13px;'><strong>Enter the code</strong> in the verification window (valid for 15 minutes)</li>
                            <li style='padding: 8px 0; font-size: 13px;'><strong>Set a new strong password</strong> - Mix letters, numbers & special characters</li>
                            <li style='padding: 8px 0; font-size: 13px;'><strong>Log in with your new password</strong> - Your account will be secured</li>
                        </ol>
                    </div>

                    <!-- Call to Action -->
                    <div style='text-align: center; margin: 30px 0;'>
                        <a href='verify_otp_reset.php' style='display: inline-block; background: linear-gradient(135deg, #1b5e20 0%, #2e7d32 100%); color: white; padding: 15px 40px; text-decoration: none; border-radius: 30px; font-weight: 600; font-size: 16px; box-shadow: 0 4px 6px rgba(0,0,0,0.2); transition: all 0.3s ease;'>
                            ✅ Verify OTP Code
                        </a>
                    </div>

                    <!-- Support Section -->
                    <div style='background-color: #f0f0f0; border-radius: 8px; padding: 20px; margin: 25px 0; text-align: center;'>
                        <p style='margin: 0 0 15px 0; color: #333; font-size: 14px; font-weight: 600;'>
                            <span style='font-size: 18px;'>❓</span> Need Help?
                        </p>
                        <p style='margin: 0 0 10px 0; color: #666; font-size: 13px;'>
                            If you didn't request a password reset or didn't receive an OTP, please contact our support team.
                        </p>
                        <p style='margin: 0; color: #1b5e20;'>
                            <strong>📧 Email:</strong> cycloancldd@gmail.com | <strong>📞 Phone:</strong> 0981-303-8698 | <strong>📞 Landline:</strong> 545-6789 loc 8018-19
                        </p>
                    </div>

                    <!-- Security Alert -->
                    <div style='background: linear-gradient(135deg, #ffebee 0%, #ffcdd2 100%); border-left: 5px solid #c62828; border-radius: 8px; padding: 15px; margin: 25px 0;'>
                        <p style='margin: 0; color: #b71c1c; font-size: 13px; line-height: 1.6;'>
                            <strong>⚠️ Security Alert:</strong> If you did not request a password reset, <strong>do not share this code with anyone</strong>. 
                            Your account may be under unauthorized access. Contact support immediately.
                        </p>
                    </div>
                </div>

                <!-- Footer -->
                <div style='background-color: #1b5e20; color: white; padding: 25px 20px; text-align: center; border-radius: 0 0 8px 8px;'>
                    <table style='width: 100%; margin-bottom: 20px;'>
                        <tr>
                            <td style='padding: 0 10px; border-right: 1px solid rgba(255,255,255,0.3);'>
                                <a href='landing_page.php' style='color: white; text-decoration: none; font-size: 13px;'>Visit Website</a>
                            </td>
                            <td style='padding: 0 10px; border-right: 1px solid rgba(255,255,255,0.3);'>
                                <a href='#' style='color: white; text-decoration: none; font-size: 13px;'>FAQs</a>
                            </td>
                            <td style='padding: 0 10px;'>
                                <a href='#' style='color: white; text-decoration: none; font-size: 13px;'>Contact Us</a>
                            </td>
                        </tr>
                    </table>
                    <p style='margin: 0; color: rgba(255,255,255,0.8); font-size: 12px; line-height: 1.8;'>
                        © {$year} CYCLOAN. All rights reserved. This is an automated message, please do not reply.<br>
                        <strong>CYCLOAN Support Team</strong> | Cooperative Credit Loan Digital Access Network
                    </p>
                    <p style='margin: 15px 0 0 0; color: rgba(255,255,255,0.6); font-size: 11px;'>
                        This email was sent to <strong>{$email}</strong> from CYCLOAN. If you believe this was sent in error, please contact support.
                    </p>
                </div>
            </div>
        ";

        $mail->Body = $emailBody;

        // Attempt to send email
        if (!$mail->send()) {
            error_log("PHPMailer Error - Email send failed for $email: " . $mail->ErrorInfo);

            // Fallback: Try using PHP's mail() function
            error_log("Attempting fallback mail() function for $email");
            $headers = "MIME-Version: 1.0\r\n";
            $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
            $headers .= "From: " . SMTP_FROM_EMAIL . " <" . SMTP_FROM_NAME . ">\r\n";
            $headers .= "Reply-To: " . SUPPORT_EMAIL . "\r\n";

            if (mail($email, 'CYCLOAN - Your Password Reset OTP Code', $emailBody, $headers)) {
                error_log("Fallback mail() succeeded for $email");
                return true;
            } else {
                error_log("Fallback mail() also failed for $email");
                return false;
            }
        }

        error_log("OTP email successfully sent to $email via PHPMailer");
        return true;
    } catch (Exception $e) {
        error_log("PHPMailer Exception for $email - Message: " . $e->getMessage());
        error_log("PHPMailer ErrorInfo: " . (isset($mail->ErrorInfo) ? $mail->ErrorInfo : 'N/A'));

        // Fallback: Try using PHP's mail() function
        error_log("Attempting fallback mail() function after exception for $email");
        $headers = "MIME-Version: 1.0\r\n";
        $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
        $headers .= "From: " . SMTP_FROM_EMAIL . " <" . SMTP_FROM_NAME . ">\r\n";
        $headers .= "Reply-To: " . SUPPORT_EMAIL . "\r\n";

        if (mail($email, 'CYCLOAN - Your Password Reset OTP Code', $emailBody, $headers)) {
            error_log("Fallback mail() succeeded for $email after exception");
            return true;
        } else {
            error_log("Fallback mail() failed for $email");
            return false;
        }
    }
}

function initiatePasswordReset($email, $conn)
{
    // Database connection validation
    if (!$conn || $conn->connect_error) {
        error_log("Database connection error in initiatePasswordReset");
        header("Location: forget_pass.php?message=" . urlencode('Database connection failed. Please try again later.') . "&type=error");
        exit();
    }

    $email = trim($email);

    // Check email existence in all tables
    $userTable = null;
    $userId = null;
    $userRole = null;

    $tables = ['users1', 'admin1', 'admin2', 'superadmins'];
    foreach ($tables as $table) {
        $stmt = $conn->prepare("SELECT id FROM $table WHERE email = ?");
        if (!$stmt) {
            error_log("Prepare error for table $table: " . $conn->error);
            continue;
        }
        $stmt->bind_param("s", $email);
        if (!$stmt->execute()) {
            error_log("Execute error for table $table: " . $stmt->error);
            $stmt->close();
            continue;
        }
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            $user = $result->fetch_assoc();
            $userTable = $table;
            $userId = $user['id'];
            $userRole = ($table === 'users1') ? 'user' : $table;
            $stmt->close();
            break;
        }
        $stmt->close();
    }

    // If email not found
    if (!$userTable) {
        header("Location: forget_pass.php?message=" . urlencode('The email address you entered does not exist in our records.') . "&type=error");
        exit();
    }

    // Log the password reset request
    $logStmt = $conn->prepare("INSERT INTO activity_logs (user_id, user_role, admin_name, admin_email, action_type, module, description, affected_id) VALUES (?, ?, 'System', 'system@cycloan.com', 'password_reset_requested', 'authentication', 'Password reset requested via OTP', ?)");
    if ($logStmt) {
        $logStmt->bind_param("iss", $userId, $userRole, $userId);
        $logStmt->execute();
        $logStmt->close();
    }

    // Generate and send OTP
    $otp = generateOTP();
    if (!sendOTPEmail($email, $otp, $conn)) {
        error_log("Failed to send OTP email to $email");
        header("Location: forget_pass.php?message=" . urlencode('Failed to send OTP. Please try again later.') . "&type=error");
        exit();
    }

    // Set session variables for multi-step workflow
    $_SESSION['reset_email'] = $email;
    $_SESSION['reset_otp_id'] = $userId;
    $_SESSION['otp_verified'] = false;

    // Redirect to OTP verification page
    header("Location: verify_otp_reset.php");
    exit();
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    try {
        if (!isset($_POST['email']) || empty($_POST['email'])) {
            header("Location: forget_pass.php?message=" . urlencode('Please enter an email address.') . "&type=error");
            exit();
        }

        $email = trim($_POST['email']);

        // Validate email format
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            header("Location: forget_pass.php?message=" . urlencode('Please enter a valid email address.') . "&type=error");
            exit();
        }

        // Initiate the OTP-based password reset workflow
        initiatePasswordReset($email, $conn);
    } catch (Exception $e) {
        error_log("Error in submit_forget_pass.php: " . $e->getMessage());
        header("Location: forget_pass.php?message=" . urlencode('An error occurred. Please try again later.') . "&type=error");
        exit();
    }
}
?>