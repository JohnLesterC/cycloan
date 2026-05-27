<?php
session_start();
require "CYCLOAN_db.php";

if (!isset($_SESSION['email'])) {
    header("Location: index.php");
    exit();
}

$loan_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$loan_id) {
    echo json_encode([]);
    exit();
}

$stmt = $conn->prepare("
    SELECT id, payment_date, 
           CAST(amount_due AS DECIMAL(12,2)) as amount_due,
           CAST(amount_paid AS DECIMAL(12,2)) as amount_paid, 
           status, payment_date_actual 
    FROM loan_payments 
    WHERE loan_id = ?
    ORDER BY payment_date ASC
");

if (!$stmt) {
    echo json_encode(['error' => 'Database error']);
    exit();
}

$stmt->bind_param("i", $loan_id);
$stmt->execute();
$result = $stmt->get_result();
$payments = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Ensure numeric values
foreach ($payments as &$payment) {
    $payment['amount_due'] = (float) $payment['amount_due'];
    $payment['amount_paid'] = (float) $payment['amount_paid'];
}

echo json_encode($payments);
?>