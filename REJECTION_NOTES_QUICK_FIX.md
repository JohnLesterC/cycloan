# Quick Start: Rejection Notes Fix

## The Problem in 30 Seconds

Email shows rejection reason ✅ → But database rejection_notes = 0 ❌

## What Was Fixed

Added **comprehensive debugging** to trace why rejection_notes not being saved:

```php
// Line 1724: Log the rejection reason BEFORE UPDATE
error_log("DEBUG_REJECTION_REASON: Value='$approvalReason' | Length=" . strlen($approvalReason) . " | Type=" . gettype($approvalReason), E_USER_NOTICE);

// Line 1730-1734: UPDATE documents
$updateResult = executeUpdate($conn, "UPDATE documents SET rejection_notes = ? WHERE document_id = ?", "si", [$approvalReason, $docToReject['document_id']]);

// Line 1737: Log UPDATE result
error_log("PREAPPROVAL_UPDATE_DOC: Update result for doc {$docToReject['document_id']}: $updateResult rows affected", E_USER_NOTICE);

// Line 1740-1745: Verify what was actually saved
$verify = executeQuery($conn, "SELECT rejection_notes FROM documents WHERE document_id = ?", "i", [$docToReject['document_id']]);
$saved = $verify[0]['rejection_notes'];
error_log("VERIFY_SAVED: Doc {$docToReject['document_id']} rejection_notes='$saved' (Type: " . gettype($saved) . ")", E_USER_NOTICE);
```

## Test the Fix

1. **Reject an application** in admin2_dashboard.php with a test reason
2. **Run the diagnostic:**
   ```bash
   php test_rejection_debug.php
   ```
3. **Check for these debug messages** in the output:
   - `DEBUG_REJECTION_REASON: Value=...`
   - `VERIFY_SAVED: Doc 123 rejection_notes=...`

## What the Debug Messages Tell You

### Good Signs ✅

```
DEBUG_REJECTION_REASON: Value='Missing documents' | Length=18 | Type=string
PREAPPROVAL_UPDATE_DOC: Update result for doc 123: 1 rows affected
VERIFY_SAVED: Doc 123 rejection_notes='Missing documents' (Type: string)
```

→ Everything working! The rejection_notes is being saved correctly.

### Red Flags ❌

```
VERIFY_SAVED: Doc 123 rejection_notes='0' (Type: string)
```

→ UPDATE saved as 0 instead of text. Database/encoding issue.

```
PREAPPROVAL_UPDATE_DOC: Update result for doc 123: 0 rows affected
```

→ UPDATE didn't affect any rows. Document not found or already rejected.

```
[No debug messages at all]
```

→ Pre-approval rejection code not running. Check if 'Reject' button clicked.

## Files Modified

| File                           | Change                        |
| ------------------------------ | ----------------------------- |
| admin2_dashboard.php           | Added logging lines 1724-1745 |
| test_rejection_debug.php       | NEW - Diagnostic script       |
| REJECTION_NOTES_DEBUG_GUIDE.md | NEW - Full guide              |

## If It's Still Not Working

Debug steps (in order):

1. **Verify form data:**

   - Does email show the rejection reason? (Yes = data reaching backend)
   - What does approval_reason field contain?

2. **Check database:**

   - Run: `SELECT * FROM documents WHERE document_id = 123 LIMIT 1;`
   - Is rejection_notes showing 0, empty, or NULL?

3. **Check parameter type:**

   - Try changing "si" to "ss" at line 1730
   - Force string type: `", "ss", [$approvalReason, (string)$docToReject['document_id']]`

4. **Check character encoding:**
   - Is the form sending UTF-8?
   - Is the database expecting UTF-8?

## Need Help?

Share the output of:

```bash
php test_rejection_debug.php
```

Plus:

1. What did you enter as the rejection reason?
2. What does the email show?
3. What does the database query show?
4. Any error messages in the error log?

---

**Status:** Enhanced debugging in place. Run test script to identify root cause.
