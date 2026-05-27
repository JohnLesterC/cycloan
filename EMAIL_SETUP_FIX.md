# Email Not Sending - Solution Guide

## Problem Identified
The decision reasoning form shows "Email sent to applicant" but **no email actually reaches the user**. The issue is **invalid or expired Gmail credentials** in the email configuration.

## Root Cause
The Gmail app password in `admin2_dashboard.php` (line 547) is either:
- **Expired** - Gmail app passwords can expire
- **Invalidated** - Account security changes can invalidate them
- **Wrong** - The credential may have been changed but not updated here

Current credentials location:
```php
// Line 521-550 in admin2_dashboard.php
$mail->Username = 'cycloancldd@gmail.com';
$mail->Password = 'hbfh ukgh tmzw nqbq'; // ← This is likely expired/invalid
```

## Fix: Update Gmail App Password

### Option 1: Generate New Gmail App Password (Recommended)

1. **Go to your Google Account**:
   - Visit https://myaccount.google.com
   - Sign in to cycloancldd@gmail.com

2. **Enable 2-Factor Authentication (if not already enabled)**:
   - Click "Security" in the left sidebar
   - Find "2-Step Verification"
   - Follow the prompts to enable it

3. **Generate App Password**:
   - After 2FA is enabled, go back to Security
   - Find "App passwords" (appears only after 2FA is enabled)
   - Select "Mail" and "Windows Computer" (or your device)
   - Google will generate a 16-character password
   - Copy this password

4. **Update admin2_dashboard.php**:
   - Replace line 547 with your new app password:
   ```php
   $mail->Password = 'your-new-16-char-password-here';
   ```

### Option 2: Use Regular Gmail Password (Less Secure)

If you don't want to use app passwords:

1. Go to https://myaccount.google.com/security
2. Find "Less secure app access" - ENABLE IT
3. Update the password in admin2_dashboard.php:
   ```php
   $mail->Password = 'your-gmail-password-here';
   ```

### Option 3: Use Gmail OAuth2 (Most Secure - Advanced)

This requires more setup but is the most secure method.

## Test the Fix

After updating credentials, test by:

1. **Submitting a decision** on any loan application
2. **Check if email arrives** to the applicant
3. **Check server error logs** for any remaining SMTP issues

### Debug Email Sending

Add this test to verify emails are working:

1. Create a test PHP file to send a test email:

```php
<?php
require_once 'admin2_dashboard.php';

// Test email
$testResult = sendEmail(
    'your-test-email@gmail.com',
    'Test User',
    'CYCLOAN Email Test',
    '<h2>If you see this, emails are working!</h2>',
    'TEST_EMAIL'
);

echo $testResult ? "✅ Email sent successfully" : "❌ Email send failed";
?>
```

2. Check PHP error log for SMTP debug messages

## What Each Part Does

| Component | Purpose |
|-----------|---------|
| `$mail->isSMTP()` | Use SMTP protocol (not sendmail) |
| `$mail->Host = 'smtp.gmail.com'` | Gmail's mail server |
| `$mail->Port = 587` | TLS port for secure connection |
| `$mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS` | Enable TLS encryption |
| `$mail->Username` | Gmail account email |
| `$mail->Password` | App password or regular password |

## Important Notes

⚠️ **DO NOT commit credentials to version control!**

Once you fix this, consider moving credentials to environment variables:

```php
$mail->Username = getenv('GMAIL_USERNAME') ?: 'cycloancldd@gmail.com';
$mail->Password = getenv('GMAIL_PASSWORD') ?: 'fallback-password';
```

## Files Affected

- `admin2_dashboard.php` (Line 547) - Password location
- `CYCLOAN_db.php` - Connection config (verify database is accessible)

## Troubleshooting Checklist

- [ ] Credentials are up-to-date
- [ ] 2-Factor Authentication is enabled on Gmail account
- [ ] App password is generated (not regular password) if using 2FA
- [ ] Port 587 is not blocked by firewall
- [ ] Applicant email address exists in database
- [ ] Error logs show no SMTP connection errors
- [ ] Test email sends successfully

## Common Error Messages

| Error | Solution |
|-------|----------|
| "SMTP connect() failed" | Port 587 blocked, use port 465 instead |
| "535 5.7.8 Username and Password not accepted" | Credentials are wrong |
| "Invalid email address" | Applicant has no email in database |
| "Stream initialization" | SSL/TLS error, check encryption settings |

## Next Steps After Fix

1. Update the password in `admin2_dashboard.php`
2. Submit a test decision on a loan application
3. Verify email arrives to applicant
4. Check error logs for any remaining issues
5. Consider moving credentials to environment variables for security

