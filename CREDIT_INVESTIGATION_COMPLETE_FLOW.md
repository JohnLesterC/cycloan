# ✅ Credit Investigation Flow - Complete Fix & Architecture

## Problem Summary

**Error**: "Array to string conversion" in NotificationManager.php line 368

**Root Cause**: The function signature mismatch - `createStatusNotification()` was being called with wrong parameters and format.

---

## Complete Credit Investigation Flow

### Step 1: Form Submission (JavaScript → admin1_dashboard.php)

**Location**: admin1_dashboard.php Line ~5469

**Form Data Sent**:

```javascript
{
  applicationId: 'APP-20251116-0001',  // STRING (VARCHAR)
  creditStatus: 'Pending',              // STRING (enum: Pending/Completed/Failed)
  finalAmount: 50000,                   // DECIMAL
  termLength: '12'                      // STRING (enum: 6/12/18/24/36)
}
```

---

### Step 2: PHP Processing (admin1_dashboard.php)

**Location**: Lines 2182-2545

#### 2.1 - Validate & Parse Input

```php
// Line 2187-2190
$applicationId = isset($_POST['application_id']) ? trim($_POST['application_id']) : null;  // KEEP AS STRING
$creditStatus = isset($_POST['credit_status']) ? trim($_POST['credit_status']) : null;
$finalLoanAmount = isset($_POST['final_loan_amount']) ? floatval($_POST['final_loan_amount']) : null;  // CONVERT TO FLOAT
$termLength = isset($_POST['term_length']) ? trim($_POST['term_length']) : null;  // KEEP AS STRING

// Line 2194-2196 - Validation
if (!$applicationId || !$creditStatus || !$finalLoanAmount || !$termLength) {
    throw new Exception("All fields are required.");
}
```

**Key Point**: `applicationId` and `termLength` MUST stay as strings. Don't convert to int!

---

#### 2.2 - Fetch Current Application

```php
// Line 2200-2207
$stmt = $conn->prepare("
    SELECT la.*, u.id as user_id, u.email, u.first_name, u.last_name
    FROM loan_applications la
    JOIN users1 u ON la.user_id = u.id
    WHERE la.application_id = ?
");
$stmt->bind_param("s", $applicationId);  // ✅ STRING type for application_id
$stmt->execute();
$currentApp = $stmt->get_result()->fetch_assoc();
```

---

#### 2.3 - Update Database

```php
// Line 2217-2240
$updateQuery = "
    UPDATE loan_applications
    SET credit_investigation_status = ?, final_loan_amount = ?, term_length = ?, updated_at = ?
    WHERE application_id = ?
";

$stmt = $conn->prepare($updateQuery);

// ✅ FIXED: Correct bind_param signature
// s = string, d = double, s = string, s = string, s = string
$stmt->bind_param("sdsss",
    $creditStatus,      // s = STRING (enum)
    $finalLoanAmount,   // d = DOUBLE (decimal)
    $termLength,        // s = STRING (enum) ← CRITICAL FIX
    $updatedAt,         // s = STRING (timestamp)
    $applicationId      // s = STRING (varchar) ← CRITICAL FIX
);

$stmt->execute();
```

**Why This Matters**:

- ENUM fields must use STRING type (`s`), never INTEGER (`i`)
- VARCHAR fields must use STRING type (`s`), never INTEGER (`i`)
- Mismatch causes: Type confusion → Query fails → HTML error page returned

---

#### 2.4 - Send Email Notification

```php
// Line 2244-2390
if ($creditStatus === 'Completed') {
    // Construct HTML email with approval message
    // Use PHPMailer to send to $currentApp['email']
}
elseif ($creditStatus === 'Failed') {
    // Construct HTML email with rejection message
    // Use PHPMailer to send to $currentApp['email']
}
```

**Email includes**:

- ✅ Application ID
- ✅ Applicant name
- ✅ Status update
- ✅ Final loan amount
- ✅ Terms and conditions

---

#### 2.5 - Create In-App Notification

```php
// Line 2537-2546
$notificationMessage = $creditStatus === 'Completed'
    ? "Credit investigation completed. Final loan amount: ₱" . number_format($finalLoanAmount, 2)
    : "Credit investigation status: $creditStatus. Please contact support.";

// ✅ FIXED: Correct function signature
// Parameters: ($conn, $user_id, $message, $priority)
createStatusNotification(
    $conn,                      // Database connection
    $currentApp['user_id'],     // User receiving notification
    $notificationMessage,       // Message (STRING only, not array)
    'normal'                    // Priority level
);
```

**What This Does**:

1. Inserts notification into `notifications` table
2. Stores message for user to see in notification center
3. User receives in-app alert

---

### Step 3: Notification Manager Processing

**Location**: NotificationManager.php Line 701-712

#### 3.1 - Function Signature

```php
function createStatusNotification($conn, $user_id, $status_message, $priority = 'normal')
{
    // Create NotificationManager instance
    $manager = new NotificationManager($conn);

    // Call createNotification with proper parameters
    return $manager->createNotification(
        $user_id,           // INT - user ID
        'status',           // STRING - notification type
        'Status Update',    // STRING - title
        $status_message,    // STRING - message body ← MUST BE STRING
        $priority           // STRING - priority level
    );
}
```

**Critical**: The `$status_message` parameter MUST be a STRING. Passing an array causes "Array to string conversion" error.

---

#### 3.2 - Insert Notification

```php
public function createNotification($user_id, $type_name, $title, $message, $priority = 'normal')
{
    // Get type_id for notification type
    $type_stmt = $this->conn->prepare("SELECT type_id FROM notification_types WHERE type_name = ?");
    $type_stmt->bind_param("s", $type_name);
    $type_stmt->execute();

    // Insert notification record
    $insert_stmt = $this->conn->prepare("
        INSERT INTO notifications (user_id, type_id, title, message, priority, created_at)
        VALUES (?, ?, ?, ?, ?, NOW())
    ");
    $insert_stmt->bind_param("iisss", $user_id, $type_id, $title, $message, $priority);
    $insert_stmt->execute();
}
```

---

### Step 4: Response to Frontend

```php
// Line 2549-2552
ob_clean();
header('Content-Type: application/json; charset=utf-8');
echo json_encode(['success' => true, 'message' => 'Credit investigation submitted successfully.']);
exit;
```

**Browser Receives**:

```json
{
  "success": true,
  "message": "Credit investigation submitted successfully."
}
```

---

## Database Schema Reference

### loan_applications Table

```sql
CREATE TABLE `loan_applications` (
  `application_id` varchar(20) NOT NULL,              -- ← PRIMARY KEY, STRING
  `user_id` int(11) NOT NULL,                         -- ← Foreign key to users
  `credit_investigation_status` enum('Pending','Completed','Failed'),  -- ← STRING/ENUM
  `final_loan_amount` decimal(15,2) DEFAULT NULL,    -- ← DECIMAL, not INT
  `term_length` enum('6','12','18','24','36'),        -- ← STRING/ENUM, not INT
  `created_at` timestamp DEFAULT current_timestamp(),
  `updated_at` timestamp DEFAULT current_timestamp() ON UPDATE current_timestamp()
);
```

### notifications Table

```sql
CREATE TABLE `notifications` (
  `notification_id` int(11) PRIMARY KEY AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `type_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `message` text NOT NULL,                           -- ← MUST BE STRING/TEXT
  `priority` enum('low','normal','high'),
  `read_at` timestamp NULL,
  `created_at` timestamp DEFAULT current_timestamp()
);
```

---

## Complete Parameter Binding Reference

### ✅ CORRECT Calls in admin1_dashboard.php

**Line 2085** - Fetch Application:

```php
$stmt->bind_param("s", $applicationId);  // application_id is VARCHAR
```

**Line 2233** - Update Application:

```php
$stmt->bind_param("sdsss",           // Type signature for 5 parameters:
    $creditStatus,                  // s = STRING (enum: Completed/Failed/Pending)
    $finalLoanAmount,              // d = DOUBLE (decimal 15,2)
    $termLength,                   // s = STRING (enum: 6/12/18/24/36)
    $updatedAt,                    // s = STRING (timestamp)
    $applicationId                 // s = STRING (varchar 20)
);
```

**Line 2540** - Create Notification:

```php
createStatusNotification(
    $conn,                         // Database connection object
    $currentApp['user_id'],        // INT - user ID
    $notificationMessage,          // STRING - message text (NOT array!)
    'normal'                       // STRING - priority
);
```

---

## Error Prevention Checklist

✅ **Always validate input types before using**

- `$applicationId` should be string, not int
- `$termLength` should be string (enum value), not int
- `$finalLoanAmount` should be float/decimal, not string

✅ **Match bind_param signature to database schema**

- VARCHAR fields → use `s`
- ENUM fields → use `s` (enums are strings)
- INT fields → use `i`
- DECIMAL/FLOAT fields → use `d`

✅ **Pass simple values to notification functions**

- Message parameter must be STRING: `"message text"`
- NOT array: `['message' => 'text']`

✅ **Always include $conn as first parameter**

```php
createStatusNotification($conn, ...)  // ✅ CORRECT
createStatusNotification($user_id, ...)  // ❌ WRONG
```

---

## Testing the Fix

1. **Upload** the fixed `admin1_dashboard.php`
2. **Go to** Admin Dashboard → View Loan Applicants
3. **Click** "View Details" on an application
4. **Fill** Credit Investigation form:
   - Credit Status: "Completed" ✅
   - Final Amount: "50000" ✅
   - Term Length: "12" ✅
5. **Expected Result**: JSON success response, NO HTML errors

---

## Summary of All Fixes

| Issue              | Location  | Fix                          | Status |
| ------------------ | --------- | ---------------------------- | ------ |
| ArgumentCountError | Line 2084 | Fixed function signature     | ✅     |
| Array to string    | Line 2117 | Pass STRING not array        | ✅     |
| Array to string    | Line 2540 | Pass STRING not array        | ✅     |
| Type mismatch      | Line 2207 | Use `s` for applicationId    | ✅     |
| Type mismatch      | Line 2233 | Use `sdsss` for 5 parameters | ✅     |
| Validation         | Line 2194 | Check applicationId not null | ✅     |
