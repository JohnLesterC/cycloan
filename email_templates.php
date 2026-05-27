<?php
/**
 * CYCLOAN Email Template System
 * Professional email templates with consistent branding
 */

require_once 'email_config.php';

class EmailTemplate
{

    /**
     * Generate base email HTML structure
     */
    private static function getBaseTemplate($content, $preheader = '')
    {
        $primaryColor = PRIMARY_COLOR;
        $secondaryColor = SECONDARY_COLOR;
        $accentColor = ACCENT_COLOR;
        $bgColor = BACKGROUND_COLOR;
        $textColor = TEXT_COLOR;
        $lightTextColor = LIGHT_TEXT_COLOR;
        $companyName = COMPANY_NAME;
        $companyTagline = COMPANY_TAGLINE;
        $companyWebsite = COMPANY_WEBSITE;
        $supportEmail = SUPPORT_EMAIL;
        $currentYear = date('Y');

        return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <title>{$companyName}</title>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap');
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Arial, sans-serif;
            line-height: 1.6;
            color: {$textColor};
            background-color: #f3f4f6;
        }
        
        .email-wrapper {
            max-width: 600px;
            margin: 0 auto;
            background-color: #ffffff;
        }
        
        .email-header {
            background: linear-gradient(135deg, {$primaryColor} 0%, {$secondaryColor} 100%);
            padding: 40px 30px;
            text-align: center;
        }
        
        .email-header h1 {
            color: #ffffff;
            font-size: 28px;
            font-weight: 700;
            margin: 0;
            text-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }
        
        .email-header p {
            color: rgba(255, 255, 255, 0.9);
            font-size: 14px;
            margin-top: 8px;
        }
        
        .email-body {
            padding: 40px 30px;
            background-color: #ffffff;
        }
        
        .email-footer {
            background-color: {$bgColor};
            padding: 30px;
            text-align: center;
            border-top: 3px solid {$accentColor};
        }
        
        .email-footer p {
            color: {$lightTextColor};
            font-size: 13px;
            margin: 8px 0;
        }
        
        .button {
            display: inline-block;
            padding: 14px 32px;
            background: linear-gradient(135deg, {$primaryColor} 0%, {$secondaryColor} 100%);
            color: #ffffff !important;
            text-decoration: none;
            border-radius: 8px;
            font-weight: 600;
            font-size: 15px;
            margin: 20px 0;
            box-shadow: 0 4px 12px rgba(27, 94, 32, 0.3);
            transition: all 0.3s ease;
        }
        
        .button:hover {
            box-shadow: 0 6px 16px rgba(27, 94, 32, 0.4);
            transform: translateY(-2px);
        }
        
        .info-card {
            background: linear-gradient(135deg, {$bgColor} 0%, #dcfce7 100%);
            border-left: 4px solid {$accentColor};
            padding: 20px;
            border-radius: 8px;
            margin: 20px 0;
        }
        
        .info-card h3 {
            color: {$primaryColor};
            font-size: 16px;
            margin-bottom: 12px;
            font-weight: 700;
        }
        
        .info-row {
            display: flex;
            justify-content: space-between;
            padding: 10px 0;
            border-bottom: 1px solid rgba(27, 94, 32, 0.1);
        }
        
        .info-row:last-child {
            border-bottom: none;
        }
        
        .info-label {
            font-weight: 600;
            color: {$textColor};
            font-size: 14px;
        }
        
        .info-value {
            color: {$primaryColor};
            font-weight: 600;
            font-size: 14px;
        }
        
        .alert {
            padding: 16px 20px;
            border-radius: 8px;
            margin: 20px 0;
            font-size: 14px;
        }
        
        .alert-success {
            background-color: #f0fdf4;
            border-left: 4px solid #22c55e;
            color: #166534;
        }
        
        .alert-info {
            background-color: #eff6ff;
            border-left: 4px solid #3b82f6;
            color: #1e40af;
        }
        
        .alert-warning {
            background-color: #fffbeb;
            border-left: 4px solid #f59e0b;
            color: #92400e;
        }
        
        .divider {
            height: 1px;
            background: linear-gradient(90deg, transparent, {$accentColor}, transparent);
            margin: 30px 0;
        }
        
        .social-links {
            margin-top: 20px;
        }
        
        .social-links a {
            display: inline-block;
            margin: 0 8px;
            color: {$primaryColor};
            text-decoration: none;
            font-size: 13px;
        }
        
        @media only screen and (max-width: 600px) {
            .email-header {
                padding: 30px 20px;
            }
            
            .email-header h1 {
                font-size: 24px;
            }
            
            .email-body {
                padding: 30px 20px;
            }
            
            .email-footer {
                padding: 20px;
            }
            
            .button {
                display: block;
                padding: 12px 24px;
                font-size: 14px;
            }
            
            .info-row {
                flex-direction: column;
                gap: 4px;
            }
        }
    </style>
    <!--[if mso]>
    <style type="text/css">
        body, table, td {font-family: Arial, sans-serif !important;}
    </style>
    <![endif]-->
</head>
<body>
    <div style="display: none; max-height: 0px; overflow: hidden;">
        {$preheader}
    </div>
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="background-color: #f3f4f6; padding: 20px 0;">
        <tr>
            <td align="center">
                <div class="email-wrapper">
                    <div class="email-header">
                        <h1>{$companyName}</h1>
                        <p>{$companyTagline}</p>
                    </div>
                    <div class="email-body">
                        {$content}
                    </div>
                    <div class="email-footer">
                        <p><strong>{$companyName}</strong></p>
                        <p style="margin-top: 12px;">
                            <a href="mailto:{$supportEmail}" style="color: {$primaryColor}; text-decoration: none;">{$supportEmail}</a>
                        </p>
                        <p style="margin-top: 4px;">
                            <a href="{$companyWebsite}" style="color: {$primaryColor}; text-decoration: none;">{$companyWebsite}</a>
                        </p>
                        <div class="divider"></div>
                        <p style="font-size: 12px; color: {$lightTextColor};">
                            © {$currentYear} {$companyName}. All rights reserved.
                        </p>
                        <p style="font-size: 11px; color: {$lightTextColor}; margin-top: 8px;">
                            This email was sent to you because you are a valued member of our cooperative.
                        </p>
                    </div>
                </div>
            </td>
        </tr>
    </table>
</body>
</html>
HTML;
    }

    /**
     * Welcome Email Template
     */
    public static function welcome($userName, $email)
    {
        $content = <<<HTML
<h2 style="color: #1b5e20; margin-bottom: 16px;">Welcome to CYCLOAN! 🎉</h2>

<p style="font-size: 15px; line-height: 1.8; color: #374151; margin-bottom: 20px;">
    Dear <strong>{$userName}</strong>,
</p>

<p style="font-size: 15px; line-height: 1.8; color: #374151; margin-bottom: 20px;">
    Congratulations! Your account has been successfully verified and activated. We're thrilled to have you as a member of the CYCLOAN Cooperative family.
</p>

<div class="info-card">
    <h3>Your Account Details</h3>
    <div class="info-row">
        <span class="info-label">Email Address:</span>
        <span class="info-value">{$email}</span>
    </div>
    <div class="info-row">
        <span class="info-label">Status:</span>
        <span class="info-value">✓ Verified & Active</span>
    </div>
</div>

<div class="alert alert-success">
    <strong>✓ What's Next?</strong><br>
    You can now log in to your account and start exploring our loan services. Our team will review your profile and contact you soon regarding available loan options.
</div>

<center>
    <a href="index.php" class="button">Access My Dashboard</a>
</center>

<p style="font-size: 14px; line-height: 1.8; color: #6b7280; margin-top: 30px;">
    If you have any questions or need assistance, please don't hesitate to contact our support team.
</p>
HTML;

        return self::getBaseTemplate($content, "Welcome to CYCLOAN Cooperative - Your account is now active!");
    }

    /**
     * Loan Status Update Email
     */
    public static function loanStatusUpdate($userName, $loanId, $status, $statusMessage, $loanAmount = null)
    {
        $statusBadge = '';
        $statusClass = 'alert-info';

        switch (strtolower($status)) {
            case 'approved':
                $statusBadge = '<span style="color: #22c55e; font-weight: 700;">✓ APPROVED</span>';
                $statusClass = 'alert-success';
                break;
            case 'rejected':
            case 'cancelled':
                $statusBadge = '<span style="color: #ef4444; font-weight: 700;">✗ ' . strtoupper($status) . '</span>';
                $statusClass = 'alert-warning';
                break;
            case 'pending':
                $statusBadge = '<span style="color: #f59e0b; font-weight: 700;">⏳ PENDING REVIEW</span>';
                $statusClass = 'alert-info';
                break;
            default:
                $statusBadge = '<span style="font-weight: 700;">' . strtoupper($status) . '</span>';
        }

        $amountInfo = $loanAmount ? <<<HTML
    <div class="info-row">
        <span class="info-label">Loan Amount:</span>
        <span class="info-value">₱{$loanAmount}</span>
    </div>
HTML : '';

        $content = <<<HTML
<h2 style="color: #1b5e20; margin-bottom: 16px;">Loan Application Update</h2>

<p style="font-size: 15px; line-height: 1.8; color: #374151; margin-bottom: 20px;">
    Dear <strong>{$userName}</strong>,
</p>

<p style="font-size: 15px; line-height: 1.8; color: #374151; margin-bottom: 20px;">
    We have an update regarding your loan application:
</p>

<div class="info-card">
    <h3>Application Details</h3>
    <div class="info-row">
        <span class="info-label">Application ID:</span>
        <span class="info-value">#{$loanId}</span>
    </div>
    {$amountInfo}
    <div class="info-row">
        <span class="info-label">Status:</span>
        <span>{$statusBadge}</span>
    </div>
</div>

<div class="alert {$statusClass}">
    <strong>Status Update:</strong><br>
    {$statusMessage}
</div>

<center>
    <a href="user_dashboard.php" class="button">View Application Details</a>
</center>

<p style="font-size: 14px; line-height: 1.8; color: #6b7280; margin-top: 30px;">
    For any questions or concerns, please contact our support team.
</p>
HTML;

        return self::getBaseTemplate($content, "Loan Application Update - Application #{$loanId}");
    }

    /**
     * Payment Confirmation Email
     */
    public static function paymentConfirmation($userName, $loanId, $amount, $paymentDate, $remainingBalance, $invoiceNumber = null)
    {
        $invoiceInfo = $invoiceNumber ? <<<HTML
    <div class="info-row">
        <span class="info-label">Invoice Number:</span>
        <span class="info-value">{$invoiceNumber}</span>
    </div>
HTML : '';

        $content = <<<HTML
<h2 style="color: #1b5e20; margin-bottom: 16px;">Payment Received ✓</h2>

<p style="font-size: 15px; line-height: 1.8; color: #374151; margin-bottom: 20px;">
    Dear <strong>{$userName}</strong>,
</p>

<p style="font-size: 15px; line-height: 1.8; color: #374151; margin-bottom: 20px;">
    Thank you! We have successfully received your payment.
</p>

<div class="info-card">
    <h3>Payment Details</h3>
    <div class="info-row">
        <span class="info-label">Loan ID:</span>
        <span class="info-value">#{$loanId}</span>
    </div>
    <div class="info-row">
        <span class="info-label">Amount Paid:</span>
        <span class="info-value" style="font-size: 18px; color: #22c55e;">₱{$amount}</span>
    </div>
    <div class="info-row">
        <span class="info-label">Payment Date:</span>
        <span class="info-value">{$paymentDate}</span>
    </div>
    {$invoiceInfo}
    <div class="info-row">
        <span class="info-label">Remaining Balance:</span>
        <span class="info-value" style="font-size: 16px;">₱{$remainingBalance}</span>
    </div>
</div>

<div class="alert alert-success">
    <strong>✓ Payment Confirmed!</strong><br>
    Your payment has been successfully processed and applied to your loan account. You will receive an official receipt shortly.
</div>

<center>
    <a href="user_dashboard.php" class="button">View Payment History</a>
</center>

<p style="font-size: 14px; line-height: 1.8; color: #6b7280; margin-top: 30px;">
    Keep this email for your records. If you have any questions about this payment, please contact us.
</p>
HTML;

        return self::getBaseTemplate($content, "Payment Confirmation - ₱{$amount} received");
    }

    /**
     * Payment Reminder Email
     */
    public static function paymentReminder($userName, $loanId, $amountDue, $dueDate, $daysUntilDue)
    {
        $urgencyClass = $daysUntilDue <= 3 ? 'alert-warning' : 'alert-info';
        $urgencyMessage = $daysUntilDue <= 3
            ? "⚠️ Your payment is due soon! Please ensure payment is made by {$dueDate} to avoid late fees."
            : "This is a friendly reminder about your upcoming payment due on {$dueDate}.";

        $content = <<<HTML
<h2 style="color: #1b5e20; margin-bottom: 16px;">Payment Reminder</h2>

<p style="font-size: 15px; line-height: 1.8; color: #374151; margin-bottom: 20px;">
    Dear <strong>{$userName}</strong>,
</p>

<p style="font-size: 15px; line-height: 1.8; color: #374151; margin-bottom: 20px;">
    {$urgencyMessage}
</p>

<div class="info-card">
    <h3>Payment Information</h3>
    <div class="info-row">
        <span class="info-label">Loan ID:</span>
        <span class="info-value">#{$loanId}</span>
    </div>
    <div class="info-row">
        <span class="info-label">Amount Due:</span>
        <span class="info-value" style="font-size: 18px;">₱{$amountDue}</span>
    </div>
    <div class="info-row">
        <span class="info-label">Due Date:</span>
        <span class="info-value" style="color: #ef4444; font-weight: 700;">{$dueDate}</span>
    </div>
    <div class="info-row">
        <span class="info-label">Days Remaining:</span>
        <span class="info-value">{$daysUntilDue} days</span>
    </div>
</div>

<div class="alert {$urgencyClass}">
    <strong>Payment Options:</strong><br>
    • Visit our office to make payment in person<br>
    • Contact our team to arrange payment<br>
    • Use our online payment portal (if available)
</div>

<center>
    <a href="user_dashboard.php" class="button">Make Payment</a>
</center>

<p style="font-size: 14px; line-height: 1.8; color: #6b7280; margin-top: 30px;">
    If you've already made this payment, please disregard this reminder. Thank you for your prompt attention.
</p>
HTML;

        return self::getBaseTemplate($content, "Payment Reminder - ₱{$amountDue} due on {$dueDate}");
    }

    /**
     * OTP Verification Email
     */
    public static function otpVerification($userName, $otp, $expiryMinutes = 10)
    {
        $content = <<<HTML
<h2 style="color: #1b5e20; margin-bottom: 16px;">Email Verification Code</h2>

<p style="font-size: 15px; line-height: 1.8; color: #374151; margin-bottom: 20px;">
    Dear <strong>{$userName}</strong>,
</p>

<p style="font-size: 15px; line-height: 1.8; color: #374151; margin-bottom: 20px;">
    Please use the following verification code to complete your registration:
</p>

<center>
    <div style="background: linear-gradient(135deg, #f0fdf4 0%, #dcfce7 100%); padding: 30px; border-radius: 12px; margin: 30px 0; border: 3px solid #34c759;">
        <p style="font-size: 14px; color: #6b7280; margin-bottom: 12px; font-weight: 600; text-transform: uppercase; letter-spacing: 1px;">Your Verification Code</p>
        <p style="font-size: 48px; font-weight: 700; color: #1b5e20; letter-spacing: 8px; margin: 0; font-family: 'Courier New', monospace;">{$otp}</p>
    </div>
</center>

<div class="alert alert-warning">
    <strong>⏱️ Important:</strong><br>
    This code will expire in <strong>{$expiryMinutes} minutes</strong>. Please enter it promptly to verify your email address.
</div>

<p style="font-size: 14px; line-height: 1.8; color: #6b7280; margin-top: 30px;">
    <strong>Security Notice:</strong> If you didn't request this code, please ignore this email or contact our support team immediately.
</p>
HTML;

        return self::getBaseTemplate($content, "Your CYCLOAN verification code: {$otp}");
    }

    /**
     * Loan Application Submitted Email
     */
    public static function loanApplicationSubmitted($userName, $loanId, $applicationId, $loanType, $amount, $term)
    {
        $formattedAmount = number_format($amount, 2);
        $submissionDate = date('F j, Y \a\t h:i A');

        // Calculate estimated interest and total amount (6% annual rate)
        $interestRate = 6.0; // 6% annual interest
        $termYears = $term / 12;
        $estimatedInterest = $amount * ($interestRate / 100) * $termYears;
        $estimatedTotal = $amount + $estimatedInterest;

        $content = <<<HTML
<div style="text-align: center; margin-bottom: 30px;">
    <div style="display: inline-block; background: linear-gradient(135deg, #10b981 0%, #059669 100%); color: white; padding: 12px 24px; border-radius: 50px; font-weight: 600; font-size: 16px;">
        ✓ Application Successfully Submitted
    </div>
</div>

<h2 style="color: #1b5e20; margin-bottom: 16px; font-size: 24px; text-align: center;">Loan Application Confirmation</h2>

<p style="font-size: 16px; line-height: 1.8; color: #374151; margin-bottom: 24px; text-align: center;">
    Dear <strong>{$userName}</strong>,
</p>

<p style="font-size: 15px; line-height: 1.8; color: #374151; margin-bottom: 24px;">
    Thank you for choosing CYCLOAN Cooperative for your financial needs! We have successfully received your loan application and it is now in our review queue.
</p>

<div class="info-card" style="background: linear-gradient(135deg, #f0fdf4 0%, #dcfce7 100%); border: 2px solid #22c55e; border-radius: 12px; padding: 24px; margin: 24px 0;">
    <h3 style="color: #1b5e20; margin-bottom: 16px; font-size: 18px; display: flex; align-items: center;">
        📋 Application Summary
    </h3>
    <div class="info-row" style="display: flex; justify-content: space-between; padding: 8px 0; border-bottom: 1px solid rgba(34, 197, 94, 0.2);">
        <span class="info-label" style="font-weight: 500; color: #374151;">Submission Date:</span>
        <span class="info-value" style="font-weight: 600; color: #1b5e20;">{$submissionDate}</span>
    </div>
    <div class="info-row" style="display: flex; justify-content: space-between; padding: 8px 0; border-bottom: 1px solid rgba(34, 197, 94, 0.2);">
        <span class="info-label" style="font-weight: 500; color: #374151;">Loan ID:</span>
        <span class="info-value" style="font-weight: 600; color: #1b5e20;">{$loanId}</span>
    </div>
    <div class="info-row" style="display: flex; justify-content: space-between; padding: 8px 0; border-bottom: 1px solid rgba(34, 197, 94, 0.2);">
        <span class="info-label" style="font-weight: 500; color: #374151;">Application ID:</span>
        <span class="info-value" style="font-weight: 600; color: #1b5e20;">{$applicationId}</span>
    </div>
    <div class="info-row" style="display: flex; justify-content: space-between; padding: 8px 0; border-bottom: 1px solid rgba(34, 197, 94, 0.2);">
        <span class="info-label" style="font-weight: 500; color: #374151;">Loan Type:</span>
        <span class="info-value" style="font-weight: 600; color: #1b5e20;">{$loanType}</span>
    </div>
    <div class="info-row" style="display: flex; justify-content: space-between; padding: 8px 0; border-bottom: 1px solid rgba(34, 197, 94, 0.2);">
        <span class="info-label" style="font-weight: 500; color: #374151;">Requested Amount:</span>
        <span class="info-value" style="font-weight: 700; color: #1b5e20; font-size: 16px;">₱{$formattedAmount}</span>
    </div>
    <div class="info-row" style="display: flex; justify-content: space-between; padding: 8px 0; border-bottom: 1px solid rgba(34, 197, 94, 0.2);">
        <span class="info-label" style="font-weight: 500; color: #374151;">Loan Term:</span>
        <span class="info-value" style="font-weight: 600; color: #1b5e20;">{$term} Months</span>
    </div>
    <div class="info-row" style="display: flex; justify-content: space-between; padding: 8px 0; border-bottom: 1px solid rgba(34, 197, 94, 0.2);">
        <span class="info-label" style="font-weight: 500; color: #374151;">Interest Rate:</span>
        <span class="info-value" style="font-weight: 600; color: #1b5e20;">{$interestRate}% per annum</span>
    </div>
    <div class="info-row" style="display: flex; justify-content: space-between; padding: 8px 0;">
        <span class="info-label" style="font-weight: 500; color: #374151;">Estimated Total Amount:</span>
        <span class="info-value" style="font-weight: 700; color: #dc2626; font-size: 16px;">₱" . number_format($estimatedTotal, 2) . "</span>
    </div>
</div>

<div class="alert alert-info" style="background: linear-gradient(135deg, #eff6ff 0%, #dbeafe 100%); border: 2px solid #3b82f6; border-radius: 12px; padding: 20px; margin: 24px 0;">
    <h4 style="color: #1e40af; margin-bottom: 12px; font-size: 16px; display: flex; align-items: center;">
        ⏰ What Happens Next?
    </h4>
    <ol style="color: #1e40af; font-size: 14px; line-height: 1.6; margin-left: 20px;">
        <li><strong>Document Review:</strong> Our loan officers will verify all submitted documents (1-2 days)</li>
        <li><strong>Credit Investigation:</strong> We'll conduct a thorough credit assessment (2-3 days)</li>
        <li><strong>Approval Decision:</strong> You'll receive notification of our decision via email</li>
        <li><strong>Loan Disbursement:</strong> If approved, funds will be released as per our policy</li>
    </ol>
    <p style="color: #1e40af; font-size: 13px; margin-top: 12px; font-style: italic;">
        📧 We'll keep you updated via email throughout the entire process.
    </p>
</div>

<div style="text-align: center; margin: 32px 0;">
    <a href="user_dashboard.php" class="button" style="display: inline-block; background: linear-gradient(135deg, #1b5e20 0%, #2e7d32 100%); color: white; text-decoration: none; padding: 14px 28px; border-radius: 8px; font-weight: 600; font-size: 15px; box-shadow: 0 4px 12px rgba(27, 94, 32, 0.3); transition: all 0.3s ease;">
        📊 Check Application Status
    </a>
</div>

<div style="background: #f9fafb; border-left: 4px solid #6b7280; padding: 16px; margin: 24px 0; border-radius: 0 8px 8px 0;">
    <h4 style="color: #374151; margin-bottom: 8px; font-size: 14px;">Need Help or Have Questions?</h4>
    <p style="font-size: 13px; color: #6b7280; line-height: 1.5;">
        📧 Email us at: <a href="mailto:scycloan@gmail.com" style="color: #1b5e20;">scycloan@gmail.com</a><br>
        📞 Call us at: 0981-303-8698 | Landline: 545-6789 loc 8018-19<br>
        🏙 Address: Lower Ground Floor (LG)24 New City Hall Bldg, Bacnotan St., Brgy Real, Calamba City Laguna<br>
        🕖 Hours: Monday to Friday, 8:00 AM - 5:00 PM
    </p>
</div>

<p style="font-size: 13px; line-height: 1.6; color: #6b7280; margin-top: 30px; text-align: center;">
    This is an automated confirmation email. Please keep this email for your records.<br>
    Reference Number: <strong>{$applicationId}</strong>
</p>
HTML;

        return self::getBaseTemplate($content, "Your loan application #{$loanId} has been submitted successfully!");
    }

    /**
     * Account Activation Confirmation Email
     * Sent after successful OTP verification to confirm account activation
     */
    public static function accountActivationConfirmation($userName, $email, $activationDate = null, $registrationDetails = [])
    {
        $activationDate = $activationDate ?? date('F j, Y \a\t h:i A');

        // Build registration details if provided
        $detailsHtml = '';
        if (!empty($registrationDetails)) {
            $detailsHtml = '<h3>Your Registration Details</h3>';
            foreach ($registrationDetails as $label => $value) {
                $detailsHtml .= <<<HTML
    <div class="info-row">
        <span class="info-label">{$label}:</span>
        <span class="info-value">{$value}</span>
    </div>
HTML;
            }
        }

        $content = <<<HTML
<h2 style="color: #1b5e20; margin-bottom: 16px;">Account Successfully Activated! 🎉</h2>

<p style="font-size: 15px; line-height: 1.8; color: #374151; margin-bottom: 20px;">
    Dear <strong>{$userName}</strong>,
</p>

<p style="font-size: 15px; line-height: 1.8; color: #374151; margin-bottom: 20px;">
    Congratulations! Your email address has been verified and your CYCLOAN account is now fully activated and ready to use.
</p>

<div class="info-card">
    <h3>Activation Details</h3>
    <div class="info-row">
        <span class="info-label">Email Verified:</span>
        <span class="info-value">✓ {$email}</span>
    </div>
    <div class="info-row">
        <span class="info-label">Activation Date & Time:</span>
        <span class="info-value">{$activationDate}</span>
    </div>
    <div class="info-row">
        <span class="info-label">Account Status:</span>
        <span class="info-value" style="color: #22c55e; font-weight: 700;">✓ ACTIVE</span>
    </div>
    {$detailsHtml}
</div>

<div class="alert alert-success">
    <strong>✓ What You Can Do Now:</strong><br>
    Your account is fully activated! You can now:<br>
    • Log in to your CYCLOAN account<br>
    • View your profile and personal information<br>
    • Apply for loans<br>
    • Track your applications<br>
    • Access member benefits
</div>

<div style="background: linear-gradient(135deg, #f0fdf4 0%, #dcfce7 100%); padding: 20px; border-radius: 8px; margin: 20px 0; border-left: 4px solid #1b5e20;">
    <h3 style="color: #1b5e20; margin-bottom: 12px; font-size: 16px;">🔒 Security Tips:</h3>
    <ul style="color: #374151; font-size: 14px; line-height: 1.8; margin-left: 20px;">
        <li><strong>Keep Your Credentials Safe:</strong> Never share your password with anyone, including CYCLOAN staff.</li>
        <li><strong>Use Strong Passwords:</strong> Your password should include uppercase, lowercase, numbers, and special characters.</li>
        <li><strong>Log Out After Use:</strong> Always log out when using public or shared computers.</li>
        <li><strong>Monitor Your Account:</strong> Regularly check your account for any suspicious activity.</li>
    </ul>
</div>

<center>
    <a href="index.php" class="button">Login to Your Account</a>
</center>

<div class="divider"></div>

<h3 style="color: #1b5e20; margin-bottom: 12px; font-size: 16px;">📋 Next Steps:</h3>

<p style="font-size: 14px; line-height: 1.8; color: #374151; margin-bottom: 12px;">
    <strong>1. Complete Your Profile:</strong> Update your profile with additional information to maximize your loan eligibility.
</p>

<p style="font-size: 14px; line-height: 1.8; color: #374151; margin-bottom: 12px;">
    <strong>2. Review Loan Options:</strong> Browse available loan products and their requirements.
</p>

<p style="font-size: 14px; line-height: 1.8; color: #374151; margin-bottom: 12px;">
    <strong>3. Submit Loan Application:</strong> Start the loan application process when you're ready.
</p>

<p style="font-size: 14px; line-height: 1.8; color: #374151; margin-bottom: 20px;">
    <strong>4. Track Your Application:</strong> Monitor the status of your loan application in real-time through your dashboard.
</p>

<div class="alert alert-info">
    <strong>📞 Need Help?</strong><br>
    If you have any questions about your account or our services, our support team is ready to assist you. You can reach us at support@cycloan-cldd.com or visit our office during business hours.
</p>

<p style="font-size: 14px; line-height: 1.8; color: #6b7280; margin-top: 30px;">
    Thank you for joining the CYCLOAN family. We look forward to serving you!
</p>
HTML;

        return self::getBaseTemplate($content, "Your CYCLOAN account is now activated and ready to use!");
    }
}
?>