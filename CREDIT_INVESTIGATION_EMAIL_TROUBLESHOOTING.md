# 🔧 CREDIT INVESTIGATION EMAIL - TROUBLESHOOTING GUIDE

## Quick Diagnosis

### Problem: Email Not Sending

**Check these in order:**

#### 1. Check Admin1 Dashboard

```
✓ Login as Admin1
✓ Find a Loan Application
✓ Scroll to "Credit Investigation Status" section
✓ Make sure you fill in:
  - Credit Investigation Status (dropdown)
  - Final Loan Amount (number field)
  - Remarks (text area)
✓ Click "Submit" or "Update"
```

#### 2. Check Debug Log

```
File: debug_log.txt (root directory)

Look for one of these:
✓ SUCCESS:
  "✓ Credit investigation status email sent successfully to
   user@email.com (Application ID: APP-20251120-0001, Status: Completed)"

✗ ERROR:
  "✗ FAILED: Mailer Error for application_id APP-20251120-0001:
   [specific error]"

⚠ WARNING:
  "sendCreditStatusEmail: Missing required parameters"
  "sendCreditStatusEmail: No user found for application_id"
  "sendCreditStatusEmail: Prepare failed"
```

---

## Common Issues & Solutions

### Issue 1: "No user found for application_id"

**Cause:** Application doesn't exist or user deleted

**Solution:**

1. Verify application_id exists in database
2. Check that applicant account still exists
3. Try with a different application

---

### Issue 2: "Missing required parameters"

**Cause:** Credit Investigation Status not set

**Solution:**

1. Go back to application edit form
2. Make sure "Credit Investigation Status" dropdown has a value
3. Must be: Pending, Completed, or Failed
4. Don't leave it blank
5. Click Submit again

---

### Issue 3: "Prepare failed" or "Execute failed"

**Cause:** Database connection error

**Solution:**

1. Check database is running
2. Verify `CYCLOAN_db.php` credentials are correct
3. Check database connection in browser console
4. Restart PHP server

---

### Issue 4: SMTP/Mailer Error

**Cause:** Email configuration issue

**Solution:**

```
Check in admin1_dashboard.php (around line 1620):

$mail->Host = 'smtp.gmail.com';
$mail->Port = 587;
$mail->Username = 'cycloancldd@gmail.com';
$mail->Password = 'hbfh ukgh tmzw nqbq';

Verify:
✓ Host is correct (smtp.gmail.com)
✓ Port is 587 (not 465 or 25)
✓ App password is current (not account password)
✓ Gmail app passwords enabled
```

---

### Issue 5: Email Arrives But No Investigation Details

**Cause:** Remarks not fetched or formatted incorrectly

**Solution:**

1. Check that remarks were added to application
2. Verify remarks table has data:
   ```sql
   SELECT * FROM remarks WHERE application_id = 'APP-xxxxx' LIMIT 5;
   ```
3. Check admin_name field is not empty
4. Try adding a new remark and resending

---

## What Should Happen

### Step-by-Step Process

1. **Admin1 Updates Application**

   ```
   - Sets Credit Investigation Status: "Completed"
   - Sets Final Loan Amount: 45000
   - Adds Remarks: "Investigation passed. Ready for approval."
   - Clicks Submit
   ```

2. **System Processes**

   ```
   ✓ Updates database
   ✓ Fetches user info (email, name)
   ✓ Fetches all remarks (up to 5)
   ✓ Prepares email with investigation details
   ✓ Sends email via Gmail SMTP
   ✓ Logs success to debug_log.txt
   ```

3. **Applicant Receives Email**
   ```
   Within: 1-2 minutes
   Contains:
   - Subject: "🎉 CYCLOAN Loan Approved - Office Visit Required"
   - Application ID: #APP-20251120-0001
   - Investigation Status: Completed
   - Amount Requested: ₱50,000.00
   - Approved Amount: ₱45,000.00 ← GREEN highlighted
   - Loan Term: 12 months
   - Investigation Notes: [Your remarks here]
   - Next Steps: Visit office section
   - Contact info
   - Login button
   ```

---

## Database Schema Quick Reference

```sql
-- Check if application exists
SELECT * FROM loan_applications
WHERE application_id = 'APP-20251120-0001';

-- Check if user exists
SELECT id, email, first_name, last_name FROM users1
WHERE id = [user_id];

-- Check remarks exist
SELECT * FROM remarks
WHERE application_id = 'APP-20251120-0001'
ORDER BY created_at DESC LIMIT 5;

-- Check if term_length is set
SELECT application_id, term_length, amount_applied
FROM loan_applications
WHERE application_id = 'APP-20251120-0001';
```

---

## Email Testing Steps

### Test 1: Verify Email Sending

1. Create test application (or use existing)
2. Go to Admin1 Dashboard
3. Update Credit Investigation Status: **Completed**
4. Enter Final Loan Amount: **50000**
5. Add Remarks: **Test investigation - all documents verified**
6. Click Submit
7. Check applicant inbox for email
8. Check debug_log.txt for ✓ success message

### Test 2: Verify Email Content

1. Open received email
2. Verify it contains:
   - [ ] Application ID
   - [ ] "Investigation Status: Completed"
   - [ ] "Amount Requested: ₱50,000.00"
   - [ ] "Approved Amount: ₱50,000.00" (in green)
   - [ ] "Loan Term: 12 months"
   - [ ] "Test investigation - all documents verified"
   - [ ] Admin name below remarks
   - [ ] "Next Steps Required" section
   - [ ] Office address and phone
   - [ ] Login button

### Test 3: Test Failed Status

1. Use a different application
2. Update Credit Investigation Status: **Failed**
3. Add Remarks: **Unable to verify income documents**
4. Click Submit
5. Check email has:
   - [ ] Status shows: Failed
   - [ ] ❌ icon
   - [ ] "Contact Support" section
   - [ ] Your remarks displayed

### Test 4: Test Pending Status

1. Use a different application
2. Update Credit Investigation Status: **Pending**
3. Click Submit
4. Check email has:
   - [ ] Status shows: Pending
   - [ ] ⏳ icon
   - [ ] "Please Wait" section
   - [ ] No Approved Amount shown

---

## Debug Log Examples

### ✓ Successful Email

```
[2025-11-20 14:35:12] ✓ Credit investigation status email sent successfully
to johnlestercamit@gmail.com (Application ID: APP-20251120-0001,
Status: Completed)
```

### ✗ Missing Parameters

```
[2025-11-20 14:35:12] sendCreditStatusEmail: Missing required parameters -
applicationId: , newStatus:
[2025-11-20 14:35:12] Failed to send credit investigation status email
for application_id:
```

### ✗ User Not Found

```
[2025-11-20 14:35:12] sendCreditStatusEmail: No user found for
application_id: APP-20251120-0001
```

### ✗ Database Error

```
[2025-11-20 14:35:12] sendCreditStatusEmail: Prepare failed for user fetch
- Error: Access denied for user 'root'@'localhost' (using password: YES)
```

### ✗ SMTP Error

```
[2025-11-20 14:35:12] ✗ FAILED: Mailer Error for application_id
APP-20251120-0001: SMTP connect() failed.
https://github.com/PHPMailer/PHPMailer/wiki/Troubleshooting
```

---

## Files to Check

| File                     | Purpose             | Check                    |
| ------------------------ | ------------------- | ------------------------ |
| `admin1_dashboard.php`   | Email function      | Lines 1489-1744          |
| `debug_log.txt`          | Error logs          | Search for "✓" or "✗"    |
| `CYCLOAN_db.php`         | Database connection | Verify credentials       |
| Database `remarks` table | Investigation notes | Query to verify data     |
| Gmail account            | SMTP credentials    | Check app password valid |

---

## Quick Fix Checklist

- [ ] Application exists in database
- [ ] User/Applicant account exists
- [ ] Credit Investigation Status is set (not blank)
- [ ] Final Loan Amount is entered
- [ ] Remarks/Notes are added
- [ ] Clicked Submit button
- [ ] Wait 1-2 minutes for email
- [ ] Check applicant's email inbox AND spam folder
- [ ] Check debug_log.txt for messages
- [ ] Database connection working
- [ ] Gmail SMTP credentials correct and current

---

## When All Else Fails

1. **Restart PHP Server**

   ```powershell
   # Stop current server (Ctrl+C)
   # Then restart:
   php -S 0.0.0.0:8000 -t .
   ```

2. **Check CYCLOAN_db.php**

   ```php
   // Verify connection exists
   if ($conn->connect_error) {
       die("Connection failed: " . $conn->connect_error);
   }
   ```

3. **Test Email Separately**

   ```
   Create test file: email_test.php
   Send test email to verify SMTP works
   Check if it arrives
   ```

4. **Check Recent Changes**
   ```
   Review admin1_dashboard.php for any conflicts
   Verify sendCreditStatusEmail() function intact
   Check database schema intact
   ```

---

**Last Updated:** November 20, 2025  
**Status:** ✅ All Fixes Applied  
**Ready for:** Testing & Deployment
