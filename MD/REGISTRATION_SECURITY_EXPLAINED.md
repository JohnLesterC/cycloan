# 🛡️ CYCLOAN Registration Security - Complete Explanation

**Date:** November 4, 2025  
**Purpose:** Explain all security features protecting the registration system

---

## 📋 Table of Contents

1. [Overview](#overview)
2. [SQL Injection Prevention](#sql-injection-prevention)
3. [Input Validation](#input-validation)
4. [Password Security](#password-security)
5. [Data Protection](#data-protection)
6. [Email Verification](#email-verification)
7. [Error Handling](#error-handling)
8. [Session Security](#session-security)
9. [Security Checklist](#security-checklist)

---

## 🎯 Overview

Your registration system now has **9 layers of security** protecting user data:

```
User Input
    ↓
[Layer 1: Input Type Checking]
    ↓
[Layer 2: Input Validation]
    ↓
[Layer 3: Format Sanitization]
    ↓
[Layer 4: XSS Prevention]
    ↓
[Layer 5: SQL Injection Prevention]
    ↓
[Layer 6: Database Storage]
    ↓
[Layer 7: Password Hashing]
    ↓
[Layer 8: Email Verification]
    ↓
[Layer 9: Error Logging]
    ↓
User Registered & Verified ✅
```

---

## 🔒 1. SQL Injection Prevention

### What is SQL Injection?

An attacker tries to enter malicious SQL code instead of data:

```
VULNERABLE (BAD ❌):
User enters: ' OR '1'='1
Query becomes: SELECT * FROM users WHERE email = '' OR '1'='1'
Result: Returns ALL users in database!
```

### How We Prevent It

#### ✅ Solution 1: Prepared Statements

All database queries use prepared statements with parameter binding:

```php
// SECURE ✅ - Prepared Statement
$stmt = $conn->prepare("SELECT COUNT(*) FROM users1 WHERE email = ?");
$stmt->bind_param("s", $form_data['email']);  // "s" = string type
$stmt->execute();

// The ? placeholder is NEVER replaced with user data
// User input is sent SEPARATELY to the database
// The database KNOWS this is data, not SQL code
```

**Why this works:**

- Database treats user input as DATA, not code
- Even if user enters `' OR '1'='1`, it's stored as literal text
- Attacker cannot execute SQL commands

#### ✅ Solution 2: Parameterized Insert Statements

When creating new users, we use multiple placeholders:

```php
// SECURE ✅
$stmt = $conn->prepare("INSERT INTO users1
    (first_name, email, password, ...)
    VALUES (?, ?, ?, ...)");

$stmt->bind_param(
    "sss",  // 3 strings
    $first_name,
    $email,
    $hashed_password
);
$stmt->execute();

// Each ? is filled with safe, validated data
```

**Parameter Types Used:**

- `s` = String (names, emails, addresses)
- `i` = Integer (age, year_resident)
- `d` = Double/Float (income, expenses)
- `b` = Blob (binary data)

#### ❌ Vulnerable Pattern (What We DON'T Do)

```php
// VULNERABLE ❌ - NEVER DO THIS!
$email = $_POST['email'];
$query = "SELECT * FROM users1 WHERE email = '" . $email . "'";
// If user enters: admin'--
// Query becomes: SELECT * FROM users1 WHERE email = 'admin'--'
// The -- comments out the password check!
```

---

## ✓ 2. Input Validation

### What is Input Validation?

Checking that user input matches what we expect (right type, right format, right length).

### Validation Types Used

#### ✅ String Validation

```php
validateString($_POST['first_name'], 1, 50)
// Checks:
// - Type is string ✓
// - Length between 1-50 characters ✓
// - Trimmed of leading/trailing spaces ✓
// - Not empty ✓

Example:
"  John  " → "John" ✓
"" → false ❌
"This name is way too long and exceeds 50 characters" → false ❌
```

**Applied to:**

- first_name, middle_name, last_name (1-50 chars)
- occupation, fb_account (0-100 chars)
- birth_place, addresses (0-255 chars)

#### ✅ Email Validation

```php
validateEmail($_POST['email'])
// Checks:
// - Valid email format (user@domain.com) ✓
// - No injection characters ✓
// - Length ≤ 254 characters ✓
// - Case normalized (lowercase) ✓

Examples:
"john@example.com" → "john@example.com" ✓
"invalid-email" → false ❌
"' OR '1'='1@example.com" → false ❌
"JOHN@EXAMPLE.COM" → "john@example.com" ✓ (normalized)
```

#### ✅ Phone Number Validation (Philippines-Specific)

```php
validatePhoneNumber($_POST['contact'])
// Checks:
// - Valid Philippine format ✓
// - Accepts: 09XXXXXXXXX, +639XXXXXXXXX, 639XXXXXXXXX ✓
// - Normalizes to 09 format ✓

Examples:
"09123456789" → "09123456789" ✓
"+639123456789" → "09123456789" ✓
"12345" → false ❌
"abcdefghijk" → false ❌
```

#### ✅ Date Validation

```php
validateDate($_POST['birthday'], 'Y-m-d')
// Checks:
// - Valid date format (YYYY-MM-DD) ✓
// - Date actually exists (catches 2025-02-30) ✓
// - Format matches exactly ✓

Examples:
"1990-05-15" → "1990-05-15" ✓
"1990-13-01" → false ❌ (month 13 doesn't exist)
"1990-02-30" → false ❌ (Feb 30 doesn't exist)
"05/15/1990" → false ❌ (wrong format)
```

#### ✅ Integer Validation (Age, Years Resident)

```php
validateInteger($_POST['age'], 18, 120)
// Checks:
// - Type is integer ✓
// - Between 18-120 range ✓

Examples:
"25" → 25 ✓
"17" → false ❌ (below minimum)
"150" → false ❌ (exceeds maximum)
"25.5" → false ❌ (not integer)
```

#### ✅ Decimal Validation (Income, Expenses)

```php
validateDecimal($_POST['business_income'], 2, 0, 9999999.99)
// Checks:
// - Type converts to float/decimal ✓
// - 2 decimal places maximum ✓
// - Between 0 - 9,999,999.99 ✓
// - SANITIZES common formats (NEW!) ✓

Examples (with sanitization):
"1000" → 1000.00 ✓
"1,000" → 1000.00 ✓ (comma removed)
"₱1,000.00" → 1000.00 ✓ (symbol removed)
"$1 000" → 1000.00 ✓ (all formatting removed)
"-50" → false ❌ (below minimum of 0)
"10000000" → false ❌ (exceeds maximum)

// Sanitization removes: ,  ₱  $  €  spaces, etc.
// Then validates numeric value
```

#### ✅ Enum/Choice Validation

```php
validateEnum($_POST['civil_status'], ['Single', 'Married', 'Widowed', 'Separated', 'Divorced'])
// Checks:
// - Value is in whitelist ✓
// - Only allowed choices accepted ✓

Examples:
"Single" → "Single" ✓
"Married" → "Married" ✓
"Invalid" → false ❌
"'; DROP TABLE users;" → false ❌
```

**Applied to:**

- civil_status: Single, Married, Widowed, Separated, Divorced
- house_ownership: Owned, Rented, Borrowed, Other
- income_sources: business, salary, remittance, other_income, business2, salary2
- expenditure_types: food_allowance, electricity_bill, water_bill, internet_bill, gas_bill, educational_allowance, car_amortization, insurance, other_expense
- reg_voter: Yes, No

---

## 🔐 3. Password Security

### ✅ Multi-Layer Password Protection

#### Layer 1: Password Strength Requirements

Passwords must have:

- **Minimum 8 characters** (up to 128)
- **At least one UPPERCASE letter** (A-Z)
- **At least one lowercase letter** (a-z)
- **At least one NUMBER** (0-9)
- **At least one SPECIAL character** (!@#$%^&\*()\_+-=[]{}';:"\\|,.<>/?}

**Examples:**

```
"Password123!" → ✅ Strong (all requirements met)
"password123!" → ❌ Missing uppercase
"PASSWORD123!" → ❌ Missing lowercase
"PasswordAbc" → ❌ Missing number
"Password12" → ❌ Missing special character
"Pass1!" → ❌ Too short
```

#### Layer 2: Password Hashing

Passwords are never stored in plain text:

```php
// SECURE ✅
$hashed = password_hash($form_data['password'], PASSWORD_DEFAULT);
// Uses bcrypt algorithm
// Result: $2y$10$... (60 characters, one-way hash)

// Storage:
// User enters: MyPassword123!
// Stored as:  $2y$10$...encrypted...
// Cannot be reversed to see original password
```

**Why bcrypt is secure:**

- One-way hashing (cannot decrypt)
- Salting (adds randomness)
- Slowing (takes 0.2-1 second to hash)
- Brute force attacks take centuries

#### Layer 3: Password Matching

Both passwords must match before storage:

```php
if ($form_data['password'] !== $form_data['confirm_password']) {
    // Error: Passwords do not match
}

// Prevents typos from locking user out
```

---

## 🛡️ 4. Data Protection

### ✅ Database Separation

Different data stored in separate tables with relationships:

```
users1 table (personal info)
├── user_id (PK)
├── first_name, middle_name, last_name
├── email (UNIQUE - no duplicates)
├── password (hashed)
└── ...personal details...

spouses table (spouse info, only if married)
├── spouse_id (PK)
├── user_id (FK → users1)
├── first_name, middle_name, last_name
└── ...spouse details...

financial_info table (income/expenses)
├── financial_info_id (PK)
├── user_id (FK → users1)
├── business_income
├── salary_income
└── ...financial details...

income_sources table (selected income types)
├── income_source_id (PK)
├── user_id (FK → users1)
└── source_type (business, salary, etc.)

expenditure_types table (selected expense types)
├── expenditure_type_id (PK)
├── user_id (FK → users1)
└── expense_type (food, electricity, etc.)
```

**Benefits:**

- Data organized logically
- Easy to query and update
- Relationships enforced
- Redundancy minimized

### ✅ Unique Email Constraint

Before creating user, we check:

```php
// Check if email already exists
$stmt = $conn->prepare("SELECT COUNT(*) FROM users1 WHERE email = ?");
$stmt->bind_param("s", $email);
$stmt->execute();
$stmt->bind_result($email_count);
$stmt->fetch();

if ($email_count > 0) {
    // Error: Email already registered
}

// Database also has UNIQUE constraint on email column
// Double protection: application + database level
```

### ✅ Default Profile Image

New users get a default avatar:

```php
$profile_image = '/assets/default.jpg';
// User can update later
```

---

## ✉️ 5. Email Verification (OTP System)

### ✅ OTP Generation

6-digit One-Time Password generated securely:

```php
function generateOTP()
{
    return str_pad(mt_rand(0, 999999), 6, '0', STR_PAD_LEFT);
}

// Generates: 000000 to 999999
// Uses mt_rand (better randomness than rand)
// Example: 487629
```

### ✅ OTP Storage & Expiration

OTPs stored in database with 10-minute expiration:

```php
$otp = generateOTP();                                    // Generate
$expires_at = (new DateTime())->modify('+10 minutes')   // Expiry time
              ->format('Y-m-d H:i:s');

// Store in otps table
$stmt = $conn->prepare("INSERT INTO otps (user_id, otp_code, expires_at) VALUES (?, ?, ?)");
$stmt->bind_param("iss", $user_id, $otp, $expires_at);
$stmt->execute();

// After 10 minutes, OTP is expired and cannot be used
```

### ✅ Email Sending (PHPMailer)

OTP sent via secure email with professional template:

```php
// Uses Gmail SMTP (secure encryption)
// Professional HTML template (not plain text)
// Contains CYCLOAN branding and support info
// User must verify email before account activation

// Email includes:
// - Welcome message
// - 6-digit OTP code
// - 10-minute validity notice
// - Security warnings
// - Support contact info
```

### ✅ User Account Status

New users start as inactive:

```php
// During registration insert:
$stmt->bind_param("...0...", ..., 0, ...);  // is_active = 0

// Only activated after email OTP verification
// User cannot log in until verified
```

---

## 🚨 6. Error Handling

### ✅ User-Friendly Error Messages

Users see helpful messages without exposing system details:

```php
// SECURE ✅ - User sees:
"Error: First name is required (1-50 characters)."
"Error: Invalid email format. Please enter a valid email."
"Error: Password must include uppercase, lowercase, number, and special character."

// VULNERABLE ❌ - We DON'T show:
"Database connection failed at line 45"
"Unexpected error in mysqli->prepare(): Syntax error in query"
"Table 'cycloan_db.users1' doesn't have a column named 'emai1'"
```

### ✅ Security Error Logging

Suspicious attempts logged securely:

```php
logSecurityError("Attempted invalid income source: $source", 'injection_attempt');
// Logs to: security_errors.log (NOT visible to user)
// Format: [Timestamp] Type: injection_attempt | User: ID | IP: XXX.XXX.XXX.XXX | Message: ...
// Admins can review to detect attacks
```

### ✅ Error Redirection

On errors, user stays on current step to fix issues:

```php
// If validation fails on step 4 (spouse info):
$_SESSION['error_message'] = "Error: Spouse last name is required...";
header("Location: registration.php?step=4");
// User can correct and resubmit
// Form data preserved in session
```

---

## 🔑 7. Session Security

### ✅ Multi-Step Form State

Form data stored in session (server-side, not client-side):

```php
// Each step stores data:
$_SESSION['form_data']['first_name'] = "John";
$_SESSION['form_data']['email'] = "john@example.com";
// ...etc...

// Data NEVER exposed to client
// Only session ID sent in cookie (cannot be modified)
```

### ✅ Session Initialization

Session starts at top of file:

```php
session_start();  // Initializes secure session
// Session ID generated by server
// User cannot forge session ID
```

### ✅ Timezone Management

All timestamps use Philippine Time:

```php
require_once 'timezone_config.php';
// Sets: date_default_timezone_set('Asia/Manila');
// All database timestamps: consistent timezone
// No confusion between server time and local time
```

### ✅ Fresh Redirect Flag

Prevents form resubmission:

```php
$_SESSION['fresh_redirect'] = true;
// After successful step, redirect to next
// If user refreshes, form doesn't resubmit
// Prevents duplicate data entries
```

---

## 📋 8. XSS Prevention (Cross-Site Scripting)

### ✅ Output Escaping

User data displayed safely:

```php
// SECURE ✅
$name = escapeOutput($_SESSION['first_name']);
// Converts: <script>alert('XSS')</script>
// To:       &lt;script&gt;alert('XSS')&lt;/script&gt;
// Browser displays as text, not code

// VULNERABLE ❌ - We DON'T do:
echo $_SESSION['first_name'];  // User can inject JavaScript
```

### ✅ Email Body Escaping

OTP email encodes special characters:

```php
$mail->Body = '...
<h1>Welcome, ' . htmlspecialchars($name) . '!</h1>
<div class="otp-box">' . htmlspecialchars($otp) . '</div>
...';

// htmlspecialchars prevents email injection
// User cannot inject malicious HTML into email
```

---

## ✅ 9. Security Checklist

### For Users

- [x] Strong password required (8+ chars, mixed case, numbers, symbols)
- [x] Password hashed using bcrypt (one-way encryption)
- [x] Email verification required (OTP)
- [x] Account starts inactive (cannot log in until verified)
- [x] Personal data protected with prepared statements
- [x] Financial data separated from personal data
- [x] No duplicate emails allowed
- [x] User-friendly error messages

### For Administrators

- [x] All database queries use prepared statements
- [x] All input validated before use
- [x] Sensitive errors logged securely
- [x] Attack attempts tracked in security log
- [x] Database uses relationships and constraints
- [x] Session data server-side (not client-side)
- [x] Timezone consistency (Asia/Manila)
- [x] XSS prevention through output escaping

### For Developers

- [x] Include `security_validation.php` for validation functions
- [x] Use prepared statements for ALL database queries
- [x] Validate input with appropriate validator functions
- [x] Log suspicious activity with `logSecurityError()`
- [x] Display safe error messages to users
- [x] Never trust `$_POST` or `$_GET` data
- [x] Escape output before displaying
- [x] Use enum validation for restricted choices

---

## 🔍 Validation Rules by Field Type

### Text Fields

```
first_name, middle_name, last_name → 1-50 chars, string
occupation, fb_account → 0-100 chars, string
birth_place → 0-100 chars, string
address fields → 0-255 chars, string
```

### Email & Phone

```
email → valid email format, unique, ≤254 chars
contact → valid Philippine format (09XXXXXXXXX)
```

### Date & Age

```
birthday → YYYY-MM-DD format, valid date
age → 18-120 years
year_resident → 0-150 years
```

### Financial/Numeric

```
business_income → 0-9,999,999.99, decimal with comma support
salary_income → 0-9,999,999.99
expenses → 500+ minimum if selected
net_income → 0-9,999,999.99
```

### Choices (Enum)

```
civil_status → Single, Married, Widowed, Separated, Divorced
house_ownership → Owned, Rented, Borrowed, Other
income_sources → business, salary, remittance, other_income, business2, salary2
expenditure_types → food_allowance, electricity_bill, water_bill, internet_bill, etc.
```

---

## 🎓 Summary

Your registration system is protected by:

| Layer            | Protection                | Example                           |
| ---------------- | ------------------------- | --------------------------------- |
| **Input**        | Type checking             | "25" must be integer              |
| **Format**       | Pattern matching          | Email must be user@domain.com     |
| **Sanitization** | Remove harmful characters | "1,000" becomes 1000              |
| **Database**     | Prepared statements       | SQL code separated from data      |
| **Hashing**      | One-way encryption        | Password never stored plain       |
| **Verification** | Email OTP                 | User proves email ownership       |
| **Activation**   | Account status            | User cannot log in until verified |
| **Logging**      | Security tracking         | Attacks logged for review         |
| **XSS**          | Output escaping           | User data displayed as text       |

---

## 📞 Support

**If you encounter validation errors:**

1. Check the error message shown on the form
2. Review the validation rules above
3. Ensure input matches required format
4. Contact support if issue persists

**If you're a developer:**

1. Read the SQL_INJECTION_PREVENTION_GUIDE.md for detailed patterns
2. Always use prepared statements
3. Always validate input using security_validation.php functions
4. Log suspicious attempts with logSecurityError()

---

**Last Updated:** November 4, 2025  
**Status:** ✅ Production Ready
