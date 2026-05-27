# 🚨 Registration 500 Internal Server Error - Diagnostic & Fix Guide

## The Problem

```
POST https://cycloan-cldd.com/process_registration.php?step=1
net::ERR_HTTP_RESPONSE_CODE_FAILURE 500 (Internal Server Error)
```

When users try to register and submit the form, they get a **500 Internal Server Error** instead of proceeding to the next step.

---

## 📊 Common Causes (In Priority Order)

### **1. Database Connection Failure** ⚠️ MOST LIKELY

The database credentials are wrong or the database is unreachable.

**Symptoms**:

- Error appears immediately on form submission
- No error logs show anything specific
- Registration process stops at database connection

**Fix**:

```bash
# Check database credentials in .env:
DB_HOST=localhost              # Should be localhost for cPanel
DB_USER=u455107563_cycloan_dbuse    # Your cPanel DB user
DB_PASS=cm~Mc2~oJ              # Your cPanel DB password
DB_NAME=u455107563_cycloan_db1      # Your cPanel DB name
DB_PORT=3306                   # Must be 3306 (NOT 21!)
```

**⚠️ CRITICAL**: If your hosting is **shared cPanel**, you likely need to use:

```
DB_HOST=localhost   (NOT an IP address)
```

---

### **2. Missing env_config.php Include** 🔴 VERY LIKELY

The `process_registration.php` file isn't loading the environment config.

**Check**: Does `process_registration.php` have this at the top?

```php
<?php
require_once 'env_config.php';
```

If missing, **registration will crash** because it can't find database credentials.

---

### **3. PHPMailer Not Installed** 🔴 VERY LIKELY

OTP email sending fails, crashing the registration process.

**Check**: Does this folder exist?

```
phpmailer/
  ├── src/
  │   ├── Exception.php
  │   ├── PHPMailer.php
  │   └── SMTP.php
  └── README.md
```

If missing, you need to install PHPMailer.

---

### **4. Database Tables Missing** 🟡 POSSIBLE

Required database tables don't exist.

**Required Tables**:

- `users1`
- `otps`
- `financial_info`
- `income_sources`
- `expenditure_types`
- `spouses`

**Check**: Import the database dump:

```bash
mysql -h localhost -u u455107563_cycloan_dbuse -p u455107563_cycloan_db1 < database/cycloan_db.sql
```

---

### **5. CSRF Token Missing** 🟡 POSSIBLE

Session or CSRF validation fails.

**Check**: Does registration form include?

```html
<input
  type="hidden"
  name="csrf_token"
  value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>"
/>
```

---

### **6. PHP Extension Missing** 🟡 POSSIBLE

Required PHP extensions not installed.

**Required Extensions**:

- `mysqli` (MySQL database)
- `json` (For JSON encoding/decoding)
- `curl` (For interest rate API)
- `mbstring` (For string functions)

---

## 🔍 Step-by-Step Diagnostic

### **Step 1: Check Error Logs**

**Access your server via SSH/Terminal**:

```bash
# Check PHP error log
tail -f /var/log/php-fpm/error.log

# Or check application error log
tail -f error_registration.log

# Or check Apache error log
tail -f /var/log/apache2/error.log
```

**What to look for**:

- `mysqli_connect(): Connection refused` → Database connection failed
- `Class 'PHPMailer' not found` → PHPMailer missing
- `Call to undefined function` → Missing function/class
- `Access denied for user` → Wrong database credentials

---

### **Step 2: Test Database Connection**

Create a test file: `test_db.php`

```php
<?php
require_once 'env_config.php';

echo "Testing Database Connection...\n";
echo "DB_HOST: " . DB_HOST . "\n";
echo "DB_USER: " . DB_USER . "\n";
echo "DB_NAME: " . DB_NAME . "\n";
echo "DB_PORT: " . DB_PORT . "\n";

$conn = mysqli_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME, DB_PORT);

if ($conn->connect_error) {
    echo "❌ Connection Failed: " . $conn->connect_error . "\n";
    exit(1);
} else {
    echo "✅ Connection Successful!\n";

    // Check tables
    $tables = ['users1', 'otps', 'financial_info', 'income_sources', 'expenditure_types', 'spouses'];
    foreach ($tables as $table) {
        $result = $conn->query("SHOW TABLES LIKE '$table'");
        if ($result->num_rows > 0) {
            echo "✅ Table '$table' exists\n";
        } else {
            echo "❌ Table '$table' MISSING\n";
        }
    }
}
?>
```

**Run it**:

```bash
php test_db.php
```

**Expected Output**:

```
Testing Database Connection...
DB_HOST: localhost
DB_USER: u455107563_cycloan_dbuse
DB_NAME: u455107563_cycloan_db1
DB_PORT: 3306
✅ Connection Successful!
✅ Table 'users1' exists
✅ Table 'otps' exists
✅ Table 'financial_info' exists
✅ Table 'income_sources' exists
✅ Table 'expenditure_types' exists
✅ Table 'spouses' exists
```

---

### **Step 3: Test Email Configuration**

Create test file: `test_email.php`

```php
<?php
require_once 'env_config.php';
require_once 'phpmailer/src/Exception.php';
require_once 'phpmailer/src/PHPMailer.php';
require_once 'phpmailer/src/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

$mail = new PHPMailer(true);

try {
    echo "Testing Email Configuration...\n";

    $mail->isSMTP();
    $mail->Host = MAIL_HOST;
    $mail->SMTPAuth = true;
    $mail->Username = MAIL_USERNAME;
    $mail->Password = MAIL_PASSWORD;
    $mail->SMTPSecure = MAIL_ENCRYPTION;
    $mail->Port = MAIL_PORT;

    echo "SMTP Host: " . MAIL_HOST . "\n";
    echo "SMTP Port: " . MAIL_PORT . "\n";
    echo "SMTP User: " . MAIL_USERNAME . "\n";
    echo "SMTP Encryption: " . MAIL_ENCRYPTION . "\n";

    if ($mail->smtpConnect()) {
        echo "✅ Email Configuration is Valid!\n";
    } else {
        echo "❌ Email Connection Failed\n";
    }
} catch (Exception $e) {
    echo "❌ Email Error: {$mail->ErrorInfo}\n";
}
?>
```

**Run it**:

```bash
php test_email.php
```

**Expected Output**:

```
Testing Email Configuration...
SMTP Host: smtp.gmail.com
SMTP Port: 587
SMTP User: scycloan@gmail.com
SMTP Encryption: tls
✅ Email Configuration is Valid!
```

---

### **Step 4: Test Registration Process**

Visit: `https://cycloan-cldd.com/registration.php?step=1`

Fill in the form and submit. Watch the browser console for the error.

Check error logs:

```bash
tail -f error_registration.log
```

---

## 🔧 Quick Fixes

### **Fix 1: Ensure env_config.php is Included**

Edit `process_registration.php` - make sure the TOP of the file has:

```php
<?php
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/error_registration.log');

require_once 'env_config.php';
require_once 'CYCLOAN_db.php';
require_once 'security_validation.php';

session_start();
```

---

### **Fix 2: Update Database Credentials**

Edit `.env` and verify:

```ini
# ========== DATABASE CONFIGURATION ==========
DB_HOST=localhost
DB_USER=u455107563_cycloan_dbuse
DB_PASS=cm~Mc2~oJ
DB_NAME=u455107563_cycloan_db1
DB_PORT=3306
```

---

### **Fix 3: Install PHPMailer (If Missing)**

```bash
# If composer is installed
composer require phpmailer/phpmailer

# Or manually download from:
# https://github.com/PHPMailer/PHPMailer/releases
# Extract to: phpmailer/ folder
```

---

### **Fix 4: Import Database Tables**

```bash
# Navigate to your project root
cd /path/to/cycloan

# Import database
mysql -h localhost -u u455107563_cycloan_dbuse -p u455107563_cycloan_db1 < database/cycloan_db.sql
```

**Password**: `cm~Mc2~oJ`

---

### **Fix 5: Enable Debug Mode**

Edit `.env`:

```ini
APP_DEBUG=true
```

Then test again. This will show detailed error messages.

**⚠️ IMPORTANT**: Set back to `false` after debugging:

```ini
APP_DEBUG=false
```

---

## 📝 Checklist to Resolve 500 Error

### Database Setup

- [ ] `.env` file exists in project root
- [ ] Database credentials are correct in `.env`
- [ ] Database server is reachable (use `test_db.php`)
- [ ] All required tables exist (`users1`, `otps`, `financial_info`, etc.)
- [ ] `env_config.php` is in project root
- [ ] `CYCLOAN_db.php` includes `env_config.php`

### Email Setup

- [ ] Gmail app-specific password is generated
- [ ] `MAIL_PASSWORD` in `.env` is correct
- [ ] `MAIL_USERNAME` is `scycloan@gmail.com`
- [ ] `MAIL_HOST` is `smtp.gmail.com`
- [ ] `MAIL_PORT` is `587`
- [ ] `MAIL_ENCRYPTION` is `tls`
- [ ] PHPMailer folder exists with all required files

### Application Setup

- [ ] `process_registration.php` includes `env_config.php`
- [ ] `security_validation.php` exists
- [ ] All required PHP extensions installed (mysqli, json, curl, mbstring)
- [ ] Error logging is enabled
- [ ] File permissions are correct (755 for directories, 644 for files)

### Testing

- [ ] Run `test_db.php` and verify all tables exist
- [ ] Run `test_email.php` and verify email configuration
- [ ] Check `error_registration.log` for specific errors
- [ ] Test registration form with valid data

---

## 🆘 If Still Not Working

### Action 1: Check Server Error Logs

```bash
# SSH into your server and check:
tail -f /var/log/php-fpm/error.log
tail -f /var/log/apache2/error.log
tail -f error_registration.log
```

### Action 2: Enable Verbose Logging

Edit `process_registration.php` and add at the top:

```php
<?php
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/error_registration.log');
error_reporting(E_ALL);

// Log all errors and warnings
set_error_handler(function($errno, $errstr, $errfile, $errline) {
    error_log("[ERROR] $errno: $errstr in $errfile:$errline");
});
```

### Action 3: Test with Simple Registration

Create `test_registration.php`:

```php
<?php
require_once 'env_config.php';
require_once 'CYCLOAN_db.php';

echo "PHP Version: " . phpversion() . "\n";
echo "MySQL Version: " . mysqli_get_server_info($conn) . "\n";
echo "Database: " . DB_NAME . "\n";
echo "Environment: " . APP_ENV . "\n";
echo "Debug Mode: " . (APP_DEBUG ? 'ON' : 'OFF') . "\n";

// Test a simple query
$result = $conn->query("SELECT 1 as test");
if ($result) {
    echo "✅ Database Query Works\n";
} else {
    echo "❌ Database Query Failed: " . $conn->error . "\n";
}
?>
```

---

## 🎯 Most Common Fix (90% of Cases)

**The registration usually fails because**:

1. **Database credentials wrong** in `.env`
2. **env_config.php not included** in `process_registration.php`
3. **PHPMailer not installed** or can't be loaded
4. **Database tables missing** (need to import SQL dump)

**Quick test** (run these commands):

```bash
# Test 1: Database
php test_db.php

# Test 2: Email
php test_email.php

# Test 3: Check logs
tail -f error_registration.log
```

If all three pass ✅, your registration should work!

---

## 📞 Still Need Help?

Provide the output from:

1. `test_db.php` - Database connection test
2. `test_email.php` - Email configuration test
3. `error_registration.log` - Last 20 lines
4. Browser console error (F12 → Network → Click registration request → Response)

And I'll help diagnose the exact issue!

---

**Last Updated**: November 10, 2025
**Status**: 🟡 Diagnostic files created, awaiting test results
