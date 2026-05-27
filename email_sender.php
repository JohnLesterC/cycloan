<?php
/**
 * CYCLOAN Email Sender
 * Simplified email sending with PHPMailer
 */

require_once 'phpmailer/src/Exception.php';
require_once 'phpmailer/src/PHPMailer.php';
require_once 'phpmailer/src/SMTP.php';
require_once 'email_config.php';
require_once 'email_templates.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

class EmailSender
{

    /**
     * Configure PHPMailer with default settings
     */
    private static function configurePHPMailer()
    {
        $mail = new PHPMailer(true);

        try {
            // Server settings
            $mail->isSMTP();
            $mail->Host = SMTP_HOST;
            $mail->SMTPAuth = true;
            $mail->Username = SMTP_USERNAME;
            $mail->Password = SMTP_PASSWORD;
            $mail->SMTPSecure = SMTP_ENCRYPTION;
            $mail->Port = SMTP_PORT;

            // Sender info
            $mail->setFrom(SMTP_FROM_EMAIL, SMTP_FROM_NAME);
            $mail->CharSet = 'UTF-8';
            $mail->isHTML(true);

            // Optional: Enable debugging (set to 0 for production)
            // $mail->SMTPDebug = 2;

            return $mail;
        } catch (Exception $e) {
            error_log("Email configuration error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Send welcome email to new users
     */
    public static function sendWelcomeEmail($toEmail, $userName)
    {
        try {
            $mail = self::configurePHPMailer();
            if (!$mail)
                return false;

            $mail->addAddress($toEmail, $userName);
            $mail->Subject = 'Welcome to CYCLOAN Cooperative! 🎉';
            $mail->Body = EmailTemplate::welcome($userName, $toEmail);
            $mail->AltBody = "Welcome to CYCLOAN! Your account has been verified and activated. Log in at http://localhost/CYCLOAN";

            $result = $mail->send();
            error_log("Welcome email sent to {$toEmail}: " . ($result ? 'SUCCESS' : 'FAILED'));
            return $result;
        } catch (Exception $e) {
            error_log("Welcome email error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Send loan status update email
     */
    public static function sendLoanStatusEmail($toEmail, $userName, $loanId, $status, $statusMessage, $loanAmount = null)
    {
        try {
            $mail = self::configurePHPMailer();
            if (!$mail)
                return false;

            $mail->addAddress($toEmail, $userName);
            $mail->Subject = "Loan Application Update - #{$loanId} - " . strtoupper($status);
            $mail->Body = EmailTemplate::loanStatusUpdate($userName, $loanId, $status, $statusMessage, $loanAmount);
            $mail->AltBody = "Your loan application #{$loanId} status has been updated to: {$status}. {$statusMessage}";

            $result = $mail->send();
            error_log("Loan status email sent to {$toEmail} for loan #{$loanId}: " . ($result ? 'SUCCESS' : 'FAILED'));
            return $result;
        } catch (Exception $e) {
            error_log("Loan status email error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Send payment confirmation email
     */
    public static function sendPaymentConfirmation($toEmail, $userName, $loanId, $amount, $paymentDate, $remainingBalance, $invoiceNumber = null)
    {
        try {
            $mail = self::configurePHPMailer();
            if (!$mail)
                return false;

            $mail->addAddress($toEmail, $userName);
            $mail->Subject = "Payment Received - ₱" . number_format($amount, 2);
            $mail->Body = EmailTemplate::paymentConfirmation($userName, $loanId, number_format($amount, 2), $paymentDate, number_format($remainingBalance, 2), $invoiceNumber);
            $mail->AltBody = "Payment received! Amount: ₱{$amount}. Remaining balance: ₱{$remainingBalance}. Thank you!";

            $result = $mail->send();
            error_log("Payment confirmation sent to {$toEmail} for loan #{$loanId}: " . ($result ? 'SUCCESS' : 'FAILED'));
            return $result;
        } catch (Exception $e) {
            error_log("Payment confirmation email error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Send payment reminder email
     */
    public static function sendPaymentReminder($toEmail, $userName, $loanId, $amountDue, $dueDate, $daysUntilDue)
    {
        try {
            $mail = self::configurePHPMailer();
            if (!$mail)
                return false;

            $mail->addAddress($toEmail, $userName);
            $mail->Subject = "Payment Reminder - ₱" . number_format($amountDue, 2) . " due on {$dueDate}";
            $mail->Body = EmailTemplate::paymentReminder($userName, $loanId, number_format($amountDue, 2), $dueDate, $daysUntilDue);
            $mail->AltBody = "Payment reminder: ₱{$amountDue} due on {$dueDate} for loan #{$loanId}. {$daysUntilDue} days remaining.";

            $result = $mail->send();
            error_log("Payment reminder sent to {$toEmail} for loan #{$loanId}: " . ($result ? 'SUCCESS' : 'FAILED'));
            return $result;
        } catch (Exception $e) {
            error_log("Payment reminder email error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Send OTP verification email
     */
    public static function sendOTPEmail($toEmail, $userName, $otp, $expiryMinutes = 10)
    {
        try {
            $mail = self::configurePHPMailer();
            if (!$mail)
                return false;

            $mail->addAddress($toEmail, $userName);
            $mail->Subject = "Your CYCLOAN Verification Code: {$otp}";
            $mail->Body = EmailTemplate::otpVerification($userName, $otp, $expiryMinutes);
            $mail->AltBody = "Your CYCLOAN verification code is: {$otp}. This code will expire in {$expiryMinutes} minutes.";

            $result = $mail->send();
            error_log("OTP email sent to {$toEmail}: " . ($result ? 'SUCCESS' : 'FAILED'));
            return $result;
        } catch (Exception $e) {
            error_log("OTP email error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Send loan application confirmation email
     */
    public static function sendLoanApplicationSubmitted($toEmail, $userName, $loanId, $applicationId, $loanType, $amount, $term)
    {
        try {
            $mail = self::configurePHPMailer();
            if (!$mail)
                return false;

            $mail->addAddress($toEmail, $userName);
            $mail->Subject = "Loan Application Submitted - #{$loanId}";
            $mail->Body = EmailTemplate::loanApplicationSubmitted($userName, $loanId, $applicationId, $loanType, $amount, $term);
            $mail->AltBody = "Your loan application #{$loanId} has been submitted successfully! Application ID: {$applicationId}. We will review your application and contact you within 3-5 business days.";

            $result = $mail->send();
            error_log("Loan application confirmation sent to {$toEmail} for loan #{$loanId}: " . ($result ? 'SUCCESS' : 'FAILED'));
            return $result;
        } catch (Exception $e) {
            error_log("Loan application email error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Send custom email
     */
    public static function sendCustomEmail($recipientEmail, $recipientName, $subject, $htmlBody, $altBody = '')
    {
        try {
            $mail = self::configurePHPMailer();
            if (!$mail)
                return false;

            $mail->addAddress($recipientEmail, $recipientName);
            $mail->Subject = $subject;
            $mail->Body = $htmlBody;
            $mail->AltBody = $altBody ?: strip_tags($htmlBody);

            $result = $mail->send();
            error_log("Custom email sent to {$recipientEmail}: " . ($result ? 'SUCCESS' : 'FAILED'));
            return $result;
        } catch (Exception $e) {
            error_log("Custom email error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Send Account Activation Confirmation Email
     * Sent after successful OTP verification to confirm account activation
     */
    public static function sendAccountActivationConfirmation($toEmail, $userName, $activationDate = null, $registrationDetails = [])
    {
        try {
            $mail = self::configurePHPMailer();
            if (!$mail)
                return false;

            $mail->addAddress($toEmail, $userName);
            $mail->Subject = "Your CYCLOAN Account Has Been Activated! 🎉";
            $mail->Body = EmailTemplate::accountActivationConfirmation($userName, $toEmail, $activationDate, $registrationDetails);
            $mail->AltBody = "Your CYCLOAN account has been successfully activated! You can now log in and start using our services. Visit http://localhost/CYCLOAN to access your account.";

            $result = $mail->send();
            error_log("Account activation confirmation sent to {$toEmail}: " . ($result ? 'SUCCESS' : 'FAILED'));
            return $result;
        } catch (Exception $e) {
            error_log("Account activation confirmation email error: " . $e->getMessage());
            return false;
        }
    }
}
?>