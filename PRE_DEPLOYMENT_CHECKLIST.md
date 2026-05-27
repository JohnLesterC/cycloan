# ✅ PRE-DEPLOYMENT CHECKLIST

## Before You Upload

- [ ] Open `process_registration.php` in editor
- [ ] Find line 75: `$mail->Host = 'smtp.gmail.com';`
- [ ] **Replace** with your SMTP server (Gmail, Office365, etc.)
- [ ] Find line 77: `$mail->Username = 'your_email@gmail.com';`
- [ ] **Replace** with your email address
- [ ] Find line 78: `$mail->Password = 'your_app_password';`
- [ ] **Replace** with your app password or password

## Files to Upload

Upload to: `/home/u455107563/public_html/`

- [ ] `process_registration.php` (UPDATED with credentials)
- [ ] `registration.php` (unchanged)

## Upload Methods

**Option 1: FileZilla SFTP**

- [ ] Open FileZilla
- [ ] Host: 145.79.28.91
- [ ] Port: 22
- [ ] User: u455107563
- [ ] Password: (your cPanel password)
- [ ] Drag files to /home/u455107563/public_html/

**Option 2: cPanel File Manager**

- [ ] Login to cPanel
- [ ] Click "File Manager"
- [ ] Navigate to "public_html"
- [ ] Upload process_registration.php
- [ ] Upload registration.php

## After Upload - Testing

- [ ] Visit: https://cycloan-cldd.com/registration.php?step=1
- [ ] Fill in Step 1 form (name, email, etc.)
- [ ] Click "Next"
- [ ] Expected: Proceed to Step 2
- [ ] NOT expected: 403 Forbidden error
- [ ] Check email: You should receive OTP

## If Issues

### 403 Forbidden Still?

- [ ] Clear browser cache (Ctrl+Shift+Delete)
- [ ] Try incognito mode
- [ ] Wait 5 minutes (DNS cache)
- [ ] Verify files uploaded with FileZilla

### Email Not Sending?

- [ ] Check SMTP credentials are correct
- [ ] Check Gmail app password (not regular password)
- [ ] Check firewall/host restrictions on port 587
- [ ] Check error_log for details

### Database Connection Error?

- [ ] Check CYCLOAN_db.php exists
- [ ] Check database credentials are correct
- [ ] Check database u455107563_cycloan_db1 exists

## Success Indicators ✅

- [ ] Form displays without errors
- [ ] Can fill all fields
- [ ] Click "Next" advances to Step 2
- [ ] No 403 Forbidden error
- [ ] No PHP errors or warnings
- [ ] OTP email is received

---

**Date Completed:** November 10, 2025
**Status:** Ready for upload
