# Pre-Approval Function - Fixed Flow Documentation

## Problem Fixed

### Issue 1: Audit Records Not Created for Pre-Approval Rejections

**Before:** When rejecting an application during pre-approval, no document audit entries were created.
**After:** ✅ Each document gets an audit trail entry with rejection reason

### Issue 2: Rejection Notes Null Despite Notes Being Chosen

**Before:** When rejecting pre-approval with a reason, the rejection_notes column remained NULL for documents.
**After:** ✅ rejection_notes column is populated with the pre-approval reason for all rejected documents

### Issue 3: No Connection Between Pre-Approval Rejection and Document Rejection

**Before:** Pre-approval rejection and document rejection were separate processes.
**After:** ✅ Pre-approval rejection automatically rejects all documents with the same reason

---

## Fixed Pre-Approval Workflow

### Step 1: Admin Reviews Application (Lines 1463-1560)

```php
// Admin enters:
- pre_approval_status: 'Rejected' (selected from dropdown)
- approval_reason: 'Documents do not meet requirements' (rejection template or custom text)
- remarks: 'Please resubmit complete income documents' (optional decision notes)
```

**Security Validation:**

- ✅ CSRF token verified
- ✅ Status validated against whitelist (Pending, Approved, Rejected)
- ✅ Approval reason required for Approved/Rejected status
- ✅ Admin2 permissions validated

### Step 2: Update Pre-Approval Status (Lines 1590-1640)

```php
// Database Update:
UPDATE loan_applications
SET pre_approval_status = 'Rejected',
    approval_reason = 'Documents do not meet requirements',
    updated_at = NOW()
WHERE application_id = 'APP-20251113-0001'
```

**Activity Logged:**

```
"Pre-Approval Status Updated | From: Pending → To: Rejected |
Reason: Documents do not meet requirements | Updated by: Ken Sollosa"
```

### Step 3: Process Decision Notes (Lines 1673-1700)

If admin adds remarks:

```php
INSERT INTO remarks (application_id, remarks, created_at, admin_name)
VALUES ('APP-20251113-0001', 'Resubmission required', NOW(), 'Ken Sollosa')
```

### Step 4: FIXED - Automatic Document Rejection (Lines 1700-1758) ⭐ NEW

When pre-approval status changes to REJECTED:

**Step 4A: Fetch all non-rejected documents**

```sql
SELECT d.document_id, d.document_type_id, d.status, dt.document_name
FROM documents d
JOIN document_types dt ON d.document_type_id = dt.document_type_id
WHERE d.application_id = 'APP-20251113-0001' AND d.status != 'Rejected'
```

**Step 4B: For each document:**

```php
// 1. Update document status AND store rejection notes
UPDATE documents
SET status = 'Rejected',
    status_updated_at = NOW(),
    rejection_notes = 'Documents do not meet requirements'  ← FIXED: NOW STORED!
WHERE document_id = 93

// 2. Create audit trail entry
INSERT INTO document_audit
(document_id, application_id, document_type_id, old_status, new_status,
 admin_id, admin_name, admin_role, notes)
VALUES (93, 'APP-20251113-0001', 1, 'Pending', 'Rejected', 9, 'Ken Sollosa', 'Admin2',
        'Documents do not meet requirements')  ← FIXED: AUDIT CREATED!
```

**Activity Logged for Each Document:**

```
"Document Status Updated | Document: ID Proof | From: Pending → To: Rejected |
Reason: Documents do not meet requirements | Updated by: Ken Sollosa"
```

### Step 5: Fetch Updated Documents (Lines 1750-1758)

```sql
SELECT d.document_id, dt.document_name as name, d.status, d.rejection_notes
FROM documents d
JOIN document_types dt ON d.document_type_id = dt.document_type_id
WHERE d.application_id = 'APP-20251113-0001'
ORDER BY dt.document_name
```

**Result:**

```
document_id | name              | status   | rejection_notes
------------|------------------|----------|----------------------------------
93          | ID Proof          | Rejected | Documents do not meet requirements
94          | Income Certificate| Rejected | Documents do not meet requirements
95          | Bank Statement    | Rejected | Documents do not meet requirements
```

### Step 6: Send Comprehensive Email (Lines 1759+)

Email now includes:

1. **Pre-Approval Decision** with reason
2. **Rejected Documents Section** showing each document with rejection reason
3. **Admin Notes** (if any remarks added)
4. **Contact Information** for resubmission

---

## Data Flow Diagram - FIXED

```
┌─────────────────────────────────────────────────────────────────┐
│ Admin Dashboard - Pre-Approval Section                          │
│ - Select Status: "Rejected"                                     │
│ - Select/Enter Reason: "Documents do not meet requirements"     │
│ - Optional Remarks: "Please resubmit complete docs"             │
└─────────────────────────────────────────────────────────────────┘
                              ↓
┌─────────────────────────────────────────────────────────────────┐
│ [1] Update Pre-Approval Status                                  │
│ ✓ UPDATE loan_applications SET pre_approval_status='Rejected'   │
│ ✓ Log activity for pre-approval change                         │
│ ✓ Create status notification                                   │
└─────────────────────────────────────────────────────────────────┘
                              ↓
┌─────────────────────────────────────────────────────────────────┐
│ [2] Add Decision Remarks (if provided)                         │
│ ✓ INSERT INTO remarks                                           │
│ ✓ Log activity for remarks                                      │
│ ✓ Create remark notification                                    │
└─────────────────────────────────────────────────────────────────┘
                              ↓
┌─────────────────────────────────────────────────────────────────┐
│ [3] FIXED - Automatic Document Rejection                        │
│ For each document in application:                               │
│ ✓ UPDATE documents SET status='Rejected'                        │
│ ✓ UPDATE documents SET rejection_notes=[reason]  ← FIXED!       │
│ ✓ UPDATE documents SET status_updated_at=NOW()                  │
└─────────────────────────────────────────────────────────────────┘
                              ↓
┌─────────────────────────────────────────────────────────────────┐
│ [4] FIXED - Create Document Audit Entries                       │
│ For each rejected document:                                     │
│ ✓ INSERT INTO document_audit WITH all details  ← FIXED!         │
│ ✓ admin_name: Admin who rejected                               │
│ ✓ notes: Rejection reason                                       │
│ ✓ changed_at: Timestamp                                         │
└─────────────────────────────────────────────────────────────────┘
                              ↓
┌─────────────────────────────────────────────────────────────────┐
│ [5] Fetch Documents with Rejection Notes                        │
│ ✓ SELECT... rejection_notes FROM documents  ← FIXED!            │
│ ✓ All rejection reasons now in consolidatedUpdates             │
└─────────────────────────────────────────────────────────────────┘
                              ↓
┌─────────────────────────────────────────────────────────────────┐
│ [6] Send Consolidated Email                                    │
│ Email Includes:                                                 │
│ • Pre-Approval Decision: REJECTED                               │
│ • Reason: "Documents do not meet requirements"                  │
│ • Rejected Documents List with reasons                          │
│ • Admin Notes (if added)                                        │
│ • Contact info for next steps                                   │
└─────────────────────────────────────────────────────────────────┘
                              ↓
┌─────────────────────────────────────────────────────────────────┐
│ [7] Applicant Receives Email                                    │
│ ✓ Sees why pre-approval was rejected                            │
│ ✓ Sees why each document was rejected                           │
│ ✓ Knows exact requirements to fix                               │
│ ✓ Can resubmit with corrections                                 │
└─────────────────────────────────────────────────────────────────┘
```

---

## Database Schema - Before and After

### Before (BROKEN)

```
documents table:
- document_id: 93
- status: Pending  ← Rejected but stayed Pending!
- rejection_notes: NULL  ← Reason not stored!
- status_updated_at: NULL

document_audit table: (NO ENTRIES CREATED)
- Empty for pre-approval rejections
```

### After (FIXED)

```
documents table:
- document_id: 93
- status: Rejected  ← ✓ UPDATED
- rejection_notes: 'Documents do not meet requirements'  ← ✓ STORED
- status_updated_at: 2025-11-18 15:30:45  ← ✓ RECORDED

document_audit table:
- audit_id: 1001
- document_id: 93
- old_status: Pending
- new_status: Rejected
- admin_name: Ken Sollosa  ← ✓ AUDIT CREATED
- notes: 'Documents do not meet requirements'  ← ✓ REASON STORED
- changed_at: 2025-11-18 15:30:45
```

---

## Testing the Fix

### Test Case 1: Reject with Reason

1. Open applicant record
2. Go to Pre-Approval section
3. Set Status to "Rejected"
4. Enter reason: "Missing required documents"
5. Click "Submit Decision"

**Verify:**

```sql
-- Check pre-approval updated
SELECT pre_approval_status, approval_reason
FROM loan_applications
WHERE application_id = 'APP-20251113-0001';
-- Expected: Rejected | Missing required documents

-- Check documents rejected
SELECT document_id, status, rejection_notes
FROM documents
WHERE application_id = 'APP-20251113-0001';
-- Expected: All rows show Rejected | Missing required documents

-- Check audit trail created
SELECT * FROM document_audit
WHERE application_id = 'APP-20251113-0001'
AND new_status = 'Rejected'
ORDER BY changed_at DESC;
-- Expected: Multiple audit entries, one for each document
```

### Test Case 2: Verify Email

1. Check applicant's email inbox
2. Look for rejection email
3. Verify sections show:
   - Pre-approval status: REJECTED
   - Rejection reason visible
   - Each document listed with reason
   - Contact information included

### Test Case 3: Verify Activity Log

1. Go to applicant record
2. Check Activity Log
3. Verify entries for:
   - Pre-approval status change
   - Each document rejection
   - Timestamps and admin name correct

---

## Code Changes Summary

**File Modified:** `admin2_dashboard.php`

**Lines Changed:** 1700-1758 (Pre-approval email section)

**Key Additions:**

1. ✅ Check if rejecting during pre-approval
2. ✅ Fetch all non-rejected documents
3. ✅ Update each document: status + rejection_notes
4. ✅ Create audit trail for each document
5. ✅ Update consolidatedUpdates with rejection_notes
6. ✅ Refresh documents query for email with rejection_notes
7. ✅ Send email with complete rejection information

**Status:** ✅ TESTED AND WORKING

---

## Affected Workflows

### Applicant Impact

- ✅ Receives email with clear rejection reason
- ✅ Knows exactly why documents rejected
- ✅ Understands next steps
- ✅ Can resubmit correctly

### Admin Impact

- ✅ One action rejects entire application
- ✅ Reason applied consistently to all documents
- ✅ Complete audit trail created automatically
- ✅ No need for manual document rejection

### System Impact

- ✅ Consistent data in database
- ✅ Complete audit trail for compliance
- ✅ Email notifications accurate
- ✅ Activity logs comprehensive

---

## Performance Notes

**Optimizations:**

- Uses single query to fetch documents (not N+1)
- Audit entries created in loop (only if documents exist)
- Email updated with final rejection_notes (single query)
- No unnecessary database calls

**Indexes Utilized:**

- `idx_app_status` on documents (application_id, status)
- `idx_document_audit` on document_audit (document_id, application_id)

---

## Future Enhancements

- [ ] Allow partial rejection (some documents ok, some rejected)
- [ ] Provide rejection templates specific to document types
- [ ] Auto-reject if specific document requirements not met
- [ ] Bulk pre-approval rejection with reason templates
- [ ] Rejection analytics dashboard
