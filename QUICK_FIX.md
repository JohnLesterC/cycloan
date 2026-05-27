# Quick Fix: 500 Error on Registration

## The Problem

```
POST https://cycloan-cldd.com/process_registration.php?step=1
net::ERR_HTTP_RESPONSE_CODE_FAILURE 500 (Internal Server Error)
```

## Most Likely Cause: Missing `.env` File

The registration process requires a `.env` configuration file that was not created.

## Solution (Quick Steps)

### Option 1: Automatic (Already Done ✅)

The `.env` file has been created with default values at:

```
.vscode/.env
```

### Option 2: Manual Fix

If the automatic fix didn't work:

1. **Create `.env` file** in your project root:

```bash
cp .env.example .env
```

2. **Update with your credentials**:

```bash
# Email Configuration
MAIL_HOST=smtp.gmail.com
MAIL_USERNAME=your-email@gmail.com
MAIL_PASSWORD=your-app-specific-password

# Database
DB_HOST=localhost
DB_USER=your_db_user
DB_PASS=your_db_password
DB_NAME=your_database
```

3. **Verify the file**:

- Ensure no trailing whitespace
- No quotes around values
- One variable per line

## How to Verify It's Fixed

### Option 1: Access Diagnostic Page

```
https://cycloan-cldd.com/registration_diagnostic.php
```

All checks should show ✅

### Option 2: Check Error Log

```bash
tail -f error_registration.log
```

Should show:

```
[timestamp] Registration POST received for step: 1, IP: xxx.xxx.xxx.xxx
```

### Option 3: Test Registration

1. Go to registration form
2. Fill in Step 1 details
3. Submit
4. Should proceed to Step 2 (or show form validation errors, not 500)

## If Still Getting 500 Error

### Step 1: Check `.env` Exists

```bash
ls -la .env
# Should output something like: -rw-r--r-- 1 user group 1234 Nov 10 12:00 .env
```

### Step 2: Verify Database Connection

```bash
php -r "require 'CYCLOAN_db.php'; echo 'DB Connected';"
# Should output: DB Connected
```

### Step 3: Check Error Logs

```bash
# Check PHP error log
tail -f /var/log/php-fpm/error.log

# Or check application error log
tail -f error_registration.log
```

### Step 4: Enable Debug Mode

Edit `.env` and change:

```
APP_DEBUG=true
```

Then test registration again to see detailed errors.

## Common Issues & Fixes

| Error                                    | Fix                                     |
| ---------------------------------------- | --------------------------------------- |
| `Call to undefined function getEnvVar()` | Missing `env_config.php` include        |
| `Class 'RateLimiter' not found`          | Missing `rate_limiter.php` include      |
| `SMTP Error: Could not authenticate`     | Check email credentials in `.env`       |
| `Connection failed: Unknown host`        | Check `DB_HOST` in `.env`               |
| `Access denied for user`                 | Check `DB_USER` and `DB_PASS` in `.env` |

## Files Created/Modified

✅ **Created**:

- `.env` - Main configuration file
- `REGISTRATION_ERROR_FIX.md` - Detailed fix guide
- `registration_diagnostic.php` - Diagnostic tool

✅ **Modified**:

- `process_registration.php` - Added better error logging

## Testing Checklist

- [ ] `.env` file exists
- [ ] All credentials in `.env` are correct
- [ ] Database connection works
- [ ] Email configuration is valid
- [ ] All required PHP files exist
- [ ] Log files show requests being processed

## Next Steps

1. **Access the diagnostic page**:

   ```
   https://cycloan-cldd.com/registration_diagnostic.php
   ```

2. **Run through all checks** and note any ❌ items

3. **Fix any identified issues** using the guides above

4. **Test registration** and verify it works

If problems persist, provide the output from `registration_diagnostic.php` for detailed troubleshooting.

---

**Last Updated**: November 10, 2025
