# Document Email Consolidation Solution

## Problem Statement

**Current Issue:**

- When admin updates document status, an email is sent **immediately** for each document
- If applicant has 5 documents → **5 separate emails sent** (one per update)
- Creates email spam for applicants
- Poor user experience
- Clutters email inbox

**Example Scenario:**

```
Admin approves document 1 → EMAIL ✉️
Admin approves document 2 → EMAIL ✉️
Admin rejects document 3 → EMAIL ✉️
Admin approves document 4 → EMAIL ✉️
Admin approves document 5 → EMAIL ✉️
─────────────────────────────────
Total: 5 emails sent (PROBLEM!)
```

**Desired Solution:**

```
Admin updates all documents (5 changes)
    ↓
System QUEUES the email changes (does not send immediately)
    ↓
Admin finishes all document updates
    ↓
Single BATCH EMAIL sent with all document changes
    ↓
Applicant receives ONE email with full summary
─────────────────────────────────
Total: 1 email sent (SOLUTION!)
```

---

## Solution Architecture

### Option 1: Email Batching with Session Storage (RECOMMENDED)

**How It Works:**

1. When admin updates a document, store change in session/cache instead of sending immediately
2. Accumulate all document updates in memory
3. On admin action completion (close modal/page), send ONE consolidated email
4. Email includes ALL document changes from the session

**Advantages:**

- ✅ Real-time batching within user session
- ✅ Simple implementation
- ✅ No database table needed
- ✅ Works per admin session
- ✅ Emails sent quickly after all updates

**Disadvantages:**

- ❌ Only batches within single session/page load
- ❌ If admin leaves without closing modal, email may not send
- ❌ Session-dependent

### Option 2: Database Queue with Delayed Processing (PRODUCTION-GRADE)

**How It Works:**

1. Create new table: `document_change_queue`
2. When admin updates document, INSERT change into queue (don't send email yet)
3. Add background cron job to process queue every 5-10 minutes
4. Cron groups all pending changes by application_id and sends one consolidated email
5. Mark changes as processed in queue

**Advantages:**

- ✅ Guaranteed delivery
- ✅ Works across multiple sessions
- ✅ Reliable and scalable
- ✅ Persists even if server restarts
- ✅ Professional-grade solution
- ✅ Can run on any schedule (every 5, 10, 30 minutes)

**Disadvantages:**

- ❌ Slight email delay (5-10 minutes)
- ❌ Requires database table
- ❌ Requires cron job setup
- ❌ More complex implementation

### Option 3: Hybrid Solution (BEST)

**How It Works:**

1. Use database queue for reliability
2. Add immediate notification in UI (no email yet)
3. Batch emails sent by cron job every 5 minutes
4. Optional: Send immediate email if all documents are finalized

**Advantages:**

- ✅ All advantages of both approaches
- ✅ Users see feedback immediately (UI notification)
- ✅ Emails batched and professional
- ✅ Most reliable and scalable

---

## Recommended Implementation: Option 3 (Hybrid)

### Step 1: Create Database Table for Queuing

```sql
CREATE TABLE document_change_queue (
    queue_id INT AUTO_INCREMENT PRIMARY KEY,
    application_id VARCHAR(50) NOT NULL,
    document_id INT NOT NULL,
    old_status VARCHAR(20),
    new_status VARCHAR(20) NOT NULL,
    rejection_reason TEXT,
    admin_id INT NOT NULL,
    admin_name VARCHAR(100) NOT NULL,
    change_timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    processed BOOLEAN DEFAULT FALSE,
    processed_at TIMESTAMP NULL,
    email_sent_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_app_id (application_id),
    INDEX idx_processed (processed),
    FOREIGN KEY (application_id) REFERENCES loan_applications(application_id)
);
```

### Step 2: Modify Document Status Update Code

**Current Code (Line 2013):**

```php
// SENDS EMAIL IMMEDIATELY
$emailSent = sendConsolidatedUpdateEmail($conn, $applicationId, $consolidatedUpdates);
```

**New Code (Queue-based):**

```php
// ADD TO QUEUE INSTEAD OF SENDING IMMEDIATELY
$queueResult = addDocumentChangeToQueue($conn, [
    'application_id' => $applicationId,
    'document_id' => $documentId,
    'old_status' => $document['status'],
    'new_status' => $newStatus,
    'rejection_reason' => $rejectionReason,
    'admin_id' => $adminId,
    'admin_name' => $adminName,
    'document_name' => $document['document_name']
]);

if ($queueResult) {
    error_log("DOCUMENT_CHANGE_QUEUED: App ID $applicationId, Doc $documentId queued for batch email", E_USER_NOTICE);
} else {
    error_log("DOCUMENT_CHANGE_QUEUE_FAILED: Failed to queue change for App ID $applicationId", E_USER_WARNING);
}

// SHOW IMMEDIATE NOTIFICATION IN UI (NO EMAIL YET)
try {
    $notificationMessage = "Document '{$document['document_name']}' status updated to: $newStatus (email notification pending)";
    createDocumentNotification($conn, $userId, $notificationMessage);
} catch (Exception $e) {
    error_log("Notification creation failed", E_USER_WARNING);
}
```

### Step 3: Create Queue Processing Function

```php
/**
 * Add document change to queue for batch email processing
 */
function addDocumentChangeToQueue($conn, $changeData)
{
    $query = "
        INSERT INTO document_change_queue
        (application_id, document_id, old_status, new_status, rejection_reason, admin_id, admin_name)
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ";

    $result = executeUpdate($conn, $query, "sissss", [
        $changeData['application_id'],
        $changeData['document_id'],
        $changeData['old_status'],
        $changeData['new_status'],
        $changeData['rejection_reason'] ?? null,
        $changeData['admin_id'],
        $changeData['admin_name']
    ]);

    return $result > 0;
}
```

### Step 4: Create Batch Email Processor

**File: `process_document_email_queue.php`**

```php
<?php
require_once 'CYCLOAN_db.php';
require_once 'path/to/email_functions.php';

// Get all unprocessed document changes, grouped by application
$query = "
    SELECT
        application_id,
        GROUP_CONCAT(DISTINCT document_id) as document_ids,
        COUNT(*) as change_count,
        MAX(change_timestamp) as latest_change
    FROM document_change_queue
    WHERE processed = FALSE
    GROUP BY application_id
    HAVING change_count >= 1
    LIMIT 50
";

$applications = executeQuery($conn, $query);

foreach ($applications as $app) {
    $applicationId = $app['application_id'];

    // Fetch all changes for this application
    $changesQuery = "
        SELECT * FROM document_change_queue
        WHERE application_id = ? AND processed = FALSE
        ORDER BY change_timestamp ASC
    ";

    $changes = executeQuery($conn, $changesQuery, "s", [$applicationId]);

    // Prepare consolidated email data
    $consolidatedUpdates = [
        'documents' => [],
        'admin_name' => $changes[0]['admin_name'],
        'admin_updated_at' => date('F j, Y \a\t g:i A'),
        'batch_count' => count($changes)
    ];

    foreach ($changes as $change) {
        $consolidatedUpdates['documents'][] = [
            'document_id' => $change['document_id'],
            'old_status' => $change['old_status'],
            'new_status' => $change['new_status'],
            'rejection_reason' => $change['rejection_reason']
        ];
    }

    // SEND CONSOLIDATED EMAIL WITH ALL CHANGES
    $emailSent = sendConsolidatedUpdateEmail($conn, $applicationId, $consolidatedUpdates);

    if ($emailSent) {
        // Mark all changes as processed
        $updateQuery = "
            UPDATE document_change_queue
            SET processed = TRUE, processed_at = NOW(), email_sent_at = NOW()
            WHERE application_id = ? AND processed = FALSE
        ";
        executeUpdate($conn, $updateQuery, "s", [$applicationId]);

        error_log("BATCH_EMAIL_SENT: App ID $applicationId - $change_count changes sent in 1 email", E_USER_NOTICE);
    } else {
        error_log("BATCH_EMAIL_FAILED: App ID $applicationId - Failed to send batch email", E_USER_WARNING);
    }
}

$conn->close();
?>
```

### Step 5: Setup Cron Job

**Run every 5 minutes:**

```bash
*/5 * * * * /usr/bin/php /path/to/cycloan/process_document_email_queue.php >> /var/log/cycloan_queue.log 2>&1
```

**Or Windows Task Scheduler:**

```powershell
# Run PHP script every 5 minutes
schtasks /create /tn "CycloanEmailQueue" /tr "C:\php\php.exe C:\path\to\cycloan\process_document_email_queue.php" /sc minute /mo 5
```

---

## Email Template Enhancement

### Updated Consolidated Email for Multiple Changes

```php
$content .= "
<div style='margin: 20px 0; padding: 15px; background: #fff3e0; border-left: 4px solid #ff6f00; border-radius: 4px;'>
    <h3 style='margin: 0 0 10px 0; color: #e65100; font-size: 15px;'>📋 Document Status Update Summary</h3>
    <p style='margin: 8px 0; color: #333;'><strong>Number of Changes:</strong> {$consolidatedUpdates['batch_count']}</p>
    <p style='margin: 8px 0; color: #333;'><strong>Review Date:</strong> {$consolidatedUpdates['admin_updated_at']}</p>
    <p style='margin: 8px 0; color: #333;'><strong>Reviewed by:</strong> {$consolidatedUpdates['admin_name']}</p>

    <h4 style='margin: 15px 0 10px 0; color: #333; font-size: 14px;'>📄 Document Changes:</h4>
    <table style='width: 100%; border-collapse: collapse; margin: 10px 0;'>
        <tr style='background: #f5f5f5;'>
            <th style='padding: 10px; text-align: left; border: 1px solid #ddd;'>Document</th>
            <th style='padding: 10px; text-align: left; border: 1px solid #ddd;'>Previous Status</th>
            <th style='padding: 10px; text-align: left; border: 1px solid #ddd;'>New Status</th>
            <th style='padding: 10px; text-align: left; border: 1px solid #ddd;'>Notes</th>
        </tr>";

foreach ($consolidatedUpdates['documents'] as $doc) {
    $content .= "
        <tr>
            <td style='padding: 10px; border: 1px solid #ddd;'>{$doc['document_name']}</td>
            <td style='padding: 10px; border: 1px solid #ddd;'>{$doc['old_status']}</td>
            <td style='padding: 10px; border: 1px solid #ddd;'>
                <span style='padding: 4px 8px; border-radius: 4px;
                    " . ($doc['new_status'] === 'Approved' ? "background: #c8e6c9; color: #2e7d32;" :
                    ($doc['new_status'] === 'Rejected' ? "background: #ffcdd2; color: #d32f2f;" :
                    "background: #fff9c4; color: #f57f17;")) . "'>
                    {$doc['new_status']}
                </span>
            </td>
            <td style='padding: 10px; border: 1px solid #ddd; font-size: 12px;'>
                " . (!empty($doc['rejection_reason']) ? htmlspecialchars($doc['rejection_reason']) : '-') . "
            </td>
        </tr>";
}

$content .= "</table></div>";
```

---

## Implementation Comparison

| Feature                    | Current   | Solution         |
| -------------------------- | --------- | ---------------- |
| **Emails per 5 documents** | 5 emails  | 1 email          |
| **Email delay**            | Immediate | 5 minutes max    |
| **User experience**        | Spam-like | Professional     |
| **Database impact**        | Minimal   | Adds queue table |
| **Complexity**             | Simple    | Medium           |
| **Reliability**            | Good      | Excellent        |
| **Scalability**            | Limited   | Excellent        |

---

## Implementation Steps

### Phase 1: Database Setup (5 minutes)

1. Create `document_change_queue` table
2. Verify table structure

### Phase 2: Code Modification (30 minutes)

1. Update document status update code to queue changes instead of sending
2. Add `addDocumentChangeToQueue()` function
3. Keep UI notifications (immediate feedback)

### Phase 3: Queue Processor (20 minutes)

1. Create `process_document_email_queue.php`
2. Add batch email consolidation logic
3. Test email sending

### Phase 4: Cron Job Setup (10 minutes)

1. Configure cron job (Linux) or Task Scheduler (Windows)
2. Set to run every 5 minutes
3. Monitor first batch processing

### Phase 5: Testing & Monitoring (ongoing)

1. Update 5 documents in sequence
2. Verify 1 batch email sent after 5 minutes
3. Monitor logs and email queue
4. Adjust email template as needed

---

## Benefits Summary

✅ **User Experience:** Reduces email spam from 5+ emails to 1 consolidated email
✅ **Professional:** Batched emails look more polished
✅ **Scalable:** Can handle unlimited document updates
✅ **Reliable:** Database-backed queue ensures no lost emails
✅ **Flexible:** Can adjust batch interval (5, 10, 30 minutes)
✅ **Transparent:** Queue table provides audit trail
✅ **Immediate Feedback:** In-app notifications still appear immediately

---

## Alternative: Instant Batching with Session

If you want emails sent **immediately** but still batched:

```php
// Accumulate document changes in session
if (!isset($_SESSION['document_changes'])) {
    $_SESSION['document_changes'] = [];
}

$_SESSION['document_changes'][] = [
    'document_id' => $documentId,
    'old_status' => $document['status'],
    'new_status' => $newStatus,
    'rejection_reason' => $rejectionReason,
    'document_name' => $document['document_name']
];

// When session ends or admin closes document review modal,
// send ONE email with all accumulated changes
```

**Pros:** Immediate email, batched
**Cons:** Only works within single session, may miss changes if session interrupted

---

## Recommendation

**Use Solution Option 3 (Hybrid) for:**

- ✅ Production environments
- ✅ Best user experience
- ✅ Maximum reliability
- ✅ Professional appearance
- ✅ Scalable solution

**Cron Job Interval:** Every 5 minutes = good balance between timeliness and batching
