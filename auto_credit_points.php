<?php
/**
 * Auto Credit Points Award System
 * This file contains functions to automatically award/deduct credit points based on loan activities
 */

require_once "CYCLOAN_db.php";
require_once "credit_points_manager.php";

/**
 * Award points when a loan is fully paid/completed
 * Call this function when a loan status changes to 'Closed'
 */
function awardLoanCompletionPoints($userId, $loanId)
{
    try {
        $creditManager = getCreditPointsManager();
        return $creditManager->awardLoanCompletionPoints($userId, $loanId);
    } catch (Exception $e) {
        error_log("Error awarding loan completion points: " . $e->getMessage());
        return false;
    }
}

/**
 * Award points for on-time payment
 * Call this when a payment is made before or on the due date
 */
function awardOnTimePaymentPoints($userId, $loanId)
{
    try {
        $creditManager = getCreditPointsManager();
        return $creditManager->awardOnTimePayment($userId, $loanId);
    } catch (Exception $e) {
        error_log("Error awarding on-time payment points: " . $e->getMessage());
        return false;
    }
}

/**
 * Deduct points for late payment
 * Call this when a payment is made after the due date
 */
function deductLatePaymentPoints($userId, $loanId)
{
    try {
        $creditManager = getCreditPointsManager();
        return $creditManager->deductLatePayment($userId, $loanId);
    } catch (Exception $e) {
        error_log("Error deducting late payment points: " . $e->getMessage());
        return false;
    }
}

/**
 * Check if payment is on time and award/deduct points accordingly
 * Returns: 'on-time', 'late', or 'error'
 */
function checkAndAwardPaymentPoints($userId, $loanId, $dueDate, $paymentDate)
{
    try {
        $due = new DateTime($dueDate);
        $paid = new DateTime($paymentDate);

        if ($paid <= $due) {
            // On-time payment
            awardOnTimePaymentPoints($userId, $loanId);
            return 'on-time';
        } else {
            // Late payment
            deductLatePaymentPoints($userId, $loanId);
            return 'late';
        }
    } catch (Exception $e) {
        error_log("Error in checkAndAwardPaymentPoints: " . $e->getMessage());
        return 'error';
    }
}

/**
 * Get user's credit tier based on their points
 * Returns: 'Excellent', 'Good', 'Fair', 'Poor'
 */
function getUserCreditTier($creditPoints)
{
    if ($creditPoints >= 500)
        return 'Excellent';
    if ($creditPoints >= 300)
        return 'Good';
    if ($creditPoints >= 100)
        return 'Fair';
    return 'Building';
}

/**
 * Get credit tier color for UI display
 */
function getCreditTierColor($creditPoints)
{
    $tier = getUserCreditTier($creditPoints);
    switch ($tier) {
        case 'Excellent':
            return '#4caf50';
        case 'Good':
            return '#2196f3';
        case 'Fair':
            return '#ff9800';
        default:
            return '#9e9e9e';
    }
}
?>