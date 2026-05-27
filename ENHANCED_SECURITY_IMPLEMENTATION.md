# CYCLOAN Enhanced Security Implementation Guide

## Overview

This guide explains the three new security features added to your CYCLOAN registration system:

1. **Environment Variables** - For managing sensitive credentials securely
2. **Rate Limiting** - For protecting against brute force attacks
3. **Two-Factor Authentication** - For enhanced account security

---

## 🔐 1. Environment Variables Configuration

### Purpose

Separates sensitive credentials from source code to prevent accidental exposure in version control.

### Files Created

- `env_config.php` - Configuration loader and environment variable handler
- `.env` - Environment variables file (not tracked in git)

### Setup Instructions

#### Step 1: Create `.env` File

```bash
# In your project root, create .env file:
cp env.example .env
```

#### Step 2: Add Environment Variables

```env
# .env file (NEVER commit this to version control!)

# === EMAIL CONFIGURATION ===
MAIL_HOST=smtp.gmail.com
MAIL_USERNAME=your-email@gmail.com
MAIL_PASSWORD=your-app-password-here
MAIL_FROM_NAME=CYCLOAN Support
MAIL_PORT=587
MAIL_ENCRYPTION=tls

# === OTP SETTINGS ===
OTP_EXPIRATION_MINUTES=10
OTP_MAX_ATTEMPTS=5
OTP_LENGTH=6

# === RATE LIMITING ===
RATE_LIMIT_ENABLED=true
RATE_LIMIT_REGISTRATION_PER_IP=5
RATE_LIMIT_REGISTRATION_WINDOW=3600
RATE_LIMIT_OTP_PER_EMAIL=3
RATE_LIMIT_OTP_WINDOW=3600
RATE_LIMIT_VERIFICATION_PER_EMAIL=5
RATE_LIMIT_VERIFICATION_WINDOW=300

# === TWO-FACTOR AUTHENTICATION ===
TWO_FACTOR_ENABLED=true
TWO_FACTOR_METHOD=email
TWO_FACTOR_GRACE_PERIOD=300

# === DATABASE ===
DB_HOST=localhost
DB_USER=root
DB_PASS=
DB_NAME=cycloan
DB_PORT=3306

# === APPLICATION ===
APP_NAME=CYCLOAN
APP_URL=http://localhost:8000
APP_ENV=development
APP_DEBUG=false

# === REDIS (Optional - for rate limiting) ===
REDIS_ENABLED=false
REDIS_HOST=localhost
REDIS_PORT=6379
REDIS_PASSWORD=
REDIS_DB=0
```

#### Step 3: Update `.gitignore`

```bash
# Add to .gitignore:
.env
.env.local
.env.*.local
```

#### Step 4: Include in Your Code

```php
<?php
require_once 'env_config.php';

// Now use constants instead of hardcoded values:
$mail_username = MAIL_USERNAME;      // Instead of 'scycloan@gmail.com'
$mail_password = MAIL_PASSWORD;      // Instead of hardcoded password
$rate_limit = RATE_LIMIT_ENABLED;   // Instead of hardcoded value
?>
```

### Benefits

✅ Credentials not in version control  
✅ Different configs per environment (dev, staging, production)  
✅ Easy credential rotation  
✅ Complies with OWASP guidelines

---

## 🛡️ 2. Rate Limiting System

### Purpose

Prevents brute force attacks by limiting registration attempts, OTP requests, and verification tries.

### Files Created

- `rate_limiter.php` - Rate limiting engine with file or Redis storage

### Features

#### Configurable Limits

```
Registration:     Max 5 per IP per hour
OTP Requests:     Max 3 per email per hour
OTP Verification: Max 5 attempts per 5 minutes
```

### Usage in Registration

#### Step 1: Initialize Rate Limiter

```php
<?php
require_once 'rate_limiter.php';

$rate_limiter = new RateLimiter();
?>
```

#### Step 2: Check Limits Before Allowing Action

```php
<?php
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $user_ip = $_SERVER['REMOTE_ADDR'];

    // Check registration rate limit
    if (!$rate_limiter->checkLimit('registration', $user_ip)) {
        $remaining_time = $rate_limiter->getTimeUntilReset('registration', $user_ip);
        http_response_code(429); // Too Many Requests
        die(json_encode([
            'success' => false,
            'message' => "Too many registration attempts. Please try again in $remaining_time seconds.",
            'retry_after' => $remaining_time
        ]));
    }

    // Process registration...

    // Record the attempt
    $rate_limiter->recordAttempt('registration', $user_ip);
}
?>
```

#### Step 3: Check OTP Rate Limits

```php
<?php
$email = $form_data['email'];

// Check OTP request limit
if (!$rate_limiter->checkLimit('otp_request', $email)) {
    $remaining_time = $rate_limiter->getTimeUntilReset('otp_request', $email);
    $_SESSION['error_message'] = "Too many OTP requests. Please try again in $remaining_time seconds.";
    exit();
}

// Send OTP...

$rate_limiter->recordAttempt('otp_request', $email);
?>
```

### API Reference

#### Check Limit

```php
$limiter = new RateLimiter();

// Returns: true if within limit, false if rate limited
if ($limiter->checkLimit('registration', $_SERVER['REMOTE_ADDR'])) {
    // Proceed
}
```

#### Record Attempt

```php
// Increments the counter
$limiter->recordAttempt('registration', $_SERVER['REMOTE_ADDR']);
```

#### Get Remaining Attempts

```php
$remaining = $limiter->getRemainingAttempts('registration', $_SERVER['REMOTE_ADDR']);
// Returns: 3 (if user has 2 more registration attempts left)
```

#### Get Time Until Reset

```php
$seconds = $limiter->getTimeUntilReset('registration', $_SERVER['REMOTE_ADDR']);
// Returns: 1200 (20 minutes until counter resets)
```

#### Reset Limit

```php
// Manually reset a user's rate limit
$limiter->reset('registration', $_SERVER['REMOTE_ADDR']);
```

### Storage Backends

#### File Storage (Default)

- Uses `/tmp/cycloan_rate_limits/` directory
- Works without additional dependencies
- Good for development
- Auto-cleanup after 24 hours

#### Redis Storage (Recommended for Production)

```env
REDIS_ENABLED=true
REDIS_HOST=localhost
REDIS_PORT=6379
REDIS_PASSWORD=your-redis-password
REDIS_DB=0
```

- Better performance for high-traffic applications
- Automatic TTL management
- Shared across multiple servers (load-balanced)

### Attack Prevention Examples

#### Example 1: Brute Force Registration

```
Attacker tries: 10 registrations from same IP in 1 hour
Rate Limiter blocks: After 5 attempts
Response: "Too many registrations. Try again in 3600 seconds."
```

#### Example 2: OTP Guessing

```
Attacker tries: 100 OTP codes on same email
Rate Limiter blocks: After 5 verification attempts in 5 minutes
Response: "Too many verification attempts. Try again in 45 seconds."
```

#### Example 3: Email Bombing

```
Attacker requests: 20 OTPs for same email
Rate Limiter blocks: After 3 requests in 1 hour
Response: "Too many OTP requests. Try again in 900 seconds."
```

---

## 🔑 3. Two-Factor Authentication (2FA)

### Purpose

Adds an extra security layer requiring users to verify their identity through multiple methods.

### Files Created

- `two_factor_auth.php` - 2FA engine with multiple methods

### Supported Methods

| Method    | Description                       | Pros                | Cons                      |
| --------- | --------------------------------- | ------------------- | ------------------------- |
| **Email** | OTP sent via email                | No extra app needed | Slightly slower           |
| **SMS**   | OTP sent via SMS                  | Very fast           | Requires SMS service      |
| **TOTP**  | Authenticator app (Google, Authy) | Very secure         | Requires app installation |

### Database Schema

#### Tables Created Automatically

```sql
-- 2FA settings per user
two_factor_methods (
  id, user_id, method, phone_number, totp_secret, is_enabled, created_at, updated_at
)

-- 2FA challenges (OTP records)
two_factor_challenges (
  id, user_id, challenge_id, method, code_hash, verified, attempt_count,
  created_at, expires_at, verified_at
)

-- Backup codes for account recovery
two_factor_backup_codes (
  id, user_id, code_hash, used, used_at, created_at
)
```

### Usage in Registration

#### Step 1: Initialize 2FA

```php
<?php
require_once 'two_factor_auth.php';
require_once 'CYCLOAN_db.php';

$twofa = new TwoFactorAuth($conn);
?>
```

#### Step 2: Enable 2FA After Registration

```php
<?php
// After user successfully registers
$user_id = $user->id;
$user_email = $user->email;

// Enable email-based 2FA
$twofa->enableTwoFactorAuth($user_id, 'email');

// Or for SMS:
$twofa->enableTwoFactorAuth($user_id, 'sms', '+639171234567');

// Or for TOTP:
$twofa->enableTwoFactorAuth($user_id, 'totp');
?>
```

#### Step 3: Generate 2FA Challenge

```php
<?php
// After password verification, send 2FA code
$challenge_id = $twofa->generateChallenge($user_id, $user_email);

if (!$challenge_id) {
    $_SESSION['error_message'] = "Could not generate 2FA code. Please try again.";
    exit();
}

// Store challenge ID in session
$_SESSION['2fa_challenge_id'] = $challenge_id;

// Redirect to 2FA verification page
header("Location: verify_2fa.php");
?>
```

#### Step 4: Verify 2FA Code

```php
<?php
// On 2FA verification page
$user_code = $_POST['verification_code'];
$challenge_id = $_SESSION['2fa_challenge_id'];
$user_id = $_SESSION['user_id'];

if ($twofa->verifyChallenge($user_id, $user_code, $challenge_id)) {
    // 2FA passed - complete login
    $_SESSION['authenticated'] = true;
    header("Location: user_dashboard.php");
} else {
    // Get remaining attempts
    $remaining = $rate_limiter->getRemainingAttempts('otp_verification', (string) $user_id);
    $_SESSION['error_message'] = "Invalid code. $remaining attempts remaining.";
}
?>
```

### API Reference

#### Enable 2FA

```php
$twofa->enableTwoFactorAuth($user_id, 'email');
// Returns: bool (success/failure)
```

#### Disable 2FA

```php
$twofa->disableTwoFactorAuth($user_id);
// Returns: bool (success/failure)
```

#### Check if Enabled

```php
if ($twofa->isTwoFactorEnabled($user_id)) {
    // User has 2FA enabled
}
```

#### Generate Challenge

```php
$challenge_id = $twofa->generateChallenge($user_id, $email);
// Returns: string challenge_id or null on failure
```

#### Verify Challenge

```php
if ($twofa->verifyChallenge($user_id, $code, $challenge_id)) {
    // Verification passed
}
```

#### Generate Backup Codes

```php
$codes = $twofa->generateBackupCodes($user_id, 10);
// Returns: array ['CODE1', 'CODE2', 'CODE3', ...]
```

#### Verify Backup Code

```php
if ($twofa->verifyBackupCode($user_id, $backup_code)) {
    // Backup code is valid
}
```

### Implementation Flow

#### Registration with 2FA Flow

```
1. User enters registration form
        ↓
2. Rate limiter checks: 5 registrations per IP per hour
        ↓
3. Password is validated and hashed (bcrypt)
        ↓
4. User data inserted into database
        ↓
5. 2FA enabled for user (method: email)
        ↓
6. OTP generated and sent to email
        ↓
7. User sees verify_otp.php page
        ↓
8. User enters OTP from email
        ↓
9. Rate limiter checks: 5 attempts per 5 minutes
        ↓
10. OTP verified (bcrypt hash comparison)
        ↓
11. Account activated
        ↓
12. User redirected to dashboard
```

#### Login with 2FA Flow

```
1. User enters email/password
        ↓
2. Rate limiter checks: 5 registrations per IP per hour
        ↓
3. Password verified against hash
        ↓
4. 2FA check: Is 2FA enabled?
        ↓
5. YES: Generate challenge
        ↓
6. Send code via email/SMS/TOTP
        ↓
7. User enters code
        ↓
8. Rate limiter checks: 5 attempts per 5 minutes
        ↓
9. Code verified
        ↓
10. Grant access to dashboard
```

### Configuration

```env
# In .env file:
TWO_FACTOR_ENABLED=true
TWO_FACTOR_METHOD=email          # email, sms, or totp
TWO_FACTOR_GRACE_PERIOD=300      # 5 minutes
OTP_EXPIRATION_MINUTES=10         # 10 minutes
OTP_MAX_ATTEMPTS=5                # Max verification attempts
```

### Security Considerations

✅ OTP codes are hashed (never stored in plain text)  
✅ Codes expire after 10 minutes  
✅ Rate limited to 5 attempts per 5 minutes  
✅ Attempt count tracked in database  
✅ Backup codes for account recovery  
✅ Challenge ID is cryptographically unique

---

## 📋 Integration Checklist

- [ ] Created `.env` file with credentials
- [ ] Added `.env` to `.gitignore`
- [ ] Included `env_config.php` in `process_registration.php`
- [ ] Updated email credentials to use `MAIL_USERNAME`, `MAIL_PASSWORD` constants
- [ ] Imported `RateLimiter` class in registration handler
- [ ] Added rate limit checks before registration
- [ ] Imported `TwoFactorAuth` class
- [ ] Added 2FA tables to database
- [ ] Enabled 2FA for new registrations
- [ ] Created `verify_2fa.php` page for code entry
- [ ] Tested rate limiting with multiple attempts
- [ ] Tested 2FA with email verification
- [ ] Configured environment variables per environment (dev, staging, prod)

---

## 🚀 Production Deployment

### Before Going Live

1. **Set Production Environment Variables**

   ```env
   APP_ENV=production
   APP_DEBUG=false
   ```

2. **Enable Redis for Rate Limiting**

   ```env
   REDIS_ENABLED=true
   ```

3. **Use Strong Email Credentials**

   - Use app-specific passwords (not main account)
   - Rotate quarterly

4. **Enable HTTPS**

   ```php
   SESSION_SECURE=true
   ```

5. **Monitor Rate Limits**

   - Track 429 responses
   - Adjust limits based on usage patterns

6. **Backup Codes**
   - Generate and securely store for admins
   - Document recovery process

---

## 🔍 Monitoring & Troubleshooting

### Check Rate Limit Status

```php
$limiter = new RateLimiter();
$remaining = $limiter->getRemainingAttempts('registration', $user_ip);
$reset_time = $limiter->getTimeUntilReset('registration', $user_ip);

echo "Remaining attempts: $remaining";
echo "Reset in: $reset_time seconds";
```

### Check 2FA Status

```php
$twofa = new TwoFactorAuth($conn);
if ($twofa->isTwoFactorEnabled($user_id)) {
    echo "2FA is enabled for user";
}
```

### Clear Rate Limits (Admin Only)

```php
$limiter = new RateLimiter();
$limiter->reset('registration', '192.168.1.100');
$limiter->reset('otp_request', 'user@example.com');
```

### Cleanup Expired Records

```php
$limiter->cleanup();  // Remove 24+ hour old files
```

---

## 📚 Additional Resources

- [OWASP Authentication Cheat Sheet](https://cheatsheetseries.owasp.org/cheatsheets/Authentication_Cheat_Sheet.html)
- [OWASP Rate Limiting Guide](https://cheatsheetseries.owasp.org/cheatsheets/Rate_Limiting_Cheat_Sheet.html)
- [2FA Best Practices](https://cheatsheetseries.owasp.org/cheatsheets/Credential_Stuffing_Prevention_Cheat_Sheet.html)

---

**Created**: November 10, 2025  
**Version**: 1.0  
**Status**: ✅ Ready for Implementation
