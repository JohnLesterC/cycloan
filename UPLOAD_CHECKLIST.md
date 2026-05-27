# UPLOAD CHECKLIST - COMPLETE FILE LIST

Destination: `https://cycloan-cldd.com` → Folder: `/home/u455107563/public_html/`

## 🔴 CRITICAL FILES (JUST FIXED - UPLOAD FIRST)

These are the files that were broken and are now fixed:

- [ ] **registration.php** ← FIXED (Session regeneration bug)
- [ ] **process_registration.php** ← FIXED (Session regeneration bug)

**Why Critical:** These files fix the 403 Forbidden error you were getting

---

## 🟡 IMPORTANT FILES (Configuration & Core)

These files are required for the registration system to work:

- [ ] **.env** ← REQUIRED (Database & email credentials)
- [ ] **env_config.php** ← REQUIRED (Loads .env file)
- [ ] **CYCLOAN_db.php** ← REQUIRED (Database connection)
- [ ] **security_validation.php** ← REQUIRED (Input validation)

**Why Important:** Without these, registration won't connect to database

---

## 🟢 SECURITY FILES (Rate Limiting & 2FA)

These files prevent spam and add security:

- [ ] **rate_limiter.php** ← FIXED (Spam prevention)
- [ ] **two_factor_auth.php** ← FIXED (2FA system)

**Why Important:** Protects against registration abuse

---

## 🔵 DIAGNOSTIC FILES (Testing & Debugging)

These files help you verify everything works:

- [ ] **system_health_check.php** ← DIAGNOSTIC (Checks all components)
- [ ] **test_db.php** ← DIAGNOSTIC (Tests database)
- [ ] **test_email.php** ← DIAGNOSTIC (Tests email)

**Why Important:** Run these to diagnose any remaining issues

---

## 📚 FOLDERS TO UPLOAD (If Not Already There)

Verify these folders exist on production:

- [ ] CSS/ (folder with all stylesheets)
- [ ] JAVASCRIPT/ (folder with all scripts)
- [ ] phpmailer/ (folder with email library)
- [ ] tcpdf/ (folder with PDF library)
- [ ] IMAGE/ (folder with images/logos)
- [ ] uploads/ (folder for file uploads)
- [ ] database/ (folder with database dump)

---

## 🚀 UPLOAD ORDER

**Recommended upload order:**

1. First: .env (without this, nothing will work)
2. Second: env_config.php, CYCLOAN_db.php, security_validation.php
3. Third: **registration.php, process_registration.php (THE FIXED FILES)**
4. Fourth: rate_limiter.php, two_factor_auth.php
5. Fifth: system_health_check.php, test_db.php, test_email.php

---

## 📊 FILE LOCATIONS ON YOUR LOCAL MACHINE

All files are in: `c:\Users\john lester\cycloan\.vscode\`

To upload them:

1. Open FileZilla or cPanel File Manager
2. Navigate to `/home/u455107563/public_html/`
3. Select files from your local directory
4. Upload them

---

## ✅ VERIFICATION CHECKLIST

After uploading, verify:

- [ ] All 2 critical files uploaded: registration.php, process_registration.php
- [ ] All 4 config files uploaded: .env, env_config.php, CYCLOAN_db.php, security_validation.php
- [ ] All 2 security files uploaded: rate_limiter.php, two_factor_auth.php
- [ ] All 3 diagnostic files uploaded: system_health_check.php, test_db.php, test_email.php
- [ ] All supporting folders exist: CSS/, JAVASCRIPT/, phpmailer/, tcpdf/, IMAGE/, uploads/
- [ ] File permissions are correct: PHP files should be 644 or 755

---

## 🧪 POST-UPLOAD TESTING

After uploading all files:

1. **Test 1: Load Registration Form**

   ```
   Visit: https://cycloan-cldd.com/registration.php?step=1
   Expected: Form displays without PHP errors
   ```

2. **Test 2: Submit Step 1**

   ```
   Fill in all Step 1 fields
   Click "Next"
   Expected: 200 OK (not 403 Forbidden)
   ```

3. **Test 3: Continue Through Steps**

   ```
   Complete Steps 2-6
   Expected: No 403 errors, smooth progression
   ```

4. **Test 4: Run Diagnostic (Optional)**
   ```
   SSH: php system_health_check.php
   Expected: All components show ✅
   ```

---

## 📝 TOTAL FILES TO UPLOAD

Count: **15 files + 6 folders**

Critical: 2 files
Important: 4 files
Security: 2 files
Diagnostic: 3 files
Documentation: 4+ files (optional but recommended)

---

## ⏱️ ESTIMATED UPLOAD TIME

Using FileZilla SFTP: ~2-5 minutes
Using cPanel File Manager: ~5-10 minutes

---

## 🆘 IF UPLOAD FAILS

Check:

1. FTP credentials are correct (u455107563)
2. Destination folder is: /home/u455107563/public_html/
3. Files are not too large (all files < 1MB)
4. Connection is stable
5. Server has disk space available

---

**Status: Ready to Upload**

All files are prepared and ready to deploy.
Follow checklist and test after upload.
