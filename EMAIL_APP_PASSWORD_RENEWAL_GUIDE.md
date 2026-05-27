# Gmail App Password Renewal Guide for CYCLOAN

## 🔍 Current Status

✅ **Email System is WORKING!**  
The last successful email was sent on **November 13, 2025 at 2:36:57 PM** (Manila time).

- **Email sent to:** johnlestercamit@gmail.com
- **Status:** Credit investigation completion notification
- **App Password:** hbfh ukgh tmzw nqbq (Working as of Nov 13)

---

## 📧 Gmail Account Details

- **Email:** cycloancldd@gmail.com
- **SMTP Server:** smtp.gmail.com
- **SMTP Port:** 587
- **Security:** TLS (Transport Layer Security)
- **Current App Password:** `hbfh ukgh tmzw nqbq`

---

## ⚠️ When to Renew the App Password

Gmail app passwords may need renewal if:

1. **Authentication failures** - "535 5.7.8 Username and Password not accepted"
2. **Connection timeouts** - SMTP connection hangs or fails
3. **After inactivity** - No emails sent for extended periods (Gmail may disable for security)
4. **Account recovery** - After changing main Gmail password
5. **Security audit** - During regular security reviews

---

## 🔐 How to Renew Gmail App Password

### Step 1: Prerequisites

- You must have **Two-Factor Authentication (2FA)** enabled on your Google account
- Access to the gmail account recovery email or phone number

### Step 2: Go to App Passwords

1. Visit: **https://myaccount.google.com/apppasswords**
2. You may be prompted to sign in to cycloancldd@gmail.com
3. If 2FA is required, complete the verification

### Step 3: Generate New Password

1. Select **"Mail"** from the application dropdown
2. Select **"Windows Computer"** (or your actual device)
3. Click **"Generate"**
4. Google will display a **16-character app password** (format: `xxxx xxxx xxxx xxxx`)

### Step 4: Copy & Save the New Password

- Google shows the password in a blue box
- **Copy it exactly** (with or without spaces)
- Save it in a secure location (password manager)

### Step 5: Update CYCLOAN Files

The app password is used in these locations:

#### Location 1: admin1_dashboard.php (Line 1102)

```php
$mail->Password = 'hbfh ukgh tmzw nqbq';  // REPLACE THIS
```

#### Location 2: admin1_dashboard.php (Line 1557)

```php
$mail->Password = 'hbfh ukgh tmzw nqbq';  // REPLACE THIS
```

#### Location 3: admin1_dashboard.php (Line 2377)

```php
$mailer->Password = 'hbfh ukgh tmzw nqbq';  // REPLACE THIS
```

#### Location 4: admin1_dashboard.php (Line 2490)

```php
$mailer->Password = 'hbfh ukgh tmzw nqbq';  // REPLACE THIS
```

**To update:**

1. Open `admin1_dashboard.php` in your code editor
2. Use Find & Replace (Ctrl+H):
   - **Find:** `hbfh ukgh tmzw nqbq`
   - **Replace with:** Your new app password
3. Save the file
4. Upload to your server

### Step 6: Test the New Password

Run the **Email Debug Test**:

- URL: `http://yoursite.com/email_debug_test.php`
- This will validate the new credentials

---

## 🛡️ Security Best Practices

### ✅ DO:

- ✅ Store app password in a password manager
- ✅ Use different app passwords for different apps (don't reuse)
- ✅ Regenerate passwords if you suspect compromise
- ✅ Keep Two-Factor Authentication enabled
- ✅ Review app passwords periodically at https://myaccount.google.com/apppasswords

### ❌ DON'T:

- ❌ Use your main Gmail password (NEVER!)
- ❌ Share the app password in emails or chats
- ❌ Commit the app password to version control
- ❌ Store in plain text (use environment variables in production)
- ❌ Use the same app password for multiple applications

---

## 🚀 Email Features That Use This Password

### 1. **Registration & OTP Verification**

- Sends OTP codes during user registration
- Located in: `process_registration.php`

### 2. **Credit Investigation Notifications**

- Notifies applicants of credit investigation status
- Located in: `admin1_dashboard.php` (lines 2350-2420)

### 3. **Password Reset**

- Sends password reset links to users
- Located in: `admin1_dashboard.php` (lines 1070-1160)

### 4. **Loan Status Updates**

- Notifies users of loan application status changes
- Located in: `admin1_dashboard.php` (lines 1520-1610)

---

## 🧪 Testing Email Configuration

### Quick Test:

```bash
# Access the debug page in your browser
http://yoursite.com/email_debug_test.php
```

### What the test checks:

1. ✅ Gmail credentials validity
2. ✅ SMTP connection establishment
3. ✅ TLS security negotiation
4. ✅ Email sending capability

---

## 🔧 Troubleshooting

### Error: "535 5.7.8 Username and Password not accepted"

**Solution:**

- App password may have expired
- Regenerate new app password from Google
- Update all 4 locations in admin1_dashboard.php
- Wait 5 minutes before testing

### Error: "SMTP connection timeout"

**Possible causes:**

- Firewall blocking port 587
- Google account locked for security
- Network connectivity issue
  **Solution:**
- Check firewall allows outbound port 587
- Visit Google Account Security settings
- Try test from different network

### Error: "Unexpected response: 530 5.7.0 Must issue a STARTTLS command first"

**Solution:**

- This should not occur (TLS is configured)
- Check `SMTPSecure = 'tls'` and `Port = 587`
- Regenerate app password

### Emails sent but not received

**Possible causes:**

- Recipient email marked as spam
- Recipient has spam filters
- Email sent to wrong address
  **Solution:**
- Check `debug_log.txt` for sent confirmations
- Ask recipient to check spam folder
- Verify recipient email in database

---

## 📊 Email Log Location

Check if emails are being sent:

```
/debug_log.txt
```

Search for: `"Credit investigation status email sent to"`

**Example log entry:**

```
[13-Nov-2025 14:36:57 Asia/Manila] Credit investigation status email sent to johnlestercamit@gmail.com for application_id: 0, status: Completed, final_loan_amount: 10001
```

---

## 🔐 Environment Variables (Production Best Practice)

Instead of hardcoding passwords, use environment variables:

### 1. Create `.env` file (in project root):

```
GMAIL_USERNAME=cycloancldd@gmail.com
GMAIL_PASSWORD=your_new_app_password_here
GMAIL_HOST=smtp.gmail.com
GMAIL_PORT=587
```

### 2. Update PHP code:

```php
$mailer->Username = getenv('GMAIL_USERNAME');
$mailer->Password = getenv('GMAIL_PASSWORD');
```

### 3. Ensure `.env` is in `.gitignore`:

```
.env
.env.local
```

---

## 📝 Renewal Reminder

**Set a reminder to check email credentials every 90 days:**

- [ ] Review app passwords on Google Account Security page
- [ ] Test email sending via debug page
- [ ] Check debug_log.txt for any errors
- [ ] Renew app password if needed

---

## ✅ Verification Checklist

After renewing the app password:

- [ ] Updated all 4 locations in admin1_dashboard.php
- [ ] File saved locally
- [ ] File uploaded to server
- [ ] Ran email_debug_test.php successfully
- [ ] Tested credit investigation email sending
- [ ] Verified email received by test recipient
- [ ] Checked debug_log.txt for confirmation
- [ ] Documented new password in secure location

---

## 🆘 Need Help?

If emails are not sending:

1. **First:** Run `email_debug_test.php` to identify the issue
2. **Then:** Check `debug_log.txt` for error messages
3. **Check:** That all 4 locations have been updated with new password
4. **Verify:** 2FA is enabled on cycloancldd@gmail.com
5. **Test:** Send a test email manually to verify

---

**Last Updated:** November 13, 2025  
**Status:** Email System Operational ✅  
**Next Review:** February 13, 2026
