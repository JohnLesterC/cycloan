# 📺 Visual Guide - Running the Diagnostic

## 🖥️ Option 1: SSH (Command Line)

### For Windows Users (Using PowerShell or PuTTY)

**Step 1: Open Terminal**

```
1. Press: Windows + R
2. Type: powershell
3. Press: Enter
```

**Step 2: Connect to Server**

```bash
ssh u455107563@145.79.28.91
```

**Step 3: You'll See**

```
u455107563@145.79.28.91's password:
```

**Step 4: Type Your Password**

- Type your hosting password
- Note: Password won't show as you type (this is normal)
- Press Enter

**Step 5: You'll See**

```
[u455107563@server ~]$
```

**Step 6: Navigate to Project**

```bash
cd /home/u455107563/public_html
```

**Step 7: Run Diagnostic**

```bash
php system_health_check.php
```

**Step 8: Results Will Show**

```
✅ env_config.php exists
✅ .env exists
...
```

---

## 🌐 Option 2: cPanel Terminal (Web Browser)

### Step-by-Step

**Step 1: Go to cPanel**

```
Visit: https://your-domain.com:2083
Or: https://cpanel.yourdomain.com
```

**Step 2: Login**

- Enter your username
- Enter your password
- Click Login

**Step 3: Find Terminal**

- Look for "Terminal" in the left sidebar
- Or search for "Terminal" in the search box
- Click it

**Step 4: A Terminal Window Opens**

```
You'll see a black box with:
[user@server ~]$
```

**Step 5: Run Commands**

```bash
cd /home/u455107563/public_html
php system_health_check.php
```

**Step 6: Results Display**

```
See the diagnostic output below
```

---

## 📱 Option 3: File Manager (If No SSH)

### In cPanel File Manager

**Step 1: Open File Manager**

- Go to cPanel
- Click "File Manager"

**Step 2: Navigate**

- Navigate to `/home/u455107563/public_html/`

**Step 3: Right-Click**

- Right-click in the folder
- Select "New File"
- Name it: `run_diagnostic.php`

**Step 4: Paste Code**

```php
<?php
system('php system_health_check.php');
?>
```

**Step 5: Visit in Browser**

```
https://cycloan-cldd.com/run_diagnostic.php
```

**Step 6: See Results**

- The diagnostic output will display in your browser
- Copy it all

---

## 🎯 The Easiest Way

### Use cPanel Terminal (No Installation Needed)

1. **Go to**: https://cpanel.your-domain.com
2. **Login** with your credentials
3. **Find**: "Terminal" (usually in Advanced section)
4. **Run**:
   ```bash
   cd /home/u455107563/public_html
   php system_health_check.php
   ```
5. **Select All**: Ctrl+A or Cmd+A
6. **Copy**: Ctrl+C or Cmd+C
7. **Paste** the output in your next message

---

## 📋 What You'll See

### Good Output (All Working)

```
✅ env_config.php exists
✅ .env exists
✅ Extension 'mysqli' loaded
✅ Connection successful
✅ Table 'users1' exists
...
✅ ALL CHECKS PASSED!
```

### Bad Output (Some Broken)

```
✅ env_config.php exists
❌ .env MISSING
❌ Extension 'mysqli' NOT loaded
❌ Connection FAILED: Unknown host
...
❌ FOUND 4 ISSUE(S):
1. .env file not found
2. PHP extension 'mysqli' missing
...
```

---

## 🆘 Common Issues When Running

### "Command not found: php"

**Solution**: Try:

```bash
/usr/bin/php system_health_check.php
```

Or:

```bash
php-cli system_health_check.php
```

### "Permission denied"

**Solution**: Run:

```bash
chmod +x system_health_check.php
php system_health_check.php
```

### "No such file or directory"

**Solution**: Make sure you're in the right folder:

```bash
pwd  # Shows current folder
ls   # Lists files (should see system_health_check.php)
```

### Can't Connect to Server

**Solution**: Check:

- Server IP address
- Username
- Password
- Port (usually 22 for SSH)

Ask your hosting provider for details!

---

## 📊 My Recommendation

**Easiest**: Use cPanel Terminal

1. One click to access
2. No extra software needed
3. Works in any browser

**How**:

1. Login to cPanel
2. Find "Terminal"
3. Copy-paste the commands
4. Share the output

---

## ✅ Checklist

- [ ] Connected to server (SSH or cPanel)
- [ ] Navigated to `/home/u455107563/public_html`
- [ ] Ran: `php system_health_check.php`
- [ ] Copied all output
- [ ] Ready to share output

---

## 🎬 Ready to Start?

**Follow one of these paths**:

### Path A (Recommended - cPanel Terminal)

1. https://cpanel.your-domain.com
2. Find "Terminal"
3. Run: `php system_health_check.php`
4. Copy output
5. Share here

### Path B (SSH via Command Line)

1. Open Terminal/PowerShell
2. `ssh u455107563@145.79.28.91`
3. `cd /home/u455107563/public_html`
4. `php system_health_check.php`
5. Copy output
6. Share here

### Path C (Browser File Manager)

1. cPanel File Manager
2. Create `run_diagnostic.php`
3. Visit in browser
4. Copy output
5. Share here

---

**Pick one method above and run the diagnostic!** 🚀

Once you share the output, I'll fix the 500 error immediately! ✅
