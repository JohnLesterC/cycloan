# Pre-Approval Flow - Complete Fix & Debugging Guide

## 🐛 Issues Found & Fixed

### UI/Form Issues:
1. ✅ **Approval Reason not passed to confirmation modal** 
   - Fixed: Added `approvalReason` parameter to `showPreApprovalConfirmModal()`
   - Now passes it through: form → modal → FormData → backend

2. ✅ **Missing approval_reason in stored data**
   - Fixed: Updated `pendingPreApprovalData` to include `approvalReason`
   - Now stores: `{ preApprovalStatus, remarks, approvalReason }`

3. ✅ **Confirmation modal not receiving reason**
   - Fixed: Updated function call to pass all 3 parameters
   - Now: `showPreApprovalConfirmModal(preApprovalStatus, remarks, approvalReason)`

### Backend Issues:
4. ✅ **Added comprehensive logging**
   - Logs incoming data
   - Logs document fetch
   - Logs each document update
   - Logs each audit entry creation

---

## 🔍 How to Debug

### Step 1: Check Browser Console (F12)
When submitting pre-approval rejection, you should see:
```
PRE-APPROVAL SUBMISSION: {
    pre_approval_status: 'Rejected',
    remarks: '[your notes]',
    approval_reason: '[your reason]'  ← SHOULD BE POPULATED
}
```

**If approval_reason is empty/missing:**
- [ ] Check if you filled in the "Approval Reason" field
- [ ] Field should appear ONLY when status is "Rejected" or "Approved"
- [ ] Click on "Reject" button first to show the field

### Step 2: Check Error Log (error_log.txt)
After submitting, look for these log entries in order:

```
PRE-APPROVAL HANDLER: Received data - Status: Rejected, Reason: [your text], Remarks: [your notes]
PREAPPROVAL_REJECT_START: Rejecting application APP-xxx with reason: [your text]
PREAPPROVAL_DOCS_FOUND: Found 5 documents to reject
PREAPPROVAL_REJECTING_DOC: Document ID Proof (ID: 93)
PREAPPROVAL_UPDATE_DOC: Update result for doc 93: 1 rows
PREAPPROVAL_AUDIT_CREATED: Audit entry created, result: 1
[repeat for each document]
PREAPPROVAL_REJECT_COMPLETE: All documents rejected successfully
```

**If logs don't appear:**
- Check if PHP error logging is enabled
- Verify error_log.txt location and permissions
- Look for PHP warnings before these logs

### Step 3: Verify Database

```sql
-- Check pre-approval updated
SELECT pre_approval_status, approval_reason 
FROM loan_applications 
WHERE application_id = 'APP-20251113-0001';
```
✅ Expected: Status = 'Rejected', approval_reason = your text

```sql
-- Check documents rejected
SELECT document_id, status, rejection_notes 
FROM documents 
WHERE application_id = 'APP-20251113-0001';
```
✅ Expected: All status = 'Rejected', rejection_notes = your text

```sql
-- Check audit trail
SELECT audit_id, document_id, new_status, admin_name, notes, changed_at 
FROM document_audit 
WHERE application_id = 'APP-20251113-0001' 
ORDER BY changed_at DESC;
```
✅ Expected: Multiple entries, one per document with admin info and reason

---

## 🔧 Complete Flow (Fixed)

### 1. Admin UI - Reject Button Clicked
```javascript
// admin2_dashboard.php line ~5470
// Status radio change event listener fires
statusRadios.forEach(radio => {
    radio.addEventListener("change", function () {
        // Radio value = "Rejected"
        // approvalReasonGroup.style.display = 'block'  ← Field appears
    });
});
```

### 2. Admin Fills Form
```
Pre-Approval Status: Rejected (selected)
Decision Notes: "Documents incomplete" (text)
Approval Reason: "Missing income certificate" (text) ← CRITICAL FIELD
```

### 3. Admin Clicks Submit Decision
```javascript
// admin2_dashboard.php line ~5530
statusForm.addEventListener("submit", function (e) {
    // Collects:
    const preApprovalStatus = 'Rejected'
    const remarks = 'Documents incomplete'
    const approvalReason = 'Missing income certificate'
    
    // Validates approvalReason exists
    if (!approvalReason) {
        showNotification('Approval reason is required...', 'warning');
        return;  // STOP if missing
    }
    
    // Passes to confirmation modal
    showPreApprovalConfirmModal(preApprovalStatus, remarks, approvalReason);
    //                                                      ↑ FIXED: Now passes this
});
```

### 4. Confirmation Modal Receives Data
```javascript
// admin2_dashboard.php line ~4882
function showPreApprovalConfirmModal(preApprovalStatus, remarks, approvalReason) {
    //                                                           ↑ FIXED: Now accepts this
    
    // Stores in pendingPreApprovalData
    pendingPreApprovalData = { preApprovalStatus, remarks, approvalReason };
    //                                                     ↑ FIXED: Now stores this
    
    // Shows confirmation modal
    modal.style.display = 'flex';
}
```

### 5. Admin Confirms in Modal
```javascript
// admin2_dashboard.php line ~4955
function confirmPreApprovalAction() {
    const { preApprovalStatus, remarks, approvalReason } = pendingPreApprovalData;
    //                                ↑ FIXED: Now retrieves this
    
    // Get form
    const formData = new FormData(statusForm);
    
    // Form already contains approval_reason field from HTML form
    // FormData automatically captures: 
    //   - action: 'update_status'
    //   - application_id: 'APP-xxx'
    //   - pre_approval_status: 'Rejected'
    //   - remarks: 'Documents incomplete'
    //   - approval_reason: 'Missing income certificate' ← CAPTURED
    
    // Send to backend
    fetch("admin2_dashboard.php", { method: "POST", body: formData })
}
```

### 6. Backend Receives Data
```php
// admin2_dashboard.php line ~1475
$preApprovalStatus = sanitizeString($_POST['pre_approval_status'], 20);  // 'Rejected'
$remarks = sanitizeString($_POST['remarks'], 500);                       // 'Documents incomplete'
$approvalReason = sanitizeString($_POST['approval_reason'], 1000);       // 'Missing income certificate'

error_log("PRE-APPROVAL HANDLER: Received data - Status: $preApprovalStatus, Reason: $approvalReason, Remarks: $remarks", E_USER_NOTICE);
// Logs: Status: Rejected, Reason: Missing income certificate, Remarks: Documents incomplete
```

### 7. Backend Updates Pre-Approval
```php
// admin2_dashboard.php line ~1590
UPDATE loan_applications 
SET pre_approval_status = 'Rejected', 
    approval_reason = 'Missing income certificate',  ← STORED
    updated_at = NOW() 
WHERE application_id = 'APP-20251113-0001'
```

### 8. Backend Rejects All Documents (NEW)
```php
// admin2_dashboard.php line ~1708
if ($preApprovalStatus === 'Rejected' && !empty($approvalReason)) {
    error_log("PREAPPROVAL_REJECT_START: Rejecting with reason: $approvalReason", E_USER_NOTICE);
    
    // For each document:
    foreach ($allDocsToReject as $docToReject) {
        // UPDATE documents table
        UPDATE documents 
        SET status = 'Rejected', 
            status_updated_at = NOW(), 
            rejection_notes = 'Missing income certificate'  ← STORED
        WHERE document_id = 93
        
        // INSERT audit trail
        INSERT INTO document_audit 
        (document_id, application_id, old_status, new_status, admin_id, admin_name, admin_role, notes) 
        VALUES (93, 'APP-20251113-0001', 'Pending', 'Rejected', 9, 'Ken Sollosa', 'Admin2', 'Missing income certificate')
    }
}
```

### 9. Email Sent with All Details
```php
// Admin2_dashboard.php line ~1759
sendConsolidatedUpdateEmail($conn, $applicationId, $consolidatedUpdates);

// Email contains:
// - Pre-Approval Decision: REJECTED
// - Reason: Missing income certificate
// - For each document:
//   - Document Name
//   - Status: Rejected
//   - Reason: Missing income certificate
```

---

## ✅ Verification Checklist

After implementing fix, verify:

### Browser (F12 Console)
- [ ] `PRE-APPROVAL SUBMISSION` log shows approval_reason populated
- [ ] No JavaScript errors
- [ ] Success notification appears

### Error Log (error_log.txt)
- [ ] `PRE-APPROVAL HANDLER` shows reason received
- [ ] `PREAPPROVAL_REJECT_START` appears
- [ ] `PREAPPROVAL_DOCS_FOUND` shows document count
- [ ] Multiple `PREAPPROVAL_REJECTING_DOC` entries
- [ ] `PREAPPROVAL_REJECT_COMPLETE` appears

### Database
```sql
-- All 3 should show rejection_notes/reason populated:
SELECT COUNT(*) as docs_rejected 
FROM documents 
WHERE application_id = 'APP-TEST' AND status = 'Rejected' AND rejection_notes IS NOT NULL;
-- Expected: Count > 0 ✓

SELECT COUNT(*) as audit_entries 
FROM document_audit 
WHERE application_id = 'APP-TEST' AND new_status = 'Rejected' AND notes IS NOT NULL;
-- Expected: Count = number of rejected docs ✓

SELECT approval_reason 
FROM loan_applications 
WHERE application_id = 'APP-TEST';
-- Expected: Your reason (NOT NULL) ✓
```

### Email
- [ ] Received in applicant inbox
- [ ] Shows "REJECTED" status
- [ ] Shows rejection reason
- [ ] Lists each document with reason

---

## 🎯 Quick Test (3 minutes)

1. **Go to applicant record**
2. **Select Status: Reject**
3. **Wait for "Approval Reason" field to appear** ← If NOT appearing, UI issue
4. **Fill in both fields:**
   - Decision Notes: "Testing fix"
   - Approval Reason: "Missing documents"
5. **Click Submit Decision**
6. **Confirm in modal**
7. **Check:**
   - Browser console (F12) for log
   - Error log for debug entries
   - Database for data
   - Email for message

**If rejection_notes still NULL:**
- [ ] Check Step 3 - Is Approval Reason field appearing?
- [ ] Check console log - Is approval_reason in SUBMISSION data?
- [ ] Check error log - Does PRE-APPROVAL HANDLER see the reason?
- [ ] Check database - Is approval_reason NULL in loan_applications?

---

## 📊 Data Examples

### Correct Submission (All fields populated)
```
Browser Console:
PRE-APPROVAL SUBMISSION: {
    pre_approval_status: 'Rejected',
    remarks: 'Testing fix',
    approval_reason: 'Missing documents'  ← ✓
}

Error Log:
PRE-APPROVAL HANDLER: Received data - Status: Rejected, Reason: Missing documents, Remarks: Testing fix

Database:
documents: rejection_notes = 'Missing documents' ✓
document_audit: notes = 'Missing documents' ✓
loan_applications: approval_reason = 'Missing documents' ✓
```

### Incorrect Submission (Reason missing)
```
Browser Console:
PRE-APPROVAL SUBMISSION: {
    pre_approval_status: 'Rejected',
    remarks: 'Testing fix',
    approval_reason: ''  ← ✗ EMPTY
}

Error Log:
PRE-APPROVAL HANDLER: Received data - Status: Rejected, Reason: , Remarks: Testing fix
PREAPPROVAL_NO_DOCS: No documents found to reject  ← ✗ Condition not met

Database:
rejection_notes = NULL ✗
document_audit: empty ✗
```

---

## 🚨 Troubleshooting Matrix

| Problem | Check | Solution |
|---------|-------|----------|
| Approval Reason field not showing | Reject radio selected? | Click "Reject" button first |
| rejection_notes NULL | Console log has value? | Check form submission |
| Audit not created | Error log shows REJECT_START? | Check document_audit table exists |
| Email lacks details | Database has notes? | Check email template |
| Multiple rejections fail | First one worked? | Check logs for errors |

---

## 📝 Files Modified

- `admin2_dashboard.php` (Lines: 1478, 4882, 4943, 5559, 1708-1750)

## 🔑 Key Variables Tracked

| Variable | Source | Destination | Expected Value |
|----------|--------|-------------|-----------------|
| `approval_reason` | Form field | POST data | User-entered text |
| `$approvalReason` | $_POST | Backend | Sanitized text |
| `rejection_notes` | `$approvalReason` | documents table | Same as above |
| `notes` | `$approvalReason` | document_audit | Same as above |
| `approval_reason` | `$approvalReason` | loan_applications | Same as above |

---

## ✅ Expected Success State

After rejection with reason "Test":

```
loan_applications:
├─ pre_approval_status: "Rejected" ✓
└─ approval_reason: "Test" ✓

documents (all):
├─ status: "Rejected" ✓
└─ rejection_notes: "Test" ✓

document_audit (one per doc):
├─ old_status: "Pending/Approved" ✓
├─ new_status: "Rejected" ✓
├─ admin_name: "Your Name" ✓
└─ notes: "Test" ✓

applicant email:
├─ Pre-Approval Decision: REJECTED ✓
├─ Reason visible ✓
└─ Document rejection details ✓
```

---

