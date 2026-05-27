# Philippine Time Timestamp - Copy-Paste Code Pattern

## Standard Pattern (Use this everywhere!)

### For INSERT operations:
```php
// Generate timestamp
$phpTimeZone = new DateTimeZone('Asia/Manila');
$now = new DateTime('now', $phpTimeZone);
$createdAt = $now->format('Y-m-d H:i:s');

// Method 1: Prepared statement (RECOMMENDED)
$stmt = $conn->prepare("INSERT INTO table (column1, created_at) VALUES (?, ?)");
$stmt->bind_param("ss", $value, $createdAt);
$stmt->execute();

// Method 2: Simple query (if no other parameters)
$query = "INSERT INTO table (column1, created_at) VALUES ('$value', '$createdAt')";
mysqli_query($conn, $query);

// Method 3: Multiple parameters with prepared statement
$stmt = $conn->prepare("INSERT INTO remarks (app_id, text, created_at) VALUES (?, ?, ?)");
$stmt->bind_param("iss", $applicationId, $remarks, $createdAt);
$stmt->execute();
```

### For UPDATE operations:
```php
// Generate timestamp
$phpTimeZone = new DateTimeZone('Asia/Manila');
$now = new DateTime('now', $phpTimeZone);
$updatedAt = $now->format('Y-m-d H:i:s');

// Method 1: Prepared statement with WHERE clause
$stmt = $conn->prepare("UPDATE table SET status = ?, updated_at = ? WHERE id = ?");
$stmt->bind_param("ssi", $status, $updatedAt, $id);
$stmt->execute();

// Method 2: Simple query
$query = "UPDATE table SET status = '$status', updated_at = '$updatedAt' WHERE id = $id";
mysqli_query($conn, $query);
```

### For Complex Updates with Multiple Fields:
```php
// Generate timestamp once at the beginning
$phpTimeZone = new DateTimeZone('Asia/Manila');
$now = new DateTime('now', $phpTimeZone);
$timestamp = $now->format('Y-m-d H:i:s');

// Build dynamic UPDATE query
$updateFields = [];
$paramTypes = '';
$paramValues = [];

if ($status !== null) {
    $updateFields[] = "status = ?";
    $paramTypes .= 's';
    $paramValues[] = $status;
}

if ($amount !== null) {
    $updateFields[] = "amount = ?";
    $paramTypes .= 'd';
    $paramValues[] = $amount;
}

// Always add timestamp at the end
$updateFields[] = "updated_at = ?";
$paramTypes .= 's';
$paramValues[] = $timestamp;

// Add WHERE clause
$paramTypes .= 'i';
$paramValues[] = $id;

// Execute
$sql = "UPDATE table SET " . implode(', ', $updateFields) . " WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param($paramTypes, ...$paramValues);
$stmt->execute();
```

---

## Real Examples from CYCLOAN

### Example 1: Loan Registration (loan_register_process.php)
```php
// Generate Philippine Time timestamp
$phpTimeZone = new DateTimeZone('Asia/Manila');
$now = new DateTime('now', $phpTimeZone);
$createdAt = $now->format('Y-m-d H:i:s');

// Use in INSERT query
$query = "INSERT INTO loan_applications (
    application_id, loan_id, user_id, loan_type_id, loan_status, 
    amount_applied, term_length, repayment_frequency,
    purpose, others_text, project_type, project_description, created_at
) VALUES (
    '$applicationId', '$loanId', $userId, $loanTypeId, '$loanStatus', 
    $amountApplied, '$termLength', '$repaymentFrequency',
    '$purpose', '$othersText', '$projectType', '$projectDescription', '$createdAt'
)";
```

### Example 2: Admin Remarks (admin1_dashboard.php)
```php
// Generate timestamp
$phpTimeZone = new DateTimeZone('Asia/Manila');
$now = new DateTime('now', $phpTimeZone);
$createdAt = $now->format('Y-m-d H:i:s');

// Use in prepared statement
$stmt = $conn->prepare("
    INSERT INTO remarks (application_id, remarks, created_at, admin_name)
    VALUES (?, ?, ?, ?)
");
$stmt->bind_param("isss", $applicationId, $remarks, $createdAt, $adminName);
$stmt->execute();
```

### Example 3: Payment Update (pay_balance.php)
```php
// Generate timestamp
$phpTimeZone = new DateTimeZone('Asia/Manila');
$now = new DateTime('now', $phpTimeZone);
$updatedAt = $now->format('Y-m-d H:i:s');

// Use in UPDATE query
$stmt = mysqli_prepare($conn, "
    UPDATE payment_schedules
    SET amount_paid = ?, interest_paid = ?, status = ?, updated_at = ?
    WHERE payment_id = ?
");
mysqli_stmt_bind_param($stmt, "ddssi", $amountPaid, $interestPaid, $status, $updatedAt, $paymentId);
mysqli_stmt_execute($stmt);
```

### Example 4: Loan Closure (pay_balance.php)
```php
// Generate timestamp
$phpTimeZone = new DateTimeZone('Asia/Manila');
$now = new DateTime('now', $phpTimeZone);
$closedAt = $now->format('Y-m-d H:i:s');

// Use in UPDATE query
$stmt = mysqli_prepare($conn, "UPDATE loans SET status = 'closed', updated_at = ? WHERE loan_id = ?");
mysqli_stmt_bind_param($stmt, "si", $closedAt, $loanId);
mysqli_stmt_execute($stmt);
```

---

## Variable Naming Convention

Use descriptive names for clarity:

| Use Case | Variable Name | Example |
|----------|--------------|---------|
| Creation time | `$createdAt` | `2025-11-02 14:04:03` |
| Update time | `$updatedAt` | `2025-11-02 14:04:03` |
| Closed/Completed time | `$closedAt` | `2025-11-02 14:04:03` |
| Document status change | `$statusUpdatedAt` | `2025-11-02 14:04:03` |
| Generic current time | `$now` | Datetime object |

---

## Testing Your Code

After adding timestamp code, test with:

```sql
-- Check that new timestamp is in Philippine Time
SELECT id, created_at FROM your_table 
ORDER BY created_at DESC LIMIT 1;

-- Expected: Shows current time like 14:04:03 (not 06:04:03)
```

---

## Common Mistakes to Avoid

### ❌ Wrong:
```php
$query = "INSERT INTO table SET created_at = NOW()";  // Uses server timezone (UTC)

date_default_timezone_set('Asia/Manila');             // Not used for database
$time = date('Y-m-d H:i:s');                          // Uses PHP timezone, not DB
$query = "INSERT INTO table SET created_at = '$time'";

$query = "INSERT INTO table SET created_at = '" . time() . "'";  // Wrong format
```

### ✅ Correct:
```php
$phpTimeZone = new DateTimeZone('Asia/Manila');
$now = new DateTime('now', $phpTimeZone);
$timestamp = $now->format('Y-m-d H:i:s');
$stmt->bind_param("s", $timestamp);

// Result: 2025-11-02 14:04:03 (Philippine Time)
```

---

## Checklist for Code Review

When reviewing code for timestamps, check:

- [ ] Uses `DateTimeZone('Asia/Manila')`?
- [ ] Uses `DateTime('now', $phpTimeZone)`?
- [ ] Formatted as `Y-m-d H:i:s`?
- [ ] Passed as parameter (not concatenated)?
- [ ] Uses prepared statements?
- [ ] Variable named descriptively (`$createdAt`, `$updatedAt`, etc)?
- [ ] No `NOW()` function used?
- [ ] No bare `date()` calls?

---

## One-Liner Timestamp Generation

If you want a quick one-liner (not recommended but works):

```php
$timestamp = (new DateTime('now', new DateTimeZone('Asia/Manila')))->format('Y-m-d H:i:s');
```

**But always prefer the readable multi-line version:**
```php
$phpTimeZone = new DateTimeZone('Asia/Manila');
$now = new DateTime('now', $phpTimeZone);
$timestamp = $now->format('Y-m-d H:i:s');
```

---

## Complete Copy-Paste Template

Use this as a starting template for any new timestamp operation:

```php
<?php
// Include database connection
require 'CYCLOAN_db.php';

try {
    // Generate Philippine Time timestamp
    $phpTimeZone = new DateTimeZone('Asia/Manila');
    $now = new DateTime('now', $phpTimeZone);
    $createdAt = $now->format('Y-m-d H:i:s');
    
    // Prepare statement with timestamp
    $stmt = $conn->prepare("
        INSERT INTO your_table 
        (column1, column2, created_at) 
        VALUES (?, ?, ?)
    ");
    
    if (!$stmt) {
        throw new Exception("Prepare failed: " . $conn->error);
    }
    
    // Bind parameters
    $stmt->bind_param("sss", $column1, $column2, $createdAt);
    
    // Execute
    if (!$stmt->execute()) {
        throw new Exception("Execute failed: " . $stmt->error);
    }
    
    // Success
    echo "Record inserted with timestamp: $createdAt";
    $stmt->close();
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
} finally {
    $conn->close();
}
?>
```

---

## Remember

✅ **Every timestamp** → Asia/Manila timezone  
✅ **Every update/insert** → Use this pattern  
✅ **Every prepared statement** → Bind the timestamp parameter  
✅ **Every future feature** → Follow this guide  

**Philippine Time is UTC+8** → Our timestamps will always be 8 hours ahead of UTC ✅
