<?php
session_start();
require "CYCLOAN_db.php";
require_once 'timezone_config.php';
require_once 'NotificationManager.php';
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Ensure $conn is a MySQLi instance
if (!($conn instanceof mysqli)) {
    error_log("Database connection is not a MySQLi instance", 3, 'errors.log');
    die(json_encode(['success' => false, 'message' => 'Database connection is not MySQLi']));
}

// Check if connection is successful
if (mysqli_connect_errno()) {
    error_log("Database connection error: " . mysqli_connect_error(), 3, 'errors.log');
    die(json_encode(['success' => false, 'message' => 'Database connection failed: ' . mysqli_connect_error()]));
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$payment_id = $data['payment_id'] ?? null;
$amount_paid = $data['amount_paid'] ?? null;
$payment_date = $data['payment_date'] ?? null;
$payment_type = $data['payment_type'] ?? null;
$interest_paid = $data['interest_paid'] ?? 0;
$principal_paid = $data['principal_paid'] ?? 0;
$invoice_number = $data['invoice_number'] ?? null;

if (!$payment_id || !$amount_paid || !$payment_date || !$payment_type || !in_array($payment_type, ['full', 'interest', 'principal', 'custom'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid or missing input parameters']);
    exit;
}

// Validate numeric inputs and round to 2 decimal places
$amount_paid = round(floatval($amount_paid), 2);
$interest_paid = round(floatval($interest_paid), 2);
$principal_paid = round(floatval($principal_paid), 2);

// Validate invoice number (optional, max 50 characters)
if ($invoice_number !== null && (strlen($invoice_number) > 50 || !preg_match('/^[a-zA-Z0-9-_]*$/', $invoice_number))) {
    echo json_encode(['success' => false, 'message' => 'Invalid invoice number. Use up to 50 alphanumeric characters, hyphens, or underscores.']);
    exit;
}

if ($amount_paid <= 0) {
    echo json_encode(['success' => false, 'message' => 'Payment amount must be greater than 0']);
    exit;
}

mysqli_begin_transaction($conn);

try {
    // Fetch payment schedule details and loan_applications.loan_id
    $stmt = mysqli_prepare($conn, "
        SELECT ps.loan_id, ps.amount, ps.amount_paid, ps.interest_amount, ps.principal_amount, ps.interest_paid, ps.principal_paid,
               la.application_id, la.loan_id AS application_loan_id, u.email, u.first_name, u.last_name
        FROM payment_schedules ps
        JOIN loans l ON ps.loan_id = l.loan_id
        JOIN loan_applications la ON l.application_id = la.application_id
        JOIN users1 u ON la.user_id = u.id
        WHERE ps.payment_id = ?
    ");
    if (!$stmt) {
        throw new Exception("Prepare failed: " . mysqli_error($conn));
    }
    mysqli_stmt_bind_param($stmt, "i", $payment_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $payment = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);

    if (!$payment) {
        throw new Exception("Invalid payment ID");
    }

    $loan_id = $payment['loan_id']; // Internal loans.loan_id (INT)
    $application_loan_id = $payment['application_loan_id'] ?: 'N/A'; // loan_applications.loan_id (VARCHAR)
    $applicant_email = $payment['email'];
    $applicant_name = trim($payment['first_name'] . ' ' . $payment['last_name']);
    $remaining_due = round($payment['amount'] - $payment['amount_paid'], 2);
    $remaining_interest = round($payment['interest_amount'] - $payment['interest_paid'], 2);
    $remaining_principal = round($payment['principal_amount'] - $payment['principal_paid'], 2);

    // Validate payment amount
    if ($amount_paid > ($remaining_due + 0.01)) {
        throw new Exception("Payment amount exceeds remaining due (₱" . number_format($remaining_due, 2) . ")");
    }

    // Calculate interest and principal paid based on payment type
    if ($payment_type === 'custom') {
        if ($interest_paid < 0 || $interest_paid > $remaining_interest) {
            throw new Exception("Interest paid must be between 0 and ₱" . number_format($remaining_interest, 2));
        }
        if ($principal_paid < 0 || $principal_paid > $remaining_principal) {
            throw new Exception("Principal paid must be between 0 and ₱" . number_format($remaining_principal, 2));
        }
        if (abs($interest_paid + $principal_paid - $amount_paid) > 0.01) {
            throw new Exception("Interest and principal paid must sum to payment amount (₱" . number_format($amount_paid, 2) . ")");
        }
    } else {
        $interest_paid = ($payment_type === 'principal') ? 0 : min($amount_paid, $remaining_interest);
        $principal_paid = ($payment_type === 'interest') ? 0 : $amount_paid - $interest_paid;

        if ($payment_type === 'interest' && $interest_paid > $remaining_interest) {
            throw new Exception("Interest payment cannot exceed remaining interest (₱" . number_format($remaining_interest, 2) . ")");
        }
        if ($payment_type === 'principal' && $principal_paid > $remaining_principal) {
            throw new Exception("Principal payment cannot exceed remaining principal (₱" . number_format($remaining_principal, 2) . ")");
        }
    }

    // Insert into payment_history
    $stmt = mysqli_prepare($conn, "
        INSERT INTO payment_history (payment_id, loan_id, amount_paid, interest_paid, principal_paid, payment_type, payment_date, invoice_number)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
    ");
    if (!$stmt) {
        throw new Exception("Prepare failed: " . mysqli_error($conn));
    }
    mysqli_stmt_bind_param($stmt, "iidddsss", $payment_id, $loan_id, $amount_paid, $interest_paid, $principal_paid, $payment_type, $payment_date, $invoice_number);
    mysqli_stmt_execute($stmt);
    $payment_history_id = mysqli_insert_id($conn);
    mysqli_stmt_close($stmt);

    // Update payment_schedules
    $new_amount_paid = round($payment['amount_paid'] + $amount_paid, 2);
    $new_interest_paid = round($payment['interest_paid'] + $interest_paid, 2);
    $new_principal_paid = round($payment['principal_paid'] + $principal_paid, 2);
    $status = ($new_amount_paid >= $payment['amount'] - 0.01) ? 'Paid' : 'partial';

    // Get current time in Philippine Time (UTC+8)
    $phpTimeZone = new DateTimeZone('Asia/Manila');
    $now = new DateTime('now', $phpTimeZone);
    $updatedAt = $now->format('Y-m-d H:i:s');

    $stmt = mysqli_prepare($conn, "
        UPDATE payment_schedules
        SET amount_paid = ?, interest_paid = ?, principal_paid = ?, status = ?, payment_date_actual = ?, updated_at = ?
        WHERE payment_id = ?
    ");
    if (!$stmt) {
        throw new Exception("Prepare failed: " . mysqli_error($conn));
    }
    mysqli_stmt_bind_param($stmt, "dddsssi", $new_amount_paid, $new_interest_paid, $new_principal_paid, $status, $payment_date, $updatedAt, $payment_id);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    // Update loans
    $stmt = mysqli_prepare($conn, "
        UPDATE loans
        SET remaining_balance = remaining_balance - ?, total_paid = total_paid + ?
        WHERE loan_id = ?
    ");
    if (!$stmt) {
        throw new Exception("Prepare failed: " . mysqli_error($conn));
    }
    mysqli_stmt_bind_param($stmt, "ddi", $principal_paid, $amount_paid, $loan_id);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    // Check if loan is fully paid
    $stmt = mysqli_prepare($conn, "SELECT remaining_balance FROM loans WHERE loan_id = ?");
    if (!$stmt) {
        throw new Exception("Prepare failed: " . mysqli_error($conn));
    }
    mysqli_stmt_bind_param($stmt, "i", $loan_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $row = mysqli_fetch_assoc($result);
    $remaining_balance = round($row['remaining_balance'], 2);
    mysqli_stmt_close($stmt);

    if ($remaining_balance <= 0.01) {
        // Get current time in Philippine Time (UTC+8)
        $phpTimeZone = new DateTimeZone('Asia/Manila');
        $now = new DateTime('now', $phpTimeZone);
        $closedAt = $now->format('Y-m-d H:i:s');

        $stmt = mysqli_prepare($conn, "UPDATE loans SET status = 'closed', updated_at = ? WHERE loan_id = ?");
        if (!$stmt) {
            throw new Exception("Prepare failed: " . mysqli_error($conn));
        }
        mysqli_stmt_bind_param($stmt, "si", $closedAt, $loan_id);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);

        $stmt = mysqli_prepare($conn, "UPDATE loan_applications SET status = 'Closed' WHERE application_id = (SELECT application_id FROM loans WHERE loan_id = ?)");
        if (!$stmt) {
            throw new Exception("Prepare failed: " . mysqli_error($conn));
        }
        mysqli_stmt_bind_param($stmt, "i", $loan_id);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
    }

    // Insert invoice
    $stmt = mysqli_prepare($conn, "
        INSERT INTO invoices (payment_id, loan_id, payment_history_id, amount_paid, interest_paid, principal_paid, payment_date, invoice_number)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
    ");
    if (!$stmt) {
        throw new Exception("Prepare failed: " . mysqli_error($conn));
    }
    mysqli_stmt_bind_param($stmt, "iiidddss", $payment_id, $loan_id, $payment_history_id, $amount_paid, $interest_paid, $principal_paid, $payment_date, $invoice_number);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    // Log activity with loan_applications.loan_id
    $user_id = $_SESSION['user_id'] ?? null;
    $user_role = $_SESSION['role'] ?? 'unknown';
    $description = "Processed payment of ₱" . number_format($amount_paid, 2) . " ($payment_type)" . ($invoice_number ? " with invoice $invoice_number" : "") . " for payment ID $payment_id on loan $application_loan_id";
    $stmt = mysqli_prepare($conn, "
        INSERT INTO activity_logs (user_id, user_role, action_type, module, description, affected_id, created_at)
        VALUES (?, ?, ?, ?, ?, ?, NOW())
    ");
    if (!$stmt) {
        throw new Exception("Prepare failed for activity log: " . mysqli_error($conn));
    }
    $action_type = 'payment';
    $module = 'payment';
    mysqli_stmt_bind_param($stmt, "issssi", $user_id, $user_role, $action_type, $module, $description, $payment_id);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    // Create notification for payment received
    try {
        // Get user ID from loan application
        $stmt = mysqli_prepare($conn, "
            SELECT la.user_id, u.first_name, u.last_name
            FROM loan_applications la
            JOIN users1 u ON la.user_id = u.id
            WHERE la.application_id = (SELECT application_id FROM loans WHERE loan_id = ?)
        ");
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, "i", $loan_id);
            mysqli_stmt_execute($stmt);
            $userResult = mysqli_stmt_get_result($stmt);
            $userData = mysqli_fetch_assoc($userResult);
            mysqli_stmt_close($stmt);

            if ($userData) {
                $userId = $userData['user_id'];
                $fullName = trim($userData['first_name'] . ' ' . $userData['last_name']);

                // Create payment notification (call matches function signature: 4 parameters)
                createPaymentNotification(
                    $userId,
                    $amount_paid,
                    $payment_date,
                    $payment_id
                );

                error_log("Payment notification created for user ID $userId, payment ID $payment_id", 3, 'errors.log');
            }
        }
    } catch (Exception $e) {
        error_log("Failed to create payment notification: " . $e->getMessage(), 3, 'errors.log');
        // Don't fail the payment if notification fails
    }

    // Send email to applicant after successful payment
    if (sendPaymentInvoiceEmail($payment['email'], $applicant_name, $amount_paid, $interest_paid, $principal_paid, $payment_type, $payment_date, $invoice_number, $application_loan_id)) {
        error_log("Payment invoice email sent to {$payment['email']} for payment ID $payment_id", 3, 'errors.log');
    } else {
        error_log("Failed to send payment invoice email to {$payment['email']} for payment ID $payment_id", 3, 'errors.log');
    }

    mysqli_commit($conn);
    echo json_encode(['success' => true, 'message' => 'Payment processed successfully']);
} catch (Exception $e) {
    mysqli_rollback($conn);
    error_log("Error processing payment: " . $e->getMessage(), 3, 'errors.log');
    echo json_encode(['success' => false, 'message' => 'Error processing payment: ' . $e->getMessage()]);
}

function sendPaymentInvoiceEmail($email, $name, $amount_paid, $interest_paid, $principal_paid, $payment_type, $payment_date, $invoice_number, $application_loan_id)
{
    require 'phpmailer/src/Exception.php';
    require 'phpmailer/src/PHPMailer.php';
    require 'phpmailer/src/SMTP.php';
    require 'tcpdf/tcpdf.php';

    $mail = new PHPMailer(true);

    try {
        // SMTP settings
        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com';
        $mail->SMTPAuth = true;
        $mail->Username = 'cycloancldd@gmail.com';
        $mail->Password = 'hbfh ukgh tmzw nqbq'; // Use an app password or environment variable for security
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = 587;

        $mail->setFrom('cycloancldd@gmail.com', 'CLDD Loan Support');
        $mail->addAddress($email);

        $mail->isHTML(true);
        $mail->Subject = 'CYCLOAN - Payment Receipt and Invoice - ₱' . number_format($amount_paid, 2);

        // Prepare email content variables
        $loanIdText = $application_loan_id !== 'N/A' ? $application_loan_id : 'N/A';
        $year = date('Y');
        $payment_type_label = ucfirst($payment_type);

        // Generate professional email body with improved styling
        $emailContent = "
            <div style='background: linear-gradient(135deg, #e8f5e9 0%, #f1f8e9 100%); border-radius: 8px; padding: 20px; margin: 20px 0; text-align: center; border: 2px solid #2e7d32;'>
                <h2 style='color: #1b5e20; margin: 0 0 10px 0; font-size: 24px;'>✅ Payment Successfully Processed!</h2>
                <p style='font-size: 16px; color: #2e7d32; margin: 0;'><strong>Thank you for your payment</strong></p>
            </div>
            
            <div style='background-color: #e8f5e9; border: 2px solid #2e7d32; border-radius: 8px; padding: 20px; margin: 25px 0;'>
                <div style='text-align: center; margin-bottom: 15px;'>
                    <span style='background-color: #2e7d32; color: white; padding: 8px 20px; border-radius: 20px; font-size: 14px; font-weight: bold;'>✅ PAID</span>
                </div>
                <table style='width: 100%; border-collapse: collapse;'>
                    <tr>
                        <td style='padding: 8px 0; color: #666; font-size: 14px;'>Loan ID:</td>
                        <td style='padding: 8px 0; text-align: right; font-weight: bold; color: #1b5e20; font-size: 16px;'>$loanIdText</td>
                    </tr>" .
            ($invoice_number ? "
                    <tr>
                        <td style='padding: 8px 0; color: #666; font-size: 14px;'>Invoice Number:</td>
                        <td style='padding: 8px 0; text-align: right; font-weight: bold; color: #1b5e20; font-size: 16px;'>$invoice_number</td>
                    </tr>" : "") . "
                    <tr>
                        <td style='padding: 8px 0; color: #666; font-size: 14px;'>Payment Date:</td>
                        <td style='padding: 8px 0; text-align: right; font-weight: bold; color: #1b5e20; font-size: 16px;'>" . date('F j, Y', strtotime($payment_date)) . "</td>
                    </tr>
                </table>
            </div>
            
            <h3 style='color: #1b5e20; border-bottom: 3px solid #2e7d32; padding-bottom: 10px; margin: 30px 0 20px 0;'>
                <span style='font-size: 20px;'>💰</span> Payment Summary
            </h3>
            
            <table style='width: 100%; border-collapse: collapse; margin: 20px 0; box-shadow: 0 2px 4px rgba(0,0,0,0.1); border-radius: 8px; overflow: hidden;'>
                <thead>
                    <tr style='background: linear-gradient(135deg, #1b5e20 0%, #2e7d32 100%); color: white;'>
                        <th style='padding: 15px; text-align: left; font-weight: 600;'>Description</th>
                        <th style='padding: 15px; text-align: right; font-weight: 600;'>Amount</th>
                    </tr>
                </thead>
                <tbody>
                    <tr style='background-color: #f8f8f8;'>
                        <td style='padding: 12px 15px; border-bottom: 1px solid #e0e0e0;'><strong>Interest Paid</strong></td>
                        <td style='padding: 12px 15px; text-align: right; border-bottom: 1px solid #e0e0e0; font-family: monospace; color: #f57c00;'>₱" . number_format($interest_paid, 2) . "</td>
                    </tr>
                    <tr style='background-color: white;'>
                        <td style='padding: 12px 15px; border-bottom: 1px solid #e0e0e0;'><strong>Principal Paid</strong></td>
                        <td style='padding: 12px 15px; text-align: right; border-bottom: 1px solid #e0e0e0; font-family: monospace;'>₱" . number_format($principal_paid, 2) . "</td>
                    </tr>
                    <tr style='background-color: #e8f5e9;'>
                        <td style='padding: 15px; border-bottom: 2px solid #2e7d32;'><strong style='font-size: 16px;'>Total Amount Paid</strong></td>
                        <td style='padding: 15px; text-align: right; border-bottom: 2px solid #2e7d32; font-family: monospace; color: #1b5e20; font-weight: bold; font-size: 18px;'>₱" . number_format($amount_paid, 2) . "</td>
                    </tr>
                </tbody>
            </table>
            
            <div style='background-color: #f8f8f8; border-radius: 8px; padding: 20px; margin: 20px 0;'>
                <table style='width: 100%; border-collapse: collapse;'>
                    <tr>
                        <td style='padding: 10px 0; color: #666;'>
                            <span style='font-size: 18px;'>📋</span> <strong>Payment Type:</strong>
                        </td>
                        <td style='padding: 10px 0; text-align: right; color: #1b5e20; font-size: 16px; font-weight: bold;'>
                            $payment_type_label
                        </td>
                    </tr>
                    <tr>
                        <td style='padding: 10px 0; color: #666;'>
                            <span style='font-size: 18px;'>📅</span> <strong>Processed On:</strong>
                        </td>
                        <td style='padding: 10px 0; text-align: right; font-size: 16px; font-weight: 600;'>
                            " . date('F j, Y', strtotime($payment_date)) . "
                        </td>
                    </tr>
                </table>
            </div>
            
            <div style='background: linear-gradient(135deg, #e3f2fd 0%, #bbdefb 100%); border-left: 5px solid #1976d2; border-radius: 8px; padding: 20px; margin: 25px 0;'>
                <h4 style='color: #0d47a1; margin: 0 0 10px 0; font-size: 18px;'>
                    📎 Receipt Attached
                </h4>
                <p style='margin: 0; color: #1565c0; line-height: 1.6;'>
                    Your payment receipt PDF is attached to this email. Please <strong>download and save it</strong> for your financial records.
                </p>
            </div>
            
            <div style='text-align: center; margin: 30px 0;'>
                <a href='user_dashboard.php' style='display: inline-block; background: linear-gradient(135deg, #1b5e20 0%, #2e7d32 100%); color: white; padding: 15px 40px; text-decoration: none; border-radius: 30px; font-weight: bold; font-size: 16px; box-shadow: 0 4px 6px rgba(0,0,0,0.2);'>
                    📊 View Payment History
                </a>
            </div>
            
            <div style='background: linear-gradient(135deg, #fff3e0 0%, #ffe0b2 100%); border-left: 5px solid #f57c00; border-radius: 8px; padding: 20px; margin: 25px 0;'>
                <h4 style='color: #e65100; margin: 0 0 15px 0; font-size: 18px;'>
                    💡 Important Reminders
                </h4>
                <table style='width: 100%;'>
                    <tr>
                        <td style='padding: 8px 0; color: #e65100; width: 30px;'>✅</td>
                        <td style='padding: 8px 0; color: #e65100;'>Keep this receipt for your financial records and tax purposes</td>
                    </tr>
                    <tr>
                        <td style='padding: 8px 0; color: #e65100;'>📊</td>
                        <td style='padding: 8px 0; color: #e65100;'>Track your remaining balance in your dashboard</td>
                    </tr>
                    <tr>
                        <td style='padding: 8px 0; color: #e65100;'>📧</td>
                        <td style='padding: 8px 0; color: #e65100;'>Contact support if you have any questions about this payment</td>
                    </tr>
                    <tr>
                        <td style='padding: 8px 0; color: #e65100;'>🔔</td>
                        <td style='padding: 8px 0; color: #e65100;'>You'll receive reminders for upcoming payments</td>
                    </tr>
                </table>
            </div>
            
            <div style='background-color: #f5f5f5; border-radius: 8px; padding: 20px; margin: 30px 0; text-align: center;'>
                <p style='color: #666; margin: 0 0 10px 0;'>Questions about your payment?</p>
                <p style='color: #1b5e20; font-size: 18px; font-weight: bold; margin: 0;'>📧 cycloancldd@gmail.com | 📞 0981-303-8698</p>
            </div>
            
            <p style='text-align: center; color: #666; margin: 25px 0;'>
                Thank you for choosing <strong style='color: #1b5e20;'>CLDD Loan Program</strong>!
            </p>
            <p style='text-align: center; color: #999; font-size: 14px; margin: 15px 0;'>
                Best regards,<br>
                <strong style='color: #1b5e20;'>The CLDD Loan Support Team</strong>
            </p>
        ";

        // Generate complete email template
        $mail->Body = <<<HTML
<!DOCTYPE html>
<html lang='en'>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background-color: #f4f4f4; margin: 0; padding: 0; }
        .email-container { max-width: 600px; margin: 20px auto; background-color: #ffffff; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,0.1); }
        .email-header { background: linear-gradient(135deg, #1b5e20 0%, #2e7d32 100%); color: #ffffff; padding: 30px 20px; text-align: center; }
        .email-header h1 { margin: 0; font-size: 24px; font-weight: 600; }
        .email-body { padding: 30px 20px; color: #333333; line-height: 1.6; }
        .email-footer { background-color: #f8f8f8; padding: 20px; text-align: center; font-size: 12px; color: #666666; border-top: 1px solid #e0e0e0; }
    </style>
</head>
<body>
    <div class='email-container'>
        <div class='email-header'>
            <h1>💳 Payment Receipt</h1>
            <p style='margin: 5px 0 0 0; font-size: 14px;'>CLDD Loan Program</p>
        </div>
        <div class='email-body'>
            <p>Hello <strong>$name</strong>,</p>
            $emailContent
        </div>
        <div class='email-footer'>
            <p><strong>CLDD Loan Support Team</strong></p>
            <p>Email: cycloancldd@gmail.com | Phone: 0981-303-8698</p>
            <p>© $year CLDD Loan Program. All rights reserved.</p>
            <p style='margin-top: 10px; font-size: 11px;'>This is an automated message. Please do not reply directly to this email.</p>
        </div>
    </div>
</body>
</html>
HTML;

        // Generate PDF with improved styling
        $pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);

        // Set document information
        $pdf->SetCreator('CYCLOAN - CLDD Loan Program');
        $pdf->SetAuthor('CLDD Loan Program');
        $pdf->SetTitle('Payment Receipt - ' . ($invoice_number ?: 'No Invoice'));
        $pdf->SetSubject('Payment Receipt');

        // Remove default header/footer
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);

        // Set margins
        $pdf->SetMargins(15, 15, 15);
        $pdf->SetAutoPageBreak(TRUE, 15);

        // Add a page
        $pdf->AddPage();

        // Set font
        $pdf->SetFont('helvetica', '', 10);

        // Header with green gradient background
        for ($i = 0; $i < 45; $i++) {
            $r = 27 + ($i * (46 - 27) / 45);
            $g = 94 + ($i * (125 - 94) / 45);
            $b = 32;
            $pdf->SetFillColor($r, $g, $b);
            $pdf->Rect(0, $i, 210, 1, 'F');
        }

        $pdf->SetTextColor(255, 255, 255);
        $pdf->SetFont('helvetica', 'B', 26);
        $pdf->SetXY(15, 10);
        $pdf->Cell(0, 12, 'PAYMENT RECEIPT', 0, 1, 'C');
        $pdf->SetFont('helvetica', '', 12);
        $pdf->SetX(15);
        $pdf->Cell(0, 8, 'CLDD Loan Program', 0, 1, 'C');
        $pdf->SetFont('helvetica', '', 9);
        $pdf->SetX(15);
        $pdf->Cell(0, 6, 'Email: cycloancldd@gmail.com | Phone: (123) 456-7890', 0, 1, 'C');

        // Reset text color
        $pdf->SetTextColor(0, 0, 0);

        // Receipt details box
        $pdf->SetY(55);
        $pdf->SetFont('helvetica', 'B', 14);
        $pdf->SetTextColor(27, 94, 32);
        $pdf->Cell(0, 8, 'Receipt Details', 0, 1, 'L');
        $pdf->SetTextColor(0, 0, 0);

        $pdf->SetFillColor(232, 245, 233);
        $pdf->SetDrawColor(46, 125, 50);
        $pdf->SetLineWidth(1);
        $pdf->Rect(15, $pdf->GetY(), 180, ($invoice_number ? 46 : 40), 'FD');

        $y_position = $pdf->GetY() + 6;
        $pdf->SetFont('helvetica', '', 10);

        $pdf->SetXY(20, $y_position);
        $pdf->SetFont('helvetica', 'B', 10);
        $pdf->SetTextColor(27, 94, 32);
        $pdf->Cell(65, 6, 'Recipient:', 0, 0);
        $pdf->SetFont('helvetica', '', 10);
        $pdf->SetTextColor(0, 0, 0);
        $pdf->Cell(0, 6, $name, 0, 1);

        $pdf->SetX(20);
        $pdf->SetFont('helvetica', 'B', 10);
        $pdf->SetTextColor(27, 94, 32);
        $pdf->Cell(65, 6, 'Loan ID:', 0, 0);
        $pdf->SetFont('helvetica', '', 10);
        $pdf->SetTextColor(0, 0, 0);
        $pdf->Cell(0, 6, $loanIdText, 0, 1);

        if ($invoice_number) {
            $pdf->SetX(20);
            $pdf->SetFont('helvetica', 'B', 10);
            $pdf->SetTextColor(27, 94, 32);
            $pdf->Cell(65, 6, 'Invoice Number:', 0, 0);
            $pdf->SetFont('helvetica', '', 10);
            $pdf->SetTextColor(0, 0, 0);
            $pdf->Cell(0, 6, $invoice_number, 0, 1);
        }

        $pdf->SetX(20);
        $pdf->SetFont('helvetica', 'B', 10);
        $pdf->SetTextColor(27, 94, 32);
        $pdf->Cell(65, 6, 'Payment Date:', 0, 0);
        $pdf->SetFont('helvetica', '', 10);
        $pdf->SetTextColor(0, 0, 0);
        $pdf->Cell(0, 6, date('F j, Y', strtotime($payment_date)), 0, 1);

        $pdf->SetX(20);
        $pdf->SetFont('helvetica', 'B', 10);
        $pdf->SetTextColor(27, 94, 32);
        $pdf->Cell(65, 6, 'Generated:', 0, 0);
        $pdf->SetFont('helvetica', '', 10);
        $pdf->SetTextColor(0, 0, 0);
        $pdf->Cell(0, 6, date('F j, Y h:i A'), 0, 1);

        // Payment Summary Section
        $pdf->SetY($pdf->GetY() + 15);
        $pdf->SetFont('helvetica', 'B', 14);
        $pdf->SetTextColor(27, 94, 32);
        $pdf->Cell(0, 8, 'Payment Summary', 0, 1, 'L');
        $pdf->SetTextColor(0, 0, 0);

        // Payment summary table with header
        $pdf->SetFillColor(27, 94, 32);
        $pdf->SetTextColor(255, 255, 255);
        $pdf->SetFont('helvetica', 'B', 11);
        $pdf->Cell(120, 10, 'Description', 1, 0, 'L', true);
        $pdf->Cell(60, 10, 'Amount', 1, 1, 'R', true);

        $pdf->SetTextColor(0, 0, 0);
        $pdf->SetFont('helvetica', '', 10);

        // Interest Paid
        $pdf->SetFillColor(248, 248, 248);
        $pdf->Cell(120, 10, 'Interest Paid', 1, 0, 'L', true);
        $pdf->SetFont('helvetica', 'B', 10);
        $pdf->SetTextColor(245, 124, 0);
        $pdf->Cell(60, 10, chr(0x20) . chr(0xB1) . number_format($interest_paid, 2), 1, 1, 'R', true);

        // Principal Paid
        $pdf->SetFont('helvetica', '', 10);
        $pdf->SetTextColor(0, 0, 0);
        $pdf->SetFillColor(255, 255, 255);
        $pdf->Cell(120, 10, 'Principal Paid', 1, 0, 'L', true);
        $pdf->SetFont('helvetica', 'B', 10);
        $pdf->Cell(60, 10, chr(0x20) . chr(0xB1) . number_format($principal_paid, 2), 1, 1, 'R', true);

        // Total Amount Paid
        $pdf->SetFont('helvetica', 'B', 11);
        $pdf->SetFillColor(232, 245, 233);
        $pdf->SetTextColor(0, 0, 0);
        $pdf->Cell(120, 12, 'Total Amount Paid', 1, 0, 'L', true);
        $pdf->SetFont('helvetica', 'B', 12);
        $pdf->SetTextColor(27, 94, 32);
        $pdf->Cell(60, 12, chr(0x20) . chr(0xB1) . number_format($amount_paid, 2), 1, 1, 'R', true);

        // Payment Information Section
        $pdf->SetY($pdf->GetY() + 10);
        $pdf->SetFont('helvetica', 'B', 14);
        $pdf->SetTextColor(27, 94, 32);
        $pdf->Cell(0, 8, 'Payment Information', 0, 1, 'L');
        $pdf->SetTextColor(0, 0, 0);

        $pdf->SetFillColor(248, 248, 248);
        $pdf->SetFont('helvetica', '', 10);
        $pdf->Rect(15, $pdf->GetY(), 180, 16, 'F');

        $y_pos = $pdf->GetY() + 5;
        $pdf->SetXY(20, $y_pos);
        $pdf->SetFont('helvetica', 'B', 10);
        $pdf->Cell(60, 6, 'Payment Type:', 0, 0);
        $pdf->SetFont('helvetica', '', 10);
        $pdf->Cell(0, 6, ucfirst($payment_type), 0, 1);

        // Important Notice
        $pdf->SetY($pdf->GetY() + 10);
        $pdf->SetFillColor(255, 243, 224);
        $pdf->SetDrawColor(245, 124, 0);
        $pdf->SetLineWidth(1);
        $pdf->Rect(15, $pdf->GetY(), 180, 25, 'FD');

        $pdf->SetXY(20, $pdf->GetY() + 5);
        $pdf->SetFont('helvetica', 'B', 11);
        $pdf->SetTextColor(230, 81, 0);
        $pdf->Cell(0, 6, 'Important Notice', 0, 1);

        $pdf->SetX(20);
        $pdf->SetFont('helvetica', '', 9);
        $pdf->MultiCell(170, 5, 'Keep this receipt for your financial records. This serves as proof of payment for your loan. If you have any questions, please contact our support team.', 0, 'L');

        // Footer
        $pdf->SetY(270);
        $pdf->SetFont('helvetica', 'I', 8);
        $pdf->SetTextColor(100, 100, 100);
        $pdf->Cell(0, 5, 'CLDD Loan Program | Email: cycloancldd@gmail.com | Phone: (123) 456-7890', 0, 1, 'C');
        $pdf->Cell(0, 5, 'Generated on ' . date('F j, Y \a\t h:i A'), 0, 1, 'C');
        $pdf->SetTextColor(0, 0, 0);

        // Save PDF to temporary file
        $pdf_filename = $invoice_number ? "payment_receipt_{$invoice_number}_" . date('YmdHis') . '.pdf' : 'payment_receipt_' . date('YmdHis') . '.pdf';
        $pdf_path = sys_get_temp_dir() . '/' . $pdf_filename;
        $pdf->Output($pdf_path, 'F');

        // Attach PDF to email
        $mail->addAttachment($pdf_path, $pdf_filename);

        // Send email
        $mail->send();

        // Delete temporary PDF file
        if (file_exists($pdf_path)) {
            unlink($pdf_path);
        }

        return true;
    } catch (Exception $e) {
        error_log("Payment email failed: {$mail->ErrorInfo}", 3, 'errors.log');
        // Attempt to delete temporary PDF file if it exists
        if (isset($pdf_path) && file_exists($pdf_path)) {
            unlink($pdf_path);
        }
        return false;
    }
}
?>