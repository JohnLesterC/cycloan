<?php
session_start();
require "CYCLOAN_db.php";

// Start output buffering to catch stray output
ob_start();

// Set JSON content type
header('Content-Type: application/json');

// Ensure user is authorized (admin1, admin2, or superadmin)
if (!isset($_SESSION['email']) || !in_array($_SESSION['role'], ['admin1', 'admin2', 'superadmin'])) {
    ob_end_clean();
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized access']);
    exit;
}

// Ensure $conn is a MySQLi instance
if (!($conn instanceof mysqli)) {
    error_log("Database connection is not a MySQLi instance", 3, 'errors.log');
    ob_end_clean();
    echo json_encode(['status' => 'error', 'message' => 'Database connection error']);
    exit;
}

// Check if connection is successful
if (mysqli_connect_errno()) {
    error_log("Database connection error: " . mysqli_connect_error(), 3, 'errors.log');
    ob_end_clean();
    echo json_encode(['status' => 'error', 'message' => 'Database connection failed']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    ob_end_clean();
    echo json_encode(['status' => 'error', 'message' => 'Invalid request method']);
    exit;
}

// Validate inputs from JS
$application_id = trim($_POST['application_id'] ?? '');
$amount = filter_input(INPUT_POST, 'amount', FILTER_VALIDATE_FLOAT);
$duration = filter_input(INPUT_POST, 'duration', FILTER_VALIDATE_INT);
$frequency = $_POST['frequency'] ?? '';
$interest_rate = filter_input(INPUT_POST, 'interest_rate', FILTER_VALIDATE_FLOAT) ?: 6.0;
$payment_schedule_json = $_POST['payment_schedule'] ?? '';
$total_interest = filter_input(INPUT_POST, 'total_interest', FILTER_VALIDATE_FLOAT);
$total_principal = filter_input(INPUT_POST, 'total_principal', FILTER_VALIDATE_FLOAT) ?: $amount;
$payment_amount = filter_input(INPUT_POST, 'payment_amount', FILTER_VALIDATE_FLOAT);
$remaining_balance = filter_input(INPUT_POST, 'remaining_balance', FILTER_VALIDATE_FLOAT);

// Debug logging - log all incoming data
error_log("CREATE LOAN DEBUG - Incoming POST data: " . json_encode($_POST), 3, 'errors.log');
error_log("CREATE LOAN DEBUG - Parsed values - application_id: $application_id, amount: $amount, duration: $duration, frequency: $frequency, total_interest: $total_interest, payment_amount: $payment_amount", 3, 'errors.log');

if (!$payment_schedule_json || json_decode($payment_schedule_json, true) === null) {
    ob_end_clean();
    error_log("Invalid payment schedule JSON: " . $payment_schedule_json, 3, 'errors.log');
    echo json_encode(['status' => 'error', 'message' => 'Invalid payment schedule format']);
    exit;
}
$payment_schedule = json_decode($payment_schedule_json, true);

// Improved validation with detailed error messages
$validation_errors = [];

if (empty($application_id)) {
    $validation_errors[] = "Missing or invalid application_id";
}
if ($amount === false || $amount < 10000) {
    $validation_errors[] = "Amount must be at least ₱10,000 (received: $amount)";
}
if ($duration === false || $duration < 1 || $duration > 60) {
    $validation_errors[] = "Duration must be between 1 and 60 months (received: $duration)";
}
if (!in_array($frequency, ['monthly', 'quarterly', 'semi-annually', 'annually'])) {
    $validation_errors[] = "Invalid payment frequency (received: $frequency)";
}
if (empty($payment_schedule)) {
    $validation_errors[] = "Payment schedule is empty";
}
if ($total_interest === false) {
    $validation_errors[] = "Invalid total interest value";
}
if ($payment_amount === false || $payment_amount <= 0) {
    $validation_errors[] = "Invalid payment amount (received: $payment_amount)";
}

if (!empty($validation_errors)) {
    ob_end_clean();
    $error_message = implode(", ", $validation_errors);
    error_log("CREATE LOAN VALIDATION FAILED: " . $error_message, 3, 'errors.log');
    echo json_encode(['status' => 'error', 'message' => 'Invalid input data: ' . $error_message]);
    exit;
}

// Validate application exists and is Active
$stmt = $conn->prepare("SELECT user_id, status FROM loan_applications WHERE application_id = ?");
if (!$stmt) {
    ob_end_clean();
    error_log("Prepare failed: " . $conn->error, 3, 'errors.log');
    echo json_encode(['status' => 'error', 'message' => 'Database error: Unable to validate application']);
    exit;
}
$stmt->bind_param("s", $application_id);
$stmt->execute();
$result = $stmt->get_result();
$application = $result->fetch_assoc();
$stmt->close();

if (!$application || $application['status'] !== 'Active') {
    ob_end_clean();
    echo json_encode(['status' => 'error', 'message' => 'Invalid or inactive application']);
    exit;
}

// Check if loan already exists for this application
$stmt = $conn->prepare("SELECT loan_id FROM loans WHERE application_id = ?");
if (!$stmt) {
    ob_end_clean();
    error_log("Prepare failed: " . $conn->error, 3, 'errors.log');
    echo json_encode(['status' => 'error', 'message' => 'Database error: Unable to check existing loans']);
    exit;
}
$stmt->bind_param("s", $application_id);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows > 0) {
    ob_end_clean();
    echo json_encode(['status' => 'error', 'message' => 'Loan already exists for this application']);
    $stmt->close();
    exit;
}
$stmt->close();

// Start transaction
$conn->begin_transaction();

try {
    $final_loan_amount = $total_principal + $total_interest;
    // Set remaining balance to the full amount since no payments have been made yet
    $initial_remaining_balance = $final_loan_amount;

    // Insert into loans table
    $stmt = $conn->prepare("
        INSERT INTO loans (
            application_id, user_id, amount, duration, interest_rate, 
            monthly_payment, remaining_balance, total_paid, status, created_at, 
            payment_frequency, payment_amount, total_interest, total_principal, updated_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, 0.00, 'active', NOW(), ?, ?, ?, ?, NOW())
    ");
    if (!$stmt) {
        throw new Exception("Prepare failed for loans: " . $conn->error);
    }
    $stmt->bind_param(
        "siddsdddsdd",
        $application_id,
        $application['user_id'],
        $amount,
        $duration,
        $interest_rate,
        $payment_amount,  // monthly_payment
        $initial_remaining_balance,  // remaining_balance = full loan amount
        $frequency,
        $payment_amount,
        $total_interest,
        $total_principal
    );
    $stmt->execute();
    $loan_id = $conn->insert_id;
    $stmt->close();

    // Insert payment schedules
    $stmt = $conn->prepare("
        INSERT INTO payment_schedules (
            loan_id, due_date, amount, status, paid_at, amount_paid, 
            payment_date_actual, interest_amount, principal_amount, 
            updated_at, interest_paid, principal_paid, late_fee, late_fee_paid
        ) VALUES (?, ?, ?, 'pending', NULL, 0.00, NULL, ?, ?, NOW(), 0.00, 0.00, 0.00, 0.00)
    ");
    if (!$stmt) {
        throw new Exception("Prepare failed for payment_schedules: " . $conn->error);
    }
    foreach ($payment_schedule as $item) {
        $stmt->bind_param(
            "isddd",
            $loan_id,
            $item['due_date'],
            $item['amount'],
            $item['interest_amount'],
            $item['principal_amount']
        );
        $stmt->execute();
    }
    $stmt->close();

    // Update loan application with final amount
    $stmt = $conn->prepare("UPDATE loan_applications SET final_loan_amount = ?, updated_at = NOW() WHERE application_id = ?");
    if (!$stmt) {
        throw new Exception("Prepare failed for loan_applications: " . $conn->error);
    }
    $stmt->bind_param("ds", $final_loan_amount, $application_id);
    $stmt->execute();
    $stmt->close();

    // Log activity (dynamic table based on role)
    $admin_table = ($_SESSION['role'] === 'superadmin') ? 'superadmins' : ($_SESSION['role'] === 'admin1' ? 'admin1' : 'admin2');
    $description = "Created loan for application ID $application_id with final amount ₱" . number_format($final_loan_amount, 2);
    $stmt = $conn->prepare("
        INSERT INTO activity_logs (user_id, user_role, admin_email, action_type, module, description, affected_id)
        SELECT id, ?, email, 'create', 'loan', ?, 0 FROM $admin_table WHERE email = ?
    ");
    if (!$stmt) {
        throw new Exception("Prepare failed for activity_logs: " . $conn->error);
    }
    $stmt->bind_param("sss", $_SESSION['role'], $description, $_SESSION['email']);
    $stmt->execute();
    $stmt->close();

    $conn->commit();

    // Send email notification with PDF invoice to the user (non-blocking)
    try {
        // Get user's email and name
        $stmt = $conn->prepare("SELECT email, first_name, last_name FROM users1 WHERE id = ?");
        if ($stmt) {
            $stmt->bind_param("i", $application['user_id']);
            $stmt->execute();
            $user_result = $stmt->get_result();
            $user_data = $user_result->fetch_assoc();
            $stmt->close();

            if ($user_data && !empty($user_data['email'])) {
                $user_name = trim($user_data['first_name'] . ' ' . $user_data['last_name']);

                // Get first payment date from payment schedule
                $first_payment_date = !empty($payment_schedule) ? $payment_schedule[0]['due_date'] : date('Y-m-d', strtotime('+1 month'));

                // Generate PDF Invoice using TCPDF
                $pdf_filename = '';
                if (file_exists(__DIR__ . '/tcpdf/tcpdf.php')) {
                    require_once __DIR__ . '/tcpdf/tcpdf.php';

                    $pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);

                    // Set document information
                    $pdf->SetCreator('CYCLOAN - CLDD Loan Program');
                    $pdf->SetAuthor('CLDD Loan Program');
                    $pdf->SetTitle('Loan Payment Invoice - ' . $application_id);
                    $pdf->SetSubject('Loan Payment Plan Invoice');

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

                    // Header with green gradient background (matching Admin 2 style)
                    // Create gradient effect manually with rectangles
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
                    $pdf->Cell(0, 12, 'LOAN PAYMENT INVOICE', 0, 1, 'C');
                    $pdf->SetFont('helvetica', '', 12);
                    $pdf->SetX(15);
                    $pdf->Cell(0, 8, 'CLDD Loan Program', 0, 1, 'C');
                    $pdf->SetFont('helvetica', '', 9);
                    $pdf->SetX(15);
                    $pdf->Cell(0, 6, 'Email: scycloan@gmail.com | Phone: 0981-303-8698', 0, 1, 'C');

                    // Reset text color
                    $pdf->SetTextColor(0, 0, 0);

                    // Invoice details box (matching Admin 2 highlight-box style)
                    $pdf->SetY(55);
                    $pdf->SetFont('helvetica', 'B', 14);
                    $pdf->SetTextColor(27, 94, 32);
                    $pdf->Cell(0, 8, 'Invoice Details', 0, 1, 'L');
                    $pdf->SetTextColor(0, 0, 0);

                    $pdf->SetFillColor(232, 245, 233); // #e8f5e9 - matching highlight-box
                    $pdf->SetDrawColor(46, 125, 50); // #2e7d32 - green border
                    $pdf->SetLineWidth(1);
                    $pdf->Rect(15, $pdf->GetY(), 180, 40, 'FD');

                    $y_position = $pdf->GetY() + 6;
                    $pdf->SetFont('helvetica', '', 10);

                    $pdf->SetXY(20, $y_position);
                    $pdf->SetFont('helvetica', 'B', 10);
                    $pdf->SetTextColor(27, 94, 32);
                    $pdf->Cell(65, 6, 'Application ID:', 0, 0);
                    $pdf->SetFont('helvetica', '', 10);
                    $pdf->SetTextColor(0, 0, 0);
                    $pdf->Cell(0, 6, $application_id, 0, 1);

                    $pdf->SetX(20);
                    $pdf->SetFont('helvetica', 'B', 10);
                    $pdf->SetTextColor(27, 94, 32);
                    $pdf->Cell(65, 6, 'Loan ID:', 0, 0);
                    $pdf->SetFont('helvetica', '', 10);
                    $pdf->SetTextColor(0, 0, 0);
                    $pdf->Cell(0, 6, 'LOAN-' . str_pad($loan_id, 6, '0', STR_PAD_LEFT), 0, 1);

                    $pdf->SetX(20);
                    $pdf->SetFont('helvetica', 'B', 10);
                    $pdf->SetTextColor(27, 94, 32);
                    $pdf->Cell(65, 6, 'Borrower Name:', 0, 0);
                    $pdf->SetFont('helvetica', '', 10);
                    $pdf->SetTextColor(0, 0, 0);
                    $pdf->Cell(0, 6, $user_name, 0, 1);

                    $pdf->SetX(20);
                    $pdf->SetFont('helvetica', 'B', 10);
                    $pdf->SetTextColor(27, 94, 32);
                    $pdf->Cell(65, 6, 'Invoice Date:', 0, 0);
                    $pdf->SetFont('helvetica', '', 10);
                    $pdf->SetTextColor(0, 0, 0);
                    $pdf->Cell(0, 6, date('F j, Y'), 0, 1);

                    $pdf->SetX(20);
                    $pdf->SetFont('helvetica', 'B', 10);
                    $pdf->SetTextColor(27, 94, 32);
                    $pdf->Cell(65, 6, 'Status:', 0, 0);
                    $pdf->SetFont('helvetica', 'B', 10);
                    $pdf->SetTextColor(46, 125, 50);
                    $pdf->Cell(0, 6, 'Active', 0, 1);

                    // Loan Summary
                    $pdf->SetY($pdf->GetY() + 12);
                    $pdf->SetFont('helvetica', 'B', 14);
                    $pdf->SetTextColor(27, 94, 32);
                    $pdf->Cell(0, 8, 'Loan Summary', 0, 1, 'L');

                    // Table header (matching Admin 2 green theme)
                    $pdf->SetFillColor(27, 94, 32);
                    $pdf->SetTextColor(255, 255, 255);
                    $pdf->SetFont('helvetica', 'B', 10);
                    $pdf->SetLineWidth(0.3);
                    $pdf->Cell(90, 9, 'Description', 1, 0, 'L', true);
                    $pdf->Cell(90, 9, 'Amount', 1, 1, 'R', true);

                    // Table content
                    $pdf->SetTextColor(0, 0, 0);
                    $pdf->SetFont('helvetica', '', 10);
                    $pdf->SetFillColor(248, 248, 248);

                    $pdf->Cell(90, 8, 'Principal Amount', 1, 0, 'L', true);
                    $pdf->Cell(90, 8, 'PHP ' . number_format($total_principal, 2), 1, 1, 'R', true);

                    $pdf->Cell(90, 8, 'Total Interest (' . $interest_rate . '% per annum)', 1, 0, 'L');
                    $pdf->Cell(90, 8, 'PHP ' . number_format($total_interest, 2), 1, 1, 'R');

                    $pdf->SetFont('helvetica', 'B', 11);
                    $pdf->SetFillColor(232, 245, 233); // Light green matching highlight-box
                    $pdf->SetTextColor(27, 94, 32);
                    $pdf->Cell(90, 9, 'Total Loan Amount', 1, 0, 'L', true);
                    $pdf->Cell(90, 9, 'PHP ' . number_format($final_loan_amount, 2), 1, 1, 'R', true);

                    // Loan Terms
                    $pdf->SetY($pdf->GetY() + 10);
                    $pdf->SetFont('helvetica', 'B', 14);
                    $pdf->SetTextColor(27, 94, 32);
                    $pdf->Cell(0, 8, 'Loan Terms', 0, 1, 'L');

                    $pdf->SetFont('helvetica', '', 10);
                    $pdf->SetTextColor(0, 0, 0);
                    $pdf->SetFillColor(248, 248, 248);

                    $pdf->Cell(90, 8, 'Loan Duration', 1, 0, 'L', true);
                    $pdf->Cell(90, 8, $duration . ' months', 1, 1, 'R', true);

                    $pdf->Cell(90, 8, 'Payment Frequency', 1, 0, 'L');
                    $pdf->Cell(90, 8, ucfirst($frequency), 1, 1, 'R');

                    $pdf->Cell(90, 8, 'Payment Amount', 1, 0, 'L', true);
                    $pdf->SetFont('helvetica', 'B', 10);
                    $pdf->SetTextColor(27, 94, 32);
                    $pdf->Cell(90, 8, 'PHP ' . number_format($payment_amount, 2), 1, 1, 'R', true);

                    $pdf->SetFont('helvetica', '', 10);
                    $pdf->SetTextColor(0, 0, 0);
                    $pdf->Cell(90, 8, 'First Payment Date', 1, 0, 'L');
                    $pdf->Cell(90, 8, date('F j, Y', strtotime($first_payment_date)), 1, 1, 'R');

                    // Payment Schedule
                    $pdf->SetY($pdf->GetY() + 10);
                    $pdf->SetFont('helvetica', 'B', 14);
                    $pdf->SetTextColor(27, 94, 32);
                    $pdf->Cell(0, 8, 'Payment Schedule', 0, 1, 'L');

                    // Payment schedule table header
                    $pdf->SetFillColor(27, 94, 32);
                    $pdf->SetTextColor(255, 255, 255);
                    $pdf->SetFont('helvetica', 'B', 9);
                    $pdf->Cell(15, 8, '#', 1, 0, 'C', true);
                    $pdf->Cell(35, 8, 'Due Date', 1, 0, 'C', true);
                    $pdf->Cell(40, 8, 'Principal', 1, 0, 'R', true);
                    $pdf->Cell(40, 8, 'Interest', 1, 0, 'R', true);
                    $pdf->Cell(50, 8, 'Payment Amount', 1, 1, 'R', true);

                    // Payment schedule rows
                    $pdf->SetTextColor(0, 0, 0);
                    $pdf->SetFont('helvetica', '', 8);
                    $counter = 1;
                    foreach ($payment_schedule as $payment) {
                        $fill = ($counter % 2 == 0);
                        $pdf->SetFillColor(248, 248, 248);

                        $pdf->Cell(15, 7, $counter, 1, 0, 'C', $fill);
                        $pdf->Cell(35, 7, date('M j, Y', strtotime($payment['due_date'])), 1, 0, 'C', $fill);
                        $pdf->Cell(40, 7, 'PHP ' . number_format($payment['principal_amount'], 2), 1, 0, 'R', $fill);
                        $pdf->Cell(40, 7, 'PHP ' . number_format($payment['interest_amount'], 2), 1, 0, 'R', $fill);
                        $pdf->Cell(50, 7, 'PHP ' . number_format($payment['amount'], 2), 1, 1, 'R', $fill);

                        $counter++;

                        // Add new page if needed
                        if ($pdf->GetY() > 260) {
                            $pdf->AddPage();

                            // Re-add header on new page
                            $pdf->SetFillColor(27, 94, 32);
                            $pdf->SetTextColor(255, 255, 255);
                            $pdf->SetFont('helvetica', 'B', 9);
                            $pdf->Cell(15, 8, '#', 1, 0, 'C', true);
                            $pdf->Cell(35, 8, 'Due Date', 1, 0, 'C', true);
                            $pdf->Cell(40, 8, 'Principal', 1, 0, 'R', true);
                            $pdf->Cell(40, 8, 'Interest', 1, 0, 'R', true);
                            $pdf->Cell(50, 8, 'Payment Amount', 1, 1, 'R', true);

                            $pdf->SetTextColor(0, 0, 0);
                            $pdf->SetFont('helvetica', '', 8);
                        }
                    }

                    // Important reminders box (matching Admin 2 warning box style)
                    $pdf->SetY($pdf->GetY() + 10);
                    $pdf->SetFillColor(255, 243, 224); // #fff3e0 - light orange
                    $pdf->SetDrawColor(245, 124, 0); // #f57c00 - orange border
                    $pdf->SetLineWidth(1);
                    $pdf->Rect(15, $pdf->GetY(), 180, 35, 'FD');

                    $pdf->SetXY(20, $pdf->GetY() + 5);
                    $pdf->SetFont('helvetica', 'B', 11);
                    $pdf->SetTextColor(245, 124, 0);
                    $pdf->Cell(0, 6, 'Important Reminders', 0, 1);

                    $pdf->SetX(20);
                    $pdf->SetFont('helvetica', '', 9);
                    $pdf->SetTextColor(0, 0, 0);
                    $pdf->MultiCell(170, 5, "• Payments are due on the specified dates to avoid late fees\n• You can make early payments at any time\n• Track your loan progress in your dashboard\n• Contact support for any payment concerns", 0, 'L');

                    // Footer
                    $pdf->SetY(-25);
                    $pdf->SetFont('helvetica', 'I', 9);
                    $pdf->SetTextColor(100, 100, 100);
                    $pdf->MultiCell(0, 5, 'This is a computer-generated invoice. For inquiries, contact us at scycloan@gmail.com or call 0981-303-8698 / 545-6789 loc 8018-19.', 0, 'C');

                    $pdf->SetFont('helvetica', '', 8);
                    $pdf->SetTextColor(150, 150, 150);
                    $pdf->Cell(0, 5, 'CLDD Loan Program • ' . date('Y') . ' • All Rights Reserved', 0, 1, 'C');
                    $pdf->Cell(0, 5, 'Generated on ' . date('F j, Y \a\t g:i A'), 0, 1, 'C');
                    $pdf->MultiCell(0, 5, 'Note: This is a computer-generated invoice. Please ensure timely payments to avoid late fees. For any inquiries, please contact our support team at scycloan@gmail.com or call 0981-303-8698 / 545-6789 loc 8018-19.', 0, 'L');

                    // Footer
                    $pdf->SetY(-20);
                    $pdf->SetFont('helvetica', '', 8);
                    $pdf->SetTextColor(150, 150, 150);
                    $pdf->Cell(0, 5, 'CLDD Loan Program - ' . date('Y') . ' - All Rights Reserved', 0, 1, 'C');
                    $pdf->Cell(0, 5, 'Generated on ' . date('F j, Y \a\t g:i A'), 0, 1, 'C');

                    // Save PDF to temp file
                    $pdf_filename = __DIR__ . '/uploads/invoices/invoice_' . $application_id . '_' . time() . '.pdf';

                    // Create directory if it doesn't exist
                    if (!is_dir(__DIR__ . '/uploads/invoices')) {
                        mkdir(__DIR__ . '/uploads/invoices', 0755, true);
                    }

                    $pdf->Output($pdf_filename, 'F');
                }

                // Generate professional email using Admin 2 template style with improved styling
                $emailContent = "
                    <div style='background: linear-gradient(135deg, #e8f5e9 0%, #f1f8e9 100%); border-radius: 8px; padding: 20px; margin: 20px 0; text-align: center; border: 2px solid #2e7d32;'>
                        <h2 style='color: #1b5e20; margin: 0 0 10px 0; font-size: 24px;'>🎉 Loan Successfully Created!</h2>
                        <p style='font-size: 16px; color: #2e7d32; margin: 0;'><strong>Your payment plan is now active</strong></p>
                    </div>
                    
                    <div class='status-box' style='background-color: #e8f5e9; border: 2px solid #2e7d32; border-radius: 8px; padding: 20px; margin: 25px 0;'>
                        <div style='text-align: center; margin-bottom: 15px;'>
                            <span style='background-color: #2e7d32; color: white; padding: 8px 20px; border-radius: 20px; font-size: 14px; font-weight: bold;'>✅ ACTIVE</span>
                        </div>
                        <table style='width: 100%; border-collapse: collapse;'>
                            <tr>
                                <td style='padding: 8px 0; color: #666; font-size: 14px;'>Application ID:</td>
                                <td style='padding: 8px 0; text-align: right; font-weight: bold; color: #1b5e20; font-size: 16px;'>" . htmlspecialchars($application_id) . "</td>
                            </tr>
                            <tr>
                                <td style='padding: 8px 0; color: #666; font-size: 14px;'>Loan ID:</td>
                                <td style='padding: 8px 0; text-align: right; font-weight: bold; color: #1b5e20; font-size: 16px;'>LOAN-" . str_pad($loan_id, 6, '0', STR_PAD_LEFT) . "</td>
                            </tr>
                        </table>
                    </div>
                    
                    <h3 style='color: #1b5e20; border-bottom: 3px solid #2e7d32; padding-bottom: 10px; margin: 30px 0 20px 0;'>
                        <span style='font-size: 20px;'>📊</span> Loan Summary
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
                                <td style='padding: 12px 15px; border-bottom: 1px solid #e0e0e0;'><strong>Principal Amount</strong></td>
                                <td style='padding: 12px 15px; text-align: right; border-bottom: 1px solid #e0e0e0; font-family: monospace;'>₱" . number_format($total_principal, 2) . "</td>
                            </tr>
                            <tr style='background-color: white;'>
                                <td style='padding: 12px 15px; border-bottom: 1px solid #e0e0e0;'><strong>Total Interest</strong></td>
                                <td style='padding: 12px 15px; text-align: right; border-bottom: 1px solid #e0e0e0; font-family: monospace; color: #f57c00;'>₱" . number_format($total_interest, 2) . "</td>
                            </tr>
                            <tr style='background-color: #e8f5e9;'>
                                <td style='padding: 15px; border-bottom: 2px solid #2e7d32;'><strong style='font-size: 16px;'>Total Loan Amount</strong></td>
                                <td style='padding: 15px; text-align: right; border-bottom: 2px solid #2e7d32; font-family: monospace; color: #1b5e20; font-weight: bold; font-size: 18px;'>₱" . number_format($final_loan_amount, 2) . "</td>
                            </tr>
                        </tbody>
                    </table>
                    
                    <h3 style='color: #1b5e20; border-bottom: 3px solid #2e7d32; padding-bottom: 10px; margin: 30px 0 20px 0;'>
                        <span style='font-size: 20px;'>📋</span> Payment Plan Details
                    </h3>
                    
                    <div style='background-color: #f8f8f8; border-radius: 8px; padding: 20px; margin: 20px 0;'>
                        <table style='width: 100%; border-collapse: collapse;'>
                            <tr>
                                <td style='padding: 10px 0; color: #666;'>
                                    <span style='font-size: 18px;'>💰</span> <strong>Payment Amount:</strong>
                                </td>
                                <td style='padding: 10px 0; text-align: right; color: #1b5e20; font-size: 20px; font-weight: bold; font-family: monospace;'>
                                    ₱" . number_format($payment_amount, 2) . "
                                </td>
                            </tr>
                            <tr>
                                <td style='padding: 10px 0; color: #666;'>
                                    <span style='font-size: 18px;'>📅</span> <strong>Payment Frequency:</strong>
                                </td>
                                <td style='padding: 10px 0; text-align: right; font-size: 16px; font-weight: 600;'>
                                    " . ucfirst($frequency) . "
                                </td>
                            </tr>
                            <tr>
                                <td style='padding: 10px 0; color: #666;'>
                                    <span style='font-size: 18px;'>⏱️</span> <strong>Loan Duration:</strong>
                                </td>
                                <td style='padding: 10px 0; text-align: right; font-size: 16px; font-weight: 600;'>
                                    {$duration} Months
                                </td>
                            </tr>
                            <tr>
                                <td style='padding: 10px 0; color: #666;'>
                                    <span style='font-size: 18px;'>📈</span> <strong>Interest Rate:</strong>
                                </td>
                                <td style='padding: 10px 0; text-align: right; font-size: 16px; font-weight: 600;'>
                                    {$interest_rate}% per annum
                                </td>
                            </tr>
                            <tr style='background-color: #fff3e0; border-radius: 4px;'>
                                <td style='padding: 15px 10px; color: #e65100;'>
                                    <span style='font-size: 18px;'>🗓️</span> <strong>First Payment Date:</strong>
                                </td>
                                <td style='padding: 15px 10px; text-align: right; color: #e65100; font-size: 18px; font-weight: bold;'>
                                    " . date('F j, Y', strtotime($first_payment_date)) . "
                                </td>
                            </tr>
                        </table>
                    </div>
                    
                    <div style='background: linear-gradient(135deg, #e3f2fd 0%, #bbdefb 100%); border-left: 5px solid #1976d2; border-radius: 8px; padding: 20px; margin: 25px 0;'>
                        <h4 style='color: #0d47a1; margin: 0 0 10px 0; font-size: 18px;'>
                            📎 Invoice Attached
                        </h4>
                        <p style='margin: 0; color: #1565c0; line-height: 1.6;'>
                            Your detailed payment invoice PDF is attached to this email. Please <strong>download and save it</strong> for your records. The invoice contains your complete payment schedule with all due dates and amounts.
                        </p>
                    </div>
                    
                    <p><strong>�📋 What's Next?</strong></p>
                    <ul style='line-height: 1.8;'>
                        <li>Review your attached invoice for the complete payment schedule</li>
                        <li>Mark your calendar for your first payment date: <strong>" . date('F j, Y', strtotime($first_payment_date)) . "</strong></li>
                        <li>You can view and manage your loan anytime through your dashboard</li>
                        <li>Set up payment reminders to ensure you never miss a due date</li>
                    </ul>
                    
                    <a href='user_dashboard.php' class='button'>View Payment Schedule</a>
                    
                    <div class='highlight-box' style='background-color: #fff3e0; border-left-color: #f57c00; margin-top: 20px;'>
                        <p><strong>💡 Important Reminders:</strong></p>
                        <ul style='margin: 5px 0; padding-left: 20px;'>
                            <li>Payments are due on the specified dates to avoid late fees</li>
                            <li>You can make early payments at any time</li>
                            <li>Track your loan progress and payment history in your dashboard</li>
                            <li>Keep your invoice for your financial records</li>
                        </ul>
                    </div>
                    
                    <p>If you have any questions about your loan or payment schedule, please do not hesitate to contact our support team.</p>
                    <p>Thank you for choosing CLDD Loan Program!</p>
                    <p>Best regards,<br><strong>The CLDD Loan Support Team</strong></p>
                ";

                // Prepare dynamic variables for email template
                $year = date('Y');

                // Generate complete email template (matching Admin 2 style)
                $emailBody = <<<HTML
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
        .email-body p { margin: 15px 0; }
        .email-body strong { color: #1b5e20; }
        .email-body ul { margin: 10px 0; padding-left: 25px; }
        .email-body ul li { margin: 8px 0; }
        .highlight-box { background-color: #e8f5e9; border-left: 4px solid #2e7d32; padding: 15px; margin: 20px 0; border-radius: 4px; }
        .button { display: inline-block; padding: 12px 30px; background-color: #2e7d32; color: #ffffff; text-decoration: none; border-radius: 5px; margin: 20px 0; font-weight: 600; }
        .button:hover { background-color: #1b5e20; }
        .email-footer { background-color: #f8f8f8; padding: 20px; text-align: center; font-size: 12px; color: #666666; border-top: 1px solid #e0e0e0; }
        .email-footer p { margin: 5px 0; }
    </style>
</head>
<body>
    <div class='email-container'>
        <div class='email-header'>
            <img src='assets/Main-Logo.png' alt='CLDD Logo' style='max-width: 200px; height: auto; display: block; margin: 0 auto;'>
        </div>
        <div class='email-body'>
            <p>Hello <strong>{$user_name}</strong>,</p>
            {$emailContent}
        </div>
        <div class='email-footer'>
            <p><strong>CLDD Loan Support Team</strong></p>
            <p>Email: scycloan@gmail.com | Phone: 0981-303-8698</p>
            <p>© {$year} CLDD Loan Program. All rights reserved.</p>
            <p style='margin-top: 10px; font-size: 11px;'>This is an automated message. Please do not reply directly to this email.</p>
        </div>
    </div>
</body>
</html>
HTML;

                // Prepare email subject
                $subject = "Loan Payment Plan Created - Invoice Attached - Application #{$application_id}";

                // Send email using PHPMailer (matching Admin 2 sendEmail function)
                if (file_exists(__DIR__ . '/phpmailer/src/PHPMailer.php')) {
                    require_once __DIR__ . '/phpmailer/src/PHPMailer.php';
                    require_once __DIR__ . '/phpmailer/src/SMTP.php';
                    require_once __DIR__ . '/phpmailer/src/Exception.php';

                    $mail = new PHPMailer\PHPMailer\PHPMailer(true);
                    try {
                        // Server settings (matching Admin 2)
                        $mail->isSMTP();
                        $mail->Host = 'smtp.gmail.com';
                        $mail->SMTPAuth = true;
                        $mail->Username = 'scycloan@gmail.com';
                        $mail->Password = 'xbvo zplr dpme ixxj';
                        $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
                        $mail->Port = 587;
                        $mail->Timeout = 30;
                        $mail->SMTPKeepAlive = true;

                        // Recipients (matching Admin 2)
                        $mail->setFrom('scycloan@gmail.com', 'CYCLOAN Support');
                        $mail->addAddress($user_data['email'], $user_name);

                        // Attach PDF invoice if it was generated
                        if (!empty($pdf_filename) && file_exists($pdf_filename)) {
                            $mail->addAttachment($pdf_filename, 'Loan_Invoice_' . $application_id . '.pdf');
                        }

                        // Content (matching Admin 2)
                        $mail->isHTML(true);
                        $mail->Subject = $subject;
                        $mail->Body = $emailBody;
                        $mail->AltBody = strip_tags(str_replace(['<br>', '<br/>', '<br />'], "\n", $emailBody));

                        $mail->send();
                        error_log("Loan creation email with invoice sent successfully to {$user_data['email']} | Application ID: {$application_id} | Context: Loan Created", 3, 'errors.log');
                    } catch (Throwable $mail_error) {
                        error_log("Email sending failed to {$user_data['email']} | Application ID: {$application_id} | Context: Loan Created | Error: {$mail->ErrorInfo}", 3, 'errors.log');
                    }
                }
            }
        }
    } catch (Throwable $email_error) {
        // Log email error but don't fail - transaction is already committed
        error_log("Email notification error (non-critical): " . $email_error->getMessage(), 3, 'errors.log');
    }

    ob_end_clean();
    echo json_encode(['status' => 'success', 'message' => 'Loan created successfully']);
} catch (Exception $e) {
    $conn->rollback();
    error_log("Error creating loan: " . $e->getMessage(), 3, 'errors.log');
    ob_end_clean();
    echo json_encode(['status' => 'error', 'message' => 'Error creating loan: ' . $e->getMessage()]);
}

$conn->close();
?>