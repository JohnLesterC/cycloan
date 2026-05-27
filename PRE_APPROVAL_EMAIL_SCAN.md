# Pre-Approval Email Flow Scan Report

## Overview

This document provides a detailed analysis of how the pre-approval function sends emails in the admin2_dashboard.php system.

---

## 1. Pre-Approval Status Email Trigger

### Location: Lines 1711-1810 (admin2_dashboard.php)

### Trigger Condition:

```php
if (($preApprovalStatus === 'Approved' || $preApprovalStatus === 'Rejected')
    && $preApprovalStatus !== $currentData['pre_approval_status'])
```

**Email is sent ONLY when:**

- Pre-approval status changes to either **'Approved'** or **'Rejected'**
- The new status is **different** from the current status in the database
- This prevents duplicate emails for unchanged statuses

---

## 2. Email Sending Code

### Location: Line 1801 (admin2_dashboard.php)

```php
if (!sendConsolidatedUpdateEmail($conn, $applicationId, $consolidatedUpdates)) {
    error_log("Email notification operation failed", E_USER_WARNING);
}
```

### Function Definition: Line 1068

```php
function sendPreApprovalStatusEmail($conn, $applicationId, $newStatus)
{
    return sendConsolidatedUpdateEmail($conn, $applicationId, ['pre_approval_status' => $newStatus]);
}
```

**Current Implementation:**

- Uses `sendConsolidatedUpdateEmail()` function
- Passes the application ID and consolidated updates array
- Emails are sent **immediately** (not queued)
- Returns success/failure status

---

## 3. Data Prepared Before Email Send

### Pre-Approval Rejection Processing (Lines 1719-1792)

When status is **'Rejected'**:

#### Step 1: Fetch all remarks

```php
$allRemarks = executeQuery($conn, "
    SELECT remarks, created_at, admin_name
    FROM remarks
    WHERE application_id = ?
    ORDER BY created_at DESC
", "s", [$applicationId]);
```

#### Step 2: Mark all documents as rejected

```php
UPDATE documents
SET status = 'Rejected', status_updated_at = NOW(), rejection_notes = ?
WHERE document_id = ?
```

- Stores rejection reason in `rejection_notes` field
- Updates `status_updated_at` timestamp
- Includes extensive logging and audit trails

#### Step 3: Create audit trails for each document

```php
INSERT INTO document_audit
(document_id, application_id, document_type_id, old_status, new_status,
 admin_id, admin_name, admin_role, notes)
VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
```

#### Step 4: Fetch all documents with rejection notes

```php
$consolidatedUpdates['all_documents'] = executeQuery($conn, "
    SELECT d.document_id, dt.document_name as name, d.status, d.rejection_notes
    FROM documents d
    JOIN document_types dt ON d.document_type_id = dt.document_type_id
    WHERE d.application_id = ?
    ORDER BY dt.document_name
", "s", [$applicationId]);
```

---

## 4. Email Content Structure

### Consolidated Updates Array:

```php
$consolidatedUpdates = [
    'pre_approval_status' => $preApprovalStatus,  // 'Approved' or 'Rejected'
    'remarks' => $remarks,                        // Individual remark
    'all_remarks' => [...],                       // All historical remarks
    'all_documents' => [                          // For rejection emails
        [
            'document_id' => '...',
            'name' => '...',
            'status' => 'Rejected',
            'rejection_notes' => '...'            // Reason for rejection
        ]
    ]
]
```

---

## 5. Email Sending Flow

### Step 1: Condition Check

```
Pre-Approval Status = 'Approved' or 'Rejected'?
    └─ Yes: Status changed from current value?
        └─ Yes: Prepare data and send email
        └─ No: Skip email (status unchanged)
    └─ No: Skip email (status is 'Pending' or unchanged)
```

### Step 2: Data Consolidation

- If **Rejected**: Fetch remarks, reject documents, create audits, fetch all documents
- If **Approved**: Fetch remarks only
- Combine all data into `$consolidatedUpdates` array

### Step 3: Email Transmission

```php
sendConsolidatedUpdateEmail($conn, $applicationId, $consolidatedUpdates)
```

- Sends email **immediately** (synchronous)
- No queue, no batching, no delays
- Returns true/false success status

### Step 4: Error Handling

```php
if (!sendConsolidatedUpdateEmail(...)) {
    error_log("Email notification operation failed", E_USER_WARNING);
}
```

- Failure is logged but does not stop the request
- Continues to return success JSON response

---

## 6. Response to User

### Location: Lines 1807-1810

```php
ob_clean();
header('Content-Type: application/json');
echo json_encode(['success' => true, 'message' => 'Status and/or remarks updated successfully.']);
exit;
```

**Response is sent regardless of email success/failure**

- Database changes are committed
- Email errors do not rollback transaction
- Front-end receives success message

---

## 7. Logging & Debugging

### Pre-Approval Rejection Logging:

```
✓ PREAPPROVAL_REJECT_START
  └─ Application ID and rejection reason

✓ PREAPPROVAL_DOCS_FOUND
  └─ Number of documents to reject

✓ PREAPPROVAL_REJECTING_DOC
  └─ Each document name and ID

✓ DEBUG_REJECTION_REASON
  └─ Exact value, length, and type

✓ PREAPPROVAL_UPDATE_DOC
  └─ Database update result rows

✓ VERIFY_SAVED
  └─ Verify rejection_notes saved correctly

✓ PREAPPROVAL_AUDIT_CREATED
  └─ Audit trail creation result

✓ PREAPPROVAL_REJECT_COMPLETE
  └─ All documents rejected successfully
```

---

## 8. Current Email Behavior Summary

| Aspect                   | Current Behavior                                               |
| ------------------------ | -------------------------------------------------------------- |
| **Trigger**              | When pre-approval status changes to Approved/Rejected          |
| **Sending Method**       | Immediate synchronous send via `sendConsolidatedUpdateEmail()` |
| **Queue System**         | None - direct send                                             |
| **Batching**             | None - individual emails                                       |
| **Delay**                | No delay - sent in real-time                                   |
| **Content**              | Status + Remarks + Documents (if rejected)                     |
| **Error Handling**       | Logged but does not fail the request                           |
| **Response**             | Always returns success (regardless of email status)            |
| **Database Transaction** | Committed before email sent                                    |

---

## 9. Key Points

✅ **Email sends immediately** when pre-approval status changes
✅ **Consolidates rejection data** (documents, remarks, reasons)
✅ **Includes audit trail** for all rejected documents
✅ **Stores rejection reason** in documents table
✅ **Prevents duplicate emails** (only sends on status change)
✅ **Comprehensive logging** for debugging
✅ **Does not fail the request** if email send fails

---

## 10. Document Status Email Sending

### Location: Lines 1813-2040 (admin2_dashboard.php)

### Email Trigger for Individual Document Status:

```php
if (isset($_POST['action']) && $_POST['action'] === 'update_document_status'...)
```

**Email is sent when:**

- Admin updates a **single document status** (Approved/Rejected/Pending)
- Document status is different from current status
- Email sent **immediately** after database update

### Email Content for Document Status Update:

```php
$consolidatedUpdates = [
    'admin_name' => $adminName,
    'admin_updated_at' => date('F j, Y \a\t g:i A'),
    'documents' => [
        [
            'name' => $document['document_name'],
            'status' => $newStatus,
            'rejection_reason' => $rejectionReason  // If rejected
        ]
    ],
    'all_documents' => [                            // ALL documents in the application
        ['name' => '...', 'status' => '...', 'rejection_notes' => '...'],
        ['name' => '...', 'status' => '...', 'rejection_notes' => '...']
    ]
];
```

### Key Difference from Pre-Approval:

| Aspect            | Pre-Approval                        | Document Status                          |
| ----------------- | ----------------------------------- | ---------------------------------------- |
| **Scope**         | Changes ALL documents (if rejected) | Changes ONE document only                |
| **Email Trigger** | Status change to Approved/Rejected  | Any status change                        |
| **Data Sent**     | All rejected docs + remarks         | Current doc + all docs overview          |
| **Loop**          | Rejects all docs in one action      | One email per document change            |
| **Frequency**     | One email per pre-approval decision | One email per individual document update |

### Answer to User Question:

✅ **YES - Document Status Sends Individual Emails**

- Each time an admin updates a **single document**, an email is sent immediately
- Each document status change = **ONE EMAIL**
- NOT batched or consolidated by document type
- Emails send individually for each document update

### Document Status Email Flow:

```
Admin updates Document Status
    ├─ Update database with new status + rejection_reason
    ├─ Create audit trail for this document
    ├─ Fetch ALL documents for the application (for overview)
    ├─ Build consolidated email with:
    │   ├─ Updated document details (current change)
    │   └─ All documents status overview
    ├─ Send email immediately
    └─ Return success response
```

### Example Scenario:

**If applicant has 5 documents:**

1. Admin approves Document 1 → **EMAIL SENT** ✉️
2. Admin approves Document 2 → **EMAIL SENT** ✉️
3. Admin rejects Document 3 → **EMAIL SENT** ✉️ (with rejection reason)
4. Admin approves Document 4 → **EMAIL SENT** ✉️
5. Admin approves Document 5 → **EMAIL SENT** ✉️

**Total: 5 emails sent** (one per document update)

---

## 11. Complete Email Sending Summary

### Pre-Approval vs Document Status:

**Pre-Approval (Line 1801):**

- ✅ Single email when pre-approval status changes
- ✅ If rejected: rejects all documents in one action
- ✅ Multiple documents rejected in parallel, but one pre-approval email

**Document Status (Line 2013):**

- ✅ Individual email for EACH document status change
- ✅ If 5 documents = up to 5 emails
- ✅ Sequential emails as admin updates each document

### Combined Email Flow:

```
PRE-APPROVAL STAGE:
├─ If Status → Approved: 1 email (approval notification)
└─ If Status → Rejected: 1 email (rejection with all docs rejected)

DOCUMENT REVIEW STAGE:
├─ Document 1 approval: 1 email (shows doc 1 updated + all docs overview)
├─ Document 2 approval: 1 email (shows doc 2 updated + all docs overview)
├─ Document 3 rejection: 1 email (shows doc 3 rejected + rejection reason + all docs overview)
└─ ... (repeat for each document)
```

---

## 12. Files Involved

- **admin2_dashboard.php** - Main handler
  - Pre-approval emails: Line 1701-1810
  - Document status emails: Line 1813-2040
- **sendConsolidatedUpdateEmail()** - Email sender function (Line 697)
- **documents table** - Stores rejection_notes and status
- **document_audit table** - Stores all status change history
- **remarks table** - Stores admin remarks
- **users1 table** - Applicant contact information

---

**Report Generated:** Current System State Analysis
**System Status:** Email queue system removed - using immediate email sends
**Email Mode:** Immediate synchronous sends (no queue, no batching, no delays)
