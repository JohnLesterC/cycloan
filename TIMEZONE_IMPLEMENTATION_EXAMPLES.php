<?php
/**
 * TIMEZONE IMPLEMENTATION EXAMPLES
 * 
 * This file shows practical examples of how to use the timezone functions
 * in real application scenarios.
 * 
 * DO NOT RUN THIS FILE DIRECTLY - It's for reference only
 * Copy the patterns shown here into your actual PHP files
 */

// =============================================================================
// EXAMPLE 1: Display Timestamps in Admin Dashboard
// =============================================================================

// In admin1_dashboard.php, activity_logs display:
?>
<!-- BEFORE (Wrong) -->
<td><?php echo date('Y-m-d H:i:s', strtotime($log['created_at'])); ?></td>

<!-- AFTER (Correct) -->
<td><?php 
    require_once 'timezone_config.php';
    echo formatPHTimestamp($log['created_at']); 
?></td>

<!-- AFTER (Custom format) -->
<td><?php echo formatPHTimestamp($log['created_at'], 'M d, Y g:i A'); ?></td>
<?php

// =============================================================================
// EXAMPLE 2: OTP Expiry Verification
// =============================================================================

// In process_otp1.php or verify_otp.php:
?>
<?php
// Add this at the top of the file
require_once 'timezone_config.php';

// Check if OTP is expired
if (!empty($otp_data['expires_at'])) {
    $remaining = getTimeRemaining($otp_data['expires_at']);
    
    if ($remaining['expired']) {
        $_SESSION['error_message'] = "OTP has expired. Please request a new one.";
        header("Location: resend_otp.php");
        exit;
    }
    
    // Show countdown to user (optional)
    echo "OTP expires in: " . $remaining['display']; // e.g., "5 minutes 32 seconds remaining"
}
?>

<?php
// =============================================================================
// EXAMPLE 3: Creating OTP with Correct Expiry Time
// =============================================================================

// In process_registration.php when generating OTP:
?>
<?php
require_once 'timezone_config.php';

// Set OTP to expire in 10 minutes (Philippines time)
$expires_at = (new DateTime('now', new DateTimeZone('Asia/Manila')))
    ->modify('+10 minutes')
    ->format('Y-m-d H:i:s');

// Insert into database
$stmt = $conn->prepare("INSERT INTO otps (user_id, code, expires_at) VALUES (?, ?, ?)");
$stmt->bind_param("iss", $user_id, $otp_code, $expires_at);
$stmt->execute();
?>

<?php
// =============================================================================
// EXAMPLE 4: Activity Log Display
// =============================================================================

// In admin dashboard showing activity logs:
?>
<table>
    <tr>
        <th>Time</th>
        <th>User</th>
        <th>Action</th>
        <th>Time Ago</th>
    </tr>
    <?php
    require_once 'timezone_config.php';
    
    foreach ($logs as $log) {
        echo "<tr>";
        echo "<td>" . formatPHTimestamp($log['created_at']) . "</td>";
        echo "<td>" . htmlspecialchars($log['user_name']) . "</td>";
        echo "<td>" . htmlspecialchars($log['action']) . "</td>";
        echo "<td>" . getTimeDifference($log['created_at']) . "</td>";
        echo "</tr>";
    }
    ?>
</table>

<?php
// =============================================================================
// EXAMPLE 5: Payment Due Date Alerts
// =============================================================================

// In user dashboard or active records:
?>
<?php
require_once 'timezone_config.php';

// Get all upcoming payments due in next 30 days
$today = getStartOfDayPH()->format('Y-m-d');
$thirtyDaysLater = (new DateTime('now', new DateTimeZone('Asia/Manila')))
    ->modify('+30 days')
    ->format('Y-m-d');

$stmt = $conn->prepare("
    SELECT * FROM payment_schedules 
    WHERE due_date BETWEEN ? AND ?
    AND status != 'Paid'
    ORDER BY due_date ASC
");
$stmt->bind_param("ss", $today, $thirtyDaysLater);
$stmt->execute();
$result = $stmt->get_result();

while ($payment = $result->fetch_assoc()) {
    $remaining = getTimeRemaining($payment['due_date']);
    echo "Payment due " . formatPHDate($payment['due_date']);
    echo " - " . $remaining['display'];
}
?>

<?php
// =============================================================================
// EXAMPLE 6: Registration Form with Timestamp
// =============================================================================

// In process_registration.php when saving user:
?>
<?php
require_once 'timezone_config.php';

// The database will use NOW() which automatically uses +08:00 timezone
$stmt = $conn->prepare("
    INSERT INTO users1 
    (email, password, first_name, last_name, created_at) 
    VALUES (?, ?, ?, ?, NOW())
");
$stmt->bind_param("ssss", $email, $password, $first_name, $last_name);
$stmt->execute();

// The created_at will automatically be set to Philippine time
?>

<?php
// =============================================================================
// EXAMPLE 7: Displaying Registration Date in Profile
// =============================================================================

// In profile.php:
?>
<div class="profile-info">
    <p>
        <strong>Member Since:</strong> 
        <?php echo formatPHTimestamp($user['created_at'], 'F d, Y'); ?>
    </p>
    <p>
        <strong>Member For:</strong> 
        <?php echo getTimeDifference($user['created_at']); ?>
    </p>
</div>

<?php
// =============================================================================
// EXAMPLE 8: Age Calculation Based on Philippine Time
// =============================================================================

// In process_registration.php or verification:
?>
<?php
require_once 'timezone_config.php';

// Calculate if user is 18 years old (as of Philippine time)
$age = calculateAgePH($user['birthday']);

if ($age >= 18) {
    echo "Age requirement met";
} else {
    $_SESSION['error'] = "Must be at least 18 years old";
}
?>

<?php
// =============================================================================
// EXAMPLE 9: Loan Application Timeline
// =============================================================================

// In loan details view:
?>
<div class="timeline">
    <div class="timeline-item">
        <span class="timeline-label">Application Submitted:</span>
        <span class="timeline-date">
            <?php echo formatPHTimestamp($loan['created_at'], 'M d, Y \a\t g:i A'); ?>
        </span>
    </div>
    
    <div class="timeline-item">
        <span class="timeline-label">Approved:</span>
        <span class="timeline-date">
            <?php echo formatPHTimestamp($loan['approved_at'], 'M d, Y \a\t g:i A'); ?>
        </span>
    </div>
    
    <div class="timeline-item">
        <span class="timeline-label">Time to Approval:</span>
        <span class="timeline-duration">
            <?php
            $approved = new DateTime($loan['approved_at'], new DateTimeZone('Asia/Manila'));
            $submitted = new DateTime($loan['created_at'], new DateTimeZone('Asia/Manila'));
            $diff = $submitted->diff($approved);
            echo $diff->d . " days, " . $diff->h . " hours";
            ?>
        </span>
    </div>
</div>

<?php
// =============================================================================
// EXAMPLE 10: Generating Reports with Timestamps
// =============================================================================

// In reports_record.php:
?>
<?php
require_once 'timezone_config.php';

// Generate report for records created in the last 30 days
$thirtyDaysAgo = (new DateTime('now', new DateTimeZone('Asia/Manila')))
    ->modify('-30 days')
    ->format('Y-m-d H:i:s');

$stmt = $conn->prepare("
    SELECT * FROM loan_applications 
    WHERE created_at >= ? 
    ORDER BY created_at DESC
");
$stmt->bind_param("s", $thirtyDaysAgo);
$stmt->execute();
$result = $stmt->get_result();

// Display report with timestamps
while ($record = $result->fetch_assoc()) {
    echo "Application: " . $record['application_id'];
    echo " - Created: " . formatPHTimestamp($record['created_at']);
    echo " - Duration: " . getTimeDifference($record['created_at']);
    echo "<br>";
}
?>

<?php
// =============================================================================
// EXAMPLE 11: Admin Action Logging
// =============================================================================

// When admin performs an action:
?>
<?php
require_once 'timezone_config.php';

// The database will automatically log with correct timezone
$stmt = $conn->prepare("
    INSERT INTO activity_logs 
    (user_id, user_role, action_type, module, description, created_at) 
    VALUES (?, ?, ?, ?, ?, NOW())
");
$stmt->bind_param("issss", 
    $_SESSION['user_id'],
    $_SESSION['role'],
    'UPDATE',
    'Loan',
    'Updated loan status to Approved'
);
$stmt->execute();

// To display this log later:
$stmt = $conn->prepare("SELECT * FROM activity_logs WHERE id = ?");
$stmt->bind_param("i", $log_id);
$stmt->execute();
$log = $stmt->get_result()->fetch_assoc();

echo "Action performed: " . formatPHTimestamp($log['created_at']);
?>

<?php
// =============================================================================
// EXAMPLE 12: Due Date Highlighting
// =============================================================================

// In closed_records.php or active_records.php:
?>
<?php
require_once 'timezone_config.php';

// Highlight overdue payments
foreach ($payments as $payment) {
    $today = new DateTime('now', new DateTimeZone('Asia/Manila'));
    $dueDate = new DateTime($payment['due_date'], new DateTimeZone('Asia/Manila'));
    
    if ($today > $dueDate && $payment['status'] != 'Paid') {
        $daysOverdue = $today->diff($dueDate)->days;
        echo '<span style="color: red;">';
        echo formatPHDate($payment['due_date']);
        echo " - OVERDUE by $daysOverdue days";
        echo '</span>';
    } else {
        echo '<span style="color: green;">';
        echo formatPHDate($payment['due_date']);
        echo " - " . $payment['status'];
        echo '</span>';
    }
}
?>

<?php
// =============================================================================
// EXAMPLE 13: System Verification Endpoint
// =============================================================================

// Create a verify_timezone.php file for debugging:
?>
<?php
header('Content-Type: application/json');
require_once 'timezone_config.php';

$info = getTimezoneInfo();
$info['database_timezone'] = 'Run: SELECT @@session.time_zone;';
$info['sample_db_timestamp'] = 'Check any created_at field';

echo json_encode($info, JSON_PRETTY_PRINT);
// Output should show timezone_correct: true
?>

<?php
// =============================================================================
// EXAMPLE 14: Email Notifications with Timestamps
// =============================================================================

// In email_templates.php or send_email.php:
?>
<?php
require_once 'timezone_config.php';

$emailBody = "
    Dear " . $user['first_name'] . ",
    
    Your payment is due on " . formatPHDate($payment['due_date']) . ".
    
    Date/Time Sent: " . formatPHTimestamp(getCurrentPHTime(), 'F d, Y g:i A') . "
    
    Thank you,
    CYCLOAN Team
";
?>

<?php
// =============================================================================
// BEST PRACTICES SUMMARY
// =============================================================================

/**
 * BEST PRACTICES FOR TIMEZONE HANDLING
 * 
 * ✅ DO:
 * - Use formatPHTimestamp() when displaying database timestamps
 * - Use NOW() in SQL queries (automatically uses +08:00)
 * - Use getPhilippineTime() when you need DateTime object
 * - Use getTimeRemaining() for deadline checks
 * - Use timezone_config.php functions consistently
 * 
 * ❌ DON'T:
 * - Use strtotime() without timezone awareness
 * - Mix UTC and Philippine times in comparisons
 * - Hardcode timezone offsets
 * - Use date() without setting timezone first
 * - Forget to include timezone_config.php
 * 
 * 📋 CHECKLIST:
 * - Set timezone in CYCLOAN_db.php (DONE ✅)
 * - Include timezone_config.php in files that need it
 * - Use helper functions for all date operations
 * - Test on actual Philippines timezone
 * - Verify database timezone: SELECT @@session.time_zone;
 */

?>
