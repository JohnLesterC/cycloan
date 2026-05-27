# CYCLOAN Database Timezone Functions Reference

## Overview

This document provides timezone functions specifically designed for the CYCLOAN database schema. All functions are based on the actual database tables and their timestamp fields.

---

## Database Schema Timestamp Fields

### Tables with Timestamp Tracking

| Table                    | Primary Timestamp Field      | Other Timestamp Fields | Purpose                   |
| ------------------------ | ---------------------------- | ---------------------- | ------------------------- |
| `activity_logs`          | `created_at`                 | —                      | Log admin/system actions  |
| `admin1`                 | `created_at`                 | —                      | Admin1 profile creation   |
| `admin2`                 | `created_at`                 | —                      | Admin2 profile creation   |
| `credit_points_history`  | `created_at`                 | —                      | Credit point changes      |
| `credit_points_settings` | `updated_at`                 | —                      | Settings updates          |
| `documents`              | `updated_at`                 | `status_updated_at`    | Document tracking         |
| `expenditure_types`      | `created_at`                 | —                      | Expense type creation     |
| `financial_info`         | `created_at`, `updated_at`   | —                      | Financial record tracking |
| `income_sources`         | `created_at`                 | —                      | Income source creation    |
| `interest_rates`         | `updated_at`                 | —                      | Interest rate changes     |
| `invoices`               | `created_at`                 | `payment_date`         | Payment records           |
| `loans`                  | `created_at`, `updated_at`   | —                      | Loan record tracking      |
| `loan_applications`      | `created_at`, `updated_at`   | —                      | Application tracking      |
| `payment_history`        | `payment_date`, `created_at` | —                      | Payment timeline          |
| `payment_schedules`      | `created_at`, `updated_at`   | `due_date`             | Schedule management       |
| `otps`                   | `created_at`, `expires_at`   | —                      | OTP validity tracking     |
| `users1`                 | `created_at`, `updated_at`   | —                      | User registration         |

---

## Core Timezone Functions for CYCLOAN

### 1. Activity Log Functions

```php
/**
 * Get formatted activity log entry with PHT timestamp
 * Used in: admin1_dashboard.php, admin2_dashboard.php
 *
 * @param array $log Activity log row from database
 * @return array Formatted log entry with PH time
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
 * SQL: SELECT * FROM activity_logs ORDER BY created_at DESC LIMIT 10
 *
 * @param mysqli $conn Database connection
 * @param int $limit Number of logs to fetch
 * @return array Formatted activity logs
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

    return $logs;
}

/**
 * Get activity logs for specific date range in PH time
 *
 * @param mysqli $conn Database connection
 * @param string $startDate Start date in 'Y-m-d' format (PH time)
 * @param string $endDate End date in 'Y-m-d' format (PH time)
 * @return array Activity logs within date range
 */
function getActivityLogsByDateRange($conn, $startDate, $endDate)
{
    $startDt = new DateTime($startDate . ' 00:00:00', new DateTimeZone('Asia/Manila'));
    $endDt = new DateTime($endDate . ' 23:59:59', new DateTimeZone('Asia/Manila'));

    $query = "SELECT * FROM activity_logs
              WHERE created_at >= ? AND created_at <= ?
              ORDER BY created_at DESC";

    $stmt = $conn->prepare($query);
    $stmt->bind_param('ss',
        $startDt->format('Y-m-d H:i:s'),
        $endDt->format('Y-m-d H:i:s')
    );
    $stmt->execute();
    $result = $stmt->get_result();

    $logs = [];
    while ($row = $result->fetch_assoc()) {
        $logs[] = formatActivityLog($row);
    }

    return $logs;
}
```

### 2. OTP Timestamp Functions

```php
/**
 * Check if OTP is still valid (not expired) using PH time
 * Used in: process_otp1.php, verify_otp.php
 *
 * @param string $expiresAt OTP expiry timestamp from database
 * @return bool True if OTP not expired, false if expired
 */
function isOTPValid($expiresAt)
{
    $expiryTime = new DateTime($expiresAt, new DateTimeZone('Asia/Manila'));
    $currentTime = getPhilippineTime();

    return $currentTime < $expiryTime;
}

/**
 * Get remaining time for OTP validity in minutes
 *
 * @param string $expiresAt OTP expiry timestamp from database
 * @return int Remaining minutes (0 if expired, negative if past)
 */
function getOTPTimeRemaining($expiresAt)
{
    $expiryTime = new DateTime($expiresAt, new DateTimeZone('Asia/Manila'));
    $currentTime = getPhilippineTime();

    $interval = $currentTime->diff($expiryTime);

    if ($currentTime > $expiryTime) {
        return 0; // Already expired
    }

    // Calculate total minutes remaining
    $minutes = ($interval->days * 24 * 60) + ($interval->h * 60) + $interval->i;

    return $minutes;
}

/**
 * Get human-readable OTP expiry display
 * Returns format like "Expires in 5 minutes" or "Expired 2 minutes ago"
 *
 * @param string $expiresAt OTP expiry timestamp from database
 * @return string Human-readable expiry status
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
 * Generate OTP expiry timestamp (15 minutes from now in PH time)
 * Used in: process_registration.php
 *
 * @param int $minutes OTP validity in minutes (default: 15)
 * @return string OTP expiry timestamp in format 'Y-m-d H:i:s'
 */
function generateOTPExpiry($minutes = 15)
{
    $expiryTime = getPhilippineTime();
    $expiryTime->add(new DateInterval('PT' . $minutes . 'M'));

    return $expiryTime->format('Y-m-d H:i:s');
}
```

### 3. Loan Payment Functions

```php
/**
 * Format payment schedule entry with PH time
 * Used in: user_active_record.php, active_records.php
 *
 * @param array $schedule Payment schedule row from database
 * @return array Formatted schedule with timezone-aware dates
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
        'days_remaining' => $daysRemaining,
        'payment_status_badge' => getPaymentStatusBadge($schedule['status'], $isOverdue),
        'created_at' => formatPHTimestamp($schedule['created_at'], 'M d, Y g:i A')
    ];
}

/**
 * Get payment status with overdue indicator using PH time
 *
 * @param mysqli $conn Database connection
 * @param int $paymentId Payment ID
 * @return array Payment status information with PH timezone
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
        return null;
    }

    $payment = $result->fetch_assoc();
    return formatPaymentSchedule($payment);
}

/**
 * Get overdue payments for a user using PH time
 *
 * @param mysqli $conn Database connection
 * @param int $userId User ID
 * @return array Array of overdue payment schedules
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

    return $overduePayments;
}

/**
 * Get upcoming payments due within days (PH timezone aware)
 *
 * @param mysqli $conn Database connection
 * @param int $userId User ID
 * @param int $daysAhead Number of days to look ahead
 * @return array Upcoming payment schedules
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
    $stmt->bind_param('iss',
        $userId,
        $currentDate->format('Y-m-d'),
        $futureDate->format('Y-m-d')
    );
    $stmt->execute();
    $result = $stmt->get_result();

    $upcomingPayments = [];
    while ($row = $result->fetch_assoc()) {
        $upcomingPayments[] = formatPaymentSchedule($row);
    }

    return $upcomingPayments;
}
```

### 4. Loan Application Timeline Functions

```php
/**
 * Format loan application with timeline (all PH times)
 * Used in: loan_register.php, pending_records.php, closed_records.php
 *
 * @param array $application Loan application row
 * @return array Application with formatted PH times
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
        'status_badge' => getApplicationStatusBadge($application['status']),
        'timeline' => getApplicationTimeline($application)
    ];
}

/**
 * Get application processing time in human-readable format (PH timezone)
 *
 * @param string $startDate Application start timestamp
 * @param string $endDate Application end timestamp (or NULL for current)
 * @return string Human-readable processing time
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
 * Generate application timeline showing all status changes in PH time
 *
 * @param array $application Application row
 * @return array Timeline events with PH formatted times
 */
function getApplicationTimeline($application)
{
    $timeline = [];

    // Application created
    $timeline[] = [
        'event' => 'Application Created',
        'timestamp' => formatPHTimestamp($application['created_at'], 'M d, Y g:i A'),
        'status' => 'created',
        'icon' => 'document-add'
    ];

    // Application updated
    if ($application['updated_at'] !== $application['created_at']) {
        $timeline[] = [
            'event' => 'Application Updated',
            'timestamp' => formatPHTimestamp($application['updated_at'], 'M d, Y g:i A'),
            'status' => 'updated',
            'icon' => 'pencil'
        ];
    }

    return $timeline;
}
```

### 5. User Profile Registration Functions

```php
/**
 * Format user registration info with PH timestamp
 * Used in: profile.php, user_dashboard.php
 *
 * @param array $user User row from users1 table
 * @return array Formatted user info with PH times
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
        'age' => calculateAgePH($user['date_of_birth'])
    ];
}

/**
 * Calculate age based on birth date using PH timezone
 *
 * @param string $birthDate Birth date in 'Y-m-d' format
 * @return int Age in years
 */
function calculateAgePH($birthDate)
{
    if (empty($birthDate) || $birthDate === '0000-00-00') {
        return null;
    }

    $birth = new DateTime($birthDate, new DateTimeZone('Asia/Manila'));
    $current = getPhilippineTime();

    return $current->diff($birth)->y;
}

/**
 * Get profile last updated information in PH time
 *
 * @param mysqli $conn Database connection
 * @param int $userId User ID
 * @return array Last update information
 */
function getUserProfileUpdateInfo($conn, $userId)
{
    $query = "SELECT updated_at FROM users1 WHERE id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        return null;
    }

    $user = $result->fetch_assoc();

    return [
        'updated_at' => formatPHTimestamp($user['updated_at'], 'M d, Y g:i A'),
        'updated_at_short' => formatPHTimestamp($user['updated_at'], 'M d, Y'),
        'time_since_update' => getTimeDifference($user['updated_at'], getCurrentPHTime()),
        'is_recently_updated' => isWithinTimeRange($user['updated_at'], 60) // Last hour
    ];
}
```

### 6. Credit Points Tracking Functions

```php
/**
 * Format credit points history entry with PH timestamp
 * Used in: credit_points_manager.php, manage_credit_points.php
 *
 * @param array $historyRow Credit points history row
 * @return array Formatted history entry
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
        'change_badge' => $historyRow['points_change'] >= 0 ? '+' . $historyRow['points_change'] : $historyRow['points_change']
    ];
}

/**
 * Get user credit points history within date range (PH timezone)
 *
 * @param mysqli $conn Database connection
 * @param int $userId User ID
 * @param string $startDate Start date 'Y-m-d'
 * @param string $endDate End date 'Y-m-d'
 * @return array Formatted credit points history
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
    $stmt->bind_param('iss',
        $userId,
        $startDt->format('Y-m-d H:i:s'),
        $endDt->format('Y-m-d H:i:s')
    );
    $stmt->execute();
    $result = $stmt->get_result();

    $history = [];
    while ($row = $result->fetch_assoc()) {
        $history[] = formatCreditPointsHistory($row);
    }

    return $history;
}
```

### 7. Invoice and Payment Receipt Functions

```php
/**
 * Format invoice with PH timezone information
 * Used in: payment receipt, invoice generation
 *
 * @param array $invoice Invoice row from invoices table
 * @return array Formatted invoice data
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
 *
 * @param mysqli $conn Database connection
 * @param int $userId User ID
 * @param string $startDate Start date 'Y-m-d'
 * @param string $endDate End date 'Y-m-d'
 * @return array Formatted invoices
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
    $stmt->bind_param('iss',
        $userId,
        $startDt->format('Y-m-d H:i:s'),
        $endDt->format('Y-m-d H:i:s')
    );
    $stmt->execute();
    $result = $stmt->get_result();

    $receipts = [];
    while ($row = $result->fetch_assoc()) {
        $receipts[] = formatInvoice($row);
    }

    return $receipts;
}
```

---

## Helper Functions Used in Above Functions

```php
/**
 * Format time difference as human-readable string (X hours ago, X days ago, etc)
 *
 * @param string $oldTime Older timestamp
 * @param string $newTime Newer timestamp (default: current PH time)
 * @return string Human-readable time difference
 */
function getTimeDifference($oldTime, $newTime = null)
{
    if (!$newTime) {
        $newTime = getCurrentPHTime('Y-m-d H:i:s');
    }

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
}

/**
 * Check if timestamp is within X minutes from now
 *
 * @param string $timestamp Timestamp to check
 * @param int $minutes Minutes to check within
 * @return bool True if within timeframe
 */
function isWithinTimeRange($timestamp, $minutes = 60)
{
    $ts = new DateTime($timestamp, new DateTimeZone('Asia/Manila'));
    $current = getPhilippineTime();

    $interval = $current->diff($ts);
    $totalMinutes = ($interval->days * 24 * 60) + ($interval->h * 60) + $interval->i;

    return $totalMinutes <= $minutes;
}

/**
 * Get status badge HTML for payment/application
 *
 * @param string $status Status value
 * @return string HTML badge
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
 *
 * @param string $status Application status
 * @return string HTML badge
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
```

---

## Usage Examples in CYCLOAN Files

### In admin1_dashboard.php

```php
<?php
require_once 'CYCLOAN_db.php';
require_once 'timezone_config.php';
require_once 'DATABASE_TIMEZONE_FUNCTIONS.php';

// Get recent activity logs with PH timestamps
$recentLogs = getRecentActivityLogs($conn, 20);

foreach ($recentLogs as $log) {
    echo '<tr>';
    echo '<td>' . $log['admin_name'] . '</td>';
    echo '<td>' . $log['action_type'] . '</td>';
    echo '<td>' . $log['created_at'] . '</td>';
    echo '<td>' . $log['time_ago'] . '</td>';
    echo '</tr>';
}
?>
```

### In active_records.php (Payment Status)

```php
<?php
require_once 'CYCLOAN_db.php';
require_once 'timezone_config.php';
require_once 'DATABASE_TIMEZONE_FUNCTIONS.php';

// Get payment schedule with overdue status
$payments = getUpcomingPayments($conn, $userId, 30);

foreach ($payments as $payment) {
    echo '<tr>';
    echo '<td>' . $payment['due_date'] . '</td>';
    echo '<td>' . $payment['amount'] . '</td>';
    echo '<td>' . $payment['payment_status_badge'] . '</td>';
    if ($payment['is_overdue']) {
        echo '<td class="text-danger">Overdue by ' . $payment['days_remaining'] . ' days</td>';
    }
    echo '</tr>';
}
?>
```

### In profile.php (User Registration Info)

```php
<?php
require_once 'CYCLOAN_db.php';
require_once 'timezone_config.php';
require_once 'DATABASE_TIMEZONE_FUNCTIONS.php';

// Get user profile with registration times
$userInfo = formatUserRegistration($user);

echo '<p>Member since: ' . $userInfo['member_since'] . '</p>';
echo '<p>Registered: ' . $userInfo['registered_at'] . '</p>';
echo '<p>Member for: ' . $userInfo['member_for'] . '</p>';
?>
```

---

## SQL Queries for Date Range Filtering (PH Timezone)

When filtering by date range in PHP:

```php
// Example: Get all payments from Oct 1 to Oct 31, 2025 (PH time)
$startDate = new DateTime('2025-10-01 00:00:00', new DateTimeZone('Asia/Manila'));
$endDate = new DateTime('2025-10-31 23:59:59', new DateTimeZone('Asia/Manila'));

$query = "SELECT * FROM invoices
          WHERE created_at BETWEEN ? AND ?
          ORDER BY created_at DESC";

$stmt = $conn->prepare($query);
$stmt->bind_param('ss',
    $startDate->format('Y-m-d H:i:s'),
    $endDate->format('Y-m-d H:i:s')
);
$stmt->execute();
```

---

## Database Verification Queries

Run these queries to verify timezone setup in MySQL:

```sql
-- Check session timezone
SELECT @@session.time_zone;

-- Should return: +08:00

-- Get current time in MySQL
SELECT NOW();

-- Should show current Philippine time

-- Check all timestamps recorded today (PH time)
SELECT * FROM activity_logs WHERE DATE(created_at) = CURDATE();

-- Get payment activity from past 7 days
SELECT * FROM invoices
WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
ORDER BY created_at DESC;
```

---

## Summary

All CYCLOAN database tables now have timezone-aware functions for:

- ✅ Displaying timestamps in Philippine Time
- ✅ Calculating time differences and countdowns
- ✅ Filtering data by date ranges in PH timezone
- ✅ Detecting overdue payments
- ✅ Tracking activity with correct local times
- ✅ Generating reports with accurate timestamps

Use these functions instead of raw database timestamps to ensure consistency across the system.
