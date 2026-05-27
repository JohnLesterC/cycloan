<?php
session_start();
require "CYCLOAN_db.php";

if (!isset($_SESSION['email'])) {
    header("Location: index.php");
    exit();
}

header('Content-Type: application/json');

$application_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$application_id) {
    echo json_encode(['error' => 'Invalid application ID']);
    exit;
}

// Use the MySQLi connection from CYCLOAN_db.php
if (!$conn || !($conn instanceof mysqli)) {
    echo json_encode(['error' => 'Database connection failed']);
    exit;
}

// Fetch loan details
$stmt = $conn->prepare("
    SELECT l.loan_id, l.application_id, l.amount, l.duration, l.interest_rate, 
           l.payment_amount AS monthly_payment, l.status, l.created_at
    FROM loans l
    WHERE l.application_id = ? AND l.user_id = ?
");

if (!$stmt) {
    echo json_encode(['error' => 'Database error']);
    exit;
}

$stmt->bind_param("ii", $application_id, $_SESSION['user_id']);
$stmt->execute();
$result = $stmt->get_result();
$loan = $result->fetch_assoc();
$stmt->close();

if (!$loan) {
    echo json_encode(['error' => 'Loan not found or you do not have access']);
    exit;
}

// Fetch payment schedule
$stmt = $conn->prepare("
    SELECT ps.payment_id, ps.due_date, ps.amount, ps.status
    FROM payment_schedules ps
    WHERE ps.loan_id = ?
    ORDER BY ps.due_date ASC
");

if (!$stmt) {
    echo json_encode(['error' => 'Database error']);
    exit;
}

$stmt->bind_param("i", $loan['loan_id']);
$stmt->execute();
$result = $stmt->get_result();
$schedule = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

echo json_encode([
    'loan_id' => $loan['loan_id'],
    'amount' => (float) $loan['amount'],
    'duration' => (int) $loan['duration'],
    'interest_rate' => (float) $loan['interest_rate'],
    'monthly_payment' => (float) $loan['monthly_payment'],
    'created_at' => $loan['created_at'],
    'status' => $loan['status'],
    'schedule' => $schedule
]);

$conn->close();
?>