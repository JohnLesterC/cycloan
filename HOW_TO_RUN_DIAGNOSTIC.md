# 🎯 How to Run the Diagnostic Command

## Step 1: Connect to Your Server

### Option A: Using SSH (Recommended)

If you have SSH access:

```bash
ssh u455107563@145.79.28.91
```

Or if your host provided a different SSH command:

```bash
ssh user@your-server-ip
```

**Password**: Your cPanel/hosting password

---

### Option B: Using cPanel Terminal

1. Go to: https://cpanel.yourdomain.com
2. Login with your credentials
3. Find "Terminal" in the control panel
4. Click it
5. A terminal window will open

---

### Option C: Using File Manager

If you don't have SSH, use cPanel File Manager:

1. Go to: https://cpanel.yourdomain.com
2. Login
3. Find "File Manager"
4. Navigate to your project root folder

---

## Step 2: Navigate to Your Project

After connecting, run:

```bash
cd /home/u455107563/public_html
```

Or if your path is different:

```bash
cd /home/youruser/public_html/cycloan
```

---

## Step 3: Run the Diagnostic

```bash
php system_health_check.php
```

That's it! 🎉

---

## Step 4: Copy the Output

The terminal will display the results.

**Select ALL the text**:

- Windows/Linux: `Ctrl+A` then `Ctrl+C`
- Mac: `Cmd+A` then `Cmd+C`

**Paste it here** in your next message

---

## 📝 Example (What It Looks Like)

When you run the command, you'll see something like:

```
================================================================================
CYCLOAN REGISTRATION SYSTEM HEALTH CHECK
================================================================================

1. CONFIGURATION CHECK
------------------------
✅ env_config.php exists
✅ .env exists

2. PHP VERSION & EXTENSIONS
---------------------------
PHP Version: 7.4.30
✅ Extension 'mysqli' loaded
✅ Extension 'json' loaded
✅ Extension 'curl' loaded
✅ Extension 'mbstring' loaded

3. REQUIRED FILES
-----------------
✅ process_registration.php (Registration processor)
✅ security_validation.php (Validation functions)
✅ CYCLOAN_db.php (Database connection)
...

[More output follows]
```

---

## 🚀 Quick Reference

| What                           | Command                               |
| ------------------------------ | ------------------------------------- |
| Connect via SSH                | `ssh u455107563@145.79.28.91`         |
| Go to project                  | `cd /home/u455107563/public_html`     |
| Run diagnostic                 | `php system_health_check.php`         |
| See last 50 lines of error log | `tail -50 error_registration.log`     |
| See PHP errors                 | `tail -50 /var/log/php-fpm/error.log` |

---

## 💡 Tips

- The commands are **case-sensitive**
- If you get "command not found", make sure you're in the right directory
- If `php` doesn't work, try `/usr/bin/php` or ask your hosting provider
- You can copy output by selecting with mouse and Ctrl+C

---

## 🎯 Next Steps

1. **Connect** to your server
2. **Navigate** to `/home/u455107563/public_html`
3. **Run**: `php system_health_check.php`
4. **Copy**: The entire output
5. **Paste**: Here in your next message

**Then I'll tell you exactly what to fix!** ✅

---

## ❓ Don't Know Your Server Details?

**Check your hosting email** for:

- Server IP address
- SSH username
- SSH password
- cPanel URL

Or check cPanel directly:

1. Login to cPanel at: https://your-domain.com:2083 (or /cpanel)
2. Look for "SSH/Shell Access" or "Terminal"
3. Note your username shown there

---

**Ready?** Connect to your server and run the command! 🚀
