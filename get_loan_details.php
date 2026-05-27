<?php
session_start();
require "CYCLOAN_db.php";

// Start output buffering
ob_start();

// Set JSON content type
header('Content-Type: application/json');

// Ensure user is authorized
if (!isset($_SESSION['email']) || !in_array($_SESSION['role'], ['admin1', 'superadmin'])) {
    ob_end_clean();
    echo json_encode(['error' => 'Unauthorized access']);
    exit;
}

// Ensure $conn is a MySQLi instance
if (!($conn instanceof mysqli)) {
    error_log("Database connection is not a MySQLi instance", 3, 'errors.log');
    ob_end_clean();
    echo json_encode(['error' => 'Database connection error']);
    exit;
}

// Check if connection is successful
if (mysqli_connect_errno()) {
    error_log("Database connection error: " . mysqli_connect_error(), 3, 'errors.log');
    ob_end_clean();
    echo json_encode(['error' => 'Database connection failed']);
    exit;
}

$loan_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$loan_id) {
    ob_end_clean();
    echo json_encode(['error' => 'Invalid loan ID']);
    exit;
}

// Fetch loan details, including loan_applications.loan_id
$stmt = $conn->prepare("
    SELECT l.loan_id, l.application_id, l.user_id, l.amount, l.duration, l.interest_rate, 
           l.payment_frequency, l.payment_amount, l.total_principal, l.total_interest, 
           l.remaining_balance, l.total_paid, l.status, l.created_at,
           la.final_loan_amount AS final_amount, la.loan_id AS application_loan_id
    FROM loans l
    LEFT JOIN loan_applications la ON l.application_id = la.application_id
    WHERE l.loan_id = ?
");
if (!$stmt) {
    error_log("Prepare failed: " . $conn->error, 3, 'errors.log');
    ob_end_clean();
    echo json_encode(['error' => 'Database error']);
    exit;
}
$stmt->bind_param("i", $loan_id);
$stmt->execute();
$result = $stmt->get_result();
$loan = $result->fetch_assoc();
$stmt->close();

if (!$loan) {
    ob_end_clean();
    echo json_encode(['error' => 'Loan not found']);
    exit;
}

// Fetch payment schedule
$stmt = $conn->prepare("
    SELECT payment_id, due_date, 
           CAST(amount AS DECIMAL(12,2)) AS amount,
           CAST(interest_amount AS DECIMAL(12,2)) AS interest_amount,
           CAST(principal_amount AS DECIMAL(12,2)) AS principal_amount,
           CAST(amount_paid AS DECIMAL(12,2)) AS amount_paid,
           CAST(interest_paid AS DECIMAL(12,2)) AS interest_paid,
           CAST(principal_paid AS DECIMAL(12,2)) AS principal_paid,
           status, payment_date_actual
    FROM payment_schedules
    WHERE loan_id = ?
    ORDER BY due_date ASC
");
if (!$stmt) {
    error_log("Prepare failed: " . $conn->error, 3, 'errors.log');
    ob_end_clean();
    echo json_encode(['error' => 'Database error']);
    exit;
}
$stmt->bind_param("i", $loan_id);
$stmt->execute();
$result = $stmt->get_result();
$schedule = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Fetch payment history with admin name and invoice number
$stmt = $conn->prepare("
    SELECT ph.payment_history_id, ph.payment_id, ph.loan_id, ph.amount_paid, ph.interest_paid, 
           ph.principal_paid, ph.payment_type, ph.payment_date, ph.invoice_number,
           CONCAT(COALESCE(a1.first_name, a2.first_name, sa.first_name, 'Unknown'), ' ', 
                  COALESCE(a1.last_name, a2.last_name, sa.last_name, '')) AS admin_name
    FROM payment_history ph
    LEFT JOIN activity_logs al ON ph.payment_id = al.affected_id 
        AND al.action_type = 'payment' AND al.module = 'payment'
    LEFT JOIN admin1 a1 ON al.user_id = a1.id AND al.user_role = 'admin1'
    LEFT JOIN admin2 a2 ON al.user_id = a2.id AND al.user_role = 'admin2'
    LEFT JOIN superadmins sa ON al.user_id = sa.id AND al.user_role = 'superadmin'
    WHERE ph.loan_id = ?
    ORDER BY ph.payment_date ASC
");
if (!$stmt) {
    error_log("Prepare failed: " . $conn->error, 3, 'errors.log');
    ob_end_clean();
    echo json_encode(['error' => 'Database error']);
    exit;
}
$stmt->bind_param("i", $loan_id);
$stmt->execute();
$result = $stmt->get_result();
$payment_history = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Ensure numeric values
foreach ($schedule as &$item) {
    $item['amount'] = (float) $item['amount'];
    $item['interest_amount'] = (float) $item['interest_amount'];
    $item['principal_amount'] = (float) $item['principal_amount'];
    $item['amount_paid'] = (float) ($item['amount_paid'] ?? 0);
    $item['interest_paid'] = (float) ($item['interest_paid'] ?? 0);
    $item['principal_paid'] = (float) ($item['principal_paid'] ?? 0);
}
foreach ($payment_history as &$item) {
    $item['amount_paid'] = (float) ($item['amount_paid'] ?? 0);
    $item['interest_paid'] = (float) ($item['interest_paid'] ?? 0);
    $item['principal_paid'] = (float) ($item['principal_paid'] ?? 0);
    $item['admin_name'] = trim($item['admin_name'] ?? 'Unknown');
    $item['invoice_number'] = $item['invoice_number'] ?? '';
}

$loan['schedule'] = $schedule;
$loan['payment_history'] = $payment_history;

ob_end_clean();
echo json_encode($loan);

$conn->close();
?>