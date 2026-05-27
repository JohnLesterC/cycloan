# 📤 FINAL UPLOAD GUIDE

## What to Upload

Upload **ONLY 2 FILES** to production:

### Destination: `/home/u455107563/public_html/`

1. **registration.php**
   - Location: `c:\Users\john lester\cycloan\.vscode\registration.php`
   - Size: ~753 KB
   - Changes: CSRF fix + form UI
   - Status: ✅ Ready

2. **process_registration.php**
   - Location: `c:\Users\john lester\cycloan\.vscode\process_registration.php`
   - Size: ~602 KB
   - Changes: CSRF fix + PHPMailer + no 2FA/rate limiting/env vars
   - Status: ✅ Ready (update credentials first!)

## ⚠️ BEFORE UPLOADING

**You MUST update these lines in `process_registration.php`:**

```php
// Line ~75-78
$mail->Host = 'smtp.gmail.com';              // Change smtp.gmail.com
$mail->Username = 'your_email@gmail.com';    // Change to your email
$mail->Password = 'your_app_password';       // Change to your password
```

**For Gmail:**
- Host: smtp.gmail.com
- Port: 587
- Use: App Password (not regular password)

**For other providers:**
- Office365: smtp.office365.com
- GoDaddy: smtpout.secureserver.net
- etc.

## Upload Methods

### Method 1: FileZilla SFTP ⭐ Recommended

1. Open FileZilla
2. Host: 145.79.28.91
3. Port: 22 (SFTP)
4. User: u455107563
5. Password: (your cPanel password)
6. Drag and drop files to: `/home/u455107563/public_html/`

### Method 2: cPanel File Manager

1. Login to cPanel
2. Click "File Manager"
3. Navigate to "public_html"
4. Click "Upload"
5. Select registration.php
6. Select process_registration.php
7. Click Upload

### Method 3: Command Line (SSH)

```bash
scp registration.php u455107563@145.79.28.91:/home/u455107563/public_html/
scp process_registration.php u455107563@145.79.28.91:/home/u455107563/public_html/
```

## ✅ After Upload - Testing

1. **Clear browser cache:**
   - Press Ctrl+Shift+Delete
   - Clear all data

2. **Test URL:**
   - https://cycloan-cldd.com/registration.php?step=1

3. **Expected results:**
   - Form displays ✅
   - Can fill all fields ✅
   - Click "Next" proceeds to Step 2 ✅
   - No 403 Forbidden error ✅
   - No error messages ✅

4. **If form works:**
   - Continue with all steps
   - Should receive OTP email
   - Verify you can complete registration

## 🆘 Troubleshooting

### Still getting 403 Forbidden?
- [ ] Clear browser cache (Ctrl+Shift+Delete)
- [ ] Try incognito mode
- [ ] Wait 5 minutes (DNS cache)
- [ ] Verify files uploaded with FileZilla

### Email not sending?
- [ ] Check SMTP credentials in code
- [ ] Check email address is correct
- [ ] Check app password (not regular password)
- [ ] Check port 587 is open on server

### Form shows errors?
- [ ] Check CYCLOAN_db.php exists
- [ ] Check database connection
- [ ] Check database credentials
- [ ] Check phpmailer/ folder exists

## 📊 File Checklist

Before uploading, verify you have:

- [ ] registration.php (updated CSRF fix)
- [ ] process_registration.php (updated credentials + CSRF fix)
- [ ] CYCLOAN_db.php (already on server, don't need to upload)
- [ ] phpmailer/ folder (already on server, don't need to upload)

---

**Ready?** Upload the 2 files now!

**Questions?** Check FINAL_CLEANUP_SUMMARY.md or DEPLOYMENT_READY.md
