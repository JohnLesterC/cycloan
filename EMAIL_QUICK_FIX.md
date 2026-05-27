# Email Sending Function - Quick Fix Reference

## Problem: Users Not Receiving Rejection Emails

## Immediate Action Items

### 1. **Check Error Logs FIRST**
```bash
# Look for these patterns in your PHP error log:
grep -i "email\|consolidated" /var/log/php/error.log | tail -30
```

### 2. **Common Error Messages & Quick Fixes**

| Error | Cause | Fix |
|-------|-------|-----|
| `SMTP connect() failed` | Network/firewall issue | Check port 587 open, firewall rules |
| `Could not authenticate` | Wrong Gmail password | Use Gmail App Password, not regular password |
| `EMAIL_VALIDATION_FAILED` | Empty email in database | Check `users1` table, verify email exists |
| `Connection timed out` | Server unreachable | Gmail SMTP down or network issue |
| `No mechanism available` | TLS configuration | Verify STARTTLS on port 587 |

### 3. **Email Configuration Verification**

The email function uses these settings. Verify they're correct:

```
Gmail Account: scycloan@gmail.com
App Password: xbvo zplr dpme ixxj  (must be App Password with 2FA enabled)
SMTP Server: smtp.gmail.com
Port: 587
Encryption: STARTTLS (TLS not SSL)
```

### 4. **Files Modified**
- `admin2_dashboard.php` - sendEmail() and sendConsolidatedUpdateEmail() functions
- Enhanced with detailed logging (EMAIL_, CONSOLIDATED_EMAIL_, DOCUMENT_UPDATE_ prefixes)

### 5. **Enable Debug Mode (Temporary)**

Edit `admin2_dashboard.php` around line 530:

Change FROM:
```php
$mail->SMTPDebug = 0;
```

Change TO:
```php
$mail->SMTPDebug = SMTP::DEBUG_SERVER;
```

Then check error logs for SMTP conversation. **Remember to set back to 0 when done!**

### 6. **Test Email Sending**

Create `test_email.php` in your project root:

```php
<?php
require_once 'CYCLOAN_db.php';
require_once 'admin2_dashboard.php';

// Test parameters
$test_app_id = 12345; // Use a real application ID
$test_email = "test@example.com"; // Use a real email

// Manually call sendEmail
$result = sendEmail($test_email, "Test User", "Test Subject", "<p>Test email body</p>", "Manual test");

echo "Test result: " . ($result ? "SUCCESS" : "FAILED") . "\n";
echo "Check error logs for details\n";
?>
```

Run: `php test_email.php`

### 7. **Log Messages to Expect**

**Success Sequence:**
```
DOCUMENT_UPDATE: Attempting to send email...
CONSOLIDATED_EMAIL_START: Processing email...
EMAIL_INIT: Starting email send...
EMAIL_RECIPIENT: Set recipient...
EMAIL_SUCCESS: Email sent successfully...
```

**Failure Points:**
- `CONSOLIDATED_EMAIL_ERROR` = Application not found
- `CONSOLIDATED_EMAIL_VALIDATION_ERROR` = Invalid email
- `EMAIL_VALIDATION_FAILED` = Email format invalid
- `EMAIL_SEND_FAILED` = SMTP/send error
- `EMAIL_EXCEPTION` = PHPMailer exception

### 8. **Database Verification**

Check that user data exists:

```sql
-- Verify application and user exist
SELECT la.application_id, la.user_id, u.email, u.first_name, u.last_name
FROM loan_applications la
JOIN users1 u ON la.user_id = u.id
WHERE la.application_id = 12345;
```

If no results = application doesn't exist
If email is NULL = email not stored

### 9. **Gmail Account Settings**

1. **2-Factor Authentication Required** - Must be enabled
2. **App Password** - Use this, not your regular Gmail password
3. **Less Secure Apps** - Configure in Gmail settings if needed

Get correct App Password:
1. Go to: myaccount.google.com/apppasswords
2. Select Mail + Windows PC (or your platform)
3. Generate password
4. Copy and update in admin2_dashboard.php

### 10. **Network Connectivity Test**

Check if server can reach Gmail SMTP:

```bash
# Telnet test
telnet smtp.gmail.com 587

# Or using PHP (add to a test file):
$socket = @fsockopen('smtp.gmail.com', 587, $errno, $errstr, 5);
if ($socket) {
    echo "✓ Can connect to SMTP\n";
    fclose($socket);
} else {
    echo "✗ Cannot connect: $errstr\n";
}
```

## What Was Fixed

1. ✅ **Better Error Logging** - Now shows actual PHPMailer errors
2. ✅ **Step-by-Step Logging** - Trace exactly where email fails
3. ✅ **Email Validation** - Checks email format before sending
4. ✅ **Status Tracking** - Know if send succeeded or failed
5. ✅ **Debug Mode** - Can enable verbose SMTP logging
6. ✅ **Context Information** - Logs include app ID, recipient, subject

## Testing Workflow

1. **Reject a document** in the admin panel
2. **Check error logs** for EMAIL_SUCCESS or EMAIL_SEND_FAILED
3. **If failed**, identify the error from log messages
4. **Apply fix** from table above
5. **Test again** and verify "EMAIL_SUCCESS" in logs

## Need Help?

Provide these details:
1. **Exact error message** from error logs
2. **Email address** that didn't receive email
3. **Application ID** that was being processed
4. **When it happened** (exact time)
5. **What action** triggered it (reject, approve, etc.)

---

**Remember:** Check error logs first - they tell you exactly what's wrong!
