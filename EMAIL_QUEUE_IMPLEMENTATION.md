# Email Consolidation System - Implementation Checklist

## Phase 1: Database Setup ✓ COMPLETE

- [x] Create `email_queue.sql` with required tables
  - email_queue (event queue)
  - email_batch_log (batch tracking)
  - email_queue_settings (configuration)

## Phase 2: Queue Processing Script ✓ COMPLETE

- [x] Create `process_email_queue.php`
  - Loads settings from database
  - Gets pending batches ready to process
  - Consolidates events into email data
  - Calls sendConsolidatedUpdateEmail() once per batch
  - Marks events as sent
  - Cleans up old records

## Phase 3: Integration with Admin Dashboard

### Step 1: Initialize Queue in admin2_dashboard.php

```php
// At the top of the file after other includes
require_once 'process_email_queue.php';
```

### Step 2: Replace Email Sends in Document Status Update

**Location:** Around line 1979

**BEFORE:**

```php
$consolidatedUpdates = [
    'admin_name' => $adminName,
    'admin_updated_at' => date('F j, Y \a\t g:i A'),
    'documents' => [$documentInfo],
    'all_documents' => $allDocuments
];

error_log("DOCUMENT_UPDATE: Attempting to send email for App ID: $applicationId, Document: {$document['document_name']}, Status: $newStatus", E_USER_NOTICE);
$emailSent = sendConsolidatedUpdateEmail($conn, $applicationId, $consolidatedUpdates);
```

**AFTER:**

```php
// Queue the event instead of sending immediately
$eventData = [
    'document_id' => $documentId,
    'document_name' => $document['document_name'],
    'status' => $newStatus,
    'reason' => $rejectionReason ?? null
];

addToEmailQueue($conn, $applicationId, 'document_' . strtolower($newStatus), $eventData);
error_log("DOCUMENT_UPDATE_QUEUED: Event queued for App ID: $applicationId, Document: {$document['document_name']}, Status: $newStatus", E_USER_NOTICE);
```

### Step 3: Replace Email Sends in Pre-Approval Decision

**Location:** Around line 1710-1800

**BEFORE:**

```php
if (($preApprovalStatus === 'Approved' || $preApprovalStatus === 'Rejected') && $preApprovalStatus !== $currentData['pre_approval_status']) {
    // ... fetch remarks ...

    if (!sendConsolidatedUpdateEmail($conn, $applicationId, $consolidatedUpdates)) {
```

**AFTER:**

```php
if (($preApprovalStatus === 'Approved' || $preApprovalStatus === 'Rejected') && $preApprovalStatus !== $currentData['pre_approval_status']) {
    // Queue the pre-approval decision
    $eventData = [
        'status' => $preApprovalStatus,
        'reason' => $approvalReason ?? null
    ];

    addToEmailQueue($conn, $applicationId, 'pre_approval_status', $eventData);
    error_log("PREAPPROVAL_QUEUED: Pre-approval decision queued for App ID: $applicationId, Status: $preApprovalStatus", E_USER_NOTICE);
```

### Step 4: Replace Email Sends in Remark Addition

**Location:** Around line 1700 (or search for "createRemarkNotification")

**BEFORE:**

```php
if (!empty($remarks)) {
    // Insert remark
    $remarkInsert = executeUpdate($conn, "INSERT INTO remarks ...");

    // Send email
    sendRemarkEmail($conn, $applicationId, $remarks);
}
```

**AFTER:**

```php
if (!empty($remarks)) {
    // Insert remark
    $remarkInsert = executeUpdate($conn, "INSERT INTO remarks ...");

    // Queue remark notification
    $eventData = ['remark' => $remarks];
    addToEmailQueue($conn, $applicationId, 'remark_added', $eventData);
    error_log("REMARK_QUEUED: Remark queued for App ID: $applicationId", E_USER_NOTICE);
}
```

### Step 5: Add Queue Helper Function

Add this function to admin2_dashboard.php (near other helper functions):

```php
/**
 * Add an event to the email queue instead of sending immediately
 * Events are consolidated and sent in batches
 */
function addToEmailQueue($conn, $applicationId, $eventType, $eventData = [])
{
    try {
        // Get user_id
        $userQuery = "SELECT user_id FROM loan_applications WHERE application_id = ?";
        $userStmt = $conn->prepare($userQuery);
        if (!$userStmt) {
            error_log("EMAIL_QUEUE_USER_ERROR: Failed to get user for app $applicationId", E_USER_WARNING);
            return false;
        }

        $userStmt->bind_param("s", $applicationId);
        $userStmt->execute();
        $userResult = $userStmt->get_result();
        $userRow = $userResult->fetch_assoc();
        $userStmt->close();

        if (!$userRow) {
            error_log("EMAIL_QUEUE_APP_NOT_FOUND: Application not found: $applicationId", E_USER_WARNING);
            return false;
        }

        $userId = $userRow['user_id'];
        $eventDataJson = json_encode($eventData);

        // Get or create batch
        $cutoffTime = date('Y-m-d H:i:s', strtotime('-300 seconds'));
        $batchQuery = "SELECT batch_id FROM email_batch_log
                       WHERE application_id = ? AND user_id = ? AND status = 'pending'
                       AND batch_start_time > ? LIMIT 1";
        $batchStmt = $conn->prepare($batchQuery);

        $batchId = null;
        if ($batchStmt) {
            $batchStmt->bind_param("sis", $applicationId, $userId, $cutoffTime);
            $batchStmt->execute();
            $batchResult = $batchStmt->get_result();
            $batchRow = $batchResult->fetch_assoc();
            $batchStmt->close();

            if ($batchRow) {
                $batchId = $batchRow['batch_id'];
            }
        }

        // Create new batch if needed
        if (!$batchId) {
            $batchId = 'BATCH_' . $applicationId . '_' . time() . '_' . uniqid();
            $now = date('Y-m-d H:i:s');

            $createBatchQuery = "INSERT INTO email_batch_log
                                 (batch_id, application_id, user_id, batch_start_time, status)
                                 VALUES (?, ?, ?, ?, 'pending')";
            $createBatchStmt = $conn->prepare($createBatchQuery);
            if ($createBatchStmt) {
                $createBatchStmt->bind_param("ssis", $batchId, $applicationId, $userId, $now);
                $createBatchStmt->execute();
                $createBatchStmt->close();
            }
        }

        // Insert event into queue
        $insertQuery = "INSERT INTO email_queue (application_id, user_id, event_type, event_data, batch_id)
                        VALUES (?, ?, ?, ?, ?)";
        $insertStmt = $conn->prepare($insertQuery);
        if (!$insertStmt) {
            error_log("EMAIL_QUEUE_INSERT_ERROR: Failed to prepare insert", E_USER_WARNING);
            return false;
        }

        $insertStmt->bind_param("sisss", $applicationId, $userId, $eventType, $eventDataJson, $batchId);
        $result = $insertStmt->execute();
        $insertStmt->close();

        if ($result) {
            error_log("EMAIL_QUEUE_ADDED: Event queued - App: $applicationId, Type: $eventType, Batch: $batchId", E_USER_NOTICE);
            return true;
        } else {
            error_log("EMAIL_QUEUE_ADD_FAILED: Failed to add event", E_USER_WARNING);
            return false;
        }
    } catch (Exception $e) {
        error_log("EMAIL_QUEUE_EXCEPTION: " . $e->getMessage(), E_USER_WARNING);
        return false;
    }
}
```

## Phase 4: Setup Cron Job for Processing

### Linux/Unix:

```bash
# Create cron job
crontab -e

# Add this line (runs every 5 minutes)
*/5 * * * * /usr/bin/php /var/www/html/cycloan/process_email_queue.php >> /var/log/cycloan_email_queue.log 2>&1
```

### Windows Task Scheduler:

1. Create batch file: `C:\cycloan\run_email_queue.bat`

   ```batch
   @echo off
   cd C:\xampp\htdocs\cycloan
   php process_email_queue.php
   ```

2. Create scheduled task:
   - Program: `C:\Windows\System32\cmd.exe`
   - Arguments: `/c C:\cycloan\run_email_queue.bat`
   - Trigger: Repeat every 5 minutes

## Phase 5: Testing & Validation

### Test 1: Database Setup

```sql
SELECT COUNT(*) as queue_events FROM email_queue;
SELECT COUNT(*) as batch_logs FROM email_batch_log;
SELECT * FROM email_queue_settings LIMIT 10;
```

### Test 2: Queue Events

1. Go to admin dashboard
2. Select a loan application
3. Reject 2-3 documents
4. Check `email_queue` table - should see events
5. Check `email_batch_log` table - should see pending batch

### Test 3: Process Queue Manually

```bash
php process_email_queue.php
```

Check logs:

```bash
tail -f /var/log/php-fpm.log | grep EMAIL_QUEUE
```

### Test 4: Verify Email

1. Check email_batch_log - status should be 'sent'
2. Check user's email inbox - should have 1 email with all updates
3. Email should show all rejected documents with reasons

### Test 5: Verify No Duplicates

- Repeat test 2-4 with another application
- Confirm only 1 email received (not multiple)

## Phase 6: Configuration Adjustments

Fine-tune based on your needs:

```sql
-- Faster processing (process every 1 minute instead of 5)
UPDATE email_queue_settings
SET setting_value = '1'
WHERE setting_key = 'batch_delay_minutes';

-- Larger batch window (collect events for 10 minutes instead of 5)
UPDATE email_queue_settings
SET setting_value = '600'
WHERE setting_key = 'batch_window_seconds';

-- Disable queue temporarily if needed
UPDATE email_queue_settings
SET setting_value = '0'
WHERE setting_key = 'enable_queue';
```

## Phase 7: Monitoring

### View Pending Events:

```sql
SELECT queue_id, application_id, event_type, created_at
FROM email_queue
WHERE processed = FALSE
ORDER BY created_at DESC
LIMIT 20;
```

### View Sent Emails:

```sql
SELECT batch_id, application_id, event_count, sent_timestamp, status
FROM email_batch_log
WHERE status = 'sent'
ORDER BY sent_timestamp DESC
LIMIT 20;
```

### View Failed Emails:

```sql
SELECT batch_id, application_id, error_message, created_at
FROM email_batch_log
WHERE status = 'failed'
ORDER BY created_at DESC;
```

## Troubleshooting

### Issue: Events never get sent

- Check if cron job is running: `ps aux | grep process_email_queue.php`
- Check if `enable_queue` = 1 in settings
- Check PHP error logs

### Issue: Still getting multiple emails

- Verify `sendConsolidatedUpdateEmail()` is not being called directly
- Check that all document updates use `addToEmailQueue()`
- Check email_queue table for events

### Issue: Emails taking too long to send

- Reduce `batch_delay_minutes` in settings
- Reduce `batch_window_seconds` to consolidate faster
- Check server performance and mail queue

## Success Criteria

✅ Single email sent per application decision (not per document)
✅ Email contains all document updates + pre-approval decision
✅ No missing information in consolidated email
✅ User receives email within 5-10 minutes of decision
✅ No emails marked as spam by mail filter
✅ Email queue processing completes without errors
✅ No duplicate emails sent

## Support

For issues:

1. Check process_email_queue.php logs
2. Review email_queue and email_batch_log tables
3. Verify cron job is executing
4. Test manual processing: `php process_email_queue.php`
