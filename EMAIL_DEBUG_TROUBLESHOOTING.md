# Email Consolidation - Debug & Fix Guide

## Issues Fixed

✅ **Batch Email Template Issue**

- Added `batch_documents` template type detection
- Created batch email header content
- Added professional table format for consolidated changes

✅ **Email Template Enhancement**

- Added detection for `is_batch` flag
- Added batch-specific email header
- Added complete table with document changes
- Properly formats rejection reasons

---

## How to Test the Consolidated Email System

### Step 1: Verify Queue is Being Populated

Check error logs for:

```
DOCUMENT_CHANGE_QUEUED: App ID APP_123, Document: Income Proof, Status: Approved (queued for consolidated email)
QUEUE_STATUS: Total changes in queue for App APP_123: 1
```

**If you don't see these logs:**

- ❌ Document update handler not executing properly
- ❌ Session not initialized
- ❌ Database update failing

### Step 2: Manually Trigger Email Send

Call the AJAX endpoint:

```javascript
fetch("admin2_dashboard.php", {
  method: "POST",
  headers: {
    "Content-Type": "application/x-www-form-urlencoded",
  },
  body: new URLSearchParams({
    action: "send_queued_document_email",
    application_id: "APP_123",
    csrf_token: "<?php echo getCSRFToken(); ?>",
  }),
})
  .then((response) => response.json())
  .then((data) => {
    console.log("Email send response:", data);
  });
```

**Expected Log Output:**

```
QUEUED_EMAIL_SEND: Sending consolidated email for App ID: APP_123 with 5 document change(s)
CONSOLIDATED_EMAIL_START: Processing email for App ID: APP_123
CONSOLIDATED_EMAIL_TEMPLATE: Selected template: batch_documents
CONSOLIDATED_EMAIL_USER: Retrieved user - Name: John Applicant, Email: john@example.com
```

**If email fails:**

```
QUEUED_EMAIL_FAILED: Failed to send email for App ID: APP_123
CONSOLIDATED_EMAIL_VALIDATION_ERROR: Invalid email address...
```

### Step 3: Check Email Function

Verify `sendConsolidatedUpdateEmail()` is working:

- Check PHPMailer is configured
- Verify SMTP credentials
- Check email address validity
- Review firewall/host email restrictions

---

## Common Issues & Solutions

### Issue 1: Emails Not Being Queued

**Symptom:** No "DOCUMENT_CHANGE_QUEUED" in logs

**Causes:**

1. Document update handler not reached

   - Check: Admin role validation (`$adminRole !== 'Admin2'`)
   - Check: CSRF token validation failing
   - Check: Input validation failing

2. Session not initialized
   - Check: `session_start()` called at top of file
   - Check: Session enabled in PHP config

**Solution:**

```php
// Add debug logging at top of document update handler
error_log("DEBUG_DOCUMENT_UPDATE: Handler triggered for App ID: $applicationId", E_USER_NOTICE);
error_log("DEBUG_ADMIN_ROLE: Admin role = $adminRole", E_USER_NOTICE);
error_log("DEBUG_SESSION: Session active? " . (session_id() ? 'YES' : 'NO'), E_USER_NOTICE);
```

---

### Issue 2: Emails Queued But Not Sending

**Symptom:** Logs show queue updated but no email sent

**Causes:**

1. `send_queued_document_email` handler not called

   - Check: No frontend button to trigger send
   - Check: AJAX endpoint not configured
   - Check: CSRF token validation failing

2. `sendQueuedDocumentChangesEmail()` failing
   - Check: Queue data structure correct
   - Check: Application data retrieval working
   - Check: Email function returning false

**Solution:**

Add a manual trigger test:

```php
// Add to top of page to auto-send queue (for testing only)
if (isset($_SESSION['document_changes_queue']) && !empty($_SESSION['document_changes_queue'])) {
    foreach ($_SESSION['document_changes_queue'] as $appId => $data) {
        sendQueuedDocumentChangesEmail($conn, $appId);
    }
}
```

---

### Issue 3: sendConsolidatedUpdateEmail() Failing

**Symptom:** Function returns false, email not sent

**Causes:**

1. Invalid email address

   - Check: Email in `users1` table valid
   - Check: Email not filtered as spam
   - Check: Email format validation

2. PHPMailer configuration issue

   - Check: SMTP credentials correct
   - Check: SMTP server reachable
   - Check: Authentication working

3. Email content generation failing
   - Check: Array structure matches template expectations
   - Check: Batch data properly formatted

**Solution:**

Debug the email content:

```php
// Add before email send to debug
error_log("EMAIL_DEBUG: To: $to", E_USER_NOTICE);
error_log("EMAIL_DEBUG: Name: $name", E_USER_NOTICE);
error_log("EMAIL_DEBUG: Template: $template", E_USER_NOTICE);
error_log("EMAIL_DEBUG: Updates array: " . json_encode($updates), E_USER_NOTICE);
```

---

## Required Data Structure

### Queue Storage (Session)

```php
$_SESSION['document_changes_queue'] = [
    'APP_ID' => [
        'changes' => [
            [
                'document_id' => 1,
                'document_name' => 'Income Proof',      // REQUIRED
                'old_status' => 'Pending',              // REQUIRED
                'new_status' => 'Approved',             // REQUIRED
                'rejection_reason' => null,             // Optional
                'timestamp' => '14:25:30'
            ]
        ],
        'admin_name' => 'John Admin',                   // REQUIRED
        'admin_id' => 5,
        'user_id' => 12,                               // REQUIRED
        'all_documents' => [...],                      // REQUIRED
        'first_change_time' => 'Dec 18, 2024 at 2:30 PM'
    ]
]
```

### Email Updates Array

```php
$consolidatedUpdates = [
    'admin_name' => 'John Admin',                      // REQUIRED
    'admin_updated_at' => 'Dec 18, 2024 at 2:30 PM',  // REQUIRED
    'documents' => [                                   // REQUIRED
        [
            'document_name' => 'Income Proof',         // REQUIRED (for batch)
            'old_status' => 'Pending',                 // REQUIRED (for batch)
            'new_status' => 'Approved',                // REQUIRED (for batch)
            'rejection_reason' => null                 // Optional (for batch)
        ]
    ],
    'all_documents' => [...],
    'batch_count' => 5,                               // REQUIRED (for batch)
    'is_batch' => true                                // REQUIRED (for batch)
];
```

---

## Testing Checklist

✅ **Step 1: Queue Population**

```
1. Open document review modal
2. Update document 1 status
3. Check error log for "DOCUMENT_CHANGE_QUEUED"
4. Verify queue count shows "1 change queued"
```

✅ **Step 2: Queue Accumulation**

```
1. Update documents 2, 3, 4, 5 (all in same modal)
2. Check error log shows "Total changes in queue: 5"
3. Verify UI shows "5 changes queued"
```

✅ **Step 3: Manual Email Send**

```
1. Click "Send Queued Email" button (if available)
2. Check error log for "QUEUED_EMAIL_SEND"
3. Check for "CONSOLIDATED_EMAIL_TEMPLATE: Selected template: batch_documents"
4. Verify applicant receives ONE email with all 5 changes
```

✅ **Step 4: Email Content Verification**

```
1. Open received email
2. Check for "Consolidated Document Status Changes" header
3. Verify table shows all 5 document updates
4. Check Status column shows correct colors (green/red/yellow)
5. Verify rejection reasons shown in Notes column
```

---

## If Emails Still Not Sending

### Enable Verbose Logging

Add to `admin2_dashboard.php`:

```php
// At top of file
if (!defined('DEBUG_MODE')) {
    define('DEBUG_MODE', true);  // Enable for debugging
}

// In sendConsolidatedUpdateEmail function
if (DEBUG_MODE) {
    error_log("=== EMAIL CONSOLIDATION DEBUG ===", E_USER_NOTICE);
    error_log("Application ID: $applicationId", E_USER_NOTICE);
    error_log("To Email: $to", E_USER_NOTICE);
    error_log("Template Type: $template", E_USER_NOTICE);
    error_log("Is Batch: " . (isset($updates['is_batch']) ? 'YES' : 'NO'), E_USER_NOTICE);
    error_log("Document Count: " . count($updates['documents'] ?? []), E_USER_NOTICE);
    error_log("=== END DEBUG ===", E_USER_NOTICE);
}
```

### Check Email Queue Function

Add diagnostic output:

```php
function sendQueuedDocumentChangesEmail($conn, $applicationId)
{
    error_log("=== QUEUED EMAIL FUNCTION START ===", E_USER_NOTICE);
    error_log("Application ID: $applicationId", E_USER_NOTICE);
    error_log("Queue exists: " . (isset($_SESSION['document_changes_queue'][$applicationId]) ? 'YES' : 'NO'), E_USER_NOTICE);

    if (empty($_SESSION['document_changes_queue'][$applicationId])) {
        error_log("NO QUEUED DATA - Returning FALSE", E_USER_NOTICE);
        return false;
    }

    $queueData = $_SESSION['document_changes_queue'][$applicationId];
    error_log("Queue data loaded. Changes count: " . count($queueData['changes']), E_USER_NOTICE);

    // ... rest of function
}
```

---

## Next Steps

1. **Test queue population** - Verify changes are queuing
2. **Test email send** - Call AJAX endpoint manually
3. **Check logs** - Review error_log output
4. **Enable debug mode** - Get detailed logging
5. **Test content** - Verify email HTML renders correctly
6. **Add UI button** - Create frontend button to send email

---

## Frontend Implementation Example

```html
<!-- Add send email button to document review modal -->
<div class="modal-footer">
  <button type="button" class="btn btn-secondary" data-dismiss="modal">
    Close
  </button>
  <button
    type="button"
    class="btn btn-primary"
    id="sendQueuedEmailBtn"
    onclick="sendQueuedDocumentEmail()"
  >
    📧 Send Consolidated Email (<span id="queueCount">0</span> changes)
  </button>
</div>
```

```javascript
function sendQueuedDocumentEmail() {
  const applicationId = "<?php echo $applicationId; ?>";

  fetch("admin2_dashboard.php", {
    method: "POST",
    headers: { "Content-Type": "application/x-www-form-urlencoded" },
    body: new URLSearchParams({
      action: "send_queued_document_email",
      application_id: applicationId,
      csrf_token: "<?php echo getCSRFToken(); ?>",
    }),
  })
    .then((response) => response.json())
    .then((data) => {
      if (data.success) {
        alert("✅ Consolidated email sent successfully!");
        location.reload();
      } else {
        alert("❌ Failed to send email: " + data.message);
      }
    })
    .catch((error) => {
      console.error("Error:", error);
      alert("Error sending email");
    });
}
```

---

## Files Modified

✅ **admin2_dashboard.php**

1. Updated `selectEmailTemplate()` - Added batch email detection
2. Added batch email header template
3. Added batch document changes table in email
4. Verified `sendQueuedDocumentChangesEmail()` function
5. Verified AJAX handler `send_queued_document_email`

---

**Status: ✅ FIXED & READY TO TEST**

All email sending logic is now in place. Use this debugging guide if emails still don't send.
