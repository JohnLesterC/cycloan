# 🔒 SQL Injection - Before & After Code Examples

**Date:** November 4, 2025  
**Purpose:** Show exact code changes needed

---

## Example 1: Simple SELECT Query

### ❌ VULNERABLE CODE (BEFORE)

```php
<?php
session_start();
require 'CYCLOAN_db.php';

$email = $_POST['email'];
$password = $_POST['password'];

// VULNERABLE - Direct string concatenation!
$query = "SELECT id, password FROM users1 WHERE email = '" . $email . "'";
$result = mysqli_query($conn, $query);

if (mysqli_num_rows($result) > 0) {
    $user = mysqli_fetch_assoc($result);
    if (password_verify($password, $user['password'])) {
        $_SESSION['user_id'] = $user['id'];
        header("Location: dashboard.php");
    } else {
        echo "Invalid password";
    }
}

?>
```

**Vulnerability:** An attacker could enter: `' OR '1'='1` as email and bypass login

---

### ✅ SECURE CODE (AFTER)

```php
<?php
session_start();
require 'CYCLOAN_db.php';
require_once 'security_validation.php';

// Validate email
$email = validateEmail($_POST['email']);
if (!$email) {
    logSecurityError('Invalid email format attempted', 'validation');
    die('Invalid email format');
}

// Validate password is not empty
$password = isset($_POST['password']) ? $_POST['password'] : '';
if (empty($password)) {
    logSecurityError('Empty password attempted', 'validation');
    die('Password required');
}

// Use prepared statement
$stmt = $conn->prepare("SELECT id, password FROM users1 WHERE email = ?");
if (!$stmt) {
    error_log("Prepare failed: " . $conn->error);
    die('Database error');
}

$stmt->bind_param("s", $email);  // "s" = string type

if (!$stmt->execute()) {
    error_log("Execute failed: " . $stmt->error);
    die('Database error');
}

$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $user = $result->fetch_assoc();

    if (password_verify($password, $user['password'])) {
        $_SESSION['user_id'] = $user['id'];
        $stmt->close();
        header("Location: dashboard.php");
        exit;
    } else {
        logSecurityError('Invalid password for email: ' . $email, 'login');
        die('Invalid password');
    }
} else {
    logSecurityError('Email not found: ' . $email, 'login');
    die('Email not found');
}

$stmt->close();

?>
```

**Security:** All input validated, query parameterized, errors logged

---

## Example 2: INSERT Multiple Values

### ❌ VULNERABLE CODE (BEFORE)

```php
<?php
require 'CYCLOAN_db.php';

$name = $_POST['name'];
$email = $_POST['email'];
$amount = $_POST['amount'];
$phone = $_POST['phone'];

// VULNERABLE - All user input directly in query!
$query = "INSERT INTO applicants (name, email, amount, phone)
          VALUES ('" . $name . "', '" . $email . "', " . $amount . ", '" . $phone . "')";

if (mysqli_query($conn, $query)) {
    echo "Record inserted";
} else {
    echo "Error: " . mysqli_error($conn);
}

?>
```

**Vulnerability:** Attacker could enter: `', (SELECT password FROM users WHERE 1=1)--` in name field

---

### ✅ SECURE CODE (AFTER)

```php
<?php
require 'CYCLOAN_db.php';
require_once 'security_validation.php';

// Validate all inputs
$name = validateString($_POST['name'], 2, 100);
$email = validateEmail($_POST['email']);
$amount = validateDecimal($_POST['amount'], 2, 0, 999999.99);
$phone = validatePhoneNumber($_POST['phone']);

// Check if all validations passed
if (!$name || !$email || !$amount || !$phone) {
    logSecurityError('Invalid applicant input data', 'validation');
    die('Invalid input data');
}

// Use prepared statement with parameterized query
$stmt = $conn->prepare("INSERT INTO applicants (name, email, amount, phone)
                        VALUES (?, ?, ?, ?)");

if (!$stmt) {
    error_log("Prepare failed: " . $conn->error);
    die('Database error');
}

// Bind parameters: "ssds" = string, string, double, string
$stmt->bind_param("ssds", $name, $email, $amount, $phone);

if (!$stmt->execute()) {
    error_log("Insert failed: " . $stmt->error);
    logSecurityError("Failed to insert applicant: $email", 'database');
    die('Failed to insert record');
}

$stmt->close();
echo "Record inserted successfully";

?>
```

**Security:** Each value validated before INSERT, parameterized query prevents injection

---

## Example 3: UPDATE with Enum Validation

### ❌ VULNERABLE CODE (BEFORE)

```php
<?php
require 'CYCLOAN_db.php';

$loan_id = $_POST['loan_id'];
$status = $_POST['status'];  // Could be anything!

// VULNERABLE - Status not validated!
$query = "UPDATE loans SET status = '" . $status . "' WHERE id = " . $loan_id;

mysqli_query($conn, $query);

?>
```

**Vulnerability:** Attacker could set any status value or inject SQL

---

### ✅ SECURE CODE (AFTER)

```php
<?php
require 'CYCLOAN_db.php';
require_once 'security_validation.php';

// Validate loan ID
$loan_id = validateInteger($_POST['loan_id'], 1);
if (!$loan_id) {
    logSecurityError('Invalid loan ID', 'validation');
    die('Invalid loan ID');
}

// Validate status against allowed values only
$allowed_statuses = ['pending', 'approved', 'rejected', 'paid', 'closed'];
$status = validateEnum($_POST['status'], $allowed_statuses);

if (!$status) {
    logSecurityError('Invalid status value: ' . $_POST['status'], 'injection_attempt');
    die('Invalid status');
}

// Use prepared statement
$stmt = $conn->prepare("UPDATE loans SET status = ?, updated_at = NOW() WHERE id = ?");

if (!$stmt) {
    error_log("Prepare failed: " . $conn->error);
    die('Database error');
}

// Bind parameters: "si" = string, integer
$stmt->bind_param("si", $status, $loan_id);

if (!$stmt->execute()) {
    error_log("Update failed: " . $stmt->error);
    die('Failed to update loan');
}

$stmt->close();
echo "Loan updated successfully";

?>
```

**Security:** Status validated against whitelist, ID validated as integer

---

## Example 4: Dynamic Table Names (CRITICAL FIX)

### ❌ VULNERABLE CODE (BEFORE)

```php
<?php
require 'CYCLOAN_db.php';

$role = $_SESSION['role'];  // Could be manipulated!
$email = $_POST['email'];

// EXTREMELY VULNERABLE - Table name from user session!
$query = "SELECT * FROM " . $role . " WHERE email = '" . $email . "'";
$result = mysqli_query($conn, $query);

?>
```

**Vulnerability:** If session is compromised, ANY table could be accessed

---

### ✅ SECURE CODE (AFTER)

```php
<?php
require 'CYCLOAN_db.php';
require_once 'security_validation.php';

// Get role from session (but still validate it!)
$role = $_SESSION['role'] ?? '';

// Whitelist of allowed tables
$allowed_tables = [
    'users1' => 'users1',
    'admin1' => 'admin1',
    'admin2' => 'admin2',
    'superadmins' => 'superadmins'
];

// Validate table name
$table_name = validateTableName($role, array_keys($allowed_tables));

if (!$table_name) {
    logSecurityError("Invalid table access attempt: $role", 'injection_attempt');
    die('Invalid access');
}

// Validate email
$email = validateEmail($_POST['email']);
if (!$email) {
    die('Invalid email');
}

// Now it's safe to use table name in query
$stmt = $conn->prepare("SELECT * FROM $table_name WHERE email = ?");

if (!$stmt) {
    error_log("Prepare failed: " . $conn->error);
    die('Database error');
}

$stmt->bind_param("s", $email);
$stmt->execute();
$result = $stmt->get_result();

?>
```

**Security:** Table name verified against whitelist, email parameterized

---

## Example 5: LIKE Search (Common Mistake)

### ❌ VULNERABLE CODE (BEFORE)

```php
<?php
require 'CYCLOAN_db.php';

$search = $_POST['search'];

// VULNERABLE - LIKE clause with unparameterized input!
$query = "SELECT * FROM loans WHERE applicant_name LIKE '%" . $search . "%'";
$result = mysqli_query($conn, $query);

?>
```

**Vulnerability:** Attacker could inject SQL in search field

---

### ✅ SECURE CODE (AFTER)

```php
<?php
require 'CYCLOAN_db.php';
require_once 'security_validation.php';

// Validate search term
$search = validateString($_POST['search'] ?? '', 1, 100);

if (!$search) {
    logSecurityError('Invalid search term', 'validation');
    die('Invalid search term');
}

// IMPORTANT: Add wildcards in the bind variable, not the query!
$search_param = "%" . $search . "%";

// Use prepared statement
$stmt = $conn->prepare("SELECT * FROM loans WHERE applicant_name LIKE ?");

if (!$stmt) {
    error_log("Prepare failed: " . $conn->error);
    die('Database error');
}

$stmt->bind_param("s", $search_param);
$stmt->execute();
$result = $stmt->get_result();

?>
```

**Security:** Search term validated and parameterized with LIKE

---

## Example 6: Multiple INSERT (Batch)

### ❌ VULNERABLE CODE (BEFORE)

```php
<?php
require 'CYCLOAN_db.php';

$documents = $_POST['documents'];  // Array from form

foreach ($documents as $doc) {
    $name = $doc['name'];
    $type = $doc['type'];
    $status = $doc['status'];

    // VULNERABLE - Repeated concatenation!
    $query = "INSERT INTO documents (name, type, status)
              VALUES ('" . $name . "', '" . $type . "', '" . $status . "')";

    mysqli_query($conn, $query);
}

?>
```

**Vulnerability:** Each insert vulnerable to SQL injection

---

### ✅ SECURE CODE (AFTER)

```php
<?php
require 'CYCLOAN_db.php';
require_once 'security_validation.php';

$documents = $_POST['documents'] ?? [];

// Prepare statement once, use multiple times
$stmt = $conn->prepare("INSERT INTO documents (name, type, status) VALUES (?, ?, ?)");

if (!$stmt) {
    error_log("Prepare failed: " . $conn->error);
    die('Database error');
}

$inserted = 0;
$failed = 0;

foreach ($documents as $doc) {
    // Validate each document
    $name = validateString($doc['name'] ?? '', 1, 255);
    $type = validateEnum($doc['type'] ?? '', ['pdf', 'image', 'document']);
    $status = validateEnum($doc['status'] ?? '', ['pending', 'approved', 'rejected']);

    if (!$name || !$type || !$status) {
        logSecurityError("Invalid document data: " . json_encode($doc), 'validation');
        $failed++;
        continue;
    }

    // Bind and execute
    $stmt->bind_param("sss", $name, $type, $status);

    if ($stmt->execute()) {
        $inserted++;
    } else {
        error_log("Insert failed: " . $stmt->error);
        $failed++;
    }
}

$stmt->close();

echo "Inserted: $inserted, Failed: $failed";

?>
```

**Security:** Each value validated, single prepared statement reused

---

## Example 7: Error Handling

### ❌ VULNERABLE CODE (BEFORE)

```php
<?php
require 'CYCLOAN_db.php';

$query = "SELECT * FROM users WHERE email = '" . $_POST['email'] . "'";
$result = mysqli_query($conn, $query);

if (!$result) {
    echo "Error: " . mysqli_error($conn);  // Exposes DB structure!
    exit;
}

?>
```

**Vulnerability:** Error messages expose database structure to attackers

---

### ✅ SECURE CODE (AFTER)

```php
<?php
require 'CYCLOAN_db.php';
require_once 'security_validation.php';

$email = validateEmail($_POST['email']);
if (!$email) {
    die('Invalid email');
}

$stmt = $conn->prepare("SELECT * FROM users WHERE email = ?");
$stmt->bind_param("s", $email);

if (!$stmt->execute()) {
    // Log error internally, don't show to user
    error_log("Database error for email lookup: " . $stmt->error, 3, 'db_errors.log');
    logSecurityError('Database error during lookup', 'database');

    // Show safe message to user
    die('An error occurred. Please try again later.');
}

$result = $stmt->get_result();

?>
```

**Security:** Errors logged but not exposed, user gets generic message

---

## 📊 Quick Comparison

| Aspect             | Before (Vulnerable)  | After (Secure)             |
| ------------------ | -------------------- | -------------------------- |
| Query Building     | String concatenation | Prepared statements        |
| Input Validation   | None                 | Full validation            |
| Type Safety        | No type checking     | Type binding (s, i, d)     |
| Error Messages     | Exposed to user      | Logged internally          |
| SQL Injection Risk | CRITICAL             | Eliminated                 |
| Performance        | Slower (no caching)  | Faster (query plan cached) |

---

## 🎓 Key Principles

1. **Never trust user input** - Always validate
2. **Always use prepared statements** - Non-negotiable
3. **Validate table/column names** - Use whitelist only
4. **Log errors securely** - Don't expose internals
5. **Test with injection payloads** - Verify fixes
6. **Code review** - Have another dev verify changes

---

**Status:** Ready to implement  
**Risk if not fixed:** CRITICAL  
**Time required:** 8-10 hours for all files
