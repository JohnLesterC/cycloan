<?php
/**
 * Get Payment History for a Loan
 * Returns JSON response with payment records
 */

session_start();
require "CYCLOAN_db.php";

// Check if user is authenticated
if (!isset($_SESSION['email'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

// Validate loan ID
if (!isset($_GET['loan_id']) || empty($_GET['loan_id'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Loan ID is required']);
    exit;
}

$loanId = intval($_GET['loan_id']);

try {
    // Fetch payment history for the loan from payment_schedules
    $stmt = $conn->prepare("
        SELECT 
            ps.payment_id,
            ps.amount,
            ps.principal_amount,
            ps.interest_amount,
            ps.due_date,
            ps.paid_at,
            ps.status,
            ph.invoice_number,
            ph.payment_date,
            ph.amount_paid,
            ph.principal_paid,
            ph.interest_paid
        FROM payment_schedules ps
        LEFT JOIN payment_history ph ON ps.payment_id = ph.payment_id
        WHERE ps.loan_id = ?
        ORDER BY ps.due_date DESC
    ");

    if (!$stmt) {
        throw new Exception('Prepare failed: ' . $conn->error);
    }

    $stmt->bind_param('i', $loanId);

    if (!$stmt->execute()) {
        throw new Exception('Execute failed: ' . $stmt->error);
    }

    $result = $stmt->get_result();
    $payments = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    // Calculate summary statistics
    $summary = [
        'total_payments' => count($payments),
        'total_amount_scheduled' => 0,
        'total_amount_paid' => 0,
        'total_principal_paid' => 0,
        'total_interest_paid' => 0,
        'pending_count' => 0,
        'paid_count' => 0
    ];

    foreach ($payments as $payment) {
        $summary['total_amount_scheduled'] += floatval($payment['amount'] ?? 0);
        $summary['total_amount_paid'] += floatval($payment['amount_paid'] ?? 0);
        $summary['total_principal_paid'] += floatval($payment['principal_paid'] ?? 0);
        $summary['total_interest_paid'] += floatval($payment['interest_paid'] ?? 0);

        if (strtolower($payment['status']) === 'pending') {
            $summary['pending_count']++;
        } else if (strtolower($payment['status']) === 'paid') {
            $summary['paid_count']++;
        }
    }

    echo json_encode([
        'success' => true,
        'payments' => $payments,
        'summary' => $summary
    ]);

} catch (Exception $e) {
    error_log('Payment History Error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error retrieving payment history: ' . $e->getMessage()
    ]);
}

mysqli_close($conn);
?>