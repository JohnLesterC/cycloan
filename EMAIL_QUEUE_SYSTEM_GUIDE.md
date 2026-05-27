# Email Queue & Consolidation System - Implementation Guide

## Current Problem
- **Issue**: Each document update sends a separate email
- **Impact**: Users receive multiple emails per session (feels like spam)
- **Root Cause**: `sendConsolidatedUpdateEmail()` called immediately after each document status change

## Proposed Solution: Email Queue System

### Architecture Overview

```
Document Update 1 → Queue Entry
Document Update 2 → Queue Entry  
Document Update 3 → Queue Entry
        ↓
    (Wait 30 seconds)
        ↓
   Batch Process → Single Email with all 3 updates
```

### Components to Implement

#### 1. Email Queue Table
Store pending emails instead of sending immediately:

```sql
CREATE TABLE email_queue (
    id INT AUTO_INCREMENT PRIMARY KEY,
    application_id VARCHAR(50) NOT NULL,
    user_id INT NOT NULL,
    queue_type ENUM('document_update', 'pre_approval', 'remark', 'payment_reminder') NOT NULL,
    update_data JSON NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    scheduled_send_at TIMESTAMP NULL,
    sent_at TIMESTAMP NULL,
    send_attempts INT DEFAULT 0,
    max_attempts INT DEFAULT 3,
    last_error VARCHAR(500),
    status ENUM('pending', 'queued', 'sent', 'failed') DEFAULT 'pending',
    INDEX idx_application_id (application_id),
    INDEX idx_user_id (user_id),
    INDEX idx_status (status),
    INDEX idx_scheduled_send_at (scheduled_send_at)
);

CREATE TABLE email_batch_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    batch_id VARCHAR(100) UNIQUE NOT NULL,
    application_id VARCHAR(50) NOT NULL,
    user_id INT NOT NULL,
    queue_count INT NOT NULL,
    consolidated_email_data JSON,
    sent_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    recipient_email VARCHAR(100),
    subject VARCHAR(255),
    INDEX idx_application_id (application_id),
    INDEX idx_batch_id (batch_id)
);
```

#### 2. Queue Management Functions

**a) Add to Queue** (instead of sending immediately):
```php
function addToEmailQueue($conn, $applicationId, $queueType, $updateData = [])
{
    $query = "INSERT INTO email_queue (application_id, user_id, queue_type, update_data, scheduled_send_at)
              SELECT la.application_id, la.user_id, ?, ?, DATE_ADD(NOW(), INTERVAL 30 SECOND)
              FROM loan_applications la
              WHERE la.application_id = ?";
    
    $updateDataJson = json_encode($updateData);
    executeUpdate($conn, $query, "sss", [$queueType, $updateDataJson, $applicationId]);
}
```

**b) Process Queue** (batch updates for same application):
```php
function processEmailQueue($conn, $timeLimitSeconds = 30)
{
    // Find applications with queued emails
    $queuedApplications = executeQuery($conn, "
        SELECT DISTINCT application_id, user_id
        FROM email_queue
        WHERE status IN ('pending', 'queued')
        AND scheduled_send_at <= NOW()
        GROUP BY application_id
        LIMIT 100
    ");
    
    foreach ($queuedApplications as $app) {
        $applicationId = $app['application_id'];
        
        // Fetch all queued updates for this application
        $queuedUpdates = executeQuery($conn, "
            SELECT id, queue_type, update_data
            FROM email_queue
            WHERE application_id = ? AND status IN ('pending', 'queued')
            ORDER BY created_at ASC
        ", "s", [$applicationId]);
        
        // Consolidate all updates
        $consolidatedData = consolidateQueuedUpdates($queuedUpdates);
        
        // Send single email
        $emailSent = sendConsolidatedUpdateEmail($conn, $applicationId, $consolidatedData);
        
        // Mark as sent/failed
        if ($emailSent) {
            foreach ($queuedUpdates as $queue) {
                executeUpdate($conn, "UPDATE email_queue SET status = 'sent', sent_at = NOW() WHERE id = ?", "i", [$queue['id']]);
            }
        } else {
            // Retry logic
            foreach ($queuedUpdates as $queue) {
                executeUpdate($conn, "
                    UPDATE email_queue 
                    SET send_attempts = send_attempts + 1,
                        status = IF(send_attempts >= max_attempts, 'failed', 'queued')
                    WHERE id = ?
                ", "i", [$queue['id']]);
            }
        }
    }
}

function consolidateQueuedUpdates($queuedUpdates)
{
    $consolidated = [
        'documents' => [],
        'all_documents' => [],
        'pre_approval_status' => null,
        'remarks' => [],
        'admin_name' => null,
        'admin_updated_at' => date('F j, Y \a\t g:i A')
    ];
    
    foreach ($queuedUpdates as $update) {
        $data = json_decode($update['update_data'], true);
        
        if ($update['queue_type'] === 'document_update' && isset($data['documents'])) {
            $consolidated['documents'] = array_merge($consolidated['documents'], $data['documents']);
            if (isset($data['all_documents'])) {
                $consolidated['all_documents'] = $data['all_documents'];
            }
        } elseif ($update['queue_type'] === 'pre_approval' && isset($data['pre_approval_status'])) {
            $consolidated['pre_approval_status'] = $data['pre_approval_status'];
        } elseif ($update['queue_type'] === 'remark' && isset($data['remarks'])) {
            $consolidated['remarks'][] = $data['remarks'];
        }
        
        if (isset($data['admin_name'])) {
            $consolidated['admin_name'] = $data['admin_name'];
        }
    }
    
    return $consolidated;
}
```

#### 3. Queue Processing Scheduler

**Option A: Cron Job** (Recommended for production):
```bash
# Run every 5 minutes
*/5 * * * * /usr/bin/php /var/www/cycloan/process_email_queue.php
```

**Option B: On-Demand Processing** (Simpler, less efficient):
```php
// Add to document update handler
addToEmailQueue($conn, $applicationId, 'document_update', $updateData);

// Check if should process queue (process every 5th request)
if (rand(1, 5) === 1) {
    processEmailQueue($conn);
}
```

#### 4. Queue Processing Script
Create `process_email_queue.php`:

```php
<?php
require_once 'CYCLOAN_db.php';
require_once 'admin2_dashboard.php'; // For sendConsolidatedUpdateEmail

// Only allow command-line execution
if (php_sapi_name() !== 'cli' && php_sapi_name() !== 'cli-server') {
    http_response_code(403);
    die('Access Denied - CLI only');
}

$startTime = microtime(true);
$maxExecutionTime = 300; // 5 minutes max

try {
    $processed = 0;
    
    while (microtime(true) - $startTime < $maxExecutionTime) {
        $queuedApps = executeQuery($conn, "
            SELECT DISTINCT application_id, COUNT(*) as queue_count
            FROM email_queue
            WHERE status IN ('pending', 'queued')
            AND scheduled_send_at <= NOW()
            GROUP BY application_id
            LIMIT 10
        ");
        
        if (empty($queuedApps)) {
            break; // No more items to process
        }
        
        foreach ($queuedApps as $app) {
            processApplicationEmailQueue($conn, $app['application_id']);
            $processed++;
        }
    }
    
    error_log("EMAIL_QUEUE_PROCESSOR: Processed $processed applications", E_USER_NOTICE);
    
} catch (Exception $e) {
    error_log("EMAIL_QUEUE_PROCESSOR_ERROR: " . $e->getMessage(), E_USER_WARNING);
}

function processApplicationEmailQueue($conn, $applicationId)
{
    $queuedUpdates = executeQuery($conn, "
        SELECT id, queue_type, update_data, created_at
        FROM email_queue
        WHERE application_id = ? AND status IN ('pending', 'queued')
        ORDER BY created_at ASC
    ", "s", [$applicationId]);
    
    if (empty($queuedUpdates)) {
        return;
    }
    
    // Consolidate all updates
    $consolidated = [
        'documents' => [],
        'all_documents' => [],
        'pre_approval_status' => null,
        'admin_name' => null,
        'admin_updated_at' => date('F j, Y \a\t g:i A'),
        'batch_id' => 'BATCH-' . $applicationId . '-' . time()
    ];
    
    foreach ($queuedUpdates as $update) {
        $data = json_decode($update['update_data'], true);
        
        if (isset($data['documents'])) {
            $consolidated['documents'] = array_merge($consolidated['documents'], $data['documents']);
        }
        if (isset($data['all_documents'])) {
            $consolidated['all_documents'] = $data['all_documents'];
        }
        if (isset($data['pre_approval_status'])) {
            $consolidated['pre_approval_status'] = $data['pre_approval_status'];
        }
        if (isset($data['admin_name'])) {
            $consolidated['admin_name'] = $data['admin_name'];
        }
    }
    
    // Send consolidated email
    if (sendConsolidatedUpdateEmail($conn, $applicationId, $consolidated)) {
        // Mark all as sent
        foreach ($queuedUpdates as $update) {
            executeUpdate($conn, "UPDATE email_queue SET status = 'sent', sent_at = NOW() WHERE id = ?", "i", [$update['id']]);
        }
        
        error_log("EMAIL_QUEUE: Successfully sent consolidated email for App $applicationId with " . count($queuedUpdates) . " queued items", E_USER_NOTICE);
    } else {
        // Retry with backoff
        foreach ($queuedUpdates as $update) {
            executeUpdate($conn, "
                UPDATE email_queue 
                SET send_attempts = send_attempts + 1,
                    scheduled_send_at = DATE_ADD(NOW(), INTERVAL " . (5 * pow(2, 'send_attempts')) . " SECOND),
                    status = IF(send_attempts >= max_attempts, 'failed', 'queued')
                WHERE id = ?
            ", "i", [$update['id']]);
        }
        
        error_log("EMAIL_QUEUE: Email send failed for App $applicationId, will retry", E_USER_WARNING);
    }
}
?>
```

### Implementation Steps

#### Step 1: Create Queue Table
```sql
-- Run this in your database
-- (SQL provided above)
```

#### Step 2: Modify Document Update Handler
Replace immediate email send with queue:

```php
// OLD CODE (line ~2004 in admin2_dashboard.php):
// $emailSent = sendConsolidatedUpdateEmail($conn, $applicationId, $consolidatedUpdates);

// NEW CODE:
addToEmailQueue($conn, $applicationId, 'document_update', $consolidatedUpdates);
```

#### Step 3: Add Queue Functions to admin2_dashboard.php

#### Step 4: Set Up Cron Job or Timer

#### Step 5: Monitor & Adjust Wait Times

### Configuration Options

```php
// In a config file or at top of admin2_dashboard.php:
define('EMAIL_QUEUE_WAIT_SECONDS', 30);      // Wait 30 seconds before sending
define('EMAIL_QUEUE_MAX_BATCH', 50);         // Max 50 items per batch
define('EMAIL_QUEUE_RETRY_ATTEMPTS', 3);     // Retry failed emails 3 times
define('EMAIL_QUEUE_ENABLE', true);          // Enable/disable queueing
```

### Benefits of This System

1. **Reduced Email Volume**: Users get 1-3 emails instead of 10+
2. **Better Organization**: Related updates bundled together
3. **Improved Performance**: Email processing is non-blocking
4. **Retry Logic**: Failed emails are automatically retried
5. **Audit Trail**: Queue table provides email history
6. **Flexible Wait Times**: Adjust 30-second window as needed
7. **Easy to Disable**: Can immediately revert to direct sending

### Alternative: Simpler Approach (Immediate Implementation)

If full queue system is too complex, implement a simpler version:

```php
// Option 1: Don't send email after individual document updates
// Instead, only send email when:
//   - Pre-approval decision is made
//   - All documents are processed
//   - Admin manually sends email

// Option 2: Implement client-side batching
// Add JavaScript delay before final submission that consolidates updates
```

### Monitoring & Debugging

```php
// Monitor queue health
function getQueueStats($conn)
{
    return executeQuery($conn, "
        SELECT 
            status,
            COUNT(*) as count,
            AVG(TIMESTAMPDIFF(SECOND, created_at, NOW())) as avg_age_seconds
        FROM email_queue
        GROUP BY status
    ");
}

// View failed emails
function getFailedEmails($conn)
{
    return executeQuery($conn, "
        SELECT id, application_id, queue_type, last_error, created_at
        FROM email_queue
        WHERE status = 'failed'
        ORDER BY created_at DESC
        LIMIT 100
    ");
}

// Manually retry failed email
function retryFailedEmail($conn, $queueId)
{
    return executeUpdate($conn, "
        UPDATE email_queue
        SET status = 'queued', send_attempts = 0, scheduled_send_at = NOW()
        WHERE id = ?
    ", "i", [$queueId]);
}
```

### Next Steps

1. Choose implementation approach (full queue vs. simpler)
2. Create database table
3. Implement queue functions
4. Modify document update handler
5. Set up processor (cron or on-demand)
6. Test with multiple document updates
7. Monitor email delivery
8. Adjust wait times based on testing

---

**Recommendation**: Start with full queue system for maximum benefit. It provides flexibility for future enhancements and email optimization.
