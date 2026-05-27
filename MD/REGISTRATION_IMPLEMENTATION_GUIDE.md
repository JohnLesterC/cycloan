# 🚀 Registration Security - Implementation Guide

**Date:** November 4, 2025  
**Purpose:** Step-by-step code implementation for all registration security enhancements  
**Duration:** 2-3 weeks  
**Priority:** HIGH

---

## 📋 Quick Start (30 minutes)

### Essential Files to Update

1. `registration.php` - Add CSRF tokens + CSP headers
2. `process_registration.php` - Add rate limiting + token validation
3. `registration.js` - Add input sanitization
4. `security_validation.php` - Already enhanced (✅ Ready)

---

## 🔧 Step-by-Step Implementation

### STEP 1: Add CSRF Token to registration.php

**Location:** Top of file (after session_start)

```php
<?php
session_start();

// Clear session messages for fresh page loads unless explicitly redirected
if ($_SERVER['REQUEST_METHOD'] === 'GET' && !isset($_SESSION['fresh_redirect'])) {
    unset($_SESSION['success_message']);
    unset($_SESSION['error_message']);
}

// ✅ NEW: Initialize CSRF token
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Initialize current step
$current_step = isset($_GET['step']) ? (int) $_GET['step'] : 1;
// ... rest of existing code
```

---

### STEP 2: Add CSP Headers to registration.php

**Location:** Just after <?php session_start();

```php
<?php
session_start();

// ✅ NEW: Security headers
if (empty(headers_sent())) {
    // Prevent MIME type sniffing
    header("X-Content-Type-Options: nosniff");

    // Prevent clickjacking
    header("X-Frame-Options: DENY");

    // XSS protection
    header("X-XSS-Protection: 1; mode=block");

    // Referrer policy
    header("Referrer-Policy: strict-origin-when-cross-origin");

    // Restrict browser APIs
    header("Permissions-Policy: geolocation=(), camera=()");

    // Content Security Policy
    header("Content-Security-Policy: " .
        "default-src 'self'; " .
        "script-src 'self' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com; " .
        "style-src 'self' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com 'unsafe-inline'; " .
        "img-src 'self' data: https:; " .
        "font-src 'self' https://cdnjs.cloudflare.com; " .
        "connect-src 'self'; " .
        "frame-ancestors 'none'; " .
        "base-uri 'self'; " .
        "form-action 'self'"
    );
}

// ... existing code
```

---

### STEP 3: Add CSRF Token to Form

**Location:** In HTML form (registration.php, around line 300+)

Find: `<form method="POST" action="process_registration.php" id="registrationForm">`

Add after opening `<form>` tag:

```html
<form method="POST" action="process_registration.php" id="registrationForm">
  <!-- ✅ NEW: CSRF Token -->
  <input
    type="hidden"
    name="csrf_token"
    value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>"
  />

  <!-- ... rest of form content -->
</form>
```

---

### STEP 4: Validate CSRF Token in process_registration.php

**Location:** Top of file after require_once statements

```php
<?php
session_start();

// Include the MySQLi database connection
require_once 'CYCLOAN_db.php';

// Include security validation library
require_once 'security_validation.php';

// ✅ NEW: CSRF Token Validation
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Check if CSRF token exists and matches
    if (!isset($_POST['csrf_token'])) {
        logSecurityError('Missing CSRF token in POST request', 'csrf_attack');
        error_log("CSRF token missing | IP: " . $_SERVER['REMOTE_ADDR']);
        $_SESSION['error_message'] = "Security error: Invalid session. Please refresh and try again.";
        header("Location: registration.php?step=1");
        exit();
    }

    if ($_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        logSecurityError('CSRF token mismatch - possible attack', 'csrf_attack');
        error_log("CSRF token mismatch | IP: " . $_SERVER['REMOTE_ADDR'] . " | Token: " . substr($_POST['csrf_token'], 0, 10));
        $_SESSION['error_message'] = "Security error: Invalid session token. Please refresh and try again.";
        header("Location: registration.php?step=1");
        exit();
    }
}

// Include PHPMailer
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'phpmailer/src/Exception.php';
require 'phpmailer/src/PHPMailer.php';
require 'phpmailer/src/SMTP.php';

// ... rest of existing code
```

---

### STEP 5: Add OTP Rate Limiting to verify_otp.php

**Location:** Near top after session_start()

```php
<?php
session_start();
require_once 'CYCLOAN_db.php';
require_once 'security_validation.php';

// ✅ NEW: OTP Attempt Rate Limiting
$email = $_SESSION['otp_email'] ?? '';
$max_attempts = 5;
$cooldown_minutes = 15;

if (!empty($email) && $_SERVER["REQUEST_METHOD"] == "POST") {
    // Create attempt key (based on email and IP)
    $attempt_key = "otp_attempts_" . md5($email . $_SERVER['REMOTE_ADDR']);

    // Get existing attempts
    $attempts = $_SESSION[$attempt_key] ?? [];

    // Filter out attempts older than cooldown period
    $attempts = array_filter($attempts, function($timestamp) use ($cooldown_minutes) {
        return time() - $timestamp < ($cooldown_minutes * 60);
    });
    $attempts = array_values($attempts); // Reset array keys

    // Check if max attempts exceeded
    if (count($attempts) >= $max_attempts) {
        $oldest_attempt = min($attempts);
        $time_remaining = $cooldown_minutes - (int)((time() - $oldest_attempt) / 60);

        logSecurityError("OTP rate limit exceeded for $email after " . count($attempts) . " attempts", 'brute_force');
        error_log("OTP brute force attempt | Email: $email | IP: " . $_SERVER['REMOTE_ADDR']);

        $_SESSION['error_message'] = "Too many verification attempts. Please try again in {$time_remaining} minute" . ($time_remaining !== 1 ? 's' : '') . ".";
        header("Location: verify_otp.php");
        exit();
    }

    // Record this attempt
    $attempts[] = time();
    $_SESSION[$attempt_key] = $attempts;
}

// ... rest of existing code
```

---

### STEP 6: Add Input Sanitization to registration.js

**Location:** At the beginning of the file (after DOMContentLoaded listener)

```javascript
// ✅ NEW: Input Sanitization Helper
/**
 * Sanitize user input to prevent XSS attacks
 */
function sanitizeInput(input) {
  if (!input || typeof input !== "string") return "";

  // Create temporary element to properly escape HTML
  const element = document.createElement("div");
  element.textContent = input;
  let sanitized = element.innerHTML;

  // Remove common attack patterns
  sanitized = sanitized
    .replace(/<script\b[^<]*(?:(?!<\/script>)<[^<]*)*<\/script>/gi, "")
    .replace(/on\w+\s*=\s*["'][^"']*["']/gi, "")
    .replace(/on\w+\s*=\s*[^\s>]*/gi, "")
    .replace(/javascript:/gi, "")
    .replace(/vbscript:/gi, "");

  return sanitized;
}

/**
 * Validate input type before sending
 */
function validateInputType(input, type) {
  let clean = sanitizeInput(input);

  if (type === "email") {
    // Remove dangerous characters
    clean = clean.replace(/[<>"`]/g, "");
  } else if (type === "phone") {
    // Allow only digits and common phone characters
    clean = clean.replace(/[^\d\-\+\(\)\s]/g, "");
  } else if (type === "text") {
    // Remove HTML-like characters
    clean = clean.replace(/[<>"`]/g, "");
  } else if (type === "number") {
    // Allow only numbers, commas, and decimal points
    clean = clean.replace(/[^\d,\.]/g, "");
  }

  return clean;
}

// ✅ NEW: Form submission safety check
document.addEventListener(
  "submit",
  function (e) {
    // Only process registration forms
    if (e.target.id === "registrationForm") {
      // Sanitize all text inputs before submission
      const textInputs = e.target.querySelectorAll(
        'input[type="text"], input[type="email"]'
      );
      textInputs.forEach((input) => {
        const inputType = input.type === "email" ? "email" : "text";
        input.value = validateInputType(input.value, inputType);
      });
    }
  },
  true
);
```

---

### STEP 7: Add Session Fixation Prevention to process_consent.php

**Location:** In the accept consent section

```php
<?php
session_start();
require_once 'CYCLOAN_db.php';

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'accept') {
        // ✅ NEW: Regenerate session to prevent session fixation
        $old_session_id = session_id();
        session_regenerate_id(true);  // true = delete old session

        $_SESSION['data_privacy_consented'] = true;
        $_SESSION['consent_timestamp'] = time();
        $_SESSION['consent_ip'] = $_SERVER['REMOTE_ADDR'];

        // Log the action
        error_log("User accepted data privacy consent | Old Session: " . substr($old_session_id, 0, 10) .
                  "... | New Session: " . substr(session_id(), 0, 10) . "... | IP: " . $_SERVER['REMOTE_ADDR']);

        echo json_encode(['success' => true]);
        exit();
    }

    elseif ($action === 'decline') {
        $_SESSION['data_privacy_consented'] = false;
        $_SESSION['consent_timestamp'] = time();

        // Clear any form data
        unset($_SESSION['form_data']);

        echo json_encode(['success' => true]);
        exit();
    }

    echo json_encode(['success' => false, 'message' => 'Invalid action']);
    exit();
}

// Redirect to consent page if accessed directly
header('Location: registration.php');
exit();
?>
```

---

### STEP 8: Enhanced OTP Generation in process_registration.php

**Location:** Replace existing generateOTP function

```php
// ✅ NEW: Improved OTP Generation
/**
 * Generate cryptographically secure OTP
 * Uses random_bytes for true randomness
 */
function generateOTP($length = 6)
{
    try {
        // Generate random bytes
        $bytes = random_bytes(ceil($length / 2));

        // Convert to hex and take only needed digits
        $hex = bin2hex($bytes);
        $otp = substr($hex, 0, $length);

        // Ensure it's all digits (0-9)
        $otp = (int)$otp % pow(10, $length);

        // Pad with leading zeros
        return str_pad($otp, $length, '0', STR_PAD_LEFT);
    } catch (Exception $e) {
        // Fallback (should rarely be needed)
        error_log("Failed to generate cryptographic OTP: " . $e->getMessage());
        return str_pad(random_int(0, pow(10, $length) - 1), $length, '0', STR_PAD_LEFT);
    }
}
```

---

### STEP 9: Hash OTP in Database in process_registration.php

**Location:** Find OTP insert section, update as follows

```php
// ✅ NEW: Generate secure OTP
$otp = generateOTP();
$otp_hash = password_hash($otp, PASSWORD_DEFAULT);  // Hash the OTP

$expires_at = (new DateTime())->modify('+10 minutes')->format('Y-m-d H:i:s');

// Store hashed OTP (not plain text)
$stmt = $conn->prepare("INSERT INTO otps (user_id, otp_hash, expires_at, created_at, attempts) VALUES (?, ?, ?, NOW(), 0)");
if ($stmt === false) {
    throw new Exception("An internal error occurred while generating your OTP. Please try again.");
}
$stmt->bind_param("iss", $user_id, $otp_hash, $expires_at);
if (!$stmt->execute()) {
    throw new Exception("An error occurred while storing your OTP. Please try again.");
}
$stmt->close();

// Send PLAIN OTP in email (user sees this)
// Even if database is breached, attackers can't reverse the hash
$full_name = trim("{$form_data['first_name']} " . ($form_data['middle_name'] ? "{$form_data['middle_name']} " : '') . "{$form_data['last_name']}" . ($form_data['name_extension'] ? " {$form_data['name_extension']}" : ''));
if (!sendOTPEmail($form_data['email'], $full_name, $otp)) {  // Send plain OTP
    error_log("Failed to send OTP email to {$form_data['email']}");
    // ... error handling
}
```

---

### STEP 10: Update OTP Verification in verify_otp.php

**Location:** Find OTP validation section

```php
// ✅ NEW: Verify OTP with hashing
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['otp'])) {
    $otp_input = $_POST['otp'];
    $email = $_SESSION['otp_email'] ?? '';

    if (empty($email)) {
        $_SESSION['error_message'] = "Session expired. Please register again.";
        header("Location: registration.php");
        exit();
    }

    try {
        // Get user ID from email
        $stmt = $conn->prepare("SELECT id FROM users1 WHERE email = ? AND is_active = 0");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();
        $stmt->close();

        if (!$user) {
            $_SESSION['error_message'] = "User not found or already activated.";
            header("Location: registration.php");
            exit();
        }

        $user_id = $user['id'];

        // Get the hashed OTP from database
        $stmt = $conn->prepare("SELECT otp_hash, attempts FROM otps WHERE user_id = ? AND expires_at > NOW() ORDER BY created_at DESC LIMIT 1");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $otp_record = $result->fetch_assoc();
        $stmt->close();

        if (!$otp_record) {
            $_SESSION['error_message'] = "No valid OTP found. Please request a new one.";
            header("Location: registration.php");
            exit();
        }

        // Check attempts
        if ($otp_record['attempts'] >= 5) {
            $_SESSION['error_message'] = "Too many verification attempts. Please request a new OTP.";
            logSecurityError("OTP brute force detected for user $user_id", 'brute_force');
            header("Location: registration.php");
            exit();
        }

        // Increment attempts
        $stmt = $conn->prepare("UPDATE otps SET attempts = attempts + 1 WHERE user_id = ? AND expires_at > NOW()");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $stmt->close();

        // Verify OTP (comparing plain text with hash)
        if (password_verify($otp_input, $otp_record['otp_hash'])) {
            // ✅ OTP is correct!
            // Activate user
            $stmt = $conn->prepare("UPDATE users1 SET is_active = 1 WHERE id = ?");
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $stmt->close();

            // Delete used OTP
            $stmt = $conn->prepare("DELETE FROM otps WHERE user_id = ?");
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $stmt->close();

            // ... send welcome email and redirect
        } else {
            $_SESSION['error_message'] = "Invalid OTP. Please check and try again.";
        }
    } catch (Exception $e) {
        error_log("OTP verification error: " . $e->getMessage());
        $_SESSION['error_message'] = "An error occurred during verification. Please try again.";
    }

    header("Location: verify_otp.php");
    exit();
}
```

---

## 📝 Database Migration (if needed)

If your `otps` table doesn't have all columns, run this SQL:

```sql
ALTER TABLE otps ADD COLUMN IF NOT EXISTS attempts INT DEFAULT 0;
ALTER TABLE otps ADD COLUMN IF NOT EXISTS created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP;
ALTER TABLE otps MODIFY COLUMN otp_code VARCHAR(255);  -- For hash storage
ALTER TABLE otps RENAME COLUMN otp_code TO otp_hash;   -- Rename column

-- Add index for faster queries
ALTER TABLE otps ADD INDEX idx_user_expires (user_id, expires_at);
```

---

## 🧪 Testing Each Enhancement

### Test CSRF Protection

```
1. Open registration.php
2. Check HTML source - should see hidden CSRF token
3. Modify token in browser console
4. Try to submit form
Expected: Request rejected with security error
```

### Test OTP Rate Limiting

```
1. Register and get to OTP page
2. Try entering wrong OTP 6 times
3. On 6th attempt, should see cooldown message
Expected: "Too many attempts. Try again in XX minutes"
```

### Test Input Sanitization

```
1. Try entering script tags in name field: <script>alert('test')</script>
2. Check browser console before submission
Expected: Sanitized to safe text
```

### Test Session Fixation Prevention

```
1. Note the session ID (Set-Cookie header)
2. Accept privacy consent
3. Check session ID changes
Expected: New session ID assigned
```

---

## 📊 Deployment Checklist

- [ ] Backup current registration files
- [ ] Update registration.php with CSRF + CSP headers
- [ ] Update process_registration.php with CSRF validation + OTP hashing
- [ ] Update verify_otp.php with rate limiting
- [ ] Update registration.js with sanitization
- [ ] Update process_consent.php with session regeneration
- [ ] Run database migration script
- [ ] Test all 6 registration steps
- [ ] Test OTP verification
- [ ] Test with SQL injection payloads
- [ ] Test with XSS payloads
- [ ] Deploy to staging
- [ ] Monitor error logs for 48 hours
- [ ] Deploy to production

---

## 🆘 Troubleshooting

### "CSRF token missing" error

- Check form includes: `<input type="hidden" name="csrf_token">`
- Verify SESSION is active
- Check session file permissions

### "OTP rate limit" message appears too quickly

- Check if attempt tracking is in $\_SESSION properly
- May need to clear browser cookies and restart
- Check time synchronization on server

### Sanitization breaking legitimate input

- Adjust regex patterns if needed
- Test with actual user data
- Log sanitized values to debug

---

## ✅ Success Criteria

After implementation, all of these should work:

```php
// Normal registration flow should work
✅ Register with valid data
✅ OTP verification succeeds
✅ Account activation works

// Security should be enforced
✅ CSRF attack blocked
✅ SQL injection blocked
✅ XSS injection blocked
✅ OTP brute force blocked
✅ Rate limiting active

// Logs should show security events
✅ Successful registrations logged
✅ Failed attempts logged
✅ Security errors logged
```

---

**Implementation Time:** 2-3 weeks  
**Testing Time:** 3-5 days  
**Rollback Plan:** Keep backup of original files  
**Support:** Check error logs at `error_log` and `security_errors.log`
