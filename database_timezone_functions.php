<?php
/**
 * DATABASE TIMEZONE FUNCTIONS FOR CYCLOAN
 * 
 * This file contains all database-specific timezone functions
 * tailored to the CYCLOAN schema and tables.
 * 
 * Include this file after timezone_config.php:
 * require_once 'timezone_config.php';
 * require_once 'database_timezone_functions.php';
 * 
 * Last Updated: Nov 2, 2025
 */

// ============================================================
// 1. ACTIVITY LOG FUNCTIONS
// ============================================================

/**
 * Format activity log entry with PHT timestamp
 * Used in: admin1_dashboard.php, admin2_dashboard.php
 */
function formatActivityLog($log)
{
    return [
        'log_id' => $log['log_id'],
        'user_id' => $log['user_id'],
        'user_role' => $log['user_role'],
        'admin_name' => $log['admin_name'],
        'action_type' => $log['action_type'],
        'module' => $log['module'],
        'description' => $log['description'],
        'created_at' => formatPHTimestamp($log['created_at'], 'M d, Y \a\t g:i A'),
        'created_at_short' => formatPHTimestamp($log['created_at'], 'M d g:i A'),
        'time_ago' => getTimeDifference($log['created_at'], getCurrentPHTime())
    ];
}

/**
 * Get recent activity logs with formatted timestamps
 */
function getRecentActivityLogs($conn, $limit = 10)
{
    $query = "SELECT * FROM activity_logs ORDER BY created_at DESC LIMIT ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param('i', $limit);
    $stmt->execute();
    $result = $stmt->get_result();

    $logs = [];
    while ($row = $result->fetch_assoc()) {
        $logs[] = formatActivityLog($row);
    }

    $stmt->close();
    return $logs;
}

/**
 * Get activity logs for specific date range in PH time
 */
function getActivityLogsByDateRange($conn, $startDate, $endDate)
{
    $startDt = new DateTime($startDate . ' 00:00:00', new DateTimeZone('Asia/Manila'));
    $endDt = new DateTime($endDate . ' 23:59:59', new DateTimeZone('Asia/Manila'));

    $query = "SELECT * FROM activity_logs 
              WHERE created_at >= ? AND created_at <= ?
              ORDER BY created_at DESC";

    $stmt = $conn->prepare($query);
    $startStr = $startDt->format('Y-m-d H:i:s');
    $endStr = $endDt->format('Y-m-d H:i:s');
    $stmt->bind_param('ss', $startStr, $endStr);
    $stmt->execute();
    $result = $stmt->get_result();

    $logs = [];
    while ($row = $result->fetch_assoc()) {
        $logs[] = formatActivityLog($row);
    }

    $stmt->close();
    return $logs;
}

/**
 * Get activity count by user for today (PH timezone)
 */
function getActivityCountByUserToday($conn, $userId)
{
    $todayStart = getPhilippineTime()->format('Y-m-d 00:00:00');
    $todayEnd = getPhilippineTime()->format('Y-m-d 23:59:59');

    $query = "SELECT COUNT(*) as count FROM activity_logs 
              WHERE user_id = ? AND created_at BETWEEN ? AND ?";

    $stmt = $conn->prepare($query);
    $stmt->bind_param('iss', $userId, $todayStart, $todayEnd);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();

    $stmt->close();
    return $row['count'];
}

// ============================================================
// 2. OTP TIMESTAMP FUNCTIONS
// ============================================================

/**
 * Check if OTP is still valid (not expired) using PH time
 */
function isOTPValid($expiresAt)
{
    $expiryTime = new DateTime($expiresAt, new DateTimeZone('Asia/Manila'));
    $currentTime = getPhilippineTime();

    return $currentTime < $expiryTime;
}

/**
 * Get remaining time for OTP validity in minutes
 */
function getOTPTimeRemaining($expiresAt)
{
    $expiryTime = new DateTime($expiresAt, new DateTimeZone('Asia/Manila'));
    $currentTime = getPhilippineTime();

    if ($currentTime > $expiryTime) {
        return 0;
    }

    $interval = $currentTime->diff($expiryTime);
    $minutes = ($interval->days * 24 * 60) + ($interval->h * 60) + $interval->i;

    return $minutes;
}

/**
 * Get human-readable OTP expiry display
 */
function getOTPExpiryDisplay($expiresAt)
{
    $expiryTime = new DateTime($expiresAt, new DateTimeZone('Asia/Manila'));
    $currentTime = getPhilippineTime();

    if ($currentTime > $expiryTime) {
        $diff = $currentTime->diff($expiryTime);
        return "Expired " . $diff->i . " minutes ago";
    }

    $diff = $expiryTime->diff($currentTime);

    if ($diff->days > 0) {
        return "Expires in " . ($diff->days * 24 + $diff->h) . " hours";
    } elseif ($diff->h > 0) {
        return "Expires in " . $diff->h . " hours " . $diff->i . " minutes";
    } else {
        return "Expires in " . $diff->i . " minutes";
    }
}

/**
 * Generate OTP expiry timestamp (default 15 minutes from now in PH time)
 */
function generateOTPExpiry($minutes = 15)
{
    $expiryTime = getPhilippineTime();
    $expiryTime->add(new DateInterval('PT' . $minutes . 'M'));

    return $expiryTime->format('Y-m-d H:i:s');
}

/**
 * Verify OTP hasn't expired and return status
 */
function verifyOTPStatus($expiresAt)
{
    return [
        'is_valid' => isOTPValid($expiresAt),
        'remaining_minutes' => getOTPTimeRemaining($expiresAt),
        'display_text' => getOTPExpiryDisplay($expiresAt),
        'expires_at' => formatPHTimestamp($expiresAt, 'M d, Y g:i A')
    ];
}

// ============================================================
// 3. LOAN PAYMENT FUNCTIONS
// ============================================================

/**
 * Format payment schedule entry with PH time
 */
function formatPaymentSchedule($schedule)
{
    $dueDate = new DateTime($schedule['due_date'], new DateTimeZone('Asia/Manila'));
    $currentDate = getPhilippineTime();

    $isOverdue = $currentDate > $dueDate;
    $daysRemaining = $currentDate->diff($dueDate)->days;

    return [
        'payment_id' => $schedule['payment_id'],
        'amount' => $schedule['amount'],
        'due_date' => formatPHTimestamp($schedule['due_date'], 'M d, Y'),
        'due_date_full' => formatPHTimestamp($schedule['due_date'], 'F d, Y'),
        'status' => $schedule['status'],
        'is_overdue' => $isOverdue,
        'days_remaining' => abs($daysRemaining),
        'payment_status_badge' => getPaymentStatusBadge($schedule['status'], $isOverdue),
        'created_at' => formatPHTimestamp($schedule['created_at'], 'M d, Y g:i A')
    ];
}

/**
 * Get payment status with overdue indicator using PH time
 */
function getPaymentStatus($conn, $paymentId)
{
    $query = "SELECT ps.*, l.loan_id 
              FROM payment_schedules ps
              JOIN loans l ON ps.loan_id = l.loan_id
              WHERE ps.payment_id = ?";

    $stmt = $conn->prepare($query);
    $stmt->bind_param('i', $paymentId);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        $stmt->close();
        return null;
    }

    $payment = $result->fetch_assoc();
    $stmt->close();

    return formatPaymentSchedule($payment);
}

/**
 * Get overdue payments for a user using PH time
 */
function getOverduePayments($conn, $userId)
{
    $currentTime = getCurrentPHTime('Y-m-d H:i:s');

    $query = "SELECT ps.* FROM payment_schedules ps
              JOIN loans l ON ps.loan_id = l.loan_id
              WHERE l.user_id = ? 
              AND ps.due_date < ?
              AND ps.status != 'Paid'
              ORDER BY ps.due_date ASC";

    $stmt = $conn->prepare($query);
    $stmt->bind_param('is', $userId, $currentTime);
    $stmt->execute();
    $result = $stmt->get_result();

    $overduePayments = [];
    while ($row = $result->fetch_assoc()) {
        $overduePayments[] = formatPaymentSchedule($row);
    }

    $stmt->close();
    return $overduePayments;
}

/**
 * Get upcoming payments due within days (PH timezone aware)
 */
function getUpcomingPayments($conn, $userId, $daysAhead = 7)
{
    $currentDate = getPhilippineTime();
    $futureDate = clone $currentDate;
    $futureDate->add(new DateInterval('P' . $daysAhead . 'D'));

    $query = "SELECT ps.* FROM payment_schedules ps
              JOIN loans l ON ps.loan_id = l.loan_id
              WHERE l.user_id = ?
              AND ps.due_date BETWEEN ? AND ?
              AND ps.status != 'Paid'
              ORDER BY ps.due_date ASC";

    $stmt = $conn->prepare($query);
    $currentStr = $currentDate->format('Y-m-d');
    $futureStr = $futureDate->format('Y-m-d');
    $stmt->bind_param('iss', $userId, $currentStr, $futureStr);
    $stmt->execute();
    $result = $stmt->get_result();

    $upcomingPayments = [];
    while ($row = $result->fetch_assoc()) {
        $upcomingPayments[] = formatPaymentSchedule($row);
    }

    $stmt->close();
    return $upcomingPayments;
}

/**
 * Get total overdue amount for user
 */
function getTotalOverdueAmount($conn, $userId)
{
    $currentTime = getCurrentPHTime('Y-m-d H:i:s');

    $query = "SELECT COALESCE(SUM(ps.amount), 0) as total_overdue 
              FROM payment_schedules ps
              JOIN loans l ON ps.loan_id = l.loan_id
              WHERE l.user_id = ? 
              AND ps.due_date < ?
              AND ps.status != 'Paid'";

    $stmt = $conn->prepare($query);
    $stmt->bind_param('is', $userId, $currentTime);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();

    $stmt->close();
    return floatval($row['total_overdue']);
}

// ============================================================
// 4. LOAN APPLICATION TIMELINE FUNCTIONS
// ============================================================

/**
 * Format loan application with timeline (all PH times)
 */
function formatLoanApplication($application)
{
    $createdDate = new DateTime($application['created_at'], new DateTimeZone('Asia/Manila'));
    $currentDate = getPhilippineTime();
    $daysActive = $createdDate->diff($currentDate)->days;

    return [
        'application_id' => $application['application_id'],
        'status' => $application['status'],
        'loan_amount' => $application['loan_amount'],
        'created_at' => formatPHTimestamp($application['created_at'], 'M d, Y g:i A'),
        'created_at_short' => formatPHTimestamp($application['created_at'], 'M d, Y'),
        'updated_at' => formatPHTimestamp($application['updated_at'], 'M d, Y g:i A'),
        'days_active' => $daysActive,
        'status_badge' => getApplicationStatusBadge($application['status'])
    ];
}

/**
 * Get application processing time in human-readable format
 */
function getApplicationProcessingTime($startDate, $endDate = null)
{
    $start = new DateTime($startDate, new DateTimeZone('Asia/Manila'));
    $end = $endDate
        ? new DateTime($endDate, new DateTimeZone('Asia/Manila'))
        : getPhilippineTime();

    $interval = $start->diff($end);

    if ($interval->days > 0) {
        return $interval->days . " days, " . $interval->h . " hours";
    } elseif ($interval->h > 0) {
        return $interval->h . " hours, " . $interval->i . " minutes";
    } else {
        return $interval->i . " minutes";
    }
}

/**
 * Get applications pending approval with age
 */
function getPendingApplicationsWithAge($conn)
{
    $query = "SELECT * FROM loan_applications 
              WHERE status = 'Pending'
              ORDER BY created_at ASC";

    $stmt = $conn->prepare($query);
    $stmt->execute();
    $result = $stmt->get_result();

    $applications = [];
    while ($row = $result->fetch_assoc()) {
        $app = formatLoanApplication($row);
        $app['processing_time'] = getApplicationProcessingTime($row['created_at']);
        $applications[] = $app;
    }

    $stmt->close();
    return $applications;
}

// ============================================================
// 5. USER PROFILE REGISTRATION FUNCTIONS
// ============================================================

/**
 * Format user registration info with PH timestamp
 */
function formatUserRegistration($user)
{
    $registeredDate = new DateTime($user['created_at'], new DateTimeZone('Asia/Manila'));
    $currentDate = getPhilippineTime();
    $memberDays = $registeredDate->diff($currentDate)->days;

    return [
        'user_id' => $user['id'],
        'full_name' => $user['first_name'] . ' ' . $user['last_name'],
        'email' => $user['email'],
        'registered_on' => formatPHTimestamp($user['created_at'], 'F d, Y'),
        'registered_at' => formatPHTimestamp($user['created_at'], 'F d, Y \a\t g:i A'),
        'member_since' => formatPHTimestamp($user['created_at'], 'M Y'),
        'member_for' => $memberDays . ' days',
        'last_updated' => formatPHTimestamp($user['updated_at'], 'M d, Y g:i A'),
        'age' => isset($user['date_of_birth']) ? calculateAgePH($user['date_of_birth']) : null
    ];
}

/**
 * Calculate age based on birth date using PH timezone
 */
function calculateAgePH($birthDate)
{
    if (empty($birthDate) || $birthDate === '0000-00-00') {
        return null;
    }

    try {
        $birth = new DateTime($birthDate, new DateTimeZone('Asia/Manila'));
        $current = getPhilippineTime();
        return $current->diff($birth)->y;
    } catch (Exception $e) {
        return null;
    }
}

/**
 * Get profile last updated information in PH time
 */
function getUserProfileUpdateInfo($conn, $userId)
{
    $query = "SELECT updated_at FROM users1 WHERE id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        $stmt->close();
        return null;
    }

    $user = $result->fetch_assoc();
    $stmt->close();

    return [
        'updated_at' => formatPHTimestamp($user['updated_at'], 'M d, Y g:i A'),
        'updated_at_short' => formatPHTimestamp($user['updated_at'], 'M d, Y'),
        'time_since_update' => getTimeDifference($user['updated_at'], getCurrentPHTime()),
        'is_recently_updated' => isWithinTimeRange($user['updated_at'], 60)
    ];
}

// ============================================================
// 6. CREDIT POINTS TRACKING FUNCTIONS
// ============================================================

/**
 * Format credit points history entry with PH timestamp
 */
function formatCreditPointsHistory($historyRow)
{
    return [
        'history_id' => $historyRow['history_id'],
        'points_change' => $historyRow['points_change'],
        'previous_points' => $historyRow['previous_points'],
        'new_points' => $historyRow['new_points'],
        'reason' => $historyRow['reason'],
        'admin_name' => $historyRow['admin_role'] . ' (ID: ' . $historyRow['admin_id'] . ')',
        'created_at' => formatPHTimestamp($historyRow['created_at'], 'M d, Y g:i A'),
        'created_at_short' => formatPHTimestamp($historyRow['created_at'], 'M d g:i A'),
        'change_badge' => $historyRow['points_change'] >= 0
            ? '+' . $historyRow['points_change']
            : $historyRow['points_change']
    ];
}

/**
 * Get user credit points history within date range
 */
function getUserCreditPointsHistory($conn, $userId, $startDate = null, $endDate = null)
{
    if (!$startDate) {
        $startDate = getPhilippineTime()->modify('first day of this month')->format('Y-m-d');
    }
    if (!$endDate) {
        $endDate = getPhilippineTime()->format('Y-m-d');
    }

    $startDt = new DateTime($startDate . ' 00:00:00', new DateTimeZone('Asia/Manila'));
    $endDt = new DateTime($endDate . ' 23:59:59', new DateTimeZone('Asia/Manila'));

    $query = "SELECT * FROM credit_points_history 
              WHERE user_id = ?
              AND created_at BETWEEN ? AND ?
              ORDER BY created_at DESC";

    $stmt = $conn->prepare($query);
    $startStr = $startDt->format('Y-m-d H:i:s');
    $endStr = $endDt->format('Y-m-d H:i:s');
    $stmt->bind_param('iss', $userId, $startStr, $endStr);
    $stmt->execute();
    $result = $stmt->get_result();

    $history = [];
    while ($row = $result->fetch_assoc()) {
        $history[] = formatCreditPointsHistory($row);
    }

    $stmt->close();
    return $history;
}

// ============================================================
// 7. INVOICE AND PAYMENT RECEIPT FUNCTIONS
// ============================================================

/**
 * Format invoice with PH timezone information
 */
function formatInvoice($invoice)
{
    return [
        'invoice_id' => $invoice['invoice_id'],
        'invoice_number' => $invoice['invoice_number'] ?? 'INV-' . $invoice['invoice_id'],
        'payment_id' => $invoice['payment_id'],
        'loan_id' => $invoice['loan_id'],
        'amount_paid' => '₱' . number_format($invoice['amount_paid'], 2),
        'principal_paid' => '₱' . number_format($invoice['principal_paid'], 2),
        'interest_paid' => '₱' . number_format($invoice['interest_paid'], 2),
        'payment_date' => formatPHTimestamp($invoice['payment_date'], 'F d, Y'),
        'created_at' => formatPHTimestamp($invoice['created_at'], 'M d, Y g:i A'),
        'created_at_full' => formatPHTimestamp($invoice['created_at'], 'l, F d, Y \a\t g:i A')
    ];
}

/**
 * Get payment receipts for date range in PH timezone
 */
function getPaymentReceiptsByDateRange($conn, $userId, $startDate, $endDate)
{
    $startDt = new DateTime($startDate . ' 00:00:00', new DateTimeZone('Asia/Manila'));
    $endDt = new DateTime($endDate . ' 23:59:59', new DateTimeZone('Asia/Manila'));

    $query = "SELECT i.* FROM invoices i
              JOIN loans l ON i.loan_id = l.loan_id
              WHERE l.user_id = ?
              AND i.created_at BETWEEN ? AND ?
              ORDER BY i.created_at DESC";

    $stmt = $conn->prepare($query);
    $startStr = $startDt->format('Y-m-d H:i:s');
    $endStr = $endDt->format('Y-m-d H:i:s');
    $stmt->bind_param('iss', $userId, $startStr, $endStr);
    $stmt->execute();
    $result = $stmt->get_result();

    $receipts = [];
    while ($row = $result->fetch_assoc()) {
        $receipts[] = formatInvoice($row);
    }

    $stmt->close();
    return $receipts;
}

/**
 * Get invoices created today (PH timezone)
 */
function getInvoicesCreatedToday($conn, $userId)
{
    $todayStart = getPhilippineTime()->format('Y-m-d 00:00:00');
    $todayEnd = getPhilippineTime()->format('Y-m-d 23:59:59');

    $query = "SELECT i.* FROM invoices i
              JOIN loans l ON i.loan_id = l.loan_id
              WHERE l.user_id = ?
              AND i.created_at BETWEEN ? AND ?
              ORDER BY i.created_at DESC";

    $stmt = $conn->prepare($query);
    $stmt->bind_param('iss', $userId, $todayStart, $todayEnd);
    $stmt->execute();
    $result = $stmt->get_result();

    $invoices = [];
    while ($row = $result->fetch_assoc()) {
        $invoices[] = formatInvoice($row);
    }

    $stmt->close();
    return $invoices;
}

// ============================================================
// 8. HELPER FUNCTIONS
// ============================================================

/**
 * Format time difference as human-readable string
 */
function getTimeDifference($oldTime, $newTime = null)
{
    if (!$newTime) {
        $newTime = getCurrentPHTime('Y-m-d H:i:s');
    }

    try {
        $old = new DateTime($oldTime, new DateTimeZone('Asia/Manila'));
        $new = new DateTime($newTime, new DateTimeZone('Asia/Manila'));

        $interval = $old->diff($new);

        if ($interval->y > 0) {
            return $interval->y . " year" . ($interval->y > 1 ? "s" : "") . " ago";
        } elseif ($interval->m > 0) {
            return $interval->m . " month" . ($interval->m > 1 ? "s" : "") . " ago";
        } elseif ($interval->d > 0) {
            return $interval->d . " day" . ($interval->d > 1 ? "s" : "") . " ago";
        } elseif ($interval->h > 0) {
            return $interval->h . " hour" . ($interval->h > 1 ? "s" : "") . " ago";
        } elseif ($interval->i > 0) {
            return $interval->i . " minute" . ($interval->i > 1 ? "s" : "") . " ago";
        } else {
            return "Just now";
        }
    } catch (Exception $e) {
        return "Unknown";
    }
}

/**
 * Check if timestamp is within X minutes from now
 */
function isWithinTimeRange($timestamp, $minutes = 60)
{
    try {
        $ts = new DateTime($timestamp, new DateTimeZone('Asia/Manila'));
        $current = getPhilippineTime();

        $interval = $current->diff($ts);
        $totalMinutes = ($interval->days * 24 * 60) + ($interval->h * 60) + $interval->i;

        return $totalMinutes <= $minutes;
    } catch (Exception $e) {
        return false;
    }
}

/**
 * Get status badge HTML for payment/application
 */
function getPaymentStatusBadge($status, $isOverdue = false)
{
    $badgeClass = '';
    $badgeText = '';

    if ($isOverdue && $status !== 'Paid') {
        $badgeClass = 'badge-danger';
        $badgeText = 'OVERDUE';
    } else {
        switch ($status) {
            case 'Paid':
                $badgeClass = 'badge-success';
                $badgeText = 'PAID';
                break;
            case 'Pending':
                $badgeClass = 'badge-warning';
                $badgeText = 'PENDING';
                break;
            case 'Partial':
                $badgeClass = 'badge-info';
                $badgeText = 'PARTIAL';
                break;
            default:
                $badgeClass = 'badge-secondary';
                $badgeText = $status;
        }
    }

    return '<span class="badge ' . $badgeClass . '">' . $badgeText . '</span>';
}

/**
 * Get application status badge
 */
function getApplicationStatusBadge($status)
{
    $badgeClass = '';

    switch (strtolower($status)) {
        case 'pending':
            $badgeClass = 'badge-warning';
            break;
        case 'approved':
            $badgeClass = 'badge-success';
            break;
        case 'rejected':
            $badgeClass = 'badge-danger';
            break;
        case 'completed':
            $badgeClass = 'badge-primary';
            break;
        default:
            $badgeClass = 'badge-secondary';
    }

    return '<span class="badge ' . $badgeClass . '">' . ucfirst($status) . '</span>';
}

/**
 * Get document status with last update time (PH timezone)
 */
function getDocumentStatusWithTime($document)
{
    return [
        'status' => $document['status'],
        'updated_at' => formatPHTimestamp($document['status_updated_at'], 'M d, Y g:i A'),
        'updated_at_short' => formatPHTimestamp($document['status_updated_at'], 'M d'),
        'status_badge' => '<span class="badge badge-' .
            ($document['status'] === 'Approved' ? 'success' :
                ($document['status'] === 'Rejected' ? 'danger' : 'warning')) .
            '">' . $document['status'] . '</span>'
    ];
}

/**
 * Get interest rate update information in PH timezone
 */
function getInterestRateUpdateInfo($conn, $termLength)
{
    $query = "SELECT * FROM interest_rates WHERE term_length = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param('s', $termLength);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        $stmt->close();
        return null;
    }

    $rate = $result->fetch_assoc();
    $stmt->close();

    return [
        'rate' => $rate['interest_rate'],
        'term_length' => $rate['term_length'],
        'updated_at' => formatPHTimestamp($rate['updated_at'], 'M d, Y g:i A'),
        'updated_at_short' => formatPHTimestamp($rate['updated_at'], 'M d, Y'),
        'time_since_update' => getTimeDifference($rate['updated_at'], getCurrentPHTime())
    ];
}

?>