<?php
session_start();
require "CYCLOAN_db.php";

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $loan_id = $_POST['loan_id'];
    $main_status = $_POST['main_status'];

    // Check if the loan is approved in both pre-approval and credit investigation statuses
    $stmt = $conn->prepare("SELECT pre_approval_status, credit_investigation_status FROM loan_status WHERE loan_id = ?");
    $stmt->bind_param("i", $loan_id);
    $stmt->execute();
    $stmt->bind_result($pre_approval_status, $credit_investigation_status);
    $stmt->fetch();
    $stmt->close();

    if ($pre_approval_status == 'Approved' && $credit_investigation_status == 'Approved') {
        // Update the main status
        $stmt = $conn->prepare("UPDATE loan_status SET main_status = ? WHERE loan_id = ?");
        $stmt->bind_param("si", $main_status, $loan_id);
        $stmt->execute();
        $stmt->close();

        echo "Main status updated successfully.";
    } else {
        echo "Cannot update main status. Loan must be approved in both pre-approval and credit investigation statuses.";
    }
}
?>