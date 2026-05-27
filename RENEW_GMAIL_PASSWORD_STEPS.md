# How to Renew Gmail App Password - Step-by-Step

## ⏱️ Time Required: 5-10 minutes

---

## 🎯 STEP 1: Generate New App Password

### 1.1 Visit Google Account Settings

- Go to: **https://myaccount.google.com/apppasswords**
- Make sure you're signed in as **cycloancldd@gmail.com**

### 1.2 Complete 2FA Challenge

- Google may ask you to enter a code
- Use your phone or backup codes

### 1.3 Select Application

```
Select dropdown 1: [Mail              ▼]
Select dropdown 2: [Windows Computer  ▼]
                   (or your device)
```

### 1.4 Generate Password

- Click **"Generate"** button
- Google will show: **`xxxx xxxx xxxx xxxx`** (16 characters)
- Example: `hbfh ukgh tmzw nqbq`

### 1.5 Copy Password

```
┌────────────────────────────┐
│ pxzq wxyz abcd efgh        │  ← Copy this!
└────────────────────────────┘
```

**✅ Save in a secure location (password manager)**

---

## 🎯 STEP 2: Update CYCLOAN Files

### OPTION A: Using the Automatic Script (Recommended)

#### 2A.1 Open Terminal/PowerShell

```powershell
cd C:\Users\john lester\cycloan
```

#### 2A.2 Run the Update Script

```powershell
php update_gmail_password.php
```

#### 2A.3 Follow Prompts

```
Enter old password to replace: hbfh ukgh tmzw nqbq
Enter new password: pxzq wxyz abcd efgh
Confirm password replacement:
  Old: hbfh ukgh tmzw nqbq
  New: pxzq wxyz abcd efgh
Proceed? (yes/no): yes
```

#### 2A.4 Verify Results

```
✅ admin1_dashboard.php - 4 replacement(s)
✅ SUCCESS! Gmail app password has been updated.
```

---

### OPTION B: Manual Update (If Script Fails)

#### 2B.1 Open admin1_dashboard.php in Your Editor

#### 2B.2 Find & Replace (Ctrl+H)

```
Find:    hbfh ukgh tmzw nqbq
Replace: pxzq wxyz abcd efgh
Replace All
```

#### 2B.3 Verify 4 Replacements

- Line 1102: Password Reset Email
- Line 1557: Loan Status Email
- Line 2377: Credit Investigation Setup
- Line 2490: Credit Investigation Send

#### 2B.4 Save File (Ctrl+S)

---

## 🎯 STEP 3: Upload to Server

### 3.1 Upload Updated Files

```bash
# Using SFTP/FTP or your hosting panel
Upload: admin1_dashboard.php → public_html/
```

### 3.2 Verify Upload

```bash
# Check file timestamp on server
ls -la admin1_dashboard.php
```

**✅ Files should show current time**

---

## 🎯 STEP 4: Test the New Password

### 4.1 Access Debug Page in Browser

```
http://yoursite.com/email_debug_test.php
```

### 4.2 Check Results

#### ✅ SUCCESS:

```
✅ SMTP Connection SUCCESSFUL!
The Gmail server accepted your credentials. Emails should work!
```

#### ❌ FAILED:

```
❌ SMTP Connection FAILED!
Error: 535 5.7.8 Username and Password not accepted
```

**If failed:**

- Double-check the app password was copied correctly
- Verify 2FA is enabled on cycloancldd@gmail.com
- Try generating a new app password

### 4.3 Send Test Email

- Enter test email: `johnlestercamit@gmail.com`
- Click "Send Test Email"
- Check inbox for test message

---

## 🎯 STEP 5: Verify in Application

### 5.1 Test Credit Investigation Email

1. Login to Admin Dashboard
2. Find a loan application
3. Update "Credit Investigation Status" to "Completed"
4. Email should be sent to applicant

### 5.2 Check Debug Logs

```bash
# View last 20 lines
tail -20 debug_log.txt
```

### 5.3 Look for Confirmation

```
[DATE TIME] Credit investigation status email sent to johnlestercamit@gmail.com for application_id: 123, status: Completed
```

**✅ If you see this, emails are working!**

---

## 📋 Troubleshooting

### Problem: "Password not accepted"

**Solution:**

```
1. Go back to myaccount.google.com/apppasswords
2. Delete the old app password
3. Generate a completely new one
4. Wait 5 minutes
5. Update CYCLOAN files again
6. Test again
```

### Problem: "Connection timeout"

**Solution:**

```
1. Check your internet connection
2. Verify port 587 is not blocked by firewall
3. Try from a different network
4. Contact your hosting provider if persistent
```

### Problem: "Files not updated"

**Solution:**

```
# If script doesn't work, use manual method:
1. Download admin1_dashboard.php from server
2. Edit locally with Find & Replace
3. Upload back to server
4. Clear browser cache (Ctrl+Shift+Delete)
5. Test again
```

### Problem: "Test email not received"

**Solution:**

```
1. Check spam/junk folder
2. Check email is correct in database
3. Run email_debug_test.php again
4. Check debug_log.txt for errors
5. Ask recipient to whitelist cycloancldd@gmail.com
```

---

## ✅ Verification Checklist

After completing all steps:

- [ ] Generated new app password from Google
- [ ] Copied it to safe location
- [ ] Updated admin1_dashboard.php (or ran script)
- [ ] Uploaded file to server
- [ ] Ran email_debug_test.php successfully
- [ ] Sent test email and received it
- [ ] Tested credit investigation email
- [ ] Found confirmation in debug_log.txt
- [ ] Documented password in password manager

---

## 📞 Quick Links

| Resource              | URL                                       |
| --------------------- | ----------------------------------------- |
| **App Passwords**     | https://myaccount.google.com/apppasswords |
| **Security Settings** | https://myaccount.google.com/security     |
| **Gmail Support**     | https://support.google.com/mail/          |
| **PHPMailer Docs**    | https://github.com/PHPMailer/PHPMailer    |

---

## 🔔 Set Reminder

**Next renewal date:** February 13, 2026 (90 days)

```
📅 Add to Calendar:
   Task: Renew Gmail App Password
   Date: February 13, 2026
   Time: 2:00 PM
   Duration: 10 minutes
   Reminder: EMAIL_APP_PASSWORD_RENEWAL_GUIDE.md
```

---

## ❓ FAQ

**Q: How often do I need to do this?**  
A: Every 90 days, or when credentials stop working

**Q: Is the main Gmail password still needed?**  
A: No, only the 16-character app password

**Q: What if I lose the app password?**  
A: Just generate a new one, update files, and delete the old one

**Q: Can I use the same app password for other apps?**  
A: Not recommended - generate unique password per app

**Q: What if emails suddenly stop working?**  
A: Usually means app password expired - regenerate new one

---

**Estimated Time:** 5-10 minutes  
**Difficulty:** Easy  
**Risk Level:** Low (easily reversible)  
**Support:** EMAIL_SYSTEM_QUICK_REFERENCE.md
