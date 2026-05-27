# Email Fix - Exact Code Changes Required

## Issue Summary
Decision Reasoning form shows "Email sent to applicant" but no email actually arrives. 

## Root Cause
Gmail app password is invalid/expired at line 545 and line 2400

## Solution
Update Gmail credentials in TWO locations

---

## Change #1: Line 545 (sendEmail function)

### Current Code:
```php
function sendEmail($to, $toName, $subject, $body, $context = '')
{
    try {
        // Validate recipient email
        if (empty($to) || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
            error_log("EMAIL_VALIDATION_FAILED: Invalid recipient email '$to' | Context: $context", E_USER_WARNING);
            return false;
        }

        error_log("EMAIL_INIT: Starting email send to $to | Subject: $subject | Context: $context", E_USER_NOTICE);

        $mail = new PHPMailer(true);

        // Enable SMTP debug for troubleshooting (Set to 2 for detailed logging)
        // 0 = off, 1 = client messages, 2 = client and server messages, 3 = verbose
        $mail->SMTPDebug = 2; // Set to 2 to capture SMTP conversation in logs

        // Server settings
        error_log("EMAIL_SMTP_INIT: Initializing SMTP connection to smtp.gmail.com:587", E_USER_NOTICE);

        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com';
        $mail->SMTPAuth = true;
        $mail->Username = 'cycloancldd@gmail.com';
        $mail->Password = 'hbfh ukgh tmzw nqbq'; // ← CHANGE THIS LINE
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = 587;
        $mail->Timeout = 30;
        $mail->SMTPKeepAlive = true;
```

### How to Fix:

1. **Open**: `admin2_dashboard.php`
2. **Find line**: 545 (use Ctrl+G to go to line)
3. **Look for**: `$mail->Password = 'hbfh ukgh tmzw nqbq';`
4. **Replace with**: `$mail->Password = 'YOUR_NEW_APP_PASSWORD_HERE';`

### Example After Fix:
```php
        $mail->Username = 'cycloancldd@gmail.com';
        $mail->Password = 'abcd efgh ijkl mnop'; // Replace with new password from Gmail
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
```

---

## Change #2: Line 2400 (Payment Reminder function)

### Current Code:
```php
        // Create fresh PHPMailer instance for this reminder
        $reminderMail = new PHPMailer(true);
        $reminderMail->isSMTP();
        $reminderMail->Host = 'smtp.gmail.com';
        $reminderMail->SMTPAuth = true;
        $reminderMail->Username = 'cycloancldd@gmail.com';
        $reminderMail->Password = 'hbfh ukgh tmzw nqbq'; // ← CHANGE THIS LINE
        $reminderMail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $reminderMail->Port = 587;
```

### How to Fix:

1. **Open**: `admin2_dashboard.php`
2. **Find line**: 2400 (use Ctrl+G to go to line)
3. **Look for**: `$reminderMail->Password = 'hbfh ukgh tmzw nqbq';`
4. **Replace with**: `$reminderMail->Password = 'YOUR_NEW_APP_PASSWORD_HERE';` (same password as line 545)

### Example After Fix:
```php
        $reminderMail->Username = 'cycloancldd@gmail.com';
        $reminderMail->Password = 'abcd efgh ijkl mnop'; // Same password as line 545
        $reminderMail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
```

---

## Where to Get New Password

1. **Go to**: https://myaccount.google.com/security
2. **Log in**: cycloancldd@gmail.com
3. **Find**: "App passwords" (in Security section)
4. **Generate**: New password for "Mail" on "Windows Computer"
5. **Copy**: The 16-character password (format: `xxxx xxxx xxxx xxxx`)
6. **Use**: This password in both locations above

---

## Testing After Fix

### Quick Test:
1. Click on any loan application in dashboard
2. Click "Decision Reasoning" button
3. Select status: "Approved"
4. Enter reason/remark in textarea
5. Click "Submit Decision"
6. Check applicant's email (should arrive within 1 minute)

### Manual Test (If needed):
Create file `test_email.php`:
```php
<?php
require_once 'admin2_dashboard.php';

$result = sendEmail(
    'your-test-email@gmail.com',
    'Test User',
    'Email Test from CYCLOAN',
    '<h2>If you see this, emails are working!</h2>',
    'TEST'
);

echo $result ? "✅ Email sent successfully" : "❌ Email send failed";
echo "\nCheck error logs for SMTP debug messages.";
?>
```

Run: `php test_email.php`

---

## Summary of Changes

| Line | File | Current Value | New Value |
|------|------|---|---|
| 545 | admin2_dashboard.php | `hbfh ukgh tmzw nqbq` | `<new app password>` |
| 2400 | admin2_dashboard.php | `hbfh ukgh tmzw nqbq` | `<same new password>` |

---

## Verification

After making changes:

- [ ] Updated line 545
- [ ] Updated line 2400
- [ ] Used same password in both locations
- [ ] Saved file
- [ ] Tested decision submission
- [ ] Email received by applicant
- [ ] No SMTP errors in logs

---

## Common Password Formats

### Gmail App Password (Recommended):
```
Format: xxxx xxxx xxxx xxxx (16 characters with spaces)
Example: abcd efgh ijkl mnop
```

### Regular Gmail Password (Less Secure):
```
Format: your-password-here (any length, no spaces)
Example: MySecurePassword123
Note: Only works if "Less secure apps" is enabled
```

---

## If Email Still Fails

1. **Verify new password is correct** (copy from Gmail account again)
2. **Wait 2-3 minutes** (sometimes credentials take time to activate)
3. **Check firewall** (port 587 must be open)
4. **Check server error logs** for SMTP errors
5. **Try with regular password** to isolate the issue
6. **Check applicant's email exists** in database

---

## Security Best Practice

After testing, consider moving password to environment variable:

```php
// INSTEAD OF:
$mail->Password = 'hbfh ukgh tmzw nqbq';

// USE:
$mail->Password = getenv('GMAIL_APP_PASSWORD') ?: 'default-app-password';
```

Then set environment variable on server:
```bash
export GMAIL_APP_PASSWORD="your-app-password"
```

This prevents hardcoding secrets in source code.

