<?php
/**
 * Example Integration: Notifications in Active Records Page
 * 
 * This file demonstrates notification integration
 * for active loan records management
 */

// Include at top of active_records.php:
require_once 'AdminNotificationIntegration.php';

// ============================================================================
// EXAMPLE 1: Loan Status Change Notifications
// ============================================================================

function handleLoanStatusUpdate($conn, $adminId, $adminRole, $loan_id, $application_id, $user_id, $user_name, $old_status, $new_status)
{
    $notificationHandler = new AdminNotificationIntegration($conn, $adminId, $adminRole);

    // Status-specific messages
    $status_messages = [
        'Active' => 'Your loan has been activated. You can now proceed with fund disbursement.',
        'On Hold' => 'Your loan has been put on hold. Please contact support for more information.',
        'Completed' => 'Congratulations! Your loan has been fully paid and your account is closed.',
        'Defaulted' => 'Your account has been marked as defaulted. Please contact us immediately.'
    ];

    // Notify the user
    $user_message = $status_messages[$new_status] ?? 'Your loan status has been updated to: ' . $new_status;
    $priority = in_array($new_status, ['Defaulted', 'On Hold']) ? 'high' : 'normal';

    $notificationHandler->sendNotification(
        $user_id,
        'User',
        'loan_status',
        'Loan Status Updated: ' . $user_name,
        $user_message,
        'active_records.php?loan_id=' . $loan_id,
        'View Loan Details',
        $priority
    );

    // Notify all admins
    $notificationHandler->notifyLoanStatusUpdate(
        $application_id,
        $loan_id,
        $old_status,
        $new_status,
        $user_name
    );

    // Log activity
    logActivity(
        $conn,
        $adminId,
        $adminRole,
        'update',
        'loan_application',
        "Updated loan status from '$old_status' to '$new_status'",
        $application_id
    );
}

// ============================================================================
// EXAMPLE 2: Payment Received Notifications
// ============================================================================

function handlePaymentReceived($conn, $adminId, $adminRole, $payment_id, $loan_id, $user_id, $user_name, $amount, $payment_date)
{
    $notificationHandler = new AdminNotificationIntegration($conn, $adminId, $adminRole);

    $user_message = 'We have received your payment of ₱' . number_format($amount, 2) .
        ' on ' . date('M j, Y', strtotime($payment_date)) .
        '. Your account has been updated.';

    $notificationHandler->sendNotification(
        $user_id,
        'User',
        'payment_reminder',
        'Payment Received',
        $user_message,
        'active_records.php?loan_id=' . $loan_id . '&tab=payments',
        'View Payment History',
        'normal'
    );

    // Notify admins
    $notificationHandler->broadcastToAdminRole(
        'admin1',
        'payment_reminder',
        'Payment Received from ' . $user_name,
        'Payment of ₱' . number_format($amount, 2) . ' received for Loan #' . $loan_id,
        'active_records.php?loan_id=' . $loan_id,
        'View Loan',
        'normal'
    );
}

// ============================================================================
// EXAMPLE 3: Payment Overdue Notifications
// ============================================================================

function handlePaymentOverdue($conn, $adminId, $adminRole, $payment_id, $loan_id, $user_id, $user_name, $amount, $due_date, $days_overdue)
{
    $notificationHandler = new AdminNotificationIntegration($conn, $adminId, $adminRole);

    $priority = $days_overdue > 30 ? 'high' : 'normal';

    // Notify user
    $user_message = 'Your payment of ₱' . number_format($amount, 2) .
        ' was due on ' . date('M j, Y', strtotime($due_date)) .
        ' and is now ' . $days_overdue . ' days overdue. ' .
        'Please make payment immediately to avoid penalties.';

    $notificationHandler->sendNotification(
        $user_id,
        'User',
        'payment_reminder',
        'Payment Overdue - Immediate Action Required',
        $user_message,
        'active_records.php?loan_id=' . $loan_id . '&tab=payments',
        'Make Payment',
        $priority
    );

    // Notify admins
    $notificationHandler->broadcastToAdminRole(
        'admin1',
        'payment_reminder',
        'OVERDUE PAYMENT: ' . $user_name . ' (' . $days_overdue . ' days)',
        'Payment of ₱' . number_format($amount, 2) . ' is ' . $days_overdue . ' days overdue',
        'active_records.php?loan_id=' . $loan_id . '&tab=payments',
        'Send Reminder',
        $priority
    );
}

// ============================================================================
// EXAMPLE 4: Loan Amount Update Notifications
// ============================================================================

function handleLoanAmountUpdate($conn, $adminId, $adminRole, $loan_id, $user_id, $user_name, $old_amount, $new_amount)
{
    $notificationHandler = new AdminNotificationIntegration($conn, $adminId, $adminRole);

    $difference = $new_amount - $old_amount;
    $diff_text = $difference > 0 ? '₱' . number_format($difference, 2) . ' increase' : '₱' . number_format(abs($difference), 2) . ' decrease';

    $user_message = 'Your loan amount has been adjusted. Previous amount: ₱' .
        number_format($old_amount, 2) . '. New amount: ₱' .
        number_format($new_amount, 2) . ' (' . $diff_text . '). ' .
        'Your payment schedule has been updated accordingly.';

    $notificationHandler->sendNotification(
        $user_id,
        'User',
        'loan_status',
        'Loan Amount Updated',
        $user_message,
        'active_records.php?loan_id=' . $loan_id,
        'View Updated Loan Details',
        'normal'
    );
}

// ============================================================================
// EXAMPLE 5: Payment Schedule Update Notifications
// ============================================================================

function handlePaymentScheduleUpdate($conn, $adminId, $adminRole, $loan_id, $user_id, $user_name, $changes_description)
{
    $notificationHandler = new AdminNotificationIntegration($conn, $adminId, $adminRole);

    $notificationHandler->sendNotification(
        $user_id,
        'User',
        'loan_status',
        'Payment Schedule Updated',
        'Your payment schedule has been modified. ' . $changes_description .
        ' Please review your updated schedule and make payments accordingly.',
        'active_records.php?loan_id=' . $loan_id . '&tab=payments',
        'View Payment Schedule',
        'normal'
    );
}

// ============================================================================
// EXAMPLE 6: Dashboard Widget - Display Recent Active Loans
// ============================================================================

function displayActiveLoansWidget($conn, $adminId, $adminRole, $limit = 5)
{
    // Get notifications related to active loans
    $notificationHandler = new AdminNotificationIntegration($conn, $adminId, $adminRole);
    $recent = $notificationHandler->getRecentNotifications($limit);

    $html = '
    <div class="active-loans-widget">
        <h3><i class="fas fa-file-invoice-dollar"></i> Active Loans Updates</h3>
        <div class="loans-list">';

    if (empty($recent)) {
        $html .= '<p class="empty">No recent updates</p>';
    } else {
        foreach ($recent as $notification) {
            $html .= '
            <div class="loan-item">
                <div class="loan-icon ' . ($notification['color_class'] ?? '') . '">
                    <i class="' . ($notification['icon_class'] ?? 'fas fa-info-circle') . '"></i>
                </div>
                <div class="loan-info">
                    <p class="loan-title">' . htmlspecialchars($notification['title']) . '</p>
                    <p class="loan-time">' . timeAgo($notification['created_at']) . '</p>
                </div>
            </div>';
        }
    }

    $html .= '
        </div>
        <a href="notifications_enhanced.php" class="view-all-link">View All Notifications</a>
    </div>';

    return $html;
}

// ============================================================================
// HTML INTEGRATION EXAMPLES
// ============================================================================

?>

<!-- Example: Payment Table with Notification Indicators -->

<table class="payments-table">
    <thead>
        <tr>
            <th>Payment Schedule ID</th>
            <th>Due Date</th>
            <th>Amount</th>
            <th>Status</th>
            <th>Days Overdue</th>
            <th>Actions</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($payments as $payment): ?>
            <tr class="payment-row <?php echo $payment['days_overdue'] > 0 ? 'overdue' : ''; ?>">
                <td><?php echo htmlspecialchars($payment['payment_id']); ?></td>
                <td><?php echo date('M j, Y', strtotime($payment['due_date'])); ?></td>
                <td>₱<?php echo number_format($payment['amount'], 2); ?></td>
                <td>
                    <span class="status-badge <?php echo strtolower($payment['status']); ?>">
                        <?php echo htmlspecialchars($payment['status']); ?>
                    </span>
                </td>
                <td>
                    <?php if ($payment['days_overdue'] > 0): ?>
                        <span class="overdue-badge">
                            <i class="fas fa-exclamation-circle"></i>
                            <?php echo $payment['days_overdue']; ?> days
                        </span>
                    <?php else: ?>
                        <span class="on-time">On Time</span>
                    <?php endif; ?>
                </td>
                <td>
                    <button onclick="viewPaymentDetails(<?php echo $payment['payment_id']; ?>)" class="btn btn-small">
                        <i class="fas fa-eye"></i>
                    </button>
                    <?php if ($payment['days_overdue'] > 0): ?>
                        <button
                            onclick="sendPaymentReminder(<?php echo $payment['payment_id']; ?>, '<?php echo htmlspecialchars($payment['user_name']); ?>')"
                            class="btn btn-small btn-warning">
                            <i class="fas fa-bell"></i> Send Reminder
                        </button>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
    </tbody>
</table>

<script>
    function sendPaymentReminder(paymentId, userName) {
        if (confirm('Send payment reminder to ' + userName + '?')) {
            fetch('process_active_records.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded'
                },
                body: 'action=send_payment_reminder&payment_id=' + paymentId +
                    '&user_name=' + encodeURIComponent(userName)
            })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showToast('Reminder sent to ' + userName, 'success');
                    } else {
                        showToast('Error: ' + data.message, 'error');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    showToast('An error occurred', 'error');
                });
        }
    }

    function showToast(message, type) {
        const toast = document.createElement('div');
        toast.className = 'toast ' + type;
        toast.textContent = message;
        document.body.appendChild(toast);
        setTimeout(() => toast.remove(), 3000);
    }
</script>

<style>
    /* Notification-related styles for active records */

    .payment-row.overdue {
        background: rgba(211, 47, 47, 0.05);
    }

    .overdue-badge {
        display: inline-flex;
        align-items: center;
        gap: 5px;
        padding: 4px 8px;
        background: #ffebee;
        color: #d32f2f;
        border-radius: 4px;
        font-size: 0.85rem;
        font-weight: 500;
    }

    .on-time {
        color: #2e7d32;
        font-weight: 500;
    }

    .status-badge {
        display: inline-block;
        padding: 6px 12px;
        border-radius: 20px;
        font-size: 0.85rem;
        font-weight: 500;
        text-transform: capitalize;
    }

    .status-badge.pending {
        background: #fff3cd;
        color: #856404;
    }

    .status-badge.paid {
        background: #d4edda;
        color: #155724;
    }

    .status-badge.overdue {
        background: #f8d7da;
        color: #721c24;
    }

    .active-loans-widget {
        background: white;
        border-radius: 8px;
        padding: 20px;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
    }

    .active-loans-widget h3 {
        margin: 0 0 15px 0;
        color: #1a3c34;
        font-size: 1rem;
    }

    .loans-list {
        max-height: 300px;
        overflow-y: auto;
    }

    .loan-item {
        display: flex;
        gap: 10px;
        padding: 10px;
        border-bottom: 1px solid #eee;
    }

    .loan-item:last-child {
        border-bottom: none;
    }

    .loan-icon {
        width: 36px;
        height: 36px;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 6px;
        flex-shrink: 0;
    }

    .loan-info {
        flex: 1;
        min-width: 0;
    }

    .loan-title {
        margin: 0;
        font-size: 0.9rem;
        font-weight: 500;
        color: #333;
    }

    .loan-time {
        margin: 3px 0 0 0;
        font-size: 0.8rem;
        color: #999;
    }

    .view-all-link {
        display: block;
        text-align: center;
        margin-top: 15px;
        padding: 10px;
        color: #1b5e20;
        text-decoration: none;
        font-weight: 500;
        transition: all 0.3s ease;
    }

    .view-all-link:hover {
        background: #f5f5f5;
        border-radius: 4px;
    }

    .empty {
        text-align: center;
        padding: 30px 10px;
        color: #999;
        font-size: 0.9rem;
    }

    .toast {
        position: fixed;
        bottom: 20px;
        right: 20px;
        padding: 12px 20px;
        border-radius: 4px;
        color: white;
        font-weight: 500;
        z-index: 3000;
    }

    .toast.success {
        background: #2e7d32;
    }

    .toast.error {
        background: #d32f2f;
    }
</style>