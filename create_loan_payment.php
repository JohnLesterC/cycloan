<?php
session_start();
require "CYCLOAN_db.php";

if (!isset($_SESSION['email']) || $_SESSION['user_type'] !== 'admin1') {
    header("Location: index.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $loan_id = filter_input(INPUT_POST, 'loan_id', FILTER_VALIDATE_INT);
    $amount_borrowed = filter_input(INPUT_POST, 'amount_borrowed', FILTER_VALIDATE_FLOAT);
    $term_length = filter_input(INPUT_POST, 'term_length', FILTER_VALIDATE_INT);
    $payment_frequency = $_POST['payment_frequency'] ?? '';

    // Validate inputs
    if (!$loan_id || !$amount_borrowed || !$term_length || !in_array($payment_frequency, ['monthly', 'bi-weekly', 'weekly'])) {
        echo json_encode(['success' => false, 'message' => 'Invalid input data']);
        exit();
    }

    // Check if loan exists
    $check_loan = $conn->prepare("SELECT id FROM loan_application1 WHERE id = ?");
    $check_loan->bind_param("i", $loan_id);
    $check_loan->execute();
    $check_loan->store_result();

    if ($check_loan->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'Loan not found']);
        exit();
    }
    $check_loan->close();

    // Check if payment schedule already exists
    $check_payments = $conn->prepare("SELECT COUNT(*) FROM loan_payments WHERE loan_id = ?");
    $check_payments->bind_param("i", $loan_id);
    $check_payments->execute();
    $check_payments->bind_result($count);
    $check_payments->fetch();
    $check_payments->close();

    if ($count > 0) {
        echo json_encode(['success' => false, 'message' => 'Payment schedule already exists for this loan']);
        exit();
    }

    // Start transaction
    $conn->begin_transaction();

    try {
        // Calculate payment dates and amounts
        $payment_schedule = [];
        $payment_amount = $amount_borrowed / $term_length;
        $current_date = new DateTime();

        // Set first payment date based on frequency
        if ($payment_frequency === 'monthly') {
            $current_date->modify('first day of next month');
            $interval = 'P1M';
        } elseif ($payment_frequency === 'bi-weekly') {
            $current_date->modify('next monday');
            $current_date->modify('+1 week');
            $interval = 'P2W';
        } else { // weekly
            $current_date->modify('next monday');
            $interval = 'P1W';
        }

        // Create payment schedule
        for ($i = 0; $i < $term_length; $i++) {
            $payment_schedule[] = [
                'date' => $current_date->format('Y-m-d'),
                'amount' => round($payment_amount, 2)
            ];
            $current_date->add(new DateInterval($interval));
        }

        // Insert payments
        $stmt = $conn->prepare("INSERT INTO loan_payments (loan_id, payment_date, amount_due) VALUES (?, ?, ?)");

        foreach ($payment_schedule as $payment) {
            $stmt->bind_param("isd", $loan_id, $payment['date'], $payment['amount']);
            $stmt->execute();
        }



        $conn->commit();
        echo json_encode(['success' => true, 'message' => 'Payment schedule created successfully']);
    } catch (Exception $e) {
        $conn->rollback();
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
}
?>