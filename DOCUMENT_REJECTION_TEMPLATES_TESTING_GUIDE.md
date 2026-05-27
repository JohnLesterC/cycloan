# Document Rejection Reason Templates - Testing & Deployment Guide

**Status:** ✅ READY FOR TESTING & DEPLOYMENT  
**Implementation Date:** Phase 7  
**Testing Priority:** CRITICAL (User-Facing Feature)

---

## 🎯 Quick Start - End-to-End Test (5 minutes)

### Step 1: Login as Admin2

```
1. Open: http://localhost:8000/login.php (or your dev URL)
2. Username: [Admin2 user]
3. Password: [Admin2 password]
4. Verify: Redirected to admin2_dashboard.php
```

### Step 2: Navigate to Document Approval

```
1. From Admin2 Dashboard, find a loan application with documents
2. Click on application to view documents
3. Find a document with status "Pending"
4. Click "Reject" button on any document
```

### Step 3: Verify Modal Shows Rejection Fields

```
Confirm Modal Should Display:
✓ "Confirm document rejection?" message
✓ Dropdown: "Select Rejection Reason"
✓ Textarea: "Rejection Reason" field
✓ Character counter: "0/1000"
✓ "Confirm Rejection" button
```

### Step 4: Test Template Selection

```
1. Click dropdown: "Select Rejection Reason"
2. Verify 8 templates appear:
   - Poor Image Quality
   - Expired Document
   - Incomplete Information
   - Name Mismatch
   - Invalid Signature
   - Unverifiable Document
   - Incorrect Document Type
   - Duplicate Submission
3. Click "Poor Image Quality" template
4. Verify: Textarea auto-fills with template text
5. Verify: Character counter updates (e.g., "189/1000")
```

### Step 5: Test Form Submission

```
1. Click "Confirm Rejection" button
2. Loading overlay should appear
3. After 2-3 seconds, should show success message
4. Modal should close
5. Document should show "Rejected" status
```

### Step 6: Verify Activity Log

```
1. Click on application again
2. Scroll to Activity Log section
3. Find latest entry that says "Document Status Updated"
4. Should include: "Document: [name] | From: Pending → To: Rejected | Reason: [full text]"
5. Reason should show the full template text
```

### Step 7: Check Applicant Email

```
1. Open applicant's email (check spam folder too)
2. Email subject should mention document rejection
3. Email body should include:
   - Document name
   - Rejection status
   - Rejection reason (template text)
   - Link to resubmit
```

---

## 📋 Detailed Test Cases

### Test Case 1: Template Loading

**Test ID:** DRT-001  
**Title:** Rejection templates load when modal opens  
**Prerequisites:** Admin2 logged in, document visible

**Steps:**

1. Click "Reject" on a document
2. Modal opens
3. Observe network tab in DevTools
4. Should see GET request to `admin2_dashboard.php?action=get_rejection_templates`

**Expected Results:**
| Item | Expected | Status |
|------|----------|--------|
| Request URL | `admin2_dashboard.php?action=get_rejection_templates` | ✓ |
| HTTP Status | 200 OK | ✓ |
| Response Type | application/json | ✓ |
| Response Contains | "success": true | ✓ |
| Template Count | 8 templates | ✓ |
| Template Structure | id, name, reason fields | ✓ |

**Example Response:**

```json
{
  "success": true,
  "templates": [
    {
      "id": 1,
      "name": "Poor Image Quality",
      "reason": "The document image provided is unclear..."
    },
    ...
  ]
}
```

---

### Test Case 2: Template Auto-Population

**Test ID:** DRT-002  
**Title:** Selecting template auto-fills rejection reason field  
**Prerequisites:** Template dropdown populated

**Steps:**

1. Modal open with rejection fields visible
2. Click dropdown "Select Rejection Reason"
3. Select "Expired Document"
4. Observe textarea field

**Expected Results:**
| Item | Expected | Status |
|------|----------|--------|
| Textarea Content | Fills with template reason | ✓ |
| Character Counter | Updates to actual length | ✓ |
| Focus | Moves to textarea | ✓ |
| Display Format | Text is readable, not truncated | ✓ |

**Example:**

```
Textarea Before: [empty]
Dropdown Selected: "Expired Document"
Textarea After: "The document has expired and is no longer valid. Please provide an updated version of this document."
Character Counter: "131/1000"
```

---

### Test Case 3: Character Counter

**Test ID:** DRT-003  
**Title:** Character counter updates in real-time  
**Prerequisites:** Rejection reason field visible

**Steps:**

1. Modal with rejection fields open
2. Click in textarea
3. Type or paste text
4. Observe character counter
5. Type until near 1000 characters

**Expected Results:**
| Action | Expected Counter | Status |
|--------|------------------|--------|
| Empty | 0/1000 | ✓ |
| Type 10 chars | 10/1000 | ✓ |
| Select template (131 chars) | 131/1000 | ✓ |
| Delete 20 chars | 111/1000 | ✓ |
| Type to 900+ chars | 900+/1000 (text warning) | ✓ |

---

### Test Case 4: Form Submission Validation

**Test ID:** DRT-004  
**Title:** Cannot submit rejection without reason  
**Prerequisites:** Modal in rejection mode, textarea empty

**Steps:**

1. Click "Reject" on document
2. Modal opens with empty rejection reason field
3. Click "Confirm Rejection" button
4. Observe response

**Expected Results:**
| Item | Expected | Status |
|------|----------|--------|
| Form Submission | Blocked (not sent) | ✓ |
| Error Message | "Please provide a rejection reason" (warning) | ✓ |
| Field Focus | Focus moves to rejection reason textarea | ✓ |
| Textarea Remains | Empty field still visible for input | ✓ |

---

### Test Case 5: Successful Rejection with Template

**Test ID:** DRT-005  
**Title:** Reject document with template reason  
**Prerequisites:** Document in "Pending" status

**Steps:**

1. Click "Reject" on document
2. Modal opens
3. Select template: "Poor Image Quality"
4. Textarea auto-fills
5. Click "Confirm Rejection"

**Expected Results:**
| Item | Expected | Status |
|------|----------|--------|
| Loading Overlay | Shows "Rejecting document..." | ✓ |
| Backend Processing | < 2 seconds | ✓ |
| Success Message | "Document status updated successfully!" | ✓ |
| Modal Closes | Modal disappears | ✓ |
| Document Status | Changes to "Rejected" | ✓ |
| Activity Log | Updated with reason | ✓ |
| Applicant Email | Sent with rejection reason | ✓ |

---

### Test Case 6: Activity Log Contains Reason

**Test ID:** DRT-006  
**Title:** Activity log displays full rejection reason  
**Prerequisites:** Document rejected with template reason

**Steps:**

1. Go to document's application
2. Scroll to Activity Logs section
3. Find most recent "Document Status Updated" entry
4. Read description

**Expected Result:**

```
Document Status Updated | Document: PDF Contract | From: Pending → To: Rejected |
Reason: The document image provided is unclear or of poor quality. Please resubmit
a clear, high-resolution photograph or scan of your document. |
Reference: application ABC123 | Updated by: Admin John
```

**Verification:**
| Item | Expected | Status |
|------|----------|--------|
| Log Entry Found | Present in activity log | ✓ |
| Status Change | Shows "Pending → Rejected" | ✓ |
| Reason Included | Full template text visible | ✓ |
| HTML Escaped | Text is safe (no code injection) | ✓ |
| Timestamp | Recent timestamp | ✓ |
| Admin Name | Shows who approved/rejected | ✓ |

---

### Test Case 7: Email Contains Rejection Reason

**Test ID:** DRT-007  
**Title:** Applicant receives email with rejection reason  
**Prerequisites:** Document rejected, applicant email configured

**Steps:**

1. Reject a document with reason
2. Check applicant's email (check spam)
3. Open rejection email
4. Read email body

**Expected Result:**

```
From: noreply@cycloan.com
To: applicant@email.com
Subject: Document Status Update - Loan Application ABC123

Dear John,

Your document "PDF Contract" status has been updated:

STATUS: Rejected

REASON:
The document image provided is unclear or of poor quality.
Please resubmit a clear, high-resolution photograph or scan
of your document.

NEXT STEPS:
Click the link below to resubmit your document:
[Dashboard Link]

Thank you,
CYCLOAN Team
```

**Verification:**
| Item | Expected | Status |
|------|----------|--------|
| Email Received | Present in inbox/spam | ✓ |
| Subject Line | Mentions document rejection | ✓ |
| Document Name | Shows correct document | ✓ |
| Status | Shows "Rejected" | ✓ |
| Reason Included | Full template text in email | ✓ |
| Action Link | Clickable resubmit link | ✓ |
| Format | Professional, readable | ✓ |

---

### Test Case 8: Approval Still Works (No Reason)

**Test ID:** DRT-008  
**Title:** Approving documents doesn't require rejection reason  
**Prerequisites:** Document in "Pending" status

**Steps:**

1. Click "Approve" on document
2. Modal opens
3. Observe modal content

**Expected Results:**
| Item | Expected | Status |
|------|----------|--------|
| Modal Shows | "Confirm document approval?" message | ✓ |
| Rejection Fields | NOT visible (hidden) | ✓ |
| Dropdown Present | No template dropdown shown | ✓ |
| Textarea Present | No rejection reason field shown | ✓ |
| Buttons | "Approve" and "Cancel" | ✓ |
| Submit Success | Can click "Approve" without reason | ✓ |
| Status Changes | Document becomes "Approved" | ✓ |

---

### Test Case 9: Security - CSRF Protection

**Test ID:** DRT-009  
**Title:** CSRF token validated on form submission  
**Prerequisites:** Network interception tools (like Burp)

**Steps:**

1. Intercept document rejection POST request
2. Remove CSRF token from request
3. Modify CSRF token value
4. Send modified request

**Expected Results:**
| Modification | Expected Response | Status |
|--------------|-------------------|--------|
| No CSRF Token | 403 Forbidden, "Security validation failed" | ✓ |
| Wrong CSRF Token | 403 Forbidden, "Security validation failed" | ✓ |
| Modified Token | 403 Forbidden, "Security validation failed" | ✓ |
| Valid Token | 200 OK with success response | ✓ |

---

### Test Case 10: Security - XSS Prevention

**Test ID:** DRT-010  
**Title:** Rejection reason properly escaped in activity log  
**Prerequisites:** Ability to send custom POST data

**Steps:**

1. Prepare XSS payload: `<script>alert('XSS')</script>`
2. Attempt to submit rejection with payload as reason
3. Check activity log entry

**Expected Result:**
| Item | Expected | Status |
|------|----------|--------|
| Activity Log Entry | Shows escaped HTML: `&lt;script&gt;alert...` | ✓ |
| JavaScript Execution | No alert popup | ✓ |
| Email Body | Payload escaped, not executed | ✓ |
| Database Storage | Payload stored safely | ✓ |

---

## 🔧 Browser Testing Checklist

### Chrome / Edge / Brave

- [ ] Templates load in dropdown
- [ ] Template selection auto-fills textarea
- [ ] Character counter updates correctly
- [ ] Form submits successfully
- [ ] Activity log shows reason
- [ ] Email received with reason

### Firefox

- [ ] All above tests pass
- [ ] Textarea styling correct
- [ ] Dropdown display proper
- [ ] No console errors

### Safari

- [ ] All above tests pass
- [ ] Mobile viewport (iPhone size)
- [ ] Tablet viewport (iPad size)
- [ ] Touch events work properly

### Mobile Testing

- [ ] Modal displays correctly on small screen
- [ ] Dropdown scrollable if too many options
- [ ] Textarea usable on mobile keyboard
- [ ] Submit button accessible
- [ ] Success message visible

---

## 📊 Performance Testing

### Response Time Benchmarks

| Operation           | Target   | Acceptable | Status |
| ------------------- | -------- | ---------- | ------ |
| Template Loading    | < 500ms  | < 1000ms   | ✓      |
| Document Rejection  | < 1500ms | < 3000ms   | ✓      |
| Activity Log Update | Instant  | < 500ms    | ✓      |
| Email Sending       | < 2000ms | < 5000ms   | ✓      |
| Page Refresh        | < 2000ms | < 3000ms   | ✓      |

### Load Testing

```sql
-- Test with 100 simultaneous rejections
-- Should handle without timeout or data loss
```

---

## 🐛 Common Issues & Solutions

### Issue 1: Dropdown shows but no templates

**Symptoms:** Dropdown renders but no options appear  
**Root Causes:**

1. Backend endpoint missing or broken
2. JSON response format invalid
3. JavaScript error in loadRejectionTemplates()

**Troubleshooting:**

```
1. Check browser DevTools Network tab
2. Look for GET admin2_dashboard.php?action=get_rejection_templates
3. Check response JSON: Valid JSON or parse error?
4. Check browser Console: Any JavaScript errors?
5. Test endpoint directly in browser/Postman
```

**Fix:**

```php
// Verify endpoint exists around line 1100
if (isset($_GET['action']) && $_GET['action'] === 'get_rejection_templates') {
    // Should return valid JSON
    echo json_encode(['success' => true, 'templates' => $rejectionTemplates]);
}
```

---

### Issue 2: Textarea doesn't auto-fill

**Symptoms:** Selecting template doesn't populate textarea  
**Root Causes:**

1. JavaScript function onTemplateSelect() not defined
2. Textarea ID doesn't match ('rejectionReason')
3. Dropdown onchange event not bound

**Troubleshooting:**

```
1. Open browser Console
2. Type: document.getElementById('rejectionReason')
3. Should return textarea element (not null)
4. Type: typeof onTemplateSelect
5. Should show "function" (not undefined)
```

**Fix:**

```html
<!-- Verify dropdown has onchange handler -->
<select id="rejectionTemplateSelect" onchange="onTemplateSelect()"></select>

<!-- Verify textarea has correct ID -->
<textarea id="rejectionReason" maxlength="1000"></textarea>
```

---

### Issue 3: Character counter stuck at 0

**Symptoms:** Character counter always shows "0/1000" even with text  
**Root Causes:**

1. updateRejectionReasonCharCount() function missing
2. oninput event not bound to textarea
3. Character counter display element ID wrong

**Troubleshooting:**

```
1. Click in textarea and type 'test'
2. Open browser Console
3. Run: document.getElementById('rejectionReasonCharCount')
4. Should return element (not null)
5. Run: document.getElementById('rejectionReason').value.length
6. Should return number of characters
```

**Fix:**

```html
<!-- Add oninput handler to textarea -->
<textarea
  id="rejectionReason"
  oninput="updateRejectionReasonCharCount()"
  maxlength="1000"
></textarea>

<!-- Verify character count display -->
<span id="rejectionReasonCharCount">0</span>/1000
```

---

### Issue 4: Form submits but no reason sent

**Symptoms:** Backend receives form submission but rejection_reason is empty  
**Root Causes:**

1. FormData.append('rejection_reason', ...) missing from confirmDocumentAction()
2. Textarea value is empty or whitespace only
3. Value not being trimmed before submission

**Troubleshooting:**

```javascript
// In browser Console:
// Check if textarea has value
document.getElementById("rejectionReason").value;

// Check if FormData includes it
const fd = new FormData();
fd.append("rejection_reason", "test");
console.log(fd.get("rejection_reason")); // Should show 'test'
```

**Fix:**

```javascript
// In confirmDocumentAction():
if (status === "Rejected") {
  const rejectionReasonField = document.getElementById("rejectionReason");
  if (rejectionReasonField) {
    // This line must exist:
    formData.append("rejection_reason", rejectionReasonField.value.trim());
  }
}
```

---

### Issue 5: Backend returns 400 error

**Symptoms:** Modal shows error "Rejection reason is required"  
**Root Causes:**

1. Rejecting without selecting template or entering custom reason
2. Rejection reason field is empty or only whitespace
3. Reason text got trimmed to empty string

**User Fix:**

```
1. See error message
2. Click on rejection reason field
3. Either select template from dropdown OR type custom reason
4. Make sure field is not empty (at least 1 character)
5. Click "Confirm Rejection" again
```

---

## ✅ Pre-Deployment Checklist

### Code Quality

- [ ] All PHP follows PSR-12 standards
- [ ] All JavaScript is ES6+ compatible
- [ ] No console.error() or console.warn() without context
- [ ] No hardcoded values (except templates)
- [ ] Comments explain non-obvious logic
- [ ] PHPDoc blocks on functions

### Security

- [ ] CSRF tokens validated on all POST requests
- [ ] All user input sanitized (rejection_reason)
- [ ] XSS prevention: htmlspecialchars() used
- [ ] SQL injection prevention: prepared statements
- [ ] Role authorization: Admin2 check present
- [ ] No secrets in code (passwords, API keys)

### Functionality

- [ ] Templates load successfully
- [ ] Template selection auto-populates field
- [ ] Character counter works in real-time
- [ ] Form validates rejection reason required
- [ ] Backend receives and stores reason
- [ ] Activity log captures reason
- [ ] Email includes reason
- [ ] Approval workflow unaffected

### Performance

- [ ] Template loading < 1 second
- [ ] Form submission < 3 seconds
- [ ] No memory leaks (check DevTools)
- [ ] No N+1 query problems
- [ ] Overlay timeout prevents hanging

### Browser Compatibility

- [ ] Chrome/Edge: Pass
- [ ] Firefox: Pass
- [ ] Safari: Pass
- [ ] Mobile browsers: Pass
- [ ] IE11: Document or skip

### Documentation

- [ ] Code comments adequate
- [ ] Function documentation complete
- [ ] Testing guide provided
- [ ] Troubleshooting guide provided
- [ ] Deployment instructions clear

### Data & Backups

- [ ] Database backup taken
- [ ] Migration tested on staging DB
- [ ] Rollback plan documented
- [ ] No data loss scenarios identified

### Monitoring

- [ ] Error logging configured
- [ ] Performance metrics tracked
- [ ] Admin notifications for failures
- [ ] User-facing error messages clear

---

## 🚀 Deployment Steps

### Pre-Deployment (30 min)

```
1. [ ] Backup database: mysqldump -u root -p cycloan_db > backup_$(date +%Y%m%d).sql
2. [ ] Backup admin2_dashboard.php: cp admin2_dashboard.php admin2_dashboard.php.bak
3. [ ] Review code changes one more time
4. [ ] Verify all tests passing in staging
5. [ ] Notify team of deployment window
```

### Deployment (5 min)

```
1. [ ] Stop any active processes (optional if no downtime needed)
2. [ ] Copy new admin2_dashboard.php to production
3. [ ] Clear browser cache (CDN if applicable)
4. [ ] Verify file permissions (644 for .php files)
5. [ ] Test one rejection workflow manually
```

### Post-Deployment (30 min)

```
1. [ ] Monitor error logs for exceptions
2. [ ] Check activity logs for new entries
3. [ ] Verify email delivery (check inbox/spam)
4. [ ] Monitor server load/performance
5. [ ] Notify team deployment complete
6. [ ] Schedule admin training if needed
```

### Rollback (if needed)

```
1. [ ] Restore backup: cp admin2_dashboard.php.bak admin2_dashboard.php
2. [ ] Clear caches
3. [ ] Verify rollback successful
4. [ ] Investigate what went wrong
5. [ ] Schedule post-mortem review
```

---

## 📞 Support & Escalation

### During Testing Phase

- **Bug Found:** Create issue with Test Case ID and detailed reproduction steps
- **Performance Issue:** Check response times, database queries, server resources
- **Security Concern:** Stop testing, notify security team immediately

### Known Limitations

1. Max rejection reason: 1000 characters
2. Templates cannot be modified via UI (code change only)
3. Rejection reasons stored in activity logs (not separate table)
4. No bulk rejection with same reason (one at a time)

### Feature Requests

- Custom template management (future phase)
- Reason analytics/reporting (future phase)
- Multiple languages for templates (future phase)
- Batch operations (future phase)

---

## 📝 Sign-Off

**Tested By:** ********\_\_\_********  
**Test Date:** ********\_\_\_********  
**Test Result:** ✅ PASS / ❌ FAIL

**Approved By:** ********\_\_\_********  
**Approval Date:** ********\_\_\_********

**Deployed By:** ********\_\_\_********  
**Deployment Date:** ********\_\_\_********  
**Deployment Status:** ✅ SUCCESS / ❌ ROLLBACK

---

**Document Version:** 1.0  
**Last Updated:** [Current Date]  
**Next Review:** [14 days after deployment]
