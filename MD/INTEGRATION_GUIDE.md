# CYCLOAN Timezone Integration Guide

## Quick Start: Using Database Timezone Functions

### Step 1: Include the Files in Your PHP Scripts

Add these two lines at the top of any file where you need timezone functions:

```php
<?php
require_once 'CYCLOAN_db.php';  // Database connection (already has timezone set)
require_once 'timezone_config.php';  // Core timezone functions
require_once 'database_timezone_functions.php';  // Database-specific functions

// Your code here...
?>
```

### Step 2: Use in Your PHP Files

---

## Common Use Cases

### 1. Display Activity Logs with Timestamps

**File: admin1_dashboard.php**

```php
<?php
require_once 'CYCLOAN_db.php';
require_once 'timezone_config.php';
require_once 'database_timezone_functions.php';

// Get recent activity logs
$recentLogs = getRecentActivityLogs($conn, 10);

?>
<table>
  <thead>
    <tr>
      <th>Admin</th>
      <th>Action</th>
      <th>Time</th>
      <th>Status</th>
    </tr>
  </thead>
  <tbody>
    <?php foreach ($recentLogs as $log): ?>
      <tr>
        <td><?php echo htmlspecialchars($log['admin_name']); ?></td>
        <td><?php echo htmlspecialchars($log['action_type']); ?></td>
        <td><?php echo $log['created_at']; ?></td>
        <td><?php echo $log['time_ago']; ?></td>
      </tr>
    <?php endforeach; ?>
  </tbody>
</table>
```

### 2. Display Payment Status with Overdue Alerts

**File: active_records.php**

```php
<?php
require_once 'CYCLOAN_db.php';
require_once 'timezone_config.php';
require_once 'database_timezone_functions.php';

// Get all upcoming payments for user
$userId = $_SESSION['user_id'];
$upcomingPayments = getUpcomingPayments($conn, $userId, 30);
$overduePayments = getOverduePayments($conn, $userId);

?>
<!-- Overdue Alert -->
<?php if (!empty($overduePayments)): ?>
  <div class="alert alert-danger">
    <h4>⚠️ Overdue Payments</h4>
    <?php foreach ($overduePayments as $payment): ?>
      <p>
        Payment of ₱<?php echo number_format($payment['amount'], 2); ?>
        was due <?php echo $payment['due_date']; ?>
      </p>
    <?php endforeach; ?>
  </div>
<?php endif; ?>

<!-- Upcoming Payments Table -->
<table class="table">
  <thead>
    <tr>
      <th>Due Date</th>
      <th>Amount</th>
      <th>Status</th>
      <th>Days Left</th>
    </tr>
  </thead>
  <tbody>
    <?php foreach ($upcomingPayments as $payment): ?>
      <tr>
        <td><?php echo $payment['due_date_full']; ?></td>
        <td>₱<?php echo number_format($payment['amount'], 2); ?></td>
        <td><?php echo $payment['payment_status_badge']; ?></td>
        <td><?php echo $payment['days_remaining']; ?> days</td>
      </tr>
    <?php endforeach; ?>
  </tbody>
</table>
```

### 3. Verify OTP Expiry

**File: verify_otp.php**

```php
<?php
require_once 'CYCLOAN_db.php';
require_once 'timezone_config.php';
require_once 'database_timezone_functions.php';

$otp = $_SESSION['otp'];
$expiresAt = $_SESSION['otp_expires_at'];

// Check if OTP is valid
if (!isOTPValid($expiresAt)) {
    echo "OTP has expired. Please request a new one.";
    exit;
}

// Show remaining time
$remaining = getOTPTimeRemaining($expiresAt);
echo "OTP expires in " . $remaining . " minutes";
echo "  (" . getOTPExpiryDisplay($expiresAt) . ")";

// Verify OTP code
if ($_POST['otp_code'] === $otp) {
    // OTP matches
    $_SESSION['otp_verified'] = true;
    redirect('registration.php?step=next');
}
?>
```

### 4. Display User Profile with Registration Date

**File: profile.php**

```php
<?php
require_once 'CYCLOAN_db.php';
require_once 'timezone_config.php';
require_once 'database_timezone_functions.php';

$userId = $_SESSION['user_id'];
$query = "SELECT * FROM users1 WHERE id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param('i', $userId);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$stmt->close();

// Format user info with PH timezone
$userInfo = formatUserRegistration($user);

?>
<div class="profile-card">
  <h3><?php echo htmlspecialchars($userInfo['full_name']); ?></h3>
  <p>Email: <?php echo htmlspecialchars($userInfo['email']); ?></p>
  <p>Member Since: <?php echo $userInfo['registered_at']; ?></p>
  <p>Member For: <?php echo $userInfo['member_for']; ?></p>
  <p>Age: <?php echo $userInfo['age']; ?> years old</p>
  <p>Last Updated: <?php echo $userInfo['last_updated']; ?></p>
</div>
```

### 5. Display Payment History/Receipts

**File: user_active_record.php**

```php
<?php
require_once 'CYCLOAN_db.php';
require_once 'timezone_config.php';
require_once 'database_timezone_functions.php';

$userId = $_SESSION['user_id'];
$startDate = $_GET['start_date'] ?? date('Y-m-d', strtotime('-30 days'));
$endDate = $_GET['end_date'] ?? date('Y-m-d');

// Get payment receipts for the date range
$receipts = getPaymentReceiptsByDateRange($conn, $userId, $startDate, $endDate);

// Get invoices created today
$todayInvoices = getInvoicesCreatedToday($conn, $userId);

?>
<!-- Today's Payments -->
<?php if (!empty($todayInvoices)): ?>
  <div class="section">
    <h4>Today's Payments</h4>
    <table class="table">
      <thead>
        <tr>
          <th>Invoice #</th>
          <th>Amount</th>
          <th>Time</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($todayInvoices as $invoice): ?>
          <tr>
            <td><?php echo $invoice['invoice_number']; ?></td>
            <td><?php echo $invoice['amount_paid']; ?></td>
            <td><?php echo $invoice['created_at_short']; ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
<?php endif; ?>

<!-- Payment History -->
<div class="section">
  <h4>Payment History (<?php echo $startDate; ?> to <?php echo $endDate; ?>)</h4>
  <table class="table">
    <thead>
      <tr>
        <th>Receipt #</th>
        <th>Amount</th>
        <th>Principal</th>
        <th>Interest</th>
        <th>Date</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($receipts as $receipt): ?>
        <tr>
          <td><?php echo $receipt['invoice_number']; ?></td>
          <td><?php echo $receipt['amount_paid']; ?></td>
          <td><?php echo $receipt['principal_paid']; ?></td>
          <td><?php echo $receipt['interest_paid']; ?></td>
          <td><?php echo $receipt['created_at_full']; ?></td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>
```

### 6. Get Loan Application Status

**File: loan_register.php**

```php
<?php
require_once 'CYCLOAN_db.php';
require_once 'timezone_config.php';
require_once 'database_timezone_functions.php';

$applicationId = $_GET['app_id'];

$query = "SELECT * FROM loan_applications WHERE application_id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param('s', $applicationId);
$stmt->execute();
$result = $stmt->get_result();
$application = $result->fetch_assoc();
$stmt->close();

// Format with PH timezone
$appInfo = formatLoanApplication($application);
$processingTime = getApplicationProcessingTime($application['created_at'], $application['updated_at']);

?>
<div class="app-details">
  <p>Application ID: <?php echo htmlspecialchars($appInfo['application_id']); ?></p>
  <p>Status: <?php echo $appInfo['status_badge']; ?></p>
  <p>Amount: ₱<?php echo number_format($appInfo['loan_amount'], 2); ?></p>
  <p>Created: <?php echo $appInfo['created_at']; ?></p>
  <p>Processing Time: <?php echo $processingTime; ?></p>
  <p>Days Active: <?php echo $appInfo['days_active']; ?> days</p>
</div>
```

### 7. Log Activity with Timestamp

**File: Any handler (e.g., process_registration.php)**

```php
<?php
require_once 'CYCLOAN_db.php';
require_once 'timezone_config.php';
require_once 'database_timezone_functions.php';

// Your processing code...

// Log the activity with current PH timestamp
$userId = $_SESSION['user_id'];
$action = 'create';
$module = 'registration';
$description = 'User completed registration';

// This will automatically use PHT because both PHP and MySQL are timezone-aware
$logQuery = "INSERT INTO activity_logs
  (user_id, user_role, action_type, module, description, created_at)
VALUES (?, ?, ?, ?, ?, NOW())";

$stmt = $conn->prepare($logQuery);
$stmt->bind_param('issss', $userId, $_SESSION['role'], $action, $module, $description);
$stmt->execute();
$stmt->close();

// Confirm the activity was logged correctly
$recentLogs = getRecentActivityLogs($conn, 1);
echo "Activity logged at: " . $recentLogs[0]['created_at'];

?>
```

---

## Function Reference Quick Lookup

### Activity Logs

- `getRecentActivityLogs($conn, $limit)` - Get recent activity with PH times
- `getActivityLogsByDateRange($conn, $startDate, $endDate)` - Filter by date range
- `getActivityCountByUserToday($conn, $userId)` - Count activities today

### OTP Management

- `isOTPValid($expiresAt)` - Check if OTP hasn't expired
- `getOTPTimeRemaining($expiresAt)` - Get remaining minutes
- `getOTPExpiryDisplay($expiresAt)` - Human-readable expiry text
- `generateOTPExpiry($minutes)` - Generate expiry timestamp

### Payment Tracking

- `getOverduePayments($conn, $userId)` - Get overdue payments
- `getUpcomingPayments($conn, $userId, $daysAhead)` - Get due soon
- `getTotalOverdueAmount($conn, $userId)` - Calculate total overdue
- `formatPaymentSchedule($schedule)` - Format single payment

### Applications

- `formatLoanApplication($application)` - Format app with times
- `getApplicationProcessingTime($startDate, $endDate)` - Time elapsed
- `getPendingApplicationsWithAge($conn)` - Get pending with age

### User Profiles

- `formatUserRegistration($user)` - Format user info with times
- `calculateAgePH($birthDate)` - Calculate age in PH timezone
- `getUserProfileUpdateInfo($conn, $userId)` - Last update info

### Credit Points

- `formatCreditPointsHistory($historyRow)` - Format history entry
- `getUserCreditPointsHistory($conn, $userId, $start, $end)` - Date range

### Invoices

- `formatInvoice($invoice)` - Format invoice with times
- `getPaymentReceiptsByDateRange($conn, $userId, $start, $end)` - Date range
- `getInvoicesCreatedToday($conn, $userId)` - Today's invoices

### Helpers

- `getTimeDifference($oldTime, $newTime)` - "X hours ago" format
- `isWithinTimeRange($timestamp, $minutes)` - Check if recent
- `getPaymentStatusBadge($status, $isOverdue)` - HTML badge
- `getApplicationStatusBadge($status)` - HTML badge

---

## Database Verification

Before using these functions, verify the timezone is set correctly:

```sql
-- Check timezone setting
SELECT @@session.time_zone;
-- Should return: +08:00

-- Get current time
SELECT NOW();
-- Should show current Philippine time

-- Check a recent activity log
SELECT * FROM activity_logs ORDER BY created_at DESC LIMIT 1;
-- The created_at should match current PH time
```

---

## Troubleshooting

### Timestamps still show wrong time?

1. Verify `CYCLOAN_db.php` has both timezone settings
2. Restart PHP (clear OPcache if enabled)
3. Run: `SELECT NOW()` in MySQL to verify

### OTP expires too quickly?

- Check `generateOTPExpiry($minutes)` is called with correct minutes value
- Verify MySQL timezone with: `SELECT @@session.time_zone;`

### Dates appear in wrong order?

- All functions use DateTime with 'Asia/Manila' timezone
- Functions compare timestamps correctly regardless of storage

### Display format wrong?

- Use function parameters to change format
- Example: `formatPHTimestamp($ts, 'M d, Y')` for "Nov 02, 2025"

---

## Summary

✅ **All timezone functions are ready to use**
✅ **Database is timezone-aware**
✅ **All timestamps automatically use Philippine Time**
✅ **No manual timezone conversion needed**

Just include the three files and use the functions!
