# 🔧 Registration 500 Error - Quick Action Plan

## The Problem

```
POST https://cycloan-cldd.com/process_registration.php?step=1
net::ERR_HTTP_RESPONSE_CODE_FAILURE 500 (Internal Server Error)
```

---

## ⚡ Quick Fix (5 Steps)

### **Step 1: Verify `.env` File**

```bash
# Check if .env exists in project root
ls -la .env

# Should show credentials like:
DB_HOST=localhost
DB_USER=u455107563_cycloan_dbuse
DB_PASS=cm~Mc2~oJ
DB_NAME=u455107563_cycloan_db1
```

### **Step 2: Run System Health Check**

```bash
cd /path/to/cycloan
php system_health_check.php
```

**Expected Output**: All checks should show ✅

**If you see ❌**, note which items failed - that's your problem!

### **Step 3: Run Database Test**

```bash
php test_db.php
```

**Expected Output**:

```
✅ CONNECTION SUCCESSFUL!
✅ Table 'users1' exists
✅ Table 'otps' exists
... (all tables exist)
```

**If this fails**: Your database credentials are wrong or database is unreachable

### **Step 4: Run Email Test**

```bash
php test_email.php
```

**Expected Output**:

```
✅ SMTP Connection successful!
```

**If this fails**: Your email configuration is wrong

### **Step 5: Test Registration**

1. Go to: `https://cycloan-cldd.com/registration.php?step=1`
2. Fill in the form
3. Click Submit
4. It should work or show a form validation error (not 500)

---

## 🔍 Most Common Causes

### **❌ Database Connection Failing**

**Symptoms**: 500 error immediately on form submit
**Fix**: Check `.env` credentials:

```ini
DB_HOST=localhost      # NOT an IP address
DB_USER=u455107563_cycloan_dbuse
DB_PASS=cm~Mc2~oJ
DB_NAME=u455107563_cycloan_db1
DB_PORT=3306           # NOT 21!
```

### **❌ Missing env_config.php Include**

**Symptoms**: "Call to undefined function" errors
**Fix**: Check top of `process_registration.php`:

```php
<?php
require_once 'env_config.php';
```

### **❌ Email Configuration Wrong**

**Symptoms**: 500 error on OTP step
**Fix**: Check `.env` email settings:

```ini
MAIL_HOST=smtp.gmail.com
MAIL_USERNAME=scycloan@gmail.com
MAIL_PASSWORD=xbvo zplr dpme ixxj
MAIL_PORT=587
MAIL_ENCRYPTION=tls
```

### **❌ Database Tables Missing**

**Symptoms**: "Table doesn't exist" errors
**Fix**: Import database dump:

```bash
mysql -u u455107563_cycloan_dbuse -p u455107563_cycloan_db1 < database/cycloan_db.sql
```

### **❌ PHPMailer Not Installed**

**Symptoms**: "Class not found" errors
**Fix**: Download PHPMailer:

```bash
# Option 1: Via Composer
composer require phpmailer/phpmailer

# Option 2: Manual download
# Download from: https://github.com/PHPMailer/PHPMailer/releases
# Extract to: phpmailer/ folder
```

---

## 📋 Files Created for You

| File                            | Purpose                        | Run with                      |
| ------------------------------- | ------------------------------ | ----------------------------- |
| `system_health_check.php`       | Complete system diagnostic     | `php system_health_check.php` |
| `test_db.php`                   | Test database connection       | `php test_db.php`             |
| `test_email.php`                | Test email configuration       | `php test_email.php`          |
| `REGISTRATION_500_ERROR_FIX.md` | Detailed troubleshooting guide | Read in editor                |

---

## 🆘 If Still Not Working

### **Check Server Logs**

```bash
# SSH into your server and run:
tail -f /var/log/php-fpm/error.log
# or
tail -f /var/log/apache2/error.log
# or
tail -f error_registration.log
```

### **Enable Debug Mode**

Edit `.env`:

```ini
APP_DEBUG=true
```

Then test registration again. Error messages will be more detailed.

**⚠️ Remember to set back to false after debugging!**

### **Check Browser Console**

1. Open browser (Chrome/Firefox)
2. Press **F12** (Developer Tools)
3. Go to **Network** tab
4. Submit registration form
5. Click on the **POST** request to `process_registration.php`
6. Click **Response** tab to see the error

---

## ✅ Success Indicators

When registration is working, you should see:

- ✅ Form submits without 500 error
- ✅ Form either validates or proceeds to next step
- ✅ OTP email is sent (check spam folder)
- ✅ Browser console shows no critical errors

---

## 📞 Next Steps

1. **Run**: `php system_health_check.php`
2. **Check output** for any ❌ items
3. **Fix the problems** listed above
4. **Test again**: Submit registration form
5. **Report**: If still failing, share:
   - Output from `system_health_check.php`
   - Last 20 lines of error logs
   - Browser Network tab error response

---

**Created**: November 10, 2025
**Status**: Ready for testing
