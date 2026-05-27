# Parameterized Queries Explained - Deep Dive

## Quick Summary

**Parameterized queries** separate the SQL code structure from user data, making it impossible for attackers to inject malicious SQL commands.

---

## 🔄 How It Works: The 4-Step Process

### Step 1️⃣: Prepare (Send SQL Template)

```php
$stmt = $conn->prepare("SELECT * FROM users WHERE email = ?");
```

- **What happens**: PHP sends the SQL template to MySQL
- **MySQL sees**: "I need to SELECT from users table WHERE email matches something"
- **Placeholders**: The `?` tells MySQL "a value will come here later"
- **Status**: Query structure is now **locked** - cannot be changed

### Step 2️⃣: Compile (Database Compiles Structure)

```
MySQL Internal Processing:
┌─────────────────────────────────────────────┐
│ Incoming: SELECT * FROM users WHERE email=?│
├─────────────────────────────────────────────┤
│ Parse: Valid SQL structure ✓                │
│ Compile: Create execution plan              │
│ Lock: Structure cannot change               │
└─────────────────────────────────────────────┘
```

- MySQL **compiles** the query structure
- MySQL **creates an execution plan**
- The placeholder knows: "Position 1 expects a string value"

### Step 3️⃣: Bind (Supply Parameter Data)

```php
$email = "user@example.com";
$stmt->bind_param("s", $email);
//                 ↑    ↑
//                 |    └─ Your variable
//                 └────── Type: "s" = string
```

- **What happens**: Your data is attached to the query
- **Important**: Data is attached to the **already-compiled** query
- **Security**: Data can ONLY go where placeholders are
- **Type checking**: MySQL knows the expected type for each position

### Step 4️⃣: Execute (Run Query)

```php
$stmt->execute();
```

- **What happens**: MySQL runs the pre-compiled query
- **Insert data**: User data is inserted only in placeholder positions
- **Cannot execute**: New SQL commands in the data - impossible!

---

## 📋 Detailed Comparison: Vulnerable vs. Safe

### ❌ VULNERABLE: String Concatenation

```php
// User input from form
$user_email = $_POST['email'];  // e.g., "john@example.com"

// BAD: Building query by concatenating strings
$query = "SELECT * FROM users WHERE email = '" . $user_email . "'";
//        Directly inserting user input into SQL code
```

**What Actually Gets Built:**

```sql
SELECT * FROM users WHERE email = 'john@example.com'
```

**Attack Example:**

```php
$user_email = "' OR '1'='1";  // Attacker enters this
$query = "SELECT * FROM users WHERE email = '" . $user_email . "'";
// Result: SELECT * FROM users WHERE email = '' OR '1'='1'
//                                                    ↑
//                                    This is TRUE for all rows!
// Database returns: ALL users (security breach!)
```

**Why it's vulnerable:**

- User input is treated as **SQL code**
- Special characters like `'` close the string and create new SQL
- Attacker can change the query's logic

### ✅ SAFE: Parameterized Queries

```php
// User input from form
$user_email = $_POST['email'];  // e.g., "john@example.com"

// GOOD: Using placeholders
$stmt = $conn->prepare("SELECT * FROM users WHERE email = ?");
//                                                          ↑
//                                    Placeholder (not code)
$stmt->bind_param("s", $user_email);  // Attach the data
$stmt->execute();
```

**Same Attack Attempt:**

```php
$user_email = "' OR '1'='1";  // Attacker enters this
$stmt = $conn->prepare("SELECT * FROM users WHERE email = ?");
$stmt->bind_param("s", $user_email);
$stmt->execute();

// What database does:
// Step 1: Query structure already compiled: WHERE email = [placeholder]
// Step 2: Insert user data: WHERE email = [' OR '1'='1']
// Step 3: Search for email literally equal to: ' OR '1'='1'
// Database returns: No matches (correctly)
// Attacker fails - cannot inject SQL!
```

**Why it's safe:**

- Query structure is **compiled first**
- User data comes **after** compilation
- Special characters are **treated as literal text**
- **Cannot** change query logic

---

## 🎯 Real-World Example from Your Code

### From `process_registration.php`:

**Step 1: Prepare**

```php
$stmt = $conn->prepare("INSERT INTO users1
    (first_name, middle_name, last_name, email, birthday, age, contact, password)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
```

**Step 2: Bind Parameters**

```php
$stmt->bind_param(
    "sssssssi",  // 7 strings + 1 integer
    $form_data['first_name'],      // ? #1 - string
    $form_data['middle_name'],     // ? #2 - string
    $form_data['last_name'],       // ? #3 - string
    $form_data['email'],           // ? #4 - string
    $form_data['birthday'],        // ? #5 - string
    $form_data['age'],             // ? #6 - integer
    $form_data['contact'],         // ? #7 - string
    $hashed_password               // ? #8 - string
);
```

**Step 3: Execute**

```php
if (!$stmt->execute()) {
    throw new Exception("Database error");
}
```

**Data Flow:**

```
User enters form:
    First Name: John
    Email: john@example.com
    Age: 25
                        ↓
    Validation: Check all fields
                        ↓
    Database Query Construction:
    ┌──────────────────────────────────────────────┐
    │ Template: INSERT INTO users1 (...) VALUES    │
    │           (?, ?, ?, ?, ?, ?, ?, ?)           │
    │ Status: LOCKED - Cannot be modified          │
    └──────────────────────────────────────────────┘
                        ↓
    Parameter Binding:
    ┌──────────────────────────────────────────────┐
    │ ? #1 = "John"                                │
    │ ? #2 = ""                (middle name empty) │
    │ ? #3 = "Doe"                                 │
    │ ? #4 = "john@example.com"                    │
    │ ? #5 = "1990-01-15"                          │
    │ ? #6 = 25                                    │
    │ ? #7 = "09171234567"                         │
    │ ? #8 = "$2y$10$hash..."   (hashed password)  │
    └──────────────────────────────────────────────┘
                        ↓
    Execute:
    INSERT into database (all data treated as values)
```

---

## 🛡️ Type Specifiers

MySQL MySQLi extension uses type specifiers:

| Specifier | Type         | Example            | Max Value            |
| --------- | ------------ | ------------------ | -------------------- |
| `s`       | String       | Email, names, text | 65,535 chars         |
| `i`       | Integer      | Age, count, ID     | ±2,147,483,647       |
| `d`       | Double/Float | Money, decimals    | Depends on precision |
| `b`       | Blob         | Binary data        | 65,535 bytes         |

**Your Registration Example:**

```php
"ssssssssi"
 ↑↑↑↑↑↑↑↑↑
 ||||||||└─ #8: password - string (s)
 |||||||└── #7: contact - string (s)
 ||||||└─── #6: age - integer (i)  ← Different type!
 |||||└──── #5: birthday - string (s)
 ||||└───── #4: email - string (s)
 |||└────── #3: last_name - string (s)
 ||└─────── #2: middle_name - string (s)
 |└──────── #1: first_name - string (s)
```

---

## 🔍 Detecting Vulnerable Code (What NOT to Do)

### ❌ Vulnerable Pattern 1: String Concatenation

```php
// BAD - Direct concatenation
$email = $_POST['email'];
$query = "SELECT * FROM users WHERE email = '" . $email . "'";
$result = $conn->query($query);  // ❌ VULNERABLE!
```

### ❌ Vulnerable Pattern 2: String Interpolation

```php
// BAD - Using variables inside strings
$email = $_POST['email'];
$query = "SELECT * FROM users WHERE email = '$email'";
//                                          ↑
//                                   User data directly in query
$result = $conn->query($query);  // ❌ VULNERABLE!
```

### ❌ Vulnerable Pattern 3: Missing Prepared Statements

```php
// BAD - No prepared statement
$id = $_POST['id'];
$query = "DELETE FROM orders WHERE id = " . $id;
$result = $conn->query($query);  // ❌ VULNERABLE!
```

### ✅ Correct Pattern: Always Use Parameterized Queries

```php
// GOOD - Prepared statement with placeholders
$email = $_POST['email'];
$stmt = $conn->prepare("SELECT * FROM users WHERE email = ?");
$stmt->bind_param("s", $email);
$stmt->execute();
$result = $stmt->get_result();  // ✅ SAFE!
```

---

## 📊 Attack Examples That Fail With Parameterized Queries

### Attack 1: Comment-Based Injection

```
Attacker enters: admin'--
Vulnerable code:
  SELECT * FROM users WHERE email = 'admin'--'
  ❌ Returns all records (-- comments out rest)

Parameterized code:
  WHERE email = 'admin'--'
  ✅ Returns no records (looks for literal email "admin'--")
```

### Attack 2: OR-Based Injection

```
Attacker enters: ' OR '1'='1
Vulnerable code:
  SELECT * FROM users WHERE email = '' OR '1'='1'
  ❌ Returns all records (1=1 is always true)

Parameterized code:
  WHERE email = ' OR '1'='1'
  ✅ Returns no records (looks for literal email with quotes and condition)
```

### Attack 3: UNION-Based Injection

```
Attacker enters: ' UNION SELECT password FROM users--
Vulnerable code:
  SELECT * FROM users WHERE email = '' UNION SELECT password FROM users--'
  ❌ Returns passwords from users table

Parameterized code:
  WHERE email = ' UNION SELECT password FROM users--'
  ✅ Returns no records (looks for that literal email string)
```

### Attack 4: Stacked Queries

```
Attacker enters: '; DROP TABLE users;--
Vulnerable code:
  SELECT * FROM users WHERE email = ''; DROP TABLE users;--'
  ❌ Could delete the entire users table!

Parameterized code:
  WHERE email = ''; DROP TABLE users;--'
  ✅ Returns no records (extra SQL ignored, treated as data)
```

---

## 🔐 Security Benefits Summary

| Benefit                              | Explanation                                   |
| ------------------------------------ | --------------------------------------------- |
| **Separation of Structure and Data** | Query logic cannot be modified by data        |
| **Type Checking**                    | Only specified data types accepted            |
| **Implicit Escaping**                | Special characters handled automatically      |
| **Pre-compilation**                  | Query plan created before data insertion      |
| **Consistent Protection**            | Works the same regardless of input            |
| **Performance**                      | Query can be reused with different parameters |
| **Readability**                      | Clear which parts are code vs. data           |

---

## 📝 Best Practices for Your Registration

### ✅ DO:

- Always use `prepare()` for all dynamic queries
- Use `bind_param()` with correct type specifiers
- Store validated data in variables
- Check query execution results
- Log SQL errors securely

### ❌ DON'T:

- Ever concatenate user input into SQL strings
- Use `query()` with untrusted data
- Mix parameterized and non-parameterized queries
- Trust user input for column/table names (use whitelists)
- Disable prepared statements for "convenience"

---

## 🎓 Final Checklist

- [ ] All `SELECT`, `INSERT`, `UPDATE`, `DELETE` queries use `prepare()`?
- [ ] All parameters use `bind_param()` with correct types?
- [ ] Type specifiers match variable types (`s`=string, `i`=int, `d`=double)?
- [ ] Never directly concatenate user input into SQL strings?
- [ ] Exception handling logs full errors but shows generic messages to users?
- [ ] All security-sensitive operations logged with IP and timestamp?

---

## 🔗 Related Security Measures in Your Code

1. **Input Validation** (`security_validation.php`) - Validates data BEFORE using in queries
2. **Output Escaping** - HTML escaping prevents XSS after data retrieval
3. **Session Security** - CSRF tokens prevent unauthorized queries
4. **Error Logging** - SQL errors logged without exposing to users

**Together, these create defense-in-depth:** Even if one layer is bypassed, others protect your application.

---

**Created**: November 10, 2025  
**Status**: ✅ SECURE
