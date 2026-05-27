# ✅ FINAL CHECKLIST - READY TO UPLOAD

## ✅ Completed Tasks

- [x] Deleted two_factor_auth.php
- [x] Deleted rate_limiter.php
- [x] Deleted env_config.php
- [x] Deleted .env
- [x] Deleted .env.example
- [x] Deleted 2FA_SCHEMA.sql
- [x] Verified registration.php is clean
- [x] Verified process_registration.php is clean
- [x] Verified CSRF fix is intact
- [x] Verified email function is working
- [x] Created documentation

## ⏳ Pending: Before Upload

- [ ] Open process_registration.php in editor
- [ ] Find line ~75: `$mail->Host = 'smtp.gmail.com';`
- [ ] Replace `smtp.gmail.com` with your SMTP server
- [ ] Find line ~77: `$mail->Username = 'your_email@gmail.com';`
- [ ] Replace with your actual email address
- [ ] Find line ~78: `$mail->Password = 'your_app_password';`
- [ ] Replace with your actual password
- [ ] Save the file
- [ ] Close the editor

## ⏳ Pending: Upload

### Using FileZilla

- [ ] Open FileZilla
- [ ] Host: 145.79.28.91
- [ ] Port: 22
- [ ] User: u455107563
- [ ] Password: (your cPanel password)
- [ ] Navigate to: /home/u455107563/public_html/
- [ ] Drag registration.php to server
- [ ] Drag process_registration.php to server
- [ ] Wait for upload to complete
- [ ] Verify files appear on server

### OR Using cPanel

- [ ] Login to cPanel
- [ ] Click "File Manager"
- [ ] Navigate to "public_html"
- [ ] Click "Upload"
- [ ] Select registration.php
- [ ] Select process_registration.php
- [ ] Click Upload
- [ ] Wait for completion
- [ ] Close File Manager

## ⏳ Pending: After Upload

- [ ] Open browser
- [ ] Press Ctrl+Shift+Delete (clear cache)
- [ ] Clear ALL data
- [ ] Close browser completely
- [ ] Reopen browser

## ⏳ Pending: Testing

- [ ] Visit: https://cycloan-cldd.com/registration.php?step=1
- [ ] Form displays without errors ✅
- [ ] Fill in Step 1 (First Name, Email, etc.)
- [ ] Click "Next" button
- [ ] Check F12 → Network tab for response
- [ ] Verify response is 200 OK (not 403)
- [ ] Advance to Step 2 ✅
- [ ] Continue through all 6 steps
- [ ] Verify OTP email is received
- [ ] Verify form completes successfully

## Summary

| Task | Status |
|------|--------|
| Cleanup | ✅ Complete |
| Verification | ✅ Complete |
| Documentation | ✅ Complete |
| Update Credentials | ⏳ Pending |
| Upload Files | ⏳ Pending |
| Test Registration | ⏳ Pending |

---

## Files to Upload

**From:** `c:\Users\john lester\cycloan\.vscode\`

**To:** `/home/u455107563/public_html/`

```
✅ registration.php
✅ process_registration.php
```

---

## SMTP Server Examples

**Gmail (most common):**
```
Host: smtp.gmail.com
Port: 587
Username: your_email@gmail.com
Password: your_app_password (NOT regular password)
```

**Office365:**
```
Host: smtp.office365.com
Port: 587
Username: your_email@outlook.com
Password: your_password
```

**GoDaddy:**
```
Host: smtpout.secureserver.net
Port: 587
Username: your_email
Password: your_password
```

---

## Troubleshooting

### Can't find SMTP credentials?
- Check your email provider's settings
- Look for "SMTP server" or "Email settings"
- Some providers require "app passwords" (Gmail, Office365)

### Upload fails?
- Check FTP credentials are correct
- Verify server address is correct
- Ensure you have write permissions
- Try different port (some use 21 instead of 22)

### Still getting 403 error after upload?
- Clear browser cache completely
- Try incognito/private mode
- Wait 5 minutes for DNS cache
- Verify files actually uploaded with FileZilla

### Email not sending?
- Check credentials are correct
- Verify SMTP host is correct
- Check firewall allows port 587
- Check mail server isn't blocking your IP

---

## Next: Run This Checklist! ✅

This is your action plan. Follow it step by step and your registration will be working!

**Estimated time: 15-20 minutes**

---

**Questions?** See documentation files in your workspace.
