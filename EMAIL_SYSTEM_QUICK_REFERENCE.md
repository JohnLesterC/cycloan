# CYCLOAN Email System - Quick Reference

## 📧 System Overview

```
┌─────────────────────────────────────────────────────────────┐
│                   CYCLOAN Email Flow                        │
├─────────────────────────────────────────────────────────────┤
│                                                             │
│  User Action → PHP Handler → Email Template → Gmail SMTP   │
│                                                             │
│  ✓ Verified & Working (Last: Nov 13, 2:36 PM)             │
│                                                             │
└─────────────────────────────────────────────────────────────┘
```

---

## ⚙️ Configuration

| Setting                  | Value                 |
| ------------------------ | --------------------- |
| **SMTP Server**          | smtp.gmail.com        |
| **SMTP Port**            | 587                   |
| **Security Protocol**    | STARTTLS (TLS)        |
| **From Email**           | cycloancldd@gmail.com |
| **From Name**            | CYCLOAN Loan Support  |
| **Authentication**       | OAuth2 / App Password |
| **Current App Password** | `hbfh ukgh tmzw nqbq` |

---

## 📨 Email Types & Triggers

### 1️⃣ **OTP Verification Emails**

- **When:** User registers for CYCLOAN account
- **Handler:** `process_registration.php`
- **Template:** HTML with OTP code
- **Recipient:** Email provided during registration
- **Status:** ✅ Working

### 2️⃣ **Credit Investigation Status**

- **When:** Admin updates credit investigation status
- **Handler:** `admin1_dashboard.php` (lines 2350-2420)
- **Template:** Status update with loan amount
- **Recipient:** Applicant email
- **Last Sent:** Nov 13, 2:36 PM ✅
- **Status:** ✅ Working

### 3️⃣ **Password Reset**

- **When:** User requests password reset
- **Handler:** `admin1_dashboard.php` (lines 1070-1160)
- **Template:** Reset link with token
- **Recipient:** User email
- **Status:** ✅ Configured

### 4️⃣ **Loan Status Updates**

- **When:** Admin updates loan status
- **Handler:** `admin1_dashboard.php` (lines 1520-1610)
- **Template:** Status change notification
- **Recipient:** Applicant email
- **Status:** ✅ Configured

---

## 📁 Code Locations

### Email Configuration

```
admin1_dashboard.php
├── Line 1102:  $mail->Password (Password Reset)
├── Line 1557:  $mail->Password (Loan Status)
├── Line 2377:  $mailer->Password (Credit Investigation - Setup)
└── Line 2490:  $mailer->Password (Credit Investigation - Send)
```

### Email Functions

```
admin1_dashboard.php
├── Line 600-700:   password reset email setup
├── Line 1070-1160: sendPasswordResetEmail()
├── Line 1520-1610: sendLoanStatusEmail()
└── Line 2350-2420: Credit investigation email send
```

### OTP Email

```
process_registration.php
├── Line 400-500: sendOTPEmail() function
└── Uses PHPMailer with same credentials
```

---

## 🧪 Testing Email

### Quick Test:

```bash
# In browser
http://yoursite.com/email_debug_test.php
```

### What it checks:

✓ Gmail credentials are valid  
✓ SMTP connection successful  
✓ TLS encryption working  
✓ Can send test email

### View Email Logs:

```bash
# Terminal
tail -f debug_log.txt
```

### Search for email confirmations:

```bash
grep "email sent to" debug_log.txt
```

---

## 🔄 Email Sending Flow

```php
// Step 1: Create PHPMailer instance
$mailer = new PHPMailer(true);

// Step 2: Configure SMTP
$mailer->isSMTP();
$mailer->Host = 'smtp.gmail.com';
$mailer->SMTPAuth = true;
$mailer->Username = 'cycloancldd@gmail.com';
$mailer->Password = 'hbfh ukgh tmzw nqbq';
$mailer->SMTPSecure = 'tls';
$mailer->Port = 587;

// Step 3: Set email content
$mailer->setFrom('cycloancldd@gmail.com', 'CYCLOAN');
$mailer->addAddress($recipient_email);
$mailer->Subject = 'Your Subject';
$mailer->Body = '<html>Email content</html>';

// Step 4: Send
$mailer->send();

// Step 5: Log result
error_log("Email sent to $recipient_email");
```

---

## ✅ Email Verification Checklist

Before deploying or troubleshooting:

- [ ] App password has been generated (https://myaccount.google.com/apppasswords)
- [ ] All 4 locations in admin1_dashboard.php updated
- [ ] 2FA enabled on cycloancldd@gmail.com
- [ ] Email debug test passes (email_debug_test.php)
- [ ] Test email received successfully
- [ ] debug_log.txt shows "email sent" confirmation
- [ ] Recipients can receive emails (check spam folder)

---

## 🚨 Common Issues & Solutions

### Issue: "535 5.7.8 Username and Password not accepted"

**Cause:** App password expired or wrong  
**Fix:** Regenerate new app password from Google, update all 4 locations

### Issue: "SMTP connection timeout"

**Cause:** Firewall/network issue or account locked  
**Fix:** Check port 587 is open, verify Google account security

### Issue: "Emails sent but not received"

**Cause:** Spam filter or wrong recipient address  
**Fix:** Check debug_log.txt, ask recipient to check spam folder

### Issue: "Too many login attempts"

**Cause:** Too many failed authentication attempts  
**Fix:** Wait 24 hours, then regenerate app password

---

## 📊 Email Statistics

| Metric              | Status                    |
| ------------------- | ------------------------- |
| **Last Email Sent** | Nov 13, 2:36 PM ✅        |
| **Email Type**      | Credit Investigation      |
| **Recipient**       | johnlestercamit@gmail.com |
| **Days Since Last** | 2 days                    |
| **System Status**   | Operational ✅            |

---

## 🔐 Security Notes

✅ **What's Protected:**

- App password is separate from main Google password
- Only used for CYCLOAN email sending
- Can be revoked independently
- Requires 2FA on main account

⚠️ **What Needs Protection:**

- App password should not be committed to Git
- Should use .env variables in production
- Regenerate if accidentally exposed
- Rotate every 90 days (recommended)

---

## 📱 Gmail Settings Required

For this to work, the cycloancldd@gmail.com account must have:

1. **✅ Two-Factor Authentication** - REQUIRED

   - Go to: https://myaccount.google.com/security
   - Enable 2-Step Verification
   - Use backup codes if phone unavailable

2. **✅ Less Secure Apps** - DISABLED (OK with 2FA + App Passwords)

   - Uses "App Passwords" feature instead (more secure)

3. **✅ Account Recovery** - Configured
   - Recovery email set
   - Recovery phone set
   - Needed for account recovery

---

## 🔄 Renewal Schedule

| Frequency               | Task                               |
| ----------------------- | ---------------------------------- |
| **Monthly**             | Test email sending via debug page  |
| **Quarterly (90 days)** | Regenerate app password            |
| **When**                | After main Google password change  |
| **When**                | If account shows security warnings |

---

## 📞 Support Resources

- **Gmail Support:** https://support.google.com/mail/
- **App Passwords:** https://myaccount.google.com/apppasswords
- **Account Security:** https://myaccount.google.com/security
- **PHPMailer Docs:** https://github.com/PHPMailer/PHPMailer

---

## 🎯 Action Items

- [ ] Set calendar reminder for app password renewal (90 days from now: Feb 13, 2026)
- [ ] Save EMAIL_APP_PASSWORD_RENEWAL_GUIDE.md in team wiki
- [ ] Document new app password in password manager
- [ ] Test email_debug_test.php quarterly
- [ ] Monitor debug_log.txt for email errors

---

**Last Updated:** November 13, 2025  
**Email Status:** ✅ OPERATIONAL  
**Verified By:** System Email Tests  
**Next Review:** February 13, 2026
