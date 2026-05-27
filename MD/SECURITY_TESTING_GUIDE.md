# 🧪 REGISTRATION SECURITY - QUICK TESTING GUIDE

**Test Everything Before Going Live!**

---

## Test 1: CSRF Token Protection ✅

### What it protects against:

Attackers forging requests from their own website

### How to test:

```bash
1. Open registration.php in browser
2. Right-click → Inspect → Elements tab
3. Find the hidden CSRF token field:
   <input type="hidden" name="csrf_token" value="abc123def456...">
4. Copy the value
5. Try submitting form with WRONG token value
6. Expected: ❌ Error "Security error: Invalid session token"
```

### What happens if it fails:

- Form submits successfully with wrong token = SECURITY ISSUE ⚠️
- Check: process_registration.php has CSRF validation code
- Check: $\_SESSION['csrf_token'] is set in registration.php

---

## Test 2: XSS Prevention ✅

### What it protects against:

Attackers injecting JavaScript code through form fields

### How to test:

```bash
1. Open registration form
2. In "First Name" field, enter: <script>alert('XSS')</script>
3. Fill other required fields normally
4. Click Next or submit
5. Expected: ❌ No alert appears, text stored as-is
6. Expected: Form submits successfully with sanitized text

Also test in other text fields:
- Middle Name: <img src=x onerror=alert('XSS')>
- Last Name: " onclick="alert('XSS')
- Email: test@example.com<script>alert('x')</script>
```

### What happens if it fails:

- Alert popup appears = SECURITY ISSUE ⚠️
- Check: sanitizeFormInputs() is called in validateStep()
- Check: registration.js has sanitizeInput() function
- Check: input values are sanitized before submission

---

## Test 3: Email Header Injection Prevention ✅

### What it protects against:

Attackers sending BCC emails through the registration form

### How to test:

```bash
1. Complete step 1 of registration
2. In name fields, try injecting newlines:
   First Name: John%0aBcc:
   Or: John\nBcc:
   Or: John
   Bcc: attacker@evil.com
3. Complete registration and check received OTP email
4. Expected: Email goes ONLY to user, no BCC
5. Expected: Email headers are clean
```

### What happens if it fails:

- Attacker's email receives copy of OTP = SECURITY ISSUE ⚠️
- Check: filter_var() is used on $to email
- Check: str_replace(["\r", "\n", "%0a", "%0d"], '', $name)

---

## Test 4: OTP Rate Limiting ✅

### What it protects against:

Attackers trying all 1,000,000 possible 6-digit OTP codes

### How to test:

```bash
1. Complete registration, receive OTP
2. Go to verify_otp.php
3. Enter WRONG OTP (e.g., 000000) - Click Submit
4. Repeat wrong OTP 4 MORE times (5 total wrong attempts)
5. On the 6th attempt, enter wrong OTP again
6. Expected: ❌ Error "Too many attempts. Try again in X minutes"
7. Cannot submit again until 15 minutes pass
```

### What happens if it fails:

- Can submit unlimited OTP attempts = SECURITY ISSUE ⚠️
- Check: Rate limiting code in verify_otp.php
- Check: $_SESSION[$attempt_key] is tracking attempts
- Check: 15-minute window is enforced

---

## Test 5: OTP Hash Verification ✅

### What it protects against:

If database is breached, attackers cannot use OTP codes

### How to test:

```bash
1. Complete registration, receive OTP (e.g., 123456)
2. Go to verify_otp.php
3. Enter CORRECT OTP - Should work
4. Clear screen, test again with different OTP
5. Expected: ✅ Correct OTP verifies successfully
6. Expected: ❌ Incorrect OTP rejected (after multiple tries)
```

### To verify hashing in database:

```bash
1. Open database admin (phpMyAdmin, etc.)
2. Go to otps table
3. Look at otp_hash column
4. It should look like: $2y$12$abc...xyz (bcrypt hash)
5. NOT like: 123456 (plain text)
6. Expected: ✅ All hashes start with $2y$ (bcrypt format)
```

### What happens if it fails:

- OTP stored as plain text = SECURITY ISSUE ⚠️
- Check: Database migration was applied
- Check: generateOTP() and hashOTP() are in process_registration.php

---

## Test 6: Session Security ✅

### What it protects against:

Session fixation, session hijacking

### How to test:

```bash
1. Open registration.php
2. Open browser DevTools → Application → Cookies
3. Find PHPSESSID cookie
4. Check "HttpOnly" flag = ✅ Set (cannot access via JavaScript)
5. Check "Secure" flag = ✅ Set (only over HTTPS)
6. Check "SameSite" attribute = ✅ Strict
```

### What happens if it fails:

- HttpOnly not set = JavaScript can steal session = ISSUE ⚠️
- Secure not set = Cookie sent over HTTP = ISSUE ⚠️
- SameSite not Strict = CSRF possible = ISSUE ⚠️

---

## Test 7: Password Validation ✅

### What it protects against:

Weak passwords that are easy to guess

### How to test:

```bash
1. Go to Step 5 (Account Security) of registration
2. Try these passwords:
   a) "password" (no special char) = ❌ Should show error
   b) "Password1" (no special char) = ❌ Should show error
   c) "Pass@123" (has all required) = ✅ Should pass
3. Password must have:
   - 8+ characters
   - At least 1 uppercase (A-Z)
   - At least 1 lowercase (a-z)
   - At least 1 number (0-9)
   - At least 1 special character (!@#$%^&*)
```

### What happens if it fails:

- Weak password accepted = Security issue ⚠️
- Check: validatePassword() function in registration.js

---

## Test 8: Complete Happy Path ✅

### The normal registration flow:

```bash
1. ✅ Open registration.php
2. ✅ Accept data privacy consent
3. ✅ Fill all 5-6 steps with VALID data
4. ✅ Submit each step successfully
5. ✅ Receive OTP email
6. ✅ Enter OTP correctly
7. ✅ See "Account verified successfully"
8. ✅ Can now log in
```

### What should happen:

- Every step validates and proceeds
- No error messages for correct data
- OTP email received quickly
- OTP verification succeeds on first try
- Account activated and ready to use

---

## Test 9: Validation & Error Messages ✅

### Test invalid inputs:

```bash
Step 1 - Personal Info:
[ ] First Name: Leave empty → Error "Required field"
[ ] Birthday: Enter future date → Error "Invalid age"
[ ] Age: Enter 15 → Error "Must be 21+"
[ ] Email: Enter "notanemail" → Error "Invalid email"
[ ] Phone: Enter "1234567" (7 digits) → Error "Invalid phone"

Step 5 - Account Security:
[ ] Password: Enter "weak" → Error "Must be 8+ characters"
[ ] Password: Enter "Pass123" → Error "Missing special character"
[ ] Password & Confirm: Different values → Error "Passwords don't match"
```

### What should happen:

- Clear error messages displayed
- Form doesn't submit with invalid data
- User can correct and retry

---

## Test 10: Database Verification ✅

### Verify OTP hashing in database:

```bash
# Open database (phpMyAdmin, MySQL CLI, etc.)

# Check users1 table:
SELECT id, email, is_active, created_at FROM users1;
- is_active should be 0 before OTP verification
- is_active should be 1 after OTP verification

# Check otps table structure:
DESCRIBE otps;
- Should have: id, user_id, otp_hash, attempt_count, created_at, expires_at
- Should NOT have: otp_code (old column)

# Check otp_hash format:
SELECT id, user_id, otp_hash FROM otps LIMIT 5;
- All otp_hash values should start with $2y$ (bcrypt)
- Should NOT see 6-digit numbers (123456)
```

---

## 📋 Testing Checklist

Copy and paste this checklist:

```
SECURITY FEATURE TESTING CHECKLIST
==================================

Basic Functionality:
[ ] Registration form loads without errors
[ ] Data privacy consent modal displays
[ ] All 5-6 steps of form work
[ ] Each step validates inputs correctly
[ ] OTP email arrives within 30 seconds
[ ] OTP verification works
[ ] Account becomes active after verification

CSRF Protection:
[ ] CSRF token exists in hidden field
[ ] Submitting with wrong token fails
[ ] Error message "Invalid session token" appears
[ ] Correct token allows submission

XSS Prevention:
[ ] Script tags in text fields don't execute
[ ] Event handlers (onclick, onerror) don't trigger
[ ] JavaScript protocol injection blocked
[ ] No alert() popups from malicious input

Rate Limiting:
[ ] Can enter wrong OTP 5 times
[ ] On 6th wrong attempt, blocked
[ ] Error shows remaining wait time
[ ] After 15 minutes, can try again

Email Security:
[ ] OTP email arrives to correct recipient
[ ] No BCC emails sent
[ ] Email headers clean (no injection)
[ ] Email content formatted correctly

Session Security:
[ ] PHPSESSID cookie has HttpOnly flag
[ ] PHPSESSID cookie has Secure flag
[ ] PHPSESSID cookie has SameSite=Strict
[ ] Session ID changes after consent

Database:
[ ] OTP stored as bcrypt hash ($2y$...)
[ ] NOT stored as plain text
[ ] Users created with is_active=0
[ ] Users updated to is_active=1 after verification
[ ] Indexes present for performance

Logging:
[ ] Error log records security events
[ ] "Invalid CSRF" attempts logged
[ ] Rate limiting attempts logged
[ ] No sensitive data in logs

[ ] ✅ ALL TESTS PASSED - READY FOR PRODUCTION
```

---

## 🚨 Critical Issues to Watch For

| Issue                      | Impact      | Fix                                      |
| -------------------------- | ----------- | ---------------------------------------- |
| OTP stored plain text      | 🔴 Critical | Apply database migration                 |
| No CSRF token              | 🔴 Critical | Check registration.php token field       |
| XSS in form fields         | 🔴 Critical | Check sanitizeFormInputs() call          |
| No rate limiting           | 🔴 Critical | Check verify_otp.php rate limit code     |
| Email injection possible   | 🟠 High     | Check filter_var() in sendOTPEmail()     |
| Session cookies not secure | 🟠 High     | Check ini*set session.cookie*\* settings |

---

## 📊 Test Results Template

Save this after testing:

```
CYCLOAN REGISTRATION SECURITY TEST RESULTS
==========================================

Test Date: ___________
Tester: ___________
Environment: [Production / Staging / Local]

RESULTS:
--------
Test 1 - CSRF Protection: [ ] PASS [ ] FAIL
Test 2 - XSS Prevention: [ ] PASS [ ] FAIL
Test 3 - Email Injection: [ ] PASS [ ] FAIL
Test 4 - Rate Limiting: [ ] PASS [ ] FAIL
Test 5 - OTP Hashing: [ ] PASS [ ] FAIL
Test 6 - Session Security: [ ] PASS [ ] FAIL
Test 7 - Password Validation: [ ] PASS [ ] FAIL
Test 8 - Happy Path: [ ] PASS [ ] FAIL
Test 9 - Validation Messages: [ ] PASS [ ] FAIL
Test 10 - Database: [ ] PASS [ ] FAIL

OVERALL: [ ] APPROVED [ ] NEEDS FIXES

Issues Found:
- Issue 1: ___________
- Issue 2: ___________

Sign-off: ___________ Date: ___________
```

---

## ✅ Ready for Testing!

**All security enhancements are implemented and ready for testing.**

Start with Test 1 and work through all 10 tests to verify everything is working correctly.

**Expected Result:** All tests PASS ✅

Then you're ready for production deployment! 🚀
