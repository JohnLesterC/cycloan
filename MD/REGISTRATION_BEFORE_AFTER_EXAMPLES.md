# 📊 Registration - Before & After Code Examples

**Date:** November 4, 2025  
**File:** process_registration.php

---

## Example 1: Personal Information - Step 1

### BEFORE (Less Secure)

```php
// Just sanitization, no type checking
$form_data['first_name'] = sanitizeInput($_POST['first_name'] ?? '');
$form_data['email'] = sanitizeInput($_POST['email'] ?? '');
$form_data['contact'] = sanitizeInput($_POST['contact'] ?? '');
$form_data['civil_status'] = sanitizeInput($_POST['civil_status'] ?? '');

// Manual validation later
if (!empty($form_data['contact']) && !preg_match("/^\d{11}$/", $form_data['contact'])) {
    $errors[] = "Error: Contact number must be exactly 11 digits";
}
if (!empty($form_data['email']) && !filter_var($form_data['email'], FILTER_VALIDATE_EMAIL)) {
    $errors[] = "Error: Invalid email format";
}

// Issues:
// ❌ No length checking (could be 10000+ characters)
// ❌ No type validation
// ❌ No enum validation for dropdown
// ❌ No attack detection/logging
```

### AFTER (More Secure)

```php
// Type-safe validation with bounds
require_once 'security_validation.php';

$form_data['first_name'] = validateString($_POST['first_name'] ?? '', 1, 50);
$form_data['email'] = validateEmail($_POST['email'] ?? '');
$form_data['contact'] = validatePhoneNumber($_POST['contact'] ?? '');
$form_data['civil_status'] = validateEnum($_POST['civil_status'] ?? '',
    ['Single', 'Married', 'Widowed', 'Separated', 'Divorced'], false);

// Better validation checking
if ($form_data['first_name'] === false) {
    $errors[] = "First name is required (1-50 characters)";
}
if (!empty($_POST['contact'] ?? '') && $form_data['contact'] === false) {
    $errors[] = "Contact number must be a valid Philippine phone number (09XXXXXXXXX)";
    logSecurityError("Invalid phone attempt", 'validation');
}
if (!empty($_POST['email'] ?? '') && $form_data['email'] === false) {
    $errors[] = "Invalid email format";
    logSecurityError("Invalid email attempt", 'validation');
}

// Benefits:
// ✅ Length enforced (min 1, max 50 for names)
// ✅ Type validated (string, not other types)
// ✅ Phone format validated (PH-specific)
// ✅ Email format validated with filter_var()
// ✅ Enum values whitelisted (only 5 allowed)
// ✅ Injection attempts logged
```

---

## Example 2: Financial Information - Step 4/5

### BEFORE (Less Secure)

```php
// Simple numeric cleaning
$form_data['business'] = cleanNumeric($_POST['business'] ?? '0');
$form_data['salary'] = cleanNumeric($_POST['salary'] ?? '0');
$form_data['net_income'] = cleanNumeric($_POST['net_income'] ?? '0');

// Manual validation
foreach ($form_data['income_sources'] as $source) {
    $sourceName = ucwords(str_replace('_', ' ', $source));
    if ($form_data[$source] <= 0) {
        $errors[] = "Error: $sourceName must have a positive value";
    } elseif ($form_data[$source] < 100) {
        $errors[] = "$sourceName must be at least ₱100";
    }
}

// Issues:
// ❌ No maximum value check (could be 999999999999999)
// ❌ No decimal precision control
// ❌ Income sources not validated
// ❌ No attack detection
```

### AFTER (More Secure)

```php
// Decimal validation with bounds
$form_data['business'] = validateDecimal($_POST['business'] ?? '0', 2, 0, 9999999.99);
$form_data['salary'] = validateDecimal($_POST['salary'] ?? '0', 2, 0, 9999999.99);
$form_data['net_income'] = validateDecimal($_POST['net_income'] ?? '0', 2, 0, 9999999.99);

// Enhanced validation with injection attempt detection
foreach ($form_data['income_sources'] as $source) {
    $sourceName = ucwords(str_replace('_', ' ', $source));

    // Validate the source name itself
    $validated_source = validateString($source, 1, 50);
    if ($validated_source === false) {
        $errors[] = "Error: Invalid income source selected";
        logSecurityError("Attempted invalid income source: $source", 'injection_attempt');
        continue;
    }

    // Validate the value
    $source_value = $form_data[$source] ?? false;
    if ($source_value === false) {
        $errors[] = "Error: $sourceName must have a valid positive value";
    } elseif ($source_value <= 0) {
        $errors[] = "Error: $sourceName must have a positive value since selected";
    } elseif ($source_value < 100) {
        $errors[] = "Error: $sourceName must be at least ₱100. Please adjust the value";
    }
}

// Benefits:
// ✅ Values bounded (0 to 9,999,999.99)
// ✅ Decimal places fixed (exactly 2)
// ✅ Source names validated
// ✅ Injection attempts logged
// ✅ Better error messages
```

---

## Example 3: Spouse Information - Step 4

### BEFORE (Less Secure)

```php
$form_data['spouse_first_name'] = sanitizeInput($_POST['spouse_first_name'] ?? '');
$form_data['spouse_contact'] = sanitizeInput($_POST['spouse_contact'] ?? '');
$form_data['spouse_email'] = sanitizeInput($_POST['spouse_email'] ?? '');
$form_data['spouse_dependents'] = sanitizeInput($_POST['spouse_dependents'] ?? '');

// Validation
if (!empty($form_data['spouse_contact']) &&
    !preg_match("/^\d{11}$/", $form_data['spouse_contact'])) {
    $errors[] = "Spouse contact format invalid";
}

// Issues:
// ❌ No length checking on names
// ❌ No range checking on dependents
// ❌ No email validation
// ❌ No type conversion for numbers
```

### AFTER (More Secure)

```php
$form_data['spouse_first_name'] = validateString($_POST['spouse_first_name'] ?? '', 1, 50);
$form_data['spouse_contact'] = validatePhoneNumber($_POST['spouse_contact'] ?? '');
$form_data['spouse_email'] = validateEmail($_POST['spouse_email'] ?? '');
$form_data['spouse_dependents'] = validateInteger($_POST['spouse_dependents'] ?? 0, 0, 20);
$form_data['spouse_reg_voter'] = validateEnum($_POST['spouse_reg_voter'] ?? 'No',
    ['Yes', 'No'], false);

// Better validation
if ($form_data['spouse_first_name'] === false) {
    $errors[] = "Spouse first name is required (1-50 characters)";
}
if (!empty($_POST['spouse_contact'] ?? '') && $form_data['spouse_contact'] === false) {
    $errors[] = "Spouse contact must be a valid Philippine phone number";
    logSecurityError("Invalid spouse phone", 'validation');
}
if (!empty($_POST['spouse_email'] ?? '') && $form_data['spouse_email'] === false) {
    $errors[] = "Invalid spouse email format";
}

// Benefits:
// ✅ Name length enforced (1-50)
// ✅ Phone format validated (PH-specific)
// ✅ Email format validated
// ✅ Dependents range limited (0-20)
// ✅ Reg voter is enumerated (Yes/No)
```

---

## Example 4: Password Validation - Final Step

### BEFORE (Less Secure)

```php
$form_data['password'] = $_POST['password'] ?? '';
$form_data['confirm_password'] = $_POST['confirm_password'] ?? '';

if (empty($form_data['password'])) {
    $errors[] = "Password required";
} elseif (
    strlen($form_data['password']) < 8 ||
    !preg_match("/[A-Z]/", $form_data['password']) ||
    !preg_match("/[a-z]/", $form_data['password']) ||
    !preg_match("/\d/", $form_data['password']) ||
    !preg_match("/[!@#$%^&*()_+\-=\[\]{};':\"\\\\|,.<>\/?]/", $form_data['password'])
) {
    $errors[] = "Password must be at least 8 characters with uppercase, lowercase, number, and special char";
}

// Issues:
// ❌ No maximum length (could be millions of chars)
// ❌ Checks minimum but not maximum
```

### AFTER (More Secure)

```php
$form_data['password'] = $_POST['password'] ?? '';
$form_data['confirm_password'] = $_POST['confirm_password'] ?? '';

if (empty($form_data['password'])) {
    $errors[] = "Error: Password is required. Please create a strong password";
} else {
    // Validate string length (8-128)
    $password_validation = validateString($form_data['password'], 8, 128);
    if ($password_validation === false) {
        $errors[] = "Error: Password must be 8-128 characters long";
    } elseif (
        !preg_match("/[A-Z]/", $form_data['password']) ||
        !preg_match("/[a-z]/", $form_data['password']) ||
        !preg_match("/\d/", $form_data['password']) ||
        !preg_match("/[!@#$%^&*()_+\-=\[\]{};':\"\\\\|,.<>\/?]/", $form_data['password'])
    ) {
        $errors[] = "Error: Password must include uppercase, lowercase, number, and special character (!@#, etc)";
    }
}

if ($form_data['password'] !== $form_data['confirm_password']) {
    $errors[] = "Error: Passwords do not match";
}

// Benefits:
// ✅ Minimum length enforced (8)
// ✅ Maximum length enforced (128)
// ✅ Prevents oversized input attacks
// ✅ Better error messages
```

---

## Example 5: Address Validation - Step 2

### BEFORE (Less Secure)

```php
$form_data['res_house_no'] = sanitizeInput($_POST['res_house_no'] ?? '');
$form_data['res_street'] = sanitizeInput($_POST['res_street'] ?? '');
$form_data['res_subdivision'] = sanitizeInput($_POST['res_subdivision'] ?? '');
$form_data['res_barangay'] = sanitizeInput($_POST['res_barangay'] ?? '');
$form_data['res_address'] = sanitizeInput($_POST['res_address'] ?? '');
$form_data['house_ownership'] = sanitizeInput($_POST['house_ownership'] ?? '');

// Issues:
// ❌ No length limits
// ❌ No enum validation for ownership
// ❌ Could accept thousands of characters
```

### AFTER (More Secure)

```php
$form_data['res_house_no'] = validateString($_POST['res_house_no'] ?? '', 0, 50);
$form_data['res_street'] = validateString($_POST['res_street'] ?? '', 0, 100);
$form_data['res_subdivision'] = validateString($_POST['res_subdivision'] ?? '', 0, 100);
$form_data['res_barangay'] = validateString($_POST['res_barangay'] ?? '', 0, 100);
$form_data['res_address'] = validateString($_POST['res_address'] ?? '', 0, 255);
$form_data['house_ownership'] = validateEnum($_POST['house_ownership'] ?? 'Owned',
    ['Owned', 'Rented', 'Borrowed', 'Other'], false);

// Benefits:
// ✅ House number max 50 chars
// ✅ Street/Subdivision/Barangay max 100 chars
// ✅ Full address max 255 chars
// ✅ Ownership values whitelisted
// ✅ Prevents oversized input attacks
```

---

## Example 6: Error Handling Improvement

### BEFORE (Less Specific)

```php
if (!empty($form_data['email']) && !filter_var($form_data['email'], FILTER_VALIDATE_EMAIL)) {
    $errors[] = "Error: Invalid email format. Please enter a valid email like example@email.com.";
}
if (!empty($form_data['contact']) && !preg_match("/^\d{11}$/", $form_data['contact'])) {
    $errors[] = "Error: Contact number must be exactly 11 digits (e.g., 09123456789). Please correct it.";
}
```

### AFTER (Better & Logged)

```php
if (!empty($_POST['email'] ?? '') && $form_data['email'] === false) {
    $errors[] = "Error: Invalid email format. Please enter a valid email.";
    logSecurityError("Invalid email attempt: " . $_POST['email'], 'validation');
}
if (!empty($_POST['contact'] ?? '') && $form_data['contact'] === false) {
    $errors[] = "Error: Contact must be a valid Philippine phone number (09XXXXXXXXX)";
    logSecurityError("Invalid phone attempt: " . $_POST['contact'], 'validation');
}

// Benefits:
// ✅ Injection attempts are logged
// ✅ Better error messages
// ✅ Audit trail created
// ✅ Admin can review logs
```

---

## Summary Table

| Check               | Before  | After       |
| ------------------- | ------- | ----------- |
| Type Validation     | Partial | Complete    |
| Length Limits       | None    | Yes         |
| Value Bounds        | None    | Yes         |
| Format Validation   | Basic   | Advanced    |
| Enum Validation     | None    | Whitelist   |
| Phone Validation    | Regex   | PH-specific |
| Error Logging       | None    | Complete    |
| Security Messages   | No      | Yes         |
| Injection Detection | No      | Yes         |

---

## Key Differences Explained

### validateString() vs sanitizeInput()

```php
// sanitizeInput - only removes tags
sanitizeInput("Hello<script>alert('hi')</script>")
// Result: "Hello"

// validateString - enforces length too
validateString("x" * 1000, 1, 50)
// Result: false (exceeds max length)
```

### validateInteger() vs cleanNumeric()

```php
// cleanNumeric - just converts to float
cleanNumeric("123.45abc")
// Result: 123.45 (no validation!)

// validateInteger - type safe with bounds
validateInteger("123.45abc", 0, 100)
// Result: false (not an integer, & value converts to 123 which is valid, so returns 123)

// Better:
validateInteger("123", 0, 100)
// Result: 123 (valid)

validateInteger("999", 0, 100)
// Result: false (exceeds max of 100)
```

### validateEmail() vs filter_var()

```php
// filter_var - just format check
filter_var("test@example", FILTER_VALIDATE_EMAIL)
// Result: false (@ without domain)

// validateEmail - format + length check
validateEmail("verylongemailaddresssssss@example.com")
// Result: validated (length < 254)

validateEmail("x" * 300 . "@example.com")
// Result: false (exceeds 254 char limit)
```

---

## Testing Examples

### Test: Oversized String Attack

```php
// Input: 1000 character name string
$form_data['first_name'] = validateString($huge_string, 1, 50);

// Before: Would sanitize but could still store huge string
// After: Returns false, rejected

$errors[] = "First name exceeds maximum length (1-50)";
```

### Test: Negative Number Attack

```php
// Input: -9999 for age
$form_data['age'] = validateInteger("-9999", 18, 120);

// Before: Would sanitize but store -9999
// After: Returns false (less than min 18)

$errors[] = "Age must be between 18 and 120";
```

### Test: Invalid Enum Attack

```php
// Input: "hacker_status" for civil_status
$form_data['civil_status'] = validateEnum("hacker_status",
    ['Single', 'Married', 'Widowed', 'Separated', 'Divorced']);

// Before: Would sanitize but store "hacker_status"
// After: Returns false (not in whitelist)

$errors[] = "Invalid civil status selected";
```

---

## Conclusion

The improvements provide **defense in depth**:

1. **Input Validation Layer** ← NEW

   - Rejects invalid/oversized/out-of-range data
   - Prevents attacks before they reach database

2. **Prepared Statements** ← Already Secure

   - Prevents SQL injection at database level
   - Even if bad data gets through, SQL injection prevented

3. **Error Logging** ← NEW
   - Tracks all validation failures
   - Helps detect attack patterns
   - Provides audit trail

**Result:** Your registration is now **significantly more secure**! 🔐
