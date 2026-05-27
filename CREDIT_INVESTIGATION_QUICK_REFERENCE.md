# Credit Investigation Form - Quick Troubleshooting Guide

## ⚡ Quick Links

| Issue                   | Solution                                                           | File                 | Line       |
| ----------------------- | ------------------------------------------------------------------ | -------------------- | ---------- |
| Form won't submit       | Check browser console for validation errors                        | admin1_dashboard.php | 7421-7470  |
| "Invalid JSON response" | Check error_log.txt for PHP errors                                 | admin1_dashboard.php | 7465-7512  |
| Email not sending       | Verify credentials in CREDIT_INVESTIGATION_FIX_SUMMARY.md          | admin1_dashboard.php | 2303, 2407 |
| Remarks not saving      | Check remarks table schema with verify_credit_investigation_db.php | admin1_dashboard.php | 2432-2456  |
| Modal not opening       | Check browser console for JavaScript errors                        | admin1_dashboard.php | 6716+      |

---

## 🔴 Error Messages & Fixes

### "An error occurred while submitting the credit investigation"

**Check These:**

1. Browser Console → Look for detailed error
2. error_log.txt → Check backend PHP error
3. Form Validation → Ensure all fields filled
4. Database Connection → Verify CYCLOAN_db.php credentials

**Solution Steps:**

```
1. Open Developer Tools (F12)
2. Go to Console tab
3. Look for error message
4. Take screenshot of error
5. Check error_log.txt in project root
6. Compare with CREDIT_INVESTIGATION_FIX_SUMMARY.md
```

### "Tracking Prevention blocked access to storage"

**This is Normal!** Firefox privacy feature

- Doesn't affect form submission
- Forms still work correctly
- No action needed

### "Invalid JSON response"

**Cause:** PHP error before JSON sent
**Fix:**

```
1. Check error_log.txt for PHP errors
2. Look for "Call to a member function bind_param() on bool"
3. If found: Parameter binding issue (should be fixed)
4. Verify remarks table structure:
   SELECT * FROM INFORMATION_SCHEMA.COLUMNS
   WHERE TABLE_NAME='remarks'
```

### "Database prepare error"

**Cause:** SQL statement syntax error
**Debug:**

```sql
-- Verify loan_applications table exists
SHOW COLUMNS FROM loan_applications;

-- Verify remarks table exists
SHOW COLUMNS FROM remarks;

-- Check if columns match:
-- remarks table should have: remark_id, application_id, remarks, admin_name, created_at
```

---

## ✅ Verification Checklist

### Before Testing

- [ ] Email credentials updated to cycloancldd@gmail.com
- [ ] Email password is: hbfh ukgh tmzw nqbq
- [ ] Database connection working
- [ ] error_log.txt is writable
- [ ] Browser JavaScript console visible (F12)

### During Form Submission

- [ ] All required fields filled (Status, Amount, Term)
- [ ] Amount is within min/max range
- [ ] No JavaScript errors in console
- [ ] Submit button shows spinner
- [ ] Form stays enabled (not stuck on loading)

### After Submission

- [ ] Success message appears
- [ ] Modal closes automatically after 2 seconds
- [ ] Loan applicants table refreshes
- [ ] Check error_log.txt shows "SUCCESS"
- [ ] Check database for updated values:
  ```sql
  SELECT application_id, credit_investigation_status,
         final_loan_amount, term_length, updated_at
  FROM loan_applications
  WHERE application_id = [APP_ID]
  LIMIT 1;
  ```

### Email Verification

- [ ] Applicant receives email notification
- [ ] Email has correct contact info:
  - Phone: 0981-303-8698
  - Landline: 545-6789 loc 8018-19
  - Address: Lower Ground Floor (LG)24 New City Hall Bldg...
- [ ] If Status='Completed' → Green email template
- [ ] If Status='Failed' → Red/Orange email template

### Database Verification

```sql
-- Check remarks inserted
SELECT * FROM remarks
WHERE application_id = [APP_ID]
ORDER BY created_at DESC;

-- Check activity log created
SELECT * FROM activity_logs
WHERE affected_id = [APP_ID]
AND action_type = 'update'
ORDER BY created_at DESC;

-- Check loan_applications updated
SELECT credit_investigation_status, final_loan_amount,
       term_length, updated_at
FROM loan_applications
WHERE application_id = [APP_ID];
```

---

## 🧪 Step-by-Step Test

### Test 1: Form Validation

```
1. Open admin dashboard
2. Click on any loan applicant → Opens modal
3. Scroll to "Credit Investigation" section
4. Try submitting WITHOUT filling fields
5. Should see error messages for each missing field
6. Fill in fields and try again
7. Expected: Form submits successfully

✓ PASS if: All validation errors show correctly
```

### Test 2: Amount Range Validation

```
1. Open credit investigation form
2. Note the loan type (Individual or Cooperative)
3. Enter amount BELOW minimum
   - Individual: less than 10,000
   - Cooperative: less than 300,000
4. Submit form
5. Expected: Error message about range

✓ PASS if: Range validation error appears
```

### Test 3: Successful Submission (Completed)

```
1. Open credit investigation form
2. Select Status: "Completed"
3. Enter Amount: 50,000
4. Select Term: 12 months
5. Enter Remarks: "Applicant passed all checks"
6. Click "Submit Investigation"
7. Expected:
   - Spinner appears
   - "Success" message after 1-2 seconds
   - Modal closes automatically
   - Table refreshes

✓ PASS if: All above happen without errors
```

### Test 4: Email Delivery (Completed)

```
1. Submit form with Status='Completed'
2. Check applicant's email (usually takes 5-10 seconds)
3. Expected email should have:
   ✓ Subject: "🎉 Loan Application Approved!"
   ✓ Green color scheme in email
   ✓ Says "LOAN APPROVED"
   ✓ Shows final amount: ₱50,000
   ✓ Shows term: 12 months
   ✓ Contains correct contact info
   ✓ Contains office hours

✓ PASS if: Email received with all correct info
```

### Test 5: Failed Status Submission

```
1. Open credit investigation form
2. Select Status: "Failed"
3. Enter Amount: 40,000
4. Select Term: 18 months
5. Enter Remarks: "Credit score below threshold"
6. Click "Submit Investigation"
7. Expected:
   - Success message appears
   - Applicant receives email with red/orange theme
   - Email says "Additional Information Required"

✓ PASS if: All above completed
```

### Test 6: Database Integrity

```
1. After successful submission, run:

SELECT la.application_id, la.credit_investigation_status,
       la.final_loan_amount, la.term_length,
       r.remarks, r.admin_name, r.created_at
FROM loan_applications la
LEFT JOIN remarks r ON la.application_id = r.application_id
WHERE la.application_id = [SUBMITTED_APP_ID]
ORDER BY r.created_at DESC
LIMIT 1;

Expected output:
┌─────────────┬─────────────────────┬──────────────────┬─────────┬──────────────────────┬────────────────┬───────────────────────┐
│ app_id      │ status              │ final_amount     │ term    │ remarks              │ admin_name     │ created_at            │
├─────────────┼─────────────────────┼──────────────────┼─────────┼──────────────────────┼────────────────┼───────────────────────┤
│ 1001        │ Completed           │ 50000.00         │ 12      │ Applicant passed...  │ Admin Name     │ 2025-11-19 14:45:23  │
└─────────────┴─────────────────────┴──────────────────┴─────────┴──────────────────────┴────────────────┴───────────────────────┘

✓ PASS if: All values present and correct
```

---

## 🐛 Debug Commands

### Check recent errors

```bash
# Last 20 lines of error log
tail -20 debug_log.txt

# All credit investigation errors
grep "Credit Investigation" debug_log.txt | tail -20

# Last submission attempt
grep "CREDIT INVESTIGATION SUBMISSION" debug_log.txt | tail -5

# All bind_param errors (if any)
grep "bind_param" debug_log.txt

# All remarks insertions
grep "Remarks" debug_log.txt | tail -10
```

### Quick SQL checks

```sql
-- All tables exist
SHOW TABLES LIKE 'loan_applications';
SHOW TABLES LIKE 'remarks';
SHOW TABLES LIKE 'activity_logs';

-- Table structures
DESCRIBE loan_applications;
DESCRIBE remarks;
DESCRIBE activity_logs;

-- Data verification
SELECT COUNT(*) as recent_investigations
FROM loan_applications
WHERE credit_investigation_status IN ('Completed', 'Failed', 'Pending');

-- Check activity logs
SELECT COUNT(*) as updates_today
FROM activity_logs
WHERE DATE(created_at) = CURDATE()
AND action_type = 'update';
```

---

## 📋 Browser Developer Tools Tips

### Console Tab (F12 → Console)

```javascript
// Clear console
clear();

// Check form data
document.getElementById("creditInvestigationForm").elements;

// Test field values
document.getElementById("ciApplicationId").value;
document.getElementById("ciStatus").value;
document.getElementById("ciFinalAmount").value;
document.getElementById("ciTermLength").value;

// Check if form exists
document.getElementById("creditInvestigationForm") !== null;

// Check modal visibility
document.getElementById("loanDetailsModal").classList.contains("show");
```

### Network Tab (F12 → Network)

```
1. Open Network tab before submitting form
2. Submit form
3. Look for POST request to "admin1_dashboard.php"
4. Click on it
5. Check:
   - Status: Should be 200
   - Response: Should be valid JSON
   - Headers: Content-Type should be application/json
6. If status is not 200 or response is HTML → PHP error occurred
```

### Elements/Inspector Tab (F12 → Inspector)

```
1. Find credit investigation form: <form id="creditInvestigationForm">
2. Check visible elements:
   - ciStatus (should be visible SELECT)
   - ciFinalAmount (should be visible INPUT)
   - ciTermLength (should be visible SELECT)
   - ciRemarks (should be visible TEXTAREA)
3. If elements not found → Form not rendered in modal

4. Check error messages:
   - ciStatusValidation (should show/hide based on validation)
   - amountValidation (should show/hide based on validation)
   - ciMessageBox (should show success/error messages)
```

---

## 📞 Contact & References

### Database Tables

- **loan_applications**: Stores credit_investigation_status, final_loan_amount, term_length
- **remarks**: Stores remarks, admin_name, created_at, application_id
- **activity_logs**: Tracks all updates with timestamps
- **users1**: Contains applicant contact info (email, phone)
- **interest_rates**: Contains interest rates by term_length

### Related Functions

- `logActivity()` - Line 149: Logs to activity_logs table
- `createStatusNotification()` - Creates notification for applicant
- `sendEmail()` - Not used in credit investigation (using PHPMailer directly)
- `initializeCreditInvestigationForm()` - Line 7179: Frontend initialization

### Email Configuration

```
SMTP: smtp.gmail.com:587
User: cycloancldd@gmail.com
Pass: hbfh ukgh tmzw nqbq
From: CYCLOAN Loan Support
```

### File Locations

```
Main Logic:     admin1_dashboard.php (Lines 2124-2478)
Frontend Form:  admin1_dashboard.php (Lines 6902-7050)
Validation:     admin1_dashboard.php (Lines 7179-7512)
Database Init:  verify_credit_investigation_db.php
Schema:         CREDIT_INVESTIGATION_SCHEMA_FIX.sql
```

---

## 🎯 Success Criteria

- ✅ Form submits without JavaScript errors
- ✅ Applicant receives email notification within 10 seconds
- ✅ Database shows updated credit_investigation_status
- ✅ Database shows updated final_loan_amount
- ✅ Database shows updated term_length
- ✅ Remarks inserted with admin_name and timestamp
- ✅ Activity log shows credit investigation entry
- ✅ Modal closes and table refreshes
- ✅ error_log.txt shows "SUCCESS" message

---

**Version:** 1.0
**Last Updated:** November 19, 2025
**Status:** READY FOR TESTING
